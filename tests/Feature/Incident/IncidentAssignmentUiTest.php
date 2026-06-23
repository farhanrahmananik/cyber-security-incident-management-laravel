<?php

namespace Tests\Feature\Incident;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentAssignmentUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_with_incident_assign_can_see_assignment_form_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'incident.assign',
        ]);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($manager)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Assign Analyst');
        $response->assertSee('name="assigned_to_id"', false);
        $response->assertSee(route('incidents.assign', $incident), false);
        $response->assertSee($analyst->name);
    }

    public function test_user_without_incident_assign_cannot_see_assignment_form(): void
    {
        $this->createPermission('incident.assign');

        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', ['incident.view']);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('name="assigned_to_id"', false);
        $response->assertDontSee(route('incidents.assign', $incident), false);
    }

    public function test_show_page_displays_unassigned_when_no_current_assignee_exists(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', ['incident.view']);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Current Assignee');
        $response->assertSee('Unassigned');
    }

    public function test_show_page_displays_current_assignee_name_after_assignment_exists(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', ['incident.view']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee($analyst->name);
        $response->assertSee($analyst->email);
    }

    public function test_show_page_displays_assignment_history_details(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', ['incident.view']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $assigner = $this->createUserWithRoleAndPermissions('security-manager');
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);

        IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Assigned for malware triage.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 14:30:00'),
        ]);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Assignment History');
        $response->assertSee($analyst->name);
        $response->assertSee($assigner->name);
        $response->assertSee('Assigned for malware triage.');
        $response->assertSee('2026-06-23 14:30');
    }

    /**
     * Create an active role-backed user with optional active permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithRoleAndPermissions(
        string $roleSlug,
        array $permissionSlugs = [],
        bool $isActive = true,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)->replace('-', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $permissionSlug): int => $this->createPermission($permissionSlug)->id)
            ->all();

        if ($permissionIds !== []) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $user = User::factory()->create(['is_active' => $isActive]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createPermission(string $permissionSlug): Permission
    {
        return Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                'group_name' => 'incidents',
                'is_active' => true,
            ],
        );
    }

    /**
     * Create active taxonomy records needed by incident records.
     *
     * @return array{category: IncidentCategory, severity: SeverityLevel, priority: PriorityLevel}
     */
    private function createTaxonomy(): array
    {
        return [
            'category' => IncidentCategory::query()->create([
                'name' => 'Phishing',
                'slug' => 'phishing',
                'sort_order' => 10,
                'is_active' => true,
            ]),
            'severity' => SeverityLevel::query()->create([
                'name' => 'High',
                'slug' => 'high',
                'sort_order' => 30,
                'is_active' => true,
            ]),
            'priority' => PriorityLevel::query()->create([
                'name' => 'Urgent',
                'slug' => 'urgent',
                'sort_order' => 40,
                'is_active' => true,
            ]),
        ];
    }

    /**
     * Create a persisted incident for the given reporter.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createIncidentFor(User $reporter, array $overrides = []): Incident
    {
        $taxonomy = $this->createTaxonomy();

        return Incident::query()->create(array_merge([
            'incident_number' => 'INC-20260623-9201',
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
            'status' => 'reported',
        ], $overrides));
    }
}
