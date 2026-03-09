<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_types', function (Blueprint $table) {
            $table->boolean('ill_enabled')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('material_types', function (Blueprint $table) {
            $table->dropColumn('ill_enabled');
        });
    }
};
