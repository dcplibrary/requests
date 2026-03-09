<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables that get soft deletes and created_by / modified_by (audit). */
    private array $tables = [
        'material_types',
        'sfp_custom_fields',
        'sfp_custom_field_options',
        'sfp_form_fields',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('created_by')->nullable()->after('updated_at')
                    ->constrained('sfp_users')->nullOnDelete();
                $table->foreignId('modified_by')->nullable()->after('created_by')
                    ->constrained('sfp_users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['modified_by']);
                $table->dropSoftDeletes();
            });
        }
    }
};
