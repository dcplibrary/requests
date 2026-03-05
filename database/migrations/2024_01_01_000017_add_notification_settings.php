<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $placeholderNote = 'Available placeholders: {title}, {author}, {patron_name}, {patron_first_name}, {material_type}, {audience}, {status}, {submitted_date}, {request_url}.';

        $settings = [
            [
                'key'         => 'notifications_enabled',
                'value'       => '1',
                'label'       => 'Enable Notifications',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Master switch for all email notifications. Turn off to silence everything without changing individual settings.',
            ],

            // ── Staff routing ─────────────────────────────────────────────────
            [
                'key'         => 'staff_routing_enabled',
                'value'       => '1',
                'label'       => 'Enable Staff Routing Emails',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Send an email to the selector group(s) matching the request\'s material type and audience when a new request is submitted. Configure routing addresses on each Group.',
            ],
            [
                'key'         => 'staff_routing_subject',
                'value'       => 'New Purchase Suggestion: {title}',
                'label'       => 'Staff Routing — Subject',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Subject line for staff routing emails. ' . $placeholderNote,
            ],
            [
                'key'         => 'staff_routing_template',
                'value'       => '',
                'label'       => 'Staff Routing — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'HTML body for staff routing emails. Leave blank to use the built-in default. ' . $placeholderNote,
            ],

            // ── Patron status notification ─────────────────────────────────────
            [
                'key'         => 'patron_status_notification_enabled',
                'value'       => '1',
                'label'       => 'Enable Patron Status Emails',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Send an email to the patron when their request\'s status changes. Only fires for statuses that have "Notify Patron" checked. The patron must have an email on file.',
            ],
            [
                'key'         => 'patron_status_subject',
                'value'       => 'Update on your suggestion: {title}',
                'label'       => 'Patron Status — Subject',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Subject line for patron status-change emails. ' . $placeholderNote,
            ],
            [
                'key'         => 'patron_status_template',
                'value'       => '',
                'label'       => 'Patron Status — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'HTML body for patron status-change emails. Leave blank to use the built-in default. ' . $placeholderNote,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => now(), 'updated_at' => now()])
            );
            Cache::forget("setting:{$setting['key']}");
        }
    }

    public function down(): void
    {
        $keys = [
            'notifications_enabled',
            'staff_routing_enabled',
            'staff_routing_subject',
            'staff_routing_template',
            'patron_status_notification_enabled',
            'patron_status_subject',
            'patron_status_template',
        ];

        DB::table('settings')->whereIn('key', $keys)->delete();
        foreach ($keys as $key) {
            Cache::forget("setting:{$key}");
        }
    }
};
