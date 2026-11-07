<template>
    <section class="pcat">
        <header class="pcat-head">
            <div class="pcat-search">
                <i v-if="!loading" class="fas fa-magnifying-glass pcat-search-icon"></i>
                <i v-else class="fas fa-circle-notch fa-spin pcat-search-icon is-busy"></i>
                <input
                    ref="inputRef"
                    :value="query"
                    type="search"
                    class="pcat-search-input"
                    :placeholder="placeholder || $t('pf_catalog_search')"
                    autocomplete="off"
                    @input="onInput"
                    @keydown.enter.prevent="$emit('enter')"
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

        <!-- Filters: category (searched on the server) and what is on the
             shelf, which is what a seller sells from. -->
        <div class="pcat-filters">
            <div class="pcat-stock" role="radiogroup" :aria-label="$t('pf_stock_filter')">
                <button
                    v-for="s in STOCK_FILTERS"
                    :key="s.value"
                    type="button"
                    class="pcat-seg"
                    :class="[{ 'is-active': stockLevel === s.value }, s.tone]"
                    @click="stockLevel = s.value"
                >
                    <i v-if="s.icon" class="fas" :class="s.icon"></i>
                    {{ $t(s.label) }}
                </button>
            </div>
            <el-select
                v-if="categories.length"
                :model-value="categoryId"
                class="pcat-category"
                size="small"
                filterable
                clearable
                :placeholder="$t('all_categories')"
                @update:model-value="onCategory"
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

        <div class="pcat-list">
            <div v-if="loading && !shown.length" class="pcat-state">
                <i class="fas fa-circle-notch fa-spin"></i>
                <span>{{ $t('loading') }}…</span>
            </div>

            <div v-else-if="!shown.length" class="pcat-state">
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
                    v-for="g in shown"
                    :key="g.key"
                    class="pcat-card"
                    :class="{ 'is-in': groupCount(g) > 0 }"
                >
                    <div
                        class="pcat-card-main"
                        :class="{ 'is-pickable': !g.hasVariants && !(countOf(g.product) > 0) }"
                        @click="!g.hasVariants && pick(g.product)"
                    >
                        <div class="pcat-thumb">
                            <EntityImage :src="imageOf(g.product)" type="product" :size="48" shape="square" />
                            <span v-if="groupCount(g) > 0" class="pcat-thumb-badge">{{ groupCount(g) }}</span>
                        </div>
                        <div class="pcat-info">
                            <div class="pcat-name" :title="titleOf(g)">{{ titleOf(g) }}</div>
                            <div class="pcat-meta">
                                <span v-if="!g.hasVariants && g.product.sku" class="pcat-sku" dir="ltr">{{ g.product.sku }}</span>
                                <span v-if="g.hasVariants" class="pcat-chip">
                                    <i class="fas fa-layer-group"></i> {{ $t('po_variants_count', g.rows.length) }}
                                </span>
                                <span v-if="categoryName(g.product)" class="pcat-chip is-muted">{{ categoryName(g.product) }}</span>
                            </div>
                            <div v-if="!g.hasVariants" class="pcat-figures">
                                <span class="pcat-cost">{{ money(priceOf(g.product)) }}</span>
                                <span v-if="oldPrice(g.product)" class="scat-old">{{ money(oldPrice(g.product)) }}</span>
                                <span v-if="stockOf(g.product) != null" class="pcat-stock-val" :class="stockTone(g.product)">
                                    <i class="fas fa-warehouse"></i> {{ stockOf(g.product) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <template v-if="!g.hasVariants">
                        <WarehouseStockChips
                            v-if="Array.isArray(g.product.warehouse_stock)"
                            class="scat-wh"
                            :stock="g.product.warehouse_stock"
                            :default-id="defaultWarehouseId"
                            compact
                        />
                        <div class="scat-actions">
                            <OrderCardActions
                                :count="countOf(g.product)"
                                :pending="pendingOf(g.product)"
                                :over-pending="isOver(g.product, pendingOf(g.product))"
                                :over-count="isOver(g.product, countOf(g.product))"
                                :add-label="addLabel || $t('add')"
                                :purchase-label="purchaseLabel"
                                :purchase-highlight="Number(stockOf(g.product)) <= 0"
                                :show-purchase="showPurchase"
                                v-on="actionsOf(g.product)"
                            />
                        </div>
                    </template>

                    <!-- A product with sizes: each size is its own line, so each
                         gets its own controls rather than one for the product. -->
                    <div v-else class="pcat-variants">
                        <div
                            v-for="p in g.rows"
                            :key="p.listing_key || optionKey(p)"
                            class="pcat-variant scat-variant"
                            :class="{ 'is-in': countOf(p) > 0 }"
                        >
                            <div class="pcat-variant-info">
                                <span class="pcat-variant-label" dir="ltr">{{ p.variant_label || p.sku || nameOf(p) }}</span>
                                <span class="pcat-variant-figs">
                                    <span class="pcat-cost">{{ money(priceOf(p)) }}</span>
                                    <span v-if="stockOf(p) != null" class="pcat-stock-val" :class="stockTone(p)">{{ stockOf(p) }}</span>
                                    <span v-if="p.sku && p.variant_label" class="pcat-sku" dir="ltr">{{ p.sku }}</span>
                                </span>
                                <WarehouseStockChips
                                    v-if="Array.isArray(p.warehouse_stock)"
                                    :stock="p.warehouse_stock"
                                    :default-id="defaultWarehouseId"
                                    compact
                                />
                            </div>
                            <OrderCardActions
                                compact
                                :count="countOf(p)"
                                :pending="pendingOf(p)"
                                :over-pending="isOver(p, pendingOf(p))"
                                :over-count="isOver(p, countOf(p))"
                                :add-label="$t('add')"
                                :purchase-label="purchaseLabel"
                                :purchase-highlight="Number(stockOf(p)) <= 0"
                                :show-purchase="showPurchase"
                                v-on="actionsOf(p)"
                            />
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <footer class="scat-foot">
            <span><kbd>Enter</kbd> {{ $t('add') }}</span>
            <span><kbd>F4</kbd> {{ $t('pqa_button') }}</span>
            <span v-if="shown.length" class="scat-foot-count">{{ resultCount }}</span>
        </footer>
    </section>
</template>

<script setup>
/**
 * The product catalogue beside a sales invoice or sales order — the same panel
 * as the purchase forms' (PurchaseCatalogPanel), for selling: list price,
 * stock and where it sits, and the quantity steppers of OrderCardActions.
 *
 * It only draws. The form owns the search (query, category, results, Enter)
 * and the lines; every click goes back to it as an event with the row.
 */
import { ref, computed } from 'vue';
import { formatMoney } from '@/utils/currency';
import { resolveImageUrl } from '@/utils/productImages';
import { optionKey, baseName } from '@/utils/productPick';
import EntityImage from '@/components/admin/EntityImage.vue';
import OrderCardActions from '@/components/admin/sales/OrderCardActions.vue';
import WarehouseStockChips from '@/components/admin/sales/WarehouseStockChips.vue';

const props = defineProps({
    // Search rows grouped by product: [{ key, product, rows, hasVariants }].
    groups: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    query: { type: String, default: '' },
    categoryId: { type: [Number, String], default: null },
    // Category options as [{ id, parent_id, label }].
    categories: { type: Array, default: () => [] },
    // (row) => quantity of that product/size already on the document.
    countOf: { type: Function, required: true },
    // (row) => quantity waiting to be added from this row.
    pendingOf: { type: Function, required: true },
    // (row, qty) => whether qty is more than the shelf holds.
    isOver: { type: Function, default: () => false },
    // (row) => the price a line for it starts at.
    priceOf: { type: Function, default: (row) => row?.price },
    nameOf: { type: Function, default: (row) => row?.name_ar || row?.name || '' },
    defaultWarehouseId: { type: [Number, String], default: null },
    showPurchase: { type: Boolean, default: false },
    purchaseLabel: { type: String, default: '' },
    addLabel: { type: String, default: '' },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits([
    'update:query', 'input', 'enter', 'clear',
    'update:categoryId', 'category-change',
    'create',
    'add', 'bump-pending', 'set-pending', 'blur-pending', 'decrement', 'set', 'commit', 'purchase',
]);

const STOCK_FILTERS = [
    { value: '', label: 'pf_stock_all' },
    { value: 'in', label: 'in_stock', icon: 'fa-check', tone: 'tone-ok' },
    { value: 'out', label: 'pf_stock_out', icon: 'fa-ban', tone: 'tone-danger' },
];

const inputRef = ref(null);
const stockLevel = ref('');

const money = (v) => formatMoney(v);
const imageOf = (p) => resolveImageUrl(p?.image_main || p?.image || p?.images?.[0]?.url || p?.images?.[0]?.path || p?.primary_image_url || '');
const stockOf = (p) => (p?.stock_quantity ?? p?.stock ?? null);
const categoryName = (p) => p?.category?.name_ar || p?.category_name || '';
const oldPrice = (p) => (p?.has_sale && p?.price && Number(p.price) !== Number(props.priceOf(p)) ? p.price : null);
const titleOf = (g) => (g.hasVariants ? baseName(g.product) || props.nameOf(g.product) : props.nameOf(g.product));
const stockTone = (p) => {
    const s = Number(stockOf(p));
    if (s <= 0) return 'is-out';
    return s <= 5 ? 'is-low' : 'is-ok';
};

const groupCount = (g) => g.rows.reduce((sum, r) => sum + (Number(props.countOf(r)) || 0), 0);

// The stock filter works on what the search brought back: a product with
// sizes stays while any of its sizes matches, showing only those.
const shown = computed(() => {
    if (!stockLevel.value) return props.groups;
    const keep = (r) => {
        const s = stockOf(r);
        if (s == null) return stockLevel.value === 'in';
        return stockLevel.value === 'in' ? Number(s) > 0 : Number(s) <= 0;
    };
    return props.groups
        .map((g) => ({ ...g, rows: g.rows.filter(keep) }))
        .filter((g) => g.rows.length)
        .map((g) => ({ ...g, product: g.rows[0] }));
});

const resultCount = computed(() => shown.value.reduce((n, g) => n + g.rows.length, 0));

const actionsOf = (row) => ({
    add: (qty) => emit('add', row, qty),
    'bump-pending': (delta) => emit('bump-pending', row, delta),
    'set-pending': (raw) => emit('set-pending', row, raw),
    'blur-pending': (el) => emit('blur-pending', row, el),
    decrement: () => emit('decrement', row),
    set: (raw) => emit('set', row, raw),
    commit: (el) => emit('commit', row, el),
    purchase: () => emit('purchase', row),
});

const onInput = (e) => {
    emit('update:query', e.target.value);
    emit('input');
};

const clearQuery = () => {
    if (!props.query) return;
    emit('update:query', '');
    emit('clear');
};

const onCategory = (value) => {
    emit('update:categoryId', value ?? null);
    emit('category-change', value ?? null);
};

// A click on a product (not on its controls) adds it the first time; once it
// is on the document the stepper beside it sets the count.
const pick = (row) => {
    if (!(Number(props.countOf(row)) > 0)) emit('add', row, props.pendingOf(row));
};

const focus = () => {
    inputRef.value?.focus();
    inputRef.value?.select?.();
};

defineExpose({ focus });
</script>

<style scoped src="../purchases/purchase-catalog.css"></style>

<style scoped>
.pcat-seg.is-active.tone-ok {
    color: #047857;
}

.pcat-card-main.is-pickable:hover .pcat-name {
    color: var(--el-color-primary);
}

.scat-old {
    font-size: 0.72rem;
    color: var(--el-text-color-secondary);
    text-decoration: line-through;
}

.scat-wh {
    padding: 0 0.65rem 0.35rem;
}

.scat-actions {
    display: flex;
    justify-content: flex-end;
    padding: 0 0.65rem 0.6rem;
}

.scat-variant {
    align-items: flex-start;
    flex-wrap: wrap;
}

.scat-variant .pcat-variant-info {
    flex: 1;
    gap: 0.2rem;
}

.scat-foot {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.45rem 0.75rem;
    border-top: 1px solid var(--el-border-color-lighter, #e5e7eb);
    background: var(--el-fill-color-lighter, #fafafa);
    font-size: 0.72rem;
    color: var(--el-text-color-secondary);
}

.scat-foot kbd {
    font-family: ui-monospace, monospace;
    font-size: 0.66rem;
    padding: 0.05rem 0.35rem;
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
    background: #fff;
}

.scat-foot-count {
    margin-inline-start: auto;
    font-weight: 700;
}
</style>
