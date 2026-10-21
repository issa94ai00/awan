<template>
    <div class="transfers-page">
        <AdminPageHeader
            icon="fas fa-truck-ramp-box"
            :title="$t('trf_title')"
            :subtitle="$t('trf_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="loading" :aria-label="$t('refresh')" @click="fetchTransfers()" />
                </el-tooltip>
                <el-button v-if="mayMove" type="primary" :icon="Plus" @click="formVisible = true">
                    {{ $t('trf_new') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- The stages as tabs: what is waiting on whom is the question here. -->
        <div class="stage-tabs" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                class="stage-tab"
                :class="[`st-${tab.key}`, { active: filters.status === tab.value }]"
                :aria-selected="filters.status === tab.value"
                @click="setStatus(tab.value)"
            >
                <span>{{ tab.label }}</span>
                <span class="stage-count">{{ tab.count }}</span>
            </button>
        </div>

        <div class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('trf_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.warehouse_id" class="filter-select" :placeholder="$t('trf_any_warehouse')" filterable clearable @change="applyFilters">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-select v-model="filters.from_warehouse_id" class="filter-select" :placeholder="$t('trf_any_source')" filterable clearable @change="applyFilters">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-select v-model="filters.to_warehouse_id" class="filter-select" :placeholder="$t('trf_any_destination')" filterable clearable @change="applyFilters">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="loadError && !transfers.length" icon="error" :title="loadError">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchTransfers()">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="loading"
                :data="transfers"
                row-key="id"
                class="transfers-table"
                style="width: 100%"
                :row-class-name="() => 'is-clickable'"
                @row-click="(row) => openDetail(row.id)"
            >
                <template #empty>
                    <el-empty v-if="!loading && (activeFilterCount || filters.status)" :description="$t('trf_no_matches')" :image-size="90">
                        <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!loading" :image-size="90">
                        <template #description>
                            <p class="empty-title">{{ $t('trf_empty') }}</p>
                            <p class="empty-hint">{{ $t('trf_empty_hint') }}</p>
                        </template>
                        <el-button v-if="mayMove" type="primary" :icon="Plus" @click="formVisible = true">{{ $t('trf_new') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column :label="$t('trf_number')" min-width="140">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="ref-text" dir="ltr">{{ row.transfer_number }}</span>
                            <span class="cell-secondary">{{ formatDate(row.requested_at || row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('trf_route')" min-width="220">
                    <template #default="{ row }">
                        <div class="route-cell">
                            <span>{{ row.from_warehouse?.name || '—' }}</span>
                            <i class="fas fa-arrow-left route-cell-arrow" aria-hidden="true"></i>
                            <strong>{{ row.to_warehouse?.name || '—' }}</strong>
                        </div>
                        <span v-if="row.created_by?.name" class="cell-secondary"><i class="fas fa-user"></i> {{ row.created_by.name }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('trf_items')" min-width="190">
                    <template #default="{ row }">
                        <el-tooltip :disabled="(row.items || []).length < 2" placement="top" :enterable="false">
                            <template #content>
                                <div v-for="item in row.items" :key="item.id">{{ transferProductName(item) }} × {{ item.quantity_requested }}</div>
                            </template>
                            <div class="cell-stack">
                                <span class="cell-primary items-first">{{ itemsHeadline(row) }}</span>
                                <span class="cell-secondary">{{ $t('trf_units_n', { count: unitsOf(row) }) }}</span>
                            </div>
                        </el-tooltip>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('trf_method')" min-width="120">
                    <template #default="{ row }">
                        <span v-if="row.fulfillment_method" class="method-tag" :class="`m-${row.fulfillment_method}`">
                            <i class="fas" :class="row.fulfillment_method === 'pickup' ? 'fa-hand-holding' : 'fa-truck'"></i>
                            {{ transferMethodLabel(t, row.fulfillment_method) }}
                        </span>
                        <span v-else class="cell-empty">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="140" align="center">
                    <template #default="{ row }">
                        <el-tag :type="transferStatusTone(row.status)" size="small" round>{{ transferStatusLabel(t, row.status) }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column width="132" align="center">
                    <template #default="{ row }">
                        <div class="actions-row" @click.stop>
                            <el-tooltip v-if="mayMove && canShip(row)" :content="$t('trf_approve')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="primary" :aria-label="$t('trf_approve')" @click="openDetail(row.id)">
                                    <i class="fas fa-truck-fast"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip v-if="mayMove && canReceive(row)" :content="$t('trf_receive')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="success" :aria-label="$t('trf_receive')" @click="openDetail(row.id)">
                                    <i class="fas fa-box-open"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('print')" placement="top" :enterable="false">
                                <el-button size="small" circle text :aria-label="$t('print')" @click="openPrint(row.id)">
                                    <i class="fas fa-print"></i>
                                </el-button>
                            </el-tooltip>
                            <el-icon class="row-chevron"><ArrowLeft /></el-icon>
                        </div>
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

        <TransferForm v-model="formVisible" :warehouses="warehouses" @saved="onCreated" />
        <TransferDetail v-model="detailVisible" :transfer-id="detailId" :may-move="mayMove" @changed="fetchTransfers" @print="openPrint" />
        <TransferPrintDialog v-model="printVisible" :transfer-id="printId" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import TransferForm from '@/components/admin/wms/TransferForm.vue';
import TransferDetail from '@/components/admin/wms/TransferDetail.vue';
import TransferPrintDialog from '@/components/admin/wms/TransferPrintDialog.vue';
import { inventoryTransfersApi } from '@/api/inventoryTransfers';
import { useInventoryStore } from '@/stores/inventory';
import { useAuthStore } from '@/stores/auth';
import { apiErrorMessage, formatDate } from '@/utils/sales';
import {
    TRANSFER_STATUSES, canReceive, canShip, transferMethodLabel, transferProductName,
    transferStatusLabel, transferStatusTone,
} from '@/utils/transfers';

/**
 * Stock moving between the company's own warehouses — raised here or asked
 * for by a branch from the field app — by the stage it is waiting at.
 */
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const inventoryStore = useInventoryStore();
const authStore = useAuthStore();

// A sales rep may look but not move stock (the API refuses it too).
const mayMove = computed(() => !authStore.user?.is_sales_scoped);

const transfers = ref([]);
const statusCounts = ref({});
const total = ref(0);
const loading = ref(false);
const loadError = ref('');
const warehouses = computed(() => inventoryStore.warehouses || []);

// ── Filters and paging, kept in the URL ───────────────────────────────────
const filters = reactive({ search: '', status: '', warehouse_id: null, from_warehouse_id: null, to_warehouse_id: null });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.status = TRANSFER_STATUSES.includes(String(q.status)) ? String(q.status) : '';
    filters.warehouse_id = Number(q.warehouse_id) || null;
    filters.from_warehouse_id = Number(q.from) || null;
    filters.to_warehouse_id = Number(q.to) || null;
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        status: filters.status || undefined,
        warehouse_id: filters.warehouse_id || undefined,
        from: filters.from_warehouse_id || undefined,
        to: filters.to_warehouse_id || undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.warehouse_id, filters.from_warehouse_id, filters.to_warehouse_id,
].filter(Boolean).length);

// ── Data ─────────────────────────────────────────────────────────────────
// A slow earlier response must not land over a newer one.
let requestSeq = 0;

const fetchTransfers = async () => {
    const seq = ++requestSeq;
    loading.value = true;
    loadError.value = '';
    try {
        const res = await inventoryTransfersApi.getAll({
            page: currentPage.value,
            per_page: pageSize.value,
            search: filters.search || undefined,
            status: filters.status || undefined,
            warehouse_id: filters.warehouse_id || undefined,
            from_warehouse_id: filters.from_warehouse_id || undefined,
            to_warehouse_id: filters.to_warehouse_id || undefined,
        });
        if (seq !== requestSeq) return;
        const page = res.data?.data || {};
        transfers.value = page.data || [];
        total.value = page.total || 0;
        statusCounts.value = res.data?.status_counts || {};
    } catch (e) {
        if (seq !== requestSeq) return;
        loadError.value = apiErrorMessage(e, t('trf_load_failed'));
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchTransfers();
};

let searchTimer = null;
const onSearchInput = (value) => {
    clearTimeout(searchTimer);
    if (!value) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const setStatus = (status) => {
    filters.status = status;
    applyFilters();
};

const resetFilters = () => {
    Object.assign(filters, { search: '', status: '', warehouse_id: null, from_warehouse_id: null, to_warehouse_id: null });
    applyFilters();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchTransfers();
};

const rangeFrom = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, total.value));

// ── Tabs ─────────────────────────────────────────────────────────────────
// Counts follow the other filters but not the stage, so every tab shows its own.
const tabs = computed(() => {
    const counts = statusCounts.value || {};
    const all = Object.values(counts).reduce((sum, n) => sum + Number(n || 0), 0);
    return [
        { key: 'all', value: '', label: t('trf_tab_all'), count: all },
        ...TRANSFER_STATUSES.map((status) => ({
            key: status, value: status, label: transferStatusLabel(t, status), count: Number(counts[status] || 0),
        })),
    ];
});

// ── Rows ─────────────────────────────────────────────────────────────────
const unitsOf = (row) => (row.items || []).reduce((sum, item) => sum + Number(item.quantity_requested || 0), 0);
const itemsHeadline = (row) => {
    const items = row.items || [];
    if (!items.length) return '—';
    const first = transferProductName(items[0]);
    return items.length > 1 ? t('pret_and_more', { name: first, count: items.length - 1 }) : first;
};

// ── Drawers ──────────────────────────────────────────────────────────────
const formVisible = ref(false);
const detailVisible = ref(false);
const detailId = ref(null);
const printVisible = ref(false);
const printId = ref(null);

const openDetail = (id) => {
    detailId.value = id;
    detailVisible.value = true;
};

const openPrint = (id) => {
    printId.value = id;
    printVisible.value = true;
};

// Show what was just raised, as the list now has it.
const onCreated = async (created) => {
    filters.status = '';
    currentPage.value = 1;
    writeQuery();
    await fetchTransfers();
    if (created?.id) openDetail(created.id);
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.wms.transfers' || query.open || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchTransfers();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();

    // A link to one transfer opens it.
    const openId = Number(route.query.open);
    const { open: _open, ...listQuery } = route.query;
    lastQueryKey = queryKey(listQuery);
    if (openId) {
        router.replace({ query: listQuery });
        openDetail(openId);
    }

    fetchTransfers();
    if (!warehouses.value.length) inventoryStore.fetchWarehouses?.()?.catch?.(() => {});
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.transfers-page { font-family: 'Cairo', sans-serif; }

/* ── Stage tabs ── */
.stage-tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.stage-tab {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}
.stage-tab:hover { border-color: #93c5fd; color: #1d4ed8; }
.stage-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.stage-tab.active { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.stage-count { min-width: 1.3rem; padding: 0 0.4rem; text-align: center; font-size: 0.75rem; font-weight: 700; border-radius: 999px; background: rgba(100, 116, 139, 0.14); }
.stage-tab.active .stage-count { background: rgba(255, 255, 255, 0.25); }
.stage-tab.st-pending:not(.active) .stage-count { background: #fef3c7; color: #b45309; }

/* ── Panel and filters ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 180px; }

/* ── Table ── */
.transfers-table :deep(.is-clickable) { cursor: pointer; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.cell-primary { font-weight: 600; color: #0f172a; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.cell-empty { color: #94a3b8; }
.items-first { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ref-text { font-family: ui-monospace, monospace; font-weight: 700; color: #2563eb; }

.route-cell { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; color: #334155; }
.route-cell strong { color: #0f172a; }
.route-cell-arrow { color: #94a3b8; font-size: 0.75rem; }
[dir='ltr'] .route-cell-arrow { transform: scaleX(-1); }

.method-tag { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.8rem; font-weight: 600; padding: 0.1rem 0.55rem; border-radius: 999px; }
.method-tag.m-delivery { background: #eff6ff; color: #1d4ed8; }
.method-tag.m-pickup { background: #f5f3ff; color: #6d28d9; }

.actions-row { display: inline-flex; align-items: center; gap: 0.1rem; }
.row-chevron { color: #cbd5e1; }
:deep(.el-table__row:hover) .row-chevron { color: #2563eb; }
[dir='ltr'] .row-chevron { transform: scaleX(-1); }

.empty-title { margin: 0; font-weight: 700; color: #334155; }
.empty-hint { margin: 0.35rem auto 0; max-width: 440px; font-size: 0.85rem; color: #64748b; line-height: 1.6; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
.pagination-summary { font-size: 0.85rem; color: #64748b; }

@media (max-width: 768px) {
    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .pagination-row { justify-content: center; }
}
</style>
