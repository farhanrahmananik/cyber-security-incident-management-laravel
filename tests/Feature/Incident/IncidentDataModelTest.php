<?php

namespace Tests\Feature\Incident;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentCategory;
use App\Models\IncidentEvidence;
use App\Models\IncidentIoc;
use App\Models\InvestigationNote;
use App\Models\PriorityLevel;
use App\Models\ResponseAction;
use App\Models\SeverityLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_incident_belongs_to_reporter_category_severity_and_priority(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $taxonomy = $this->createTaxonomy();
        $incident = $this->createIncidentFor($reporter, [
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
        ]);

        $incident->load(['reporter', 'category', 'severity', 'priority']);

        $this->assertTrue($incident->reporter->is($reporter));
        $this->assertTrue($incident->category->is($taxonomy['category']));
        $this->assertTrue($incident->severity->is($taxonomy['severity']));
        $this->assertTrue($incident->priority->is($taxonomy['priority']));
    }

    public function test_incident_can_belong_to_current_assignee(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $analyst = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'current_assigned_to_id' => $analyst->id,
        ]);

        $incident->load('currentAssignee');

        $this->assertTrue($incident->currentAssignee->is($analyst));
    }

    public function test_incident_has_many_assignment_records(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $firstAnalyst = User::factory()->create(['is_active' => true]);
        $secondAnalyst = User::factory()->create(['is_active' => true]);
        $assigner = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $firstAssignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $firstAnalyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Initial assignment.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 09:00:00'),
        ]);
        $secondAssignment = IncidentAssignment::query()->create([
            'incident_id' => $incident->id,
            'assigned_to_id' => $secondAnalyst->id,
            'assigned_by_id' => $assigner->id,
            'notes' => 'Escalated assignment.',
            'assigned_at' => CarbonImmutable::parse('2026-06-23 10:00:00'),
        ]);

        $incident = $incident->fresh()->load('assignments');

        $this->assertCount(2, $incident->assignments);
        $this->assertTrue($incident->assignments->contains($firstAssignment));
        $this->assertTrue($incident->assignments->contains($secondAssignment));
    }

    public function test_incident_has_many_investigation_notes(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $author = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        InvestigationNote::factory()->count(2)->create([
            'incident_id' => $incident->id,
            'author_id' => $author->id,
        ]);

        $incident = $incident->fresh()->load('investigationNotes');

        $this->assertCount(2, $incident->investigationNotes);
        $this->assertTrue($incident->investigationNotes->every(
            fn (InvestigationNote $note): bool => (int) $note->incident_id === (int) $incident->id
        ));
    }

    public function test_incident_has_many_iocs(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        IncidentIoc::factory()->count(2)->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
        ]);

        $incident = $incident->fresh()->load('iocs');

        $this->assertCount(2, $incident->iocs);
        $this->assertTrue($incident->iocs->every(
            fn (IncidentIoc $ioc): bool => (int) $ioc->incident_id === (int) $incident->id
        ));
    }

    public function test_incident_has_many_evidences(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $uploader = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        IncidentEvidence::factory()->count(2)->create([
            'incident_id' => $incident->id,
            'uploaded_by_id' => $uploader->id,
        ]);

        $incident = $incident->fresh()->load('evidences');

        $this->assertCount(2, $incident->evidences);
        $this->assertTrue($incident->evidences->every(
            fn (IncidentEvidence $evidence): bool => (int) $evidence->incident_id === (int) $incident->id
        ));
    }

    public function test_incident_has_many_response_actions(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        ResponseAction::factory()->count(2)->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
        ]);

        $incident = $incident->fresh()->load('responseActions');

        $this->assertCount(2, $incident->responseActions);
        $this->assertTrue($incident->responseActions->every(
            fn (ResponseAction $responseAction): bool => (int) $responseAction->incident_id === (int) $incident->id
        ));
    }

    public function test_incident_datetime_fields_are_cast_correctly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [
            'occurred_at' => '2026-06-23 08:15:00',
            'detected_at' => '2026-06-23 09:45:00',
        ]);

        $this->assertSame('2026-06-23 08:15:00', $incident->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-23 09:45:00', $incident->detected_at->format('Y-m-d H:i:s'));
    }

    public function test_incident_status_defaults_to_reported_when_not_set(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter, [], false);

        $this->assertSame('reported', $incident->refresh()->status);
    }

    public function test_incident_uses_soft_deletes(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        $incident->delete();

        $this->assertSoftDeleted('incidents', [
            'id' => $incident->id,
        ]);
        $this->assertNull(Incident::query()->find($incident->id));
        $this->assertTrue(Incident::withTrashed()->findOrFail($incident->id)->trashed());
    }

    /**
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
    private function createIncidentFor(
        User $reporter,
        array $overrides = [],
        bool $includeStatus = true,
    ): Incident {
        $taxonomy = $this->createTaxonomy();
        $nextNumber = Incident::query()->withTrashed()->count() + 1;
        $attributes = [
            'incident_number' => 'INC-20260624-'.str_pad((string) (9700 + $nextNumber), 4, '0', STR_PAD_LEFT),
            'reporter_id' => $reporter->id,
            'incident_category_id' => $taxonomy['category']->id,
            'severity_level_id' => $taxonomy['severity']->id,
            'priority_level_id' => $taxonomy['priority']->id,
            'title' => 'Endpoint malware alert',
            'description' => 'Endpoint protection detected suspicious behavior.',
        ];

        if ($includeStatus) {
            $attributes['status'] = 'reported';
        }

        return Incident::query()->create(array_merge($attributes, $overrides));
    }
}
