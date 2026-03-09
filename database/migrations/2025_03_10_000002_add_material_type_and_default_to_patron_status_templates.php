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
            $table->unsignedBigInteger('patron_status_template_id');
            $table->unsignedBigInteger('material_type_id');
            $table->primary(['patron_status_template_id', 'material_type_id']);
            $table->foreign('patron_status_template_id', 'pst_mt_patron_tpl_fk')
                ->references('id')->on('patron_status_templates')->cascadeOnDelete();
            $table->foreign('material_type_id', 'pst_mt_material_type_fk')
                ->references('id')->on('material_types')->cascadeOnDelete();
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
