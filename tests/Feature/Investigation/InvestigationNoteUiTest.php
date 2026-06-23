<?php

namespace Tests\Feature\Investigation;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\InvestigationNote;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationNoteUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_permitted_user_can_see_investigation_notes_panel_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Investigation Notes');
        $response->assertSee('Internal SOC notes for triage, analysis, and response tracking.');
    }

    public function test_reporter_employee_cannot_see_investigation_notes_panel(): void
    {
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee', ['incident.view']);
        $author = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createInvestigationNote($incident, $author, [
            'note' => 'Internal SOC-only investigation note.',
        ]);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('Investigation Notes');
        $response->assertDontSee('Internal SOC-only investigation note.');
    }

    public function test_existing_investigation_note_body_and_author_are_visible_to_permitted_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
        ]);
        $author = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $createdAt = CarbonImmutable::parse('2026-06-23 15:45:00');
        $note = $this->createInvestigationNote($incident, $author, [
            'note' => 'Confirmed suspicious login and preserved related telemetry.',
        ]);
        $note->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Confirmed suspicious login and preserved related telemetry.');
        $response->assertSee($author->name);
        $response->assertSee('2026-06-23 15:45');
    }

    public function test_empty_state_is_visible_when_no_investigation_notes_exist(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('No investigation notes have been recorded yet.');
    }

    public function test_create_form_is_visible_to_user_with_create_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
            'investigation-note.create',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Add Investigation Note');
        $response->assertSee('Add Note');
        $response->assertSee('name="note"', false);
        $response->assertSee(route('incidents.investigation-notes.store', $incident), false);
    }

    public function test_edit_and_delete_controls_are_visible_to_user_with_update_and_delete_permissions(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
            'investigation-note.update',
            'investigation-note.delete',
        ]);
        $author = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $author);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertSee(route('incidents.investigation-notes.update', [$incident, $note]), false);
        $response->assertSee(route('incidents.investigation-notes.destroy', [$incident, $note]), false);
    }

    public function test_edit_and_delete_controls_are_hidden_from_user_without_update_or_delete_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'investigation-note.view',
            'investigation-note.create',
        ]);
        $author = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $note = $this->createInvestigationNote($incident, $author);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Investigation Notes');
        $response->assertDontSee(route('incidents.investigation-notes.update', [$incident, $note]), false);
        $response->assertDontSee(route('incidents.investigation-notes.destroy', [$incident, $note]), false);
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9400 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
            'note' => 'Initial SOC investigation note.',
        ], $overrides));
    }
}
