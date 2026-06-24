<?php

namespace Tests\Feature\Dashboard;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authenticated_permitted_user_can_access_dashboard(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.view'], 'security-manager', 'Security Manager');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Welcome, '.$user->name);
    }

    public function test_dashboard_displays_real_incident_metric_values(): void
    {
        $securityManager = $this->createUserWithPermissions(['dashboard.view'], 'security-manager', 'Security Manager');
        $reporter = $this->createUserWithPermissions([], 'reporter-employee', 'Reporter / Employee');
        $analyst = $this->createUserWithPermissions([], 'soc-analyst', 'SOC Analyst');

        $incidentOne = $this->createIncidentFor($reporter, [
            'title' => 'Mailbox phishing report',
            'status' => 'reported',
        ]);
        $incidentTwo = $this->createIncidentFor($reporter, [
            'title' => 'Endpoint malware containment',
            'status' => 'investigating',
            'current_assigned_to_id' => $analyst->id,
        ]);
        $incidentThree = $this->createIncidentFor($reporter, [
            'title' => 'Resolved credential abuse',
            'status' => 'resolved',
        ]);

        InvestigationNote::query()->create([
            'incident_id' => $incidentOne->id,
            'author_id' => $analyst->id,
            'note' => 'Initial analysis note.',
        ]);
        InvestigationNote::query()->create([
            'incident_id' => $incidentTwo->id,
            'author_id' => $analyst->id,
            'note' => 'Containment note.',
        ]);
        IncidentIoc::factory()->create([
            'incident_id' => $incidentOne->id,
            'created_by_id' => $analyst->id,
        ]);
        IncidentEvidence::factory()->create([
            'incident_id' => $incidentTwo->id,
            'uploaded_by_id' => $analyst->id,
        ]);
        ResponseAction::factory()->create([
            'incident_id' => $incidentThree->id,
            'performed_by' => $securityManager->id,
        ]);

        $response = $this->actingAs($securityManager)->get(route('dashboard'));

        $response->assertOk();
        $this->assertMetricValue($response->getContent(), 'total_incidents', 3);
        $this->assertMetricValue($response->getContent(), 'active_incidents', 2);
        $this->assertMetricValue($response->getContent(), 'unassigned_incidents', 2);
        $this->assertMetricValue($response->getContent(), 'resolved_incidents', 1);
        $this->assertMetricValue($response->getContent(), 'total_investigation_notes', 2);
        $this->assertMetricValue($response->getContent(), 'total_iocs', 1);
        $this->assertMetricValue($response->getContent(), 'total_evidence_items', 1);
        $this->assertMetricValue($response->getContent(), 'total_response_actions', 1);
    }

    public function test_reporter_only_sees_own_incident_metrics(): void
    {
        $reporter = $this->createUserWithPermissions(['dashboard.view'], 'reporter-employee', 'Reporter / Employee');
        $otherReporter = $this->createUserWithPermissions([], 'reporter-employee', 'Reporter / Employee');

        $ownIncident = $this->createIncidentFor($reporter, [
            'title' => 'Reporter visible phishing report',
        ]);
        $otherIncident = $this->createIncidentFor($otherReporter, [
            'title' => 'Another employee incident',
        ]);

        $response = $this->actingAs($reporter)->get(route('dashboard'));

        $response->assertOk();
        $this->assertMetricValue($response->getContent(), 'total_incidents', 1);
        $response->assertSee($ownIncident->title);
        $response->assertDontSee($otherIncident->title);
    }

    public function test_security_manager_can_see_organization_wide_metrics(): void
    {
        $securityManager = $this->createUserWithPermissions(['dashboard.view'], 'security-manager', 'Security Manager');
        $reporterOne = User::factory()->create(['is_active' => true]);
        $reporterTwo = User::factory()->create(['is_active' => true]);

        $incidentOne = $this->createIncidentFor($reporterOne, [
            'title' => 'Organization incident one',
        ]);
        $incidentTwo = $this->createIncidentFor($reporterTwo, [
            'title' => 'Organization incident two',
        ]);

        $response = $this->actingAs($securityManager)->get(route('dashboard'));

        $response->assertOk();
        $this->assertMetricValue($response->getContent(), 'total_incidents', 2);
        $response->assertSee($incidentOne->title);
        $response->assertSee($incidentTwo->title);
        $response->assertSee('Analyst Workload');
    }

    public function test_soc_analyst_sees_assigned_incident_metrics(): void
    {
        $analyst = $this->createUserWithPermissions(['dashboard.view'], 'soc-analyst', 'SOC Analyst');
        $otherAnalyst = $this->createUserWithPermissions([], 'soc-analyst', 'SOC Analyst');
        $reporter = User::factory()->create(['is_active' => true]);

        $assignedIncident = $this->createIncidentFor($reporter, [
            'title' => 'Assigned endpoint malware case',
            'current_assigned_to_id' => $analyst->id,
        ]);
        $otherIncident = $this->createIncidentFor($reporter, [
            'title' => 'Other analyst incident',
            'current_assigned_to_id' => $otherAnalyst->id,
        ]);

        $response = $this->actingAs($analyst)->get(route('dashboard'));

        $response->assertOk();
        $this->assertMetricValue($response->getContent(), 'total_incidents', 1);
        $response->assertSee($assignedIncident->title);
        $response->assertDontSee($otherIncident->title);
        $response->assertDontSee('Analyst Workload');
    }

    public function test_dashboard_includes_recent_incident_information(): void
    {
        $securityManager = $this->createUserWithPermissions(['dashboard.view'], 'security-manager', 'Security Manager');
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-1001',
            'title' => 'Recent suspicious login alert',
            'status' => 'triaged',
        ]);

        $response = $this->actingAs($securityManager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($incident->incident_number);
        $response->assertSee('Recent suspicious login alert');
        $response->assertSee('Triaged');
        $response->assertSee('High');
        $response->assertSee('Urgent');
    }

    public function test_dashboard_does_not_break_when_no_incidents_exist(): void
    {
        $securityManager = $this->createUserWithPermissions(['dashboard.view'], 'security-manager', 'Security Manager');

        $response = $this->actingAs($securityManager)->get(route('dashboard'));

        $response->assertOk();
        $this->assertMetricValue($response->getContent(), 'total_incidents', 0);
        $this->assertMetricValue($response->getContent(), 'active_incidents', 0);
        $response->assertSee('No recent incidents match your current dashboard scope.');
        $response->assertSee('No status data available.');
        $response->assertSee('No assigned analyst workload is available yet.');
    }

    private function assertMetricValue(string $content, string $metricKey, int $expectedValue): void
    {
        $this->assertMatchesRegularExpression(
            sprintf(
                '/data-dashboard-metric="%s"[^>]*>\s*%s\s*</',
                preg_quote($metricKey, '/'),
                preg_quote(number_format($expectedValue), '/'),
            ),
            $content,
        );
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
                        'group_name' => str($permissionSlug)->before('.')->toString(),
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
            'incident_number' => 'INC-20260624-'.str_pad((string) (1000 + $nextNumber), 4, '0', STR_PAD_LEFT),
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
            'status' => 'reported',
        ], $overrides));
    }
}
