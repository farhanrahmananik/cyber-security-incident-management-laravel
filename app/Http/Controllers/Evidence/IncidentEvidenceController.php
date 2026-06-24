<?php

namespace App\Http\Controllers\Evidence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evidence\StoreIncidentEvidenceRequest;
use App\Http\Requests\Evidence\UpdateIncidentEvidenceRequest;
use App\Models\Incident;
use App\Models\IncidentEvidence;
use App\Services\Incident\IncidentEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentEvidenceController extends Controller
{
    /**
     * Store uploaded evidence for the incident.
     */
    public function store(
        StoreIncidentEvidenceRequest $request,
        Incident $incident,
        IncidentEvidenceService $service,
    ): RedirectResponse {
        Gate::authorize('evidence.manage');

        $service->create($incident, $request->validated(), $request->user());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Evidence uploaded successfully.');
    }

    /**
     * Update evidence metadata.
     */
    public function update(
        UpdateIncidentEvidenceRequest $request,
        Incident $incident,
        IncidentEvidence $incidentEvidence,
        IncidentEvidenceService $service,
    ): RedirectResponse {
        Gate::authorize('evidence.manage');
        $this->ensureEvidenceBelongsToIncident($incident, $incidentEvidence);

        $service->update($incidentEvidence, $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Evidence updated successfully.');
    }

    /**
     * Soft delete evidence metadata.
     */
    public function destroy(
        Request $request,
        Incident $incident,
        IncidentEvidence $incidentEvidence,
        IncidentEvidenceService $service,
    ): RedirectResponse {
        Gate::authorize('evidence.manage');
        $this->ensureEvidenceBelongsToIncident($incident, $incidentEvidence);

        $service->delete($incidentEvidence, $request->user());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Evidence deleted successfully.');
    }

    /**
     * Download a stored private evidence file.
     */
    public function download(
        Request $request,
        Incident $incident,
        IncidentEvidence $incidentEvidence,
        IncidentEvidenceService $service,
    ): StreamedResponse {
        Gate::authorize('evidence.view');
        $this->ensureEvidenceBelongsToIncident($incident, $incidentEvidence);

        abort_unless(
            Storage::disk($incidentEvidence->disk)->exists($incidentEvidence->stored_path),
            404,
        );

        $service->recordDownload($incidentEvidence, $request->user());

        return Storage::disk($incidentEvidence->disk)
            ->download($incidentEvidence->stored_path, $incidentEvidence->original_filename);
    }

    /**
     * Ensure the nested evidence belongs to the route incident.
     */
    private function ensureEvidenceBelongsToIncident(Incident $incident, IncidentEvidence $incidentEvidence): void
    {
        abort_unless((int) $incidentEvidence->incident_id === (int) $incident->getKey(), 404);
    }
}
