<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Incident\IncidentController;
use App\Http\Controllers\IncidentSetup\IncidentCategoryController;
use App\Http\Controllers\IncidentSetup\PriorityLevelController;
use App\Http\Controllers\IncidentSetup\SeverityLevelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->middleware('permission:dashboard.view')->name('dashboard');

    Route::prefix('incidents')
        ->name('incidents.')
        ->controller(IncidentController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:incident.view')
                ->name('index');

            Route::get('/create', 'create')
                ->middleware('permission:incident.create')
                ->name('create');

            Route::post('/', 'store')
                ->middleware('permission:incident.create')
                ->name('store');

            Route::get('/{incident}', 'show')
                ->middleware('permission:incident.view')
                ->name('show');

            Route::get('/{incident}/edit', 'edit')
                ->middleware('permission:incident.update')
                ->name('edit');

            Route::match(['put', 'patch'], '/{incident}', 'update')
                ->middleware('permission:incident.update')
                ->name('update');

            Route::delete('/{incident}', 'destroy')
                ->middleware('permission:incident.delete')
                ->name('destroy');
        });

    Route::prefix('incident-setup/categories')
        ->name('incident-categories.')
        ->controller(IncidentCategoryController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:incident-category.view')
                ->name('index');

            Route::post('/', 'store')
                ->middleware('permission:incident-category.manage')
                ->name('store');

            Route::match(['put', 'patch'], '/{incidentCategory}', 'update')
                ->middleware('permission:incident-category.manage')
                ->name('update');

            Route::delete('/{incidentCategory}', 'destroy')
                ->middleware('permission:incident-category.manage')
                ->name('destroy');
        });

    Route::prefix('incident-setup/severity-levels')
        ->name('severity-levels.')
        ->controller(SeverityLevelController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:severity-level.view')
                ->name('index');

            Route::post('/', 'store')
                ->middleware('permission:severity-level.manage')
                ->name('store');

            Route::match(['put', 'patch'], '/{severityLevel}', 'update')
                ->middleware('permission:severity-level.manage')
                ->name('update');

            Route::delete('/{severityLevel}', 'destroy')
                ->middleware('permission:severity-level.manage')
                ->name('destroy');
        });

    Route::prefix('incident-setup/priority-levels')
        ->name('priority-levels.')
        ->controller(PriorityLevelController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:priority-level.view')
                ->name('index');

            Route::post('/', 'store')
                ->middleware('permission:priority-level.manage')
                ->name('store');

            Route::match(['put', 'patch'], '/{priorityLevel}', 'update')
                ->middleware('permission:priority-level.manage')
                ->name('update');

            Route::delete('/{priorityLevel}', 'destroy')
                ->middleware('permission:priority-level.manage')
                ->name('destroy');
        });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
