<?php

namespace App\Services\Dashboard;

use App\Models\Incident;
use App\Models\IncidentEvidence;
use App\Models\IncidentIoc;
use App\Models\InvestigationNote;
use App\Models\ResponseAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Build role-aware dashboard data for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $incidentQuery = $this->visibleIncidentQuery($user);

        return [
            'scope_label' => $this->scopeLabel($user),
            'can_view_analyst_workload' => $this->canViewAnalystWorkload($user),
            'metrics' => [
                'total_incidents' => (clone $incidentQuery)->count(),
                'active_incidents' => (clone $incidentQuery)
                    ->whereNotIn('status', $this->resolvedStatuses())
                    ->count(),
                'unassigned_incidents' => (clone $incidentQuery)
                    ->whereNull('current_assigned_to_id')
                    ->count(),
                'resolved_incidents' => (clone $incidentQuery)
                    ->whereIn('status', $this->resolvedStatuses())
                    ->count(),
                'total_investigation_notes' => $this->countChildRecordsForVisibleIncidents(
                    InvestigationNote::query(),
                    $incidentQuery,
                ),
                'total_iocs' => $this->countChildRecordsForVisibleIncidents(
                    IncidentIoc::query(),
                    $incidentQuery,
                ),
                'total_evidence_items' => $this->countChildRecordsForVisibleIncidents(
                    IncidentEvidence::query(),
                    $incidentQuery,
                ),
                'total_response_actions' => $this->countChildRecordsForVisibleIncidents(
                    ResponseAction::query(),
                    $incidentQuery,
                ),
            ],
            'recent_incidents' => $this->recentIncidents($incidentQuery),
            'incidents_by_status' => $this->incidentsByStatus($incidentQuery),
            'incidents_by_severity' => $this->incidentsByTaxonomy(
                $incidentQuery,
                'severity_levels',
                'severity_level_id',
            ),
            'incidents_by_priority' => $this->incidentsByTaxonomy(
                $incidentQuery,
                'priority_levels',
                'priority_level_id',
            ),
            'analyst_workload' => $this->canViewAnalystWorkload($user)
                ? $this->analystWorkload()
                : collect(),
        ];
    }

    /**
     * Incidents visible to the user for dashboard metrics.
     */
    private function visibleIncidentQuery(User $user): Builder
    {
        $query = Incident::query();

        if ($user->hasAnyRole(['super-admin', 'security-manager'])) {
            return $query;
        }

        if ($user->hasRole('soc-analyst')) {
            return $query->where('current_assigned_to_id', $user->getKey());
        }

        return $query->where('reporter_id', $user->getKey());
    }

    /**
     * Dashboard scope label for the current user.
     */
    private function scopeLabel(User $user): string
    {
        if ($user->hasAnyRole(['super-admin', 'security-manager'])) {
            return 'Organization-wide incident operations';
        }

        if ($user->hasRole('soc-analyst')) {
            return 'Incidents currently assigned to you';
        }

        return 'Incidents reported by you';
    }

    /**
     * Determine whether operational analyst workload should be visible.
     */
    private function canViewAnalystWorkload(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'security-manager'])
            || $user->hasPermission('incident.assign');
    }

    /**
     * Incident statuses considered resolved/non-active.
     *
     * @return list<string>
     */
    private function resolvedStatuses(): array
    {
        return ['resolved', 'closed'];
    }

    /**
     * Count child records attached to incidents in the user's dashboard scope.
     */
    private function countChildRecordsForVisibleIncidents(Builder $childQuery, Builder $incidentQuery): int
    {
        return $childQuery
            ->whereIn('incident_id', (clone $incidentQuery)->select('incidents.id'))
            ->count();
    }

    /**
     * Recently reported incidents for the dashboard table.
     */
    private function recentIncidents(Builder $incidentQuery): Collection
    {
        return (clone $incidentQuery)
            ->with(['reporter', 'currentAssignee', 'severity', 'priority'])
            ->latestReported()
            ->limit(5)
            ->get();
    }

    /**
     * Count visible incidents by status.
     */
    private function incidentsByStatus(Builder $incidentQuery): Collection
    {
        return (clone $incidentQuery)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (Incident $incident): array => [
                'key' => $incident->status,
                'label' => str($incident->status)->replace('_', ' ')->title()->toString(),
                'total' => (int) $incident->total,
            ]);
    }

    /**
     * Count visible incidents by severity or priority taxonomy.
     */
    private function incidentsByTaxonomy(Builder $incidentQuery, string $table, string $foreignKey): Collection
    {
        return (clone $incidentQuery)
            ->join($table, "incidents.{$foreignKey}", '=', "{$table}.id")
            ->select([
                "{$table}.name",
                "{$table}.slug",
                "{$table}.color",
                "{$table}.sort_order",
            ])
            ->selectRaw('COUNT(incidents.id) as total')
            ->groupBy("{$table}.id", "{$table}.name", "{$table}.slug", "{$table}.color", "{$table}.sort_order")
            ->orderBy("{$table}.sort_order")
            ->orderBy("{$table}.name")
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->slug,
                'label' => $row->name,
                'color' => $row->color,
                'total' => (int) $row->total,
            ]);
    }

    /**
     * Current analyst workload based on active assigned incidents.
     */
    private function analystWorkload(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('assignedIncidents')
            ->withCount([
                'assignedIncidents as assigned_incidents_count',
                'assignedIncidents as active_assigned_incidents_count' => fn (Builder $query) => $query
                    ->whereNotIn('status', $this->resolvedStatuses()),
            ])
            ->orderByDesc('active_assigned_incidents_count')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }
}
