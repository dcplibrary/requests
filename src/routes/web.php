<?php

use Dcplibrary\Sfp\Http\Controllers\Admin\AudienceController;
use Dcplibrary\Sfp\Http\Controllers\Admin\CatalogController;
use Dcplibrary\Sfp\Http\Controllers\Admin\TitleController;
use Dcplibrary\Sfp\Http\Controllers\Admin\MaterialTypeController;
use Dcplibrary\Sfp\Http\Controllers\Admin\PatronController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestStatusController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SelectorGroupController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SettingController;
use Dcplibrary\Sfp\Http\Controllers\Admin\UserController;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Illuminate\Support\Facades\Route;

$prefix          = config('sfp.route_prefix', 'sfp');
$middleware      = config('sfp.middleware', ['web']);
$staffMiddleware = array_merge(
    config('sfp.staff_middleware', ['web', 'auth']),
    ['sfp.role']
);

Route::group([
    'prefix'     => $prefix,
    'middleware' => $middleware,
], function () use ($staffMiddleware) {

    // --- Public: SFP Patron Form ---
    Route::get('/', SfpForm::class)->name('sfp.form');

    // --- Staff: Protected ---
    Route::prefix('staff')
        ->name('sfp.staff.')
        ->middleware($staffMiddleware)
        ->group(function () {

            Route::get('/', fn () => redirect()->route('sfp.staff.requests.index'));

            Route::get('/requests',                              [RequestController::class, 'index'])->name('requests.index');
            Route::get('/requests/{sfpRequest}',               [RequestController::class, 'show'])->name('requests.show');
            Route::patch('/requests/{sfpRequest}/status',      [RequestController::class, 'updateStatus'])->name('requests.status');
            Route::post('/requests/{sfpRequest}/catalog-recheck', [RequestController::class, 'recheckCatalog'])->name('requests.catalog-recheck');
            Route::delete('/requests/{sfpRequest}',            [RequestController::class, 'destroy'])->name('requests.destroy');

            Route::resource('patrons', PatronController::class)
                ->names([
                    'index'  => 'patrons.index',
                    'show'   => 'patrons.show',
                    'edit'   => 'patrons.edit',
                    'update' => 'patrons.update',
                ])
                ->except(['create', 'store', 'destroy']);

            Route::get('patrons/{patron}/merge-confirm', [PatronController::class, 'mergeConfirm'])
                ->name('patrons.merge-confirm');

            Route::post('patrons/{patron}/merge', [PatronController::class, 'merge'])
                ->name('patrons.merge');

            Route::post('patrons/{patron}/retrigger-polaris', [PatronController::class, 'retriggerPolaris'])
                ->name('patrons.retrigger-polaris');

            Route::post('patrons/{patron}/ignore-duplicate', [PatronController::class, 'ignoreDuplicate'])
                ->name('patrons.ignore-duplicate');

            Route::get('titles', [TitleController::class, 'index'])
                ->name('titles.index');

            Route::get('titles/{material}', [TitleController::class, 'show'])
                ->name('titles.show');

            Route::post('titles/{material}/merge', [TitleController::class, 'merge'])
                ->name('titles.merge');

            Route::post('titles/{material}/bulk-status', [TitleController::class, 'bulkStatus'])
                ->name('titles.bulk-status');

            Route::get('/settings',   [SettingController::class, 'index'])->name('settings.index');
            Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');

            Route::get('/catalog',    [CatalogController::class, 'index'])->name('catalog.index');
            Route::patch('/catalog',  [CatalogController::class, 'update'])->name('catalog.update');
            Route::post('/catalog/format-labels',                          [CatalogController::class, 'storeFormatLabel'])->name('catalog.format-labels.store');
            Route::delete('/catalog/format-labels/{catalogFormatLabel}',   [CatalogController::class, 'destroyFormatLabel'])->name('catalog.format-labels.destroy');

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
