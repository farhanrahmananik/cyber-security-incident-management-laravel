<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Determine if the user has an active role.
     */
    public function hasRole(string $roleSlug): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $role): bool => $role->is_active && $role->slug === $roleSlug
            );
        }

        return $this->roles()
            ->where('slug', $roleSlug)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Determine if the user has at least one active role from the given list.
     *
     * @param  array<int, string>  $roleSlugs
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        if ($this->is_active === false || $roleSlugs === []) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $role): bool => $role->is_active && in_array($role->slug, $roleSlugs, true)
            );
        }

        return $this->roles()
            ->whereIn('slug', $roleSlugs)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Determine if the user has an active permission through assigned roles.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        if (
            $this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('permissions'))
        ) {
            return $this->roles->contains(function (Role $role) use ($permissionSlug): bool {
                return $role->is_active
                    && $role->permissions->contains(
                        fn (Permission $permission): bool => $permission->is_active
                            && $permission->slug === $permissionSlug
                    );
            });
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionSlug): void {
                $query->where('slug', $permissionSlug)
                    ->where('permissions.is_active', true);
            })
            ->exists();
    }
}
