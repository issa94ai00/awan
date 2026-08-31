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
                <el-select
                    v-model="filterCategory"
                    :placeholder="$t('all_categories')"
                    clearable
                    class="cat-select"
                    @change="onCategoryChange"
                >
                    <el-option
                        v-for="cat in categories"
                        :key="cat.id"
                        :label="cat.name_ar || cat.name"
                        :value="cat.id"
                    />
                </el-select>
                <div class="divwrap">
                    <input
                        v-model="divideValue"
                        type="number"
                        min="0.0001"
                        step="any"
                        :placeholder="$t('divide_all_prices')"
                    />
                    <button type="button" class="btn-divide" @click="divideAllPrices">{{ $t('divide') }}</button>
                    <button type="button" class="btn-ghost" @click="resetFilters">{{ $t('reset') }}</button>
                    <button type="button" class="btn-ghost" @click="triggerImport">{{ $t('import') }}</button>
                    <input ref="fileInput" type="file" accept=".csv,text/csv" hidden @change="handleImportFile" />
                </div>
                <button type="button" class="btn-pdf" :disabled="printLoading" @click="printPage">
                    <el-icon v-if="printLoading && prepMode === 'print'" class="is-loading"><Loading /></el-icon>
                    {{ printLoading && prepMode === 'print' ? $t('loading') : $t('print') }}
                </button>
                <button type="button" class="btn-pdf-download" :disabled="printLoading" @click="downloadPdf">
                    <el-icon v-if="printLoading && prepMode === 'pdf'" class="is-loading"><Loading /></el-icon>
                    <el-icon v-else><Download /></el-icon>
                    {{ printLoading && prepMode === 'pdf' ? $t('loading') : $t('download_pdf') }}
                </button>
                <button type="button" class="btn-csv" @click="exportCsv">{{ $t('download_csv') }}</button>
                <span v-if="importMsg" class="saved-msg">{{ importMsg }}</span>
            </div>
        </header>

        <div v-loading="loading" class="offer-table-wrap screen-only">
            <ProductOfferTable :groups="groupedProducts" :loading="loading" :editing-id="editingId" :edit-value="editValue"
                @start-edit="startEdit" @commit-edit="commitEdit" @cancel-edit="cancelEdit" />
        </div>

        <!-- Print/PDF-only table: holds every product across every page, grouped by product name -->
        <div class="offer-table-wrap print-only" :class="{ 'pdf-render': pdfRendering }">
            <ProductOfferTable ref="printTableRef" :groups="printGroups" :loading="false" :editing-id="null" :edit-value="''" print-mode />
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
import { Search, Loading, Close, Download } from '@element-plus/icons-vue';

const { t } = useI18n();
const store = useProductsStore();

const searchQuery = ref('');
const filterCategory = ref(null);
const currentPage = ref(1);
const pageSize = ref(50);
const divideValue = ref(null);

// Price overrides keyed by row id (`p-{productId}` or `v-{variantId}`), so
// every size/variant row can be edited or divided independently.
const overrides = ref({});

const editingId = ref(null);
const editValue = ref('');

const fileInput = ref(null);
const importMsg = ref('');
let importMsgTimeout = null;

const products = computed(() => store.products);
const categories = computed(() => store.categories);
const loading = computed(() => store.loading);
const total = computed(() => store.pagination.total);

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
    const order = [];
    for (const p of list) {
        const name = (p.name_ar || p.name_en || '').trim();
        const key = name || `id-${p.id}`;
        if (!map.has(key)) {
            map.set(key, { key, product: p, items: [] });
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
        map.get(key).items.push(...items);
    }
    return order.map((k) => map.get(k));
}

const groupedProducts = computed(() => buildGroups(products.value));

function makeItem(id, base) {
    const override = overrides.value[id];
    return {
        id,
        ...base,
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
            category_id: filterCategory.value || undefined,
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
    filterCategory.value = null;
    divideValue.value = null;
    currentPage.value = 1;
    overrides.value = {};
    store.clearFilters();
    fetchProducts();
    hydratePrintCatalog();
    flashMsg(t('reset'));
};

const onCategoryChange = () => {
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
    val = String(val ?? editValue.value).replace(/[^\d.\-]/g, '');
    const num = parseFloat(val);
    if (!isNaN(num) && num >= 0) {
        overrides.value[editingId.value] = Math.round(num * 10000) / 10000;
    }
    editingId.value = null;
};

const cancelEdit = () => {
    editingId.value = null;
};

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
        category_id: filterCategory.value || undefined,
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
    const head = [t('product'), t('details'), t('the_price'), t('inventory')];
    const lines = ['﻿' + head.map(csvCell).join(',')];
    for (const g of groupedProducts.value) {
        const name = g.product.name_ar || g.product.name_en || '';
        for (const item of g.items) {
            const detail = [item.size, item.color, item.unit].filter(Boolean).join(' / ') || '—';
            lines.push([
                csvCell(name),
                csvCell(detail),
                csvCell(item.displayPrice),
                csvCell(item.stock_quantity ?? 0),
            ].join(','));
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
.cat-select {
    width: 160px;
}
.cat-select :deep(.el-select__wrapper) {
    border-radius: 999px;
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
        page-break-after: always;
        break-after: page;
    }
    .print-cover img {
        width: 100%;
        height: 100vh;
        object-fit: contain;
    }
    body {
        background: #fff;
    }
    .price-offer-page {
        padding: 8px;
    }
    .offer-table-wrap {
        max-height: none;
        overflow: visible;
        border: none;
        box-shadow: none;
    }
}
</style>
