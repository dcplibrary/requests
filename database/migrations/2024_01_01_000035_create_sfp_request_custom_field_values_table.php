<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sfp_request_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('sfp_custom_fields')->cascadeOnDelete();
            $table->string('value_slug')->nullable(); // for select/radio (option slug)
            $table->text('value_text')->nullable();   // for text/textarea/date/number (stringy)
            $table->timestamps();

            $table->unique(['request_id', 'custom_field_id'], 'sfp_rcfv_request_custom_field_unique');
            $table->index(['custom_field_id', 'value_slug'], 'sfp_req_custom_field_value_slug');
            $table->index(['custom_field_id'], 'sfp_req_custom_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sfp_request_custom_field_values');
    }
};

