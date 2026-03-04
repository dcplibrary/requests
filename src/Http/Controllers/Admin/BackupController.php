<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\CatalogFormatLabel;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\SelectorGroup;
use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class BackupController extends Controller
{
    // ── View ──────────────────────────────────────────────────────────────────

    public function index()
    {
        return view('sfp::staff.settings.backups');
    }

    // ── Configuration Export ──────────────────────────────────────────────────

    public function exportConfig()
    {
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

                'catalog_format_labels' => CatalogFormatLabel::orderBy('sort_order')
                    ->get(['label', 'sort_order'])
                    ->toArray(),
            ],
        ];

        $filename = 'sfp-config-' . now()->format('Y-m-d-His') . '.json';
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

        $data    = $payload['data'];
        $results = [];

        DB::transaction(function () use ($data, &$results) {

            // ── Settings ──────────────────────────────────────────────────────
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

            // ── Request Statuses ──────────────────────────────────────────────
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

            // ── Material Types ────────────────────────────────────────────────
            if (! empty($data['material_types'])) {
                $upserted = 0;
                foreach ($data['material_types'] as $row) {
                    $slug = $row['slug'] ?? Str::slug($row['name']);
                    MaterialType::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name'           => $row['name'],
                            'has_other_text' => (bool) ($row['has_other_text'] ?? false),
                            'sort_order'     => (int)  ($row['sort_order'] ?? 0),
                            'active'         => (bool) ($row['active'] ?? true),
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} material type(s) restored";
            }

            // ── Audiences ─────────────────────────────────────────────────────
            if (! empty($data['audiences'])) {
                $upserted = 0;
                foreach ($data['audiences'] as $row) {
                    $slug = $row['slug'] ?? Str::slug($row['name']);
                    Audience::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name'               => $row['name'],
                            'bibliocommons_value' => $row['bibliocommons_value'] ?? null,
                            'sort_order'         => (int)  ($row['sort_order'] ?? 0),
                            'active'             => (bool) ($row['active'] ?? true),
                        ]
                    );
                    $upserted++;
                }
                $results[] = "{$upserted} audience(s) restored";
            }

            // ── Selector Groups ───────────────────────────────────────────────
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

                    if (isset($row['material_type_slugs'])) {
                        $mtIds = MaterialType::whereIn('slug', $row['material_type_slugs'])->pluck('id');
                        $group->materialTypes()->sync($mtIds);
                    }

                    if (isset($row['audience_slugs'])) {
                        $audIds = Audience::whereIn('slug', $row['audience_slugs'])->pluck('id');
                        $group->audiences()->sync($audIds);
                    }

                    $upserted++;
                }
                $results[] = "{$upserted} selector group(s) restored";
            }

            // ── Catalog Format Labels ─────────────────────────────────────────
            if (! empty($data['catalog_format_labels'])) {
                CatalogFormatLabel::truncate();
                $labels = collect($data['catalog_format_labels'])
                    ->map(fn ($row) => [
                        'label'      => $row['label'],
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                    ])->all();
                CatalogFormatLabel::insert($labels);
                $results[] = count($labels) . ' catalog format label(s) restored';
            }
        });

        $summary = implode(', ', $results) ?: 'Nothing to restore.';
        return back()->with('success', "Config import complete: {$summary}.");
    }

    // ── Database Export ───────────────────────────────────────────────────────

    public function exportDatabase()
    {
        $pdo    = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $col    = 'Tables_in_' . $dbName;

        $sql  = "-- SFP Database Backup\n";
        $sql .= "-- Exported:  " . now()->toIso8601String() . "\n";
        $sql .= "-- Database:  {$dbName}\n";
        $sql .= "-- Generator: dcplibrary/sfp\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $tableRow) {
            $table = $tableRow->$col;

            // CREATE TABLE
            $create    = DB::select("SHOW CREATE TABLE `{$table}`");
            $createSql = $create[0]->{'Create Table'};

            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table: `{$table}`\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createSql . ";\n\n";

            // INSERT rows
            $rows = DB::table($table)->get();
            if ($rows->isNotEmpty()) {
                $columns = array_keys((array) $rows->first());
                $colList = '`' . implode('`, `', $columns) . '`';
                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";

                $allValues = $rows->map(function ($row) use ($pdo) {
                    $vals = array_map(function ($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote((string) $v);
                    }, (array) $row);
                    return '  (' . implode(', ', $vals) . ')';
                })->implode(",\n");

                $sql .= $allValues . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $filename = 'sfp-database-' . now()->format('Y-m-d-His') . '.sql';

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
            DB::connection()->getPdo()->exec($sql);
        } catch (\Exception $e) {
            return back()->withErrors(['sql_file' => 'Database restore failed: ' . $e->getMessage()]);
        }

        Cache::flush();

        return back()->with('success', 'Database restored successfully.');
    }

    // ── Storage Export ────────────────────────────────────────────────────────

    public function exportStorage()
    {
        $storagePath = storage_path('app');
        $filename    = 'sfp-storage-' . now()->format('Y-m-d-His') . '.zip';
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

        $dbName = DB::connection()->getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $col    = 'Tables_in_' . $dbName;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $tableRow) {
            DB::table($tableRow->$col)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Cache::flush();

        return back()->with('success', 'All data has been wiped. Every table in the database has been cleared.');
    }
}
