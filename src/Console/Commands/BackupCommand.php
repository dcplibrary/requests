<?php

namespace Dcplibrary\Requests\Console\Commands;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\CatalogFormatLabel;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Exports package config JSON, full-database SQL dump, and/or storage zip to disk (CLI).
 * Options: --config, --db, --storage, --prune, --path, --all.
 */
class BackupCommand extends Command
{
    protected $signature = 'requests:backup
        {--config   : Export configuration as JSON}
        {--db       : Export database as SQL dump}
        {--storage  : Export storage/app as a zip archive}
        {--prune    : Delete backup files older than the configured retention period after writing}
        {--path=    : Directory to write backup files (default: storage/app/requests-backups)}
        {--all      : Shorthand for --config --db --storage}';

    protected $description = 'Export request package backups (configuration, database, and/or storage) to disk.';

    public function handle(): int
    {
        $doConfig  = $this->option('config')  || $this->option('all');
        $doDb      = $this->option('db')      || $this->option('all');
        $doStorage = $this->option('storage') || $this->option('all');

        if (! $doConfig && ! $doDb && ! $doStorage) {
            $this->error('No backup type selected. Use --config, --db, --storage, or --all.');
            return Command::FAILURE;
        }

        $outputDir = rtrim($this->option('path') ?: storage_path('app/requests-backups'), '/');

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true)) {
            $this->error("Cannot create output directory: {$outputDir}");
            return Command::FAILURE;
        }

        $timestamp = now()->format('Y-m-d-His');
        $written   = [];

        // ── Configuration JSON ────────────────────────────────────────────────
        if ($doConfig) {
            $payload = [
                'version'     => 1,
                'exported_at' => now()->toIso8601String(),
                'app'         => 'dcplibrary/requests',
                'data'        => [
                    'settings' => Setting::orderBy('group')->orderBy('key')
                        ->get(['key', 'value'])
                        ->map(fn ($s) => ['key' => $s->key, 'value' => $s->value])
                        ->all(),

                    'request_statuses' => RequestStatus::orderBy('sort_order')
                        ->get(['slug', 'name', 'color', 'icon', 'sort_order', 'active', 'is_terminal',
                               'notify_patron', 'action_label', 'advance_on_claim', 'applies_to_sfp',
                               'applies_to_ill', 'description'])
                        ->toArray(),

                    'field_options' => $this->exportFieldOptions(),

                    'selector_groups' => SelectorGroup::with('fieldOptions.field')
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($g) => [
                            'name'               => $g->name,
                            'description'         => $g->description,
                            'active'              => $g->active,
                            'field_option_slugs'  => $g->fieldOptions->map(fn ($o) => [
                                'field_key' => $o->field->key ?? null,
                                'slug'      => $o->slug,
                            ])->all(),
                        ])
                        ->all(),

                    'catalog_format_labels' => CatalogFormatLabel::orderBy('id')
                        ->get(['format_code', 'label'])
                        ->toArray(),
                ],
            ];

            $file = "{$outputDir}/requests-config-{$timestamp}.json";
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $written[] = $file;
            $this->line("  ✔ Config  → {$file}");
        }

        // ── Database SQL ──────────────────────────────────────────────────────
        if ($doDb) {
            $pdo    = DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $tables = $this->listTables();
            $q      = $this->qi(...);

            $sql  = "-- Requests Database Backup\n";
            $sql .= "-- Exported:  " . now()->toIso8601String() . "\n";
            $sql .= "-- Database:  {$dbName}\n";
            $sql .= "-- Generator: dcplibrary/requests artisan requests:backup\n\n";
            $sql .= $this->fkOff() . ";\n\n";

            foreach ($tables as $table) {
                $createSql = $this->showCreateTable($table);

                $sql .= "-- --------------------------------------------------------\n";
                $sql .= "-- Table: {$q($table)}\n";
                $sql .= "-- --------------------------------------------------------\n\n";
                $sql .= "DROP TABLE IF EXISTS {$q($table)};\n";
                $sql .= $createSql . ";\n\n";

                $rows = DB::table($table)->get();
                if ($rows->isNotEmpty()) {
                    $columns = array_keys((array) $rows->first());
                    $colList = implode(', ', array_map($q, $columns));
                    $sql .= "INSERT INTO {$q($table)} ({$colList}) VALUES\n";

                    $allValues = $rows->map(function ($row) use ($pdo) {
                        $vals = array_map(function ($v) use ($pdo) {
                            return $v === null ? 'NULL' : $pdo->quote((string) $v);
                        }, (array) $row);
                        return '  (' . implode(', ', $vals) . ')';
                    })->implode(",\n");

                    $sql .= $allValues . ";\n\n";
                }
            }

            $sql .= $this->fkOn() . ";\n";

            $file = "{$outputDir}/requests-database-{$timestamp}.sql";
            file_put_contents($file, $sql);
            $written[] = $file;
            $this->line("  ✔ DB      → {$file}");
        }

        // ── Storage Zip ───────────────────────────────────────────────────────
        if ($doStorage) {
            $storagePath = storage_path('app');
            $file        = "{$outputDir}/requests-storage-{$timestamp}.zip";

            $zip = new ZipArchive();
            if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error("Could not create zip archive: {$file}");
                return Command::FAILURE;
            }

            if (is_dir($storagePath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($storagePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iterator as $f) {
                    if ($f->isFile()) {
                        $filePath     = $f->getRealPath();
                        $relativePath = substr($filePath, strlen($storagePath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();
            $written[] = $file;
            $this->line("  ✔ Storage → {$file}");
        }

        $this->info('Backup complete. ' . count($written) . ' file(s) written.');

        // ── Prune old backups ─────────────────────────────────────────────────
        if ($this->option('prune')) {
            $days   = (int) (Setting::where('key', 'backup_retention_days')->value('value') ?: 30);
            $cutoff = now()->subDays($days)->getTimestamp();
            $files  = glob($outputDir . DIRECTORY_SEPARATOR . 'requests-*.{json,sql,zip}', GLOB_BRACE) ?: [];
            $pruned = 0;

            foreach ($files as $path) {
                if (filemtime($path) < $cutoff) {
                    if (@unlink($path)) {
                        $pruned++;
                        $this->line('  ✔ Pruned  → ' . basename($path));
                    } else {
                        $this->warn('  ✘ Could not delete ' . basename($path));
                    }
                }
            }

            $this->info("Pruning complete. {$pruned} file(s) removed (retention: {$days} days).");
        }

        return Command::SUCCESS;
    }

    /**
     * Export all field options grouped by field key for the config backup.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function exportFieldOptions(): array
    {
        return Field::with(['options' => fn ($q) => $q->withTrashed()->orderBy('sort_order')])
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Field $f) => [
                $f->key => $f->options->map(fn (FieldOption $o) => [
                    'slug'       => $o->slug,
                    'name'       => $o->name,
                    'metadata'   => $o->metadata,
                    'sort_order' => $o->sort_order,
                    'active'     => $o->active,
                ])->all(),
            ])
            ->all();
    }

    // ── Driver-aware helpers ──────────────────────────────────────────────────

    private function listTables(): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return array_column(
                DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
                'name'
            );
        }

        $dbName = DB::connection()->getDatabaseName();
        $col    = 'Tables_in_' . $dbName;
        return array_column(DB::select('SHOW TABLES'), $col);
    }

    private function showCreateTable(string $table): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            );
            return $row->sql ?? '';
        }

        $rows = DB::select("SHOW CREATE TABLE `{$table}`");
        return $rows[0]->{'Create Table'};
    }

    private function fkOff(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys = OFF'
            : 'SET FOREIGN_KEY_CHECKS=0';
    }

    private function fkOn(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys = ON'
            : 'SET FOREIGN_KEY_CHECKS=1';
    }

    private function qi(string $name): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '"' . $name . '"'
            : '`' . $name . '`';
    }
}
