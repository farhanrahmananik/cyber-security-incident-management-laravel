<?php

namespace App\Services\ResponseAction;

use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ResponseActionService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create a response action for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $performedBy, array $data): ResponseAction
    {
        $responseAction = DB::transaction(function () use ($incident, $performedBy, $data): ResponseAction {
            return ResponseAction::query()->create([
                ...$this->sanitize($data),
                'incident_id' => $incident->getKey(),
                'performed_by' => $performedBy->getKey(),
            ]);
        });

        $this->auditLogService->record(
            event: 'response_action.created',
            auditable: $responseAction,
            newValues: $this->safeValues($responseAction) + [
                'description_present' => filled($responseAction->description),
                'description_length' => $responseAction->description === null ? 0 : strlen($responseAction->description),
            ],
            request: request(),
        );

        return $responseAction;
    }

    /**
     * Update fields allowed by the response action workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, ResponseAction $responseAction, array $data): ResponseAction
    {
        $this->ensureResponseActionBelongsToIncident($incident, $responseAction);

        $oldValues = $this->safeValues($responseAction);
        $oldDescription = $responseAction->description;

        $updatedResponseAction = DB::transaction(function () use ($responseAction, $data): ResponseAction {
            $responseAction->update($this->sanitize($data));

            return $responseAction;
        });

        $newValues = $this->safeValues($updatedResponseAction);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($oldDescription !== $updatedResponseAction->description) {
            $changedValues['old']['description_changed'] = false;
            $changedValues['old']['description_length'] = $oldDescription === null ? 0 : strlen($oldDescription);
            $changedValues['new']['description_changed'] = true;
            $changedValues['new']['description_length'] = $updatedResponseAction->description === null ? 0 : strlen($updatedResponseAction->description);
        }

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'response_action.updated',
                auditable: $updatedResponseAction,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $updatedResponseAction;
    }

    /**
     * Delete the response action.
     */
    public function delete(Incident $incident, ResponseAction $responseAction): void
    {
        $this->ensureResponseActionBelongsToIncident($incident, $responseAction);

        DB::transaction(function () use ($responseAction): void {
            $responseAction->delete();
        });

        $this->auditLogService->record(
            event: 'response_action.deleted',
            auditable: $responseAction,
            oldValues: ['deleted' => false],
            newValues: ['deleted' => true],
            request: request(),
        );
    }

    /**
     * Ensure the nested response action belongs to the route incident.
     */
    private function ensureResponseActionBelongsToIncident(Incident $incident, ResponseAction $responseAction): void
    {
        abort_unless((int) $responseAction->incident_id === (int) $incident->getKey(), 404);
    }

    /**
     * Normalize response action input while keeping only allowed fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $sanitized = Arr::only($data, [
            'action_type',
            'status',
            'title',
            'description',
            'started_at',
            'completed_at',
        ]);

        foreach (['action_type', 'status', 'title', 'description'] as $field) {
            if (array_key_exists($field, $sanitized) && is_string($sanitized[$field])) {
                $sanitized[$field] = trim($sanitized[$field]);
            }
        }

        if (($sanitized['description'] ?? null) === '') {
            $sanitized['description'] = null;
        }

        return $sanitized;
    }

    /**
     * Return safe response action fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(ResponseAction $responseAction): array
    {
        return [
            'incident_id' => $responseAction->incident_id,
            'action_type' => $responseAction->action_type,
            'status' => $responseAction->status,
            'title' => $responseAction->title,
            'performed_by' => $responseAction->performed_by,
            'started_at' => $responseAction->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $responseAction->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Extract changed audit values from two safe snapshots.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changedValues(array $oldValues, array $newValues): array
    {
        $old = [];
        $new = [];

        foreach ($newValues as $key => $value) {
            if (($oldValues[$key] ?? null) === $value) {
                continue;
            }

            $old[$key] = $oldValues[$key] ?? null;
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }
}
