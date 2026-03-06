<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenresSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            ['name' => 'Fiction',    'slug' => 'fiction',    'sort_order' => 1, 'active' => true],
            ['name' => 'Nonfiction', 'slug' => 'nonfiction', 'sort_order' => 2, 'active' => true],
        ];

        foreach ($genres as $genre) {
            DB::table('sfp_genres')->updateOrInsert(
                ['slug' => $genre['slug']],
                array_merge($genre, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
