<?php

namespace Dcplibrary\Sfp\Console\Commands;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\CatalogFormatLabel;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SelectorGroup;
use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class SfpBackupCommand extends Command
{
    /**
     * php artisan sfp:backup --config --db --storage --path=/var/backups/sfp
     */
    protected $signature = 'sfp:backup
        {--config   : Export configuration as JSON}
        {--db       : Export database as SQL dump}
        {--storage  : Export storage/app as a zip archive}
        {--path=    : Directory to write backup files (default: storage/app/sfp-backups)}
        {--all      : Shorthand for --config --db --storage}';

    protected $description = 'Export SFP backups (configuration, database, and/or storage) to disk.';

    public function handle(): int
    {
        $doConfig  = $this->option('config')  || $this->option('all');
        $doDb      = $this->option('db')      || $this->option('all');
        $doStorage = $this->option('storage') || $this->option('all');

        if (! $doConfig && ! $doDb && ! $doStorage) {
            $this->error('No backup type selected. Use --config, --db, --storage, or --all.');
            return Command::FAILURE;
        }

        $outputDir = rtrim($this->option('path') ?: storage_path('app/sfp-backups'), '/');

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
                'app'         => 'dcplibrary/sfp',
                'data'        => [
                    'settings' => Setting::orderBy('group')->orderBy('key')
                        ->get(['key', 'value'])
                        ->map(fn ($s) => ['key' => $s->key, 'value' => $s->value])
                        ->all(),

                    'request_statuses' => RequestStatus::orderBy('sort_order')
                        ->get(['slug', 'name', 'color', 'is_terminal', 'sort_order', 'active'])
                        ->toArray(),

                    'material_types' => MaterialType::orderBy('sort_order')
                        ->get(['slug', 'name', 'has_other_text', 'sort_order', 'active'])
                        ->toArray(),

                    'audiences' => Audience::orderBy('sort_order')
                        ->get(['slug', 'name', 'bibliocommons_value', 'sort_order', 'active'])
                        ->toArray(),

                    'selector_groups' => SelectorGroup::with('materialTypes', 'audiences')
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($g) => [
                            'name'                => $g->name,
                            'description'         => $g->description,
                            'active'              => $g->active,
                            'material_type_slugs' => $g->materialTypes->pluck('slug')->all(),
                            'audience_slugs'      => $g->audiences->pluck('slug')->all(),
                        ])
                        ->all(),

                    'catalog_format_labels' => CatalogFormatLabel::orderBy('id')
                        ->get(['format_code', 'label'])
                        ->toArray(),
                ],
            ];

            $file = "{$outputDir}/sfp-config-{$timestamp}.json";
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

            $sql  = "-- SFP Database Backup\n";
            $sql .= "-- Exported:  " . now()->toIso8601String() . "\n";
            $sql .= "-- Database:  {$dbName}\n";
            $sql .= "-- Generator: dcplibrary/sfp artisan sfp:backup\n\n";
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

            $file = "{$outputDir}/sfp-database-{$timestamp}.sql";
            file_put_contents($file, $sql);
            $written[] = $file;
            $this->line("  ✔ DB      → {$file}");
        }

        // ── Storage Zip ───────────────────────────────────────────────────────
        if ($doStorage) {
            $storagePath = storage_path('app');
            $file        = "{$outputDir}/sfp-storage-{$timestamp}.zip";

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
        return Command::SUCCESS;
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
