<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Dashboard | Cyber Security Incident Management</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <span class="navbar-brand fw-semibold">Cyber Security Incident Management</span>

            <form method="POST" action="{{ route('logout') }}" class="ms-auto">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
            </form>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="bg-white border rounded-2 p-4 p-md-5">
                    <p class="text-secondary mb-1">Authenticated session</p>
                    <h1 class="h3 mb-3">Welcome, {{ auth()->user()->name }}</h1>
                    <p class="mb-0">
                        This is a temporary authenticated landing page for the auth foundation.
                        Incident and dashboard modules will be implemented in later steps.
                    </p>

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
        </div>
    </main>
</body>
</html>
