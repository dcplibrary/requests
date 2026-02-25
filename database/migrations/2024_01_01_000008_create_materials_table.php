<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();

            // Core identifying fields (used for deduplication)
            $table->string('title');
            $table->string('author');
            $table->string('publish_date')->nullable(); // stored as patron entered (flexible format)

            // Enriched fields from ISBNdb or Polaris
            $table->string('isbn')->nullable();
            $table->string('isbn13')->nullable();
            $table->string('publisher')->nullable();
            $table->date('exact_publish_date')->nullable(); // resolved from ISBNdb
            $table->string('edition')->nullable();
            $table->text('overview')->nullable(); // ISBNdb description if available

            // Source of enrichment
            $table->enum('source', ['submitted', 'isbndb', 'polaris'])->default('submitted');

            // FK to material type (nullable — "Other" type stores text on request instead)
            $table->foreignId('material_type_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['title', 'author']);
            $table->index('isbn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
