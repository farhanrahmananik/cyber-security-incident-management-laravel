<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Cyber Security Incident Management')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell d-flex align-items-center justify-content-center px-3 py-5">
        <div class="auth-panel p-4 p-sm-5">
            <div class="mb-4">
                <div class="auth-brand-mark mb-3">CS</div>
                <h1 class="h4 mb-1">Cyber Security Incident Management</h1>
                <p class="text-secondary mb-0">Sign in to continue to the incident management platform.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="fw-semibold mb-1">Please review the highlighted fields.</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
