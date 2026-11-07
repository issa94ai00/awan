<template>
    <section class="pcat" :class="{ 'is-compact': compact }">
        <header class="pcat-head">
            <div class="pcat-search">
                <i v-if="!loading" class="fas fa-magnifying-glass pcat-search-icon"></i>
                <i v-else class="fas fa-circle-notch fa-spin pcat-search-icon is-busy"></i>
                <input
                    ref="inputRef"
                    v-model="query"
                    type="search"
                    class="pcat-search-input"
                    :placeholder="placeholder || $t('pf_catalog_search')"
                    autocomplete="off"
                    @input="onInput"
                    @keydown.enter.prevent="onEnter"
                    @keydown.esc.prevent="clearQuery"
                />
                <button v-if="query" type="button" class="pcat-clear" :title="$t('clear')" @click="clearQuery">
                    <i class="fas fa-xmark"></i>
                </button>
                <kbd class="pcat-kbd">F2</kbd>
            </div>
            <button type="button" class="pcat-new" :title="`${$t('pqa_title')} (F4)`" @click="$emit('create', query.trim())">
                <i class="fas fa-plus"></i>
                <span>{{ $t('pqa_button') }}</span>
            </button>
        </header>

        <!-- Filters: category (searched on the server, with its sub-categories)
             and how much is left on the shelf, which is what a buyer restocks by. -->
        <div class="pcat-filters">
            <div class="pcat-stock" role="radiogroup" :aria-label="$t('pf_stock_filter')">
                <button
                    v-for="s in STOCK_FILTERS"
                    :key="s.value"
                    type="button"
                    class="pcat-seg"
                    :class="[{ 'is-active': stockLevel === s.value }, s.tone]"
                    @click="setStockLevel(s.value)"
                >
                    <i v-if="s.icon" class="fas" :class="s.icon"></i>
                    {{ $t(s.label) }}
                </button>
            </div>
            <el-select
                v-if="categories.length"
                v-model="categoryId"
                class="pcat-category"
                size="small"
                filterable
                clearable
                :placeholder="$t('all_categories')"
                @change="refresh"
            >
                <el-option
                    v-for="c in categories"
                    :key="c.id"
                    :label="c.label"
                    :value="c.id"
                    :class="{ 'pcat-sub-option': c.parent_id }"
                />
            </el-select>
        </div>

        <div v-if="highlightPicks && highlightPicks.size && !query && !onlyHighlighted" class="pcat-hint-bar">
            <button type="button" class="pcat-link" @click="onlyHighlighted = true">
                <i class="fas fa-file-signature"></i> {{ highlightLabel }}
            </button>
        </div>
        <div v-else-if="onlyHighlighted" class="pcat-hint-bar">
            <span><i class="fas fa-filter"></i> {{ highlightLabel }}</span>
            <button type="button" class="pcat-link" @click="onlyHighlighted = false">{{ $t('pf_show_all') }}</button>
        </div>

        <div ref="listRef" class="pcat-list">
            <div v-if="loading && !groups.length" class="pcat-state">
                <i class="fas fa-circle-notch fa-spin"></i>
                <span>{{ $t('loading') }}…</span>
            </div>

            <div v-else-if="!groups.length" class="pcat-state">
                <i class="fas fa-box-open pcat-state-icon"></i>
                <p>{{ $t('po_no_products_found') }}</p>
                <button type="button" class="pcat-create-cta" @click="$emit('create', query.trim())">
                    <i class="fas fa-plus"></i>
                    <span v-if="query.trim()">{{ $t('pqa_add_named', { name: query.trim() }) }}</span>
                    <span v-else>{{ $t('pqa_title') }}</span>
                </button>
            </div>

            <template v-else>
                <article
                    v-for="g in groups"
                    :key="g.key"
                    class="pcat-card"
                    :class="{ 'is-in': groupCount(g) > 0, 'is-flagged': groupFlagged(g) }"
                >
                    <div
                        class="pcat-card-main"
                        :class="{ 'is-pickable': !g.hasVariants }"
                        @click="!g.hasVariants && pick(g.product)"
                    >
                        <div class="pcat-thumb">
                            <EntityImage :src="imageOf(g.product)" type="product" :size="compact ? 40 : 48" shape="square" />
                            <span v-if="groupCount(g) > 0" class="pcat-thumb-badge">{{ groupCount(g) }}</span>
                        </div>
                        <div class="pcat-info">
                            <div class="pcat-name" :title="baseName(g.product)">{{ baseName(g.product) }}</div>
                            <div class="pcat-meta">
                                <span v-if="!g.hasVariants && g.product.sku" class="pcat-sku" dir="ltr">{{ g.product.sku }}</span>
                                <span v-if="g.hasVariants" class="pcat-chip">
                                    <i class="fas fa-layer-group"></i> {{ $t('po_variants_count', g.rows.length) }}
                                </span>
                                <span v-if="categoryName(g.product)" class="pcat-chip is-muted">{{ categoryName(g.product) }}</span>
                                <span v-if="!g.hasVariants && isFlagged(g.product)" class="pcat-chip is-flag">
                                    <i class="fas fa-file-signature"></i> {{ highlightShort }}
                                </span>
                            </div>
                            <div v-if="!g.hasVariants" class="pcat-figures">
                                <span class="pcat-cost">{{ money(costOf(g.product)) }}</span>
                                <span v-if="stockOf(g.product) != null" class="pcat-stock-val" :class="stockTone(g.product)">
                                    <i class="fas fa-warehouse"></i> {{ stockOf(g.product) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="!g.hasVariants" class="pcat-action">
                            <AddQtyControl
                                :count="countOf(g.product)"
                                :label="addLabel || $t('add')"
                                size="sm"
                                @add="(d) => $emit('add', g.product, d)"
                                @set="(n) => $emit('set', g.product, n)"
                                @commit="(n) => $emit('commit', g.product, n)"
                            />
                        </div>
                    </div>

                    <!-- A product with sizes: each size is its own line, so each
                         gets its own stepper rather than one for the product. -->
                    <div v-if="g.hasVariants" class="pcat-variants">
                        <div
                            v-for="p in g.rows"
                            :key="optionKey(p)"
                            class="pcat-variant is-pickable"
                            :class="{ 'is-in': countOf(p) > 0, 'is-flagged': isFlagged(p) }"
                            @click="pick(p)"
                        >
                            <div class="pcat-variant-info">
                                <span class="pcat-variant-label" dir="ltr">{{ p.variant_label || p.sku || baseName(p) }}</span>
                                <span class="pcat-variant-figs">
                                    <span class="pcat-cost">{{ money(costOf(p)) }}</span>
                                    <span v-if="stockOf(p) != null" class="pcat-stock-val" :class="stockTone(p)">{{ stockOf(p) }}</span>
                                    <span v-if="isFlagged(p)" class="pcat-chip is-flag">{{ highlightShort }}</span>
                                </span>
                            </div>
                            <AddQtyControl
                                :count="countOf(p)"
                                :label="$t('add')"
                                size="sm"
                                @add="(d) => $emit('add', p, d)"
                                @set="(n) => $emit('set', p, n)"
                                @commit="(n) => $emit('commit', p, n)"
                            />
                        </div>
                    </div>
                </article>

                <button v-if="hasMore" type="button" class="pcat-more" :disabled="loading" @click="loadMore">
                    <i class="fas" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-angles-down'"></i>
                    {{ $t('pf_load_more') }}
                </button>
            </template>
        </div>
    </section>
</template>

<script setup>
/**
 * The product catalogue beside a purchase order or goods receipt: search by
 * name, code or barcode, filter by category or by what is running low, and
 * add with a stepper per product (per size, for a product with sizes).
 *
 * It owns its own searching; the document owns its lines. What a row shows
 * as "already on the document" comes from `countOf`, and every add or change
 * goes back to the form as an event, so the form stays the one place that
 * decides what a line is (its price, merging, the request it fills).
 */
import { ref, computed, onMounted } from 'vue';
import { productsApi } from '@/api/products';
import { formatMoney } from '@/utils/currency';
import { resolveImageUrl } from '@/utils/productImages';
import { optionKey, baseName } from '@/utils/productPick';
import EntityImage from '@/components/admin/EntityImage.vue';
import AddQtyControl from '@/components/admin/purchases/AddQtyControl.vue';

const props = defineProps({
    // (row) => quantity of that product/size already on the document.
    countOf: { type: Function, required: true },
    // Category options as [{ id, parent_id, label }].
    categories: { type: Array, default: () => [] },
    // Picks to mark (e.g. what the linked purchase request asked for).
    highlightPicks: { type: Set, default: null },
    highlightLabel: { type: String, default: '' },
    highlightShort: { type: String, default: '' },
    // Rows standing in for the highlighted picks, so "show only" works
    // without a search (the request's lines as search rows).
    highlightRows: { type: Array, default: () => [] },
    addLabel: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['add', 'set', 'commit', 'create', 'rows']);

const PAGE = 40;
const STOCK_FILTERS = [
    { value: '', label: 'pf_stock_all' },
    { value: 'low', label: 'pf_stock_low', icon: 'fa-arrow-trend-down', tone: 'tone-warn' },
    { value: 'out', label: 'pf_stock_out', icon: 'fa-ban', tone: 'tone-danger' },
];

const query = ref('');
const categoryId = ref('');
const stockLevel = ref('');
const onlyHighlighted = ref(false);
const rows = ref([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const inputRef = ref(null);
const listRef = ref(null);

let timer = null;
// Each fetch is numbered so a slow earlier response cannot overwrite the
// results of the search typed after it.
let seq = 0;

const money = (v) => formatMoney(v);
const imageOf = (p) => resolveImageUrl(p?.image_main || p?.image || p?.primary_image_url || p?.image_url || '');
const stockOf = (p) => p?.stock_quantity ?? p?.stock ?? null;
const costOf = (p) => (Number(p?.cost_price) > 0 ? p.cost_price : p?.price);
const categoryName = (p) => p?.category?.name_ar || p?.category_name || '';
const stockTone = (p) => {
    const s = Number(stockOf(p));
    if (s <= 0) return 'is-out';
    const min = Number(p?.min_stock) || 10;
    return s <= min ? 'is-low' : 'is-ok';
};

const isFlagged = (p) => Boolean(props.highlightPicks?.has(optionKey(p)));
const groupFlagged = (g) => g.rows.some(isFlagged);
const groupCount = (g) => g.rows.reduce((sum, r) => sum + (Number(props.countOf(r)) || 0), 0);

const params = (pageNo) => {
    const p = { per_page: PAGE, page: pageNo, expand_variants: 1 };
    if (query.value.trim()) p.search = query.value.trim();
    if (categoryId.value) p.category_id = categoryId.value;
    if (stockLevel.value) p.stock_level = stockLevel.value;
    return p;
};

const fetchPage = async (pageNo = 1) => {
    const mine = ++seq;
    loading.value = true;
    try {
        const res = await productsApi.getAll(params(pageNo));
        if (mine !== seq) return null;
        const data = res.data?.data || [];
        const meta = res.data?.meta || res.data?.pagination || {};
        lastPage.value = Number(meta.last_page) || (data.length < PAGE ? pageNo : pageNo + 1);
        page.value = pageNo;
        rows.value = pageNo === 1 ? data : [...rows.value, ...data];
        emit('rows', data);
        return data;
    } catch {
        // Keep what was showing on a transient failure.
        return null;
    } finally {
        if (mine === seq) loading.value = false;
    }
};

const refresh = () => {
    clearTimeout(timer);
    timer = null;
    listRef.value?.scrollTo?.({ top: 0 });
    return fetchPage(1);
};

const loadMore = () => fetchPage(page.value + 1);
const hasMore = computed(() => !onlyHighlighted.value && page.value < lastPage.value);

const onInput = () => {
    clearTimeout(timer);
    onlyHighlighted.value = false;
    timer = setTimeout(() => {
        timer = null;
        refresh();
    }, 250);
};

const clearQuery = () => {
    if (!query.value) return;
    query.value = '';
    refresh();
};

const setStockLevel = (value) => {
    stockLevel.value = value;
    refresh();
};

// One card per product; its sizes inside it.
const groups = computed(() => {
    const source = onlyHighlighted.value
        ? props.highlightRows
        : rows.value;
    const map = new Map();
    source.forEach((row) => {
        if (!map.has(row.id)) map.set(row.id, { key: String(row.id), product: row, rows: [] });
        map.get(row.id).rows.push(row);
    });
    return [...map.values()].map((g) => ({ ...g, hasVariants: g.rows.some((r) => r.variant_id) }));
});

/**
 * Enter adds what the search points at without doubt: a size or product whose
 * code or barcode is exactly what was typed (a scanner ends with Enter), or
 * the only row found. Several sizes found is the operator's choice to make.
 * Pressed before the typed search came back, it waits for it.
 */
const onEnter = async () => {
    const q = query.value.trim();
    if (!q) return;
    let list = rows.value;
    if (timer || loading.value) {
        clearTimeout(timer);
        timer = null;
        const fresh = await fetchPage(1);
        // Failed, or overtaken by a newer search: that is not "nothing
        // found", so it must not offer to create the product.
        if (!fresh || query.value.trim() !== q) return;
        list = fresh;
    }
    const lower = q.toLowerCase();
    const exact = list.find((r) => [r.sku, r.barcode, r.variant_sku, r.variant_barcode]
        .some((code) => code && String(code).toLowerCase() === lower));
    const target = exact || (list.length === 1 ? list[0] : null);
    if (target) {
        emit('add', target, 1);
        query.value = '';
        refresh();
        return;
    }
    if (!list.length) emit('create', q);
};

// A click on a product (not on its stepper) adds one of it the first time;
// once it is on the document the stepper beside it sets the count.
const pick = (row) => {
    if (!(Number(props.countOf(row)) > 0)) emit('add', row, 1);
};

const focus = () => {
    inputRef.value?.focus();
    inputRef.value?.select?.();
};

onMounted(() => fetchPage(1));

defineExpose({ focus, refresh });
</script>

<style scoped src="./purchase-catalog.css"></style>

<style>
.el-select-dropdown__item.pcat-sub-option {
    padding-inline-start: 2rem;
    font-size: 0.85rem;
}
</style>
