<?php

namespace App\Http\Controllers\Incident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Services\Incident\IncidentStatusService;
use Illuminate\Http\RedirectResponse;

class IncidentStatusController extends Controller
{
    /**
     * Update the incident workflow status.
     */
    public function update(
        UpdateIncidentStatusRequest $request,
        Incident $incident,
        IncidentStatusService $service,
    ): RedirectResponse {
        $service->transition(
            incident: $incident,
            user: $request->user(),
            toStatus: $request->validated('status'),
            notes: $request->validated('notes'),
        );

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident status updated successfully.');
    }
}
