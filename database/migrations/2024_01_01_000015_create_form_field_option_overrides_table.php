<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-form overrides for field options — replaces form_form_field_options + form_custom_field_options.
     * Allows each form to customise labels, visibility, and order of individual options.
     */
    public function up(): void
    {
        Schema::create('form_field_option_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('option_slug');
            $table->string('label_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'field_id', 'option_slug'], 'form_field_opt_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_option_overrides');
    }
};
