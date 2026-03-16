<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->boolean('advance_on_claim')->default(false)
                  ->comment('When true, a request on this status is automatically advanced to the next status (by sort_order) when claimed by a staff member.');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn('advance_on_claim');
        });
    }
};
