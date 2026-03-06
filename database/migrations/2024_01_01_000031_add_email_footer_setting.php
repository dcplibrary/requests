<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'email_footer_text'],
            [
                'key'         => 'email_footer_text',
                'value'       => 'Please do not reply to this message. Replies will not be routed to or seen by library staff. If you have any comments, please contact us at your library.',
                'label'       => 'Email Footer Text',
                'type'        => 'text',
                'group'       => 'notifications',
                'description' => 'Text shown in the footer of every notification email sent to patrons and staff.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        Cache::forget('setting:email_footer_text');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'email_footer_text')->delete();
        Cache::forget('setting:email_footer_text');
    }
};
