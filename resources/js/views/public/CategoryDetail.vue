<template>
    <div class="category-detail-page-view">
        <!-- Page Header -->
        <section class="page-header" v-if="category">
            <div class="container">
                <h1>{{ $p(category, 'name') }}</h1>
                <nav class="breadcrumb" :aria-label="t('nav_categories')">
                    <router-link to="/">{{ t('nav_home') }}</router-link>
                    <span class="sep">›</span>
                    <router-link to="/categories">{{ t('nav_categories') }}</router-link>
                    <template v-if="parent">
                        <span class="sep">›</span>
                        <router-link :to="`/category/${parent.slug}`">{{ $p(parent, 'name') }}</router-link>
                    </template>
                    <span class="sep">›</span>
                    <span aria-current="page">{{ $p(category, 'name') }}</span>
                </nav>
            </div>
        </section>

        <!-- Products List Section -->
        <section ref="sectionRef" class="products-section category-products-section fade-up">
            <div class="container">
                <div class="products-header" v-if="category">
                    <h2 class="section-title">{{ t('nav_products') }} {{ $p(category, 'name') }}</h2>
                    <p class="section-lead">{{ $p(category, 'description') || t('browse_category_products') }}</p>
                </div>

                <!-- Subcategories: a category with twenty-seven of them used to
                     offer no way into any but the site menu. -->
                <div v-if="subcategories.length || parent" class="subcategory-strip" role="list">
                    <router-link
                        v-if="parent"
                        :to="`/category/${parent.slug}`"
                        class="sub-chip sub-chip--back"
                        role="listitem"
                    >
                        <i :class="isRtl ? 'fas fa-arrow-right' : 'fas fa-arrow-left'"></i>
                        {{ t('catp_back_to', { name: $p(parent, 'name') }) }}
                    </router-link>
                    <router-link
                        v-for="sub in subcategories"
                        :key="sub.id"
                        :to="`/category/${sub.slug}`"
                        class="sub-chip"
                        role="listitem"
                    >
                        {{ $p(sub, 'name') }}
                        <span class="sub-count">{{ formatCount(sub.product_count) }}</span>
                    </router-link>
                </div>

                <!-- Search, sort and stock, all in the address bar with the
                     page: a refresh, the back button or a shared link lands
                     on the same list. -->
                <div class="listing-toolbar">
                    <div class="toolbar-search">
                        <i class="fas fa-search"></i>
                        <input
                            v-model="searchInput"
                            type="search"
                            :placeholder="t('catp_search', { name: category ? $p(category, 'name') : '' })"
                            :aria-label="t('search')"
                        >
                        <button v-if="searchInput" type="button" class="search-clear" :aria-label="t('clear')" @click="searchInput = ''">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <label class="toolbar-select">
                        <span>{{ t('sort_by') }}</span>
                        <select :value="query.sort" @change="setQuery({ sort: $event.target.value })">
                            <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="toolbar-toggle">
                        <input type="checkbox" :checked="query.stock" @change="setQuery({ stock: $event.target.checked })">
                        <span>{{ t('catp_in_stock_only') }}</span>
                    </label>
                </div>

                <p v-if="pagination.total" class="result-count" aria-live="polite">
                    {{ t('catp_range', { from: formatCount(rangeFrom), to: formatCount(rangeTo), total: formatCount(pagination.total) }) }}
                </p>

                <!-- First visit: placeholders the shape of the cards. -->
                <div v-if="!loaded" class="products-grid" aria-busy="true">
                    <div v-for="n in query.per" :key="n" class="product-card skeleton-card">
                        <div class="skeleton-image"></div>
                        <div class="product-info">
                            <span class="skeleton-line wide"></span>
                            <span class="skeleton-line"></span>
                            <span class="skeleton-line short"></span>
                        </div>
                    </div>
                </div>

                <div v-else-if="loadError" class="empty-state">
                    <i class="fas fa-triangle-exclamation"></i>
                    <p>{{ t('catp_load_failed') }}</p>
                    <button type="button" class="empty-action" @click="fetchProducts">{{ t('catp_retry') }}</button>
                </div>

                <!-- A later page or filter keeps the current cards up, dimmed,
                     until the new ones land — the grid used to collapse to a
                     spinner and throw the page back up to the header. -->
                <div v-else class="grid-wrap" :class="{ 'is-loading': loading }" :aria-busy="loading">
                    <div v-if="products.length" class="products-grid">
                        <div v-for="product in products" :key="product.listing_key || product.id" class="product-card">
                            <div class="product-image">
                                <div class="badges-container">
                                    <span v-if="!product.in_stock" class="badge badge-out">{{ t('out_of_stock') }}</span>
                                    <span v-else class="badge badge-in">{{ t('in_stock') }}</span>
                                </div>
                                <img :src="getImageUrl(product.image_main)" :alt="$p(product, 'name')" loading="lazy" decoding="async">
                                <router-link :to="productLink(product)" class="product-overlay" :aria-label="$p(product, 'name')">
                                    <span class="view-btn"><i class="fas fa-eye"></i></span>
                                </router-link>
                            </div>
                            <div class="product-info">
                                <div class="product-title-row">
                                    <h3 class="product-title">
                                        <router-link :to="productLink(product)">{{ $p(product, 'name') }}</router-link>
                                    </h3>
                                </div>
                                <div class="product-details-row">
                                    <div class="product-category">{{ $p(product.category, 'name') || $p(category, 'name') }}</div>
                                    <div v-if="product.brand || product.model" class="product-meta-info">
                                        <span v-if="product.brand">{{ product.brand }}</span>
                                        <span v-if="product.model">{{ product.model }}</span>
                                    </div>
                                    <div v-if="showPrice(product)" class="product-price">
                                        <span>{{ formatPrice(product.price) }}</span>
                                    </div>
                                </div>
                                <div class="product-actions-row">
                                    <button
                                        type="button"
                                        class="btn-add-to-cart"
                                        :disabled="adding === (product.listing_key || product.id)"
                                        @click="handleAddToCart(product)"
                                    >
                                        <i :class="adding === (product.listing_key || product.id) ? 'fas fa-spinner fa-spin' : 'fas fa-cart-plus'"></i>
                                        <span>{{ t('add_to_cart') }}</span>
                                    </button>
                                    <a :href="whatsappLink(product)" class="btn-whatsapp" target="_blank" rel="noopener">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>WhatsApp</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p v-if="query.q">{{ t('catp_no_match', { q: query.q }) }}</p>
                        <p v-else-if="query.stock">{{ t('catp_none_in_stock') }}</p>
                        <p v-else>{{ t('no_products_found') }}</p>
                        <button v-if="query.q || query.stock" type="button" class="empty-action" @click="clearFilters">
                            {{ t('catp_clear_filters') }}
                        </button>
                    </div>
                    <div v-if="loading" class="grid-spinner"><i class="fas fa-spinner fa-spin"></i></div>
                </div>

                <!-- Pagination: a window of pages around the current one rather
                     than a button for every page — this category runs to over
                     a hundred and eighty of them. Each is a real link, so pages
                     can be opened in a new tab and followed by crawlers. -->
                <nav v-if="loaded && pagination.last_page > 1" class="listing-pagination" :aria-label="t('catp_pagination')">
                    <router-link
                        v-if="pagination.current_page > 1"
                        :to="pageLink(pagination.current_page - 1)"
                        class="page-btn page-step"
                        rel="prev"
                        :aria-label="t('previous')"
                    >
                        <i :class="prevIcon"></i>
                        <span class="step-label">{{ t('previous') }}</span>
                    </router-link>
                    <span v-else class="page-btn page-step disabled" aria-hidden="true">
                        <i :class="prevIcon"></i>
                        <span class="step-label">{{ t('previous') }}</span>
                    </span>

                    <ol class="page-list">
                        <li v-for="item in pageItems" :key="item.key">
                            <span v-if="item.gap" class="page-gap" aria-hidden="true">…</span>
                            <span v-else-if="item.page === pagination.current_page" class="page-btn active" aria-current="page">{{ formatCount(item.page) }}</span>
                            <router-link v-else :to="pageLink(item.page)" class="page-btn">{{ formatCount(item.page) }}</router-link>
                        </li>
                    </ol>
                    <span class="page-of">{{ t('catp_page_of', { page: formatCount(pagination.current_page), pages: formatCount(pagination.last_page) }) }}</span>

                    <router-link
                        v-if="pagination.current_page < pagination.last_page"
                        :to="pageLink(pagination.current_page + 1)"
                        class="page-btn page-step"
                        rel="next"
                        :aria-label="t('next')"
                    >
                        <span class="step-label">{{ t('next') }}</span>
                        <i :class="nextIcon"></i>
                    </router-link>
                    <span v-else class="page-btn page-step disabled" aria-hidden="true">
                        <span class="step-label">{{ t('next') }}</span>
                        <i :class="nextIcon"></i>
                    </span>

                    <label class="per-page">
                        <select :value="query.per" :aria-label="t('catp_per_page_label')" @change="setQuery({ per: Number($event.target.value) })">
                            <option v-for="size in PER_PAGE" :key="size" :value="size">{{ t('catp_per_page', { count: size }) }}</option>
                        </select>
                    </label>
                </nav>
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
import { ref, computed, watch, reactive, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useSeo } from '@/Composables/useSeo';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const settingsStore = useSettingsStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];
const PER_PAGE = [12, 24, 48];

// State
const category = ref(null);
const parent = ref(null);
const subcategories = ref([]);
const products = ref([]);
const pagination = ref({});
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
const adding = ref(null);
const sectionRef = ref(null);
const toast = reactive({ show: false, message: '', error: false });

const settings = computed(() => settingsStore.data);
const categorySlug = computed(() => route.params.slug);
const isRtl = computed(() => locale.value === 'ar');

/* ------------------------------------------------------------------ *
 * The listing's state is the URL's query — page, sort, search, stock,
 * page size — so the back button steps through pages, a refresh keeps
 * the place, and a link shares exactly this list.
 * ------------------------------------------------------------------ */
const query = computed(() => {
    const q = route.query;
    const page = Number.parseInt(q.page, 10);
    const per = Number.parseInt(q.per, 10);
    return {
        page: Number.isFinite(page) && page > 0 ? page : 1,
        sort: SORTS.includes(q.sort) ? q.sort : 'newest',
        q: typeof q.q === 'string' ? q.q.trim() : '',
        stock: q.stock === '1',
        per: PER_PAGE.includes(per) ? per : 12,
    };
});

/** The URL for a change of listing, with defaults left out to keep it short. */
const buildQuery = (changes) => {
    const next = { ...query.value, ...changes };
    const out = {};
    if (next.page > 1) out.page = String(next.page);
    if (next.sort !== 'newest') out.sort = next.sort;
    if (next.q) out.q = next.q;
    if (next.stock) out.stock = '1';
    if (next.per !== 12) out.per = String(next.per);
    return out;
};

const pageLink = (page) => ({ path: route.path, query: buildQuery({ page }) });

/** Anything but a page change starts the list again from its first page. */
const setQuery = (changes) => {
    router.push({ path: route.path, query: buildQuery({ page: 1, ...changes }) });
};

const clearFilters = () => {
    searchInput.value = '';
    setQuery({ q: '', stock: false });
};

// Search as you type, gathered for a moment; replace rather than push so
// each keystroke is not a step for the back button.
const searchInput = ref(query.value.q);
let searchTimer = null;
watch(searchInput, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const q = value.trim();
        if (q === query.value.q) return;
        router.replace({ path: route.path, query: buildQuery({ page: 1, q }) });
    }, 350);
});
watch(() => query.value.q, (q) => {
    if (q !== searchInput.value.trim()) searchInput.value = q;
});

const sortOptions = computed(() => [
    { value: 'newest', label: t('newest') },
    { value: 'price_asc', label: t('price_low_high') },
    { value: 'price_desc', label: t('price_high_low') },
    { value: 'name', label: t('name') },
]);

/* ------------------------------------------------------------------ *
 * SEO — owned by the shared useSeo composable (routed through
 * PublicLayout). Breadcrumb structured data is re-emitted here so the
 * client-side head matches the server's BreadcrumbList for category pages.
 * ------------------------------------------------------------------ */
const seo = useSeo();

const dispatchSeoEvent = () => {
    if (!category.value) return;
    const currentLocale = locale.value;
    const categoryName = currentLocale === 'en' ? (category.value.name_en || category.value.name_ar) : category.value.name_ar;
    const categoryDesc = currentLocale === 'en' ? (category.value.description_en || category.value.description_ar || category.value.description) : (category.value.description_ar || category.value.description);
    const seoTitleVal = category.value.meta_title || categoryName;
    const seoDescVal = category.value.meta_description || categoryDesc;

    const homeLabel = t('nav_home') || (currentLocale === 'en' ? 'Home' : 'الرئيسية');

    seo.setOverride({
        title: seoTitleVal,
        description: seoDescVal,
        keywords: '',
        image: category.value.image || '',
        ogType: 'website',
        jsonLd: [
            seo.breadcrumbSchema([
                { name: homeLabel, url: '/' },
                { name: categoryName, url: `/category/${category.value.slug}` },
            ]),
        ],
    });
};

watch(locale, () => {
    if (category.value) dispatchSeoEvent();
});

/* ------------------------------------------------------------------ *
 * Loading
 * ------------------------------------------------------------------ */
let controller = null;

const fetchProducts = async () => {
    // A newer request cancels the one still in flight, so a quick run of page
    // clicks cannot end on an older page's cards.
    controller?.abort();
    controller = new AbortController();
    const { signal } = controller;
    const slug = categorySlug.value;
    const { page, sort, q, stock, per } = query.value;

    loading.value = true;
    loadError.value = false;
    try {
        const res = await axios.get(`/api/v1/categories/${slug}/products`, {
            signal,
            params: {
                page,
                per_page: per,
                ...(sort !== 'newest' ? { sort } : {}),
                ...(sort === 'name' ? { lang: locale.value } : {}),
                ...(q ? { search: q } : {}),
                ...(stock ? { in_stock: 1 } : {}),
            },
        });
        if (!res.data?.success) throw new Error('Unexpected response');
        const data = res.data.data;
        category.value = data.category;
        parent.value = data.parent || null;
        subcategories.value = data.subcategories || [];
        products.value = data.products || [];
        pagination.value = data.pagination || {};
        dispatchSeoEvent();

        // Past the end — a stale link, or a filter that shrank the list: go to
        // the last page there is, in place of this one.
        const last = pagination.value.last_page || 1;
        if (page > last) {
            router.replace(pageLink(last));
        }
    } catch (e) {
        if (axios.isCancel(e) || e?.name === 'CanceledError') return;
        loadError.value = true;
    } finally {
        if (!signal.aborted) {
            loading.value = false;
            loaded.value = true;
            triggerFadeUp();
        }
    }
};

const scrollToList = () => {
    const top = sectionRef.value?.getBoundingClientRect().top;
    // Only when the list's top is out of view: a click near the top of the
    // page should not jump.
    if (top !== undefined && top < 0) {
        sectionRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Everything the list depends on; a change of category starts over.
watch(
    () => [categorySlug.value, query.value.page, query.value.sort, query.value.q, query.value.stock, query.value.per],
    ([slug, page], previous) => {
        if (!slug) return;
        if (previous && slug !== previous[0]) {
            loaded.value = false;
            window.scrollTo({ top: 0 });
        } else if (previous && page !== previous[1]) {
            scrollToList();
        }
        fetchProducts();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    controller?.abort();
    clearTimeout(searchTimer);
});

/* ------------------------------------------------------------------ *
 * Pagination
 * ------------------------------------------------------------------ */
/** First, last, and two either side of the current page; gaps in between. */
const pageItems = computed(() => {
    const current = pagination.value.current_page || 1;
    const last = pagination.value.last_page || 1;
    const pages = new Set([1, last]);
    for (let p = current - 2; p <= current + 2; p++) {
        if (p >= 1 && p <= last) pages.add(p);
    }
    const sorted = [...pages].sort((a, b) => a - b);
    const items = [];
    sorted.forEach((page, index) => {
        if (index && page - sorted[index - 1] > 1) items.push({ key: `gap-${page}`, gap: true });
        items.push({ key: page, page });
    });
    return items;
});

const rangeFrom = computed(() => ((pagination.value.current_page || 1) - 1) * (pagination.value.per_page || query.value.per) + 1);
const rangeTo = computed(() => Math.min(rangeFrom.value + products.value.length - 1, pagination.value.total || 0));

// "Previous" points back along the reading direction: right in Arabic, left
// in English. It was fixed to the right, so in English both arrows lied.
const prevIcon = computed(() => (isRtl.value ? 'fas fa-chevron-right' : 'fas fa-chevron-left'));
const nextIcon = computed(() => (isRtl.value ? 'fas fa-chevron-left' : 'fas fa-chevron-right'));

/* ------------------------------------------------------------------ *
 * Cards
 * ------------------------------------------------------------------ */
const productLink = (product) => ({
    path: `/product/${product.slug}`,
    query: product.variant_id ? { variant: product.variant_id } : {},
});

const showPrice = (product) => settings.value?.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0;

// Dollars, as everywhere else in the shop, but with thousands grouped.
const formatPrice = (value) => `$${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatCount = (value) => Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');

const whatsappLink = (product) => {
    const number = settings.value?.contact_whatsapp || '963900000000';
    return `https://wa.me/${number}?text=${encodeURIComponent(t('catp_whatsapp_text', { name: $pName(product) }))}`;
};

const $pName = (product) => (locale.value === 'en' ? (product.name_en || product.name_ar) : product.name_ar) || '';

let toastTimer = null;
const showToast = (message, error = false) => {
    Object.assign(toast, { message, error, show: true });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.show = false; }, 3000);
};

const handleAddToCart = async (product) => {
    const key = product.listing_key || product.id;
    adding.value = key;
    try {
        await cartStore.addToCart(product.id, 1, product.variant_id);
        showToast(t('catp_added', { name: $pName(product) }));
    } catch (e) {
        showToast(t('catp_add_failed'), true);
    } finally {
        if (adding.value === key) adding.value = null;
    }
};
</script>

<style scoped>

.category-detail-page-view {
    padding-bottom: 3rem;
}

.products-section {
    padding: 2rem 0 4rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-top: 30px;
}

.product-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.product-image {
    position: relative;
    height: 250px;
    background: #f8fafc;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .product-image {
    background: #1e293b;
}

.product-image img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover .product-image img {
    transform: scale(1.06);
}

.badges-container {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.badge-in {
    background: #e6f4ea;
    color: #137333;
}

[data-theme="dark"] .badge-in {
    background: rgba(19, 115, 51, 0.2);
    color: #81c995;
    border: 1px solid rgba(129, 201, 149, 0.2);
}

.badge-out {
    background: #fce8e6;
    color: #c5221f;
}

[data-theme="dark"] .badge-out {
    background: rgba(197, 34, 31, 0.2);
    color: #f28b82;
    border: 1px solid rgba(242, 139, 130, 0.2);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: color-mix(in srgb, var(--mobile-primary) 20%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.4s ease;
    z-index: 5;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.view-btn {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--el-color-primary);
    font-size: 1.2rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-card:hover .view-btn {
    transform: translateY(0);
}

.product-info {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    gap: 12px;
}

.product-title-row {
    margin-bottom: 4px;
}

.product-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
    line-height: 1.4;
    transition: color 0.3s;
}

[data-theme="dark"] .product-title {
    color: #f1f5f9;
}

.product-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    display: block;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    padding-top: 12px;
    margin-bottom: 6px;
}

[data-theme="dark"] .product-details-row {
    border-color: rgba(255, 255, 255, 0.08);
}

.product-category {
    font-size: 0.8rem;
    color: var(--mobile-primary);
    font-weight: 600;
}

.product-meta-info {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    gap: 8px;
}

.product-meta-info span {
    background: rgba(0, 0, 0, 0.04);
    padding: 2px 8px;
    border-radius: 4px;
}

[data-theme="dark"] .product-meta-info span {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
}

.product-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--mobile-primary);
    margin-top: 4px;
}

[data-theme="dark"] .product-price {
    color: var(--mobile-primary);
}

.product-actions-row {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.btn-add-to-cart, .btn-whatsapp {
    padding: 10px 14px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    flex: 1;
    border: none;
}

.btn-add-to-cart {
    background: var(--mobile-primary);
    color: white;
}

.btn-add-to-cart:hover {
    background: var(--el-color-primary-light-3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-whatsapp {
    background: #25d366;
    color: white;
    text-decoration: none;
}

.btn-whatsapp:hover {
    background: #20ba5a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
}

/* Titles are links now, so they keep the card's colour until hovered. */
.product-title a {
    color: inherit;
    text-decoration: none;
}

.product-title a:hover {
    color: var(--mobile-primary);
}

.btn-add-to-cart:disabled {
    opacity: 0.7;
    cursor: wait;
    transform: none;
}

.products-header .section-title {
    margin-bottom: 0.5rem;
}

.section-lead {
    color: #556;
    margin: 0;
}

[data-theme="dark"] .section-lead {
    color: #94a3b8;
}

/* ── Subcategories ── */
.subcategory-strip {
    display: flex;
    gap: 8px;
    margin-top: 1.25rem;
    padding-bottom: 6px;
    overflow-x: auto;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.sub-chip {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.06);
    color: #334155;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.sub-chip:hover {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
}

.sub-chip--back {
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
}

.sub-count {
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.05);
    font-size: 0.75rem;
    color: #64748b;
}

[data-theme="dark"] .sub-chip {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
}

[data-theme="dark"] .sub-count {
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

/* ── Toolbar ── */
.listing-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 1.25rem;
    padding: 12px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

[data-theme="dark"] .listing-toolbar {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(255, 255, 255, 0.06);
}

.toolbar-search {
    position: relative;
    flex: 1 1 260px;
    display: flex;
    align-items: center;
}

.toolbar-search > i {
    position: absolute;
    inset-inline-start: 14px;
    color: #94a3b8;
    pointer-events: none;
}

.toolbar-search input {
    width: 100%;
    height: 44px;
    padding-inline: 40px 38px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #fff;
    font: inherit;
    color: #1e293b;
}

.toolbar-search input:focus,
.toolbar-select select:focus {
    outline: 2px solid color-mix(in srgb, var(--mobile-primary) 45%, transparent);
    outline-offset: 1px;
}

/* The browser's own clear button would sit beside ours. */
.toolbar-search input::-webkit-search-cancel-button {
    display: none;
}

.search-clear {
    position: absolute;
    inset-inline-end: 8px;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
}

.search-clear:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #475569;
}

.toolbar-select {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #64748b;
}

.toolbar-select select,
.per-page select {
    height: 44px;
    padding-inline: 12px 32px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background-color: #fff;
    font: inherit;
    color: #1e293b;
    cursor: pointer;
}

.toolbar-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 12px;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

.toolbar-toggle input {
    width: 18px;
    height: 18px;
    accent-color: var(--mobile-primary);
}

[data-theme="dark"] .toolbar-search input,
[data-theme="dark"] .toolbar-select select,
[data-theme="dark"] .per-page select {
    background-color: #0f172a;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .toolbar-toggle {
    color: #e2e8f0;
}

.result-count {
    margin: 1rem 0 0;
    font-size: 0.88rem;
    color: #64748b;
}

/* ── Grid states ── */
.grid-wrap {
    position: relative;
}

.grid-wrap .products-grid {
    transition: opacity 0.2s ease;
}

.grid-wrap.is-loading .products-grid {
    opacity: 0.5;
    pointer-events: none;
}

.grid-spinner {
    position: absolute;
    top: 120px;
    left: 50%;
    transform: translateX(-50%);
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    color: var(--mobile-primary);
    font-size: 1.3rem;
}

.skeleton-card {
    pointer-events: none;
}

.skeleton-image,
.skeleton-line {
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.14) 25%, rgba(148, 163, 184, 0.26) 37%, rgba(148, 163, 184, 0.14) 63%);
    background-size: 400% 100%;
    animation: skeleton-shimmer 1.4s ease infinite;
}

.skeleton-image {
    height: 250px;
}

.skeleton-line {
    display: block;
    height: 14px;
    width: 60%;
    border-radius: 6px;
}

.skeleton-line.wide { width: 85%; height: 18px; }
.skeleton-line.short { width: 35%; }

@keyframes skeleton-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-image,
    .skeleton-line {
        animation: none;
    }
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
}

.empty-state > i {
    display: block;
    margin-bottom: 15px;
    font-size: 2.5rem;
    color: #909399;
}

.empty-state p {
    margin: 0 0 1rem;
}

.empty-action {
    padding: 10px 20px;
    border: none;
    border-radius: 12px;
    background: var(--mobile-primary);
    color: #fff;
    font-weight: 700;
    cursor: pointer;
}

/* ── Pagination ── */
.listing-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
    padding: 12px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
}

[data-theme="dark"] .listing-pagination {
    background: rgba(30, 41, 59, 0.3);
    border-color: rgba(255, 255, 255, 0.05);
}

.page-list {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.page-btn {
    min-width: 42px;
    height: 42px;
    padding: 0 10px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.05);
    font-variant-numeric: tabular-nums;
    transition: all 0.2s ease;
}

[data-theme="dark"] .page-btn {
    background: rgba(30, 41, 59, 0.5);
    color: #f1f5f9;
    border-color: rgba(255, 255, 255, 0.05);
}

a.page-btn:hover {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff !important;
}

a.page-btn:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

.page-btn.active {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff !important;
}

[data-theme="dark"] .page-btn.active {
    color: #0f172a !important;
}

.page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-gap {
    padding: 0 4px;
    color: #94a3b8;
}

/* Shown only where the numbers are not. */
.page-of {
    display: none;
    font-size: 0.9rem;
    font-weight: 600;
    color: #475569;
}

[data-theme="dark"] .page-of {
    color: #cbd5e1;
}

.per-page {
    margin-inline-start: 8px;
}

@media (max-width: 640px) {
    .listing-toolbar {
        padding: 10px;
    }

    .toolbar-select {
        flex: 1 1 auto;
    }

    .toolbar-select span {
        display: none;
    }

    .toolbar-select select {
        width: 100%;
    }

    .page-list {
        display: none;
    }

    .page-of {
        display: inline;
        flex: 1;
        text-align: center;
    }

    .page-step {
        flex: none;
    }

    .per-page {
        flex-basis: 100%;
        display: flex;
        justify-content: center;
        margin: 4px 0 0;
    }
}
</style>
