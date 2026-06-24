<?php

use App\Http\Controllers\AuditLog\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Evidence\IncidentEvidenceController;
use App\Http\Controllers\Incident\IncidentAssignmentController;
use App\Http\Controllers\Incident\IncidentController;
use App\Http\Controllers\Incident\IncidentStatusController;
use App\Http\Controllers\IncidentSetup\IncidentCategoryController;
use App\Http\Controllers\IncidentSetup\PriorityLevelController;
use App\Http\Controllers\IncidentSetup\SeverityLevelController;
use App\Http\Controllers\Investigation\InvestigationNoteController;
use App\Http\Controllers\Ioc\IncidentIocController;
use App\Http\Controllers\Report\SecurityReportController;
use App\Http\Controllers\RolePermission\RoleController;
use App\Http\Controllers\ResponseAction\ResponseActionController;
use App\Http\Controllers\UserManagement\UserController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::prefix('users')
        ->name('users.')
        ->controller(UserController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:user.view')
                ->name('index');

            Route::post('/', 'store')
                ->middleware('permission:user.create')
                ->name('store');

            Route::patch('/{user}', 'update')
                ->middleware('permission:user.update')
                ->name('update');

            Route::patch('/{user}/activate', 'activate')
                ->middleware('permission:user.update')
                ->name('activate');

            Route::patch('/{user}/deactivate', 'deactivate')
                ->middleware('permission:user.delete')
                ->name('deactivate');
        });

    Route::prefix('roles')
        ->name('roles.')
        ->controller(RoleController::class)
        ->group(function () {
            Route::get('/', 'index')
                ->middleware('permission:role.view')
                ->name('index');

            Route::post('/', 'store')
                ->middleware('permission:role.create')
                ->name('store');

            Route::patch('/{role}', 'update')
                ->middleware('permission:role.update')
                ->name('update');

            Route::patch('/{role}/activate', 'activate')
                ->middleware('permission:role.update')
                ->name('activate');

            Route::patch('/{role}/deactivate', 'deactivate')
                ->middleware('permission:role.delete')
                ->name('deactivate');
        });

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

            Route::post('/{incident}/assign', [IncidentAssignmentController::class, 'store'])
                ->middleware('permission:incident.assign')
                ->name('assign');

            Route::patch('/{incident}/status', [IncidentStatusController::class, 'update'])
                ->middleware('permission:incident.status.update')
                ->name('status.update');

            Route::post('/{incident}/investigation-notes', [InvestigationNoteController::class, 'store'])
                ->middleware('permission:investigation-note.create')
                ->name('investigation-notes.store');

            Route::patch('/{incident}/investigation-notes/{investigationNote}', [InvestigationNoteController::class, 'update'])
                ->middleware('permission:investigation-note.update')
                ->name('investigation-notes.update');

            Route::delete('/{incident}/investigation-notes/{investigationNote}', [InvestigationNoteController::class, 'destroy'])
                ->middleware('permission:investigation-note.delete')
                ->name('investigation-notes.destroy');

            Route::post('/{incident}/iocs', [IncidentIocController::class, 'store'])
                ->middleware('permission:ioc.manage')
                ->name('iocs.store');

            Route::patch('/{incident}/iocs/{incidentIoc}', [IncidentIocController::class, 'update'])
                ->middleware('permission:ioc.manage')
                ->name('iocs.update');

            Route::delete('/{incident}/iocs/{incidentIoc}', [IncidentIocController::class, 'destroy'])
                ->middleware('permission:ioc.manage')
                ->name('iocs.destroy');

            Route::post('/{incident}/evidences', [IncidentEvidenceController::class, 'store'])
                ->middleware('permission:evidence.manage')
                ->name('evidences.store');

            Route::patch('/{incident}/evidences/{incidentEvidence}', [IncidentEvidenceController::class, 'update'])
                ->middleware('permission:evidence.manage')
                ->name('evidences.update');

            Route::delete('/{incident}/evidences/{incidentEvidence}', [IncidentEvidenceController::class, 'destroy'])
                ->middleware('permission:evidence.manage')
                ->name('evidences.destroy');

            Route::get('/{incident}/evidences/{incidentEvidence}/download', [IncidentEvidenceController::class, 'download'])
                ->middleware('permission:evidence.view')
                ->name('evidences.download');

            Route::post('/{incident}/response-actions', [ResponseActionController::class, 'store'])
                ->middleware('permission:response-action.manage')
                ->name('response-actions.store');

            Route::patch('/{incident}/response-actions/{responseAction}', [ResponseActionController::class, 'update'])
                ->middleware('permission:response-action.manage')
                ->name('response-actions.update');

            Route::delete('/{incident}/response-actions/{responseAction}', [ResponseActionController::class, 'destroy'])
                ->middleware('permission:response-action.manage')
                ->name('response-actions.destroy');

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

    Route::get('/reports/security', [SecurityReportController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('reports.security.index');

    Route::get('/reports/security/export', [SecurityReportController::class, 'export'])
        ->middleware('permission:report.view')
        ->name('reports.security.export');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-log.view')
        ->name('audit-logs.index');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
