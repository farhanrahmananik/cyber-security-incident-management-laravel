<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap authorization services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::before(function (User $user, string $ability): ?bool {
            if (! $this->activePermissionExists($ability)) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        $this->definePermissionGates();
    }

    /**
     * Define gates for all active permission slugs.
     */
    private function definePermissionGates(): void
    {
        if (! $this->permissionsTableIsReady()) {
            return;
        }

        Permission::query()
            ->where('is_active', true)
            ->pluck('slug')
            ->each(function (string $permissionSlug): void {
                Gate::define(
                    $permissionSlug,
                    fn (User $user): bool => $user->hasPermission($permissionSlug),
                );
            });
    }

    /**
     * Determine if an active permission exists for the given ability.
     */
    private function activePermissionExists(string $ability): bool
    {
        if (! $this->permissionsTableIsReady()) {
            return false;
        }

        return Permission::query()
            ->where('slug', $ability)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Safely check whether the permissions table can be queried.
     */
    private function permissionsTableIsReady(): bool
    {
        try {
            return Schema::hasTable('permissions')
                && Schema::hasColumn('permissions', 'slug')
                && Schema::hasColumn('permissions', 'is_active');
        } catch (Throwable) {
            return false;
        }
    }
}
