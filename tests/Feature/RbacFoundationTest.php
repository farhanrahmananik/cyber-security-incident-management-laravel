<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_seed_correctly(): void
    {
        $this->seed();

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(33, Permission::query()->count());

        $this->assertDatabaseHas('roles', [
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Reporter / Employee',
            'slug' => 'reporter-employee',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'incident.assign',
            'group_name' => 'incidents',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'audit-log.view',
            'group_name' => 'audit logs',
            'is_active' => true,
        ]);

        $superAdmin = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $securityManager = Role::query()->where('slug', 'security-manager')->firstOrFail();

        $this->assertSame(Permission::query()->count(), $superAdmin->permissions()->count());
        $this->assertFalse($securityManager->permissions()->where('slug', 'user.delete')->exists());
        $this->assertFalse($securityManager->permissions()->where('slug', 'role.delete')->exists());
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_super_admin_user_is_created_and_has_super_admin_role(): void
    {
        $this->seed();

        $admin = User::query()
            ->with('roles.permissions')
            ->where('email', 'admin@example.com')
            ->firstOrFail();

        $this->assertSame('Super Admin', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertTrue($admin->hasPermission('audit-log.view'));
    }

    public function test_user_has_role_checks_active_roles(): void
    {
        $analystRole = Role::query()->create([
            'name' => 'SOC Analyst',
            'slug' => 'soc-analyst',
            'is_active' => true,
        ]);

        $inactiveRole = Role::query()->create([
            'name' => 'Legacy Role',
            'slug' => 'legacy-role',
            'is_active' => false,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach([$analystRole->id, $inactiveRole->id]);

        $user = $user->fresh()->load('roles');

        $this->assertTrue($user->hasRole('soc-analyst'));
        $this->assertTrue($user->hasAnyRole(['missing-role', 'soc-analyst']));
        $this->assertFalse($user->hasRole('legacy-role'));
        $this->assertFalse($user->hasAnyRole(['missing-role', 'legacy-role']));
    }

    public function test_user_has_permission_through_active_role_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'SOC Analyst',
            'slug' => 'soc-analyst',
            'is_active' => true,
        ]);

        $activePermission = Permission::query()->create([
            'name' => 'Incident Investigate',
            'slug' => 'incident.investigate',
            'group_name' => 'incidents',
            'is_active' => true,
        ]);

        $inactivePermission = Permission::query()->create([
            'name' => 'Legacy Permission',
            'slug' => 'legacy.permission',
            'group_name' => 'legacy',
            'is_active' => false,
        ]);

        $role->permissions()->attach([$activePermission->id, $inactivePermission->id]);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $user = $user->fresh()->load('roles.permissions');

        $this->assertTrue($user->hasPermission('incident.investigate'));
        $this->assertFalse($user->hasPermission('legacy.permission'));
        $this->assertFalse($user->hasPermission('role.delete'));
    }
}
