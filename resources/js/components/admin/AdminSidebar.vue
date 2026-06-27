<template>
    <aside class="admin-sidebar" :class="{ collapsed: collapsed, 'mobile-open': mobileOpen }">
        <div class="sidebar-header">
            <div class="logo">
                <el-icon :size="28"><Box /></el-icon>
                <span v-if="!collapsed">{{ siteName }}</span>
            </div>
            <el-button
                v-if="!collapsed && !mobileOpen"
                :icon="Fold"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <el-button
                v-if="mobileOpen"
                :icon="Close"
                circle
                size="small"
                @click="closeMobile"
                class="mobile-close-btn"
            />
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li>
                    <router-link to="/admin/dashboard" class="nav-link" :class="{ active: isActive('/admin/dashboard') }">
                        <el-icon><Odometer /></el-icon>
                        <span v-if="!collapsed">{{ t('dashboard') }}</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('content') }">
                    <div class="nav-group-header" @click="toggleGroup('content')">
                        <el-icon><Box /></el-icon>
                        <span v-if="!collapsed">{{ t('content_management') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('content') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('content')">
                        <li>
                            <router-link to="/admin/categories" class="nav-link">
                                <el-icon><Folder /></el-icon>
                                <span v-if="!collapsed">{{ t('categories') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products" class="nav-link">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">{{ t('products') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products/units" class="nav-link" :class="{ active: isActive('/admin/products/units') }">
                                <el-icon><ScaleToOriginal /></el-icon>
                                <span v-if="!collapsed">{{ t('product_units') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/special-offers" class="nav-link" :class="{ active: isActive('/admin/special-offers') }">
                                <el-icon><Discount /></el-icon>
                                <span v-if="!collapsed">{{ t('special_offers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/secondary-navbar" class="nav-link" :class="{ active: isActive('/admin/secondary-navbar') }">
                                <el-icon><List /></el-icon>
                                <span v-if="!collapsed">{{ t('secondary_navbar') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('sales') }">
                    <div class="nav-group-header" @click="toggleGroup('sales')">
                        <el-icon><ShoppingCart /></el-icon>
                        <span v-if="!collapsed">{{ t('sales') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('sales') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('sales')">
                        <li>
                            <router-link to="/admin/sales" class="nav-link" :class="{ active: isActive('/admin/sales') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/invoices" class="nav-link" :class="{ active: isActive('/admin/sales/invoices') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('invoices') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/invoices/create" class="nav-link" :class="{ active: isActive('/admin/sales/invoices/create') }">
                                <el-icon><DocumentAdd /></el-icon>
                                <span v-if="!collapsed">{{ t('create_invoice') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/customers" class="nav-link" :class="{ active: isActive('/admin/sales/customers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">{{ t('customers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/quotes" class="nav-link" :class="{ active: isActive('/admin/sales/quotes') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('quotes') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/sales-orders" class="nav-link" :class="{ active: isActive('/admin/sales/sales-orders') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('sales_orders') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/payments" class="nav-link" :class="{ active: isActive('/admin/sales/payments') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">{{ t('payments') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('rma') }">
                    <div class="nav-group-header" @click="toggleGroup('rma')">
                        <el-icon><Refresh /></el-icon>
                        <span v-if="!collapsed">{{ t('rma') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('rma') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('rma')">
                        <li>
                            <router-link to="/admin/rma" class="nav-link" :class="{ active: isActive('/admin/rma') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/rma/create" class="nav-link" :class="{ active: isActive('/admin/rma/create') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('create') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('purchases') }">
                    <div class="nav-group-header" @click="toggleGroup('purchases')">
                        <el-icon><ShoppingBag /></el-icon>
                        <span v-if="!collapsed">{{ t('purchases') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('purchases') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('purchases')">
                        <li>
                            <router-link to="/admin/purchases" class="nav-link" :class="{ active: isActive('/admin/purchases') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/suppliers" class="nav-link" :class="{ active: isActive('/admin/purchases/suppliers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">{{ t('suppliers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/orders" class="nav-link" :class="{ active: isActive('/admin/purchases/orders') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('purchase_orders') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/receipts" class="nav-link" :class="{ active: isActive('/admin/purchases/receipts') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('receipts') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('accounting') }">
                    <div class="nav-group-header" @click="toggleGroup('accounting')">
                        <el-icon><Coin /></el-icon>
                        <span v-if="!collapsed">{{ t('accounting') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('accounting') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('accounting')">
                        <li>
                            <router-link to="/admin/accounting" class="nav-link" :class="{ active: isActive('/admin/accounting') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/journal" class="nav-link" :class="{ active: isActive('/admin/accounting/journal') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('journal') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/ledger" class="nav-link" :class="{ active: isActive('/admin/accounting/ledger') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('ledger') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/trial-balance" class="nav-link" :class="{ active: isActive('/admin/accounting/trial-balance') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('trial_balance') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('inventory') }">
                    <div class="nav-group-header" @click="toggleGroup('inventory')">
                        <el-icon><Box /></el-icon>
                        <span v-if="!collapsed">{{ t('inventory') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('inventory') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('inventory')">
                        <li>
                            <router-link to="/admin/inventory" class="nav-link" :class="{ active: isActive('/admin/inventory') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/inventory/movements" class="nav-link" :class="{ active: isActive('/admin/inventory/movements') }">
                                <el-icon><Refresh /></el-icon>
                                <span v-if="!collapsed">{{ t('stock_movements') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('wms') }">
                    <div class="nav-group-header" @click="toggleGroup('wms')">
                        <el-icon><Location /></el-icon>
                        <span v-if="!collapsed">{{ t('wms') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('wms') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('wms')">
                        <li>
                            <router-link to="/admin/wms" class="nav-link" :class="{ active: isActive('/admin/wms') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/warehouses" class="nav-link" :class="{ active: isActive('/admin/wms/warehouses') }">
                                <el-icon><Folder /></el-icon>
                                <span v-if="!collapsed">{{ t('warehouses') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/bins" class="nav-link" :class="{ active: isActive('/admin/wms/bins') }">
                                <el-icon><List /></el-icon>
                                <span v-if="!collapsed">{{ t('bins') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/picking" class="nav-link" :class="{ active: isActive('/admin/wms/picking') }">
                                <el-icon><Checked /></el-icon>
                                <span v-if="!collapsed">{{ t('picking_lists') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/packing" class="nav-link" :class="{ active: isActive('/admin/wms/packing') }">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">{{ t('packing_lists') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/cycle-counts" class="nav-link" :class="{ active: isActive('/admin/wms/cycle-counts') }">
                                <el-icon><Calendar /></el-icon>
                                <span v-if="!collapsed">{{ t('cycle_counts') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/performance" class="nav-link" :class="{ active: isActive('/admin/wms/performance') }">
                                <el-icon><Odometer /></el-icon>
                                <span v-if="!collapsed">{{ t('wms_performance') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('hr') }">
                    <div class="nav-group-header" @click="toggleGroup('hr')">
                        <el-icon><UserFilled /></el-icon>
                        <span v-if="!collapsed">{{ t('hr') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('hr') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('hr')">
                        <li>
                            <router-link to="/admin/hr" class="nav-link" :class="{ active: isActive('/admin/hr') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/employees" class="nav-link" :class="{ active: isActive('/admin/hr/employees') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">{{ t('employees') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/attendance" class="nav-link" :class="{ active: isActive('/admin/hr/attendance') }">
                                <el-icon><Clock /></el-icon>
                                <span v-if="!collapsed">{{ t('attendance') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/leaves" class="nav-link" :class="{ active: isActive('/admin/hr/leaves') }">
                                <el-icon><Calendar /></el-icon>
                                <span v-if="!collapsed">{{ t('leaves') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/payrolls" class="nav-link" :class="{ active: isActive('/admin/hr/payrolls') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">{{ t('payrolls') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('crm') }">
                    <div class="nav-group-header" @click="toggleGroup('crm')">
                        <el-icon><ChatDotRound /></el-icon>
                        <span v-if="!collapsed">{{ t('crm') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('crm') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('crm')">
                        <li>
                            <router-link to="/admin/crm" class="nav-link" :class="{ active: isActive('/admin/crm') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/customers" class="nav-link" :class="{ active: isActive('/admin/crm/customers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">{{ t('customers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/tickets" class="nav-link" :class="{ active: isActive('/admin/crm/tickets') }">
                                <el-icon><Ticket /></el-icon>
                                <span v-if="!collapsed">{{ t('tickets') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('production') }">
                    <div class="nav-group-header" @click="toggleGroup('production')">
                        <el-icon><Tools /></el-icon>
                        <span v-if="!collapsed">{{ t('production') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('production') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('production')">
                        <li>
                            <router-link to="/admin/production" class="nav-link" :class="{ active: isActive('/admin/production') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('reports') }">
                    <div class="nav-group-header" @click="toggleGroup('reports')">
                        <el-icon><DataAnalysis /></el-icon>
                        <span v-if="!collapsed">{{ t('reports') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('reports') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('reports')">
                        <li>
                            <router-link to="/admin/reports" class="nav-link" :class="{ active: isActive('/admin/reports') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/sales" class="nav-link" :class="{ active: isActive('/admin/reports/sales') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('sales_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/inventory" class="nav-link" :class="{ active: isActive('/admin/reports/inventory') }">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">{{ t('inventory_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/financial" class="nav-link" :class="{ active: isActive('/admin/reports/financial') }">
                                <el-icon><Coin /></el-icon>
                                <span v-if="!collapsed">{{ t('financial_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/payroll" class="nav-link" :class="{ active: isActive('/admin/reports/payroll') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">{{ t('payroll_report') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('bi_analytics') }">
                    <div class="nav-group-header" @click="toggleGroup('bi_analytics')">
                        <el-icon><DataAnalysis /></el-icon>
                        <span v-if="!collapsed">{{ t('bi_analytics') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('bi_analytics') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('bi_analytics')">
                        <li>
                            <router-link to="/admin/analytics" class="nav-link" :class="{ active: isActive('/admin/analytics') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/sales" class="nav-link" :class="{ active: isActive('/admin/analytics/sales') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_sales') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/inventory" class="nav-link" :class="{ active: isActive('/admin/analytics/inventory') }">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_inventory') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/warehouse" class="nav-link" :class="{ active: isActive('/admin/analytics/warehouse') }">
                                <el-icon><Location /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_warehouse') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/financial" class="nav-link" :class="{ active: isActive('/admin/analytics/financial') }">
                                <el-icon><Coin /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_financial') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/metrics" class="nav-link" :class="{ active: isActive('/admin/analytics/metrics') }">
                                <el-icon><Odometer /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_metrics') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/reports" class="nav-link" :class="{ active: isActive('/admin/analytics/reports') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_reports') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/dashboards" class="nav-link" :class="{ active: isActive('/admin/analytics/dashboards') }">
                                <el-icon><DataAnalysis /></el-icon>
                                <span v-if="!collapsed">{{ t('analytics_dashboards') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('workflows') }">
                    <div class="nav-group-header" @click="toggleGroup('workflows')">
                        <el-icon><Cpu /></el-icon>
                        <span v-if="!collapsed">{{ t('workflows') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('workflows') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('workflows')">
                        <li>
                            <router-link to="/admin/workflows" class="nav-link" :class="{ active: isActive('/admin/workflows') }">
                                <el-icon><List /></el-icon>
                                <span v-if="!collapsed">{{ t('workflow_list') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/workflows/create" class="nav-link" :class="{ active: isActive('/admin/workflows/create') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('create') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('notifications_management') }">
                    <div class="nav-group-header" @click="toggleGroup('notifications_management')">
                        <el-icon><Bell /></el-icon>
                        <span v-if="!collapsed">{{ t('notifications_management') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('notifications_management') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('notifications_management')">
                        <li>
                            <router-link to="/admin/notifications" class="nav-link" :class="{ active: isActive('/admin/notifications') }">
                                <el-icon><List /></el-icon>
                                <span v-if="!collapsed">{{ t('notification_logs') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/notifications/templates" class="nav-link" :class="{ active: isActive('/admin/notifications/templates') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('notification_templates') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/notifications/preferences" class="nav-link" :class="{ active: isActive('/admin/notifications/preferences') }">
                                <el-icon><Setting /></el-icon>
                                <span v-if="!collapsed">{{ t('notification_preferences') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('audit_logs') }">
                    <div class="nav-group-header" @click="toggleGroup('audit_logs')">
                        <el-icon><View /></el-icon>
                        <span v-if="!collapsed">{{ t('audit_logs') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('audit_logs') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('audit_logs')">
                        <li>
                            <router-link to="/admin/audit" class="nav-link" :class="{ active: isActive('/admin/audit') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/audit/entity-logs" class="nav-link" :class="{ active: isActive('/admin/audit/entity-logs') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">{{ t('entity_logs') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/audit/statistics" class="nav-link" :class="{ active: isActive('/admin/audit/statistics') }">
                                <el-icon><Odometer /></el-icon>
                                <span v-if="!collapsed">{{ t('audit_statistics') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li>
                    <router-link to="/admin/pos" class="nav-link" :class="{ active: isActive('/admin/pos') }">
                        <el-icon><Monitor /></el-icon>
                        <span v-if="!collapsed">{{ t('pos') }}</span>
                    </router-link>
                </li>

                <li>
                    <router-link to="/admin/inquiries" class="nav-link" :class="{ active: isActive('/admin/inquiries') }">
                        <el-icon><ChatLineRound /></el-icon>
                        <span v-if="!collapsed">{{ t('inquiries') }}</span>
                    </router-link>
                </li>

                <li>
                    <router-link to="/admin/visitors" class="nav-link" :class="{ active: isActive('/admin/visitors') }">
                        <el-icon><View /></el-icon>
                        <span v-if="!collapsed">{{ t('visitors') }}</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('system') }">
                    <div class="nav-group-header" @click="toggleGroup('system')">
                        <el-icon><Setting /></el-icon>
                        <span v-if="!collapsed">{{ t('system') }}</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('system') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('system')">
                        <li>
                            <router-link to="/admin/roles" class="nav-link" :class="{ active: isActive('/admin/roles') }">
                                <el-icon><Lock /></el-icon>
                                <span v-if="!collapsed">{{ t('roles') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/permissions" class="nav-link" :class="{ active: isActive('/admin/permissions') }">
                                <el-icon><Key /></el-icon>
                                <span v-if="!collapsed">{{ t('permissions') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <el-button
                v-if="collapsed"
                :icon="Expand"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <router-link v-if="!collapsed" to="/admin/settings" class="footer-link" :class="{ active: isActive('/admin/settings') }">
                <el-icon><Setting /></el-icon>
                <span>{{ t('settings') }}</span>
            </router-link>
        </div>
    </aside>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import {
    Box, Fold, Expand, Close, Odometer, Folder, ShoppingCart,
    TrendCharts, Document, ArrowDown, Setting, ShoppingBag,
    Coin, Wallet, UserFilled, User, ChatDotRound, Tools,
    DataAnalysis, Monitor, ChatLineRound, View, Lock, Key,
    Clock, Calendar, Ticket, Refresh, Discount, Bell, Location,
    List, Checked, Cpu
} from '@element-plus/icons-vue';

const { t } = useI18n();
const settingsStore = useSettingsStore();
const siteName = computed(() => settingsStore.data?.site_name || 'أوان التقدم');

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false
    },
    mobileOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:collapsed', 'update:mobileOpen']);

const closeMobile = () => {
    emit('update:mobileOpen', false);
};

const route = useRoute();
const openGroups = ref(['content']);

const isActive = (path) => {
    return route.path === path || route.path.startsWith(path + '/');
};

const isGroupOpen = (group) => {
    return openGroups.value.includes(group);
};

const toggleGroup = (group) => {
    const index = openGroups.value.indexOf(group);
    if (index > -1) {
        openGroups.value.splice(index, 1);
    } else {
        openGroups.value = [group];
    }
};

const toggleSidebar = () => {
    emit('update:collapsed', !props.collapsed);
    localStorage.setItem('sidebarCollapsed', !props.collapsed);
};

watch(() => props.collapsed, (newVal) => {
    if (newVal) {
        openGroups.value = [];
    }
});
</script>

<style scoped>
.admin-sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a2332 0%, #2d3e50 100%);
    color: white;
    position: fixed;
    right: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

[dir="ltr"] .admin-sidebar {
    right: auto;
    left: 0;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
}

[dir="rtl"] .admin-sidebar {
    right: 0;
    left: auto;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.admin-sidebar.collapsed {
    width: 70px;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-weight: 600;
    font-size: 1.125rem;
}

.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: all 0.3s ease;
    border-right: 3px solid transparent;
}

[dir="ltr"] .nav-link {
    border-right: none;
    border-left: 3px solid transparent;
}

[dir="rtl"] .nav-link {
    border-right: 3px solid transparent;
    border-left: none;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

[dir="ltr"] .nav-link:hover {
    transform: translateX(4px);
}

[dir="rtl"] .nav-link:hover {
    transform: translateX(-4px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

[dir="ltr"] .nav-link.active {
    border-right-color: transparent;
    border-left-color: #d4af37;
}

[dir="rtl"] .nav-link.active {
    border-right-color: #d4af37;
    border-left-color: transparent;
}

.nav-group {
    margin-bottom: 0.25rem;
}

.nav-group-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    color: rgba(255, 255, 255, 0.85);
    border-right: 3px solid transparent;
}

[dir="ltr"] .nav-group-header {
    border-right: none;
    border-left: 3px solid transparent;
}

[dir="rtl"] .nav-group-header {
    border-right: 3px solid transparent;
    border-left: none;
}

.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

[dir="ltr"] .nav-group-header:hover {
    transform: translateX(4px);
}

[dir="rtl"] .nav-group-header:hover {
    transform: translateX(-4px);
}

.toggle-icon {
    margin-left: auto;
    transition: transform 0.3s ease;
    font-size: 0.75rem;
}

[dir="ltr"] .toggle-icon {
    margin-left: auto;
    margin-right: 0;
}

[dir="rtl"] .toggle-icon {
    margin-left: 0;
    margin-right: auto;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

.nav-group-items {
    padding-right: 1.5rem;
    list-style: none;
    padding: 0;
    margin: 0.25rem 0;
}

[dir="ltr"] .nav-group-items {
    padding-right: 0;
    padding-left: 1.5rem;
}

[dir="rtl"] .nav-group-items {
    padding-right: 1.5rem;
    padding-left: 0;
}

.nav-group-items li {
    margin-bottom: 0.25rem;
}

.nav-group-items .nav-link {
    padding: 0.625rem 1.5rem;
    font-size: 0.875rem;
}

.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: center;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.footer-link:hover {
    color: white;
}

.footer-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

[dir="ltr"] .footer-link.active {
    border-right-color: transparent;
    border-left-color: #d4af37;
}

[dir="rtl"] .footer-link.active {
    border-right-color: #d4af37;
    border-left-color: transparent;
}

.admin-sidebar .mobile-close-btn {
    display: none;
}

@media (max-width: 992px) {
    .admin-sidebar {
        transform: translateX(100%);
        transition: transform 0.3s ease;
        box-shadow: none;
    }

    [dir="ltr"] .admin-sidebar {
        transform: translateX(-100%);
    }

    [dir="rtl"] .admin-sidebar {
        transform: translateX(100%);
    }

    .admin-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
    }

    [dir="ltr"] .admin-sidebar.mobile-open {
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
    }

    [dir="rtl"] .admin-sidebar.mobile-open {
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
    }

    .admin-sidebar .mobile-close-btn {
        display: inline-flex;
    }

    .admin-sidebar.collapsed {
        width: 260px;
    }
}
</style>
