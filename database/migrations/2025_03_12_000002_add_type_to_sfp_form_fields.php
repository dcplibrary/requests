<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a 'type' column to sfp_form_fields so the input type
 * (text, date, radio, select, etc.) is stored in the database
 * instead of being hardcoded in controllers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->string('type', 30)->default('text')->after('label');
        });

        // Populate type for the known global form fields.
        $typeMap = [
            'material_type' => 'select',
            'audience'      => 'radio',
            'genre'         => 'radio',
            'title'         => 'text',
            'author'        => 'text',
            'isbn'          => 'text',
            'publish_date'  => 'date',
        ];

        foreach ($typeMap as $key => $type) {
            DB::table('sfp_form_fields')
                ->where('key', $key)
                ->update(['type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('sfp_form_fields', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
