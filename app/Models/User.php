<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

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

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function permissions()
    {
        return $this->role ? $this->role->permissions : collect();
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->is_admin || $this->isMaster()) {
            return true;
        }

        return $this->role && $this->role->hasPermission($permissionName);
    }

    public function hasRole(string $roleName): bool
    {
        if ($this->is_admin || $this->isMaster()) {
            return true;
        }

        return $this->role && $this->role->name === $roleName;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin
            || ($this->role && in_array($this->role->name, ['admin', 'master'], true));
    }

    /**
     * The master role is a super admin that cannot be restricted by the feature
     * matrix. `is_admin` accounts and the built-in `admin` role count too.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin
            || ($this->role && in_array($this->role->name, ['admin', 'master'], true));
    }

    public function isMaster(): bool
    {
        return $this->role && $this->role->name === 'master';
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
}
