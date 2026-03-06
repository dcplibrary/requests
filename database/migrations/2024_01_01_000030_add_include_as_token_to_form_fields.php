<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `include_as_token` to sfp_form_fields.
 *
 * When true, the field's submitted value is made available as a {key} token
 * in notification email templates (e.g. {isbn}, {where_heard}, {genre}).
 *
 * Also enables the flag by default for the built-in fields whose values
 * are most useful in notification context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->boolean('include_as_token')->default(false)->after('condition');
        });

        // Enable by default for built-in fields that carry meaningful
        // per-request data not already covered by the core token set.
        DB::table('sfp_form_fields')
            ->whereIn('key', ['isbn', 'publish_date', 'where_heard', 'ill_requested', 'genre', 'console'])
            ->update(['include_as_token' => true]);
    }

    public function down(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->dropColumn('include_as_token');
        });
    }
};
