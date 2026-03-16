<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->boolean('applies_to_sfp')->default(true)->after('advance_on_claim')
                  ->comment('Whether this status is available for Suggest for Purchase requests.');
            $table->boolean('applies_to_ill')->default(true)->after('applies_to_sfp')
                  ->comment('Whether this status is available for Interlibrary Loan requests.');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn(['applies_to_sfp', 'applies_to_ill']);
        });
    }
};
