<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Book',        'slug' => 'book',         'active' => true,  'has_other_text' => false, 'sort_order' => 1],
            ['name' => 'Large Print', 'slug' => 'large-print',  'active' => true,  'has_other_text' => false, 'sort_order' => 2],
            ['name' => 'Graphic Novel','slug' => 'graphic-novel','active' => true, 'has_other_text' => false, 'sort_order' => 3],
            ['name' => 'DVD',         'slug' => 'dvd',          'active' => true,  'has_other_text' => false, 'sort_order' => 4],
            ['name' => 'Blu-Ray',     'slug' => 'blu-ray',      'active' => true,  'has_other_text' => false, 'sort_order' => 5],
            ['name' => 'eAudiobook',  'slug' => 'eaudiobook',   'active' => true,  'has_other_text' => false, 'sort_order' => 6],
            ['name' => 'eBook',       'slug' => 'ebook',        'active' => true,  'has_other_text' => false, 'sort_order' => 7],
            ['name' => 'Video Game',   'slug' => 'video-game',   'active' => true,  'has_other_text' => false, 'sort_order' => 8],
            ['name' => 'Console Game', 'slug' => 'console-game', 'active' => true,  'has_other_text' => true,  'sort_order' => 9],
            ['name' => 'Other',        'slug' => 'other',        'active' => true,  'has_other_text' => true,  'sort_order' => 10],
        ];

        foreach ($types as $type) {
            DB::table('material_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
