// The permission that opens each admin page, by path. It mirrors
// app/Support/PagePermissions.php; PagePermissionsTest keeps the two in step.
//
// The sidebar hides a page, its search skips it and the router guard turns it
// away unless the account holds the permission. The longest matching prefix
// wins, so a detail page such as /admin/sales/invoices/12 takes the invoices
// page's permission. Paths matching none (the dashboard, profile, settings)
// need no page permission; Features Management is gated as masterOnly.
const PAGE_PERMISSIONS = [
    ['/admin/categories', 'categories.view'],
    ['/admin/products', 'products.view'],
    ['/admin/products/units', 'products.units.view'],
    ['/admin/products/price-offer', 'products.catalog.view'],
    ['/admin/special-offers', 'content.special_offers.view'],
    ['/admin/secondary-navbar', 'content.secondary_navbar.view'],
    ['/admin/sales', 'sales.view'],
    ['/admin/sales/invoices', 'sales.invoices.view'],
    ['/admin/sales/invoices/create', 'sales.create'],
    ['/admin/sales/customers', 'sales.customers.view'],
    ['/admin/sales/quotes', 'sales.quotes.view'],
    ['/admin/sales/sales-orders', 'sales.orders.view'],
    ['/admin/sales/payments', 'sales.payments.view'],
    ['/admin/sales/reports', 'sales.reports.view'],
    ['/admin/rma', 'rma.view'],
    ['/admin/rma/create', 'rma.create'],
    ['/admin/purchases', 'purchases.view'],
    ['/admin/purchases/suppliers', 'purchases.suppliers.view'],
    ['/admin/purchases/orders', 'purchases.orders.view'],
    ['/admin/purchases/receipts', 'purchases.receipts.view'],
    ['/admin/purchases/payments', 'purchases.payments.view'],
    ['/admin/purchases/returns', 'purchases.returns.view'],
    ['/admin/purchases/report', 'purchases.report.view'],
    ['/admin/purchase-requests', 'purchases.requests.view'],
    ['/admin/accounting', 'accounting.view'],
    ['/admin/accounting/journal', 'accounting.journal.view'],
    ['/admin/accounting/chart-of-accounts', 'accounting.chart_of_accounts.view'],
    ['/admin/accounting/ledger', 'accounting.ledger.view'],
    ['/admin/accounting/trial-balance', 'accounting.trial_balance.view'],
    ['/admin/accounting/income-statement', 'accounting.income_statement.view'],
    ['/admin/accounting/balance-sheet', 'accounting.balance_sheet.view'],
    ['/admin/accounting/aging', 'accounting.aging.view'],
    ['/admin/accounting/cash-flow', 'accounting.cash_flow.view'],
    ['/admin/accounting/party-statement', 'accounting.party_statement.view'],
    ['/admin/accounting/fixed-assets', 'accounting.fixed_assets.view'],
    ['/admin/accounting/bank-reconciliation', 'accounting.bank_reconciliation.view'],
    ['/admin/accounting/cost-centers', 'accounting.cost_centers.view'],
    ['/admin/accounting/vat-return', 'accounting.vat_return.view'],
    ['/admin/accounting/periods', 'accounting.periods.view'],
    ['/admin/inventory', 'inventory.view'],
    ['/admin/stock', 'inventory.manage'],
    ['/admin/inventory/movements', 'inventory.movements.view'],
    ['/admin/wms', 'wms.view'],
    ['/admin/wms/warehouses', 'wms.warehouses.view'],
    ['/admin/wms/bins', 'wms.bins.view'],
    ['/admin/wms/picking', 'wms.picking.view'],
    ['/admin/wms/packing', 'wms.packing.view'],
    ['/admin/wms/cycle-counts', 'wms.cycle_counts.view'],
    ['/admin/wms/performance', 'wms.performance.view'],
    ['/admin/hr', 'hr.view'],
    ['/admin/hr/employees', 'hr.employees.view'],
    ['/admin/hr/employee-customers', 'hr.employee_customers.view'],
    ['/admin/hr/attendance', 'hr.attendance.view'],
    ['/admin/hr/leaves', 'hr.leaves.view'],
    ['/admin/hr/payrolls', 'hr.payrolls.view'],
    ['/admin/hr/commissions', 'hr.commissions.view'],
    ['/admin/crm', 'crm.view'],
    ['/admin/crm/customers', 'crm.customers.view'],
    ['/admin/crm/tickets', 'crm.tickets.view'],
    ['/admin/production', 'production.view'],
    ['/admin/reports', 'reports.view'],
    ['/admin/reports/sales', 'reports.sales.view'],
    ['/admin/reports/inventory', 'reports.inventory.view'],
    ['/admin/reports/financial', 'reports.financial.view'],
    ['/admin/reports/payroll', 'reports.payroll.view'],
    ['/admin/analytics', 'analytics.view'],
    ['/admin/analytics/sales', 'analytics.sales.view'],
    ['/admin/analytics/inventory', 'analytics.inventory.view'],
    ['/admin/analytics/warehouse', 'analytics.warehouse.view'],
    ['/admin/analytics/financial', 'analytics.financial.view'],
    ['/admin/analytics/metrics', 'analytics.metrics.view'],
    ['/admin/analytics/reports', 'analytics.reports.view'],
    ['/admin/analytics/dashboards', 'analytics.dashboards.view'],
    ['/admin/workflows', 'workflows.view'],
    ['/admin/workflows/create', 'workflows.create'],
    ['/admin/notifications', 'notifications.view'],
    ['/admin/notifications/templates', 'notifications.templates.view'],
    ['/admin/notifications/preferences', 'notifications.preferences.view'],
    ['/admin/audit', 'audit.view'],
    ['/admin/audit/entity-logs', 'audit.entity_logs.view'],
    ['/admin/audit/statistics', 'audit.statistics.view'],
    ['/admin/pos', 'pos.view'],
    ['/admin/inquiries', 'inquiries.view'],
    ['/admin/visitors', 'visitors.view'],
    ['/admin/users', 'users.view'],
    ['/admin/roles', 'roles.view'],
    // The matrix is also read-only reference for anyone who may view roles.
    ['/admin/permissions', ['permissions.view', 'roles.view']],
    ['/admin/currencies', 'currencies.view'],
];

const byLength = [...PAGE_PERMISSIONS].sort((a, b) => b[0].length - a[0].length);

/**
 * The permission an admin path needs (a list means any one of them), or null
 * when it needs none.
 */
export function pagePermissionFor(path) {
    const target = String(path || '').replace(/\/+$/, '');
    for (const [prefix, permission] of byLength) {
        if (target === prefix || target.startsWith(prefix + '/')) {
            return permission;
        }
    }
    return null;
}

export default PAGE_PERMISSIONS;
