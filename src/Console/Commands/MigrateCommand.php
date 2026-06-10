<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Support\PackagePaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs only pending migrations shipped with the requests package.
 *
 * Before delegating to Laravel's migrator, this command auto-syncs the
 * migrations table for any package migrations that are not yet recorded
 * but whose schema changes are already present in the database (e.g. after
 * a SQL dump restore). This prevents "table/column already exists" errors
 * when running on a database that was not originally set up via artisan.
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

        $this->syncAlreadyApplied($path);

        return $this->call('migrate', [
            '--path'     => $path,
            '--realpath' => true,
            '--force'    => $this->option('force'),
            '--pretend'  => $this->option('pretend'),
            '--step'     => $this->option('step'),
        ]);
    }

    /**
     * Scan all package migration files. For any not yet recorded in the
     * migrations table, check whether the schema change is already present.
     * If so, record the migration as applied so Laravel skips it.
     *
     * Handles two patterns:
     *   create_X_table[s]  → checks Schema::hasTable(X)
     *   add_*_to_X_table   → reads the file to find column names, checks Schema::hasColumn(X, col)
     *
     * Data-only migrations (fix_*, clarify_*, scope_*, add_*_setting, etc.) are
     * left to run normally — they already use updateOrInsert / conditional logic.
     */
    protected function syncAlreadyApplied(string $path): void
    {
        $files = glob($path . '/*.php') ?: [];
        if (empty($files)) {
            return;
        }

        $ran   = DB::table('migrations')->pluck('migration')->flip()->toArray();
        $batch = max(1, (int) DB::table('migrations')->max('batch'));
        $toMark = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (isset($ran[$name])) {
                continue;
            }

            if ($this->isAlreadyApplied($name, $file)) {
                $toMark[] = ['migration' => $name, 'batch' => $batch];
                $this->line("  <fg=yellow>Synced (already in DB):</>  {$name}");
            }
        }

        if (! empty($toMark)) {
            DB::table('migrations')->insert($toMark);
            $this->line('');
        }
    }

    /**
     * Determine whether a migration's schema changes are already present
     * by inspecting the database schema directly.
     */
    protected function isAlreadyApplied(string $name, string $file): bool
    {
        // Strip timestamp prefix: "2024_01_01_000001_create_settings_table" → "create_settings_table"
        $base = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);

        // create_X_table or create_X_tables
        if (preg_match('/^create_(.+?)_tables?$/', $base, $m)) {
            return Schema::hasTable($m[1]);
        }

        // add_*_to_X_table — verify the target table and all added columns exist
        if (preg_match('/^add_.+_to_(.+)_table$/', $base, $m)) {
            $table = $m[1];
            if (! Schema::hasTable($table)) {
                return false;
            }

            $columns = $this->parseAddedColumns($file);

            if (empty($columns)) {
                // No columns detected — assume applied if table exists
                return true;
            }

            foreach ($columns as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Parse a migration file and extract column names from $table->type('col') calls.
     *
     * @return string[]
     */
    protected function parseAddedColumns(string $file): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }

        // Match $table->someType('column_name') or $table->someType('column_name', ...)
        preg_match_all('/\$table->\w+\(\s*[\'"](\w+)[\'"]\s*[,)]/', $content, $matches);

        // Exclude pseudo-column names that are actually table names or options
        $exclude = ['id', 'primary', 'index', 'unique', 'foreign'];

        return array_values(array_filter(
            $matches[1] ?? [],
            fn (string $col) => ! in_array($col, $exclude, true)
        ));
    }
}
