<?php

namespace Dcplibrary\Requests\Jobs;

use Dcplibrary\Requests\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deletes server-side backup files older than the configured retention period.
 *
 * Dispatch this job from the queue worker or via the artisan scheduler:
 *
 *   PruneBackupsJob::dispatch();
 *
 * Or trigger it manually from the Backups admin UI.
 * The retention period is configured in Settings → Backup Retention (backup_retention_days).
 */
class PruneBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $days   = (int) (Setting::where('key', 'backup_retention_days')->value('value') ?: 30);
        $cutoff = now()->subDays($days)->getTimestamp();
        $dir    = storage_path('app/requests-backups');

        if (! is_dir($dir)) {
            Log::info('Backup pruning: directory does not exist, nothing to prune.');
            return;
        }

        $files  = glob($dir . DIRECTORY_SEPARATOR . 'requests-*.{json,sql,zip}', GLOB_BRACE) ?: [];
        $pruned = 0;

        foreach ($files as $path) {
            if (filemtime($path) < $cutoff) {
                if (@unlink($path)) {
                    $pruned++;
                    Log::debug('Backup pruning: removed ' . basename($path));
                } else {
                    Log::warning('Backup pruning: could not remove ' . basename($path));
                }
            }
        }

        Log::info("Backup pruning complete: {$pruned} file(s) removed (retention: {$days} days).");
    }
}
