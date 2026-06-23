<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentAssignmentService
{
    /**
     * Assign an incident to an eligible analyst or security manager.
     */
    public function assign(
        Incident $incident,
        User $assignedTo,
        User $assignedBy,
        ?string $notes = null,
    ): IncidentAssignment {
        $this->validateAssignee($incident, $assignedTo);

        return DB::transaction(function () use ($incident, $assignedTo, $assignedBy, $notes): IncidentAssignment {
            $incident->update([
                'current_assigned_to_id' => $assignedTo->getKey(),
            ]);

            return IncidentAssignment::query()->create([
                'incident_id' => $incident->getKey(),
                'assigned_to_id' => $assignedTo->getKey(),
                'assigned_by_id' => $assignedBy->getKey(),
                'notes' => $notes,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * Validate assignment business rules.
     */
    private function validateAssignee(Incident $incident, User $assignedTo): void
    {
        if ($assignedTo->is_active !== true) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'The selected assignee must be an active user.',
            ]);
        }

        if (! $assignedTo->hasAnyRole(['soc-analyst', 'security-manager'])) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'The selected assignee must be a SOC Analyst or Security Manager.',
            ]);
        }

        if ((int) $incident->current_assigned_to_id === (int) $assignedTo->getKey()) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'This incident is already assigned to the selected user.',
            ]);
        }
    }
}
