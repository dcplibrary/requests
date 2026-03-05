<?php

namespace Dcplibrary\Sfp;

use Dcplibrary\Sfp\Console\Commands\SfpBackupCommand;
use Dcplibrary\Sfp\Http\Middleware\RequireSfpRole;
use Dcplibrary\Sfp\Livewire\PatronLookup;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel service provider for the dcplibrary/sfp package.
 *
 * Registers routes, views (including anonymous Blade component namespace),
 * Livewire components, config merging, migrations, and publishable assets.
 *
 * Publish tags:
 *  - `sfp-config`     — config/sfp.php
 *  - `sfp-migrations` — database migrations
 *  - `sfp-seeders`    — database seeders
 *  - `sfp-views`      — Blade views
 *  - `sfp-assets`     — compiled CSS → public/vendor/sfp/
 *  - `sfp`            — all of the above at once
 */
class SfpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sfp.php',
            'sfp'
        );
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerLivewire();
        $this->registerPublishables();

        // Always load migrations so `php artisan migrate` discovers them
        // without needing to publish them first.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SfpBackupCommand::class,
            ]);
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('sfp.role', RequireSfpRole::class);
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sfp');

        Blade::anonymousComponentNamespace('sfp::components', 'sfp');
    }

    protected function registerLivewire(): void
    {
        Livewire::component('sfp-form', SfpForm::class);
        Livewire::component('sfp-patron-lookup', PatronLookup::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sfp.php' => config_path('sfp.php'),
        ], 'sfp-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sfp-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'sfp-seeders');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/sfp'),
        ], 'sfp-views');

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/sfp'),
        ], 'sfp-assets');

        // Convenience tag: publish everything at once
        $this->publishes([
            __DIR__ . '/../config/sfp.php'      => config_path('sfp.php'),
            __DIR__ . '/../database/migrations'  => database_path('migrations'),
            __DIR__ . '/../database/seeders'     => database_path('seeders'),
            __DIR__ . '/../resources/views'      => resource_path('views/vendor/sfp'),
            __DIR__ . '/../public'               => public_path('vendor/sfp'),
        ], 'sfp');
    }
}
