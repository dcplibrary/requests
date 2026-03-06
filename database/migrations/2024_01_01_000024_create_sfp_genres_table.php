<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sfp_genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Genre-scoped selector groups (mirrors selector_group_material_type / selector_group_audience)
        Schema::create('selector_group_genre', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('sfp_genres')->cascadeOnDelete();
            $table->primary(['selector_group_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selector_group_genre');
        Schema::dropIfExists('sfp_genres');
    }
};
