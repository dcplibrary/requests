<?php

namespace Dcplibrary\Requests\Console\Commands;

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
use Dcplibrary\Requests\Support\StorageAppBackupArchive;
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
                               'applies_to_ill', 'staff_email_quick_action', 'description'])
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

                    'staff_routing_templates' => StaffRoutingTemplate::with('selectorGroup')
                        ->get()
                        ->map(fn (StaffRoutingTemplate $t) => [
                            'selector_group_name' => $t->selectorGroup?->name,
                            'name'                => $t->name,
                            'enabled'             => $t->enabled,
                            'subject'             => $t->subject,
                            'body'                => $t->body,
                        ])
                        ->all(),

                    'patron_status_templates' => PatronStatusTemplate::with(['requestStatuses', 'fieldOptions.field'])
                        ->orderBy('sort_order')
                        ->get()
                        ->map(fn (PatronStatusTemplate $t) => [
                            'name'                 => $t->name,
                            'enabled'              => $t->enabled,
                            'subject'              => $t->subject,
                            'body'                 => $t->body,
                            'sort_order'           => $t->sort_order,
                            'is_default'           => $t->is_default,
                            'trigger_on_ill_conversion' => (bool) ($t->trigger_on_ill_conversion ?? false),
                            'request_status_slugs' => $t->requestStatuses->pluck('slug')->all(),
                            'field_option_slugs'   => $t->fieldOptions->map(fn ($o) => [
                                'field_key' => $o->field?->key,
                                'slug'      => $o->slug,
                            ])->all(),
                        ])
                        ->all(),
                ],
            ];

            $file = "{$outputDir}/requests-config-{$timestamp}.json";
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $written[] = $file;
            $this->line("  ✔ Config  → {$file}");
        }

        // ── Database JSON (database-agnostic) ────────────────────────────────
        if ($doDb) {
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
            $file = "{$outputDir}/requests-database-{$timestamp}.json";
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
                        if (StorageAppBackupArchive::shouldExcludeRelativePath($relativePath)) {
                            continue;
                        }
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
     * Export per-form field configuration rows.
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
     * Export per-form field option overrides.
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
}
