@extends('layouts.auth')

@section('title', 'Login | Cyber Security Incident Management')

@section('auth-header')
    <div class="auth-branding">
        <div class="auth-brand-lock">
            <i class="ti ti-shield-lock" aria-hidden="true"></i>
        </div>

        <div>
            <p class="auth-kicker">Cyber Security</p>
            <h1 class="auth-title">Incident Management</h1>
            <p class="auth-subtitle">
                Portfolio-ready incident reporting, RBAC, and security operations workflow foundation.
            </p>
        </div>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="email" class="form-label">
                <i class="ti ti-mail" aria-hidden="true"></i>
                Email address
            </label>
            <div class="input-group auth-input-group">
                <span class="input-group-text" aria-hidden="true">
                    <i class="ti ti-mail"></i>
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    autocomplete="email"
                    placeholder="Enter your email"
                    autofocus
                    required
                >
            </div>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="form-label">
                <i class="ti ti-key" aria-hidden="true"></i>
                Password
            </label>
            <div class="input-group auth-input-group">
                <span class="input-group-text" aria-hidden="true">
                    <i class="ti ti-key"></i>
                </span>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    required
                >
            </div>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-form-row">
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

        <button type="submit" class="btn btn-primary auth-submit w-100">
            <i class="ti ti-shield-check" aria-hidden="true"></i>
            Sign in
        </button>
    </form>
@endsection
