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
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('patron_status_template_request_status', function (Blueprint $table) {
            $table->unsignedBigInteger('patron_status_template_id');
            $table->unsignedBigInteger('request_status_id');
            $table->primary(['patron_status_template_id', 'request_status_id']);
            $table->foreign('patron_status_template_id', 'pst_rq_patron_tpl_fk')
                ->references('id')->on('patron_status_templates')->cascadeOnDelete();
            $table->foreign('request_status_id', 'pst_rq_req_status_fk')
                ->references('id')->on('request_statuses')->cascadeOnDelete();
        });

        // Scope templates to specific field options (e.g. material types)
        Schema::create('patron_status_template_field_option', function (Blueprint $table) {
            $table->unsignedBigInteger('patron_status_template_id');
            $table->unsignedBigInteger('field_option_id');
            $table->primary(['patron_status_template_id', 'field_option_id'], 'pst_fo_pk');
            $table->foreign('patron_status_template_id', 'pst_fo_patron_tpl_fk')
                ->references('id')->on('patron_status_templates')->cascadeOnDelete();
            $table->foreign('field_option_id', 'pst_fo_field_option_fk')
                ->references('id')->on('field_options')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_status_template_field_option');
        Schema::dropIfExists('patron_status_template_request_status');
        Schema::dropIfExists('patron_status_templates');
    }
};
