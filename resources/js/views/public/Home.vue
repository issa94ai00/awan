<template>
    <div class="home-page-view">
        <!-- Hero Section -->
        <section class="hero" id="home" :style="heroBgStyle">
            <div class="hero-content">
                <h1>{{ $p(settings, 'site_name') || 'أوان التقدم' }}</h1>
                <p>{{ $p(settings, 'site_tagline') || 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة، لجميع موديلات الهواتف الذكية' }}</p>
                <div class="hero-buttons">
                    <router-link to="/categories" class="btn-hero-primary">
                        <i class="fas fa-mobile-alt"></i>
                        {{ t('browse_products') || 'تصفح المنتجات' }}
                    </router-link>
                    <router-link to="/contact" class="btn-hero-secondary">
                        <i class="fas fa-headset"></i>
                        {{ t('contact_us_btn') || 'تواصل معنا' }}
                    </router-link>
                </div>
            </div>
            <div class="floating-icons">
                <i class="fas fa-mobile-alt"></i>
                <i class="fas fa-microchip"></i>
                <i class="fas fa-battery-full"></i>
                <i class="fas fa-camera"></i>
            </div>
        </section>

        <!-- Secondary Navigation Bar -->
        <section class="secondary-navbar" id="secondary-nav">
            <div class="container">
                <div class="secondary-nav-content">
                    <template v-for="secItem in secondaryNavItems" :key="secItem.id">
                        <div v-if="secItem.active && secItem.type === 'dropdown'" class="nav-item dropdown" @mouseenter="openSecDropdown(secItem.id)" @mouseleave="closeSecDropdown(secItem.id)">
                            <button class="nav-trigger" @click="toggleSecDropdown(secItem.id)">
                                <i :class="secItem.icon"></i>
                                {{ getLabel(secItem) }}
                                <i class="fas fa-chevron-down dropdown-arrow" :class="{ 'rotated': openDropdowns[secItem.id] }"></i>
                            </button>
                            <div class="dropdown-menu" :class="{ 'show': openDropdowns[secItem.id] }">
                                <router-link v-for="child in getActiveChildren(secItem)" :key="child.id" :to="child.route" class="dropdown-item">
                                    <i :class="child.icon"></i> {{ getLabel(child) }}
                                </router-link>
                            </div>
                        </div>
                        <router-link v-else-if="secItem.active && secItem.type === 'link'" :to="secItem.route" class="nav-item">
                            <i :class="secItem.icon"></i>
                            {{ getLabel(secItem) }}
                        </router-link>
                    </template>
                </div>
            </div>
        </section>

        <!-- Special Offers Carousel Section -->
        <section v-if="specialOffers.length" class="special-offers fade-up" id="special-offers">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('special_offers_title') || 'العروض المميزة' }}</h2>
                    <p>{{ t('special_offers_subtitle') || 'اكتشف أقوى العروض والخصومات الحصرية لفترة محدودة' }}</p>
                </div>
                
                <div class="offers-slider-wrapper">
                    <button class="slider-btn prev-btn" aria-label="العرض السابق" @click="scrollSlider('prev')" :style="{ opacity: prevBtnOpacity, pointerEvents: prevBtnOpacity === '0.5' ? 'none' : 'auto' }">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="slider-btn next-btn" aria-label="العرض التالي" @click="scrollSlider('next')" :style="{ opacity: nextBtnOpacity, pointerEvents: nextBtnOpacity === '0.5' ? 'none' : 'auto' }">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <div class="offers-slider" ref="offersSlider" @scroll="updateSliderButtons">
                        <div v-for="offer in specialOffers" :key="offer.id" class="offer-card-container">
                            <div class="offer-card">
                                <div class="offer-image">
                                    <img :src="getImageUrl(offer.image)" :alt="$p(offer, 'title')" loading="lazy">
                                    <div class="image-overlay"></div>
                                    <span class="floating-badge"><i class="fas fa-fire"></i> {{ t('exclusive_offer') || 'عرض حصري' }}</span>
                                    <div v-if="offer.discount_percentage" class="discount-badge">
                                        <span class="discount-label">{{ t('discount') || 'خصم' }}</span>
                                        <span class="discount-val">{{ offer.discount_percentage }}%</span>
                                    </div>
                                </div>
                                <div class="offer-content">
                                    <h3 class="offer-title">{{ $p(offer, 'title') }}</h3>
                                    <p class="offer-desc">{{ $p(offer, 'description') }}</p>
                                    
                                    <div v-if="offer.end_date" class="offer-expiry">
                                        <span class="pulse-indicator"></span>
                                        <i class="far fa-clock"></i>
                                        <span>{{ t('ends_in') || 'ينتهي في' }}: {{ formatDate(offer.end_date) }}</span>
                                    </div>
                                    
                                    <div class="offer-actions">
                                        <router-link v-if="offer.product" :to="'/product/' + offer.product.slug" class="btn-offer-primary">
                                            <i class="fas fa-shopping-bag"></i>
                                            {{ t('view_product') || 'عرض المنتج' }}
                                        </router-link>
                                        <a v-else-if="offer.link" :href="offer.link" class="btn-offer-primary" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                            {{ t('discover_more') || 'اكتشف المزيد' }}
                                        </a>
                                        <router-link v-else to="/categories" class="btn-offer-primary">
                                            <i class="fas fa-th-large"></i>
                                            {{ t('browse_categories') || 'تصفح الفئات' }}
                                        </router-link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="categories fade-up" id="categories">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('main_categories') || 'فئات المنتجات الرئيسية' }}</h2>
                    <p>{{ t('categories_subtitle') || 'تصفح الفئات الأكثر طلباً مع وصف سريع لكل فئة.' }}</p>
                </div>
                
                <div v-if="loading" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--mobile-primary);"></i>
                </div>
                
                <div v-else class="categories-grid">
                    <div v-for="category in categories" 
                         :key="category.id" 
                         class="category-card" 
                         @click="goToCategory(category.slug)">
                        <div v-if="category.image" class="category-image">
                            <img :src="getImageUrl(category.image)" :alt="$p(category, 'name')">
                        </div>
                        <div v-else class="category-icon">
                            <i class="fas" :class="category.icon || 'fa-cube'"></i>
                        </div>
                        <h3>{{ $p(category, 'name') }}</h3>
                        <p>{{ truncateText($p(category, 'description') || t('high_quality_spare_parts'), 50) }}</p>
                        <span class="category-count">{{ category.product_count || 0 }} {{ t('products_count') || 'منتج' }}</span>
                    </div>
                    
                    <div v-if="!categories.length" style="grid-column: 1/-1; text-align:center; padding: 2rem; color:#666;">
                        {{ t('no_categories') || 'لا توجد فئات متاحة حالياً' }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section class="featured-products fade-up" id="featured-products">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('featured_products') || 'منتجات مميزة' }}</h2>
                    <p>{{ t('featured_subtitle') || 'أحدث وأفضل المنتجات المتوفرة في المتجر' }}</p>
                </div>
                
                <div v-if="loading" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--mobile-primary);"></i>
                </div>
                
                <div v-else>
                    <div v-if="featuredProducts.length" class="products-slider-wrapper">
                        <button class="slider-btn prev-btn" aria-label="المنتج السابق" @click="scrollFeatSlider('prev')" :style="{ opacity: featPrevBtnOpacity, pointerEvents: featPrevBtnOpacity === '0.5' ? 'none' : 'auto' }">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="slider-btn next-btn" aria-label="المنتج التالي" @click="scrollFeatSlider('next')" :style="{ opacity: featNextBtnOpacity, pointerEvents: featNextBtnOpacity === '0.5' ? 'none' : 'auto' }">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <div class="products-slider" ref="featProductsSlider" @scroll="updateFeatSliderButtons">
                            <div v-for="product in featuredProducts" :key="product.id" class="product-card-container">
                                <div class="product-card">
                                    <div class="product-image">
                                        <img :src="getImageUrl(product.image_main)" :alt="$p(product, 'name')">
                                        <router-link :to="'/product/' + product.slug" class="product-overlay">
                                            <span class="view-btn"><i class="fas fa-eye"></i></span>
                                        </router-link>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name-container">
                                            <h3 class="product-title">{{ $p(product, 'name') }}</h3>
                                        </div>
                                        <div class="product-category">{{ $p(product.category, 'name') || 'قطع غيار' }}</div>
                                        <div v-if="settings.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0" class="product-price">
                                            ${{ parseFloat(product.price).toFixed(2) }}
                                        </div>
                                        <button class="btn-add-to-cart" @click="handleAddToCart(product)">
                                            <i class="fas fa-cart-plus"></i>
                                            {{ t('add_to_cart') || 'أضف للسلة' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else style="text-align:center; padding: 2rem; color:#666;">
                        {{ t('no_featured') || 'لا توجد منتجات مميزة حالياً' }}
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA fallback Section -->
        <section v-if="!featuredProducts.length && !loading" class="cta-section" style="background: var(--bg-light); padding: 80px 0; text-align: center;">
            <div class="container">
                <div class="cta-content">
                    <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: var(--primary-dark);">
                        {{ t('discover_world') || 'اكتشف عالمنا المتميز' }}
                    </h2>
                    <p style="font-size: 1.2rem; margin-bottom: 2rem; color: #666;">
                        {{ t('discover_desc') || 'نقدم أفضل قطع غيار الهواتف المحمولة بجودة عالمية وأسعار تنافسية' }}
                    </p>
                    <div class="cta-actions">
                        <router-link to="/categories" class="btn btn-primary btn-lg" style="background: var(--mobile-primary); color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 0 10px;">
                            <i class="fas fa-th-large"></i> {{ t('explore_categories') || 'استكشف الفئات' }}
                        </router-link>
                        <router-link to="/contact" class="btn btn-outline-primary btn-lg" style="border: 2px solid var(--mobile-primary); color: var(--mobile-primary); padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 0 10px;">
                            <i class="fas fa-phone"></i> {{ t('contact_us_btn') || 'تواصل معنا' }}
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Notification Toast -->
        <div v-if="toast.show" class="cart-notification success show" style="top: 100px;">
            <i class="fas fa-check-circle"></i>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const settingsStore = useSettingsStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();

// Router
const router = useRouter();

// State
const categories = ref([]);
const featuredProducts = ref([]);
const specialOffers = ref([]);
const loading = ref(true);

// Secondary Navbar
const openDropdowns = ref({});

// Slider Buttons Opacity
const prevBtnOpacity = ref('0.5');
const nextBtnOpacity = ref('1');
const offersSlider = ref(null);

// Featured Products Slider Refs
const featPrevBtnOpacity = ref('0.5');
const featNextBtnOpacity = ref('1');
const featProductsSlider = ref(null);

// Toast Notification
const toast = reactive({ show: false, message: '' });

// Settings getter
const settings = computed(() => settingsStore.data);

const secondaryNavItems = computed(() => {
    const raw = settings.value.secondary_navbar_items;
    if (raw) {
        try {
            return JSON.parse(raw);
        } catch (e) {
            return getDefaultNavItems();
        }
    }
    return getDefaultNavItems();
});

// SEO Meta Tags
const updateSEOMetaTags = () => {
    const siteName = settings.value[`site_name_${locale.value}`] || settings.value.site_name || 'أوان التقدم';
    const siteDescription = settings.value[`site_description_${locale.value}`] || settings.value.site_description || 'أفضل قطع الغيار الأصلية بجودة عالية وأسعار منافسة، لجميع موديلات الهواتف الذكية';
    const ogImage = settings.value.og_image ? getImageUrl(settings.value.og_image) : '/assets/images/logo.png';
    
    // Update document title
    document.title = siteName + ' - الصفحة الرئيسية';
    
    // Update meta description
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription) {
        metaDescription.setAttribute('content', siteDescription);
    }
    
    // Update og:title
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', siteName + ' - الصفحة الرئيسية');
    }
    
    // Update og:description
    const ogDescription = document.querySelector('meta[property="og:description"]');
    if (ogDescription) {
        ogDescription.setAttribute('content', siteDescription);
    }
    
    // Update og:image
    const ogImageMeta = document.querySelector('meta[property="og:image"]');
    if (ogImageMeta) {
        ogImageMeta.setAttribute('content', ogImage);
    }
    
    // Update twitter:title
    const twitterTitle = document.querySelector('meta[property="twitter:title"]');
    if (twitterTitle) {
        twitterTitle.setAttribute('content', siteName + ' - الصفحة الرئيسية');
    }
    
    // Update twitter:description
    const twitterDescription = document.querySelector('meta[property="twitter:description"]');
    if (twitterDescription) {
        twitterDescription.setAttribute('content', siteDescription);
    }
    
    // Update twitter:image
    const twitterImage = document.querySelector('meta[property="twitter:image"]');
    if (twitterImage) {
        twitterImage.setAttribute('content', ogImage);
    }
};

const heroBgStyle = computed(() => {
    const bg = settings.value.hero_bg;
    if (bg) {
        return {
            '--hero-bg': `url('${getImageUrl(bg)}')`
        };
    }
    return {};
});

// Helpers

const truncateText = (text, len) => {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '...' : text;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SY', { day: 'numeric', month: 'short', year: 'numeric' });
};

// Route redirection helper
const goToCategory = (slug) => {
    router.push(`/category/${slug}`);
};

// Add to cart click
const handleAddToCart = async (product) => {
    try {
        await cartStore.addToCart(product.id, 1);
        showToast(`تم إضافة "${product.name_ar}" إلى السلة`);
    } catch (e) {
        showToast('حدث خطأ أثناء إضافة المنتج');
    }
};

const showToast = (msg) => {
    toast.message = msg;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// Secondary Navbar Helpers
const openSecDropdown = (id) => { openDropdowns.value[id] = true; };
const closeSecDropdown = (id) => { openDropdowns.value[id] = false; };
const toggleSecDropdown = (id) => { openDropdowns.value[id] = !openDropdowns.value[id]; };
const getLabel = (item) => {
    if (locale.value === 'en' && item.label_en) return item.label_en;
    return item.label_ar || item.label_en || '';
};
const getActiveChildren = (item) => {
    return (item.children || []).filter(c => c.active);
};

const getDefaultNavItems = () => [
    { id: 'products', type: 'dropdown', active: true, label_ar: 'المنتجات', label_en: 'Products', icon: 'fas fa-th-list', children: [
        { id: 'all_products', active: true, label_ar: 'جميع المنتجات', label_en: 'All Products', icon: 'fas fa-th-large', route: '/products' },
    ]},
    { id: 'featured', type: 'dropdown', active: true, label_ar: 'منتجات مميزة', label_en: 'Featured Products', icon: 'fas fa-star', children: [
        { id: 'view_all_featured', active: true, label_ar: 'عرض جميع المنتجات المميزة', label_en: 'View All Featured', icon: 'fas fa-fire', route: '/featured-products' },
    ]},
    { id: 'offers', type: 'dropdown', active: true, label_ar: 'العروض المميزة', label_en: 'Special Offers', icon: 'fas fa-tag', children: [
        { id: 'current_offers', active: true, label_ar: 'العروض الحالية', label_en: 'Current Offers', icon: 'fas fa-fire', route: '/special-offers' },
    ]},
    { id: 'categories', type: 'link', active: true, label_ar: 'الفئات', label_en: 'Categories', icon: 'fas fa-folder', route: '/categories' },
    { id: 'contact', type: 'link', active: true, label_ar: 'تواصل معنا', label_en: 'Contact Us', icon: 'fas fa-headset', route: '/contact' },
];

// Slider Scrolling
const scrollSlider = (dir) => {
    if (!offersSlider.value) return;
    const card = offersSlider.value.querySelector('.offer-card-container');
    if (!card) return;
    
    const scrollAmount = card.offsetWidth + 24;
    offersSlider.value.scrollBy({
        left: dir === 'prev' ? scrollAmount : -scrollAmount,
        behavior: 'smooth'
    });
};

const updateSliderButtons = () => {
    if (!offersSlider.value) return;
    const slider = offersSlider.value;
    const maxScroll = slider.scrollWidth - slider.clientWidth;
    const scrollPos = Math.abs(slider.scrollLeft);
    
    prevBtnOpacity.value = scrollPos <= 5 ? '0.5' : '1';
    nextBtnOpacity.value = scrollPos >= maxScroll - 5 ? '0.5' : '1';
};

// Featured Products Slider Scrolling
const scrollFeatSlider = (dir) => {
    if (!featProductsSlider.value) return;
    const card = featProductsSlider.value.querySelector('.product-card-container');
    if (!card) return;
    
    const scrollAmount = card.offsetWidth + 24;
    featProductsSlider.value.scrollBy({
        left: dir === 'prev' ? scrollAmount : -scrollAmount,
        behavior: 'smooth'
    });
};

const updateFeatSliderButtons = () => {
    if (!featProductsSlider.value) return;
    const slider = featProductsSlider.value;
    const maxScroll = slider.scrollWidth - slider.clientWidth;
    const scrollLeftVal = Math.abs(slider.scrollLeft);
    
    featPrevBtnOpacity.value = scrollLeftVal <= 5 ? '0.5' : '1';
    featNextBtnOpacity.value = scrollLeftVal >= maxScroll - 5 ? '0.5' : '1';
};

onMounted(async () => {
    console.log('Home.vue onMounted started');
    loading.value = true;
    try {
        console.log('Fetching Home.vue API data...');
        console.log('Sending request to /api/v1/home...');
        const homeResPromise = axios.get('/api/v1/home').then(res => {
            console.log('/api/v1/home resolved:', res.status, res.data);
            return res;
        }).catch(err => {
            console.error('/api/v1/home rejected:', err);
            throw err;
        });

        console.log('Sending request to /api/v1/special-offers...');
        const offersResPromise = axios.get('/api/v1/special-offers').then(res => {
            console.log('/api/v1/special-offers resolved:', res.status, res.data);
            return res;
        }).catch(err => {
            console.error('/api/v1/special-offers rejected:', err);
            throw err;
        });

        const [homeRes, offersRes] = await Promise.all([homeResPromise, offersResPromise]);
        
        if (homeRes.data?.success) {
            categories.value = homeRes.data.data.categories || [];
            featuredProducts.value = homeRes.data.data.featured_products || [];
        }
        
        if (offersRes.data?.success) {
            specialOffers.value = offersRes.data.data || [];
        }
    } catch (e) {
        console.error('Failed to load home data', e);
    } finally {
        loading.value = false;
        // Check slider state after layout settles
        setTimeout(updateSliderButtons, 300);
        triggerFadeUp();
        // Update SEO meta tags
        updateSEOMetaTags();
    }
});
</script>

<style scoped>
.home-page-view {
    padding-bottom: 2rem;
}

/* ===== HERO STYLE OVERRIDES ===== */
.hero {
    position: relative;
    min-height: 520px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin-top: 0;
    padding: 70px 20px 70px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(96, 96, 96, 0.9)), var(--hero-bg, url('/assets/images/hero-bg.jpg')) center/cover no-repeat;
    color: #1f2937;
    overflow: hidden;
    box-sizing: border-box;
}

[data-theme="dark"] .hero {
    background: linear-gradient(135deg, rgba(13, 27, 42, 0.9), rgba(22, 42, 69, 0.95)), var(--hero-bg, url('/assets/images/hero-bg.jpg')) center/cover no-repeat !important;
    color: #ffffff;
}

.hero-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 35px;
    flex-wrap: wrap;
    position: relative;
    z-index: 5;
}

.btn-hero-primary,
.btn-hero-secondary {
    padding: 16px 38px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    letter-spacing: 0.3px;
}

.btn-hero-primary::before,
.btn-hero-secondary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.6s ease;
}

.btn-hero-primary:hover::before,
.btn-hero-secondary:hover::before {
    left: 100%;
}

/* .btn-hero-primary styles are now managed via dynamic settings in PublicLayout.vue */

/* .btn-hero-primary:hover styles are now managed via dynamic settings in PublicLayout.vue */

.btn-hero-primary:active {
    transform: translateY(-1px) scale(0.98);
    box-shadow: 0 5px 15px rgba(90, 107, 122, 0.3);
}

.btn-hero-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #1f2937;
    border: 2px solid rgba(31, 41, 55, 0.4);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .btn-hero-secondary {
    color: white;
    border-color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #111827;
    border-color: #111827;
    transform: translateY(-4px) scale(1.03);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

[data-theme="dark"] .btn-hero-secondary:hover {
    color: white;
    border-color: white;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
}

.btn-hero-secondary:active {
    transform: translateY(-1px) scale(0.98);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.btn-hero-primary i,
.btn-hero-secondary i {
    transition: transform 0.4s ease;
    font-size: 1.15rem;
}

.btn-hero-primary:hover i,
.btn-hero-secondary:hover i {
    transform: translateX(-3px) scale(1.1);
}

/* ===== SPECIAL OFFERS ===== */
.special-offers {
    padding: 80px 0;
    background: linear-gradient(180deg, var(--bg-light, #f8fafc) 0%, rgba(255,255,255,0) 100%);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .special-offers {
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.4) 0%, rgba(15, 23, 42, 0) 100%);
}

.offers-slider-wrapper {
    position: relative;
    margin-top: 40px;
    padding: 0 15px;
}

.offers-slider {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    gap: 24px;
    padding: 15px 5px;
}

/* Hide scrollbar */
.offers-slider::-webkit-scrollbar {
    display: none;
}
.offers-slider {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.offer-card-container {
    flex: 0 0 100%;
    scroll-snap-align: start;
    display: flex;
}

@media (min-width: 768px) {
    .offer-card-container {
        flex: 0 0 calc(50% - 12px);
    }
}

@media (min-width: 1024px) {
    .offer-card-container {
        flex: 0 0 calc(33.333% - 16px);
    }
}

.offer-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    width: 100%;
    position: relative;
}

[data-theme="dark"] .offer-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.8) 100%);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-color: rgba(255, 255, 255, 0.06);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
}

.offer-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .offer-card:hover {
    border-color: var(--mobile-primary-light, var(--mobile-primary));
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
}

.offer-image {
    position: relative;
    padding-top: 56.25%; /* 16:9 Aspect Ratio */
    overflow: hidden;
    background: #f5f5f5;
}

[data-theme="dark"] .offer-image {
    background: #1e293b;
}

.offer-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.offer-card:hover .offer-image img {
    transform: scale(1.08);
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, rgba(0,0,0,0) 60%, rgba(0,0,0,0.3) 100%);
    z-index: 1;
    pointer-events: none;
}

.floating-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #f3f4f6;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.floating-badge i {
    color: #fbbf24;
}

.discount-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #ef4444 0%, #f59e0b 100%);
    color: white;
    padding: 8px 14px;
    border-radius: 18px;
    font-weight: 800;
    font-size: 0.9rem;
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.2;
    z-index: 2;
    border: 1.5px solid rgba(255, 255, 255, 0.25);
}

.discount-badge .discount-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.95;
}

.discount-badge .discount-val {
    font-size: 1.1rem;
}

.offer-content {
    padding: 26px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    text-align: right;
}

.offer-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
    line-height: 1.4;
    transition: color 0.3s;
}

[data-theme="dark"] .offer-title {
    color: #f8fafc;
}

.offer-card:hover .offer-title {
    color: var(--mobile-primary);
}

[data-theme="dark"] .offer-card:hover .offer-title {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

.offer-desc {
    font-size: 0.95rem;
    color: #64748b;
    line-height: 1.65;
    margin-bottom: 24px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-grow: 1;
}

[data-theme="dark"] .offer-desc {
    color: #94a3b8;
}

.offer-expiry {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border-right: 4px solid var(--mobile-primary);
    color: #475569;
    padding: 10px 14px;
    border-radius: 0 12px 12px 0;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 24px;
    position: relative;
}

[data-theme="dark"] .offer-expiry {
    background: rgba(255, 255, 255, 0.03);
    border-right-color: var(--mobile-primary-light, var(--mobile-primary));
    color: #cbd5e1;
}

.offer-expiry i {
    color: var(--mobile-primary);
    font-size: 0.95rem;
}

[data-theme="dark"] .offer-expiry i {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

.pulse-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
    position: absolute;
    left: 14px;
    top: calc(50% - 4px);
}

.pulse-indicator::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #ef4444;
    animation: pulse-ring 1.5s cubic-bezier(0.215, 0.610, 0.355, 1) infinite;
    top: 0;
    left: 0;
}

@keyframes pulse-ring {
    0% { transform: scale(0.5); opacity: 1; }
    100% { transform: scale(2.2); opacity: 0; }
}

.btn-offer-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--mobile-primary, var(--mobile-primary)) 0%, var(--mobile-primary-dark, var(--mobile-primary-dark)) 100%);
    color: white;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px color-mix(in srgb, var(--mobile-primary) 15%, transparent);
    position: relative;
    overflow: hidden;
}

.btn-offer-primary::after {
    content: '';
    position: absolute;
    top: 0;
    left: -150%;
    width: 80%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
    transform: skewX(-20deg);
    transition: left 0.75s ease;
}

.btn-offer-primary:hover::after {
    left: 150%;
}

.btn-offer-primary:hover {
    background: linear-gradient(135deg, var(--mobile-primary, var(--mobile-primary)) 0%, var(--mobile-primary-dark, var(--mobile-primary-dark)) 100%);
    box-shadow: 0 8px 25px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
    transform: translateY(-3px);
    color: white;
}

.btn-offer-primary:active {
    transform: translateY(0);
}

.btn-offer-primary i {
    font-size: 1.05rem;
    transition: transform 0.3s ease;
}

.btn-offer-primary:hover i {
    transform: translateX(-5px) scale(1.1);
}

/* Slider Nav Buttons */
.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: white;
    border: 1px solid rgba(0,0,0,0.08);
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .slider-btn {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.slider-btn:hover {
    background: var(--mobile-primary);
    color: white;
    box-shadow: 0 8px 25px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .slider-btn:hover {
    background: var(--mobile-primary-light, var(--mobile-primary));
    color: #0f172a;
    border-color: var(--mobile-primary-light, var(--mobile-primary));
}

.prev-btn {
    right: -23px;
}

.next-btn {
    left: -23px;
}

@media (max-width: 768px) {
    .slider-btn {
        display: none; /* Swipe is preferred on mobile */
    }
    .offers-slider-wrapper {
        padding: 0;
    }
    .hero {
        padding: 40px 15px 40px !important;
        min-height: 420px !important;
    }
}

/* Redesign Overrides for Home Page */
/* Dynamic hero button styling is now applied via PublicLayout.vue; removed static override */

/* ===== SECONDARY NAVBAR ===== */
.secondary-navbar {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--mobile-primary) 96%, rgba(20, 32, 48, 0.92)), 
        color-mix(in srgb, var(--mobile-primary-dark) 98%, rgba(13, 27, 42, 0.95))
    );
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: sticky;
    top: 80px;
    z-index: 999;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.secondary-nav-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    padding: 1rem 0;
    flex-wrap: wrap;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    padding: 0.6rem 1.2rem;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
}

.nav-item:hover,
.nav-item.router-link-active {
    background: rgba(255, 255, 255, 0.12);
    color: var(--mobile-accent, var(--accent-gold, #c9a959));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.nav-item i {
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.nav-item:hover i {
    transform: scale(1.15);
}

.nav-trigger {
    background: none;
    border: none;
    color: inherit;
    font-family: inherit;
    font-size: inherit;
    font-weight: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0;
}

.dropdown {
    position: relative;
}
.dropdown::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    height: 28px;
    pointer-events: auto;
    z-index: 1;
}

.dropdown-arrow {
    font-size: 0.65rem;
    transition: transform 0.3s ease;
}

.dropdown-arrow.rotated {
    transform: rotate(180deg);
}

.dropdown:hover .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 50%;
    transform: translateX(50%) translateY(-8px) scale(0.96);
    min-width: 220px;
    background: rgba(15, 23, 42, 0.97);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.04);
    z-index: 1000;
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

.dropdown:hover .dropdown-menu {
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

@media (max-width: 768px) {
    .secondary-navbar {
        display: none;
    }
}

.category-card, .product-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.category-card:hover, .product-card:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .category-card, 
[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

[data-theme="dark"] .category-card:hover, 
[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.product-image {
    overflow: hidden;
    position: relative;
    border-radius: 16px;
}

.product-image img {
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover .product-image img {
    transform: scale(1.06);
}

/* Featured Products Slider */
.products-slider-wrapper {
    position: relative;
    margin-top: 40px;
    padding: 0 15px;
}

.products-slider {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    gap: 24px;
    padding: 15px 5px;
    scrollbar-width: none; /* Hide scrollbar Firefox */
}

.products-slider::-webkit-scrollbar {
    display: none; /* Hide scrollbar Chrome/Safari/Opera */
}

.product-card-container {
    flex: 0 0 285px;
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
}

@media (max-width: 640px) {
    .product-card-container {
        flex: 0 0 240px;
    }
    .products-slider-wrapper {
        padding: 0;
    }
}
</style>
