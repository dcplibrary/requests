<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-form field configuration — replaces form_form_fields + form_custom_fields.
     * Controls visibility, ordering, and required state of each field per form.
     */
    public function up(): void
    {
        Schema::create('form_field_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('label_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedTinyInteger('step')->default(2);
            $table->json('conditional_logic')->nullable();
            $table->timestamps();

            $table->unique(['form_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_config');
    }
};
