<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentIoc;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IncidentIocService
{
    /**
     * Create an IOC for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $user, array $data): IncidentIoc
    {
        return DB::transaction(function () use ($incident, $user, $data): IncidentIoc {
            return IncidentIoc::query()->create([
                ...$this->sanitize($data),
                'incident_id' => $incident->getKey(),
                'created_by_id' => $user->getKey(),
            ]);
        });
    }

    /**
     * Update the IOC fields allowed by the workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, IncidentIoc $incidentIoc, array $data): IncidentIoc
    {
        $this->ensureIocBelongsToIncident($incident, $incidentIoc);

        return DB::transaction(function () use ($incidentIoc, $data): IncidentIoc {
            $incidentIoc->update($this->sanitize($data));

            return $incidentIoc;
        });
    }

    /**
     * Delete the IOC.
     */
    public function delete(Incident $incident, IncidentIoc $incidentIoc): void
    {
        $this->ensureIocBelongsToIncident($incident, $incidentIoc);

        DB::transaction(function () use ($incidentIoc): void {
            $incidentIoc->delete();
        });
    }

    /**
     * Ensure the nested IOC belongs to the route incident.
     */
    private function ensureIocBelongsToIncident(Incident $incident, IncidentIoc $incidentIoc): void
    {
        abort_unless((int) $incidentIoc->incident_id === (int) $incident->getKey(), 404);
    }

    /**
     * Normalize IOC input while keeping only allowed fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $sanitized = Arr::only($data, [
            'type',
            'value',
            'description',
            'confidence',
            'first_seen_at',
            'last_seen_at',
        ]);

        foreach (['type', 'value', 'description', 'confidence'] as $field) {
            if (array_key_exists($field, $sanitized) && is_string($sanitized[$field])) {
                $sanitized[$field] = trim($sanitized[$field]);
            }
        }

        return $sanitized;
    }
}
