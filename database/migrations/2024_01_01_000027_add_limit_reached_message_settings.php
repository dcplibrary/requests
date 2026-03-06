<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds two configurable message templates for the request-limit-reached banner.
 *
 * Available tokens:
 *   {limit}   — the numeric cap (e.g. 5)
 *   {period}  — the window description (e.g. "every 30 days", "per calendar month")
 *   {until}   — the formatted reset date (e.g. "March 29, 2026")
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            [
                'key'         => 'limit_reached_message',
                'value'       => 'You have reached the limit of {limit} suggestions {period}.',
                'label'       => 'Limit Reached Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown when a patron hits their request limit. Tokens: {limit}, {period}',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'limit_until_message',
                'value'       => 'You won\'t be able to submit another suggestion until {until}.',
                'label'       => 'Limit Until Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown below the limit message when a reset date is known. Token: {until}',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'limit_reached_message',
            'limit_until_message',
        ])->delete();
    }
};
