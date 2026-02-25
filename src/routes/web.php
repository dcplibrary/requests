<?php

use Dcplibrary\Sfp\Http\Controllers\Admin\AudienceController;
use Dcplibrary\Sfp\Http\Controllers\Admin\MaterialTypeController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestStatusController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SelectorGroupController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SettingController;
use Dcplibrary\Sfp\Http\Controllers\Admin\UserController;
use Dcplibrary\Sfp\Http\Controllers\Auth\EntraAuthController;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Illuminate\Support\Facades\Route;

$prefix     = config('sfp.route_prefix', 'sfp');
$middleware = config('sfp.middleware', ['web']);
$guard      = config('sfp.guard', 'sfp');

Route::group([
    'prefix'     => $prefix,
    'middleware' => $middleware,
], function () use ($guard) {

    // --- Public: SFP Patron Form ---
    Route::get('/', SfpForm::class)->name('sfp.form');

    // --- Auth ---
    Route::get('/login',         [EntraAuthController::class, 'redirect'])->name('sfp.login');
    Route::get('/auth/callback', [EntraAuthController::class, 'callback'])->name('sfp.auth.callback');
    Route::post('/logout',       [EntraAuthController::class, 'logout'])->name('sfp.logout');

    // --- Staff: Protected ---
    Route::prefix('staff')
        ->name('sfp.staff.')
        ->middleware(["auth:{$guard}"])
        ->group(function () {

            Route::get('/', fn () => redirect()->route('sfp.staff.requests.index'));

            Route::get('/requests',                     [RequestController::class, 'index'])->name('requests.index');
            Route::get('/requests/{request}',           [RequestController::class, 'show'])->name('requests.show');
            Route::patch('/requests/{request}/status',  [RequestController::class, 'updateStatus'])->name('requests.status');

            // Admin-only
            Route::middleware('sfp.role:admin')->group(function () {

                Route::get('/settings',   [SettingController::class, 'index'])->name('settings.index');
                Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');

                Route::resource('material-types', MaterialTypeController::class)
                    ->names([
                        'index'   => 'material-types.index',
                        'create'  => 'material-types.create',
                        'store'   => 'material-types.store',
                        'edit'    => 'material-types.edit',
                        'update'  => 'material-types.update',
                        'destroy' => 'material-types.destroy',
                    ])
                    ->except(['show']);

                Route::resource('audiences', AudienceController::class)
                    ->names([
                        'index'   => 'audiences.index',
                        'create'  => 'audiences.create',
                        'store'   => 'audiences.store',
                        'edit'    => 'audiences.edit',
                        'update'  => 'audiences.update',
                        'destroy' => 'audiences.destroy',
                    ])
                    ->except(['show']);

                Route::resource('statuses', RequestStatusController::class)
                    ->names([
                        'index'   => 'statuses.index',
                        'create'  => 'statuses.create',
                        'store'   => 'statuses.store',
                        'edit'    => 'statuses.edit',
                        'update'  => 'statuses.update',
                        'destroy' => 'statuses.destroy',
                    ])
                    ->except(['show']);

                Route::resource('users', UserController::class)
                    ->names([
                        'index'   => 'users.index',
                        'edit'    => 'users.edit',
                        'update'  => 'users.update',
                        'destroy' => 'users.destroy',
                    ])
                    ->except(['show', 'create', 'store']);

                Route::resource('groups', SelectorGroupController::class)
                    ->names([
                        'index'   => 'groups.index',
                        'create'  => 'groups.create',
                        'store'   => 'groups.store',
                        'edit'    => 'groups.edit',
                        'update'  => 'groups.update',
                        'destroy' => 'groups.destroy',
                    ])
                    ->except(['show']);
            });
        });
});
