<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'barcode_not_found_message'],
            [
                'key'         => 'barcode_not_found_message',
                'value'       => '<p>The card number you entered was not found. Please <a href="#">apply for a card online</a> or visit the library to register.</p>',
                'label'       => 'Barcode Not Found Message',
                'type'        => 'html',
                'group'       => 'messaging',
                'description' => 'Shown on Step 1 of the request form when the patron\'s barcode is not found in Polaris. The form is stopped and no request is created. HTML is allowed.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        Cache::forget('setting:barcode_not_found_message');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'barcode_not_found_message')->delete();
        Cache::forget('setting:barcode_not_found_message');
    }
};
