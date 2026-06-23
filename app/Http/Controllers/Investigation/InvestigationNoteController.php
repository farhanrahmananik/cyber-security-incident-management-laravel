<?php

namespace App\Http\Controllers\Investigation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investigation\StoreInvestigationNoteRequest;
use App\Http\Requests\Investigation\UpdateInvestigationNoteRequest;
use App\Models\Incident;
use App\Models\InvestigationNote;
use App\Services\Investigation\InvestigationNoteService;
use Illuminate\Http\RedirectResponse;

class InvestigationNoteController extends Controller
{
    /**
     * Store a new investigation note for the incident.
     */
    public function store(
        StoreInvestigationNoteRequest $request,
        Incident $incident,
        InvestigationNoteService $service,
    ): RedirectResponse {
        $service->create($incident, $request->user(), $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Investigation note added successfully.');
    }

    /**
     * Update the specified investigation note.
     */
    public function update(
        UpdateInvestigationNoteRequest $request,
        Incident $incident,
        InvestigationNote $investigationNote,
        InvestigationNoteService $service,
    ): RedirectResponse {
        $this->ensureNoteBelongsToIncident($incident, $investigationNote);

        $service->update($investigationNote, $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Investigation note updated successfully.');
    }

    /**
     * Delete the specified investigation note.
     */
    public function destroy(
        Incident $incident,
        InvestigationNote $investigationNote,
        InvestigationNoteService $service,
    ): RedirectResponse {
        $this->ensureNoteBelongsToIncident($incident, $investigationNote);

        $service->delete($investigationNote);

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Investigation note deleted successfully.');
    }

    /**
     * Ensure the nested investigation note belongs to the route incident.
     */
    private function ensureNoteBelongsToIncident(Incident $incident, InvestigationNote $investigationNote): void
    {
        abort_unless((int) $investigationNote->incident_id === (int) $incident->getKey(), 404);
    }
}
