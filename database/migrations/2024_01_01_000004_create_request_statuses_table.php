<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#6b7280'); // tailwind-compatible hex
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_terminal')->default(false); // purchased/denied = no further transitions expected
            $table->boolean('notify_patron')->default(false); // fire patron email on transition to this status
            $table->string('action_label', 50)->nullable();
            $table->boolean('advance_on_claim')->default(false);
            $table->boolean('applies_to_sfp')->default(true);
            $table->boolean('applies_to_ill')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_statuses');
    }
};
