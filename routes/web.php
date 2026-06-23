<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\IncidentSetup\IncidentCategoryController;
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

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
