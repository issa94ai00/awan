<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * Roles and permissions all live on the `web` guard; the Sanctum guard
     * authenticates the same users and must resolve the same grants.
     */
    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'role_id',
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
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Keeps `role_id` — the primary role — inside the user's Spatie roles.
     * Changing it swaps the old primary out and the new one in; other roles
     * the user holds are left alone.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (! $user->wasRecentlyCreated && ! $user->wasChanged('role_id')) {
                return;
            }

            $old = $user->wasRecentlyCreated ? null : $user->getOriginal('role_id');
            if ($old && (int) $old !== (int) $user->role_id) {
                $user->roles()->detach($old);
            }
            if ($user->role_id) {
                $user->roles()->syncWithoutDetaching([$user->role_id]);
            }

            $user->unsetRelation('roles');
        });
    }

    /** The primary role: the one shown on the user's badge. */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Whether the user may do what the permission names. Super admins may do
     * anything. Otherwise the permission has to be active and come from an
     * active role the user holds or from a direct grant. An unknown name is a
     * plain no rather than an exception.
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permissionName, $this->effectivePermissionNames(), true);
    }

    /** @param  array<int, string>  $permissionNames */
    public function hasAnyPermissionNamed(array $permissionNames): bool
    {
        foreach ($permissionNames as $name) {
            if ($this->hasPermission($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of every active permission the user holds, through active roles
     * or directly. Super admins hold the whole active catalogue.
     *
     * @return array<int, string>
     */
    public function effectivePermissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::where('is_active', true)->orderBy('name')->pluck('name')->all();
        }

        $this->loadMissing(['roles.permissions', 'permissions']);

        return $this->roles
            ->where('is_active', true)
            ->flatMap(fn (Role $role) => $role->permissions)
            ->concat($this->permissions)
            ->where('is_active', true)
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Ids of the roles the user holds. Falls back to `role_id` in case the
     * pivot has not caught up (rows written straight to the table).
     *
     * @return array<int, int>
     */
    public function roleIds(): array
    {
        return $this->roles->pluck('id')
            ->push($this->role_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * The master role is a super admin that cannot be restricted by the feature
     * matrix. `is_admin` accounts and the built-in `admin` role count too.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_admin || $this->holdsRoleNamed(Role::SUPER_ROLES);
    }

    public function isMaster(): bool
    {
        return $this->holdsRoleNamed(['master']);
    }

    /** Holds master or admin as a role (as opposed to the `is_admin` flag). */
    public function holdsSuperRole(): bool
    {
        return $this->holdsRoleNamed(Role::SUPER_ROLES);
    }

    /** @param  array<int, string>  $names */
    private function holdsRoleNamed(array $names): bool
    {
        if ($this->roles->whereIn('name', $names)->isNotEmpty()) {
            return true;
        }

        return $this->role_id && $this->role && in_array($this->role->name, $names, true);
    }

    public function inquiries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * The staff record behind this login, if there is one. It carries the
     * warehouse the person works out of, which is what scopes the field app.
     */
    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Whether this user may change or delete existing products. The sells
     * role may only add products and look at them; admins are never held back.
     */
    public function mayEditProducts(): bool
    {
        return $this->isSuperAdmin() || ! $this->holdsRoleNamed(['sells']);
    }

    /**
     * Whether this user is restricted to only their own sales and assigned warehouse.
     * Super admins and managers are unconfined.
     */
    public function isSalesScoped(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        if ($this->holdsRoleNamed(['manager'])) {
            return false;
        }

        return $this->holdsRoleNamed(['sells', 'employee']) || $this->employee !== null;
    }

    /**
     * The warehouse ID this user is confined to, if any.
     */
    public function scopedWarehouseId(): ?int
    {
        if (! $this->isSalesScoped()) {
            return null;
        }

        return $this->employee?->warehouse_id ? (int) $this->employee->warehouse_id : null;
    }

    /**
     * Whether this user may deal with the customer: a sales-scoped user only
     * with those they serve (Customer::scopeServedBy), everyone else with any.
     */
    public function servesCustomer(int $customerId): bool
    {
        if (! $this->isSalesScoped()) {
            return true;
        }

        $employeeId = $this->employee?->id;

        return $employeeId && Customer::servedBy($employeeId)->whereKey($customerId)->exists();
    }
}
