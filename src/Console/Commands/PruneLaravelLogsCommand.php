<?php

namespace Dcplibrary\Requests\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Remove rotated / stale {@code *.log} files under Laravel's log directory (default {@code storage/logs}).
 *
 * The active {@code laravel.log} is only removed if it has not been modified within the retention window
 * (unusual for a running app). Daily log files such as {@code laravel-2026-03-01.log} are removed once past retention.
 */
class PruneLaravelLogsCommand extends Command
{
    protected $signature = 'requests:prune-logs
        {--days= : Retention period in days (default: requests.log_pruning.retention_days)}
        {--path= : Absolute path to a log directory inside storage/ (default: storage/logs)}
        {--dry-run : List files that would be deleted without removing them}';

    protected $description = 'Delete log files in storage/logs older than the configured retention period.';

    public function handle(): int
    {
        $days = $this->option('days');
        $days = $days !== null && $days !== ''
            ? max(1, (int) $days)
            : max(1, (int) config('requests.log_pruning.retention_days', 14));

        $dirOption = $this->option('path');
        $dir = $dirOption !== null && $dirOption !== ''
            ? $dirOption
            : (config('requests.log_pruning.path') ?: storage_path('logs'));

        $storageRoot = realpath(storage_path()) ?: storage_path();
        $resolved = realpath($dir);
        if ($resolved === false) {
            if (! is_dir($dir)) {
                $this->warn("Log directory does not exist: {$dir}");

                return self::SUCCESS;
            }
            $resolved = $dir;
        }

        if (! str_starts_with($resolved, $storageRoot)) {
            $this->error('Log path must be inside the application storage directory.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');

        $finder = new Finder();
        $finder->files()->in($resolved)->depth('== 0')->name('*.log');

        $removed = 0;
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] would remove: ' . basename($path) . ' (' . $this->ageLabel($mtime) . ')');
                $removed++;

                continue;
            }

            if (@unlink($path)) {
                $this->line('Removed: ' . basename($path));
                $removed++;
            } else {
                $this->warn('Could not remove: ' . basename($path));
            }
        }

        if ($removed === 0) {
            $this->info('No log files older than ' . $days . ' day(s) in ' . $resolved . '.');

            return self::SUCCESS;
        }

        $this->info($dryRun ? "{$removed} file(s) would be removed." : "Pruned {$removed} log file(s).");

        return self::SUCCESS;
    }

    private function ageLabel(int $mtime): string
    {
        $d = (int) floor((time() - $mtime) / 86400);

        return $d . 'd old';
    }
}
