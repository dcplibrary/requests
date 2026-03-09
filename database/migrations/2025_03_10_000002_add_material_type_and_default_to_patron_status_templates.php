<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patron_status_templates', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('sort_order');
        });

        Schema::create('patron_status_template_material_type', function (Blueprint $table) {
            $table->foreignId('patron_status_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['patron_status_template_id', 'material_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_status_template_material_type');
        Schema::table('patron_status_templates', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
