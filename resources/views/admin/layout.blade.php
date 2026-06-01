<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'لوحة التحكم') - أوان التقدم</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">

    <style>
        /* User Dropdown Styles */
        .user-dropdown {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 180px;
            padding: 0.5rem 0;
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.2s;
        }

        .dropdown-menu a:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .dropdown-menu form button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-menu form button:hover {
            background: #fff5f5;
        }

        /* Navigation Group Styles */
        .nav-group {
            margin-bottom: 0.25rem;
        }

        .nav-group-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
            color: #4a5568;
        }

        .nav-group-header:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .nav-group-header i:first-child {
            width: 20px;
            text-align: center;
        }

        .nav-group-header span {
            flex: 1;
            font-weight: 500;
        }

        .toggle-icon {
            font-size: 0.75rem;
            transition: transform 0.3s ease;
        }

        .nav-group.open .toggle-icon {
            transform: rotate(180deg);
        }

        .nav-group-items {
            display: none;
            padding-right: 1.5rem;
            margin-top: 0.25rem;
        }

        .nav-group.open .nav-group-items {
            display: block;
        }

        .nav-group-items li {
            margin-bottom: 0.25rem;
        }

        .nav-group-items li a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: #718096;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-group-items li a:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .nav-group-items li a i {
            width: 20px;
            text-align: center;
            font-size: 0.85rem;
        }

        .nav-group.active .nav-group-header {
            background: var(--accent-blue);
            color: white;
        }

        .nav-group.active .nav-group-items li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('home') }}" class="logo">
                    <i class="fas fa-building"></i>
                    <span>أوان التقدم</span>
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
                            <span>لوحة التحكم</span>
                        </a>
                    </li>
                    
                    <!-- إدارة المحتوى -->
                    <li class="nav-group">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-cubes"></i>
                            <span>إدارة المحتوى</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.categories.index') }}">
                                    <i class="fas fa-folder-open"></i>
                                    <span>الفئات</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.products.index') }}">
                                    <i class="fas fa-boxes"></i>
                                    <span>المنتجات</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المبيعات -->
                    <li class="nav-group {{ request()->routeIs('admin.sales.*', 'admin.quotes.*', 'admin.sales-orders.*', 'admin.payments.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-shopping-cart"></i>
                            <span>المبيعات</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.sales.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.quotes.index') }}">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>عروض الأسعار</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.sales-orders.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales-orders.index') }}">
                                    <i class="fas fa-shopping-bag"></i>
                                    <span>طلبات البيع</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.payments.index') }}">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>المدفوعات</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.sales.customers') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales.customers') }}">
                                    <i class="fas fa-users"></i>
                                    <span>العملاء</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المشتريات -->
                    <li class="nav-group {{ request()->routeIs('admin.purchases.*', 'admin.purchase-receipts.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-truck-loading"></i>
                            <span>المشتريات</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.purchases.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchases.suppliers') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.suppliers') }}">
                                    <i class="fas fa-truck"></i>
                                    <span>الموردين</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchases.orders') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchases.orders') }}">
                                    <i class="fas fa-file-alt"></i>
                                    <span>أوامر الشراء</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.purchase-receipts.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.purchase-receipts.index') }}">
                                    <i class="fas fa-receipt"></i>
                                    <span>إيصالات الاستلام</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المخزون -->
                    <li class="nav-group {{ request()->routeIs('admin.inventory.*', 'admin.stock-alerts') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-warehouse"></i>
                            <span>المخزون</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.inventory.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.inventory.index') }}">
                                    <i class="fas fa-boxes"></i>
                                    <span>المخزون العام</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.inventory.movements') ? 'active' : '' }}">
                                <a href="{{ route('admin.inventory.movements') }}">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>حركات المخزون</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.stock-alerts') ? 'active' : '' }}">
                                <a href="{{ route('admin.stock-alerts') }}">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>تنبيهات المخزون</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الموارد البشرية -->
                    <li class="nav-group {{ request()->routeIs('admin.hr.*', 'admin.payrolls.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-users"></i>
                            <span>الموارد البشرية</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.hr.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.employees') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.employees') }}">
                                    <i class="fas fa-user-tie"></i>
                                    <span>الموظفين</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.attendance') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.attendance') }}">
                                    <i class="fas fa-clock"></i>
                                    <span>الحضور والانصراف</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.hr.leaves') ? 'active' : '' }}">
                                <a href="{{ route('admin.hr.leaves') }}">
                                    <i class="fas fa-calendar-minus"></i>
                                    <span>طلبات الإجازات</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.payrolls.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.payrolls.index') }}">
                                    <i class="fas fa-money-check-alt"></i>
                                    <span>الرواتب</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- المحاسبة -->
                    <li class="nav-group {{ request()->routeIs('admin.accounting.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-calculator"></i>
                            <span>المحاسبة</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.accounting.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.journal') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.journal') }}">
                                    <i class="fas fa-book"></i>
                                    <span>دفتر اليومية</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.ledger') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.ledger') }}">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span>دفتر الأستاذ</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.accounting.trial-balance') ? 'active' : '' }}">
                                <a href="{{ route('admin.accounting.trial-balance') }}">
                                    <i class="fas fa-balance-scale"></i>
                                    <span>ميزان المراجعة</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الإنتاج -->
                    <li class="nav-group {{ request()->routeIs('admin.production.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-industry"></i>
                            <span>الإنتاج</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.production.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.production.index') }}">
                                    <i class="fas fa-list"></i>
                                    <span>أوامر الإنتاج</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- إدارة العملاء -->
                    <li class="nav-group {{ request()->routeIs('admin.crm.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-user-friends"></i>
                            <span>إدارة العملاء</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.crm.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.crm.customers') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.customers') }}">
                                    <i class="fas fa-users"></i>
                                    <span>العملاء</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.crm.tickets') ? 'active' : '' }}">
                                <a href="{{ route('admin.crm.tickets') }}">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span>التذاكر</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- التقارير -->
                    <li class="nav-group {{ request()->routeIs('admin.reports.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-chart-pie"></i>
                            <span>التقارير</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.reports.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.index') }}">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>نظرة عامة</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.sales') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.sales') }}">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>تقرير المبيعات</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.inventory') }}">
                                    <i class="fas fa-warehouse"></i>
                                    <span>تقرير المخزون</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.financial') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.financial') }}">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>التقرير المالي</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.reports.payroll') ? 'active' : '' }}">
                                <a href="{{ route('admin.reports.payroll') }}">
                                    <i class="fas fa-money-check-alt"></i>
                                    <span>تقرير الرواتب</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- الأدوار والصلاحيات -->
                    <li class="nav-group {{ request()->routeIs('admin.roles.*', 'admin.permissions.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-shield-alt"></i>
                            <span>الأدوار والصلاحيات</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.roles.index') }}">
                                    <i class="fas fa-user-shield"></i>
                                    <span>الأدوار</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.permissions.index') }}">
                                    <i class="fas fa-key"></i>
                                    <span>الصلاحيات</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- أدوات أخرى -->
                    <li class="nav-group {{ request()->routeIs('admin.pos', 'admin.inquiries.*', 'admin.visitors.*') ? 'open active' : '' }}">
                        <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
                            <i class="fas fa-tools"></i>
                            <span>أدوات أخرى</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <ul class="nav-group-items">
                            <li class="{{ request()->routeIs('admin.pos') ? 'active' : '' }}">
                                <a href="{{ route('admin.pos') }}">
                                    <i class="fas fa-cash-register"></i>
                                    <span>نقطة البيع</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.inquiries.index') }}">
                                    <i class="fas fa-envelope"></i>
                                    <span>الاستفسارات</span>
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('admin.visitors.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.visitors.index') }}">
                                    <i class="fas fa-chart-line"></i>
                                    <span>إحصائيات الزوار</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <li class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings.index') }}">
                            <i class="fas fa-cog"></i>
                            <span>الإعدادات</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="{{ url('/') }}" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>عرض الموقع</span>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                    @csrf
                    <button type="submit">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>تسجيل الخروج</span>
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
                    <input type="text" placeholder="بحث سريع...">
                </div>

                <div class="header-actions">
                    <a href="{{ route('admin.inquiries.index') }}" class="action-btn" title="الاستفسارات الجديدة">
                        <i class="fas fa-bell"></i>
                        @php
                            $unreadInquiries = \App\Models\Inquiry::where('status', 'new')->count();
                        @endphp
                        @if($unreadInquiries > 0)
                            <span class="badge">{{ $unreadInquiries }}</span>
                        @endif
                    </a>
                    <div class="user-dropdown">
                        <button class="user-btn" onclick="document.querySelector('.dropdown-menu').classList.toggle('show')">
                            <i class="fas fa-user-circle"></i>
                            <span>{{ Auth::check() ? Auth::user()->name : 'المستخدم' }}</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="{{ route('admin.profile.edit') }}">
                                <i class="fas fa-user"></i> الملف الشخصي
                            </a>
                            <a href="{{ route('admin.settings.index') }}">
                                <i class="fas fa-cog"></i> الإعدادات
                            </a>
                            <div style="border-top: 1px solid #e2e8f0; margin: 0.5rem 0;"></div>
                            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                                @csrf
                                <button type="submit" style="background: none; border: none; width: 100%; text-align: right; padding: 0.5rem 1rem; cursor: pointer; color: #e53e3e;">
                                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
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
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </div>

            <!-- Footer -->
            <footer class="admin-footer">
                <p>جميع الحقوق محفوظة © {{ date('Y') }} أوان التقدم</p>
            </footer>
        </main>
    </div>

    <script src="{{ asset('assets/js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
