<?php

namespace Dcplibrary\Requests\Console\Scheduling;

use Illuminate\Support\Facades\Artisan;

/**
 * Registered by {@see \Dcplibrary\Requests\RequestsServiceProvider} when
 * {@code config('requests.log_pruning.enabled')} is true.
 *
 * Runs {@code requests:prune-logs} using {@code requests.log_pruning.retention_days} and path.
 */
class RunScheduledLogPrune
{
    public function __invoke(): void
    {
        if (! (bool) config('requests.log_pruning.enabled', true)) {
            return;
        }

        Artisan::call('requests:prune-logs');
    }
}
