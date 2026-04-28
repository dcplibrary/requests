<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Insert staff_email_show_header and staff_email_show_footer settings.
 *
 * Both default to true so existing installations keep their current behaviour
 * (logo + footer visible in all emails) until an admin explicitly disables them
 * via Settings → Notifications → General.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'key'         => 'staff_email_show_header',
                'value'       => '1',
                'label'       => 'Staff Emails — Show Logo Header',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Show the library logo at the top of staff routing, assignee, and workflow emails. Disable to send plain body-only emails to staff.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'staff_email_show_footer',
                'value'       => '1',
                'label'       => 'Staff Emails — Show Footer',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Show the footer text at the bottom of staff routing, assignee, and workflow emails.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                $row,
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'staff_email_show_header',
            'staff_email_show_footer',
        ])->delete();
    }
};
