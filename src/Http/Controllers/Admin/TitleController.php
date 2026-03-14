<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Material;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\PatronRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TitleController extends Controller
{
    // -------------------------------------------------------------------------
    // INDEX — materials grouped, with request counts and bulk status action
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $staffUser = $this->currentStaffUser($request);
        $search  = $request->input('search');
        $source  = $request->input('source');

        $query = Material::visibleTo($staffUser)
            ->withCount('requests')
            ->with(['materialTypeOption', 'requests.status']);

        // Sorting
        $sortable = ['title', 'author', 'requests_count'];
        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('requests_count')->orderBy('title');
        }

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('author', 'like', $term)
                  ->orWhere('isbn', 'like', $term)
                  ->orWhere('isbn13', 'like', $term);
            });
        }

        if ($source) {
            $query->where('source', $source);
        }

        $materials = $query->paginate(30)->withQueryString();

        // Collect duplicate candidates within the user's visible set.
        $allTitles = Material::visibleTo($staffUser)->select('id', 'title', 'author')->get();
        $duplicateMaterialIds = $allTitles
            ->groupBy(fn ($m) => strtolower(trim($m->title)) . '|' . strtolower(trim($m->author)))
            ->filter(fn ($group) => $group->count() > 1)
            ->flatten()
            ->pluck('id')
            ->all();

        // Unmatched requests within the user's visible scope.
        $unmatchedCount = PatronRequest::visibleTo($staffUser)->whereNull('material_id')->count();

        return view('requests::staff.titles.index', [
            'materials'            => $materials,
            'duplicateMaterialIds' => $duplicateMaterialIds,
            'unmatchedCount'       => $unmatchedCount,
            'statuses'             => RequestStatus::active()->orderBy('sort_order')->get(),
            'filters'              => $request->only(['search', 'source']),
        ]);
    }

    // -------------------------------------------------------------------------
    // SHOW — one material: its enriched data, all requests, bulk status
    // -------------------------------------------------------------------------

    public function show(Request $request, Material $material)
    {
        $staffUser = $this->currentStaffUser($request);

        // 403 if this material is outside the user's visible scope.
        abort_unless(
            Material::visibleTo($staffUser)->where('id', $material->id)->exists(),
            403
        );

        $material->load([
            'materialTypeOption',
            'requests.patron',
            'requests.status',
            'requests.fieldValues.field',
        ]);

        // Duplicate candidates also scoped to what the user can see.
        $duplicates = Material::visibleTo($staffUser)
            ->where('id', '!=', $material->id)
            ->whereRaw('LOWER(title) = ?', [strtolower(trim($material->title))])
            ->whereRaw('LOWER(author) = ?', [strtolower(trim($material->author))])
            ->withCount('requests')
            ->get();

        return view('requests::staff.titles.show', [
            'material'   => $material,
            'duplicates' => $duplicates,
            'statuses'   => RequestStatus::active()->orderBy('sort_order')->get(),
        ]);
    }

    // -------------------------------------------------------------------------
    // BULK STATUS — update all requests for a material at once
    // -------------------------------------------------------------------------

    public function bulkStatus(Request $request, Material $material)
    {
        $staffUser = $this->currentStaffUser($request);

        abort_unless(
            Material::visibleTo($staffUser)->where('id', $material->id)->exists(),
            403
        );

        $request->validate([
            'status_id' => 'required|exists:request_statuses,id',
            'note'      => 'nullable|string|max:2000',
        ]);

        $userId = $staffUser?->id;

        $material->requests()->each(function ($req) use ($request, $userId) {
            $req->transitionStatus($request->status_id, $userId, $request->note);
        });

        $count = $material->requests()->count();

        return back()->with('success', "Status updated for {$count} request(s).");
    }

    // -------------------------------------------------------------------------
    // MERGE — reassign all requests from loser material to winner, delete loser
    // -------------------------------------------------------------------------

    public function merge(Request $request, Material $loser)
    {
        $staffUser = $this->currentStaffUser($request);

        abort_unless(
            Material::visibleTo($staffUser)->where('id', $loser->id)->exists(),
            403
        );

        $request->validate([
            'target_id' => 'required|integer|exists:materials,id',
        ]);

        $winner = Material::visibleTo($staffUser)->findOrFail($request->target_id);

        if ($winner->id === $loser->id) {
            return back()->withErrors(['target_id' => 'Cannot merge a title into itself.']);
        }

        DB::transaction(function () use ($loser, $winner) {
            PatronRequest::where('material_id', $loser->id)
                ->update(['material_id' => $winner->id]);

            $loser->delete();
        });

        return redirect()
            ->route('request.staff.titles.show', $winner)
            ->with('success', "Merged into \"{$winner->title}\". Duplicate record deleted.");
    }
}
