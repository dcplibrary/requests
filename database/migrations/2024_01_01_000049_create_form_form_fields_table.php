<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained('sfp_form_fields')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('conditional_logic')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'form_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_form_fields');
    }
};
