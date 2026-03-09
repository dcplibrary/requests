<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->string('form_scope', 20)->default('global')->after('include_as_token');
        });

        \Illuminate\Support\Facades\DB::table('sfp_form_fields')
            ->whereIn('key', ['console', 'where_heard'])
            ->update(['form_scope' => 'sfp']);
    }

    public function down(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->dropColumn('form_scope');
        });
    }
};
