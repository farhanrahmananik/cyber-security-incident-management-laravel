<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | Cyber Security Incident Management</title>

    <script>
        (function () {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="app-sidebar d-none d-lg-flex flex-column">
            @include('layouts.partials.sidebar')
        </aside>

        <div
            class="offcanvas offcanvas-start app-mobile-sidebar"
            tabindex="-1"
            id="mobileSidebar"
            aria-labelledby="mobileSidebarLabel"
        >
            <div class="offcanvas-header border-bottom">
                <h2 class="offcanvas-title h6 mb-0" id="mobileSidebarLabel">
                    Cyber Security Incident Management
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-0">
                @include('layouts.partials.sidebar')
            </div>
        </div>

        <div class="app-main">
            @include('layouts.partials.topbar')

            <main class="app-content">
                <div class="app-content-container">
                    <div class="app-page-header">
                        <div>
                            <p class="app-page-eyebrow">Cyber Security Incident Management</p>
                            <h1 class="app-page-title">
                                @hasSection('page-title')
                                    @yield('page-title')
                                @else
                                    @yield('title', 'Dashboard')
                                @endif
                            </h1>
                        </div>
                        <div class="app-page-actions">
                            @yield('page-actions')
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
