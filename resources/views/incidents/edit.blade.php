@extends('layouts.app')

@section('title', 'Edit Incident')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('incidents.show', $incident) }}" class="btn btn-outline-secondary">
            <i class="ti ti-eye me-1" aria-hidden="true"></i>
            View Incident
        </a>
        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>
            Back to Incidents
        </a>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the incident form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="incident-panel incident-page-card incident-form-shell incident-form-section bg-white border rounded-2 p-4">
        <div class="incident-form-hero mb-4">
            <span class="incident-panel-icon" aria-hidden="true">
                <i class="ti ti-clipboard-edit"></i>
            </span>
            <div>
                <p class="text-secondary mb-1">{{ $incident->incident_number }}</p>
                <h2 class="h5 mb-1">Edit Incident</h2>
                <p class="text-secondary mb-0">Update the incident report fields available in this foundation step.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('incidents.update', $incident) }}" class="incident-form-grid row g-3">
            @csrf
            @method('PUT')

            <div class="col-12">
                <label for="title" class="form-label">Title <span class="incident-required">Required</span></label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    value="{{ old('title', $incident->title) }}"
                    class="form-control @error('title') is-invalid @enderror"
                    maxlength="255"
                    required
                >
                @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="incident_category_id" class="form-label">Category <span class="incident-required">Required</span></label>
                <select
                    id="incident_category_id"
                    name="incident_category_id"
                    class="form-select @error('incident_category_id') is-invalid @enderror"
                    required
                >
                    @foreach ($categories as $category)
                        <option
                            value="{{ $category->id }}"
                            @selected((string) old('incident_category_id', $incident->incident_category_id) === (string) $category->id)
                        >
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('incident_category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="severity_level_id" class="form-label">Severity <span class="incident-required">Required</span></label>
                <select
                    id="severity_level_id"
                    name="severity_level_id"
                    class="form-select @error('severity_level_id') is-invalid @enderror"
                    required
                >
                    @foreach ($severityLevels as $severityLevel)
                        <option
                            value="{{ $severityLevel->id }}"
                            @selected((string) old('severity_level_id', $incident->severity_level_id) === (string) $severityLevel->id)
                        >
                            {{ $severityLevel->name }}
                        </option>
                    @endforeach
                </select>
                @error('severity_level_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="priority_level_id" class="form-label">Priority <span class="incident-required">Required</span></label>
                <select
                    id="priority_level_id"
                    name="priority_level_id"
                    class="form-select @error('priority_level_id') is-invalid @enderror"
                    required
                >
                    @foreach ($priorityLevels as $priorityLevel)
                        <option
                            value="{{ $priorityLevel->id }}"
                            @selected((string) old('priority_level_id', $incident->priority_level_id) === (string) $priorityLevel->id)
                        >
                            {{ $priorityLevel->name }}
                        </option>
                    @endforeach
                </select>
                @error('priority_level_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="affected_system" class="form-label">Affected System</label>
                <input
                    id="affected_system"
                    name="affected_system"
                    type="text"
                    value="{{ old('affected_system', $incident->affected_system) }}"
                    class="form-control @error('affected_system') is-invalid @enderror"
                    maxlength="255"
                >
                @error('affected_system')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="incident-helper">Hostname, application, network segment, endpoint, or service if known.</div>
            </div>

            <div class="col-md-3">
                <label for="occurred_at" class="form-label">Occurred At</label>
                <input
                    id="occurred_at"
                    name="occurred_at"
                    type="datetime-local"
                    value="{{ old('occurred_at', $incident->occurred_at?->format('Y-m-d\TH:i')) }}"
                    class="form-control @error('occurred_at') is-invalid @enderror"
                >
                @error('occurred_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="detected_at" class="form-label">Detected At</label>
                <input
                    id="detected_at"
                    name="detected_at"
                    type="datetime-local"
                    value="{{ old('detected_at', $incident->detected_at?->format('Y-m-d\TH:i')) }}"
                    class="form-control @error('detected_at') is-invalid @enderror"
                >
                @error('detected_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label">Status</label>
                <input
                    type="text"
                    value="{{ str($incident->status)->replace('_', ' ')->title() }}"
                    class="form-control"
                    disabled
                >
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Description <span class="incident-required">Required</span></label>
                <textarea
                    id="description"
                    name="description"
                    class="form-control @error('description') is-invalid @enderror"
                    rows="5"
                    required
                >{{ old('description', $incident->description) }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="impact_summary" class="form-label">Impact Summary</label>
                <textarea
                    id="impact_summary"
                    name="impact_summary"
                    class="form-control @error('impact_summary') is-invalid @enderror"
                    rows="3"
                >{{ old('impact_summary', $incident->impact_summary) }}</textarea>
                @error('impact_summary')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <div class="incident-action-row d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>
                        Save Changes
                    </button>
                    <a href="{{ route('incidents.show', $incident) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
@endsection
