<?php

namespace Dcplibrary\Requests\Console\Scheduling;

use Dcplibrary\Requests\Models\Setting;
use Illuminate\Support\Facades\Artisan;

/**
 * Callable registered by {@see \Dcplibrary\Requests\RequestsServiceProvider} on the Laravel {@see \Illuminate\Console\Scheduling\Schedule}.
 *
 * Reads {@see Setting} keys: {@code backup_schedule_enabled}, {@code backup_schedule_include_config},
 * {@code backup_schedule_include_db}, {@code backup_schedule_include_storage}, {@code backup_schedule_prune},
 * {@code backup_schedule_path}. When disabled or no backup types are selected, exits without running Artisan.
 * Otherwise runs {@code requests:backup} with the equivalent CLI flags.
 */
class RunScheduledBackup
{
    /**
     * Execute one scheduled backup run (non-interactive, from {@code schedule:run}).
     */
    public function __invoke(): void
    {
        if (! (bool) Setting::get('backup_schedule_enabled', false)) {
            return;
        }

        $config  = (bool) Setting::get('backup_schedule_include_config', true);
        $db      = (bool) Setting::get('backup_schedule_include_db', true);
        $storage = (bool) Setting::get('backup_schedule_include_storage', false);

        if (! $config && ! $db && ! $storage) {
            return;
        }

        $params = [];
        if ($config) {
            $params['--config'] = true;
        }
        if ($db) {
            $params['--db'] = true;
        }
        if ($storage) {
            $params['--storage'] = true;
        }
        if ((bool) Setting::get('backup_schedule_prune', true)) {
            $params['--prune'] = true;
        }

        $path = trim((string) Setting::get('backup_schedule_path', ''));
        if ($path !== '') {
            $params['--path'] = $path;
        }

        Artisan::call('requests:backup', $params);
    }
}
