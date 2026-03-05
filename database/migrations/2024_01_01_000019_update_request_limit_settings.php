<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename patron_lookup_enabled to reflect PIN-based authentication.
        DB::table('settings')
            ->where('key', 'patron_lookup_enabled')
            ->update([
                'label'       => 'Enable Patron PIN Login',
                'description' => 'Allow patrons to sign in with their library card PIN at /my-requests to view and track their submitted requests.',
                'updated_at'  => now(),
            ]);

        // Move sfp_limit_count and sfp_limit_window_days out of rate_limiting
        // and into the unified request_limits group.
        DB::table('settings')
            ->whereIn('key', ['sfp_limit_count', 'sfp_limit_window_days'])
            ->update(['group' => 'request_limits', 'updated_at' => now()]);

        // Update sfp_limit_window_days label/description now that it lives next to
        // the window-type radio and is only visible when rolling mode is active.
        DB::table('settings')
            ->where('key', 'sfp_limit_window_days')
            ->update([
                'label'       => 'Rolling Window Length',
                'description' => 'How many days the rolling window spans (e.g. 30 means a patron may submit up to the limit count within any 30-day period).',
                'updated_at'  => now(),
            ]);

        // Move sfp_limit_window_type and sfp_limit_calendar_reset_day out of the
        // patron group and into the unified request_limits group.
        DB::table('settings')
            ->whereIn('key', ['sfp_limit_window_type', 'sfp_limit_calendar_reset_day'])
            ->update(['group' => 'request_limits', 'updated_at' => now()]);

        // Update sfp_limit_window_type description to avoid raw value references.
        DB::table('settings')
            ->where('key', 'sfp_limit_window_type')
            ->update([
                'label'       => 'Limit Window Type',
                'description' => 'How the submission limit window is measured: Rolling counts requests within a sliding day window; Calendar Month resets on a fixed day each month; Calendar Week resets every Monday.',
                'updated_at'  => now(),
            ]);

        foreach ([
            'patron_lookup_enabled',
            'sfp_limit_count',
            'sfp_limit_window_days',
            'sfp_limit_window_type',
            'sfp_limit_calendar_reset_day',
        ] as $key) {
            Cache::forget("setting:{$key}");
        }
    }

    public function down(): void
    {
        // Restore patron_lookup_enabled original wording.
        DB::table('settings')
            ->where('key', 'patron_lookup_enabled')
            ->update([
                'label'       => 'Enable Patron Request Lookup',
                'description' => 'Allow patrons to look up their own submitted suggestions and statuses at /my-requests using their library card number and last name.',
                'updated_at'  => now(),
            ]);

        // Restore original groups.
        DB::table('settings')
            ->whereIn('key', ['sfp_limit_count', 'sfp_limit_window_days'])
            ->update(['group' => 'rate_limiting', 'updated_at' => now()]);

        DB::table('settings')
            ->where('key', 'sfp_limit_window_days')
            ->update([
                'label'       => 'Request Limit Window',
                'description' => 'Time window for rate limiting (in days).',
                'updated_at'  => now(),
            ]);

        DB::table('settings')
            ->whereIn('key', ['sfp_limit_window_type', 'sfp_limit_calendar_reset_day'])
            ->update(['group' => 'patron', 'updated_at' => now()]);

        foreach ([
            'patron_lookup_enabled',
            'sfp_limit_count',
            'sfp_limit_window_days',
            'sfp_limit_window_type',
            'sfp_limit_calendar_reset_day',
        ] as $key) {
            Cache::forget("setting:{$key}");
        }
    }
};
