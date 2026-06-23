<?php

namespace Database\Seeders;

use App\Models\PriorityLevel;
use Illuminate\Database\Seeder;

class PriorityLevelSeeder extends Seeder
{
    /**
     * Seed default priority levels.
     */
    public function run(): void
    {
        $priorityLevels = [
            ['Low', 'low', '#198754', 10],
            ['Medium', 'medium', '#ffc107', 20],
            ['High', 'high', '#fd7e14', 30],
            ['Urgent', 'urgent', '#dc3545', 40],
        ];

        foreach ($priorityLevels as [$name, $slug, $color, $sortOrder]) {
            PriorityLevel::updateOrCreate(
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
