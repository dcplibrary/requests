<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Insert the auto_claim_enabled setting for existing installs.
 *
 * Defaults to true so existing sites preserve the previous behaviour where
 * opening an unassigned request auto-claims it. Admins can disable the
 * toggle in Settings → Staff to require manual claiming instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['key' => 'auto_claim_enabled'],
            [
                'key'         => 'auto_claim_enabled',
                'value'       => '1',
                'label'       => 'Auto-claim on open',
                'type'        => 'boolean',
                'group'       => 'staff',
                'description' => 'When enabled, opening an unassigned request automatically assigns it to the staff user viewing it (and likewise on a status update). Disable to require staff to claim manually. Has no effect when Enable Request Assignment is off.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'auto_claim_enabled')->delete();
    }
};
