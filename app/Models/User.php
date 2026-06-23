<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Incidents submitted by this user.
     */
    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reporter_id');
    }

    /**
     * Incidents currently assigned to this user.
     */
    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'current_assigned_to_id');
    }

    /**
     * Incident assignments received by this user.
     */
    public function incidentAssignmentsReceived(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class, 'assigned_to_id');
    }

    /**
     * Incident assignments made by this user.
     */
    public function incidentAssignmentsMade(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class, 'assigned_by_id');
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
