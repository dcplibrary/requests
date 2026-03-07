<?php

// Minimal facade boot for integration tests that rely on Setting::get().
// Keeps SQLite integration tests lightweight while allowing Cache facade usage.

use Illuminate\Container\Container;
use Illuminate\Cache\Repository;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Cache;

$app = new Container();
Facade::setFacadeApplication($app);

$app->singleton('cache', function () {
    return new Repository(new ArrayStore());
});

Cache::swap($app->make('cache'));

