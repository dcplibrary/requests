<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refresh the auto_claim_enabled setting description to match the simpler
 * model: the toggle controls only the on-open auto-claim. Status updates
 * always claim the request to the acting staff user, regardless of this
 * setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'auto_claim_enabled')
            ->update([
                'description' => 'When enabled, the first staff user to open an unassigned request is automatically assigned to it. Disable to require staff to claim manually via the Claim button. Changing a request\'s status always claims it regardless of this setting. Has no effect when Enable Request Assignment is off.',
                'updated_at'  => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'auto_claim_enabled')
            ->update([
                'description' => 'When enabled, opening an unassigned request automatically assigns it to the staff user viewing it (and likewise on a status update). Disable to require staff to claim manually. Has no effect when Enable Request Assignment is off.',
                'updated_at'  => now(),
            ]);
    }
};
