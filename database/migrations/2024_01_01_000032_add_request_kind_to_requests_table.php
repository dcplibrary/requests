<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('request_kind', 20)->default('sfp')->after('request_status_id');
            $table->index('request_kind');
        });

        // Backfill existing rows (defensive; default already covers new installs).
        DB::table('requests')->whereNull('request_kind')->update(['request_kind' => 'sfp']);
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['request_kind']);
            $table->dropColumn('request_kind');
        });
    }
};

