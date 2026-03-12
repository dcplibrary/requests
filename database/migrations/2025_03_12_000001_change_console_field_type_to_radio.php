<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Change the console field type from 'select' to 'radio'.
 *
 * Console only has 3 options (Nintendo Switch, PlayStation 5, Xbox One)
 * and should render as a radio group, consistent with audience and genre.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up(): void
    {
        DB::table('fields')
            ->where('key', 'console')
            ->where('type', 'select')
            ->update(['type' => 'radio']);
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('fields')
            ->where('key', 'console')
            ->where('type', 'radio')
            ->update(['type' => 'select']);
    }
};
