<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'email_footer_text')
            ->update(['type' => 'html', 'updated_at' => now()]);

        Cache::forget('setting:email_footer_text');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'email_footer_text')
            ->update(['type' => 'text', 'updated_at' => now()]);

        Cache::forget('setting:email_footer_text');
    }
};
