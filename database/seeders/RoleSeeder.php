<?php

namespace Database\Seeders;

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
                'name' => 'admin',
                'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على النظام',
                'is_active' => true,
            ],
            [
                'name' => 'manager',
                'display_name' => 'مدير',
                'description' => 'صلاحيات إدارية محدودة',
                'is_active' => true,
            ],
            [
                'name' => 'employee',
                'display_name' => 'موظف',
                'description' => 'صلاحيات الموظفين',
                'is_active' => true,
            ],
            [
                'name' => 'sells',
                'display_name' => 'مبيعات',
                'description' => 'صلاحية إدارة المبيعات والطلبات',
                'is_active' => true,
            ],
            [
                'name' => 'accountant',
                'display_name' => 'محاسب',
                'description' => 'صلاحية متابعة الحسابات والتقارير المالية',
                'is_active' => true,
            ],
            [
                'name' => 'marketer',
                'display_name' => 'مسوق',
                'description' => 'صلاحية متابعة التسويق والعروض',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'display_name' => $role['display_name'],
                    'description' => $role['description'],
                    'is_active' => $role['is_active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $permissions = [
            ['name' => 'dashboard.view', 'display_name' => 'عرض لوحة التحكم', 'module' => 'dashboard'],
            ['name' => 'categories.view', 'display_name' => 'عرض الفئات', 'module' => 'categories'],
            ['name' => 'categories.create', 'display_name' => 'إنشاء فئة', 'module' => 'categories'],
            ['name' => 'categories.edit', 'display_name' => 'تعديل فئة', 'module' => 'categories'],
            ['name' => 'categories.delete', 'display_name' => 'حذف فئة', 'module' => 'categories'],
            ['name' => 'products.view', 'display_name' => 'عرض المنتجات', 'module' => 'products'],
            ['name' => 'products.create', 'display_name' => 'إنشاء منتج', 'module' => 'products'],
            ['name' => 'products.edit', 'display_name' => 'تعديل منتج', 'module' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'حذف منتج', 'module' => 'products'],
            ['name' => 'inquiries.view', 'display_name' => 'عرض الاستفسارات', 'module' => 'inquiries'],
            ['name' => 'inquiries.reply', 'display_name' => 'الرد على الاستفسارات', 'module' => 'inquiries'],
            ['name' => 'settings.view', 'display_name' => 'عرض الإعدادات', 'module' => 'settings'],
            ['name' => 'settings.edit', 'display_name' => 'تعديل الإعدادات', 'module' => 'settings'],
            ['name' => 'users.view', 'display_name' => 'عرض المستخدمين', 'module' => 'users'],
            ['name' => 'users.create', 'display_name' => 'إنشاء مستخدم', 'module' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'تعديل مستخدم', 'module' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'حذف مستخدم', 'module' => 'users'],
            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار', 'module' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'إنشاء دور', 'module' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'تعديل دور', 'module' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'حذف دور', 'module' => 'roles'],
            ['name' => 'permissions.view', 'display_name' => 'عرض الصلاحيات', 'module' => 'permissions'],
            ['name' => 'permissions.assign', 'display_name' => 'تعيين صلاحيات', 'module' => 'permissions'],
            ['name' => 'reports.view', 'display_name' => 'عرض التقارير', 'module' => 'reports'],
            ['name' => 'sales.view', 'display_name' => 'عرض المبيعات', 'module' => 'sales'],
            ['name' => 'sales.create', 'display_name' => 'إنشاء مبيعات', 'module' => 'sales'],
            ['name' => 'inventory.view', 'display_name' => 'عرض المخزون', 'module' => 'inventory'],
            ['name' => 'inventory.manage', 'display_name' => 'إدارة المخزون', 'module' => 'inventory'],
            ['name' => 'purchases.view', 'display_name' => 'عرض المشتريات', 'module' => 'purchases'],
            ['name' => 'purchases.create', 'display_name' => 'إنشاء مشتريات', 'module' => 'purchases'],
            ['name' => 'hr.view', 'display_name' => 'عرض الموارد البشرية', 'module' => 'hr'],
            ['name' => 'hr.manage', 'display_name' => 'إدارة الموارد البشرية', 'module' => 'hr'],
            ['name' => 'marketing.view', 'display_name' => 'عرض التسويق', 'module' => 'marketing'],
            ['name' => 'marketing.campaigns', 'display_name' => 'إدارة الحملات', 'module' => 'marketing'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'module' => $permission['module'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $rolePermissions = [
            'admin' => DB::table('permissions')->pluck('id')->all(),
            'manager' => ['dashboard.view', 'products.view', 'products.create', 'products.edit', 'sales.view', 'sales.create', 'inventory.view', 'inventory.manage', 'reports.view'],
            'employee' => ['dashboard.view', 'sales.view'],
            'sells' => ['dashboard.view', 'products.view', 'sales.view', 'sales.create', 'inventory.view', 'reports.view'],
            'accountant' => ['dashboard.view', 'sales.view', 'inventory.view', 'purchases.view', 'reports.view', 'settings.view'],
            'marketer' => ['dashboard.view', 'products.view', 'marketing.view', 'marketing.campaigns', 'sales.view', 'reports.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            $ids = is_array($permissionNames) ? DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id')->all() : $permissionNames;

            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }
}
