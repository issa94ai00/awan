<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Add a new client" becomes its own permission (customers.create), checked by
 * POST /pos/customers. The sells role gets it, and so does every role that
 * could already add clients through the POS module, so no one loses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'customers.create',
            'guard_name' => 'web',
            'display_name' => 'إضافة عميل جديد',
            'module' => 'crm',
            'description' => 'إضافة عميل من شاشات العملاء والمبيعات وعروض الأسعار ونقطة البيع',
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', 'customers.create')->where('guard_name', 'web')->value('id');

        $posRoles = DB::table('feature_role')
            ->join('features', 'features.id', '=', 'feature_role.feature_id')
            ->where('features.key', 'pos')
            ->pluck('feature_role.role_id');
        $roleIds = DB::table('roles')->whereIn('name', ['master', 'admin', 'sells'])->pluck('id')
            ->merge($posRoles)->unique();

        DB::table('role_has_permissions')->insertOrIgnore(
            $roleIds->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->values()->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'customers.create')->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
