<?php

namespace App\Http\Controllers\IncidentSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentSetup\StoreSeverityLevelRequest;
use App\Http\Requests\IncidentSetup\UpdateSeverityLevelRequest;
use App\Models\SeverityLevel;
use App\Services\IncidentSetup\SeverityLevelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeverityLevelController extends Controller
{
    /**
     * Display severity levels.
     */
    public function index(): View
    {
        return view('incident-setup.severity-levels.index', [
            'severityLevels' => SeverityLevel::query()->ordered()->get(),
        ]);
    }

    /**
     * Store a newly created severity level.
     */
    public function store(
        StoreSeverityLevelRequest $request,
        SeverityLevelService $severityLevelService,
    ): RedirectResponse {
        $severityLevelService->create($request->validated());

        return redirect()
            ->route('severity-levels.index')
            ->with('success', 'Severity level created successfully.');
    }

    /**
     * Update the specified severity level.
     */
    public function update(
        UpdateSeverityLevelRequest $request,
        SeverityLevel $severityLevel,
        SeverityLevelService $severityLevelService,
    ): RedirectResponse {
        $severityLevelService->update($severityLevel, $request->validated());

        return redirect()
            ->route('severity-levels.index')
            ->with('success', 'Severity level updated successfully.');
    }

    /**
     * Deactivate the specified severity level.
     */
    public function destroy(
        SeverityLevel $severityLevel,
        SeverityLevelService $severityLevelService,
    ): RedirectResponse {
        $severityLevelService->deactivate($severityLevel);

        return redirect()
            ->route('severity-levels.index')
            ->with('success', 'Severity level deactivated successfully.');
    }
}
