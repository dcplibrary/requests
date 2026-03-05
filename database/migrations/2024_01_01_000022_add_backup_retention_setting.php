<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'backup_retention_days',
            'value'       => '30',
            'group'       => 'backup',
            'label'       => 'Backup Retention',
            'description' => 'How many days to keep server-side backup files. Files older than this are removed when pruning runs.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'backup_retention_days')->delete();
    }
};
