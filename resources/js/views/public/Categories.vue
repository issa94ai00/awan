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

                    <p v-if="catalogLoaded && !catalogError" class="result-count">
                        {{ t('catl_summary', { sections: formatCount(visibleSections.length), products: formatCount(totalProducts) }) }}
                    </p>

                    <div v-if="!catalogLoaded" class="sections-grid" aria-busy="true">
                        <div v-for="n in 8" :key="n" class="section-card skeleton">
                            <span class="skeleton-block icon"></span>
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
                        <article v-for="section in visibleSections" :key="section.id" class="section-card">
                            <router-link :to="`/category/${section.slug}`" class="section-link">
                                <span v-if="section.image" class="section-image">
                                    <img :src="getImageUrl(section.image)" :alt="$p(section, 'name')" loading="lazy" decoding="async">
                                </span>
                                <span v-else class="section-icon"><i class="fas" :class="section.icon || 'fa-cube'"></i></span>
                                <span class="section-text">
                                    <h3>{{ $p(section, 'name') }}</h3>
                                    <span class="section-count">{{ t('catl_products_n', { count: formatCount(section.product_count) }) }}</span>
                                </span>
                                <i :class="isRtl ? 'fas fa-chevron-left' : 'fas fa-chevron-right'" class="section-arrow"></i>
                            </router-link>

                            <p v-if="$p(section, 'description')" class="section-desc">{{ $p(section, 'description') }}</p>

                            <div v-if="section.children.length" class="section-children">
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
import { useListingQuery } from '@/Composables/useListingQuery';
import ProductListingCard from '@/components/public/ProductListingCard.vue';
import ListingPagination from '@/components/public/ListingPagination.vue';
import ListingToolbar from '@/components/public/ListingToolbar.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

// SEO <head> for this page is fully covered by PublicLayout's route defaults
// (localized categories title/description matching the server).

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

const CHILDREN_SHOWN = 8;

const loadCategories = async () => {
    catalogError.value = false;
    try {
        const res = await axios.get('/api/v1/categories');
        if (!res.data?.success) throw new Error('Unexpected response');
        categories.value = res.data.data || [];
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
const normalize = (text) => String(text || '').toLocaleLowerCase();
const matches = (cat) => {
    const term = normalize(finderTerm.value);
    return normalize(cat.name_ar).includes(term) || normalize(cat.name_en).includes(term);
};

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
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 1.5rem;
}

.section-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03);
    border-radius: 24px;
    transition: box-shadow 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
}

.section-card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent);
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .section-card {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.section-link {
    display: flex;
    align-items: center;
    gap: 14px;
    color: inherit;
    text-decoration: none;
}

.section-link:focus-visible {
    outline: 2px solid var(--mobile-primary);
    outline-offset: 4px;
    border-radius: 12px;
}

.section-image,
.section-icon {
    flex: none;
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: color-mix(in srgb, var(--mobile-primary) 10%, #fff);
    color: var(--mobile-primary);
    font-size: 1.6rem;
}

[data-theme="dark"] .section-image,
[data-theme="dark"] .section-icon {
    background: rgba(255, 255, 255, 0.06);
}

.section-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.section-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.section-text h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e293b;
}

[data-theme="dark"] .section-text h3 {
    color: #f1f5f9;
}

.section-link:hover h3 {
    color: var(--mobile-primary);
}

.section-count {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--mobile-primary);
}

.section-arrow {
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.section-link:hover .section-arrow {
    transform: translateX(var(--nudge, 3px));
}

[dir='rtl'] .section-link:hover .section-arrow {
    --nudge: -3px;
}

.section-desc {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.section-children {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 12px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .section-children {
    border-color: rgba(255, 255, 255, 0.08);
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

.skeleton-block.icon { width: 64px; height: 64px; border-radius: 16px; }
.skeleton-block.line { height: 14px; width: 55%; }
.skeleton-block.line.wide { width: 80%; height: 18px; }

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
    }

    .finder-submit {
        flex: 1 1 100%;
    }
}
</style>
