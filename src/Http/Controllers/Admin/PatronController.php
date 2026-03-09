<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Jobs\LookupPatronInPolaris;
use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\SfpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatronController extends Controller
{
    // -------------------------------------------------------------------------
    // INDEX — default shows only flagged patrons
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $showAll = $request->boolean('show_all');
        $search  = $request->input('search');

        $suspectedDuplicateIds = $this->getSuspectedDuplicateIds();

        $query = Patron::withCount('requests')
            ->orderBy('name_last')
            ->orderBy('name_first');

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('barcode', 'like', $term)
                  ->orWhere('name_last', 'like', $term)
                  ->orWhere('name_first', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        if (! $showAll) {
            $query->where(function ($q) use ($suspectedDuplicateIds) {
                $q->where('polaris_lookup_attempted', false)
                  ->orWhere(function ($inner) {
                      $inner->where('found_in_polaris', false)
                            ->where('polaris_lookup_attempted', true);
                  })
                  ->orWhere('name_first_matches', false)
                  ->orWhere('name_last_matches', false)
                  ->orWhere('phone_matches', false)
                  ->orWhere('email_matches', false)
                  ->orWhereIn('id', $suspectedDuplicateIds);
            });
        }

        $patrons = $query->paginate(30)->withQueryString();

        return view('sfp::staff.patrons.index', [
            'patrons'               => $patrons,
            'suspectedDuplicateIds' => $suspectedDuplicateIds,
            'showAll'               => $showAll,
            'filters'               => $request->only(['search', 'show_all']),
        ]);
    }

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    public function show(Patron $patron)
    {
        $patron->load(['requests.status', 'requests.materialType', 'ignoredDuplicates']);

        $suspects = $this->getSuspectsForPatron($patron);

        return view('sfp::staff.patrons.show', [
            'patron'  => $patron,
            'suspects' => $suspects,
        ]);
    }

    // -------------------------------------------------------------------------
    // EDIT
    // -------------------------------------------------------------------------

    public function edit(Patron $patron)
    {
        return view('sfp::staff.patrons.edit', [
            'patron' => $patron,
        ]);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    public function update(Request $request, Patron $patron)
    {
        $data = $request->validate([
            'barcode'    => 'required|string|max:255|unique:patrons,barcode,' . $patron->id,
            'name_first' => 'required|string|max:255',
            'name_last'  => 'required|string|max:255',
            'phone'      => 'required|string|max:50',
            'email'      => 'nullable|email|max:255',
        ]);

        $patron->update($data);

        return redirect()
            ->route('request.staff.patrons.show', $patron)
            ->with('success', 'Patron updated.');
    }

    // -------------------------------------------------------------------------
    // RETRIGGER POLARIS LOOKUP
    // -------------------------------------------------------------------------

    public function retriggerPolaris(Patron $patron)
    {
        $patron->update(['polaris_lookup_attempted' => false]);

        LookupPatronInPolaris::dispatch($patron->id)
            ->onConnection(config('sfp.queue.connection'))
            ->onQueue(config('sfp.queue.name'));

        return back()->with('success', 'Polaris lookup queued. Refresh in a moment to see updated data.');
    }

    // -------------------------------------------------------------------------
    // IGNORE DUPLICATE — marks two patrons as not duplicates of each other
    // -------------------------------------------------------------------------

    public function ignoreDuplicate(Request $request, Patron $patron)
    {
        $request->validate([
            'other_id' => 'required|integer|exists:patrons,id',
        ]);

        $other = Patron::findOrFail($request->other_id);

        // Symmetric — ignore in both directions so neither sees the other
        $patron->ignoredDuplicates()->syncWithoutDetaching([$other->id]);
        $other->ignoredDuplicates()->syncWithoutDetaching([$patron->id]);

        return back()->with('success', 'Marked as not a duplicate.');
    }

    // -------------------------------------------------------------------------
    // MERGE CONFIRM (GET — kept for manual ID entry fallback)
    // -------------------------------------------------------------------------

    public function mergeConfirm(Request $request, Patron $loser)
    {
        $request->validate([
            'target_id' => 'required|integer|exists:patrons,id',
        ]);

        $winner = Patron::findOrFail($request->target_id);

        if ($winner->id === $loser->id) {
            return back()->withErrors(['target_id' => 'Cannot merge a patron into itself.']);
        }

        $loser->load('requests.status');
        $winner->load('requests.status');

        return view('sfp::staff.patrons.merge', [
            'loser'  => $loser,
            'winner' => $winner,
        ]);
    }

    // -------------------------------------------------------------------------
    // MERGE (POST — executes the merge from the modal)
    //
    // Expects:
    //   target_id        — winner patron ID (the one to keep)
    //   polaris_patron_id — optional override (entered by staff if missing)
    //   preferred_phone  — 'submitted' | 'polaris'  for each patron
    //   preferred_email  — 'submitted' | 'polaris'  for each patron
    //
    // The "loser" {patron} in the URL is the one being deleted.
    // All requests are reassigned to the winner, then the loser is deleted.
    // -------------------------------------------------------------------------

    public function merge(Request $request, Patron $loser)
    {
        // Convert empty string to null so nullable|integer doesn't reject it
        if ($request->input('polaris_patron_id') === '') {
            $request->merge(['polaris_patron_id' => null]);
        }

        $request->validate([
            'target_id'        => 'required|integer|exists:patrons,id',
            'polaris_patron_id'=> 'nullable|integer',
            'preferred_phone'  => 'nullable|in:submitted,polaris',
            'preferred_email'  => 'nullable|in:submitted,polaris',
        ]);

        $winner = Patron::findOrFail($request->target_id);

        if ($winner->id === $loser->id) {
            return back()->withErrors(['target_id' => 'Cannot merge a patron into itself.']);
        }

        $movedCount = 0;

        DB::transaction(function () use ($loser, $winner, $request, &$movedCount) {
            // Apply any contact preference and Polaris ID override to the winner
            $winnerUpdates = [];

            if ($request->filled('preferred_phone')) {
                $winnerUpdates['preferred_phone'] = $request->preferred_phone;
            }
            if ($request->filled('preferred_email')) {
                $winnerUpdates['preferred_email'] = $request->preferred_email;
            }
            if ($request->filled('polaris_patron_id')) {
                $winnerUpdates['polaris_patron_id'] = $request->polaris_patron_id;
            }

            if ($winnerUpdates) {
                $winner->update($winnerUpdates);
            }

            // Move all of the loser's requests to the winner
            $movedCount = SfpRequest::where('patron_id', $loser->id)
                ->update(['patron_id' => $winner->id]);

            // Delete the loser
            $loser->delete();
        });

        return redirect()
            ->route('request.staff.patrons.show', $winner)
            ->with('success', "Patron #{$loser->id} merged. {$movedCount} request(s) moved to this record.");
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Compute suspected duplicate patron IDs using PHP-side grouping
     * (SQLite-safe — no REGEXP_REPLACE needed).
     */
    private function getSuspectedDuplicateIds(): array
    {
        $allPatrons = Patron::select('id', 'name_last', 'phone', 'email', 'polaris_patron_id')->get();

        // Load every ignored pair as a set of "patronA|patronB" keys (both directions)
        $ignoredPairs = DB::table('patron_ignored_duplicates')
            ->select('patron_id', 'ignored_patron_id')
            ->get()
            ->flatMap(fn ($row) => [
                min($row->patron_id, $row->ignored_patron_id) . '|' . max($row->patron_id, $row->ignored_patron_id),
            ])
            ->flip() // use as a lookup set
            ->all();

        // Returns true if the two patrons have mutually ignored each other
        $isIgnored = fn (int $a, int $b): bool =>
            isset($ignoredPairs[min($a, $b) . '|' . max($a, $b)]);

        // Given a group of patrons, return only those that still have at least
        // one non-ignored partner within the group.
        $filterGroup = function (\Illuminate\Support\Collection $group) use ($isIgnored): \Illuminate\Support\Collection {
            return $group->filter(function ($patron) use ($group, $isIgnored) {
                return $group->contains(function ($other) use ($patron, $isIgnored) {
                    return $other->id !== $patron->id && ! $isIgnored($patron->id, $other->id);
                });
            });
        };

        $ids = collect();

        // 1. Same normalized phone + same last name
        $ids = $ids->merge(
            $allPatrons
                ->filter(fn ($p) => preg_replace('/\D/', '', $p->phone ?? '') !== '')
                ->groupBy(fn ($p) =>
                    strtolower(trim($p->name_last)) . '|' . preg_replace('/\D/', '', $p->phone)
                )
                ->filter(fn ($group) => $group->count() > 1)
                ->map($filterGroup)
                ->filter(fn ($group) => $group->count() > 1)
                ->flatten()
                ->pluck('id')
        );

        // 2. Same non-empty email (case-insensitive)
        $ids = $ids->merge(
            $allPatrons
                ->filter(fn ($p) => ! empty($p->email))
                ->groupBy(fn ($p) => strtolower(trim($p->email)))
                ->filter(fn ($group) => $group->count() > 1)
                ->map($filterGroup)
                ->filter(fn ($group) => $group->count() > 1)
                ->flatten()
                ->pluck('id')
        );

        // 3. Same non-null Polaris patron ID
        $ids = $ids->merge(
            $allPatrons
                ->filter(fn ($p) => ! empty($p->polaris_patron_id))
                ->groupBy('polaris_patron_id')
                ->filter(fn ($group) => $group->count() > 1)
                ->map($filterGroup)
                ->filter(fn ($group) => $group->count() > 1)
                ->flatten()
                ->pluck('id')
        );

        return $ids->unique()->values()->all();
    }

    /**
     * Find patrons suspected to be duplicates of the given patron,
     * excluding any pairs the staff have marked as ignored.
     */
    private function getSuspectsForPatron(Patron $patron): \Illuminate\Support\Collection
    {
        $ignoredIds = $patron->ignoredDuplicates->pluck('id')->all();

        $suspects = collect();

        $normalizedPhone = preg_replace('/\D/', '', $patron->phone ?? '');

        if ($normalizedPhone) {
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->whereNotIn('id', $ignoredIds)
                    ->whereRaw('LOWER(name_last) = ?', [strtolower($patron->name_last)])
                    ->get()
                    ->filter(fn ($p) => preg_replace('/\D/', '', $p->phone ?? '') === $normalizedPhone)
            );
        }

        if (! empty($patron->email)) {
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->whereNotIn('id', $ignoredIds)
                    ->whereRaw('LOWER(email) = ?', [strtolower($patron->email)])
                    ->get()
            );
        }

        if (! empty($patron->polaris_patron_id)) {
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->whereNotIn('id', $ignoredIds)
                    ->where('polaris_patron_id', $patron->polaris_patron_id)
                    ->get()
            );
        }

        return $suspects->unique('id')->values();
    }
}
