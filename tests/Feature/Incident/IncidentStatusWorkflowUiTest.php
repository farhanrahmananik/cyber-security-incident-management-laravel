<?php

namespace Tests\Feature\Incident;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentStatusTransition;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentStatusWorkflowUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_with_incident_status_update_can_see_status_workflow_form_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'incident.status.update',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($manager)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Incident Status Workflow');
        $response->assertSee('Update Status');
        $response->assertSee('name="status"', false);
        $response->assertSee('name="notes"', false);
        $response->assertSee(route('incidents.status.update', $incident), false);
    }

    public function test_reporter_employee_cannot_see_status_workflow_form(): void
    {
        $this->createPermission('incident.status.update');

        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee', [
            'incident.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Incident Status Workflow');
        $response->assertDontSee('name="status"', false);
        $response->assertDontSee(route('incidents.status.update', $incident), false);
    }

    public function test_available_transition_option_is_visible_for_valid_next_status(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'incident.status.update',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($manager)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('value="triaged"', false);
        $response->assertSee('Triaged');
    }

    public function test_unavailable_transition_option_is_not_visible(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'incident.status.update',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($manager)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('value="investigating"', false);
        $response->assertDontSee('value="closed"', false);
    }

    public function test_status_history_is_visible_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
        ]);
        $changer = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter, ['status' => 'triaged']);
        $createdAt = CarbonImmutable::parse('2026-06-24 10:15:00');

        $transition = IncidentStatusTransition::query()->create([
            'incident_id' => $incident->id,
            'changed_by_id' => $changer->id,
            'from_status' => 'reported',
            'to_status' => 'triaged',
            'notes' => 'SOC reviewed the report and confirmed triage.',
        ]);
        $transition->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Status Transition History');
        $response->assertSee('Reported');
        $response->assertSee('Triaged');
        $response->assertSee($changer->name);
        $response->assertSee('SOC reviewed the report and confirmed triage.');
        $response->assertSee('2026-06-24 10:15');
    }

    public function test_no_history_empty_state_is_visible_when_no_transitions_exist(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('No status transitions recorded yet.');
    }

    public function test_user_without_incident_close_does_not_see_closed_transition_option_for_resolved_incident(): void
    {
        $this->createPermission('incident.close');

        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'incident.view',
            'incident.status.update',
        ]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'resolved']);

        $response = $this->actingAs($analyst)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('value="investigating"', false);
        $response->assertDontSee('value="closed"', false);
    }

    public function test_user_with_incident_close_can_see_closed_transition_option_for_resolved_incident(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'incident.status.update',
            'incident.close',
        ]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'resolved']);

        $response = $this->actingAs($manager)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('value="closed"', false);
        $response->assertSee('Closed');
    }

    /**
     * Create an active role-backed user with optional active permissions.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithRoleAndPermissions(string $roleSlug, array $permissionSlugs = []): User
    {
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

        $user = User::factory()->create(['is_active' => true]);
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
            'category' => IncidentCategory::query()->firstOrCreate(
                ['slug' => 'phishing'],
                [
                    'name' => 'Phishing',
                    'sort_order' => 10,
                    'is_active' => true,
                ],
            ),
            'severity' => SeverityLevel::query()->firstOrCreate(
                ['slug' => 'high'],
                [
                    'name' => 'High',
                    'sort_order' => 30,
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

    /**
     * Create a persisted incident for the given reporter.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createIncidentFor(User $reporter, array $overrides = []): Incident
    {
        $taxonomy = $this->createTaxonomy();
        $nextNumber = Incident::query()->withTrashed()->count() + 1;

        return Incident::query()->create(array_merge([
            'incident_number' => 'INC-20260624-'.str_pad((string) (9600 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
