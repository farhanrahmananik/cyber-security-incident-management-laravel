<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the initial super administrator account.
     */
    public function run(): void
    {
        $superAdminRole = Role::query()->where('slug', 'super-admin')->firstOrFail();

        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
    }
}
