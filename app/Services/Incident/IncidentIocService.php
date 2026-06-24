<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentIoc;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IncidentIocService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create an IOC for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $user, array $data): IncidentIoc
    {
        $ioc = DB::transaction(function () use ($incident, $user, $data): IncidentIoc {
            return IncidentIoc::query()->create([
                ...$this->sanitize($data),
                'incident_id' => $incident->getKey(),
                'created_by_id' => $user->getKey(),
            ]);
        });

        $this->auditLogService->record(
            event: 'incident_ioc.created',
            auditable: $ioc,
            newValues: $this->safeValues($ioc) + [
                'value_present' => filled($ioc->value),
                'value_hash' => $this->valueHash($ioc->value),
                'description_present' => filled($ioc->description),
                'description_length' => $ioc->description === null ? 0 : strlen($ioc->description),
            ],
            request: request(),
        );

        return $ioc;
    }

    /**
     * Update the IOC fields allowed by the workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, IncidentIoc $incidentIoc, array $data): IncidentIoc
    {
        $this->ensureIocBelongsToIncident($incident, $incidentIoc);

        $oldValues = $this->safeValues($incidentIoc);
        $oldValueHash = $this->valueHash($incidentIoc->value);
        $oldDescription = $incidentIoc->description;

        $updatedIoc = DB::transaction(function () use ($incidentIoc, $data): IncidentIoc {
            $incidentIoc->update($this->sanitize($data));

            return $incidentIoc;
        });

        $newValues = $this->safeValues($updatedIoc);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($oldValueHash !== $this->valueHash($updatedIoc->value)) {
            $changedValues['old']['value_changed'] = false;
            $changedValues['old']['value_hash'] = $oldValueHash;
            $changedValues['new']['value_changed'] = true;
            $changedValues['new']['value_hash'] = $this->valueHash($updatedIoc->value);
        }

        if ($oldDescription !== $updatedIoc->description) {
            $changedValues['old']['description_changed'] = false;
            $changedValues['old']['description_length'] = $oldDescription === null ? 0 : strlen($oldDescription);
            $changedValues['new']['description_changed'] = true;
            $changedValues['new']['description_length'] = $updatedIoc->description === null ? 0 : strlen($updatedIoc->description);
        }

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'incident_ioc.updated',
                auditable: $updatedIoc,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $updatedIoc;
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

        $this->auditLogService->record(
            event: 'incident_ioc.deleted',
            auditable: $incidentIoc,
            oldValues: ['deleted' => false],
            newValues: ['deleted' => true],
            request: request(),
        );
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

    /**
     * Return safe IOC metadata for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(IncidentIoc $incidentIoc): array
    {
        return [
            'incident_id' => $incidentIoc->incident_id,
            'type' => $incidentIoc->type,
            'confidence' => $incidentIoc->confidence,
            'first_seen_at' => $incidentIoc->first_seen_at?->format('Y-m-d H:i:s'),
            'last_seen_at' => $incidentIoc->last_seen_at?->format('Y-m-d H:i:s'),
            'created_by_id' => $incidentIoc->created_by_id,
        ];
    }

    private function valueHash(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
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
