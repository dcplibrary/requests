<?php

use App\Http\Controllers\Admin\AudienceController;
use App\Http\Controllers\Admin\MaterialTypeController;
use App\Http\Controllers\Admin\RequestController;
use App\Http\Controllers\Admin\RequestStatusController;
use App\Http\Controllers\Admin\SelectorGroupController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Livewire\SfpForm;
use Illuminate\Support\Facades\Route;

// --- Public: SFP Form ---
Route::get('/', SfpForm::class)->name('sfp.form');

// --- Staff: Entra SSO auth ---
Route::get('/login', [\App\Http\Controllers\Auth\EntraAuthController::class, 'redirect'])->name('login');
Route::get('/auth/callback', [\App\Http\Controllers\Auth\EntraAuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [\App\Http\Controllers\Auth\EntraAuthController::class, 'logout'])->name('logout');

// --- Staff: Protected ---
Route::prefix('staff')->name('staff.')->middleware(['auth'])->group(function () {

    Route::get('/', fn() => redirect()->route('staff.requests.index'));

    // Requests
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{request}', [RequestController::class, 'show'])->name('requests.show');
    Route::patch('/requests/{request}/status', [RequestController::class, 'updateStatus'])->name('requests.status');

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {

        // Settings
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');

        // Material Types
        Route::resource('material-types', MaterialTypeController::class)->except(['show']);

        // Audiences
        Route::resource('audiences', AudienceController::class)->except(['show']);

        // Request Statuses
        Route::resource('statuses', RequestStatusController::class)->except(['show']);

        // Users
        Route::resource('users', UserController::class)->except(['show', 'create', 'store']);

        // Selector Groups
        Route::resource('groups', SelectorGroupController::class)->except(['show']);
    });
});
