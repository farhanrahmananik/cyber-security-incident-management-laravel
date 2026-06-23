@extends('layouts.app')

@section('title', 'Severity Levels')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the severity level form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('severity-level.manage')
        <div class="bg-white border rounded-2 p-4 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Create Severity Level</h2>
                    <p class="text-secondary mb-0">Define impact levels used to classify incident urgency and risk.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('severity-levels.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label for="name" class="form-label">Name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror"
                        maxlength="255"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="color" class="form-label">Color</label>
                    <input
                        id="color"
                        name="color"
                        type="text"
                        value="{{ old('color', '#dc3545') }}"
                        class="form-control @error('color') is-invalid @enderror"
                        maxlength="50"
                        placeholder="#dc3545"
                    >
                    @error('color')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input
                        id="sort_order"
                        name="sort_order"
                        type="number"
                        value="{{ old('sort_order', 0) }}"
                        class="form-control @error('sort_order') is-invalid @enderror"
                        min="0"
                        max="9999"
                        required
                    >
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control @error('description') is-invalid @enderror"
                        rows="3"
                        maxlength="5000"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            class="form-check-input"
                            @checked((bool) old('is_active', true))
                        >
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Severity Level</button>
                </div>
            </form>
        </div>
    @endcan

    <div class="bg-white border rounded-2 p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Severity Levels</h2>
                <p class="text-secondary mb-0">Master data for incident impact classification.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table id="severity-levels-table" class="table table-striped align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($severityLevels as $severityLevel)
                        <tr>
                            <td class="fw-semibold">{{ $severityLevel->name }}</td>
                            <td><code>{{ $severityLevel->slug }}</code></td>
                            <td>{{ $severityLevel->description ?: 'Not provided' }}</td>
                            <td>
                                @if ($severityLevel->color)
                                    <span class="badge text-bg-light border">{{ $severityLevel->color }}</span>
                                @else
                                    <span class="text-secondary">Not set</span>
                                @endif
                            </td>
                            <td>{{ $severityLevel->sort_order }}</td>
                            <td>
                                @if ($severityLevel->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('severity-level.manage')
                                    <div class="d-inline-flex justify-content-end gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editSeverityLevel{{ $severityLevel->id }}"
                                        >
                                            Edit
                                        </button>

                                        @if ($severityLevel->is_active)
                                            <form
                                                method="POST"
                                                action="{{ route('severity-levels.destroy', $severityLevel) }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Deactivate
                                                </button>
                                            </form>
                                        @else
                                            <span class="btn btn-outline-secondary btn-sm disabled">Inactive</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-secondary">No actions</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                No severity levels have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('severity-level.manage')
        @foreach ($severityLevels as $severityLevel)
            <div
                class="modal fade"
                id="editSeverityLevel{{ $severityLevel->id }}"
                tabindex="-1"
                aria-labelledby="editSeverityLevelLabel{{ $severityLevel->id }}"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('severity-levels.update', $severityLevel) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editSeverityLevelLabel{{ $severityLevel->id }}">
                                    Edit Severity Level
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name_{{ $severityLevel->id }}" class="form-label">Name</label>
                                        <input
                                            id="name_{{ $severityLevel->id }}"
                                            name="name"
                                            type="text"
                                            value="{{ old('name', $severityLevel->name) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="color_{{ $severityLevel->id }}" class="form-label">Color</label>
                                        <input
                                            id="color_{{ $severityLevel->id }}"
                                            name="color"
                                            type="text"
                                            value="{{ old('color', $severityLevel->color) }}"
                                            class="form-control"
                                            maxlength="50"
                                            placeholder="#dc3545"
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="sort_order_{{ $severityLevel->id }}" class="form-label">Sort Order</label>
                                        <input
                                            id="sort_order_{{ $severityLevel->id }}"
                                            name="sort_order"
                                            type="number"
                                            value="{{ old('sort_order', $severityLevel->sort_order) }}"
                                            class="form-control"
                                            min="0"
                                            max="9999"
                                            required
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="description_{{ $severityLevel->id }}" class="form-label">
                                            Description
                                        </label>
                                        <textarea
                                            id="description_{{ $severityLevel->id }}"
                                            name="description"
                                            class="form-control"
                                            rows="3"
                                            maxlength="5000"
                                        >{{ old('description', $severityLevel->description) }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <input type="hidden" name="is_active" value="0">
                                        <div class="form-check">
                                            <input
                                                id="is_active_{{ $severityLevel->id }}"
                                                name="is_active"
                                                type="checkbox"
                                                value="1"
                                                class="form-check-input"
                                                @checked((bool) old('is_active', $severityLevel->is_active))
                                            >
                                            <label for="is_active_{{ $severityLevel->id }}" class="form-check-label">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endcan
@endsection
