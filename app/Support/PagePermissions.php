<?php

namespace App\Support;

/**
 * One permission per admin sidebar page, so a role can be given exactly the
 * screens it needs rather than a whole module.
 *
 * The SPA maps each page's path to these names in
 * resources/js/config/pagePermissions.js (the sidebar, its search and the
 * router guard all read it); PagePermissionsTest keeps the two lists in step.
 *
 * Each module's overview page is `<module>.view`, which the role editor treats
 * as the gateway: granting any other page of the module grants it too.
 * A page that already had a fitting permission (products.view, sales.create,
 * inventory.manage, ...) reuses it.
 */
final class PagePermissions
{
    /**
     * name => [display name, module, feature key, path].
     *
     * The feature key is the Features Management module the page belongs to;
     * the grant that kept existing access on rollout went by it.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const PAGES = [
        // Content
        'categories.view' => ['عرض الفئات', 'categories', 'content', '/admin/categories'],
        'products.view' => ['عرض المنتجات', 'products', 'content', '/admin/products'],
        'products.units.view' => ['صفحة وحدات المنتجات', 'products', 'content', '/admin/products/units'],
        'products.catalog.view' => ['صفحة الكتالوج وعرض الأسعار', 'products', 'content', '/admin/products/price-offer'],
        'content.special_offers.view' => ['صفحة العروض الخاصة', 'content', 'content', '/admin/special-offers'],
        'content.secondary_navbar.view' => ['صفحة الشريط الثانوي', 'content', 'content', '/admin/secondary-navbar'],

        // Sales
        'sales.view' => ['عرض المبيعات', 'sales', 'sales', '/admin/sales'],
        'sales.invoices.view' => ['صفحة الفواتير', 'sales', 'sales', '/admin/sales/invoices'],
        'sales.create' => ['إنشاء مبيعات', 'sales', 'sales', '/admin/sales/invoices/create'],
        'sales.customers.view' => ['صفحة عملاء المبيعات', 'sales', 'sales', '/admin/sales/customers'],
        'sales.quotes.view' => ['صفحة عروض الأسعار', 'sales', 'sales', '/admin/sales/quotes'],
        'sales.orders.view' => ['صفحة أوامر البيع', 'sales', 'sales', '/admin/sales/sales-orders'],
        'sales.payments.view' => ['صفحة المدفوعات', 'sales', 'sales', '/admin/sales/payments'],
        'sales.reports.view' => ['صفحة تقارير المبيعات الاحترافية', 'sales', 'sales', '/admin/sales/reports'],

        // Returns (RMA)
        'rma.view' => ['نظرة عامة على المرتجعات', 'rma', 'rma', '/admin/rma'],
        'rma.create' => ['إنشاء طلب إرجاع', 'rma', 'rma', '/admin/rma/create'],
        'rma.credit_notes.view' => ['صفحة الإشعارات الدائنة', 'rma', 'rma', '/admin/rma/credit-notes'],

        // Purchases
        'purchases.view' => ['عرض المشتريات', 'purchases', 'purchases', '/admin/purchases'],
        'purchases.suppliers.view' => ['صفحة الموردين', 'purchases', 'purchases', '/admin/purchases/suppliers'],
        'purchases.orders.view' => ['صفحة أوامر الشراء', 'purchases', 'purchases', '/admin/purchases/orders'],
        'purchases.receipts.view' => ['صفحة الاستلامات', 'purchases', 'purchases', '/admin/purchases/receipts'],
        'purchases.payments.view' => ['صفحة دفعات الموردين', 'purchases', 'purchases', '/admin/purchases/payments'],
        'purchases.returns.view' => ['صفحة مرتجعات الشراء', 'purchases', 'purchases', '/admin/purchases/returns'],
        'purchases.report.view' => ['صفحة تقرير المشتريات', 'purchases', 'purchases', '/admin/purchases/report'],
        'purchases.requests.view' => ['صفحة طلبات الشراء', 'purchases', 'purchases', '/admin/purchase-requests'],

        // Accounting
        'accounting.view' => ['نظرة عامة على المحاسبة', 'accounting', 'accounting', '/admin/accounting'],
        'accounting.journal.view' => ['صفحة دفتر اليومية', 'accounting', 'accounting', '/admin/accounting/journal'],
        'accounting.chart_of_accounts.view' => ['صفحة دليل الحسابات', 'accounting', 'accounting', '/admin/accounting/chart-of-accounts'],
        'accounting.ledger.view' => ['صفحة دفتر الأستاذ', 'accounting', 'accounting', '/admin/accounting/ledger'],
        'accounting.trial_balance.view' => ['صفحة ميزان المراجعة', 'accounting', 'accounting', '/admin/accounting/trial-balance'],
        'accounting.income_statement.view' => ['صفحة قائمة الدخل', 'accounting', 'accounting', '/admin/accounting/income-statement'],
        'accounting.balance_sheet.view' => ['صفحة الميزانية العمومية', 'accounting', 'accounting', '/admin/accounting/balance-sheet'],
        'accounting.aging.view' => ['صفحة أعمار الذمم', 'accounting', 'accounting', '/admin/accounting/aging'],
        'accounting.cash_flow.view' => ['صفحة التدفقات النقدية', 'accounting', 'accounting', '/admin/accounting/cash-flow'],
        'accounting.party_statement.view' => ['صفحة كشف حساب', 'accounting', 'accounting', '/admin/accounting/party-statement'],
        'accounting.fixed_assets.view' => ['صفحة الأصول الثابتة', 'accounting', 'accounting', '/admin/accounting/fixed-assets'],
        'accounting.bank_reconciliation.view' => ['صفحة التسوية البنكية', 'accounting', 'accounting', '/admin/accounting/bank-reconciliation'],
        'accounting.cost_centers.view' => ['صفحة مراكز التكلفة', 'accounting', 'accounting', '/admin/accounting/cost-centers'],
        'accounting.vat_return.view' => ['صفحة إقرار ضريبة القيمة المضافة', 'accounting', 'accounting', '/admin/accounting/vat-return'],
        'accounting.periods.view' => ['صفحة الفترات المحاسبية', 'accounting', 'accounting', '/admin/accounting/periods'],

        // Inventory
        'inventory.view' => ['عرض المخزون', 'inventory', 'inventory', '/admin/inventory'],
        'inventory.manage' => ['إدارة المخزون', 'inventory', 'inventory', '/admin/stock'],
        'inventory.movements.view' => ['صفحة حركات المخزون', 'inventory', 'inventory', '/admin/inventory/movements'],

        // Warehouses (WMS)
        'wms.view' => ['نظرة عامة على المستودعات', 'wms', 'wms', '/admin/wms'],
        'wms.warehouses.view' => ['صفحة المستودعات', 'wms', 'wms', '/admin/wms/warehouses'],
        'wms.bins.view' => ['صفحة مواقع التخزين', 'wms', 'wms', '/admin/wms/bins'],
        'wms.picking.view' => ['صفحة قوائم الالتقاط', 'wms', 'wms', '/admin/wms/picking'],
        'wms.packing.view' => ['صفحة قوائم التعبئة', 'wms', 'wms', '/admin/wms/packing'],
        'wms.cycle_counts.view' => ['صفحة الجرد الدوري', 'wms', 'wms', '/admin/wms/cycle-counts'],
        'wms.performance.view' => ['صفحة أداء المستودعات', 'wms', 'wms', '/admin/wms/performance'],

        // Human resources
        'hr.view' => ['عرض الموارد البشرية', 'hr', 'hr', '/admin/hr'],
        'hr.employees.view' => ['صفحة الموظفين', 'hr', 'hr', '/admin/hr/employees'],
        'hr.employee_customers.view' => ['صفحة علاقة الموظفين بالعملاء', 'hr', 'hr', '/admin/hr/employee-customers'],
        'hr.attendance.view' => ['صفحة الحضور', 'hr', 'hr', '/admin/hr/attendance'],
        'hr.leaves.view' => ['صفحة الإجازات', 'hr', 'hr', '/admin/hr/leaves'],
        'hr.payrolls.view' => ['صفحة الرواتب', 'hr', 'hr', '/admin/hr/payrolls'],
        'hr.commissions.view' => ['صفحة كشف العمولات', 'hr', 'hr', '/admin/hr/commissions'],

        // CRM
        'crm.view' => ['نظرة عامة على إدارة العملاء', 'crm', 'crm', '/admin/crm'],
        'crm.customers.view' => ['صفحة عملاء CRM', 'crm', 'crm', '/admin/crm/customers'],
        'crm.tickets.view' => ['صفحة التذاكر', 'crm', 'crm', '/admin/crm/tickets'],

        // Production
        'production.view' => ['صفحة الإنتاج', 'production', 'production', '/admin/production'],

        // Reports
        'reports.view' => ['عرض التقارير', 'reports', 'reports', '/admin/reports'],
        'reports.sales.view' => ['صفحة تقرير المبيعات', 'reports', 'reports', '/admin/reports/sales'],
        'reports.inventory.view' => ['صفحة تقرير المخزون', 'reports', 'reports', '/admin/reports/inventory'],
        'reports.financial.view' => ['صفحة التقرير المالي', 'reports', 'reports', '/admin/reports/financial'],
        'reports.payroll.view' => ['صفحة تقرير الرواتب', 'reports', 'reports', '/admin/reports/payroll'],

        // BI & analytics
        'analytics.view' => ['نظرة عامة على التحليلات', 'analytics', 'bi_analytics', '/admin/analytics'],
        'analytics.sales.view' => ['صفحة تحليلات المبيعات', 'analytics', 'bi_analytics', '/admin/analytics/sales'],
        'analytics.inventory.view' => ['صفحة تحليلات المخزون', 'analytics', 'bi_analytics', '/admin/analytics/inventory'],
        'analytics.warehouse.view' => ['صفحة تحليلات المستودعات', 'analytics', 'bi_analytics', '/admin/analytics/warehouse'],
        'analytics.financial.view' => ['صفحة التحليلات المالية', 'analytics', 'bi_analytics', '/admin/analytics/financial'],
        'analytics.metrics.view' => ['صفحة المؤشرات', 'analytics', 'bi_analytics', '/admin/analytics/metrics'],
        'analytics.reports.view' => ['صفحة تقارير التحليلات', 'analytics', 'bi_analytics', '/admin/analytics/reports'],
        'analytics.dashboards.view' => ['صفحة لوحات التحليلات', 'analytics', 'bi_analytics', '/admin/analytics/dashboards'],

        // Workflows
        'workflows.view' => ['صفحة سير العمل', 'workflows', 'workflows', '/admin/workflows'],
        'workflows.create' => ['إنشاء سير عمل', 'workflows', 'workflows', '/admin/workflows/create'],

        // Notifications
        'notifications.view' => ['صفحة سجل الإشعارات', 'notifications', 'notifications_management', '/admin/notifications'],
        'notifications.templates.view' => ['صفحة قوالب الإشعارات', 'notifications', 'notifications_management', '/admin/notifications/templates'],
        'notifications.preferences.view' => ['صفحة تفضيلات الإشعارات', 'notifications', 'notifications_management', '/admin/notifications/preferences'],

        // Audit
        'audit.view' => ['نظرة عامة على سجل التدقيق', 'audit', 'audit_logs', '/admin/audit'],
        'audit.entity_logs.view' => ['صفحة سجلات الكيانات', 'audit', 'audit_logs', '/admin/audit/entity-logs'],
        'audit.statistics.view' => ['صفحة إحصائيات التدقيق', 'audit', 'audit_logs', '/admin/audit/statistics'],

        // Stand-alone pages
        'pos.view' => ['صفحة نقطة البيع', 'pos', 'pos', '/admin/pos'],
        'inquiries.view' => ['عرض الاستفسارات', 'inquiries', 'inquiries', '/admin/inquiries'],
        'visitors.view' => ['صفحة الزوار', 'visitors', 'visitors', '/admin/visitors'],

        // System. Users, roles and permissions were gated already and are
        // also checked by the API, so the rollout grant left them alone.
        'users.view' => ['عرض المستخدمين', 'users', 'system', '/admin/users'],
        'roles.view' => ['عرض الأدوار', 'roles', 'system', '/admin/roles'],
        'permissions.view' => ['عرض الصلاحيات', 'permissions', 'system', '/admin/permissions'],
        'currencies.view' => ['صفحة العملات', 'currencies', 'system', '/admin/currencies'],
    ];

    /** Page permissions the API also enforces; never granted wholesale. */
    public const API_ENFORCED = ['users.view', 'roles.view', 'permissions.view'];

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_keys(self::PAGES);
    }
}
