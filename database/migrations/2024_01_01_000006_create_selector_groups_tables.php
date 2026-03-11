<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selector_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notification_emails')->nullable(); // routing addresses for new-request emails
            $table->timestamps();
        });

        // Users can belong to many groups
        Schema::create('selector_group_user', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->primary(['selector_group_id', 'user_id']);
        });

        // Groups are scoped to field options (material types, audiences, genres, etc.)
        Schema::create('selector_group_field_option', function (Blueprint $table) {
            $table->foreignId('selector_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_option_id')->constrained('field_options')->cascadeOnDelete();
            $table->primary(['selector_group_id', 'field_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selector_group_field_option');
        Schema::dropIfExists('selector_group_user');
        Schema::dropIfExists('selector_groups');
    }
};
