<?php

namespace App\Http\Controllers\IncidentSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentSetup\StoreIncidentCategoryRequest;
use App\Http\Requests\IncidentSetup\UpdateIncidentCategoryRequest;
use App\Models\IncidentCategory;
use App\Services\IncidentSetup\IncidentCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IncidentCategoryController extends Controller
{
    /**
     * Display incident categories.
     */
    public function index(): View
    {
        return view('incident-setup.categories.index', [
            'incidentCategories' => IncidentCategory::query()->ordered()->get(),
        ]);
    }

    /**
     * Store a newly created incident category.
     */
    public function store(
        StoreIncidentCategoryRequest $request,
        IncidentCategoryService $incidentCategoryService,
    ): RedirectResponse {
        $incidentCategoryService->create($request->validated());

        return redirect()
            ->route('incident-categories.index')
            ->with('success', 'Incident category created successfully.');
    }

    /**
     * Update the specified incident category.
     */
    public function update(
        UpdateIncidentCategoryRequest $request,
        IncidentCategory $incidentCategory,
        IncidentCategoryService $incidentCategoryService,
    ): RedirectResponse {
        $incidentCategoryService->update($incidentCategory, $request->validated());

        return redirect()
            ->route('incident-categories.index')
            ->with('success', 'Incident category updated successfully.');
    }

    /**
     * Deactivate the specified incident category.
     */
    public function destroy(
        IncidentCategory $incidentCategory,
        IncidentCategoryService $incidentCategoryService,
    ): RedirectResponse {
        $incidentCategoryService->deactivate($incidentCategory);

        return redirect()
            ->route('incident-categories.index')
            ->with('success', 'Incident category deactivated successfully.');
    }
}
