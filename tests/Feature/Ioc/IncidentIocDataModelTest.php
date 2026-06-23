<?php

namespace Tests\Feature\Ioc;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentIoc;
use App\Models\PriorityLevel;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentIocDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_incident_can_have_many_iocs_and_load_iocs_relationship(): void
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

    public function test_ioc_belongs_to_incident(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = IncidentIoc::factory()->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
        ]);

        $this->assertTrue($ioc->incident->is($incident));
    }

    public function test_ioc_belongs_to_created_by_user(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = IncidentIoc::factory()->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
        ]);

        $this->assertTrue($ioc->createdBy->is($creator));
    }

    public function test_user_can_load_created_iocs_relationship(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);

        IncidentIoc::factory()->count(3)->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
        ]);

        $creator = $creator->fresh()->load('createdIocs');

        $this->assertCount(3, $creator->createdIocs);
        $this->assertTrue($creator->createdIocs->every(
            fn (IncidentIoc $ioc): bool => (int) $ioc->created_by_id === (int) $creator->id
        ));
    }

    public function test_datetime_fields_are_cast_properly(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $ioc = IncidentIoc::factory()->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
            'first_seen_at' => '2026-06-22 10:30:00',
            'last_seen_at' => '2026-06-23 11:45:00',
        ]);

        $this->assertSame('2026-06-22 10:30:00', $ioc->first_seen_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-23 11:45:00', $ioc->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_ioc_supports_long_url_style_value_up_to_2048_characters(): void
    {
        $reporter = User::factory()->create(['is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);
        $incident = $this->createIncidentFor($reporter);
        $longUrl = 'https://example.test/'.str_repeat('a', 2027);

        $ioc = IncidentIoc::factory()->create([
            'incident_id' => $incident->id,
            'created_by_id' => $creator->id,
            'type' => 'url',
            'value' => $longUrl,
        ]);

        $this->assertSame(2048, strlen($ioc->value));
        $this->assertDatabaseHas('incident_iocs', [
            'id' => $ioc->id,
            'value' => $longUrl,
        ]);
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
            'incident_number' => 'INC-20260623-'.str_pad((string) (9500 + $nextNumber), 4, '0', STR_PAD_LEFT),
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
