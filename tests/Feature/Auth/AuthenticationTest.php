<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Cyber Security Incident Management');
        $response->assertSee('Sign in');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'analyst@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'analyst@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'analyst@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'analyst@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_dashboard(): void
    {
        $role = Role::query()->create([
            'name' => 'SOC Analyst',
            'slug' => 'soc-analyst',
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'Dashboard View',
            'slug' => 'dashboard.view',
            'group_name' => 'dashboard',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'name' => 'SOC Analyst',
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Welcome, SOC Analyst');
        $response->assertSee('Dashboard metrics are calculated from real incident records');
        $response->assertSee('Total Incidents');
        $response->assertSee('data-dashboard-metric="total_incidents"', false);
    }
}
