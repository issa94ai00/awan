<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * A quote must be approved by a manager before a rep may send or share it.
 *
 * Quotes that exist already were sent under the old rules, so they are marked
 * approved rather than suddenly locked. The new quotes.approve permission goes
 * to master, admin and manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('status')->index();
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');
        });

        DB::table('quotes')->update(['approval_status' => 'approved', 'approved_at' => now()]);

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'quotes.approve',
            'guard_name' => 'web',
            'display_name' => 'اعتماد عروض الأسعار',
            'module' => 'sales',
            'description' => 'اعتماد أو رفض عرض السعر قبل أن يرسله المندوب للعميل',
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', 'quotes.approve')->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->whereIn('name', ['master', 'admin', 'manager'])->pluck('id');

        DB::table('role_has_permissions')->insertOrIgnore(
            $roleIds->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->values()->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at', 'approval_note']);
        });

        $id = DB::table('permissions')->where('name', 'quotes.approve')->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
