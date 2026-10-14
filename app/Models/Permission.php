<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A permission, backed by spatie/laravel-permission. Names follow
 * `module.action` (e.g. `products.edit`); `module` groups them on screen.
 */
class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'module',
        'description',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Everyone holding this permission directly. Spatie resolves the user class from the
     * default guard, which under `auth:sanctum` is a guard with no provider —
     * so a count or eager load of this relation threw inside every API
     * request. All grants are on the `web` guard and its users are App\Models\User.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_permissions'),
            app(PermissionRegistrar::class)->pivotPermission,
            config('permission.column_names.model_morph_key')
        );
    }
}
