<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sfp_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // e.g. 'genre', 'console', 'audience'
            $table->string('label');                  // Display label in admin UI
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);    // Show/hide globally
            $table->boolean('required')->default(false); // Fail validation if left blank
            $table->json('condition')->nullable();        // Conditional logic rules, null = always show
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfp_form_fields');
    }
};
