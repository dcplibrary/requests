<?php

namespace Dcplibrary\Requests;

use Dcplibrary\Requests\Console\Commands\SeedDefaultsCommand;
use Dcplibrary\Requests\Console\Commands\BackupCommand;
use Dcplibrary\Requests\Console\Commands\UsersBackupCommand;
use Dcplibrary\Requests\Console\Commands\UsersRestoreCommand;
use Dcplibrary\Requests\Http\Middleware\RequireStaffRole;
use Dcplibrary\Requests\Livewire\Admin\FormFieldEdit;
use Dcplibrary\Requests\Livewire\Admin\FormFields as FormFieldsAdmin;
use Dcplibrary\Requests\Livewire\Admin\FormFormFieldEdit;
use Dcplibrary\Requests\Livewire\Admin\FormFormFieldOptionsManager;
use Dcplibrary\Requests\Livewire\Admin\FormFormFieldOptionEdit;
use Dcplibrary\Requests\Livewire\Admin\FormCustomFieldEdit;
use Dcplibrary\Requests\Livewire\Admin\FormCustomFieldOptionsManager;
use Dcplibrary\Requests\Livewire\Admin\FormCustomFieldOptionEdit;
use Dcplibrary\Requests\Livewire\Admin\CustomFields as CustomFieldsAdmin;
use Dcplibrary\Requests\Livewire\Admin\CustomFieldEdit as CustomFieldEditAdmin;
use Dcplibrary\Requests\Livewire\Admin\CustomFieldOptionsManager;
use Dcplibrary\Requests\Livewire\Admin\OptionsManager;
use Dcplibrary\Requests\Livewire\PatronPinLogin;
use Dcplibrary\Requests\Livewire\PatronRequests;
use Dcplibrary\Requests\Livewire\IllForm;
use Dcplibrary\Requests\Livewire\RequestForm;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel service provider for the dcplibrary/requests package.
 *
 * Registers routes, views (including anonymous Blade component namespace),
 * Livewire components, config merging, migrations, and publishable assets.
 *
 * Publish tags:
 *  - `requests-config`     — config/requests.php
 *  - `requests-migrations` — database migrations
 *  - `requests-seeders`    — database seeders
 *  - `requests-views`      — Blade views (optional override)
 *  - `requests`            — all of the above at once
 *
 * CSS is served via the /{prefix}/assets/css route directly from resources/dist/requests.css
 * inside the package — no vendor:publish required for assets.
 */
class RequestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/requests.php',
            'requests'
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

        // Register commands outside runningInConsole() so Artisan::call()
        // from web requests (e.g. backup UI) can find them.
        $this->commands([
            SeedDefaultsCommand::class,
            BackupCommand::class,
            UsersBackupCommand::class,
            UsersRestoreCommand::class,
        ]);
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('request.role', RequireStaffRole::class);
    }

    protected function registerRoutes(): void
    {
        // Asset route — serves compiled CSS directly from inside the package.
        // This is the Horizon/Telescope pattern: no vendor:publish needed for CSS.
        $routePrefix = config('requests.route_prefix', 'request');
        $assetPath = trim($routePrefix . '/assets/css', '/');
        Route::get($assetPath, function () {
            $path = __DIR__ . '/../resources/dist/requests.css';

            return response(file_get_contents($path), 200)
                ->header('Content-Type', 'text/css')
                ->header('Cache-Control', 'public, max-age=31536000');
        })->name('request.assets.css');

        // Logo — served via URL so email clients (e.g. Gmail) don't strip it as a data: URI.
        $logoPath = trim($routePrefix . '/assets/logo', '/');
        Route::get($logoPath, function () {
            $path = __DIR__ . '/../resources/images/dcpl-logo.png';
            return response(file_get_contents($path), 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=31536000');
        })->name('request.assets.logo');

        // Help pages — served directly from resources/dist, no vendor:publish needed.
        Route::get('requests-settings-help.html', function () {
            return response(file_get_contents(__DIR__ . '/../resources/dist/requests-settings-help.html'), 200)
                ->header('Content-Type', 'text/html');
        })->name('request.assets.settings-help');

        Route::get('requests-selector-help.html', function () {
            return response(file_get_contents(__DIR__ . '/../resources/dist/requests-selector-help.html'), 200)
                ->header('Content-Type', 'text/html');
        })->name('request.assets.selector-help');

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'requests');

        Blade::anonymousComponentNamespace('requests::components', 'requests');
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
        Livewire::component('requests-form', RequestForm::class);
        Livewire::component('ill-form', IllForm::class);
        Livewire::component('requests-patron-pin-login', PatronPinLogin::class);
        Livewire::component('requests-patron-requests', PatronRequests::class);
        Livewire::component('requests-admin-form-fields', FormFieldsAdmin::class);
        Livewire::component('requests-admin-form-field-edit', FormFieldEdit::class);
        Livewire::component('requests-admin-form-form-field-edit', FormFormFieldEdit::class);
        Livewire::component('requests-admin-form-form-field-options', FormFormFieldOptionsManager::class);
        Livewire::component('requests-admin-form-form-field-option-edit', FormFormFieldOptionEdit::class);
        Livewire::component('requests-admin-form-custom-field-edit', FormCustomFieldEdit::class);
        Livewire::component('requests-admin-form-custom-field-options', FormCustomFieldOptionsManager::class);
        Livewire::component('requests-admin-form-custom-field-option-edit', FormCustomFieldOptionEdit::class);
        Livewire::component('requests-admin-options-manager', OptionsManager::class);
        Livewire::component('requests-admin-custom-fields', CustomFieldsAdmin::class);
        Livewire::component('requests-admin-custom-field-edit', CustomFieldEditAdmin::class);
        Livewire::component('requests-admin-custom-field-options', CustomFieldOptionsManager::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/requests.php' => config_path('requests.php'),
        ], 'requests-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'requests-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'requests-seeders');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/requests'),
        ], 'requests-views');

        // Convenience tag: publish everything at once
        $this->publishes([
            __DIR__ . '/../config/requests.php'  => config_path('requests.php'),
            __DIR__ . '/../database/migrations'  => database_path('migrations'),
            __DIR__ . '/../database/seeders'     => database_path('seeders'),
            __DIR__ . '/../resources/views'      => resource_path('views/vendor/requests'),
        ], 'requests');
    }
}
