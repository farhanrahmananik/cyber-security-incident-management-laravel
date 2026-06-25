@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Please review the user management form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('user.create')
        <div class="management-page-card management-create-card bg-white border rounded-2 p-4 mb-4">
            <div class="management-card-header d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="setup-card-icon" aria-hidden="true">
                        <i class="ti ti-user-plus"></i>
                    </span>
                    <div>
                        <h2 class="h5 mb-1">Create User</h2>
                        <p class="text-secondary mb-0">
                            Add an active application user and assign existing RBAC roles.
                        </p>
                    </div>
                </div>
            </div>

            @if ($roles->isEmpty())
                <div class="alert alert-warning mb-0" role="alert">
                    No active roles are available. Create or activate roles before adding users.
                </div>
            @else
                @php
                    $selectedCreateRoles = collect(old('role_ids', []))
                        ->map(fn ($roleId) => (int) $roleId)
                        ->all();
                @endphp

                <form method="POST" action="{{ route('users.store') }}" class="management-form row g-3">
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
                        <label for="email" class="form-label">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            maxlength="255"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            minlength="12"
                            required
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirm Password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="form-control"
                            minlength="12"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">Roles</label>
                        <div class="row g-2">
                            @foreach ($roles as $role)
                                <div class="col-sm-6 col-lg-4">
                                    <div class="permission-option form-check">
                                        <input
                                            id="create_role_{{ $role->id }}"
                                            name="role_ids[]"
                                            type="checkbox"
                                            value="{{ $role->id }}"
                                            class="form-check-input"
                                            @checked(in_array((int) $role->id, $selectedCreateRoles, true))
                                        >
                                        <label for="create_role_{{ $role->id }}" class="form-check-label">
                                            {{ $role->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('role_ids')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        @error('role_ids.*')
                            <div class="text-danger small mt-2">{{ $message }}</div>
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
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            @endif
        </div>
    @endcan

    <div class="management-page-card bg-white border rounded-2 p-4">
        <div class="management-card-header d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-start gap-3">
                <span class="setup-card-icon" aria-hidden="true">
                    <i class="ti ti-users-group"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">Application Users</h2>
                    <p class="text-secondary mb-0">
                        Manage user accounts, role assignment, and account activation status.
                    </p>
                </div>
            </div>
        </div>

        <div class="management-table-shell table-responsive">
            <table id="users-table" class="management-table table table-striped align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        <tr>
                            <td class="fw-semibold">{{ $managedUser->name }}</td>
                            <td>{{ $managedUser->email }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse ($managedUser->roles as $role)
                                        <span class="setup-meta-badge badge text-bg-light border">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-secondary">No roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if ($managedUser->is_active)
                                    <span class="status-badge status-badge-active">Active</span>
                                @else
                                    <span class="status-badge status-badge-inactive">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $managedUser->created_at?->format('Y-m-d') }}</td>
                            <td class="text-end">
                                <div class="management-action-group d-inline-flex flex-wrap justify-content-end gap-2">
                                    @can('user.update')
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUser{{ $managedUser->id }}"
                                        >
                                            <i class="ti ti-pencil me-1" aria-hidden="true"></i>
                                            Edit
                                        </button>

                                        @if (! $managedUser->is_active)
                                            <form method="POST" action="{{ route('users.activate', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-success btn-sm"
                                                    onclick="return confirm('Activate this user account?')"
                                                >
                                                    <i class="ti ti-circle-check me-1" aria-hidden="true"></i>
                                                    Activate
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    @can('user.delete')
                                        @if ($managedUser->is_active && ! $managedUser->is(auth()->user()))
                                            <form method="POST" action="{{ route('users.deactivate', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Deactivate this user account?')"
                                                >
                                                    <i class="ti ti-circle-off me-1" aria-hidden="true"></i>
                                                    Deactivate
                                                </button>
                                            </form>
                                        @elseif ($managedUser->is(auth()->user()))
                                            <span class="btn btn-outline-secondary btn-sm disabled">
                                                <i class="ti ti-user-check me-1" aria-hidden="true"></i>
                                                Current User
                                            </span>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                <span class="setup-empty-state">
                                    <span class="setup-empty-icon" aria-hidden="true">
                                        <i class="ti ti-user-plus"></i>
                                    </span>
                                    <span>
                                        <span class="setup-empty-title d-block">No users have been created yet.</span>
                                        <span class="setup-empty-copy d-block">Create users to assign roles and application access.</span>
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="mt-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @can('user.update')
        @foreach ($users as $managedUser)
            @php
                $selectedRoleIds = collect(old('role_ids', $managedUser->roles->pluck('id')->all()))
                    ->map(fn ($roleId) => (int) $roleId)
                    ->all();
            @endphp

            <div
                class="modal fade setup-modal"
                id="editUser{{ $managedUser->id }}"
                tabindex="-1"
                aria-labelledby="editUserLabel{{ $managedUser->id }}"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('users.update', $managedUser) }}">
                            @csrf
                            @method('PATCH')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editUserLabel{{ $managedUser->id }}">
                                    Edit User
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name_{{ $managedUser->id }}" class="form-label">Name</label>
                                        <input
                                            id="name_{{ $managedUser->id }}"
                                            name="name"
                                            type="text"
                                            value="{{ old('name', $managedUser->name) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email_{{ $managedUser->id }}" class="form-label">Email</label>
                                        <input
                                            id="email_{{ $managedUser->id }}"
                                            name="email"
                                            type="email"
                                            value="{{ old('email', $managedUser->email) }}"
                                            class="form-control"
                                            maxlength="255"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password_{{ $managedUser->id }}" class="form-label">
                                            New Password
                                        </label>
                                        <input
                                            id="password_{{ $managedUser->id }}"
                                            name="password"
                                            type="password"
                                            class="form-control"
                                            minlength="12"
                                        >
                                        <div class="form-text">Leave blank to keep the current password.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password_confirmation_{{ $managedUser->id }}" class="form-label">
                                            Confirm New Password
                                        </label>
                                        <input
                                            id="password_confirmation_{{ $managedUser->id }}"
                                            name="password_confirmation"
                                            type="password"
                                            class="form-control"
                                            minlength="12"
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Roles</label>
                                        <div class="row g-2">
                                            @foreach ($roles as $role)
                                                <div class="col-sm-6 col-lg-4">
                                                    <div class="permission-option form-check">
                                                        <input
                                                            id="user_{{ $managedUser->id }}_role_{{ $role->id }}"
                                                            name="role_ids[]"
                                                            type="checkbox"
                                                            value="{{ $role->id }}"
                                                            class="form-check-input"
                                                            @checked(in_array((int) $role->id, $selectedRoleIds, true))
                                                        >
                                                        <label
                                                            for="user_{{ $managedUser->id }}_role_{{ $role->id }}"
                                                            class="form-check-label"
                                                        >
                                                            {{ $role->name }}
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
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
