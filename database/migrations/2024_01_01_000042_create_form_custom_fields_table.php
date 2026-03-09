<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('sfp_custom_fields')->cascadeOnDelete();
            $table->string('label_override')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('step')->default(2);
            $table->json('conditional_logic')->nullable(); // { match: 'all'|'any', rules: [...] }
            $table->timestamps();

            $table->unique(['form_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_custom_fields');
    }
};
