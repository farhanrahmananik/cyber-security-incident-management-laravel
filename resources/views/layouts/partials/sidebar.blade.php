<div class="sidebar-brand">
    <span class="auth-brand-mark">CS</span>
    <div>
        <div class="fw-semibold">Cyber Security</div>
        <div class="small text-secondary">Incident Management</div>
    </div>
</div>

<nav class="sidebar-nav" aria-label="Primary navigation">
    @can('dashboard.view')
        <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <span>Dashboard</span>
        </a>
    @endcan

    @can('user.view')
        <a
            class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
            href="{{ route('users.index') }}"
        >
            <span>User Management</span>
        </a>
    @endcan

    @can('role.view')
        <a
            class="sidebar-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
            href="{{ route('roles.index') }}"
        >
            <span>Role &amp; Permission</span>
        </a>
    @endcan

    @can('incident.view')
        <a class="sidebar-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}" href="{{ route('incidents.index') }}">
            <span>Incidents</span>
        </a>
    @endcan

    @canany(['incident-category.view', 'severity-level.view', 'priority-level.view'])
        <div class="sidebar-section-label">Incident Setup</div>

        @can('incident-category.view')
            <a
                class="sidebar-link {{ request()->routeIs('incident-categories.*') ? 'active' : '' }}"
                href="{{ route('incident-categories.index') }}"
            >
                <span>Incident Categories</span>
            </a>
        @endcan

        @can('severity-level.view')
            <a
                class="sidebar-link {{ request()->routeIs('severity-levels.*') ? 'active' : '' }}"
                href="{{ route('severity-levels.index') }}"
            >
                <span>Severity Levels</span>
            </a>
        @endcan

        @can('priority-level.view')
            <a
                class="sidebar-link {{ request()->routeIs('priority-levels.*') ? 'active' : '' }}"
                href="{{ route('priority-levels.index') }}"
            >
                <span>Priority Levels</span>
            </a>
        @endcan
    @endcanany

    @can('report.view')
        <a
            class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
            href="{{ route('reports.security.index') }}"
        >
            <span>Security Reports</span>
        </a>
    @endcan

    @can('audit-log.view')
        <a class="sidebar-link planned" href="#" aria-disabled="true">
            <span>Audit Logs</span>
            <span class="planned-label">Planned</span>
        </a>
    @endcan
</nav>
