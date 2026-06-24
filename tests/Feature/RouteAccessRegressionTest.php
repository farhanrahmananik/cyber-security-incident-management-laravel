<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RouteAccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login_for_protected_application_pages(): void
    {
        foreach ($this->protectedPageRoutes() as $routeName) {
            $this->get(route($routeName))
                ->assertRedirect(route('login'));
        }
    }

    public function test_user_with_required_permissions_can_access_main_application_routes(): void
    {
        $user = $this->createUserWithPermissions([
            'dashboard.view',
            'incident.view',
            'incident.create',
            'report.view',
        ]);

        foreach ($this->protectedPageRoutes() as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_user_without_required_permissions_receives_forbidden_for_main_application_routes(): void
    {
        $this->ensurePermissionsExist([
            'dashboard.view',
            'incident.view',
            'incident.create',
            'report.view',
        ]);

        $user = $this->createUserWithPermissions([]);

        foreach ($this->protectedPageRoutes() as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_permitted_user_can_access_incident_edit_route(): void
    {
        $user = $this->createUserWithPermissions(['incident.update'], 'security-manager', 'Security Manager');
        $incident = $this->createIncident();

        $response = $this->actingAs($user)->get(route('incidents.edit', $incident));

        $response->assertOk();
        $response->assertSee('Edit Incident');
    }

    public function test_guest_is_redirected_to_login_for_security_report_export(): void
    {
        $response = $this->get(route('reports.security.export'));

        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_security_report_export(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $incident = $this->createIncident([
            'incident_number' => 'INC-20260624-9001',
            'title' => 'Route access export incident',
        ]);

        $response = $this->actingAs($user)->get(route('reports.security.export'));

        $response->assertOk();
        $this->assertCsvDownloadHeaders($response);
        $this->assertStringContainsString($incident->incident_number, $response->streamedContent());
    }

    public function test_user_without_required_permission_cannot_access_security_report_export(): void
    {
        $this->ensurePermissionsExist(['report.view']);
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('reports.security.export'));

        $response->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function protectedPageRoutes(): array
    {
        return [
            'dashboard',
            'incidents.index',
            'incidents.create',
            'reports.security.index',
        ];
    }

    /**
     * Create an active user with one active role and optional active permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(
        array $permissionSlugs,
        string $roleSlug = 'route-access-test-role',
        string $roleName = 'Route Access Test Role',
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => $roleName,
                'is_active' => true,
            ],
        );

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $permissionSlug): int => $this->ensurePermissionExists($permissionSlug)->id)
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createIncident(array $overrides = []): Incident
    {
        $taxonomy = $this->createTaxonomy();
        $reporter = User::factory()->create(['is_active' => true]);

        return Incident::query()->create(array_merge([
            'incident_number' => 'INC-20260624-'.str_pad((string) (9000 + Incident::query()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Route access regression incident',
            'description' => 'Regression fixture for protected route access.',
            'status' => 'reported',
        ], $overrides));
    }

    /**
     * @return array{category: IncidentCategory, severity: SeverityLevel, priority: PriorityLevel}
     */
    private function createTaxonomy(): array
    {
        return [
            'category' => IncidentCategory::query()->firstOrCreate(
                ['slug' => 'phishing'],
                [
                    'name' => 'Phishing',
                    'sort_order' => 10,
                    'is_active' => true,
                ],
            ),
            'severity' => SeverityLevel::query()->firstOrCreate(
                ['slug' => 'critical'],
                [
                    'name' => 'Critical',
                    'sort_order' => 40,
                    'is_active' => true,
                ],
            ),
            'priority' => PriorityLevel::query()->firstOrCreate(
                ['slug' => 'urgent'],
                [
                    'name' => 'Urgent',
                    'sort_order' => 40,
                    'is_active' => true,
                ],
            ),
        ];
    }

    private function assertCsvDownloadHeaders(TestResponse $response): void
    {
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('security-reports-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
    }
}
