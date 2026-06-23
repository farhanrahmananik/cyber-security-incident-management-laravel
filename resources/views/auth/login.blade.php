@extends('layouts.auth')

@section('title', 'Login | Cyber Security Incident Management')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                autocomplete="email"
                autofocus
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                class="form-control @error('password') is-invalid @enderror"
                autocomplete="current-password"
                required
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
            <div class="form-check">
                <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    value="1"
                    class="form-check-input"
                    @checked(old('remember'))
                >
                <label for="remember" class="form-check-label">Remember me</label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Sign in
        </button>
    </form>
@endsection
