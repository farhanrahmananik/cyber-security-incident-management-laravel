<?php

namespace Tests\Feature\Investigation;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\InvestigationNote;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationNoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_create_investigation_note_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->post(route('incidents.investigation-notes.store', $incident), [
            'note' => 'Initial investigation note.',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_employee_cannot_create_investigation_note(): void
    {
        $this->createPermission('investigation-note.create');

        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->post(route('incidents.investigation-notes.store', $incident), [
            'note' => 'Reporter should not be able to add investigation notes.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('investigation_notes', [
            'note' => 'Reporter should not be able to add investigation notes.',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_create_investigation_note(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.investigation-notes.store', $incident), [
            'note' => 'Reviewed the escalation and assigned SOC follow-up.',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Investigation note added successfully.');
        $this->assertDatabaseHas('investigation_notes', [
            'incident_id' => $incident->id,
            'author_id' => $securityManager->id,
            'note' => 'Reviewed the escalation and assigned SOC follow-up.',
        ]);
    }

    public function test_soc_analyst_can_create_investigation_note(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $socAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($socAnalyst)->post(route('incidents.investigation-notes.store', $incident), [
            'note' => 'Correlated endpoint telemetry with firewall events.',
        ]);

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('investigation_notes', [
            'incident_id' => $incident->id,
            'author_id' => $socAnalyst->id,
            'note' => 'Correlated endpoint telemetry with firewall events.',
        ]);
    }

    public function test_validation_fails_when_note_is_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.investigation-notes.store', $incident), []);

        $response->assertSessionHasErrors('note');
        $this->assertDatabaseCount('investigation_notes', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_create_stores_incident_id_and_author_id_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.investigation-notes.store', $incident), [
            'note' => 'Validated reported indicators against SIEM telemetry.',
        ]);

        $note = InvestigationNote::query()->firstOrFail();

        $this->assertSame($incident->id, $note->incident_id);
        $this->assertSame($securityManager->id, $note->author_id);
    }

    public function test_creating_note_creates_investigation_note_created_audit_log_without_raw_body(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $noteBody = 'Sensitive SOC investigation detail for audit redaction.';

        $this->actingAs($securityManager)->post(route('incidents.investigation-notes.store', $incident), [
            'note' => $noteBody,
        ])->assertRedirect(route('incidents.show', $incident));

        $note = InvestigationNote::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('investigation_note.created', $note);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'incident_id' => $incident->id,
            'author_id' => $securityManager->id,
            'body_present' => true,
            'body_length' => strlen($noteBody),
        ], $auditLog->new_values);
        $this->assertStringNotContainsString($noteBody, json_encode($auditLog->new_values, JSON_UNESCAPED_SLASHES));
    }

    public function test_permitted_user_can_update_investigation_note(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.update',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $securityManager, [
            'note' => 'Original investigation note.',
        ]);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.investigation-notes.update', [$incident, $note]),
            ['note' => 'Updated investigation note with confirmed IOC details.'],
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Investigation note updated successfully.');
        $this->assertDatabaseHas('investigation_notes', [
            'id' => $note->id,
            'note' => 'Updated investigation note with confirmed IOC details.',
        ]);
    }

    public function test_updating_note_creates_investigation_note_updated_audit_log_without_raw_body(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.update',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $originalBody = 'Original sensitive investigation note body.';
        $updatedBody = 'Updated sensitive investigation note body with containment details.';
        $note = $this->createInvestigationNote($incident, $securityManager, [
            'note' => $originalBody,
        ]);

        $this->actingAs($securityManager)->patch(
            route('incidents.investigation-notes.update', [$incident, $note]),
            ['note' => $updatedBody],
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('investigation_note.updated', $note);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'body_changed' => false,
            'body_length' => strlen($originalBody),
        ], $auditLog->old_values);
        $this->assertSame([
            'body_changed' => true,
            'body_length' => strlen($updatedBody),
        ], $auditLog->new_values);

        $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($originalBody, $auditPayload);
        $this->assertStringNotContainsString($updatedBody, $auditPayload);
    }

    public function test_permitted_user_can_delete_investigation_note(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.delete',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $securityManager, [
            'note' => 'Investigation note to remove.',
        ]);

        $response = $this->actingAs($securityManager)->delete(
            route('incidents.investigation-notes.destroy', [$incident, $note]),
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Investigation note deleted successfully.');
        $this->assertDatabaseMissing('investigation_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_deleting_note_creates_investigation_note_deleted_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.delete',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $securityManager);

        $this->actingAs($securityManager)->delete(
            route('incidents.investigation-notes.destroy', [$incident, $note]),
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('investigation_note.deleted', $note);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame(['deleted' => false], $auditLog->old_values);
        $this->assertSame(['deleted' => true], $auditLog->new_values);
    }

    public function test_cannot_update_or_delete_note_through_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'investigation-note.update',
            'investigation-note.delete',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $securityManager, [
            'note' => 'Original nested note.',
        ]);

        $this->actingAs($securityManager)
            ->patch(route('incidents.investigation-notes.update', [$otherIncident, $note]), [
                'note' => 'Unauthorized nested update.',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('investigation_notes', [
            'id' => $note->id,
            'incident_id' => $incident->id,
            'note' => 'Original nested note.',
        ]);

        $this->actingAs($securityManager)
            ->delete(route('incidents.investigation-notes.destroy', [$otherIncident, $note]))
            ->assertNotFound();

        $this->assertDatabaseHas('investigation_notes', [
            'id' => $note->id,
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
                'group_name' => 'investigations',
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9300 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
     * Create a persisted investigation note for the given incident.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createInvestigationNote(Incident $incident, User $author, array $overrides = []): InvestigationNote
    {
        return InvestigationNote::query()->create(array_merge([
            'incident_id' => $incident->id,
            'author_id' => $author->id,
            'note' => 'Initial investigation note.',
        ], $overrides));
    }

    private function latestAuditLogFor(string $event, InvestigationNote $note): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $note->getMorphClass())
            ->where('auditable_id', $note->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
