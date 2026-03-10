<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $newSettings = [
            [
                'key'         => 'ill_limit_count',
                'value'       => '',
                'label'       => 'ILL Request Limit Count',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Maximum number of ILL requests a patron can submit within the limit window. Leave blank for unlimited.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'ill_limit_window_type',
                'value'       => 'rolling',
                'label'       => 'ILL Limit Window Type',
                'type'        => 'text',
                'group'       => 'request_limits',
                'description' => 'How the ILL submission limit window is measured (same options as SFP).',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'ill_limit_window_days',
                'value'       => '30',
                'label'       => 'ILL Rolling Window Length',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Days the ILL rolling window spans. Used only when ILL Limit Window Type is Rolling.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'ill_limit_calendar_reset_day',
                'value'       => '1',
                'label'       => 'ILL Monthly Reset Day',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Day of the month the ILL counter resets when using Calendar Month. Must be between 1 and 28.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'ill_limit_reached_message',
                'value'       => 'You have reached the limit of {limit} ILL requests {period}.',
                'label'       => 'ILL Limit Reached Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown when a patron hits their ILL request limit. Tokens: {limit}, {period}',
                'tokens'      => json_encode(['{limit}', '{period}']),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'ill_limit_until_message',
                'value'       => "You won't be able to submit another ILL request until {until}.",
                'label'       => 'ILL Limit Until Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown below the ILL limit message when a reset date is known. Token: {until}',
                'tokens'      => json_encode(['{until}']),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($newSettings as $row) {
            if (DB::table('settings')->where('key', $row['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert($row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'ill_limit_count',
            'ill_limit_window_type',
            'ill_limit_window_days',
            'ill_limit_calendar_reset_day',
            'ill_limit_reached_message',
            'ill_limit_until_message',
        ])->delete();
    }
};
