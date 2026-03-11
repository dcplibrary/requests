<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified option rows for select/radio fields.
     * Replaces custom_field_options, material_types, audiences, and genres.
     * Type-specific attributes (ill_enabled, bibliocommons_value, etc.) live in metadata JSON.
     */
    public function up(): void
    {
        Schema::create('field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('modified_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['field_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_options');
    }
};
