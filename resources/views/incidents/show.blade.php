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
    @php
        $iocTypeLabels = [
            'ip_address' => 'IP Address',
            'domain' => 'Domain',
            'url' => 'URL',
            'file_hash' => 'File Hash',
            'email_address' => 'Email Address',
            'malware_filename' => 'Malware Filename',
            'process_name' => 'Process Name',
            'registry_key' => 'Registry Key',
            'other' => 'Other',
        ];

        $iocConfidenceLabels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];

        $responseActionTypeLabels = [
            'containment' => 'Containment',
            'eradication' => 'Eradication',
            'recovery' => 'Recovery',
            'communication' => 'Communication',
            'monitoring' => 'Monitoring',
            'lessons_learned' => 'Lessons Learned',
            'other' => 'Other',
        ];

        $responseActionStatusLabels = [
            'planned' => 'Planned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $responseActionStatusBadgeClasses = [
            'planned' => 'text-bg-secondary',
            'in_progress' => 'text-bg-info',
            'completed' => 'text-bg-success',
            'cancelled' => 'text-bg-warning',
        ];

        $formatEvidenceFileSize = static function (?int $bytes): string {
            $bytes ??= 0;

            if ($bytes < 1024) {
                return $bytes.' B';
            }

            $units = ['KB', 'MB', 'GB', 'TB'];
            $value = $bytes / 1024;
            $unitIndex = 0;

            while ($value >= 1024 && $unitIndex < count($units) - 1) {
                $value /= 1024;
                $unitIndex++;
            }

            return rtrim(rtrim(number_format($value, 1), '0'), '.').' '.$units[$unitIndex];
        };
    @endphp

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

        @can('investigation-note.view')
            <div class="col-12">
                <div class="bg-white border rounded-2 p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                        <div class="flex-fill">
                            <h2 class="h5 mb-1">Investigation Notes</h2>
                            <p class="text-secondary mb-4">
                                Internal SOC notes for triage, analysis, and response tracking.
                            </p>

                            @forelse ($investigationNotes as $investigationNote)
                                <div class="border rounded-2 p-3 mb-3">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                                        <div>
                                            <p class="fw-semibold mb-1">
                                                {{ $investigationNote->author?->name ?? 'Unknown author' }}
                                            </p>
                                            <p class="text-secondary mb-0">
                                                {{ $investigationNote->created_at?->format('Y-m-d H:i') }}
                                            </p>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            @can('investigation-note.update')
                                                <details>
                                                    <summary class="btn btn-outline-primary btn-sm">
                                                        Edit
                                                    </summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('incidents.investigation-notes.update', [$incident, $investigationNote]) }}"
                                                        class="mt-3"
                                                    >
                                                        @csrf
                                                        @method('PATCH')

                                                        <label for="note_{{ $investigationNote->id }}" class="form-label">
                                                            Note
                                                        </label>
                                                        <textarea
                                                            id="note_{{ $investigationNote->id }}"
                                                            name="note"
                                                            class="form-control @error('note') is-invalid @enderror"
                                                            rows="4"
                                                            maxlength="5000"
                                                            required
                                                        >{{ old('note', $investigationNote->note) }}</textarea>
                                                        @error('note')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror

                                                        <button type="submit" class="btn btn-primary btn-sm mt-3">
                                                            Save Note
                                                        </button>
                                                    </form>
                                                </details>
                                            @endcan

                                            @can('investigation-note.delete')
                                                <form
                                                    method="POST"
                                                    action="{{ route('incidents.investigation-notes.destroy', [$incident, $investigationNote]) }}"
                                                    onsubmit="return confirm('Delete this investigation note?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>

                                    <p class="mb-0" style="white-space: pre-line;">{{ $investigationNote->note }}</p>
                                </div>
                            @empty
                                <div class="border rounded-2 p-3 text-secondary">
                                    No investigation notes have been recorded yet.
                                </div>
                            @endforelse
                        </div>

                        @can('investigation-note.create')
                            <div class="border rounded-2 p-3 flex-fill" style="max-width: 420px;">
                                <h3 class="h6 mb-3">Add Investigation Note</h3>

                                <form method="POST" action="{{ route('incidents.investigation-notes.store', $incident) }}">
                                    @csrf

                                    <div class="mb-3">
                                        <label for="note" class="form-label">Note</label>
                                        <textarea
                                            id="note"
                                            name="note"
                                            class="form-control @error('note') is-invalid @enderror"
                                            rows="5"
                                            maxlength="5000"
                                            required
                                        >{{ old('note') }}</textarea>
                                        @error('note')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Add Note
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        @endcan

        @can('ioc.view')
            <div class="col-12">
                <div class="bg-white border rounded-2 p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                        <div class="flex-fill">
                            <h2 class="h5 mb-1">Indicators of Compromise</h2>
                            <p class="text-secondary mb-4">
                                Incident-linked observables for threat analysis, enrichment, and response tracking.
                            </p>

                            @forelse ($iocs as $incidentIoc)
                                <div class="border rounded-2 p-3 mb-3">
                                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
                                        <div class="flex-fill">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <span class="badge text-bg-secondary">
                                                    {{ $iocTypeLabels[$incidentIoc->type] ?? str($incidentIoc->type)->replace('_', ' ')->title() }}
                                                </span>

                                                @if ($incidentIoc->confidence)
                                                    <span class="badge text-bg-info">
                                                        {{ $iocConfidenceLabels[$incidentIoc->confidence] ?? str($incidentIoc->confidence)->title() }} Confidence
                                                    </span>
                                                @else
                                                    <span class="badge text-bg-light">Confidence Not Set</span>
                                                @endif
                                            </div>

                                            <code class="d-block text-break mb-2">{{ $incidentIoc->value }}</code>

                                            <div class="row g-2 text-secondary small">
                                                <div class="col-md-6">
                                                    First seen:
                                                    <span class="text-body">
                                                        {{ $incidentIoc->first_seen_at?->format('Y-m-d H:i') ?? 'Not provided' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Last seen:
                                                    <span class="text-body">
                                                        {{ $incidentIoc->last_seen_at?->format('Y-m-d H:i') ?? 'Not provided' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Recorded by:
                                                    <span class="text-body">
                                                        {{ $incidentIoc->createdBy?->name ?? 'Unknown user' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Recorded at:
                                                    <span class="text-body">
                                                        {{ $incidentIoc->created_at?->format('Y-m-d H:i') }}
                                                    </span>
                                                </div>
                                            </div>

                                            @if ($incidentIoc->description)
                                                <p class="mb-0 mt-3" style="white-space: pre-line;">{{ $incidentIoc->description }}</p>
                                            @endif
                                        </div>

                                        @can('ioc.manage')
                                            <div class="d-flex flex-wrap align-items-start gap-2">
                                                <details>
                                                    <summary class="btn btn-outline-primary btn-sm">
                                                        Edit
                                                    </summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('incidents.iocs.update', [$incident, $incidentIoc]) }}"
                                                        class="mt-3 border rounded-2 p-3"
                                                        style="min-width: min(100%, 420px);"
                                                    >
                                                        @csrf
                                                        @method('PATCH')

                                                        <div class="mb-3">
                                                            <label for="ioc_type_{{ $incidentIoc->id }}" class="form-label">Type</label>
                                                            <select
                                                                id="ioc_type_{{ $incidentIoc->id }}"
                                                                name="type"
                                                                class="form-select @error('type') is-invalid @enderror"
                                                                required
                                                            >
                                                                @foreach ($iocTypeLabels as $iocTypeValue => $iocTypeLabel)
                                                                    <option
                                                                        value="{{ $iocTypeValue }}"
                                                                        @selected(old('type', $incidentIoc->type) === $iocTypeValue)
                                                                    >
                                                                        {{ $iocTypeLabel }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('type')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="ioc_value_{{ $incidentIoc->id }}" class="form-label">Value</label>
                                                            <textarea
                                                                id="ioc_value_{{ $incidentIoc->id }}"
                                                                name="value"
                                                                class="form-control @error('value') is-invalid @enderror"
                                                                rows="3"
                                                                maxlength="2048"
                                                                required
                                                            >{{ old('value', $incidentIoc->value) }}</textarea>
                                                            @error('value')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="ioc_confidence_{{ $incidentIoc->id }}" class="form-label">Confidence</label>
                                                            <select
                                                                id="ioc_confidence_{{ $incidentIoc->id }}"
                                                                name="confidence"
                                                                class="form-select @error('confidence') is-invalid @enderror"
                                                            >
                                                                <option value="">Select confidence</option>
                                                                @foreach ($iocConfidenceLabels as $confidenceValue => $confidenceLabel)
                                                                    <option
                                                                        value="{{ $confidenceValue }}"
                                                                        @selected(old('confidence', $incidentIoc->confidence) === $confidenceValue)
                                                                    >
                                                                        {{ $confidenceLabel }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('confidence')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label for="ioc_first_seen_at_{{ $incidentIoc->id }}" class="form-label">First Seen</label>
                                                                <input
                                                                    id="ioc_first_seen_at_{{ $incidentIoc->id }}"
                                                                    type="datetime-local"
                                                                    name="first_seen_at"
                                                                    class="form-control @error('first_seen_at') is-invalid @enderror"
                                                                    value="{{ old('first_seen_at', $incidentIoc->first_seen_at?->format('Y-m-d\TH:i')) }}"
                                                                >
                                                                @error('first_seen_at')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label for="ioc_last_seen_at_{{ $incidentIoc->id }}" class="form-label">Last Seen</label>
                                                                <input
                                                                    id="ioc_last_seen_at_{{ $incidentIoc->id }}"
                                                                    type="datetime-local"
                                                                    name="last_seen_at"
                                                                    class="form-control @error('last_seen_at') is-invalid @enderror"
                                                                    value="{{ old('last_seen_at', $incidentIoc->last_seen_at?->format('Y-m-d\TH:i')) }}"
                                                                >
                                                                @error('last_seen_at')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>

                                                        <div class="mt-3">
                                                            <label for="ioc_description_{{ $incidentIoc->id }}" class="form-label">Description</label>
                                                            <textarea
                                                                id="ioc_description_{{ $incidentIoc->id }}"
                                                                name="description"
                                                                class="form-control @error('description') is-invalid @enderror"
                                                                rows="3"
                                                                maxlength="5000"
                                                            >{{ old('description', $incidentIoc->description) }}</textarea>
                                                            @error('description')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <button type="submit" class="btn btn-primary btn-sm mt-3">
                                                            Save IOC
                                                        </button>
                                                    </form>
                                                </details>

                                                <form
                                                    method="POST"
                                                    action="{{ route('incidents.iocs.destroy', [$incident, $incidentIoc]) }}"
                                                    onsubmit="return confirm('Delete this IOC?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        @endcan
                                    </div>
                                </div>
                            @empty
                                <div class="border rounded-2 p-3 text-secondary">
                                    No indicators of compromise recorded yet.
                                </div>
                            @endforelse
                        </div>

                        @can('ioc.manage')
                            <div class="border rounded-2 p-3 flex-fill" style="max-width: 420px;">
                                <h3 class="h6 mb-3">Add IOC</h3>

                                <form method="POST" action="{{ route('incidents.iocs.store', $incident) }}">
                                    @csrf

                                    <div class="mb-3">
                                        <label for="ioc_type" class="form-label">Type</label>
                                        <select
                                            id="ioc_type"
                                            name="type"
                                            class="form-select @error('type') is-invalid @enderror"
                                            required
                                        >
                                            <option value="">Select type</option>
                                            @foreach ($iocTypeLabels as $iocTypeValue => $iocTypeLabel)
                                                <option value="{{ $iocTypeValue }}" @selected(old('type') === $iocTypeValue)>
                                                    {{ $iocTypeLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="ioc_value" class="form-label">Value</label>
                                        <textarea
                                            id="ioc_value"
                                            name="value"
                                            class="form-control @error('value') is-invalid @enderror"
                                            rows="3"
                                            maxlength="2048"
                                            required
                                        >{{ old('value') }}</textarea>
                                        @error('value')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="ioc_confidence" class="form-label">Confidence</label>
                                        <select
                                            id="ioc_confidence"
                                            name="confidence"
                                            class="form-select @error('confidence') is-invalid @enderror"
                                        >
                                            <option value="">Select confidence</option>
                                            @foreach ($iocConfidenceLabels as $confidenceValue => $confidenceLabel)
                                                <option value="{{ $confidenceValue }}" @selected(old('confidence') === $confidenceValue)>
                                                    {{ $confidenceLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('confidence')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="ioc_first_seen_at" class="form-label">First Seen</label>
                                            <input
                                                id="ioc_first_seen_at"
                                                type="datetime-local"
                                                name="first_seen_at"
                                                class="form-control @error('first_seen_at') is-invalid @enderror"
                                                value="{{ old('first_seen_at') }}"
                                            >
                                            @error('first_seen_at')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="ioc_last_seen_at" class="form-label">Last Seen</label>
                                            <input
                                                id="ioc_last_seen_at"
                                                type="datetime-local"
                                                name="last_seen_at"
                                                class="form-control @error('last_seen_at') is-invalid @enderror"
                                                value="{{ old('last_seen_at') }}"
                                            >
                                            @error('last_seen_at')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mt-3 mb-3">
                                        <label for="ioc_description" class="form-label">Description</label>
                                        <textarea
                                            id="ioc_description"
                                            name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            rows="3"
                                            maxlength="5000"
                                        >{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Add IOC
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        @endcan

        @can('evidence.view')
            <div class="col-12">
                <div class="bg-white border rounded-2 p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                        <div class="flex-fill">
                            <h2 class="h5 mb-1">Evidence / Attachments</h2>
                            <p class="text-secondary mb-4">
                                Store incident-related files privately with metadata and SHA-256 integrity tracking.
                            </p>

                            @forelse ($evidences as $incidentEvidence)
                                <div class="border rounded-2 p-3 mb-3">
                                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                                        <div class="flex-fill">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h3 class="h6 mb-0">{{ $incidentEvidence->title }}</h3>
                                                <span class="badge text-bg-secondary">
                                                    {{ $incidentEvidence->mime_type ?: 'Unknown' }}
                                                </span>
                                                <span class="badge text-bg-light">
                                                    {{ $formatEvidenceFileSize($incidentEvidence->file_size) }}
                                                </span>
                                            </div>

                                            @if ($incidentEvidence->description)
                                                <p class="mb-3" style="white-space: pre-line;">{{ $incidentEvidence->description }}</p>
                                            @endif

                                            <div class="row g-2 text-secondary small mb-3">
                                                <div class="col-md-6">
                                                    Original filename:
                                                    <span class="text-body text-break">
                                                        {{ $incidentEvidence->original_filename }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Uploaded by:
                                                    <span class="text-body">
                                                        {{ $incidentEvidence->uploadedBy?->name ?? 'Unknown' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Uploaded at:
                                                    <span class="text-body">
                                                        {{ $incidentEvidence->created_at?->format('Y-m-d H:i') }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Integrity:
                                                    <span class="text-body">
                                                        SHA-256
                                                    </span>
                                                </div>
                                            </div>

                                            <code class="d-block text-break small mb-3">
                                                {{ $incidentEvidence->checksum_sha256 ?: 'Checksum not available' }}
                                            </code>

                                            <a
                                                href="{{ route('incidents.evidences.download', [$incident, $incidentEvidence]) }}"
                                                class="btn btn-outline-secondary btn-sm"
                                            >
                                                Download
                                            </a>
                                        </div>

                                        @can('evidence.manage')
                                            <div class="d-flex flex-wrap align-items-start gap-2">
                                                <details>
                                                    <summary class="btn btn-outline-primary btn-sm">
                                                        Edit
                                                    </summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('incidents.evidences.update', [$incident, $incidentEvidence]) }}"
                                                        class="mt-3 border rounded-2 p-3"
                                                        style="min-width: min(100%, 420px);"
                                                    >
                                                        @csrf
                                                        @method('PATCH')

                                                        <div class="mb-3">
                                                            <label for="evidence_title_{{ $incidentEvidence->id }}" class="form-label">Title</label>
                                                            <input
                                                                id="evidence_title_{{ $incidentEvidence->id }}"
                                                                type="text"
                                                                name="title"
                                                                class="form-control @error('title') is-invalid @enderror"
                                                                value="{{ old('title', $incidentEvidence->title) }}"
                                                                maxlength="255"
                                                                required
                                                            >
                                                            @error('title')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="evidence_description_{{ $incidentEvidence->id }}" class="form-label">Description</label>
                                                            <textarea
                                                                id="evidence_description_{{ $incidentEvidence->id }}"
                                                                name="description"
                                                                class="form-control @error('description') is-invalid @enderror"
                                                                rows="3"
                                                                maxlength="5000"
                                                            >{{ old('description', $incidentEvidence->description) }}</textarea>
                                                            @error('description')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            Update
                                                        </button>
                                                    </form>
                                                </details>

                                                <form
                                                    method="POST"
                                                    action="{{ route('incidents.evidences.destroy', [$incident, $incidentEvidence]) }}"
                                                    onsubmit="return confirm('Delete this evidence attachment?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        @endcan
                                    </div>
                                </div>
                            @empty
                                <div class="border rounded-2 p-3 text-secondary">
                                    No evidence attachments have been recorded yet.
                                </div>
                            @endforelse
                        </div>

                        @can('evidence.manage')
                            <div class="border rounded-2 p-3 flex-fill" style="max-width: 420px;">
                                <h3 class="h6 mb-3">Upload Evidence</h3>

                                <form
                                    method="POST"
                                    action="{{ route('incidents.evidences.store', $incident) }}"
                                    enctype="multipart/form-data"
                                >
                                    @csrf

                                    <div class="mb-3">
                                        <label for="evidence_title" class="form-label">Title</label>
                                        <input
                                            id="evidence_title"
                                            type="text"
                                            name="title"
                                            class="form-control @error('title') is-invalid @enderror"
                                            value="{{ old('title') }}"
                                            maxlength="255"
                                            required
                                        >
                                        @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="evidence_description" class="form-label">Description</label>
                                        <textarea
                                            id="evidence_description"
                                            name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            rows="3"
                                            maxlength="5000"
                                        >{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="evidence_file" class="form-label">Evidence File</label>
                                        <input
                                            id="evidence_file"
                                            type="file"
                                            name="evidence_file"
                                            class="form-control @error('evidence_file') is-invalid @enderror"
                                            required
                                        >
                                        @error('evidence_file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Upload Evidence
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        @endcan

        @can('response-action.view')
            <div class="col-12">
                <div class="bg-white border rounded-2 p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                        <div class="flex-fill">
                            <h2 class="h5 mb-1">Response Actions</h2>
                            <p class="text-secondary mb-4">
                                Track containment, eradication, recovery, communication, monitoring, and lessons learned work.
                            </p>

                            @forelse ($responseActions as $responseAction)
                                <div class="border rounded-2 p-3 mb-3">
                                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                                        <div class="flex-fill">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h3 class="h6 mb-0">{{ $responseAction->title }}</h3>
                                                <span class="badge text-bg-primary">
                                                    {{ $responseActionTypeLabels[$responseAction->action_type] ?? str($responseAction->action_type)->replace('_', ' ')->title() }}
                                                </span>
                                                <span class="badge {{ $responseActionStatusBadgeClasses[$responseAction->status] ?? 'text-bg-secondary' }}">
                                                    {{ $responseActionStatusLabels[$responseAction->status] ?? str($responseAction->status)->replace('_', ' ')->title() }}
                                                </span>
                                            </div>

                                            @if ($responseAction->description)
                                                <p class="mb-3" style="white-space: pre-line;">{{ $responseAction->description }}</p>
                                            @endif

                                            <div class="row g-2 text-secondary small">
                                                <div class="col-md-6">
                                                    Started:
                                                    <span class="text-body">
                                                        {{ $responseAction->started_at?->format('Y-m-d H:i') ?? 'Not started' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Completed:
                                                    <span class="text-body">
                                                        {{ $responseAction->completed_at?->format('Y-m-d H:i') ?? 'Not completed' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Performed by:
                                                    <span class="text-body">
                                                        {{ $responseAction->performedBy?->name ?? 'Unknown' }}
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    Updated:
                                                    <span class="text-body">
                                                        {{ $responseAction->updated_at?->format('Y-m-d H:i') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        @can('response-action.manage')
                                            <div class="d-flex flex-wrap align-items-start gap-2">
                                                <details>
                                                    <summary class="btn btn-outline-primary btn-sm">
                                                        Edit
                                                    </summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('incidents.response-actions.update', [$incident, $responseAction]) }}"
                                                        class="mt-3 border rounded-2 p-3"
                                                        style="min-width: min(100%, 420px);"
                                                    >
                                                        @csrf
                                                        @method('PATCH')

                                                        <div class="mb-3">
                                                            <label for="response_action_type_{{ $responseAction->id }}" class="form-label">Action Type</label>
                                                            <select
                                                                id="response_action_type_{{ $responseAction->id }}"
                                                                name="action_type"
                                                                class="form-select @error('action_type') is-invalid @enderror"
                                                                required
                                                            >
                                                                @foreach ($responseActionTypeLabels as $value => $label)
                                                                    <option value="{{ $value }}" @selected(old('action_type', $responseAction->action_type) === $value)>
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('action_type')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="response_action_status_{{ $responseAction->id }}" class="form-label">Status</label>
                                                            <select
                                                                id="response_action_status_{{ $responseAction->id }}"
                                                                name="status"
                                                                class="form-select @error('status') is-invalid @enderror"
                                                                required
                                                            >
                                                                @foreach ($responseActionStatusLabels as $value => $label)
                                                                    <option value="{{ $value }}" @selected(old('status', $responseAction->status) === $value)>
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('status')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="response_action_title_{{ $responseAction->id }}" class="form-label">Title</label>
                                                            <input
                                                                id="response_action_title_{{ $responseAction->id }}"
                                                                type="text"
                                                                name="title"
                                                                class="form-control @error('title') is-invalid @enderror"
                                                                value="{{ old('title', $responseAction->title) }}"
                                                                maxlength="255"
                                                                required
                                                            >
                                                            @error('title')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="response_action_description_{{ $responseAction->id }}" class="form-label">Description</label>
                                                            <textarea
                                                                id="response_action_description_{{ $responseAction->id }}"
                                                                name="description"
                                                                class="form-control @error('description') is-invalid @enderror"
                                                                rows="3"
                                                            >{{ old('description', $responseAction->description) }}</textarea>
                                                            @error('description')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <label for="response_action_started_at_{{ $responseAction->id }}" class="form-label">Started At</label>
                                                                <input
                                                                    id="response_action_started_at_{{ $responseAction->id }}"
                                                                    type="datetime-local"
                                                                    name="started_at"
                                                                    class="form-control @error('started_at') is-invalid @enderror"
                                                                    value="{{ old('started_at', $responseAction->started_at?->format('Y-m-d\TH:i')) }}"
                                                                >
                                                                @error('started_at')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="response_action_completed_at_{{ $responseAction->id }}" class="form-label">Completed At</label>
                                                                <input
                                                                    id="response_action_completed_at_{{ $responseAction->id }}"
                                                                    type="datetime-local"
                                                                    name="completed_at"
                                                                    class="form-control @error('completed_at') is-invalid @enderror"
                                                                    value="{{ old('completed_at', $responseAction->completed_at?->format('Y-m-d\TH:i')) }}"
                                                                >
                                                                @error('completed_at')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>

                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            Update
                                                        </button>
                                                    </form>
                                                </details>

                                                <form
                                                    method="POST"
                                                    action="{{ route('incidents.response-actions.destroy', [$incident, $responseAction]) }}"
                                                    onsubmit="return confirm('Delete this response action?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        @endcan
                                    </div>
                                </div>
                            @empty
                                <div class="border rounded-2 p-3 text-secondary">
                                    No response actions have been recorded yet.
                                </div>
                            @endforelse
                        </div>

                        @can('response-action.manage')
                            <div class="border rounded-2 p-3 flex-fill" style="max-width: 420px;">
                                <h3 class="h6 mb-3">Add Response Action</h3>

                                <form
                                    method="POST"
                                    action="{{ route('incidents.response-actions.store', $incident) }}"
                                >
                                    @csrf

                                    <div class="mb-3">
                                        <label for="response_action_type" class="form-label">Action Type</label>
                                        <select
                                            id="response_action_type"
                                            name="action_type"
                                            class="form-select @error('action_type') is-invalid @enderror"
                                            required
                                        >
                                            @foreach ($responseActionTypeLabels as $value => $label)
                                                <option value="{{ $value }}" @selected(old('action_type', 'containment') === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('action_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="response_action_status" class="form-label">Status</label>
                                        <select
                                            id="response_action_status"
                                            name="status"
                                            class="form-select @error('status') is-invalid @enderror"
                                            required
                                        >
                                            @foreach ($responseActionStatusLabels as $value => $label)
                                                <option value="{{ $value }}" @selected(old('status', 'planned') === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="response_action_title" class="form-label">Title</label>
                                        <input
                                            id="response_action_title"
                                            type="text"
                                            name="title"
                                            class="form-control @error('title') is-invalid @enderror"
                                            value="{{ old('title') }}"
                                            maxlength="255"
                                            required
                                        >
                                        @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="response_action_description" class="form-label">Description</label>
                                        <textarea
                                            id="response_action_description"
                                            name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            rows="3"
                                        >{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="response_action_started_at" class="form-label">Started At</label>
                                            <input
                                                id="response_action_started_at"
                                                type="datetime-local"
                                                name="started_at"
                                                class="form-control @error('started_at') is-invalid @enderror"
                                                value="{{ old('started_at') }}"
                                            >
                                            @error('started_at')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="response_action_completed_at" class="form-label">Completed At</label>
                                            <input
                                                id="response_action_completed_at"
                                                type="datetime-local"
                                                name="completed_at"
                                                class="form-control @error('completed_at') is-invalid @enderror"
                                                value="{{ old('completed_at') }}"
                                            >
                                            @error('completed_at')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Add Response Action
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        @endcan

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
