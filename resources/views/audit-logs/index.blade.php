@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    @php
        $filters = $filters ?? [];

        $valuePreview = function (?array $values): string {
            if ($values === null || $values === []) {
                return 'None';
            }

            return \Illuminate\Support\Str::limit(
                json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'Unavailable',
                180,
            );
        };
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="audit-page-card audit-filter-card bg-white border rounded-2 p-4">
                <div class="audit-card-header d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="audit-card-icon" aria-hidden="true">
                            <i class="ti ti-filter"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1">Audit Logs</h2>
                            <p class="text-secondary mb-0">
                                Read-only security activity history for accountability, investigation, and review.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="keyword" class="form-label">Keyword</label>
                        <input
                            id="keyword"
                            type="search"
                            name="keyword"
                            class="form-control @error('keyword') is-invalid @enderror"
                            value="{{ old('keyword', $filters['keyword'] ?? '') }}"
                            placeholder="Search event, actor, or model"
                        >
                        @error('keyword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="event" class="form-label">Event</label>
                        <input
                            id="event"
                            type="text"
                            name="event"
                            class="form-control @error('event') is-invalid @enderror"
                            value="{{ old('event', $filters['event'] ?? '') }}"
                            placeholder="incident.created"
                        >
                        @error('event')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="user_id" class="form-label">Actor User ID</label>
                        <input
                            id="user_id"
                            type="number"
                            min="1"
                            name="user_id"
                            class="form-control @error('user_id') is-invalid @enderror"
                            value="{{ old('user_id', $filters['user_id'] ?? '') }}"
                        >
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="auditable_type" class="form-label">Auditable Type</label>
                        <input
                            id="auditable_type"
                            type="text"
                            name="auditable_type"
                            class="form-control @error('auditable_type') is-invalid @enderror"
                            value="{{ old('auditable_type', $filters['auditable_type'] ?? '') }}"
                            placeholder="App\Models\Incident"
                        >
                        @error('auditable_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="auditable_id" class="form-label">Auditable ID</label>
                        <input
                            id="auditable_id"
                            type="number"
                            min="1"
                            name="auditable_id"
                            class="form-control @error('auditable_id') is-invalid @enderror"
                            value="{{ old('auditable_id', $filters['auditable_id'] ?? '') }}"
                        >
                        @error('auditable_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

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

                    <div class="col-md-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-filter me-1" aria-hidden="true"></i>
                            Filter
                        </button>
                        <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="audit-page-card bg-white border rounded-2 p-4">
                <div class="audit-card-header d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="audit-card-icon" aria-hidden="true">
                            <i class="ti ti-clipboard-list"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1">Security Activity</h2>
                            <p class="text-secondary mb-0">
                                Audit entries are append-only and cannot be edited or deleted from this interface.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="audit-table-shell table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Actor</th>
                                <th>Auditable</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                                <th>Changes Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($auditLogs as $auditLog)
                                @php
                                    $oldValues = $auditLog->old_values;
                                    $newValues = $auditLog->new_values;
                                    $changedFields = collect($oldValues ?? [])
                                        ->keys()
                                        ->merge(collect($newValues ?? [])->keys())
                                        ->unique()
                                        ->values();
                                @endphp

                                <tr>
                                    <td class="text-nowrap">
                                        {{ $auditLog->created_at?->format('Y-m-d H:i') }}
                                    </td>
                                    <td>
                                        <span class="audit-event-badge" data-audit-event="{{ $auditLog->event }}">
                                            {{ $auditLog->event }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($auditLog->user)
                                            <div class="fw-semibold">{{ $auditLog->user->name }}</div>
                                            <div class="text-secondary small">{{ $auditLog->user->email }}</div>
                                        @else
                                            <span class="text-secondary">System</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($auditLog->auditable_type || $auditLog->auditable_id)
                                            <div class="audit-meta-grid">
                                                <div class="small text-break">
                                                    <i class="ti ti-database-search me-1" aria-hidden="true"></i>
                                                    {{ $auditLog->auditable_type ?? 'Unknown' }}
                                                </div>
                                                <div class="text-secondary small">ID: {{ $auditLog->auditable_id ?? 'N/A' }}</div>
                                            </div>
                                        @else
                                            <span class="text-secondary">Not linked</span>
                                        @endif
                                    </td>
                                    <td>{{ $auditLog->ip_address ?? 'N/A' }}</td>
                                    <td class="text-break">
                                        {{ $auditLog->user_agent ? \Illuminate\Support\Str::limit($auditLog->user_agent, 80) : 'N/A' }}
                                    </td>
                                    <td>
                                        @if ($changedFields->isNotEmpty())
                                            <div class="mb-1">
                                                <span class="text-secondary small">Fields:</span>
                                                {{ $changedFields->implode(', ') }}
                                            </div>
                                        @else
                                            <span class="text-secondary">No value changes recorded.</span>
                                        @endif

                                        <details class="small mt-1">
                                            <summary class="audit-details-summary text-secondary">Value preview</summary>
                                            <div class="mt-2 audit-meta-grid">
                                                <div>
                                                    <span class="fw-semibold">Old:</span>
                                                    <code class="audit-json-block">{{ $valuePreview($oldValues) }}</code>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold">New:</span>
                                                    <code class="audit-json-block">{{ $valuePreview($newValues) }}</code>
                                                </div>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-5">
                                        <span class="setup-empty-state">
                                            <span class="setup-empty-icon" aria-hidden="true">
                                                <i class="ti ti-user-search"></i>
                                            </span>
                                            <span>
                                                <span class="setup-empty-title d-block">No audit logs match the selected filters.</span>
                                                <span class="setup-empty-copy d-block">Adjust the filters to review a broader activity window.</span>
                                            </span>
                                        </span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 audit-pagination">
                    {{ $auditLogs->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
