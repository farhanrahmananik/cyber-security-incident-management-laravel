<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's RBAC roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full platform administration access.',
            ],
            [
                'name' => 'Security Manager',
                'slug' => 'security-manager',
                'description' => 'Manages incident operations, reporting, assignments, and security workflows.',
            ],
            [
                'name' => 'SOC Analyst',
                'slug' => 'soc-analyst',
                'description' => 'Investigates incidents, records findings, and performs response actions.',
            ],
            [
                'name' => 'Reporter / Employee',
                'slug' => 'reporter-employee',
                'description' => 'Reports security incidents and reviews submitted incident information.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role + ['is_active' => true],
            );
        }
    }
}
