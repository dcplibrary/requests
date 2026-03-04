<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'patron_lookup_enabled'],
            [
                'key'         => 'patron_lookup_enabled',
                'value'       => '1',
                'label'       => 'Enable Patron Request Lookup',
                'type'        => 'boolean',
                'group'       => 'patron',
                'description' => 'Allow patrons to look up their own submitted suggestions and statuses at /my-requests using their library card number and last name.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        Cache::forget('setting:patron_lookup_enabled');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'patron_lookup_enabled')->delete();
        Cache::forget('setting:patron_lookup_enabled');
    }
};
