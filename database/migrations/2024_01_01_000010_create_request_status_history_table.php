<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('staff_users')->nullOnDelete(); // null = system
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_status_history');
    }
};
