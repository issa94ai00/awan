<template>
    <div class="home-page-view">
        <!-- Hero Section -->
        <section class="hero" id="home" :style="heroBgStyle">
            <div class="hero-content">
                <h1>{{ heroHeading }}</h1>
                <p>{{ heroTagline }}</p>
                <div class="hero-buttons">
                    <router-link to="/categories" class="btn-hero-primary">
                        <i class="fas fa-th-large" aria-hidden="true"></i>
                        {{ t('browse_products') || 'تصفح المنتجات' }}
                    </router-link>
                    <router-link to="/contact" class="btn-hero-secondary">
                        <i class="fas fa-headset" aria-hidden="true"></i>
                        {{ t('contact_us_btn') || 'تواصل معنا' }}
                    </router-link>
                </div>
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
                    <router-link v-for="category in categories"
                         :key="category.id"
                         :to="`/category/${category.slug}`"
                         class="category-card">
                        <div v-if="category.image" class="category-image">
                            <img :src="getImageUrl(category.image)" :alt="$p(category, 'name')" loading="lazy" decoding="async" width="400" height="300">
                        </div>
                        <div v-else class="category-icon">
                            <i class="fas" :class="category.icon || 'fa-cube'" aria-hidden="true"></i>
                        </div>
                        <h3>{{ $p(category, 'name') }}</h3>
                        <p>{{ truncateText($p(category, 'description') || t('high_quality_materials'), 50) }}</p>
                        <span class="category-count">{{ category.product_count || 0 }} {{ t('products_count') || 'منتج' }}</span>
                    </router-link>

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
                                        <img :src="getImageUrl(product.image_main)" :alt="$p(product, 'name')" loading="lazy" decoding="async" width="285" height="285">
                                        <router-link :to="'/product/' + product.slug" class="product-overlay" :aria-label="$p(product, 'name')">
                                            <span class="view-btn"><i class="fas fa-eye" aria-hidden="true"></i></span>
                                        </router-link>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name-container">
                                            <h3 class="product-title">
                                                <router-link :to="'/product/' + product.slug" class="product-title-link">{{ $p(product, 'name') }}</router-link>
                                            </h3>
                                        </div>
                                        <div class="product-category">{{ $p(product.category, 'name') || t('building_materials') }}</div>
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
                        {{ t('discover_desc') || 'نقدم أفضل مستلزمات البناء بجودة عالمية وأسعار تنافسية' }}
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

// Hero copy. Falls back to the company's own line of business — the previous
// fallbacks were leftovers from a phone-spare-parts template.
const heroHeading = computed(() => {
    const name = locale.value === 'en'
        ? (settings.value.site_name_en || settings.value.site_name)
        : (settings.value.site_name || settings.value.site_name_en);
    return name || (locale.value === 'en' ? 'Awaan Altakadom' : 'أوان التقدم');
});

const heroTagline = computed(() => {
    const tagline = locale.value === 'en'
        ? (settings.value.site_tagline_en || settings.value.site_tagline)
        : (settings.value.site_tagline || settings.value.site_tagline_en);
    if (tagline) return tagline;
    return locale.value === 'en'
        ? 'Building materials, sanitary ware and installation systems that combine global quality with modern design.'
        : 'مستلزمات البناء والأدوات الصحية وأنظمة التثبيت التي تجمع بين الجودة العالمية والتصميم العصري.';
});

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

// Page-level SEO is owned by PublicLayout's updateSEOMetaTags(), which already
// resolves meta_title / meta_description per locale. This view previously ran its
// own copy that appended a hard-coded Arabic " - الصفحة الرئيسية" suffix on top of
// it (in both locales) after the API calls resolved, clobbering the server-rendered
// title. Emitting the ItemList JSON-LD is all this page needs to add.

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

// Add to cart click
const handleAddToCart = async (product) => {
    const name = (locale.value === 'en' ? product.name_en : product.name_ar) || product.name_ar || product.name_en || '';
    try {
        await cartStore.addToCart(product.id, 1);
        showToast(locale.value === 'en' ? `"${name}" was added to the cart` : `تم إضافة "${name}" إلى السلة`);
    } catch (e) {
        showToast(locale.value === 'en' ? 'Something went wrong while adding the product' : 'حدث خطأ أثناء إضافة المنتج');
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

// ===== Structured data =====
// Exposes the featured-products grid as an ItemList so crawlers can read the
// listing that is otherwise only rendered client-side.
const JSON_LD_ID = 'home-itemlist-jsonld';

const syncItemListJsonLd = () => {
    document.getElementById(JSON_LD_ID)?.remove();

    if (!featuredProducts.value.length) return;

    const origin = window.location.origin;
    const payload = {
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: t('featured_products') || 'منتجات مميزة',
        itemListElement: featuredProducts.value.map((product, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            url: `${origin}/product/${product.slug}`,
            name: (locale.value === 'en' ? product.name_en : product.name_ar) || product.name_ar || product.name_en || '',
        })),
    };

    const script = document.createElement('script');
    script.id = JSON_LD_ID;
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(payload);
    document.head.appendChild(script);
};

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
    loading.value = true;
    try {
        const [homeRes, offersRes] = await Promise.all([
            axios.get('/api/v1/home'),
            axios.get('/api/v1/special-offers')
        ]);

        if (homeRes.data?.success) {
            categories.value = homeRes.data.data.categories || [];
            featuredProducts.value = homeRes.data.data.featured_products || [];
        }

        if (offersRes.data?.success) {
            specialOffers.value = offersRes.data.data || [];
        }
    } catch (e) {
        // Home page renders gracefully with empty sections if these requests fail.
    } finally {
        loading.value = false;
        // Check slider state after layout settles
        setTimeout(updateSliderButtons, 300);
        triggerFadeUp();
        syncItemListJsonLd();
    }
});

onUnmounted(() => {
    document.getElementById(JSON_LD_ID)?.remove();
});
</script>

<style scoped>
.home-page-view {
    padding-bottom: 2rem;
}

/* ===== HERO — modern minimal ===== */
.hero {
    position: relative;
    min-height: 460px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin-top: 0;
    padding: 96px 24px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.82) 55%, rgba(255, 255, 255, 0.95) 100%), var(--hero-bg, linear-gradient(160deg, #f8fafc, #eef2f7)) center/cover no-repeat;
    color: #0f172a;
    box-sizing: border-box;
}

[data-theme="dark"] .hero {
    background: linear-gradient(180deg, rgba(9, 15, 26, 0.94) 0%, rgba(9, 15, 26, 0.8) 55%, rgba(9, 15, 26, 0.94) 100%), var(--hero-bg, linear-gradient(160deg, #0f172a, #111827)) center/cover no-repeat !important;
    color: #f8fafc;
}

.hero-content {
    max-width: 720px;
    position: relative;
    z-index: 2;
}

.hero h1 {
    font-size: clamp(2.1rem, 4.5vw, 3.1rem);
    font-weight: 800;
    letter-spacing: -0.02em;
    margin: 0 0 1.1rem;
    line-height: 1.2;
}

.hero p {
    font-size: 1.1rem;
    line-height: 1.75;
    color: #475569;
    max-width: 560px;
    margin: 0 auto;
}

[data-theme="dark"] .hero p {
    color: #94a3b8;
}

.hero-buttons {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 2.5rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 5;
}

.btn-hero-primary,
.btn-hero-secondary {
    padding: 14px 32px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease, border-color 0.25s ease;
    letter-spacing: 0.1px;
}

.btn-hero-primary:active,
.btn-hero-secondary:active {
    transform: translateY(0) scale(0.98);
}

.btn-hero-secondary {
    background: transparent;
    color: #0f172a;
    border: 1.5px solid rgba(15, 23, 42, 0.18);
}

[data-theme="dark"] .btn-hero-secondary {
    color: #f8fafc;
    border-color: rgba(255, 255, 255, 0.22);
}

.btn-hero-secondary:hover {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
    transform: translateY(-2px);
}

[data-theme="dark"] .btn-hero-secondary:hover {
    color: var(--mobile-primary-light, var(--mobile-primary));
    border-color: var(--mobile-primary-light, var(--mobile-primary));
}

.btn-hero-primary i,
.btn-hero-secondary i {
    font-size: 1rem;
}

/* ===== SPECIAL OFFERS ===== */
.special-offers {
    padding: 72px 0;
    position: relative;
}

.offers-slider-wrapper {
    position: relative;
    margin-top: 36px;
    padding: 0 15px;
}

.offers-slider {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    gap: 20px;
    padding: 10px 5px;
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
        flex: 0 0 calc(50% - 10px);
    }
}

@media (min-width: 1024px) {
    .offer-card-container {
        flex: 0 0 calc(33.333% - 14px);
    }
}

.offer-card {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.07);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    display: flex;
    flex-direction: column;
    width: 100%;
    position: relative;
}

[data-theme="dark"] .offer-card {
    background: #131c2b;
    border-color: rgba(255, 255, 255, 0.06);
}

.offer-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    border-color: color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

[data-theme="dark"] .offer-card:hover {
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
    border-color: color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

.offer-image {
    position: relative;
    padding-top: 56.25%; /* 16:9 Aspect Ratio */
    overflow: hidden;
    background: #f1f5f9;
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
    transition: transform 0.5s ease;
}

.offer-card:hover .offer-image img {
    transform: scale(1.04);
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, rgba(0,0,0,0) 65%, rgba(0,0,0,0.22) 100%);
    z-index: 1;
    pointer-events: none;
}

.floating-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    background: rgba(15, 23, 42, 0.68);
    color: #f8fafc;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 6px;
}

.floating-badge i {
    color: var(--mobile-accent, #f59e0b);
}

.discount-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    background: var(--mobile-primary);
    color: white;
    padding: 6px 12px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.2;
    z-index: 2;
}

.discount-badge .discount-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
}

.discount-badge .discount-val {
    font-size: 1.05rem;
}

.offer-content {
    padding: 22px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    text-align: right;
}

.offer-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
    line-height: 1.4;
}

[data-theme="dark"] .offer-title {
    color: #f1f5f9;
}

.offer-desc {
    font-size: 0.92rem;
    color: #64748b;
    line-height: 1.65;
    margin-bottom: 20px;
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
    color: #475569;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.83rem;
    font-weight: 600;
    margin-bottom: 20px;
    position: relative;
}

[data-theme="dark"] .offer-expiry {
    background: rgba(255, 255, 255, 0.04);
    color: #cbd5e1;
}

.offer-expiry i {
    color: var(--mobile-primary);
    font-size: 0.9rem;
}

.pulse-indicator {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ef4444;
    position: relative;
    flex-shrink: 0;
}

.pulse-indicator::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #ef4444;
    animation: pulse-ring 1.6s cubic-bezier(0.215, 0.610, 0.355, 1) infinite;
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
    padding: 12px 20px;
    background: var(--mobile-primary);
    color: white;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.92rem;
    transition: background-color 0.25s ease, transform 0.25s ease;
}

.btn-offer-primary:hover {
    background: var(--mobile-primary-dark, var(--mobile-primary));
    transform: translateY(-2px);
    color: white;
}

.btn-offer-primary i {
    font-size: 1rem;
}

/* Slider Nav Buttons */
.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    cursor: pointer;
    z-index: 10;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

[data-theme="dark"] .slider-btn {
    background: #131c2b;
    border-color: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

.slider-btn:hover {
    background: var(--mobile-primary);
    color: white;
    border-color: var(--mobile-primary);
}

.prev-btn {
    right: -20px;
}

.next-btn {
    left: -20px;
}

@media (max-width: 768px) {
    .slider-btn {
        display: none; /* Swipe is preferred on mobile */
    }
    .offers-slider-wrapper {
        padding: 0;
    }
    .hero {
        padding: 56px 18px !important;
        min-height: 380px !important;
    }
}

/* ===== SECONDARY NAVBAR — minimal underline nav ===== */
.secondary-navbar {
    background: #ffffff;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    position: sticky;
    top: 80px;
    z-index: 999;
}

[data-theme="dark"] .secondary-navbar {
    background: #0f172a;
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.secondary-nav-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 0;
    flex-wrap: wrap;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #475569;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    padding: 0.6rem 1.1rem;
    border-radius: 10px;
    transition: background-color 0.2s ease, color 0.2s ease;
    cursor: pointer;
    position: relative;
}

[data-theme="dark"] .nav-item {
    color: #cbd5e1;
}

.nav-item:hover,
.nav-item.router-link-active {
    background: color-mix(in srgb, var(--mobile-primary) 8%, transparent);
    color: var(--mobile-primary);
}

.nav-item i {
    font-size: 0.95rem;
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
    height: 20px;
    pointer-events: auto;
    z-index: 1;
}

.dropdown-arrow {
    font-size: 0.6rem;
    transition: transform 0.2s ease;
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
    transform: translateX(50%) translateY(4px);
    min-width: 210px;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    z-index: 1000;
    padding: 0.4rem;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
}

[data-theme="dark"] .dropdown-menu {
    background: #131c2b;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateX(50%) translateY(8px);
    pointer-events: auto;
}

.dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateX(50%) translateY(8px);
    pointer-events: auto;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    color: #475569;
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 500;
    border-radius: 8px;
    transition: background-color 0.2s ease, color 0.2s ease;
    white-space: nowrap;
}

[data-theme="dark"] .dropdown-item {
    color: #cbd5e1;
}

.dropdown-item i {
    color: var(--mobile-primary);
    width: 18px;
    font-size: 0.85rem;
    text-align: center;
}

.dropdown-item:hover {
    background: color-mix(in srgb, var(--mobile-primary) 8%, transparent);
    color: var(--mobile-primary);
}

.dropdown-item + .dropdown-item {
    margin-top: 2px;
}

@media (max-width: 768px) {
    .secondary-navbar {
        display: none;
    }
}

/* .category-card is an <a> now (crawlable + keyboard reachable); keep it looking
   like the original card and not like body copy. */
a.category-card {
    /* Mirrors the global .category-card layout: this scoped selector is more
       specific, so it must not drop the flex column the cards are built on. */
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

a.category-card:focus-visible,
.product-title-link:focus-visible,
.product-overlay:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 3px;
}

.product-title-link {
    color: inherit;
    text-decoration: none;
}

.product-title-link:hover {
    color: var(--mobile-primary);
}

/* ===== CARDS — modern minimal surfaces ===== */
.category-card, .product-card {
    background: #ffffff !important;
    border: 1px solid rgba(15, 23, 42, 0.07) !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03) !important;
    border-radius: 18px !important;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease !important;
}

.category-card:hover, .product-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 30%, transparent) !important;
}

[data-theme="dark"] .category-card,
[data-theme="dark"] .product-card {
    background: #131c2b !important;
    border-color: rgba(255, 255, 255, 0.06) !important;
    box-shadow: none !important;
}

[data-theme="dark"] .category-card:hover,
[data-theme="dark"] .product-card:hover {
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 35%, transparent) !important;
}

.product-image {
    overflow: hidden;
    position: relative;
    border-radius: 12px;
}

.product-image img {
    transition: transform 0.5s ease !important;
}

.product-card:hover .product-image img {
    transform: scale(1.03);
}

/* Featured Products Slider */
.products-slider-wrapper {
    position: relative;
    margin-top: 36px;
    padding: 0 15px;
}

.products-slider {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    gap: 20px;
    padding: 10px 5px;
    scrollbar-width: none; /* Hide scrollbar Firefox */
}

.products-slider::-webkit-scrollbar {
    display: none; /* Hide scrollbar Chrome/Safari/Opera */
}

.product-card-container {
    flex: 0 0 270px;
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
}

@media (max-width: 640px) {
    .product-card-container {
        flex: 0 0 230px;
    }
    .products-slider-wrapper {
        padding: 0;
    }
}
</style>
