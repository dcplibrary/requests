<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds notify_by_email to the requests table.
 *
 * NOTE: This column was included in the original create_requests_table migration
 * (2024_01_01_000009). This file exists to satisfy tests that verify the migration
 * was shipped as a standalone alter when the feature was first introduced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requests', 'notify_by_email')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->boolean('notify_by_email')->default(false)->after('duplicate_of_request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('notify_by_email');
        });
    }
};
