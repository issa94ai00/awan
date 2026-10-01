<template>
    <div ref="rootRef" class="product-search" @keydown.esc="open = false">
        <div class="search-row">
            <el-input
                ref="inputRef"
                v-model="query"
                size="large"
                clearable
                class="search-input"
                :placeholder="t('search_product_by_name_sku_barcode')"
                @input="search"
                @focus="open = true"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="pickHighlighted"
            >
                <template #prefix>
                    <i v-if="loading" class="fas fa-circle-notch fa-spin"></i>
                    <i v-else class="fas fa-magnifying-glass"></i>
                </template>
                <template #suffix>
                    <kbd class="key-hint" :title="t('focus_product_search')">F2</kbd>
                </template>
            </el-input>

            <el-select
                v-model="categoryId"
                size="large"
                clearable
                filterable
                class="search-filter"
                :placeholder="t('all_categories')"
                @change="search"
            >
                <el-option v-for="c in categoryOptions" :key="c.id" :value="c.id" :label="c.label">
                    <span :class="{ 'sub-category': c.parent_id }">{{ c.label }}</span>
                </el-option>
            </el-select>

            <el-select v-model="stockFilter" size="large" clearable class="search-filter search-filter--stock" :placeholder="t('nav_inventory')" @change="search">
                <el-option :label="t('in_stock')" value="available" />
                <el-option :label="t('low')" value="low" />
                <el-option :label="t('out_of_stock_short')" value="out" />
            </el-select>
        </div>

        <!-- Under the box, as wide as it: the old list was placed by script
             against the window and drifted off to one side. -->
        <div v-if="open && (results.length || loading || searched)" class="results" role="listbox">
            <div v-if="loading && !results.length" class="results-state">
                <i class="fas fa-circle-notch fa-spin"></i> {{ t('searching_products') }}
            </div>
            <div v-else-if="!results.length" class="results-state">
                <i class="fas fa-box-open"></i>
                <div>
                    <strong>{{ t('no_matching_results') }}</strong>
                    <span>{{ t('try_other_search_terms') }}</span>
                </div>
            </div>
            <template v-else>
                <button
                    v-for="(product, index) in results"
                    :key="optionKey(product)"
                    type="button"
                    role="option"
                    class="result"
                    :class="{ 'is-active': index === highlighted }"
                    :aria-selected="index === highlighted"
                    @mouseenter="highlighted = index"
                    @mousedown.prevent
                    @click="pick(product)"
                >
                    <span class="result-thumb">
                        <img v-if="product.image_main" :src="getImageUrl(product.image_main)" alt="" loading="lazy" />
                        <i v-else class="fas fa-box"></i>
                    </span>
                    <span class="result-main">
                        <span class="result-name">
                            {{ baseName(product) || product.name_en }}
                            <VariantChip v-if="product.variant_id" :label="product.variant_label" />
                        </span>
                        <span class="result-meta">
                            <span v-if="product.sku" class="mono">{{ product.sku }}</span>
                            <span class="stock-pill" :class="stockClass(product)">{{ stockText(product) }}</span>
                            <span v-if="inOrder(product)" class="in-order"><i class="fas fa-check"></i> {{ t('sof_in_order', { n: inOrder(product) }) }}</span>
                        </span>
                    </span>
                    <span class="result-price">{{ formatCurrency(product.price) }}</span>
                </button>
                <div class="results-foot">
                    <span><kbd>↑</kbd><kbd>↓</kbd> {{ t('move_through_results') }}</span>
                    <span><kbd>Enter</kbd> {{ t('add_selected_product') }}</span>
                    <span><kbd>Esc</kbd> {{ t('close_search_list') }}</span>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { posApi } from '@/api/pos';
import { categoriesApi } from '@/api/categories';
import { getImageUrl } from '@/utils/imageUrl';
import { formatCurrency } from '@/utils/sales';
import { optionKey, baseName } from '@/utils/productPick';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

const emit = defineEmits(['added']);
const { t } = useI18n();
const order = useSalesOrderForm();

const rootRef = ref(null);
const inputRef = ref(null);
const query = ref('');
const categoryId = ref(null);
const stockFilter = ref(null);
const categories = ref([]);

const results = ref([]);
const loading = ref(false);
const searched = ref(false);
const open = ref(false);
const highlighted = ref(-1);

const LOW = 5;

// Sections first, each followed by its subcategories.
const categoryOptions = computed(() => {
    const name = (c) => c.name_ar || c.name_en || c.name;
    const roots = categories.value.filter((c) => !c.parent_id);
    return roots.flatMap((root) => [
        { id: root.id, parent_id: null, label: name(root) },
        ...categories.value.filter((c) => c.parent_id === root.id).map((c) => ({ id: c.id, parent_id: c.parent_id, label: name(c) })),
    ]);
});

const stockClass = (p) => (p.stock_quantity <= 0 ? 'is-out' : (p.stock_quantity <= LOW ? 'is-low' : 'is-ok'));
const stockText = (p) => (p.stock_quantity <= 0 ? t('out_of_stock') : t('units_available', { count: p.stock_quantity }));
const inOrder = (p) => order.lines.value.find((line) => line.key === optionKey(p))?.quantity || 0;

let timer = null;
let seq = 0;
// Enter before the results came back: a barcode scanner types the code and
// Enter in one burst, faster than the search can answer.
let enterPending = false;

const canSearch = () => query.value.trim().length >= 2 || !!categoryId.value;

const search = () => {
    clearTimeout(timer);
    enterPending = false;
    highlighted.value = -1;
    if (!canSearch()) {
        results.value = [];
        searched.value = false;
        loading.value = false;
        return;
    }
    loading.value = true;
    open.value = true;
    const mine = ++seq;
    timer = setTimeout(async () => {
        try {
            const { data } = await posApi.productLookup({
                q: query.value.trim() || undefined,
                category_id: categoryId.value || undefined,
                expand_variants: 1,
            });
            if (mine !== seq) return; // a later search has taken over
            let rows = Array.isArray(data?.data) ? data.data : [];
            if (stockFilter.value === 'available') rows = rows.filter((p) => p.stock_quantity > LOW);
            if (stockFilter.value === 'low') rows = rows.filter((p) => p.stock_quantity > 0 && p.stock_quantity <= LOW);
            if (stockFilter.value === 'out') rows = rows.filter((p) => p.stock_quantity <= 0);
            results.value = rows;
            highlighted.value = rows.length ? 0 : -1;

            if (enterPending) {
                enterPending = false;
                const hit = exactMatch() || (rows.length === 1 ? rows[0] : null);
                if (hit) pick(hit);
            }
        } catch {
            if (mine === seq) results.value = [];
        } finally {
            if (mine === seq) {
                loading.value = false;
                searched.value = true;
            }
        }
    }, 250);
};

/** A result whose code or barcode is exactly what was typed or scanned. */
const exactMatch = () => {
    const code = query.value.trim().toLowerCase();
    if (!code) return null;
    return results.value.find((p) => [p.sku, p.barcode].some((v) => v && String(v).toLowerCase() === code)) || null;
};

const move = (direction) => {
    open.value = true;
    if (!results.value.length) return;
    const last = results.value.length - 1;
    highlighted.value = direction > 0
        ? (highlighted.value >= last ? 0 : highlighted.value + 1)
        : (highlighted.value <= 0 ? last : highlighted.value - 1);
    rootRef.value?.querySelectorAll('.result')[highlighted.value]?.scrollIntoView({ block: 'nearest' });
};

const pickHighlighted = () => {
    if (loading.value) {
        enterPending = true;
        return;
    }
    const hit = exactMatch() || results.value[highlighted.value];
    if (hit) pick(hit);
};

const pick = (product) => {
    const line = order.addProduct(product);
    emit('added', line);
    // Ready for the next item: the text goes, the category stays.
    query.value = '';
    if (categoryId.value) {
        highlighted.value = results.value.indexOf(product);
    } else {
        results.value = [];
        searched.value = false;
        open.value = false;
    }
    focus();
};

const focus = () => inputRef.value?.focus();

const onDocumentClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) open.value = false;
};

onMounted(async () => {
    document.addEventListener('mousedown', onDocumentClick);
    try {
        const { data } = await categoriesApi.getAll({ per_page: 500 });
        categories.value = (Array.isArray(data?.data) ? data.data : data?.data?.data || []).filter((c) => c.is_active !== false);
    } catch {
        categories.value = [];
    }
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocumentClick);
    clearTimeout(timer);
});

defineExpose({ focus });
</script>

<style scoped>
.product-search { position: relative; }

.search-row { display: flex; gap: 0.5rem; }
.search-input { flex: 1 1 auto; min-width: 0; }
.search-filter { flex: 0 0 170px; }
.search-filter--stock { flex-basis: 130px; }
.sub-category { padding-inline-start: 1rem; color: #475569; }

.key-hint, kbd {
    font-family: inherit;
    font-size: 0.68rem;
    padding: 0 0.35rem;
    border: 1px solid #cbd5e1;
    border-bottom-width: 2px;
    border-radius: 4px;
    color: #64748b;
    background: #f8fafc;
    line-height: 1.5;
}

.results {
    position: absolute;
    inset-inline: 0;
    top: calc(100% + 6px);
    z-index: 30;
    max-height: min(440px, 60vh);
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
}

.results-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1.5rem;
    color: #64748b;
    font-size: 0.88rem;
}
.results-state > i { font-size: 1.4rem; color: #94a3b8; }
.results-state div { display: flex; flex-direction: column; }
.results-state strong { color: #334155; }

.result {
    all: unset;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: center;
    width: 100%;
    padding: 0.55rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
}
.result.is-active { background: #eff6ff; }

.result-thumb {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #f1f5f9;
    color: #94a3b8;
    overflow: hidden;
}
.result-thumb img { width: 100%; height: 100%; object-fit: cover; }

.result-main { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.result-name {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1e293b;
    min-width: 0;
}
.result-meta { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.75rem; color: #64748b; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; }

.stock-pill { padding: 0 0.45rem; border-radius: 999px; line-height: 1.6; }
.stock-pill.is-ok { background: #f0fdf4; color: #15803d; }
.stock-pill.is-low { background: #fffbeb; color: #b45309; }
.stock-pill.is-out { background: #fef2f2; color: #b91c1c; }
.in-order { color: #2563eb; font-weight: 600; }

.result-price { font-weight: 700; color: #1e293b; font-variant-numeric: tabular-nums; white-space: nowrap; }

.results-foot {
    position: sticky;
    bottom: 0;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.45rem 0.85rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 0.72rem;
    color: #64748b;
}
.results-foot kbd { margin-inline-end: 0.15rem; }

@media (max-width: 768px) {
    .search-row { flex-wrap: wrap; }
    .search-input { flex-basis: 100%; }
    .search-filter, .search-filter--stock { flex: 1 1 0; }
    .key-hint, .results-foot { display: none; }
    .result { grid-template-columns: 36px minmax(0, 1fr) auto; gap: 0.5rem; padding: 0.5rem 0.6rem; }
    .result-thumb { width: 36px; height: 36px; }
}
</style>
