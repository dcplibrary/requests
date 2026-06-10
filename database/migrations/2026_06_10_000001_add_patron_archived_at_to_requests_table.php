<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('requests', 'patron_archived_at')) {
            return;
        }

        Schema::table('requests', function (Blueprint $table) {
            $table->timestamp('patron_archived_at')->nullable()->after('assigned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('patron_archived_at');
        });
    }
};
