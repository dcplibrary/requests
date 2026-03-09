<?php

namespace Dcplibrary\Sfp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UsersRestoreCommand extends Command
{
    protected $signature = 'request:users-restore
        {file? : Path to the backup JSON file. Defaults to the most recent backup in storage/app/sfp-backups}
        {--force : Skip the confirmation prompt}
        {--path= : Directory to search for the most recent backup (default: storage/app/sfp-backups)}';

    protected $description = 'Restore sfp_users, selector_groups, and their pivot tables from a JSON backup file.';

    /** Restore order: pivots first (truncate), then parents; insert in reverse. */
    private const TRUNCATE_ORDER = [
        'selector_group_genre',
        'selector_group_audience',
        'selector_group_material_type',
        'selector_group_user',
        'selector_groups',
        'sfp_users',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! $file) {
            $dir   = rtrim($this->option('path') ?: storage_path('app/sfp-backups'), '/');
            $files = glob($dir . '/users-backup-*.json') ?: [];

            if (empty($files)) {
                $this->error("No backup files found in {$dir}. Run request:users-backup first.");
                return Command::FAILURE;
            }

            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $file = $files[0];
            $this->line("Using most recent backup: {$file}");
        }

        if (! file_exists($file)) {
            $this->error("Backup file not found: {$file}");
            return Command::FAILURE;
        }

        $payload = json_decode(file_get_contents($file), true);

        if (! isset($payload['tables'])) {
            $this->error('Invalid backup file — missing "tables" key.');
            return Command::FAILURE;
        }

        // Show a summary so the user knows what they're restoring
        $this->line('');
        $this->line('Backup: ' . ($payload['exported_at'] ?? 'unknown'));
        foreach ($payload['tables'] as $table => $rows) {
            $this->line("  {$table}: " . count($rows) . ' row(s)');
        }
        $this->line('');

        if (! $this->option('force') && ! $this->confirm('This will replace all existing user and group data. Continue?')) {
            $this->line('Restore cancelled.');
            return Command::SUCCESS;
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        DB::statement($isSqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS=0');

        try {
            // Clear in child-first order
            foreach (self::TRUNCATE_ORDER as $table) {
                if ($isSqlite) {
                    DB::table($table)->delete();
                } else {
                    DB::table($table)->truncate();
                }
            }

            // Insert in parent-first order (same as the backup order)
            foreach ($payload['tables'] as $table => $rows) {
                if (empty($rows)) {
                    $this->line("  — {$table}: empty, skipped");
                    continue;
                }

                // Insert in chunks to avoid hitting query size limits
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $this->line("  ✔ {$table} — " . count($rows) . ' row(s) restored');
            }
        } finally {
            DB::statement($isSqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Restore complete.');

        return Command::SUCCESS;
    }
}
