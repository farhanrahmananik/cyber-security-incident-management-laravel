<?php

namespace App\Http\Controllers\Ioc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ioc\StoreIncidentIocRequest;
use App\Http\Requests\Ioc\UpdateIncidentIocRequest;
use App\Models\Incident;
use App\Models\IncidentIoc;
use App\Services\Incident\IncidentIocService;
use Illuminate\Http\RedirectResponse;

class IncidentIocController extends Controller
{
    /**
     * Store a new IOC for the incident.
     */
    public function store(
        StoreIncidentIocRequest $request,
        Incident $incident,
        IncidentIocService $service,
    ): RedirectResponse {
        $service->create($incident, $request->user(), $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'IOC added successfully.');
    }

    /**
     * Update the specified IOC.
     */
    public function update(
        UpdateIncidentIocRequest $request,
        Incident $incident,
        IncidentIoc $incidentIoc,
        IncidentIocService $service,
    ): RedirectResponse {
        $service->update($incident, $incidentIoc, $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'IOC updated successfully.');
    }

    /**
     * Delete the specified IOC.
     */
    public function destroy(
        Incident $incident,
        IncidentIoc $incidentIoc,
        IncidentIocService $service,
    ): RedirectResponse {
        $service->delete($incident, $incidentIoc);

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'IOC deleted successfully.');
    }
}
