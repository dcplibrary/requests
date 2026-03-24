<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audience and genre apply to Suggest for Purchase only, not Interlibrary Loan.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fields')) {
            return;
        }

        DB::table('fields')->whereIn('key', ['audience', 'genre'])->update(['scope' => 'sfp']);

        if (! Schema::hasTable('form_field_config') || ! Schema::hasTable('forms')) {
            return;
        }

        $illFormId = DB::table('forms')->where('slug', 'ill')->value('id');
        if (! $illFormId) {
            return;
        }

        $fieldIds = DB::table('fields')->whereIn('key', ['audience', 'genre'])->pluck('id');
        if ($fieldIds->isEmpty()) {
            return;
        }

        DB::table('form_field_config')
            ->where('form_id', $illFormId)
            ->whereIn('field_id', $fieldIds)
            ->delete();
    }

    /**
     * Restore previous behaviour (not recommended — only for rollback).
     */
    public function down(): void
    {
        if (! Schema::hasTable('fields')) {
            return;
        }

        DB::table('fields')->whereIn('key', ['audience', 'genre'])->update(['scope' => 'both']);

        // Cannot reliably recreate deleted form_field_config rows without full seed data.
    }
};
