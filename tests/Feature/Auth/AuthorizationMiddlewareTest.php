<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Route::middleware(['auth', 'role:security-manager'])
            ->get('/authorization-test/role', fn (): string => 'role authorized')
            ->name('authorization.test.role');

        Route::middleware(['auth', 'permission:incident.assign'])
            ->get('/authorization-test/permission', fn (): string => 'permission authorized')
            ->name('authorization.test.permission');

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_user_with_dashboard_view_can_access_dashboard(): void
    {
        $user = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'dashboard.view',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Authorization active');
        $response->assertDontSee('Incident assignment access available.');
    }

    public function test_user_without_dashboard_view_gets_forbidden_on_dashboard(): void
    {
        Permission::query()->create([
            'name' => 'Dashboard View',
            'slug' => 'dashboard.view',
            'group_name' => 'dashboard',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRoleAndPermissions('reporter-employee');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_dashboard_through_gate_before(): void
    {
        $user = $this->createUserWithRoleAndPermissions('super-admin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Authorization active');
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $user = $this->createUserWithRoleAndPermissions('security-manager');

        $response = $this->actingAs($user)->get(route('authorization.test.role'));

        $response->assertOk();
        $response->assertSee('role authorized');
    }

    public function test_role_middleware_denies_non_matching_role(): void
    {
        $user = $this->createUserWithRoleAndPermissions('reporter-employee');

        $response = $this->actingAs($user)->get(route('authorization.test.role'));

        $response->assertForbidden();
    }

    public function test_permission_middleware_allows_matching_permission(): void
    {
        $user = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'incident.assign',
        ]);

        $response = $this->actingAs($user)->get(route('authorization.test.permission'));

        $response->assertOk();
        $response->assertSee('permission authorized');
    }

    public function test_permission_middleware_denies_missing_permission(): void
    {
        Permission::query()->create([
            'name' => 'Incident Assign',
            'slug' => 'incident.assign',
            'group_name' => 'incidents',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRoleAndPermissions('reporter-employee');

        $response = $this->actingAs($user)->get(route('authorization.test.permission'));

        $response->assertForbidden();
    }

    public function test_dashboard_can_directives_render_permission_specific_content(): void
    {
        $user = $this->createUserWithRoleAndPermissions('security-manager', [
            'dashboard.view',
            'incident.assign',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Authorization active');
        $response->assertSee('Incident assignment access available.');
    }

    /**
     * Create an active user with one active role and optional active permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithRoleAndPermissions(string $roleSlug, array $permissionSlugs = []): User
    {
        $role = Role::query()->create([
            'name' => str($roleSlug)->replace('-', ' ')->title()->toString(),
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        $permissionIds = collect($permissionSlugs)
            ->map(function (string $permissionSlug): int {
                return Permission::query()->create([
                    'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                    'slug' => $permissionSlug,
                    'group_name' => str($permissionSlug)->before('.')->toString(),
                    'is_active' => true,
                ])->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
