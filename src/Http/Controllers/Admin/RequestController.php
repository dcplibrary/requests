<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\RequestFieldValue;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\User as StaffUser;
use Dcplibrary\Requests\Services\BibliocommonsService;
use Dcplibrary\Requests\Services\CoverService;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PatronRequest::with(['patron', 'material', 'status', 'assignedTo', 'fieldValues.field'])
            ->visibleTo($user);

        // Sorting
        $sortable = ['id', 'request_kind', 'submitted_title', 'created_at'];
        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        // Filters
        $kind = $request->query('kind');
        if (in_array($kind, ['sfp', 'ill'], true)) {
            $query->where('request_kind', $kind);
        } else {
            $kind = null;
        }
        if ($request->filled('status')) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $request->status));
        }
        if ($request->filled('material_type')) {
            $mtFieldId = Field::where('key', 'material_type')->value('id');
            if ($mtFieldId) {
                $query->whereExists(function ($sub) use ($request, $mtFieldId) {
                    $sub->selectRaw('1')
                        ->from('request_field_values')
                        ->whereColumn('request_field_values.request_id', 'requests.id')
                        ->where('request_field_values.field_id', $mtFieldId)
                        ->where('request_field_values.value', $request->material_type);
                });
            }
        }
        if ($request->filled('audience')) {
            $audFieldId = Field::where('key', 'audience')->value('id');
            if ($audFieldId) {
                $query->whereExists(function ($sub) use ($request, $audFieldId) {
                    $sub->selectRaw('1')
                        ->from('request_field_values')
                        ->whereColumn('request_field_values.request_id', 'requests.id')
                        ->where('request_field_values.field_id', $audFieldId)
                        ->where('request_field_values.value', $request->audience);
                });
            }
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
        $assigned = $request->query('assigned');
        if ($assignmentEnabled && is_string($assigned) && $assigned !== '') {
            $currentStaffUser = $this->currentStaffUser($request);
            if ($assigned === 'me' && $currentStaffUser) {
                $query->where('assigned_to_user_id', $currentStaffUser->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            }
        }

        // Filter by dynamic fields (select/radio with value slug)
        $cfKey = $request->query('cf');
        $cfValue = $request->query('cf_value');
        if (is_string($cfKey) && $cfKey !== '' && is_string($cfValue) && $cfValue !== '') {
            $field = Field::query()
                ->where('key', $cfKey)
                ->where('filterable', true)
                ->whereIn('type', ['select', 'radio'])
                ->when($kind, fn ($q) => $q->forKind($kind))
                ->first();

            if ($field) {
                $query->whereExists(function ($sub) use ($field, $cfValue) {
                    $sub->selectRaw('1')
                        ->from('request_field_values as rfv')
                        ->whereColumn('rfv.request_id', 'requests.id')
                        ->where('rfv.field_id', $field->id)
                        ->where('rfv.value', $cfValue);
                });
            }
        }

        $requests = $query->paginate(30)->withQueryString();

        $customFilterFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->when($kind, fn ($q) => $q->forKind($kind))
            ->ordered()
            ->get(['id', 'key', 'label', 'type']);

        $selectedCustomField = null;
        if (is_string($cfKey) && $cfKey !== '') {
            $selectedCustomField = $customFilterFields->firstWhere('key', $cfKey)
                ?? Field::query()->where('key', $cfKey)->first();
        }

        $customFilterOptions = collect();
        if ($selectedCustomField) {
            $customFilterOptions = FieldOption::query()
                ->where('field_id', $selectedCustomField->id)
                ->active()
                ->ordered()
                ->get(['slug', 'name']);
        }

        $mtField  = Field::where('key', 'material_type')->first();
        $audField = Field::where('key', 'audience')->first();

        $staffUser = $this->currentStaffUser($request);
        $hasIllAccess = $staffUser && $staffUser->hasIllAccess();

        // Compute selector group name per request (in-memory, no extra queries).
        $scopingFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->get(['id', 'key']);
        $selectorGroups = SelectorGroup::active()->with('fieldOptions')->orderBy('name')->get();

        /** @var array<int, string|null> */
        $groupNameByRequestId = [];
        foreach ($requests as $req) {
            $groupNameByRequestId[$req->id] = $this->resolveGroupName($req, $selectorGroups, $scopingFields);
        }

        return view('requests::staff.requests.index', [
            'requests'           => $requests,
            'currentStaffUser'   => $staffUser,
            'statuses'           => RequestStatus::active()->get(),
            'materialTypes'  => $mtField ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->get() : collect(),
            'audiences'      => $audField ? FieldOption::where('field_id', $audField->id)->active()->ordered()->get() : collect(),
            'filters'        => $request->only(['kind', 'status', 'material_type', 'audience', 'search', 'cf', 'cf_value', 'assigned']),
            'currentKind'    => $kind,
            'customFilterFields'  => $customFilterFields,
            'customFilterOptions' => $customFilterOptions,
            'assignmentEnabled'   => $assignmentEnabled,
            'hasIllAccess'        => $hasIllAccess,
            'groupNameByRequestId' => $groupNameByRequestId,
            'selectorGroups' => $selectorGroups,
            'staffUsers'     => $assignmentEnabled ? StaffUser::where('active', true)->orderBy('name')->get(['id', 'name', 'email']) : collect(),
        ]);
    }

    /**
     * Resolve the selector group name for a request by matching its field
     * values against the group's field options. Returns the first match.
     *
     * @param  PatronRequest                                      $req
     * @param  \Illuminate\Database\Eloquent\Collection           $groups
     * @param  \Illuminate\Support\Collection                     $scopingFields
     * @return string|null
     */
    private function resolveGroupName(PatronRequest $req, $groups, $scopingFields): ?string
    {
        foreach ($groups as $group) {
            $matches = true;
            foreach ($scopingFields as $field) {
                $groupOpts = $group->fieldOptions->where('field_id', $field->id);
                if ($groupOpts->isEmpty()) {
                    continue; // unrestricted for this field
                }
                $slug = $req->fieldValue($field->key);
                if (! $slug || ! $groupOpts->pluck('slug')->contains($slug)) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $group->name;
            }
        }

        return null;
    }

    public function show(PatronRequest $patronRequest)
    {
        $user = request()->user();
        $allowed = PatronRequest::query()
            ->visibleTo($user)
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $patronRequest->load([
            'patron',
            'material',
            'status',
            'statusHistory.status',
            'statusHistory.user',
            'fieldValues.field',
            'assignedTo',
            'assignedBy',
        ]);

        // Auto-claim on first open: assign to the current staff user if unassigned.
        // Suppressed by ?noclaim=1 (used after reroute / manual unassign).
        $justClaimed = false;
        $assignmentEnabled = (bool) Setting::get('assignment_enabled', false);
        if ($assignmentEnabled && ! $patronRequest->assigned_to_user_id && ! request()->boolean('noclaim')) {
            $actor = $this->currentStaffUser(request());
            if ($actor) {
                $patronRequest->update([
                    'assigned_to_user_id' => $actor->id,
                    'assigned_at'         => now(),
                    'assigned_by_user_id' => $actor->id,
                ]);
                $patronRequest->statusHistory()->create([
                    'request_status_id' => $patronRequest->request_status_id,
                    'user_id'           => $actor->id,
                    'note'              => 'Auto-claimed on open.',
                ]);
                $patronRequest->load(['assignedTo', 'assignedBy', 'status', 'statusHistory.status', 'statusHistory.user']);
                $justClaimed = true;
            }
        }

        // If the request is assigned and its current status has "advance_on_claim" enabled,
        // advance it to the next status by sort_order. This is configured per-status
        // (Settings → Statuses → edit → "Auto-advance to next status when claimed").
        // Runs on every open so it also catches requests claimed before this logic existed.
        if ($patronRequest->assigned_to_user_id && $patronRequest->request_status_id) {
            $currentStatus = $patronRequest->status ?? RequestStatus::find($patronRequest->request_status_id);
            if ($currentStatus?->advance_on_claim) {
                $nextStatus = RequestStatus::where('sort_order', '>', $currentStatus->sort_order)
                    ->forKind($patronRequest->request_kind)
                    ->orderBy('sort_order')
                    ->first();
                if ($nextStatus) {
                    $actor = $actor ?? $this->currentStaffUser(request());
                    $patronRequest->transitionStatus($nextStatus->id, $actor?->id, 'Status advanced on claim.');
                    $patronRequest->load(['status', 'statusHistory.status', 'statusHistory.user']);
                }
            }
        }

        $fieldValueLabelByFieldId = [];
        $fieldIds = $patronRequest->fieldValues->pluck('field_id')->unique()->values()->all();
        if (! empty($fieldIds)) {
            $optionMaps = FieldOption::query()
                ->whereIn('field_id', $fieldIds)
                ->get()
                ->groupBy('field_id')
                ->map(fn ($group) => $group->pluck('name', 'slug')->all())
                ->all();

            foreach ($patronRequest->fieldValues as $val) {
                $label = $val->value;
                $field = $val->field;
                if ($field && in_array($field->type, ['select', 'radio'])) {
                    $label = $optionMaps[$val->field_id][$val->value] ?? $val->value;
                } elseif ($field && $field->type === 'checkbox') {
                    $label = $val->value ? 'Yes' : 'No';
                }
                $fieldValueLabelByFieldId[$val->field_id] = $label;
            }
        }

        // Show "Convert to ILL" button when this is an SFP request and ILL was requested.
        $showConvertToIll = $patronRequest->request_kind === 'sfp'
            && $patronRequest->ill_requested;

        // Reroute: all filterable select/radio fields.
        $rerouteFields = collect();
        if ($assignmentEnabled) {
            $rerouteFields = Field::query()
                ->where('filterable', true)
                ->whereIn('type', ['select', 'radio'])
                ->where('active', true)
                ->with(['options' => fn ($q) => $q->active()->ordered()])
                ->ordered()
                ->get();
        }

        // Selector groups for the reroute modal (only when reroutable fields exist).
        // Load with fieldOptions so we can also determine the current matching group.
        $selectorGroups = collect();
        $currentGroupName = null;
        if ($rerouteFields->isNotEmpty()) {
            $selectorGroups = SelectorGroup::active()
                ->with('fieldOptions')
                ->orderBy('name')
                ->get();

            // Find the first group whose field options match all of the
            // request's current filterable field values.
            foreach ($selectorGroups as $group) {
                $matches = true;
                foreach ($rerouteFields as $field) {
                    $groupOpts = $group->fieldOptions->where('field_id', $field->id);
                    if ($groupOpts->isEmpty()) {
                        continue; // unrestricted for this field
                    }
                    $slug = $patronRequest->fieldValue($field->key);
                    if (! $slug || ! $groupOpts->pluck('slug')->contains($slug)) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $currentGroupName = $group->name;
                    break;
                }
            }
        }

        // Build cover image URL: Syndetics → Open Library → null.
        $material = $patronRequest->material;
        $coverIsbn = $material?->isbn13 ?? $material?->isbn ?? null;
        $openLibFallback = $coverIsbn
            ? "https://covers.openlibrary.org/b/isbn/{$coverIsbn}-L.jpg"
            : null;
        $coverUrl = app(CoverService::class)->url($coverIsbn, $openLibFallback);

        return view('requests::staff.requests.show', [
            'patronRequest' => $patronRequest,
            'coverUrl'   => $coverUrl,
            'statuses'   => RequestStatus::active()->forKind($patronRequest->request_kind)->get(),
            'fieldValueLabelByFieldId' => $fieldValueLabelByFieldId,
            'assignmentEnabled' => $assignmentEnabled,
            'justClaimed' => $justClaimed,
            'staffUsers' => StaffUser::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'showConvertToIll' => $showConvertToIll,
            'rerouteFields' => $rerouteFields,
            'selectorGroups' => $selectorGroups,
            'currentGroupName' => $currentGroupName,
            'sfpIsbnLookupUrl' => Setting::get('sfp_isbn_lookup_url'),
            'illIsbnLookupUrl' => Setting::get('ill_isbn_lookup_url'),
            'polarisLeapUrl'   => Setting::get('polaris_leap_url'),
        ]);
    }

    public function assign(Request $httpRequest, PatronRequest $patronRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) abort(403);

        $data = $httpRequest->validate([
            'assigned_to_user_id' => 'nullable|integer|exists:staff_users,id',
            'note'                => 'nullable|string|max:2000',
        ]);

        $actor = $this->currentStaffUser($httpRequest);
        $newAssigneeId = $data['assigned_to_user_id'] ?? null;
        $userNote      = trim((string) ($data['note'] ?? ''));

        $was = $patronRequest->assigned_to_user_id;
        $patronRequest->update([
            'assigned_to_user_id' => $newAssigneeId,
            'assigned_at' => $newAssigneeId ? now() : null,
            'assigned_by_user_id' => $actor?->id,
        ]);

        $historyNote = $newAssigneeId
            ? (($was ? 'Reassigned' : 'Assigned') . " to user #{$newAssigneeId}.")
            : 'Unassigned.';
        if ($userNote) {
            $historyNote .= " {$userNote}";
        }
        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id' => $actor?->id,
            'note' => $historyNote,
        ]);

        // Notify the new assignee (skip if unassigning or self-assigning).
        if ($newAssigneeId && $actor && (int) $newAssigneeId !== $actor->id) {
            $assignee = StaffUser::find($newAssigneeId);
            if ($assignee) {
                app(NotificationService::class)->notifyAssignee(
                    $patronRequest->fresh(),
                    $assignee,
                    $actor,
                    $userNote ?: null
                );
            }
        }

        // After unassign, redirect with noclaim so auto-claim doesn't immediately re-grab it.
        if (! $newAssigneeId) {
            return redirect()
                ->to(route('request.staff.requests.show', $patronRequest) . '?noclaim=1')
                ->with('success', 'Assignment updated.');
        }

        return back()->with('success', 'Assignment updated.');
    }

    public function claim(Request $httpRequest, PatronRequest $patronRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) abort(403);

        $actor = $this->currentStaffUser($httpRequest);
        if (! $actor) abort(403);

        if ($patronRequest->assigned_to_user_id) {
            return back()->withErrors(['error' => 'This request is already assigned.']);
        }

        $patronRequest->update([
            'assigned_to_user_id' => $actor->id,
            'assigned_at' => now(),
            'assigned_by_user_id' => $actor->id,
        ]);

        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id' => $actor->id,
            'note' => 'Claimed by staff user.',
        ]);

        return back()->with('success', 'Request claimed.');
    }

    /**
     * Reroute a request to a different selector group.
     *
     * Accepts a group_id and optional field overrides (for fields where the
     * target group has multiple options). Adjusts filterable field values to
     * match the target group and unassigns the request.
     *
     * @param  Request        $httpRequest
     * @param  PatronRequest  $patronRequest
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reroute(Request $httpRequest, PatronRequest $patronRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $httpRequest->validate([
            'group_id'   => 'required|integer|exists:selector_groups,id',
            'fields'     => 'nullable|array',
            'fields.*'   => 'nullable|string',
            'note'       => 'nullable|string|max:2000',
        ]);

        $group = SelectorGroup::with('fieldOptions.field')->findOrFail($httpRequest->group_id);
        $fieldOverrides = $httpRequest->input('fields', []);

        $rerouteFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->get(['id', 'key', 'label']);

        $patronRequest->load('fieldValues.field');
        $changes = [];

        foreach ($rerouteFields as $field) {
            $groupOptions = $group->fieldOptions->where('field_id', $field->id);

            // Unrestricted for this field — no change needed.
            if ($groupOptions->isEmpty()) {
                continue;
            }

            $existing = $patronRequest->fieldValues->first(fn ($v) => $v->field_id === $field->id);
            $currentSlug = $existing?->value;

            // Current value already matches one of the group's options.
            if ($currentSlug && $groupOptions->pluck('slug')->contains($currentSlug)) {
                continue;
            }

            // Determine new slug: use form override if provided, otherwise auto-pick single option.
            $newSlug = $fieldOverrides[$field->key] ?? null;
            if (! $newSlug && $groupOptions->count() === 1) {
                $newSlug = $groupOptions->first()->slug;
            }
            if (! $newSlug) {
                return back()->withErrors(['error' => "Please select a value for {$field->label}."]);
            }

            // Validate the chosen slug belongs to this group.
            if (! $groupOptions->pluck('slug')->contains($newSlug)) {
                return back()->withErrors(['error' => "Invalid option for {$field->label}."]);
            }

            $oldLabel = $currentSlug
                ? (FieldOption::where('field_id', $field->id)->where('slug', $currentSlug)->value('name') ?? $currentSlug)
                : '(none)';
            $newLabel = $groupOptions->firstWhere('slug', $newSlug)?->name ?? $newSlug;

            if ($existing) {
                $existing->update(['value' => $newSlug]);
            } else {
                RequestFieldValue::create([
                    'request_id' => $patronRequest->id,
                    'field_id'   => $field->id,
                    'value'      => $newSlug,
                ]);
            }

            $changes[] = "{$field->label}: {$oldLabel} → {$newLabel}";
        }

        if (empty($changes)) {
            return back()->with('success', 'No changes needed — fields already match this group.');
        }

        // Unassign so the next group member who opens it auto-claims.
        $patronRequest->update([
            'assigned_to_user_id' => null,
            'assigned_at'         => null,
            'assigned_by_user_id' => null,
        ]);

        $actor = $this->currentStaffUser($httpRequest);
        $userNote = trim((string) $httpRequest->input('note'));
        $historyNote = 'Rerouted to ' . $group->name . ': ' . implode('; ', $changes) . '.';
        if ($userNote) {
            $historyNote .= " {$userNote}";
        }
        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id'           => $actor?->id,
            'note'              => $historyNote,
        ]);

        // Notify the target group.
        if ($actor) {
            app(NotificationService::class)->notifyStaffWorkflowAction(
                $patronRequest->fresh(),
                'Rerouted',
                $actor,
                $userNote ?: null,
                $changes
            );
        }

        return redirect()
            ->route('request.staff.requests.index')
            ->with('success', 'Request #' . $patronRequest->id . ' rerouted to ' . $group->name . '.');
    }

    /**
     * Return JSON showing what field changes are needed to reroute to a given group.
     *
     * For each filterable field, compares the request's current value against the
     * target group's options. Returns a list of changes with available options.
     *
     * @param  Request        $httpRequest
     * @param  PatronRequest  $patronRequest
     * @return JsonResponse
     */
    public function reroutePreview(Request $httpRequest, PatronRequest $patronRequest): JsonResponse
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $groupId = (int) $httpRequest->query('group_id');
        if (! $groupId) {
            return response()->json(['changes' => [], 'no_changes' => true]);
        }

        $group = SelectorGroup::with('fieldOptions.field')->find($groupId);
        if (! $group) {
            return response()->json(['changes' => [], 'no_changes' => true]);
        }

        $patronRequest->loadMissing('fieldValues.field');

        $rerouteFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->get(['id', 'key', 'label']);

        $changes = [];
        foreach ($rerouteFields as $field) {
            $groupOptions = $group->fieldOptions->where('field_id', $field->id);

            // Unrestricted — no change needed.
            if ($groupOptions->isEmpty()) {
                continue;
            }

            $currentSlug = $patronRequest->fieldValue($field->key);
            $currentLabel = $currentSlug
                ? (FieldOption::where('field_id', $field->id)->where('slug', $currentSlug)->value('name') ?? $currentSlug)
                : '(none)';

            // Already matches — no change needed.
            if ($currentSlug && $groupOptions->pluck('slug')->contains($currentSlug)) {
                continue;
            }

            $options = $groupOptions->map(fn ($opt) => [
                'slug' => $opt->slug,
                'name' => $opt->name,
            ])->values();

            $changes[] = [
                'field_key'     => $field->key,
                'field_label'   => $field->label,
                'current_value' => $currentSlug ?? '',
                'current_label' => $currentLabel,
                'options'       => $options,
                'auto_selected' => $options->count() === 1 ? $options->first()['slug'] : null,
            ];
        }

        return response()->json([
            'group'      => ['id' => $group->id, 'name' => $group->name],
            'changes'    => $changes,
            'no_changes' => empty($changes),
        ]);
    }

    public function convertKind(Request $httpRequest, PatronRequest $patronRequest)
    {
        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $data = $httpRequest->validate([
            'to' => 'required|in:sfp,ill',
            'note' => 'nullable|string|max:2000',
        ]);

        $from = $patronRequest->request_kind ?: 'sfp';
        $to   = $data['to'];

        if ($from === $to) {
            return back()->with('success', "Request is already '{$to}'.");
        }

        $patronRequest->update([
            'request_kind'  => $to,
            'ill_requested' => $to === 'ill' ? true : $patronRequest->ill_requested,
        ]);

        $staffUserId = $this->currentStaffUser($httpRequest)?->id;
        $note = trim((string) ($data['note'] ?? ''));
        $notePrefix = "Converted workflow: {$from} → {$to}.";
        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id'           => $staffUserId,
            'note'              => $note ? "{$notePrefix} {$note}" : $notePrefix,
        ]);

        // Notify ILL staff when something is converted into ILL.
        if ($to === 'ill') {
            $staffUser = $this->currentStaffUser($httpRequest);
            if ($staffUser) {
                app(NotificationService::class)->notifyStaffWorkflowAction(
                    $patronRequest->fresh(),
                    'Converted to ILL',
                    $staffUser,
                    $note ?: null
                );
            }
            // Redirect to the list — the user may not have ILL access so back() could 403.
            return redirect()
                ->route('request.staff.requests.index')
                ->with('success', "Request #{$patronRequest->id} converted to ILL.");
        }

        return back()->with('success', "Request converted: {$from} → {$to}.");
    }

    /**
     * Return a JSON preview of the patron email that would be sent for the
     * given status change, without actually sending anything.
     */
    public function previewStatusEmail(Request $httpRequest, PatronRequest $patronRequest): JsonResponse
    {
        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $httpRequest->validate([
            'status_id' => 'required|integer|exists:request_statuses,id',
        ]);

        $statusId = (int) $httpRequest->status_id;
        $preview  = app(NotificationService::class)->renderPatronEmail($patronRequest, $statusId);

        $staffEmail = $this->currentStaffUser($httpRequest)?->email;

        return response()->json([
            'would_send'      => $preview !== null,
            'subject'         => $preview['subject'] ?? '',
            'body'            => $preview['body']    ?? '',
            'to'              => $preview['to']      ?? '',
            'staff_email'     => $staffEmail ?? '',
            'preview_enabled' => (bool) Setting::get('email_preview_enabled', true),
            'editing_enabled' => (bool) Setting::get('email_editing_enabled', false),
        ]);
    }

    public function updateStatus(\Illuminate\Http\Request $httpRequest, PatronRequest $patronRequest)
    {
        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $httpRequest->validate([
            'status_id'          => 'required|exists:request_statuses,id',
            'note'               => 'nullable|string|max:2000',
            'email_confirmed'    => 'nullable|boolean',
            'email_skip'         => 'nullable|boolean',
            'email_subject'      => 'nullable|string|max:500',
            'email_body'         => 'nullable|string',
            'email_to'           => 'nullable|email',
            'email_cc'           => 'nullable|string|max:1000',
            'email_bcc'          => 'nullable|string|max:1000',
            'email_copy_to_self' => 'nullable|boolean',
        ]);

        $staffUserId = $this->currentStaffUser($httpRequest)?->id;
        $patronRequest->transitionStatus(
            $httpRequest->status_id,
            $staffUserId,
            $httpRequest->note
        );

        // Auto-claim on status update (only if assignment is enabled and currently unassigned).
        if (Setting::get('assignment_enabled', false) && $staffUserId && ! $patronRequest->assigned_to_user_id) {
            $patronRequest->update([
                'assigned_to_user_id' => $staffUserId,
                'assigned_at'         => now(),
                'assigned_by_user_id' => $staffUserId,
            ]);

            $patronRequest->statusHistory()->create([
                'request_status_id' => $patronRequest->request_status_id,
                'user_id'           => $staffUserId,
                'note'              => 'Auto-claimed on status update.',
            ]);
        }

        // Reload so notify service sees the fresh status relationship.
        $patronRequest->refresh();

        if ($httpRequest->boolean('email_confirmed')) {
            // Staff reviewed (and optionally edited) the email in the preview modal — send it.
            $this->sendCustomPatronEmail(
                subject:     (string) ($httpRequest->email_subject ?? ''),
                body:        (string) ($httpRequest->email_body    ?? ''),
                to:          (string) ($httpRequest->email_to      ?? ''),
                cc:          (string) ($httpRequest->email_cc      ?? ''),
                bcc:         (string) ($httpRequest->email_bcc     ?? ''),
                copyToSelf:  $httpRequest->boolean('email_copy_to_self'),
                staffUserId: $staffUserId,
            );
            $patronRequest->statusHistory()->create([
                'request_status_id' => $patronRequest->request_status_id,
                'user_id'           => $staffUserId,
                'note'              => 'Notification sent.',
            ]);
        } elseif ($httpRequest->boolean('email_skip')) {
            // Staff saw the preview and chose to skip sending — do nothing.
        } else {
            // Preview was never shown (disabled or no email would fire) — use standard path.
            $sent = app(NotificationService::class)->notifyPatronStatusChange($patronRequest);
            if ($sent) {
                $patronRequest->statusHistory()->create([
                    'request_status_id' => $patronRequest->request_status_id,
                    'user_id'           => $staffUserId,
                    'note'              => 'Notification sent.',
                ]);
            }
        }

        return back()->with('success', 'Status updated.');
    }

    /**
     * Handle a one-click status change from a signed email action link.
     *
     * The URL is generated by NotificationService and is a temporary signed route
     * (?status_id=X&expires=...) — Laravel's signed URL middleware validates it.
     * No authentication is required; the cryptographic signature serves as the
     * authorization token (anyone with the link can act, just like any email link).
     *
     * On success the staff member is redirected to the full request detail page.
     * If the request is already past the target status the action is a no-op and
     * a friendly message is shown.
     */
    public function emailAction(
        \Illuminate\Http\Request $httpRequest,
        PatronRequest $patronRequest
    ) {
        if (! $httpRequest->hasValidSignature()) {
            abort(403, 'This link has expired or is invalid.');
        }

        $statusId = (int) $httpRequest->query('status_id');
        if (! $statusId) {
            abort(400, 'Missing status_id.');
        }

        $newStatus = RequestStatus::find($statusId);
        if (! $newStatus) {
            abort(404, 'Status not found.');
        }

        // No-op if the request is already on this status (idempotent).
        if ($patronRequest->request_status_id !== $newStatus->id) {
            $patronRequest->transitionStatus(
                $newStatus->id,
                null,   // no staff user — came from email
                'Status set via email action link.'
            );

            // Fire patron notification if the new status has it enabled.
            $patronRequest->refresh();
            app(NotificationService::class)->notifyPatronStatusChange($patronRequest);
        }

        // Redirect to the request detail page so the staff member can see it.
        $detailUrl = route('request.staff.requests.show', $patronRequest);
        return redirect($detailUrl)->with(
            'success',
            "Status updated to \"{$newStatus->name}\"."
        );
    }

    /**
     * Send a patron email with custom subject/body/recipients assembled in the
     * email preview modal. Parsing CC/BCC from comma-separated strings.
     */
    private function sendCustomPatronEmail(
        string  $subject,
        string  $body,
        string  $to,
        string  $cc,
        string  $bcc,
        bool    $copyToSelf,
        ?int    $staffUserId,
    ): void {
        if (! $to) return;

        $ccAddresses  = $this->parseAddressList($cc);
        $bccAddresses = $this->parseAddressList($bcc);

        if ($copyToSelf && $staffUserId) {
            $staffEmail = StaffUser::find($staffUserId)?->email;
            if ($staffEmail) {
                $ccAddresses[] = $staffEmail;
            }
        }

        // Deduplicate.
        $ccAddresses  = array_unique(array_filter($ccAddresses));
        $bccAddresses = array_unique(array_filter($bccAddresses));

        try {
            $mailer = \Illuminate\Support\Facades\Mail::to($to);
            if (! empty($ccAddresses))  $mailer = $mailer->cc($ccAddresses);
            if (! empty($bccAddresses)) $mailer = $mailer->bcc($bccAddresses);
            $mailer->send(new \Dcplibrary\Requests\Mail\RequestMail($subject, $body));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Custom patron email failed', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Split a comma/semicolon/newline-separated address list into an array of
     * trimmed, validated email strings.
     *
     * @return string[]
     */
    private function parseAddressList(string $raw): array
    {
        if (trim($raw) === '') return [];

        $emails = [];
        foreach (preg_split('/[\s,;]+/', $raw) as $part) {
            $part = trim($part);
            if ($part && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $part;
            }
        }
        return $emails;
    }

    public function recheckCatalog(PatronRequest $patronRequest)
    {
        $allowed = PatronRequest::query()
            ->visibleTo(request()->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        $audienceSlug = $patronRequest->fieldValue('audience') ?? 'adult';

        $result = app(BibliocommonsService::class)->search(
            $patronRequest->submitted_title,
            $patronRequest->submitted_author,
            $audienceSlug,
            $patronRequest->submitted_publish_date ?: null
        );

        // Accept first physical book format, fall back to first result
        $match = collect($result['results'])->firstWhere('format', 'BK')
            ?? collect($result['results'])->firstWhere('format', 'LPRINT')
            ?? ($result['results'][0] ?? null);

        $patronRequest->update([
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

    /**
     * Bulk update the status of selected requests.
     *
     * @param  Request  $httpRequest
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkStatus(Request $httpRequest)
    {
        $httpRequest->validate([
            'ids'       => 'required|array|min:1',
            'ids.*'     => 'integer|exists:requests,id',
            'status_id' => 'required|integer|exists:request_statuses,id',
        ]);

        $actor = $this->currentStaffUser($httpRequest);
        $requests = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereIn('id', $httpRequest->input('ids'))
            ->get();

        if ($requests->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible requests selected.']);
        }

        $statusId = (int) $httpRequest->input('status_id');
        $status = RequestStatus::findOrFail($statusId);

        foreach ($requests as $req) {
            $req->transitionStatus($statusId, $actor?->id, 'Bulk status change.');
        }

        return back()->with('success', $requests->count() . " request(s) moved to {$status->name}.");
    }

    /**
     * Bulk reassign selected requests to a group (reroute) or a user (assign).
     *
     * When a group is chosen, each request's filterable field values are updated
     * to match the group (auto-picking the single option per field) and the
     * request is unassigned. When a user is chosen, each request is assigned to
     * that user.
     *
     * @param  Request  $httpRequest
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkReassign(Request $httpRequest)
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        $httpRequest->validate([
            'ids'      => 'required|array|min:1',
            'ids.*'    => 'integer|exists:requests,id',
            'group_id' => 'nullable|integer|exists:selector_groups,id',
            'user_id'  => 'nullable|integer|exists:staff_users,id',
        ]);

        $groupId = $httpRequest->input('group_id');
        $userId  = $httpRequest->input('user_id');

        if (! $groupId && ! $userId) {
            return back()->withErrors(['error' => 'Please select a group or user.']);
        }

        $actor = $this->currentStaffUser($httpRequest);
        $requests = PatronRequest::with('fieldValues.field')
            ->visibleTo($httpRequest->user())
            ->whereIn('id', $httpRequest->input('ids'))
            ->get();

        if ($requests->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible requests selected.']);
        }

        // --- Reroute to group ---
        if ($groupId) {
            $group = SelectorGroup::with('fieldOptions.field')->findOrFail($groupId);

            $rerouteFields = Field::query()
                ->where('filterable', true)
                ->whereIn('type', ['select', 'radio'])
                ->where('active', true)
                ->get(['id', 'key', 'label']);

            $rerouted = 0;
            foreach ($requests as $req) {
                $changes = [];
                foreach ($rerouteFields as $field) {
                    $groupOptions = $group->fieldOptions->where('field_id', $field->id);
                    if ($groupOptions->isEmpty()) {
                        continue;
                    }

                    $existing = $req->fieldValues->first(fn ($v) => $v->field_id === $field->id);
                    $currentSlug = $existing?->value;

                    if ($currentSlug && $groupOptions->pluck('slug')->contains($currentSlug)) {
                        continue;
                    }

                    // Auto-pick single option; skip if ambiguous.
                    if ($groupOptions->count() !== 1) {
                        continue;
                    }
                    $newSlug = $groupOptions->first()->slug;

                    $oldLabel = $currentSlug
                        ? (FieldOption::where('field_id', $field->id)->where('slug', $currentSlug)->value('name') ?? $currentSlug)
                        : '(none)';
                    $newLabel = $groupOptions->first()->name;

                    if ($existing) {
                        $existing->update(['value' => $newSlug]);
                    } else {
                        RequestFieldValue::create([
                            'request_id' => $req->id,
                            'field_id'   => $field->id,
                            'value'      => $newSlug,
                        ]);
                    }
                    $changes[] = "{$field->label}: {$oldLabel} → {$newLabel}";
                }

                $req->update([
                    'assigned_to_user_id' => null,
                    'assigned_at'         => null,
                    'assigned_by_user_id' => null,
                ]);

                $historyNote = 'Bulk rerouted to ' . $group->name . '.';
                if (! empty($changes)) {
                    $historyNote .= ' ' . implode('; ', $changes) . '.';
                }
                $req->statusHistory()->create([
                    'request_status_id' => $req->request_status_id,
                    'user_id'           => $actor?->id,
                    'note'              => $historyNote,
                ]);
                $rerouted++;
            }

            return back()->with('success', "{$rerouted} request(s) rerouted to {$group->name}.");
        }

        // --- Assign to user ---
        $assignee = StaffUser::findOrFail($userId);
        foreach ($requests as $req) {
            $was = $req->assigned_to_user_id;
            $req->update([
                'assigned_to_user_id' => $assignee->id,
                'assigned_at'         => now(),
                'assigned_by_user_id' => $actor?->id,
            ]);
            $req->statusHistory()->create([
                'request_status_id' => $req->request_status_id,
                'user_id'           => $actor?->id,
                'note'              => ($was ? 'Bulk reassigned' : 'Bulk assigned') . " to {$assignee->name}.",
            ]);
        }

        return back()->with('success', $requests->count() . " request(s) assigned to {$assignee->name}.");
    }

    /**
     * Bulk delete selected requests. Admin only.
     *
     * @param  Request  $httpRequest
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkDelete(Request $httpRequest)
    {
        $staffUser = $this->currentStaffUser($httpRequest);
        if (! $staffUser || ! $staffUser->isAdmin()) {
            abort(403);
        }

        $httpRequest->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:requests,id',
        ]);

        $requests = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereIn('id', $httpRequest->input('ids'))
            ->get();

        if ($requests->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible requests selected.']);
        }

        $count = $requests->count();
        foreach ($requests as $req) {
            $req->delete();
        }

        return back()->with('success', "{$count} request(s) deleted.");
    }

    public function destroy(Request $httpRequest, PatronRequest $patronRequest)
    {
        $allowed = PatronRequest::query()
            ->visibleTo($httpRequest->user())
            ->whereKey($patronRequest->getKey())
            ->exists();
        if (! $allowed) {
            abort(403);
        }

        // Destructive action: restrict to admins.
        $staffUser = $this->currentStaffUser($httpRequest);
        if (! $staffUser || ! $staffUser->isAdmin()) {
            abort(403);
        }

        $patronRequest->delete();

        return redirect()
            ->route('request.staff.requests.index')
            ->with('success', "Request #{$patronRequest->id} deleted.");
    }
}
