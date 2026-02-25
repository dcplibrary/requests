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
                        'index'   => 'sfp.staff.material-types.index',
                        'create'  => 'sfp.staff.material-types.create',
                        'store'   => 'sfp.staff.material-types.store',
                        'edit'    => 'sfp.staff.material-types.edit',
                        'update'  => 'sfp.staff.material-types.update',
                        'destroy' => 'sfp.staff.material-types.destroy',
                    ])
                    ->except(['show']);

                Route::resource('audiences', AudienceController::class)
                    ->names([
                        'index'   => 'sfp.staff.audiences.index',
                        'create'  => 'sfp.staff.audiences.create',
                        'store'   => 'sfp.staff.audiences.store',
                        'edit'    => 'sfp.staff.audiences.edit',
                        'update'  => 'sfp.staff.audiences.update',
                        'destroy' => 'sfp.staff.audiences.destroy',
                    ])
                    ->except(['show']);

                Route::resource('statuses', RequestStatusController::class)
                    ->names([
                        'index'   => 'sfp.staff.statuses.index',
                        'create'  => 'sfp.staff.statuses.create',
                        'store'   => 'sfp.staff.statuses.store',
                        'edit'    => 'sfp.staff.statuses.edit',
                        'update'  => 'sfp.staff.statuses.update',
                        'destroy' => 'sfp.staff.statuses.destroy',
                    ])
                    ->except(['show']);

                Route::resource('users', UserController::class)
                    ->names([
                        'index'   => 'sfp.staff.users.index',
                        'edit'    => 'sfp.staff.users.edit',
                        'update'  => 'sfp.staff.users.update',
                        'destroy' => 'sfp.staff.users.destroy',
                    ])
                    ->except(['show', 'create', 'store']);

                Route::resource('groups', SelectorGroupController::class)
                    ->names([
                        'index'   => 'sfp.staff.groups.index',
                        'create'  => 'sfp.staff.groups.create',
                        'store'   => 'sfp.staff.groups.store',
                        'edit'    => 'sfp.staff.groups.edit',
                        'update'  => 'sfp.staff.groups.update',
                        'destroy' => 'sfp.staff.groups.destroy',
                    ])
                    ->except(['show']);
            });
        });
});
