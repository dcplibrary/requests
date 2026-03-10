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
            FormsSeeder::class,
            MaterialTypesSeeder::class,
            AudiencesSeeder::class,
            RequestStatusesSeeder::class,
            PatronStatusTemplatesSeeder::class,
            CatalogFormatLabelsSeeder::class,
            FormFieldsSeeder::class,
            ConsolesSeeder::class,
            SfpCustomFieldsSeeder::class,
            GenresSeeder::class,
            IllCustomFieldsSeeder::class,
            FormFormFieldsSeeder::class,
        ]);
    }
}
