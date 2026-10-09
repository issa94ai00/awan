<template>
    <div class="home-page-view">
        <!-- Hero Section -->
        <section class="hero" id="home" :style="heroBgStyle">
            <div class="hero-content">
                <!-- The brand, and what it sells: the h1 used to be the shop's
                     name alone, which tells a search engine nothing. -->
                <h1>
                    <span class="hero-brand">{{ heroHeading }}</span>
                    <span class="hero-kicker">{{ t('home_h1_sub') }}</span>
                </h1>
                <p>{{ heroTagline }}</p>

                <!-- A real GET form to /products, so it works before the app
                     has loaded too; the app takes it over once it has. -->
                <form class="hero-search" role="search" action="/products" method="get" @submit.prevent="submitSearch">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input
                        v-model="heroQuery"
                        name="q"
                        type="search"
                        :placeholder="t('prod_search_placeholder')"
                        :aria-label="t('search')"
                        enterkeyhint="search"
                        autocomplete="off"
                    >
                    <button type="submit">{{ t('search') }}</button>
                </form>

                <div class="hero-buttons">
                    <router-link to="/products" class="btn-hero-primary">
                        <i class="fas fa-th-large" aria-hidden="true"></i>
                        {{ t('browse_products') }}
                    </router-link>
                    <router-link to="/contact" class="btn-hero-secondary">
                        <i class="fas fa-headset" aria-hidden="true"></i>
                        {{ t('contact_us_btn') }}
                    </router-link>
                </div>

                <ul v-if="stats.products" class="hero-stats">
                    <li><strong>{{ formatCount(stats.products) }}</strong> {{ t('home_stat_products') }}</li>
                    <li><strong>{{ formatCount(stats.sections) }}</strong> {{ t('home_stat_sections') }}</li>
                    <li v-if="workingHours"><i class="far fa-clock" aria-hidden="true"></i> {{ workingHours }}</li>
                </ul>
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

        <!-- Sections: the top-level ones with products, each with a photo of
             its products (none has an image of its own). -->
        <section class="categories fade-up" id="categories">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('main_categories') }}</h2>
                    <p>{{ t('categories_subtitle') }}</p>
                </div>

                <div v-if="loading" class="home-sections-grid" aria-busy="true">
                    <div v-for="n in 8" :key="n" class="home-section-card is-skeleton">
                        <span class="hs-media"></span>
                        <span class="hs-line"></span>
                    </div>
                </div>

                <div v-else-if="categories.length" class="home-sections-grid">
                    <router-link
                        v-for="category in categories"
                        :key="category.id"
                        :to="`/category/${category.slug}`"
                        class="home-section-card"
                    >
                        <span class="hs-media" :class="{ 'is-drawing': isDrawing(category) }">
                            <img
                                v-if="category.image || category.thumbnail"
                                :src="getImageUrl(category.image || category.thumbnail)"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                width="240"
                                height="150"
                            >
                            <i v-else class="fas" :class="category.icon || 'fa-cube'" aria-hidden="true"></i>
                        </span>
                        <span class="hs-body">
                            <h3>{{ $p(category, 'name') }}</h3>
                            <span class="hs-count">{{ t('catl_products_n', { count: formatCount(category.product_count) }) }}</span>
                        </span>
                    </router-link>
                </div>

                <div v-if="!loading && categories.length" class="section-more">
                    <router-link to="/categories" class="btn-more">
                        {{ t('home_all_sections', { count: formatCount(stats.sections || categories.length) }) }}
                        <i :class="isRtl ? 'fas fa-arrow-left' : 'fas fa-arrow-right'" aria-hidden="true"></i>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Products: the featured ones, or — with none featured — a daily
             pick of what is in stock. The row used to say "no featured
             products" and fall back to a banner. -->
        <section v-if="loading || featuredProducts.length" class="featured-products fade-up" id="featured-products">
            <div class="container">
                <div class="section-header">
                    <h2>{{ productsSource === 'featured' ? t('featured_products') : t('home_picks_title') }}</h2>
                    <p>{{ productsSource === 'featured' ? t('featured_subtitle') : t('home_picks_subtitle') }}</p>
                </div>

                <div class="products-slider-wrapper">
                    <button
                        v-if="!loading"
                        type="button"
                        class="slider-btn prev-btn"
                        :aria-label="t('previous')"
                        :disabled="featAtStart"
                        @click="scrollFeatSlider('prev')"
                    >
                        <i :class="isRtl ? 'fas fa-chevron-right' : 'fas fa-chevron-left'" aria-hidden="true"></i>
                    </button>
                    <button
                        v-if="!loading"
                        type="button"
                        class="slider-btn next-btn"
                        :aria-label="t('next')"
                        :disabled="featAtEnd"
                        @click="scrollFeatSlider('next')"
                    >
                        <i :class="isRtl ? 'fas fa-chevron-left' : 'fas fa-chevron-right'" aria-hidden="true"></i>
                    </button>

                    <div ref="featProductsSlider" class="products-slider" @scroll.passive="updateFeatSliderButtons">
                        <template v-if="loading">
                            <div v-for="n in 4" :key="n" class="product-card-container">
                                <ProductListingCard skeleton />
                            </div>
                        </template>
                        <div v-for="product in featuredProducts" v-else :key="product.listing_key || product.id" class="product-card-container">
                            <ProductListingCard
                                :product="product"
                                @added="showToast(t('catp_added', { name: $event }))"
                                @add-failed="showToast(t('catp_add_failed'), true)"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="!loading" class="section-more">
                    <router-link :to="productsSource === 'featured' ? '/featured-products' : '/products'" class="btn-more">
                        {{ t('home_view_all_products') }}
                        <i :class="isRtl ? 'fas fa-arrow-left' : 'fas fa-arrow-right'" aria-hidden="true"></i>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Help: a trade customer often knows the job, not the part. -->
        <section class="home-help fade-up">
            <div class="container">
                <div class="help-card">
                    <div class="help-text">
                        <h2>{{ t('home_help_title') }}</h2>
                        <p>{{ t('home_help_text') }}</p>
                    </div>
                    <div class="help-actions">
                        <a v-if="whatsappLink" :href="whatsappLink" class="btn-help-whatsapp" target="_blank" rel="noopener">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            {{ t('home_whatsapp') }}
                        </a>
                        <router-link to="/contact" class="btn-help-contact">
                            <i class="fas fa-headset" aria-hidden="true"></i>
                            {{ t('contact_us_btn') }}
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Notification Toast -->
        <div v-if="toast.show" class="cart-notification show" :class="toast.error ? 'error' : 'success'" role="status" style="top: 100px;">
            <i :class="toast.error ? 'fas fa-circle-exclamation' : 'fas fa-check-circle'"></i>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import ProductListingCard from '@/components/public/ProductListingCard.vue';

// Stores
const settingsStore = useSettingsStore();
const router = useRouter();
const { t, locale } = useI18n();

// State
const categories = ref([]);
const featuredProducts = ref([]);
/** 'featured', or 'picks' when nothing is featured (see Api\HomeController). */
const productsSource = ref('featured');
const stats = ref({});
const specialOffers = ref([]);
const loading = ref(true);
const heroQuery = ref('');

const isRtl = computed(() => locale.value === 'ar');
const formatCount = (value) => Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');

/** The generic drawings carry their own slate tile; framed as icons, not photos. */
const isDrawing = (category) => !category.image && /\/images_items\/generic\//.test(category.thumbnail || '');

const submitSearch = () => {
    const q = heroQuery.value.trim();
    router.push({ path: '/products', query: q ? { q } : {} });
};

// Secondary Navbar
const openDropdowns = ref({});

// Slider Buttons Opacity
const prevBtnOpacity = ref('0.5');
const nextBtnOpacity = ref('1');
const offersSlider = ref(null);

// Featured Products Slider
const featAtStart = ref(true);
const featAtEnd = ref(false);
const featProductsSlider = ref(null);

// Toast Notification
const toast = reactive({ show: false, message: '', error: false });

// Settings: the store once its request lands, the values the page shell
// ships in window.systemData until then (and for keys the API leaves out).
const settings = computed(() => ({
    ...(window.systemData?.settings || {}),
    ...(settingsStore.data || {}),
}));

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

// Page-level SEO is owned by the shared useSeo composable (routed through
// PublicLayout), which resolves meta_title / meta_description per locale. This
// view previously ran its own copy that appended a hard-coded Arabic
// " - الصفحة الرئيسية" suffix on top of it (in both locales) after the API calls
// resolved, clobbering the server-rendered title. Emitting the ItemList JSON-LD
// is all this page needs to add.

const heroBgStyle = computed(() => {
    const bg = settings.value.hero_bg;
    if (bg) {
        return {
            '--hero-bg': `url('${getImageUrl(bg)}')`
        };
    }
    return {};
});

const workingHours = computed(() => (locale.value === 'en'
    ? (settings.value.working_hours_en || '')
    : (settings.value.working_hours || '')));

const whatsappLink = computed(() => {
    const digits = String(settings.value.contact_whatsapp || '').replace(/\D/g, '').replace(/^00/, '');
    return digits ? `https://wa.me/${digits}?text=${encodeURIComponent(t('home_whatsapp_text'))}` : '';
});

// Helpers

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SY', { day: 'numeric', month: 'short', year: 'numeric' });
};

let toastTimer = null;
const showToast = (message, error = false) => {
    Object.assign(toast, { message, error, show: true });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.show = false; }, 3000);
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
        name: productsSource.value === 'featured' ? t('featured_products') : t('home_picks_title'),
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

// Featured Products Slider Scrolling. "Next" runs toward the end of the
// reading direction: scrollLeft goes negative in a right-to-left page.
const scrollFeatSlider = (dir) => {
    const slider = featProductsSlider.value;
    const card = slider?.querySelector('.product-card-container');
    if (!card) return;

    const step = card.offsetWidth + 20;
    const forward = dir === 'next' ? 1 : -1;
    slider.scrollBy({ left: forward * (isRtl.value ? -step : step), behavior: 'smooth' });
};

const updateFeatSliderButtons = () => {
    const slider = featProductsSlider.value;
    if (!slider) return;
    const maxScroll = slider.scrollWidth - slider.clientWidth;
    const position = Math.abs(slider.scrollLeft);

    featAtStart.value = position <= 5;
    featAtEnd.value = position >= maxScroll - 5;
};

onMounted(async () => {
    loading.value = true;
    try {
        const [homeRes, offersRes] = await Promise.all([
            axios.get('/api/v1/home'),
            axios.get('/api/v1/special-offers')
        ]);

        if (homeRes.data?.success) {
            const data = homeRes.data.data;
            categories.value = data.categories || [];
            featuredProducts.value = data.featured_products || [];
            productsSource.value = data.products_source || 'featured';
            stats.value = data.stats || {};
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
        nextTick(updateFeatSliderButtons);
        triggerFadeUp();
        syncItemListJsonLd();
    }
});

onUnmounted(() => {
    document.getElementById(JSON_LD_ID)?.remove();
    clearTimeout(toastTimer);
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

/* Logical sides: Previous sits at the start of the reading direction. */
.prev-btn {
    inset-inline-start: -20px;
}

.next-btn {
    inset-inline-end: -20px;
}

.slider-btn:disabled {
    opacity: 0.4;
    cursor: default;
    pointer-events: none;
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
/* ===== HERO additions: keyword line, search, stats ===== */
.hero h1 {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.hero-kicker {
    font-size: clamp(1rem, 2vw, 1.25rem);
    font-weight: 700;
    letter-spacing: 0;
    color: var(--mobile-primary);
}

.hero-search {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 560px;
    margin: 2rem auto 0;
    padding: 6px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.1);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
}

.hero-search:focus-within {
    border-color: var(--mobile-primary);
    box-shadow: 0 12px 32px color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

.hero-search > i {
    position: absolute;
    inset-inline-start: 20px;
    color: #94a3b8;
    pointer-events: none;
}

.hero-search input {
    flex: 1;
    min-width: 0;
    height: 48px;
    padding-inline: 42px 10px;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 1rem;
    color: #0f172a;
    outline: none;
}

.hero-search button {
    flex: none;
    height: 48px;
    padding: 0 24px;
    border: none;
    border-radius: 12px;
    background: var(--mobile-primary);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.hero-search button:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

[data-theme="dark"] .hero-search {
    background: #0f172a;
    border-color: rgba(255, 255, 255, 0.12);
}

[data-theme="dark"] .hero-search input {
    color: #f1f5f9;
}

.hero-search + .hero-buttons {
    margin-top: 1.25rem;
}

.hero-stats {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px 22px;
    margin: 1.75rem 0 0;
    padding: 0;
    list-style: none;
    font-size: 0.92rem;
    color: #64748b;
}

.hero-stats li {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.hero-stats strong {
    color: #0f172a;
    font-size: 1.05rem;
    font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .hero-stats {
    color: #94a3b8;
}

[data-theme="dark"] .hero-stats strong {
    color: #f1f5f9;
}

/* ===== Sections grid ===== */
.home-sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr));
    gap: 18px;
    margin-top: 32px;
}

.home-section-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(15, 23, 42, 0.06);
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
    color: inherit;
    text-decoration: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.home-section-card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--mobile-primary) 30%, transparent);
    box-shadow: 0 16px 34px color-mix(in srgb, var(--mobile-primary) 10%, transparent);
}

.home-section-card:focus-visible {
    outline: 3px solid var(--mobile-primary);
    outline-offset: 2px;
}

[data-theme="dark"] .home-section-card {
    background: rgba(30, 41, 59, 0.55);
    border-color: rgba(255, 255, 255, 0.08);
}

/* Product photos are cut-outs on white: fitted, not cropped. */
.hs-media {
    height: 150px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.05);
    color: var(--mobile-primary);
    font-size: 2.6rem;
}

.hs-media img {
    width: 100%;
    height: 100%;
    padding: 12px;
    object-fit: contain;
    transition: transform 0.35s ease;
}

.hs-media.is-drawing {
    background: #f1f5f9;
}

.hs-media.is-drawing img {
    padding: 0;
}

.home-section-card:hover .hs-media img {
    transform: scale(1.06);
}

[data-theme="dark"] .hs-media {
    background: rgba(255, 255, 255, 0.94);
    border-color: transparent;
}

.hs-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 4px 4px;
}

.hs-body h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.45;
    color: #1e293b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

[data-theme="dark"] .hs-body h3 {
    color: #f1f5f9;
}

.home-section-card:hover h3 {
    color: var(--mobile-primary);
}

.hs-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--mobile-primary);
}

.home-section-card.is-skeleton {
    pointer-events: none;
}

.home-section-card.is-skeleton .hs-media,
.home-section-card.is-skeleton .hs-line {
    border: none;
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.14) 25%, rgba(148, 163, 184, 0.26) 37%, rgba(148, 163, 184, 0.14) 63%);
    background-size: 400% 100%;
    animation: home-shimmer 1.4s ease infinite;
}

.hs-line {
    display: block;
    height: 16px;
    width: 70%;
    margin: 0 4px 8px;
    border-radius: 8px;
}

@keyframes home-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

/* ===== "View all" under a section ===== */
.section-more {
    display: flex;
    justify-content: center;
    margin-top: 28px;
}

.btn-more {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 46px;
    padding: 0 22px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--mobile-primary) 35%, transparent);
    color: var(--mobile-primary);
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.btn-more:hover {
    background: var(--mobile-primary);
    color: #fff;
}

.btn-more:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

/* ===== Help band ===== */
.home-help {
    padding: 56px 0 24px;
}

.help-card {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 20px 32px;
    padding: 32px 36px;
    border-radius: 24px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--mobile-primary) 10%, #fff), #fff);
    border: 1px solid color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

[data-theme="dark"] .help-card {
    background: linear-gradient(135deg, color-mix(in srgb, var(--mobile-primary) 22%, #0f172a), #0f172a);
    border-color: rgba(255, 255, 255, 0.08);
}

.help-text {
    flex: 1 1 320px;
}

.help-text h2 {
    margin: 0 0 6px;
    font-size: 1.4rem;
    font-weight: 800;
    color: #0f172a;
}

.help-text p {
    margin: 0;
    color: #475569;
    line-height: 1.7;
}

[data-theme="dark"] .help-text h2 {
    color: #f1f5f9;
}

[data-theme="dark"] .help-text p {
    color: #94a3b8;
}

.help-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.btn-help-whatsapp,
.btn-help-contact {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 48px;
    padding: 0 22px;
    border-radius: 12px;
    font-weight: 700;
    text-decoration: none;
}

.btn-help-whatsapp {
    background: #16a34a;
    color: #fff;
}

.btn-help-whatsapp:hover {
    background: #15803d;
}

.btn-help-contact {
    background: #fff;
    color: var(--mobile-primary);
    border: 1px solid color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

[data-theme="dark"] .btn-help-contact {
    background: transparent;
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.15);
}

.btn-help-whatsapp:focus-visible,
.btn-help-contact:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

@media (max-width: 640px) {
    .home-sections-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .hs-media {
        height: 120px;
    }

    .hs-body h3 {
        font-size: 0.92rem;
    }

    .hero-search button {
        padding: 0 16px;
    }

    .help-card {
        padding: 24px 20px;
    }

    .help-actions,
    .help-actions > * {
        flex: 1 1 100%;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .home-section-card,
    .hs-media img,
    .home-section-card.is-skeleton .hs-media,
    .home-section-card.is-skeleton .hs-line {
        transition: none;
        animation: none;
    }

    .home-section-card:hover,
    .home-section-card:hover .hs-media img {
        transform: none;
    }
}
</style>
