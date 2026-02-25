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
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_terminal')->default(false); // purchased/denied = no further transitions expected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_statuses');
    }
};
