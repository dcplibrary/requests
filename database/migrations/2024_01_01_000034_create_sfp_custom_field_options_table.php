<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sfp_custom_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained('sfp_custom_fields')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('sfp_users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('sfp_users')->nullOnDelete();

            $table->unique(['custom_field_id', 'slug']);
            $table->index(['custom_field_id', 'active', 'sort_order'], 'sfp_custom_field_options_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfp_custom_field_options');
    }
};

