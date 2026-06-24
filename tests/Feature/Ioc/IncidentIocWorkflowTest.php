<?php

namespace Tests\Feature\Ioc;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentIoc;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentIocWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_create_ioc_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->post(route('incidents.iocs.store', $incident), $this->validIocPayload());

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_employee_cannot_create_ioc(): void
    {
        $this->createPermission('ioc.manage');

        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'value' => '198.51.100.10',
        ]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('incident_iocs', [
            'value' => '198.51.100.10',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_create_ioc(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'value' => '203.0.113.25',
        ]));

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'IOC added successfully.');
        $this->assertDatabaseHas('incident_iocs', [
            'incident_id' => $incident->id,
            'created_by_id' => $securityManager->id,
            'type' => 'ip_address',
            'value' => '203.0.113.25',
        ]);
    }

    public function test_soc_analyst_can_create_ioc(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $socAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($socAnalyst)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'type' => 'domain',
            'value' => 'malicious.example',
        ]));

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incident_iocs', [
            'incident_id' => $incident->id,
            'created_by_id' => $socAnalyst->id,
            'type' => 'domain',
            'value' => 'malicious.example',
        ]);
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), []);

        $response->assertSessionHasErrors(['type', 'value']);
        $this->assertDatabaseCount('incident_iocs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_for_invalid_ioc_type(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'type' => 'unsupported_type',
        ]));

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('incident_iocs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_when_last_seen_at_is_before_first_seen_at(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'first_seen_at' => '2026-06-23 12:00:00',
            'last_seen_at' => '2026-06-23 11:00:00',
        ]));

        $response->assertSessionHasErrors('last_seen_at');
        $this->assertDatabaseCount('incident_iocs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_create_stores_incident_id_and_created_by_id_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload());

        $ioc = IncidentIoc::query()->firstOrFail();

        $this->assertSame($incident->id, $ioc->incident_id);
        $this->assertSame($securityManager->id, $ioc->created_by_id);
    }

    public function test_creating_ioc_creates_incident_ioc_created_audit_log_without_raw_value(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $iocValue = 'https://malicious.example/sensitive-path';
        $description = 'Sensitive IOC context that should not be dumped into audit values.';

        $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'type' => 'url',
            'value' => $iocValue,
            'description' => $description,
            'confidence' => 'high',
        ]))->assertRedirect(route('incidents.show', $incident));

        $ioc = IncidentIoc::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('incident_ioc.created', $ioc);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'incident_id' => $incident->id,
            'type' => 'url',
            'confidence' => 'high',
            'first_seen_at' => '2026-06-22 08:30:00',
            'last_seen_at' => '2026-06-23 09:45:00',
            'created_by_id' => $securityManager->id,
            'value_present' => true,
            'value_hash' => hash('sha256', $iocValue),
            'description_present' => true,
            'description_length' => strlen($description),
        ], $auditLog->new_values);

        $auditPayload = json_encode($auditLog->new_values, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($iocValue, $auditPayload);
        $this->assertStringNotContainsString($description, $auditPayload);
    }

    public function test_create_stores_valid_ioc_details_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'type' => 'file_hash',
            'value' => '44d88612fea8a8f36de82e1278abb02f',
            'description' => '  Hash observed in endpoint quarantine telemetry.  ',
            'confidence' => 'high',
            'first_seen_at' => '2026-06-22 08:30:00',
            'last_seen_at' => '2026-06-23 09:45:00',
        ]));

        $ioc = IncidentIoc::query()->firstOrFail();

        $this->assertSame('file_hash', $ioc->type);
        $this->assertSame('44d88612fea8a8f36de82e1278abb02f', $ioc->value);
        $this->assertSame('Hash observed in endpoint quarantine telemetry.', $ioc->description);
        $this->assertSame('high', $ioc->confidence);
        $this->assertSame('2026-06-22 08:30:00', $ioc->first_seen_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-23 09:45:00', $ioc->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_permitted_user_can_update_ioc(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $securityManager, [
            'value' => '192.0.2.10',
        ]);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.iocs.update', [$incident, $ioc]),
            $this->validIocPayload([
                'type' => 'url',
                'value' => 'https://malicious.example/payload',
                'confidence' => 'high',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'IOC updated successfully.');
        $this->assertDatabaseHas('incident_iocs', [
            'id' => $ioc->id,
            'type' => 'url',
            'value' => 'https://malicious.example/payload',
            'confidence' => 'high',
        ]);
    }

    public function test_updating_ioc_creates_incident_ioc_updated_audit_log_without_raw_values(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $originalValue = '192.0.2.10';
        $updatedValue = 'https://malicious.example/payload';
        $originalDescription = 'Original sensitive IOC context.';
        $updatedDescription = 'Updated sensitive IOC context.';
        $ioc = $this->createIncidentIoc($incident, $securityManager, $this->validIocPayload([
            'value' => $originalValue,
            'description' => $originalDescription,
        ]));

        $this->actingAs($securityManager)->patch(
            route('incidents.iocs.update', [$incident, $ioc]),
            $this->validIocPayload([
                'type' => 'url',
                'value' => $updatedValue,
                'description' => $updatedDescription,
            ]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident_ioc.updated', $ioc);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(hash('sha256', $originalValue), $auditLog->old_values['value_hash']);
        $this->assertSame(hash('sha256', $updatedValue), $auditLog->new_values['value_hash']);
        $this->assertFalse($auditLog->old_values['value_changed']);
        $this->assertTrue($auditLog->new_values['value_changed']);
        $this->assertSame(strlen($originalDescription), $auditLog->old_values['description_length']);
        $this->assertSame(strlen($updatedDescription), $auditLog->new_values['description_length']);

        $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($originalValue, $auditPayload);
        $this->assertStringNotContainsString($updatedValue, $auditPayload);
        $this->assertStringNotContainsString($originalDescription, $auditPayload);
        $this->assertStringNotContainsString($updatedDescription, $auditPayload);
    }

    public function test_permitted_user_can_delete_ioc(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $securityManager);

        $response = $this->actingAs($securityManager)->delete(route('incidents.iocs.destroy', [$incident, $ioc]));

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'IOC deleted successfully.');
        $this->assertDatabaseMissing('incident_iocs', [
            'id' => $ioc->id,
        ]);
    }

    public function test_deleting_ioc_creates_incident_ioc_deleted_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $securityManager);

        $this->actingAs($securityManager)
            ->delete(route('incidents.iocs.destroy', [$incident, $ioc]))
            ->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident_ioc.deleted', $ioc);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['deleted' => false], $auditLog->old_values);
        $this->assertSame(['deleted' => true], $auditLog->new_values);
    }

    public function test_cannot_update_ioc_through_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $securityManager, [
            'value' => '192.0.2.10',
        ]);

        $this->actingAs($securityManager)
            ->patch(route('incidents.iocs.update', [$otherIncident, $ioc]), $this->validIocPayload([
                'value' => '198.51.100.77',
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('incident_iocs', [
            'id' => $ioc->id,
            'incident_id' => $incident->id,
            'value' => '192.0.2.10',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cannot_delete_ioc_through_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $securityManager);

        $this->actingAs($securityManager)
            ->delete(route('incidents.iocs.destroy', [$otherIncident, $ioc]))
            ->assertNotFound();

        $this->assertDatabaseHas('incident_iocs', [
            'id' => $ioc->id,
            'incident_id' => $incident->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_ioc_value_supports_long_url_style_value_up_to_2048_characters_through_request_validation(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $longUrl = 'https://example.test/'.str_repeat('a', 2027);

        $response = $this->actingAs($securityManager)->post(route('incidents.iocs.store', $incident), $this->validIocPayload([
            'type' => 'url',
            'value' => $longUrl,
        ]));

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertSame(2048, strlen($longUrl));
        $this->assertDatabaseHas('incident_iocs', [
            'incident_id' => $incident->id,
            'created_by_id' => $securityManager->id,
            'type' => 'url',
            'value' => $longUrl,
        ]);
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
                'group_name' => 'indicators of compromise',
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9600 + $nextNumber), 4, '0', STR_PAD_LEFT),
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
            'status' => 'reported',
        ], $overrides));
    }

    /**
     * Create a persisted IOC for the given incident.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createIncidentIoc(Incident $incident, User $createdBy, array $overrides = []): IncidentIoc
    {
        return IncidentIoc::factory()->create(array_merge([
            'incident_id' => $incident->id,
            'created_by_id' => $createdBy->id,
        ], $overrides));
    }

    /**
     * Build a valid IOC request payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validIocPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'ip_address',
            'value' => '192.0.2.10',
            'description' => 'Suspicious source observed during incident triage.',
            'confidence' => 'medium',
            'first_seen_at' => '2026-06-22 08:30:00',
            'last_seen_at' => '2026-06-23 09:45:00',
        ], $overrides);
    }

    private function latestAuditLogFor(string $event, IncidentIoc $incidentIoc): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $incidentIoc->getMorphClass())
            ->where('auditable_id', $incidentIoc->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
