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
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PatronRequest::with(['patron', 'material', 'status', 'assignedTo', 'fieldValues.field'])
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
        $assigned = $request->get('assigned');
        if ($assignmentEnabled && is_string($assigned) && $assigned !== '') {
            $currentStaffUser = $this->currentStaffUser($request);
            if ($assigned === 'me' && $currentStaffUser) {
                $query->where('assigned_to_user_id', $currentStaffUser->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            }
        }

        // Filter by dynamic fields (select/radio with value slug)
        $cfKey = $request->get('cf');
        $cfValue = $request->get('cf_value');
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

        return view('requests::staff.requests.index', [
            'requests'       => $requests,
            'statuses'       => RequestStatus::active()->get(),
            'materialTypes'  => $mtField ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->get() : collect(),
            'audiences'      => $audField ? FieldOption::where('field_id', $audField->id)->active()->ordered()->get() : collect(),
            'filters'        => $request->only(['kind', 'status', 'material_type', 'audience', 'search', 'cf', 'cf_value', 'assigned']),
            'currentKind'    => $kind,
            'customFilterFields'  => $customFilterFields,
            'customFilterOptions' => $customFilterOptions,
            'assignmentEnabled'   => $assignmentEnabled,
            'hasIllAccess'        => $hasIllAccess,
        ]);
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
        $justClaimed = false;
        $assignmentEnabled = (bool) Setting::get('assignment_enabled', false);
        if ($assignmentEnabled && ! $patronRequest->assigned_to_user_id) {
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
                $patronRequest->load(['assignedTo', 'assignedBy', 'statusHistory.status', 'statusHistory.user']);
                $justClaimed = true;
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

        // Reroute: filterable select/radio fields that have group-assigned options.
        $rerouteFields = collect();
        if ($assignmentEnabled) {
            $rerouteFields = Field::query()
                ->where('filterable', true)
                ->whereIn('type', ['select', 'radio'])
                ->where('active', true)
                ->whereHas('options', function ($q) {
                    $q->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('selector_group_field_option')
                            ->whereColumn('selector_group_field_option.field_option_id', 'field_options.id');
                    });
                })
                ->with(['options' => fn ($q) => $q->active()->ordered()])
                ->ordered()
                ->get();
        }

        return view('requests::staff.requests.show', [
            'patronRequest' => $patronRequest,
            'statuses'   => RequestStatus::active()->get(),
            'fieldValueLabelByFieldId' => $fieldValueLabelByFieldId,
            'assignmentEnabled' => $assignmentEnabled,
            'justClaimed' => $justClaimed,
            'staffUsers' => StaffUser::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'showConvertToIll' => $showConvertToIll,
            'rerouteFields' => $rerouteFields,
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
        ]);

        $actor = $this->currentStaffUser($httpRequest);
        $newAssigneeId = $data['assigned_to_user_id'] ?? null;

        $was = $patronRequest->assigned_to_user_id;
        $patronRequest->update([
            'assigned_to_user_id' => $newAssigneeId,
            'assigned_at' => $newAssigneeId ? now() : null,
            'assigned_by_user_id' => $actor?->id,
        ]);

        $note = $newAssigneeId
            ? (($was ? 'Reassigned' : 'Assigned') . " to user #{$newAssigneeId}.")
            : 'Unassigned.';
        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id' => $actor?->id,
            'note' => $note,
        ]);

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
     * Reroute a request by changing its filterable field values and unassigning it.
     *
     * The request flows to whichever selector group covers the new field combination.
     * The next staff user in that group who opens it will auto-claim it.
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

        // Discover which fields are reroutable (filterable + have group-assigned options).
        $rerouteFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->whereHas('options', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('selector_group_field_option')
                        ->whereColumn('selector_group_field_option.field_option_id', 'field_options.id');
                });
            })
            ->get(['id', 'key', 'label']);

        if ($rerouteFields->isEmpty()) {
            return back()->withErrors(['error' => 'No reroutable fields configured.']);
        }

        // Build validation rules dynamically.
        $rules = [];
        foreach ($rerouteFields as $field) {
            $validSlugs = FieldOption::where('field_id', $field->id)->active()->pluck('slug')->all();
            $rules["fields.{$field->key}"] = ['required', 'string', 'in:' . implode(',', $validSlugs)];
        }
        $data = $httpRequest->validate($rules);
        $fieldInputs = $data['fields'] ?? [];

        // Load current values and apply changes.
        $patronRequest->load('fieldValues.field');
        $changes = [];

        foreach ($rerouteFields as $field) {
            $newSlug = $fieldInputs[$field->key] ?? null;
            if ($newSlug === null) {
                continue;
            }

            $existing = $patronRequest->fieldValues->first(fn ($v) => $v->field_id === $field->id);
            $oldSlug = $existing?->value;

            if ($oldSlug === $newSlug) {
                continue;
            }

            $oldLabel = $oldSlug
                ? (FieldOption::where('field_id', $field->id)->where('slug', $oldSlug)->value('name') ?? $oldSlug)
                : '(none)';
            $newLabel = FieldOption::where('field_id', $field->id)->where('slug', $newSlug)->value('name') ?? $newSlug;

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
            return back()->with('success', 'No changes needed — fields already match.');
        }

        // Unassign so the next group member who opens it auto-claims.
        $patronRequest->update([
            'assigned_to_user_id' => null,
            'assigned_at'         => null,
            'assigned_by_user_id' => null,
        ]);

        $actor = $this->currentStaffUser($httpRequest);
        $patronRequest->statusHistory()->create([
            'request_status_id' => $patronRequest->request_status_id,
            'user_id'           => $actor?->id,
            'note'              => 'Rerouted: ' . implode('; ', $changes) . '.',
        ]);

        return redirect()
            ->route('request.staff.requests.index')
            ->with('success', 'Request #' . $patronRequest->id . ' rerouted and unassigned.');
    }

    /**
     * Return JSON listing selector groups that would cover a given field combination.
     *
     * Used by the reroute form's Alpine.js to show a live "Will be visible to" preview.
     *
     * @param  Request        $httpRequest
     * @param  PatronRequest  $patronRequest
     * @return JsonResponse
     */
    public function reroutePreview(Request $httpRequest, PatronRequest $patronRequest): JsonResponse
    {
        abort_unless(Setting::get('assignment_enabled', false), 404);

        // Collect field slugs from query params (e.g. ?material_type=book&audience=adult).
        $rerouteFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->whereHas('options', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('selector_group_field_option')
                        ->whereColumn('selector_group_field_option.field_option_id', 'field_options.id');
                });
            })
            ->get(['id', 'key']);

        // For each field, resolve the slug to an option ID.
        $selectedOptionIds = [];
        foreach ($rerouteFields as $field) {
            $slug = $httpRequest->query($field->key);
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $optionId = FieldOption::where('field_id', $field->id)->where('slug', $slug)->value('id');
            if ($optionId) {
                $selectedOptionIds[$field->id] = $optionId;
            }
        }

        // Find groups that cover ALL selected field values (same logic as applySfpFieldScoping).
        // For each field: group must have matching option OR have no options for that field.
        $groups = SelectorGroup::active()
            ->with('fieldOptions')
            ->get()
            ->filter(function (SelectorGroup $group) use ($rerouteFields, $selectedOptionIds) {
                foreach ($rerouteFields as $field) {
                    $groupOptionIds = $group->fieldOptions
                        ->where('field_id', $field->id)
                        ->pluck('id')
                        ->all();

                    // If group has no options for this field, it's unrestricted — pass.
                    if (empty($groupOptionIds)) {
                        continue;
                    }

                    // If we have a selected option for this field, it must be in the group's options.
                    $selected = $selectedOptionIds[$field->id] ?? null;
                    if ($selected && ! in_array($selected, $groupOptionIds, true)) {
                        return false;
                    }
                }
                return true;
            })
            ->values();

        return response()->json([
            'groups' => $groups->map(fn (SelectorGroup $g) => [
                'id'   => $g->id,
                'name' => $g->name,
            ])->values(),
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
            app(NotificationService::class)->notifyStaffNewRequest($patronRequest->fresh());
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
