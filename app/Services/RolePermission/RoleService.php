<?php

namespace App\Services\RolePermission;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /**
     * Return roles with permission relationships and user counts.
     */
    public function paginateRoles(): LengthAwarePaginator
    {
        return Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('group_name')->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Return active permissions grouped by permission group for display.
     */
    public function activePermissionsGrouped(): Collection
    {
        return Permission::query()
            ->where('is_active', true)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->group_name ?: 'general');
    }

    /**
     * Create a role and synchronize selected active permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $slug = $this->uniqueSlugFromData($data);

            $this->ensureSlugIsNotReservedForCreate($slug);

            $role = Role::query()->create([
                ...Arr::only($data, ['name', 'description']),
                'slug' => $slug,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $role->permissions()->sync($this->validatedActivePermissionIds($data));

            return $role->load('permissions');
        });
    }

    /**
     * Update a role and synchronize selected active permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $slug = $this->uniqueSlugFromData($data, $role);

            $this->ensureSuperAdminSlugIsNotChanged($role, $slug);

            $role->update([
                ...Arr::only($data, ['name', 'description']),
                'slug' => $slug,
            ]);

            $role->permissions()->sync($this->validatedActivePermissionIds($data));

            return $role->load('permissions');
        });
    }

    /**
     * Activate a role.
     */
    public function activate(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role->update(['is_active' => true]);
        });
    }

    /**
     * Deactivate a role without deleting it.
     */
    public function deactivate(Role $role): void
    {
        $this->ensureRoleCanBeDeactivated($role);

        DB::transaction(function () use ($role): void {
            $role->update(['is_active' => false]);
        });
    }

    /**
     * Generate or normalize a unique role slug.
     *
     * @param  array<string, mixed>  $data
     */
    private function uniqueSlugFromData(array $data, ?Role $ignore = null): string
    {
        $rawSlug = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : (string) $data['name'];

        $slug = Str::slug($rawSlug) ?: 'role';

        if ($this->slugExists($slug, $ignore)) {
            throw ValidationException::withMessages([
                'slug' => 'The role slug has already been taken.',
            ]);
        }

        return $slug;
    }

    /**
     * Determine if a role slug already exists on a non-deleted role.
     */
    private function slugExists(string $slug, ?Role $ignore = null): bool
    {
        return Role::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();
    }

    /**
     * Prevent creating another Super Admin role.
     */
    private function ensureSlugIsNotReservedForCreate(string $slug): void
    {
        if ($slug !== 'super-admin') {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => 'The super-admin role is a protected system role.',
        ]);
    }

    /**
     * Prevent changing the protected Super Admin role slug.
     */
    private function ensureSuperAdminSlugIsNotChanged(Role $role, string $slug): void
    {
        if ($role->slug !== 'super-admin' || $slug === 'super-admin') {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => 'The protected Super Admin role slug cannot be changed.',
        ]);
    }

    /**
     * Prevent deactivating the protected Super Admin role.
     */
    private function ensureRoleCanBeDeactivated(Role $role): void
    {
        if ($role->slug !== 'super-admin') {
            return;
        }

        throw ValidationException::withMessages([
            'role' => 'The protected Super Admin role cannot be deactivated.',
        ]);
    }

    /**
     * Validate permission ids against active, non-deleted permissions before syncing.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function validatedActivePermissionIds(array $data): array
    {
        $permissionIds = collect($data['permission_ids'] ?? [])
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->unique()
            ->values();

        if ($permissionIds->isEmpty()) {
            return [];
        }

        $activePermissionIds = Permission::query()
            ->whereIn('id', $permissionIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->values();

        if ($activePermissionIds->count() !== $permissionIds->count()) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Only active permissions can be assigned to roles.',
            ]);
        }

        return $activePermissionIds->all();
    }
}
