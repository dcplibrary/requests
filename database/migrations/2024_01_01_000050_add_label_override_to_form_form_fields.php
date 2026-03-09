<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_form_fields', function (Blueprint $table) {
            $table->string('label_override', 255)->nullable()->after('form_field_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_form_fields', function (Blueprint $table) {
            $table->dropColumn('label_override');
        });
    }
};
