<template>
    <nav class="navbar" :class="{ scrolled: isScrolled, dark: isDarkMode }">
        <div class="navbar-container">
            <div class="navbar-content">
                <!-- Logo -->
                <router-link to="/" class="navbar-logo">
                    <img :src="siteLogo" :alt="siteName" @error="handleLogoError">
                    <span v-if="showSiteName">{{ siteName }}</span>
                </router-link>

                <!-- Desktop Navigation -->
                <div class="navbar-nav desktop-nav">
                    <router-link to="/" class="nav-link">{{ $t('nav_home') }}</router-link>
                    <router-link to="/categories" class="nav-link">{{ $t('nav_categories') }}</router-link>
                    <div class="nav-dropdown" @mouseenter="isProductsDropdownOpen = true" @mouseleave="isProductsDropdownOpen = false">
                        <button class="nav-link dropdown-trigger" @click="toggleProductsDropdown">
                            {{ $t('nav_products') }}
                            <i class="fas fa-chevron-down dropdown-arrow" :class="{ 'rotated': isProductsDropdownOpen }"></i>
                        </button>
                        <div class="dropdown-menu" :class="{ 'show': isProductsDropdownOpen }">
                            <router-link to="/products" class="dropdown-item">
                                <i class="fas fa-th-list"></i> {{ $t('all_products') || 'All Products' }}
                            </router-link>
                            <router-link to="/featured-products" class="dropdown-item">
                                <i class="fas fa-star"></i> {{ $t('featured_products') || 'Featured Products' }}
                            </router-link>
                            <div class="dropdown-divider"></div>
                            <router-link to="/products?category=electronics" class="dropdown-item">
                                <i class="fas fa-laptop"></i> {{ $t('electronics') || 'Electronics' }}
                            </router-link>
                            <router-link to="/products?category=clothing" class="dropdown-item">
                                <i class="fas fa-tshirt"></i> {{ $t('clothing') || 'Clothing' }}
                            </router-link>
                            <router-link to="/products?category=home" class="dropdown-item">
                                <i class="fas fa-home"></i> {{ $t('home_garden') || 'Home & Garden' }}
                            </router-link>
                        </div>
                    </div>
                    <div class="nav-dropdown" @mouseenter="isOffersDropdownOpen = true" @mouseleave="isOffersDropdownOpen = false">
                        <button class="nav-link dropdown-trigger" @click="toggleOffersDropdown">
                            {{ $t('special_offers') || 'Special Offers' }}
                            <i class="fas fa-tag dropdown-arrow" :class="{ 'rotated': isOffersDropdownOpen }"></i>
                        </button>
                        <div class="dropdown-menu" :class="{ 'show': isOffersDropdownOpen }">
                            <router-link to="/special-offers" class="dropdown-item">
                                <i class="fas fa-fire"></i> {{ $t('current_offers') || 'Current Offers' }}
                            </router-link>
                            <router-link to="/special-offers?flash" class="dropdown-item">
                                <i class="fas fa-bolt"></i> {{ $t('flash_sales') || 'Flash Sales' }}
                            </router-link>
                            <router-link to="/special-offers?clearance" class="dropdown-item">
                                <i class="fas fa-percent"></i> {{ $t('clearance') || 'Clearance' }}
                            </router-link>
                            <router-link to="/special-offers?bundle" class="dropdown-item">
                                <i class="fas fa-boxes"></i> {{ $t('bundle_deals') || 'Bundle Deals' }}
                            </router-link>
                        </div>
                    </div>
                    <router-link to="/vision" class="nav-link">{{ $t('nav_vision') }}</router-link>
                    <router-link to="/about" class="nav-link">{{ $t('nav_about') }}</router-link>
                    <router-link to="/contact" class="nav-link">{{ $t('nav_contact') }}</router-link>
                </div>

                <!-- Search & Actions -->
                <div class="navbar-actions">
                    <!-- Language Switcher -->
                    <div class="language-switcher" style="display: flex !important; gap: 8px !important; align-items: center !important; visibility: visible !important; opacity: 1 !important;">
                        <a 
                            @click="switchLanguage('en')" 
                            class="action-btn lang-btn"
                            :style="{ padding: '8px 12px !important', borderRadius: '50% !important', textDecoration: 'none !important', color: 'white !important', fontSize: '16px !important', transition: 'all 0.3s ease !important', backgroundColor: currentLocale === 'en' ? 'rgba(255, 255, 255, 0.2) !important' : 'rgba(255, 255, 255, 0.1) !important', borderColor: currentLocale === 'en' ? 'var(--mobile-accent, var(--accent-gold, #c9a959)) !important' : 'rgba(255, 255, 255, 0.18) !important', display: 'flex !important', alignItems: 'center !important', justifyContent: 'center !important', minWidth: '42px !important', minHeight: '42px !important' }"
                            title="English"
                        >
                            🇬🇧
                        </a>
                        <a 
                            @click="switchLanguage('ar')" 
                            class="action-btn lang-btn"
                            :style="{ padding: '8px 12px !important', borderRadius: '50% !important', textDecoration: 'none !important', color: 'white !important', fontSize: '16px !important', transition: 'all 0.3s ease !important', backgroundColor: currentLocale === 'ar' ? 'rgba(255, 255, 255, 0.2) !important' : 'rgba(255, 255, 255, 0.1) !important', borderColor: currentLocale === 'ar' ? 'var(--mobile-accent, var(--accent-gold, #c9a959)) !important' : 'rgba(255, 255, 255, 0.18) !important', display: 'flex !important', alignItems: 'center !important', justifyContent: 'center !important', minWidth: '42px !important', minHeight: '42px !important' }"
                            title="العربية"
                        >
                            🇸🇦
                        </a>
                    </div>

                    <div class="search-box desktop-search">
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            :placeholder="$t('search')"
                        >
                        <i class="fas fa-search search-icon" @click="handleSearch"></i>
                    </div>

                    <!-- Orders button -->
                    <router-link to="/customer-orders" class="action-btn orders-btn" :title="$t('my_orders') || 'طلباتي'">
                        <i class="fas fa-box-open"></i>
                    </router-link>

                    <!-- Cart button -->
                    <router-link to="/cart" class="action-btn cart-btn" :title="$t('shopping_cart') || 'سلة المشتريات'">
                        <i class="fas fa-shopping-cart"></i>
                        <span v-if="cartStore.totalItems > 0" class="cart-badge">{{ cartStore.totalItems }}</span>
                    </router-link>

                    <!-- User Account / Profile dropdown -->
                    <div 
                        class="profile-dropdown"
                        @mouseenter="isProfileMenuOpen = true"
                        @mouseleave="isProfileMenuOpen = false"
                    >
                        <button class="action-btn profile-trigger" :title="$t('my_profile') || 'حسابي'">
                            <i class="fas fa-user"></i>
                        </button>
                        <div v-show="isProfileMenuOpen" class="profile-menu-dropdown">
                            <template v-if="customerAuthStore.isAuthenticated">
                                <div class="profile-menu-header">{{ $t('welcome') || 'مرحباً' }}، {{ customerAuthStore.customer?.name }}</div>
                                <div class="profile-menu-divider"></div>
                                <a href="#" class="profile-menu-item logout" @click.prevent="handleLogout">
                                    <i class="fas fa-sign-out-alt"></i> {{ $t('logout') || 'تسجيل خروج' }}
                                </a>
                            </template>
                            <template v-else>
                                <div class="profile-menu-header">{{ $t('my_profile') || 'حسابي' }}</div>
                                <a href="#" class="profile-menu-item" @click.prevent="triggerLoginModal">
                                    <i class="fas fa-sign-in-alt"></i> {{ $t('login') || 'تسجيل دخول' }}
                                </a>
                                <a href="#" class="profile-menu-item" @click.prevent="triggerSubscribeModal">
                                    <i class="fas fa-bell"></i> {{ $t('subscribe_with_us') || 'اشترك معنا' }}
                                </a>
                            </template>
                        </div>
                    </div>

                    <button 
                        @click="toggleDarkMode"
                        class="action-btn"
                        :title="$t('dark_mode') || 'الوضع الداكن'"
                    >
                        <i :class="isDarkMode ? 'fas fa-sun' : 'fas fa-moon'"></i>
                    </button>

                    <button 
                        @click="toggleMenu"
                        class="action-btn mobile-menu-btn"
                    >
                        <i :class="isMenuOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div v-show="isMenuOpen" class="mobile-menu">
                <div class="mobile-search">
                    <input 
                        type="text" 
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        :placeholder="$t('search_placeholder') || 'ابحث عن منتج...'"
                    >
                    <i class="fas fa-search search-icon" @click="handleSearch"></i>
                </div>
                <div class="mobile-nav">
                    <!-- Language Switcher Mobile -->
                    <div class="mobile-language-switcher" style="display: flex; gap: 12px; justify-content: center; padding: 1rem 0;">
                        <a 
                            @click="switchLanguage('en'); isMenuOpen = false;" 
                            class="mobile-lang-btn {{ currentLocale === 'en' ? 'active' : '' }}"
                            style="padding: 8px 16px; border-radius: 8px; text-decoration: none; color: white; font-size: 14px; transition: all 0.3s ease; background-color: currentLocale === 'en' ? 'rgba(255, 255, 255, 0.2)' : 'rgba(255, 255, 255, 0.1); border: 1px solid currentLocale === 'en' ? 'var(--mobile-accent, var(--accent-gold, #c9a959))' : 'rgba(255, 255, 255, 0.18)';"
                        >
                            🇬🇧 English
                        </a>
                        <a 
                            @click="switchLanguage('ar'); isMenuOpen = false;" 
                            class="mobile-lang-btn {{ currentLocale === 'ar' ? 'active' : '' }}"
                            style="padding: 8px 16px; border-radius: 8px; text-decoration: none; color: white; font-size: 14px; transition: all 0.3s ease; background-color: currentLocale === 'ar' ? 'rgba(255, 255, 255, 0.2)' : 'rgba(255, 255, 255, 0.1); border: 1px solid currentLocale === 'ar' ? 'var(--mobile-accent, var(--accent-gold, #c9a959))' : 'rgba(255, 255, 255, 0.18)';"
                        >
                            🇸🇦 العربية
                        </a>
                    </div>
                    
                    <div class="mobile-divider"></div>
                    <router-link to="/" class="mobile-nav-link" @click="isMenuOpen = false">{{ $t('nav_home') }}</router-link>
                    <router-link to="/categories" class="mobile-nav-link" @click="isMenuOpen = false">{{ $t('nav_categories') }}</router-link>
                    <div class="mobile-nav-section-label">{{ $t('nav_products') }}</div>
                    <router-link to="/products" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-th-list"></i> {{ $t('all_products') || 'All Products' }}
                    </router-link>
                    <router-link to="/featured-products" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-star"></i> {{ $t('featured_products') || 'Featured Products' }}
                    </router-link>
                    <router-link to="/products?category=electronics" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-laptop"></i> {{ $t('electronics') || 'Electronics' }}
                    </router-link>
                    <router-link to="/products?category=clothing" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-tshirt"></i> {{ $t('clothing') || 'Clothing' }}
                    </router-link>
                    <router-link to="/products?category=home" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-home"></i> {{ $t('home_garden') || 'Home & Garden' }}
                    </router-link>
                    
                    <div class="mobile-divider"></div>
                    <div class="mobile-nav-section-label">{{ $t('special_offers') || 'Special Offers' }}</div>
                    <router-link to="/special-offers" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-fire"></i> {{ $t('current_offers') || 'Current Offers' }}
                    </router-link>
                    <router-link to="/special-offers?flash" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-bolt"></i> {{ $t('flash_sales') || 'Flash Sales' }}
                    </router-link>
                    <router-link to="/special-offers?clearance" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-percent"></i> {{ $t('clearance') || 'Clearance' }}
                    </router-link>
                    <router-link to="/special-offers?bundle" class="mobile-nav-link mobile-nav-sublink" @click="isMenuOpen = false">
                        <i class="fas fa-boxes"></i> {{ $t('bundle_deals') || 'Bundle Deals' }}
                    </router-link>
                    
                    <div class="mobile-divider"></div>
                    <router-link to="/vision" class="mobile-nav-link" @click="isMenuOpen = false">{{ $t('nav_vision') }}</router-link>
                    <router-link to="/about" class="mobile-nav-link" @click="isMenuOpen = false">{{ $t('nav_about') }}</router-link>
                    <router-link to="/contact" class="mobile-nav-link" @click="isMenuOpen = false">{{ $t('nav_contact') }}</router-link>
                    
                    <div class="mobile-divider"></div>
                    <router-link to="/cart" class="mobile-nav-link" @click="isMenuOpen = false">
                        <i class="fas fa-shopping-cart"></i> {{ $t('shopping_cart') || 'السلة' }} ({{ cartStore.totalItems }})
                    </router-link>
                    <template v-if="customerAuthStore.isAuthenticated">
                        <a href="#" class="mobile-nav-link logout" @click.prevent="handleLogout">
                            <i class="fas fa-sign-out-alt"></i> {{ $t('logout') || 'تسجيل خروج' }}
                        </a>
                    </template>
                    <template v-else>
                        <a href="#" class="mobile-nav-link" @click.prevent="triggerLoginModal">
                            <i class="fas fa-sign-in-alt"></i> {{ $t('login') || 'تسجيل دخول' }}
                        </a>
                        <a href="#" class="mobile-nav-link" @click.prevent="triggerSubscribeModal">
                            <i class="fas fa-bell"></i> {{ $t('subscribe_with_us') || 'اشترك معنا' }}
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import { useCustomerAuthStore } from '@/stores/customerAuth';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { locale } = useI18n();
const searchQuery = ref('');
const isDarkMode = ref(false);
const isMenuOpen = ref(false);
const isScrolled = ref(false);
const isProfileMenuOpen = ref(false);
const isProductsDropdownOpen = ref(false);
const isOffersDropdownOpen = ref(false);

const currentLocale = computed(() => locale.value);

const cartStore = useCartStore();
const customerAuthStore = useCustomerAuthStore();
const settingsStore = useSettingsStore();

const settings = computed(() => settingsStore.data);

const siteName = computed(() => settings.value.site_name || 'أوان التقدم');
const siteLogo = computed(() => {
    const logo = settings.value.site_logo;
    if (logo) {
        // Handle different logo path formats
        if (logo.startsWith('http://') || logo.startsWith('https://')) {
            return logo;
        }
        if (logo.startsWith('assets/')) {
            return `/${logo}`;
        }
        if (logo.startsWith('/')) {
            return logo;
        }
        return `/storage/${logo}`;
    }
    // Fallback to default logo
    return '/assets/images/logo.png';
});
const showSiteName = computed(() => settings.value.show_site_name === '1' || settings.value.show_site_name === true);

function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle('dark', isDarkMode.value);
    document.documentElement.setAttribute('data-theme', isDarkMode.value ? 'dark' : 'light');
    localStorage.setItem('darkMode', isDarkMode.value);
    localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light');
}

function toggleMenu() {
    isMenuOpen.value = !isMenuOpen.value;
}

function toggleProductsDropdown() {
    isProductsDropdownOpen.value = !isProductsDropdownOpen.value;
}

function toggleOffersDropdown() {
    isOffersDropdownOpen.value = !isOffersDropdownOpen.value;
}

async function handleLogout() {
    isProfileMenuOpen.value = false;
    isMenuOpen.value = false;
    await customerAuthStore.logout();
    router.push('/');
}

function triggerLoginModal() {
    isProfileMenuOpen.value = false;
    isMenuOpen.value = false;
    window.dispatchEvent(new CustomEvent('trigger-customer-login-modal'));
}

function triggerSubscribeModal() {
    isProfileMenuOpen.value = false;
    isMenuOpen.value = false;
    window.dispatchEvent(new CustomEvent('trigger-customer-subscribe-modal'));
}

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.push({ name: 'search', query: { q: searchQuery.value } });
    }
}

function handleLogoError(event) {
    // Fallback to default logo if the configured logo fails to load
    event.target.src = '/assets/images/logo.png';
}

function onScroll() {
    isScrolled.value = window.scrollY > 50;
}

function switchLanguage(newLocale) {
    locale.value = newLocale;
    localStorage.setItem('locale', newLocale);
    document.documentElement.lang = newLocale;
    document.documentElement.dir = newLocale === 'ar' ? 'rtl' : 'ltr';
    
    // Call Laravel route to update session locale
    fetch(`/lang/${newLocale}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(() => {
        window.location.reload();
    });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll);
    
    // Set initial locale and direction
    const savedLocale = localStorage.getItem('locale') || window.systemData?.locale || 'ar';
    locale.value = savedLocale;
    document.documentElement.lang = savedLocale;
    document.documentElement.dir = savedLocale === 'ar' ? 'rtl' : 'ltr';
    
    // Fetch initial settings, cart and auth details
    settingsStore.fetch().catch((err) => console.warn(err));
    cartStore.fetchCart().catch((err) => console.warn(err));
    customerAuthStore.init().catch((err) => console.warn(err));

    const savedDarkMode = localStorage.getItem('darkMode') || localStorage.getItem('theme') === 'dark';
    if (savedDarkMode === 'true' || savedDarkMode === true) {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
    }
});
</script>

<style scoped>
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2000;
    /* Enhanced frosted glass with better color blending */
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--mobile-primary) 94%, rgba(20, 32, 48, 0.88)), 
        color-mix(in srgb, var(--mobile-primary-dark) 96%, rgba(13, 27, 42, 0.92))
    );
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 32px color-mix(in srgb, var(--mobile-primary) 18%, rgba(0, 0, 0, 0.18));
}

.navbar.scrolled {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--mobile-primary) 96%, rgba(20, 32, 48, 0.92)), 
        color-mix(in srgb, var(--mobile-primary-dark) 98%, rgba(13, 27, 42, 0.95))
    );
    box-shadow: 0 12px 48px color-mix(in srgb, var(--mobile-primary) 28%, rgba(0, 0, 0, 0.3));
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
}

.navbar-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

.navbar-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 80px;
}

.navbar-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    position: relative;
}

.navbar-logo img {
    height: 55px;
    width: auto;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
}

.navbar-logo:hover img {
    transform: scale(1.1) translateY(-2px);
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
}

.navbar-logo span {
    font-size: 1.3rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.navbar-logo:hover span {
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
}

.navbar-nav {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.nav-link {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    padding: 0.5rem 0;
}

.nav-link::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, 
        var(--mobile-accent, var(--accent-gold, #c9a959)), 
        var(--mobile-accent-light, var(--accent-gold-light, #f6ad55))
    );
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 2px;
    transform: translateX(-50%);
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, 
        var(--mobile-accent, var(--accent-gold, #c9a959)), 
        var(--mobile-accent-light, var(--accent-gold-light, #f6ad55))
    );
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 2px;
}

.nav-link:hover::before,
.nav-link.router-link-active::before {
    width: 100%;
}

.nav-link:hover::after,
.nav-link.router-link-active::after {
    opacity: 0;
}

.nav-link:hover,
.nav-link.router-link-active {
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    transform: translateY(-3px);
    text-shadow: 0 0 20px color-mix(in srgb, var(--mobile-accent) 30%, transparent);
}

.nav-link-featured {
    position: relative;
    padding-left: 1.6rem !important;
}

.nav-link-featured::before {
    content: '\f005';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
}

/* Products Dropdown */
.nav-dropdown {
    position: relative;
}

.dropdown-trigger {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-family: inherit;
    padding: 0.5rem 0;
}

.dropdown-arrow {
    font-size: 0.65rem;
    transition: transform 0.3s ease;
}

.dropdown-arrow.rotated {
    transform: rotate(180deg);
}

.nav-dropdown:hover .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 12px);
    right: 50%;
    transform: translateX(50%) translateY(-8px) scale(0.96);
    min-width: 240px;
    background: rgba(15, 23, 42, 0.97);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.04);
    z-index: 10000;
    padding: 0.5rem;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(50%) translateY(0) scale(1);
    pointer-events: auto;
}

.nav-dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateX(50%) translateY(0) scale(1);
    pointer-events: auto;
}

.dropdown-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 12px;
    height: 12px;
    background: rgba(15, 23, 42, 0.97);
    border-left: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 3px 0 0 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.25s ease;
    white-space: nowrap;
    position: relative;
    border-left: 3px solid transparent;
}

.dropdown-item i {
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    width: 20px;
    font-size: 0.9rem;
    text-align: center;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
    color: white;
    border-left-color: var(--mobile-accent, var(--accent-gold, #c9a959));
    padding-left: 1.2rem;
}

.dropdown-item:hover i {
    transform: scale(1.15);
    color: white;
}

.dropdown-item + .dropdown-item {
    margin-top: 2px;
}

.dropdown-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 0.5rem 0;
}

/* Mobile nav section label */
.mobile-nav-section-label {
    padding: 0.75rem 1.1rem 0.25rem;
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    opacity: 0.85;
}

.mobile-nav-sublink {
    padding-right: 2.2rem !important;
    font-size: 0.93rem !important;
    margin: 0 0.5rem !important;
    border-radius: 10px !important;
    border-left: 3px solid rgba(255, 255, 255, 0.06);
    position: relative;
}

.mobile-nav-sublink i {
    font-size: 0.85rem !important;
}

.mobile-nav-sublink.router-link-active {
    border-left-color: var(--mobile-accent, var(--accent-gold, #c9a959));
    background: rgba(255, 255, 255, 0.04);
}

.mobile-nav-sublink:hover {
    border-left-color: var(--mobile-accent, var(--accent-gold, #c9a959));
}

.navbar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box input {
    padding: 0.6rem 2.8rem 0.6rem 1.1rem;
    border: 2px solid rgba(255, 255, 255, 0.18);
    border-radius: 50px;
    font-size: 0.875rem;
    width: 200px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-family: inherit;
    backdrop-filter: blur(10px);
}

.search-box input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.search-box input:focus {
    border-color: var(--mobile-accent, var(--accent-gold, #c9a959));
    background: rgba(255, 255, 255, 0.18);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-accent) 25%, transparent), 
                0 4px 12px rgba(0, 0, 0, 0.15);
    width: 260px;
}

.search-icon {
    position: absolute;
    left: 0.95rem;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    transition: all 0.3s ease;
    top: 50%;
    transform: translateY(-50%);
}

.search-box input:focus ~ .search-icon {
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    transform: translateY(-50%) scale(1.1);
}

.action-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.1);
    color: white;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    position: relative;
    text-decoration: none;
    backdrop-filter: blur(10px);
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: var(--mobile-accent, var(--accent-gold, #c9a959));
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--mobile-accent) 25%, rgba(0, 0, 0, 0.2));
}

.action-btn:active {
    transform: scale(0.95);
}

/* Cart Badge */
.cart-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: linear-gradient(135deg, var(--mobile-accent, var(--accent-gold, #c9a959)), var(--mobile-accent-light, var(--accent-gold-light, #f6ad55)));
    color: #121c2c;
    font-weight: 700;
    font-size: 0.75rem;
    min-width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    box-shadow: 0 3px 10px color-mix(in srgb, var(--mobile-accent) 45%, rgba(0, 0, 0, 0.2));
    border: 2px solid #1a2634;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

/* Profile Dropdown */
.profile-dropdown {
    position: relative;
}

.profile-menu-dropdown {
    position: absolute;
    left: 0;
    top: calc(100% + 12px);
    background: rgba(26, 38, 52, 0.96);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 240px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    z-index: 1000;
    overflow: hidden;
    animation: fadeInMenu 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeInMenu {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.profile-menu-header {
    padding: 1.1rem 1.4rem;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    text-align: right;
}

.profile-menu-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.95rem 1.4rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 600;
    transition: all 0.25s ease;
    text-align: right;
    border-radius: 8px;
    margin: 0.25rem 0.5rem;
}

.profile-menu-item i {
    font-size: 1.05rem;
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    width: 20px;
    transition: transform 0.3s ease;
}

.profile-menu-item:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

.profile-menu-item:hover i {
    transform: scale(1.15);
}

.profile-menu-item.logout {
    color: #ef4444;
}

.profile-menu-item.logout i {
    color: #ef4444;
}

.profile-menu-item.logout:hover {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.profile-menu-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 6px 0;
}

.mobile-menu-btn {
    display: none;
}

.desktop-nav,
.desktop-search {
    display: flex;
}

.mobile-menu {
    display: none;
}

.mobile-search {
    display: none;
    position: relative;
    margin-bottom: 1.4rem;
}

.mobile-search input {
    width: 100%;
    padding: 0.8rem 3rem 0.8rem 1.1rem;
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 50px;
    font-size: 0.92rem;
    outline: none;
    background: rgba(255, 255, 255, 0.08);
    color: white;
    font-family: inherit;
    backdrop-filter: blur(10px);
}

.mobile-search input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.mobile-search input:focus {
    border-color: var(--mobile-accent, var(--accent-gold, #c9a959));
    background: rgba(255, 255, 255, 0.14);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-accent) 25%, transparent);
}

.mobile-search .search-icon {
    position: absolute;
    left: 0.95rem;
    color: rgba(255, 255, 255, 0.55);
    pointer-events: none;
    top: 50%;
    transform: translateY(-50%);
}

.mobile-nav {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.mobile-nav-link {
    padding: 1rem 1.1rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-weight: 600;
    border-radius: 14px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 1.02rem;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.mobile-nav-link i {
    font-size: 1.15rem;
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    width: 22px;
    transition: transform 0.3s ease;
}

.mobile-nav-link:hover,
.mobile-nav-link.router-link-active {
    background: rgba(255, 255, 255, 0.08);
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    transform: translateX(-6px);
}

.mobile-nav-link:hover i,
.mobile-nav-link.router-link-active i {
    transform: scale(1.15);
}

.mobile-nav-link.logout {
    color: #ef4444;
}

.mobile-nav-link.logout i {
    color: #ef4444;
}

.mobile-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 10px 0;
}

@media (max-width: 1100px) {
    .search-box input {
        width: 160px;
    }

    .search-box input:focus {
        width: 200px;
    }

    .navbar-nav {
        gap: 1.1rem;
    }

    .nav-link {
        font-size: 0.9rem;
    }
}

@media (max-width: 992px) {
    .navbar-container {
        padding: 0 1.5rem;
    }

    .navbar-content {
        height: 72px;
    }

    .navbar-logo img {
        height: 50px;
    }

    .desktop-nav,
    .desktop-search {
        display: none;
    }

    .mobile-menu-btn {
        display: flex;
    }

    .mobile-menu {
        display: block;
        padding: 1.8rem 0;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        animation: slideDown 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        background: linear-gradient(180deg, 
            color-mix(in srgb, var(--mobile-primary-dark) 98%, black), 
            color-mix(in srgb, var(--mobile-primary-dark) 95%, black)
        );
        margin: 0 -1.5rem;
        padding: 1.8rem;
        border-radius: 0 0 20px 20px;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .mobile-search {
        display: block;
    }

    .navbar-logo span {
        font-size: 1.15rem;
    }
}

@media (max-width: 768px) {
    .navbar-content {
        height: 65px;
    }

    .navbar-logo img {
        height: 42px;
    }

    .navbar-logo span {
        display: none;
    }

    .action-btn {
        width: 38px;
        height: 38px;
    }
}

/* Dark mode overrides */
.navbar.dark {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--mobile-primary-dark) 88%, rgba(10, 15, 30, 0.98)), 
        color-mix(in srgb, var(--mobile-primary-dark) 96%, rgba(10, 15, 30, 0.99))
    );
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.navbar.dark.scrolled {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--mobile-primary-dark) 90%, rgba(10, 15, 30, 0.98)), 
        color-mix(in srgb, var(--mobile-primary-dark) 98%, rgba(10, 15, 30, 0.99))
    );
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.6);
}

.navbar.dark .profile-menu-dropdown {
    background: rgba(18, 28, 44, 0.98);
    border-color: rgba(255, 255, 255, 0.08);
}
</style>
