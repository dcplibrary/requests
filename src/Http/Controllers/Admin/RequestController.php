<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\User as SfpUser;
use Dcplibrary\Sfp\Services\BibliocommonsService;
use Dcplibrary\Sfp\Services\NotificationService;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SfpRequest::with(['patron', 'material', 'materialType', 'audience', 'status', 'assignedTo'])
            ->visibleTo($user)
            ->latest();

        // Filters
        $kind = $request->get('kind');
        if (in_array($kind, ['sfp', 'ill'], true)) {
            $query->where('request_kind', $kind);
        } else {
            $kind = null;
        }
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

        // Assignment filters (when enabled)
        $assignmentEnabled = (bool) Setting::get('assignment_enabled', false);
        $assigned = $request->get('assigned');
        if ($assignmentEnabled && is_string($assigned) && $assigned !== '') {
            $currentSfpUser = $this->currentSfpUser($request);
            if ($assigned === 'me' && $currentSfpUser) {
                $query->where('assigned_to_user_id', $currentSfpUser->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            }
        }

        // Filter by dynamic custom fields (select/radio with value_slug)
        $cfKey = $request->get('cf');
        $cfValue = $request->get('cf_value');
        if (is_string($cfKey) && $cfKey !== '' && is_string($cfValue) && $cfValue !== '') {
            $field = CustomField::query()
                ->where('key', $cfKey)
                ->where('filterable', true)
                ->whereIn('type', ['select', 'radio'])
                ->when($kind, fn ($q) => $q->forKind($kind))
                ->first();

            if ($field) {
                $query->whereExists(function ($sub) use ($field, $cfValue) {
                    $sub->selectRaw('1')
                        ->from('sfp_request_custom_field_values as rcfv')
                        ->whereColumn('rcfv.request_id', 'requests.id')
                        ->where('rcfv.custom_field_id', $field->id)
                        ->where('rcfv.value_slug', $cfValue);
                });
            }
        }

        $requests = $query->paginate(30)->withQueryString();

        $customFilterFields = CustomField::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->when($kind, fn ($q) => $q->forKind($kind))
            ->ordered()
            ->get(['id', 'key', 'label', 'type']);

        $selectedCustomField = null;
        if (is_string($cfKey) && $cfKey !== '') {
            $selectedCustomField = $customFilterFields->firstWhere('key', $cfKey)
                ?? CustomField::query()->where('key', $cfKey)->first();
        }

        $customFilterOptions = collect();
        if ($selectedCustomField) {
            $customFilterOptions = CustomFieldOption::query()
                ->where('custom_field_id', $selectedCustomField->id)
                ->active()
                ->ordered()
                ->get(['slug', 'name']);
        }

        return view('sfp::staff.requests.index', [
            'requests'      => $requests,
            'statuses'      => RequestStatus::active()->get(),
            'materialTypes' => MaterialType::active()->get(),
            'audiences'     => Audience::active()->get(),
            'filters'       => $request->only(['kind', 'status', 'material_type', 'audience', 'search', 'cf', 'cf_value', 'assigned']),
            'currentKind'   => $kind,
            'customFilterFields' => $customFilterFields,
            'customFilterOptions' => $customFilterOptions,
            'assignmentEnabled' => $assignmentEnabled,
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
            'customFieldValues.field',
            'assignedTo',
            'assignedBy',
        ]);

        $customValueLabelByFieldId = [];
        $fieldIds = $sfpRequest->customFieldValues->pluck('custom_field_id')->unique()->values()->all();
        if (! empty($fieldIds)) {
            $optionMaps = CustomFieldOption::query()
                ->whereIn('custom_field_id', $fieldIds)
                ->get()
                ->groupBy('custom_field_id')
                ->map(fn ($group) => $group->pluck('name', 'slug')->all())
                ->all();

            foreach ($sfpRequest->customFieldValues as $val) {
                $label = $val->value_text;
                if ($val->value_slug) {
                    $label = $optionMaps[$val->custom_field_id][$val->value_slug] ?? $val->value_slug;
                }
                $customValueLabelByFieldId[$val->custom_field_id] = $label;
            }
        }

        return view('sfp::staff.requests.show', [
            'sfpRequest' => $sfpRequest,
            'statuses'   => RequestStatus::active()->get(),
            'customValueLabelByFieldId' => $customValueLabelByFieldId,
            'assignmentEnabled' => (bool) Setting::get('assignment_enabled', false),
            'staffUsers' => SfpUser::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function assign(Request $httpRequest, SfpRequest $sfpRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $allowed = SfpRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) abort(403);

        $data = $httpRequest->validate([
            'assigned_to_user_id' => 'nullable|integer|exists:sfp_users,id',
        ]);

        $actor = $this->currentSfpUser($httpRequest);
        $newAssigneeId = $data['assigned_to_user_id'] ?? null;

        $was = $sfpRequest->assigned_to_user_id;
        $sfpRequest->update([
            'assigned_to_user_id' => $newAssigneeId,
            'assigned_at' => $newAssigneeId ? now() : null,
            'assigned_by_user_id' => $actor?->id,
        ]);

        $note = $newAssigneeId
            ? (($was ? 'Reassigned' : 'Assigned') . " to user #{$newAssigneeId}.")
            : 'Unassigned.';
        $sfpRequest->statusHistory()->create([
            'request_status_id' => $sfpRequest->request_status_id,
            'user_id' => $actor?->id,
            'note' => $note,
        ]);

        return back()->with('success', 'Assignment updated.');
    }

    public function claim(Request $httpRequest, SfpRequest $sfpRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $allowed = SfpRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) abort(403);

        $actor = $this->currentSfpUser($httpRequest);
        if (! $actor) abort(403);

        if ($sfpRequest->assigned_to_user_id) {
            return back()->withErrors(['error' => 'This request is already assigned.']);
        }

        $sfpRequest->update([
            'assigned_to_user_id' => $actor->id,
            'assigned_at' => now(),
            'assigned_by_user_id' => $actor->id,
        ]);

        $sfpRequest->statusHistory()->create([
            'request_status_id' => $sfpRequest->request_status_id,
            'user_id' => $actor->id,
            'note' => 'Claimed by staff user.',
        ]);

        return back()->with('success', 'Request claimed.');
    }

    public function convertKind(Request $httpRequest, SfpRequest $sfpRequest)
    {
        $allowed = SfpRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $data = $httpRequest->validate([
            'to' => 'required|in:sfp,ill',
            'note' => 'nullable|string|max:2000',
        ]);

        $from = $sfpRequest->request_kind ?: 'sfp';
        $to   = $data['to'];

        if ($from === $to) {
            return back()->with('success', "Request is already '{$to}'.");
        }

        $sfpRequest->update([
            'request_kind'  => $to,
            'ill_requested' => $to === 'ill' ? true : $sfpRequest->ill_requested,
        ]);

        $sfpUserId = $this->currentSfpUser($httpRequest)?->id;
        $note = trim((string) ($data['note'] ?? ''));
        $notePrefix = "Converted workflow: {$from} → {$to}.";
        $sfpRequest->statusHistory()->create([
            'request_status_id' => $sfpRequest->request_status_id,
            'user_id'           => $sfpUserId,
            'note'              => $note ? "{$notePrefix} {$note}" : $notePrefix,
        ]);

        // Notify ILL staff when something is converted into ILL.
        if ($to === 'ill') {
            app(NotificationService::class)->notifyStaffNewRequest($sfpRequest->fresh());
        }

        return back()->with('success', "Request converted: {$from} → {$to}.");
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

        // Auto-claim on status update (only if assignment is enabled and currently unassigned).
        if (Setting::get('assignment_enabled', false) && $sfpUserId && ! $sfpRequest->assigned_to_user_id) {
            $sfpRequest->update([
                'assigned_to_user_id' => $sfpUserId,
                'assigned_at'         => now(),
                'assigned_by_user_id' => $sfpUserId,
            ]);

            $sfpRequest->statusHistory()->create([
                'request_status_id' => $sfpRequest->request_status_id,
                'user_id'           => $sfpUserId,
                'note'              => 'Auto-claimed on status update.',
            ]);
        }

        // Reload so notify service sees the fresh status relationship.
        $sfpRequest->refresh();
        app(NotificationService::class)->notifyPatronStatusChange($sfpRequest);

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

    public function destroy(Request $httpRequest, SfpRequest $sfpRequest)
    {
        $allowed = SfpRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($sfpRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        // Destructive action: restrict to SFP admins.
        $sfpUser = $this->currentSfpUser($httpRequest);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $sfpRequest->delete();

        return redirect()
            ->route('request.staff.requests.index')
            ->with('success', "Request #{$sfpRequest->id} deleted.");
    }
}
