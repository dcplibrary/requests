<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            // --- Foreign keys ---
            $table->foreignId('patron_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_status_id')->constrained()->restrictOnDelete();

            // --- Raw submitted data (always preserved as entered) ---
            $table->string('submitted_title');
            $table->string('submitted_author');
            $table->string('submitted_publish_date')->nullable();

            // --- "Other" material type free text ---
            $table->string('other_material_text')->nullable();

            // --- Fiction / Nonfiction classification ---
            $table->string('genre')->nullable();

            // --- Patron's additional info ---
            $table->text('where_heard')->nullable();
            $table->boolean('ill_requested')->default(false); // final checkbox

            // --- Catalog (Bibliocommons) search tracking ---
            $table->boolean('catalog_searched')->default(false);
            $table->integer('catalog_result_count')->nullable();
            $table->boolean('catalog_match_accepted')->nullable(); // null = not asked, true/false = patron chose
            $table->string('catalog_match_bib_id')->nullable(); // Bibliocommons bib ID if accepted

            // --- ISBNdb search tracking ---
            $table->boolean('isbndb_searched')->default(false);
            $table->integer('isbndb_result_count')->nullable();
            $table->boolean('isbndb_match_accepted')->nullable();

            // --- Duplicate detection ---
            $table->boolean('is_duplicate')->default(false); // matched an existing request
            $table->foreignId('duplicate_of_request_id')->nullable()->constrained('requests')->nullOnDelete();

            $table->timestamps();

            $table->index('patron_id');
            $table->index('material_id');
            $table->index('request_status_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
