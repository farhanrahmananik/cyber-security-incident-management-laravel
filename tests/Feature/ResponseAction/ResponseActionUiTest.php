<?php

namespace Tests\Feature\ResponseAction;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEvidence;
use App\Models\IncidentIoc;
use App\Models\InvestigationNote;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\ResponseAction;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponseActionUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_permitted_user_can_see_response_actions_panel_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Response Actions');
        $response->assertSee('Track containment, eradication, recovery, communication, monitoring, and lessons learned work.');
    }

    public function test_reporter_employee_cannot_see_response_actions_panel(): void
    {
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee', ['incident.view']);
        $performer = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createResponseAction($incident, $performer, [
            'title' => 'Internal Containment Action',
        ]);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('Response Actions');
        $response->assertDontSee('Internal Containment Action');
    }

    public function test_existing_response_action_title_type_status_and_performer_are_visible_to_permitted_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
        ]);
        $performer = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $createdAt = CarbonImmutable::parse('2026-06-23 15:45:00');
        $responseAction = $this->createResponseAction($incident, $performer, [
            'action_type' => 'containment',
            'status' => 'completed',
            'title' => 'Isolate Affected Endpoint',
            'description' => 'Endpoint was isolated from the network during containment.',
            'started_at' => CarbonImmutable::parse('2026-06-23 14:00:00'),
            'completed_at' => CarbonImmutable::parse('2026-06-23 15:30:00'),
        ]);
        $responseAction->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Isolate Affected Endpoint');
        $response->assertSee('Containment');
        $response->assertSee('Completed');
        $response->assertSee('Endpoint was isolated from the network during containment.');
        $response->assertSee($performer->name);
        $response->assertSee('2026-06-23 14:00');
        $response->assertSee('2026-06-23 15:30');
        $response->assertSee('2026-06-23 15:45');
    }

    public function test_empty_state_is_visible_when_no_response_actions_exist(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('No response actions have been recorded yet.');
    }

    public function test_create_form_is_visible_to_user_with_response_action_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
            'response-action.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Add Response Action');
        $response->assertSee('name="action_type"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="started_at"', false);
        $response->assertSee('name="completed_at"', false);
        $response->assertSee(route('incidents.response-actions.store', $incident), false);
    }

    public function test_edit_and_delete_controls_are_visible_to_user_with_response_action_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
            'response-action.manage',
        ]);
        $performer = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $performer);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertSee(route('incidents.response-actions.update', [$incident, $responseAction]), false);
        $response->assertSee(route('incidents.response-actions.destroy', [$incident, $responseAction]), false);
    }

    public function test_edit_and_delete_controls_are_hidden_from_user_without_response_action_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
        ]);
        $performer = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $responseAction = $this->createResponseAction($incident, $performer);
        $content = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $content->assertOk();
        $content->assertSee('Response Actions');
        $content->assertDontSee(route('incidents.response-actions.update', [$incident, $responseAction]), false);
        $content->assertDontSee(route('incidents.response-actions.destroy', [$incident, $responseAction]), false);
        $this->assertStringNotContainsString('Delete this response action?', $content->getContent());
    }

    public function test_response_actions_panel_does_not_break_existing_investigation_notes_ioc_and_evidence_panel_visibility(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'response-action.view',
            'evidence.view',
            'ioc.view',
            'investigation-note.view',
        ]);
        $actor = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createResponseAction($incident, $actor, [
            'title' => 'Response action remains visible beside SOC panels.',
        ]);
        $this->createIncidentEvidence($incident, $actor, [
            'title' => 'Evidence remains visible beside response actions.',
        ]);
        $this->createIncidentIoc($incident, $actor, [
            'value' => '198.51.100.88',
        ]);
        $this->createInvestigationNote($incident, $actor, [
            'note' => 'SOC note remains visible beside response actions.',
        ]);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Response Actions');
        $response->assertSee('Response action remains visible beside SOC panels.');
        $response->assertSee('Evidence / Attachments');
        $response->assertSee('Evidence remains visible beside response actions.');
        $response->assertSee('Indicators of Compromise');
        $response->assertSee('198.51.100.88');
        $response->assertSee('Investigation Notes');
        $response->assertSee('SOC note remains visible beside response actions.');
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
                    str_starts_with($permissionSlug, 'response-action.') => 'response actions',
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
            'description' => 'Initial response action for the incident.',
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
