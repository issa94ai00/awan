<template>
    <div class="price-offer-page">
        <div class="print-cover"><img :src="'/cover.jpeg'" alt=""></div>
        <header class="offer-toolbar">
            <div class="toolbar-title">
                <span class="dot"></span>
                <h2>{{ $t('price_offer') }}</h2>
                <span v-if="total > 0" class="badge">{{ total }} {{ $t('product') }}</span>
            </div>

            <div class="toolbar-tools">
                <div class="tools-group tools-group-filter">
                    <div class="qbox">
                        <el-icon class="qicon"><Search /></el-icon>
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="qinput"
                            :placeholder="$t('search_for_a_product')"
                            autocomplete="off"
                            @input="onSearchInput"
                        />
                    </div>
                    <el-popover
                        v-model:visible="categoryPopoverVisible"
                        placement="bottom-start"
                        trigger="click"
                        width="260"
                        popper-class="categories-popover"
                    >
                        <template #reference>
                            <button
                                type="button"
                                class="btn-ghost btn-categories"
                                :class="{ active: selectedCategoryIds.length > 0 }"
                            >
                                {{ categoryButtonLabel }}
                            </button>
                        </template>
                        <div class="categories-menu">
                            <div class="categories-menu-header">
                                <p class="categories-menu-title">{{ $t('choose_classifications_to_print') }}</p>
                                <div class="categories-menu-actions">
                                    <button type="button" class="link-btn" @click="selectAllCategories">{{ $t('common.select_all') }}</button>
                                    <button type="button" class="link-btn" @click="clearCategories">{{ $t('clear') }}</button>
                                </div>
                            </div>
                            <div class="cat-search">
                                <el-icon class="cat-search-icon"><Search /></el-icon>
                                <input
                                    v-model="categorySearch"
                                    type="search"
                                    class="cat-search-input"
                                    :placeholder="$t('search_classifications')"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="categories-list">
                                <label v-for="cat in filteredCategoryOptions" :key="cat.id" class="categories-menu-item">
                                    <el-checkbox
                                        :model-value="selectedCategoryIds.includes(cat.id)"
                                        @update:model-value="(val) => toggleCategory(cat.id, val)"
                                    />
                                    <span class="cat-name">{{ cat.name_ar || cat.name }}</span>
                                    <span v-if="cat.product_count !== undefined && cat.product_count !== null" class="cat-count">{{ cat.product_count }}</span>
                                </label>
                                <p v-if="filteredCategoryOptions.length === 0" class="cat-empty">{{ $t('no_matching_classifications') }}</p>
                            </div>
                        </div>
                    </el-popover>
                </div>

                <span class="tool-divider" aria-hidden="true"></span>

                <div class="tools-group tools-group-edit">
                    <div class="divwrap">
                        <input
                            v-model="divideValue"
                            type="number"
                            min="0.0001"
                            step="any"
                            :placeholder="$t('divide_all_prices')"
                        />
                        <button type="button" class="btn-divide" @click="divideAllPrices">{{ $t('divide') }}</button>
                    </div>
                    <el-tooltip :content="$t('reset_toolbar_tooltip')" placement="bottom" effect="dark">
                        <button type="button" class="btn-ghost btn-icon" @click="resetFilters">
                            <el-icon><Refresh /></el-icon>
                            {{ $t('reset') }}
                        </button>
                    </el-tooltip>
                    <el-tooltip :content="$t('import_csv_tooltip')" placement="bottom" effect="dark">
                        <button type="button" class="btn-ghost btn-icon" @click="triggerImport">
                            <el-icon><Upload /></el-icon>
                            {{ $t('import') }}
                        </button>
                    </el-tooltip>
                    <input ref="fileInput" type="file" accept=".csv,text/csv" hidden @change="handleImportFile" />
                </div>

                <span class="tool-divider" aria-hidden="true"></span>

                <div class="tools-group tools-group-export">
                    <el-popover placement="bottom-end" trigger="click" width="230" popper-class="columns-popover">
                        <template #reference>
                            <button type="button" class="btn-ghost btn-columns">{{ $t('print_columns') }}</button>
                        </template>
                        <div class="columns-menu">
                            <p class="columns-menu-title">{{ $t('choose_columns_to_print') }}</p>
                            <label v-for="col in columnOptions" :key="col.key" class="columns-menu-item">
                                <el-checkbox
                                    :model-value="visibleColumns[col.key]"
                                    :disabled="visibleColumns[col.key] && selectedColumnCount === 1"
                                    @update:model-value="(val) => toggleColumn(col.key, val)"
                                />
                                <span>{{ $t(col.label) }}</span>
                            </label>
                        </div>
                    </el-popover>
                    <div class="export-actions">
                        <button type="button" class="btn-pdf" :disabled="printLoading" @click="printPage">
                            <el-icon v-if="printLoading && prepMode === 'print'" class="is-loading"><Loading /></el-icon>
                            {{ printLoading && prepMode === 'print' ? $t('loading') : $t('print') }}
                        </button>
                        <button type="button" class="btn-pdf-download" :disabled="printLoading" @click="downloadPdf">
                            <el-icon v-if="printLoading && prepMode === 'pdf'" class="is-loading"><Loading /></el-icon>
                            <el-icon v-else><Download /></el-icon>
                            {{ printLoading && prepMode === 'pdf' ? $t('loading') : $t('download_pdf') }}
                        </button>
                    </div>
                    <button type="button" class="btn-csv" @click="exportCsv">{{ $t('download_csv') }}</button>
                </div>

                <span v-if="importMsg" class="saved-msg">{{ importMsg }}</span>
            </div>
        </header>

        <div v-if="selectedCategoryIds.length" class="active-filters screen-only">
            <span class="active-filters-label">{{ $t('classifications') }}:</span>
            <span v-for="id in selectedCategoryIds" :key="id" class="filter-chip">
                {{ categoryNameById(id) }}
                <button type="button" class="filter-chip-remove" :aria-label="$t('clear')" @click="toggleCategory(id, false)">×</button>
            </span>
            <button type="button" class="filter-clear-all" @click="clearCategories">{{ $t('clear') }}</button>
        </div>

        <div v-loading="loading" class="offer-table-wrap screen-only">
            <ProductOfferTable :groups="groupedProducts" :loading="loading" :editing-id="editingId" :edit-value="editValue" :visible-columns="visibleColumns"
                :editing-stock-id="editingStockId" :edit-stock-value="editStockValue"
                :editing-details-id="editingDetailsId" :detail-value="detailValue" :item-status="itemStatus"
                @start-edit="startEdit" @commit-edit="commitEdit" @cancel-edit="cancelEdit"
                @start-edit-stock="startEditStock" @commit-edit-stock="commitEditStock" @cancel-edit-stock="cancelEditStock"
                @start-edit-details="startEditDetails" @commit-edit-details="commitEditDetails" @cancel-edit-details="cancelEditDetails" />
        </div>

        <!-- Print/PDF-only table: holds every product across every page, grouped by product name -->
        <div class="offer-table-wrap print-only" :class="{ 'pdf-render': pdfRendering }">
            <ProductOfferTable ref="printTableRef" :groups="printGroups" :loading="false" :editing-id="null" :edit-value="''" print-mode :visible-columns="visibleColumns" />
        </div>

        <!-- Print/PDF-prep overlay: shows progress while every page is pulled, images are loaded, and (for PDF) pages are rendered -->
        <Teleport to="body">
            <Transition name="print-prep-fade">
                <div v-if="printLoading" class="print-prep-overlay screen-only" role="alert" aria-live="assertive">
                    <div class="print-prep-card">
                        <el-icon class="print-prep-spinner is-loading"><Loading /></el-icon>
                        <h3>{{ prepMode === 'pdf' ? $t('preparing_pdf') : $t('preparing_print') }}</h3>
                        <el-progress
                            :percentage="printProgressPercent"
                            :stroke-width="10"
                            :show-text="false"
                            color="#c00000"
                        />
                        <p class="print-prep-count">
                            <template v-if="exportPhase === 'images'">
                                {{ $t('loading_images_progress', { loaded: printProgress.loaded, total: printProgress.total }) }}
                            </template>
                            <template v-else-if="exportPhase === 'render'">
                                {{ $t('rendering_pdf_progress', { loaded: printProgress.loaded, total: printProgress.total }) }}
                            </template>
                            <template v-else>
                                {{ printProgress.total > 0
                                    ? $t('preparing_print_progress', { loaded: printProgress.loaded, total: printProgress.total })
                                    : $t('preparing_print_progress_unknown') }}
                            </template>
                        </p>
                        <button v-if="exportPhase !== 'render'" type="button" class="print-prep-cancel" @click="cancelPrintPrep">
                            <el-icon><Close /></el-icon>
                            {{ $t('cancel') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <div v-if="total > 0" class="pagination-wrapper screen-only">
            <el-pagination
                v-model:current-page="currentPage"
                v-model:page-size="pageSize"
                :total="total"
                :page-sizes="[20, 50, 100, 200]"
                layout="total, sizes, prev, pager, next, jumper"
                background
                @size-change="onSizeChange"
                @current-change="onPageChange"
            />
        </div>
    </div>
</template>

<script setup>
import ProductOfferTable from '@/components/admin/products/ProductOfferTable.vue';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { productsApi } from '@/api/products';
import { waitForImages, renderTableToPdf } from '@/utils/pdfExport';
import { Search, Loading, Close, Download, Refresh, Upload } from '@element-plus/icons-vue';

const { t } = useI18n();
const store = useProductsStore();

const searchQuery = ref('');
// Multi-classification filter — empty means every classification is included.
const selectedCategoryIds = ref([]);
const categorySearch = ref('');
const categoryPopoverVisible = ref(false);
const currentPage = ref(1);
const pageSize = ref(50);
const divideValue = ref(null);

// Price/stock/detail overrides keyed by row id (`p-{productId}` or
// `v-{variantId}`). They keep the screen table, the print/PDF table and the
// CSV export all showing the same edited value regardless of which one was
// fetched most recently, while `saveField` below pushes the same edit to the
// database in the background.
const overrides = ref({});
const stockOverrides = ref({});
const detailOverrides = ref({});

const editingId = ref(null);
const editValue = ref('');

const editingStockId = ref(null);
const editStockValue = ref('');

const editingDetailsId = ref(null);
const detailValue = ref({ size: '', color: '', unit: '' });

// Per-row autosave status for the inline editors: 'saving' | 'saved' | 'error'.
const itemStatus = ref({});

function flashStatus(id, state) {
    itemStatus.value = { ...itemStatus.value, [id]: state };
    if (state === 'saved') {
        setTimeout(() => {
            if (itemStatus.value[id] === 'saved') {
                const next = { ...itemStatus.value };
                delete next[id];
                itemStatus.value = next;
            }
        }, 2000);
    }
}

// Persists a single row's edited field(s) straight to the product or variant
// record behind it — `id` is `p-{productId}` or `v-{variantId}`, so the right
// endpoint is picked from its prefix. Fired only from the per-cell editors
// (price, count, size/color/unit) when the cell is committed — never from the
// bulk "divide all prices" tool, which stays a local print-preview action.
async function saveField(id, patch) {
    flashStatus(id, 'saving');
    try {
        const [type, rawId] = String(id).split('-');
        const res = type === 'v'
            ? await productsApi.updateVariant(rawId, patch)
            : await productsApi.update(rawId, patch);
        applyServerRecord(type, rawId, res.data.data);
        flashStatus(id, 'saved');
        return true;
    } catch (err) {
        flashStatus(id, 'error');
        ElMessage.error(err.response?.data?.message || t('failed_to_save'));
        return false;
    }
}

// Merges the server's saved record back into the store's product list so the
// "original" value (used for the edited/strikethrough display) matches what
// is now actually in the database instead of the stale fetched copy.
function applyServerRecord(type, rawId, record) {
    if (!record) return;
    if (type === 'v') {
        for (const p of store.products) {
            if (!Array.isArray(p.variants)) continue;
            const idx = p.variants.findIndex((v) => String(v.id) === String(rawId));
            if (idx !== -1) { p.variants[idx] = { ...p.variants[idx], ...record }; return; }
        }
    } else {
        const idx = store.products.findIndex((p) => String(p.id) === String(rawId));
        if (idx !== -1) store.products[idx] = { ...store.products[idx], ...record };
    }
}

const fileInput = ref(null);
const importMsg = ref('');
let importMsgTimeout = null;

// Which columns appear on the printed/exported price list — persisted per
// browser so the choice sticks across visits.
const COLUMNS_STORAGE_KEY = 'price_offer_visible_columns';
const defaultColumns = { image: true, product: false, details: true, price: true, inventory: true };
const columnOptions = [
    { key: 'image', label: 'image' },
    { key: 'product', label: 'product' },
    { key: 'details', label: 'details' },
    { key: 'price', label: 'the_price' },
    { key: 'inventory', label: 'inventory' },
];

function loadVisibleColumns() {
    try {
        const raw = localStorage.getItem(COLUMNS_STORAGE_KEY);
        if (!raw) return { ...defaultColumns };
        return { ...defaultColumns, ...JSON.parse(raw) };
    } catch {
        return { ...defaultColumns };
    }
}

const visibleColumns = ref(loadVisibleColumns());
const selectedColumnCount = computed(() => Object.values(visibleColumns.value).filter(Boolean).length);

function toggleColumn(key, val) {
    if (!val && selectedColumnCount.value <= 1) return;
    visibleColumns.value[key] = val;
    try {
        localStorage.setItem(COLUMNS_STORAGE_KEY, JSON.stringify(visibleColumns.value));
    } catch {
        // Private mode / quota exceeded — selection just won't persist.
    }
}

const products = computed(() => store.products);
const categories = computed(() => store.categories);
const loading = computed(() => store.loading);
const total = computed(() => store.pagination.total);

const categoryButtonLabel = computed(() => {
    const n = selectedCategoryIds.value.length;
    if (n === 0) return t('all_classifications');
    if (n === 1) return categoryNameById(selectedCategoryIds.value[0]) || t('classifications_selected', { n });
    return t('classifications_selected', { n });
});

const filteredCategoryOptions = computed(() => {
    const q = categorySearch.value.trim().toLowerCase();
    if (!q) return categories.value;
    return categories.value.filter((c) =>
        (c.name_ar || '').toLowerCase().includes(q) || (c.name_en || '').toLowerCase().includes(q)
    );
});

function categoryNameById(id) {
    const cat = categories.value.find((c) => c.id === id);
    return cat ? (cat.name_ar || cat.name_en || '') : '';
}

function toggleCategory(id, checked) {
    if (checked) {
        if (!selectedCategoryIds.value.includes(id)) selectedCategoryIds.value = [...selectedCategoryIds.value, id];
    } else {
        selectedCategoryIds.value = selectedCategoryIds.value.filter((x) => x !== id);
    }
    onCategoryChange();
}

function selectAllCategories() {
    selectedCategoryIds.value = filteredCategoryOptions.value.map((c) => c.id);
    onCategoryChange();
}

function clearCategories() {
    selectedCategoryIds.value = [];
    onCategoryChange();
}

// Full catalog snapshot for printing/PDF — every page, grouped by product name.
const printGroups = ref([]);
const printTableRef = ref(null);
const printLoading = ref(false);
const printCancelled = ref(false);
const printProgress = ref({ loaded: 0, total: 0 });
const printProgressPercent = computed(() => {
    const { loaded, total: totalCount } = printProgress.value;
    return totalCount > 0 ? Math.min(100, Math.round((loaded / totalCount) * 100)) : 0;
});
// 'print' | 'pdf' — which action the prep overlay is running for.
const prepMode = ref('print');
// 'fetch' | 'images' | 'render' — drives the overlay's progress copy.
const exportPhase = ref('fetch');
// Rendered off-screen (not display:none) only while html2canvas needs to see it.
const pdfRendering = ref(false);

// Groups rows by product name (not just product id), so products stored as
// separate rows sharing a name (e.g. color variants) print as one entry.
function buildGroups(list) {
    const map = new Map();
    const seenDetails = new Map();
    const order = [];
    for (const p of list) {
        const name = (p.name_ar || p.name_en || '').trim();
        const key = name || `id-${p.id}`;
        if (!map.has(key)) {
            map.set(key, { key, product: p, items: [] });
            seenDetails.set(key, new Set());
            order.push(key);
        }
        const variants = Array.isArray(p.variants) ? p.variants : [];
        const items = variants.length
            ? variants.map((v) => makeItem(`v-${v.id}`, {
                size: v.size || '',
                color: v.color || '',
                unit: v.material || '',
                price: parseFloat(v.price) || 0,
                stock_quantity: v.stock_quantity ?? 0,
            }))
            : [makeItem(`p-${p.id}`, {
                size: p.size || '',
                color: p.color || '',
                unit: p.unit || '',
                price: parseFloat(p.price) || 0,
                stock_quantity: p.stock_quantity ?? 0,
            })];
        // Two variants with the same size/color/unit print as the same row to
        // a reader, so collapse them even when they come from separate variant
        // records (or the same product/variant surfaces twice in `list`
        // because it's joined in via more than one category).
        const seen = seenDetails.get(key);
        const group = map.get(key);
        for (const item of items) {
            const detailKey = `${item.size}|${item.color}|${item.unit}`;
            if (seen.has(detailKey)) continue;
            seen.add(detailKey);
            group.items.push(item);
        }
    }
    return order.map((k) => map.get(k));
}

const groupedProducts = computed(() => buildGroups(products.value));

function makeItem(id, base) {
    const override = overrides.value[id];
    const detailOverride = detailOverrides.value[id];
    const stockOverride = stockOverrides.value[id];
    return {
        id,
        ...base,
        ...detailOverride,
        stock_quantity: stockOverride !== undefined ? stockOverride : base.stock_quantity,
        originalPrice: base.price,
        displayPrice: override !== undefined ? override : base.price,
    };
}

let searchTimeout = null;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchProducts();
        hydratePrintCatalog();
    }, 400);
};

const fetchProducts = async () => {
    try {
        await store.fetchProducts({
            page: currentPage.value,
            per_page: pageSize.value,
            search: searchQuery.value || undefined,
            category_id: selectedCategoryIds.value.length ? selectedCategoryIds.value : undefined,
            is_active: true,
            with_variants: true,
        });
    } catch {
        ElMessage.error(t('failed_to_bring_products'));
    }
};

// Keeps the print/PDF table hydrated with the full, current-filter catalog in
// the background, so a native Ctrl+P (or any print trigger other than our own
// Print/Download PDF buttons) still shows real images and prices instead of
// an empty table. The buttons still do their own guaranteed, visible fetch on
// click — this is just a best-effort head start.
let hydrating = false;
async function hydratePrintCatalog() {
    if (hydrating) return;
    hydrating = true;
    printCancelled.value = false;
    try {
        printGroups.value = buildGroups(await fetchAllProductsFlat());
    } catch {
        // Silent — Print/Download PDF still fetch fresh, visibly, on demand.
    } finally {
        hydrating = false;
    }
}

const resetFilters = () => {
    searchQuery.value = '';
    selectedCategoryIds.value = [];
    categorySearch.value = '';
    divideValue.value = null;
    currentPage.value = 1;
    overrides.value = {};
    stockOverrides.value = {};
    detailOverrides.value = {};
    store.clearFilters();
    fetchProducts();
    hydratePrintCatalog();
    flashMsg(t('reset'));
};

const onCategoryChange = () => {
    currentPage.value = 1;
    fetchProducts();
    hydratePrintCatalog();
};

const onSizeChange = () => {
    currentPage.value = 1;
    fetchProducts();
};

const onPageChange = () => {
    fetchProducts();
};

const startEdit = (item) => {
    editingId.value = item.id;
    editValue.value = String(item.displayPrice ?? '');
    nextTick(() => {
        const input = document.querySelector('.price-edit-input');
        if (input) { input.focus(); input.select(); }
    });
};

const commitEdit = (val) => {
    if (!editingId.value) return;
    const id = editingId.value;
    editingId.value = null;
    val = String(val ?? editValue.value).replace(/[^\d.\-]/g, '');
    const num = parseFloat(val);
    if (isNaN(num) || num < 0) return;
    const rounded = Math.round(num * 10000) / 10000;
    overrides.value[id] = rounded;
    saveField(id, { price: rounded });
};

const cancelEdit = () => {
    editingId.value = null;
};

const startEditStock = (item) => {
    editingStockId.value = item.id;
    editStockValue.value = String(item.stock_quantity ?? '');
    nextTick(() => {
        const input = document.querySelector('.stock-edit-input');
        if (input) { input.focus(); input.select(); }
    });
};

const commitEditStock = (val) => {
    if (!editingStockId.value) return;
    const id = editingStockId.value;
    editingStockId.value = null;
    val = String(val ?? editStockValue.value).replace(/[^\d]/g, '');
    const num = parseInt(val, 10);
    if (isNaN(num) || num < 0) return;
    stockOverrides.value[id] = num;
    saveField(id, { stock_quantity: num });
};

const cancelEditStock = () => {
    editingStockId.value = null;
};

const startEditDetails = (item) => {
    editingDetailsId.value = item.id;
    detailValue.value = { size: item.size || '', color: item.color || '', unit: item.unit || '' };
};

const commitEditDetails = (val) => {
    if (!editingDetailsId.value) return;
    const id = editingDetailsId.value;
    editingDetailsId.value = null;
    const patch = {
        size: String(val?.size ?? '').trim(),
        color: String(val?.color ?? '').trim(),
        unit: String(val?.unit ?? '').trim(),
    };
    detailOverrides.value[id] = patch;
    // The base product's unit column is `unit`; a variant's equivalent field
    // is `material` — same displayed value, different column per item type.
    const isVariant = id.startsWith('v-');
    saveField(id, {
        size: patch.size,
        color: patch.color,
        ...(isVariant ? { material: patch.unit } : { unit: patch.unit }),
    });
};

const cancelEditDetails = () => {
    editingDetailsId.value = null;
};

// Local print-preview only — divides the shown prices without writing
// anything to the database. Only the per-cell editors (price, count,
// size/color/unit) autosave; a bulk rewrite of every price needs a deliberate
// separate action, not a side effect of building a custom print list.
const divideAllPrices = () => {
    const v = parseFloat(divideValue.value);
    if (!(v > 0)) {
        ElMessage.warning(t('please_enter_positive_number'));
        return;
    }
    for (const group of groupedProducts.value) {
        for (const item of group.items) {
            overrides.value[item.id] = Math.round((item.originalPrice / v) * 10000) / 10000;
        }
    }
    flashMsg(t('prices_divided'));
};

// Pulls every page of the current filter (not just the on-screen page) so
// printing/PDF export covers the full price list, grouped by product name.
// Reports progress as it goes and stops early if the user cancels.
async function fetchAllProductsFlat(onProgress) {
    const baseParams = {
        search: searchQuery.value || undefined,
        category_id: selectedCategoryIds.value.length ? selectedCategoryIds.value : undefined,
        is_active: true,
        with_variants: true,
        per_page: 100,
    };
    const all = [];
    let page = 1;
    let lastPage = 1;
    do {
        const res = await productsApi.getAll({ ...baseParams, page });
        const data = res.data;
        all.push(...(data.data || (Array.isArray(data) ? data : [])));
        const pagination = data.pagination || data;
        lastPage = pagination.last_page || 1;
        onProgress?.(all.length, pagination.total ?? all.length);
        page += 1;
    } while (page <= lastPage && !printCancelled.value);
    return all;
}

// Shared prep: fetch every page, build the merged groups, mount the print
// table, then block until every product image has actually loaded — a lazy
// or still-fetching <img> would otherwise print/export as a blank box.
// Returns the mounted <table> element, or null if the user cancelled.
async function prepareFullCatalog() {
    printCancelled.value = false;
    printProgress.value = { loaded: 0, total: 0 };
    exportPhase.value = 'fetch';
    const all = await fetchAllProductsFlat((loaded, total) => {
        printProgress.value = { loaded, total };
    });
    if (printCancelled.value) return null;

    printGroups.value = buildGroups(all);
    await nextTick();

    const tableEl = printTableRef.value?.$el;
    if (tableEl) {
        exportPhase.value = 'images';
        printProgress.value = { loaded: 0, total: 0 };
        await waitForImages(tableEl, (loaded, total) => {
            printProgress.value = { loaded, total };
        });
    }
    return printCancelled.value ? null : tableEl;
}

const printPage = async () => {
    prepMode.value = 'print';
    printLoading.value = true;
    try {
        const tableEl = await prepareFullCatalog();
        if (!tableEl) return;
        printLoading.value = false;
        window.print();
    } catch {
        if (!printCancelled.value) ElMessage.error(t('failed_to_bring_products'));
    } finally {
        printLoading.value = false;
    }
};

const downloadPdf = async () => {
    prepMode.value = 'pdf';
    printLoading.value = true;
    try {
        const tableEl = await prepareFullCatalog();
        if (!tableEl) return;

        exportPhase.value = 'render';
        printProgress.value = { loaded: 0, total: 0 };
        pdfRendering.value = true;
        await nextTick();
        await renderTableToPdf({
            table: tableEl,
            groups: printGroups.value,
            filename: `price-offer-${new Date().toISOString().slice(0, 10)}.pdf`,
            coverSrc: '/cover.jpeg',
            onPageProgress: (loaded, total) => {
                printProgress.value = { loaded, total };
            },
        });
        flashMsg(t('pdf_ready'));
    } catch {
        if (!printCancelled.value) ElMessage.error(t('failed_to_generate_pdf'));
    } finally {
        pdfRendering.value = false;
        printLoading.value = false;
        exportPhase.value = 'fetch';
    }
};

const cancelPrintPrep = () => {
    printCancelled.value = true;
    printLoading.value = false;
    flashMsg(t('print_prep_cancelled'));
};

function flashMsg(msg) {
    importMsg.value = msg;
    clearTimeout(importMsgTimeout);
    importMsgTimeout = setTimeout(() => { importMsg.value = ''; }, 2500);
}

const exportCsv = () => {
    const cols = visibleColumns.value;
    const head = [];
    if (cols.product) head.push(t('product'));
    if (cols.details) head.push(t('details'));
    if (cols.price) head.push(t('the_price'));
    if (cols.inventory) head.push(t('inventory'));
    const lines = ['﻿' + head.map(csvCell).join(',')];
    for (const g of groupedProducts.value) {
        const name = g.product.name_ar || g.product.name_en || '';
        for (const item of g.items) {
            const detail = [item.size, item.color, item.unit].filter(Boolean).join(' / ') || '—';
            const row = [];
            if (cols.product) row.push(csvCell(name));
            if (cols.details) row.push(csvCell(detail));
            if (cols.price) row.push(csvCell(item.displayPrice));
            if (cols.inventory) row.push(csvCell(item.stock_quantity ?? 0));
            lines.push(row.join(','));
        }
    }
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `price-offer-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 600);
};

function csvCell(v) {
    let s = String(v == null ? '' : v);
    if (/[",\r\n]/.test(s)) s = '"' + s.replace(/"/g, '""') + '"';
    return s;
}

// --- CSV import: bulk price update, matching the file_with_images template ---

const triggerImport = () => {
    if (fileInput.value) {
        fileInput.value.value = '';
        fileInput.value.click();
    }
};

const handleImportFile = async (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    try {
        const text = (await file.text()).replace(/^﻿/, '');
        const rows = parseCsv(text);
        applyImport(rows);
    } catch (err) {
        ElMessage.error(t('import_failed'));
    }
    e.target.value = '';
};

function parseCsv(text) {
    const rows = [];
    let row = [], cur = '', quoted = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (quoted) {
            if (ch === '"') {
                if (text[i + 1] === '"') { cur += '"'; i++; } else quoted = false;
            } else cur += ch;
        } else if (ch === '"') {
            quoted = true;
        } else if (ch === ',') {
            row.push(cur); cur = '';
        } else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') i++;
            row.push(cur); cur = ''; rows.push(row); row = [];
        } else cur += ch;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return rows.filter((r) => r.some((c) => (c || '').trim() !== ''));
}

function toNumber(raw) {
    const s = String(raw ?? '')
        .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
        .replace(/[,،٫]/g, '.')
        .trim();
    const n = parseFloat(s);
    return isFinite(n) ? n : null;
}

const applyImport = (rows) => {
    // Header row, if present, is skipped by not matching any product name.
    const byKey = new Map();
    for (const g of groupedProducts.value) {
        const name = (g.product.name_ar || g.product.name_en || '').trim();
        for (const item of g.items) {
            const key = name + '' + (item.size || '');
            if (!byKey.has(key)) byKey.set(key, item);
        }
    }

    let updated = 0, unmatched = 0;
    for (const row of rows) {
        const [name = '', size = '', price = ''] = row;
        const key = String(name).trim() + '' + String(size).trim();
        const item = byKey.get(key) || byKey.get(String(name).trim() + '');
        if (!item) { unmatched++; continue; }
        const n = toNumber(price);
        if (n !== null && n >= 0) {
            overrides.value[item.id] = Math.round(n * 10000) / 10000;
            updated++;
        }
    }
    flashMsg(`${t('imported')} ✓ ${updated}${unmatched ? ` · ${unmatched} ${t('skipped')}` : ''}`);
};

onMounted(async () => {
    await store.fetchCategories();
    await fetchProducts();
    hydratePrintCatalog();
});
</script>

<style scoped>
.price-offer-page {
    padding: 0;
}

/* Cover page — only rendered when printing, matches public/file_with_images.html */
.print-cover {
    display: none;
}

/* Toolbar — matches public/file_with_images.html */
.offer-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #293344 0%, #3d4d63 100%);
    color: #fff;
    padding: 14px 18px;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .25);
    margin-bottom: 1.25rem;
}
.toolbar-title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar-title h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}
.toolbar-title .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #c00000;
    box-shadow: 0 0 0 4px rgba(192, 0, 0, .25);
    flex: 0 0 auto;
}
.badge {
    font-size: 11px;
    background: rgba(255, 255, 255, .14);
    padding: 5px 11px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, .28);
    white-space: nowrap;
}

.toolbar-tools {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 320px;
    justify-content: flex-end;
}
.tools-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
/* Separates search/filter, bulk-edit, and export tools into three scannable
   clusters instead of one undifferentiated row of pills. */
.tool-divider {
    width: 1px;
    align-self: stretch;
    min-height: 22px;
    background: rgba(255, 255, 255, .18);
    flex: 0 0 auto;
}
.export-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-icon {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.qbox {
    position: relative;
    flex: 1 1 200px;
    max-width: 260px;
}
.qicon {
    position: absolute;
    inset-inline-start: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, .75);
    font-size: 13px;
    pointer-events: none;
}
.qinput {
    width: 100%;
    border: 1px solid rgba(255, 255, 255, .35);
    background: rgba(255, 255, 255, .12);
    color: #fff;
    border-radius: 999px;
    padding: 8px 14px 8px 32px;
    font-size: 12.5px;
    outline: none;
}
.qinput::placeholder {
    color: rgba(255, 255, 255, .65);
}
.btn-categories {
    position: relative;
}
.btn-categories::after {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-inline-start: 6px;
    border-inline-end: 1.5px solid currentColor;
    border-block-end: 1.5px solid currentColor;
    transform: rotate(45deg) translateY(-2px);
}
.btn-categories.active {
    background: rgba(192, 0, 0, .28);
    border-color: rgba(192, 0, 0, .55);
}

.divwrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.divwrap input[type=number] {
    width: 110px;
    border: 1px solid rgba(255, 255, 255, .35);
    background: rgba(255, 255, 255, .12);
    color: #fff;
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12.5px;
    outline: none;
}
.divwrap input[type=number]::placeholder {
    color: rgba(255, 255, 255, .65);
}
.btn-columns {
    position: relative;
}
.btn-columns::after {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-inline-start: 6px;
    border-inline-end: 1.5px solid currentColor;
    border-block-end: 1.5px solid currentColor;
    transform: rotate(45deg) translateY(-2px);
}

.btn-divide,
.btn-ghost,
.btn-pdf,
.btn-pdf-download,
.btn-csv {
    border: none;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    padding: 8px 16px;
    border-radius: 999px;
    transition: transform .15s ease, box-shadow .15s ease;
    white-space: nowrap;
}
.btn-divide {
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    box-shadow: 0 3px 10px rgba(15, 118, 110, .35);
}
.btn-ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, .4);
}
.btn-pdf,
.btn-pdf-download {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-pdf {
    background: linear-gradient(135deg, #c00000, #e02424);
    box-shadow: 0 3px 10px rgba(192, 0, 0, .35);
}
.btn-pdf-download {
    background: linear-gradient(135deg, #6d28d9, #9333ea);
    box-shadow: 0 3px 10px rgba(109, 40, 217, .35);
}
.btn-csv {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    box-shadow: 0 3px 10px rgba(29, 78, 216, .35);
}
.btn-divide:hover,
.btn-pdf:hover:not(:disabled),
.btn-pdf-download:hover:not(:disabled),
.btn-csv:hover {
    transform: translateY(-1px);
}
.btn-pdf:disabled,
.btn-pdf-download:disabled {
    opacity: .75;
    cursor: wait;
}
.saved-msg {
    font-size: 11.5px;
    color: #8fe3ab;
    font-weight: 600;
    white-space: nowrap;
}

/* Below the tablet break each tool cluster takes its own full-width row
   instead of pills wrapping mid-group in an arbitrary order. */
@media (max-width: 860px) {
    .offer-toolbar {
        align-items: stretch;
    }
    .toolbar-tools {
        justify-content: flex-start;
    }
    .tools-group {
        width: 100%;
    }
    .tool-divider {
        display: none;
    }
    .qbox {
        max-width: none;
        flex: 1 1 100%;
    }
}

.columns-menu-title {
    margin: 0 0 8px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.columns-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 2px;
    font-size: 13px;
    color: #1e293b;
    cursor: pointer;
}

/* Classification (category) multi-select popover */
.categories-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.categories-menu-title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.categories-menu-actions {
    display: flex;
    gap: 10px;
    flex: 0 0 auto;
}
.link-btn {
    border: none;
    background: none;
    padding: 0;
    font-size: 11.5px;
    font-weight: 700;
    color: #2563eb;
    cursor: pointer;
}
.link-btn:hover {
    text-decoration: underline;
}
.cat-search {
    position: relative;
    margin-bottom: 8px;
}
.cat-search-icon {
    position: absolute;
    inset-inline-start: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 12px;
    pointer-events: none;
}
.cat-search-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #1e293b;
    border-radius: 999px;
    padding: 7px 10px 7px 28px;
    font-size: 12.5px;
    outline: none;
}
.cat-search-input:focus {
    border-color: #2563eb;
}
.categories-list {
    max-height: 260px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.categories-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 2px;
    font-size: 13px;
    color: #1e293b;
    cursor: pointer;
    border-radius: 6px;
}
.categories-menu-item:hover {
    background: #f1f5f9;
}
.cat-name {
    flex: 1 1 auto;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cat-count {
    flex: 0 0 auto;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    background: #f1f5f9;
    border-radius: 999px;
    padding: 1px 7px;
}
.cat-empty {
    margin: 10px 0 2px;
    font-size: 12.5px;
    color: #94a3b8;
    text-align: center;
}

/* Active classification chips shown under the toolbar */
.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 1rem;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 12px;
}
.active-filters-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 6px 4px 10px;
    border-radius: 999px;
}
.filter-chip-remove {
    border: none;
    background: rgba(55, 48, 163, .12);
    color: #3730a3;
    width: 16px;
    height: 16px;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.filter-chip-remove:hover {
    background: rgba(55, 48, 163, .25);
}
.filter-clear-all {
    margin-inline-start: auto;
    border: none;
    background: none;
    font-size: 11.5px;
    font-weight: 700;
    color: #b00e0e;
    cursor: pointer;
}
.filter-clear-all:hover {
    text-decoration: underline;
}

.offer-table-wrap {
    overflow: auto;
    max-height: calc(100vh - 240px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
    border: 1px solid rgba(15, 23, 42, .08);
}

.pagination-wrapper {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}

/* The print-only table (full catalog, all pages) stays out of the way on screen. */
.print-only {
    display: none;
}
/* While a PDF is being captured, the table needs a real (if invisible) box for
   html2canvas to read — display:none has no layout, so it's parked off-screen
   with a fixed width instead of hidden outright. */
.print-only.pdf-render {
    display: block;
    position: fixed;
    top: 0;
    left: -99999px;
    width: 960px;
    max-height: none;
    overflow: visible;
    border: none;
    box-shadow: none;
    background: #fff;
    z-index: -1;
}

/* Print-prep overlay — shown while every page is fetched for a full print */
.print-prep-overlay {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(3px);
}
.print-prep-card {
    background: #fff;
    border-radius: 18px;
    padding: 28px 32px;
    width: min(360px, 88vw);
    box-shadow: 0 24px 60px rgba(15, 23, 42, .35);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
}
.print-prep-spinner {
    font-size: 30px;
    color: #c00000;
}
.print-prep-card h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #111c2c;
}
.print-prep-card :deep(.el-progress) {
    width: 100%;
}
.print-prep-count {
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
    font-weight: 600;
}
.print-prep-cancel {
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    padding: 7px 16px;
    border-radius: 999px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.print-prep-cancel:hover {
    background: #fee2e2;
    color: #b00e0e;
}

.print-prep-fade-enter-active,
.print-prep-fade-leave-active {
    transition: opacity .18s ease;
}
.print-prep-fade-enter-from,
.print-prep-fade-leave-to {
    opacity: 0;
}

/* Zero page margin so the browser has no room to draw its own URL/date
   header and footer line — same trick used by public/file_with_images.html. */
@page {
    margin: 0;
}

/* Print styles */
@media print {
    .offer-toolbar,
    .screen-only {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .print-cover {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100vh;
        /* Chromium resolves 100vh against the screen viewport, not the @page
           print box, so the cover renders a hair taller than one physical
           page. That sliver used to spill onto a blank page 2 before this
           break was reached — clipping it here keeps everything on page 1. */
        overflow: hidden;
        page-break-after: always;
        break-after: page;
    }
    .print-cover img {
        max-width: 100%;
        max-height: 100vh;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    body {
        background: #fff;
        margin: 0;
    }
    .price-offer-page {
        /* Zero here (not on .print-cover) — any padding on this wrapper adds
           to the cover's height too, pushing it past one full page and
           spilling a near-blank page behind it before the real content. */
        padding: 0;
    }
    .print-only {
        padding: 8px;
    }
    .offer-table-wrap {
        max-height: none;
        overflow: visible;
        border: none;
        box-shadow: none;
    }
    :global(.columns-popover),
    :global(.categories-popover) {
        display: none !important;
    }
}
</style>
