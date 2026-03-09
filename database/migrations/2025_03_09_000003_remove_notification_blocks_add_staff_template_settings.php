<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_template_blocks')) {
            Schema::dropIfExists('notification_template_blocks');
        }

        $newSettings = [
            [
                'key'         => 'staff_routing_title',
                'value'       => 'Staff routing',
                'label'       => 'Staff routing template title',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Title for staff use in the Emails list.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'staff_routing_material_type_ids',
                'value'       => '[]',
                'label'       => 'Staff routing material types',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'JSON array of material type IDs; empty = all.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'staff_routing_status_ids',
                'value'       => '[]',
                'label'       => 'Staff routing statuses',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'JSON array of status IDs; empty = all.',
                'tokens'      => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($newSettings as $row) {
            if (DB::table('settings')->where('key', $row['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert($row);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'staff_routing_title',
            'staff_routing_material_type_ids',
            'staff_routing_status_ids',
        ])->delete();

        // Recreate notification_template_blocks if needed (optional; original migration can be re-run).
    }
};
