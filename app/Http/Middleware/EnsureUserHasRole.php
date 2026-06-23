<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user has at least one required role.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $roleSlugs = collect($roles)
            ->flatMap(fn (string $role): array => explode(',', $role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_if($roleSlugs === [] || ! $user->hasAnyRole($roleSlugs), 403);

        return $next($request);
    }
}
