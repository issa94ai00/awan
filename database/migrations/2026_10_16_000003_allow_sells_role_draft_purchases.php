<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grant the sells role access to draft purchase orders/requests:
 * - Feature 'purchases' so the purchases section is accessible in UI
 * - Permissions 'purchases.view', 'purchases.orders.view', 'purchases.create', 'purchases.requests.view'
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $sellsRoleId = DB::table('roles')->where('name', 'sells')->value('id');
        $purchasesFeatureId = DB::table('features')->where('key', 'purchases')->value('id');

        if ($sellsRoleId && $purchasesFeatureId) {
            DB::table('feature_role')->insertOrIgnore([
                'feature_id' => $purchasesFeatureId,
                'role_id' => $sellsRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($sellsRoleId) {
            $permissionNames = [
                'purchases.view',
                'purchases.orders.view',
                'purchases.create',
                'purchases.requests.view',
            ];

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->pluck('id');

            DB::table('role_has_permissions')->insertOrIgnore(
                $permissionIds->map(fn ($permId) => [
                    'permission_id' => $permId,
                    'role_id' => $sellsRoleId,
                ])->values()->all()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $sellsRoleId = DB::table('roles')->where('name', 'sells')->value('id');
        $purchasesFeatureId = DB::table('features')->where('key', 'purchases')->value('id');

        if ($sellsRoleId && $purchasesFeatureId) {
            DB::table('feature_role')
                ->where('feature_id', $purchasesFeatureId)
                ->where('role_id', $sellsRoleId)
                ->delete();
        }

        if ($sellsRoleId) {
            $permissionNames = [
                'purchases.orders.view',
                'purchases.create',
                'purchases.requests.view',
            ];

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->pluck('id');

            DB::table('role_has_permissions')
                ->where('role_id', $sellsRoleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
