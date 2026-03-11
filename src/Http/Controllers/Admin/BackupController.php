<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Jobs\PruneBackupsJob;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\CatalogFormatLabel;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class BackupController extends Controller
{
    /** Directory where artisan requests:backup writes files. */
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/requests-backups');
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $retentionDays = (int) (Setting::where('key', 'backup_retention_days')->value('value') ?: 30);

        return view('requests::staff.settings.backups', [
            'serverFiles'   => $this->scanServerFiles(),
            'retentionDays' => $retentionDays,
        ]);
    }

    // ── Configuration Export ──────────────────────────────────────────────────

    public function exportConfig()
    {
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
                    ->get(['slug', 'name', 'color', 'is_terminal', 'sort_order', 'active'])
                    ->toArray(),

                'material_types' => $this->exportFieldOptions('material_type'),

                'audiences' => $this->exportFieldOptions('audience'),

                'selector_groups' => SelectorGroup::with('fieldOptions.field')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($g) => [
                        'name'                => $g->name,
                        'description'         => $g->description,
                        'active'              => $g->active,
                        'material_type_slugs' => $g->fieldOptions->filter(fn ($o) => $o->field?->key === 'material_type')->pluck('slug')->all(),
                        'audience_slugs'      => $g->fieldOptions->filter(fn ($o) => $o->field?->key === 'audience')->pluck('slug')->all(),
                    ])
                    ->all(),

                'catalog_format_labels' => CatalogFormatLabel::orderBy('id')
                    ->get(['format_code', 'label'])
                    ->toArray(),
            ],
        ];

        $filename = 'requests-config-' . now()->format('Y-m-d-His') . '.json';
        $json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Configuration Import ──────────────────────────────────────────────────

    public function importConfig(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json,txt|max:5120',
        ]);

        $raw     = file_get_contents($request->file('backup_file')->getRealPath());
        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['version'], $payload['data'])) {
            return back()->withErrors(['backup_file' => 'Invalid backup file format.']);
        }

        $summary = $this->applyConfigPayload($payload['data']);
        return back()->with('success', "Config import complete: {$summary}.");
    }

    // ── Restore from server backup ────────────────────────────────────────────

    public function restoreFromServer(Request $request)
    {
        $request->validate([
            'filename' => ['required', 'string', 'regex:/^requests-[\w\-]+\.(sql|json)$/'],
        ]);

        $filename = $request->input('filename');
        $path     = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        // Prevent any path traversal
        if (! str_starts_with(realpath($path) ?: '', realpath($this->backupDir) ?: $this->backupDir)) {
            return back()->withErrors(['filename' => 'Invalid file path.']);
        }

        if (! file_exists($path)) {
            return back()->withErrors(['filename' => "File not found: {$filename}"]);
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        // ── SQL restore ───────────────────────────────────────────────────────
        if ($ext === 'sql') {
            try {
                $pdo = DB::connection()->getPdo();
                foreach ($this->splitSqlStatements(file_get_contents($path)) as $statement) {
                    $pdo->exec($statement);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['filename' => 'Database restore failed: ' . $e->getMessage()]);
            }
            Cache::flush();
            return back()->with('success', "Database restored from {$filename}.");
        }

        // ── JSON config restore ───────────────────────────────────────────────
        if ($ext === 'json') {
            $payload = json_decode(file_get_contents($path), true);

            if (! is_array($payload) || ! isset($payload['version'], $payload['data'])) {
                return back()->withErrors(['filename' => 'Invalid config backup file.']);
            }

            $summary = $this->applyConfigPayload($payload['data']);
            return back()->with('success', "Config restored from {$filename}: {$summary}.");
        }

        return back()->withErrors(['filename' => 'Unsupported file type.']);
    }

    // ── Download server backup file ───────────────────────────────────────────

    public function downloadFromServer(Request $request)
    {
        $request->validate([
            'filename' => ['required', 'string', 'regex:/^requests-[\w\-]+\.(sql|json|zip)$/'],
        ]);

        $filename = $request->query('filename');
        $path     = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        if (! str_starts_with(realpath($path) ?: '', realpath($this->backupDir) ?: $this->backupDir)) {
            abort(403);
        }

        if (! file_exists($path)) {
            abort(404);
        }

        $mimes = ['sql' => 'application/octet-stream', 'json' => 'application/json', 'zip' => 'application/zip'];
        $ext   = pathinfo($filename, PATHINFO_EXTENSION);

        return response()->download($path, $filename, [
            'Content-Type' => $mimes[$ext] ?? 'application/octet-stream',
        ]);
    }

    // ── Backup retention ──────────────────────────────────────────────────────

    public function updateRetention(Request $request)
    {
        $request->validate([
            'retention_days' => 'required|integer|min:1|max:3650',
        ]);

        Setting::where('key', 'backup_retention_days')
            ->update(['value' => (string) $request->integer('retention_days')]);
        Cache::forget('setting:backup_retention_days');

        return back()->with('success', 'Backup retention updated.');
    }

    public function pruneBackups()
    {
        PruneBackupsJob::dispatch();
        return back()->with('success', 'Backup pruning job dispatched to the queue.');
    }

    // ── Database Export ───────────────────────────────────────────────────────

    public function exportDatabase()
    {
        $pdo    = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();
        $tables = $this->listTables();
        $q      = $this->qi(...);   // identifier-quoting shorthand

        $sql  = "-- Requests Database Backup\n";
        $sql .= "-- Exported:  " . now()->toIso8601String() . "\n";
        $sql .= "-- Database:  {$dbName}\n";
        $sql .= "-- Generator: dcplibrary/requests\n\n";
        $sql .= $this->fkOff() . ";\n\n";

        foreach ($tables as $table) {
            $createSql = $this->showCreateTable($table);

            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table: {$q($table)}\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS {$q($table)};\n";
            $sql .= $createSql . ";\n\n";

            // INSERT rows
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

        $filename = 'requests-database-' . now()->format('Y-m-d-His') . '.sql';

        return response($sql, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Database Import ───────────────────────────────────────────────────────

    public function importDatabase(Request $request)
    {
        $request->validate([
            'sql_file' => 'required|file|max:102400', // 100 MB
        ]);

        $sql = file_get_contents($request->file('sql_file')->getRealPath());

        try {
            $pdo = DB::connection()->getPdo();
            foreach ($this->splitSqlStatements($sql) as $statement) {
                $pdo->exec($statement);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['sql_file' => 'Database restore failed: ' . $e->getMessage()]);
        }

        Cache::flush();

        return back()->with('success', 'Database restored successfully.');
    }

    // ── Save backup to server ─────────────────────────────────────────────────

    /**
     * Write one or both backup files directly to storage/app/requests-backups
     * without sending a download to the browser.
     */
    public function saveToServer(Request $request)
    {
        $request->validate([
            'types' => 'required|array|min:1',
            'types.*' => 'in:config,db',
        ]);

        $types = $request->input('types');

        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d-His');
        $written   = [];
        $errors    = [];

        // ── Config JSON ───────────────────────────────────────────────────────
        if (in_array('config', $types)) {
            try {
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
                            ->get(['slug', 'name', 'color', 'is_terminal', 'sort_order', 'active'])
                            ->toArray(),
                        'material_types' => $this->exportFieldOptions('material_type'),
                        'audiences' => $this->exportFieldOptions('audience'),
                        'selector_groups' => SelectorGroup::with('fieldOptions.field')
                            ->orderBy('name')->get()
                            ->map(fn ($g) => [
                                'name'                => $g->name,
                                'description'         => $g->description,
                                'active'              => $g->active,
                                'material_type_slugs' => $g->fieldOptions->filter(fn ($o) => $o->field?->key === 'material_type')->pluck('slug')->all(),
                                'audience_slugs'      => $g->fieldOptions->filter(fn ($o) => $o->field?->key === 'audience')->pluck('slug')->all(),
                            ])->all(),
                        'catalog_format_labels' => CatalogFormatLabel::orderBy('id')
                            ->get(['format_code', 'label'])->toArray(),
                    ],
                ];

                $file = $this->backupDir . DIRECTORY_SEPARATOR . "requests-config-{$timestamp}.json";
                file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $written[] = "requests-config-{$timestamp}.json";
            } catch (\Throwable $e) {
                $errors[] = "Config backup failed: {$e->getMessage()}";
            }
        }

        // ── Database SQL ──────────────────────────────────────────────────────
        if (in_array('db', $types)) {
            try {
                $pdo    = DB::connection()->getPdo();
                $dbName = DB::connection()->getDatabaseName();
                $tables = $this->listTables();
                $q      = $this->qi(...);

                $sql  = "-- Requests Database Backup\n";
                $sql .= "-- Exported:  " . now()->toIso8601String() . "\n";
                $sql .= "-- Database:  {$dbName}\n";
                $sql .= "-- Generator: dcplibrary/requests (manual)\n\n";
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
                            $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                            return '  (' . implode(', ', $vals) . ')';
                        })->implode(",\n");
                        $sql .= $allValues . ";\n\n";
                    }
                }

                $sql .= $this->fkOn() . ";\n";

                $file = $this->backupDir . DIRECTORY_SEPARATOR . "requests-database-{$timestamp}.sql";
                file_put_contents($file, $sql);
                $written[] = "requests-database-{$timestamp}.sql";
            } catch (\Throwable $e) {
                $errors[] = "Database backup failed: {$e->getMessage()}";
            }
        }

        if ($errors) {
            return back()->withErrors(['save' => implode(' ', $errors)]);
        }

        $names = implode(' and ', $written);
        return back()->with('success', "Saved to server: {$names}");
    }

    // ── Storage Export ────────────────────────────────────────────────────────

    public function exportStorage()
    {
        $storagePath = storage_path('app');
        $filename    = 'requests-storage-' . now()->format('Y-m-d-His') . '.zip';
        $tmpPath     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['error' => 'Could not create zip archive.']);
        }

        if (is_dir($storagePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($storagePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath     = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($storagePath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // ── Wipe Everything ───────────────────────────────────────────────────────

    /**
     * Truncate every table in the database. This is a full wipe —
     * requests, patrons, titles, and all configuration. Use with caution.
     */
    public function wipeAll(Request $request)
    {
        $request->validate([
            'confirm_wipe' => 'required|in:WIPE',
        ], [
            'confirm_wipe.required' => 'Type WIPE to confirm.',
            'confirm_wipe.in'       => 'You must type WIPE exactly to confirm.',
        ]);

        DB::statement($this->fkOff());
        foreach ($this->listTables() as $table) {
            DB::table($table)->truncate();
        }
        DB::statement($this->fkOn());

        Cache::flush();

        return back()->with('success', 'All data has been wiped. Every table in the database has been cleared.');
    }

    // ── Shared config import logic ────────────────────────────────────────────

    /**
     * Apply a decoded config payload array to the database.
     * Returns a human-readable summary string.
     */
    private function applyConfigPayload(array $data): string
    {
        $results = [];

        DB::transaction(function () use ($data, &$results) {

            // Settings
            if (! empty($data['settings'])) {
                $updated = 0;
                foreach ($data['settings'] as $item) {
                    $rows = Setting::where('key', $item['key'])->update(['value' => $item['value']]);
                    if ($rows) {
                        $updated++;
                        Cache::forget("setting:{$item['key']}");
                    }
                }
                $results[] = "{$updated} setting(s) restored";
            }

            // Request Statuses
            if (! empty($data['request_statuses'])) {
                $upserted = 0;
                foreach ($data['request_statuses'] as $row) {
                    $slug = $row['slug'] ?? Str::slug($row['name']);
                    RequestStatus::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name'        => $row['name'],
                            'color'       => $row['color'],
                            'is_terminal' => (bool) ($row['is_terminal'] ?? false),
                            'sort_order'  => (int)  ($row['sort_order'] ?? 0),
                            'active'      => (bool) ($row['active'] ?? true),
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} request status(es) restored";
            }

            // Material Types (field_options for 'material_type')
            if (! empty($data['material_types'])) {
                $results[] = $this->importFieldOptions('material_type', $data['material_types']) . ' material type(s) restored';
            }

            // Audiences (field_options for 'audience')
            if (! empty($data['audiences'])) {
                $results[] = $this->importFieldOptions('audience', $data['audiences']) . ' audience(s) restored';
            }

            // Selector Groups
            if (! empty($data['selector_groups'])) {
                $upserted = 0;
                foreach ($data['selector_groups'] as $row) {
                    $group = SelectorGroup::updateOrCreate(
                        ['name' => $row['name']],
                        [
                            'description' => $row['description'] ?? null,
                            'active'      => (bool) ($row['active'] ?? true),
                        ]
                    );

                    $optionIds = collect();
                    if (isset($row['material_type_slugs'])) {
                        $optionIds = $optionIds->merge(
                            FieldOption::whereHas('field', fn ($q) => $q->where('key', 'material_type'))
                                ->whereIn('slug', $row['material_type_slugs'])
                                ->pluck('id')
                        );
                    }
                    if (isset($row['audience_slugs'])) {
                        $optionIds = $optionIds->merge(
                            FieldOption::whereHas('field', fn ($q) => $q->where('key', 'audience'))
                                ->whereIn('slug', $row['audience_slugs'])
                                ->pluck('id')
                        );
                    }
                    $group->fieldOptions()->sync($optionIds->all());

                    $upserted++;
                }
                $results[] = "{$upserted} selector group(s) restored";
            }

            // Catalog Format Labels
            if (! empty($data['catalog_format_labels'])) {
                $upserted = 0;
                foreach ($data['catalog_format_labels'] as $row) {
                    if (empty($row['format_code'])) {
                        continue;
                    }
                    CatalogFormatLabel::updateOrCreate(
                        ['format_code' => $row['format_code']],
                        ['label'       => $row['label']]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} catalog format label(s) restored";
            }
        });

        return implode(', ', $results) ?: 'Nothing to restore.';
    }

    // ── Server backup file listing ────────────────────────────────────────────

    /**
     * Scan the local backup directory and return file metadata grouped by type.
     * Returns ['config' => [...], 'db' => [...], 'storage' => [...]]
     */
    private function scanServerFiles(): array
    {
        $groups = ['config' => [], 'db' => [], 'storage' => []];

        if (! is_dir($this->backupDir)) {
            return $groups;
        }

        $files = glob($this->backupDir . DIRECTORY_SEPARATOR . 'requests-*.{json,sql,zip}', GLOB_BRACE) ?: [];

        foreach ($files as $path) {
            $name = basename($path);
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $meta = [
                'name'     => $name,
                'size'     => filesize($path),
                'modified' => filemtime($path),
            ];

            if ($ext === 'json')      $groups['config'][]  = $meta;
            elseif ($ext === 'sql')   $groups['db'][]      = $meta;
            elseif ($ext === 'zip')   $groups['storage'][] = $meta;
        }

        // Newest first within each group
        foreach ($groups as &$group) {
            usort($group, fn ($a, $b) => $b['modified'] <=> $a['modified']);
        }

        return $groups;
    }

    // ── SQL statement splitter ────────────────────────────────────────────────

    /**
     * Split a multi-statement SQL dump into individual statements.
     *
     * Uses a character-level state machine to correctly handle semicolons
     * that appear inside quoted string literals or quoted identifiers,
     * and skips single-line (--) comments entirely.
     *
     * This is required because SQLite's PDO::exec() only processes the first
     * statement in a multi-statement string and silently ignores the rest
     * (or throws a syntax error on drivers that reject multi-statement input).
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $i          = 0;
        $len        = strlen($sql);

        while ($i < $len) {
            $ch = $sql[$i];

            // Skip line comments (-- ... \n)
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Quoted strings/identifiers: copy verbatim until matching close quote
            if ($ch === "'" || $ch === '"') {
                $quote    = $ch;
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    $c        = $sql[$i];
                    $current .= $c;
                    $i++;
                    if ($c === $quote) {
                        // SQL escaped quote: '' or ""
                        if ($i < $len && $sql[$i] === $quote) {
                            $current .= $sql[$i++];
                        } else {
                            break;
                        }
                    }
                }
                continue;
            }

            // Statement terminator
            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $ch;
            $i++;
        }

        // Trailing statement without a trailing semicolon
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return array_values(array_filter($statements));
    }

    // ── Driver-aware helpers ──────────────────────────────────────────────────

    /** Return the list of user-defined table names for the current connection. */
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

    /** Return the CREATE TABLE DDL string for a given table. */
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

    /** SQL statement to disable foreign-key checks (driver-aware). */
    private function fkOff(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys = OFF'
            : 'SET FOREIGN_KEY_CHECKS=0';
    }

    /** SQL statement to re-enable foreign-key checks (driver-aware). */
    private function fkOn(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys = ON'
            : 'SET FOREIGN_KEY_CHECKS=1';
    }

    /** Quote a table or column identifier for the current driver. */
    private function qi(string $name): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '"' . $name . '"'
            : '`' . $name . '`';
    }

    /**
     * Export field options for a given field key in the legacy backup format.
     *
     * @param  string  $fieldKey  e.g. 'material_type', 'audience'
     * @return list<array<string, mixed>>
     */
    private function exportFieldOptions(string $fieldKey): array
    {
        $field = Field::where('key', $fieldKey)->first();
        if (! $field) {
            return [];
        }

        return FieldOption::where('field_id', $field->id)
            ->ordered()
            ->get()
            ->map(function (FieldOption $o) {
                $meta = $o->metadata;
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?: [];
                }

                return array_merge(
                    [
                        'slug'       => $o->slug,
                        'name'       => $o->name,
                        'sort_order' => $o->sort_order,
                        'active'     => $o->active,
                    ],
                    is_array($meta) ? $meta : []
                );
            })
            ->all();
    }

    /**
     * Import field options from the legacy backup format.
     *
     * @param  string                    $fieldKey  e.g. 'material_type', 'audience'
     * @param  list<array<string, mixed>> $rows
     * @return int  Number of upserted rows
     */
    private function importFieldOptions(string $fieldKey, array $rows): int
    {
        $field = Field::where('key', $fieldKey)->first();
        if (! $field) {
            return 0;
        }

        $knownMeta = ['has_other_text', 'ill_enabled', 'isbndb_searchable', 'bibliocommons_value'];
        $upserted  = 0;

        foreach ($rows as $row) {
            $slug = $row['slug'] ?? Str::slug($row['name']);
            $metadata = array_filter(
                array_intersect_key($row, array_flip($knownMeta)),
                fn ($v) => $v !== null
            );

            FieldOption::updateOrCreate(
                ['field_id' => $field->id, 'slug' => $slug],
                [
                    'name'       => $row['name'],
                    'metadata'   => $metadata ?: null,
                    'sort_order' => (int)  ($row['sort_order'] ?? 0),
                    'active'     => (bool) ($row['active'] ?? true),
                ]
            );
            $upserted++;
        }

        return $upserted;
    }
}
