<?php

namespace App\Services\ResponseAction;

use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ResponseActionService
{
    /**
     * Create a response action for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $performedBy, array $data): ResponseAction
    {
        return DB::transaction(function () use ($incident, $performedBy, $data): ResponseAction {
            return ResponseAction::query()->create([
                ...$this->sanitize($data),
                'incident_id' => $incident->getKey(),
                'performed_by' => $performedBy->getKey(),
            ]);
        });
    }

    /**
     * Update fields allowed by the response action workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, ResponseAction $responseAction, array $data): ResponseAction
    {
        $this->ensureResponseActionBelongsToIncident($incident, $responseAction);

        return DB::transaction(function () use ($responseAction, $data): ResponseAction {
            $responseAction->update($this->sanitize($data));

            return $responseAction;
        });
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
}
