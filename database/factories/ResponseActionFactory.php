<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\PriorityLevel;
use App\Models\ResponseAction;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponseAction>
 */
class ResponseActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $action = fake()->randomElement([
            [
                'action_type' => 'containment',
                'title' => 'Isolate Affected Endpoint',
                'description' => 'Network access was restricted while the endpoint was reviewed for suspicious activity.',
            ],
            [
                'action_type' => 'eradication',
                'title' => 'Remove Malicious Artifact',
                'description' => 'Detected malicious files and persistence indicators were removed from the affected asset.',
            ],
            [
                'action_type' => 'recovery',
                'title' => 'Restore Clean Service State',
                'description' => 'Service configuration was restored after containment and validation checks were completed.',
            ],
            [
                'action_type' => 'communication',
                'title' => 'Notify Affected Stakeholders',
                'description' => 'Incident status and response expectations were communicated to relevant stakeholders.',
            ],
            [
                'action_type' => 'monitoring',
                'title' => 'Increase Detection Monitoring',
                'description' => 'Additional monitoring was configured for related indicators and suspicious follow-up activity.',
            ],
            [
                'action_type' => 'lessons_learned',
                'title' => 'Document Lessons Learned',
                'description' => 'Post-incident lessons were captured for future detection and response improvements.',
            ],
        ]);

        $status = fake()->randomElement(['planned', 'in_progress', 'completed', 'cancelled']);
        $startedAt = in_array($status, ['in_progress', 'completed', 'cancelled'], true)
            ? now()->subHours(fake()->numberBetween(4, 72))
            : null;
        $completedAt = $status === 'completed' && $startedAt !== null
            ? (clone $startedAt)->addHours(fake()->numberBetween(1, 6))
            : null;

        return [
            'incident_id' => fn (): int => $this->createIncident()->id,
            'performed_by' => fn (): int => User::factory()->create(['is_active' => true])->id,
            'action_type' => $action['action_type'],
            'status' => $status,
            'title' => $action['title'],
            'description' => $action['description'],
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ];
    }

    /**
     * Create a valid incident when one is not provided to the factory.
     */
    private function createIncident(): Incident
    {
        $reporter = User::factory()->create(['is_active' => true]);

        $category = IncidentCategory::query()->create([
            'name' => 'Phishing',
            'slug' => 'phishing-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $severity = SeverityLevel::query()->create([
            'name' => 'High',
            'slug' => 'high-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $priority = PriorityLevel::query()->create([
            'name' => 'Urgent',
            'slug' => 'urgent-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 40,
            'is_active' => true,
        ]);

        return Incident::query()->create([
            'incident_number' => 'INC-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'reporter_id' => $reporter->id,
            'incident_category_id' => $category->id,
            'severity_level_id' => $severity->id,
            'priority_level_id' => $priority->id,
            'title' => 'Suspicious authentication activity',
            'description' => 'A suspicious authentication event requires investigation.',
            'status' => 'reported',
        ]);
    }
}
