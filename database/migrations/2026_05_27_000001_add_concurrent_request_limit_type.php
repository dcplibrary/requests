<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "concurrent" as a request limit type and defaults ILL to 5 open requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['sfp_limit_window_type', 'ill_limit_window_type'] as $key) {
            $label = str_starts_with($key, 'ill_') ? 'ILL Limit Type' : 'SFP Limit Type';
            $row   = DB::table('settings')->where('key', $key)->first();
            if ($row) {
                DB::table('settings')->where('key', $key)->update([
                    'label'       => $label,
                    'description' => 'Concurrent counts open (non-terminal) requests only. Rolling, Calendar Month, and Calendar Week count submissions within a date window.',
                    'updated_at'  => $now,
                ]);
            }
        }

        $illCount = DB::table('settings')->where('key', 'ill_limit_count')->first();
        if ($illCount && trim((string) ($illCount->value ?? '')) === '') {
            DB::table('settings')->where('key', 'ill_limit_count')->update([
                'value'       => '5',
                'description' => 'Maximum ILL requests per patron for the chosen limit type. Leave blank for unlimited.',
                'updated_at'  => $now,
            ]);
        }

        $illType = DB::table('settings')->where('key', 'ill_limit_window_type')->value('value');
        if ($illType === 'rolling' || $illType === null) {
            DB::table('settings')->where('key', 'ill_limit_window_type')->update([
                'value'      => 'concurrent',
                'updated_at' => $now,
            ]);
        }

        $illMessage = DB::table('settings')->where('key', 'ill_limit_reached_message')->first();
        if ($illMessage && str_contains((string) $illMessage->value, '{period}')) {
            DB::table('settings')->where('key', 'ill_limit_reached_message')->update([
                'value'       => 'You can only borrow {limit} items at a time. Please wait until an active request is completed.',
                'description' => 'Shown when a patron hits their ILL limit. Tokens: {limit}, {period} ({period} is empty for concurrent limits)',
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: limit type values are left as configured.
    }
};
