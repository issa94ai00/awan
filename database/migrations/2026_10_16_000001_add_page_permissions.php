<?php

use App\Support\PagePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds a permission for every admin sidebar page (App\Support\PagePermissions)
 * without taking a page away from anyone.
 *
 * Until now a page showed for every role granted its module on the Features
 * Management screen. So each role receives the page permissions of the
 * modules it holds, and the super roles receive all of them. Users, roles and
 * permissions pages are left out of that grant: the API checks them too, and
 * they were already gated by permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (PagePermissions::PAGES as $name => [$label, $module, , $path]) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if ($exists) {
                // Keep a description someone wrote; only fill the gap.
                DB::table('permissions')->where('name', $name)->where('guard_name', 'web')
                    ->whereNull('description')
                    ->update(['description' => "الصفحة: {$path}"]);

                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'guard_name' => 'web',
                'display_name' => $label,
                'module' => $module,
                'description' => "الصفحة: {$path}",
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', PagePermissions::names())
            ->pluck('id', 'name');

        $grants = [];
        foreach (DB::table('roles')->whereIn('name', ['master', 'admin'])->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $grants[] = ['permission_id' => $permissionId, 'role_id' => $roleId];
            }
        }

        $featureRoles = DB::table('feature_role')
            ->join('features', 'features.id', '=', 'feature_role.feature_id')
            ->select('features.key', 'feature_role.role_id')
            ->get()
            ->groupBy('key');

        foreach (PagePermissions::PAGES as $name => [, , $feature]) {
            if (in_array($name, PagePermissions::API_ENFORCED, true)) {
                continue;
            }
            foreach ($featureRoles->get($feature, collect()) as $row) {
                $grants[] = ['permission_id' => $permissionIds[$name], 'role_id' => $row->role_id];
            }
        }

        foreach (array_chunk($grants, 500) as $chunk) {
            DB::table('role_has_permissions')->insertOrIgnore($chunk);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Drops only the permissions this migration created; the reused ones
     * (products.view, sales.create, ...) existed before and stay.
     */
    public function down(): void
    {
        $preexisting = [
            'categories.view', 'products.view', 'sales.view', 'sales.create', 'purchases.view',
            'inventory.view', 'inventory.manage', 'hr.view', 'reports.view', 'inquiries.view',
            'users.view', 'roles.view', 'permissions.view',
        ];
        $created = array_values(array_diff(PagePermissions::names(), $preexisting));
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $created)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
