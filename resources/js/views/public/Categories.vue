<template>
    <div class="categories-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ isSearch ? t('catl_results_for', { q: query.q }) : t('nav_categories') }}</h1>
                <nav class="breadcrumb" :aria-label="t('nav_categories')">
                    <router-link to="/">{{ t('nav_home') }}</router-link>
                    <span class="sep">›</span>
                    <template v-if="isSearch">
                        <router-link to="/categories">{{ t('nav_categories') }}</router-link>
                        <span class="sep">›</span>
                        <span aria-current="page">{{ t('search') }}</span>
                    </template>
                    <span v-else aria-current="page">{{ t('nav_categories') }}</span>
                </nav>
            </div>
        </section>

        <section ref="sectionRef" class="categories fade-up">
            <div class="container">
                <!-- ═══════════ Search results ═══════════ -->
                <template v-if="isSearch">
                    <ListingToolbar
                        v-model:search="searchInput"
                        :sort="query.sort"
                        :stock="query.stock"
                        :sort-options="searchSortOptions"
                        :placeholder="t('catl_search_placeholder')"
                        @update:sort="setQuery({ sort: $event })"
                        @update:stock="setQuery({ stock: $event })"
                    />

                    <!-- A search that names a category is usually after all of
                         it, not a handful of its products. -->
                    <div v-if="matchingCategories.length && query.page === 1" class="match-strip">
                        <span class="match-label">{{ t('catl_matching_categories') }}</span>
                        <router-link v-for="cat in matchingCategories" :key="cat.id" :to="`/category/${cat.slug}`" class="sub-chip">
                            {{ $p(cat, 'name') }}
                            <span class="sub-count">{{ formatCount(cat.product_count) }}</span>
                        </router-link>
                    </div>

                    <p v-if="pagination.total" class="result-count" aria-live="polite">
                        {{ t('catp_range', { from: formatCount(rangeFrom), to: formatCount(rangeTo), total: formatCount(pagination.total) }) }}
                    </p>

                    <div v-if="!loaded" class="products-grid" aria-busy="true">
                        <ProductListingCard v-for="n in query.per" :key="n" skeleton />
                    </div>

                    <div v-else-if="loadError" class="empty-state">
                        <i class="fas fa-triangle-exclamation"></i>
                        <p>{{ t('catp_load_failed') }}</p>
                        <button type="button" class="empty-action" @click="loadSearch">{{ t('catp_retry') }}</button>
                    </div>

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
                            <i class="fas fa-search"></i>
                            <p v-if="query.q.length < 2">{{ t('catl_too_short') }}</p>
                            <p v-else-if="query.stock">{{ t('catp_none_in_stock') }}</p>
                            <p v-else>{{ t('catp_no_match', { q: query.q }) }}</p>
                            <button v-if="query.stock" type="button" class="empty-action" @click="setQuery({ stock: false })">
                                {{ t('catp_clear_filters') }}
                            </button>
                            <router-link v-else to="/categories" class="empty-action">{{ t('catl_browse_categories') }}</router-link>
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
                </template>

                <!-- ═══════════ All categories ═══════════ -->
                <template v-else>
                    <div class="section-header">
                        <h2>{{ t('main_categories') }}</h2>
                        <p>{{ t('categories_subtitle') }}</p>
                    </div>

                    <!-- Narrows the sections as you type; Enter searches the
                         products themselves. -->
                    <form class="category-finder" role="search" @submit.prevent="searchProducts">
                        <i class="fas fa-search"></i>
                        <input
                            v-model="finder"
                            type="search"
                            :placeholder="t('catl_find')"
                            :aria-label="t('catl_find')"
                            enterkeyhint="search"
                        >
                        <button v-if="finderTerm.length >= 2" type="submit" class="finder-submit">
                            {{ t('catl_search_all', { q: finderTerm }) }}
                        </button>
                    </form>

                    <div v-if="catalogLoaded && !catalogError" class="summary-row">
                        <p class="result-count">
                            {{ t('catl_summary', { sections: formatCount(visibleSections.length), products: formatCount(totalProducts) }) }}
                        </p>
                        <router-link to="/products" class="all-products-link">
                            {{ t('catl_all_products') }}
                            <i :class="isRtl ? 'fas fa-arrow-left' : 'fas fa-arrow-right'" aria-hidden="true"></i>
                        </router-link>
                    </div>

                    <div v-if="!catalogLoaded" class="sections-grid" aria-busy="true">
                        <div v-for="n in 6" :key="n" class="section-card skeleton">
                            <span class="skeleton-block media"></span>
                            <span class="skeleton-block line wide"></span>
                            <span class="skeleton-block line"></span>
                        </div>
                    </div>

                    <div v-else-if="catalogError" class="empty-state">
                        <i class="fas fa-triangle-exclamation"></i>
                        <p>{{ t('catp_load_failed') }}</p>
                        <button type="button" class="empty-action" @click="loadCategories">{{ t('catp_retry') }}</button>
                    </div>

                    <div v-else-if="visibleSections.length" class="sections-grid">
                        <!-- Sections with their subcategories inside them. The page
                             was one flat grid of every category at every level,
                             so a section and a sub-subsection sat side by side
                             with nothing saying which held which. -->
                        <!-- The whole card opens the section (the title link is
                             stretched over it); the subcategory chips sit above
                             that and open their own pages. -->
                        <article v-for="section in visibleSections" :key="section.id" class="section-card">
                            <!-- No section has an image of its own, so the API
                                 lends each one a photo of its products — or, for
                                 a section with none, a placeholder drawing. -->
                            <div class="section-media" :class="{ 'is-drawing': isDrawing(section) }">
                                <img
                                    v-if="section.image || section.thumbnail"
                                    :src="getImageUrl(section.image || section.thumbnail)"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    width="320"
                                    height="160"
                                >
                                <i v-else class="fas section-icon" :class="section.icon || 'fa-cube'" aria-hidden="true"></i>
                                <span class="media-badge">{{ t('catl_products_n', { count: formatCount(section.product_count) }) }}</span>
                            </div>

                            <div class="section-body">
                                <h3 class="section-title">
                                    <router-link :to="`/category/${section.slug}`" class="section-link">{{ $p(section, 'name') }}</router-link>
                                </h3>
                                <span class="section-go" aria-hidden="true">
                                    <i :class="isRtl ? 'fas fa-arrow-left' : 'fas fa-arrow-right'"></i>
                                </span>
                            </div>

                            <p v-if="$p(section, 'description')" class="section-desc">{{ $p(section, 'description') }}</p>

                            <div v-if="section.children.length" class="section-children">
                                <span class="children-label">{{ t('catl_subsections_n', { count: formatCount(section.children.length) }) }}</span>
                                <router-link
                                    v-for="child in shownChildren(section)"
                                    :key="child.id"
                                    :to="`/category/${child.slug}`"
                                    class="sub-chip"
                                    :class="{ 'is-match': finderTerm && matches(child) }"
                                >
                                    {{ $p(child, 'name') }}
                                    <span class="sub-count">{{ formatCount(child.product_count) }}</span>
                                </router-link>
                                <button
                                    v-if="section.children.length > shownChildren(section).length"
                                    type="button"
                                    class="sub-chip sub-chip--more"
                                    @click="expanded[section.id] = true"
                                >
                                    {{ t('catl_more', { count: section.children.length - shownChildren(section).length }) }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p v-if="finderTerm">{{ t('catl_no_category_match', { q: finderTerm }) }}</p>
                        <p v-else>{{ t('no_categories') }}</p>
                        <button v-if="finderTerm.length >= 2" type="button" class="empty-action" @click="searchProducts">
                            {{ t('catl_search_all', { q: finderTerm }) }}
                        </button>
                    </div>
                </template>
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
import { ref, reactive, computed, watch, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { matchesSearch } from '@/utils/search';
import { useListingQuery } from '@/Composables/useListingQuery';
import { useSeo } from '@/Composables/useSeo';
import ProductListingCard from '@/components/public/ProductListingCard.vue';
import ListingPagination from '@/components/public/ListingPagination.vue';
import ListingToolbar from '@/components/public/ListingToolbar.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();


const isRtl = computed(() => locale.value === 'ar');
const isSearch = computed(() => typeof route.query.q === 'string' && route.query.q.trim() !== '');
const sectionRef = ref(null);
const formatCount = (value) => Number(value || 0).toLocaleString(isRtl.value ? 'ar-SY' : 'en-US');

/* ------------------------------------------------------------------ *
 * All categories
 * ------------------------------------------------------------------ */
const categories = ref([]);
const catalogLoaded = ref(false);
const catalogError = ref(false);
const finder = ref('');
const expanded = reactive({});

const CHILDREN_SHOWN = 6;

/** The generic drawings carry their own slate tile; the card frames them as an icon, not a photo. */
const isDrawing = (section) => !section.image && /\/images_items\/generic\//.test(section.thumbnail || '');

const loadCategories = async () => {
    catalogError.value = false;
    try {
        const res = await axios.get('/api/v1/categories');
        if (!res.data?.success) throw new Error('Unexpected response');
        categories.value = res.data.data || [];
        if (!isSearch.value) applySeo();
    } catch (e) {
        catalogError.value = true;
    } finally {
        catalogLoaded.value = true;
        triggerFadeUp();
    }
};

/** Top-level sections, each with its non-empty subcategories, in shop order. */
const sections = computed(() => {
    const byOrder = (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id;
    const withProducts = categories.value.filter((cat) => Number(cat.product_count) > 0);
    return withProducts
        .filter((cat) => !cat.parent_id)
        .sort(byOrder)
        .map((section) => ({
            ...section,
            children: withProducts.filter((cat) => cat.parent_id === section.id).sort(byOrder),
        }));
});

const finderTerm = computed(() => finder.value.trim());
const matches = (cat) => matchesSearch([cat.name_ar, cat.name_en], finderTerm.value);

/** A section shows if it or any of its subcategories matches what is typed. */
const visibleSections = computed(() => {
    if (!finderTerm.value) return sections.value;
    return sections.value
        .filter((section) => matches(section) || section.children.some(matches))
        .map((section) => (matches(section)
            ? section
            : { ...section, children: section.children.filter(matches) }));
});

const totalProducts = computed(() => visibleSections.value.reduce((sum, section) => sum + Number(section.product_count || 0), 0));

const shownChildren = (section) => (expanded[section.id] || finderTerm.value
    ? section.children
    : section.children.slice(0, CHILDREN_SHOWN));

const searchProducts = () => {
    if (finderTerm.value.length < 2) return;
    router.push({ path: '/categories', query: { q: finderTerm.value } });
};

/* ------------------------------------------------------------------ *
 * Search results — paginated, sorted and filtered from the URL. They
 * used to stop at the first twenty matches and call that the count.
 * ------------------------------------------------------------------ */
const { query, pageLink, setQuery, apiParams } = useListingQuery({
    sorts: ['relevance', 'newest', 'price_asc', 'price_desc', 'name'],
    defaultSort: 'relevance',
});

const searchSortOptions = computed(() => [
    { value: 'relevance', label: t('catl_relevance') },
    { value: 'newest', label: t('newest') },
    { value: 'price_asc', label: t('price_low_high') },
    { value: 'price_desc', label: t('price_high_low') },
    { value: 'name', label: t('name') },
]);

const products = ref([]);
const matchingCategories = ref([]);
const pagination = ref({});
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
let controller = null;

const loadSearch = async () => {
    controller?.abort();
    controller = new AbortController();
    const { signal } = controller;
    const { page } = query.value;

    loading.value = true;
    loadError.value = false;
    try {
        // The search endpoint takes the query as `q`, not `search`.
        const { search, ...params } = apiParams(locale.value);
        const res = await axios.get('/api/v1/search', { signal, params: { ...params, q: search || query.value.q } });
        if (!res.data?.success) throw new Error('Unexpected response');
        const data = res.data.data || {};
        products.value = data.products || [];
        matchingCategories.value = (data.categories || []).filter((cat) => Number(cat.product_count) > 0);
        pagination.value = data.pagination || {};
        applySeo();

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

// The box in the results edits the search in place; emptied, it goes back
// to the categories.
const searchInput = ref(query.value.q);
let searchTimer = null;
watch(searchInput, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const q = value.trim();
        if (q === query.value.q) return;
        if (!q) router.push('/categories');
        else setQuery({ q }, { replace: true });
    }, 350);
});
watch(() => query.value.q, (q) => {
    if (q !== searchInput.value.trim()) searchInput.value = q;
});

const rangeFrom = computed(() => ((pagination.value.current_page || 1) - 1) * (pagination.value.per_page || query.value.per) + 1);
const rangeTo = computed(() => Math.min(rangeFrom.value + products.value.length - 1, pagination.value.total || 0));

/* ------------------------------------------------------------------ *
 * SEO — PublicLayout sets the route defaults; once the sections are in,
 * add the breadcrumb and the sections as an ItemList (what the server
 * sends on a direct visit). A search is a results page: not indexed.
 * ------------------------------------------------------------------ */
const seo = useSeo();

const applySeo = () => {
    const crumbs = [
        { name: t('nav_home'), url: '/' },
        { name: t('nav_categories'), url: '/categories' },
    ];

    if (isSearch.value) {
        seo.setOverride({
            title: t('catl_results_for', { q: query.value.q }),
            description: t('catl_search_placeholder'),
            noindex: true,
            jsonLd: [seo.breadcrumbSchema(crumbs)],
        });
        return;
    }

    seo.setOverride({
        title: t('nav_categories'),
        description: t('catl_meta_description', {
            count: formatCount(sections.value.length),
            names: sections.value.slice(0, 5).map((section) => (locale.value === 'en'
                ? (section.name_en || section.name_ar)
                : section.name_ar)).join(locale.value === 'en' ? ', ' : '، '),
        }),
        jsonLd: [
            seo.breadcrumbSchema(crumbs),
            {
                '@context': 'https://schema.org',
                '@type': 'ItemList',
                name: t('nav_categories'),
                numberOfItems: sections.value.length,
                itemListElement: sections.value.map((section, index) => ({
                    '@type': 'ListItem',
                    position: index + 1,
                    name: locale.value === 'en' ? (section.name_en || section.name_ar) : section.name_ar,
                    url: `${window.location.origin}/category/${section.slug}`,
                })),
            },
        ],
    });
};

// Applied after each load (below), which lands after PublicLayout has reset
// the head for the new URL; these cover the moves that load nothing.
watch([isSearch, locale], () => {
    if (isSearch.value ? loaded.value : (catalogLoaded.value && !catalogError.value)) applySeo();
}, { flush: 'post' });


/* ------------------------------------------------------------------ *
 * Which of the two to load
 * ------------------------------------------------------------------ */
watch(
    () => [isSearch.value, query.value.q, query.value.page, query.value.sort, query.value.stock, query.value.per],
    ([searching, q, page], previous) => {
        if (!searching) {
            controller?.abort();
            if (!catalogLoaded.value) loadCategories();
            return;
        }
        if (previous && previous[0] && page !== previous[2]) {
            const top = sectionRef.value?.getBoundingClientRect().top;
            if (top !== undefined && top < 0) sectionRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        loadSearch();
    },
    { immediate: true },
);

/* ------------------------------------------------------------------ *
 * Toast
 * ------------------------------------------------------------------ */
const toast = reactive({ show: false, message: '', error: false });
let toastTimer = null;
const showToast = (message, error = false) => {
    Object.assign(toast, { message, error, show: true });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.show = false; }, 3000);
};

onBeforeUnmount(() => {
    controller?.abort();
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});
</script>

<style scoped>
.categories {
    padding-bottom: 4rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-top: 30px;
}

.result-count {
    margin: 1rem 0 0;
    font-size: 0.88rem;
    color: #64748b;
}

.summary-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-top: 1rem;
}

.summary-row .result-count {
    margin: 0;
}

.all-products-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--mobile-primary);
    text-decoration: none;
}

.all-products-link:hover {
    text-decoration: underline;
}

@media (prefers-reduced-motion: reduce) {
    .section-card,
    .section-media img,
    .section-go {
        transition: none;
    }

    .section-card:hover,
    .section-card:hover .section-media img,
    .section-card:hover .section-go {
        transform: none;
    }
}

/* ── Chips (subcategories, matching categories) ── */
.sub-chip {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(0, 0, 0, 0.06);
    color: #334155;
    font: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
}

.sub-chip:hover,
.sub-chip:focus-visible {
    border-color: var(--mobile-primary);
    color: var(--mobile-primary);
}

.sub-chip.is-match {
    border-color: var(--mobile-primary);
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
}

.sub-chip--more {
    background: transparent;
    border-style: dashed;
    color: #64748b;
}

.sub-count {
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.05);
    font-size: 0.72rem;
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

.match-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 1rem;
    overflow-x: auto;
    padding-bottom: 4px;
}

.match-label {
    flex: none;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
}

/* ── Category finder ── */
.category-finder {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 1.5rem;
}

.category-finder > i {
    position: absolute;
    inset-inline-start: 16px;
    top: 16px;
    color: #94a3b8;
    pointer-events: none;
}

.category-finder input {
    flex: 1 1 280px;
    height: 48px;
    padding-inline: 44px 16px;
    border-radius: 14px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: rgba(255, 255, 255, 0.85);
    font: inherit;
    color: #1e293b;
}

.category-finder input:focus {
    outline: 2px solid color-mix(in srgb, var(--mobile-primary) 45%, transparent);
    outline-offset: 1px;
}

.category-finder input::-webkit-search-cancel-button {
    cursor: pointer;
}

[data-theme="dark"] .category-finder input {
    background: #0f172a;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.finder-submit {
    height: 48px;
    padding: 0 18px;
    border: none;
    border-radius: 14px;
    background: var(--mobile-primary);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Sections ── */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 1.5rem;
}

.section-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 14px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(15, 23, 42, 0.06);
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.04);
    border-radius: 22px;
    transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
}

.section-card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--mobile-primary) 30%, transparent);
    box-shadow: 0 18px 38px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 10px 24px rgba(15, 23, 42, 0.05);
}

[data-theme="dark"] .section-card {
    background: rgba(30, 41, 59, 0.55);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
}

/* ── Media ── */
/* Product photos are cut-outs on white, so they are fitted, not cropped. */
.section-media {
    position: relative;
    height: 170px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.05);
}

.section-media img {
    width: 100%;
    height: 100%;
    padding: 16px;
    object-fit: contain;
    transition: transform 0.35s ease;
}

/* A drawing is a slate tile with a grey glyph: on a panel of the same slate
   it reads as an icon, not a small grey square in a white box. */
.section-media.is-drawing {
    background: #f1f5f9;
}

.section-media.is-drawing img {
    padding: 0;
}

.section-card:hover .section-media img {
    transform: scale(1.06);
}

.section-icon {
    font-size: 3rem;
    color: var(--mobile-primary);
}

[data-theme="dark"] .section-media {
    background: rgba(255, 255, 255, 0.94);
    border-color: transparent;
}

[data-theme="dark"] .section-media.is-drawing {
    background: #f1f5f9;
}

.media-badge {
    position: absolute;
    inset-block-end: 10px;
    inset-inline-start: 10px;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.1);
    color: var(--mobile-primary);
    font-size: 0.76rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

/* ── Title ── */
.section-body {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-inline: 4px;
}

.section-title {
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    line-height: 1.45;
    color: #1e293b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

[data-theme="dark"] .section-title {
    color: #f1f5f9;
}

.section-link {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

/* Stretched over the card, so a click anywhere on it opens the section. */
.section-link::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 0;
    border-radius: inherit;
}

.section-card:hover .section-link {
    color: var(--mobile-primary);
}

.section-link:focus-visible {
    outline: none;
}

.section-link:focus-visible::after {
    outline: 3px solid var(--mobile-primary);
    outline-offset: 2px;
    border-radius: 22px;
}

.section-go {
    flex: none;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
    font-size: 0.9rem;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.section-card:hover .section-go {
    background: var(--mobile-primary);
    color: #fff;
    transform: translateX(var(--nudge, 3px));
}

[dir='rtl'] .section-card:hover .section-go {
    --nudge: -3px;
}

.section-desc {
    margin: -6px 0 0;
    padding-inline: 4px;
    font-size: 0.85rem;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ── Subcategories: pinned to the card's foot so a row lines up ── */
.section-children {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: auto;
    padding: 12px 4px 2px;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
}

[data-theme="dark"] .section-children {
    border-color: rgba(255, 255, 255, 0.08);
}

.children-label {
    flex-basis: 100%;
    margin-bottom: 2px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #94a3b8;
}

/* ── Loading and empty states ── */
.section-card.skeleton {
    pointer-events: none;
}

.skeleton-block {
    display: block;
    border-radius: 8px;
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.14) 25%, rgba(148, 163, 184, 0.26) 37%, rgba(148, 163, 184, 0.14) 63%);
    background-size: 400% 100%;
    animation: skeleton-shimmer 1.4s ease infinite;
}

.skeleton-block.line { height: 14px; width: 55%; }
.skeleton-block.line.wide { width: 80%; height: 18px; }

.skeleton-block.media { width: 100%; height: 170px; border-radius: 16px; }

@keyframes skeleton-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-block {
        animation: none;
    }
}

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
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 12px;
    background: var(--mobile-primary);
    color: #fff;
    font: inherit;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

@media (max-width: 640px) {
    .sections-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .section-media,
    .skeleton-block.media {
        height: 150px;
    }

    .finder-submit {
        flex: 1 1 100%;
    }
}
</style>
