<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Ensure the authenticated user is authorized for the required permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        Gate::authorize($permission);

        return $next($request);
    }
}
