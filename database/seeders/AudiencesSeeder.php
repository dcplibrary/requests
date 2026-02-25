<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AudiencesSeeder extends Seeder
{
    public function run(): void
    {
        $audiences = [
            ['name' => 'Adult',       'slug' => 'adult',       'bibliocommons_value' => 'adult',    'active' => true, 'sort_order' => 1],
            ['name' => 'Children',    'slug' => 'children',    'bibliocommons_value' => 'children', 'active' => true, 'sort_order' => 2],
            ['name' => 'Young Adult', 'slug' => 'young-adult', 'bibliocommons_value' => 'teen',     'active' => true, 'sort_order' => 3],
        ];

        foreach ($audiences as $audience) {
            DB::table('audiences')->updateOrInsert(
                ['slug' => $audience['slug']],
                array_merge($audience, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
