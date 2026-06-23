<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed role-permission assignments.
     */
    public function run(): void
    {
        $this->syncRolePermissions(
            'super-admin',
            Permission::query()->pluck('slug')->all(),
        );

        $this->syncRolePermissions('security-manager', [
            'dashboard.view',
            'user.view',
            'user.create',
            'user.update',
            'role.view',
            'role.create',
            'role.update',
            'incident.view',
            'incident.create',
            'incident.update',
            'incident.assign',
            'incident.investigate',
            'incident.close',
            'incident.status.update',
            'incident-category.view',
            'incident-category.manage',
            'severity-level.view',
            'severity-level.manage',
            'priority-level.view',
            'priority-level.manage',
            'investigation-note.view',
            'investigation-note.create',
            'ioc.view',
            'ioc.manage',
            'evidence.view',
            'evidence.manage',
            'response-action.view',
            'response-action.manage',
            'report.view',
            'audit-log.view',
        ]);

        $this->syncRolePermissions('soc-analyst', [
            'dashboard.view',
            'incident.view',
            'incident.update',
            'incident.investigate',
            'incident.status.update',
            'investigation-note.view',
            'investigation-note.create',
            'ioc.view',
            'ioc.manage',
            'evidence.view',
            'evidence.manage',
            'response-action.view',
            'response-action.manage',
        ]);

        $this->syncRolePermissions('reporter-employee', [
            'dashboard.view',
            'incident.view',
            'incident.create',
        ]);
    }

    /**
     * Synchronize a role to an exact set of permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function syncRolePermissions(string $roleSlug, array $permissionSlugs): void
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $permissions = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id', 'slug');

        $missingPermissions = collect($permissionSlugs)->diff($permissions->keys());

        if ($missingPermissions->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Missing permissions for role [%s]: %s',
                $roleSlug,
                $missingPermissions->implode(', '),
            ));
        }

        $role->permissions()->sync($permissions->values()->all());
    }
}
