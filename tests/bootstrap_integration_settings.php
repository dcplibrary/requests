<?php

// Minimal facade boot for integration tests that rely on Setting::get().
// Keeps SQLite integration tests lightweight while allowing Cache facade usage.

use Illuminate\Container\Container;
use Illuminate\Cache\Repository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Cache;

$app = new class extends Container {
    public function environment(...$environments)
    {
        $current = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'testing');
        if (count($environments) === 0) {
            return $current;
        }
        foreach ($environments as $env) {
            if ($env === $current) {
                return true;
            }
        }
        return false;
    }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);

$app->singleton('cache', function () {
    return new Repository(new ArrayStore());
});

Cache::swap($app->make('cache'));

