<?php

namespace Tests\Feature\Incident;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authenticated_permitted_user_can_open_create_incident_page(): void
    {
        $user = $this->createUserWithPermissions(['incident.create']);
        $this->createTaxonomy();

        $response = $this->actingAs($user)->get(route('incidents.create'));

        $response->assertOk();
        $response->assertSee('Report Incident');
    }

    public function test_authenticated_permitted_user_can_submit_an_incident(): void
    {
        $user = $this->createUserWithPermissions(['incident.create', 'incident.view']);
        $taxonomy = $this->createTaxonomy();

        $response = $this->actingAs($user)->post(route('incidents.store'), $this->incidentPayload($taxonomy));

        $incident = Incident::query()->firstOrFail();

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'reporter_id' => $user->id,
            'title' => 'Suspicious email reported',
            'status' => 'reported',
        ]);
    }

    public function test_creating_incident_creates_incident_created_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['incident.create', 'incident.view']);
        $taxonomy = $this->createTaxonomy();

        $this->actingAs($user)->post(route('incidents.store'), $this->incidentPayload($taxonomy))
            ->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('incident.created', $incident);

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame([
            'incident_number' => $incident->incident_number,
            'title' => 'Suspicious email reported',
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'reporter_id' => $user->id,
            'status' => 'reported',
            'affected_system' => 'Corporate Email',
            'occurred_at' => '2026-06-23 09:00:00',
            'detected_at' => '2026-06-23 09:30:00',
        ], $auditLog->new_values);
        $this->assertArrayNotHasKey('description', $auditLog->new_values);
    }

    public function test_incident_number_is_generated(): void
    {
        $user = $this->createUserWithPermissions(['incident.create', 'incident.view']);
        $taxonomy = $this->createTaxonomy();

        $this->actingAs($user)->post(route('incidents.store'), $this->incidentPayload($taxonomy));

        $incident = Incident::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^INC-\d{8}-0001$/', $incident->incident_number);
    }

    public function test_validation_errors_happen_when_required_fields_are_missing(): void
    {
        $user = $this->createUserWithPermissions(['incident.create']);

        $response = $this->actingAs($user)->post(route('incidents.store'), []);

        $response->assertSessionHasErrors([
            'title',
            'description',
            'incident_category_id',
            'severity_level_id',
            'priority_level_id',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reporter_can_view_own_incident(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee($incident->title);
        $response->assertSee($incident->incident_number);
    }

    public function test_other_reporter_cannot_view_another_reporters_incident(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $otherReporter = User::factory()->create(['is_active' => true]);
        $otherReporter->roles()->sync($reporter->roles()->pluck('roles.id')->all());
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($otherReporter)->get(route('incidents.show', $incident));

        $response->assertForbidden();
    }

    public function test_reporter_can_update_own_reported_incident_when_permitted(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.view', 'incident.update'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->put(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'title' => 'Updated endpoint malware alert',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Updated endpoint malware alert',
        ]);
    }

    public function test_updating_safe_incident_fields_creates_incident_updated_audit_log(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.view', 'incident.update'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter, [
            'title' => 'Original endpoint alert',
            'affected_system' => 'Original endpoint',
        ]);

        $this->actingAs($reporter)->put(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'title' => 'Updated endpoint malware alert',
                'affected_system' => 'Updated workstation',
            ]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident.updated', $incident);

        $this->assertSame($reporter->id, $auditLog->user_id);
        $this->assertSame([
            'title' => 'Original endpoint alert',
            'affected_system' => 'Original endpoint',
        ], $auditLog->old_values);
        $this->assertSame([
            'title' => 'Updated endpoint malware alert',
            'affected_system' => 'Updated workstation',
        ], $auditLog->new_values);
    }

    public function test_updating_description_does_not_dump_raw_long_description_in_audit_values(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.view', 'incident.update'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter, [
            'description' => 'Original compact description.',
        ]);
        $longDescription = str_repeat('Sensitive incident narrative ', 50);

        $this->actingAs($reporter)->put(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'description' => $longDescription,
            ]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident.updated', $incident);
        $encodedValues = json_encode([
            'old' => $auditLog->old_values,
            'new' => $auditLog->new_values,
        ]);

        $this->assertSame(['description_changed' => false], $auditLog->old_values);
        $this->assertSame(['description_changed' => true], $auditLog->new_values);
        $this->assertStringNotContainsString($longDescription, (string) $encodedValues);
        $this->assertStringNotContainsString('Original compact description.', (string) $encodedValues);
    }

    public function test_reporter_cannot_update_own_incident_after_reported_status(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.view', 'incident.update'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter, ['status' => 'triaged']);

        $response = $this->actingAs($reporter)->put(
            route('incidents.update', $incident),
            $this->incidentUpdatePayload($incident, [
                'title' => 'Unauthorized status update attempt',
            ]),
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Endpoint malware alert',
            'status' => 'triaged',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reporter_cannot_delete_own_incident_even_with_delete_permission(): void
    {
        $reporter = $this->createUserWithPermissions(
            ['incident.view', 'incident.delete'],
            'reporter-employee',
            'Reporter / Employee',
        );
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->delete(route('incidents.destroy', $incident));

        $response->assertForbidden();
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_soc_analyst_can_view_incident_list(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);
        $socAnalyst = $this->createUserWithPermissions(['incident.view'], 'soc-analyst', 'SOC Analyst');

        $response = $this->actingAs($socAnalyst)->get(route('incidents.index'));

        $response->assertOk();
        $response->assertSee($incident->title);
    }

    public function test_soc_analyst_cannot_delete_incident_even_with_delete_permission(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);
        $socAnalyst = $this->createUserWithPermissions(
            ['incident.view', 'incident.delete'],
            'soc-analyst',
            'SOC Analyst',
        );

        $response = $this->actingAs($socAnalyst)->delete(route('incidents.destroy', $incident));

        $response->assertForbidden();
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_delete_incident_when_rbac_allows_it(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);
        $securityManager = $this->createUserWithPermissions(
            ['incident.view', 'incident.delete'],
            'security-manager',
            'Security Manager',
        );

        $response = $this->actingAs($securityManager)->delete(route('incidents.destroy', $incident));

        $response->assertRedirect(route('incidents.index'));
        $this->assertSoftDeleted('incidents', [
            'id' => $incident->id,
        ]);
    }

    public function test_deleting_incident_creates_incident_deleted_audit_log(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);
        $securityManager = $this->createUserWithPermissions(
            ['incident.view', 'incident.delete'],
            'security-manager',
            'Security Manager',
        );

        $this->actingAs($securityManager)->delete(route('incidents.destroy', $incident))
            ->assertRedirect(route('incidents.index'));

        $auditLog = $this->latestAuditLogFor('incident.deleted', $incident);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['deleted_at' => null], $auditLog->old_values);
        $this->assertNotNull($auditLog->new_values['deleted_at'] ?? null);
    }

    public function test_permitted_operational_roles_can_view_incident_list(): void
    {
        $reporter = $this->createUserWithPermissions(['incident.view'], 'reporter-employee', 'Reporter / Employee');
        $incident = $this->createIncidentFor($reporter);

        $superAdmin = $this->createUserWithPermissions([], 'super-admin', 'Super Admin');
        $securityManager = $this->createUserWithPermissions(['incident.view'], 'security-manager', 'Security Manager');
        $socAnalyst = $this->createUserWithPermissions(['incident.view'], 'soc-analyst', 'SOC Analyst');

        foreach ([$superAdmin, $securityManager, $socAnalyst] as $user) {
            $response = $this->actingAs($user)->get(route('incidents.index'));

            $response->assertOk();
            $response->assertSee($incident->title);
        }
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
            ->map(function (string $permissionSlug): int {
                return Permission::query()->firstOrCreate(
                    ['slug' => $permissionSlug],
                    [
                        'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                        'group_name' => 'incidents',
                        'is_active' => true,
                    ],
                )->id;
            })
            ->all();

        if ($permissionIds !== []) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * Create active taxonomy records needed by incident forms.
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
     * Build a valid incident request payload.
     *
     * @param  array{category: IncidentCategory, severity: SeverityLevel, priority: PriorityLevel}  $taxonomy
     * @return array<string, mixed>
     */
    private function incidentPayload(array $taxonomy): array
    {
        return [
            'title' => 'Suspicious email reported',
            'description' => 'A user reported a suspicious email with an attachment.',
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'impact_summary' => 'Potential phishing attempt affecting one mailbox.',
            'affected_system' => 'Corporate Email',
            'occurred_at' => '2026-06-23T09:00',
            'detected_at' => '2026-06-23T09:30',
        ];
    }

    /**
     * Build a valid incident update payload.
     *
     * @param  array<string, mixed>  $overrides
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
            'incident_number' => 'INC-20260623-9001',
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
