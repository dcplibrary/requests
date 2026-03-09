<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove ill_requested from sfp_form_fields; it is now a custom checkbox field for SFP.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fieldId = DB::table('sfp_form_fields')->where('key', 'ill_requested')->value('id');
        if ($fieldId !== null) {
            DB::table('form_form_fields')->where('form_field_id', $fieldId)->delete();
            DB::table('sfp_form_fields')->where('id', $fieldId)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
