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
