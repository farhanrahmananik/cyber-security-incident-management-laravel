<?php

namespace Tests\Feature\ResponseAction;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\ResponseAction;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponseActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_create_response_action_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->post(route('incidents.response-actions.store', $incident), $this->validResponseActionPayload());

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_employee_cannot_create_response_action(): void
    {
        $this->createPermission('response-action.manage');

        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'title' => 'Reporter Response Attempt',
            ]),
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('response_actions', [
            'title' => 'Reporter Response Attempt',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_create_response_action(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'title' => 'Isolate Affected Endpoint',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Response action added successfully.');
        $this->assertDatabaseHas('response_actions', [
            'incident_id' => $incident->id,
            'performed_by' => $securityManager->id,
            'action_type' => 'containment',
            'title' => 'Isolate Affected Endpoint',
        ]);
    }

    public function test_soc_analyst_can_create_response_action(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $socAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($socAnalyst)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'action_type' => 'monitoring',
                'title' => 'Increase Detection Monitoring',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('response_actions', [
            'incident_id' => $incident->id,
            'performed_by' => $socAnalyst->id,
            'action_type' => 'monitoring',
            'title' => 'Increase Detection Monitoring',
        ]);
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.response-actions.store', $incident), []);

        $response->assertSessionHasErrors(['action_type', 'status', 'title']);
        $this->assertDatabaseCount('response_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_for_invalid_action_type(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'action_type' => 'unsupported_action',
            ]),
        );

        $response->assertSessionHasErrors('action_type');
        $this->assertDatabaseCount('response_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_for_invalid_status(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'status' => 'waiting_for_magic',
            ]),
        );

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('response_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_when_completed_at_is_before_started_at(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'started_at' => '2026-06-23 12:00:00',
                'completed_at' => '2026-06-23 11:00:00',
            ]),
        );

        $response->assertSessionHasErrors('completed_at');
        $this->assertDatabaseCount('response_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_create_stores_incident_id_and_performed_by_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload(),
        );

        $responseAction = ResponseAction::query()->firstOrFail();

        $this->assertSame($incident->id, $responseAction->incident_id);
        $this->assertSame($securityManager->id, $responseAction->performed_by);
    }

    public function test_creating_response_action_creates_response_action_created_audit_log_without_raw_description(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $description = 'Sensitive response action details that should not be dumped into audit values.';

        $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'title' => 'Isolate Affected Endpoint',
                'description' => $description,
            ]),
        )->assertRedirect(route('incidents.show', $incident));

        $responseAction = ResponseAction::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('response_action.created', $responseAction);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'incident_id' => $incident->id,
            'action_type' => 'containment',
            'status' => 'planned',
            'title' => 'Isolate Affected Endpoint',
            'performed_by' => $securityManager->id,
            'started_at' => '2026-06-23 08:30:00',
            'completed_at' => '2026-06-23 09:45:00',
            'description_present' => true,
            'description_length' => strlen($description),
        ], $auditLog->new_values);
        $this->assertStringNotContainsString($description, json_encode($auditLog->new_values, JSON_UNESCAPED_SLASHES));
    }

    public function test_create_stores_valid_response_action_details_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(
            route('incidents.response-actions.store', $incident),
            $this->validResponseActionPayload([
                'action_type' => 'eradication',
                'status' => 'completed',
                'title' => ' Remove Malicious Artifact ',
                'description' => ' Removed persistence entry from affected endpoint. ',
                'started_at' => '2026-06-23 08:30:00',
                'completed_at' => '2026-06-23 09:45:00',
            ]),
        );

        $responseAction = ResponseAction::query()->firstOrFail();

        $this->assertSame('eradication', $responseAction->action_type);
        $this->assertSame('completed', $responseAction->status);
        $this->assertSame('Remove Malicious Artifact', $responseAction->title);
        $this->assertSame('Removed persistence entry from affected endpoint.', $responseAction->description);
        $this->assertSame('2026-06-23 08:30:00', $responseAction->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-23 09:45:00', $responseAction->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_permitted_user_can_update_response_action(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $securityManager, [
            'title' => 'Initial Response Action',
        ]);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.response-actions.update', [$incident, $responseAction]),
            $this->validResponseActionPayload([
                'action_type' => 'recovery',
                'status' => 'in_progress',
                'title' => 'Restore Clean Service State',
                'description' => 'Recovery work is in progress.',
            ]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Response action updated successfully.');
        $this->assertDatabaseHas('response_actions', [
            'id' => $responseAction->id,
            'action_type' => 'recovery',
            'status' => 'in_progress',
            'title' => 'Restore Clean Service State',
            'description' => 'Recovery work is in progress.',
        ]);
    }

    public function test_updating_response_action_creates_response_action_updated_audit_log_without_raw_description(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $originalDescription = 'Original sensitive response action details.';
        $updatedDescription = 'Updated sensitive response action details.';
        $responseAction = $this->createResponseAction($incident, $securityManager, $this->validResponseActionPayload([
            'description' => $originalDescription,
        ]));

        $this->actingAs($securityManager)->patch(
            route('incidents.response-actions.update', [$incident, $responseAction]),
            $this->validResponseActionPayload([
                'status' => 'in_progress',
                'title' => 'Restore Clean Service State',
                'description' => $updatedDescription,
            ]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('response_action.updated', $responseAction);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame('planned', $auditLog->old_values['status']);
        $this->assertSame('in_progress', $auditLog->new_values['status']);
        $this->assertSame('Isolate Affected Endpoint', $auditLog->old_values['title']);
        $this->assertSame('Restore Clean Service State', $auditLog->new_values['title']);
        $this->assertFalse($auditLog->old_values['description_changed']);
        $this->assertTrue($auditLog->new_values['description_changed']);
        $this->assertSame(strlen($originalDescription), $auditLog->old_values['description_length']);
        $this->assertSame(strlen($updatedDescription), $auditLog->new_values['description_length']);

        $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($originalDescription, $auditPayload);
        $this->assertStringNotContainsString($updatedDescription, $auditPayload);
    }

    public function test_permitted_user_can_delete_response_action(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $securityManager);

        $response = $this->actingAs($securityManager)->delete(
            route('incidents.response-actions.destroy', [$incident, $responseAction]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Response action deleted successfully.');
        $this->assertDatabaseMissing('response_actions', [
            'id' => $responseAction->id,
        ]);
    }

    public function test_deleting_response_action_creates_response_action_deleted_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $securityManager);

        $this->actingAs($securityManager)->delete(
            route('incidents.response-actions.destroy', [$incident, $responseAction]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('response_action.deleted', $responseAction);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['deleted' => false], $auditLog->old_values);
        $this->assertSame(['deleted' => true], $auditLog->new_values);
    }

    public function test_cannot_update_response_action_through_a_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $securityManager, [
            'title' => 'Original Response Action',
        ]);

        $this->actingAs($securityManager)
            ->patch(route('incidents.response-actions.update', [$otherIncident, $responseAction]), $this->validResponseActionPayload([
                'title' => 'Wrong Incident Update',
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('response_actions', [
            'id' => $responseAction->id,
            'incident_id' => $incident->id,
            'title' => 'Original Response Action',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cannot_delete_response_action_through_a_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $securityManager);

        $this->actingAs($securityManager)
            ->delete(route('incidents.response-actions.destroy', [$otherIncident, $responseAction]))
            ->assertNotFound();

        $this->assertDatabaseHas('response_actions', [
            'id' => $responseAction->id,
            'incident_id' => $incident->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
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
                'group_name' => 'response actions',
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9900 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
     * Create a persisted response action for the given incident.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createResponseAction(Incident $incident, User $performedBy, array $overrides = []): ResponseAction
    {
        return ResponseAction::factory()->create(array_merge([
            'incident_id' => $incident->id,
            'performed_by' => $performedBy->id,
            'action_type' => 'containment',
            'status' => 'planned',
            'title' => 'Isolate Affected Endpoint',
        ], $overrides));
    }

    /**
     * Build a valid response action request payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validResponseActionPayload(array $overrides = []): array
    {
        return array_merge([
            'action_type' => 'containment',
            'status' => 'planned',
            'title' => 'Isolate Affected Endpoint',
            'description' => 'Network access was restricted while the endpoint was reviewed.',
            'started_at' => '2026-06-23 08:30:00',
            'completed_at' => '2026-06-23 09:45:00',
        ], $overrides);
    }

    private function latestAuditLogFor(string $event, ResponseAction $responseAction): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $responseAction->getMorphClass())
            ->where('auditable_id', $responseAction->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
