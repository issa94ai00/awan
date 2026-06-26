import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { triggerFadeUp } from '@/utils/fadeUp';

const routes = [
    {
        path: '/',
        component: () => import('@/layouts/PublicLayout.vue'),
        children: [
            { path: '', name: 'home', component: () => import('@/views/public/Home.vue') },
            { path: 'about', name: 'about', component: () => import('@/views/public/About.vue') },
            { path: 'vision', name: 'vision', component: () => import('@/views/public/Vision.vue') },
            { path: 'contact', name: 'contact', component: () => import('@/views/public/Contact.vue') },
            { path: 'inquiry', name: 'inquiry', component: () => import('@/views/public/Inquiry.vue') },
            { path: 'purchase-request', name: 'purchase-request', component: () => import('@/views/public/PurchaseRequest.vue') },
            { path: 'categories', name: 'categories', component: () => import('@/views/public/Categories.vue') },
            { path: 'products', name: 'products', component: () => import('@/views/public/Products.vue') },
            { path: 'category/:slug', name: 'category.detail', component: () => import('@/views/public/CategoryDetail.vue') },
            { path: 'product/:slug', name: 'product.detail', component: () => import('@/views/public/ProductDetail.vue') },
            { path: 'cart', name: 'cart', component: () => import('@/views/public/Cart.vue') },
            { path: 'customer-orders', name: 'customer.orders', component: () => import('@/views/public/CustomerOrders.vue') },
        ]
    },
    {
        path: '/admin/vue',
        redirect: '/admin/dashboard'
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/Login.vue')
    },
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true },
        redirect: '/admin/dashboard',
        children: [
            {
                path: 'dashboard',
                name: 'admin.dashboard',
                component: () => import('@/views/admin/Dashboard.vue')
            },
            {
                path: 'products',
                name: 'admin.products.index',
                component: () => import('@/views/admin/products/Index.vue')
            },
            {
                path: 'products/create',
                name: 'admin.products.create',
                component: () => import('@/views/admin/products/Form.vue')
            },
            {
                path: 'products/:id',
                name: 'admin.products.show',
                component: () => import('@/views/admin/products/Show.vue')
            },
            {
                path: 'products/:id/edit',
                name: 'admin.products.edit',
                component: () => import('@/views/admin/products/Form.vue')
            },
            {
                path: 'products/units',
                name: 'admin.products.units',
                component: () => import('@/views/admin/products/ProductUnits.vue')
            },
            {
                path: 'categories',
                name: 'admin.categories.index',
                component: () => import('@/views/admin/categories/Index.vue')
            },
            {
                path: 'categories/create',
                name: 'admin.categories.create',
                component: () => import('@/views/admin/categories/Form.vue')
            },
            {
                path: 'categories/:id/edit',
                name: 'admin.categories.edit',
                component: () => import('@/views/admin/categories/Form.vue')
            },
            {
                path: 'settings',
                name: 'admin.settings',
                component: () => import('@/views/admin/Settings.vue')
            },
            {
                path: 'special-offers',
                name: 'admin.special-offers',
                component: () => import('@/views/admin/SpecialOffers.vue')
            },
            // Accounting
            {
                path: 'accounting',
                name: 'admin.accounting.index',
                component: () => import('@/views/admin/accounting/Index.vue')
            },
            {
                path: 'accounting/journal',
                name: 'admin.accounting.journal',
                component: () => import('@/views/admin/accounting/Journal.vue')
            },
            {
                path: 'accounting/ledger',
                name: 'admin.accounting.ledger',
                component: () => import('@/views/admin/accounting/Ledger.vue')
            },
            {
                path: 'accounting/trial-balance',
                name: 'admin.accounting.trial-balance',
                component: () => import('@/views/admin/accounting/TrialBalance.vue')
            },
            // Inventory
            {
                path: 'inventory',
                name: 'admin.inventory.index',
                component: () => import('@/views/admin/inventory/Index.vue')
            },
            {
                path: 'inventory/movements',
                name: 'admin.inventory.movements',
                component: () => import('@/views/admin/inventory/Movements.vue')
            },
            // Sales
            {
                path: 'sales',
                name: 'admin.sales.index',
                component: () => import('@/views/admin/sales/Index.vue')
            },
            {
                path: 'sales/invoices',
                name: 'admin.sales.invoices',
                component: () => import('@/views/admin/sales/Invoices.vue')
            },
            {
                path: 'sales/invoices/create',
                name: 'admin.sales.invoices.create',
                component: () => import('@/views/admin/sales/InvoiceForm.vue')
            },
            {
                path: 'sales/invoices/:id/edit',
                name: 'admin.sales.invoices.edit',
                component: () => import('@/views/admin/sales/InvoiceForm.vue')
            },
            {
                path: 'sales/customers',
                name: 'admin.sales.customers',
                component: () => import('@/views/admin/sales/Customers.vue')
            },
            {
                path: 'sales/quotes',
                name: 'admin.quotes.index',
                component: () => import('@/views/admin/sales/Quotes.vue')
            },
            {
                path: 'sales/sales-orders',
                name: 'admin.sales-orders.index',
                component: () => import('@/views/admin/sales/SalesOrders.vue')
            },
            {
                path: 'sales/payments',
                name: 'admin.payments.index',
                component: () => import('@/views/admin/sales/Payments.vue')
            },
            // Purchases
            {
                path: 'purchases',
                name: 'admin.purchases.index',
                component: () => import('@/views/admin/purchases/Index.vue')
            },
            {
                path: 'purchases/suppliers',
                name: 'admin.purchases.suppliers',
                component: () => import('@/views/admin/purchases/Suppliers.vue')
            },
            {
                path: 'purchases/orders',
                name: 'admin.purchases.orders',
                component: () => import('@/views/admin/purchases/Orders.vue')
            },
            {
                path: 'purchases/receipts',
                name: 'admin.purchase-receipts.index',
                component: () => import('@/views/admin/purchases/Receipts.vue')
            },
            // HR
            {
                path: 'hr',
                name: 'admin.hr.index',
                component: () => import('@/views/admin/hr/Index.vue')
            },
            {
                path: 'hr/employees',
                name: 'admin.hr.employees',
                component: () => import('@/views/admin/hr/Employees.vue')
            },
            {
                path: 'hr/employees/create',
                name: 'admin.hr.employees.create',
                component: () => import('@/views/admin/hr/EmployeeForm.vue')
            },
            {
                path: 'hr/employees/:id/edit',
                name: 'admin.hr.employees.edit',
                component: () => import('@/views/admin/hr/EmployeeForm.vue')
            },
            {
                path: 'hr/attendance',
                name: 'admin.hr.attendance',
                component: () => import('@/views/admin/hr/Attendance.vue')
            },
            {
                path: 'hr/attendance/create',
                name: 'admin.hr.attendance.create',
                component: () => import('@/views/admin/hr/AttendanceForm.vue')
            },
            {
                path: 'hr/attendance/:id/edit',
                name: 'admin.hr.attendance.edit',
                component: () => import('@/views/admin/hr/AttendanceForm.vue')
            },
            {
                path: 'hr/leaves',
                name: 'admin.hr.leaves',
                component: () => import('@/views/admin/hr/Leaves.vue')
            },
            {
                path: 'hr/leaves/create',
                name: 'admin.hr.leaves.create',
                component: () => import('@/views/admin/hr/LeaveRequestForm.vue')
            },
            {
                path: 'hr/leaves/:id/edit',
                name: 'admin.hr.leaves.edit',
                component: () => import('@/views/admin/hr/LeaveRequestForm.vue')
            },
            {
                path: 'hr/payrolls',
                name: 'admin.payrolls.index',
                component: () => import('@/views/admin/hr/Payrolls.vue')
            },
            // CRM
            {
                path: 'crm',
                name: 'admin.crm.index',
                component: () => import('@/views/admin/crm/Index.vue')
            },
            {
                path: 'crm/customers',
                name: 'admin.crm.customers',
                component: () => import('@/views/admin/crm/Customers.vue')
            },
            {
                path: 'crm/customers/create',
                name: 'admin.crm.customers.create',
                component: () => import('@/views/admin/crm/CustomerForm.vue')
            },
            {
                path: 'crm/customers/:id/edit',
                name: 'admin.crm.customers.edit',
                component: () => import('@/views/admin/crm/CustomerForm.vue')
            },
            {
                path: 'crm/tickets',
                name: 'admin.crm.tickets',
                component: () => import('@/views/admin/crm/Tickets.vue')
            },
            {
                path: 'crm/tickets/create',
                name: 'admin.crm.tickets.create',
                component: () => import('@/views/admin/crm/TicketForm.vue')
            },
            {
                path: 'crm/tickets/:id/edit',
                name: 'admin.crm.tickets.edit',
                component: () => import('@/views/admin/crm/TicketForm.vue')
            },
            // Reports
            {
                path: 'reports',
                name: 'admin.reports.index',
                component: () => import('@/views/admin/reports/Index.vue')
            },
            {
                path: 'reports/sales',
                name: 'admin.reports.sales',
                component: () => import('@/views/admin/reports/Sales.vue')
            },
            {
                path: 'reports/inventory',
                name: 'admin.reports.inventory',
                component: () => import('@/views/admin/reports/Inventory.vue')
            },
            {
                path: 'reports/financial',
                name: 'admin.reports.financial',
                component: () => import('@/views/admin/reports/Financial.vue')
            },
            {
                path: 'reports/payroll',
                name: 'admin.reports.payroll',
                component: () => import('@/views/admin/reports/Payroll.vue')
            },
            // Production
            {
                path: 'production',
                name: 'admin.production.index',
                component: () => import('@/views/admin/production/Index.vue')
            },
            // POS
            {
                path: 'pos',
                name: 'admin.pos',
                component: () => import('@/views/admin/Pos.vue')
            },
            // Purchase Requests
            {
                path: 'purchase-requests',
                name: 'admin.purchase-requests.index',
                component: () => import('@/views/admin/purchaseRequests/Index.vue')
            },
            {
                path: 'purchase-requests/:id',
                name: 'admin.purchase-requests.show',
                component: () => import('@/views/admin/purchaseRequests/Show.vue')
            },
            // Inquiries
            {
                path: 'inquiries',
                name: 'admin.inquiries.index',
                component: () => import('@/views/admin/Inquiries.vue')
            },
            {
                path: 'inquiries/:id',
                name: 'admin.inquiries.show',
                component: () => import('@/views/admin/inquiries/Show.vue')
            },
            // Visitors
            {
                path: 'visitors',
                name: 'admin.visitors.index',
                component: () => import('@/views/admin/Visitors.vue')
            },
            // Roles & Permissions
            {
                path: 'roles',
                name: 'admin.roles.index',
                component: () => import('@/views/admin/roles/Index.vue')
            },
            {
                path: 'permissions',
                name: 'admin.permissions.index',
                component: () => import('@/views/admin/permissions/Index.vue')
            },
            // WMS (Warehouse Management System)
            {
                path: 'wms',
                name: 'admin.wms.index',
                component: () => import('@/views/admin/wms/Index.vue')
            },
            {
                path: 'wms/warehouses',
                name: 'admin.wms.warehouses.index',
                component: () => import('@/views/admin/wms/Warehouses.vue')
            },
            {
                path: 'wms/warehouses/create',
                name: 'admin.wms.warehouses.create',
                component: () => import('@/views/admin/wms/WarehouseForm.vue')
            },
            {
                path: 'wms/warehouses/:id/edit',
                name: 'admin.wms.warehouses.edit',
                component: () => import('@/views/admin/wms/WarehouseForm.vue')
            },
            {
                path: 'wms/bins',
                name: 'admin.wms.bins.index',
                component: () => import('@/views/admin/wms/Bins.vue')
            },
            {
                path: 'wms/bins/create',
                name: 'admin.wms.bins.create',
                component: () => import('@/views/admin/wms/BinForm.vue')
            },
            {
                path: 'wms/bins/:id/edit',
                name: 'admin.wms.bins.edit',
                component: () => import('@/views/admin/wms/BinForm.vue')
            },
            {
                path: 'wms/picking',
                name: 'admin.wms.picking.index',
                component: () => import('@/views/admin/wms/PickingLists.vue')
            },
            {
                path: 'wms/picking/create',
                name: 'admin.wms.picking.create',
                component: () => import('@/views/admin/wms/PickingForm.vue')
            },
            {
                path: 'wms/packing',
                name: 'admin.wms.packing.index',
                component: () => import('@/views/admin/wms/PackingLists.vue')
            },
            {
                path: 'wms/packing/create',
                name: 'admin.wms.packing.create',
                component: () => import('@/views/admin/wms/PackingForm.vue')
            },
            {
                path: 'wms/cycle-counts',
                name: 'admin.wms.cycle-counts.index',
                component: () => import('@/views/admin/wms/CycleCounts.vue')
            },
            {
                path: 'wms/cycle-counts/create',
                name: 'admin.wms.cycle-counts.create',
                component: () => import('@/views/admin/wms/CycleCountForm.vue')
            },
            {
                path: 'wms/performance',
                name: 'admin.wms.performance',
                component: () => import('@/views/admin/wms/Performance.vue')
            },
            // Advanced Analytics
            {
                path: 'analytics',
                name: 'admin.analytics.index',
                component: () => import('@/views/admin/analytics/Index.vue')
            },
            {
                path: 'analytics/sales',
                name: 'admin.analytics.sales',
                component: () => import('@/views/admin/analytics/Sales.vue')
            },
            {
                path: 'analytics/inventory',
                name: 'admin.analytics.inventory',
                component: () => import('@/views/admin/analytics/Inventory.vue')
            },
            {
                path: 'analytics/warehouse',
                name: 'admin.analytics.warehouse',
                component: () => import('@/views/admin/analytics/Warehouse.vue')
            },
            {
                path: 'analytics/financial',
                name: 'admin.analytics.financial',
                component: () => import('@/views/admin/analytics/Financial.vue')
            },
            {
                path: 'analytics/metrics',
                name: 'admin.analytics.metrics',
                component: () => import('@/views/admin/analytics/Metrics.vue')
            },
            {
                path: 'analytics/reports',
                name: 'admin.analytics.reports',
                component: () => import('@/views/admin/analytics/Reports.vue')
            },
            {
                path: 'analytics/dashboards',
                name: 'admin.analytics.dashboards',
                component: () => import('@/views/admin/analytics/Dashboards.vue')
            },
            // Notifications
            {
                path: 'notifications',
                name: 'admin.notifications.index',
                component: () => import('@/views/admin/notifications/Index.vue')
            },
            {
                path: 'notifications/templates',
                name: 'admin.notifications.templates',
                component: () => import('@/views/admin/notifications/Templates.vue')
            },
            {
                path: 'notifications/templates/create',
                name: 'admin.notifications.templates.create',
                component: () => import('@/views/admin/notifications/TemplateForm.vue')
            },
            {
                path: 'notifications/templates/:id/edit',
                name: 'admin.notifications.templates.edit',
                component: () => import('@/views/admin/notifications/TemplateForm.vue')
            },
            {
                path: 'notifications/preferences',
                name: 'admin.notifications.preferences',
                component: () => import('@/views/admin/notifications/Preferences.vue')
            },
            // Workflows
            {
                path: 'workflows',
                name: 'admin.workflows.index',
                component: () => import('@/views/admin/workflows/Index.vue')
            },
            {
                path: 'workflows/create',
                name: 'admin.workflows.create',
                component: () => import('@/views/admin/workflows/Form.vue')
            },
            {
                path: 'workflows/:id',
                name: 'admin.workflows.show',
                component: () => import('@/views/admin/workflows/Show.vue')
            },
            {
                path: 'workflows/:id/edit',
                name: 'admin.workflows.edit',
                component: () => import('@/views/admin/workflows/Form.vue')
            },
            {
                path: 'workflows/:id/steps',
                name: 'admin.workflows.steps',
                component: () => import('@/views/admin/workflows/Steps.vue')
            },
            {
                path: 'workflows/:id/executions',
                name: 'admin.workflows.executions',
                component: () => import('@/views/admin/workflows/Executions.vue')
            },
            // Audit Logs
            {
                path: 'audit',
                name: 'admin.audit.index',
                component: () => import('@/views/admin/audit/Index.vue')
            },
            {
                path: 'audit/entity-logs',
                name: 'admin.audit.entity-logs',
                component: () => import('@/views/admin/audit/EntityLogs.vue')
            },
            {
                path: 'audit/user-activity/:id',
                name: 'admin.audit.user-activity',
                component: () => import('@/views/admin/audit/UserActivity.vue')
            },
            {
                path: 'audit/module-logs/:module',
                name: 'admin.audit.module-logs',
                component: () => import('@/views/admin/audit/ModuleLogs.vue')
            },
            {
                path: 'audit/statistics',
                name: 'admin.audit.statistics',
                component: () => import('@/views/admin/audit/Statistics.vue')
            },
            // RMA (Returns Management)
            {
                path: 'rma',
                name: 'admin.rma.index',
                component: () => import('@/views/admin/rma/Index.vue')
            },
            {
                path: 'rma/create',
                name: 'admin.rma.create',
                component: () => import('@/views/admin/rma/Form.vue')
            },
            {
                path: 'rma/:id',
                name: 'admin.rma.show',
                component: () => import('@/views/admin/rma/Show.vue')
            },
            {
                path: 'rma/:id/edit',
                name: 'admin.rma.edit',
                component: () => import('@/views/admin/rma/Form.vue')
            }
        ]
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/public/NotFound.vue')
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Navigation guard for authentication
router.beforeEach((to, from) => {
    console.log('Router navigating to:', to.path, 'Name:', to.name);
    const requiresAuth = to.matched.some(record => record.meta && record.meta.requiresAuth);
    if (!requiresAuth) return true;

    const auth = useAuthStore();
    const hasToken = !!auth.token;
    const isAuthenticated = auth.isAuthenticated;

    if (requiresAuth && !hasToken) {
        return { path: '/login', query: { redirect: to.fullPath } };
    }

    if (requiresAuth && hasToken && !isAuthenticated) {
        return auth.fetchUser().then(() => {
            if (!auth.isAuthenticated) {
                return { path: '/login', query: { redirect: to.fullPath } };
            }
            return true;
        }).catch(() => ({ path: '/login', query: { redirect: to.fullPath } }));
    }

    return true;
});

router.afterEach((to) => {
    // Delay fadeUp to run after page-fade out-in transition completes.
    // The primary trigger is @after-enter in PublicLayout; this is a safety fallback.
    setTimeout(() => {
        triggerFadeUp();
    }, 350);
});

router.onError((error, to) => {
    console.error('Router error occurred:', error);
    if (error.message.includes('Failed to fetch dynamically imported module') || 
        error.message.includes('error loading dynamically imported module') ||
        error.message.includes('Importing a classmate module') ||
        error.message.includes('dynamic import') ||
        error.name === 'ChunkLoadError') {
        console.warn('Chunk load failed. Force reloading page to fetch latest assets...');
        window.location.href = to.fullPath;
    }
});

export default router;
