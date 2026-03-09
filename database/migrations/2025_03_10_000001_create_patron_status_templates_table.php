<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple patron status email templates; each can be linked to one or more
     * request statuses. When a request transitions to a status, every enabled
     * template linked to that status is sent. Footer remains universal (Setting).
     */
    public function up(): void
    {
        Schema::create('patron_status_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('subject');
            $table->mediumText('body')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('patron_status_template_request_status', function (Blueprint $table) {
            $table->foreignId('patron_status_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_status_id')->constrained()->cascadeOnDelete();
            $table->primary(['patron_status_template_id', 'request_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_status_template_request_status');
        Schema::dropIfExists('patron_status_templates');
    }
};
