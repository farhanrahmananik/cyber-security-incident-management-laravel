<?php

namespace Tests\Feature\ResponseAction;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\PriorityLevel;
use App\Models\ResponseAction;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponseActionDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_response_action_belongs_to_an_incident(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = ResponseAction::factory()->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
        ]);

        $this->assertTrue($responseAction->incident->is($incident));
    }

    public function test_response_action_belongs_to_performed_by_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = ResponseAction::factory()->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
        ]);

        $this->assertTrue($responseAction->performedBy->is($performer));
    }

    public function test_incident_has_many_response_actions(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        ResponseAction::factory()->count(3)->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
        ]);

        $incident = $incident->fresh()->load('responseActions');

        $this->assertCount(3, $incident->responseActions);
        $this->assertTrue($incident->responseActions->every(
            fn (ResponseAction $responseAction): bool => (int) $responseAction->incident_id === (int) $incident->id
        ));
    }

    public function test_user_can_load_performed_response_actions_relationship(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        ResponseAction::factory()->count(2)->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
        ]);

        $performer = $performer->fresh()->load('performedResponseActions');

        $this->assertCount(2, $performer->performedResponseActions);
        $this->assertTrue($performer->performedResponseActions->every(
            fn (ResponseAction $responseAction): bool => (int) $responseAction->performed_by === (int) $performer->id
        ));
    }

    public function test_datetime_fields_are_cast_properly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $performer = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $responseAction = ResponseAction::factory()->create([
            'incident_id' => $incident->id,
            'performed_by' => $performer->id,
            'status' => 'completed',
            'started_at' => '2026-06-23 09:15:00',
            'completed_at' => '2026-06-23 12:45:00',
        ]);

        $this->assertSame('2026-06-23 09:15:00', $responseAction->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-23 12:45:00', $responseAction->completed_at->format('Y-m-d H:i:s'));
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
}
