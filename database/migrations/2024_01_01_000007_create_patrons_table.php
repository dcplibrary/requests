<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrons', function (Blueprint $table) {
            $table->id();

            // --- Submitted patron data ---
            $table->string('barcode')->unique();
            $table->string('name_first');
            $table->string('name_last');
            $table->string('phone');
            $table->string('email')->nullable();

            // --- Polaris lookup status ---
            $table->boolean('found_in_polaris')->default(false);
            $table->boolean('polaris_lookup_attempted')->default(false);
            $table->timestamp('polaris_lookup_at')->nullable();

            // --- Polaris data (populated if found) ---
            $table->integer('polaris_patron_id')->nullable();
            $table->integer('polaris_patron_code_id')->nullable();
            $table->string('polaris_name_first')->nullable();
            $table->string('polaris_name_last')->nullable();
            $table->string('polaris_phone')->nullable();
            $table->string('polaris_email')->nullable();

            // --- Field-level match flags (submitted vs Polaris) ---
            $table->boolean('name_first_matches')->nullable();
            $table->boolean('name_last_matches')->nullable();
            $table->boolean('phone_matches')->nullable();
            $table->boolean('email_matches')->nullable();

            $table->timestamps();

            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrons');
    }
};
