<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the master role every permission and assign it to the master user.
     */
    public function up(): void
    {
        $now = now();

        $masterId = DB::table('roles')->where('name', 'master')->value('id');
        if (! $masterId) {
            $masterId = DB::table('roles')->insertGetId([
                'name' => 'master',
                'display_name' => 'مدير رئيسي',
                'description' => 'صلاحيات كاملة وغير قابلة للتقييد على النظام',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $granted = DB::table('permission_role')->where('role_id', $masterId)->pluck('permission_id')->all();
        $rows = DB::table('permissions')->whereNotIn('id', $granted)->pluck('id')
            ->map(fn ($id) => ['permission_id' => $id, 'role_id' => $masterId, 'created_at' => $now, 'updated_at' => $now])
            ->all();
        if ($rows) {
            DB::table('permission_role')->insert($rows);
        }

        $userId = DB::table('users')->where('email', 'master@awaanaltakadom.sy')->value('id');
        if ($userId) {
            DB::table('users')->where('id', $userId)->update(['role_id' => $masterId, 'is_admin' => true]);
            DB::table('role_user')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $masterId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $masterId = DB::table('roles')->where('name', 'master')->value('id');
        if ($masterId) {
            DB::table('permission_role')->where('role_id', $masterId)->delete();
        }
    }
};
