<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IncidentService
{
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
        return DB::transaction(function () use ($reporter, $data): Incident {
            $data['incident_number'] = $this->generateIncidentNumber();
            $data['reporter_id'] = $reporter->getKey();
            $data['status'] = 'reported';

            return Incident::query()->create($data);
        });
    }

    /**
     * Update incident fields allowed in this foundation step.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data): Incident
    {
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

        return $incident;
    }

    /**
     * Delete the incident using the model's configured delete behavior.
     */
    public function delete(Incident $incident): void
    {
        $incident->delete();
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
}
