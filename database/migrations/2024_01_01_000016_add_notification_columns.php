<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-status: should a patron email fire when a request moves to this status?
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->boolean('notify_patron')->default(false)->after('is_terminal');
        });

        // Per-selector-group: who gets the new-request routing email?
        Schema::table('selector_groups', function (Blueprint $table) {
            $table->text('notification_emails')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('request_statuses', function (Blueprint $table) {
            $table->dropColumn('notify_patron');
        });

        Schema::table('selector_groups', function (Blueprint $table) {
            $table->dropColumn('notification_emails');
        });
    }
};
