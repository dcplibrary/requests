<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Material;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SfpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TitleController extends Controller
{
    // -------------------------------------------------------------------------
    // INDEX — materials grouped, with request counts and bulk status action
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $search = $request->input('search');
        $source = $request->input('source');

        $query = Material::withCount('requests')
            ->with(['materialType', 'requests.status'])
            ->orderByDesc('requests_count')
            ->orderBy('title');

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

        // Collect duplicate candidates: materials that share the same
        // normalized title + author (case-insensitive, PHP-side).
        $allTitles = Material::select('id', 'title', 'author')->get();
        $duplicateMaterialIds = $allTitles
            ->groupBy(fn ($m) => strtolower(trim($m->title)) . '|' . strtolower(trim($m->author)))
            ->filter(fn ($group) => $group->count() > 1)
            ->flatten()
            ->pluck('id')
            ->all();

        // Unmatched requests: submitted but not yet linked to a Material
        $unmatchedCount = SfpRequest::whereNull('material_id')->count();

        return view('sfp::staff.titles.index', [
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

    public function show(Material $material)
    {
        $material->load([
            'materialType',
            'requests.patron',
            'requests.status',
            'requests.audience',
        ]);

        // Other materials with the same normalized title+author (duplicate candidates)
        $duplicates = Material::where('id', '!=', $material->id)
            ->whereRaw('LOWER(title) = ?', [strtolower(trim($material->title))])
            ->whereRaw('LOWER(author) = ?', [strtolower(trim($material->author))])
            ->withCount('requests')
            ->get();

        return view('sfp::staff.titles.show', [
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
        $request->validate([
            'status_id' => 'required|exists:request_statuses,id',
            'note'      => 'nullable|string|max:2000',
        ]);

        $userId = $this->currentSfpUser($request)?->id;

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
        $request->validate([
            'target_id' => 'required|integer|exists:materials,id',
        ]);

        $winner = Material::findOrFail($request->target_id);

        if ($winner->id === $loser->id) {
            return back()->withErrors(['target_id' => 'Cannot merge a title into itself.']);
        }

        DB::transaction(function () use ($loser, $winner) {
            SfpRequest::where('material_id', $loser->id)
                ->update(['material_id' => $winner->id]);

            $loser->delete();
        });

        return redirect()
            ->route('sfp.staff.titles.show', $winner)
            ->with('success', "Merged into \"{$winner->title}\". Duplicate record deleted.");
    }
}
