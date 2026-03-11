<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified EAV for all field values on a request.
     * Replaces request_custom_field_values (with custom_field_id → field_id).
     * Also stores values that previously lived as columns on requests
     * (material_type, audience, genre, where_heard, etc.).
     */
    public function up(): void
    {
        Schema::create('request_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_field_values');
    }
};
