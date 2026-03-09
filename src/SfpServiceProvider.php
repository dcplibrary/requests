<?php

namespace Dcplibrary\Sfp;

use Dcplibrary\Sfp\Console\Commands\SfpBackupCommand;
use Dcplibrary\Sfp\Http\Middleware\RequireSfpRole;
use Dcplibrary\Sfp\Livewire\Admin\FormFieldEdit;
use Dcplibrary\Sfp\Livewire\Admin\FormFields as FormFieldsAdmin;
use Dcplibrary\Sfp\Livewire\Admin\FormFormFieldEdit;
use Dcplibrary\Sfp\Livewire\Admin\CustomFields as CustomFieldsAdmin;
use Dcplibrary\Sfp\Livewire\Admin\CustomFieldEdit as CustomFieldEditAdmin;
use Dcplibrary\Sfp\Livewire\Admin\CustomFieldOptionsManager;
use Dcplibrary\Sfp\Livewire\Admin\OptionsManager;
use Dcplibrary\Sfp\Livewire\PatronPinLogin;
use Dcplibrary\Sfp\Livewire\PatronRequests;
use Dcplibrary\Sfp\Livewire\IllForm;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
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
 *  - `sfp-views`      — Blade views (optional override)
 *  - `sfp`            — all of the above at once
 *
 * CSS is served via the /sfp/assets/css route directly from resources/dist/sfp.css
 * inside the package — no vendor:publish required for assets.
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
        $this->registerBlaze();
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
        $router->aliasMiddleware('request.role', RequireSfpRole::class);
    }

    protected function registerRoutes(): void
    {
        // Asset route — serves compiled CSS directly from inside the package.
        // This is the Horizon/Telescope pattern: no vendor:publish needed for CSS.
        $routePrefix = config('sfp.route_prefix', 'request');
        $assetPath = trim($routePrefix . '/assets/css', '/');
        Route::get($assetPath, function () {
            $path = __DIR__ . '/../resources/dist/sfp.css';

            return response(file_get_contents($path), 200)
                ->header('Content-Type', 'text/css')
                ->header('Cache-Control', 'public, max-age=31536000');
        })->name('request.assets.css');

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sfp');

        Blade::anonymousComponentNamespace('sfp::components', 'sfp');
    }

    protected function registerBlaze(): void
    {
        // Blaze is an optional host-app optimization. We only enable it if the
        // consuming application has installed livewire/blaze.
        if (!class_exists(\Livewire\Blaze\Blaze::class)) {
            return;
        }

        \Livewire\Blaze\Blaze::optimize()
            ->in(__DIR__ . '/../resources/views/components');
    }

    protected function registerLivewire(): void
    {
        Livewire::component('sfp-form', SfpForm::class);
        Livewire::component('ill-form', IllForm::class);
        Livewire::component('sfp-patron-pin-login', PatronPinLogin::class);
        Livewire::component('sfp-patron-requests', PatronRequests::class);
        Livewire::component('sfp-admin-form-fields', FormFieldsAdmin::class);
        Livewire::component('sfp-admin-form-field-edit', FormFieldEdit::class);
        Livewire::component('sfp-admin-form-form-field-edit', FormFormFieldEdit::class);
        Livewire::component('sfp-admin-options-manager', OptionsManager::class);
        Livewire::component('sfp-admin-custom-fields', CustomFieldsAdmin::class);
        Livewire::component('sfp-admin-custom-field-edit', CustomFieldEditAdmin::class);
        Livewire::component('sfp-admin-custom-field-options', CustomFieldOptionsManager::class);
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
            __DIR__ . '/../config/sfp.php'      => config_path('sfp.php'),
            __DIR__ . '/../database/migrations'  => database_path('migrations'),
            __DIR__ . '/../database/seeders'     => database_path('seeders'),
            __DIR__ . '/../resources/views'      => resource_path('views/vendor/sfp'),
        ], 'sfp');
    }
}
