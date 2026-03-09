<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Mail\SfpMail;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\PatronStatusTemplate;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function index()
    {
        return view('sfp::staff.settings.index', [
            // Catalog/ISBNdb/Syndetics settings live on the dedicated Catalog tab.
            // Notification settings live on the dedicated Notifications tab.
            'settings' => Setting::allGrouped()->except(['catalog', 'isbndb', 'syndetics', 'notifications', 'backup'])
                ->sortKeys(),
        ]);
    }

    public function notifications()
    {
        // Tokens that are not form/custom fields (patron, status, dates, URL).
        $systemTokens = [
            '{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}',
            '{status}', '{submitted_date}', '{request_url}',
        ];

        // Form field keys — title, author, material_type, audience, genre, etc.
        $formFieldTokens = [];
        try {
            $formFieldTokens = FormField::ordered()
                ->pluck('key')
                ->map(fn (string $k) => "{{$k}}")
                ->all();
        } catch (\Throwable $e) {
            // Table may not exist or be empty during install
        }

        // Active custom field keys.
        $customFieldTokens = [];
        try {
            $customFieldTokens = CustomField::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->pluck('key')
                ->map(fn (string $k) => "{{$k}}")
                ->all();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Core request tokens (always include these so they’re always listed).
        $coreRequestTokens = ['{title}', '{author}', '{material_type}', '{audience}'];
        $availableTokens = array_values(array_unique(array_merge(
            $coreRequestTokens,
            $systemTokens,
            $formFieldTokens,
            $customFieldTokens
        )));

        $notifications = Setting::where('group', 'notifications')->orderBy('label')->get();
        $keyToTab = [
            'notifications_enabled' => 'general',
            'email_footer_text' => 'general',
            'staff_routing_enabled' => 'emails',
            'staff_routing_subject' => 'emails',
            'staff_routing_template' => 'emails',
            'patron_status_notification_enabled' => 'emails',
            'patron_status_subject' => 'emails',
            'patron_status_template' => 'emails',
        ];
        $tabOrder = ['general', 'emails'];
        $keyOrder = [
            'general' => ['notifications_enabled', 'email_footer_text'],
            'emails'  => [], // Enable/preview/test live on each email’s edit view
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

        // Tokens not offered for subject lines (body-only / material-type-specific).
        $subjectExcludedTokens = [
            '{will_pay_up_to}', '{ill_requested}', '{prefer_email}', '{prefer_mail}', '{other_specify}',
            '{publisher}', '{periodical_title}', '{article_author}', '{article_title}', '{volume_number}', '{page_number}',
            '{director}', '{cast}', '{comments}', '{request_url}', '{genre}', '{isbn}', '{publish_date}',
            '{where_heard}', '{date_needed_by}', '{console}', '{patron_email}', '{patron_phone}', '{audience}',
        ];

        $materialTypes = MaterialType::ordered()->get();
        $patronStatusTemplates = PatronStatusTemplate::with(['requestStatuses', 'materialTypes'])->ordered()->get();

        return view('sfp::staff.settings.notifications', [
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

        $systemTokens = ['{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}', '{status}', '{submitted_date}', '{request_url}'];
        $formFieldTokens = [];
        try {
            $formFieldTokens = FormField::ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        $customFieldTokens = [];
        try {
            $customFieldTokens = CustomField::query()->where('active', true)->where('include_as_token', true)->ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        $core = ['{title}', '{author}', '{material_type}', '{audience}'];
        $availableTokens = array_values(array_unique(array_merge($core, $systemTokens, $formFieldTokens, $customFieldTokens)));
        $subjectExcludedTokens = [
            '{will_pay_up_to}', '{ill_requested}', '{prefer_email}', '{prefer_mail}', '{other_specify}',
            '{publisher}', '{periodical_title}', '{article_author}', '{article_title}', '{volume_number}', '{page_number}',
            '{director}', '{cast}', '{comments}', '{request_url}', '{genre}', '{isbn}', '{publish_date}',
            '{where_heard}', '{date_needed_by}', '{console}', '{patron_email}', '{patron_phone}', '{audience}',
        ];

        $materialTypes = MaterialType::ordered()->get();
        $requestStatuses = RequestStatus::orderBy('sort_order')->get();
        $titleValue = optional($staffTitleSetting)->value ?? 'Staff routing';
        $materialIds = (array) json_decode(optional($staffMaterialTypeIdsSetting)->value ?? '[]', true);
        $statusIds = (array) json_decode(optional($staffStatusIdsSetting)->value ?? '[]', true);

        return view('sfp::staff.settings.notifications-staff-email', [
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

        $systemTokens = ['{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}', '{status}', '{status_description}', '{submitted_date}', '{request_url}'];
        $formFieldTokens = [];
        try {
            $formFieldTokens = FormField::ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        $customFieldTokens = [];
        try {
            $customFieldTokens = CustomField::query()->where('active', true)->where('include_as_token', true)->ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        $core = ['{title}', '{author}', '{material_type}', '{audience}'];
        $availableTokens = array_values(array_unique(array_merge($core, $systemTokens, $formFieldTokens, $customFieldTokens)));
        $subjectExcludedTokens = [
            '{will_pay_up_to}', '{ill_requested}', '{prefer_email}', '{prefer_mail}', '{other_specify}',
            '{publisher}', '{periodical_title}', '{article_author}', '{article_title}', '{volume_number}', '{page_number}',
            '{director}', '{cast}', '{comments}', '{request_url}', '{genre}', '{isbn}', '{publish_date}',
            '{where_heard}', '{date_needed_by}', '{console}', '{patron_email}', '{patron_phone}', '{audience}', '{status_description}',
        ];

        return view('sfp::staff.settings.notifications-default-patron-email', [
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

        return view('sfp::mail.sfp', ['body' => $body]);
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
            Mail::to($data['email'])->send(new SfpMail('[Test] ' . $subject, $body));
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
            '{request_url}'       => url('/sfp/staff/requests/1'),
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
