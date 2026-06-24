<?php

namespace Tests\Feature\ResponseAction;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ResponseActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $permissionSlugs = [
        'response-action.view',
        'response-action.manage',
    ];

    public function test_response_action_permissions_are_seeded(): void
    {
        $this->seed();

        foreach ($this->permissionSlugs as $permissionSlug) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $permissionSlug,
                'group_name' => 'response actions',
                'is_active' => true,
            ]);
        }
    }

    public function test_security_manager_has_response_action_permissions(): void
    {
        $this->seed();

        $securityManager = $this->createUserForRole('security-manager');

        foreach ($this->permissionSlugs as $permissionSlug) {
            $this->assertTrue($securityManager->hasPermission($permissionSlug));
            $this->assertTrue(Gate::forUser($securityManager)->allows($permissionSlug));
        }
    }

    public function test_soc_analyst_has_response_action_permissions(): void
    {
        $this->seed();

        $socAnalyst = $this->createUserForRole('soc-analyst');

        foreach ($this->permissionSlugs as $permissionSlug) {
            $this->assertTrue($socAnalyst->hasPermission($permissionSlug));
            $this->assertTrue(Gate::forUser($socAnalyst)->allows($permissionSlug));
        }
    }

    public function test_reporter_employee_does_not_have_response_action_permissions(): void
    {
        $this->seed();

        $reporter = $this->createUserForRole('reporter-employee');

        foreach ($this->permissionSlugs as $permissionSlug) {
            $this->assertFalse($reporter->hasPermission($permissionSlug));
            $this->assertFalse(Gate::forUser($reporter)->allows($permissionSlug));
        }
    }

    public function test_super_admin_global_authorization_behavior_remains_unchanged(): void
    {
        $this->seed();

        $superAdmin = User::query()
            ->where('email', 'admin@example.com')
            ->firstOrFail();

        $this->assertTrue($superAdmin->hasRole('super-admin'));
        $this->assertSame(Permission::query()->count(), $superAdmin->roles()->firstOrFail()->permissions()->count());

        foreach ($this->permissionSlugs as $permissionSlug) {
            $this->assertTrue($superAdmin->hasPermission($permissionSlug));
            $this->assertTrue(Gate::forUser($superAdmin)->allows($permissionSlug));
        }
    }

    private function createUserForRole(string $roleSlug): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create(['is_active' => true]);

        $user->roles()->sync([$role->id]);

        return $user->fresh()->load('roles.permissions');
    }
}
