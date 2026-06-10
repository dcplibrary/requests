<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Support\PackagePaths;
use Illuminate\Console\Command;

/**
 * Runs only pending migrations shipped with the requests package.
 *
 * Equivalent to {@see migrate} with --path scoped to this package's database/migrations
 * directory. Already-applied migrations are skipped.
 */
class MigrateCommand extends Command
{
    protected $signature = 'requests:migrate
        {--force : Force the operation to run when in production}
        {--pretend : Dump the SQL queries that would be run}
        {--step : Run each migration in its own batch so it can be rolled back individually}';

    protected $description = 'Run pending database migrations for the requests package only.';

    public function handle(): int
    {
        $path = PackagePaths::migrations();
        if ($path === null) {
            $this->error('Package migrations directory not found.');

            return Command::FAILURE;
        }

        $this->info('Running pending requests package migrations from:');
        $this->line('  ' . $path);

        return $this->call('migrate', [
            '--path'     => $path,
            '--realpath' => true,
            '--force'    => $this->option('force'),
            '--pretend'  => $this->option('pretend'),
            '--step'     => $this->option('step'),
        ]);
    }
}
