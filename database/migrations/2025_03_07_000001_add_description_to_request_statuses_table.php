<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->text('description')->nullable()->after('notify_patron');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
