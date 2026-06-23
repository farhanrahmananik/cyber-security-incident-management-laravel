@php
    $authenticatedUser = auth()->user();
    $userRoles = $authenticatedUser?->roles()
        ->where('roles.is_active', true)
        ->orderBy('name')
        ->get() ?? collect();
@endphp

<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button
            class="btn btn-outline-secondary btn-sm d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
            aria-controls="mobileSidebar"
        >
            Menu
        </button>
        <div>
            <div class="fw-semibold">{{ $authenticatedUser->name }}</div>
            <div class="small text-secondary">{{ $authenticatedUser->email }}</div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @forelse ($userRoles as $role)
                <span class="badge text-bg-light border">{{ $role->name }}</span>
            @empty
                <span class="badge text-bg-light border">No role assigned</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
        </form>
    </div>
</header>
