<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.direction', 'rtl') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('messages.admin_dashboard')) - {{ get_setting('site_name') ?? 'أوان التقدم' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
    <script src="{{ asset('assets/js/admin.js') }}"></script>
    <script src="{{ asset('assets/js/admin-dynamic.js') }}"></script>

    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('home') }}" class="logo">
                    <i class="fas fa-building"></i>
                    <span>{{ get_setting('site_name') ?? 'أوان التقدم' }}</span>
                </a>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>{{ __('messages.admin_dashboard') }}</span>
                        </a>
                    </li>
                    
                    <!-- إدارة المحتوى -->
                    <li class="nav-group">
                        <div class="nav-group-header">
                            <i class="fas fa-cubes"></i>
                            <span>{{ __('messages.content_management') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.categories.index') }}">
                                    <i class="fas fa-folder-open"></i>
                                    <span>{{ __('messages.categories') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.products.index') }}">
                                    <i class="fas fa-boxes"></i>
                                    <span>{{ __('messages.products') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المبيعات -->
                    <li class="nav-group {{ request()->routeIs('admin.sales.*', 'admin.quotes.*', 'admin.sales-orders.*', 'admin.payments.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-shopping-cart"></i>
                            <span>{{ __('messages.nav_sales') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.sales.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.sales_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.sales.invoices', 'admin.invoices.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales.invoices') }}">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>{{ __('messages.invoices') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.quotes.index') }}">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>{{ __('messages.quotes') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.sales-orders.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales-orders.index') }}">
                                    <i class="fas fa-shopping-bag"></i>
                                    <span>{{ __('messages.sales_orders') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.payments.index') }}">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>{{ __('messages.payments') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.sales.customers') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales.customers') }}">
                                    <i class="fas fa-users"></i>
                                    <span>{{ __('messages.customers') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المشتريات -->
                    <li class="nav-group {{ request()->routeIs('admin.purchases.*', 'admin.purchase-receipts.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-truck-loading"></i>
                            <span>{{ __('messages.nav_purchases') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.purchases.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.purchases_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchases.suppliers') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.suppliers') }}">
                                    <i class="fas fa-truck"></i>
                                    <span>{{ __('messages.suppliers') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchases.orders') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.orders') }}">
                                    <i class="fas fa-file-alt"></i>
                                    <span>{{ __('messages.purchase_orders') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchase-receipts.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchase-receipts.index') }}">
                                    <i class="fas fa-receipt"></i>
                                    <span>{{ __('messages.receipts') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المخزون -->
                    <li class="nav-group {{ request()->routeIs('admin.inventory.*', 'admin.stock-alerts') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-warehouse"></i>
                            <span>{{ __('messages.nav_inventory') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.inventory.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.inventory.index') }}">
                                    <i class="fas fa-boxes"></i>
                                    <span>{{ __('messages.general_inventory') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.inventory.movements') ? 'active' : '' }}">
                                <a href="{{ route('admin.inventory.movements') }}">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>{{ __('messages.stock_movements') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.stock-alerts') ? 'active' : '' }}">
                                <a href="{{ route('admin.stock-alerts') }}">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>{{ __('messages.stock_alerts') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الموارد البشرية -->
                    <li class="nav-group {{ request()->routeIs('admin.hr.*', 'admin.payrolls.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-users"></i>
                            <span>{{ __('messages.nav_hr') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.hr.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.hr_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.employees') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.employees') }}">
                                    <i class="fas fa-user-tie"></i>
                                    <span>{{ __('messages.employees') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.attendance') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.attendance') }}">
                                    <i class="fas fa-clock"></i>
                                    <span>{{ __('messages.attendance') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.leaves') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.leaves') }}">
                                    <i class="fas fa-calendar-minus"></i>
                                    <span>{{ __('messages.leave_requests') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.payrolls.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.payrolls.index') }}">
                                    <i class="fas fa-money-check-alt"></i>
                                    <span>{{ __('messages.payrolls') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المحاسبة -->
                    <li class="nav-group {{ request()->routeIs('admin.accounting.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-calculator"></i>
                            <span>{{ __('messages.nav_accounting') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.accounting.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.accounting_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.journal') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.journal') }}">
                                    <i class="fas fa-book"></i>
                                    <span>{{ __('messages.journal') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.ledger') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.ledger') }}">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span>{{ __('messages.ledger') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.trial-balance') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.trial-balance') }}">
                                    <i class="fas fa-balance-scale"></i>
                                    <span>{{ __('messages.trial_balance') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الإنتاج -->
                    <li class="nav-group {{ request()->routeIs('admin.production.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-industry"></i>
                            <span>{{ __('messages.nav_production') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.production.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.production.index') }}">
                                    <i class="fas fa-list"></i>
                                    <span>{{ __('messages.production_orders') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- إدارة العملاء -->
                    <li class="nav-group {{ request()->routeIs('admin.crm.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-user-friends"></i>
                            <span>{{ __('messages.nav_crm') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.crm.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.crm_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.crm.customers') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.customers') }}">
                                    <i class="fas fa-users"></i>
                                    <span>{{ __('messages.customers') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.crm.tickets') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.tickets') }}">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span>{{ __('messages.tickets') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- التقارير -->
                    <li class="nav-group {{ request()->routeIs('admin.reports.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-chart-pie"></i>
                            <span>{{ __('messages.nav_reports') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.reports.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.index') }}">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>{{ __('messages.reports_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.sales') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.sales') }}">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>{{ __('messages.sales_report') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.inventory') }}">
                                    <i class="fas fa-warehouse"></i>
                                    <span>{{ __('messages.inventory_report') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.financial') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.financial') }}">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>{{ __('messages.financial_report') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.payroll') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.payroll') }}">
                                    <i class="fas fa-money-check-alt"></i>
                                    <span>{{ __('messages.payroll_report') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الأدوار والصلاحيات -->
                    <li class="nav-group {{ request()->routeIs('admin.roles.*', 'admin.permissions.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-shield-alt"></i>
                            <span>{{ __('messages.roles_permissions') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.roles.index') }}">
                                    <i class="fas fa-user-shield"></i>
                                    <span>{{ __('messages.roles') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.permissions.index') }}">
                                    <i class="fas fa-key"></i>
                                    <span>{{ __('messages.permissions') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- إدارة المستودعات (WMS) -->
                    <li class="nav-group {{ request()->routeIs('admin.wms.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-warehouse"></i>
                            <span>{{ __('messages.nav_wms') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.wms.index') ? 'active' : '' }}">
                                <a href="/admin/wms">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>{{ __('messages.wms_dashboard') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.warehouses.*') ? 'active' : '' }}">
                                <a href="/admin/wms/warehouses">
                                    <i class="fas fa-building"></i>
                                    <span>{{ __('messages.warehouses') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.bins.*') ? 'active' : '' }}">
                                <a href="/admin/wms/bins">
                                    <i class="fas fa-boxes"></i>
                                    <span>{{ __('messages.bins') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.picking.*') ? 'active' : '' }}">
                                <a href="/admin/wms/picking">
                                    <i class="fas fa-hand-holding-box"></i>
                                    <span>{{ __('messages.picking_lists') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.packing.*') ? 'active' : '' }}">
                                <a href="/admin/wms/packing">
                                    <i class="fas fa-box-open"></i>
                                    <span>{{ __('messages.packing_lists') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.cycle-counts.*') ? 'active' : '' }}">
                                <a href="/admin/wms/cycle-counts">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>{{ __('messages.cycle_counts') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.wms.performance') ? 'active' : '' }}">
                                <a href="/admin/wms/performance">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.wms_performance') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- التحليلات المتقدمة -->
                    <li class="nav-group {{ request()->routeIs('admin.analytics.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-chart-bar"></i>
                            <span>{{ __('messages.nav_analytics') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.analytics.index') ? 'active' : '' }}">
                                <a href="/admin/analytics">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>{{ __('messages.analytics_dashboard') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.sales') ? 'active' : '' }}">
                                <a href="/admin/analytics/sales">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>{{ __('messages.sales_analytics') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.inventory') ? 'active' : '' }}">
                                <a href="/admin/analytics/inventory">
                                    <i class="fas fa-warehouse"></i>
                                    <span>{{ __('messages.inventory_analytics') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.warehouse') ? 'active' : '' }}">
                                <a href="/admin/analytics/warehouse">
                                    <i class="fas fa-boxes"></i>
                                    <span>{{ __('messages.warehouse_analytics') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.financial') ? 'active' : '' }}">
                                <a href="/admin/analytics/financial">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>{{ __('messages.financial_analytics') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.metrics') ? 'active' : '' }}">
                                <a href="/admin/analytics/metrics">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>{{ __('messages.custom_metrics') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.reports') ? 'active' : '' }}">
                                <a href="/admin/analytics/reports">
                                    <i class="fas fa-file-alt"></i>
                                    <span>{{ __('messages.reports') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.analytics.dashboards') ? 'active' : '' }}">
                                <a href="/admin/analytics/dashboards">
                                    <i class="fas fa-th-large"></i>
                                    <span>{{ __('messages.dashboards') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- الإشعارات -->
                    <li class="nav-group {{ request()->routeIs('admin.notifications.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-bell"></i>
                            <span>{{ __('messages.nav_notifications') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.notifications.index') ? 'active' : '' }}">
                                <a href="/admin/notifications">
                                    <i class="fas fa-envelope"></i>
                                    <span>{{ __('messages.notifications') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.notifications.templates.*') ? 'active' : '' }}">
                                <a href="/admin/notifications/templates">
                                    <i class="fas fa-file-alt"></i>
                                    <span>{{ __('messages.templates') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.notifications.preferences') ? 'active' : '' }}">
                                <a href="/admin/notifications/preferences">
                                    <i class="fas fa-cog"></i>
                                    <span>{{ __('messages.preferences') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- سير العمل -->
                    <li class="nav-group {{ request()->routeIs('admin.workflows.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-project-diagram"></i>
                            <span>{{ __('messages.nav_workflows') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.workflows.index') ? 'active' : '' }}">
                                <a href="/admin/workflows">
                                    <i class="fas fa-list"></i>
                                    <span>{{ __('messages.workflows') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- سجل التدقيق -->
                    <li class="nav-group {{ request()->routeIs('admin.audit.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-history"></i>
                            <span>{{ __('messages.nav_audit') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.audit.index') ? 'active' : '' }}">
                                <a href="/admin/audit">
                                    <i class="fas fa-list"></i>
                                    <span>{{ __('messages.audit_logs') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.audit.entity-logs') ? 'active' : '' }}">
                                <a href="/admin/audit/entity-logs">
                                    <i class="fas fa-file-alt"></i>
                                    <span>{{ __('messages.entity_logs') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.audit.statistics') ? 'active' : '' }}">
                                <a href="/admin/audit/statistics">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>{{ __('messages.audit_statistics') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- إرجاع البضائع (RMA) -->
                    <li class="nav-group {{ request()->routeIs('admin.rma.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-undo"></i>
                            <span>{{ __('messages.nav_rma') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.rma.index') ? 'active' : '' }}">
                                <a href="/admin/rma">
                                    <i class="fas fa-list"></i>
                                    <span>{{ __('messages.returns') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- أدوات أخرى -->
                    <li class="nav-group {{ request()->routeIs('admin.pos', 'admin.inquiries.*', 'admin.visitors.*') ? 'open active' : '' }}">
                        <div class="nav-group-header">
                            <i class="fas fa-tools"></i>
                            <span>{{ __('messages.other_tools') }}</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.pos') ? 'active' : '' }}">
                                <a href="{{ route('admin.pos') }}">
                                    <i class="fas fa-cash-register"></i>
                                    <span>{{ __('messages.pos') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.inquiries.index') }}">
                                    <i class="fas fa-envelope"></i>
                                    <span>{{ __('messages.inquiries') }}</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.visitors.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.visitors.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>{{ __('messages.visitor_stats') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <li class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.index') }}">
                            <i class="fas fa-cog"></i>
                            <span>{{ __('messages.nav_settings') }}</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="{{ url('/') }}" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>{{ __('messages.nav_view_site') }}</span>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                    @csrf
                    <button type="submit">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>{{ __('messages.nav_logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Header -->
            <header class="admin-header">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="header-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="{{ __('messages.quick_search') }}">
                </div>

                <div class="header-actions">
                    <!-- Language Switcher -->
                    <div class="language-switcher" style="display: flex !important; gap: 8px !important; align-items: center !important; visibility: visible !important; opacity: 1 !important;">
                        <a href="{{ route('lang.switch', 'en') }}" class="action-btn {{ app()->getLocale() === 'en' ? 'active-lang' : '' }}" style="padding: 8px 12px !important; border-radius: 6px !important; text-decoration: none !important; color: #333 !important; font-size: 16px !important; transition: all 0.3s ease !important; {{ app()->getLocale() === 'en' ? 'background-color: #e0e7ff !important; border: 2px solid #005a9c !important;' : 'background-color: #f3f4f6 !important; border: 1px solid #d1d5db !important;' }} display: flex !important; align-items: center !important; justify-content: center !important; min-width: 40px !important; min-height: 40px !important;" title="English">
                            🇬🇧
                        </a>
                        <a href="{{ route('lang.switch', 'ar') }}" class="action-btn {{ app()->getLocale() === 'ar' ? 'active-lang' : '' }}" style="padding: 8px 12px !important; border-radius: 6px !important; text-decoration: none !important; color: #333 !important; font-size: 16px !important; transition: all 0.3s ease !important; {{ app()->getLocale() === 'ar' ? 'background-color: #e0e7ff !important; border: 2px solid #005a9c !important;' : 'background-color: #f3f4f6 !important; border: 1px solid #d1d5db !important;' }} display: flex !important; align-items: center !important; justify-content: center !important; min-width: 40px !important; min-height: 40px !important;" title="العربية">
                            🇸🇦
                        </a>
                    </div>

                    <a href="{{ route('admin.inquiries.index') }}" class="action-btn" title="{{ __('messages.new_inquiries') }}">
                        <i class="fas fa-bell"></i>
                        @php
                            $unreadInquiries = \App\Models\Inquiry::where('status', 'new')->count();
                        @endphp
                        @if($unreadInquiries > 0)
                            <span class="badge badge-danger">{{ $unreadInquiries }}</span>
                        @endif
                    </a>
                    <div class="user-dropdown">
                        <button class="user-btn" type="button">
                            <i class="fas fa-user-circle"></i>
                            <span>{{ Auth::check() ? Auth::user()->name : 'المستخدم' }}</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" style="display: none;">
                            <a href="{{ route('admin.profile.edit') }}">
                                <i class="fas fa-user"></i> {{ __('messages.profile') }}
                            </a>
                            <a href="{{ route('admin.settings.index') }}">
                                <i class="fas fa-cog"></i> {{ __('messages.settings') }}
                            </a>
                            <div class="divider"></div>
                            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                                @csrf
                                <button type="submit">
                                    <i class="fas fa-sign-out-alt"></i> {{ __('messages.nav_logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="admin-content">
                @if(session('success'))
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        {{ session('success') }}
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ session('error') }}
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @yield('content')
            </div>

            <!-- Footer -->
            <footer class="admin-footer">
                <p>{{ __('messages.all_rights_reserved') }} © {{ date('Y') }} أوان التقدم</p>
            </footer>
        </main>
    </div>

    <script src="{{ asset('assets/js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
