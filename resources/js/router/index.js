import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    {
        path: '/',
        redirect: '/admin/vue'
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
            // Inquiries
            {
                path: 'inquiries',
                name: 'admin.inquiries.index',
                component: () => import('@/views/admin/Inquiries.vue')
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
            }
        ]
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Navigation guard for authentication
router.beforeEach((to, from) => {
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

export default router;
