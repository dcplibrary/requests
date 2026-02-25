<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selector_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Users can belong to many groups
        Schema::create('selector_group_user', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['selector_group_id', 'user_id']);
        });

        // Groups are scoped to specific material types
        Schema::create('selector_group_material_type', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['selector_group_id', 'material_type_id']);
        });

        // Groups are scoped to specific audiences
        Schema::create('selector_group_audience', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->primary(['selector_group_id', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selector_group_audience');
        Schema::dropIfExists('selector_group_material_type');
        Schema::dropIfExists('selector_group_user');
        Schema::dropIfExists('selector_groups');
    }
};
