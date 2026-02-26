<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogFormatLabelsSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['format_code' => 'BK',                      'label' => 'Book'],
            ['format_code' => 'BOOK_CD',                 'label' => 'Book on CD'],
            ['format_code' => 'AB',                      'label' => 'Audiobook'],
            ['format_code' => 'EAUDIOBOOK',              'label' => 'eAudiobook'],
            ['format_code' => 'EBOOK',                   'label' => 'eBook'],
            ['format_code' => 'LPRINT',                  'label' => 'Large Print'],
            ['format_code' => 'DVD',                     'label' => 'DVD'],
            ['format_code' => 'BLURAY',                  'label' => 'Blu-ray'],
            ['format_code' => 'UK',                      'label' => 'Unknown'],
            ['format_code' => 'GRAPHIC_NOVEL_DOWNLOAD',  'label' => 'Graphic Novel (Digital)'],
            ['format_code' => 'VIDEO_GAME',              'label' => 'Video Game'],
            ['format_code' => 'VIDEO_ONLINE',            'label' => 'Video (Online)'],
            ['format_code' => 'PASS',                    'label' => 'Museum Pass'],
            ['format_code' => 'MAG_ONLINE',              'label' => 'Magazine (Online)'],
            ['format_code' => 'MAG',                     'label' => 'Magazine'],
            ['format_code' => 'KIT',                     'label' => 'Kit'],
            ['format_code' => 'EQUIPMENT',               'label' => 'Equipment'],
        ];

        foreach ($labels as $row) {
            DB::table('catalog_format_labels')->updateOrInsert(
                ['format_code' => $row['format_code']],
                array_merge($row, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
