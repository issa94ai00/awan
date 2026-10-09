<template>
    <div class="products-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ heading }}</h1>
                <nav class="breadcrumb" :aria-label="t('prod_breadcrumb')">
                    <router-link to="/">{{ t('nav_home') }}</router-link>
                    <span class="sep">›</span>
                    <template v-if="activeCategory">
                        <router-link to="/products">{{ t('all_products') }}</router-link>
                        <span class="sep">›</span>
                        <span aria-current="page">{{ $p(activeCategory, 'name') }}</span>
                    </template>
                    <span v-else aria-current="page">{{ t('all_products') }}</span>
                </nav>
            </div>
        </section>

        <section ref="sectionRef" class="products-section fade-up">
            <div class="container">
                <div class="products-header">
                    <h2 class="section-title">{{ t('prod_section_title') }}</h2>
                    <p class="section-lead">{{ t('prod_lead') }}</p>
                </div>

                <!-- Sections as chips: real links, so a filtered list can be
                     opened in a new tab or shared, and Back undoes a filter. -->
                <nav v-if="topCategories.length" class="category-strip" :aria-label="t('nav_categories')">
                    <router-link
                        :to="categoryLink('')"
                        class="cat-chip"
                        :class="{ active: !activeCategory }"
                        :aria-current="!activeCategory ? 'true' : undefined"
                    >
                        {{ t('prod_all') }}
                    </router-link>
                    <router-link
                        v-for="cat in topCategories"
                        :key="cat.id"
                        :to="categoryLink(cat.slug)"
                        class="cat-chip"
                        :class="{ active: activeCategory?.id === cat.id }"
                        :aria-current="activeCategory?.id === cat.id ? 'true' : undefined"
                    >
                        {{ $p(cat, 'name') }}
                        <span class="cat-count">{{ formatCount(cat.product_count) }}</span>
                    </router-link>
                </nav>

                <ListingToolbar
                    v-model:search="searchInput"
                    :sort="query.sort"
                    :stock="query.stock"
                    :sort-options="sortOptions"
                    :placeholder="activeCategory ? t('catp_search', { name: $p(activeCategory, 'name') }) : t('prod_search_placeholder')"
                    @update:sort="setQuery({ sort: $event })"
                    @update:stock="setQuery({ stock: $event })"
                />

                <div v-if="pagination.total || activeCategory" class="result-bar">
                    <p v-if="pagination.total" class="result-count" aria-live="polite">
                        {{ t('catp_range', { from: formatCount(rangeFrom), to: formatCount(rangeTo), total: formatCount(pagination.total) }) }}
                    </p>
                    <!-- The section's own page has its subcategories and is the
                         address search engines know it by. -->
                    <router-link v-if="activeCategory" :to="`/category/${activeCategory.slug}`" class="category-page-link">
                        {{ t('prod_open_category', { name: $p(activeCategory, 'name') }) }}
                        <i :class="isRtl ? 'fas fa-arrow-left' : 'fas fa-arrow-right'"></i>
                    </router-link>
                </div>

                <!-- First visit: placeholders the shape of the cards. -->
                <div v-if="!loaded" class="products-grid" aria-busy="true">
                    <ProductListingCard v-for="n in query.per" :key="n" skeleton />
                </div>

                <div v-else-if="loadError" class="empty-state">
                    <i class="fas fa-triangle-exclamation"></i>
                    <p>{{ t('catp_load_failed') }}</p>
                    <button type="button" class="empty-action" @click="fetchProducts">{{ t('catp_retry') }}</button>
                </div>

                <!-- A later page or filter keeps the current cards up, dimmed,
                     until the new ones land. -->
                <div v-else class="grid-wrap" :class="{ 'is-loading': loading }" :aria-busy="loading">
                    <div v-if="products.length" class="products-grid">
                        <ProductListingCard
                            v-for="product in products"
                            :key="product.listing_key || product.id"
                            :product="product"
                            @added="showToast(t('catp_added', { name: $event }))"
                            @add-failed="showToast(t('catp_add_failed'), true)"
                        />
                    </div>

                    <div v-else class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p v-if="query.q">{{ t('catp_no_match', { q: query.q }) }}</p>
                        <p v-else-if="query.stock">{{ t('catp_none_in_stock') }}</p>
                        <p v-else>{{ t('no_products_found') }}</p>
                        <button v-if="hasFilters" type="button" class="empty-action" @click="clearFilters">
                            {{ t('catp_clear_filters') }}
                        </button>
                    </div>
                    <div v-if="loading" class="grid-spinner"><i class="fas fa-spinner fa-spin"></i></div>
                </div>

                <ListingPagination
                    v-if="loaded"
                    :pagination="pagination"
                    :link-for="pageLink"
                    :per="query.per"
                    @update:per="setQuery({ per: $event })"
                />
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
import { ref, computed, watch, reactive, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useSeo } from '@/Composables/useSeo';
import { useListingQuery } from '@/Composables/useListingQuery';
import { useProductsStore } from '@/stores/products';
import ProductListingCard from '@/components/public/ProductListingCard.vue';
import ListingPagination from '@/components/public/ListingPagination.vue';
import ListingToolbar from '@/components/public/ListingToolbar.vue';

const { t, locale } = useI18n();
const router = useRouter();
const productsStore = useProductsStore();

// State
const categories = ref([]);
const products = ref([]);
const pagination = ref({});
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
const sectionRef = ref(null);
const toast = reactive({ show: false, message: '', error: false });

const isRtl = computed(() => locale.value === 'ar');

/* ------------------------------------------------------------------ *
 * The listing's state lives in the URL — see useListingQuery. The
 * section is `?category=<slug>`, so a filtered list is a shareable link.
 * ------------------------------------------------------------------ */
const { query, pageLink, buildQuery, setQuery, apiParams } = useListingQuery({
    sorts: ['newest', 'price_asc', 'price_desc', 'name'],
    defaultSort: 'newest',
    params: ['category'],
});

const topCategories = computed(() => categories.value
    .filter((cat) => !cat.parent_id && Number(cat.product_count) > 0));

// An unknown slug (an old or mistyped link) lists everything rather than
// nothing.
const activeCategory = computed(() => (query.value.category
    ? categories.value.find((cat) => cat.slug === query.value.category) || null
    : null));

const categoryLink = (slug) => ({ path: '/products', query: buildQuery({ page: 1, category: slug }) });

const hasFilters = computed(() => Boolean(query.value.q || query.value.stock || activeCategory.value));

const clearFilters = () => {
    searchInput.value = '';
    setQuery({ q: '', stock: false, category: '' });
};

// Search as you type, gathered for a moment; replace rather than push so
// each keystroke is not a step for the back button.
const searchInput = ref(query.value.q);
let searchTimer = null;
watch(searchInput, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const q = value.trim();
        if (q !== query.value.q) setQuery({ q }, { replace: true });
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

const heading = computed(() => (activeCategory.value
    ? t('prod_heading_in', { name: activeCategory.value[`name_${locale.value}`] || activeCategory.value.name_ar })
    : t('all_products')));

/* ------------------------------------------------------------------ *
 * SEO — the route defaults come from useSeo via PublicLayout; once the
 * page's products are known, add the breadcrumb and an ItemList of them.
 * ------------------------------------------------------------------ */
const seo = useSeo();

const productName = (product) => (locale.value === 'en'
    ? (product.name_en || product.name_ar)
    : product.name_ar) || '';

const applySeo = () => {
    const homeLabel = t('nav_home');
    const allLabel = t('all_products');
    const offset = ((pagination.value.current_page || 1) - 1) * (pagination.value.per_page || query.value.per);

    seo.setOverride({
        title: heading.value,
        description: t('prod_meta_description', { count: formatCount(pagination.value.total) }),
        ogType: 'website',
        jsonLd: [
            seo.breadcrumbSchema([
                { name: homeLabel, url: '/' },
                { name: allLabel, url: '/products' },
            ]),
            {
                '@context': 'https://schema.org',
                '@type': 'ItemList',
                name: heading.value,
                numberOfItems: pagination.value.total || products.value.length,
                itemListElement: products.value.map((product, index) => ({
                    '@type': 'ListItem',
                    position: offset + index + 1,
                    name: productName(product),
                    url: `${window.location.origin}/product/${product.slug}`,
                })),
            },
        ],
    });
};

watch(locale, () => {
    if (loaded.value && !loadError.value) applySeo();
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
    const { page } = query.value;

    loading.value = true;
    loadError.value = false;
    try {
        const res = await axios.get('/api/v1/products', {
            signal,
            params: {
                ...apiParams(locale.value),
                ...(activeCategory.value ? { category_slug: activeCategory.value.slug } : {}),
            },
        });
        if (!res.data?.success) throw new Error('Unexpected response');
        products.value = res.data.data || [];
        pagination.value = res.data.pagination || {};
        applySeo();

        // Past the end — a stale link, or a filter that shrank the list: go to
        // the last page there is, in place of this one.
        const last = pagination.value.last_page || 1;
        if (page > last) router.replace(pageLink(last));
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

const categoriesReady = ref(false);

// Everything the list depends on. The category is resolved from the loaded
// sections, so the first fetch waits for them.
watch(
    () => [categoriesReady.value, query.value.page, query.value.sort, query.value.q, query.value.stock, query.value.per, activeCategory.value?.id],
    ([ready, page], previous) => {
        if (!ready) return;
        if (previous?.[0] && page !== previous[1]) scrollToList();
        fetchProducts();
    },
    { immediate: true },
);

onMounted(async () => {
    try {
        categories.value = await productsStore.fetchPublicCategories();
    } finally {
        categoriesReady.value = true;
    }
});

onBeforeUnmount(() => {
    controller?.abort();
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});

/* ------------------------------------------------------------------ *
 * Display
 * ------------------------------------------------------------------ */
const rangeFrom = computed(() => ((pagination.value.current_page || 1) - 1) * (pagination.value.per_page || query.value.per) + 1);
const rangeTo = computed(() => Math.min(rangeFrom.value + products.value.length - 1, pagination.value.total || 0));

function formatCount(value) {
    return Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');
}

let toastTimer = null;
const showToast = (message, error = false) => {
    Object.assign(toast, { message, error, show: true });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.show = false; }, 3000);
};
</script>

<style scoped>
.products-page-view {
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

.products-header .section-title {
    margin-bottom: 0.5rem;
}

.section-lead {
    color: #556;
    margin: 0;
    max-width: 70ch;
}

[data-theme="dark"] .section-lead {
    color: #94a3b8;
}

/* ── Category chips ── */
.category-strip {
    display: flex;
    gap: 8px;
    margin-top: 1.25rem;
    padding-bottom: 6px;
    overflow-x: auto;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.cat-chip {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
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

.cat-chip:hover {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
}

.cat-chip:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 2px;
}

.cat-chip.active {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff;
}

.cat-count {
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.05);
    font-size: 0.75rem;
    color: #64748b;
}

.cat-chip.active .cat-count {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

[data-theme="dark"] .cat-chip {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
}

[data-theme="dark"] .cat-chip.active {
    background: var(--mobile-primary);
    border-color: var(--mobile-primary);
    color: #fff;
}

[data-theme="dark"] .cat-count {
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

/* ── Result bar ── */
.result-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-top: 1rem;
}

.result-count {
    margin: 0;
    font-size: 0.88rem;
    color: #64748b;
}

.category-page-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--mobile-primary);
    text-decoration: none;
}

.category-page-link:hover {
    text-decoration: underline;
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
</style>
