<?php

namespace Dcplibrary\Sfp\Console\Commands;

use Dcplibrary\Sfp\Database\Seeders\SfpDatabaseSeeder;
use Illuminate\Console\Command;

class SeedDefaultsCommand extends Command
{
    protected $signature = 'request:seed-default';

    protected $description = 'Seed all default SFP data (settings, forms, form fields, statuses, material types, etc.).';

    public function handle(): int
    {
        $this->info('Seeding SFP defaults...');

        $this->call('db:seed', [
            '--class' => SfpDatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
