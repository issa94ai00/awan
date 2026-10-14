<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Features are the ERP modules (sales, purchases, accounting, WMS, ...).
     * Each one can be switched off globally for the whole company, and each
     * role can be granted or denied access to it. The master (super admin)
     * role always sees everything that is enabled.
     */
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('icon')->default('Grid');
            $table->string('group')->default('general');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('feature_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['feature_id', 'role_id']);
        });

        // A dedicated super-admin role. Anyone holding it bypasses the feature
        // matrix entirely, the same way `is_admin` already does.
        $now = now();
        if (! DB::table('roles')->where('name', 'master')->exists()) {
            DB::table('roles')->insert([
                'name' => 'master',
                'display_name' => 'مدير رئيسي',
                'description' => 'صلاحيات كاملة وغير قابلة للتقييد على النظام',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Existing installs already have these roles from the roles migration;
        // fresh installs do too. Pull their ids once.
        $roles = DB::table('roles')->pluck('id', 'name')->all();

        // key => [name_ar, name_en, icon, group, core, roles that may access]
        // The access lists mirror the menu policy that used to be hard-coded in
        // AdminSidebar.vue and router/index.js, so behaviour does not change.
        $features = [
            'dashboard' => ['لوحة التحكم', 'Dashboard', 'Odometer', 'general', true, ['admin', 'manager', 'employee', 'sells', 'accountant', 'marketer']],
            'content' => ['إدارة المحتوى', 'Content Management', 'Box', 'content', false, ['admin', 'sells', 'marketer']],
            'sales' => ['المبيعات', 'Sales', 'ShoppingCart', 'commerce', false, ['admin', 'sells']],
            'rma' => ['المرتجعات', 'Returns (RMA)', 'Refresh', 'commerce', false, ['admin', 'sells']],
            'purchases' => ['المشتريات', 'Purchases', 'ShoppingBag', 'commerce', false, ['admin']],
            'accounting' => ['المحاسبة', 'Accounting', 'Coin', 'commerce', false, ['admin', 'accountant']],
            'inventory' => ['المخزون', 'Inventory', 'Box', 'inventory', false, ['admin', 'sells']],
            'wms' => ['إدارة المستودعات', 'Warehouse (WMS)', 'Location', 'inventory', false, ['admin']],
            'hr' => ['الموارد البشرية', 'Human Resources', 'UserFilled', 'hr', false, ['admin']],
            'crm' => ['إدارة العملاء', 'CRM', 'ChatDotRound', 'hr', false, ['admin', 'marketer']],
            'production' => ['الإنتاج', 'Production', 'Tools', 'hr', false, ['admin']],
            'reports' => ['التقارير', 'Reports', 'DataAnalysis', 'reports', false, ['admin', 'sells', 'accountant', 'marketer']],
            'bi_analytics' => ['التحليلات الذكية', 'BI Analytics', 'TrendCharts', 'reports', false, ['admin', 'accountant']],
            'workflows' => ['سير العمل', 'Workflows', 'Cpu', 'reports', false, ['admin']],
            'notifications_management' => ['إدارة الإشعارات', 'Notifications', 'Bell', 'system', false, ['admin']],
            'audit_logs' => ['سجل التدقيق', 'Audit Logs', 'View', 'system', false, ['admin']],
            'pos' => ['نقطة البيع', 'Point of Sale', 'Monitor', 'system', false, ['admin', 'manager', 'employee', 'sells']],
            'inquiries' => ['الاستفسارات', 'Inquiries', 'ChatLineRound', 'system', false, ['admin', 'marketer', 'sells']],
            'visitors' => ['الزوار', 'Visitors', 'View', 'system', false, ['admin']],
            'system' => ['إعدادات النظام', 'System Settings', 'Setting', 'system', true, ['admin']],
        ];

        $order = 0;
        foreach ($features as $key => [$ar, $en, $icon, $group, $core, $allowedRoles]) {
            $featureId = DB::table('features')->insertGetId([
                'key' => $key,
                'name_ar' => $ar,
                'name_en' => $en,
                'icon' => $icon,
                'group' => $group,
                'is_enabled' => true,
                'is_core' => $core,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($allowedRoles as $roleName) {
                if (! isset($roles[$roleName])) {
                    continue;
                }

                DB::table('feature_role')->insert([
                    'feature_id' => $featureId,
                    'role_id' => $roles[$roleName],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_role');
        Schema::dropIfExists('features');

        DB::table('roles')->where('name', 'master')->delete();
    }
};
