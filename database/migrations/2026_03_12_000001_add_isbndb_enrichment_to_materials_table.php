<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('title_long')->nullable()->after('overview');
            $table->text('synopsis')->nullable()->after('title_long');
            $table->json('subjects')->nullable()->after('synopsis');
            $table->string('dewey_decimal')->nullable()->after('subjects');
            $table->unsignedInteger('pages')->nullable()->after('dewey_decimal');
            $table->string('language')->nullable()->after('pages');
            $table->decimal('msrp', 8, 2)->nullable()->after('language');
            $table->string('binding')->nullable()->after('msrp');
            $table->string('dimensions')->nullable()->after('binding');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn([
                'title_long',
                'synopsis',
                'subjects',
                'dewey_decimal',
                'pages',
                'language',
                'msrp',
                'binding',
                'dimensions',
            ]);
        });
    }
};
