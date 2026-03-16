<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->string('action_label', 50)->nullable()->after('icon')
                  ->comment('Short verb shown as an action button in staff routing emails (e.g. "Review", "Purchase", "Deny").');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn('action_label');
        });
    }
};
