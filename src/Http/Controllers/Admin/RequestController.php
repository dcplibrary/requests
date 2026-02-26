<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Services\BibliocommonsService;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SfpRequest::with(['patron', 'material', 'materialType', 'audience', 'status'])
            ->visibleTo($user)
            ->latest();

        // Filters
        if ($request->filled('status')) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $request->status));
        }
        if ($request->filled('material_type')) {
            $query->where('material_type_id', $request->material_type);
        }
        if ($request->filled('audience')) {
            $query->where('audience_id', $request->audience);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('submitted_title', 'like', $term)
                  ->orWhere('submitted_author', 'like', $term)
                  ->orWhereHas('patron', fn ($p) => $p->where('barcode', 'like', $term)
                      ->orWhere('name_last', 'like', $term));
            });
        }

        $requests = $query->paginate(30)->withQueryString();

        return view('sfp::staff.requests.index', [
            'requests'      => $requests,
            'statuses'      => RequestStatus::active()->get(),
            'materialTypes' => MaterialType::active()->get(),
            'audiences'     => Audience::active()->get(),
            'filters'       => $request->only(['status', 'material_type', 'audience', 'search']),
        ]);
    }

    public function show(SfpRequest $sfpRequest)
    {
        $user = request()->user();
        $allowed = SfpRequest::query()
            ->visibleTo($user)
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $sfpRequest->load([
            'patron',
            'material.materialType',
            'materialType',
            'audience',
            'status',
            'statusHistory.status',
            'statusHistory.user',
        ]);

        return view('sfp::staff.requests.show', [
            'sfpRequest' => $sfpRequest,
            'statuses'   => RequestStatus::active()->get(),
        ]);
    }

    public function updateStatus(\Illuminate\Http\Request $httpRequest, SfpRequest $sfpRequest)
    {
        $allowed = SfpRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $httpRequest->validate([
            'status_id' => 'required|exists:request_statuses,id',
            'note'      => 'nullable|string|max:2000',
        ]);

        $sfpUserId = $this->currentSfpUser($httpRequest)?->id;
        $sfpRequest->transitionStatus(
            $httpRequest->status_id,
            $sfpUserId,
            $httpRequest->note
        );

        return back()->with('success', 'Status updated.');
    }

    public function recheckCatalog(SfpRequest $sfpRequest)
    {
        $allowed = SfpRequest::query()
            ->visibleTo(request()->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $audience = Audience::find($sfpRequest->audience_id);

        $result = app(BibliocommonsService::class)->search(
            $sfpRequest->submitted_title,
            $sfpRequest->submitted_author,
            $audience?->bibliocommons_value ?? 'adult',
            $sfpRequest->submitted_publish_date ?: null
        );

        // Accept first physical book format, fall back to first result
        $match = collect($result['results'])->firstWhere('format', 'BK')
            ?? collect($result['results'])->firstWhere('format', 'LPRINT')
            ?? ($result['results'][0] ?? null);

        $sfpRequest->update([
            'catalog_searched'       => true,
            'catalog_result_count'   => $result['total'],
            'catalog_match_accepted' => $match !== null,
            'catalog_match_bib_id'   => $match['bib_id'] ?? null,
        ]);

        $message = $result['total'] > 0
            ? "Catalog re-checked: {$result['total']} result(s) found."
            : 'Catalog re-checked: item not found in catalog.';

        return back()->with('success', $message);
    }
}
