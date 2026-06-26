<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create roles table first
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create permission_role pivot table
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['permission_id', 'role_id']);
        });

        // Create role_user pivot table
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['role_id', 'user_id']);
        });

        // Add role_id to users table after roles table is created
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
            }
        });

        // Insert default roles
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على النظام',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manager',
                'display_name' => 'مدير',
                'description' => 'صلاحيات إدارية محدودة',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'employee',
                'display_name' => 'موظف',
                'description' => 'صلاحيات الموظفين',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert default permissions
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'عرض لوحة التحكم', 'module' => 'dashboard'],
            
            // Categories
            ['name' => 'categories.view', 'display_name' => 'عرض الفئات', 'module' => 'categories'],
            ['name' => 'categories.create', 'display_name' => 'إنشاء فئة', 'module' => 'categories'],
            ['name' => 'categories.edit', 'display_name' => 'تعديل فئة', 'module' => 'categories'],
            ['name' => 'categories.delete', 'display_name' => 'حذف فئة', 'module' => 'categories'],
            
            // Products
            ['name' => 'products.view', 'display_name' => 'عرض المنتجات', 'module' => 'products'],
            ['name' => 'products.create', 'display_name' => 'إنشاء منتج', 'module' => 'products'],
            ['name' => 'products.edit', 'display_name' => 'تعديل منتج', 'module' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'حذف منتج', 'module' => 'products'],
            
            // Inquiries
            ['name' => 'inquiries.view', 'display_name' => 'عرض الاستفسارات', 'module' => 'inquiries'],
            ['name' => 'inquiries.reply', 'display_name' => 'الرد على الاستفسارات', 'module' => 'inquiries'],
            ['name' => 'inquiries.delete', 'display_name' => 'حذف استفسار', 'module' => 'inquiries'],
            
            // Settings
            ['name' => 'settings.view', 'display_name' => 'عرض الإعدادات', 'module' => 'settings'],
            ['name' => 'settings.edit', 'display_name' => 'تعديل الإعدادات', 'module' => 'settings'],
            
            // Users
            ['name' => 'users.view', 'display_name' => 'عرض المستخدمين', 'module' => 'users'],
            ['name' => 'users.create', 'display_name' => 'إنشاء مستخدم', 'module' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'تعديل مستخدم', 'module' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'حذف مستخدم', 'module' => 'users'],
            
            // Roles
            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار', 'module' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'إنشاء دور', 'module' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'تعديل دور', 'module' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'حذف دور', 'module' => 'roles'],
            
            // Permissions
            ['name' => 'permissions.view', 'display_name' => 'عرض الصلاحيات', 'module' => 'permissions'],
            ['name' => 'permissions.assign', 'display_name' => 'تعيين صلاحيات', 'module' => 'permissions'],
            
            // Reports
            ['name' => 'reports.view', 'display_name' => 'عرض التقارير', 'module' => 'reports'],
            
            // Sales
            ['name' => 'sales.view', 'display_name' => 'عرض المبيعات', 'module' => 'sales'],
            ['name' => 'sales.create', 'display_name' => 'إنشاء مبيعات', 'module' => 'sales'],
            
            // Inventory
            ['name' => 'inventory.view', 'display_name' => 'عرض المخزون', 'module' => 'inventory'],
            ['name' => 'inventory.manage', 'display_name' => 'إدارة المخزون', 'module' => 'inventory'],
            
            // Purchases
            ['name' => 'purchases.view', 'display_name' => 'عرض المشتريات', 'module' => 'purchases'],
            ['name' => 'purchases.create', 'display_name' => 'إنشاء مشتريات', 'module' => 'purchases'],
            
            // HR
            ['name' => 'hr.view', 'display_name' => 'عرض الموارد البشرية', 'module' => 'hr'],
            ['name' => 'hr.manage', 'display_name' => 'إدارة الموارد البشرية', 'module' => 'hr'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert(array_merge($permission, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Assign all permissions to admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $allPermissions = DB::table('permissions')->pluck('id');
        
        foreach ($allPermissions as $permissionId) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => $adminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
        });
    }
};
