<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'sfp_limit_window_type'],
            [
                'key'         => 'sfp_limit_window_type',
                'value'       => 'rolling',
                'label'       => 'Limit Window Type',
                'type'        => 'text',
                'group'       => 'patron',
                'description' => 'How the submission limit window is measured. Options: "rolling" (e.g. 5 requests in any 30-day window), "calendar_month" (resets on a fixed day each month), "calendar_week" (resets every Monday).',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'sfp_limit_calendar_reset_day'],
            [
                'key'         => 'sfp_limit_calendar_reset_day',
                'value'       => '1',
                'label'       => 'Monthly Reset Day',
                'type'        => 'integer',
                'group'       => 'patron',
                'description' => 'Day of the month the submission counter resets when using the "calendar_month" window type. Must be between 1 and 28.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        Cache::forget('setting:sfp_limit_window_type');
        Cache::forget('setting:sfp_limit_calendar_reset_day');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'sfp_limit_window_type',
            'sfp_limit_calendar_reset_day',
        ])->delete();

        Cache::forget('setting:sfp_limit_window_type');
        Cache::forget('setting:sfp_limit_calendar_reset_day');
    }
};
