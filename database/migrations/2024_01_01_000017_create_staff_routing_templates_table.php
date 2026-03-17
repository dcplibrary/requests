<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_routing_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selector_group_id')->constrained('selector_groups')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('subject', 500);
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique('selector_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_routing_templates');
    }
};
