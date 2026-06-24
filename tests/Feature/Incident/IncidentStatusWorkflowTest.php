<?php

namespace Tests\Feature\Incident;

use App\Models\Incident;
use App\Models\IncidentCategory;
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
