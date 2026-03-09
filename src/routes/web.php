<?php

use Dcplibrary\Sfp\Http\Controllers\Admin\AudienceController;
use Dcplibrary\Sfp\Http\Controllers\Admin\BackupController;
use Dcplibrary\Sfp\Http\Controllers\Admin\CatalogController;
use Dcplibrary\Sfp\Http\Controllers\Admin\HelpController;
use Dcplibrary\Sfp\Http\Controllers\Admin\TitleController;
use Dcplibrary\Sfp\Http\Controllers\Admin\MaterialTypeController;
use Dcplibrary\Sfp\Http\Controllers\Admin\PatronController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestController;
use Dcplibrary\Sfp\Http\Controllers\Admin\RequestStatusController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SelectorGroupController;
use Dcplibrary\Sfp\Http\Controllers\Admin\FormFieldController;
use Dcplibrary\Sfp\Http\Controllers\Admin\CustomFieldController;
use Dcplibrary\Sfp\Http\Controllers\Admin\SettingController;
use Dcplibrary\Sfp\Http\Controllers\Admin\UserController;
use Dcplibrary\Sfp\Livewire\PatronRequests;
use Dcplibrary\Sfp\Livewire\SfpForm;
use Dcplibrary\Sfp\Livewire\IllForm;
use Illuminate\Support\Facades\Route;

$prefix          = config('sfp.route_prefix', 'request');
$middleware      = config('sfp.middleware', ['web']);
$staffMiddleware = array_merge(
    config('sfp.staff_middleware', ['web', 'auth']),
    ['request.role']
);

// --- Public: ILL Patron Form (at site root /ill) ---
Route::get('ill', IllForm::class)
    ->middleware($middleware)
    ->name('request.ill.form');

Route::group([
    'prefix'     => $prefix,
    'middleware' => $middleware,
], function () use ($staffMiddleware) {

    // --- Public: SFP Patron Form ---
    Route::get('/', SfpForm::class)->name('request.form');

    // --- Public: My Requests (Polaris PIN authentication) ---
    Route::get('/my-requests', PatronRequests::class)->name('request.patron.requests');

    // --- Staff: Protected ---
    Route::prefix('staff')
        ->name('request.staff.')
        ->middleware($staffMiddleware)
        ->group(function () {

            Route::get('/', fn () => redirect()->route('request.staff.requests.index'));

            Route::get('/help/{page?}', [HelpController::class, 'show'])->name('help');

            Route::get('/requests',                              [RequestController::class, 'index'])->name('requests.index');
            Route::get('/requests/{sfpRequest}',               [RequestController::class, 'show'])->name('requests.show');
            Route::patch('/requests/{sfpRequest}/status',      [RequestController::class, 'updateStatus'])->name('requests.status');
            Route::post('/requests/{sfpRequest}/catalog-recheck', [RequestController::class, 'recheckCatalog'])->name('requests.catalog-recheck');
            Route::post('/requests/{sfpRequest}/convert-kind',  [RequestController::class, 'convertKind'])->name('requests.convert-kind');
            Route::post('/requests/{sfpRequest}/claim',         [RequestController::class, 'claim'])->name('requests.claim');
            Route::post('/requests/{sfpRequest}/assign',        [RequestController::class, 'assign'])->name('requests.assign');
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

            Route::get('/settings',                  [SettingController::class, 'index'])->name('settings.index');
            Route::get('/settings/notifications',                      [SettingController::class, 'notifications'])->name('settings.notifications');
            Route::get('/settings/notifications/preview/{type}',    [SettingController::class, 'previewEmail'])->name('settings.notifications.preview');
            Route::post('/settings/notifications/test',             [SettingController::class, 'sendTestEmail'])->name('settings.notifications.test');
            Route::get('/settings/form-fields',              [FormFieldController::class, 'index'])->name('settings.form-fields');
            Route::get('/settings/form-fields/{field}/edit', [FormFieldController::class, 'edit'])->name('settings.form-fields.edit');
            Route::get('/settings/custom-fields',              [CustomFieldController::class, 'index'])->name('settings.custom-fields');
            Route::get('/settings/custom-fields/{field}/edit', [CustomFieldController::class, 'edit'])->name('settings.custom-fields.edit');
            Route::patch('/settings',                [SettingController::class, 'update'])->name('settings.update');

            Route::get('/backups',                  [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups/config-export',   [BackupController::class, 'exportConfig'])->name('backups.config-export');
            Route::post('/backups/config-import',   [BackupController::class, 'importConfig'])->name('backups.config-import');
            Route::post('/backups/db-export',       [BackupController::class, 'exportDatabase'])->name('backups.db-export');
            Route::post('/backups/db-import',       [BackupController::class, 'importDatabase'])->name('backups.db-import');
            Route::post('/backups/storage-export',    [BackupController::class, 'exportStorage'])->name('backups.storage-export');
            Route::post('/backups/server-save',     [BackupController::class, 'saveToServer'])->name('backups.server-save');
            Route::post('/backups/server-restore',  [BackupController::class, 'restoreFromServer'])->name('backups.server-restore');
            Route::get('/backups/server-download',  [BackupController::class, 'downloadFromServer'])->name('backups.server-download');
            Route::post('/backups/retention',       [BackupController::class, 'updateRetention'])->name('backups.retention');
            Route::post('/backups/prune',           [BackupController::class, 'pruneBackups'])->name('backups.prune');
            Route::post('/backups/wipe',            [BackupController::class, 'wipeAll'])->name('backups.wipe');

            Route::get('/catalog',    [CatalogController::class, 'index'])->name('catalog.index');
            Route::patch('/catalog',  [CatalogController::class, 'update'])->name('catalog.update');
            Route::post('/catalog/format-labels',                          [CatalogController::class, 'storeFormatLabel'])->name('catalog.format-labels.store');
            Route::delete('/catalog/format-labels/{catalogFormatLabel}',   [CatalogController::class, 'destroyFormatLabel'])->name('catalog.format-labels.destroy');

            Route::get('material-types/{materialType}/delete', [MaterialTypeController::class, 'confirmDelete'])
                ->name('material-types.delete');
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

            Route::get('audiences/{audience}/delete', [AudienceController::class, 'confirmDelete'])
                ->name('audiences.delete');
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

            Route::get('statuses/{status}/delete', [RequestStatusController::class, 'confirmDelete'])
                ->name('statuses.delete');
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

            Route::get('users/{user}/remove', [UserController::class, 'confirmDelete'])
                ->name('users.remove');
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
