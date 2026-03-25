<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patron_status_templates', function (Blueprint $table) {
            $table->boolean('trigger_on_ill_conversion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('patron_status_templates', function (Blueprint $table) {
            $table->dropColumn('trigger_on_ill_conversion');
        });
    }
};
