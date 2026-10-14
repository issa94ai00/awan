<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves roles and permissions onto spatie/laravel-permission.
 *
 * The `roles` and `permissions` tables are adopted in place rather than
 * recreated: their ids are what `feature_role` and `users.role_id` point at,
 * and a fresh table would orphan both. They gain the `guard_name` Spatie keys
 * everything by, plus `is_system` (built-in rows the code checks by name, so
 * they must not be renamed or deleted) and `color` for the dashboard.
 *
 * Assignments are copied from the old pivots into Spatie's:
 *  - `permission_role`        → `role_has_permissions`
 *  - `users.role_id` + `role_user` → `model_has_roles`
 *
 * The old pivots are left untouched, so rolling back loses nothing. Nothing
 * reads them after this migration.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const USER_MODEL = 'App\\Models\\User';

    private const SYSTEM_ROLES = ['master', 'admin', 'manager', 'employee', 'sells', 'accountant', 'marketer'];

    private const ROLE_COLORS = [
        'master' => '#7c3aed',
        'admin' => '#dc2626',
        'manager' => '#2563eb',
        'employee' => '#0891b2',
        'sells' => '#16a34a',
        'accountant' => '#d97706',
        'marketer' => '#db2777',
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('guard_name')->default(self::GUARD)->after('name');
            $table->string('color', 20)->nullable()->after('description');
            $table->boolean('is_system')->default(false)->after('is_active');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('guard_name')->default(self::GUARD)->after('name');
            $table->boolean('is_system')->default(false)->after('is_active');
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Every row that exists today is one the code or the seeders know by
        // name, so all of them are system rows.
        DB::table('permissions')->update(['is_system' => true]);
        DB::table('roles')->whereIn('name', self::SYSTEM_ROLES)->update(['is_system' => true]);
        foreach (self::ROLE_COLORS as $name => $color) {
            DB::table('roles')->where('name', $name)->update(['color' => $color]);
        }

        $rolePermissions = DB::table('permission_role')
            ->select('permission_id', 'role_id')
            ->distinct()
            ->get()
            ->map(fn ($row) => ['permission_id' => $row->permission_id, 'role_id' => $row->role_id])
            ->all();
        foreach (array_chunk($rolePermissions, 500) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }

        $primary = DB::table('users')->whereNotNull('role_id')->select('id as user_id', 'role_id')->get();
        $extra = Schema::hasTable('role_user')
            ? DB::table('role_user')->select('user_id', 'role_id')->get()
            : collect();
        $userRoles = $primary->concat($extra)
            ->unique(fn ($row) => $row->user_id.'-'.$row->role_id)
            ->map(fn ($row) => ['role_id' => $row->role_id, 'model_type' => self::USER_MODEL, 'model_id' => $row->user_id])
            ->values()
            ->all();
        foreach (array_chunk($userRoles, 500) as $chunk) {
            DB::table('model_has_roles')->insertOrIgnore($chunk);
        }

        // Permissions for the screens this migration makes real.
        $now = now();
        foreach ([
            ['name' => 'permissions.manage', 'display_name' => 'إدارة كتالوج الصلاحيات', 'module' => 'permissions'],
            ['name' => 'users.assign_roles', 'display_name' => 'تعيين أدوار المستخدمين', 'module' => 'users'],
        ] as $permission) {
            if (DB::table('permissions')->where('name', $permission['name'])->exists()) {
                continue;
            }
            $id = DB::table('permissions')->insertGetId($permission + [
                'guard_name' => self::GUARD,
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $superIds = DB::table('roles')->whereIn('name', ['admin', 'master'])->pluck('id');
            foreach ($superIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');

        DB::table('permissions')->whereIn('name', ['permissions.manage', 'users.assign_roles'])->delete();

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['guard_name', 'is_system']);
            $table->unique('name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['guard_name', 'color', 'is_system']);
            $table->unique('name');
        });

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        try {
            app('cache')
                ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key', 'spatie.permission.cache'));
        } catch (Throwable) {
            // A cold cache is the state we want anyway.
        }
    }
};
