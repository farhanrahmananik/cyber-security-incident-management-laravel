@php
    $authenticatedUser = auth()->user();
    $userRoles = $authenticatedUser?->roles()
        ->where('roles.is_active', true)
        ->orderBy('name')
        ->get() ?? collect();

    $userInitials = collect(explode(' ', (string) $authenticatedUser?->name))
        ->filter()
        ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('') ?: 'U';
@endphp

<header class="app-topbar">
    <div class="app-topbar-start">
        <button
            class="btn btn-outline-secondary btn-sm d-lg-none app-icon-button"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
            aria-controls="mobileSidebar"
            aria-label="Open navigation"
        >
            <i class="ti ti-menu-2" aria-hidden="true"></i>
        </button>
        <div class="app-user-summary">
            <span class="app-user-avatar" aria-hidden="true">{{ $userInitials }}</span>
            <div>
                <div class="app-user-name">{{ $authenticatedUser->name }}</div>
                <div class="app-user-email">{{ $authenticatedUser->email }}</div>
            </div>
        </div>
    </div>

    <div class="app-topbar-actions">
        <div class="app-role-list">
            @forelse ($userRoles as $role)
                <span class="badge text-bg-light border">{{ $role->name }}</span>
            @empty
                <span class="badge text-bg-light border">No role assigned</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-logout me-1" aria-hidden="true"></i>
                Logout
            </button>
        </form>
    </div>
</header>
