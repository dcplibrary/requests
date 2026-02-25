<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Pending',       'slug' => 'pending',       'color' => '#f59e0b', 'sort_order' => 1, 'active' => true,  'is_terminal' => false],
            ['name' => 'Under Review',  'slug' => 'under-review',  'color' => '#3b82f6', 'sort_order' => 2, 'active' => true,  'is_terminal' => false],
            ['name' => 'On Order',      'slug' => 'on-order',      'color' => '#8b5cf6', 'sort_order' => 3, 'active' => true,  'is_terminal' => false],
            ['name' => 'Purchased',     'slug' => 'purchased',     'color' => '#10b981', 'sort_order' => 4, 'active' => true,  'is_terminal' => true],
            ['name' => 'Denied',        'slug' => 'denied',        'color' => '#ef4444', 'sort_order' => 5, 'active' => true,  'is_terminal' => true],
            ['name' => 'ILL Referred',  'slug' => 'ill-referred',  'color' => '#6b7280', 'sort_order' => 6, 'active' => true,  'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            DB::table('request_statuses')->updateOrInsert(
                ['slug' => $status['slug']],
                array_merge($status, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
