<?php

namespace Tests\Feature\IncidentSetup;

use App\Models\IncidentCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $response = $this->get(route('incident-categories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_with_incident_category_view_can_access_index(): void
    {
        $user = $this->createUserWithPermissions(['incident-category.view']);

        IncidentCategory::query()->create([
            'name' => 'Malware',
            'slug' => 'malware',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('incident-categories.index'));

        $response->assertOk();
        $response->assertSee('Incident Categories');
        $response->assertSee('Malware');
    }

    public function test_user_without_incident_category_view_cannot_access_index(): void
    {
        Permission::query()->create([
            'name' => 'Incident Category View',
            'slug' => 'incident-category.view',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('incident-categories.index'));

        $response->assertForbidden();
    }

    public function test_user_with_manage_permission_can_create_category(): void
    {
        $user = $this->createUserWithPermissions(['incident-category.manage']);

        $response = $this->actingAs($user)->post(route('incident-categories.store'), [
            'name' => 'Social Engineering',
            'description' => 'Human-focused security incidents.',
            'color' => '#6610f2',
            'sort_order' => 70,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('incident-categories.index'));

        $this->assertDatabaseHas('incident_categories', [
            'name' => 'Social Engineering',
            'slug' => 'social-engineering',
            'description' => 'Human-focused security incidents.',
            'color' => '#6610f2',
            'sort_order' => 70,
            'is_active' => true,
        ]);
    }

    public function test_user_with_manage_permission_can_update_category(): void
    {
        $user = $this->createUserWithPermissions(['incident-category.manage']);
        $incidentCategory = IncidentCategory::query()->create([
            'name' => 'Phishing',
            'slug' => 'phishing',
            'description' => 'Original description.',
            'color' => '#fd7e14',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('incident-categories.update', $incidentCategory), [
            'name' => 'Phishing Simulation',
            'description' => 'Updated description.',
            'color' => '#0dcaf0',
            'sort_order' => 25,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('incident-categories.index'));

        $this->assertDatabaseHas('incident_categories', [
            'id' => $incidentCategory->id,
            'name' => 'Phishing Simulation',
            'slug' => 'phishing-simulation',
            'description' => 'Updated description.',
            'color' => '#0dcaf0',
            'sort_order' => 25,
            'is_active' => true,
        ]);
    }

    public function test_destroy_deactivates_category_instead_of_hard_deleting_it(): void
    {
        $user = $this->createUserWithPermissions(['incident-category.manage']);
        $incidentCategory = IncidentCategory::query()->create([
            'name' => 'Policy Violation',
            'slug' => 'policy-violation',
            'sort_order' => 60,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('incident-categories.destroy', $incidentCategory));

        $response->assertRedirect(route('incident-categories.index'));

        $this->assertDatabaseCount('incident_categories', 1);
        $this->assertDatabaseHas('incident_categories', [
            'id' => $incidentCategory->id,
            'is_active' => false,
        ]);
    }

    public function test_user_without_manage_permission_cannot_create_update_or_deactivate(): void
    {
        Permission::query()->create([
            'name' => 'Incident Category Manage',
            'slug' => 'incident-category.manage',
            'group_name' => 'incident setup',
            'is_active' => true,
        ]);

        $user = $this->createUserWithPermissions(['incident-category.view']);
        $incidentCategory = IncidentCategory::query()->create([
            'name' => 'Network Attack',
            'slug' => 'network-attack',
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('incident-categories.store'), [
                'name' => 'Unauthorized Access',
                'sort_order' => 30,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('incident-categories.update', $incidentCategory), [
                'name' => 'Updated Network Attack',
                'sort_order' => 55,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('incident-categories.destroy', $incidentCategory))
            ->assertForbidden();

        $this->assertDatabaseMissing('incident_categories', [
            'name' => 'Unauthorized Access',
        ]);

        $this->assertDatabaseHas('incident_categories', [
            'id' => $incidentCategory->id,
            'name' => 'Network Attack',
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
