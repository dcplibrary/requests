<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified field definitions — replaces form_fields + custom_fields.
     * Every field used in requests (material_type, audience, genre, title, author, etc.)
     * is a regular row in this table.
     */
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->json('label_overrides')->nullable();
            $table->string('type', 30)->default('text'); // text|textarea|date|number|checkbox|select|radio
            $table->unsignedTinyInteger('step')->default(2);
            $table->string('scope', 10)->default('both'); // both|sfp|ill
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('required')->default(false);
            $table->boolean('include_as_token')->default(false);
            $table->boolean('filterable')->default(false);
            $table->json('condition')->nullable();
            $table->string('created_by')->nullable();
            $table->string('modified_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
