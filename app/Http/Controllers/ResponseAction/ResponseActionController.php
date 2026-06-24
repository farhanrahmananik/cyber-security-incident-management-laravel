<?php

namespace App\Http\Controllers\ResponseAction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResponseAction\StoreResponseActionRequest;
use App\Http\Requests\ResponseAction\UpdateResponseActionRequest;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Services\ResponseAction\ResponseActionService;
use Illuminate\Http\RedirectResponse;

class ResponseActionController extends Controller
{
    /**
     * Store a response action for the incident.
     */
    public function store(
        StoreResponseActionRequest $request,
        Incident $incident,
        ResponseActionService $service,
    ): RedirectResponse {
        $service->create($incident, $request->user(), $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Response action added successfully.');
    }

    /**
     * Update the specified response action.
     */
    public function update(
        UpdateResponseActionRequest $request,
        Incident $incident,
        ResponseAction $responseAction,
        ResponseActionService $service,
    ): RedirectResponse {
        $service->update($incident, $responseAction, $request->validated());

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Response action updated successfully.');
    }

    /**
     * Delete the specified response action.
     */
    public function destroy(
        Incident $incident,
        ResponseAction $responseAction,
        ResponseActionService $service,
    ): RedirectResponse {
        $service->delete($incident, $responseAction);

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Response action deleted successfully.');
    }
}
