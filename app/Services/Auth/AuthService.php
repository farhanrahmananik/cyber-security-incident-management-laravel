<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;

class AuthService
{
    /**
     * Attempt a session-based login for an active user.
     *
     * @param  array{email: string, password: string}  $credentials
     */
    public function login(array $credentials, bool $remember = false): bool
    {
        $authenticated = Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $remember);

        if ($authenticated) {
            request()->session()->regenerate();
        }

        return $authenticated;
    }

    /**
     * End the current authenticated web session.
     */
    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
