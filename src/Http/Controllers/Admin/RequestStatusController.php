<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\RequestStatusHistory;
use Dcplibrary\Sfp\Models\RequestStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestStatusController extends Controller
{
    public function index()
    {
        return view('sfp::staff.statuses.index', [
            'statuses' => RequestStatus::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.statuses.form', ['status' => new RequestStatus()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'color'        => 'required|string|max:20',
            'sort_order'   => 'required|integer|min:0',
            'active'       => 'boolean',
            'is_terminal'  => 'boolean',
            'notify_patron' => 'boolean',
        ]);

        RequestStatus::create(array_merge($data, ['slug' => Str::slug($data['name'])]));

        return redirect()->route('request.staff.statuses.index')->with('success', 'Status created.');
    }

    public function edit(RequestStatus $status)
    {
        return view('sfp::staff.statuses.form', ['status' => $status]);
    }

    public function update(Request $request, RequestStatus $status)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'color'        => 'required|string|max:20',
            'sort_order'   => 'required|integer|min:0',
            'active'       => 'boolean',
            'is_terminal'  => 'boolean',
            'notify_patron' => 'boolean',
        ]);

        $status->update($data);

        return redirect()->route('request.staff.statuses.index')->with('success', 'Status updated.');
    }

    public function destroy(RequestStatus $status)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount = $status->requests()->count();
        $historyCount  = $status->history()->count();

        if ($requestsCount > 0 || $historyCount > 0) {
            $data = $request->validate([
                'reassign_to_id' => 'required|integer|exists:request_statuses,id|different:' . $status->id,
            ]);

            DB::transaction(function () use ($status, $data) {
                DB::table('requests')
                    ->where('request_status_id', $status->id)
                    ->update(['request_status_id' => (int) $data['reassign_to_id']]);

                DB::table('request_status_history')
                    ->where('request_status_id', $status->id)
                    ->update(['request_status_id' => (int) $data['reassign_to_id']]);

                $status->delete();
            });

            return redirect()->route('request.staff.statuses.index')->with('success', 'Status deleted and records reassigned.');
        }

        $status->delete();

        return redirect()->route('request.staff.statuses.index')->with('success', 'Status deleted.');
    }

    public function confirmDelete(RequestStatus $status)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount = $status->requests()->count();
        $historyCount  = $status->history()->count();

        $requestPreview = $status->requests()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'submitted_title'])
            ->map(fn ($r) => [
                'mono'  => "#{$r->id}",
                'label' => (string) ($r->submitted_title ?? 'Request'),
                'href'  => route('request.staff.requests.show', $r->id),
            ])->all();

        $historyPreview = RequestStatusHistory::query()
            ->where('request_status_id', $status->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'request_id'])
            ->map(fn (RequestStatusHistory $h) => [
                'mono'  => "#{$h->id}",
                'label' => "Request #{$h->request_id}",
                'href'  => route('request.staff.requests.show', $h->request_id),
            ])->all();

        $options = RequestStatus::query()
            ->whereKeyNot($status->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RequestStatus $s) => ['id' => $s->id, 'label' => $s->name])
            ->values()
            ->all();

        if (empty($options) && ($requestsCount > 0 || $historyCount > 0)) {
            return back()->withErrors(['error' => 'You must create another status before deleting this one (records need a reassignment target).']);
        }

        $hasDependencies = $requestsCount > 0 || $historyCount > 0;

        return view('sfp::staff.settings.reassign-delete', [
            'title'             => 'Delete Status',
            'itemLabel'         => $status->name,
            'hasDependencies'   => $hasDependencies,
            'impacts'     => [
                "{$requestsCount} request(s) will be reassigned",
                "{$historyCount} status history entr(y/ies) will be reassigned",
            ],
            'previews'    => [
                ['title' => 'Requests', 'count' => $requestsCount, 'count_label' => 'total', 'items' => $requestPreview],
                ['title' => 'Status history entries', 'count' => $historyCount, 'count_label' => 'total', 'items' => $historyPreview],
            ],
            'options'     => $options,
            'deleteAction'=> route('request.staff.statuses.destroy', $status),
            'cancelHref'  => route('request.staff.statuses.index'),
            'extraFields' => [],
        ]);
    }
}
