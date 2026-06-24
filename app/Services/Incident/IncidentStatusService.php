<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentStatusTransition;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentStatusService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Return all valid incident workflow statuses.
     *
     * @return list<string>
     */
    public function allowedStatuses(): array
    {
        return [
            'reported',
            'triaged',
            'assigned',
            'investigating',
            'contained',
            'resolved',
            'closed',
        ];
    }

    /**
     * Return valid forward transitions by current status.
     *
     * @return array<string, list<string>>
     */
    public function allowedTransitions(): array
    {
        return [
            'reported' => ['triaged', 'assigned'],
            'triaged' => ['assigned', 'investigating'],
            'assigned' => ['investigating'],
            'investigating' => ['contained', 'resolved'],
            'contained' => ['investigating', 'resolved'],
            'resolved' => ['investigating', 'closed'],
            'closed' => [],
        ];
    }

    /**
     * Return transitions available to the given user for the incident.
     *
     * @return list<string>
     */
    public function availableTransitions(Incident $incident, User $user): array
    {
        if (! $user->can('incident.status.update')) {
            return [];
        }

        return array_values(array_filter(
            $this->allowedTransitions()[$incident->status] ?? [],
            fn (string $status): bool => $status !== 'closed' || $user->can('incident.close'),
        ));
    }

    /**
     * Transition an incident to a new workflow status.
     */
    public function transition(
        Incident $incident,
        User $user,
        string $toStatus,
        ?string $notes = null,
    ): IncidentStatusTransition {
        $this->validateTargetStatus($toStatus);
        $this->authorizeTransition($user, $toStatus);

        $fromStatus = (string) $incident->status;
        $notes = $this->normalizeNotes($notes);

        $this->validateTransition($fromStatus, $toStatus);

        $transition = DB::transaction(function () use ($incident, $user, $fromStatus, $toStatus, $notes): IncidentStatusTransition {
            $incident->update([
                'status' => $toStatus,
            ]);

            return IncidentStatusTransition::query()->create([
                'incident_id' => $incident->getKey(),
                'changed_by_id' => $user->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $notes,
            ]);
        });

        $this->auditLogService->record(
            event: 'incident.status_changed',
            auditable: $incident->refresh(),
            oldValues: ['status' => $fromStatus],
            newValues: ['status' => $toStatus],
            user: $user,
            request: request(),
        );

        return $transition;
    }

    /**
     * Ensure the actor has the required workflow permission.
     */
    private function authorizeTransition(User $user, string $toStatus): void
    {
        if (! $user->can('incident.status.update')) {
            throw new AuthorizationException;
        }

        if ($toStatus === 'closed' && ! $user->can('incident.close')) {
            throw new AuthorizationException;
        }
    }

    /**
     * Validate the requested target status.
     */
    private function validateTargetStatus(string $toStatus): void
    {
        if (! in_array($toStatus, $this->allowedStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected incident status is invalid.',
            ]);
        }
    }

    /**
     * Validate workflow transition rules.
     */
    private function validateTransition(string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            throw ValidationException::withMessages([
                'status' => 'The incident is already in the selected status.',
            ]);
        }

        if (! in_array($toStatus, $this->allowedTransitions()[$fromStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected status transition is not allowed.',
            ]);
        }
    }

    /**
     * Normalize optional transition notes.
     */
    private function normalizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);

        return $notes === '' ? null : $notes;
    }
}
