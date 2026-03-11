<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Database\Seeders\RequestsDatabaseSeeder;
use Illuminate\Console\Command;

class SeedDefaultsCommand extends Command
{
    protected $signature = 'requests:seed-defaults';

    protected $description = 'Seed all default data (settings, forms, form fields, statuses, etc.).';

    public function handle(): int
    {
        $this->info('Seeding defaults...');

        $this->call('db:seed', [
            '--class' => RequestsDatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
