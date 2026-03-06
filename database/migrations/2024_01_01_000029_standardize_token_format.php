<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Standardizes the token format used throughout SFP settings.
 *
 * Previous format (colon-prefix):  :limit  :period  :until
 * Standard format (curly-brace):  {limit} {period} {until}
 *
 * The curly-brace format is already used everywhere in the Notifications
 * settings ({title}, {author}, etc.) and is now the single standard for
 * all settings tokens.
 *
 * Also seeds the `tokens` JSON column for Notification settings that
 * expose template fields to admins, so the Trix toolbar can display
 * contextual insert buttons for those fields.
 */
return new class extends Migration
{
    /** All tokens available in notification templates. */
    private const NOTIFICATION_TOKENS = [
        '{title}',
        '{author}',
        '{patron_name}',
        '{patron_first_name}',
        '{material_type}',
        '{audience}',
        '{status}',
        '{submitted_date}',
        '{request_url}',
    ];

    public function up(): void
    {
        // --- 1. Migrate existing limit-reached message values ----------------

        $reached = DB::table('settings')->where('key', 'limit_reached_message')->first();
        if ($reached) {
            $newValue = str_replace([':limit', ':period'], ['{limit}', '{period}'], $reached->value ?? '');
            DB::table('settings')->where('key', 'limit_reached_message')->update([
                'value'       => $newValue,
                'description' => 'Shown when a patron hits their request limit. Tokens: {limit}, {period}',
                'tokens'      => json_encode(['{limit}', '{period}']),
                'updated_at'  => now(),
            ]);
        }

        $until = DB::table('settings')->where('key', 'limit_until_message')->first();
        if ($until) {
            $newValue = str_replace(':until', '{until}', $until->value ?? '');
            DB::table('settings')->where('key', 'limit_until_message')->update([
                'value'       => $newValue,
                'description' => 'Shown below the limit message when a reset date is known. Token: {until}',
                'tokens'      => json_encode(['{until}']),
                'updated_at'  => now(),
            ]);
        }

        // --- 2. Seed tokens for Notification template settings ---------------
        //
        // The subject and body template fields support the full set of
        // notification tokens. Storing them on the setting row lets the
        // trix-editor component inject them into the toolbar automatically.

        $notifTokenJson = json_encode(self::NOTIFICATION_TOKENS);

        foreach (['staff_routing_subject', 'staff_routing_template',
                  'patron_status_subject', 'patron_status_template'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->update([
                    'tokens'     => $notifTokenJson,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // --- Revert limit-reached messages to colon-prefix format ------------

        $reached = DB::table('settings')->where('key', 'limit_reached_message')->first();
        if ($reached) {
            $oldValue = str_replace(['{limit}', '{period}'], [':limit', ':period'], $reached->value ?? '');
            DB::table('settings')->where('key', 'limit_reached_message')->update([
                'value'       => $oldValue,
                'description' => 'Shown when a patron hits their request limit. Tokens: :limit, :period',
                'tokens'      => json_encode([':limit', ':period']),
                'updated_at'  => now(),
            ]);
        }

        $until = DB::table('settings')->where('key', 'limit_until_message')->first();
        if ($until) {
            $oldValue = str_replace('{until}', ':until', $until->value ?? '');
            DB::table('settings')->where('key', 'limit_until_message')->update([
                'value'       => $oldValue,
                'description' => 'Shown below the limit message when a reset date is known. Token: :until',
                'tokens'      => json_encode([':until']),
                'updated_at'  => now(),
            ]);
        }

        // Remove tokens from notification settings
        foreach (['staff_routing_subject', 'staff_routing_template',
                  'patron_status_subject', 'patron_status_template'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->update([
                    'tokens'     => null,
                    'updated_at' => now(),
                ]);
        }
    }
};
