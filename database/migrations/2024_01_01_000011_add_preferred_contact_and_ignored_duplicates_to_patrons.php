<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrons', function (Blueprint $table) {
            // Which phone/email to use as the canonical value: 'submitted' or 'polaris'
            $table->string('preferred_phone')->default('submitted')->after('email_matches');
            $table->string('preferred_email')->default('submitted')->after('preferred_phone');
        });

        // Pairs of patron IDs that staff have explicitly marked as NOT duplicates,
        // so they no longer appear in each other's duplicate warning panel.
        Schema::create('patron_ignored_duplicates', function (Blueprint $table) {
            $table->foreignId('patron_id')->constrained('patrons')->cascadeOnDelete();
            $table->foreignId('ignored_patron_id')->constrained('patrons')->cascadeOnDelete();
            $table->primary(['patron_id', 'ignored_patron_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_ignored_duplicates');

        Schema::table('patrons', function (Blueprint $table) {
            $table->dropColumn(['preferred_phone', 'preferred_email']);
        });
    }
};
