<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Insert the captcha_enabled setting for existing installs.
 *
 * Defaults to false so existing sites are not affected until an admin
 * adds Turnstile keys to .env and enables the toggle in Settings → Security.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['key' => 'captcha_enabled'],
            [
                'key'         => 'captcha_enabled',
                'value'       => '0',
                'label'       => 'Enable CAPTCHA',
                'type'        => 'boolean',
                'group'       => 'security',
                'description' => 'Show a Cloudflare Turnstile CAPTCHA on Step 1 of the patron request forms. Requires TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY in .env.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'captcha_enabled')->delete();
    }
};
