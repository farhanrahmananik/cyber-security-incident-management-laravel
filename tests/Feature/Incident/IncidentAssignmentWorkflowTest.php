<?php

namespace Tests\Feature\Incident;

use App\Models\AuditLog;
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

class IncidentAssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_assign_incident_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_incident_assign_permission_cannot_assign_incident(): void
    {
        $this->createPermission('incident.assign');
        $reporter = User::factory()->create(['is_active' => true]);
        $manager = $this->createUserWithRoleAndPermissions('security-manager');
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($manager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('incident_assignments', [
            'incident_id' => $incident->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_assign_incident_to_active_soc_analyst(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-23 13:00:00'));

        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
            'notes' => 'Assigning for initial triage.',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Incident assigned successfully.');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'current_assigned_to_id' => $analyst->id,
        ]);
        $this->assertDatabaseHas('incident_assignments', [
            'incident_id' => $incident->id,
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $securityManager->id,
            'notes' => 'Assigning for initial triage.',
        ]);

        $assignment = IncidentAssignment::query()->firstOrFail();
        $this->assertTrue($assignment->assigned_at->isSameMinute(now()));
    }

    public function test_first_assignment_creates_incident_assigned_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
            'notes' => 'Assigning for initial triage.',
        ])->assertRedirect(route('incidents.show', $incident));

        $assignment = IncidentAssignment::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('incident.assigned', $incident);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['previous_assignee_id' => null], $auditLog->old_values);
        $this->assertSame([
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $securityManager->id,
            'assignment_id' => $assignment->id,
        ], $auditLog->new_values);
    }

    public function test_reassignment_creates_incident_reassigned_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $firstAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $secondAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $firstAnalyst->id,
        ]);
        IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $firstAnalyst->id,
            'assigned_by_id' => $securityManager->id,
            'notes' => 'Existing assignment.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 09:00:00'),
        ]);

        $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $secondAnalyst->id,
            'notes' => 'Reassigning for deeper analysis.',
        ])->assertRedirect(route('incidents.show', $incident));

        $assignment = IncidentAssignment::query()
            ->where('assigned_to_id', $secondAnalyst->id)
            ->firstOrFail();
        $auditLog = $this->latestAuditLogFor('incident.reassigned', $incident);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['previous_assignee_id' => $firstAnalyst->id], $auditLog->old_values);
        $this->assertSame([
            'assigned_to_id' => $secondAnalyst->id,
            'assigned_by_id' => $securityManager->id,
            'assignment_id' => $assignment->id,
        ], $auditLog->new_values);
    }

    public function test_assignment_updates_current_assignee_and_creates_history_record(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
            'notes' => 'Escalated to SOC queue.',
        ]);

        $incident->refresh();
        $assignment = IncidentAssignment::query()->firstOrFail();

        $this->assertSame($analyst->id, $incident->current_assigned_to_id);
        $this->assertSame($incident->id, $assignment->incident_id);
        $this->assertSame($analyst->id, $assignment->assigned_to_id);
        $this->assertSame($securityManager->id, $assignment->assigned_by_id);
        $this->assertSame('Escalated to SOC queue.', $assignment->notes);
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_inactive_user_cannot_be_assigned(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $inactiveAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst', [], false);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $inactiveAnalyst->id,
        ]);

        $response->assertSessionHasErrors('assigned_to_id');
        $this->assertNull($incident->refresh()->current_assigned_to_id);
        $this->assertDatabaseMissing('incident_assignments', [
            'incident_id' => $incident->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reporter_employee_cannot_assign_incident(): void
    {
        $this->createPermission('incident.assign');
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
        ]);

        $response->assertForbidden();
        $this->assertNull($incident->refresh()->current_assigned_to_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_with_incident_assign_cannot_assign_incident_to_normal_reporter_employee(): void
    {
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $reporter->id,
        ]);

        $response->assertSessionHasErrors('assigned_to_id');
        $this->assertNull($incident->refresh()->current_assigned_to_id);
        $this->assertDatabaseMissing('incident_assignments', [
            'incident_id' => $incident->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assigning_same_current_assignee_returns_validation_error_without_duplicate_history(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', ['incident.assign']);
        $analyst = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);
        IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $securityManager->id,
            'notes' => 'Existing assignment.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 09:00:00'),
        ]);

        $response = $this->actingAs($securityManager)->post(route('incidents.assign', $incident), [
            'assigned_to_id' => $analyst->id,
            'notes' => 'Duplicate assignment attempt.',
        ]);

        $response->assertSessionHasErrors('assigned_to_id');
        $this->assertDatabaseCount('incident_assignments', 1);
        $this->assertDatabaseMissing('incident_assignments', [
            'notes' => 'Duplicate assignment attempt.',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
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
            'incident_number' => 'INC-20260623-9101',
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
            'status' => 'reported',
        ], $overrides));
    }

    private function latestAuditLogFor(string $event, Incident $incident): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $incident->getMorphClass())
            ->where('auditable_id', $incident->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
