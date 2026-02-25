<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;

class SfpDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            MaterialTypesSeeder::class,
            AudiencesSeeder::class,
            RequestStatusesSeeder::class,
        ]);
    }
}
