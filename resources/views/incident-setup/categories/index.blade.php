@extends('layouts.app')

@section('title', 'Incident Categories')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the category form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('incident-category.manage')
        <div class="bg-white border rounded-2 p-4 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Create Incident Category</h2>
                    <p class="text-secondary mb-0">Add a controlled category used to classify reported incidents.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('incident-categories.store') }}" class="row g-3">
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
                        value="{{ old('color', '#0d6efd') }}"
                        class="form-control @error('color') is-invalid @enderror"
                        maxlength="50"
                        placeholder="#0d6efd"
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
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    @endcan

    <div class="bg-white border rounded-2 p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Incident Categories</h2>
                <p class="text-secondary mb-0">Master data for incident classification.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table id="incident-categories-table" class="table table-striped align-middle data-table mb-0">
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
                    @forelse ($incidentCategories as $incidentCategory)
                        <tr>
                            <td class="fw-semibold">{{ $incidentCategory->name }}</td>
                            <td><code>{{ $incidentCategory->slug }}</code></td>
                            <td>{{ $incidentCategory->description ?: 'Not provided' }}</td>
                            <td>
                                @if ($incidentCategory->color)
                                    <span class="badge text-bg-light border">{{ $incidentCategory->color }}</span>
                                @else
                                    <span class="text-secondary">Not set</span>
                                @endif
                            </td>
                            <td>{{ $incidentCategory->sort_order }}</td>
                            <td>
                                @if ($incidentCategory->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('incident-category.manage')
                                    <div class="d-inline-flex justify-content-end gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editIncidentCategory{{ $incidentCategory->id }}"
                                        >
                                            Edit
                                        </button>

                                        @if ($incidentCategory->is_active)
                                            <form
                                                method="POST"
                                                action="{{ route('incident-categories.destroy', $incidentCategory) }}"
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
                                No incident categories have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('incident-category.manage')
        @foreach ($incidentCategories as $incidentCategory)
            <div
                class="modal fade"
                id="editIncidentCategory{{ $incidentCategory->id }}"
                tabindex="-1"
                aria-labelledby="editIncidentCategoryLabel{{ $incidentCategory->id }}"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('incident-categories.update', $incidentCategory) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editIncidentCategoryLabel{{ $incidentCategory->id }}">
                                    Edit Incident Category
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name_{{ $incidentCategory->id }}" class="form-label">Name</label>
                                        <input
                                            id="name_{{ $incidentCategory->id }}"
                                            name="name"
                                            type="text"
                                            value="{{ old('name', $incidentCategory->name) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="color_{{ $incidentCategory->id }}" class="form-label">Color</label>
                                        <input
                                            id="color_{{ $incidentCategory->id }}"
                                            name="color"
                                            type="text"
                                            value="{{ old('color', $incidentCategory->color) }}"
                                            class="form-control"
                                            maxlength="50"
                                            placeholder="#0d6efd"
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label for="sort_order_{{ $incidentCategory->id }}" class="form-label">Sort Order</label>
                                        <input
                                            id="sort_order_{{ $incidentCategory->id }}"
                                            name="sort_order"
                                            type="number"
                                            value="{{ old('sort_order', $incidentCategory->sort_order) }}"
                                            class="form-control"
                                            min="0"
                                            max="9999"
                                            required
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="description_{{ $incidentCategory->id }}" class="form-label">
                                            Description
                                        </label>
                                        <textarea
                                            id="description_{{ $incidentCategory->id }}"
                                            name="description"
                                            class="form-control"
                                            rows="3"
                                            maxlength="5000"
                                        >{{ old('description', $incidentCategory->description) }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <input type="hidden" name="is_active" value="0">
                                        <div class="form-check">
                                            <input
                                                id="is_active_{{ $incidentCategory->id }}"
                                                name="is_active"
                                                type="checkbox"
                                                value="1"
                                                class="form-check-input"
                                                @checked((bool) old('is_active', $incidentCategory->is_active))
                                            >
                                            <label for="is_active_{{ $incidentCategory->id }}" class="form-check-label">
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
