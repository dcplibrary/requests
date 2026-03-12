<?php

namespace Dcplibrary\Requests\Services;

use Dcplibrary\Requests\Mail\RequestMail;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\PatronStatusTemplate;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\PatronRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    // ── Public notification methods ───────────────────────────────────────────

    /**
     * Send staff routing email(s) when a new request is submitted.
     *
     * Recipients are looked up by finding active SelectorGroups whose
     * material type AND audience scope both match the request, and which
     * have a notification_emails value set.
     */
    public function notifyStaffNewRequest(PatronRequest $request): void
    {
        if (! Setting::get('notifications_enabled', true)) return;
        if (! Setting::get('staff_routing_enabled', true)) return;

        $recipients = $this->getStaffRecipients($request);
        if (empty($recipients)) return;

        $request->loadMissing(['patron', 'fieldValues.field', 'status']);

        $subject = $this->replacePlaceholders(
            (string) Setting::get('staff_routing_subject', 'New Purchase Suggestion: {title}'),
            $request
        );
        $bodyTemplate = (string) Setting::get('staff_routing_template', $this->defaultStaffTemplate());
        $body = $this->replacePlaceholders($bodyTemplate, $request);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new RequestMail($subject, $body));
            } catch (\Throwable $e) {
                Log::error('Staff routing email failed', [
                    'to'         => $email,
                    'request_id' => $request->id,
                    'error'      => $e->getMessage(),
                ]);
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

        $patronEmail = $request->patron?->email;
        if (! $patronEmail) return false;

        $statusId = $request->request_status_id;
        $templates = PatronStatusTemplate::query()
            ->where('enabled', true)
            ->whereHas('requestStatuses', fn ($q) => $q->where('request_statuses.id', $statusId))
            ->ordered()
            ->get();

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
            } catch (\Throwable $e) {
                Log::error('Patron status email failed', [
                    'patron_id'  => $request->patron_id,
                    'request_id' => $request->id,
                    'error'      => $e->getMessage(),
                ]);
            }
            return true;
        }

        foreach ($templates as $template) {
            try {
                $subject = $this->replacePlaceholders($template->subject, $request);
                $body = $this->replacePlaceholders((string) $template->body, $request);
                Mail::to($patronEmail)->send(new RequestMail($subject, $body));
            } catch (\Throwable $e) {
                Log::error('Patron status email failed', [
                    'patron_id'  => $request->patron_id,
                    'request_id' => $request->id,
                    'template_id'=> $template->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return true;
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

    // ── Private helpers ───────────────────────────────────────────────────────

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

        if ($request->request_kind === 'ill') {
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
     *   {status}            — current status name
     *   {status_description} — description text for the current status (for patron emails)
     *   {submitted_date}    — submission date (e.g. January 5, 2026)
     *   {request_url}       — full URL to the request in the staff dashboard
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

        $map = [
            '{title}'             => $request->submitted_title  ?? '',
            '{author}'            => $request->submitted_author ?? '',
            '{patron_name}'       => $patronName,
            '{patron_first_name}' => $patron?->name_first        ?? '',
            '{patron_email}'      => $patron?->effective_email   ?? '',
            '{patron_phone}'      => $patron?->effective_phone  ?? '',
            '{material_type}'     => $request->fieldValueLabel('material_type') ?? '',
            '{audience}'          => $request->fieldValueLabel('audience')       ?? '',
            '{status}'            => $request->status?->name       ?? '',
            '{status_description}' => $request->status?->description ?? '',
            '{submitted_date}'    => $request->created_at?->format('F j, Y') ?? '',
            '{request_url}'       => route('request.staff.requests.show', $request),
        ];

        // Extend with dynamic field tokens from the unified fields table.
        $request->loadMissing('fieldValues.field');
        $kind = $request->request_kind ?: 'sfp';
        $tokenFields = Field::query()
            ->where('active', true)
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

        return str_replace(array_keys($map), array_values($map), $template);
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
            'isbn'          => $request->material?->isbn ?? $request->material?->isbn13 ?? '',
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
