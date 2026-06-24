@extends('layouts.app')

@section('title', 'Role & Permission Management')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the role management form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('role.create')
        <div class="bg-white border rounded-2 p-4 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Create Role</h2>
                    <p class="text-secondary mb-0">
                        Create an application role and assign existing system-defined permissions.
                    </p>
                </div>
            </div>

            @php
                $selectedCreatePermissions = collect(old('permission_ids', []))
                    ->map(fn ($permissionId) => (int) $permissionId)
                    ->all();
            @endphp

            <form method="POST" action="{{ route('roles.store') }}" class="row g-3">
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

                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input
                        id="slug"
                        name="slug"
                        type="text"
                        value="{{ old('slug') }}"
                        class="form-control @error('slug') is-invalid @enderror"
                        maxlength="255"
                        placeholder="Generated from name if blank"
                    >
                    @error('slug')
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
                        maxlength="1000"
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
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                        <div>
                            <label class="form-label mb-1">Permissions</label>
                            <p class="text-secondary small mb-0">
                                Permissions are seeded system capabilities and cannot be edited here.
                            </p>
                        </div>
                    </div>

                    @forelse ($permissionsByGroup as $groupName => $permissions)
                        <div class="border rounded-2 p-3 mb-3">
                            <h3 class="h6 text-uppercase text-secondary mb-3">
                                {{ str($groupName)->replace(['-', '_'], ' ')->title() }}
                            </h3>
                            <div class="row g-2">
                                @foreach ($permissions as $permission)
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-check">
                                            <input
                                                id="create_permission_{{ $permission->id }}"
                                                name="permission_ids[]"
                                                type="checkbox"
                                                value="{{ $permission->id }}"
                                                class="form-check-input"
                                                @checked(in_array((int) $permission->id, $selectedCreatePermissions, true))
                                            >
                                            <label
                                                for="create_permission_{{ $permission->id }}"
                                                class="form-check-label"
                                            >
                                                {{ $permission->name }}
                                                <span class="text-secondary small d-block">{{ $permission->slug }}</span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-warning mb-0" role="alert">
                            No active permissions are available for assignment.
                        </div>
                    @endforelse

                    @error('permission_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                    @error('permission_ids.*')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    @endcan

    <div class="bg-white border rounded-2 p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Roles</h2>
                <p class="text-secondary mb-0">
                    Manage application roles and their assigned permissions.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table id="roles-table" class="table table-striped align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="fw-semibold">
                                {{ $role->name }}
                                @if ($role->slug === 'super-admin')
                                    <span class="badge text-bg-warning ms-1">Protected</span>
                                @endif
                            </td>
                            <td><code>{{ $role->slug }}</code></td>
                            <td>{{ $role->description ?: 'Not provided' }}</td>
                            <td>
                                @if ($role->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions->count() }}</td>
                            <td class="text-end">
                                @if ($role->slug === 'super-admin')
                                    <span class="btn btn-outline-secondary btn-sm disabled">System Role</span>
                                @else
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                        @can('role.update')
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editRole{{ $role->id }}"
                                            >
                                                Edit
                                            </button>

                                            @if (! $role->is_active)
                                                <form method="POST" action="{{ route('roles.activate', $role) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-success btn-sm"
                                                        onclick="return confirm('Activate this role?')"
                                                    >
                                                        Activate
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan

                                        @can('role.delete')
                                            @if ($role->is_active)
                                                <form method="POST" action="{{ route('roles.deactivate', $role) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-danger btn-sm"
                                                        onclick="return confirm('Deactivate this role?')"
                                                    >
                                                        Deactivate
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                No roles have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
            <div class="mt-3">
                {{ $roles->links() }}
            </div>
        @endif
    </div>

    @can('role.update')
        @foreach ($roles as $role)
            @continue($role->slug === 'super-admin')

            @php
                $selectedPermissionIds = collect(old('permission_ids', $role->permissions->pluck('id')->all()))
                    ->map(fn ($permissionId) => (int) $permissionId)
                    ->all();
            @endphp

            <div
                class="modal fade"
                id="editRole{{ $role->id }}"
                tabindex="-1"
                aria-labelledby="editRoleLabel{{ $role->id }}"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('roles.update', $role) }}">
                            @csrf
                            @method('PATCH')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editRoleLabel{{ $role->id }}">
                                    Edit Role
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name_{{ $role->id }}" class="form-label">Name</label>
                                        <input
                                            id="name_{{ $role->id }}"
                                            name="name"
                                            type="text"
                                            value="{{ old('name', $role->name) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label for="slug_{{ $role->id }}" class="form-label">Slug</label>
                                        <input
                                            id="slug_{{ $role->id }}"
                                            name="slug"
                                            type="text"
                                            value="{{ old('slug', $role->slug) }}"
                                            class="form-control"
                                            maxlength="255"
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="description_{{ $role->id }}" class="form-label">Description</label>
                                        <textarea
                                            id="description_{{ $role->id }}"
                                            name="description"
                                            class="form-control"
                                            rows="3"
                                            maxlength="1000"
                                        >{{ old('description', $role->description) }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                                            <div>
                                                <label class="form-label mb-1">Permissions</label>
                                                <p class="text-secondary small mb-0">
                                                    Assign existing active permissions to this role.
                                                </p>
                                            </div>
                                        </div>

                                        @forelse ($permissionsByGroup as $groupName => $permissions)
                                            <div class="border rounded-2 p-3 mb-3">
                                                <h3 class="h6 text-uppercase text-secondary mb-3">
                                                    {{ str($groupName)->replace(['-', '_'], ' ')->title() }}
                                                </h3>
                                                <div class="row g-2">
                                                    @foreach ($permissions as $permission)
                                                        <div class="col-sm-6 col-lg-4">
                                                            <div class="form-check">
                                                                <input
                                                                    id="role_{{ $role->id }}_permission_{{ $permission->id }}"
                                                                    name="permission_ids[]"
                                                                    type="checkbox"
                                                                    value="{{ $permission->id }}"
                                                                    class="form-check-input"
                                                                    @checked(in_array((int) $permission->id, $selectedPermissionIds, true))
                                                                >
                                                                <label
                                                                    for="role_{{ $role->id }}_permission_{{ $permission->id }}"
                                                                    class="form-check-label"
                                                                >
                                                                    {{ $permission->name }}
                                                                    <span class="text-secondary small d-block">
                                                                        {{ $permission->slug }}
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <div class="alert alert-warning mb-0" role="alert">
                                                No active permissions are available for assignment.
                                            </div>
                                        @endforelse
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
