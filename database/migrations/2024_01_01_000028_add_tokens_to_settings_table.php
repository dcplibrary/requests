<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `tokens` JSON column to the settings table.
 *
 * When non-null, the settings UI renders a clickable token bar below the field.
 * Clicking a token inserts it at the cursor in both plain inputs and Trix editors.
 *
 * Example value: ["{limit}", "{period}"]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('tokens')->nullable()->after('description');
        });

        // Seed tokens for the limit-reached message settings
        DB::table('settings')
            ->where('key', 'limit_reached_message')
            ->update(['tokens' => json_encode(['{limit}', '{period}'])]);

        DB::table('settings')
            ->where('key', 'limit_until_message')
            ->update(['tokens' => json_encode(['{until}'])]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('tokens');
        });
    }
};
