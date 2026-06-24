<?php

namespace Tests\Feature\Evidence;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEvidence;
use App\Models\IncidentIoc;
use App\Models\InvestigationNote;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentEvidenceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_permitted_user_can_see_evidence_panel_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Evidence / Attachments');
        $response->assertSee('Store incident-related files privately with metadata and SHA-256 integrity tracking.');
    }

    public function test_reporter_employee_cannot_see_evidence_panel(): void
    {
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee', ['incident.view']);
        $uploader = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createIncidentEvidence($incident, $uploader, [
            'title' => 'Internal Evidence Attachment',
        ]);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('Evidence / Attachments');
        $response->assertDontSee('Internal Evidence Attachment');
    }

    public function test_existing_evidence_metadata_and_download_link_are_visible_to_permitted_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
        ]);
        $uploader = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $createdAt = CarbonImmutable::parse('2026-06-23 15:45:00');
        $checksum = str_repeat('a', 64);
        $evidence = $this->createIncidentEvidence($incident, $uploader, [
            'title' => 'Firewall Log Export',
            'description' => 'Exported firewall logs from the affected network segment.',
            'original_filename' => 'firewall-log-export.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1536,
            'checksum_sha256' => $checksum,
        ]);
        $evidence->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Firewall Log Export');
        $response->assertSee('Exported firewall logs from the affected network segment.');
        $response->assertSee('firewall-log-export.csv');
        $response->assertSee('text/csv');
        $response->assertSee('1.5 KB');
        $response->assertSee($uploader->name);
        $response->assertSee('2026-06-23 15:45');
        $response->assertSee($checksum);
        $response->assertSee(route('incidents.evidences.download', [$incident, $evidence]), false);
    }

    public function test_empty_state_is_visible_when_no_evidence_exists(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('No evidence attachments have been recorded yet.');
    }

    public function test_upload_form_is_visible_to_user_with_evidence_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
            'evidence.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Upload Evidence');
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="evidence_file"', false);
        $response->assertSee(route('incidents.evidences.store', $incident), false);
    }

    public function test_edit_and_delete_controls_are_visible_to_user_with_evidence_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
            'evidence.manage',
        ]);
        $uploader = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $uploader);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertSee(route('incidents.evidences.update', [$incident, $evidence]), false);
        $response->assertSee(route('incidents.evidences.destroy', [$incident, $evidence]), false);
    }

    public function test_edit_and_delete_controls_are_hidden_from_user_with_evidence_view_only(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
        ]);
        $uploader = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $evidence = $this->createIncidentEvidence($incident, $uploader);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('Evidence / Attachments');
        $response->assertSee(route('incidents.evidences.download', [$incident, $evidence]), false);
        $this->assertStringNotContainsString('value="PATCH"', $content);
        $this->assertStringNotContainsString('value="DELETE"', $content);
        $this->assertStringNotContainsString('Delete this evidence attachment?', $content);
    }

    public function test_evidence_panel_does_not_break_existing_investigation_notes_and_ioc_panel_visibility(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'evidence.view',
            'ioc.view',
            'investigation-note.view',
        ]);
        $actor = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createIncidentEvidence($incident, $actor, [
            'title' => 'Evidence remains visible beside SOC panels.',
        ]);
        $this->createIncidentIoc($incident, $actor, [
            'value' => '198.51.100.77',
        ]);
        $this->createInvestigationNote($incident, $actor, [
            'note' => 'SOC note remains visible beside evidence panel.',
        ]);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Evidence / Attachments');
        $response->assertSee('Evidence remains visible beside SOC panels.');
        $response->assertSee('Indicators of Compromise');
        $response->assertSee('198.51.100.77');
        $response->assertSee('Investigation Notes');
        $response->assertSee('SOC note remains visible beside evidence panel.');
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
                'group_name' => match (true) {
                    str_starts_with($permissionSlug, 'evidence.') => 'evidence',
                    str_starts_with($permissionSlug, 'ioc.') => 'indicators of compromise',
                    str_starts_with($permissionSlug, 'investigation-note.') => 'investigations',
                    default => 'incidents',
                },
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9800 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
            'title' => 'Firewall Log Export',
            'original_filename' => 'firewall-log-export.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1536,
            'checksum_sha256' => str_repeat('f', 64),
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
            'type' => 'ip_address',
            'value' => '192.0.2.10',
            'confidence' => 'medium',
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
            'note' => 'Initial SOC investigation note.',
        ], $overrides));
    }
}
