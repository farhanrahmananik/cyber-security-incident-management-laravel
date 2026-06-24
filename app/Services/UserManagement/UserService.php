<?php

namespace App\Services\UserManagement;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
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
        return DB::transaction(function () use ($data): User {
            $roleIds = $this->roleIdsFromData($data);
            $userData = Arr::only($data, ['name', 'email', 'password', 'is_active']);
            $userData['is_active'] = (bool) ($userData['is_active'] ?? true);

            $user = User::query()->create($userData);
            $user->roles()->sync($roleIds);

            return $user->load('roles');
        });
    }

    /**
     * Update a user and synchronize assigned roles.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
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
    }

    /**
     * Activate a user account.
     */
    public function activate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => true]);
        });
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

        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => false]);
        });
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
