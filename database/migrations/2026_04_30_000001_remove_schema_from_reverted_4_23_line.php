<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops columns and tables that were introduced by migrations shipped in the
 * 4.22.2–4.23.x line that was reverted from main. Safe to run if those
 * migrations never ran (each step is guarded).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('deletion_logs');

        foreach (['materials', 'requests', 'patrons'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }

        if (Schema::hasTable('patrons')
            && Schema::hasColumn('patrons', 'ill_agreement_signed')) {
            Schema::table('patrons', function (Blueprint $table) {
                $table->dropColumn(['ill_agreement_signed', 'ill_agreement_signed_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patrons')
            && ! Schema::hasColumn('patrons', 'ill_agreement_signed')) {
            Schema::table('patrons', function (Blueprint $table) {
                $table->boolean('ill_agreement_signed')->default(false)->after('email');
                $table->timestamp('ill_agreement_signed_at')->nullable()->after('ill_agreement_signed');
            });
        }

        foreach (['patrons', 'requests', 'materials'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }
        }

        if (! Schema::hasTable('deletion_logs')) {
            Schema::create('deletion_logs', function (Blueprint $table) {
                $table->id();
                $table->string('deleted_by')->nullable();
                $table->string('action');
                $table->string('entity_type');
                $table->integer('count')->default(0);
                $table->json('detail')->nullable();
                $table->timestamps();
            });
        }
    }
};
