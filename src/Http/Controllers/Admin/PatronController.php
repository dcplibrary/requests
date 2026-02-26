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
                // Never looked up
                $q->where('polaris_lookup_attempted', false)
                  // Looked up but not found
                  ->orWhere(function ($inner) {
                      $inner->where('found_in_polaris', false)
                            ->where('polaris_lookup_attempted', true);
                  })
                  // Any submitted/Polaris field mismatch
                  ->orWhere('name_first_matches', false)
                  ->orWhere('name_last_matches', false)
                  ->orWhere('phone_matches', false)
                  ->orWhere('email_matches', false)
                  // Suspected duplicates
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
        $patron->load(['requests.status', 'requests.materialType']);

        $suspects = $this->getSuspectsForPatron($patron);

        return view('sfp::staff.patrons.show', [
            'patron'  => $patron,
            'suspects'=> $suspects,
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
            ->route('sfp.staff.patrons.show', $patron)
            ->with('success', 'Patron updated.');
    }

    // -------------------------------------------------------------------------
    // RETRIGGER POLARIS LOOKUP
    // -------------------------------------------------------------------------

    public function retriggerPolaris(Patron $patron)
    {
        $patron->update(['polaris_lookup_attempted' => false]);

        LookupPatronInPolaris::dispatch($patron->id);

        return back()->with('success', 'Polaris lookup queued. Refresh in a moment to see updated data.');
    }

    // -------------------------------------------------------------------------
    // MERGE CONFIRM (GET — shows side-by-side comparison before the POST)
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
    // MERGE (POST — executes the merge)
    // -------------------------------------------------------------------------

    public function merge(Request $request, Patron $loser)
    {
        $request->validate([
            'target_id' => 'required|integer|exists:patrons,id',
        ]);

        $winner = Patron::findOrFail($request->target_id);

        if ($winner->id === $loser->id) {
            return back()->withErrors(['target_id' => 'Cannot merge a patron into itself.']);
        }

        $movedCount = 0;

        DB::transaction(function () use ($loser, $winner, &$movedCount) {
            $movedCount = SfpRequest::where('patron_id', $loser->id)
                ->update(['patron_id' => $winner->id]);

            $loser->delete();
        });

        return redirect()
            ->route('sfp.staff.patrons.show', $winner)
            ->with('success', "Patron #{$loser->id} merged. {$movedCount} request(s) moved to this record.");
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Compute suspected duplicate patron IDs using PHP-side grouping
     * (SQLite-safe — no REGEXP_REPLACE needed).
     *
     * Three signals: same last name + phone digits, same email, same Polaris patron ID.
     */
    private function getSuspectedDuplicateIds(): array
    {
        $allPatrons = Patron::select('id', 'name_last', 'phone', 'email', 'polaris_patron_id')->get();

        $ids = collect();

        // 1. Same normalized phone digits + same last name
        $ids = $ids->merge(
            $allPatrons
                ->filter(fn ($p) => preg_replace('/\D/', '', $p->phone ?? '') !== '')
                ->groupBy(fn ($p) =>
                    strtolower(trim($p->name_last)) . '|' . preg_replace('/\D/', '', $p->phone)
                )
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
                ->flatten()
                ->pluck('id')
        );

        // 3. Same non-null Polaris patron ID
        $ids = $ids->merge(
            $allPatrons
                ->filter(fn ($p) => ! empty($p->polaris_patron_id))
                ->groupBy('polaris_patron_id')
                ->filter(fn ($group) => $group->count() > 1)
                ->flatten()
                ->pluck('id')
        );

        return $ids->unique()->values()->all();
    }

    /**
     * Find patrons suspected to be duplicates of the given patron.
     * Used on the show page to render the warning panel.
     */
    private function getSuspectsForPatron(Patron $patron): \Illuminate\Support\Collection
    {
        $suspects = collect();

        $normalizedPhone = preg_replace('/\D/', '', $patron->phone ?? '');

        if ($normalizedPhone) {
            // Other patrons with same last name and phone digits
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->whereRaw('LOWER(name_last) = ?', [strtolower($patron->name_last)])
                    ->get()
                    ->filter(fn ($p) => preg_replace('/\D/', '', $p->phone ?? '') === $normalizedPhone)
            );
        }

        if (! empty($patron->email)) {
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->whereRaw('LOWER(email) = ?', [strtolower($patron->email)])
                    ->get()
            );
        }

        if (! empty($patron->polaris_patron_id)) {
            $suspects = $suspects->merge(
                Patron::where('id', '!=', $patron->id)
                    ->where('polaris_patron_id', $patron->polaris_patron_id)
                    ->get()
            );
        }

        return $suspects->unique('id')->values();
    }
}
