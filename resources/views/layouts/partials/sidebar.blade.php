<div class="sidebar-brand">
    <span class="auth-brand-mark">CS</span>
    <div>
        <div class="sidebar-brand-title">Cyber Security</div>
        <div class="sidebar-brand-subtitle">Incident Management</div>
    </div>
</div>

<nav class="sidebar-nav" aria-label="Primary navigation">
    @can('dashboard.view')
        <a
            class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
            href="{{ route('dashboard') }}"
            @if (request()->routeIs('dashboard')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-layout-dashboard" aria-hidden="true"></i>
                <span>Dashboard</span>
            </span>
        </a>
    @endcan

    @can('user.view')
        <a
            class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
            href="{{ route('users.index') }}"
            @if (request()->routeIs('users.*')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-users" aria-hidden="true"></i>
                <span>User Management</span>
            </span>
        </a>
    @endcan

    @can('role.view')
        <a
            class="sidebar-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
            href="{{ route('roles.index') }}"
            @if (request()->routeIs('roles.*')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-shield-check" aria-hidden="true"></i>
                <span>Role &amp; Permission</span>
            </span>
        </a>
    @endcan

    @can('incident.view')
        <a
            class="sidebar-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}"
            href="{{ route('incidents.index') }}"
            @if (request()->routeIs('incidents.*')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                <span>Incidents</span>
            </span>
        </a>
    @endcan

    @canany(['incident-category.view', 'severity-level.view', 'priority-level.view'])
        <div class="sidebar-section-label">Incident Setup</div>

        @can('incident-category.view')
            <a
                class="sidebar-link {{ request()->routeIs('incident-categories.*') ? 'active' : '' }}"
                href="{{ route('incident-categories.index') }}"
                @if (request()->routeIs('incident-categories.*')) aria-current="page" @endif
            >
                <span class="sidebar-link-content">
                    <i class="ti ti-category" aria-hidden="true"></i>
                    <span>Incident Categories</span>
                </span>
            </a>
        @endcan

        @can('severity-level.view')
            <a
                class="sidebar-link {{ request()->routeIs('severity-levels.*') ? 'active' : '' }}"
                href="{{ route('severity-levels.index') }}"
                @if (request()->routeIs('severity-levels.*')) aria-current="page" @endif
            >
                <span class="sidebar-link-content">
                    <i class="ti ti-signal-4g" aria-hidden="true"></i>
                    <span>Severity Levels</span>
                </span>
            </a>
        @endcan

        @can('priority-level.view')
            <a
                class="sidebar-link {{ request()->routeIs('priority-levels.*') ? 'active' : '' }}"
                href="{{ route('priority-levels.index') }}"
                @if (request()->routeIs('priority-levels.*')) aria-current="page" @endif
            >
                <span class="sidebar-link-content">
                    <i class="ti ti-flag" aria-hidden="true"></i>
                    <span>Priority Levels</span>
                </span>
            </a>
        @endcan
    @endcanany

    @can('report.view')
        <a
            class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
            href="{{ route('reports.security.index') }}"
            @if (request()->routeIs('reports.*')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-file-analytics" aria-hidden="true"></i>
                <span>Security Reports</span>
            </span>
        </a>
    @endcan

    @can('audit-log.view')
        <a
            class="sidebar-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
            href="{{ route('audit-logs.index') }}"
            @if (request()->routeIs('audit-logs.*')) aria-current="page" @endif
        >
            <span class="sidebar-link-content">
                <i class="ti ti-clipboard-list" aria-hidden="true"></i>
                <span>Audit Logs</span>
            </span>
        </a>
    @endcan
</nav>
