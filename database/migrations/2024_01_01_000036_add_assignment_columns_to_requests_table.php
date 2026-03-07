<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')->nullable()->after('request_kind')
                ->constrained('sfp_users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id');
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_at')
                ->constrained('sfp_users')->nullOnDelete();

            $table->index(['assigned_to_user_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_user_id', 'assigned_at']);
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropColumn('assigned_at');
            $table->dropConstrainedForeignId('assigned_by_user_id');
        });
    }
};

