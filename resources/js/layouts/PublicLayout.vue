<template>
    <div class="public-app-wrapper">
        <!-- Dynamic CSS Variables -->
        <component is="style">
            :root {
                --navbar-transparency: {{ settings.theme_navbar_transparency || 25 }};
                --navbar-bg-color: {{ settings.theme_navbar_bg_color || 'var(--mobile-primary-dark)' }};
                --navbar-text-color: {{ settings.theme_navbar_text_color || '#ffffff' }};
                --navbar-scrolled-bg-color: {{ settings.theme_navbar_scrolled_bg_color || 'var(--mobile-primary-dark)' }};
                --navbar-scrolled-text-color: {{ settings.theme_navbar_scrolled_text_color || '#ffffff' }};
            }
        </component>
        <!-- Dynamic Design Customizations -->
        <component is="style" id="dynamic-theme-customizations">
            :root {
                --font-family: '{{ settings.theme_font_family || 'Cairo' }}', sans-serif !important;
                --border-radius: {{ settings.theme_border_radius || '14px' }} !important;
            }
            
            body, html, .public-app-wrapper, .logo-text, .nav-link, .drawer-link, .product-title, .section-title, h1, h2, h3, h4, h5, h6 {
                font-family: var(--font-family) !important;
            }
            
            .product-card, 
            .category-card, 
            .btn-add-to-cart, 
            .btn-whatsapp, 
            .btn-phone, 
            .btn-inquire, 
            .nav-action-btn, 
            .btn-submit, 
            .btn-checkout, 
            .btn-add-to-cart-product,
            .search-input,
            .drawer-search-input,
            .form-control,
            .input-group,
            .result-item,
            .badge,
            .modal-content {
                border-radius: var(--border-radius) !important;
            }
            
            .hero {
                text-align: {{ settings.theme_hero_align || 'center' }} !important;
            }
            
            .hero-content {
                text-align: {{ settings.theme_hero_align || 'center' }} !important;
                align-items: {{ settings.theme_hero_align === 'center' ? 'center' : (settings.theme_hero_align === 'left' ? 'flex-start' : 'flex-end') }} !important;
            }
            
            .hero {
                background: linear-gradient(135deg, rgba(255, 255, 255, {{ settings.theme_hero_overlay_opacity || '0.85' }}), rgba(96, 96, 96, {{ settings.theme_hero_overlay_opacity || '0.9' }})), var(--hero-bg, url('/assets/images/hero-bg.jpg')) center/cover no-repeat !important;
            }
            
            [data-theme="dark"] .hero {
                background: linear-gradient(135deg, rgba(13, 27, 42, {{ settings.theme_hero_overlay_opacity || '0.9' }}), rgba(22, 42, 69, {{ settings.theme_hero_overlay_opacity || '0.95' }})), var(--hero-bg, url('/assets/images/hero-bg.jpg')) center/cover no-repeat !important;
            }
            
            /* Dynamic Navbar colors */
            .nav-top, .nav-bottom, .mobile-drawer {
                background: {{ settings.theme_navbar_bg_color || 'color-mix(in srgb, var(--mobile-primary-dark, #1e3a1a) 60%, transparent)' }} !important;
            }
            .navbar.scrolled .nav-top {
                background: transparent !important;
                backdrop-filter: blur(10px) !important;
            }
            .navbar.scrolled .nav-bottom .nav-container {
                background: transparent !important;
                backdrop-filter: blur(10px) !important;
            }
            .nav-link, 
            .logo-text, 
            .scrolled-logo-text, 
            .nav-action-btn, 
            .cart-count,
            .drawer-logo-text,
            .drawer-link,
            .drawer-link i,
            .search-icon,
            .drawer-search-icon,
            .profile-trigger,
            .profile-trigger i {
                color: {{ settings.theme_navbar_text_color || '#ffffff' }} !important;
            }
            .navbar.scrolled .nav-link,
            .navbar.scrolled .logo-text,
            .navbar.scrolled .scrolled-logo-text,
            .navbar.scrolled .nav-action-btn,
            .navbar.scrolled .cart-count,
            .navbar.scrolled .search-icon,
            .navbar.scrolled .profile-trigger,
            .navbar.scrolled .profile-trigger i {
                color: {{ settings.theme_navbar_scrolled_text_color || settings.theme_navbar_text_color || '#ffffff' }} !important;
            }
            .nav-link:hover,
            .nav-link.active,
            .drawer-link:hover,
            .drawer-link.active,
            .drawer-link:hover i,
            .drawer-link.active i {
                color: {{ settings.theme_navbar_text_color || '#ffffff' }} !important;
                opacity: 1 !important;
            }
            .navbar.scrolled .nav-link:hover,
            .navbar.scrolled .nav-link.active {
                color: {{ settings.theme_navbar_scrolled_text_color || settings.theme_navbar_text_color || '#ffffff' }} !important;
                opacity: 1 !important;
            }
            .menu-toggle span {
                background-color: {{ settings.theme_navbar_text_color || '#ffffff' }} !important;
            }
            .navbar.scrolled .menu-toggle span {
                background-color: {{ settings.theme_navbar_scrolled_text_color || settings.theme_navbar_text_color || '#ffffff' }} !important;
            }

            /* Dynamic Footer colors */
            footer, 
            .footer-multicolumn, 
            .footer-simple, 
            .footer-modern {
                background: {{ settings.theme_footer_bg_color || 'linear-gradient(135deg, #1E3A0F, #2D5016)' }} !important;
                color: {{ settings.theme_footer_text_color || '#f8f9fa' }} !important;
            }
            .footer-multicolumn a, 
            .footer-multicolumn p, 
            .footer-multicolumn span, 
            .footer-multicolumn i, 
            .footer-multicolumn h3, 
            .footer-multicolumn h4,
            .footer-simple-content h3,
            .footer-simple-content p,
            .footer-simple-content a,
            .footer-simple-content i,
            .footer-simple-content span,
            .footer-modern-grid h3,
            .footer-modern-grid h4,
            .footer-modern-grid p,
            .footer-modern-grid a,
            .footer-modern-grid i,
            .footer-modern-grid span,
            .footer-bottom,
            .footer-bottom p,
            .footer-bottom a,
            .footer-bottom span {
                color: {{ settings.theme_footer_text_color || '#f8f9fa' }} !important;
            }

            /* Dark Mode Footer Overrides */
            [data-theme="dark"] footer, 
            [data-theme="dark"] .footer-grid,
            [data-theme="dark"] .footer-simple-content,
            [data-theme="dark"] .footer-modern-grid,
            [data-theme="dark"] .footer-multicolumn, 
            [data-theme="dark"] .footer-simple, 
            [data-theme="dark"] .footer-modern {
                background: #080f1a !important;
                color: rgba(255, 255, 255, 0.85) !important;
                border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .footer-bottom {
                background: #04080e !important;
                border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
            }
            [data-theme="dark"] footer a, 
            [data-theme="dark"] footer p, 
            [data-theme="dark"] footer span, 
            [data-theme="dark"] footer i, 
            [data-theme="dark"] footer h3, 
            [data-theme="dark"] footer h4,
            [data-theme="dark"] .footer-bottom p,
            [data-theme="dark"] .footer-bottom a,
            [data-theme="dark"] .footer-bottom span {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            [data-theme="dark"] footer h3, 
            [data-theme="dark"] footer h4 {
                color: #ffffff !important;
            }
            [data-theme="dark"] footer a:hover {
                color: var(--mobile-primary-light, var(--mobile-primary)) !important;
            }
            [data-theme="dark"] .footer-social .social-link {
                background: rgba(255, 255, 255, 0.05) !important;
                color: #ffffff !important;
                border: 1px solid rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .footer-social .social-link:hover {
                background: var(--mobile-primary) !important;
                color: #ffffff !important;
                border-color: var(--mobile-primary) !important;
            }
            
            /* Dynamic Hero Buttons */
            .btn-hero-primary,
              .hero .btn-primary,
              .btn-offer-primary {
                  background: {{ settings.theme_hero_btn_bg_color || 'linear-gradient(135deg, var(--mobile-primary), #5a6b7a)' }} !important;
                  color: {{ settings.theme_hero_btn_text_color || '#ffffff' }} !important;
                  box-shadow: 0 8px 25px rgba(90, 107, 122, 0.35) !important;
              }
            .btn-hero-primary:hover,
              .hero .btn-primary:hover,
              .btn-offer-primary:hover {
                  color: {{ settings.theme_hero_btn_text_color || '#ffffff' }} !important;
                  background: {{ settings.theme_hero_btn_bg_color ? 'color-mix(in srgb, ' + settings.theme_hero_btn_bg_color + ' 90%, black)' : '' }} !important;
              }

            /* Dynamic Cart Buttons (Add to cart & Cart actions) */
            .btn-add-to-cart, 
            .btn-add-to-cart-product,
            .btn-checkout,
            #scrolledCartIcon,
            .cart-icon-wrapper .nav-action-btn {
                background: {{ settings.theme_cart_btn_bg_color || 'var(--mobile-primary)' }} !important;
                color: {{ settings.theme_cart_btn_text_color || '#ffffff' }} !important;
            }
            .btn-add-to-cart:hover, 
            .btn-add-to-cart-product:hover,
            .btn-checkout:hover {
                background: {{ settings.theme_cart_btn_bg_color ? 'color-mix(in srgb, ' + settings.theme_cart_btn_bg_color + ' 90%, black)' : 'var(--mobile-primary-dark, #1e3a0f)' }} !important;
                color: {{ settings.theme_cart_btn_text_color || '#ffffff' }} !important;
            }

            /* Custom CSS injection */
            {{ settings.theme_custom_css || '' }}
        </component>
        <!-- Preloader -->
        <transition name="fade">
            <div v-if="preloaderActive" id="preloader" class="preloader">
                <div class="preloader-content">
                    <div class="logo-pulse">
                        <img :src="getImageUrl(settings.site_logo || 'assets/images/logo.png')" 
                             :alt="$p(settings, 'site_name') || 'أوان التقدم'" 
                             class="preloader-logo">
                        <div class="spinner-ring"></div>
                    </div>
                    <div class="preloader-text">
                        {{ $p(settings, 'site_name') || 'أوان التقدم' }}
                    </div>
                </div>
            </div>
        </transition>

        <!-- Mobile menu backdrop overlay -->
        <transition name="fade">
            <div v-if="mobileMenuOpen" class="mobile-menu-overlay" @click="mobileMenuOpen = false"></div>
        </transition>

        <!-- Glass Navbar -->
        <nav class="glass-navbar" :class="{ 'scrolled': isScrolled }" id="navbar">
            <div class="glass-container">
                <!-- Logo -->
                <router-link to="/" class="glass-logo">
                    <img :src="getImageUrl(settings.site_logo || 'assets/images/logo.png')" :alt="$p(settings, 'site_name')" class="glass-logo-img">
                    <span v-if="settings.show_site_name === '1' || settings.show_site_name === true" class="glass-logo-text">
                        {{ $p(settings, 'site_name') }}
                    </span>
                </router-link>

                <!-- Navigation Links -->
                <div class="glass-nav-links">
                    <router-link to="/" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <router-link to="/categories" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_categories') || 'الفئات' }}</router-link>
                    <router-link to="/products" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_products') || 'المنتجات' }}</router-link>
                    <router-link to="/vision" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_vision') || 'الهوية والرؤية' }}</router-link>
                    <router-link to="/about" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_about') || 'من نحن' }}</router-link>
                    <router-link to="/contact" class="glass-nav-link" active-class="glass-nav-link-active">{{ t('nav_contact') || 'اتصل بنا' }}</router-link>
                </div>

                <!-- Search & Actions -->
                <div class="glass-actions">
                    <!-- Search -->
                    <div class="glass-search">
                        <input type="text" v-model="searchQuery" @input="handleSearchInput" @keyup.enter="triggerSearch" class="glass-search-input" :placeholder="t('search') || 'ابحث...'">
                        <i class="fas fa-search glass-search-icon" @click="triggerSearch"></i>
                        <div v-if="searchResults.length && searchQuery" class="glass-search-results">
                            <div v-for="item in searchResults" :key="item.id" @click="goToProduct(item.slug)" class="glass-result-item">
                                <img :src="getImageUrl(item.image_main)" :alt="item.name_ar" class="glass-result-img">
                                <div class="glass-result-info">
                                    <div class="glass-result-title">{{ $p(item, 'name') }}</div>
                                    <div class="glass-result-category">{{ $p(item.category, 'name') || t('spare_parts') || 'قطع غيار' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Orders -->
                    <router-link to="/customer-orders" class="glass-action-btn" :title="t('orders') || 'الطلبات'">
                        <i class="fas fa-box-open"></i>
                    </router-link>

                    <!-- Cart -->
                    <router-link to="/cart" class="glass-action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span v-if="cartStore.totalItems > 0" class="glass-badge">{{ cartStore.totalItems }}</span>
                    </router-link>

                    <!-- Language Switcher -->
                    <div class="glass-profile-dropdown" v-click-outside="closeLangDropdown">
                        <button class="glass-action-btn" @click="toggleLangDropdown">
                            <i class="fas fa-globe"></i>
                        </button>
                        <div class="glass-dropdown-menu" :class="{ 'show': langDropdownOpen }">
                            <button class="glass-dropdown-item" :class="{'active': currentLocale === 'ar'}" @click="changeLanguage('ar')">العربية</button>
                            <button class="glass-dropdown-item" :class="{'active': currentLocale === 'en'}" @click="changeLanguage('en')">English</button>
                        </div>
                    </div>

                    <!-- User -->
                    <div class="glass-profile-dropdown" v-click-outside="closeDropdown">
                        <button class="glass-action-btn" @click="toggleDropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <div class="glass-dropdown-menu" :class="{ 'show': dropdownOpen }">
                            <div v-if="!customerAuth.isAuthenticated">
                                <button class="glass-dropdown-item" @click="openModal('login')"><i class="fas fa-sign-in-alt"></i> {{ t('login') || 'تسجيل دخول' }}</button>
                                <button class="glass-dropdown-item" @click="openModal('subscribe')"><i class="fas fa-bell"></i> {{ t('subscribe_with_us') || 'اشترك معنا' }}</button>
                                <button class="glass-dropdown-item" @click="toggleTheme">
                                    <i class="fas" :class="theme === 'dark' ? 'fa-sun' : 'fa-moon'"></i>
                                    {{ theme === 'dark' ? (t('light_mode') || 'الوضع الفاتح') : (t('dark_mode') || 'الوضع الداكن') }}
                                </button>
                            </div>
                            <div v-else>
                                <div class="glass-dropdown-header">{{ t('welcome') || 'مرحباً' }} {{ customerAuth.customer?.name }}</div>
                                <router-link class="glass-dropdown-item" to="/customer-orders" @click="dropdownOpen = false"><i class="fas fa-user"></i> {{ t('profile') || 'ملفي الشخصي' }}</router-link>
                                <button class="glass-dropdown-item" @click="toggleTheme">
                                    <i class="fas" :class="theme === 'dark' ? 'fa-sun' : 'fa-moon'"></i>
                                    {{ theme === 'dark' ? (t('light_mode') || 'الوضع الفاتح') : (t('dark_mode') || 'الوضع الداكن') }}
                                </button>
                                <button class="glass-dropdown-item" @click="handleLogout"><i class="fas fa-sign-out-alt"></i> {{ t('logout') || 'تسجيل خروج' }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Menu Toggle -->
                    <button class="glass-menu-toggle" :class="{ 'open': mobileMenuOpen }" @click="mobileMenuOpen = !mobileMenuOpen">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Mobile Drawer Menu -->
        <aside class="mobile-drawer" :class="{ 'open': mobileMenuOpen }">
            <div class="drawer-header">
                <router-link to="/" class="drawer-logo" @click="mobileMenuOpen = false">
                    <img :src="getImageUrl(settings.site_logo || 'assets/images/logo.png')" :alt="$p(settings, 'site_name')" class="drawer-logo-img">
                    <span class="drawer-logo-text">{{ $p(settings, 'site_name') || 'أوان التقدم' }}</span>
                </router-link>
            </div>
            <div class="drawer-search">
                <div class="drawer-search-container">
                    <input type="text" v-model="searchQuery" @input="handleSearchInput" @keyup.enter="triggerSearchAndClose" class="drawer-search-input" :placeholder="t('search_product') || 'ابحث عن منتج...'">
                    <i class="fas fa-search drawer-search-icon" @click="triggerSearchAndClose"></i>
                </div>
            </div>
            <ul class="drawer-menu">
                <li><router-link to="/" class="drawer-link" active-class="active" exact @click="mobileMenuOpen = false"><i class="fas fa-home"></i> {{ t('nav_home') || 'الرئيسية' }}</router-link></li>
                <li><router-link to="/categories" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-th-large"></i> {{ t('nav_categories') || 'الفئات' }}</router-link></li>
                <li><router-link to="/products" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-box"></i> {{ t('nav_products') || 'المنتجات' }}</router-link></li>
                <li><router-link to="/customer-orders" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-file-invoice"></i> {{ t('orders') || 'الطلبات' }}</router-link></li>
                <li><router-link to="/vision" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-eye"></i> {{ t('nav_vision') || 'الهوية والرؤية' }}</router-link></li>
                <li><router-link to="/about" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-info-circle"></i> {{ t('nav_about') || 'من نحن' }}</router-link></li>
                <li><router-link to="/contact" class="drawer-link" active-class="active" @click="mobileMenuOpen = false"><i class="fas fa-envelope"></i> {{ t('nav_contact') || 'إتصل بنا' }}</router-link></li>
            </ul>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content-spa">
            <router-view v-slot="{ Component }">
                <transition name="page-fade" mode="out-in" @after-enter="onAfterPageEnter">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>

        <!-- Floating Action Buttons -->
        <div class="float-buttons">
            <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="float-button whatsapp" data-tooltip="تواصل معنا واتساب" target="_blank">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a :href="settings.contact_facebook || '#'" class="float-button facebook" data-tooltip="تابعنا على فيسبوك" target="_blank">
                <i class="fab fa-facebook"></i>
            </a>
            <router-link to="/inquiry" class="float-button inquiry" data-tooltip="إرسال استفسار">
                <i class="fas fa-comment-dots"></i>
            </router-link>
        </div>

        <!-- Footer -->
        <footer :class="'footer-' + (settings.theme_footer_layout || 'multicolumn')" id="site-contact">
            <div class="container">
                <!-- 1. Multicolumn Layout (Default) -->
                <div v-if="(settings.theme_footer_layout || 'multicolumn') === 'multicolumn'" class="footer-grid">
                    <div class="footer-column">
                        <h3>{{ t('about') || 'عن' }} {{ $p(settings, 'site_name') || '' }}</h3>
                        <p>{{ $p(settings, 'site_description') || t('about_default_desc') || '' }}</p>
                        <div class="footer-social">
                            <a :href="settings.contact_facebook || '#'" class="social-link facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="social-link whatsapp" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <a :href="settings.contact_instagram || '#'" class="social-link instagram" target="_blank"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                    
                    <div class="footer-column">
                        <h3>{{ t('quick_links') || 'روابط سريعة' }}</h3>
                        <ul>
                            <li><router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link></li>
                            <li><router-link to="/about">{{ t('nav_about') || 'من نحن' }}</router-link></li>
                            <li><router-link to="/vision">{{ t('nav_vision') || 'الهوية والرؤية' }}</router-link></li>
                            <li><router-link to="/contact">{{ t('nav_contact') || 'اتصل بنا' }}</router-link></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h3>{{ t('our_services') || 'خدماتنا' }}</h3>
                        <ul>
                            <li><router-link to="/contact">{{ t('technical_consultation') || 'استشارة فنية' }}</router-link></li>
                            <li><router-link to="/contact">{{ t('price_quote_reason') || 'طلب عرض سعر' }}</router-link></li>
                            <li><router-link to="/contact">{{ t('delivery_shipping') || 'التوصيل والشحن' }}</router-link></li>
                            <li><router-link to="/contact">{{ t('technical_support') || 'الدعم الفني' }}</router-link></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h3>{{ t('nav_contact') || 'اتصل بنا' }}</h3>
                        <p><i class="fas fa-map-marker-alt"></i> {{ $p(settings, 'address') || (currentLocale === 'en' ? 'Syria - Damascus' : 'سورية - دمشق') }}</p>
                        <p><i class="fas fa-phone"></i> <span dir="ltr">{{ settings.contact_phone || '+963 900 000 000' }}</span></p>
                        <p><i class="fas fa-envelope"></i> {{ settings.contact_email || 'info@awan-altakaddom.com' }}</p>
                    </div>
                </div>

                <!-- 2. Simple Layout -->
                <div v-else-if="settings.theme_footer_layout === 'simple'" class="footer-simple-content" style="text-align: center; padding: 2rem 0;">
                    <div class="footer-logo" style="margin-bottom: 1rem;">
                        <img :src="getImageUrl(settings.site_logo || 'assets/images/logo.png')" :alt="$p(settings, 'site_name')" style="height: 50px; object-fit: contain;">
                        <h3 style="margin-top: 0.5rem; color: #fff;">{{ $p(settings, 'site_name') }}</h3>
                    </div>
                    <p style="max-width: 600px; margin: 0 auto 1.5rem; color: rgba(255,255,255,0.7); font-size: 0.95rem;">{{ $p(settings, 'site_description') || t('about_default_desc') }}</p>
                    <div class="footer-social" style="display: flex; justify-content: center; gap: 15px; margin-bottom: 1.5rem;">
                        <a :href="settings.contact_facebook || '#'" class="social-link facebook" target="_blank" style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.08); color: white; transition: all 0.3s;"><i class="fab fa-facebook-f"></i></a>
                        <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="social-link whatsapp" target="_blank" style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.08); color: white; transition: all 0.3s;"><i class="fab fa-whatsapp"></i></a>
                        <a :href="settings.contact_instagram || '#'" class="social-link instagram" target="_blank" style="width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.08); color: white; transition: all 0.3s;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>

                <!-- 3. Modern Layout -->
                <div v-else-if="settings.theme_footer_layout === 'modern'" class="footer-modern-grid" style="display: flex; justify-content: space-between; gap: 40px; padding: 2rem 0; flex-wrap: wrap;">
                    <div class="footer-modern-left" style="display: flex; flex-direction: column; gap: 1rem; text-align: right; flex: 1; min-width: 280px;">
                        <h3 style="color:#fff; font-size: 1.4rem; margin:0;">{{ $p(settings, 'site_name') }}</h3>
                        <p style="color: rgba(255,255,255,0.7); max-width: 600px; margin:0;">{{ $p(settings, 'site_description') || t('about_default_desc') }}</p>
                        <div class="footer-social" style="display: flex; gap: 12px; margin-top: 0.5rem;">
                            <a :href="settings.contact_facebook || '#'" class="social-link facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="social-link whatsapp" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <a :href="settings.contact_instagram || '#'" class="social-link instagram" target="_blank"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                    <div class="footer-modern-right" style="display: flex; flex-direction: column; align-items: flex-start; min-width: 150px;">
                        <h4 style="color:#fff; margin-bottom: 1rem; margin-top: 0;">{{ t('quick_links') || 'روابط سريعة' }}</h4>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem; text-align: right;">
                            <li><router-link to="/" style="color: rgba(255,255,255,0.8); text-decoration:none;">{{ t('nav_home') || 'الرئيسية' }}</router-link></li>
                            <li><router-link to="/about" style="color: rgba(255,255,255,0.8); text-decoration:none;">{{ t('nav_about') || 'من نحن' }}</router-link></li>
                            <li><router-link to="/vision" style="color: rgba(255,255,255,0.8); text-decoration:none;">{{ t('nav_vision') || 'الهوية والرؤية' }}</router-link></li>
                            <li><router-link to="/contact" style="color: rgba(255,255,255,0.8); text-decoration:none;">{{ t('nav_contact') || 'اتصل بنا' }}</router-link></li>
                        </ul>
                    </div>
                </div>
                
                <div class="footer-bottom">
                    <p>&copy; {{ new Date().getFullYear() }} {{ $p(settings, 'site_name') || '' }} - {{ t('all_rights_reserved') || 'جميع الحقوق محفوظة' }}</p>
                </div>
            </div>
        </footer>

        <!-- Modals Overlay wrapper -->
        <!-- Login Modal -->
        <div class="modal-overlay" v-if="activeModal === 'login'" @click="closeModal">
            <div class="modal-content" @click.stop>
                <button class="modal-close" @click="closeModal">&times;</button>
                <h2 class="modal-title-primary">{{ t('login') || 'تسجيل دخول' }}</h2>
                <p class="modal-desc-secondary">{{ t('login_desc') || 'أدخل رقم هاتفك وكلمة المرور لإدارة طلباتك' }}</p>
                
                <form @submit.prevent="submitLogin">
                    <div class="form-group">
                        <label for="loginPhone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color:#dc3545;">*</span></label>
                        <input type="tel" v-model="loginForm.phone" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">{{ t('password') || 'كلمة المرور' }} <span style="color:#dc3545;">*</span></label>
                        <input type="password" v-model="loginForm.password" required autocomplete="current-password">
                    </div>
                    <div v-if="modalError" class="modal-error-box">
                        {{ modalError }}
                    </div>
                    <button type="submit" class="btn-submit" :disabled="modalLoading" style="width:100%;">
                        <span v-if="modalLoading"><i class="fas fa-spinner fa-spin"></i> {{ t('loading') || 'جاري التحميل...' }}</span>
                        <span v-else><i class="fas fa-sign-in-alt"></i> {{ t('login') || 'تسجيل دخول' }}</span>
                    </button>
                </form>
                <hr class="modal-divider">
                <p class="modal-footer-text">
                    {{ t('no_account') || 'ليس لديك حساب؟' }}
                    <a href="#" @click.prevent="openModal('register')" class="modal-link">{{ t('create_account') || 'إنشاء حساب جديد' }}</a>
                </p>
            </div>
        </div>

        <!-- Register Modal -->
        <div class="modal-overlay" v-if="activeModal === 'register'" @click="closeModal">
            <div class="modal-content" @click.stop>
                <button class="modal-close" @click="closeModal">&times;</button>
                <h2 class="modal-title-primary">{{ t('create_account') || 'إنشاء حساب جديد' }}</h2>
                <p class="modal-desc-secondary">{{ t('register_desc') || 'سجل الآن لتتمكن من متابعة طلباتك' }}</p>
                
                <form @submit.prevent="submitRegister">
                    <div class="form-group">
                        <label for="regName">{{ t('name') || 'الاسم' }} <span style="color:#dc3545;">*</span></label>
                        <input type="text" v-model="registerForm.name" required>
                    </div>
                    <div class="form-group">
                        <label for="regPhone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color:#dc3545;">*</span></label>
                        <input type="tel" v-model="registerForm.phone" required>
                    </div>
                    <div class="form-group">
                        <label for="regEmail">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                        <input type="email" v-model="registerForm.email">
                    </div>
                    <div class="form-group">
                        <label for="regPassword">{{ t('password') || 'كلمة المرور' }} <span style="color:#dc3545;">*</span></label>
                        <input type="password" v-model="registerForm.password" required minlength="6" autocomplete="new-password">
                    </div>
                    <div v-if="modalError" class="modal-error-box">
                        {{ modalError }}
                    </div>
                    <button type="submit" class="btn-submit" :disabled="modalLoading" style="width:100%;">
                        <span v-if="modalLoading"><i class="fas fa-spinner fa-spin"></i> {{ t('loading') || 'جاري التحميل...' }}</span>
                        <span v-else><i class="fas fa-user-plus"></i> {{ t('create_account') || 'إنشاء حساب' }}</span>
                    </button>
                </form>
                <hr class="modal-divider">
                <p class="modal-footer-text">
                    {{ t('have_account') || 'لديك حساب بالفعل؟' }}
                    <a href="#" @click.prevent="openModal('login')" class="modal-link">{{ t('login') || 'تسجيل دخول' }}</a>
                </p>
            </div>
        </div>

        <!-- Subscribe Modal -->
        <div class="modal-overlay" v-if="activeModal === 'subscribe'" @click="closeModal">
            <div class="modal-content" @click.stop>
                <button class="modal-close" @click="closeModal">&times;</button>
                <h2 class="modal-title-primary">{{ t('subscribe_with_us') || 'اشترك معنا' }}</h2>
                <p class="modal-desc-secondary">{{ t('subscribe_desc') || 'اشترك ليصلك كل جديد، وأنشئ حساباً لتتمكن من إدارة طلباتك' }}</p>
                
                <form @submit.prevent="submitSubscribe">
                    <div class="form-group">
                        <label for="subName">{{ t('name') || 'الاسم' }} <span style="color:#dc3545;">*</span></label>
                        <input type="text" v-model="subscribeForm.name" required>
                    </div>
                    <div class="form-group">
                        <label for="subPhone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color:#dc3545;">*</span></label>
                        <input type="tel" v-model="subscribeForm.phone" required>
                    </div>
                    <div class="form-group">
                        <label for="subEmail">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                        <input type="email" v-model="subscribeForm.email">
                    </div>
                    <div class="form-group">
                        <label for="subAddress">{{ t('address_label') || 'العنوان' }}</label>
                        <textarea v-model="subscribeForm.address" rows="2" style="resize:none;"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="subPassword">{{ t('password_optional') || 'كلمة المرور (اختياري)' }}</label>
                        <input type="password" v-model="subscribeForm.password" minlength="6" autocomplete="new-password">
                        <small class="form-hint">{{ t('subscribe_password_hint') || 'إذا أدخلت كلمة مرور سيتم إنشاء حساب لك لتتمكن من تسجيل الدخول لاحقاً' }}</small>
                    </div>
                    <div v-if="modalError" class="modal-error-box">
                        {{ modalError }}
                    </div>
                    <button type="submit" class="btn-submit" :disabled="modalLoading" style="width:100%;">
                        <span v-if="modalLoading"><i class="fas fa-spinner fa-spin"></i> {{ t('sending') || 'جاري الإرسال...' }}</span>
                        <span v-else><i class="fas fa-paper-plane"></i> {{ t('subscribe') || 'اشتراك' }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Notification Toast -->
        <div v-if="toast.show" class="cart-notification success show" style="top: 100px;">
            <i class="fas fa-check-circle"></i>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCustomerAuthStore } from '@/stores/customerAuth';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useI18n } from 'vue-i18n';
import { updateDirection } from '@/app';
import axios from 'axios';

// Stores
const settingsStore = useSettingsStore();
const customerAuth = useCustomerAuthStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();
const currentLocale = computed(() => locale.value);

// Router
const router = useRouter();
const route = useRoute();

// Local State
const preloaderActive = ref(true);
const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
const dropdownOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const theme = ref(localStorage.getItem('theme') || 'light');

// Modals State
const activeModal = ref(null);
const modalLoading = ref(false);
const modalError = ref(null);

// Forms
const loginForm = reactive({ phone: '', password: '' });
const registerForm = reactive({ name: '', phone: '', email: '', password: '' });
const subscribeForm = reactive({ name: '', phone: '', email: '', address: '', password: '' });

// Notification Toast
const toast = reactive({ show: false, message: '' });

// Settings getter with system data fallback
const settings = computed(() => {
    const apiSettings = settingsStore.data;
    const systemSettings = window.systemData?.settings || {};
    
    // Merge system data with API data, prioritizing API data
    return {
        ...systemSettings,
        ...apiSettings
    };
});

// Toggle user profile dropdown
const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const closeDropdown = () => {
    dropdownOpen.value = false;
};

// Toggle language dropdown
const langDropdownOpen = ref(false);
const toggleLangDropdown = () => {
    langDropdownOpen.value = !langDropdownOpen.value;
};
const closeLangDropdown = () => {
    langDropdownOpen.value = false;
};

const changeLanguage = (lang) => {
    locale.value = lang;
    localStorage.setItem('locale', lang);
    updateDirection(lang);
    closeLangDropdown();
};

const scrolledDropdownOpen = ref(false);
const toggleScrolledDropdown = () => {
    scrolledDropdownOpen.value = !scrolledDropdownOpen.value;
};

const closeScrolledDropdown = () => {
    scrolledDropdownOpen.value = false;
};

// Theme switcher
const toggleTheme = () => {
    theme.value = theme.value === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme.value);
    localStorage.setItem('theme', theme.value);
};

// Modal handlers
const openModal = (name) => {
    activeModal.value = name;
    modalError.value = null;
};

const closeModal = () => {
    activeModal.value = null;
    modalError.value = null;
};

const handleLoginTrigger = () => {
    openModal('login');
};

// API submission triggers
const submitLogin = async () => {
    modalLoading.value = true;
    modalError.value = null;
    try {
        await customerAuth.login(loginForm);
        closeModal();
        showToast(t('login_success') || 'تم تسجيل الدخول بنجاح');
        // Refresh customer-orders view data if on that page
        if (route.name === 'customer.orders') {
            window.location.reload();
        }
    } catch (err) {
        modalError.value = err.response?.data?.message || t('login_error') || 'رقم الهاتف أو كلمة المرور غير صحيحة';
    } finally {
        modalLoading.value = false;
    }
};

const submitRegister = async () => {
    modalLoading.value = true;
    modalError.value = null;
    try {
        await customerAuth.register(registerForm);
        closeModal();
        showToast(t('register_success') || 'تم إنشاء الحساب بنجاح');
    } catch (err) {
        modalError.value = err.response?.data?.message || t('register_error') || 'حدث خطأ أثناء التسجيل، يرجى المحاولة لاحقاً';
    } finally {
        modalLoading.value = false;
    }
};

const submitSubscribe = async () => {
    modalLoading.value = true;
    modalError.value = null;
    try {
        const res = await axios.post('/api/v1/subscribe', subscribeForm);
        if (res.data?.success) {
            closeModal();
            showToast(t('subscribe_success') || 'شكراً لاشتراكك معنا!');
        }
    } catch (err) {
        modalError.value = err.response?.data?.message || t('subscribe_error') || 'حدث خطأ أثناء الاشتراك';
    } finally {
        modalLoading.value = false;
    }
};

const handleLogout = async () => {
    await customerAuth.logout();
    dropdownOpen.value = false;
    showToast(t('logout_success') || 'تم تسجيل الخروج');
    if (route.name === 'customer.orders') {
        router.push('/');
    }
};

// Quick search suggestions
let searchTimeout = null;
const handleSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }
    
    searchTimeout = setTimeout(async () => {
        try {
            const res = await axios.get(`/api/v1/search/suggestions?q=${encodeURIComponent(searchQuery.value)}`);
            if (res.data?.success) {
                searchResults.value = res.data.data || [];
            }
        } catch (e) {
            console.error('Failed to load search suggestions', e);
        }
    }, 300);
};

const triggerSearch = () => {
    if (searchQuery.value.trim()) {
        router.push({ path: '/categories', query: { q: searchQuery.value } });
        searchResults.value = [];
    }
};

const triggerSearchAndClose = () => {
    triggerSearch();
    mobileMenuOpen.value = false;
};

const goToProduct = (slug) => {
    router.push(`/product/${slug}`);
    searchQuery.value = '';
    searchResults.value = [];
};

// Toast utility
const showToast = (msg) => {
    toast.message = msg;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// Trigger fade-up animations after page transition completes
const onAfterPageEnter = () => {
    triggerFadeUp();
};

// Scroll handler
const handleScroll = () => {
    isScrolled.value = window.scrollY > 50;
};

// Custom click outside directive
const vClickOutside = {
    mounted(el, binding) {
        el.clickOutsideEvent = function(event) {
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };
        document.body.addEventListener('click', el.clickOutsideEvent);
    },
    unmounted(el) {
        document.body.removeEventListener('click', el.clickOutsideEvent);
    }
};

// Dynamic page title & SEO meta tags management
const customTitle = ref('');
const customDescription = ref('');
const customKeywords = ref('');
const customImage = ref('');

const updateSEOMetaTags = () => {
    const localeVal = locale.value;
    const localeSuffix = localeVal === 'en' ? '_en' : '';
    
    // Resolve localized site settings
    const siteName = settings.value[`meta_title${localeSuffix}`] 
        || settings.value[`site_name_${localeVal}`] 
        || settings.value.site_name 
        || (localeVal === 'en' ? 'Awaan Altakadom' : 'أوان التقدم');
        
    const siteDescription = settings.value[`meta_description${localeSuffix}`] 
        || settings.value[`site_description_${localeVal}`] 
        || settings.value.site_description 
        || (localeVal === 'en' ? 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design.' : 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم.');
        
    const siteKeywords = settings.value[`meta_keywords${localeSuffix}`] 
        || settings.value.meta_keywords 
        || (localeVal === 'en' ? 'building materials, Syria, Damascus' : 'مواد بناء, سوريا, دمشق');
        
    const defaultOgImage = settings.value.og_image 
        ? (settings.value.og_image.startsWith('http') ? settings.value.og_image : `/storage/${settings.value.og_image}`)
        : '/assets/images/logo.png';

    // Route titles translations
    const routeTitles = {
        'home': '',
        'about': localeVal === 'en' ? 'About Us' : 'من نحن',
        'vision': localeVal === 'en' ? 'Identity & Vision' : 'الهوية والرؤية',
        'contact': localeVal === 'en' ? 'Contact Us' : 'اتصل بنا',
        'inquiry': localeVal === 'en' ? 'Send Inquiry' : 'إرسال استفسار',
        'purchase-request': localeVal === 'en' ? 'Purchase Request' : 'طلب شراء',
        'categories': localeVal === 'en' ? 'Categories' : 'الفئات',
        'products': localeVal === 'en' ? 'Products' : 'المنتجات',
        'cart': localeVal === 'en' ? 'Shopping Cart' : 'سلة التسوق',
        'customer.orders': localeVal === 'en' ? 'Orders & Invoices' : 'الطلبات والفواتير',
        'featured.products': localeVal === 'en' ? 'Featured Products' : 'المنتجات المميزة',
        'special.offers': localeVal === 'en' ? 'Special Offers' : 'العروض الخاصة',
    };

    let pageTitle = customTitle.value;
    if (!pageTitle) {
        if (route.name === 'product.detail') {
            pageTitle = localeVal === 'en' ? 'Product Details' : 'تفاصيل المنتج';
        } else if (route.name === 'category.detail') {
            pageTitle = localeVal === 'en' ? 'Category Details' : 'تفاصيل الفئة';
        } else {
            pageTitle = routeTitles[route.name] || '';
        }
    }

    // Set Document Title
    const finalTitle = pageTitle ? `${pageTitle} - ${siteName}` : siteName;
    document.title = finalTitle;

    // Resolve description, keywords, image content
    let finalDesc = customDescription.value || siteDescription;
    if (finalDesc) {
        finalDesc = finalDesc.replace(/<[^>]*>/g, '').trim();
        if (finalDesc.length > 160) {
            finalDesc = finalDesc.substring(0, 157) + '...';
        }
    }
    
    const finalKeywords = customKeywords.value ? `${customKeywords.value}, ${siteKeywords}` : siteKeywords;
    
    let finalImage = defaultOgImage;
    if (customImage.value) {
        finalImage = customImage.value.startsWith('http') || customImage.value.startsWith('/')
            ? customImage.value 
            : `/storage/${customImage.value}`;
    }

    // Helper to dynamically select meta tags
    const setMeta = (query, attr, value) => {
        if (!value) return;
        const el = document.querySelector(query);
        if (el) {
            el.setAttribute(attr, value);
        } else {
            const meta = document.createElement('meta');
            if (query.includes('property=')) {
                meta.setAttribute('property', query.split('"')[1]);
            } else {
                meta.setAttribute('name', query.split('"')[1]);
            }
            meta.setAttribute(attr, value);
            document.head.appendChild(meta);
        }
    };

    // Update standard meta tags
    setMeta('meta[name="description"]', 'content', finalDesc);
    setMeta('meta[name="keywords"]', 'content', finalKeywords);

    // Update Open Graph (Facebook / WhatsApp)
    setMeta('meta[property="og:title"]', 'content', finalTitle);
    setMeta('meta[property="og:description"]', 'content', finalDesc);
    setMeta('meta[property="og:image"]', 'content', finalImage);
    setMeta('meta[property="og:url"]', 'content', window.location.href);

    // Update Twitter
    setMeta('meta[property="twitter:title"]', 'content', finalTitle);
    setMeta('meta[property="twitter:description"]', 'content', finalDesc);
    setMeta('meta[property="twitter:image"]', 'content', finalImage);
    setMeta('meta[property="twitter:url"]', 'content', window.location.href);

    // Update Canonical URL
    const canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) {
        canonical.setAttribute('href', window.location.href);
    }
};

const handleDynamicTitle = (e) => {
    customTitle.value = e.detail || '';
    updateSEOMetaTags();
};

const handleDynamicSeo = (e) => {
    const data = e.detail || {};
    customTitle.value = data.title || '';
    customDescription.value = data.description || '';
    customKeywords.value = data.keywords || '';
    customImage.value = data.image || '';
    updateSEOMetaTags();
};

// Watchers for navigation cleanup and modals
watch(route, () => {
    mobileMenuOpen.value = false;
    dropdownOpen.value = false;
    scrolledDropdownOpen.value = false;
    
    // Reset custom SEO elements on route change
    customTitle.value = '';
    customDescription.value = '';
    customKeywords.value = '';
    customImage.value = '';
    
    // Scroll to top smoothly on page transitions
    window.scrollTo({ top: 0, behavior: 'smooth' });
    updateSEOMetaTags();
});

watch([() => settings.value.site_name, () => settings.value.meta_title, locale], () => {
    updateSEOMetaTags();
});

watch(activeModal, (newVal) => {
    if (newVal) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

// Lifecycle Hooks
onMounted(() => {
    console.log('PublicLayout onMounted started');
    // 1. Hide preloader shortly after load - scheduled FIRST as a bulletproof safety net
    setTimeout(() => { 
        console.log('Hiding preloader (600ms safety net)');
        preloaderActive.value = false; 
    }, 600);
    setTimeout(() => { 
        console.log('Hiding preloader (3000ms safety net)');
        preloaderActive.value = false; 
    }, 3000);

    try {
        // Dynamic Style Injection
        const linkStyle = document.createElement('link');
        linkStyle.id = 'public-style';
        linkStyle.rel = 'stylesheet';
        linkStyle.href = '/assets/css/style.css';
        document.head.appendChild(linkStyle);

        const linkRtl = document.createElement('link');
        linkRtl.id = 'public-rtl';
        linkRtl.rel = 'stylesheet';
        linkRtl.href = '/assets/css/rtl.css';
        document.head.appendChild(linkRtl);
    } catch (e) {
        console.warn('Failed to inject public styles dynamically:', e);
    }

    try {
        // Apply saved theme
        document.documentElement.setAttribute('data-theme', theme.value);
    } catch (e) {
        console.warn('Failed to apply data-theme:', e);
    }

    // Fetch initial data
    window.addEventListener('scroll', handleScroll);
    window.addEventListener('trigger-customer-login-modal', handleLoginTrigger);
    window.addEventListener('set-dynamic-title', handleDynamicTitle);
    window.addEventListener('set-dynamic-seo', handleDynamicSeo);
    updateSEOMetaTags();
    
    // Fetch Settings, Cart, and Customer profile in the background - wrapped to prevent blocking layout render
    try {
        console.log('PublicLayout background fetching started...');
        settingsStore.fetch()
            .then(() => console.log('settingsStore.fetch completed successfully'))
            .catch(e => console.error('settingsStore.fetch failed', e));

        customerAuth.init()
            .then(() => console.log('customerAuth.init completed successfully'))
            .catch(e => console.error('customerAuth.init failed', e));

        cartStore.fetchCart()
            .then(() => console.log('cartStore.fetchCart completed successfully'))
            .catch(e => console.error('cartStore.fetchCart failed', e));
    } catch (e) {
        console.warn('Failed to dispatch background store fetch actions:', e);
    }
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('trigger-customer-login-modal', handleLoginTrigger);
    window.removeEventListener('set-dynamic-title', handleDynamicTitle);
    window.removeEventListener('set-dynamic-seo', handleDynamicSeo);
    document.getElementById('public-style')?.remove();
    document.getElementById('public-rtl')?.remove();
});
</script>

<style>
:root {
    --navbar-height: 112px;
    --navbar-top-height: 74px;
    --content-padding-offset: 18px;
}

@media (max-width: 992px) {
    :root {
        --navbar-height: 74px;
        --navbar-top-height: 74px;
        --content-padding-offset: 11px;
    }
}

/* ===== BASE LAYOUT ===== */
.public-app-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    direction: rtl;
    text-align: right;
    font-family: 'Cairo', sans-serif;
}

.main-content-spa {
    flex-grow: 1;
    margin-top: 0 !important;
}












/* ===== BACKDROP OVERLAY ===== */
.mobile-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(12px) saturate(120%);
    -webkit-backdrop-filter: blur(12px) saturate(120%);
    z-index: 2999;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

/* ====================================================================
   GLASS NAVBAR — Modern Glassmorphism Design
   ==================================================================== */
.glass-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2000;
    background: linear-gradient(135deg, 
        var(--navbar-bg-color, var(--mobile-primary-dark)),
        rgba(0, 0, 0, calc(var(--navbar-transparency, 0.25) * 0.01 * 0.3))
    );
    backdrop-filter: blur(12px) saturate(150%);
    -webkit-backdrop-filter: blur(12px) saturate(150%);
    border-bottom: 1px solid rgba(255, 255, 255, calc(var(--navbar-transparency, 0.25) * 0.01 * 0.3));
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.glass-navbar.scrolled {
    background: linear-gradient(135deg, 
        var(--navbar-scrolled-bg-color, var(--mobile-primary-dark)),
        rgba(0, 0, 0, calc(var(--navbar-transparency, 0.25) * 0.01 * 0.5))
    );
    backdrop-filter: blur(16px) saturate(170%);
    -webkit-backdrop-filter: blur(16px) saturate(170%);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border-bottom: 1px solid rgba(255, 255, 255, calc(var(--navbar-transparency, 0.25) * 0.01 * 0.5));
}

.glass-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
    gap: 2rem;
}

/* Logo */
.glass-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.glass-logo:hover {
    transform: translateY(-2px);
}

.glass-logo-img {
    height: 45px;
    width: auto;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.15));
    transition: all 0.3s ease;
}

.glass-logo:hover .glass-logo-img {
    transform: scale(1.05);
}

.glass-logo-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Navigation Links */
.glass-nav-links {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-nav-link {
    padding: 0.6rem 1rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.glass-nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

.glass-nav-link-active {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
    color: white;
    border-color: rgba(102, 126, 234, 0.4);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

/* Actions */
.glass-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Search */
.glass-search {
    position: relative;
}

.glass-search-input {
    padding: 0.6rem 2.5rem 0.6rem 1rem;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    font-size: 0.9rem;
    width: 180px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    outline: none;
}

.glass-search-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.glass-search-input:focus {
    width: 240px;
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(102, 126, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.glass-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.3s ease;
}

.glass-search-input:focus ~ .glass-search-icon {
    color: rgba(102, 126, 234, 0.8);
}

.glass-search-results {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
}

.glass-result-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.glass-result-item:hover {
    background: rgba(102, 126, 234, 0.1);
}

.glass-result-item:last-child {
    border-bottom: none;
}

.glass-result-img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.glass-result-title {
    font-weight: 600;
    color: #1a202c;
    font-size: 0.9rem;
}

.glass-result-category {
    font-size: 0.8rem;
    color: #666;
}

/* Action Buttons */
.glass-action-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
    position: relative;
}

.glass-action-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(102, 126, 234, 0.5);
    color: rgba(102, 126, 234, 0.9);
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.2);
}

.glass-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

/* Profile Dropdown */
.glass-profile-dropdown {
    position: relative;
}

.glass-dropdown-menu {
    position: absolute;
    left: 0;
    top: calc(100% + 12px);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1000;
}

.glass-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.glass-dropdown-header {
    padding: 1rem 1.25rem;
    font-weight: 700;
    color: #667eea;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    font-size: 0.9rem;
}

.glass-dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    color: #333;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: right;
}

.glass-dropdown-item:hover {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.glass-dropdown-item i {
    width: 18px;
    color: #667eea;
}

/* Mobile Menu Toggle */
.glass-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    width: 30px;
    height: 24px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.glass-menu-toggle span {
    width: 100%;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.glass-menu-toggle.open span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.glass-menu-toggle.open span:nth-child(2) {
    opacity: 0;
}

.glass-menu-toggle.open span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* Responsive */
@media (max-width: 992px) {
    .glass-nav-links {
        display: none;
    }
    
    .glass-search-input {
        width: 140px;
    }
    
    .glass-search-input:focus {
        width: 180px;
    }
    
    .glass-menu-toggle {
        display: flex;
    }
}

@media (max-width: 768px) {
    .glass-container {
        padding: 0.75rem 1rem;
        gap: 1rem;
    }
    
    .glass-search {
        display: none;
    }
    
    .glass-logo-text {
        display: none;
    }
}

/* ---- Top Row: Brand + Search + Actions ---- */
.nav-top {
    position: relative;
    z-index: 10;
    background: color-mix(in srgb, var(--mobile-primary-dark, #1e3a1a) 60%, transparent);
    backdrop-filter: blur(28px) saturate(180%);
    -webkit-backdrop-filter: blur(28px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 1px 0 rgba(255, 255, 255, 0.04) inset;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

/* ---- Bottom Row: Navigation Links ---- */
.nav-bottom {
    position: relative;
    z-index: 5;
    background: color-mix(in srgb, var(--mobile-primary-dark, #1e3a1a) 45%, transparent);
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.navbar.scrolled .nav-bottom {
    background: transparent;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    box-shadow: none;
    border-bottom: none;
}

.navbar.scrolled .nav-bottom .nav-container {
    background: transparent;
    backdrop-filter: blur(30px) saturate(190%);
    -webkit-backdrop-filter: blur(30px) saturate(190%);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 50px;
    margin: 8px auto;
    max-width: calc(100% - 4.4rem);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.navbar.scrolled .nav-top {
    background: transparent;
    backdrop-filter: blur(32px) saturate(190%);
    -webkit-backdrop-filter: blur(32px) saturate(190%);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

@media (min-width: 993px) {
    .navbar.scrolled {
        transform: translateY(calc(-1 * var(--navbar-top-height)));
    }
    
    .navbar.scrolled .nav-top {
        opacity: 0;
        pointer-events: none;
        box-shadow: none;
    }
}

/* ---- Container ---- */
.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.7rem 2.2rem;
    max-width: 1440px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

.nav-links-row {
    justify-content: center;
    padding: 0.35rem 2.2rem;
}

.navbar.scrolled .nav-links-row {
    justify-content: space-between;
}

/* ===== SCROLLED NAVBAR LOGO ===== */
.scrolled-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex: 0 0 180px;
    justify-content: flex-start;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.scrolled-logo:hover {
    transform: scale(1.03);
}

.scrolled-logo-img {
    height: 34px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.1));
}

.scrolled-logo-text {
    color: #fff;
    font-weight: 800;
    font-size: 1.05rem;
    white-space: nowrap;
    background: linear-gradient(135deg, #ffffff 20%, rgba(255,255,255,0.78) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.scrolled-actions {
    flex: 0 0 180px;
    justify-content: flex-end;
    display: flex;
    align-items: center;
}

/* ===== BRAND / LOGO ===== */
.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.logo:hover {
    transform: scale(1.04);
}

.logo-img {
    height: 46px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.15));
    transition: all 0.3s ease;
}

.navbar.scrolled .logo-img {
    height: 38px;
}

.logo-text {
    color: #fff;
    font-weight: 800;
    font-size: 1.25rem;
    letter-spacing: -0.3px;
    background: linear-gradient(135deg, #ffffff 20%, rgba(255,255,255,0.7) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    white-space: nowrap;
}

/* ===== DESKTOP NAV LINKS (bottom row) ===== */
.nav-menu-desktop {
    display: flex;
    gap: 0.3rem;
    list-style: none;
    align-items: center;
    margin: 0;
    padding: 0;
}

.nav-link {
    position: relative;
    color: rgba(255, 255, 255, 0.78);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    padding: 0.45rem 1.15rem;
    border-radius: 50px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid transparent;
    letter-spacing: 0.2px;
}

.nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
}

.nav-link.active {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.16);
    font-weight: 700;
    border-color: rgba(255, 255, 255, 0.15);
    box-shadow: 0 2px 12px rgba(255, 255, 255, 0.06),
                0 0 0 1px rgba(255, 255, 255, 0.04) inset;
}

/* Animated underline indicator on active */
.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    right: 50%;
    transform: translateX(50%);
    width: 22px;
    height: 3px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.7);
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.3);
}

/* ===== SEARCH BAR ===== */
.nav-search {
    flex: 1;
    max-width: 380px;
    margin: 0 1.5rem;
}

.nav-search .search-container {
    position: relative;
    width: 100%;
}

.nav-search .search-input {
    width: 100%;
    padding: 0.6rem 1.2rem 0.6rem 2.8rem;
    font-size: 0.88rem;
    font-family: 'Cairo', sans-serif;
    border: 1.5px solid rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    background: rgba(255, 255, 255, 0.07);
    color: #ffffff;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}

.nav-search .search-input::placeholder {
    color: rgba(255, 255, 255, 0.45);
}

.nav-search .search-input:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.13);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05),
                0 4px 16px rgba(0, 0, 0, 0.08);
}

.nav-search .search-icon {
    position: absolute;
    left: 1.1rem;
    right: auto;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 0.9rem;
}

.nav-search .search-icon:hover {
    color: #ffffff;
    transform: translateY(-50%) scale(1.15);
}

/* ===== SEARCH AUTOCOMPLETE RESULTS ===== */
.search-results.show {
    display: block;
    max-height: 350px;
    overflow-y: auto;
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15), 0 4px 12px rgba(0, 0, 0, 0.06);
    z-index: 1000;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.result-item:last-child {
    border-bottom: none;
}

.result-item:hover {
    background: rgba(var(--mobile-primary-rgb, 45,90,39), 0.06);
}

.result-img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.result-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.result-title {
    font-weight: 600;
    font-size: 0.92rem;
    color: #1e293b;
}

.result-category {
    font-size: 0.78rem;
    color: #64748b;
}

/* ===== ACTION BUTTONS (User & Cart) ===== */
.cart-icon-wrapper {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.nav-action-btn {
    position: relative;
    color: #ffffff;
    font-size: 1.05rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    border: 1.5px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    outline: none;
}

.nav-action-btn:hover {
    background: rgba(255, 255, 255, 0.16);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.22);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    color: #ffffff;
}

.cart-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
    border: 2px solid color-mix(in srgb, var(--mobile-primary) 88%, #000);
    animation: cartPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes cartPop {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

/* ===== PROFILE DROPDOWN ===== */
.profile-dropdown {
    position: relative;
}

.profile-trigger {
    cursor: pointer;
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 14px);
    left: 0;
    right: auto;
    min-width: 260px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border-radius: 18px;
    box-shadow: 0 24px 56px rgba(0, 0, 0, 0.14), 0 6px 16px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.18);
    overflow: hidden;
    z-index: 1000;
    opacity: 0;
    transform: translateY(10px) scale(0.96);
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-menu.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.dropdown-header {
    padding: 16px 20px;
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
    background: rgba(248, 250, 252, 0.8);
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 0.88rem;
    font-family: 'Cairo', sans-serif;
    color: #475569;
    text-align: right;
    text-decoration: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.dropdown-item:hover {
    background: rgba(241, 245, 249, 0.8);
    color: #1e293b;
    padding-right: 24px;
}

.dropdown-item i {
    font-size: 0.95rem;
    width: 20px;
    color: #64748b;
    transition: color 0.2s;
}

.dropdown-item:hover i {
    color: var(--mobile-primary);
}

.dropdown-divider {
    height: 1px;
    background: rgba(226, 232, 240, 0.6);
    margin: 4px 0;
}

.dark-mode-item {
    justify-content: space-between;
}

.dark-mode-item .mode-label {
    flex: 1;
    text-align: right;
}

.mode-switch {
    position: relative;
    width: 38px;
    height: 20px;
    background: #cbd5e1;
    border-radius: 50px;
    transition: background 0.3s;
    flex-shrink: 0;
}

.mode-switch-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #ffffff;
    border-radius: 50%;
    transition: transform 0.3s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

/* ===== HAMBURGER BUTTON ===== */
.menu-toggle {
    display: none;
    flex-direction: column;
    justify-content: space-between;
    width: 24px;
    height: 18px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 3005;
}

.menu-toggle span {
    width: 100%;
    height: 2.5px;
    background-color: #ffffff;
    border-radius: 4px;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: center;
}

.menu-toggle.open span:nth-child(1) {
    transform: translateY(7.5px) rotate(45deg);
}

.menu-toggle.open span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
}

.menu-toggle.open span:nth-child(3) {
    transform: translateY(-7.5px) rotate(-45deg);
}

/* ===== MOBILE DRAWER ===== */
.mobile-drawer {
    display: none;
    position: fixed;
    top: 0;
    right: -100%;
    width: 300px;
    max-width: 85vw;
    height: 100vh;
    z-index: 3000;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--mobile-primary-dark) 92%, #000) 0%,
        color-mix(in srgb, var(--mobile-primary) 80%, #000) 100%
    );
    backdrop-filter: blur(32px) saturate(180%);
    -webkit-backdrop-filter: blur(32px) saturate(180%);
    box-shadow: -12px 0 48px rgba(0, 0, 0, 0.35);
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    transition: right 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.mobile-drawer.open {
    right: 0;
}

.drawer-header {
    padding: 28px 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.drawer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.drawer-logo-img {
    height: 40px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.2));
}

.drawer-logo-text {
    color: #ffffff;
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: -0.3px;
    opacity: 0.9;
}

/* Drawer Search */
.drawer-search {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.drawer-search-container {
    position: relative;
    width: 100%;
}

.drawer-search-input {
    width: 100%;
    padding: 0.62rem 1rem 0.62rem 2.6rem;
    font-size: 0.88rem;
    font-family: 'Cairo', sans-serif;
    border: 1.5px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    outline: none;
    box-sizing: border-box;
    transition: all 0.3s ease;
}

.drawer-search-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.drawer-search-input:focus {
    border-color: rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.1);
}

.drawer-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-size: 0.88rem;
}

/* Drawer Menu Items */
.drawer-menu {
    list-style: none;
    padding: 16px 0;
    margin: 0;
}

.drawer-menu li {
    margin: 2px 0;
}

.drawer-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 24px;
    margin: 0 12px;
    color: rgba(255, 255, 255, 0.72);
    text-decoration: none;
    font-size: 0.98rem;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.drawer-link i {
    font-size: 1.05rem;
    width: 22px;
    text-align: center;
    opacity: 0.7;
    transition: all 0.3s ease;
}

.drawer-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

.drawer-link:hover i {
    opacity: 1;
}

.drawer-link.active {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.13);
    font-weight: 700;
}

.drawer-link.active i {
    opacity: 1;
}

/* Active indicator bar */
.drawer-link.active::before {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 24px;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 4px 0 0 4px;
}

/* ===== MODALS ===== */
.modal-title-primary {
    margin: 0 0 5px 0;
    color: var(--mobile-primary);
}

.modal-desc-secondary {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 0.9rem;
}

.modal-error-box {
    color: #dc3545;
    background: #fdf2f2;
    padding: 10px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 12px;
    border: 1px solid #fde2e2;
}

.modal-divider {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #eee;
}

/* ===== TOAST ===== */
.cart-notification.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* ====================================================================
   DARK THEME OVERRIDES
   ==================================================================== */
[data-theme="dark"] .nav-top {
    background: rgba(15, 23, 42, 0.75);
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .nav-bottom {
    background: rgba(15, 23, 42, 0.6);
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .navbar.scrolled .nav-top {
    background: transparent;
    backdrop-filter: blur(32px) saturate(190%);
    -webkit-backdrop-filter: blur(32px) saturate(190%);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .navbar.scrolled .nav-bottom {
    background: transparent;
    box-shadow: none;
    border-bottom: none;
}

[data-theme="dark"] .navbar.scrolled .nav-bottom .nav-container {
    background: transparent;
    backdrop-filter: blur(30px) saturate(190%);
    -webkit-backdrop-filter: blur(30px) saturate(190%);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .logo-text {
    background: linear-gradient(135deg, #ffffff, #94a3b8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

[data-theme="dark"] .nav-search .search-input {
    background: rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .nav-search .search-input:focus {
    background: rgba(0, 0, 0, 0.5);
    border-color: rgba(255, 255, 255, 0.2);
}


/* Dark theme glass navbar improvements */
[data-theme="dark"] .glass-navbar {
    background: linear-gradient(135deg, 
        rgba(15, 23, 42, 0.85),
        rgba(30, 41, 59, 0.75)
    );
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .glass-navbar.scrolled {
    background: linear-gradient(135deg, 
        rgba(15, 23, 42, 0.9),
        rgba(30, 41, 59, 0.8)
    );
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .glass-nav-link {
    color: #e2e8f0;
}

[data-theme="dark"] .glass-nav-link:hover {
    color: #ffffff;
    text-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
}

[data-theme="dark"] .glass-nav-link-active {
    color: #ffffff;
    text-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
}

[data-theme="dark"] .glass-logo {
    color: #ffffff;
}

[data-theme="dark"] .drawer-link {
    color: #e2e8f0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .drawer-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

[data-theme="dark"] .drawer-link.active {
    background: rgba(99, 102, 241, 0.2);
    color: #ffffff;
    border-left: 3px solid #6366f1;
}

/* Dark theme hero button improvements */
[data-theme="dark"] .btn-hero-primary,
[data-theme="dark"] .hero .btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    border: none !important;
}

[data-theme="dark"] .btn-hero-primary:hover,
[data-theme="dark"] .hero .btn-primary:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
}


[data-theme="dark"] .hero h1 {
    color: #ffffff !important;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .hero p {
    color: #e2e8f0 !important;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

/* Redesigned btn-hero-secondary for system colors and dark mode */
.btn-hero-secondary,
.hero .btn-outline-white {
    background: rgba(255, 255, 255, 0.95);
    color: var(--mobile-primary-dark, #1e3a1a);
    border: 2px solid var(--mobile-primary-dark, #1e3a1a);
    padding: 12px 28px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-hero-secondary:hover,
.hero .btn-outline-white:hover {
    background: var(--mobile-primary-dark, #1e3a1a);
    color: #ffffff;
    border-color: var(--mobile-primary-dark, #1e3a1a);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(30, 58, 26, 0.3);
}

[data-theme="dark"] .btn-hero-secondary,
[data-theme="dark"] .hero .btn-outline-white {
    background: rgba(99, 102, 241, 0.15);
    color: #e2e8f0;
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] .btn-hero-secondary:hover,
[data-theme="dark"] .hero .btn-outline-white:hover {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

/* Elegant Page Header Design */
.public-app-wrapper .page-header {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding: 2rem 0;
    padding-top: calc(var(--navbar-height, 70px) + 2rem);
    position: relative;
    overflow: hidden;
    margin-top: 0;
}

.public-app-wrapper .page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.04) 0%, transparent 40%);
    pointer-events: none;
    z-index: 1;
}

.public-app-wrapper .page-header .container {
    position: relative;
    z-index: 2;
}

.public-app-wrapper .page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--mobile-primary-dark, #1e3a1a);
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
}

.public-app-wrapper .page-header .breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: #64748b;
    font-weight: 500;
}

.public-app-wrapper .page-header .breadcrumb a {
    color: var(--mobile-primary, var(--mobile-primary));
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.public-app-wrapper .page-header .breadcrumb a:hover {
    color: var(--mobile-primary-dark, #1e3a1a);
    transform: translateY(-1px);
}

.public-app-wrapper .page-header .breadcrumb span {
    color: #94a3b8;
}

/* Dark Mode Page Header */
[data-theme="dark"] .public-app-wrapper .page-header {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .public-app-wrapper .page-header::before {
    background: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.12) 0%, transparent 40%);
}

[data-theme="dark"] .public-app-wrapper .page-header h1 {
    color: #f1f5f9;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .public-app-wrapper .page-header .breadcrumb {
    color: #94a3b8;
}

[data-theme="dark"] .public-app-wrapper .page-header .breadcrumb a {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

[data-theme="dark"] .public-app-wrapper .page-header .breadcrumb a:hover {
    color: var(--mobile-primary);
}

[data-theme="dark"] .public-app-wrapper .page-header .breadcrumb span {
    color: #64748b;
}


/* Dark theme cart button improvements */
[data-theme="dark"] .btn-add-to-cart,
[data-theme="dark"] .btn-add-to-cart-product,
[data-theme="dark"] .btn-checkout {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    border: none !important;
}

[data-theme="dark"] .btn-add-to-cart:hover,
[data-theme="dark"] .btn-add-to-cart-product:hover,
[data-theme="dark"] .btn-checkout:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

/* Dark theme hero contact buttons improvements */
[data-theme="dark"] .float-button.whatsapp {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
    box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
}

[data-theme="dark"] .float-button.whatsapp:hover {
    background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%) !important;
    box-shadow: 0 6px 20px rgba(34, 197, 94, 0.5);
    transform: translateY(-2px);
}

[data-theme="dark"] .float-button.facebook {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

[data-theme="dark"] .float-button.facebook:hover {
    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    transform: translateY(-2px);
}

[data-theme="dark"] .float-button.inquiry {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

[data-theme="dark"] .float-button.inquiry:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
}

/* Dark theme offer card button improvements */
[data-theme="dark"] .btn-offer-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    border: none !important;
}

[data-theme="dark"] .btn-offer-primary:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
}

[data-theme="dark"] .btn-view-details {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    border: none !important;
}

[data-theme="dark"] .btn-view-details:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

[data-theme="dark"] .nav-action-btn {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .nav-action-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.15);
}

[data-theme="dark"] .cart-count {
    border-color: rgba(15, 23, 42, 0.9);
}

[data-theme="dark"] .dropdown-menu {
    background: rgba(30, 41, 59, 0.88);
    border-color: rgba(255, 255, 255, 0.06);
    box-shadow: 0 24px 56px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .dropdown-header {
    background: rgba(15, 23, 42, 0.8);
    color: #f1f5f9;
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .dropdown-item {
    color: #94a3b8;
}

[data-theme="dark"] .dropdown-item:hover {
    background: rgba(51, 65, 85, 0.7);
    color: #f8fafc;
}

[data-theme="dark"] .dropdown-item i {
    color: #64748b;
}

[data-theme="dark"] .dropdown-item:hover i {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

[data-theme="dark"] .dropdown-divider {
    background: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .mode-switch {
    background: #059669;
}

[data-theme="dark"] .mode-switch-knob {
    transform: translateX(18px);
}

[data-theme="dark"] .search-results.show {
    background: rgba(30, 41, 59, 0.92);
    border-color: rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .result-item {
    border-bottom-color: rgba(255, 255, 255, 0.04);
}

[data-theme="dark"] .result-item:hover {
    background: rgba(51, 65, 85, 0.7);
}

[data-theme="dark"] .result-title {
    color: #f8fafc;
}

[data-theme="dark"] .result-category {
    color: #94a3b8;
}

[data-theme="dark"] .mobile-drawer {
    background: linear-gradient(180deg, rgba(7, 11, 20, 0.96) 0%, rgba(15, 23, 42, 0.94) 100%);
    border-left-color: rgba(255, 255, 255, 0.04);
}

[data-theme="dark"] .mobile-menu-overlay {
    background: rgba(0, 0, 0, 0.6);
}

/* ====================================================================
   RESPONSIVE — MOBILE
   ==================================================================== */
@media (max-width: 992px) {
    .menu-toggle {
        display: flex;
    }

    .nav-bottom {
        display: none !important;
    }

    .mobile-drawer {
        display: block;
    }

    .nav-search {
        display: none;
    }

    .nav-container {
        padding: 0.65rem 1rem;
    }

    .cart-icon-wrapper {
        margin-left: 0;
    }

}

@media (max-width: 768px) {
    .logo-text {
        display: none !important;
    }

    .nav-action-btn {
        width: 36px;
        height: 36px;
        font-size: 0.95rem;
    }
}

@media (max-width: 480px) {
    .nav-container {
        padding: 0.55rem 0.75rem;
    }

    .logo-img {
        height: 36px;
    }

    .cart-icon-wrapper {
        gap: 0.4rem;
    }
}

/* ===== PRELOADER ===== */
.preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, #ffffff, #f1f5f9);
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.5s ease;
}

.preloader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}

.logo-pulse {
    position: relative;
    width: 120px;
    height: 120px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.preloader-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    animation: logoPulse 2s infinite ease-in-out;
}

.spinner-ring {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top-color: var(--mobile-primary);
    border-bottom-color: var(--mobile-primary);
    border-radius: 50%;
    animation: spinnerRotate 1.5s infinite linear;
}

.preloader-text {
    font-family: 'Cairo', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--mobile-primary);
    text-align: center;
    letter-spacing: -0.5px;
    animation: textFade 2s infinite ease-in-out;
}

/* Animations */
@keyframes logoPulse {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 4px 10px rgba(0,0,0,0.05)); }
    50% { transform: scale(1.08); filter: drop-shadow(0 8px 20px rgba(0,0,0,0.15)); }
}

@keyframes spinnerRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes textFade {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; }
}

/* Vue transition styles */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Page Transitions */
.page-fade-enter-active,
.page-fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.page-fade-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.page-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* Dark mode preloader */
[data-theme="dark"] .preloader {
    background: radial-gradient(circle at center, #0f172a, #020617);
}

[data-theme="dark"] .preloader-text {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

[data-theme="dark"] .spinner-ring {
    border-top-color: var(--mobile-primary-light, var(--mobile-primary));
    border-bottom-color: var(--mobile-primary-light, var(--mobile-primary));
}

/* ====================================================================
   MODALS & FORMS — Premium Glassmorphism
   ==================================================================== */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(12px) saturate(140%);
    -webkit-backdrop-filter: blur(12px) saturate(140%);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeInOverlay 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-content {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(25px) saturate(180%);
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 24px;
    padding: 35px 30px;
    max-width: 480px;
    width: 100%;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    direction: rtl;
    box-shadow: 
        0 4px 30px rgba(0, 0, 0, 0.03),
        0 30px 60px rgba(0, 0, 0, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);
    animation: scaleInContent 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    scrollbar-width: thin;
}

/* Scrollbar styling for modal content */
.modal-content::-webkit-scrollbar {
    width: 6px;
}
.modal-content::-webkit-scrollbar-track {
    background: transparent;
}
.modal-content::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}

.modal-close {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.05);
    background: rgba(0, 0, 0, 0.03);
    font-size: 1.4rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    transition: all 0.25s ease;
    line-height: 1;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.2);
    transform: rotate(90deg);
}

.modal-title-primary {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--mobile-primary-dark, #2d5a27);
    margin-top: 5px;
    margin-bottom: 8px;
    text-align: center;
    letter-spacing: -0.5px;
}

.modal-desc-secondary {
    font-size: 0.95rem;
    color: #64748b;
    margin-bottom: 25px;
    text-align: center;
    line-height: 1.5;
}

/* Form Styling */
.modal-content .form-group {
    margin-bottom: 20px;
    text-align: right;
}

.modal-content label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #334155;
    font-size: 0.9rem;
}

.modal-content input,
.modal-content textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-family: 'Cairo', sans-serif;
    background: rgba(255, 255, 255, 0.6);
    color: #1e293b;
}

.modal-content input:focus,
.modal-content textarea:focus {
    outline: none;
    border-color: var(--mobile-primary);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(45, 90, 39, 0.12);
}

.modal-content input::placeholder,
.modal-content textarea::placeholder {
    color: #94a3b8;
}

/* Submit Button */
.modal-content .btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, var(--mobile-primary), var(--mobile-primary-dark));
    color: white;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 15px rgba(45, 90, 39, 0.25);
    font-family: inherit;
}

.modal-content .btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(45, 90, 39, 0.35);
    filter: brightness(1.05);
}

.modal-content .btn-submit:active:not(:disabled) {
    transform: translateY(0);
}

.modal-content .btn-submit:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    box-shadow: none;
}

.modal-divider {
    border: none;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    margin: 24px 0;
}

.modal-error-box {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.15);
    color: #ef4444;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.9rem;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 500;
    animation: shakeError 0.35s ease-in-out;
}

.modal-footer-text {
    text-align: center;
    font-size: 0.9rem;
    color: #64748b;
    margin-top: 15px;
    margin-bottom: 0;
}

.modal-link {
    color: var(--mobile-primary);
    font-weight: 600;
    text-decoration: none;
    transition: color 0.25s ease;
}

.modal-link:hover {
    color: var(--mobile-primary-dark, #1e3a1a);
    text-decoration: underline;
}

.form-hint {
    color: #64748b;
    font-size: 0.8rem;
    display: block;
    margin-top: 6px;
    line-height: 1.4;
}

/* Animations */
@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleInContent {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(15px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes shakeError {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}

/* ====================================================================
   DARK MODE FOR MODALS
   ==================================================================== */
[data-theme="dark"] .modal-overlay {
    background: rgba(2, 6, 23, 0.6);
}

[data-theme="dark"] .modal-content {
    background: rgba(30, 41, 59, 0.85);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
    box-shadow: 
        0 4px 30px rgba(0, 0, 0, 0.2),
        0 30px 60px rgba(0, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .modal-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .modal-close {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: #94a3b8;
}

[data-theme="dark"] .modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .modal-title-primary {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

[data-theme="dark"] .modal-desc-secondary {
    color: #94a3b8;
}

[data-theme="dark"] .modal-content label {
    color: #cbd5e1;
}

[data-theme="dark"] .modal-content input,
[data-theme="dark"] .modal-content textarea {
    background: rgba(15, 23, 42, 0.4);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
}

[data-theme="dark"] .modal-content input:focus,
[data-theme="dark"] .modal-content textarea:focus {
    border-color: var(--mobile-primary-light, var(--mobile-primary));
    background: rgba(15, 23, 42, 0.6);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary-light, var(--mobile-primary)) 15%, transparent);
}

[data-theme="dark"] .modal-divider {
    border-top-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .modal-error-box {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}

[data-theme="dark"] .modal-footer-text {
    color: #94a3b8;
}

[data-theme="dark"] .modal-link {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

[data-theme="dark"] .modal-link:hover {
    color: var(--mobile-primary);
}

[data-theme="dark"] .form-hint {
    color: #94a3b8;
}

/* ====================================================================
   LTR DIRECTIONS & FONTS FOR PUBLIC LAYOUT
   ==================================================================== */
[dir="ltr"] .public-app-wrapper {
    direction: ltr;
    text-align: left;
    font-family: 'Outfit', 'Inter', 'Cairo', sans-serif;
}

[dir="ltr"] .public-app-wrapper .page-header {
    text-align: left;
}

[dir="ltr"] .public-app-wrapper .page-header h1 {
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.02em;
}

[dir="ltr"] .public-app-wrapper .page-header .breadcrumb {
    font-family: 'Inter', sans-serif;
}
</style>

