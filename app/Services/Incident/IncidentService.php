<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Get incidents visible to the given user.
     *
     * @return Collection<int, Incident>
     */
    public function listForUser(User $user): Collection
    {
        return Incident::query()
            ->with(['reporter', 'category', 'severity', 'priority'])
            ->visibleToUser($user)
            ->latestReported()
            ->get();
    }

    /**
     * Create a new incident for the authenticated reporter.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $reporter, array $data): Incident
    {
        $incident = DB::transaction(function () use ($reporter, $data): Incident {
            $data['incident_number'] = $this->generateIncidentNumber();
            $data['reporter_id'] = $reporter->getKey();
            $data['status'] = 'reported';

            return Incident::query()->create($data);
        });

        $this->auditLogService->record(
            event: 'incident.created',
            auditable: $incident,
            newValues: $this->safeIncidentValues($incident),
            request: request(),
        );

        return $incident;
    }

    /**
     * Update incident fields allowed in this foundation step.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data): Incident
    {
        $oldValues = $this->safeIncidentValues($incident);
        $oldDescription = $incident->description;
        $oldImpactSummary = $incident->impact_summary;

        $incident->update(Arr::only($data, [
            'incident_category_id',
            'severity_level_id',
            'priority_level_id',
            'title',
            'description',
            'impact_summary',
            'affected_system',
            'occurred_at',
            'detected_at',
        ]));

        $newValues = $this->safeIncidentValues($incident);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($oldDescription !== $incident->description) {
            $changedValues['old']['description_changed'] = false;
            $changedValues['new']['description_changed'] = true;
        }

        if ($oldImpactSummary !== $incident->impact_summary) {
            $changedValues['old']['impact_summary_changed'] = false;
            $changedValues['new']['impact_summary_changed'] = true;
        }

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'incident.updated',
                auditable: $incident,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $incident;
    }

    /**
     * Delete the incident using the model's configured delete behavior.
     */
    public function delete(Incident $incident): void
    {
        $oldDeletedAt = $incident->deleted_at?->format('Y-m-d H:i:s');

        $incident->delete();

        $this->auditLogService->record(
            event: 'incident.deleted',
            auditable: $incident,
            oldValues: ['deleted_at' => $oldDeletedAt],
            newValues: ['deleted_at' => $incident->deleted_at?->format('Y-m-d H:i:s')],
            request: request(),
        );
    }

    /**
     * Determine whether the user can view the incident record.
     */
    public function canView(User $user, Incident $incident): bool
    {
        return $user->hasAnyRole(['super-admin', 'security-manager', 'soc-analyst'])
            || (int) $incident->reporter_id === (int) $user->getKey();
    }

    /**
     * Determine whether the user can update the incident record.
     */
    public function canUpdate(User $user, Incident $incident): bool
    {
        if ($user->hasAnyRole(['super-admin', 'security-manager', 'soc-analyst'])) {
            return true;
        }

        return (int) $incident->reporter_id === (int) $user->getKey()
            && $incident->status === 'reported';
    }

    /**
     * Determine whether the user can delete the incident record.
     */
    public function canDelete(User $user, Incident $incident): bool
    {
        return $user->hasAnyRole(['super-admin', 'security-manager']);
    }

    /**
     * Generate a human-readable incident number.
     */
    private function generateIncidentNumber(): string
    {
        $prefix = 'INC-'.now()->format('Ymd').'-';
        $nextNumber = 1;

        $latestIncidentNumber = Incident::query()
            ->withTrashed()
            ->where('incident_number', 'like', $prefix.'%')
            ->orderByDesc('incident_number')
            ->value('incident_number');

        if (is_string($latestIncidentNumber) && preg_match('/-(\d+)$/', $latestIncidentNumber, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $incidentNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (
            Incident::query()
                ->withTrashed()
                ->where('incident_number', $incidentNumber)
                ->exists()
        );

        return $incidentNumber;
    }

    /**
     * Return compact incident fields safe for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeIncidentValues(Incident $incident): array
    {
        return [
            'incident_number' => $incident->incident_number,
            'title' => $incident->title,
            'incident_category_id' => $incident->incident_category_id,
            'severity_level_id' => $incident->severity_level_id,
            'priority_level_id' => $incident->priority_level_id,
            'reporter_id' => $incident->reporter_id,
            'status' => $incident->status,
            'affected_system' => $incident->affected_system,
            'occurred_at' => $incident->occurred_at?->format('Y-m-d H:i:s'),
            'detected_at' => $incident->detected_at?->format('Y-m-d H:i:s'),
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
