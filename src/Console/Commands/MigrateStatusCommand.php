<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Support\PackagePaths;
use Illuminate\Console\Command;

/**
 * Shows migration status for the requests package only.
 */
class MigrateStatusCommand extends Command
{
    protected $signature = 'requests:migrate:status';

    protected $description = 'Show which requests package migrations have run or are still pending.';

    public function handle(): int
    {
        $path = PackagePaths::migrations();
        if ($path === null) {
            $this->error('Package migrations directory not found.');

            return Command::FAILURE;
        }

        return $this->call('migrate:status', [
            '--path'     => $path,
            '--realpath' => true,
        ]);
    }
}
