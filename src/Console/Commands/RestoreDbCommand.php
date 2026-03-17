<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Services\SqlStatementSplitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Restores the database from a package SQL dump file; splits statements per driver (CLI).
 */
class RestoreDbCommand extends Command
{
    protected $signature = 'requests:restore-db
        {file : Path to the .sql backup file to restore}';

    protected $description = 'Restore the requests package database from an SQL dump file.';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return Command::FAILURE;
        }

        if (pathinfo($path, PATHINFO_EXTENSION) !== 'sql') {
            $this->warn('File does not have a .sql extension. Proceeding anyway.');
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            $this->error("Could not read file: {$path}");
            return Command::FAILURE;
        }

        $statements = SqlStatementSplitter::split($sql);
        $driver     = DB::connection()->getDriverName();
        $statements = SqlStatementSplitter::filterForDriver($statements, $driver);
        $this->info('Executing ' . count($statements) . ' SQL statement(s)...');

        try {
            $bar = $this->output->createProgressBar(count($statements));
            $bar->start();

            foreach ($statements as $statement) {
                if ($statement !== '') {
                    DB::unprepared($statement);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Database restore failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        Cache::flush();
        $this->info('Database restored successfully. Cache flushed.');

        return Command::SUCCESS;
    }
}
