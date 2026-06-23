<?php

namespace Database\Seeders;

use App\Models\IncidentCategory;
use Illuminate\Database\Seeder;

class IncidentCategorySeeder extends Seeder
{
    /**
     * Seed default incident categories.
     */
    public function run(): void
    {
        $categories = [
            ['Malware', 'malware', '#dc3545', 10],
            ['Phishing', 'phishing', '#fd7e14', 20],
            ['Unauthorized Access', 'unauthorized-access', '#6f42c1', 30],
            ['Data Breach', 'data-breach', '#b02a37', 40],
            ['Network Attack', 'network-attack', '#0d6efd', 50],
            ['Policy Violation', 'policy-violation', '#6c757d', 60],
        ];

        foreach ($categories as [$name, $slug, $color, $sortOrder]) {
            IncidentCategory::updateOrCreate(
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
