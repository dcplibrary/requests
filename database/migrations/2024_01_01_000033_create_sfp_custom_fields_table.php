<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sfp_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type', 30); // text|textarea|date|number|checkbox|select|radio
            $table->unsignedSmallInteger('step')->default(2); // 1..n (patron forms are multi-step)
            $table->string('request_kind', 20)->default('sfp'); // sfp|ill|both
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('required')->default(false);
            $table->boolean('include_as_token')->default(false);
            $table->boolean('filterable')->default(false);
            $table->json('condition')->nullable(); // same match/rules structure as FormField
            $table->timestamps();

            $table->index(['request_kind', 'step', 'active', 'sort_order'], 'sfp_custom_fields_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfp_custom_fields');
    }
};

