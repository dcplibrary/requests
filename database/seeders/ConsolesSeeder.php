<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsolesSeeder extends Seeder
{
    public function run(): void
    {
        $consoles = [
            ['name' => 'Nintendo Switch', 'slug' => 'switch',        'sort_order' => 1, 'active' => true],
            ['name' => 'PlayStation 5',   'slug' => 'playstation-5', 'sort_order' => 2, 'active' => true],
            ['name' => 'Xbox One',        'slug' => 'xbox-one',      'sort_order' => 3, 'active' => true],
        ];

        foreach ($consoles as $console) {
            DB::table('sfp_consoles')->updateOrInsert(
                ['slug' => $console['slug']],
                array_merge($console, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
