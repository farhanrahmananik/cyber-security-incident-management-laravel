<?php

namespace Tests\Feature\Report;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authorized_user_can_view_security_reports_page(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');

        $response = $this->actingAs($user)->get(route('reports.security.index'));

        $response->assertOk();
        $response->assertSee('Security Reports');
        $response->assertSee('Total Incidents');
        $response->assertSee('Incidents by Status');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('reports.security.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_export_security_reports_csv(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-4001',
            'title' => 'Exported phishing report incident',
        ]);

        $response = $this->actingAs($user)->get(route('reports.security.export'));

        $response->assertOk();
        $this->assertCsvDownloadHeaders($response);

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Incident Number', $csv);
        $this->assertStringContainsString('Assigned Analyst', $csv);
        $this->assertStringContainsString($incident->incident_number, $csv);
        $this->assertStringContainsString($incident->title, $csv);
    }

    public function test_guest_is_redirected_to_login_for_export(): void
    {
        $response = $this->get(route('reports.security.export'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_report_view_permission_cannot_export_reports(): void
    {
        Permission::query()->firstOrCreate(
            ['slug' => 'report.view'],
            [
                'name' => 'Report View',
                'group_name' => 'reports',
                'is_active' => true,
            ],
        );
        $user = $this->createUserWithPermissions([], 'reporter-employee', 'Reporter / Employee');

        $response = $this->actingAs($user)->get(route('reports.security.export'));

        $response->assertForbidden();
    }

    public function test_filters_are_accepted(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $analyst = $this->createUserWithPermissions([], 'soc-analyst', 'SOC Analyst');
        $reporter = User::factory()->create(['is_active' => true]);
        $taxonomy = $this->createTaxonomy();
        $secondaryTaxonomy = $this->createSecondaryTaxonomy();

        $matchingIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-2001',
            'title' => 'Filtered critical phishing incident',
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'current_assigned_to_id' => $analyst->id,
            'status' => 'investigating',
        ]);
        $matchingIncident->forceFill([
            'created_at' => '2026-06-20 10:00:00',
            'updated_at' => '2026-06-20 10:00:00',
        ])->save();

        $otherIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-2002',
            'title' => 'Unmatched resolved malware incident',
            'incident_category_id' => $secondaryTaxonomy['category']->id,
            'severity_level_id' => $secondaryTaxonomy['severity']->id,
            'priority_level_id' => $secondaryTaxonomy['priority']->id,
            'status' => 'resolved',
        ]);
        $otherIncident->forceFill([
            'created_at' => '2026-06-10 10:00:00',
            'updated_at' => '2026-06-10 10:00:00',
        ])->save();

        $response = $this->actingAs($user)->get(route('reports.security.index', [
            'date_from' => '2026-06-19',
            'date_to' => '2026-06-21',
            'status' => 'investigating',
            'severity_id' => $taxonomy['severity']->id,
            'priority_id' => $taxonomy['priority']->id,
            'category_id' => $taxonomy['category']->id,
            'assigned_to_id' => $analyst->id,
        ]));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
        $response->assertSee('Filtered critical phishing incident');
        $response->assertDontSee('Unmatched resolved malware incident');
        $response->assertSee('Investigating');
        $this->assertSummaryValue($response->getContent(), 'total_incidents', 1);
        $this->assertSummaryValue($response->getContent(), 'open_incidents', 1);
        $this->assertSummaryValue($response->getContent(), 'closed_incidents', 0);
        $this->assertSummaryValue($response->getContent(), 'critical_incidents', 1);
        $response->assertSee('data-report-breakdown="investigating"', false);
        $response->assertDontSee('data-report-breakdown="resolved"', false);
        $response->assertSee('data-report-breakdown="critical"', false);
        $response->assertDontSee('data-report-breakdown="low"', false);
        $response->assertSee('data-report-breakdown="phishing"', false);
        $response->assertDontSee('data-report-breakdown="malware"', false);
    }

    public function test_csv_export_includes_matching_filtered_incident_and_excludes_unmatched_incident(): void
    {
        $securityManager = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $reporter = User::factory()->create(['is_active' => true]);

        $matchingIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-4002',
            'title' => 'CSV included investigating incident',
            'status' => 'investigating',
        ]);
        $unmatchedIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-4003',
            'title' => 'CSV excluded resolved incident',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($securityManager)->get(route('reports.security.export', [
            'status' => 'investigating',
        ]));

        $response->assertOk();
        $this->assertCsvDownloadHeaders($response);

        $csv = $response->streamedContent();

        $this->assertStringContainsString($matchingIncident->incident_number, $csv);
        $this->assertStringContainsString($matchingIncident->title, $csv);
        $this->assertStringNotContainsString($unmatchedIncident->incident_number, $csv);
        $this->assertStringNotContainsString($unmatchedIncident->title, $csv);
    }

    public function test_security_manager_can_see_organization_wide_incident_report_totals(): void
    {
        $securityManager = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $reporterOne = User::factory()->create(['is_active' => true]);
        $reporterTwo = User::factory()->create(['is_active' => true]);

        $incidentOne = $this->createIncidentFor($reporterOne, [
            'title' => 'Organization phishing incident',
        ]);
        $incidentTwo = $this->createIncidentFor($reporterTwo, [
            'title' => 'Organization malware incident',
        ]);

        $response = $this->actingAs($securityManager)->get(route('reports.security.index'));

        $response->assertOk();
        $this->assertSummaryValue($response->getContent(), 'total_incidents', 2);
        $response->assertSee($incidentOne->title);
        $response->assertSee($incidentTwo->title);
    }

    public function test_soc_analyst_only_sees_assigned_incident_report_data(): void
    {
        $analyst = $this->createUserWithPermissions(['report.view'], 'soc-analyst', 'SOC Analyst');
        $otherAnalyst = $this->createUserWithPermissions([], 'soc-analyst', 'SOC Analyst');
        $reporter = User::factory()->create(['is_active' => true]);

        $assignedIncident = $this->createIncidentFor($reporter, [
            'title' => 'Assigned analyst report incident',
            'current_assigned_to_id' => $analyst->id,
        ]);
        $otherIncident = $this->createIncidentFor($reporter, [
            'title' => 'Other analyst report incident',
            'current_assigned_to_id' => $otherAnalyst->id,
        ]);

        $response = $this->actingAs($analyst)->get(route('reports.security.index'));

        $response->assertOk();
        $this->assertSummaryValue($response->getContent(), 'total_incidents', 1);
        $response->assertSee($assignedIncident->title);
        $response->assertDontSee($otherIncident->title);
    }

    public function test_soc_analyst_export_only_includes_assigned_incident_data(): void
    {
        $analyst = $this->createUserWithPermissions(['report.view'], 'soc-analyst', 'SOC Analyst');
        $otherAnalyst = $this->createUserWithPermissions([], 'soc-analyst', 'SOC Analyst');
        $reporter = User::factory()->create(['is_active' => true]);

        $assignedIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-4004',
            'title' => 'CSV assigned analyst incident',
            'current_assigned_to_id' => $analyst->id,
        ]);
        $otherIncident = $this->createIncidentFor($reporter, [
            'incident_number' => 'INC-20260624-4005',
            'title' => 'CSV other analyst incident',
            'current_assigned_to_id' => $otherAnalyst->id,
        ]);

        $response = $this->actingAs($analyst)->get(route('reports.security.export'));

        $response->assertOk();
        $this->assertCsvDownloadHeaders($response);

        $csv = $response->streamedContent();

        $this->assertStringContainsString($assignedIncident->incident_number, $csv);
        $this->assertStringContainsString($assignedIncident->title, $csv);
        $this->assertStringNotContainsString($otherIncident->incident_number, $csv);
        $this->assertStringNotContainsString($otherIncident->title, $csv);
    }

    public function test_user_without_report_view_permission_cannot_access_reports(): void
    {
        Permission::query()->firstOrCreate(
            ['slug' => 'report.view'],
            [
                'name' => 'Report View',
                'group_name' => 'reports',
                'is_active' => true,
            ],
        );
        $user = $this->createUserWithPermissions([], 'reporter-employee', 'Reporter / Employee');

        $response = $this->actingAs($user)->get(route('reports.security.index'));

        $response->assertForbidden();
    }

    public function test_filtered_summary_count_and_recent_incidents_are_consistent(): void
    {
        $securityManager = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');
        $reporter = User::factory()->create(['is_active' => true]);

        $openIncident = $this->createIncidentFor($reporter, [
            'title' => 'Open incident inside report filter',
            'status' => 'reported',
        ]);
        $closedIncident = $this->createIncidentFor($reporter, [
            'title' => 'Closed incident outside status filter',
            'status' => 'closed',
        ]);

        $response = $this->actingAs($securityManager)->get(route('reports.security.index', [
            'status' => 'reported',
        ]));

        $response->assertOk();
        $this->assertSummaryValue($response->getContent(), 'total_incidents', 1);
        $this->assertSummaryValue($response->getContent(), 'open_incidents', 1);
        $this->assertSummaryValue($response->getContent(), 'closed_incidents', 0);
        $response->assertSee($openIncident->title);
        $response->assertDontSee($closedIncident->title);
    }

    public function test_invalid_date_range_fails_validation(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');

        $response = $this
            ->actingAs($user)
            ->from(route('reports.security.index'))
            ->get(route('reports.security.index', [
                'date_from' => '2026-06-24',
                'date_to' => '2026-06-23',
            ]));

        $response->assertRedirect(route('reports.security.index'));
        $response->assertSessionHasErrors('date_to');
    }

    public function test_page_displays_expected_summary_text(): void
    {
        $user = $this->createUserWithPermissions(['report.view'], 'security-manager', 'Security Manager');

        $response = $this->actingAs($user)->get(route('reports.security.index'));

        $response->assertOk();
        $response->assertSee('Security Reports');
        $response->assertSee('Open Incidents');
        $response->assertSee('Closed Incidents');
        $response->assertSee('Critical Incidents');
        $response->assertSee('Analyst Workload');
        $response->assertSee('Recent Incidents');
    }

    private function assertSummaryValue(string $content, string $summaryKey, int $expectedValue): void
    {
        $this->assertMatchesRegularExpression(
            sprintf(
                '/data-report-summary="%s"[^>]*>\s*%s\s*</',
                preg_quote($summaryKey, '/'),
                preg_quote(number_format($expectedValue), '/'),
            ),
            $content,
        );
    }

    private function assertCsvDownloadHeaders(TestResponse $response): void
    {
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('security-reports-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
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
                ['slug' => 'critical'],
                [
                    'name' => 'Critical',
                    'sort_order' => 40,
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
     * Create secondary taxonomy records for filter exclusion checks.
     *
     * @return array{category: IncidentCategory, severity: SeverityLevel, priority: PriorityLevel}
     */
    private function createSecondaryTaxonomy(): array
    {
        return [
            'category' => IncidentCategory::query()->firstOrCreate(
                ['slug' => 'malware'],
                [
                    'name' => 'Malware',
                    'sort_order' => 20,
                    'is_active' => true,
                ],
            ),
            'severity' => SeverityLevel::query()->firstOrCreate(
                ['slug' => 'low'],
                [
                    'name' => 'Low',
                    'sort_order' => 10,
                    'is_active' => true,
                ],
            ),
            'priority' => PriorityLevel::query()->firstOrCreate(
                ['slug' => 'low'],
                [
                    'name' => 'Low',
                    'sort_order' => 10,
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
            'incident_number' => 'INC-20260624-'.str_pad((string) (3000 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
