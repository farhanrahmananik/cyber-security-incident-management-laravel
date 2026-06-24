<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentEvidence;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentEvidence>
 */
class IncidentEvidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $evidence = fake()->randomElement([
            [
                'title' => 'Firewall Log Export',
                'original_filename' => 'firewall-log-export.csv',
                'mime_type' => 'text/csv',
            ],
            [
                'title' => 'Phishing Email Header',
                'original_filename' => 'phishing-email-header.txt',
                'mime_type' => 'text/plain',
            ],
            [
                'title' => 'Malware Sample Hash Report',
                'original_filename' => 'malware-sample-hash-report.pdf',
                'mime_type' => 'application/pdf',
            ],
            [
                'title' => 'Endpoint Screenshot',
                'original_filename' => 'endpoint-screenshot.png',
                'mime_type' => 'image/png',
            ],
        ]);

        $storedFilename = fake()->uuid().'-'.$evidence['original_filename'];

        return [
            'incident_id' => fn (): int => $this->createIncident()->id,
            'uploaded_by_id' => User::factory(),
            'deleted_by_id' => null,
            'title' => $evidence['title'],
            'description' => 'Evidence collected during incident triage for analyst review.',
            'original_filename' => $evidence['original_filename'],
            'stored_path' => 'incidents/evidences/'.$storedFilename,
            'disk' => 'local',
            'mime_type' => $evidence['mime_type'],
            'file_size' => fake()->numberBetween(12000, 8500000),
            'checksum_sha256' => hash('sha256', fake()->uuid()),
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
