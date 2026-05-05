<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? (get_setting('site_name') ?? 'أوان التقدم') . ' - ' . (get_setting('site_tagline') ?? 'نبني معاً غد سورية الأجمل'); ?></title>
    <meta name="description" content="<?php echo $page_description ?? (get_setting('meta_description') ?? 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.'); ?>">
    <meta name="keywords" content="<?php echo get_setting('meta_keywords') ?? 'مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية, أدوات, مشابك, علاقات معدنية, أنظمة تثبيت ورفع'; ?>">

    <meta property="og:title" content="<?php echo $page_title ?? 'أوان التقدم - نبني معاً غد سورية الأجمل'; ?>">
    <meta property="og:description" content="<?php echo $page_description ?? (get_setting('og_description') ?? 'مستلزمات البناء بأعلى جودة وتصاميم عصرية لمشاريعكم في سورية'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo get_setting('site_name') ?? 'أوان التقدم'; ?>">
    <meta property="og:image" content="<?php echo $page_image ?? asset('assets/images/hero-bg.jpg'); ?>">
    <meta property="og:url" content="<?php echo url()->current(); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}?v={{ filemtime(public_path('assets/css/rtl.css')) }}">

    @stack('styles')

    <link rel="icon" type="image/png" href="{{ asset('favicon.ico') }}">
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="{{ url('/') }}" class="logo">

                                <img src="<?php echo get_setting('site_logo') ? asset(get_setting('site_logo')) : asset('assets/images/logo.png'); ?>" alt="<?php echo get_setting('site_name') ?? 'أوان التقدم'; ?>" class="logo-img">
            </a>
                <?php if(get_setting('show_site_name', false)): ?>
                    <span class="logo-text"><?php echo get_setting('site_name') ?? 'أوان التقدم'; ?></span>
                <?php endif; ?>s
            <div class="nav-search">
                <div class="search-container">
                    <input type="text" class="search-input" id="searchInput" placeholder="ابحث عن منتج...">
                    <i class="fas fa-search search-icon"></i>
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>

            <button class="dark-mode-toggle" id="darkModeToggle" title="الوضع الداكن">
                <i class="fas fa-moon"></i>
            </button>

            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>

            <ul class="nav-menu" id="navMenu">
                <li><a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">الرئيسية</a></li>
                <li><a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">الفئات</a></li>
                <li><a href="{{ route('featured.products') }}" class="nav-link {{ request()->routeIs('featured.products') ? 'active' : '' }}">المنتجات المميزة</a></li>
                <li><a href="{{ route('vision') }}" class="nav-link {{ request()->routeIs('vision') ? 'active' : '' }}">الهوية والرؤية</a></li>
                <li><a href="{{ route('about') }}" class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}">من نحن</a></li>
                <li><a href="{{ route('contact') }}" class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">إتصل بنا</a></li>
            </ul>
        </div>
    </nav>



    @yield('content')

    <div class="float-buttons">
        <a href="https://wa.me/<?php echo get_setting('contact_whatsapp') ?? '963900000000'; ?>" class="float-button whatsapp" data-tooltip="تواصل معنا واتساب" target="_blank">
            <i class="fab fa-whatsapp"></i>
        </a>
        <a href="<?php echo get_setting('contact_facebook') ?? '#'; ?>" class="float-button facebook" data-tooltip="تابعنا على فيسبوك" target="_blank">
            <i class="fab fa-facebook"></i>
        </a>
        <a href="{{ route('inquiry.create') }}" class="float-button inquiry" data-tooltip="إرسال استفسار">
            <i class="fas fa-comment-dots"></i>
        </a>
    </div>

    <footer class="footer-multicolumn" id="site-contact">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>عن <?php echo get_setting('site_name') ?? 'أوان التقدم'; ?></h3>
                    <p><?php echo get_setting('site_description') ?? 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.'; ?></p>
                    <div class="footer-social">
                        <a href="<?php echo get_setting('contact_facebook') ?? '#'; ?>" class="social-link facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://wa.me/<?php echo get_setting('contact_whatsapp') ?? '963900000000'; ?>" class="social-link whatsapp" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        <a href="<?php echo get_setting('contact_instagram') ?? '#'; ?>" class="social-link instagram" target="_blank"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>روابط سريعة</h3>
                    <ul>
                        <li><a href="{{ route('home') }}">الرئيسية</a></li>
                        <li><a href="{{ route('about') }}">من نحن</a></li>
                        <li><a href="{{ route('vision') }}">الهوية والرؤية</a></li>
                        <li><a href="{{ route('contact') }}">اتصل بنا</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>خدماتنا</h3>
                    <ul>
                        <li><a href="{{ route('contact') }}">استشارة فنية</a></li>
                        <li><a href="{{ route('contact') }}">طلب عرض سعر</a></li>
                        <li><a href="{{ route('contact') }}">التوصيل والشحن</a></li>
                        <li><a href="{{ route('contact') }}">الدعم الفني</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>اتصل بنا</h3>
                    <p><i class="fas fa-map-marker-alt"></i> سورية - دمشق</p>
                    <p><i class="fas fa-phone"></i> <span dir="ltr"><?php echo get_setting('contact_phone') ?? '+963 900 000 000'; ?></span></p>
                    <p><i class="fas fa-envelope"></i> <?php echo get_setting('contact_email') ?? 'info@awan-altakaddom.com'; ?></p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo get_setting('site_name') ?? 'أوان التقدم'; ?> - جميع الحقوق محفوظة</p>
            </div>
        </div>
    </footer>

    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" defer></script>
    <script src="{{ asset('assets/js/search-fixed.js') }}?v={{ filemtime(public_path('assets/js/search-fixed.js')) }}" defer></script>

    @stack('scripts')
</body>
</html>
