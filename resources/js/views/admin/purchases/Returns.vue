<template>
    <div class="purchases-page purchase-returns">
        <AdminPageHeader
            icon="fas fa-rotate-left"
            :title="$t('purchase_returns')"
            :subtitle="$t('purchase_returns_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="loading" :aria-label="$t('refresh')" @click="fetchReturns()" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="openCreate">
                    {{ $t('record_purchase_return') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Totals follow the filters: "what went back to this supplier this
             quarter" is the question these answer. -->
        <AdminStatGrid :min="190">
            <el-card v-for="card in statCards" :key="card.key" shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon" :class="card.tone"><el-icon><component :is="card.icon" /></el-icon></div>
                    <div class="stat-details">
                        <h3 :class="card.valueClass">{{ summary ? card.value : '—' }}</h3>
                        <p>{{ card.title }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('pret_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select
                    v-model="filters.supplier_id"
                    class="filter-select"
                    :placeholder="$t('pret_all_suppliers')"
                    filterable
                    clearable
                    @change="applyFilters"
                >
                    <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
                </el-select>
                <el-select
                    v-if="warehouses.length > 1"
                    v-model="filters.warehouse_id"
                    class="filter-select"
                    :placeholder="$t('pret_all_warehouses')"
                    clearable
                    @change="applyFilters"
                >
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-date-picker
                    v-model="filters.range"
                    type="daterange"
                    class="filter-dates"
                    value-format="YYYY-MM-DD"
                    format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('pret_from')"
                    :end-placeholder="$t('pret_to')"
                    :shortcuts="dateShortcuts"
                    @change="applyFilters"
                />
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="loadError && !returns.length" icon="error" :title="loadError">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchReturns()">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="loading"
                :data="returns"
                row-key="id"
                class="returns-table"
                style="width: 100%"
                :row-class-name="() => 'is-clickable'"
                @row-click="openDetail"
            >
                <template #empty>
                    <el-empty v-if="!loading && activeFilterCount" :description="$t('pret_no_matches')" :image-size="90">
                        <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!loading" :image-size="90">
                        <template #description>
                            <p class="empty-title">{{ $t('no_purchase_returns_yet') }}</p>
                            <p class="empty-hint">{{ $t('pret_empty_hint') }}</p>
                        </template>
                        <el-button type="primary" :icon="Plus" @click="openCreate">{{ $t('record_purchase_return') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column :label="$t('reference')" min-width="150">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <button type="button" class="ref-link" dir="ltr" @click.stop="openDetail(row)">{{ row.return_number }}</button>
                            <span class="cell-secondary">{{ formatDate(row.return_date) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('supplier')" min-width="190">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="cell-primary">{{ row.supplier?.name || '—' }}</span>
                            <span v-if="row.purchase_receipt" class="cell-secondary">
                                <el-icon :size="11"><Tickets /></el-icon>
                                <span dir="ltr">{{ row.purchase_receipt.receipt_number }}</span>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column v-if="warehouses.length > 1" :label="$t('warehouse')" min-width="130">
                    <template #default="{ row }">{{ row.warehouse?.name || '—' }}</template>
                </el-table-column>

                <el-table-column :label="$t('returned_items')" min-width="180">
                    <template #default="{ row }">
                        <el-tooltip :disabled="(row.items || []).length < 2" placement="top" :enterable="false">
                            <template #content>
                                <div v-for="item in row.items" :key="item.id">{{ itemName(item) }} × {{ item.quantity }}</div>
                            </template>
                            <div class="cell-stack">
                                <span class="cell-primary items-first">{{ itemsHeadline(row) }}</span>
                                <span class="cell-secondary">{{ $t('pret_units_n', { count: unitsOf(row) }) }}</span>
                            </div>
                        </el-tooltip>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('reason')" min-width="150" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span v-if="row.reason" class="reason-tag">{{ row.reason }}</span>
                        <span v-else class="cell-empty">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('pret_supplier_owed_less')" min-width="150" align="right">
                    <template #default="{ row }">
                        <div class="cell-stack amount-cell">
                            <strong>{{ formatCurrency(Number(row.credit_amount) + Number(row.tax_amount)) }}</strong>
                            <span v-if="Number(row.tax_amount)" class="cell-secondary">
                                {{ $t('pret_incl_tax', { amount: formatCurrency(row.tax_amount) }) }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column width="48" align="center">
                    <template #default>
                        <el-icon class="row-chevron"><ArrowLeft /></el-icon>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="total > 0" class="pagination-row">
                <span class="pagination-summary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </div>

        <PurchaseReturnForm
            v-model="createVisible"
            :suppliers="suppliers"
            :warehouses="warehouses"
            :preset-supplier-id="filters.supplier_id || null"
            @saved="onSaved"
        />

        <PurchaseReturnDetail v-model="detailVisible" :summary="detailRow" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import {
    ArrowLeft, Box, Coin, Plus, Refresh, RefreshLeft, Search, Tickets, TrendCharts, Van,
} from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import PurchaseReturnForm from '@/components/admin/purchases/PurchaseReturnForm.vue';
import PurchaseReturnDetail from '@/components/admin/purchases/PurchaseReturnDetail.vue';
import { purchaseReturnsApi } from '@/api/purchaseReturns';
import { suppliersApi } from '@/api/suppliers';
import { useInventoryStore } from '@/stores/inventory';
import { apiErrorMessage, formatCurrency, formatDate } from '@/utils/sales';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const inventoryStore = useInventoryStore();

const returns = ref([]);
const summary = ref(null);
const total = ref(0);
const loading = ref(false);
const loadError = ref('');
const suppliers = ref([]);
const warehouses = computed(() => inventoryStore.warehouses || []);

// ── Filters and paging, kept in the URL ───────────────────────────────────
const filters = reactive({ search: '', supplier_id: null, warehouse_id: null, range: null });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.supplier_id = Number(q.supplier_id) || null;
    filters.warehouse_id = Number(q.warehouse_id) || null;
    filters.range = q.from && q.to ? [String(q.from), String(q.to)] : null;
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        supplier_id: filters.supplier_id || undefined,
        warehouse_id: filters.warehouse_id || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.supplier_id, filters.warehouse_id, filters.range?.length ? '1' : '',
].filter(Boolean).length);

// ── Data ─────────────────────────────────────────────────────────────────
// A slow earlier response must not land over a newer one.
let requestSeq = 0;

const fetchReturns = async () => {
    const seq = ++requestSeq;
    loading.value = true;
    loadError.value = '';
    try {
        const res = await purchaseReturnsApi.getAll({
            page: currentPage.value,
            per_page: pageSize.value,
            search: filters.search || undefined,
            supplier_id: filters.supplier_id || undefined,
            warehouse_id: filters.warehouse_id || undefined,
            date_from: filters.range?.[0] || undefined,
            date_to: filters.range?.[1] || undefined,
            with_summary: 1,
        });
        if (seq !== requestSeq) return;
        const data = res.data?.data || {};
        returns.value = data.returns || [];
        total.value = data.pagination?.total || 0;
        summary.value = data.summary || null;
    } catch (e) {
        if (seq !== requestSeq) return;
        loadError.value = apiErrorMessage(e, t('pret_load_failed'));
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchReturns();
};

let searchTimer = null;
const onSearchInput = (value) => {
    clearTimeout(searchTimer);
    if (!value) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = () => {
    Object.assign(filters, { search: '', supplier_id: null, warehouse_id: null, range: null });
    applyFilters();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchReturns();
};

const rangeFrom = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, total.value));

const isoDay = (date) => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};

const dateShortcuts = computed(() => {
    const now = new Date();
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);
    const daysAgo = (n) => new Date(now.getFullYear(), now.getMonth(), now.getDate() - n);
    return [
        { text: t('pret_this_month'), value: () => [isoDay(monthStart), isoDay(now)] },
        { text: t('pret_last_month'), value: () => [isoDay(lastMonthStart), isoDay(lastMonthEnd)] },
        { text: t('pret_last_90_days'), value: () => [isoDay(daysAgo(90)), isoDay(now)] },
        { text: t('pret_this_year'), value: () => [isoDay(new Date(now.getFullYear(), 0, 1)), isoDay(now)] },
    ];
});

// ── Cards ────────────────────────────────────────────────────────────────
const statCards = computed(() => {
    const s = summary.value || {};
    const variance = Number(s.variance) || 0;
    return [
        { key: 'count', icon: Van, tone: 'blue', title: t('purchase_returns'), value: Number(s.count || 0).toLocaleString() },
        { key: 'credit', icon: Coin, tone: 'green', title: t('pret_total_credited'), value: formatCurrency(s.credit) },
        { key: 'tax', icon: Box, tone: 'purple', title: t('tax_returned'), value: formatCurrency(s.tax) },
        {
            key: 'variance',
            icon: TrendCharts,
            tone: variance < 0 ? 'red' : 'green',
            title: t('pret_credit_vs_cost'),
            value: `${variance > 0 ? '+' : ''}${formatCurrency(variance)}`,
            valueClass: variance < 0 ? 'is-bad' : variance > 0 ? 'is-good' : '',
        },
    ];
});

// ── Rows ─────────────────────────────────────────────────────────────────
const itemName = (item) => item.product?.name_ar || item.product?.name_en || `#${item.product_id}`;
const unitsOf = (row) => (row.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0);
const itemsHeadline = (row) => {
    const items = row.items || [];
    if (!items.length) return '—';
    const first = itemName(items[0]);
    return items.length > 1 ? t('pret_and_more', { name: first, count: items.length - 1 }) : first;
};

// ── Drawers ──────────────────────────────────────────────────────────────
const createVisible = ref(false);
const detailVisible = ref(false);
const detailRow = ref(null);

const openCreate = () => { createVisible.value = true; };

const openDetail = (row) => {
    detailRow.value = row;
    detailVisible.value = true;
};

const onSaved = async (created) => {
    currentPage.value = 1;
    writeQuery();
    await fetchReturns();
    // Show what was just recorded, as the list now has it.
    const row = returns.value.find((r) => r.id === created?.id);
    if (row) openDetail(row);
    loadSuppliers();
};

const loadSuppliers = async () => {
    try {
        const res = await suppliersApi.getAll({ per_page: 500 });
        suppliers.value = res.data?.data?.suppliers || [];
    } catch { /* the filter and form still work by typing */ }
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.purchase-returns.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchReturns();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    fetchReturns();
    loadSuppliers();
    if (!warehouses.value.length) inventoryStore.fetchWarehouses?.()?.catch?.(() => {});
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.purchase-returns { font-family: 'Cairo', sans-serif; }

/* ── Cards ── */
.stat-card { border-radius: 14px; }
.stat-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-details { min-width: 0; }
.stat-details h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-details h3.is-good { color: #16a34a; }
.stat-details h3.is-bad { color: #dc2626; }
.stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }

/* ── Panel and filters ── */
.panel-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
}

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 260px; max-width: 360px; }
.filter-select { width: 190px; }
.filter-dates { max-width: 280px; }

/* ── Table ── */
.returns-table :deep(.is-clickable) { cursor: pointer; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.cell-primary { font-weight: 600; color: #0f172a; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.cell-empty { color: #94a3b8; }
.items-first { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ref-link {
    all: unset;
    cursor: pointer;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #2563eb;
}
.ref-link:hover { text-decoration: underline; }
.ref-link:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.reason-tag {
    display: inline-block;
    max-width: 100%;
    padding: 0.1rem 0.6rem;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 0.78rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
}

.amount-cell { align-items: flex-end; font-variant-numeric: tabular-nums; }
.row-chevron { color: #cbd5e1; }
:deep(.el-table__row:hover) .row-chevron { color: #2563eb; }
[dir='ltr'] .row-chevron { transform: scaleX(-1); }

.empty-title { margin: 0; font-weight: 700; color: #334155; }
.empty-hint { margin: 0.35rem auto 0; max-width: 420px; font-size: 0.85rem; color: #64748b; line-height: 1.6; }

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
.pagination-summary { font-size: 0.85rem; color: #64748b; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 1.05rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
}
</style>
