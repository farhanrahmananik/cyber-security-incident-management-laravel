@extends('layouts.app')

@section('title', 'Incidents')

@section('page-actions')
    @can('incident.create')
        <a href="{{ route('incidents.create') }}" class="btn btn-primary">Report Incident</a>
    @endcan
@endsection

@section('content')
    <div class="bg-white border rounded-2 p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Incident Reports</h2>
                <p class="text-secondary mb-0">Submitted cyber security incidents visible to your role.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table id="incidents-table" class="table table-striped align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Incident #</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Severity</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reporter</th>
                        <th>Detected</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr>
                            <td><code>{{ $incident->incident_number }}</code></td>
                            <td class="fw-semibold">
                                <a href="{{ route('incidents.show', $incident) }}" class="link-dark">
                                    {{ $incident->title }}
                                </a>
                            </td>
                            <td>{{ $incident->category?->name ?? 'Not set' }}</td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ $incident->severity?->name ?? 'Not set' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ $incident->priority?->name ?? 'Not set' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge text-bg-info">
                                    {{ str($incident->status)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td>{{ $incident->reporter?->name ?? 'Unknown' }}</td>
                            <td>{{ $incident->detected_at?->format('Y-m-d H:i') ?? 'Not set' }}</td>
                            <td>{{ $incident->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex justify-content-end gap-2">
                                    <a href="{{ route('incidents.show', $incident) }}" class="btn btn-outline-secondary btn-sm">
                                        View
                                    </a>

                                    @can('incident.update')
                                        <a href="{{ route('incidents.edit', $incident) }}" class="btn btn-outline-primary btn-sm">
                                            Edit
                                        </a>
                                    @endcan

                                    @can('incident.delete')
                                        <form
                                            method="POST"
                                            action="{{ route('incidents.destroy', $incident) }}"
                                            onsubmit="return confirm('Delete this incident record?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-secondary py-4">
                                No incidents have been reported yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
