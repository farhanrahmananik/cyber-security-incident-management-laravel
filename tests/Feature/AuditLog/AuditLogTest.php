<?php

namespace Tests\Feature\AuditLog;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_audit_logs_page(): void
    {
        $response = $this->get(route('audit-logs.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_audit_log_view_gets_forbidden(): void
    {
        $this->ensurePermissionExists('audit-log.view');
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertForbidden();
    }

    public function test_security_manager_can_access_audit_logs_page(): void
    {
        $user = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee('Audit Logs');
        $response->assertSee('Read-only security activity history');
    }

    public function test_audit_logs_page_displays_existing_audit_log_event(): void
    {
        $user = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');
        $this->createAuditLog([
            'event' => 'incident.created',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee('incident.created');
    }

    public function test_filters_by_event(): void
    {
        $user = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');
        $this->createAuditLog(['event' => 'incident.created']);
        $this->createAuditLog(['event' => 'user.deactivated']);

        $response = $this->actingAs($user)->get(route('audit-logs.index', [
            'event' => 'incident.created',
        ]));

        $response->assertOk();
        $response->assertSee('incident.created');
        $response->assertDontSee('user.deactivated');
    }

    public function test_filters_by_actor_user(): void
    {
        $viewer = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');
        $actor = User::factory()->create(['name' => 'Audited Actor', 'is_active' => true]);
        $otherActor = User::factory()->create(['name' => 'Other Actor', 'is_active' => true]);

        $this->createAuditLog([
            'event' => 'user.updated',
            'user_id' => $actor->id,
        ]);
        $this->createAuditLog([
            'event' => 'role.updated',
            'user_id' => $otherActor->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('audit-logs.index', [
            'user_id' => $actor->id,
        ]));

        $response->assertOk();
        $response->assertSee('Audited Actor');
        $response->assertSee('user.updated');
        $response->assertDontSee('Other Actor');
        $response->assertDontSee('role.updated');
    }

    public function test_filters_by_date_range(): void
    {
        $user = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');
        $this->createAuditLog([
            'event' => 'incident.in.range',
            'created_at' => '2026-06-23 10:00:00',
        ]);
        $this->createAuditLog([
            'event' => 'incident.outside.range',
            'created_at' => '2026-06-20 10:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('audit-logs.index', [
            'date_from' => '2026-06-22',
            'date_to' => '2026-06-24',
        ]));

        $response->assertOk();
        $response->assertSee('incident.in.range');
        $response->assertDontSee('incident.outside.range');
    }

    public function test_filters_by_auditable_type_and_id(): void
    {
        $viewer = $this->createUserWithPermissions(['audit-log.view'], 'security-manager', 'Security Manager');
        $target = User::factory()->create(['name' => 'Target User', 'is_active' => true]);
        $otherTarget = User::factory()->create(['name' => 'Other Target', 'is_active' => true]);

        $this->createAuditLog([
            'event' => 'user.updated',
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->id,
        ]);
        $this->createAuditLog([
            'event' => 'user.deactivated',
            'auditable_type' => $otherTarget->getMorphClass(),
            'auditable_id' => $otherTarget->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('audit-logs.index', [
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->id,
        ]));

        $response->assertOk();
        $response->assertSee('user.updated');
        $response->assertDontSee('user.deactivated');
    }

    public function test_audit_log_service_records_expected_fields(): void
    {
        $service = app(AuditLogService::class);
        $actor = User::factory()->create(['is_active' => true]);
        $auditable = User::factory()->create(['is_active' => true]);
        $request = Request::create('/audit-logs', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Audit Feature Test Browser',
        ]);

        $auditLog = $service->record(
            event: 'user.updated',
            auditable: $auditable,
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name'],
            user: $actor,
            request: $request,
        );

        $this->assertSame('user.updated', $auditLog->event);
        $this->assertSame($actor->id, $auditLog->user_id);
        $this->assertSame($auditable->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($auditable->id, $auditLog->auditable_id);
        $this->assertSame(['name' => 'Old Name'], $auditLog->old_values);
        $this->assertSame(['name' => 'New Name'], $auditLog->new_values);
        $this->assertSame('203.0.113.10', $auditLog->ip_address);
        $this->assertSame('Audit Feature Test Browser', $auditLog->user_agent);
    }

    public function test_audit_log_service_supports_null_system_user(): void
    {
        $service = app(AuditLogService::class);

        $auditLog = $service->record('system.maintenance');

        $this->assertSame('system.maintenance', $auditLog->event);
        $this->assertNull($auditLog->user_id);
        $this->assertNull($auditLog->auditable_type);
        $this->assertNull($auditLog->auditable_id);
    }

    public function test_sidebar_shows_audit_logs_link_for_authorized_users_and_hides_it_for_unauthorized_users(): void
    {
        $authorizedUser = $this->createUserWithPermissions([
            'audit-log.view',
            'dashboard.view',
        ], 'security-manager', 'Security Manager');
        $unauthorizedUser = $this->createUserWithPermissions(['dashboard.view'], 'soc-analyst', 'SOC Analyst');

        $authorizedResponse = $this->actingAs($authorizedUser)->get(route('dashboard'));

        $authorizedResponse->assertOk();
        $authorizedResponse->assertSee('Audit Logs');
        $authorizedResponse->assertSee('href="'.route('audit-logs.index').'"', false);
        $authorizedResponse->assertDontSee('Audit Logs</span> <span class="planned-label">Planned', false);

        $unauthorizedResponse = $this->actingAs($unauthorizedUser)->get(route('dashboard'));

        $unauthorizedResponse->assertOk();
        $unauthorizedResponse->assertDontSee('Audit Logs');
    }

    /**
     * Create an active user with an active role and permission slugs.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    private function createUserWithPermissions(
        array $permissionSlugs,
        string $roleSlug = 'reporter-employee',
        string $roleName = 'Reporter / Employee',
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => $roleName,
                'is_active' => true,
            ],
        );

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $permissionSlug): int => $this->ensurePermissionExists($permissionSlug)->id)
            ->all();

        if ($permissionIds !== []) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function ensurePermissionExists(string $permissionSlug): Permission
    {
        return Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'name' => str($permissionSlug)->replace(['.', '-'], ' ')->title()->toString(),
                'group_name' => str($permissionSlug)->before('.')->toString(),
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAuditLog(array $attributes = []): AuditLog
    {
        $timestamp = $attributes['created_at'] ?? null;
        $auditLog = AuditLog::query()->create(array_merge([
            'event' => 'audit.test',
        ], Arr::except($attributes, ['created_at'])));

        if ($timestamp !== null) {
            $auditLog->forceFill(['created_at' => $timestamp])->save();
        }

        return $auditLog->refresh();
    }
}
