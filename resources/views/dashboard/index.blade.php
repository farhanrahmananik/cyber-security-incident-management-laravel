@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $dashboardUser = auth()->user();
        $dashboardRoles = $dashboardUser->roles()
            ->where('roles.is_active', true)
            ->orderBy('name')
            ->get();
    @endphp

    <div class="row g-4">
        <div class="col-12">
            <div class="bg-white border rounded-2 p-4 p-md-5">
                <p class="text-secondary mb-1">Authenticated session</p>
                <h2 class="h3 mb-3">Welcome, {{ $dashboardUser->name }}</h2>
                <p class="mb-4">
                    This is a temporary authenticated landing page for the auth foundation.
                    Incident and dashboard modules will be implemented in later steps.
                </p>

                <div class="d-flex gap-2 flex-wrap">
                    @forelse ($dashboardRoles as $role)
                        <span class="badge text-bg-light border">{{ $role->name }}</span>
                    @empty
                        <span class="badge text-bg-light border">No role assigned</span>
                    @endforelse
                </div>

                @can('dashboard.view')
                    <div class="border-top mt-4 pt-4">
                        <p class="fw-semibold mb-1">Authorization active</p>
                        <p class="text-secondary mb-0">The dashboard permission gate is allowing this protected page.</p>

                        @can('incident.assign')
                            <p class="text-secondary mb-0 mt-2">Incident assignment access available.</p>
                        @endcan
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="fw-semibold mb-1">Authentication Ready</p>
                <p class="text-secondary mb-0">Session login, logout, and protected routes are in place.</p>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="fw-semibold mb-1">RBAC Ready</p>
                <p class="text-secondary mb-0">Roles, permissions, relationships, and seeders are available.</p>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="fw-semibold mb-1">Authorization Middleware Ready</p>
                <p class="text-secondary mb-0">Permission and role middleware protect authenticated areas.</p>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-4 h-100">
                <p class="fw-semibold mb-1">Next Planned Module</p>
                <p class="text-secondary mb-0">Incident Category / Severity / Priority Setup.</p>
            </div>
        </div>
    </div>
@endsection
