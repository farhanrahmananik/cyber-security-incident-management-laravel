<?php

namespace App\Services\Report;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SecurityReportService
{
    /**
     * Build security report data from existing incident records.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters = []): array
    {
        $incidentQuery = $this->incidentQuery($user, $filters);

        return [
            'filters' => $filters,
            'summary' => [
                'total_incidents' => (clone $incidentQuery)->count(),
                'open_incidents' => (clone $incidentQuery)
                    ->whereNotIn('incidents.status', $this->closedStatuses())
                    ->count(),
                'closed_incidents' => (clone $incidentQuery)
                    ->whereIn('incidents.status', $this->closedStatuses())
                    ->count(),
                'critical_incidents' => (clone $incidentQuery)
                    ->whereHas('severity', function (Builder $query): void {
                        $query->where(function (Builder $query): void {
                            $query->where('slug', 'critical')
                                ->orWhere('name', 'Critical');
                        });
                    })
                    ->count(),
            ],
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
            'incidents_by_category' => $this->incidentsByTaxonomy(
                $incidentQuery,
                'incident_categories',
                'incident_category_id',
            ),
            'analyst_workload' => $this->analystWorkload($incidentQuery),
            'recent_incidents' => $this->recentIncidents($incidentQuery),
            'filter_options' => $this->filterOptions(),
        ];
    }

    /**
     * Build the visible and filtered incident query shared by report views and exports.
     *
     * @param  array<string, mixed>  $filters
     */
    public function incidentQuery(User $user, array $filters = []): Builder
    {
        return $this->filteredIncidentQuery($this->visibleIncidentQuery($user), $filters);
    }

    /**
     * Incidents visible to the user for security report metrics.
     */
    private function visibleIncidentQuery(User $user): Builder
    {
        $query = Incident::query();

        if ($user->hasAnyRole(['super-admin', 'security-manager'])) {
            return $query;
        }

        if ($user->hasRole('soc-analyst')) {
            return $query->where('incidents.current_assigned_to_id', $user->getKey());
        }

        return $query->where('incidents.reporter_id', $user->getKey());
    }

    /**
     * Apply validated filters to the visible incident query.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filteredIncidentQuery(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom): void {
                $query->whereDate('incidents.created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo): void {
                $query->whereDate('incidents.created_at', '<=', $dateTo);
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('incidents.status', $status);
            })
            ->when($filters['severity_id'] ?? null, function (Builder $query, int|string $severityId): void {
                $query->where('incidents.severity_level_id', $severityId);
            })
            ->when($filters['priority_id'] ?? null, function (Builder $query, int|string $priorityId): void {
                $query->where('incidents.priority_level_id', $priorityId);
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, int|string $categoryId): void {
                $query->where('incidents.incident_category_id', $categoryId);
            })
            ->when($filters['assigned_to_id'] ?? null, function (Builder $query, int|string $assignedToId): void {
                $query->where('incidents.current_assigned_to_id', $assignedToId);
            });
    }

    /**
     * Statuses treated as closed for summary reporting.
     *
     * @return list<string>
     */
    private function closedStatuses(): array
    {
        return ['resolved', 'closed'];
    }

    /**
     * Count filtered incidents by status.
     */
    private function incidentsByStatus(Builder $incidentQuery): Collection
    {
        return (clone $incidentQuery)
            ->select('incidents.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('incidents.status')
            ->orderBy('incidents.status')
            ->get()
            ->map(fn (Incident $incident): array => [
                'key' => $incident->status,
                'label' => $this->humanLabel($incident->status),
                'total' => (int) $incident->total,
            ]);
    }

    /**
     * Count filtered incidents by a lookup taxonomy table.
     */
    private function incidentsByTaxonomy(Builder $incidentQuery, string $table, string $foreignKey): Collection
    {
        return (clone $incidentQuery)
            ->join($table, "incidents.{$foreignKey}", '=', "{$table}.id")
            ->select([
                "{$table}.name",
                "{$table}.slug",
                "{$table}.sort_order",
            ])
            ->selectRaw('COUNT(incidents.id) as total')
            ->groupBy("{$table}.id", "{$table}.name", "{$table}.slug", "{$table}.sort_order")
            ->orderBy("{$table}.sort_order")
            ->orderBy("{$table}.name")
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->slug,
                'label' => $row->name,
                'total' => (int) $row->total,
            ]);
    }

    /**
     * Analyst workload for filtered currently assigned incidents.
     */
    private function analystWorkload(Builder $incidentQuery): Collection
    {
        $workload = (clone $incidentQuery)
            ->whereNotNull('incidents.current_assigned_to_id')
            ->select('incidents.current_assigned_to_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('incidents.current_assigned_to_id')
            ->orderByDesc('total')
            ->get();

        $users = User::query()
            ->whereIn('id', $workload->pluck('current_assigned_to_id')->all())
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        return $workload
            ->map(fn ($row): array => [
                'user' => $users->get($row->current_assigned_to_id),
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row): bool => $row['user'] !== null)
            ->values();
    }

    /**
     * Recent incidents matching the filtered reporting scope.
     */
    private function recentIncidents(Builder $incidentQuery): Collection
    {
        return (clone $incidentQuery)
            ->with(['reporter', 'currentAssignee', 'category', 'severity', 'priority'])
            ->orderByDesc('incidents.created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Options used by the filter form.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => collect([
                'reported',
                'triaged',
                'assigned',
                'investigating',
                'contained',
                'resolved',
                'closed',
            ])->map(fn (string $status): array => [
                'value' => $status,
                'label' => $this->humanLabel($status),
            ]),
            'severities' => SeverityLevel::query()->active()->ordered()->get(),
            'priorities' => PriorityLevel::query()->active()->ordered()->get(),
            'categories' => IncidentCategory::query()->active()->ordered()->get(),
            'analysts' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', function (Builder $query): void {
                    $query->whereIn('slug', ['soc-analyst', 'security-manager'])
                        ->where('roles.is_active', true);
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Convert a stored slug/status value to a user-facing label.
     */
    private function humanLabel(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
