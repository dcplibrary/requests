<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->string('action_label', 50)->nullable()
                  ->comment('Short verb shown as an action button in staff routing emails (e.g. "Review", "Purchase", "Deny").');
            $table->boolean('advance_on_claim')->default(false)
                  ->comment('When true, a request on this status is automatically advanced to the next status (by sort_order) when claimed by a staff member.');
            $table->boolean('applies_to_sfp')->default(true)
                  ->comment('Whether this status is available for Suggest for Purchase requests.');
            $table->boolean('applies_to_ill')->default(true)
                  ->comment('Whether this status is available for Interlibrary Loan requests.');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn(['action_label', 'advance_on_claim', 'applies_to_sfp', 'applies_to_ill']);
        });
    }
};
