<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\InvestigationNote;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestigationNote>
 */
class InvestigationNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => fn (): int => $this->createIncident()->id,
            'author_id' => User::factory(),
            'note' => fake()->randomElement([
                'Reviewed reported indicators and confirmed suspicious authentication activity from an unfamiliar source.',
                'Correlated endpoint telemetry with firewall logs; no evidence of lateral movement observed at this stage.',
                'Collected initial screenshots, message headers, and affected asset details for deeper triage.',
                'Validated the reported event against recent alerts and marked it for continued SOC investigation.',
            ]),
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
