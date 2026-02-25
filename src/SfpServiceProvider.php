<?php

namespace Dcplibrary\Sfp;

use Dcplibrary\Sfp\Http\Middleware\RoleMiddleware;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        $this->registerAuthGuard();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerLivewire();
        $this->registerPublishables();

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerAuthGuard(): void
    {
        config([
            'auth.guards.sfp' => [
                'driver'   => 'session',
                'provider' => 'sfp_users',
            ],
            'auth.providers.sfp_users' => [
                'driver' => 'eloquent',
                'model'  => User::class,
            ],
        ]);
    }

    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('sfp.role', RoleMiddleware::class);
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sfp');
    }

    protected function registerLivewire(): void
    {
        Livewire::component('sfp-form', SfpForm::class);
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

        // Convenience tag: publish everything at once
        $this->publishes([
            __DIR__ . '/../config/sfp.php'     => config_path('sfp.php'),
            __DIR__ . '/../database/migrations' => database_path('migrations'),
            __DIR__ . '/../database/seeders'    => database_path('seeders'),
            __DIR__ . '/../resources/views'     => resource_path('views/vendor/sfp'),
        ], 'sfp');
    }
}
