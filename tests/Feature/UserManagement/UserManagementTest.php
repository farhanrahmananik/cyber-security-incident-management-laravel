<?php

namespace Tests\Feature\UserManagement;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $roleSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_users_page(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_user_view_cannot_access_users_page(): void
    {
        $this->ensurePermissionsExist(['user.view']);
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_user_without_user_create_cannot_store_users(): void
    {
        $this->ensurePermissionsExist(['user.create']);
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');

        $response = $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Blocked Create User',
            'email' => 'blocked.create@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role_ids' => [$role->id],
            'is_active' => true,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked.create@example.com',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_without_user_update_cannot_update_users(): void
    {
        $this->ensurePermissionsExist(['user.update']);
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create([
            'name' => 'Original User',
            'email' => 'original.user@example.com',
            'is_active' => true,
        ]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => 'Blocked Update User',
            'email' => 'blocked.update@example.com',
            'role_ids' => [$role->id],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Original User',
            'email' => 'original.user@example.com',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_without_user_update_cannot_activate_users(): void
    {
        $this->ensurePermissionsExist(['user.update']);
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => false]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.activate', $managedUser));

        $response->assertForbidden();

        $this->assertFalse($managedUser->fresh()->is_active);
    }

    public function test_user_without_user_delete_cannot_deactivate_users(): void
    {
        $this->ensurePermissionsExist(['user.delete']);
        $user = $this->createUserWithPermissions([]);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.deactivate', $managedUser));

        $response->assertForbidden();

        $this->assertTrue($managedUser->fresh()->is_active);
    }

    public function test_authorized_user_can_view_users_page(): void
    {
        $user = $this->createUserWithPermissions(['user.view']);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create([
            'name' => 'Managed Analyst',
            'email' => 'managed.analyst@example.com',
            'is_active' => true,
        ]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('User Management');
        $response->assertSee('Managed Analyst');
        $response->assertSee('SOC Analyst');
    }

    public function test_authorized_user_can_create_user_with_active_role(): void
    {
        $user = $this->createUserWithPermissions(['user.create']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');

        $response = $this->actingAs($user)->post(route('users.store'), [
            'name' => 'New Reporter',
            'email' => 'new.reporter@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role_ids' => [$role->id],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));

        $createdUser = User::query()->where('email', 'new.reporter@example.com')->first();

        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->is_active);
        $this->assertTrue($createdUser->roles()->whereKey($role->id)->exists());
    }

    public function test_creating_user_creates_user_created_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['user.create']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');

        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Audited Reporter',
            'email' => 'audited.reporter@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role_ids' => [$role->id],
            'is_active' => true,
        ])->assertRedirect(route('users.index'));

        $createdUser = User::query()->where('email', 'audited.reporter@example.com')->firstOrFail();
        $auditLog = $this->latestAuditLogFor('user.created', $createdUser);

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame([
            'name' => 'Audited Reporter',
            'email' => 'audited.reporter@example.com',
            'is_active' => true,
            'role_slugs' => ['reporter-employee'],
        ], $auditLog->new_values);
        $this->assertArrayNotHasKey('password', $auditLog->new_values);
    }

    public function test_created_user_password_is_hashed(): void
    {
        $user = $this->createUserWithPermissions(['user.create']);
        $role = $this->createRole('Security Manager', 'security-manager');

        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Password Check User',
            'email' => 'password.check@example.com',
            'password' => 'AnotherSecurePassword123!',
            'password_confirmation' => 'AnotherSecurePassword123!',
            'role_ids' => [$role->id],
            'is_active' => true,
        ])->assertRedirect(route('users.index'));

        $createdUser = User::query()->where('email', 'password.check@example.com')->firstOrFail();

        $this->assertNotSame('AnotherSecurePassword123!', $createdUser->password);
        $this->assertTrue(Hash::check('AnotherSecurePassword123!', $createdUser->password));
    }

    public function test_inactive_roles_cannot_be_assigned_when_creating_user(): void
    {
        $user = $this->createUserWithPermissions(['user.create']);
        $inactiveRole = Role::query()->create([
            'name' => 'Inactive Role',
            'slug' => 'inactive-role',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Inactive Role User',
            'email' => 'inactive.role.user@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role_ids' => [$inactiveRole->id],
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('role_ids.0');

        $this->assertDatabaseMissing('users', [
            'email' => 'inactive.role.user@example.com',
        ]);
    }

    public function test_authorized_user_can_update_name_and_email(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create([
            'name' => 'Original Analyst',
            'email' => 'original.analyst@example.com',
            'is_active' => true,
        ]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => 'Updated Analyst',
            'email' => 'updated.analyst@example.com',
            'role_ids' => [$role->id],
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Updated Analyst',
            'email' => 'updated.analyst@example.com',
        ]);
    }

    public function test_updating_user_safe_fields_creates_user_updated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create([
            'name' => 'Original Analyst',
            'email' => 'original.audit.analyst@example.com',
            'is_active' => true,
        ]);
        $managedUser->roles()->sync([$role->id]);

        $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => 'Updated Analyst',
            'email' => 'updated.audit.analyst@example.com',
            'role_ids' => [$role->id],
        ])->assertRedirect(route('users.index'));

        $auditLog = $this->latestAuditLogFor('user.updated', $managedUser);

        $this->assertSame([
            'name' => 'Original Analyst',
            'email' => 'original.audit.analyst@example.com',
        ], $auditLog->old_values);
        $this->assertSame([
            'name' => 'Updated Analyst',
            'email' => 'updated.audit.analyst@example.com',
        ], $auditLog->new_values);
    }

    public function test_blank_password_on_update_does_not_change_password(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create([
            'password' => 'OriginalSecurePassword123!',
            'is_active' => true,
        ]);
        $managedUser->roles()->sync([$role->id]);
        $originalPasswordHash = $managedUser->password;

        $response = $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'password' => '',
            'password_confirmation' => '',
            'role_ids' => [$role->id],
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertSame($originalPasswordHash, $managedUser->fresh()->password);
    }

    public function test_updating_password_does_not_expose_password_or_hash_in_audit_values(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$role->id]);

        $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
            'role_ids' => [$role->id],
        ])->assertRedirect(route('users.index'));

        $auditLog = $this->latestAuditLogFor('user.updated', $managedUser);

        $this->assertSame([], $auditLog->old_values);
        $this->assertSame([], $auditLog->new_values);
        $this->assertStringNotContainsString('NewSecurePassword123!', json_encode($auditLog->old_values + $auditLog->new_values));
        $this->assertStringNotContainsString((string) $managedUser->fresh()->password, json_encode($auditLog->old_values + $auditLog->new_values));
    }

    public function test_inactive_roles_cannot_be_assigned_when_updating_user(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $activeRole = $this->createRole('SOC Analyst', 'soc-analyst');
        $inactiveRole = Role::query()->create([
            'name' => 'Inactive Role',
            'slug' => 'inactive-role',
            'is_active' => false,
        ]);
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$activeRole->id]);

        $response = $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role_ids' => [$inactiveRole->id],
        ]);

        $response->assertSessionHasErrors('role_ids.0');

        $managedUser->refresh();

        $this->assertTrue($managedUser->roles()->whereKey($activeRole->id)->exists());
        $this->assertFalse($managedUser->roles()->whereKey($inactiveRole->id)->exists());
    }

    public function test_authorized_user_can_sync_user_roles(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $oldRole = $this->createRole('Reporter / Employee', 'reporter-employee');
        $newRole = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$oldRole->id]);

        $response = $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role_ids' => [$newRole->id],
        ]);

        $response->assertRedirect(route('users.index'));

        $managedUser->refresh();

        $this->assertFalse($managedUser->roles()->whereKey($oldRole->id)->exists());
        $this->assertTrue($managedUser->roles()->whereKey($newRole->id)->exists());
    }

    public function test_syncing_user_roles_creates_user_roles_synced_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $oldRole = $this->createRole('Reporter / Employee', 'reporter-employee');
        $newRole = $this->createRole('SOC Analyst', 'soc-analyst');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$oldRole->id]);

        $this->actingAs($user)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role_ids' => [$newRole->id],
        ])->assertRedirect(route('users.index'));

        $auditLog = $this->latestAuditLogFor('user.roles_synced', $managedUser);

        $this->assertSame(['role_slugs' => ['reporter-employee']], $auditLog->old_values);
        $this->assertSame(['role_slugs' => ['soc-analyst']], $auditLog->new_values);
    }

    public function test_authorized_user_can_deactivate_another_user(): void
    {
        $user = $this->createUserWithPermissions(['user.delete']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.deactivate', $managedUser));

        $response->assertRedirect(route('users.index'));

        $this->assertFalse($managedUser->fresh()->is_active);
    }

    public function test_deactivating_user_creates_user_deactivated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['user.delete']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => true]);
        $managedUser->roles()->sync([$role->id]);

        $this->actingAs($user)->patch(route('users.deactivate', $managedUser))
            ->assertRedirect(route('users.index'));

        $auditLog = $this->latestAuditLogFor('user.deactivated', $managedUser);

        $this->assertSame(['is_active' => true], $auditLog->old_values);
        $this->assertSame(['is_active' => false], $auditLog->new_values);
    }

    public function test_authorized_user_can_activate_inactive_user(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => false]);
        $managedUser->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->patch(route('users.activate', $managedUser));

        $response->assertRedirect(route('users.index'));

        $this->assertTrue($managedUser->fresh()->is_active);
    }

    public function test_activating_user_creates_user_activated_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['user.update']);
        $role = $this->createRole('Reporter / Employee', 'reporter-employee');
        $managedUser = User::factory()->create(['is_active' => false]);
        $managedUser->roles()->sync([$role->id]);

        $this->actingAs($user)->patch(route('users.activate', $managedUser))
            ->assertRedirect(route('users.index'));

        $auditLog = $this->latestAuditLogFor('user.activated', $managedUser);

        $this->assertSame(['is_active' => false], $auditLog->old_values);
        $this->assertSame(['is_active' => true], $auditLog->new_values);
    }

    public function test_current_user_cannot_deactivate_self(): void
    {
        $user = $this->createUserWithPermissions(['user.delete']);

        $response = $this->actingAs($user)->patch(route('users.deactivate', $user));

        $response->assertSessionHasErrors('user');

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $manager = $this->createUserWithPermissions(['user.delete']);
        $superAdminRole = $this->createRole('Super Admin', 'super-admin');
        $superAdmin = User::factory()->create([
            'name' => 'Only Super Admin',
            'is_active' => true,
        ]);
        $superAdmin->roles()->sync([$superAdminRole->id]);

        $response = $this->actingAs($manager)->patch(route('users.deactivate', $superAdmin));

        $response->assertSessionHasErrors('is_active');

        $this->assertTrue($superAdmin->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'user.deactivated',
            'auditable_id' => $superAdmin->id,
        ]);
    }

    public function test_last_active_super_admin_role_cannot_be_removed(): void
    {
        $manager = $this->createUserWithPermissions(['user.update']);
        $superAdminRole = $this->createRole('Super Admin', 'super-admin');
        $securityManagerRole = $this->createRole('Security Manager', 'security-manager');
        $superAdmin = User::factory()->create([
            'name' => 'Only Super Admin',
            'email' => 'only.super.admin@example.com',
            'is_active' => true,
        ]);
        $superAdmin->roles()->sync([$superAdminRole->id]);

        $response = $this->actingAs($manager)->patch(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role_ids' => [$securityManagerRole->id],
        ]);

        $response->assertSessionHasErrors('role_ids');

        $superAdmin->refresh();

        $this->assertTrue($superAdmin->roles()->whereKey($superAdminRole->id)->exists());
        $this->assertFalse($superAdmin->roles()->whereKey($securityManagerRole->id)->exists());
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'user.roles_synced',
            'auditable_id' => $superAdmin->id,
        ]);
    }

    /**
     * Create an active user with one active role and optional permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $role = $this->createRole(
            'User Management Test Role '.$this->roleSequence,
            'user-management-test-role-'.$this->roleSequence,
        );

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $permissionSlug): int => $this->ensurePermissionExists($permissionSlug)->id)
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createRole(string $name, string $slug): Role
    {
        $this->roleSequence++;

        return Role::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function ensurePermissionsExist(array $permissionSlugs): void
    {
        collect($permissionSlugs)->each(fn (string $permissionSlug): Permission => $this->ensurePermissionExists($permissionSlug));
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

    private function latestAuditLogFor(string $event, User $user): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
