<?php

namespace Tests\Feature\Incident;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentStatusTransition;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_attempt_status_update_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->patch(route('incidents.update', $incident), [
            'status' => 'triaged',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame('reported', $incident->refresh()->status);
    }

    public function test_user_without_incident_update_permission_cannot_attempt_status_update(): void
    {
        $this->createPermission('incident.update');
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->patch(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, ['status' => 'triaged']),
        );

        $response->assertForbidden();
        $this->assertSame('reported', $incident->refresh()->status);
    }

    public function test_general_incident_update_route_does_not_persist_status_changes(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'status' => 'triaged',
                'title' => 'Updated incident title without status change',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Updated incident title without status change',
            'status' => 'reported',
        ]);
    }

    public function test_invalid_status_value_is_not_persisted_by_general_incident_update_route(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'status' => 'not-a-real-status',
                'title' => 'Valid field update with invalid status payload',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Valid field update with invalid status payload',
            'status' => 'reported',
        ]);
    }

    public function test_status_only_update_fails_validation_and_preserves_existing_fields(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'title' => 'Original incident title',
            'description' => 'Original incident description.',
            'impact_summary' => 'Original impact summary.',
            'affected_system' => 'Original endpoint',
        ]);

        $response = $this->actingAs($securityManager)->patch(route('incidents.update', $incident), [
            'status' => 'triaged',
        ]);

        $response->assertSessionHasErrors([
            'title',
            'description',
            'incident_category_id',
            'severity_level_id',
            'priority_level_id',
        ]);
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Original incident title',
            'description' => 'Original incident description.',
            'impact_summary' => 'Original impact summary.',
            'affected_system' => 'Original endpoint',
            'status' => 'reported',
        ]);
    }

    public function test_reporter_cannot_bypass_reported_status_update_restriction_with_status_payload(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.update'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter, [
            'status' => 'triaged',
            'title' => 'Triaged reporter incident',
        ]);

        $response = $this->actingAs($reporter)->patch(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'status' => 'reported',
                'title' => 'Reporter bypass attempt',
            ]),
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Triaged reporter incident',
            'status' => 'triaged',
        ]);
    }

    public function test_authorized_security_manager_can_transition_reported_to_triaged(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'triaged',
            'notes' => 'Initial SOC triage completed.',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'triaged',
        ]);
    }

    public function test_invalid_status_is_rejected_by_dedicated_status_workflow(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'not-a-real-status',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('reported', $incident->refresh()->status);
        $this->assertDatabaseCount('incident_status_transitions', 0);
    }

    public function test_invalid_transition_is_rejected_by_dedicated_status_workflow(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update', 'incident.close'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'closed',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('reported', $incident->refresh()->status);
        $this->assertDatabaseCount('incident_status_transitions', 0);
    }

    public function test_reporter_employee_cannot_transition_status(): void
    {
        $this->createPermission('incident.status.update');
        $reporter = $this->createUserWithPermissions(
            [],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->patch(route('incidents.status.update', $incident), [
            'status' => 'triaged',
        ]);

        $response->assertForbidden();
        $this->assertSame('reported', $incident->refresh()->status);
        $this->assertDatabaseCount('incident_status_transitions', 0);
    }

    public function test_soc_analyst_can_perform_normal_status_transition(): void
    {
        $socAnalyst = $this->createUserWithPermissions(
            ['incident.status.update'],
            'soc-analyst',
            'SOC Analyst',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'triaged']);

        $response = $this->actingAs($socAnalyst)->patch(route('incidents.status.update', $incident), [
            'status' => 'investigating',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertSame('investigating', $incident->refresh()->status);
    }

    public function test_soc_analyst_cannot_close_resolved_incident(): void
    {
        $this->createPermission('incident.close');
        $socAnalyst = $this->createUserWithPermissions(
            ['incident.status.update'],
            'soc-analyst',
            'SOC Analyst',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'resolved']);

        $response = $this->actingAs($socAnalyst)->patch(route('incidents.status.update', $incident), [
            'status' => 'closed',
        ]);

        $response->assertForbidden();
        $this->assertSame('resolved', $incident->refresh()->status);
        $this->assertDatabaseCount('incident_status_transitions', 0);
    }

    public function test_security_manager_can_close_resolved_incident(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update', 'incident.close'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'resolved']);

        $response = $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'closed',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertSame('closed', $incident->refresh()->status);
    }

    public function test_transition_creates_incident_status_transition_row(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'triaged',
            'notes' => 'Triage details kept in workflow history.',
        ]);

        $this->assertDatabaseHas('incident_status_transitions', [
            'incident_id' => $incident->id,
            'changed_by_id' => $securityManager->id,
            'from_status' => 'reported',
            'to_status' => 'triaged',
            'notes' => 'Triage details kept in workflow history.',
        ]);

        $this->assertTrue($incident->statusTransitions()->first() instanceof IncidentStatusTransition);
    }

    public function test_transition_records_status_changed_audit_log(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'triaged',
            'notes' => 'This note should not be copied into audit payload values.',
        ]);

        $auditLog = AuditLog::query()
            ->where('event', 'incident.status_changed')
            ->where('auditable_type', $incident->getMorphClass())
            ->where('auditable_id', $incident->id)
            ->firstOrFail();

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['status' => 'reported'], $auditLog->old_values);
        $this->assertSame(['status' => 'triaged'], $auditLog->new_values);
        $this->assertStringNotContainsString(
            'This note should not be copied',
            json_encode($auditLog->new_values, JSON_THROW_ON_ERROR),
        );
    }

    public function test_closed_incident_cannot_transition_further(): void
    {
        $securityManager = $this->createUserWithPermissions(
            ['incident.status.update', 'incident.close'],
            'security-manager',
            'Security Manager',
        );
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, ['status' => 'closed']);

        $response = $this->actingAs($securityManager)->patch(route('incidents.status.update', $incident), [
            'status' => 'investigating',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('closed', $incident->refresh()->status);
        $this->assertDatabaseCount('incident_status_transitions', 0);
    }

    /**
     * Create an active user with an active role and permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(
        array $permissionSlugs,
        string $roleSlug = 'reporter-employee',
        string $roleName = 'Reporter / Employee',
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => $roleName,
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
     * @return array<string, mixed>
     */
    private function incidentUpdatePayload(Incident $incident, array $overrides = []): array
    {
        return array_merge([
            'title' => $incident->title,
            'description' => $incident->description,
            'incident_category_id' => $incident->incident_category_id,
            'severity_level_id' => $incident->severity_level_id,
            'priority_level_id' => $incident->priority_level_id,
            'impact_summary' => $incident->impact_summary,
            'affected_system' => $incident->affected_system,
            'occurred_at' => $incident->occurred_at?->format('Y-m-d\TH:i'),
            'detected_at' => $incident->detected_at?->format('Y-m-d\TH:i'),
        ], $overrides);
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
            'incident_number' => 'INC-20260624-9101',
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
            'impact_summary' => 'Potential endpoint compromise.',
            'affected_system' => 'Corporate endpoint',
            'status' => 'reported',
        ], $overrides));
    }
}
