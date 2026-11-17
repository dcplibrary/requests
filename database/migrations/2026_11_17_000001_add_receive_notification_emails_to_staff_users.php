<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user opt-out for staff activity emails.
 *
 * Existing accounts keep receiving mail: the column defaults to true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('staff_users', 'receive_notification_emails')) {
            Schema::table('staff_users', function (Blueprint $table) {
                $table->boolean('receive_notification_emails')->default(true)->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_users', 'receive_notification_emails')) {
            Schema::table('staff_users', function (Blueprint $table) {
                $table->dropColumn('receive_notification_emails');
            });
        }
    }
};
