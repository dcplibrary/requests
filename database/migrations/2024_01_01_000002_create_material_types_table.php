<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->boolean('ill_enabled')->default(true);
            $table->boolean('isbndb_searchable')->default(false);
            $table->boolean('has_other_text')->default(false); // for "Other" type
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
            // Plain columns: sfp_users doesn't exist yet at migration time (runs at 000005)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_types');
    }
};
