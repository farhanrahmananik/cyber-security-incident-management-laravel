<?php

namespace Database\Seeders;

use App\Models\SeverityLevel;
use Illuminate\Database\Seeder;

class SeverityLevelSeeder extends Seeder
{
    /**
     * Seed default severity levels.
     */
    public function run(): void
    {
        $severityLevels = [
            ['Low', 'low', '#198754', 10],
            ['Medium', 'medium', '#ffc107', 20],
            ['High', 'high', '#fd7e14', 30],
            ['Critical', 'critical', '#dc3545', 40],
        ];

        foreach ($severityLevels as [$name, $slug, $color, $sortOrder]) {
            SeverityLevel::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => null,
                    'color' => $color,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ],
            );
        }
    }
}
