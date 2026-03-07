<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;

class SfpDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            SettingsSeeder::class,
            MaterialTypesSeeder::class,
            AudiencesSeeder::class,
            RequestStatusesSeeder::class,
            CatalogFormatLabelsSeeder::class,
            FormFieldsSeeder::class,
            GenresSeeder::class,
            ConsolesSeeder::class,
            IllCustomFieldsSeeder::class,
        ]);
    }
}
