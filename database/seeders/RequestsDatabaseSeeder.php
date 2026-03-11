<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Illuminate\Database\Seeder;

class RequestsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            SettingsSeeder::class,
            RequestStatusesSeeder::class,
            PatronStatusTemplatesSeeder::class,
            CatalogFormatLabelsSeeder::class,
            FieldsAndOptionsSeeder::class,
        ]);
    }
}
