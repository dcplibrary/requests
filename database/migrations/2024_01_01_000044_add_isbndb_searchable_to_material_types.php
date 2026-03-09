<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_types', function (Blueprint $table) {
            $table->boolean('isbndb_searchable')->default(false)->after('ill_enabled');
        });

        // Default book and audiobook to searchable for existing installs.
        DB::table('material_types')
            ->whereIn('slug', ['book', 'audiobook'])
            ->update(['isbndb_searchable' => true]);
    }

    public function down(): void
    {
        Schema::table('material_types', function (Blueprint $table) {
            $table->dropColumn('isbndb_searchable');
        });
    }
};
