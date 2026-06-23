@extends('layouts.app')

@section('title', 'Report Incident')

@section('page-actions')
    <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Back to Incidents</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the incident report form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border rounded-2 p-4">
        <div class="mb-4">
            <h2 class="h5 mb-1">New Incident Report</h2>
            <p class="text-secondary mb-0">Capture the initial facts for security triage and follow-up.</p>
        </div>

        <form method="POST" action="{{ route('incidents.store') }}" class="row g-3">
            @csrf

            <div class="col-12">
                <label for="title" class="form-label">Title</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    value="{{ old('title') }}"
                    class="form-control @error('title') is-invalid @enderror"
                    maxlength="255"
                    required
                >
                @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="incident_category_id" class="form-label">Category</label>
                <select
                    id="incident_category_id"
                    name="incident_category_id"
                    class="form-select @error('incident_category_id') is-invalid @enderror"
                    required
                >
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('incident_category_id') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('incident_category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="severity_level_id" class="form-label">Severity</label>
                <select
                    id="severity_level_id"
                    name="severity_level_id"
                    class="form-select @error('severity_level_id') is-invalid @enderror"
                    required
                >
                    <option value="">Select severity</option>
                    @foreach ($severityLevels as $severityLevel)
                        <option value="{{ $severityLevel->id }}" @selected((string) old('severity_level_id') === (string) $severityLevel->id)>
                            {{ $severityLevel->name }}
                        </option>
                    @endforeach
                </select>
                @error('severity_level_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="priority_level_id" class="form-label">Priority</label>
                <select
                    id="priority_level_id"
                    name="priority_level_id"
                    class="form-select @error('priority_level_id') is-invalid @enderror"
                    required
                >
                    <option value="">Select priority</option>
                    @foreach ($priorityLevels as $priorityLevel)
                        <option value="{{ $priorityLevel->id }}" @selected((string) old('priority_level_id') === (string) $priorityLevel->id)>
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
                    value="{{ old('affected_system') }}"
                    class="form-control @error('affected_system') is-invalid @enderror"
                    maxlength="255"
                >
                @error('affected_system')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="occurred_at" class="form-label">Occurred At</label>
                <input
                    id="occurred_at"
                    name="occurred_at"
                    type="datetime-local"
                    value="{{ old('occurred_at') }}"
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
                    value="{{ old('detected_at') }}"
                    class="form-control @error('detected_at') is-invalid @enderror"
                >
                @error('detected_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-control @error('description') is-invalid @enderror"
                    rows="5"
                    required
                >{{ old('description') }}</textarea>
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
                >{{ old('impact_summary') }}</textarea>
                @error('impact_summary')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Submit Incident</button>
            </div>
        </form>
    </div>
@endsection
