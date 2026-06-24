<?php

namespace App\Services\RolePermission;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

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
        $role = DB::transaction(function () use ($data): Role {
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

        $this->auditLogService->record(
            event: 'role.created',
            auditable: $role,
            newValues: $this->safeRoleValues($role) + [
                'permission_slugs' => $this->permissionSlugsForRole($role),
            ],
            request: request(),
        );

        return $role;
    }

    /**
     * Update a role and synchronize selected active permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRole(Role $role, array $data): Role
    {
        $oldRoleValues = $this->safeRoleValues($role);
        $oldPermissionSlugs = $this->permissionSlugsForRole($role);

        $updatedRole = DB::transaction(function () use ($role, $data): Role {
            $slug = $this->uniqueSlugFromData($data, $role);

            $this->ensureSuperAdminSlugIsNotChanged($role, $slug);

            $role->update([
                ...Arr::only($data, ['name', 'description']),
                'slug' => $slug,
            ]);

            $role->permissions()->sync($this->validatedActivePermissionIds($data));

            return $role->load('permissions');
        });

        $newRoleValues = $this->safeRoleValues($updatedRole);
        $changedRoleValues = $this->changedValues($oldRoleValues, $newRoleValues);

        if ($changedRoleValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'role.updated',
                auditable: $updatedRole,
                oldValues: $changedRoleValues['old'],
                newValues: $changedRoleValues['new'],
                request: request(),
            );
        }

        $newPermissionSlugs = $this->permissionSlugsForRole($updatedRole);

        if ($oldPermissionSlugs !== $newPermissionSlugs) {
            $this->auditLogService->record(
                event: 'role.permissions_synced',
                auditable: $updatedRole,
                oldValues: ['permission_slugs' => $oldPermissionSlugs],
                newValues: ['permission_slugs' => $newPermissionSlugs],
                request: request(),
            );
        }

        return $updatedRole;
    }

    /**
     * Activate a role.
     */
    public function activate(Role $role): void
    {
        $wasActive = (bool) $role->is_active;

        DB::transaction(function () use ($role): void {
            $role->update(['is_active' => true]);
        });

        if ($wasActive === false) {
            $this->auditLogService->record(
                event: 'role.reactivated',
                auditable: $role->refresh(),
                oldValues: ['is_active' => false],
                newValues: ['is_active' => true],
                request: request(),
            );
        }
    }

    /**
     * Deactivate a role without deleting it.
     */
    public function deactivate(Role $role): void
    {
        $this->ensureRoleCanBeDeactivated($role);

        $wasActive = (bool) $role->is_active;

        DB::transaction(function () use ($role): void {
            $role->update(['is_active' => false]);
        });

        if ($wasActive === true) {
            $this->auditLogService->record(
                event: 'role.deactivated',
                auditable: $role->refresh(),
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                request: request(),
            );
        }
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

    /**
     * Return safe role fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeRoleValues(Role $role): array
    {
        return [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_active' => (bool) $role->is_active,
        ];
    }

    /**
     * Return sorted permission slugs for audit logging.
     *
     * @return list<string>
     */
    private function permissionSlugsForRole(Role $role): array
    {
        return $role->permissions()
            ->orderBy('slug')
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * Extract changed audit values from two safe snapshots.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changedValues(array $oldValues, array $newValues): array
    {
        $old = [];
        $new = [];

        foreach ($newValues as $key => $value) {
            if (($oldValues[$key] ?? null) === $value) {
                continue;
            }

            $old[$key] = $oldValues[$key] ?? null;
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }
}
