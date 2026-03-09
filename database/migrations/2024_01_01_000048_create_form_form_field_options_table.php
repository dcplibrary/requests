<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-form overrides for individual options within an option-type form field
 * (material_type, audience, genre, console).
 *
 * Each row stores the form-specific visibility, display order, and optional
 * label override for one option slug on one form. Rows are created on first
 * interaction (move / toggle); absence means "use the global default".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_form_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained('sfp_form_fields')->cascadeOnDelete();
            $table->string('option_slug');          // references slug on MaterialType / Audience / Genre / Console
            $table->string('label_override')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'form_field_id', 'option_slug'], 'form_ffopt_form_field_slug_unique');
            $table->index(['form_id', 'form_field_id', 'sort_order'], 'form_ffopt_form_field_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_form_field_options');
    }
};
