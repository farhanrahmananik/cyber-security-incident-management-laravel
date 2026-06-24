<?php

namespace Tests\Feature\Evidence;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEvidence;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentEvidenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_guest_cannot_upload_evidence_and_is_redirected_to_login(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->post(route('incidents.evidences.store', $incident), $this->validUploadPayload());

        $response->assertRedirect(route('login'));
    }

    public function test_reporter_employee_cannot_upload_evidence(): void
    {
        $this->createPermission('evidence.manage');

        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee');
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($reporter)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload([
            'title' => 'Reporter Upload Attempt',
        ]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('incident_evidences', [
            'title' => 'Reporter Upload Attempt',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_security_manager_can_upload_evidence(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload([
            'title' => 'Firewall Log Export',
        ]));

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Evidence uploaded successfully.');
        $this->assertDatabaseHas('incident_evidences', [
            'incident_id' => $incident->id,
            'uploaded_by_id' => $securityManager->id,
            'title' => 'Firewall Log Export',
        ]);
    }

    public function test_soc_analyst_can_upload_evidence(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $socAnalyst = $this->createUserWithRoleAndPermissions('soc-analyst', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($socAnalyst)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload([
            'title' => 'Endpoint Screenshot',
        ]));

        $response->assertRedirect(route('incidents.show', $incident));
        $this->assertDatabaseHas('incident_evidences', [
            'incident_id' => $incident->id,
            'uploaded_by_id' => $socAnalyst->id,
            'title' => 'Endpoint Screenshot',
        ]);
    }

    public function test_validation_fails_when_title_is_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $payload = $this->validUploadPayload();
        unset($payload['title']);

        $response = $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $payload);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('incident_evidences', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_fails_when_evidence_file_is_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $payload = $this->validUploadPayload();
        unset($payload['evidence_file']);

        $response = $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $payload);

        $response->assertSessionHasErrors('evidence_file');
        $this->assertDatabaseCount('incident_evidences', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_upload_stores_incident_id_and_uploaded_by_id_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload());

        $evidence = IncidentEvidence::query()->firstOrFail();

        $this->assertSame($incident->id, $evidence->incident_id);
        $this->assertSame($securityManager->id, $evidence->uploaded_by_id);
    }

    public function test_upload_stores_file_metadata_and_sha256_checksum(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $file = UploadedFile::fake()->create('firewall-log-export.csv', 12, 'text/csv');
        $expectedChecksum = hash_file('sha256', $file->getRealPath() ?: $file->getPathname());
        $expectedSize = $file->getSize();

        $this->assertNotFalse($expectedChecksum);

        $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload([
            'title' => '  Firewall Log Export  ',
            'description' => '  Firewall evidence for triage.  ',
            'evidence_file' => $file,
        ]));

        $evidence = IncidentEvidence::query()->firstOrFail();

        $this->assertSame('Firewall Log Export', $evidence->title);
        $this->assertSame('Firewall evidence for triage.', $evidence->description);
        $this->assertSame('firewall-log-export.csv', $evidence->original_filename);
        $this->assertStringStartsWith('incidents/'.$incident->id.'/evidences/', $evidence->stored_path);
        $this->assertSame('local', $evidence->disk);
        $this->assertSame('text/csv', $evidence->mime_type);
        $this->assertSame($expectedSize, $evidence->file_size);
        $this->assertSame($expectedChecksum, $evidence->checksum_sha256);
        Storage::disk('local')->assertExists($evidence->stored_path);
    }

    public function test_uploading_evidence_creates_incident_evidence_uploaded_audit_log_without_storage_path(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $this->actingAs($securityManager)->post(route('incidents.evidences.store', $incident), $this->validUploadPayload([
            'title' => 'Firewall Log Export',
            'description' => 'Sensitive evidence description.',
        ]))->assertRedirect(route('incidents.show', $incident));

        $evidence = IncidentEvidence::query()->firstOrFail();
        $auditLog = $this->latestAuditLogFor('incident_evidence.uploaded', $evidence);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'incident_id' => $incident->id,
            'title' => 'Firewall Log Export',
            'mime_type' => 'text/csv',
            'file_size' => $evidence->file_size,
            'checksum_sha256' => $evidence->checksum_sha256,
            'uploaded_by_id' => $securityManager->id,
        ], $auditLog->new_values);
        $this->assertArrayNotHasKey('stored_path', $auditLog->new_values);
        $this->assertArrayNotHasKey('disk', $auditLog->new_values);
        $this->assertStringNotContainsString('Sensitive evidence description.', json_encode($auditLog->new_values, JSON_UNESCAPED_SLASHES));
    }

    public function test_permitted_user_can_update_title_and_description(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager);

        $response = $this->actingAs($securityManager)->patch(
            route('incidents.evidences.update', [$incident, $evidence]),
            [
                'title' => ' Updated Firewall Log Export ',
                'description' => ' Updated metadata description. ',
            ],
        );

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Evidence updated successfully.');
        $this->assertDatabaseHas('incident_evidences', [
            'id' => $evidence->id,
            'title' => 'Updated Firewall Log Export',
            'description' => 'Updated metadata description.',
        ]);
    }

    public function test_updating_evidence_metadata_creates_incident_evidence_updated_audit_log_without_storage_path(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'title' => 'Firewall Log Export',
            'description' => 'Original sensitive evidence description.',
        ]);

        $this->actingAs($securityManager)->patch(
            route('incidents.evidences.update', [$incident, $evidence]),
            [
                'title' => 'Updated Firewall Log Export',
                'description' => 'Updated sensitive evidence description.',
            ],
        )->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident_evidence.updated', $evidence);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'title' => 'Firewall Log Export',
            'description_changed' => false,
        ], $auditLog->old_values);
        $this->assertSame([
            'title' => 'Updated Firewall Log Export',
            'description_changed' => true,
        ], $auditLog->new_values);
        $this->assertArrayNotHasKey('stored_path', $auditLog->old_values);
        $this->assertArrayNotHasKey('stored_path', $auditLog->new_values);

        $auditPayload = json_encode([$auditLog->old_values, $auditLog->new_values], JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('Original sensitive evidence description.', $auditPayload);
        $this->assertStringNotContainsString('Updated sensitive evidence description.', $auditPayload);
    }

    public function test_permitted_user_can_soft_delete_evidence_and_deleted_by_id_is_stored(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'stored_path' => 'incidents/'.$incident->id.'/evidences/firewall-log-export.csv',
        ]);
        Storage::disk('local')->put($evidence->stored_path, 'stored evidence content');

        $response = $this->actingAs($securityManager)->delete(route('incidents.evidences.destroy', [$incident, $evidence]));

        $response->assertRedirect(route('incidents.show', $incident));
        $response->assertSessionHas('success', 'Evidence deleted successfully.');
        $this->assertSoftDeleted('incident_evidences', [
            'id' => $evidence->id,
        ]);
        $this->assertDatabaseHas('incident_evidences', [
            'id' => $evidence->id,
            'deleted_by_id' => $securityManager->id,
        ]);
        Storage::disk('local')->assertExists($evidence->stored_path);
    }

    public function test_deleting_evidence_creates_incident_evidence_deleted_audit_log(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'stored_path' => 'incidents/'.$incident->id.'/evidences/firewall-log-export.csv',
        ]);
        Storage::disk('local')->put($evidence->stored_path, 'stored evidence content');

        $this->actingAs($securityManager)
            ->delete(route('incidents.evidences.destroy', [$incident, $evidence]))
            ->assertRedirect(route('incidents.show', $incident));

        $auditLog = $this->latestAuditLogFor('incident_evidence.deleted', $evidence);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'deleted_at' => null,
            'deleted_by_id' => null,
        ], $auditLog->old_values);
        $this->assertSame($securityManager->id, $auditLog->new_values['deleted_by_id']);
        $this->assertNotNull($auditLog->new_values['deleted_at']);
    }

    public function test_cannot_update_delete_or_download_evidence_through_a_different_incident_route(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.manage',
            'evidence.view',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $otherIncident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'title' => 'Firewall Log Export',
            'stored_path' => 'incidents/'.$incident->id.'/evidences/firewall-log-export.csv',
        ]);
        Storage::disk('local')->put($evidence->stored_path, 'stored evidence content');

        $this->actingAs($securityManager)
            ->patch(route('incidents.evidences.update', [$otherIncident, $evidence]), [
                'title' => 'Wrong Incident Update',
                'description' => 'Should not be saved.',
            ])
            ->assertNotFound();

        $this->actingAs($securityManager)
            ->delete(route('incidents.evidences.destroy', [$otherIncident, $evidence]))
            ->assertNotFound();

        $this->actingAs($securityManager)
            ->get(route('incidents.evidences.download', [$otherIncident, $evidence]))
            ->assertNotFound();

        $this->assertDatabaseHas('incident_evidences', [
            'id' => $evidence->id,
            'incident_id' => $incident->id,
            'title' => 'Firewall Log Export',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_permitted_user_can_download_evidence(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.view',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'original_filename' => 'firewall-log-export.csv',
            'stored_path' => 'incidents/'.$incident->id.'/evidences/firewall-log-export.csv',
        ]);
        Storage::disk('local')->put($evidence->stored_path, 'stored evidence content');

        $response = $this->actingAs($securityManager)->get(route('incidents.evidences.download', [$incident, $evidence]));

        $response->assertOk();
        $response->assertDownload('firewall-log-export.csv');

        $auditLog = $this->latestAuditLogFor('incident_evidence.downloaded', $evidence);

        $this->assertSame($securityManager->id, $auditLog->user_id);
        $this->assertSame([
            'incident_id' => $incident->id,
            'downloaded_by_id' => $securityManager->id,
        ], $auditLog->new_values);
    }

    public function test_download_returns_not_found_when_physical_file_is_missing(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $securityManager = $this->createUserWithRoleAndPermissions('security-manager', [
            'evidence.view',
        ]);
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $securityManager, [
            'stored_path' => 'incidents/'.$incident->id.'/evidences/missing-file.csv',
        ]);

        $this->actingAs($securityManager)
            ->get(route('incidents.evidences.download', [$incident, $evidence]))
            ->assertNotFound();

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
                'group_name' => 'evidence',
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9700 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
     * Create a persisted evidence record for the given incident.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createIncidentEvidence(Incident $incident, User $uploadedBy, array $overrides = []): IncidentEvidence
    {
        return IncidentEvidence::factory()->create(array_merge([
            'incident_id' => $incident->id,
            'uploaded_by_id' => $uploadedBy->id,
        ], $overrides));
    }

    /**
     * Build a valid evidence upload request payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUploadPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Firewall Log Export',
            'description' => 'Network firewall logs exported for incident triage.',
            'evidence_file' => UploadedFile::fake()->create('firewall-log-export.csv', 12, 'text/csv'),
        ], $overrides);
    }

    private function latestAuditLogFor(string $event, IncidentEvidence $incidentEvidence): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('auditable_type', $incidentEvidence->getMorphClass())
            ->where('auditable_id', $incidentEvidence->id)
            ->latest('created_at')
            ->firstOrFail();
    }
}
