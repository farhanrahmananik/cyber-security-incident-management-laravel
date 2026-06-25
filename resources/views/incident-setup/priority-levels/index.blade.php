@extends('layouts.app')

@section('title', 'Priority Levels')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the priority level form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('priority-level.manage')
        <div class="setup-page-card setup-create-card bg-white border rounded-2 p-4 mb-4">
            <div class="setup-card-header d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="setup-card-icon" aria-hidden="true">
                        <i class="ti ti-flag-3"></i>
                    </span>
                    <div>
                        <h2 class="h5 mb-1">Create Priority Level</h2>
                        <p class="text-secondary mb-0">Define operational response priority for incident handling.</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('priority-levels.store') }}" class="setup-form row g-3">
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
                        value="{{ old('color', '#fd7e14') }}"
                        class="form-control @error('color') is-invalid @enderror"
                        maxlength="50"
                        placeholder="#fd7e14"
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
                    <button type="submit" class="btn btn-primary">Create Priority Level</button>
                </div>
            </form>
        </div>
    @endcan

    <div class="setup-page-card bg-white border rounded-2 p-4">
        <div class="setup-card-header d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-start gap-3">
                <span class="setup-card-icon" aria-hidden="true">
                    <i class="ti ti-target-arrow"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">Priority Levels</h2>
                    <p class="text-secondary mb-0">Master data for incident response prioritization.</p>
                </div>
            </div>
        </div>

        <div class="setup-table-shell table-responsive">
            <table id="priority-levels-table" class="setup-table table table-striped align-middle data-table mb-0">
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
                    @forelse ($priorityLevels as $priorityLevel)
                        @php
                            $prioritySlug = strtolower((string) $priorityLevel->slug);
                            $priorityBadgeClass = match ($prioritySlug) {
                                'low' => 'priority-badge-low',
                                'medium' => 'priority-badge-medium',
                                'high' => 'priority-badge-high',
                                'urgent', 'critical' => 'priority-badge-urgent',
                                default => 'priority-badge-default',
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold">
                                <span class="priority-badge {{ $priorityBadgeClass }}">
                                    {{ $priorityLevel->name }}
                                </span>
                            </td>
                            <td><code class="setup-code">{{ $priorityLevel->slug }}</code></td>
                            <td>{{ $priorityLevel->description ?: 'Not provided' }}</td>
                            <td>
                                @if ($priorityLevel->color)
                                    <span class="setup-meta-badge badge text-bg-light border">{{ $priorityLevel->color }}</span>
                                @else
                                    <span class="text-secondary">Not set</span>
                                @endif
                            </td>
                            <td>{{ $priorityLevel->sort_order }}</td>
                            <td>
                                @if ($priorityLevel->is_active)
                                    <span class="status-badge status-badge-active">Active</span>
                                @else
                                    <span class="status-badge status-badge-inactive">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('priority-level.manage')
                                    <div class="setup-action-group d-inline-flex justify-content-end gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPriorityLevel{{ $priorityLevel->id }}"
                                        >
                                            <i class="ti ti-pencil me-1" aria-hidden="true"></i>
                                            Edit
                                        </button>

                                        @if ($priorityLevel->is_active)
                                            <form
                                                method="POST"
                                                action="{{ route('priority-levels.destroy', $priorityLevel) }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="ti ti-circle-off me-1" aria-hidden="true"></i>
                                                    Deactivate
                                                </button>
                                            </form>
                                        @else
                                            <span class="btn btn-outline-secondary btn-sm disabled">
                                                <i class="ti ti-lock me-1" aria-hidden="true"></i>
                                                Inactive
                                            </span>
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
                                <span class="setup-empty-state">
                                    <span class="setup-empty-icon" aria-hidden="true">
                                        <i class="ti ti-flag-plus"></i>
                                    </span>
                                    <span>
                                        <span class="setup-empty-title d-block">No priority levels have been created yet.</span>
                                        <span class="setup-empty-copy d-block">Create priority levels to guide response urgency.</span>
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('priority-level.manage')
        @foreach ($priorityLevels as $priorityLevel)
            <div
                class="modal fade setup-modal"
                id="editPriorityLevel{{ $priorityLevel->id }}"
                tabindex="-1"
                aria-labelledby="editPriorityLevelLabel{{ $priorityLevel->id }}"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('priority-levels.update', $priorityLevel) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editPriorityLevelLabel{{ $priorityLevel->id }}">
                                    Edit Priority Level
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name_{{ $priorityLevel->id }}" class="form-label">Name</label>
                                        <input
                                            id="name_{{ $priorityLevel->id }}"
                                            name="name"
                                            type="text"
                                            value="{{ old('name', $priorityLevel->name) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="color_{{ $priorityLevel->id }}" class="form-label">Color</label>
                                        <input
                                            id="color_{{ $priorityLevel->id }}"
                                            name="color"
                                            type="text"
                                            value="{{ old('color', $priorityLevel->color) }}"
                                            class="form-control"
                                            maxlength="50"
                                            placeholder="#fd7e14"
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="sort_order_{{ $priorityLevel->id }}" class="form-label">Sort Order</label>
                                        <input
                                            id="sort_order_{{ $priorityLevel->id }}"
                                            name="sort_order"
                                            type="number"
                                            value="{{ old('sort_order', $priorityLevel->sort_order) }}"
                                            class="form-control"
                                            min="0"
                                            max="9999"
                                            required
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="description_{{ $priorityLevel->id }}" class="form-label">
                                            Description
                                        </label>
                                        <textarea
                                            id="description_{{ $priorityLevel->id }}"
                                            name="description"
                                            class="form-control"
                                            rows="3"
                                            maxlength="5000"
                                        >{{ old('description', $priorityLevel->description) }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <input type="hidden" name="is_active" value="0">
                                        <div class="form-check">
                                            <input
                                                id="is_active_{{ $priorityLevel->id }}"
                                                name="is_active"
                                                type="checkbox"
                                                value="1"
                                                class="form-check-input"
                                                @checked((bool) old('is_active', $priorityLevel->is_active))
                                            >
                                            <label for="is_active_{{ $priorityLevel->id }}" class="form-check-label">
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
