@extends('layouts.app')

@section('title', 'Incident Details')

@section('page-actions')
    <div class="d-flex gap-2">
        @can('incident.update')
            <a href="{{ route('incidents.edit', $incident) }}" class="btn btn-outline-primary">Edit</a>
        @endcan
        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Back to Incidents</a>
    </div>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white border rounded-2 p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                    <div>
                        <p class="text-secondary mb-1">{{ $incident->incident_number }}</p>
                        <h2 class="h4 mb-2">{{ $incident->title }}</h2>
                        <span class="badge text-bg-info">
                            {{ str($incident->status)->replace('_', ' ')->title() }}
                        </span>
                    </div>
                    <div class="text-md-end">
                        <p class="text-secondary mb-1">Reporter</p>
                        <p class="fw-semibold mb-0">{{ $incident->reporter?->name ?? 'Unknown' }}</p>
                        <p class="text-secondary mb-0">{{ $incident->reporter?->email }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Category</p>
                <p class="fw-semibold mb-0">{{ $incident->category?->name ?? 'Not set' }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Severity</p>
                <p class="fw-semibold mb-0">{{ $incident->severity?->name ?? 'Not set' }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Priority</p>
                <p class="fw-semibold mb-0">{{ $incident->priority?->name ?? 'Not set' }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Affected System</p>
                <p class="fw-semibold mb-0">{{ $incident->affected_system ?: 'Not provided' }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Occurred At</p>
                <p class="fw-semibold mb-0">{{ $incident->occurred_at?->format('Y-m-d H:i') ?? 'Not provided' }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="text-secondary mb-1">Detected At</p>
                <p class="fw-semibold mb-0">{{ $incident->detected_at?->format('Y-m-d H:i') ?? 'Not provided' }}</p>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Description</h2>
                <p class="mb-0" style="white-space: pre-line;">{{ $incident->description }}</p>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="bg-white border rounded-2 p-4 h-100">
                <h2 class="h5 mb-3">Impact Summary</h2>
                <p class="mb-0" style="white-space: pre-line;">{{ $incident->impact_summary ?: 'Not provided' }}</p>
            </div>
        </div>
    </div>
@endsection
