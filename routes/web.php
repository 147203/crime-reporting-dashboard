<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IncidentStatusController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Every route below requires an authenticated, active account; each also carries its own authorization (project rule).
Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->middleware('can:viewAny,App\Models\Incident')->name('home');

    Route::get('/password', [PasswordController::class, 'edit'])->middleware('can:change-own-password')->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->middleware(['can:change-own-password', 'throttle:password-change'])->name('password.update');

    Route::prefix('incidents')->name('incidents.')->group(function (): void {
        Route::get('/', [IncidentController::class, 'index'])->middleware('can:viewAny,App\Models\Incident')->name('index');
        Route::get('/create', [IncidentController::class, 'create'])->middleware('can:create,App\Models\Incident')->name('create');
        Route::post('/', [IncidentController::class, 'store'])->middleware('can:create,App\Models\Incident')->name('store');
        Route::get('/{incident}', [IncidentController::class, 'show'])->middleware('can:view,incident')->name('show');
        Route::get('/{incident}/edit', [IncidentController::class, 'edit'])->middleware('can:update,incident')->name('edit');
        Route::put('/{incident}', [IncidentController::class, 'update'])->middleware('can:update,incident')->name('update');
        Route::put('/{incident}/status', [IncidentStatusController::class, 'update'])->middleware('can:changeStatus,incident')->name('status');
        Route::delete('/{incident}', [IncidentController::class, 'destroy'])->middleware('can:delete,incident')->name('destroy');
    });

    Route::get('/duplicates', [DuplicateController::class, 'index'])->middleware('can:reviewDuplicates,App\Models\Incident')->name('duplicates.index');
    Route::put('/duplicates/{incident}', [DuplicateController::class, 'resolve'])->middleware(['can:reviewDuplicates,App\Models\Incident', 'can:view,incident'])->name('duplicates.resolve');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('can:export,App\Models\Incident')->name('reports.index');
    Route::get('/reports/csv', [ReportController::class, 'csv'])->middleware(['can:export,App\Models\Incident', 'throttle:exports'])->name('reports.csv');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->middleware(['can:export,App\Models\Incident', 'throttle:exports'])->name('reports.pdf');

    Route::get('/quality', [QualityController::class, 'index'])->middleware('can:viewDataQuality,App\Models\Incident')->name('quality.index');

    Route::prefix('imports')->name('imports.')->middleware('can:import,App\Models\Incident')->group(function (): void {
        Route::get('/', [ImportController::class, 'create'])->name('create');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/{batch}', [ImportController::class, 'show'])->name('show');
        Route::post('/{batch}/commit', [ImportController::class, 'commit'])->name('commit');
        Route::post('/{batch}/discard', [ImportController::class, 'discard'])->name('discard');
        Route::get('/{batch}/errors', [ImportController::class, 'errors'])->name('errors');
    });

    Route::prefix('admin')->name('admin.')->middleware(['role:admin', 'can:manage-system'])->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/reference', [ReferenceDataController::class, 'index'])->name('reference.index');
        Route::post('/reference/{type}', [ReferenceDataController::class, 'store'])->whereIn('type', ['categories', 'sub-counties', 'wards', 'stations'])->name('reference.store');
        Route::put('/reference/{type}/{item}', [ReferenceDataController::class, 'update'])->whereIn('type', ['categories', 'sub-counties', 'wards', 'stations'])->whereNumber('item')->name('reference.update');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
