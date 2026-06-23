<?php

namespace App\Http\Controllers\IncidentSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentSetup\StorePriorityLevelRequest;
use App\Http\Requests\IncidentSetup\UpdatePriorityLevelRequest;
use App\Models\PriorityLevel;
use App\Services\IncidentSetup\PriorityLevelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PriorityLevelController extends Controller
{
    /**
     * Display priority levels.
     */
    public function index(): View
    {
        return view('incident-setup.priority-levels.index', [
            'priorityLevels' => PriorityLevel::query()->ordered()->get(),
        ]);
    }

    /**
     * Store a newly created priority level.
     */
    public function store(
        StorePriorityLevelRequest $request,
        PriorityLevelService $priorityLevelService,
    ): RedirectResponse {
        $priorityLevelService->create($request->validated());

        return redirect()
            ->route('priority-levels.index')
            ->with('success', 'Priority level created successfully.');
    }

    /**
     * Update the specified priority level.
     */
    public function update(
        UpdatePriorityLevelRequest $request,
        PriorityLevel $priorityLevel,
        PriorityLevelService $priorityLevelService,
    ): RedirectResponse {
        $priorityLevelService->update($priorityLevel, $request->validated());

        return redirect()
            ->route('priority-levels.index')
            ->with('success', 'Priority level updated successfully.');
    }

    /**
     * Deactivate the specified priority level.
     */
    public function destroy(
        PriorityLevel $priorityLevel,
        PriorityLevelService $priorityLevelService,
    ): RedirectResponse {
        $priorityLevelService->deactivate($priorityLevel);

        return redirect()
            ->route('priority-levels.index')
            ->with('success', 'Priority level deactivated successfully.');
    }
}
