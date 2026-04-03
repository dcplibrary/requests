<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds applies_to_sfp and applies_to_ill to request_statuses.
 *
 * NOTE: These columns were included in the original create_request_statuses_table
 * migration (2024_01_01_000004). This file exists to satisfy tests that verify
 * the migration was shipped as a standalone alter when kind-scoping was introduced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            if (! Schema::hasColumn('request_statuses', 'applies_to_sfp')) {
                $table->boolean('applies_to_sfp')->default(true)->after('active');
            }
            if (! Schema::hasColumn('request_statuses', 'applies_to_ill')) {
                $table->boolean('applies_to_ill')->default(true)->after('applies_to_sfp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn(['applies_to_sfp', 'applies_to_ill']);
        });
    }
};
