<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The credit notes list (/admin/rma/credit-notes) gets its page permission,
 * rma.credit_notes.view. Every role and every account that can already open
 * the returns overview gets it too: the notes were reachable from each
 * return before this page existed, so no one loses sight of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'rma.credit_notes.view',
            'guard_name' => 'web',
            'display_name' => 'صفحة الإشعارات الدائنة',
            'module' => 'rma',
            'description' => 'قائمة الإشعارات الدائنة الناتجة عن تسوية المرتجعات، وطباعتها وإلغاء غير المستخدم منها',
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', 'rma.credit_notes.view')->where('guard_name', 'web')->value('id');
        $rmaViewId = DB::table('permissions')->where('name', 'rma.view')->where('guard_name', 'web')->value('id');

        $roleIds = DB::table('roles')->whereIn('name', ['master', 'admin'])->pluck('id');
        if ($rmaViewId) {
            $roleIds = $roleIds->merge(
                DB::table('role_has_permissions')->where('permission_id', $rmaViewId)->pluck('role_id')
            );

            DB::table('model_has_permissions')->insertOrIgnore(
                DB::table('model_has_permissions')->where('permission_id', $rmaViewId)->get()
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
        $id = DB::table('permissions')->where('name', 'rma.credit_notes.view')->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
