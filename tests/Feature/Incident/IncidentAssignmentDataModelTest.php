<?php

namespace Tests\Feature\Incident;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentCategory;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentAssignmentDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_incident_can_store_and_load_current_assignee_relationship(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);

        $incident->load('currentAssignee');

        $this->assertTrue($incident->currentAssignee->is($analyst));
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'current_assigned_to_id' => $analyst->id,
        ]);
    }

    public function test_incident_can_store_multiple_assignments_and_load_assignment_history(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $firstAnalyst = User::factory()->create(['is_active' => true]);
        $secondAnalyst = User::factory()->create(['is_active' => true]);
        $assigner = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $secondAnalyst->id,
        ]);

        $firstAssignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $firstAnalyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Initial triage assignment.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 09:00:00'),
        ]);
        $secondAssignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $secondAnalyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Escalated to senior analyst.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 10:00:00'),
        ]);

        $incident->load('assignments');

        $this->assertCount(2, $incident->assignments);
        $this->assertTrue($incident->assignments->contains($firstAssignment));
        $this->assertTrue($incident->assignments->contains($secondAssignment));
    }

    public function test_assignment_belongs_to_incident_assigned_user_and_assigning_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = User::factory()->create(['is_active' => true]);
        $assigner = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $assignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Assigned for investigation.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 11:00:00'),
        ]);

        $assignment->load(['incident', 'assignedTo', 'assignedBy']);

        $this->assertTrue($assignment->incident->is($incident));
        $this->assertTrue($assignment->assignedTo->is($analyst));
        $this->assertTrue($assignment->assignedBy->is($assigner));
        $this->assertTrue($assignment->assigned_at->isSameMinute(CarbonImmutable::parse('2026-06-23 11:00:00')));
    }

    public function test_user_can_load_assigned_incidents_and_assignment_relationships(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = User::factory()->create(['is_active' => true]);
        $assigner = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);
        $assignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $analyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Assigned from model test.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 12:00:00'),
        ]);

        $analyst->load(['assignedIncidents', 'incidentAssignmentsReceived']);
        $assigner->load('incidentAssignmentsMade');

        $this->assertTrue($analyst->assignedIncidents->contains($incident));
        $this->assertTrue($analyst->incidentAssignmentsReceived->contains($assignment));
        $this->assertTrue($assigner->incidentAssignmentsMade->contains($assignment));
    }

    /**
     * Create active taxonomy records needed by incident records.
     *
     * @return array{category: IncidentCategory, severity: SeverityLevel, priority: PriorityLevel}
     */
    private function createTaxonomy(): array
    {
        return [
            'category' => IncidentCategory::query()->create([
                'name' => 'Phishing',
                'slug' => 'phishing',
                'sort_order' => 10,
                'is_active' => true,
            ]),
            'severity' => SeverityLevel::query()->create([
                'name' => 'High',
                'slug' => 'high',
                'sort_order' => 30,
                'is_active' => true,
            ]),
            'priority' => PriorityLevel::query()->create([
                'name' => 'Urgent',
                'slug' => 'urgent',
                'sort_order' => 40,
                'is_active' => true,
            ]),
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

        return Incident::query()->create(array_merge([
            'incident_number' => 'INC-20260623-9001',
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
