<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catch-up migration for remote servers whose `requests` table was created
 * before the following columns were added to the original create migration:
 *
 *   - genre              (string, nullable)
 *   - where_heard        (text, nullable)
 *   - ill_requested      (boolean, default false)
 *   - isbndb_searched    (boolean, default false)
 *   - isbndb_result_count (integer, nullable)
 *   - isbndb_match_accepted (boolean, nullable)
 *   - is_duplicate       (boolean, default false)
 *   - duplicate_of_request_id (foreignId, nullable)
 *
 * Each column is guarded with hasColumn() so the migration is safe to run
 * on databases that already have some or all of these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {

            if (! Schema::hasColumn('requests', 'genre')) {
                $table->string('genre')->nullable()->after('other_material_text');
            }

            if (! Schema::hasColumn('requests', 'where_heard')) {
                $table->text('where_heard')->nullable()->after('genre');
            }

            if (! Schema::hasColumn('requests', 'ill_requested')) {
                $table->boolean('ill_requested')->default(false)->after('where_heard');
            }

            if (! Schema::hasColumn('requests', 'isbndb_searched')) {
                $table->boolean('isbndb_searched')->default(false)->after('catalog_match_bib_id');
            }

            if (! Schema::hasColumn('requests', 'isbndb_result_count')) {
                $table->integer('isbndb_result_count')->nullable()->after('isbndb_searched');
            }

            if (! Schema::hasColumn('requests', 'isbndb_match_accepted')) {
                $table->boolean('isbndb_match_accepted')->nullable()->after('isbndb_result_count');
            }

            if (! Schema::hasColumn('requests', 'is_duplicate')) {
                $table->boolean('is_duplicate')->default(false)->after('isbndb_match_accepted');
            }

            if (! Schema::hasColumn('requests', 'duplicate_of_request_id')) {
                $table->foreignId('duplicate_of_request_id')
                    ->nullable()
                    ->after('is_duplicate')
                    ->constrained('requests')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Drop FK constraint before column
            if (Schema::hasColumn('requests', 'duplicate_of_request_id')) {
                $table->dropForeign(['duplicate_of_request_id']);
                $table->dropColumn('duplicate_of_request_id');
            }

            foreach (['is_duplicate', 'isbndb_match_accepted', 'isbndb_result_count',
                      'isbndb_searched', 'ill_requested', 'where_heard', 'genre'] as $col) {
                if (Schema::hasColumn('requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
