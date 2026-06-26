<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'admin',
                'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على النظام',
                'is_active' => 1,
                'created_at' => '2026-05-31 22:34:10',
                'updated_at' => '2026-05-31 22:34:10',
            ],
            [
                'id' => 2,
                'name' => 'manager',
                'display_name' => 'مدير',
                'description' => 'صلاحيات إدارية محدودة',
                'is_active' => 1,
                'created_at' => '2026-05-31 22:34:10',
                'updated_at' => '2026-05-31 22:34:10',
            ],
            [
                'id' => 3,
                'name' => 'employee',
                'display_name' => 'موظف',
                'description' => 'صلاحيات الموظفين',
                'is_active' => 1,
                'created_at' => '2026-05-31 22:34:10',
                'updated_at' => '2026-05-31 22:34:10',
            ],
        ];

        DB::table('roles')->insertOrIgnore($roles);
    }
}
