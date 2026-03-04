<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'polaris_barcode_check_enabled'],
            [
                'key'         => 'polaris_barcode_check_enabled',
                'value'       => '1',
                'label'       => 'Enable Polaris Barcode Check',
                'type'        => 'boolean',
                'group'       => 'polaris',
                'description' => 'When enabled, Step 1 of the request form checks the patron barcode against Polaris before proceeding. If the barcode is not found, the form is stopped and the message below is shown.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'barcode_not_found_message'],
            [
                'key'         => 'barcode_not_found_message',
                'value'       => '<p>The card number you entered was not found. Please <a href="#">apply for a card online</a> or visit the library to register.</p>',
                'label'       => 'Barcode Not Found Message',
                'type'        => 'html',
                'group'       => 'polaris',
                'description' => 'Shown on Step 1 of the request form when the patron\'s barcode is not found in Polaris. HTML is allowed — you can include links and your library\'s address.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        Cache::forget('setting:polaris_barcode_check_enabled');
        Cache::forget('setting:barcode_not_found_message');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'polaris_barcode_check_enabled')->delete();
        DB::table('settings')->where('key', 'barcode_not_found_message')->delete();
        Cache::forget('setting:polaris_barcode_check_enabled');
        Cache::forget('setting:barcode_not_found_message');
    }
};
