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

<style scoped>
.pcat {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    background: #fff;
    border: 1px solid var(--el-border-color-lighter, #e5e7eb);
    border-radius: 14px;
    overflow: hidden;
}

.pcat-head {
    display: flex;
    gap: 0.5rem;
    padding: 0.75rem;
    border-bottom: 1px solid var(--el-border-color-lighter, #e5e7eb);
    background: var(--el-fill-color-lighter, #fafafa);
}

.pcat-search {
    position: relative;
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
}

.pcat-search-icon {
    position: absolute;
    inset-inline-start: 0.8rem;
    color: var(--el-text-color-placeholder, #9ca3af);
    font-size: 0.9rem;
    pointer-events: none;
}

.pcat-search-icon.is-busy {
    color: var(--el-color-primary);
}

.pcat-search-input {
    width: 100%;
    height: 42px;
    padding-inline: 2.3rem 4.2rem;
    border: 1.5px solid var(--el-border-color, #dcdfe6);
    border-radius: 10px;
    font: inherit;
    font-size: 0.92rem;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.pcat-search-input::-webkit-search-cancel-button {
    display: none;
}

.pcat-search-input:focus {
    border-color: var(--el-color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 18%, transparent);
}

.pcat-clear {
    all: unset;
    position: absolute;
    inset-inline-end: 2.6rem;
    cursor: pointer;
    color: var(--el-text-color-secondary);
    padding: 0.25rem;
}

.pcat-kbd {
    position: absolute;
    inset-inline-end: 0.6rem;
    font-size: 0.68rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid var(--el-border-color);
    color: var(--el-text-color-secondary);
    background: var(--el-fill-color-light);
    font-family: ui-monospace, monospace;
}

.pcat-new {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 0.9rem;
    height: 42px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--btn-ink-success, #047857);
    background: #ecfdf5;
    border: 1.5px solid #a7f3d0;
    white-space: nowrap;
}

.pcat-new:hover {
    background: #d1fae5;
}

.pcat-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid var(--el-border-color-lighter, #e5e7eb);
    flex-wrap: wrap;
}

.pcat-stock {
    display: inline-flex;
    padding: 2px;
    border-radius: 8px;
    background: var(--el-fill-color, #f0f2f5);
}

.pcat-seg {
    all: unset;
    cursor: pointer;
    font-size: 0.78rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    color: var(--el-text-color-regular);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pcat-seg.is-active {
    background: #fff;
    color: var(--el-color-primary);
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.pcat-seg.is-active.tone-warn {
    color: #b45309;
}

.pcat-seg.is-active.tone-danger {
    color: #b91c1c;
}

.pcat-category {
    flex: 1;
    min-width: 150px;
}

.pcat-hint-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.75rem;
    background: color-mix(in srgb, var(--el-color-primary) 7%, white);
    font-size: 0.8rem;
    color: var(--el-color-primary);
}

.pcat-link {
    all: unset;
    cursor: pointer;
    font-weight: 600;
    color: var(--el-color-primary);
}

.pcat-link:hover {
    text-decoration: underline;
}

.pcat-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pcat-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    padding: 2.5rem 1rem;
    color: var(--el-text-color-secondary);
    text-align: center;
}

.pcat-state p {
    margin: 0;
}

.pcat-state-icon {
    font-size: 2rem;
    opacity: 0.45;
}

.pcat-create-cta {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    gap: 0.4rem;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: var(--el-color-primary);
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
}

.pcat-card {
    border: 1px solid var(--el-border-color-lighter, #e5e7eb);
    border-radius: 12px;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.pcat-card:hover {
    border-color: color-mix(in srgb, var(--el-color-primary) 35%, white);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}

.pcat-card.is-in {
    border-color: color-mix(in srgb, var(--el-color-success) 55%, white);
    background: color-mix(in srgb, var(--el-color-success) 4%, white);
}

.pcat-card.is-flagged:not(.is-in) {
    border-inline-start: 3px solid var(--el-color-primary);
}

.pcat-card-main {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.65rem;
}

.pcat-thumb {
    position: relative;
    flex-shrink: 0;
}

.pcat-thumb-badge {
    position: absolute;
    top: -6px;
    inset-inline-end: -6px;
    min-width: 20px;
    height: 20px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--el-color-success);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    display: grid;
    place-items: center;
    border: 2px solid #fff;
}

.pcat-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pcat-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--el-text-color-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pcat-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    align-items: center;
}

.pcat-sku {
    font-family: ui-monospace, monospace;
    font-size: 0.72rem;
    color: var(--el-text-color-secondary);
}

.pcat-chip {
    font-size: 0.68rem;
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--el-color-primary) 10%, white);
    color: var(--el-color-primary);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
}

.pcat-chip.is-muted {
    background: var(--el-fill-color-light, #f5f7fa);
    color: var(--el-text-color-secondary);
}

.pcat-chip.is-flag {
    background: #eef2ff;
    color: #4338ca;
}

.pcat-figures,
.pcat-variant-figs {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.78rem;
}

.pcat-cost {
    font-weight: 700;
    color: var(--el-text-color-primary);
    font-variant-numeric: tabular-nums;
}

.pcat-stock-val {
    font-variant-numeric: tabular-nums;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
}

.pcat-stock-val i {
    font-size: 0.65rem;
    opacity: 0.7;
}

.pcat-stock-val.is-ok {
    color: #047857;
}

.pcat-stock-val.is-low {
    color: #b45309;
}

.pcat-stock-val.is-out {
    color: #b91c1c;
}

.pcat-action {
    flex-shrink: 0;
}

.is-pickable {
    cursor: pointer;
}

.pcat-variants {
    border-top: 1px dashed var(--el-border-color-lighter, #e5e7eb);
    padding: 0.35rem 0.5rem 0.5rem;
    display: grid;
    gap: 0.3rem;
}

.pcat-variant {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.3rem 0.45rem;
    border-radius: 8px;
}

.pcat-variant:hover {
    background: var(--el-fill-color-lighter, #fafafa);
}

.pcat-variant.is-in {
    background: color-mix(in srgb, var(--el-color-success) 8%, white);
}

.pcat-variant.is-flagged {
    box-shadow: inset 3px 0 0 #6366f1;
}

.pcat-variant-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pcat-variant-label {
    font-weight: 700;
    font-size: 0.82rem;
    text-align: start;
}

.pcat-more {
    all: unset;
    cursor: pointer;
    text-align: center;
    padding: 0.55rem;
    border-radius: 10px;
    border: 1px dashed var(--el-border-color);
    color: var(--el-color-primary);
    font-size: 0.82rem;
    font-weight: 600;
}

.pcat-more:hover {
    background: var(--el-fill-color-lighter);
}

.pcat.is-compact .pcat-list {
    max-height: 420px;
}

@media (max-width: 640px) {
    .pcat-new span {
        display: none;
    }
}
</style>

<style>
.el-select-dropdown__item.pcat-sub-option {
    padding-inline-start: 2rem;
    font-size: 0.85rem;
}
</style>
