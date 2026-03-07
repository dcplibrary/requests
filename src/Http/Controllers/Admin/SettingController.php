<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Mail\SfpMail;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormField;
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
        // Core tokens always available in notification templates.
        $coreTokens = [
            '{title}', '{author}',
            '{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}',
            '{material_type}', '{audience}', '{status}',
            '{submitted_date}', '{request_url}',
        ];

        // Dynamic tokens from form fields flagged as token sources.
        $fieldTokens = FormField::tokenFields()
            ->pluck('key')
            ->map(fn (string $k) => "{{$k}}")
            ->all();

        // Dynamic tokens from custom fields flagged as token sources.
        $customFieldTokens = CustomField::query()
            ->where('active', true)
            ->where('include_as_token', true)
            ->orderBy('sort_order')
            ->pluck('key')
            ->map(fn (string $k) => "{{$k}}")
            ->all();

        return view('sfp::staff.settings.notifications', [
            'settings'        => Setting::allGrouped()->only(['notifications']),
            'availableTokens' => array_values(array_unique(array_merge($coreTokens, $fieldTokens, $customFieldTokens))),
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
            '{submitted_date}'    => now()->format('F j, Y'),
            '{request_url}'       => url('/sfp/staff/requests/1'),
            '{genre}'             => 'Fiction',
            '{console}'           => 'PlayStation 5',
            '{isbn}'              => '978-0-7432-7356-5',
            '{publish_date}'      => '2025-06-15',
            '{where_heard}'       => 'Library staff recommendation',
            '{ill_requested}'     => 'No',
            '{borrow_type}'       => 'Book',
            '{date_needed_by}'    => now()->addWeeks(3)->format('Y-m-d'),
            '{will_pay_up_to}'    => '10.00',
        ];
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable|string|max:65535',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value']);
        }

        return back()->with('success', 'Settings saved.');
    }
}
