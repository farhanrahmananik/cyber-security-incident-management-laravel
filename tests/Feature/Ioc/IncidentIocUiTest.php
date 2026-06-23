<?php

namespace Tests\Feature\Ioc;

use App\Models\Incident;
use App\Models\IncidentCategory;
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

class IncidentIocUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_permitted_user_can_see_ioc_panel_on_incident_show_page(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Indicators of Compromise');
        $response->assertSee('Incident-linked observables for threat analysis, enrichment, and response tracking.');
    }

    public function test_reporter_employee_cannot_see_ioc_panel(): void
    {
        $reporter = $this->createUserWithRoleAndPermissions('reporter-employee', ['incident.view']);
        $creator = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createIncidentIoc($incident, $creator, [
            'value' => '198.51.100.50',
        ]);

        $response = $this->actingAs($reporter)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertDontSee('Indicators of Compromise');
        $response->assertDontSee('198.51.100.50');
    }

    public function test_existing_ioc_type_value_confidence_and_creator_are_visible_to_permitted_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
        ]);
        $creator = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $createdAt = CarbonImmutable::parse('2026-06-23 15:45:00');
        $ioc = $this->createIncidentIoc($incident, $creator, [
            'type' => 'url',
            'value' => 'https://malicious.example/payload',
            'confidence' => 'high',
            'description' => 'Payload URL observed in phishing message.',
        ]);
        $ioc->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('URL');
        $response->assertSee('https://malicious.example/payload');
        $response->assertSee('High Confidence');
        $response->assertSee($creator->name);
        $response->assertSee('Payload URL observed in phishing message.');
        $response->assertSee('2026-06-23 15:45');
    }

    public function test_empty_state_is_visible_when_no_iocs_exist(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('No indicators of compromise recorded yet.');
    }

    public function test_create_form_is_visible_to_user_with_ioc_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
            'ioc.manage',
        ]);
        $incident = $this->createIncidentFor($reporter);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Add IOC');
        $response->assertSee('name="type"', false);
        $response->assertSee('name="value"', false);
        $response->assertSee(route('incidents.iocs.store', $incident), false);
    }

    public function test_edit_and_delete_controls_are_visible_to_user_with_ioc_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
            'ioc.manage',
        ]);
        $creator = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $creator);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertSee(route('incidents.iocs.update', [$incident, $ioc]), false);
        $response->assertSee(route('incidents.iocs.destroy', [$incident, $ioc]), false);
    }

    public function test_edit_and_delete_controls_are_hidden_from_user_without_ioc_manage_permission(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
        ]);
        $creator = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $ioc = $this->createIncidentIoc($incident, $creator);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Indicators of Compromise');
        $response->assertDontSee(route('incidents.iocs.update', [$incident, $ioc]), false);
        $response->assertDontSee(route('incidents.iocs.destroy', [$incident, $ioc]), false);
    }

    public function test_ioc_panel_does_not_break_existing_investigation_notes_panel_visibility(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $viewer = $this->createUserWithRoleAndPermissions('security-manager', [
            'incident.view',
            'ioc.view',
            'investigation-note.view',
        ]);
        $creator = $this->createUserWithRoleAndPermissions('soc-analyst');
        $incident = $this->createIncidentFor($reporter);
        $this->createIncidentIoc($incident, $creator);
        $this->createInvestigationNote($incident, $creator, [
            'note' => 'SOC note remains visible beside IOC panel.',
        ]);

        $response = $this->actingAs($viewer)->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Indicators of Compromise');
        $response->assertSee('Investigation Notes');
        $response->assertSee('SOC note remains visible beside IOC panel.');
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
                'group_name' => str_starts_with($permissionSlug, 'ioc.')
                    ? 'indicators of compromise'
                    : 'investigations',
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
