<?php

namespace Tests\Feature\RolePermission;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $roleSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_roles_page(): void
    {
        $response = $this->get(route('roles.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_role_view_gets_forbidden(): void
    {
        $this->ensurePermissionExists('role.view');
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('roles.index'));

        $response->assertForbidden();
    }

    public function test_user_with_role_view_can_access_roles_page(): void
    {
        $user = $this->createUserWithPermissions(['role.view']);
        $role = $this->createRole('Security Manager', 'security-manager');

        $response = $this->actingAs($user)->get(route('roles.index'));

        $response->assertOk();
        $response->assertSee('Role &amp; Permission Management', false);
        $response->assertSee('Security Manager');
        $response->assertSee($role->slug);
    }

    public function test_user_with_role_create_can_create_role(): void
    {
        $user = $this->createUserWithPermissions(['role.create']);
        $permission = $this->createPermission('incident.view', 'Incident View', 'incidents');

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Incident Reviewer',
            'slug' => '',
            'description' => 'Reviews submitted incident records.',
            'is_active' => true,
            'permission_ids' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::query()->where('slug', 'incident-reviewer')->first();

        $this->assertNotNull($role);
        $this->assertSame('Incident Reviewer', $role->name);
        $this->assertTrue($role->is_active);
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
    }

    public function test_creating_role_creates_role_created_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['role.create']);
        $permission = $this->createPermission('incident.view', 'Incident View', 'incidents');

        $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Incident Reviewer',
            'slug' => '',
            'description' => 'Reviews submitted incident records.',
            'is_active' => true,
            'permission_ids' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('slug', 'incident-reviewer')->firstOrFail();
        $auditLog = $this->latestAuditLogFor('role.created', $role);

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame([
            'name' => 'Incident Reviewer',
            'slug' => 'incident-reviewer',
            'description' => 'Reviews submitted incident records.',
            'is_active' => true,
            'permission_slugs' => ['incident.view'],
        ], $auditLog->new_values);
    }

    public function test_duplicate_role_slug_generated_from_name_fails_validation(): void
    {
        $user = $this->createUserWithPermissions(['role.create']);
        $this->createRole('Security Manager', 'security-manager');

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Security Manager',
            'description' => 'Duplicate generated slug should fail.',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('slug');

        $this->assertSame(1, Role::query()->where('slug', 'security-manager')->count());
    }

    public function test_user_with_role_update_can_update_role(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $response = $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => 'Incident Review Lead',
            'slug' => 'incident-review-lead',
            'description' => 'Coordinates incident review activity.',
        ]);

        $response->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Incident Review Lead',
            'slug' => 'incident-review-lead',
            'description' => 'Coordinates incident review activity.',
        ]);
    }

    public function test_updating_role_creates_role_updated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => 'Incident Review Lead',
            'slug' => 'incident-review-lead',
            'description' => 'Coordinates incident review activity.',
        ])->assertRedirect(route('roles.index'));

        $auditLog = $this->latestAuditLogFor('role.updated', $role);

        $this->assertSame([
            'name' => 'Incident Reviewer',
            'slug' => 'incident-reviewer',
            'description' => null,
        ], $auditLog->old_values);
        $this->assertSame([
            'name' => 'Incident Review Lead',
            'slug' => 'incident-review-lead',
            'description' => 'Coordinates incident review activity.',
        ], $auditLog->new_values);
    }

    public function test_user_with_role_update_can_sync_active_permissions(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $oldPermission = $this->createPermission('incident.view', 'Incident View', 'incidents');
        $newPermission = $this->createPermission('incident.update', 'Incident Update', 'incidents');
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer', true, [$oldPermission->id]);

        $response = $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permission_ids' => [$newPermission->id],
        ]);

        $response->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertFalse($role->permissions()->whereKey($oldPermission->id)->exists());
        $this->assertTrue($role->permissions()->whereKey($newPermission->id)->exists());
    }

    public function test_syncing_permissions_creates_role_permissions_synced_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $oldPermission = $this->createPermission('incident.view', 'Incident View', 'incidents');
        $newPermission = $this->createPermission('incident.update', 'Incident Update', 'incidents');
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer', true, [$oldPermission->id]);

        $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permission_ids' => [$newPermission->id],
        ])->assertRedirect(route('roles.index'));

        $auditLog = $this->latestAuditLogFor('role.permissions_synced', $role);

        $this->assertSame(['permission_slugs' => ['incident.view']], $auditLog->old_values);
        $this->assertSame(['permission_slugs' => ['incident.update']], $auditLog->new_values);
    }

    public function test_inactive_permissions_cannot_be_assigned_when_creating_role(): void
    {
        $user = $this->createUserWithPermissions(['role.create']);
        $inactivePermission = $this->createPermission('legacy.permission', 'Legacy Permission', 'legacy', false);

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Legacy Operator',
            'slug' => 'legacy-operator',
            'description' => 'Should not receive inactive permissions.',
            'is_active' => true,
            'permission_ids' => [$inactivePermission->id],
        ]);

        $response->assertSessionHasErrors('permission_ids.0');

        $this->assertDatabaseMissing('roles', [
            'slug' => 'legacy-operator',
        ]);
    }

    public function test_inactive_permissions_cannot_be_assigned_when_updating_role(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $activePermission = $this->createPermission('incident.view', 'Incident View', 'incidents');
        $inactivePermission = $this->createPermission('legacy.permission', 'Legacy Permission', 'legacy', false);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer', true, [$activePermission->id]);

        $response = $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permission_ids' => [$inactivePermission->id],
        ]);

        $response->assertSessionHasErrors('permission_ids.0');

        $role->refresh();

        $this->assertTrue($role->permissions()->whereKey($activePermission->id)->exists());
        $this->assertFalse($role->permissions()->whereKey($inactivePermission->id)->exists());
    }

    public function test_user_without_role_create_cannot_create_role(): void
    {
        $this->ensurePermissionExists('role.create');
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Blocked Role',
            'slug' => 'blocked-role',
            'is_active' => true,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('roles', [
            'slug' => 'blocked-role',
        ]);
    }

    public function test_user_without_role_update_cannot_update_role(): void
    {
        $this->ensurePermissionExists('role.update');
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $response = $this->actingAs($user)->patch(route('roles.update', $role), [
            'name' => 'Blocked Update',
            'slug' => 'blocked-update',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Incident Reviewer',
            'slug' => 'incident-reviewer',
        ]);
    }

    public function test_user_without_role_delete_cannot_deactivate_role(): void
    {
        $this->ensurePermissionExists('role.delete');
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $response = $this->actingAs($user)->patch(route('roles.deactivate', $role));

        $response->assertForbidden();

        $this->assertTrue($role->fresh()->is_active);
    }

    public function test_user_with_role_delete_can_deactivate_normal_role(): void
    {
        $user = $this->createUserWithPermissions(['role.delete']);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $response = $this->actingAs($user)->patch(route('roles.deactivate', $role));

        $response->assertRedirect(route('roles.index'));

        $this->assertFalse($role->fresh()->is_active);
    }

    public function test_deactivating_role_creates_role_deactivated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['role.delete']);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer');

        $this->actingAs($user)->patch(route('roles.deactivate', $role))
            ->assertRedirect(route('roles.index'));

        $auditLog = $this->latestAuditLogFor('role.deactivated', $role);

        $this->assertSame(['is_active' => true], $auditLog->old_values);
        $this->assertSame(['is_active' => false], $auditLog->new_values);
    }

    public function test_reactivating_role_creates_role_reactivated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $role = $this->createRole('Incident Reviewer', 'incident-reviewer', false);

        $this->actingAs($user)->patch(route('roles.activate', $role))
            ->assertRedirect(route('roles.index'));

        $auditLog = $this->latestAuditLogFor('role.reactivated', $role);

        $this->assertSame(['is_active' => false], $auditLog->old_values);
        $this->assertSame(['is_active' => true], $auditLog->new_values);
    }

    public function test_super_admin_role_cannot_be_deactivated(): void
    {
        $user = $this->createUserWithPermissions(['role.delete']);
        $superAdminRole = $this->createRole('Super Admin', 'super-admin');

        $response = $this->actingAs($user)->patch(route('roles.deactivate', $superAdminRole));

        $response->assertSessionHasErrors('role');

        $this->assertTrue($superAdminRole->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'role.deactivated',
            'auditable_id' => $superAdminRole->id,
        ]);
    }

    public function test_super_admin_slug_cannot_be_changed(): void
    {
        $user = $this->createUserWithPermissions(['role.update']);
        $superAdminRole = $this->createRole('Super Admin', 'super-admin');

        $response = $this->actingAs($user)->patch(route('roles.update', $superAdminRole), [
            'name' => 'Platform Administrator',
            'slug' => 'platform-administrator',
            'description' => 'Attempted protected slug change.',
        ]);

        $response->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('roles', [
            'id' => $superAdminRole->id,
            'slug' => 'super-admin',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'role.updated',
            'auditable_id' => $superAdminRole->id,
        ]);
    }

    public function test_another_super_admin_slug_cannot_be_created(): void
    {
        $user = $this->createUserWithPermissions(['role.create']);
        $this->createRole('Super Admin', 'super-admin');

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Another Super Admin',
            'slug' => 'super-admin',
            'description' => 'Duplicate protected role.',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('slug');

        $this->assertSame(1, Role::query()->where('slug', 'super-admin')->count());
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'role.created',
        ]);
    }

    public function test_sidebar_shows_role_permission_link_for_role_view_users(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view', 'role.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $sidebarContent = $this->sidebarContent($response);

        $response->assertOk();
        $this->assertStringContainsString('Role &amp; Permission', $sidebarContent);
        $this->assertStringContainsString('href="'.route('roles.index').'"', $sidebarContent);
    }

    public function test_sidebar_hides_role_permission_link_from_users_without_role_view(): void
    {
        $this->ensurePermissionExists('role.view');
        $user = $this->createUserWithPermissions(['dashboard.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $sidebarContent = $this->sidebarContent($response);

        $response->assertOk();
        $this->assertStringNotContainsString('Role &amp; Permission', $sidebarContent);
        $this->assertStringNotContainsString('href="'.route('roles.index').'"', $sidebarContent);
    }

    /**
     * Create an active user with one active role and optional permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $this->roleSequence++;

        $role = Role::query()->create([
            'name' => 'Role Permission Test Role '.$this->roleSequence,
            'slug' => 'role-permission-test-role-'.$this->roleSequence,
            'is_active' => true,
        ]);

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $permissionSlug): int => $this->ensurePermissionExists($permissionSlug)->id)
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function createRole(
        string $name,
        string $slug,
        bool $isActive = true,
        array $permissionIds = [],
    ): Role {
        $role = Role::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => $isActive,
        ]);

        $role->permissions()->sync($permissionIds);

        return $role;
    }

    private function createPermission(
        string $slug,
        ?string $name = null,
        string $groupName = 'roles',
        bool $isActive = true,
    ): Permission {
        return Permission::query()->create([
            'name' => $name ?? str($slug)->replace(['.', '-'], ' ')->title()->toString(),
            'slug' => $slug,
            'group_name' => $groupName,
            'is_active' => $isActive,
        ]);
    }

    private function ensurePermissionExists(string $permissionSlug): Permission
    {
        return Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                'group_name' => str($permissionSlug)->before('.')->toString(),
                'is_active' => true,
            ],
        );
    }

    private function sidebarContent(TestResponse $response): string
    {
        preg_match(
            '/<nav class="sidebar-nav" aria-label="Primary navigation">(.*?)<\/nav>/s',
            $response->getContent(),
            $matches,
        );

        return $matches[1] ?? '';
    }

    private function latestAuditLogFor(string $event, Role $role): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $role->getMorphClass())
            ->where('auditable_id', $role->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
