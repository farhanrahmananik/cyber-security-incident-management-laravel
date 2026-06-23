<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedLayoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authenticated_user_with_dashboard_view_can_see_dashboard_layout(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cyber Security Incident Management');
        $response->assertSee('Dashboard');
        $response->assertSee('Authentication Ready');
        $response->assertSee('RBAC Ready');
        $response->assertSee('Authorization Middleware Ready');
        $response->assertSee('Next Planned Module');
    }

    public function test_sidebar_shows_dashboard_for_dashboard_view_permission(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.route('dashboard').'"', false);
        $response->assertSee('Dashboard');
    }

    public function test_sidebar_hides_reports_without_report_view_permission(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Reports');
    }

    public function test_sidebar_shows_reports_with_report_view_permission(): void
    {
        $user = $this->createUserWithPermissions([
            'dashboard.view',
            'report.view',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Reports');
        $response->assertSee('Planned');
    }

    public function test_topbar_shows_authenticated_user_name_email_and_roles(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view'], [
            'name' => 'Security Manager',
            'email' => 'manager@example.com',
        ], 'security-manager', 'Security Manager');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Security Manager');
        $response->assertSee('manager@example.com');
    }

    public function test_logout_form_exists_in_authenticated_layout(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('action="'.route('logout').'"', false);
        $response->assertSee('Logout');
    }

    /**
     * Create an active user with a role and the given permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     * @param  array<string, mixed>  $userAttributes
     */
    private function createUserWithPermissions(
        array $permissionSlugs,
        array $userAttributes = [],
        string $roleSlug = 'soc-analyst',
        string $roleName = 'SOC Analyst',
    ): User {
        $role = Role::query()->create([
            'name' => $roleName,
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

        $user = User::factory()->create($userAttributes + ['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
