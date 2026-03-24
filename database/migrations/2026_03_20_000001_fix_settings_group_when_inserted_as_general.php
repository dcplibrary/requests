<?php

use Dcplibrary\Requests\Database\Seeders\SettingsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rows created by {@see \Dcplibrary\Requests\Models\Setting::set()} before 2026-03 used
 * group "general", so they were invisible to Notifications settings (group filter).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (SettingsSeeder::defaultSettings(0) as $row) {
            $expectedGroup = $row['group'] ?? 'general';
            if ($expectedGroup === 'general') {
                continue;
            }

            DB::table('settings')
                ->where('key', $row['key'])
                ->where('group', 'general')
                ->update([
                    'group' => $expectedGroup,
                    'type' => $row['type'] ?? 'string',
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible — we cannot know previous group was wrong on purpose.
    }
};
