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

                <ListingToolbar
                    v-model:search="searchInput"
                    :sort="query.sort"
                    :stock="query.stock"
                    :sort-options="sortOptions"
                    :placeholder="t('catp_search', { name: category ? $p(category, 'name') : '' })"
                    @update:sort="setQuery({ sort: $event })"
                    @update:stock="setQuery({ stock: $event })"
                />

                <p v-if="pagination.total" class="result-count" aria-live="polite">
                    {{ t('catp_range', { from: formatCount(rangeFrom), to: formatCount(rangeTo), total: formatCount(pagination.total) }) }}
                </p>

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
                     until the new ones land — the grid used to collapse to a
                     spinner and throw the page back up to the header. -->
                <div v-else class="grid-wrap" :class="{ 'is-loading': loading }" :aria-busy="loading">
                    <div v-if="products.length" class="products-grid">
                        <ProductListingCard
                            v-for="product in products"
                            :key="product.listing_key || product.id"
                            :product="product"
                            :fallback-category="category"
                            @added="showToast(t('catp_added', { name: $event }))"
                            @add-failed="showToast(t('catp_add_failed'), true)"
                        />
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
import { ref, computed, watch, reactive, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useSeo } from '@/Composables/useSeo';
import { useListingQuery } from '@/Composables/useListingQuery';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import ProductListingCard from '@/components/public/ProductListingCard.vue';
import ListingPagination from '@/components/public/ListingPagination.vue';
import ListingToolbar from '@/components/public/ListingToolbar.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

// State
const category = ref(null);
const parent = ref(null);
const subcategories = ref([]);
const products = ref([]);
const pagination = ref({});
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
const sectionRef = ref(null);
const toast = reactive({ show: false, message: '', error: false });

const categorySlug = computed(() => route.params.slug);
const isRtl = computed(() => locale.value === 'ar');

/* ------------------------------------------------------------------ *
 * The listing's state lives in the URL — see useListingQuery.
 * ------------------------------------------------------------------ */
const { query, pageLink, setQuery, apiParams } = useListingQuery({
    sorts: ['newest', 'price_asc', 'price_desc', 'name'],
    defaultSort: 'newest',
});

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
    const { page } = query.value;

    loading.value = true;
    loadError.value = false;
    try {
        const res = await axios.get(`/api/v1/categories/${categorySlug.value}/products`, {
            signal,
            params: apiParams(locale.value),
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
    clearTimeout(toastTimer);
});

/* ------------------------------------------------------------------ *
 * Display
 * ------------------------------------------------------------------ */
const rangeFrom = computed(() => ((pagination.value.current_page || 1) - 1) * (pagination.value.per_page || query.value.per) + 1);
const rangeTo = computed(() => Math.min(rangeFrom.value + products.value.length - 1, pagination.value.total || 0));

const formatCount = (value) => Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');

let toastTimer = null;
const showToast = (message, error = false) => {
    Object.assign(toast, { message, error, show: true });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.show = false; }, 3000);
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
