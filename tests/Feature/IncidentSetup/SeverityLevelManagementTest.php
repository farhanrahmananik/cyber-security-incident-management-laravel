<?php

namespace Tests\Feature\IncidentSetup;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeverityLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $response = $this->get(route('severity-levels.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_with_severity_level_view_can_access_index(): void
    {
        $user = $this->createUserWithPermissions(['severity-level.view']);

        SeverityLevel::query()->create([
            'name' => 'Critical',
            'slug' => 'critical',
            'sort_order' => 40,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('severity-levels.index'));

        $response->assertOk();
        $response->assertSee('Severity Levels');
        $response->assertSee('Critical');
    }

    public function test_user_without_severity_level_view_cannot_access_index(): void
    {
        Permission::query()->create([
            'name' => 'Severity Level View',
            'slug' => 'severity-level.view',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('severity-levels.index'));

        $response->assertForbidden();
    }

    public function test_user_with_manage_permission_can_create_severity_level(): void
    {
        $user = $this->createUserWithPermissions(['severity-level.manage']);

        $response = $this->actingAs($user)->post(route('severity-levels.store'), [
            'name' => 'Emergency',
            'description' => 'Immediate executive visibility required.',
            'color' => '#842029',
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('severity-levels.index'));

        $this->assertDatabaseHas('severity_levels', [
            'name' => 'Emergency',
            'slug' => 'emergency',
            'description' => 'Immediate executive visibility required.',
            'color' => '#842029',
            'sort_order' => 50,
            'is_active' => true,
        ]);
    }

    public function test_user_with_manage_permission_can_update_severity_level(): void
    {
        $user = $this->createUserWithPermissions(['severity-level.manage']);
        $severityLevel = SeverityLevel::query()->create([
            'name' => 'High',
            'slug' => 'high',
            'description' => 'Original description.',
            'color' => '#dc3545',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('severity-levels.update', $severityLevel), [
            'name' => 'Major',
            'description' => 'Updated description.',
            'color' => '#fd7e14',
            'sort_order' => 35,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('severity-levels.index'));

        $this->assertDatabaseHas('severity_levels', [
            'id' => $severityLevel->id,
            'name' => 'Major',
            'slug' => 'major',
            'description' => 'Updated description.',
            'color' => '#fd7e14',
            'sort_order' => 35,
            'is_active' => true,
        ]);
    }

    public function test_destroy_deactivates_severity_level_instead_of_hard_deleting_it(): void
    {
        $user = $this->createUserWithPermissions(['severity-level.manage']);
        $severityLevel = SeverityLevel::query()->create([
            'name' => 'Medium',
            'slug' => 'medium',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('severity-levels.destroy', $severityLevel));

        $response->assertRedirect(route('severity-levels.index'));

        $this->assertDatabaseCount('severity_levels', 1);
        $this->assertDatabaseHas('severity_levels', [
            'id' => $severityLevel->id,
            'is_active' => false,
        ]);
    }

    public function test_user_without_manage_permission_cannot_create_update_or_deactivate(): void
    {
        Permission::query()->create([
            'name' => 'Severity Level Manage',
            'slug' => 'severity-level.manage',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions(['severity-level.view']);
        $severityLevel = SeverityLevel::query()->create([
            'name' => 'Low',
            'slug' => 'low',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('severity-levels.store'), [
                'name' => 'Informational',
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('severity-levels.update', $severityLevel), [
                'name' => 'Updated Low',
                'sort_order' => 15,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('severity-levels.destroy', $severityLevel))
            ->assertForbidden();

        $this->assertDatabaseMissing('severity_levels', [
            'name' => 'Informational',
        ]);

        $this->assertDatabaseHas('severity_levels', [
            'id' => $severityLevel->id,
            'name' => 'Low',
            'is_active' => true,
        ]);
    }

    /**
     * Create an active user with an active role and permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Security Manager',
            'slug' => 'security-manager',
            'is_active' => true,
        ]);

        $permissionIds = collect($permissionSlugs)
            ->map(function (string $permissionSlug): int {
                return Permission::query()->firstOrCreate(
                    ['slug' => $permissionSlug],
                    [
                        'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                        'group_name' => 'incident setup',
                        'is_active' => true,
                    ],
                )->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
