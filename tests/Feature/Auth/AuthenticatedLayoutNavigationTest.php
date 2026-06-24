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
        $response->assertSee('Dashboard metrics are calculated from real incident records');
        $response->assertSee('Total Incidents');
        $response->assertSee('Recent Incidents');
        $response->assertSee('Incidents by Status');
        $response->assertSee('data-dashboard-metric="total_incidents"', false);
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
        $response->assertDontSee('Security Reports');
    }

    public function test_sidebar_shows_reports_with_report_view_permission(): void
    {
        $user = $this->createUserWithPermissions([
            'dashboard.view',
            'report.view',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Security Reports');
        $response->assertSee('href="'.route('reports.security.index').'"', false);
    }

    public function test_sidebar_hides_embedded_modules_and_keeps_planned_badges_for_unimplemented_modules(): void
    {
        $user = $this->createUserWithPermissions([
            'audit-log.view',
            'dashboard.view',
            'evidence.view',
            'ioc.view',
            'investigation-note.view',
            'report.view',
            'role.view',
            'response-action.view',
            'user.view',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<nav class="sidebar-nav" aria-label="Primary navigation">(.*?)<\/nav>/s',
            $content,
        );

        preg_match('/<nav class="sidebar-nav" aria-label="Primary navigation">(.*?)<\/nav>/s', $content, $matches);
        $sidebarContent = $matches[1];

        $this->assertStringNotContainsString('Investigation Notes', $sidebarContent);
        $this->assertStringNotContainsString('IOC Management', $sidebarContent);
        $this->assertStringNotContainsString('Evidence', $sidebarContent);
        $this->assertStringNotContainsString('Response Actions', $sidebarContent);
        $this->assertStringContainsString('User Management', $sidebarContent);
        $this->assertStringContainsString('href="'.route('users.index').'"', $sidebarContent);

        $plannedModules = [
            'Role &amp; Permission',
            'Audit Logs',
        ];

        foreach ($plannedModules as $plannedModule) {
            $this->assertMatchesRegularExpression(
                sprintf('/<span>%s<\/span>\s*<span class="planned-label">Planned<\/span>/', preg_quote($plannedModule, '/')),
                $sidebarContent,
            );
        }
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
