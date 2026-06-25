@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $dashboardUser = auth()->user();
        $metrics = $dashboardData['metrics'];
        $metricCards = [
            ['key' => 'total_incidents', 'label' => 'Total Incidents', 'description' => 'Visible incidents in your dashboard scope.', 'icon' => 'ti ti-shield-search'],
            ['key' => 'active_incidents', 'label' => 'Active Incidents', 'description' => 'Incidents not resolved or closed.', 'icon' => 'ti ti-activity'],
            ['key' => 'unassigned_incidents', 'label' => 'Unassigned Incidents', 'description' => 'Incidents without a current analyst.', 'icon' => 'ti ti-user-question'],
            ['key' => 'resolved_incidents', 'label' => 'Resolved Incidents', 'description' => 'Resolved or closed incidents.', 'icon' => 'ti ti-circle-check'],
            ['key' => 'total_investigation_notes', 'label' => 'Investigation Notes', 'description' => 'SOC notes linked to visible incidents.', 'icon' => 'ti ti-notes'],
            ['key' => 'total_iocs', 'label' => 'IOCs', 'description' => 'Indicators recorded for visible incidents.', 'icon' => 'ti ti-radar'],
            ['key' => 'total_evidence_items', 'label' => 'Evidence Items', 'description' => 'Evidence metadata linked to visible incidents.', 'icon' => 'ti ti-file-search'],
            ['key' => 'total_response_actions', 'label' => 'Response Actions', 'description' => 'Response work tracked for visible incidents.', 'icon' => 'ti ti-heartbeat'],
        ];
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white border rounded-2 p-4 p-md-5">
                <p class="text-secondary mb-1">{{ $dashboardData['scope_label'] }}</p>
                <h2 class="h3 mb-3">Welcome, {{ $dashboardUser->name }}</h2>
                <p class="mb-0">
                    Dashboard metrics are calculated from real incident records and scoped to your current role.
                </p>

                @can('dashboard.view')
                    <div class="border-top mt-4 pt-4">
                        <p class="fw-semibold mb-1">Authorization active</p>
                        <p class="text-secondary mb-0">The dashboard permission gate is allowing this protected page.</p>

                        @can('incident.assign')
                            <p class="text-secondary mb-0 mt-2">Incident assignment access available.</p>
                        @endcan
                    </div>
                @endcan
            </div>
        </div>

        @foreach ($metricCards as $metricCard)
            <div class="col-md-6 col-xl-3">
                <div class="dashboard-metric-card bg-white border rounded-2 p-4 h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <p class="text-secondary mb-0">{{ $metricCard['label'] }}</p>
                        <span class="dashboard-metric-icon" aria-hidden="true">
                            <i class="{{ $metricCard['icon'] }}"></i>
                        </span>
                    </div>
                    <p
                        class="display-6 fw-semibold mb-2"
                        data-dashboard-metric="{{ $metricCard['key'] }}"
                    >
                        {{ number_format($metrics[$metricCard['key']]) }}
                    </p>
                    <p class="text-secondary small mb-0">{{ $metricCard['description'] }}</p>
                </div>
            </div>
        @endforeach

        <div class="col-xl-8">
            <div class="bg-white border rounded-2 p-4 h-100">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Incidents</h2>
                        <p class="text-secondary mb-0">Latest incidents matching your dashboard scope.</p>
                    </div>
                    @can('incident.view')
                        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">
                            View Incidents
                        </a>
                    @endcan
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Incident</th>
                                <th>Status</th>
                                <th>Severity</th>
                                <th>Priority</th>
                                <th>Reporter</th>
                                <th>Reported</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dashboardData['recent_incidents'] as $incident)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            @can('incident.view')
                                                <a href="{{ route('incidents.show', $incident) }}">
                                                    {{ $incident->incident_number }}
                                                </a>
                                            @else
                                                {{ $incident->incident_number }}
                                            @endcan
                                        </div>
                                        <div class="text-secondary small">{{ $incident->title }}</div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-info">
                                            {{ str($incident->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td>{{ $incident->severity?->name ?? 'Not set' }}</td>
                                    <td>{{ $incident->priority?->name ?? 'Not set' }}</td>
                                    <td>{{ $incident->reporter?->name ?? 'Unknown' }}</td>
                                    <td>{{ $incident->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-secondary">
                                        No recent incidents match your current dashboard scope.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Status</h2>

                @forelse ($dashboardData['incidents_by_status'] as $status)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span>{{ $status['label'] }}</span>
                        <span class="badge text-bg-light border">{{ $status['total'] }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No status data available.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-6">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Severity</h2>

                @forelse ($dashboardData['incidents_by_severity'] as $severity)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span>{{ $severity['label'] }}</span>
                        <span class="badge text-bg-light border">{{ $severity['total'] }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No severity data available.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-6">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Priority</h2>

                @forelse ($dashboardData['incidents_by_priority'] as $priority)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span>{{ $priority['label'] }}</span>
                        <span class="badge text-bg-light border">{{ $priority['total'] }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No priority data available.</p>
                @endforelse
            </div>
        </div>

        @if ($dashboardData['can_view_analyst_workload'])
            <div class="col-12">
                <div class="bg-white border rounded-2 p-4">
                    <h2 class="h5 mb-3">Analyst Workload</h2>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Analyst</th>
                                    <th>Active Assigned Incidents</th>
                                    <th>Total Assigned Incidents</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboardData['analyst_workload'] as $analyst)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $analyst->name }}</div>
                                            <div class="text-secondary small">{{ $analyst->email }}</div>
                                        </td>
                                        <td>{{ $analyst->active_assigned_incidents_count }}</td>
                                        <td>{{ $analyst->assigned_incidents_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-secondary">
                                            No assigned analyst workload is available yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
@endsection
