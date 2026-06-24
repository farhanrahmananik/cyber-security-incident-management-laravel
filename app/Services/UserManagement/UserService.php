<?php

namespace App\Services\UserManagement;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Return users with their assigned roles for the management page.
     */
    public function paginateUsers(): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Return active roles that can be assigned to users.
     */
    public function activeRoles(): Collection
    {
        return Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a user and assign selected active roles.
     *
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $roleIds = $this->roleIdsFromData($data);
            $userData = Arr::only($data, ['name', 'email', 'password', 'is_active']);
            $userData['is_active'] = (bool) ($userData['is_active'] ?? true);

            $user = User::query()->create($userData);
            $user->roles()->sync($roleIds);

            return $user->load('roles');
        });

        $this->auditLogService->record(
            event: 'user.created',
            auditable: $user,
            newValues: $this->safeUserValues($user) + [
                'role_slugs' => $this->roleSlugsForUser($user),
            ],
            request: request(),
        );

        return $user;
    }

    /**
     * Update a user and synchronize assigned roles.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        $oldUserValues = $this->safeUserValues($user);
        $oldRoleSlugs = $this->roleSlugsForUser($user);
        $passwordWasProvided = filled($data['password'] ?? null);

        $updatedUser = DB::transaction(function () use ($user, $data): User {
            $roleIds = $this->roleIdsFromData($data);

            $this->ensureLastActiveSuperAdminRoleIsNotRemoved($user, $roleIds);

            $userData = Arr::only($data, ['name', 'email', 'password']);

            if (($userData['password'] ?? null) === null || $userData['password'] === '') {
                unset($userData['password']);
            }

            $user->update($userData);
            $user->roles()->sync($roleIds);

            return $user->load('roles');
        });

        $newUserValues = $this->safeUserValues($updatedUser);
        $changedUserValues = $this->changedValues($oldUserValues, $newUserValues);

        if ($changedUserValues['old'] !== [] || $passwordWasProvided) {
            $this->auditLogService->record(
                event: 'user.updated',
                auditable: $updatedUser,
                oldValues: $changedUserValues['old'],
                newValues: $changedUserValues['new'],
                request: request(),
            );
        }

        $newRoleSlugs = $this->roleSlugsForUser($updatedUser);

        if ($oldRoleSlugs !== $newRoleSlugs) {
            $this->auditLogService->record(
                event: 'user.roles_synced',
                auditable: $updatedUser,
                oldValues: ['role_slugs' => $oldRoleSlugs],
                newValues: ['role_slugs' => $newRoleSlugs],
                request: request(),
            );
        }

        return $updatedUser;
    }

    /**
     * Activate a user account.
     */
    public function activate(User $user): void
    {
        $wasActive = (bool) $user->is_active;

        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => true]);
        });

        if ($wasActive === false) {
            $this->auditLogService->record(
                event: 'user.activated',
                auditable: $user->refresh(),
                oldValues: ['is_active' => false],
                newValues: ['is_active' => true],
                request: request(),
            );
        }
    }

    /**
     * Deactivate a user account without deleting it.
     */
    public function deactivate(User $user, User $currentUser): void
    {
        if ($user->is($currentUser)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own account.',
            ]);
        }

        $this->ensureUserCanBeDeactivated($user);

        $wasActive = (bool) $user->is_active;

        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => false]);
        });

        if ($wasActive === true) {
            $this->auditLogService->record(
                event: 'user.deactivated',
                auditable: $user->refresh(),
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                request: request(),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function roleIdsFromData(array $data): array
    {
        return collect($data['role_ids'] ?? [])
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Return safe user fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeUserValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
        ];
    }

    /**
     * Return sorted role slugs for audit logging.
     *
     * @return list<string>
     */
    private function roleSlugsForUser(User $user): array
    {
        return $user->roles()
            ->orderBy('slug')
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * Extract changed audit values from two safe snapshots.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changedValues(array $oldValues, array $newValues): array
    {
        $old = [];
        $new = [];

        foreach ($newValues as $key => $value) {
            if (($oldValues[$key] ?? null) === $value) {
                continue;
            }

            $old[$key] = $oldValues[$key] ?? null;
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Prevent deactivating the final active Super Admin account.
     */
    private function ensureUserCanBeDeactivated(User $user): void
    {
        if (! $this->userCountsAsActiveSuperAdmin($user)) {
            return;
        }

        if ($this->anotherActiveSuperAdminExists($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => 'At least one active Super Admin account must remain.',
        ]);
    }

    /**
     * Prevent removing the Super Admin role from the final active Super Admin.
     *
     * @param  list<int>  $roleIds
     */
    private function ensureLastActiveSuperAdminRoleIsNotRemoved(User $user, array $roleIds): void
    {
        if (! $this->userCountsAsActiveSuperAdmin($user)) {
            return;
        }

        $superAdminRole = Role::query()
            ->where('slug', 'super-admin')
            ->where('is_active', true)
            ->first();

        if ($superAdminRole && in_array((int) $superAdminRole->getKey(), $roleIds, true)) {
            return;
        }

        if ($this->anotherActiveSuperAdminExists($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'role_ids' => 'At least one active Super Admin must keep the Super Admin role.',
        ]);
    }

    /**
     * Determine if the user is an active Super Admin.
     */
    private function userCountsAsActiveSuperAdmin(User $user): bool
    {
        return $user->is_active
            && $user->roles()
                ->where('roles.slug', 'super-admin')
                ->where('roles.is_active', true)
                ->exists();
    }

    /**
     * Determine if another active Super Admin exists.
     */
    private function anotherActiveSuperAdminExists(User $user): bool
    {
        return User::query()
            ->where('id', '!=', $user->getKey())
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('roles.slug', 'super-admin')
                    ->where('roles.is_active', true);
            })
            ->exists();
    }
}
