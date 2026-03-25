<?php

use Dcplibrary\Requests\Http\Controllers\Admin\BackupController;
use Dcplibrary\Requests\Http\Controllers\Admin\CatalogController;
use Dcplibrary\Requests\Http\Controllers\Admin\HelpController;
use Dcplibrary\Requests\Http\Controllers\Admin\TitleController;
use Dcplibrary\Requests\Http\Controllers\Admin\PatronController;
use Dcplibrary\Requests\Http\Controllers\Admin\PatronStatusTemplateController;
use Dcplibrary\Requests\Http\Controllers\Admin\StaffRoutingTemplateController;
use Dcplibrary\Requests\Http\Controllers\Admin\RequestController;
use Dcplibrary\Requests\Http\Controllers\Admin\RequestStatusController;
use Dcplibrary\Requests\Http\Controllers\Admin\SelectorGroupController;
use Dcplibrary\Requests\Http\Controllers\Admin\FormFieldController;
use Dcplibrary\Requests\Http\Controllers\Admin\CustomFieldController;
use Dcplibrary\Requests\Http\Controllers\Admin\SettingController;
use Dcplibrary\Requests\Http\Controllers\Admin\UserController;
use Dcplibrary\Requests\Livewire\PatronRequests;
use Dcplibrary\Requests\Livewire\RequestForm;
use Dcplibrary\Requests\Livewire\IllForm;
use Illuminate\Support\Facades\Route;

$prefix          = config('requests.route_prefix', 'request');
$middleware      = config('requests.middleware', ['web']);
$staffMiddleware = array_merge(
    config('requests.staff_middleware', ['web', 'auth']),
    ['request.role']
);

// --- Public: Signed email action (no auth required — URL is signed + expiring) ---
Route::get(
    $prefix . '/email-action/{patronRequest}',
    [RequestController::class, 'emailAction']
)->name('request.email-action')->middleware($middleware);

Route::get(
    $prefix . '/email-action/convert-to-ill/{patronRequest}',
    [RequestController::class, 'convertToIllFromSignedEmail']
)->name('request.email-convert-to-ill')->middleware($middleware);

Route::group([
    'prefix'     => $prefix,
    'middleware' => $middleware,
], function () use ($staffMiddleware) {

    // --- Public: Patron Request Forms ---
    Route::get('/sfp', RequestForm::class)->name('request.form');
    Route::get('/ill', IllForm::class)->name('request.ill.form');

    // --- Public: My Requests (Polaris PIN authentication) ---
    Route::get('/my-requests', PatronRequests::class)->name('request.patron.requests');

    // --- Login redirect (keeps everything under /{prefix}) ---
    Route::get('/login', fn () => redirect()->route('login'))->name('request.login');

    // --- Staff: Protected ---
    Route::prefix('staff')
        ->name('request.staff.')
        ->middleware($staffMiddleware)
        ->group(function () {

            Route::get('/', fn () => redirect()->route('request.staff.requests.index'));

            Route::get('/help/{page?}', [HelpController::class, 'show'])->name('help');

            Route::get('/requests',                                       [RequestController::class, 'index'])->name('requests.index');
            Route::post('/requests/bulk-reassign',               [RequestController::class, 'bulkReassign'])->name('requests.bulk-reassign');
            Route::post('/requests/bulk-status',                 [RequestController::class, 'bulkStatus'])->name('requests.bulk-status');
            Route::delete('/requests/bulk-delete',               [RequestController::class, 'bulkDelete'])->name('requests.bulk-delete');
            Route::get('/requests/{patronRequest}',                        [RequestController::class, 'show'])->name('requests.show');
            Route::get('/requests/{patronRequest}/preview-email',          [RequestController::class, 'previewStatusEmail'])->name('requests.preview-email');
            Route::patch('/requests/{patronRequest}/status',               [RequestController::class, 'updateStatus'])->name('requests.status');
            Route::post('/requests/{patronRequest}/catalog-recheck', [RequestController::class, 'recheckCatalog'])->name('requests.catalog-recheck');
            Route::post('/requests/{patronRequest}/convert-kind',  [RequestController::class, 'convertKind'])->name('requests.convert-kind');
            Route::post('/requests/{patronRequest}/claim',         [RequestController::class, 'claim'])->name('requests.claim');
            Route::post('/requests/{patronRequest}/assign',        [RequestController::class, 'assign'])->name('requests.assign');
            Route::post('/requests/{patronRequest}/reroute',       [RequestController::class, 'reroute'])->name('requests.reroute');
            Route::get('/requests/{patronRequest}/reroute-preview', [RequestController::class, 'reroutePreview'])->name('requests.reroute-preview');
            Route::delete('/requests/{patronRequest}',            [RequestController::class, 'destroy'])->name('requests.destroy');

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
            Route::get('/settings/notifications/staff-email',           [SettingController::class, 'staffEmailForm'])->name('settings.notifications.staff-email');
            Route::get('/settings/notifications/default-patron-email', [SettingController::class, 'defaultPatronEmailForm'])->name('settings.notifications.default-patron-email');
            Route::get('/settings/notifications/preview/{type}',    [SettingController::class, 'previewEmail'])->name('settings.notifications.preview');
            Route::post('/settings/notifications/test',             [SettingController::class, 'sendTestEmail'])->name('settings.notifications.test');
            Route::get('/settings/form-fields',              [FormFieldController::class, 'index'])->name('settings.form-fields');
            Route::get('/settings/form-fields/create',        [FormFieldController::class, 'create'])->name('settings.form-fields.create');
            Route::post('/settings/form-fields',              [FormFieldController::class, 'store'])->name('settings.form-fields.store');
            Route::get('/settings/form-fields/{field}/form/{form}/edit', [FormFieldController::class, 'editForForm'])->name('settings.form-fields.edit-for-form')->where('form', 'sfp|ill');
            Route::get('/settings/form-fields/{field}/form/{form}/options/{slug}/edit', [FormFieldController::class, 'editForFormOption'])->name('settings.form-fields.edit-for-form-option')->where('form', 'sfp|ill');
            Route::get('/settings/form-fields/{field}/edit', [FormFieldController::class, 'edit'])->name('settings.form-fields.edit');
            Route::get('/settings/custom-fields',              [CustomFieldController::class, 'index'])->name('settings.custom-fields');
            Route::get('/settings/custom-fields/{field}/edit', [CustomFieldController::class, 'edit'])->name('settings.custom-fields.edit');
            Route::get('/settings/custom-fields/{field}/form/{form}/edit', [CustomFieldController::class, 'editForForm'])->name('settings.custom-fields.edit-for-form')->where('form', 'sfp|ill');
            Route::get('/settings/custom-fields/{field}/form/{form}/options/{optionId}/edit', [CustomFieldController::class, 'editForFormOption'])->name('settings.custom-fields.edit-for-form-option')->where(['form' => 'sfp|ill', 'optionId' => '[0-9]+']);
            Route::patch('/settings',                [SettingController::class, 'update'])->name('settings.update');

            Route::get('/backups',                  [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups/config-export',   [BackupController::class, 'exportConfig'])->name('backups.config-export');
            Route::post('/backups/config-import',   [BackupController::class, 'importConfig'])->name('backups.config-import');
            Route::post('/backups/db-export',       [BackupController::class, 'exportDatabase'])->name('backups.db-export');
            Route::post('/backups/db-export-json',  [BackupController::class, 'exportDatabaseJson'])->name('backups.db-export-json');
            Route::post('/backups/db-import',       [BackupController::class, 'importDatabase'])->name('backups.db-import');
            Route::post('/backups/db-import-json',  [BackupController::class, 'importDatabaseFromJson'])->name('backups.db-import-json');
            Route::post('/backups/storage-export',    [BackupController::class, 'exportStorage'])->name('backups.storage-export');
            Route::post('/backups/server-save',     [BackupController::class, 'saveToServer'])->name('backups.server-save');
            Route::post('/backups/server-restore',  [BackupController::class, 'restoreFromServer'])->name('backups.server-restore');
            Route::delete('/backups/server-delete', [BackupController::class, 'deleteFromServer'])->name('backups.server-delete');
            Route::get('/backups/server-download',  [BackupController::class, 'downloadFromServer'])->name('backups.server-download');
            Route::post('/backups/retention',       [BackupController::class, 'updateRetention'])->name('backups.retention');
            Route::post('/backups/prune',           [BackupController::class, 'pruneBackups'])->name('backups.prune');
            Route::post('/backups/wipe',            [BackupController::class, 'wipeAll'])->name('backups.wipe');

            Route::get('/catalog',    [CatalogController::class, 'index'])->name('catalog.index');
            Route::patch('/catalog',  [CatalogController::class, 'update'])->name('catalog.update');
            Route::post('/catalog/format-labels',                          [CatalogController::class, 'storeFormatLabel'])->name('catalog.format-labels.store');
            Route::delete('/catalog/format-labels/{catalogFormatLabel}',   [CatalogController::class, 'destroyFormatLabel'])->name('catalog.format-labels.destroy');

            Route::get('patron-status-templates/{patron_status_template}/delete', [PatronStatusTemplateController::class, 'confirmDelete'])
                ->name('patron-status-templates.delete');
            Route::get('patron-status-templates/{patron_status_template}/preview', [PatronStatusTemplateController::class, 'preview'])
                ->name('patron-status-templates.preview');
            Route::post('patron-status-templates/{patron_status_template}/test', [PatronStatusTemplateController::class, 'sendTest'])
                ->name('patron-status-templates.test');
            Route::resource('patron-status-templates', PatronStatusTemplateController::class)
                ->names([
                    'index'   => 'patron-status-templates.index',
                    'create'  => 'patron-status-templates.create',
                    'store'   => 'patron-status-templates.store',
                    'edit'    => 'patron-status-templates.edit',
                    'update'  => 'patron-status-templates.update',
                    'destroy' => 'patron-status-templates.destroy',
                ])
                ->except(['show']);

            Route::get('staff-routing-templates/{staff_routing_template}/delete', [StaffRoutingTemplateController::class, 'confirmDelete'])
                ->name('staff-routing-templates.delete');
            Route::resource('staff-routing-templates', StaffRoutingTemplateController::class)
                ->names([
                    'create'  => 'staff-routing-templates.create',
                    'store'   => 'staff-routing-templates.store',
                    'edit'    => 'staff-routing-templates.edit',
                    'update'  => 'staff-routing-templates.update',
                    'destroy' => 'staff-routing-templates.destroy',
                ])
                ->only(['create', 'store', 'edit', 'update', 'destroy']);
            Route::get('staff-routing-templates/{staffRoutingTemplate}/preview', [StaffRoutingTemplateController::class, 'preview'])
                ->name('staff-routing-templates.preview');
            Route::post('staff-routing-templates/{staffRoutingTemplate}/test', [StaffRoutingTemplateController::class, 'sendTest'])
                ->name('staff-routing-templates.test');

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

            // --- TEMPORARY DEV: screenshot capture endpoint (remove after help doc update) ---
            Route::post('/dev/save-screenshot', function (\Illuminate\Http\Request $req) {
                $filename = preg_replace('/[^a-z0-9\-]/', '', $req->input('filename'));
                $data     = $req->input('data');
                if (!$filename || !$data) return response()->json(['error' => 'missing params'], 422);
                $data = preg_replace('/^data:image\/\w+;base64,/', '', $data);
                $dir = realpath(__DIR__ . '/../../public/img/help');
                file_put_contents($dir . '/' . $filename . '.jpg', base64_decode($data));
                return response()->json(['ok' => true, 'file' => $filename . '.jpg']);
            })->name('dev.save-screenshot');

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
