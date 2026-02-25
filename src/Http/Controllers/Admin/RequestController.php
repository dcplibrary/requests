<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SfpRequest;
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
        $httpRequest->validate([
            'status_id' => 'required|exists:request_statuses,id',
            'note'      => 'nullable|string|max:2000',
        ]);

        $sfpRequest->transitionStatus(
            $httpRequest->status_id,
            $httpRequest->user()->id,
            $httpRequest->note
        );

        return back()->with('success', 'Status updated.');
    }
}
