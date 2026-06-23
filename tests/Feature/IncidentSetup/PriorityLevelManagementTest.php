<?php

namespace Tests\Feature\IncidentSetup;

use App\Models\Permission;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $response = $this->get(route('priority-levels.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_with_priority_level_view_can_access_index(): void
    {
        $user = $this->createUserWithPermissions(['priority-level.view']);

        PriorityLevel::query()->create([
            'name' => 'Urgent',
            'slug' => 'urgent',
            'sort_order' => 40,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('priority-levels.index'));

        $response->assertOk();
        $response->assertSee('Priority Levels');
        $response->assertSee('Urgent');
    }

    public function test_user_without_priority_level_view_cannot_access_index(): void
    {
        Permission::query()->create([
            'name' => 'Priority Level View',
            'slug' => 'priority-level.view',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('priority-levels.index'));

        $response->assertForbidden();
    }

    public function test_user_with_manage_permission_can_create_priority_level(): void
    {
        $user = $this->createUserWithPermissions(['priority-level.manage']);

        $response = $this->actingAs($user)->post(route('priority-levels.store'), [
            'name' => 'Immediate',
            'description' => 'Response must begin immediately.',
            'color' => '#842029',
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('priority-levels.index'));

        $this->assertDatabaseHas('priority_levels', [
            'name' => 'Immediate',
            'slug' => 'immediate',
            'description' => 'Response must begin immediately.',
            'color' => '#842029',
            'sort_order' => 50,
            'is_active' => true,
        ]);
    }

    public function test_user_with_manage_permission_can_update_priority_level(): void
    {
        $user = $this->createUserWithPermissions(['priority-level.manage']);
        $priorityLevel = PriorityLevel::query()->create([
            'name' => 'High',
            'slug' => 'high',
            'description' => 'Original description.',
            'color' => '#fd7e14',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('priority-levels.update', $priorityLevel), [
            'name' => 'Escalated',
            'description' => 'Updated description.',
            'color' => '#dc3545',
            'sort_order' => 35,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('priority-levels.index'));

        $this->assertDatabaseHas('priority_levels', [
            'id' => $priorityLevel->id,
            'name' => 'Escalated',
            'slug' => 'escalated',
            'description' => 'Updated description.',
            'color' => '#dc3545',
            'sort_order' => 35,
            'is_active' => true,
        ]);
    }

    public function test_destroy_deactivates_priority_level_instead_of_hard_deleting_it(): void
    {
        $user = $this->createUserWithPermissions(['priority-level.manage']);
        $priorityLevel = PriorityLevel::query()->create([
            'name' => 'Medium',
            'slug' => 'medium',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('priority-levels.destroy', $priorityLevel));

        $response->assertRedirect(route('priority-levels.index'));

        $this->assertDatabaseCount('priority_levels', 1);
        $this->assertDatabaseHas('priority_levels', [
            'id' => $priorityLevel->id,
            'is_active' => false,
        ]);
    }

    public function test_user_without_manage_permission_cannot_create_update_or_deactivate(): void
    {
        Permission::query()->create([
            'name' => 'Priority Level Manage',
            'slug' => 'priority-level.manage',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions(['priority-level.view']);
        $priorityLevel = PriorityLevel::query()->create([
            'name' => 'Low',
            'slug' => 'low',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('priority-levels.store'), [
                'name' => 'Deferred',
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('priority-levels.update', $priorityLevel), [
                'name' => 'Updated Low',
                'sort_order' => 15,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('priority-levels.destroy', $priorityLevel))
            ->assertForbidden();

        $this->assertDatabaseMissing('priority_levels', [
            'name' => 'Deferred',
        ]);

        $this->assertDatabaseHas('priority_levels', [
            'id' => $priorityLevel->id,
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
