<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_material_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('material_type_id')->constrained('material_types')->cascadeOnDelete();
            $table->string('label_override')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('step')->default(2);
            $table->json('conditional_logic')->nullable(); // { match: 'all'|'any', rules: [...] }
            $table->timestamps();

            $table->unique(['form_id', 'material_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_material_types');
    }
};
