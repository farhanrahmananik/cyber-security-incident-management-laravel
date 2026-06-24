@extends('layouts.app')

@section('title', 'Security Reports')

@section('content')
    @php
        $filters = $reportData['filters'];
        $summary = $reportData['summary'];
        $options = $reportData['filter_options'];
        $summaryCards = [
            ['key' => 'total_incidents', 'label' => 'Total Incidents', 'description' => 'Incidents matching the selected filters.'],
            ['key' => 'open_incidents', 'label' => 'Open Incidents', 'description' => 'Incidents not resolved or closed.'],
            ['key' => 'closed_incidents', 'label' => 'Closed Incidents', 'description' => 'Resolved or closed incidents.'],
            ['key' => 'critical_incidents', 'label' => 'Critical Incidents', 'description' => 'Incidents classified as critical severity.'],
        ];
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white border rounded-2 p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Security Reports</h2>
                        <p class="text-secondary mb-0">
                            Summarized incident data for operational review and security reporting.
                        </p>
                    </div>
                </div>

                <form method="GET" action="{{ route('reports.security.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input
                            id="date_from"
                            type="date"
                            name="date_from"
                            class="form-control @error('date_from') is-invalid @enderror"
                            value="{{ old('date_from', $filters['date_from'] ?? '') }}"
                        >
                        @error('date_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input
                            id="date_to"
                            type="date"
                            name="date_to"
                            class="form-control @error('date_to') is-invalid @enderror"
                            value="{{ old('date_to', $filters['date_to'] ?? '') }}"
                        >
                        @error('date_to')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="">All statuses</option>
                            @foreach ($options['statuses'] as $status)
                                <option value="{{ $status['value'] }}" @selected(old('status', $filters['status'] ?? '') === $status['value'])>
                                    {{ $status['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="severity_id" class="form-label">Severity</label>
                        <select id="severity_id" name="severity_id" class="form-select @error('severity_id') is-invalid @enderror">
                            <option value="">All severities</option>
                            @foreach ($options['severities'] as $severity)
                                <option value="{{ $severity->id }}" @selected((string) old('severity_id', $filters['severity_id'] ?? '') === (string) $severity->id)>
                                    {{ $severity->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('severity_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="priority_id" class="form-label">Priority</label>
                        <select id="priority_id" name="priority_id" class="form-select @error('priority_id') is-invalid @enderror">
                            <option value="">All priorities</option>
                            @foreach ($options['priorities'] as $priority)
                                <option value="{{ $priority->id }}" @selected((string) old('priority_id', $filters['priority_id'] ?? '') === (string) $priority->id)>
                                    {{ $priority->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('priority_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">All categories</option>
                            @foreach ($options['categories'] as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $filters['category_id'] ?? '') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="assigned_to_id" class="form-label">Assigned Analyst</label>
                        <select id="assigned_to_id" name="assigned_to_id" class="form-select @error('assigned_to_id') is-invalid @enderror">
                            <option value="">All analysts</option>
                            @foreach ($options['analysts'] as $analyst)
                                <option value="{{ $analyst->id }}" @selected((string) old('assigned_to_id', $filters['assigned_to_id'] ?? '') === (string) $analyst->id)>
                                    {{ $analyst->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('reports.security.index') }}" class="btn btn-outline-secondary">Reset</a>
                        <a href="{{ route('reports.security.export', request()->query()) }}" class="btn btn-outline-primary">
                            Export CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($summaryCards as $summaryCard)
            <div class="col-md-6 col-xl-3">
                <div class="bg-white border rounded-2 p-4 h-100">
                    <p class="text-secondary mb-1">{{ $summaryCard['label'] }}</p>
                    <p class="display-6 fw-semibold mb-2">
                        <span data-report-summary="{{ $summaryCard['key'] }}">
                            {{ number_format($summary[$summaryCard['key']]) }}
                        </span>
                    </p>
                    <p class="text-secondary small mb-0">{{ $summaryCard['description'] }}</p>
                </div>
            </div>
        @endforeach

        <div class="col-lg-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Status</h2>
                @include('reports.security.partials.breakdown-table', ['rows' => $reportData['incidents_by_status'], 'emptyMessage' => 'No status data available.'])
            </div>
        </div>

        <div class="col-lg-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Severity</h2>
                @include('reports.security.partials.breakdown-table', ['rows' => $reportData['incidents_by_severity'], 'emptyMessage' => 'No severity data available.'])
            </div>
        </div>

        <div class="col-lg-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Priority</h2>
                @include('reports.security.partials.breakdown-table', ['rows' => $reportData['incidents_by_priority'], 'emptyMessage' => 'No priority data available.'])
            </div>
        </div>

        <div class="col-lg-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Incidents by Category</h2>
                @include('reports.security.partials.breakdown-table', ['rows' => $reportData['incidents_by_category'], 'emptyMessage' => 'No category data available.'])
            </div>
        </div>

        <div class="col-xl-5">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Analyst Workload</h2>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Analyst</th>
                                <th class="text-end">Assigned Incidents</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData['analyst_workload'] as $workload)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $workload['user']->name }}</div>
                                        <div class="text-secondary small">{{ $workload['user']->email }}</div>
                                    </td>
                                    <td class="text-end">{{ $workload['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-secondary">
                                        No assigned analyst workload is available for these filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Recent Incidents</h2>

                <div class="table-responsive">
                    <table class="table table-striped align-middle data-table mb-0">
                        <thead>
                            <tr>
                                <th>Incident #</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Severity</th>
                                <th>Priority</th>
                                <th>Assigned</th>
                                <th>Reported</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData['recent_incidents'] as $incident)
                                <tr>
                                    <td>
                                        @can('incident.view')
                                            <a href="{{ route('incidents.show', $incident) }}">
                                                {{ $incident->incident_number }}
                                            </a>
                                        @else
                                            {{ $incident->incident_number }}
                                        @endcan
                                    </td>
                                    <td>{{ $incident->title }}</td>
                                    <td>
                                        <span class="badge text-bg-info">
                                            {{ str($incident->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td>{{ $incident->severity?->name ?? 'Not set' }}</td>
                                    <td>{{ $incident->priority?->name ?? 'Not set' }}</td>
                                    <td>{{ $incident->currentAssignee?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $incident->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-secondary">
                                        No recent incidents match the selected filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
