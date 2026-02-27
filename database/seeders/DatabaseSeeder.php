<?php

namespace Database\Seeders;

use Dcplibrary\Sfp\Database\Seeders\SfpDatabaseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SfpDatabaseSeeder::class,
        ]);
    }
}

