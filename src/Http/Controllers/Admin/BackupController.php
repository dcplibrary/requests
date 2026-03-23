<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Jobs\PruneBackupsJob;
use Dcplibrary\Requests\Models\CatalogFormatLabel;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Dcplibrary\Requests\Models\PatronStatusTemplate;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\StaffRoutingTemplate;
use Dcplibrary\Requests\Services\SqlStatementSplitter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Configuration and database backup/export, server-side backup files, retention, and wipe.
 *
 * ## Config export
 * Covers all non-transient data needed to fully restore the application on a fresh install
 * (after migrations and seeding have run):
 *   - settings (all key/value pairs)
 *   - request_statuses (slug, name, color, icon, sort order, SFP/ILL scope, terminal, patron
 *     notification, action label, advance-on-claim, description)
 *   - forms (sfp / ill — used as FK parents for field config)
 *   - fields (all field definitions — type, label, scope, sort order, required, token, filterable,
 *     conditional logic) with field_options nested per field (slug, name, sort order, metadata)
 *   - form_field_config (per-form visibility, sort order, required, step, label override,
 *     conditional logic — keyed by form slug + field key)
 *   - form_field_option_overrides (per-form option label/visibility/order overrides)
 *   - selector_groups (name, description, active, linked material type and audience slugs)
 *   - catalog_format_labels (BiblioCommons format code → display label mappings)
 *   - staff_routing_templates (per-selector-group email subject and body)
 *   - patron_status_templates (patron notification templates with linked statuses and field options)
 *
 * Old backup files that use the legacy material_types / audiences keys are still importable
 * via the backward-compat path in applyConfigPayload().
 *
 * ## Database export
 * saveToServer() and the artisan command produce a driver-agnostic JSON dump of all tables
 * (including transient data: requests, patrons, materials, and history). A MySQL-specific SQL
 * dump is still available via exportDatabase() for direct server restores.
 */
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
                    ->get(['slug', 'name', 'color', 'icon', 'sort_order', 'active', 'is_terminal',
                           'notify_patron', 'action_label', 'advance_on_claim', 'applies_to_sfp',
                           'applies_to_ill', 'description'])
                    ->toArray(),

                'forms' => Form::orderBy('slug')->get(['slug', 'name'])->toArray(),

                'fields' => $this->exportFields(),

                'form_field_config' => $this->exportFormFieldConfig(),

                'form_field_option_overrides' => $this->exportFormFieldOptionOverrides(),

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

                'staff_routing_templates' => $this->exportStaffRoutingTemplates(),

                'patron_status_templates' => $this->exportPatronStatusTemplates(),
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

        // ── Full database JSON restore ────────────────────────────────────────
        if ($ext === 'json') {
            $payload = json_decode(file_get_contents($path), true);
            if (is_array($payload) && isset($payload['tables'])) {
                try {
                    $this->restoreDatabaseFromJsonPayload($payload['tables']);
                } catch (\Throwable $e) {
                    return back()->withErrors(['filename' => 'Database restore failed: ' . $e->getMessage()]);
                }
                Cache::flush();
                return back()->with('success', "Database restored from {$filename}.");
            }
            // Fall through to config restore if payload has 'data' not 'tables'
            if (! is_array($payload) || ! isset($payload['data'])) {
                return back()->withErrors(['filename' => 'Invalid backup file.']);
            }
            $summary = $this->applyConfigPayload($payload['data']);
            return back()->with('success', "Config restored from {$filename}: {$summary}.");
        }

        // ── SQL restore ───────────────────────────────────────────────────────
        if ($ext === 'sql') {
            try {
                $driver     = DB::connection()->getDriverName();
                $statements = SqlStatementSplitter::filterForDriver(SqlStatementSplitter::split(file_get_contents($path)), $driver);
                foreach ($statements as $statement) {
                    if ($statement !== '') {
                        DB::unprepared($statement);
                    }
                }
            } catch (\Exception $e) {
                return back()->withErrors(['filename' => 'Database restore failed: ' . $e->getMessage()]);
            }
            Cache::flush();
            return back()->with('success', "Database restored from {$filename}.");
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

    // ── Delete server backup file ─────────────────────────────────────────────

    public function deleteFromServer(Request $request)
    {
        $request->validate([
            'filename' => ['required', 'string', 'regex:/^requests-[\w\-]+\.(sql|json|zip)$/'],
        ]);

        $filename = $request->input('filename');
        $path     = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        if (! str_starts_with(realpath($path) ?: '', realpath($this->backupDir) ?: $this->backupDir)) {
            return back()->withErrors(['filename' => 'Invalid file path.']);
        }

        if (! file_exists($path)) {
            return back()->withErrors(['filename' => "File not found: {$filename}"]);
        }

        if (! @unlink($path)) {
            return back()->withErrors(['filename' => "Could not delete {$filename}"]);
        }

        return back()->with('success', "Deleted {$filename}.");
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
            $driver     = DB::connection()->getDriverName();
            $statements = SqlStatementSplitter::filterForDriver(SqlStatementSplitter::split($sql), $driver);
            foreach ($statements as $statement) {
                if ($statement !== '') {
                    DB::unprepared($statement);
                }
            }
        } catch (\Exception $e) {
            return back()->withErrors(['sql_file' => 'Database restore failed: ' . $e->getMessage()]);
        }

        Cache::flush();

        return back()->with('success', 'Database restored successfully.');
    }

    // ── Database Export (JSON) ──────────────────────────────────────────────

    /**
     * Export the full database as JSON (tables and rows).
     * Driver-agnostic: restore with importDatabaseFromJson() avoids SQL dialect issues.
     */
    public function exportDatabaseJson()
    {
        $tables = $this->listTables();
        $payload = [
            'version'     => 1,
            'exported_at' => now()->toIso8601String(),
            'app'         => 'dcplibrary/requests',
            'tables'      => [],
        ];

        foreach ($tables as $table) {
            $rows = DB::table($table)->get();
            $payload['tables'][$table] = $rows->map(fn ($row) => (array) $row)->all();
        }

        $filename = 'requests-database-' . now()->format('Y-m-d-His') . '.json';
        $json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Database Import (JSON) ──────────────────────────────────────────────

    /**
     * Restore the full database from a JSON dump.
     * Uses DB::table()->insert(); no SQL parsing, works on any driver.
     */
    public function importDatabaseFromJson(Request $request)
    {
        $request->validate([
            'db_json_file' => 'required|file|max:102400', // 100 MB
        ]);

        $payload = json_decode(file_get_contents($request->file('db_json_file')->getRealPath()), true);

        if (! is_array($payload) || ! isset($payload['tables'])) {
            return back()->withErrors(['db_json_file' => 'Invalid database backup file — missing "tables" key.']);
        }

        try {
            $this->restoreDatabaseFromJsonPayload($payload['tables']);
        } catch (\Throwable $e) {
            return back()->withErrors(['db_json_file' => 'Database restore failed: ' . $e->getMessage()]);
        }

        Cache::flush();

        return back()->with('success', 'Database restored successfully from JSON.');
    }

    /**
     * Restore database from a payload['tables'] array (table name => list of rows).
     * Disables FKs, truncates tables in list order, inserts in same order.
     */
    private function restoreDatabaseFromJsonPayload(array $tablesPayload): void
    {
        $driver  = DB::connection()->getDriverName();
        $isSqlite = $driver === 'sqlite';

        DB::statement($isSqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_keys($tablesPayload) as $table) {
                if ($isSqlite) {
                    DB::table($table)->delete();
                } else {
                    DB::table($table)->truncate();
                }
            }

            foreach ($tablesPayload as $table => $rows) {
                if (empty($rows)) {
                    continue;
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        } finally {
            DB::statement($isSqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS=1');
        }
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
            'types.*' => 'in:config,db,db-json',
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
                            ->get(['slug', 'name', 'color', 'icon', 'sort_order', 'active', 'is_terminal',
                                   'notify_patron', 'action_label', 'advance_on_claim', 'applies_to_sfp',
                                   'applies_to_ill', 'description'])
                            ->toArray(),
                        'forms'                       => Form::orderBy('slug')->get(['slug', 'name'])->toArray(),
                        'fields'                      => $this->exportFields(),
                        'form_field_config'           => $this->exportFormFieldConfig(),
                        'form_field_option_overrides' => $this->exportFormFieldOptionOverrides(),
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
                        'staff_routing_templates'     => $this->exportStaffRoutingTemplates(),
                        'patron_status_templates'     => $this->exportPatronStatusTemplates(),
                    ],
                ];

                $file = $this->backupDir . DIRECTORY_SEPARATOR . "requests-config-{$timestamp}.json";
                file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $written[] = "requests-config-{$timestamp}.json";
            } catch (\Throwable $e) {
                $errors[] = "Config backup failed: {$e->getMessage()}";
            }
        }

        // ── Database JSON (database-agnostic) ────────────────────────────────
        if (in_array('db', $types) || in_array('db-json', $types)) {
            try {
                $tables  = $this->listTables();
                $payload = [
                    'version'     => 1,
                    'exported_at' => now()->toIso8601String(),
                    'app'         => 'dcplibrary/requests',
                    'tables'      => [],
                ];
                foreach ($tables as $table) {
                    $rows = DB::table($table)->get();
                    $payload['tables'][$table] = $rows->map(fn ($row) => (array) $row)->all();
                }
                $file = $this->backupDir . DIRECTORY_SEPARATOR . "requests-database-{$timestamp}.json";
                file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $written[] = "requests-database-{$timestamp}.json";
            } catch (\Throwable $e) {
                $errors[] = "Database JSON backup failed: {$e->getMessage()}";
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
     * Upsert a form row from config import.
     *
     * SQLite: if `forms` predates the package migration, `id` may be NOT NULL without
     * AUTOINCREMENT, so Eloquent create/firstOrCreate inserts fail. Assign the next id explicitly.
     */
    private function importFormRow(array $row): void
    {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            return;
        }
        $name = (string) ($row['name'] ?? $slug);

        $existing = Form::where('slug', $slug)->first();
        if ($existing) {
            if ($existing->name !== $name) {
                $existing->update(['name' => $name]);
            }

            return;
        }

        if ($this->importUsesSqliteExplicitIds()) {
            $nextId = $this->sqliteNextId('forms');
            DB::table('forms')->insert([
                'id'         => $nextId,
                'slug'       => $slug,
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        Form::create(['slug' => $slug, 'name' => $name]);
    }

    /**
     * SQLite tables that predate package migrations often have NOT NULL `id` without AUTOINCREMENT.
     */
    private function importUsesSqliteExplicitIds(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    private function sqliteNextId(string $table): int
    {
        return ((int) DB::table($table)->max('id')) + 1;
    }

    /**
     * Prepare row for DB::table()->insert on SQLite (booleans as 0/1, JSON casts encoded).
     *
     * @return array<string, mixed>
     */
    private function configImportAttributesForSqlite(Model $model, int $id): array
    {
        $table   = $model->getTable();
        $columns = array_flip($model->getConnection()->getSchemaBuilder()->getColumnListing($table));

        $attrs = array_merge(['id' => $id], $model->getAttributes());

        foreach ($model->getCasts() as $key => $cast) {
            if (! array_key_exists($key, $attrs) || $attrs[$key] === null) {
                continue;
            }
            $val = $attrs[$key];
            if (in_array($cast, ['bool', 'boolean'], true)) {
                $attrs[$key] = $val ? 1 : 0;
            } elseif (str_starts_with((string) $cast, 'array')
                || str_starts_with((string) $cast, 'json')
                || $cast === 'object') {
                $attrs[$key] = is_string($val) ? $val : json_encode($val);
            }
        }

        foreach ($attrs as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $attrs[$key] = $model->fromDateTime($value);
            }
        }

        return array_intersect_key($attrs, $columns);
    }

    /**
     * Upsert used by config import: update if unique key(s) match, else insert.
     * On SQLite, inserts use an explicit primary key when autoincrement is missing.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function configImportUpdateOrCreate(string $modelClass, array $unique, array $values, bool $withTrashed = false): Model
    {
        $query = $withTrashed ? $modelClass::withTrashed() : $modelClass::query();
        foreach ($unique as $column => $value) {
            $query->where($column, $value);
        }
        $existing = $query->first();
        if ($existing) {
            $existing->fill($values);
            $existing->save();

            return $existing->fresh();
        }

        /** @var Model $model */
        $model = new $modelClass();
        $model->forceFill(array_merge($unique, $values));

        if (in_array('created_by', $model->getFillable(), true)
            && $model->getAttribute('created_by') === null
            && auth()->check()) {
            $model->setAttribute('created_by', (string) auth()->id());
        }

        if (! $this->importUsesSqliteExplicitIds()) {
            $model->save();

            return $model->fresh();
        }

        $table = $model->getTable();
        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }

        $id = $this->sqliteNextId($table);
        DB::table($table)->insert($this->configImportAttributesForSqlite($model, $id));

        return $withTrashed
            ? $modelClass::withTrashed()->findOrFail($id)
            : $modelClass::query()->findOrFail($id);
    }

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
                    $this->configImportUpdateOrCreate(
                        RequestStatus::class,
                        ['slug' => $slug],
                        [
                            'name'             => $row['name'],
                            'color'            => $row['color'],
                            'icon'             => $row['icon'] ?? null,
                            'sort_order'       => (int)  ($row['sort_order'] ?? 0),
                            'active'           => (bool) ($row['active'] ?? true),
                            'is_terminal'      => (bool) ($row['is_terminal'] ?? false),
                            'notify_patron'    => (bool) ($row['notify_patron'] ?? false),
                            'action_label'     => $row['action_label'] ?? null,
                            'advance_on_claim' => (bool) ($row['advance_on_claim'] ?? false),
                            'applies_to_sfp'   => (bool) ($row['applies_to_sfp'] ?? true),
                            'applies_to_ill'   => (bool) ($row['applies_to_ill'] ?? true),
                            'description'      => $row['description'] ?? null,
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} request status(es) restored";
            }

            // Forms (ensure core forms exist; not user-customisable but required as FK parents)
            if (! empty($data['forms'])) {
                foreach ($data['forms'] as $row) {
                    $this->importFormRow($row);
                }
            }

            // Fields (unified field definitions + nested options)
            if (! empty($data['fields'])) {
                $fieldCount = 0;
                foreach ($data['fields'] as $row) {
                    $field = $this->configImportUpdateOrCreate(
                        Field::class,
                        ['key' => $row['key']],
                        [
                            'label'            => $row['label'],
                            'label_overrides'  => $row['label_overrides'] ?? null,
                            'type'             => $row['type'],
                            'step'             => (int)  ($row['step'] ?? 2),
                            'scope'            => $row['scope'] ?? 'both',
                            'sort_order'       => (int)  ($row['sort_order'] ?? 0),
                            'active'           => (bool) ($row['active'] ?? true),
                            'required'         => (bool) ($row['required'] ?? false),
                            'include_as_token' => (bool) ($row['include_as_token'] ?? false),
                            'filterable'       => (bool) ($row['filterable'] ?? false),
                            'condition'        => $row['condition'] ?? null,
                            'deleted_at'       => $row['deleted_at'] ?? null,
                        ],
                        true
                    );

                    foreach ($row['options'] ?? [] as $opt) {
                        $meta = $opt['metadata'] ?? null;
                        $this->configImportUpdateOrCreate(
                            FieldOption::class,
                            ['field_id' => $field->id, 'slug' => $opt['slug']],
                            [
                                'name'       => $opt['name'],
                                'sort_order' => (int)  ($opt['sort_order'] ?? 0),
                                'active'     => (bool) ($opt['active'] ?? true),
                                'metadata'   => is_array($meta)
                                    ? $meta
                                    : (is_string($meta) && $meta !== '' ? json_decode($meta, true) : $meta),
                            ],
                            true
                        );
                    }

                    $fieldCount++;
                }
                $results[] = "{$fieldCount} field(s) restored";
            }

            // Backward-compat: old exports used separate material_types / audiences keys
            if (! empty($data['material_types'])) {
                $results[] = $this->importFieldOptions('material_type', $data['material_types']) . ' material type(s) restored';
            }
            if (! empty($data['audiences'])) {
                $results[] = $this->importFieldOptions('audience', $data['audiences']) . ' audience(s) restored';
            }

            // Form field config (per-form field settings)
            if (! empty($data['form_field_config'])) {
                $upserted = 0;
                foreach ($data['form_field_config'] as $row) {
                    $form  = Form::where('slug', $row['form_slug'] ?? '')->first();
                    $field = Field::withTrashed()->where('key', $row['field_key'] ?? '')->first();
                    if (! $form || ! $field) {
                        continue;
                    }
                    $this->configImportUpdateOrCreate(
                        FormFieldConfig::class,
                        ['form_id' => $form->id, 'field_id' => $field->id],
                        [
                            'label_override'    => $row['label_override'] ?? null,
                            'sort_order'        => (int)  ($row['sort_order'] ?? 0),
                            'required'          => (bool) ($row['required'] ?? false),
                            'visible'           => (bool) ($row['visible'] ?? true),
                            'step'              => (int)  ($row['step'] ?? 2),
                            'conditional_logic' => $row['conditional_logic'] ?? null,
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} form field config(s) restored";
            }

            // Form field option overrides
            if (! empty($data['form_field_option_overrides'])) {
                $upserted = 0;
                foreach ($data['form_field_option_overrides'] as $row) {
                    $form  = Form::where('slug', $row['form_slug'] ?? '')->first();
                    $field = Field::withTrashed()->where('key', $row['field_key'] ?? '')->first();
                    if (! $form || ! $field) {
                        continue;
                    }
                    $this->configImportUpdateOrCreate(
                        FormFieldOptionOverride::class,
                        [
                            'form_id'       => $form->id,
                            'field_id'      => $field->id,
                            'option_slug'   => $row['option_slug'],
                        ],
                        [
                            'label_override' => $row['label_override'] ?? null,
                            'sort_order'     => (int)  ($row['sort_order'] ?? 0),
                            'visible'        => (bool) ($row['visible'] ?? true),
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} form field option override(s) restored";
            }

            // Selector Groups
            if (! empty($data['selector_groups'])) {
                $upserted = 0;
                foreach ($data['selector_groups'] as $row) {
                    $group = $this->configImportUpdateOrCreate(
                        SelectorGroup::class,
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
                    $this->configImportUpdateOrCreate(
                        CatalogFormatLabel::class,
                        ['format_code' => $row['format_code']],
                        ['label' => $row['label']]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} catalog format label(s) restored";
            }

            // Staff Routing Templates
            if (! empty($data['staff_routing_templates'])) {
                $upserted = 0;
                foreach ($data['staff_routing_templates'] as $row) {
                    $group = SelectorGroup::where('name', $row['selector_group_name'] ?? '')->first();
                    if (! $group) {
                        continue;
                    }
                    $this->configImportUpdateOrCreate(
                        StaffRoutingTemplate::class,
                        ['selector_group_id' => $group->id],
                        [
                            'name'    => $row['name'],
                            'enabled' => (bool) ($row['enabled'] ?? true),
                            'subject' => $row['subject'],
                            'body'    => $row['body'] ?? null,
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} staff routing template(s) restored";
            }

            // Patron Status Templates
            if (! empty($data['patron_status_templates'])) {
                $upserted = 0;
                foreach ($data['patron_status_templates'] as $row) {
                    $template = $this->configImportUpdateOrCreate(
                        PatronStatusTemplate::class,
                        ['name' => $row['name']],
                        [
                            'enabled'    => (bool) ($row['enabled'] ?? true),
                            'subject'    => $row['subject'],
                            'body'       => $row['body'] ?? null,
                            'sort_order' => (int) ($row['sort_order'] ?? 0),
                            'is_default' => (bool) ($row['is_default'] ?? false),
                        ]
                    );

                    // Re-link request statuses by slug.
                    if (! empty($row['request_status_slugs'])) {
                        $statusIds = RequestStatus::whereIn('slug', $row['request_status_slugs'])->pluck('id');
                        $template->requestStatuses()->sync($statusIds);
                    }

                    // Re-link field options by field key + slug.
                    if (! empty($row['field_option_slugs'])) {
                        $optionIds = collect();
                        foreach ($row['field_option_slugs'] as $entry) {
                            $id = FieldOption::whereHas('field', fn ($q) => $q->where('key', $entry['field_key']))
                                ->where('slug', $entry['slug'])
                                ->value('id');
                            if ($id) {
                                $optionIds->push($id);
                            }
                        }
                        $template->fieldOptions()->sync($optionIds->all());
                    }

                    $upserted++;
                }
                $results[] = "{$upserted} patron status template(s) restored";
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

            if ($ext === 'json') {
                $groups[str_starts_with($name, 'requests-database-') ? 'db' : 'config'][] = $meta;
            } elseif ($ext === 'sql') {
                $groups['db'][] = $meta;
            } elseif ($ext === 'zip') {
                $groups['storage'][] = $meta;
            }
        }

        // Newest first within each group
        foreach ($groups as &$group) {
            usort($group, fn ($a, $b) => $b['modified'] <=> $a['modified']);
        }

        return $groups;
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
    /**
     * Export all field definitions with nested options.
     *
     * @return list<array<string, mixed>>
     */
    private function exportFields(): array
    {
        return Field::withTrashed()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->ordered()
            ->get()
            ->map(fn (Field $f) => [
                'key'              => $f->key,
                'label'            => $f->label,
                'label_overrides'  => $f->label_overrides,
                'type'             => $f->type,
                'step'             => $f->step,
                'scope'            => $f->scope,
                'sort_order'       => $f->sort_order,
                'active'           => $f->active,
                'required'         => $f->required,
                'include_as_token' => $f->include_as_token,
                'filterable'       => $f->filterable,
                'condition'        => $f->condition,
                'deleted_at'       => $f->deleted_at?->toIso8601String(),
                'options'          => $f->options->map(fn (FieldOption $o) => [
                    'slug'       => $o->slug,
                    'name'       => $o->name,
                    'sort_order' => $o->sort_order,
                    'active'     => $o->active,
                    'metadata'   => is_string($o->metadata) ? json_decode($o->metadata, true) : $o->metadata,
                ])->all(),
            ])
            ->all();
    }

    /**
     * Export per-form field configuration rows, using slugs/keys as portable references.
     *
     * @return list<array<string, mixed>>
     */
    private function exportFormFieldConfig(): array
    {
        return FormFieldConfig::with(['form', 'field'])
            ->get()
            ->map(fn (FormFieldConfig $c) => [
                'form_slug'         => $c->form?->slug,
                'field_key'         => $c->field?->key,
                'label_override'    => $c->label_override,
                'sort_order'        => $c->sort_order,
                'required'          => $c->required,
                'visible'           => $c->visible,
                'step'              => $c->step,
                'conditional_logic' => $c->conditional_logic,
            ])
            ->filter(fn ($row) => $row['form_slug'] && $row['field_key'])
            ->values()
            ->all();
    }

    /**
     * Export per-form field option overrides, using slugs/keys as portable references.
     *
     * @return list<array<string, mixed>>
     */
    private function exportFormFieldOptionOverrides(): array
    {
        return FormFieldOptionOverride::with(['form', 'field'])
            ->get()
            ->map(fn (FormFieldOptionOverride $o) => [
                'form_slug'      => $o->form?->slug,
                'field_key'      => $o->field?->key,
                'option_slug'    => $o->option_slug,
                'label_override' => $o->label_override,
                'sort_order'     => $o->sort_order,
                'visible'        => $o->visible,
            ])
            ->filter(fn ($row) => $row['form_slug'] && $row['field_key'])
            ->values()
            ->all();
    }

    /**
     * Export staff routing templates, keyed to their selector group by name.
     *
     * @return list<array<string, mixed>>
     */
    private function exportStaffRoutingTemplates(): array
    {
        return StaffRoutingTemplate::with('selectorGroup')
            ->get()
            ->map(fn (StaffRoutingTemplate $t) => [
                'selector_group_name' => $t->selectorGroup?->name,
                'name'                => $t->name,
                'enabled'             => $t->enabled,
                'subject'             => $t->subject,
                'body'                => $t->body,
            ])
            ->all();
    }

    /**
     * Export patron status templates with their linked status slugs and field option slugs.
     *
     * @return list<array<string, mixed>>
     */
    private function exportPatronStatusTemplates(): array
    {
        return PatronStatusTemplate::with(['requestStatuses', 'fieldOptions.field'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PatronStatusTemplate $t) => [
                'name'                  => $t->name,
                'enabled'               => $t->enabled,
                'subject'               => $t->subject,
                'body'                  => $t->body,
                'sort_order'            => $t->sort_order,
                'is_default'            => $t->is_default,
                'request_status_slugs'  => $t->requestStatuses->pluck('slug')->all(),
                'field_option_slugs'    => $t->fieldOptions->map(fn ($o) => [
                    'field_key' => $o->field?->key,
                    'slug'      => $o->slug,
                ])->all(),
            ])
            ->all();
    }

    /**
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

            $this->configImportUpdateOrCreate(
                FieldOption::class,
                ['field_id' => $field->id, 'slug' => $slug],
                [
                    'name'       => $row['name'],
                    'metadata'   => $metadata ?: null,
                    'sort_order' => (int)  ($row['sort_order'] ?? 0),
                    'active'     => (bool) ($row['active'] ?? true),
                ],
                true
            );
            $upserted++;
        }

        return $upserted;
    }
}
