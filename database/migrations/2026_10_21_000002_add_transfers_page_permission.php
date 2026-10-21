<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The stock transfers page (/admin/wms/transfers) gets its page permission,
 * wms.transfers.view. Every role and every account that can already open the
 * warehouses overview gets it too: transfers are warehouse work, and the
 * branches' replenishment requests had no back-office screen until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'wms.transfers.view',
            'guard_name' => 'web',
            'display_name' => 'صفحة نقل المخزون',
            'module' => 'wms',
            'description' => 'نقل المخزون بين المستودعات وطلبات تزويد الفروع: إنشاؤها والموافقة عليها واستلامها وطباعتها',
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', 'wms.transfers.view')->where('guard_name', 'web')->value('id');
        $wmsViewId = DB::table('permissions')->where('name', 'wms.view')->where('guard_name', 'web')->value('id');

        $roleIds = DB::table('roles')->whereIn('name', ['master', 'admin'])->pluck('id');
        if ($wmsViewId) {
            $roleIds = $roleIds->merge(
                DB::table('role_has_permissions')->where('permission_id', $wmsViewId)->pluck('role_id')
            );

            DB::table('model_has_permissions')->insertOrIgnore(
                DB::table('model_has_permissions')->where('permission_id', $wmsViewId)->get()
                    ->map(fn ($row) => [
                        'permission_id' => $permissionId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ])->values()->all()
            );
        }

        DB::table('role_has_permissions')->insertOrIgnore(
            $roleIds->unique()->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->values()->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'wms.transfers.view')->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
