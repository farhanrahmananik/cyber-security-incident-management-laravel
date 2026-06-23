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

        <div class="col-12">
            <div class="bg-white border rounded-2 p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                    <div class="flex-fill">
                        <h2 class="h5 mb-3">Assignment</h2>

                        <div class="mb-4">
                            <p class="text-secondary mb-1">Current Assignee</p>
                            @if ($incident->currentAssignee)
                                <p class="fw-semibold mb-0">{{ $incident->currentAssignee->name }}</p>
                                <p class="text-secondary mb-0">{{ $incident->currentAssignee->email }}</p>
                            @else
                                <p class="fw-semibold mb-0">Unassigned</p>
                            @endif
                        </div>

                        <h3 class="h6 mb-3">Assignment History</h3>
                        @forelse ($assignmentHistory as $assignment)
                            <div class="border rounded-2 p-3 mb-3">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                    <div>
                                        <p class="fw-semibold mb-1">
                                            {{ $assignment->assignedTo?->name ?? 'Unknown user' }}
                                        </p>
                                        <p class="text-secondary mb-0">
                                            Assigned by {{ $assignment->assignedBy?->name ?? 'Unknown user' }}
                                        </p>
                                    </div>
                                    <div class="text-md-end text-secondary">
                                        {{ $assignment->assigned_at?->format('Y-m-d H:i') }}
                                    </div>
                                </div>

                                @if ($assignment->notes)
                                    <p class="mb-0 mt-3" style="white-space: pre-line;">{{ $assignment->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="border rounded-2 p-3 text-secondary">
                                No assignment history has been recorded yet.
                            </div>
                        @endforelse
                    </div>

                    @can('incident.assign')
                        <div class="border rounded-2 p-3 flex-fill" style="max-width: 420px;">
                            <h3 class="h6 mb-3">
                                {{ $incident->currentAssignee ? 'Reassign Analyst' : 'Assign Analyst' }}
                            </h3>

                            <form method="POST" action="{{ route('incidents.assign', $incident) }}">
                                @csrf

                                <div class="mb-3">
                                    <label for="assigned_to_id" class="form-label">Analyst</label>
                                    <select
                                        id="assigned_to_id"
                                        name="assigned_to_id"
                                        class="form-select @error('assigned_to_id') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Select analyst</option>
                                        @foreach ($assignableUsers as $assignableUser)
                                            <option
                                                value="{{ $assignableUser->id }}"
                                                @selected((string) old('assigned_to_id') === (string) $assignableUser->id)
                                            >
                                                {{ $assignableUser->name }} ({{ $assignableUser->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_to_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        class="form-control @error('notes') is-invalid @enderror"
                                        rows="3"
                                        maxlength="1000"
                                    >{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    {{ $incident->currentAssignee ? 'Reassign Analyst' : 'Assign Analyst' }}
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
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
