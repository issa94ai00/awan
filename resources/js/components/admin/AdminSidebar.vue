<template>
    <aside class="admin-sidebar" :class="{ collapsed: collapsed, 'mobile-open': mobileOpen }">
        <div class="sidebar-header">
            <div class="brand">
                <div class="brand-mark">
                    <span class="brand-mark-glow"></span>
                    <el-icon :size="17"><Box /></el-icon>
                </div>
                <div v-if="!collapsed" class="brand-text">
                    <span class="brand-name">{{ siteName }}</span>
                    <span class="brand-tag">
                        <span class="brand-dot"></span>
                        ERP Suite
                    </span>
                </div>
            </div>
            <el-button
                v-if="!mobileOpen"
                class="collapse-btn"
                :icon="collapsed ? Expand : Fold"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <el-button
                v-if="mobileOpen"
                class="collapse-btn mobile-close-btn"
                :icon="Close"
                circle
                size="small"
                @click="closeMobile"
            />
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-section-label">{{ t('nav_label_main') }}</li>
                <li class="nav-item">
                    <router-link to="/admin/dashboard" class="nav-link" :class="{ active: isActive('/admin/dashboard') }">
                        <el-icon class="nav-ic"><Odometer /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('dashboard') }}</span>
                    </router-link>
                </li>

                <li class="nav-section-label">{{ t('nav_label_content') }}</li>
                <li class="nav-group" :class="{ open: isGroupOpen('content') }">
                    <div class="nav-group-header" @click="toggleGroup('content')">
                        <el-icon class="nav-ic"><Box /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('content_management') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('content') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('content')">
                        <li>
                            <router-link to="/admin/categories" class="nav-link">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('categories') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products" class="nav-link">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('products') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products/units" class="nav-link" :class="{ active: isActive('/admin/products/units') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('product_units') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/special-offers" class="nav-link" :class="{ active: isActive('/admin/special-offers') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('special_offers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/secondary-navbar" class="nav-link" :class="{ active: isActive('/admin/secondary-navbar') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('secondary_navbar') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-section-label">{{ t('nav_label_commerce') }}</li>
                <li v-if="canAccessGroup('sales')" class="nav-group" :class="{ open: isGroupOpen('sales') }">
                    <div class="nav-group-header" @click="toggleGroup('sales')">
                        <el-icon class="nav-ic"><ShoppingCart /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('sales') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('sales') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('sales')">
                        <li>
                            <router-link to="/admin/sales" class="nav-link" :class="{ active: isActive('/admin/sales') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/invoices" class="nav-link" :class="{ active: isActive('/admin/sales/invoices') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('invoices') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/invoices/create" class="nav-link" :class="{ active: isActive('/admin/sales/invoices/create') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('create_invoice') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/customers" class="nav-link" :class="{ active: isActive('/admin/sales/customers') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('customers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/quotes" class="nav-link" :class="{ active: isActive('/admin/sales/quotes') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('quotes') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/sales-orders" class="nav-link" :class="{ active: isActive('/admin/sales/sales-orders') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('sales_orders') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/payments" class="nav-link" :class="{ active: isActive('/admin/sales/payments') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('payments') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('rma') }">
                    <div class="nav-group-header" @click="toggleGroup('rma')">
                        <el-icon class="nav-ic"><Refresh /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('rma') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('rma') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('rma')">
                        <li>
                            <router-link to="/admin/rma" class="nav-link" :class="{ active: isActive('/admin/rma') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/rma/create" class="nav-link" :class="{ active: isActive('/admin/rma/create') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('create') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li v-if="canAccessGroup('purchases')" class="nav-group" :class="{ open: isGroupOpen('purchases') }">
                    <div class="nav-group-header" @click="toggleGroup('purchases')">
                        <el-icon class="nav-ic"><ShoppingBag /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('purchases') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('purchases') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('purchases')">
                        <li>
                            <router-link to="/admin/purchases" class="nav-link" :class="{ active: isActive('/admin/purchases') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/suppliers" class="nav-link" :class="{ active: isActive('/admin/purchases/suppliers') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('suppliers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/orders" class="nav-link" :class="{ active: isActive('/admin/purchases/orders') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('purchase_orders') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/receipts" class="nav-link" :class="{ active: isActive('/admin/purchases/receipts') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('receipts') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li v-if="canAccessGroup('accounting')" class="nav-group" :class="{ open: isGroupOpen('accounting') }">
                    <div class="nav-group-header" @click="toggleGroup('accounting')">
                        <el-icon class="nav-ic"><Coin /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('accounting') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('accounting') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('accounting')">
                        <li>
                            <router-link to="/admin/accounting" class="nav-link" :class="{ active: isActive('/admin/accounting') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/journal" class="nav-link" :class="{ active: isActive('/admin/accounting/journal') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('journal') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/ledger" class="nav-link" :class="{ active: isActive('/admin/accounting/ledger') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('ledger') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/trial-balance" class="nav-link" :class="{ active: isActive('/admin/accounting/trial-balance') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('trial_balance') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/income-statement" class="nav-link" :class="{ active: isActive('/admin/accounting/income-statement') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('income_statement') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/balance-sheet" class="nav-link" :class="{ active: isActive('/admin/accounting/balance-sheet') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('balance_sheet') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-section-label">{{ t('nav_label_inventory') }}</li>
                <li v-if="canAccessGroup('inventory')" class="nav-group" :class="{ open: isGroupOpen('inventory') }">
                    <div class="nav-group-header" @click="toggleGroup('inventory')">
                        <el-icon class="nav-ic"><Box /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('inventory') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('inventory') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('inventory')">
                        <li>
                            <router-link to="/admin/inventory" class="nav-link" :class="{ active: isActive('/admin/inventory') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/stock" class="nav-link" :class="{ active: isActive('/admin/stock') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">إدارة المخزون</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/inventory/movements" class="nav-link" :class="{ active: isActive('/admin/inventory/movements') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('stock_movements') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li v-if="canAccessGroup('wms')" class="nav-group" :class="{ open: isGroupOpen('wms') }">
                    <div class="nav-group-header" @click="toggleGroup('wms')">
                        <el-icon class="nav-ic"><Location /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('wms') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('wms') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('wms')">
                        <li>
                            <router-link to="/admin/wms" class="nav-link" :class="{ active: isActive('/admin/wms') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/warehouses" class="nav-link" :class="{ active: isActive('/admin/wms/warehouses') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('warehouses') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/bins" class="nav-link" :class="{ active: isActive('/admin/wms/bins') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('bins') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/picking" class="nav-link" :class="{ active: isActive('/admin/wms/picking') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('picking_lists') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/packing" class="nav-link" :class="{ active: isActive('/admin/wms/packing') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('packing_lists') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/cycle-counts" class="nav-link" :class="{ active: isActive('/admin/wms/cycle-counts') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('cycle_counts') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/wms/performance" class="nav-link" :class="{ active: isActive('/admin/wms/performance') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('wms_performance') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-section-label">{{ t('nav_label_hr') }}</li>
                <li class="nav-group" :class="{ open: isGroupOpen('hr') }">
                    <div class="nav-group-header" @click="toggleGroup('hr')">
                        <el-icon class="nav-ic"><UserFilled /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('hr') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('hr') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('hr')">
                        <li>
                            <router-link to="/admin/hr" class="nav-link" :class="{ active: isActive('/admin/hr') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/employees" class="nav-link" :class="{ active: isActive('/admin/hr/employees') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('employees') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/employee-customers" class="nav-link" :class="{ active: isActive('/admin/hr/employee-customers') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">علاقة الموظف بالعملاء</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/attendance" class="nav-link" :class="{ active: isActive('/admin/hr/attendance') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('attendance') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/leaves" class="nav-link" :class="{ active: isActive('/admin/hr/leaves') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('leaves') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/payrolls" class="nav-link" :class="{ active: isActive('/admin/hr/payrolls') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('payrolls') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li v-if="canAccessGroup('crm')" class="nav-group" :class="{ open: isGroupOpen('crm') }">
                    <div class="nav-group-header" @click="toggleGroup('crm')">
                        <el-icon class="nav-ic"><ChatDotRound /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('crm') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('crm') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('crm')">
                        <li>
                            <router-link to="/admin/crm" class="nav-link" :class="{ active: isActive('/admin/crm') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/customers" class="nav-link" :class="{ active: isActive('/admin/crm/customers') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('customers') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/tickets" class="nav-link" :class="{ active: isActive('/admin/crm/tickets') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('tickets') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('production') }">
                    <div class="nav-group-header" @click="toggleGroup('production')">
                        <el-icon class="nav-ic"><Tools /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('production') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('production') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('production')">
                        <li>
                            <router-link to="/admin/production" class="nav-link" :class="{ active: isActive('/admin/production') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-section-label">{{ t('nav_label_reports') }}</li>
                <li v-if="canAccessGroup('reports')" class="nav-group" :class="{ open: isGroupOpen('reports') }">
                    <div class="nav-group-header" @click="toggleGroup('reports')">
                        <el-icon class="nav-ic"><DataAnalysis /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('reports') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('reports') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('reports')">
                        <li>
                            <router-link to="/admin/reports" class="nav-link" :class="{ active: isActive('/admin/reports') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/sales" class="nav-link" :class="{ active: isActive('/admin/reports/sales') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('sales_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/professional-sales" class="nav-link" :class="{ active: isActive('/admin/reports/professional-sales') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">تقارير المبيعات الاحترافية</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/inventory" class="nav-link" :class="{ active: isActive('/admin/reports/inventory') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('inventory_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/financial" class="nav-link" :class="{ active: isActive('/admin/reports/financial') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('financial_report') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/payroll" class="nav-link" :class="{ active: isActive('/admin/reports/payroll') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('payroll_report') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li v-if="canAccessGroup('bi_analytics')" class="nav-group" :class="{ open: isGroupOpen('bi_analytics') }">
                    <div class="nav-group-header" @click="toggleGroup('bi_analytics')">
                        <el-icon class="nav-ic"><DataAnalysis /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('bi_analytics') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('bi_analytics') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('bi_analytics')">
                        <li>
                            <router-link to="/admin/analytics" class="nav-link" :class="{ active: isActive('/admin/analytics') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/sales" class="nav-link" :class="{ active: isActive('/admin/analytics/sales') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_sales') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/inventory" class="nav-link" :class="{ active: isActive('/admin/analytics/inventory') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_inventory') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/warehouse" class="nav-link" :class="{ active: isActive('/admin/analytics/warehouse') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_warehouse') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/financial" class="nav-link" :class="{ active: isActive('/admin/analytics/financial') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_financial') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/metrics" class="nav-link" :class="{ active: isActive('/admin/analytics/metrics') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_metrics') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/reports" class="nav-link" :class="{ active: isActive('/admin/analytics/reports') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_reports') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/analytics/dashboards" class="nav-link" :class="{ active: isActive('/admin/analytics/dashboards') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('analytics_dashboards') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('workflows') }">
                    <div class="nav-group-header" @click="toggleGroup('workflows')">
                        <el-icon class="nav-ic"><Cpu /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('workflows') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('workflows') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('workflows')">
                        <li>
                            <router-link to="/admin/workflows" class="nav-link" :class="{ active: isActive('/admin/workflows') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('workflow_list') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/workflows/create" class="nav-link" :class="{ active: isActive('/admin/workflows/create') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('create') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-section-label">{{ t('nav_label_system') }}</li>
                <li class="nav-group" :class="{ open: isGroupOpen('notifications_management') }">
                    <div class="nav-group-header" @click="toggleGroup('notifications_management')">
                        <el-icon class="nav-ic"><Bell /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('notifications_management') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('notifications_management') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('notifications_management')">
                        <li>
                            <router-link to="/admin/notifications" class="nav-link" :class="{ active: isActive('/admin/notifications') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('notification_logs') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/notifications/templates" class="nav-link" :class="{ active: isActive('/admin/notifications/templates') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('notification_templates') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/notifications/preferences" class="nav-link" :class="{ active: isActive('/admin/notifications/preferences') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('notification_preferences') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('audit_logs') }">
                    <div class="nav-group-header" @click="toggleGroup('audit_logs')">
                        <el-icon class="nav-ic"><View /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('audit_logs') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('audit_logs') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('audit_logs')">
                        <li>
                            <router-link to="/admin/audit" class="nav-link" :class="{ active: isActive('/admin/audit') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('overview') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/audit/entity-logs" class="nav-link" :class="{ active: isActive('/admin/audit/entity-logs') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('entity_logs') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/audit" class="nav-link" :class="{ active: isActive('/admin/audit') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">مراقبة المخاطر</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/audit/statistics" class="nav-link" :class="{ active: isActive('/admin/audit/statistics') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('audit_statistics') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <router-link to="/admin/pos" class="nav-link" :class="{ active: isActive('/admin/pos') }">
                        <el-icon class="nav-ic"><Monitor /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('pos') }}</span>
                    </router-link>
                </li>

                <li class="nav-item">
                    <router-link to="/admin/inquiries" class="nav-link" :class="{ active: isActive('/admin/inquiries') }">
                        <el-icon class="nav-ic"><ChatLineRound /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('inquiries') }}</span>
                    </router-link>
                </li>

                <li class="nav-item">
                    <router-link to="/admin/visitors" class="nav-link" :class="{ active: isActive('/admin/visitors') }">
                        <el-icon class="nav-ic"><View /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('visitors') }}</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('system') }">
                    <div class="nav-group-header" @click="toggleGroup('system')">
                        <el-icon class="nav-ic"><Setting /></el-icon>
                        <span v-if="!collapsed" class="nav-text">{{ t('system') }}</span>
                        <el-icon v-if="!collapsed" class="toggle-icon" :class="{ rotated: isGroupOpen('system') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('system')">
                        <li>
                            <router-link to="/admin/roles" class="nav-link" :class="{ active: isActive('/admin/roles') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('roles') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/permissions" class="nav-link" :class="{ active: isActive('/admin/permissions') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('permissions') }}</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/currencies" class="nav-link" :class="{ active: isActive('/admin/currencies') }">
                                <span class="sub-dot"></span>
                                <span v-if="!collapsed" class="nav-text">{{ t('currencies') }}</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer" :class="{ collapsed: collapsed }">
            <div class="user-card" :title="userName" @click="collapsed && toggleSidebar()">
                <div class="user-avatar">{{ userInitials }}</div>
                <div v-if="!collapsed" class="user-meta">
                    <span class="user-name">{{ userName }}</span>
                    <span class="user-role">{{ userEmail }}</span>
                </div>
                <router-link
                    v-if="!collapsed"
                    to="/admin/settings"
                    class="user-action"
                    :class="{ active: isActive('/admin/settings') }"
                    @click.stop
                >
                    <el-icon :size="15"><Setting /></el-icon>
                </router-link>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useAuthStore } from '@/stores/auth';
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
const authStore = useAuthStore();
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

const groupRoutePrefixes = {
    content: ['/admin/categories', '/admin/products', '/admin/special-offers', '/admin/secondary-navbar'],
    sales: ['/admin/sales'],
    rma: ['/admin/rma'],
    purchases: ['/admin/purchases'],
    accounting: ['/admin/accounting'],
    inventory: ['/admin/inventory'],
    wms: ['/admin/wms'],
    hr: ['/admin/hr'],
    crm: ['/admin/crm'],
    production: ['/admin/production'],
    reports: ['/admin/reports'],
    bi_analytics: ['/admin/analytics'],
    workflows: ['/admin/workflows'],
    notifications_management: ['/admin/notifications'],
    audit_logs: ['/admin/audit'],
    system: ['/admin/roles', '/admin/permissions']
};

const groupForPath = (path) => {
    for (const [group, prefixes] of Object.entries(groupRoutePrefixes)) {
        if (prefixes.some((prefix) => path.startsWith(prefix))) {
            return group;
        }
    }
    return null;
};

const openGroups = ref([groupForPath(route.path) || 'content']);

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

watch(() => route.path, (path) => {
    const group = groupForPath(path);
    if (group && !openGroups.value.includes(group)) {
        openGroups.value = [group];
    }
});

const toggleSidebar = () => {
    emit('update:collapsed', !props.collapsed);
    localStorage.setItem('sidebarCollapsed', !props.collapsed);
};

watch(() => props.collapsed, (newVal) => {
    if (newVal) {
        openGroups.value = [];
    }
});

const userRole = computed(() => (authStore.user?.role?.name || authStore.user?.role_name || '').toLowerCase());
const canAccessGroup = (group) => {
    if (!userRole.value || userRole.value === 'admin') {
        return true;
    }

    const allowedGroups = {
        content: ['admin', 'sells', 'marketer'],
        sales: ['admin', 'sells'],
        rma: ['admin', 'sells'],
        purchases: ['admin'],
        accounting: ['admin', 'accountant'],
        inventory: ['admin', 'sells'],
        wms: ['admin'],
        hr: ['admin'],
        crm: ['admin', 'marketer'],
        production: ['admin'],
        reports: ['admin', 'sells', 'accountant', 'marketer'],
        bi_analytics: ['admin', 'accountant'],
        workflows: ['admin'],
        notifications_management: ['admin'],
        audit_logs: ['admin'],
        system: ['admin'],
    };

    return (allowedGroups[group] || ['admin']).includes(userRole.value);
};

const userName = computed(() => authStore.user?.name || 'مسؤول النظام');
const userEmail = computed(() => authStore.user?.email || '');
const userInitials = computed(() => {
    const name = (authStore.user?.name || 'مسؤول النظام').trim();
    const parts = name.split(/\s+/).filter(Boolean);
    const first = (parts[0] || '')[0] || '';
    const second = parts[1]?.[0] || (parts[0] || '')[1] || '';
    return (first + second).toUpperCase() || 'م';
});
</script>

<style scoped>
.admin-sidebar {
    --sb-accent: #2dd4bf;
    --sb-accent-2: #67e8f9;
    --sb-accent-soft: rgba(45, 212, 191, 0.14);
    --sb-accent-border: rgba(45, 212, 191, 0.32);
    --sb-glow: rgba(45, 212, 191, 0.35);
    --sb-text: rgba(203, 213, 225, 0.68);
    --sb-text-strong: rgba(241, 245, 249, 0.94);

    width: 268px;
    background:
        radial-gradient(120% 45% at 50% -8%, rgba(45, 212, 191, 0.10), transparent 60%),
        radial-gradient(90% 40% at 0% 110%, rgba(129, 140, 248, 0.07), transparent 60%),
        linear-gradient(180deg, #0a0f1e 0%, #0d1526 50%, #0f1a2e 100%);
    color: var(--sb-text);
    position: fixed;
    inset-inline-start: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.04), 0 0 40px rgba(0, 0, 0, 0.35);
}

[dir="ltr"] .admin-sidebar {
    box-shadow: inset 1px 0 0 rgba(255, 255, 255, 0.04), 0 0 40px rgba(0, 0, 0, 0.35);
}

.admin-sidebar.collapsed {
    width: 72px;
}

.sidebar-header {
    padding: 1.1rem 1.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 68px;
    gap: 0.5rem;
}

.admin-sidebar.collapsed .sidebar-header {
    justify-content: center;
    padding: 1.1rem 0.75rem;
}

.sidebar-header :deep(.collapse-btn) {
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(226, 232, 240, 0.72);
    transition: all 0.2s ease;
}

.sidebar-header :deep(.collapse-btn:hover) {
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-border);
    color: var(--sb-accent);
    box-shadow: 0 0 16px -4px var(--sb-glow);
}

.brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: white;
    min-width: 0;
}

.brand-mark {
    position: relative;
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
    color: #ecfeff;
    box-shadow: 0 6px 18px -6px var(--sb-glow), inset 0 1px 0 rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.brand-mark-glow {
    position: absolute;
    inset: 0;
    border-radius: 12px;
    background: radial-gradient(90% 90% at 30% 20%, rgba(255, 255, 255, 0.35), transparent 60%);
    pointer-events: none;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    line-height: 1.1;
}

.brand-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--sb-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.brand-tag {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.16em;
    color: rgba(148, 163, 184, 0.7);
    text-transform: uppercase;
}

.brand-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--sb-accent);
    box-shadow: 0 0 8px var(--sb-glow);
}

.sidebar-nav {
    flex: 1;
    padding: 0.35rem 0.65rem 0.75rem;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.14) transparent;
}

.sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.14);
    border-radius: 999px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.22);
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-section-label {
    padding: 1.15rem 0.85rem 0.4rem;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.5);
    white-space: nowrap;
    overflow: hidden;
}

.nav-section-label:first-child {
    padding-top: 0.4rem;
}

.nav-item,
.nav-group {
    margin-bottom: 0.125rem;
}

.nav-link,
.nav-group-header {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.62rem 0.8rem;
    color: var(--sb-text);
    text-decoration: none;
    cursor: pointer;
    border-radius: 10px;
    font-size: 0.9rem;
    border: 1px solid transparent;
    transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.nav-link:hover,
.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.055);
    color: var(--sb-text-strong);
}

.nav-link.active {
    color: var(--sb-accent);
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-border);
    font-weight: 600;
    box-shadow: 0 8px 20px -12px var(--sb-glow);
}

.nav-link.active::before {
    content: '';
    position: absolute;
    inset-block: 9px;
    inset-inline-start: 0;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--sb-accent), var(--sb-accent-2));
    box-shadow: 0 0 10px var(--sb-glow);
}

.nav-ic {
    flex-shrink: 0;
    font-size: 1.05rem;
    opacity: 0.85;
    transition: transform 0.2s ease, opacity 0.2s ease, color 0.2s ease;
}

.nav-link:hover .nav-ic,
.nav-group-header:hover .nav-ic {
    opacity: 1;
}

.nav-link.active .nav-ic {
    color: var(--sb-accent);
    opacity: 1;
}

.nav-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-group.open > .nav-group-header {
    color: var(--sb-text-strong);
    background: rgba(255, 255, 255, 0.04);
}

.nav-group.open > .nav-group-header .nav-ic {
    color: var(--sb-accent);
    opacity: 1;
}

.toggle-icon {
    margin-inline-start: auto;
    transition: transform 0.25s ease;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.5);
}

.toggle-icon.rotated {
    transform: rotate(180deg);
    color: var(--sb-accent);
}

.nav-group-items {
    list-style: none;
    padding: 0;
    margin: 0.15rem 0 0.4rem;
    position: relative;
}

.nav-group-items .nav-link {
    padding: 0.5rem 0.8rem 0.5rem 1.55rem;
    font-size: 0.83rem;
    color: rgba(148, 163, 184, 0.62);
    border: none;
    background: transparent;
    box-shadow: none;
}

.nav-group-items .nav-link:hover {
    color: var(--sb-text-strong);
    background: rgba(255, 255, 255, 0.045);
}

.nav-group-items .nav-link.active {
    color: var(--sb-accent);
    background: transparent;
    font-weight: 600;
}

.nav-group-items .nav-link.active::before {
    display: none;
}

.sub-dot {
    flex-shrink: 0;
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.35);
    transition: background 0.2s ease, box-shadow 0.2s ease;
}

.nav-group-items .nav-link:hover .sub-dot {
    background: var(--sb-accent);
}

.nav-group-items .nav-link.active .sub-dot {
    background: var(--sb-accent);
    box-shadow: 0 0 8px var(--sb-glow);
}

.sidebar-footer {
    padding: 0.85rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.045));
}

.user-card {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.55rem 0.65rem;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.03);
}

.user-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
}

.sidebar-footer.collapsed .user-card {
    justify-content: center;
    padding: 0.5rem;
    border: none;
    background: transparent;
}

.user-avatar {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: #062a24;
    background: linear-gradient(135deg, var(--sb-accent) 0%, var(--sb-accent-2) 100%);
    box-shadow: 0 4px 12px -4px var(--sb-glow), inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.user-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.user-name {
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--sb-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.65);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-action {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(148, 163, 184, 0.7);
    text-decoration: none;
    transition: all 0.2s ease;
}

.user-action:hover {
    color: var(--sb-accent);
    background: var(--sb-accent-soft);
}

.user-action.active {
    color: var(--sb-accent);
}

.admin-sidebar.collapsed .nav-link,
.admin-sidebar.collapsed .nav-group-header {
    justify-content: center;
    padding-inline: 0;
}

.admin-sidebar.collapsed .nav-link.active::before {
    inset-inline-start: 0;
}

.admin-sidebar.collapsed .nav-section-label {
    display: none;
}

.admin-sidebar .mobile-close-btn {
    display: none;
}

@media (max-width: 992px) {
    .admin-sidebar {
        inset-inline-start: auto;
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
        width: 268px;
    }

    .admin-sidebar.collapsed .nav-section-label {
        display: block;
    }

    .admin-sidebar.collapsed .nav-link,
    .admin-sidebar.collapsed .nav-group-header {
        justify-content: flex-start;
        padding-inline: 0.8rem;
    }

    .admin-sidebar.collapsed .nav-text,
    .admin-sidebar.collapsed .toggle-icon {
        display: inline-flex;
    }

    .admin-sidebar.collapsed .sidebar-header {
        justify-content: space-between;
        padding: 1.1rem 1.15rem;
    }

    .admin-sidebar.collapsed .sidebar-footer {
        padding: 0.85rem;
    }

    .admin-sidebar.collapsed .user-card {
        justify-content: flex-start;
        padding: 0.55rem 0.65rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.03);
    }

    .admin-sidebar.collapsed .user-meta,
    .admin-sidebar.collapsed .user-action {
        display: inline-flex;
    }
}
</style>
