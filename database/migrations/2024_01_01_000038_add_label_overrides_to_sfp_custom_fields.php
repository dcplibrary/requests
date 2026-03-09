<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sfp_custom_fields', function (Blueprint $table) {
            $table->json('label_overrides')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('sfp_custom_fields', function (Blueprint $table) {
            $table->dropColumn('label_overrides');
        });
    }
};
