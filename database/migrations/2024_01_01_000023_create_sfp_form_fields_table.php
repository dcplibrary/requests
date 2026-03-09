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
            $table->json('condition')->nullable();           // Conditional logic rules, null = always show
            $table->boolean('include_as_token')->default(false); // expose value as {key} in email tokens
            $table->string('form_scope', 20)->default('global'); // global|sfp|ill
            $table->softDeletes();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('sfp_users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('sfp_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfp_form_fields');
    }
};
