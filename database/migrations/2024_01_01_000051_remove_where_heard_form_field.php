<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove where_heard from sfp_form_fields; it is now a custom textarea field for SFP.
 * Deletes pivot rows first, then the form field row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fieldId = DB::table('sfp_form_fields')->where('key', 'where_heard')->value('id');
        if ($fieldId !== null) {
            DB::table('form_form_fields')->where('form_field_id', $fieldId)->delete();
            DB::table('sfp_form_fields')->where('id', $fieldId)->delete();
        }
    }

    public function down(): void
    {
        // Re-adding would require form_scope and pivot rows; leave no-op for safety.
    }
};
