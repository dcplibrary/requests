<?php

namespace Dcplibrary\Requests\Services;

use Dcplibrary\Requests\Mail\RequestMail;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\PatronStatusTemplate;
use Dcplibrary\Requests\Models\StaffRoutingTemplate;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatusHistory;
use Dcplibrary\Requests\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Staff routing and patron-facing emails for requests (placeholders, templates, mail send).
 */
class NotificationService
{
    // ── Public notification methods ───────────────────────────────────────────

    /**
     * Send staff routing email(s) when a new request is submitted.
     *
     * Recipients are looked up by finding active SelectorGroups whose
     * material type AND audience scope both match the request, and which
     * Each matching selector group receives its own send (custom template if configured).
     */
    public function notifyStaffNewRequest(PatronRequest $request): void
    {
        if (! Setting::get('notifications_enabled', true)) return;
        if (! Setting::get('staff_routing_enabled', true)) return;

        $groups = $this->getStaffRecipientGroups($request);
        if ($groups->isEmpty()) return;

        $request->loadMissing(['patron', 'fieldValues.field', 'status']);

        $templatesByGroup = StaffRoutingTemplate::query()
            ->whereIn('selector_group_id', $groups->pluck('id'))
            ->where('enabled', true)
            ->get()
            ->keyBy('selector_group_id');

        $defaultSubject = (string) Setting::get('staff_routing_subject', 'New Purchase Suggestion: {title}');
        $globalBody = (string) Setting::get('staff_routing_template', '');
        $defaultBodyTpl = $globalBody !== '' ? $globalBody : $this->defaultStaffTemplate();

        foreach ($groups as $group) {
            $emails = [];
            $this->collectEmailsFromGroup($group, $emails);
            $recipients = array_values(array_unique(array_filter($emails)));
            if ($recipients === []) {
                continue;
            }

            $tpl = $templatesByGroup->get($group->id);
            $subjectTpl = $tpl && trim((string) $tpl->subject) !== ''
                ? (string) $tpl->subject
                : $defaultSubject;
            $bodyTpl = $tpl && trim((string) ($tpl->body ?? '')) !== ''
                ? (string) $tpl->body
                : $defaultBodyTpl;

            $subject = $this->replacePlaceholders($subjectTpl, $request);
            $body = $this->finalizeStaffRoutingBody($bodyTpl, $request);

            $sentTo = [];
            foreach ($recipients as $email) {
                try {
                    Mail::to($email)->send(new RequestMail($subject, $body));
                    $sentTo[] = $email;
                } catch (\Throwable $e) {
                    Log::error('Staff routing email failed', [
                        'to'         => $email,
                        'request_id' => $request->id,
                        'group_id'   => $group->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
            if ($sentTo !== []) {
                $this->logNotificationHistory(
                    $request,
                    RequestStatusHistory::ACTIVITY_STAFF_ROUTING,
                    'Staff routing email to ' . implode(', ', $sentTo)
                        . ' (group: ' . ($group->name ?? '—') . '). Subject: ' . Str::limit($subject, 140)
                );
            }
        }
    }

    /**
     * Send the patron a status-change email when a request transitions status.
     *
     * Only fires when:
     *  - `notifications_enabled` is on
     *  - `patron_status_notification_enabled` is on
     *  - The new RequestStatus has `notify_patron = true`
     *  - The patron has an email address on record
     */
    public function notifyPatronStatusChange(PatronRequest $request): bool
    {
        if (! Setting::get('notifications_enabled', true)) return false;
        if (! Setting::get('patron_status_notification_enabled', true)) return false;

        $request->loadMissing(['patron', 'fieldValues.field', 'status']);

        if (! $request->status?->notify_patron) return false;

        // Patron must have opted in to email notifications when they submitted.
        if (! $request->notify_by_email) return false;

        $patronEmail = $request->patron?->effective_email ?? $request->patron?->email;
        if (! $patronEmail) return false;

        $statusId = $request->request_status_id;
        $templates = PatronStatusTemplate::query()
            ->where('enabled', true)
            ->whereHas('requestStatuses', fn ($q) => $q->where('request_statuses.id', $statusId))
            ->ordered()
            ->get();

        $statusName = $request->status?->name ?? 'Unknown';
        $sentCount  = 0;

        if ($templates->isEmpty()) {
            // Backward compat: no templates configured — use single setting
            $subject = $this->replacePlaceholders(
                (string) Setting::get('patron_status_subject', 'Update on your suggestion: {title}'),
                $request
            );
            $bodyTemplate = (string) Setting::get('patron_status_template', $this->defaultPatronTemplate());
            $body = $this->replacePlaceholders($bodyTemplate, $request);
            try {
                Mail::to($patronEmail)->send(new RequestMail($subject, $body));
                $sentCount = 1;
            } catch (\Throwable $e) {
                Log::error('Patron status email failed', [
                    'patron_id'  => $request->patron_id,
                    'request_id' => $request->id,
                    'error'      => $e->getMessage(),
                ]);
            }
            if ($sentCount > 0) {
                $this->logNotificationHistory(
                    $request,
                    RequestStatusHistory::ACTIVITY_PATRON_EMAIL,
                    'Patron notification sent to ' . $patronEmail . ' for status “' . $statusName . '”. Subject: ' . Str::limit($subject, 120)
                );
            }

            return $sentCount > 0;
        }

        foreach ($templates as $template) {
            try {
                $subject = $this->replacePlaceholders($template->subject, $request);
                $body = $this->replacePlaceholders((string) $template->body, $request);
                Mail::to($patronEmail)->send(new RequestMail($subject, $body));
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('Patron status email failed', [
                    'patron_id'  => $request->patron_id,
                    'request_id' => $request->id,
                    'template_id'=> $template->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($sentCount > 0) {
            $this->logNotificationHistory(
                $request,
                RequestStatusHistory::ACTIVITY_PATRON_EMAIL,
                $sentCount === 1
                    ? 'Patron notification sent to ' . $patronEmail . ' for status “' . $statusName . '”.'
                    : "Patron notification: {$sentCount} emails sent to {$patronEmail} for status “{$statusName}”."
            );
        }

        return $sentCount > 0;
    }

    /**
     * Render the patron status-change email for a given status without sending it.
     *
     * Runs the same gate checks as notifyPatronStatusChange(). Returns an
     * associative array with 'subject', 'body', and 'to' keys when an email
     * would be sent, or null when no email would be sent (gate checks fail,
     * patron has no email, etc.).
     *
     * @return array{subject: string, body: string, to: string}|null
     */
    public function renderPatronEmail(PatronRequest $request, int $statusId): ?array
    {
        if (! Setting::get('notifications_enabled', true)) return null;
        if (! Setting::get('patron_status_notification_enabled', true)) return null;

        $status = \Dcplibrary\Requests\Models\RequestStatus::find($statusId);
        if (! $status?->notify_patron) return null;

        $request->loadMissing(['patron', 'fieldValues.field']);

        $patronEmail = $request->patron?->email;
        if (! $patronEmail) return null;

        // Temporarily override the status relationship so placeholders resolve
        // against the new status rather than the current one.
        $request->setRelation('status', $status);
        $request->request_status_id = $statusId;

        $templates = \Dcplibrary\Requests\Models\PatronStatusTemplate::query()
            ->where('enabled', true)
            ->whereHas('requestStatuses', fn ($q) => $q->where('request_statuses.id', $statusId))
            ->ordered()
            ->get();

        if ($templates->isEmpty()) {
            $subject = $this->replacePlaceholders(
                (string) Setting::get('patron_status_subject', 'Update on your suggestion: {title}'),
                $request
            );
            $body = $this->replacePlaceholders(
                (string) Setting::get('patron_status_template', $this->defaultPatronTemplate()),
                $request
            );
        } else {
            // Use first matching template for preview.
            $template = $templates->first();
            $subject  = $this->replacePlaceholders($template->subject, $request);
            $body     = $this->replacePlaceholders((string) $template->body, $request);
        }

        return [
            'subject' => $subject,
            'body'    => $body,
            'to'      => $patronEmail,
        ];
    }

    /**
     * Notify the newly assigned staff user about a reassignment.
     *
     * Uses the staff routing template with a prepended header block showing
     * who performed the action, the date, and an optional note.
     *
     * @param  PatronRequest  $request   The request being reassigned.
     * @param  User           $assignee  The user being assigned to.
     * @param  User           $actor     The staff member who performed the action.
     * @param  string|null    $note      Optional note from the actor.
     * @return void
     */
    public function notifyAssignee(PatronRequest $request, User $assignee, User $actor, ?string $note = null): void
    {
        if (! Setting::get('notifications_enabled', true)) return;
        if (! Setting::get('staff_routing_enabled', true)) return;

        $email = $assignee->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) return;

        $request->loadMissing(['patron', 'fieldValues.field', 'status']);

        $header = $this->buildWorkflowHeader('Reassigned', $actor, $note);

        $subject = $this->replacePlaceholders($this->defaultStaffRoutingSubject(), $request);
        $body = $header . $this->finalizeStaffRoutingBody($this->defaultStaffRoutingBodyTemplate(), $request);

        try {
            Mail::to($email)->send(new \Dcplibrary\Requests\Mail\RequestMail($subject, $body));
            $this->logNotificationHistory(
                $request,
                RequestStatusHistory::ACTIVITY_STAFF_ASSIGNEE,
                'Assignee notification sent to ' . $email . '. Subject: ' . Str::limit($subject, 120)
            );
        } catch (\Throwable $e) {
            Log::error('Assignee notification failed', [
                'to'         => $email,
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify relevant staff groups about a workflow action (reroute or convert).
     *
     * Uses the staff routing template with a prepended header block showing
     * the action performed, who did it, the date, an optional note, and
     * (for reroutes) the field changes.
     *
     * @param  PatronRequest  $request  The request acted upon.
     * @param  string         $action   Human-readable action label (e.g. "Rerouted", "Converted to ILL").
     * @param  User           $actor    The staff member who performed the action.
     * @param  string|null    $note     Optional note from the actor.
     * @param  string[]       $changes  Field change descriptions (e.g. ["Type: Book → DVD"]).
     * @return void
     */
    public function notifyStaffWorkflowAction(
        PatronRequest $request,
        string $action,
        User $actor,
        ?string $note = null,
        array $changes = []
    ): void {
        if (! Setting::get('notifications_enabled', true)) return;
        if (! Setting::get('staff_routing_enabled', true)) return;

        $recipients = $this->getStaffRecipients($request);
        if (empty($recipients)) return;

        $request->loadMissing(['patron', 'fieldValues.field', 'status']);

        $header = $this->buildWorkflowHeader($action, $actor, $note, $changes);

        $subject = $this->replacePlaceholders($this->defaultStaffRoutingSubject(), $request);
        $body = $header . $this->finalizeStaffRoutingBody($this->defaultStaffRoutingBodyTemplate(), $request);

        $sentTo = [];
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new \Dcplibrary\Requests\Mail\RequestMail($subject, $body));
                $sentTo[] = $email;
            } catch (\Throwable $e) {
                Log::error('Staff workflow notification failed', [
                    'to'         => $email,
                    'request_id' => $request->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
        if ($sentTo !== []) {
            $this->logNotificationHistory(
                $request,
                RequestStatusHistory::ACTIVITY_STAFF_WORKFLOW,
                'Workflow notification (“' . $action . '”) sent to: ' . implode(', ', $sentTo)
            );
        }
    }

    /**
     * Record a successful notification in request activity history (best-effort).
     */
    private function logNotificationHistory(PatronRequest $request, string $activityType, string $note): void
    {
        if (! $request->getKey()) {
            return;
        }
        try {
            $req = $request->fresh();
            if ($req) {
                $req->logNotificationActivity($activityType, $note);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not log notification to activity history', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function defaultStaffRoutingSubject(): string
    {
        return (string) Setting::get('staff_routing_subject', 'New Purchase Suggestion: {title}');
    }

    private function defaultStaffRoutingBodyTemplate(): string
    {
        $tpl = (string) Setting::get('staff_routing_template', '');

        return $tpl !== '' ? $tpl : $this->defaultStaffTemplate();
    }

    /**
     * Return unique, valid email addresses for staff routing: selectors who are
     * in the groups that have access to this request, plus any group-level
     * notification_emails (e.g. shared inboxes).
     *
     * - ILL requests: the group identified by ill_selector_group_id.
     * - SFP requests: groups whose material type AND audience match the request.
     */
    private function getStaffRecipients(PatronRequest $request): array
    {
        $groups = $this->getStaffRecipientGroups($request);
        $emails = [];

        foreach ($groups as $group) {
            $this->collectEmailsFromGroup($group, $emails);
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Groups that have access to this request (and thus should receive routing).
     *
     * Uses the same "unrestricted" logic as applySfpFieldScoping(): if a group
     * has no options assigned for a given filterable field, that field is treated
     * as unrestricted (matches everything) for that group.
     */
    private function getStaffRecipientGroups(PatronRequest $request): \Illuminate\Support\Collection
    {
        $groups = collect();

        if ($request->request_kind === PatronRequest::KIND_ILL) {
            $illGroupId = (int) Setting::get('ill_selector_group_id', 0);
            if ($illGroupId > 0) {
                $illGroup = SelectorGroup::active()->with('users')->find($illGroupId);
                if ($illGroup) {
                    $groups->push($illGroup);
                }
            }
        } else {
            $request->loadMissing('fieldValues.field');

            // Discover filterable fields that have group-assigned options.
            $scopingFields = Field::query()
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

            // Resolve each field's slug to an option ID.
            $selectedOptionIds = [];
            foreach ($scopingFields as $field) {
                $slug = $request->fieldValue($field->key);
                if ($slug) {
                    $optionId = FieldOption::where('field_id', $field->id)->where('slug', $slug)->value('id');
                    if ($optionId) {
                        $selectedOptionIds[$field->id] = $optionId;
                    }
                }
            }

            $candidates = SelectorGroup::active()
                ->with(['fieldOptions', 'users'])
                ->get();

            // For each group: every scoping field must either match or be unrestricted.
            $groups = $candidates->filter(function ($group) use ($scopingFields, $selectedOptionIds) {
                foreach ($scopingFields as $field) {
                    $groupOptionIds = $group->fieldOptions
                        ->where('field_id', $field->id)
                        ->pluck('id')
                        ->all();

                    // No options for this field = unrestricted — pass.
                    if (empty($groupOptionIds)) {
                        continue;
                    }

                    // If the request has a value for this field, it must be in the group's options.
                    $selected = $selectedOptionIds[$field->id] ?? null;
                    if ($selected && ! in_array($selected, $groupOptionIds, true)) {
                        return false;
                    }
                }
                return true;
            });
        }

        return $groups;
    }

    /**
     * Append valid emails from a group: notification_emails list plus active users.
     */
    private function collectEmailsFromGroup(SelectorGroup $group, array &$emails): void
    {
        if ($group->notification_emails) {
            foreach (preg_split('/[\s,]+/', $group->notification_emails) as $email) {
                $email = trim($email);
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }

        foreach ($group->users ?? [] as $user) {
            if ($user->active && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $user->email;
            }
        }
    }

    /**
     * Build an HTML header block prepended to staff routing emails for workflow actions.
     *
     * @param  string       $action   Action label (e.g. "Rerouted").
     * @param  User         $actor    Staff user who performed the action.
     * @param  string|null  $note     Optional note text.
     * @param  string[]     $changes  Optional field change descriptions.
     * @return string
     */
    private function buildWorkflowHeader(string $action, User $actor, ?string $note = null, array $changes = []): string
    {
        $date = now()->format('M j, Y g:ia');
        $name = e($actor->name ?: $actor->email);

        $html = '<div style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#374151;">';
        $html .= '<strong style="color:#111827;">' . e($action) . '</strong>';
        $html .= ' by ' . $name . ' &mdash; ' . $date;

        if ($note = trim((string) $note)) {
            $html .= '<div style="margin-top:6px;font-style:italic;color:#6b7280;">' . e($note) . '</div>';
        }

        if (! empty($changes)) {
            $html .= '<ul style="margin:6px 0 0;padding-left:18px;">';
            foreach ($changes as $change) {
                $html .= '<li>' . e($change) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Marker replaced after placeholder expansion so {action_buttons} is not stripped.
     */
    private const STAFF_ACTION_BUTTONS_MARKER = '<!--REQUESTS_ACTION_BUTTONS-->';

    /**
     * Expand staff routing body: optional {action_buttons} slot; otherwise append buttons at end.
     */
    private function finalizeStaffRoutingBody(string $template, PatronRequest $request): string
    {
        // Match {action_buttons} case-insensitively with optional spaces (rich text / paste variants).
        // Must run before replacePlaceholders(), which strips unknown {tokens} including a mistyped slot.
        $slotCount = 0;
        $template = (string) preg_replace(
            '/\{\s*action_buttons\s*\}/iu',
            self::STAFF_ACTION_BUTTONS_MARKER,
            $template,
            -1,
            $slotCount
        );
        $hasSlot = $slotCount > 0;

        $body = $this->replacePlaceholders($template, $request);
        $buttons = $this->buildEmailActionButtons($request, standalone: ! $hasSlot);
        if ($hasSlot) {
            return str_replace(self::STAFF_ACTION_BUTTONS_MARKER, $buttons, $body);
        }

        return $body . $buttons;
    }

    /**
     * Build an HTML block of one-click action buttons for staff routing emails.
     *
     * When the request is still SFP and the patron opted into ILL, a **Convert to ILL**
     * button (signed link, 30-day) is prepended in the same row as status shortcuts.
     * Status buttons use {@see RequestStatus::$action_label} or name; excludes current
     * and earlier sort_order.
     *
     * @param  bool  $standalone  When true (appended block): top border and spacing. When false
     *                           ({action_buttons} in body): compact block for mid-template use.
     */
    private function buildEmailActionButtons(PatronRequest $request, bool $standalone = true): string
    {
        $kind = $request->request_kind ?: PatronRequest::KIND_SFP;
        $currentId = (int) $request->request_status_id;
        $currentStatus = RequestStatus::find($currentId, ['sort_order']);
        $currentSort = $currentStatus ? (int) $currentStatus->sort_order : 0;

        $buttons = RequestStatus::query()
            ->where('active', true)
            ->forKind($request->request_kind ?: PatronRequest::KIND_SFP)
            ->where('sort_order', '>', $currentSort)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'action_label', 'color']);

        $convertCell = '';
        if ($kind === PatronRequest::KIND_SFP && $request->ill_requested) {
            $convertUrl = URL::temporarySignedRoute(
                'request.email-convert-to-ill',
                now()->addDays(30),
                ['patronRequest' => $request->getKey()]
            );
            $convertCell = '<td style="padding:0 12px 8px 0;vertical-align:middle;">'
                . '<a href="' . e($convertUrl) . '" '
                . 'style="display:inline-block;padding:8px 18px;background:#4338ca;color:#ffffff;'
                . 'text-decoration:none;border-radius:6px;font-size:13px;font-weight:bold;'
                . 'font-family:Arial,Helvetica,sans-serif;line-height:1.2;">'
                . 'Convert to ILL</a></td>';
        }

        $statusCells = '';
        foreach ($buttons as $status) {
            $url = URL::temporarySignedRoute(
                'request.email-action',
                now()->addDays(14),
                ['patronRequest' => $request->id, 'status_id' => $status->id]
            );

            $color = $status->color ?: '#4b5563';
            $label = trim((string) $status->action_label);
            $text = e($label !== '' ? $label : (string) $status->name);

            $statusCells .= '<td style="padding:0 12px 8px 0;vertical-align:middle;">';
            $statusCells .= '<a href="' . e($url) . '" '
                . 'style="display:inline-block;padding:8px 18px;background:' . e($color) . ';color:#ffffff;'
                . 'text-decoration:none;border-radius:6px;font-size:13px;font-weight:bold;'
                . 'font-family:Arial,Helvetica,sans-serif;line-height:1.2;">'
                . $text
                . '</a></td>';
        }

        if ($convertCell === '' && $statusCells === '') {
            return '';
        }

        if ($convertCell !== '' && $statusCells !== '') {
            $intro = '<p style="margin:0 0 12px;font-size:12px;color:#6b7280;font-style:italic;">'
                . 'Quick actions — <strong style="color:#374151;">Convert to ILL</strong> (patron opted in) or set status:</p>';
        } elseif ($convertCell !== '') {
            $intro = '<p style="margin:0 0 12px;font-size:12px;color:#6b7280;font-style:italic;">'
                . 'Patron asked to try interlibrary loan if not purchased — convert to the ILL workflow:</p>';
        } else {
            $intro = '<p style="margin:0 0 12px;font-size:12px;color:#6b7280;font-style:italic;">'
                . 'Quick actions — click to update status directly from this email:</p>';
        }

        $table = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>'
            . $convertCell . $statusCells . '</tr></table>';

        if ($standalone) {
            return '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;">'
                . $intro . $table . '</div>';
        }

        return '<div style="margin:16px 0;">' . $intro . $table . '</div>';
    }

    /**
     * Replace all {placeholder} tokens with request data.
     *
     * Core placeholders:
     *   {title}             — submitted title
     *   {author}            — submitted author
     *   {patron_name}       — patron full name
     *   {patron_first_name} — patron first name
     *   {patron_email}      — patron canonical email address
     *   {patron_phone}      — patron canonical phone number
     *   {material_type}     — material type name
     *   {audience}          — audience name
     *   {status}            — current status action_label if set, else status name
     *   {status_name}       — current status name (internal label)
     *   {status_description} — description text for the current status (for patron emails)
     *   {submitted_date}    — submission date (e.g. January 5, 2026)
     *   {request_url}       — full URL to the request in the staff dashboard
     *   {convert_to_ill_url} — signed convert URL (empty if not SFP+ill_requested). Same action as the
     *                          indigo **Convert to ILL** button in {action_buttons}; omit if you use that block.
     *   {convert_to_ill_link} — standalone button block; duplicates {action_buttons} if both are used.
     *
     * Dynamic placeholders (form fields with include_as_token = true):
     *   {isbn}              — ISBN from matched material
     *   {publish_date}      — submitted publish / release date
     *   {genre}             — genre name (resolved from slug)
     *   {console}           — console name (resolved from slug)
     *   {where_heard}       — patron's answer to "where did you hear about this?"
     *   {ill_requested}     — "Yes" or "No"
     *   {<key>}             — any other active form field value stored on the request
     */
    private function replacePlaceholders(string $template, PatronRequest $request): string
    {
        $patron     = $request->patron;
        $patronName = trim(($patron?->name_first ?? '') . ' ' . ($patron?->name_last ?? ''));

        $st = $request->status;
        $statusName   = $st?->name ?? '';
        $actionLabel  = trim((string) ($st?->action_label ?? ''));
        $statusDisplay = $actionLabel !== '' ? $actionLabel : $statusName;

        $map = [
            '{title}'             => $request->submitted_title  ?? '',
            '{author}'            => $request->submitted_author ?? '',
            '{patron_name}'       => $patronName,
            '{patron_first_name}' => $patron?->name_first        ?? '',
            '{patron_email}'      => $patron?->effective_email   ?? '',
            '{patron_phone}'      => $patron?->effective_phone  ?? '',
            '{material_type}'     => $request->fieldValueLabel('material_type') ?? '',
            '{audience}'          => $request->fieldValueLabel('audience')       ?? '',
            '{status}'            => $statusDisplay,
            '{status_name}'       => $statusName,
            '{status_description}' => $request->status?->description ?? '',
            '{submitted_date}'    => $request->created_at?->format('F j, Y') ?? '',
            '{request_url}'       => route('request.staff.requests.show', $request),
        ];

        // Extend with dynamic field tokens from the unified fields table.
        $request->loadMissing('fieldValues.field');
        $kind = $request->request_kind ?: PatronRequest::KIND_SFP;
        $tokenFields = Field::query()
            ->where('include_as_token', true)
            ->forKind($kind)
            ->ordered()
            ->get();

        foreach ($tokenFields as $field) {
            $token = "{{$field->key}}";
            if (isset($map[$token])) {
                continue; // already populated by core placeholders
            }

            $slug = $request->fieldValue($field->key);

            if ($slug === null || $slug === '') {
                // Fall back to direct model attributes for legacy columns.
                $map[$token] = $this->legacyFieldValue($field->key, $request);
                continue;
            }

            // For select/radio fields, resolve slug to human label.
            if (in_array($field->type, ['select', 'radio'], true)) {
                $map[$token] = $request->fieldValueLabel($field->key) ?? $slug;
            } elseif ($field->type === 'checkbox') {
                $map[$token] = $slug === '1' ? 'Yes' : ($slug === '0' ? 'No' : $slug);
            } else {
                $map[$token] = $slug;
            }
        }

        $map = array_merge($map, $this->convertToIllMailTokens($request));

        $result = str_replace(array_keys($map), array_values($map), $template);

        // Strip any remaining unrecognised {tokens} so they never appear literally.
        return preg_replace('/\{[a-z0-9_]+\}/i', '', $result);
    }

    /**
     * @return array<string, string>
     */
    private function convertToIllMailTokens(PatronRequest $request): array
    {
        if (
            ($request->request_kind ?: PatronRequest::KIND_SFP) !== PatronRequest::KIND_SFP
            || ! $request->ill_requested
        ) {
            return [
                '{convert_to_ill_url}' => '',
                '{convert_to_ill_link}' => '',
            ];
        }

        $url = URL::temporarySignedRoute(
            'request.email-convert-to-ill',
            now()->addDays(30),
            ['patronRequest' => $request->getKey()]
        );

        $safe = e($url);

        return [
            '{convert_to_ill_url}' => $url,
            '{convert_to_ill_link}' => '<p style="margin:16px 0;"><a href="'.$safe.'" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Convert to ILL</a></p>'
                .'<p style="font-size:12px;color:#6b7280;margin:0;">Patron asked to try interlibrary loan if not purchased. Use when you are ready to move this into the ILL workflow.</p>',
        ];
    }

    /**
     * Resolve legacy field values that may still live as direct model attributes
     * rather than in the EAV request_field_values table.
     *
     * @param  string         $key
     * @param  PatronRequest  $request
     * @return string
     */
    private function legacyFieldValue(string $key, PatronRequest $request): string
    {
        return match ($key) {
            'ill_requested' => $request->ill_requested === null ? '' : ($request->ill_requested ? 'Yes' : 'No'),
            'isbn'          => $request->material?->isbn13 ?? $request->material?->isbn ?? $request->fieldValue('isbn') ?? '',
            'publisher'     => (string) ($request->material?->publisher ?? ''),
            'publish_date'  => $request->submitted_publish_date ?? '',
            'where_heard'   => (string) ($request->where_heard ?? ''),
            'console'       => (string) ($request->other_material_text ?? ''),
            default         => (string) ($request->{$key} ?? ''),
        };
    }

    // ── Default templates (used as fallbacks before settings are customized) ──

    public function defaultStaffTemplate(): string
    {
        return <<<'HTML'
<h2 style="font-size:17px;font-weight:bold;margin:0 0 16px;color:#111827;">New Purchase Suggestion</h2>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Type</td>
    <td style="padding:5px 0;">{material_type}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Audience</td>
    <td style="padding:5px 0;">{audience}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Patron</td>
    <td style="padding:5px 0;">{patron_name}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Submitted</td>
    <td style="padding:5px 0;">{submitted_date}</td>
  </tr>
</table>
<p style="margin:20px 0 0;">
  <a href="{request_url}"
     style="display:inline-block;padding:10px 20px;background:#1d4ed8;color:#ffffff;
            text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;">
    View Request →
  </a>
</p>
{action_buttons}
HTML;
    }

    public function defaultPatronTemplate(): string
    {
        return <<<'HTML'
<p>Hi {patron_first_name},</p>
<p>We wanted to let you know that the status of your purchase suggestion has been updated.</p>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;margin:16px 0;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Status</td>
    <td style="padding:5px 0;font-weight:bold;">{status}</td>
  </tr>
</table>
<p>Thank you for your suggestion!</p>
HTML;
    }
}
