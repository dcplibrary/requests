<?php

namespace Dcplibrary\Requests\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UsersBackupCommand extends Command
{
    protected $signature = 'requests:users-backup
        {--path= : Directory to write the backup file (default: storage/app/requests-backups)}';

    protected $description = 'Backup staff_users, selector_groups, and their pivot tables to a JSON file.';

    /** Tables to back up, in dependency order (parents before children). */
    private const TABLES = [
        'staff_users',
        'selector_groups',
        'selector_group_user',
        'selector_group_field_option',
    ];

    public function handle(): int
    {
        $outputDir = rtrim($this->option('path') ?: storage_path('app/requests-backups'), '/');

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true)) {
            $this->error("Cannot create output directory: {$outputDir}");
            return Command::FAILURE;
        }

        $payload = [
            'version'     => 1,
            'exported_at' => now()->toIso8601String(),
            'app'         => 'dcplibrary/requests',
            'tables'      => [],
        ];

        foreach (self::TABLES as $table) {
            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $payload['tables'][$table] = $rows;
            $this->line("  ✔ {$table} — " . count($rows) . ' row(s)');
        }

        $timestamp = now()->format('Y-m-d-His');
        $file      = "{$outputDir}/users-backup-{$timestamp}.json";

        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Backup written → {$file}");

        return Command::SUCCESS;
    }
}
