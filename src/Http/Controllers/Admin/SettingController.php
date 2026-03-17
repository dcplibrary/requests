<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Concerns\ProvidesEmailTokens;
use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Mail\RequestMail;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\PatronStatusTemplate;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * General package settings, notification templates, and form-field notification rules.
 */
class SettingController extends Controller
{
    use ProvidesEmailTokens;
    public function index()
    {
        return view('requests::staff.settings.index', [
            // Catalog/ISBNdb/Syndetics settings live on the dedicated Catalog tab.
            // Notification settings live on the dedicated Notifications tab.
            'settings' => Setting::allGrouped()->except(['catalog', 'isbndb', 'syndetics', 'notifications', 'backup'])
                ->sortKeys(),
        ]);
    }

    public function notifications()
    {
        $availableTokens = $this->availableTokens();

        $notifications = Setting::where('group', 'notifications')->orderBy('label')->get();
        $keyToTab = [
            'notifications_enabled'      => 'general',
            'email_preview_enabled'      => 'general',
            'email_editing_enabled'      => 'general',
            'email_footer_text'          => 'general',
            'staff_routing_enabled'      => 'emails',
            'staff_routing_subject'      => 'emails',
            'staff_routing_template'     => 'emails',
            'patron_status_notification_enabled' => 'emails',
            'patron_status_subject'      => 'emails',
            'patron_status_template'     => 'emails',
        ];
        $tabOrder = ['general', 'emails'];
        $keyOrder = [
            'general' => ['notifications_enabled', 'email_preview_enabled', 'email_editing_enabled', 'email_footer_text'],
            'emails'  => [], // Enable/preview/test live on each email's edit view
        ];
        $settingsByTab = [];
        foreach ($tabOrder as $tab) {
            $keys = $keyOrder[$tab] ?? [];
            $byKey = $notifications->filter(fn ($s) => ($keyToTab[$s->key] ?? null) === $tab)->keyBy('key');
            $settingsByTab[$tab] = collect($keys)->map(fn ($k) => $byKey->get($k))->filter()->values();
        }
        $notificationSettings = $settingsByTab['general']->values();

        $staffSubject = $notifications->firstWhere('key', 'staff_routing_subject');
        $staffEnabled = $notifications->firstWhere('key', 'staff_routing_enabled');
        $staffTitle = $notifications->firstWhere('key', 'staff_routing_title');
        $patronSubject = $notifications->firstWhere('key', 'patron_status_subject');
        $patronEnabled = $notifications->firstWhere('key', 'patron_status_notification_enabled');

        $subjectExcludedTokens = $this->subjectExcludedTokens();

        $mtField = Field::where('key', 'material_type')->first();
        $materialTypes = $mtField
            ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->get()
            : collect();
        $patronStatusTemplates = PatronStatusTemplate::with(['requestStatuses', 'fieldOptions'])->ordered()->get();

        return view('requests::staff.settings.notifications', [
            'settingsByTab'         => $settingsByTab,
            'notificationSettings'  => $notificationSettings,
            'keyToTab'              => $keyToTab,
            'availableTokens'       => $availableTokens,
            'subjectExcludedTokens' => $subjectExcludedTokens,
            'materialTypes'         => $materialTypes,
            'patronStatusTemplates' => $patronStatusTemplates,
            'staffSubject'          => $staffSubject,
            'staffEnabled'          => (bool) ($staffEnabled->value ?? false),
            'staffTitle'            => optional($staffTitle)->value ?? 'Staff routing',
            'patronSubject'         => $patronSubject,
            'patronEnabled'         => (bool) ($patronEnabled->value ?? false),
        ]);
    }

    /**
     * Edit staff routing email (subject + body). Form posts to settings.update.
     */
    public function staffEmailForm()
    {
        $notifications = Setting::where('group', 'notifications')->get()->keyBy('key');
        $staffEnabled = $notifications->get('staff_routing_enabled');
        $staffSubject = $notifications->get('staff_routing_subject');
        $staffTemplate = $notifications->get('staff_routing_template');
        $staffTitleSetting = $notifications->get('staff_routing_title');
        $staffMaterialTypeIdsSetting = $notifications->get('staff_routing_material_type_ids');
        $staffStatusIdsSetting = $notifications->get('staff_routing_status_ids');

        $availableTokens       = $this->availableTokens();
        $subjectExcludedTokens = $this->subjectExcludedTokens();

        $mtField = Field::where('key', 'material_type')->first();
        $materialTypes = $mtField
            ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->get()
            : collect();
        $requestStatuses = RequestStatus::orderBy('sort_order')->get();
        $titleValue = optional($staffTitleSetting)->value ?? 'Staff routing';
        $materialIds = (array) json_decode(optional($staffMaterialTypeIdsSetting)->value ?? '[]', true);
        $statusIds = (array) json_decode(optional($staffStatusIdsSetting)->value ?? '[]', true);

        return view('requests::staff.settings.notifications-staff-email', [
            'staffEnabled'           => (bool) (optional($staffEnabled)->value ?? false),
            'staffSubjectValue'      => optional($staffSubject)->value ?? '',
            'staffTemplateValue'     => optional($staffTemplate)->value ?? '',
            'staffTitleValue'        => $titleValue,
            'staffMaterialTypeIds'   => $materialIds,
            'staffStatusIds'         => $statusIds,
            'availableTokens'        => $availableTokens,
            'subjectExcludedTokens'  => $subjectExcludedTokens,
            'materialTypes'          => $materialTypes,
            'requestStatuses'        => $requestStatuses,
        ]);
    }

    /**
     * Edit default patron email (one fallback template). Form posts to settings.update.
     */
    public function defaultPatronEmailForm()
    {
        $notifications = Setting::where('group', 'notifications')->get()->keyBy('key');
        $patronEnabled = $notifications->get('patron_status_notification_enabled');
        $patronSubject = $notifications->get('patron_status_subject');
        $patronTemplate = $notifications->get('patron_status_template');

        // Patron email includes {status_description} as an available token.
        $availableTokens       = $this->availableTokens(includeStatusDescription: true);
        $subjectExcludedTokens = $this->subjectExcludedTokens();

        return view('requests::staff.settings.notifications-default-patron-email', [
            'patronEnabled'          => (bool) (optional($patronEnabled)->value ?? false),
            'patronSubjectValue'     => optional($patronSubject)->value ?? '',
            'patronTemplateValue'    => optional($patronTemplate)->value ?? '',
            'availableTokens'        => $availableTokens,
            'subjectExcludedTokens'  => $subjectExcludedTokens,
        ]);
    }

    /**
     * Render a live preview of the staff or patron email template in the browser.
     * Opens in a new tab — no admin chrome, just the raw email HTML.
     */
    public function previewEmail(string $type)
    {
        abort_unless(in_array($type, ['staff', 'patron']), 404);

        $ns = app(NotificationService::class);

        $templateKey = $type === 'staff'
            ? 'staff_routing_template'
            : 'patron_status_template';

        $template = Setting::get($templateKey, '');
        if (! $template) {
            $template = $type === 'staff'
                ? $ns->defaultStaffTemplate()
                : $ns->defaultPatronTemplate();
        }

        $body = str_replace(
            array_keys($this->samplePlaceholders()),
            array_values($this->samplePlaceholders()),
            $template
        );

        return view('requests::mail.notification', ['body' => $body]);
    }

    /**
     * Send a test email to the given address using sample placeholder data.
     */
    public function sendTestEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'type'  => 'required|in:staff,patron',
        ]);

        $ns = app(NotificationService::class);

        $templateKey = $data['type'] === 'staff'
            ? 'staff_routing_template'
            : 'patron_status_template';
        $subjectKey = $data['type'] === 'staff'
            ? 'staff_routing_subject'
            : 'patron_status_subject';

        $template = Setting::get($templateKey, '');
        if (! $template) {
            $template = $data['type'] === 'staff'
                ? $ns->defaultStaffTemplate()
                : $ns->defaultPatronTemplate();
        }

        $subject = str_replace(
            array_keys($this->samplePlaceholders()),
            array_values($this->samplePlaceholders()),
            Setting::get($subjectKey, '[Test] Notification Preview')
        );

        $body = str_replace(
            array_keys($this->samplePlaceholders()),
            array_values($this->samplePlaceholders()),
            $template
        );

        try {
            Mail::to($data['email'])->send(new RequestMail('[Test] ' . $subject, $body));
            return back()->with('test_success', "Test email sent to {$data['email']}.");
        } catch (\Throwable $e) {
            return back()->with('test_error', 'Failed to send: ' . $e->getMessage());
        }
    }

    /** Sample data used to populate tokens in preview and test emails. */
    private function samplePlaceholders(): array
    {
        return [
            '{title}'             => 'The Great Gatsby',
            '{author}'            => 'F. Scott Fitzgerald',
            '{patron_name}'       => 'Jane Doe',
            '{patron_first_name}' => 'Jane',
            '{patron_email}'      => 'jane.doe@example.com',
            '{patron_phone}'      => '(270) 555-0123',
            '{material_type}'     => 'Book',
            '{audience}'          => 'Adult',
            '{status}'            => 'On Order',
            '{status_description}' => 'Your request has been ordered and is on its way.',
            '{submitted_date}'    => now()->format('F j, Y'),
            '{request_url}'       => url('/request/staff/requests/1'),
            '{genre}'             => 'Fiction',
            '{console}'           => 'PlayStation 5',
            '{isbn}'              => '978-0-7432-7356-5',
            '{publish_date}'      => '2025-06-15',
            '{where_heard}'       => 'Library staff recommendation',
            '{ill_requested}'     => 'No',
            '{material_type}'     => 'Book',
            '{date_needed_by}'    => now()->addWeeks(3)->format('Y-m-d'),
            '{will_pay_up_to}'    => '10.00',
        ];
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'                   => 'required|array',
            'settings.*.key'             => 'required|string|exists:settings,key',
            'settings.*.value'          => 'nullable|string|max:65535',
            'patron_templates'           => 'nullable|array',
            'patron_templates.*.id'      => 'nullable|exists:patron_status_templates,id',
            'patron_templates.*.name'    => 'nullable|string|max:255',
            'patron_templates.*.enabled' => 'nullable|boolean',
            'patron_templates.*.subject' => 'nullable|string|max:500',
            'patron_templates.*.body'    => 'nullable|string|max:65535',
            'patron_templates.*.status_ids' => 'nullable|array',
            'patron_templates.*.status_ids.*' => 'integer|exists:request_statuses,id',
            'patron_templates.*.remove'  => 'nullable|boolean',
            'return_to'                 => 'nullable|string|in:notifications_emails',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value']);
        }

        if ($request->has('staff_material_type_ids')) {
            $ids = array_map('intval', (array) $request->input('staff_material_type_ids', []));
            Setting::set('staff_routing_material_type_ids', json_encode(array_values($ids)));
        }
        if ($request->has('staff_status_ids')) {
            $ids = array_map('intval', (array) $request->input('staff_status_ids', []));
            Setting::set('staff_routing_status_ids', json_encode(array_values($ids)));
        }

        if (isset($data['patron_templates'])) {
            $this->savePatronStatusTemplates($data['patron_templates']);
        }

        if (isset($data['return_to']) && $data['return_to'] === 'notifications_emails') {
            return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Settings saved.');
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Create/update/delete patron status templates and sync their request statuses.
     */
    private function savePatronStatusTemplates(array $rows): void
    {
        foreach ($rows as $row) {
            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            if (! empty($row['remove']) && $id) {
                PatronStatusTemplate::where('id', $id)->delete();
                continue;
            }
            // Skip empty new rows
            if (! $id && trim((string) ($row['name'] ?? '')) === '' && trim((string) ($row['subject'] ?? '')) === '') {
                continue;
            }
            $statusIds = array_values(array_filter(array_map('intval', $row['status_ids'] ?? [])));
            if ($id) {
                $template = PatronStatusTemplate::find($id);
                if ($template) {
                    $template->update([
                        'name'    => $row['name'] ?? $template->name,
                        'enabled' => (bool) ($row['enabled'] ?? $template->enabled),
                        'subject' => $row['subject'] ?? $template->subject,
                        'body'    => $row['body'] ?? $template->body,
                    ]);
                    $template->requestStatuses()->sync($statusIds);
                }
            } else {
                $template = PatronStatusTemplate::create([
                    'name'    => $row['name'] ?? 'New template',
                    'enabled' => (bool) ($row['enabled'] ?? true),
                    'subject' => $row['subject'] ?? 'Update on your suggestion: {title}',
                    'body'    => $row['body'] ?? null,
                    'sort_order' => PatronStatusTemplate::max('sort_order') + 1,
                ]);
                $template->requestStatuses()->sync($statusIds);
            }
        }
    }
}
