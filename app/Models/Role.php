<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * A role, backed by spatie/laravel-permission.
 *
 * `users()` (from Spatie) is everyone holding the role; `primaryUsers()` is the
 * narrower set whose `users.role_id` names it — the role shown on their badge
 * and the one older code still reads.
 */
class Role extends SpatieRole
{
    /** Roles that bypass every permission and feature check. */
    public const SUPER_ROLES = ['master', 'admin'];

    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'color',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_role');
    }

    public function isSuper(): bool
    {
        return in_array($this->name, self::SUPER_ROLES, true);
    }

    /**
     * Whether the role grants the permission. Unlike Spatie's hasPermissionTo,
     * an unknown permission name answers false instead of throwing.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }

    /**
     * Everyone holding this role. Spatie resolves the user class from the
     * default guard, which under `auth:sanctum` is a guard with no provider —
     * so a count or eager load of this relation threw inside every API
     * request. All grants are on the `web` guard and its users are App\Models\User.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }
}
