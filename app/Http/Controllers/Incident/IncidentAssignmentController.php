<?php

namespace App\Http\Controllers\Incident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\AssignIncidentRequest;
use App\Models\Incident;
use App\Models\User;
use App\Services\Incident\IncidentAssignmentService;
use Illuminate\Http\RedirectResponse;

class IncidentAssignmentController extends Controller
{
    /**
     * Assign the specified incident to an eligible user.
     */
    public function store(
        AssignIncidentRequest $request,
        Incident $incident,
        IncidentAssignmentService $service,
    ): RedirectResponse {
        $validated = $request->validated();
        $assignedTo = User::query()->findOrFail($validated['assigned_to_id']);

        $service->assign(
            $incident,
            $assignedTo,
            $request->user(),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('incidents.show', $incident)
            ->with('success', 'Incident assigned successfully.');
    }
}
