<template>
    <div class="credit-notes-page">
        <AdminPageHeader
            icon="fas fa-file-circle-minus"
            :title="$t('credit_notes')"
            :subtitle="$t('cn_list_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="loading" :aria-label="$t('refresh')" @click="refreshAll" />
                </el-tooltip>
            </template>
        </AdminPageHeader>

        <!-- Every live note (or one customer's): cancelled ones are left out,
             and the status and search filters below do not narrow these. -->
        <AdminStatGrid :min="180">
            <el-card v-for="card in statCards" :key="card.key" shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon" :class="card.tone"><i :class="card.icon"></i></div>
                    <div class="stat-details">
                        <h3 :class="card.valueClass">{{ summary ? card.value : '—' }}</h3>
                        <p>{{ card.title }}</p>
                        <small v-if="card.sub && summary">{{ card.sub }}</small>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('cn_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select
                    v-model="filters.status"
                    class="filter-select"
                    :placeholder="$t('cn_all_statuses')"
                    clearable
                    :disabled="filters.openOnly"
                    @change="applyFilters"
                >
                    <el-option v-for="s in STATUSES" :key="s" :label="statusLabel(s)" :value="s" />
                </el-select>
                <el-checkbox v-model="filters.openOnly" border @change="onOpenOnlyChange">
                    {{ $t('cn_open_only') }}
                </el-checkbox>
                <el-tag v-if="filters.customer" closable size="large" class="customer-chip" @close="clearCustomer">
                    <i class="fas fa-user"></i> {{ $t('pay_customer_filter', { name: filters.customerName || `#${filters.customer}` }) }}
                </el-tag>
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="loadError && !notes.length" icon="error" :title="loadError">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchNotes()">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="loading"
                :data="notes"
                row-key="id"
                class="notes-table"
                style="width: 100%"
                :row-class-name="rowClass"
            >
                <template #empty>
                    <el-empty v-if="!loading && activeFilterCount" :description="$t('cn_no_matches')" :image-size="90">
                        <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!loading" :image-size="90">
                        <template #description>
                            <p class="empty-title">{{ $t('cn_empty') }}</p>
                            <p class="empty-hint">{{ $t('cn_empty_hint') }}</p>
                        </template>
                        <el-button type="primary" plain @click="router.push('/admin/rma')">{{ $t('cn_go_to_returns') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column :label="$t('note_number')" min-width="140">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <button type="button" class="ref-link" dir="ltr" @click="openPrint(row.id)">{{ row.credit_note_number }}</button>
                            <span class="cell-secondary">{{ formatDate(row.issue_date || row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <el-tooltip v-if="row.customer_id && String(row.customer_id) !== filters.customer" :content="$t('cn_filter_by_customer')" placement="top" :enterable="false">
                                <button type="button" class="link-button plain" @click="filterByCustomer(row)">{{ customerName(row) }}</button>
                            </el-tooltip>
                            <span v-else class="cell-primary">{{ customerName(row) }}</span>
                            <span v-if="row.customer?.phone" class="cell-secondary" dir="ltr">{{ row.customer.phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('source')" min-width="150">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <button v-if="row.rma_request" type="button" class="link-button" @click="router.push(`/admin/rma/${row.rma_request.id}`)">
                                <i class="fas fa-rotate-left"></i> <span dir="ltr">{{ row.rma_request.rma_number }}</span>
                            </button>
                            <span v-if="row.invoice" class="cell-secondary">
                                <i class="fas fa-file-invoice"></i> <span dir="ltr">{{ row.invoice.invoice_number }}</span>
                            </span>
                            <span v-if="!row.rma_request && !row.invoice" class="cell-empty">—</span>
                            <span v-if="row.reason" class="reason-tag" :title="row.reason">{{ row.reason }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('value')" min-width="120" align="right">
                    <template #default="{ row }">
                        <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                    </template>
                </el-table-column>

                <!-- Where the credit went, so nobody has to open the note to see it. -->
                <el-table-column :label="$t('cn_print_settlement')" min-width="190">
                    <template #default="{ row }">
                        <div class="settle-list">
                            <span v-if="num(row.applied_to_invoice) > 0">
                                {{ $t('deducted_from_invoice') }}: <b dir="ltr">{{ formatCurrency(row.applied_to_invoice) }}</b>
                            </span>
                            <span v-if="num(row.refunded_amount) > 0">
                                {{ $t('refunded_in_cash') }}: <b dir="ltr">{{ formatCurrency(row.refunded_amount) }}</b>
                            </span>
                            <span v-if="num(row.store_credit_amount) > 0">
                                {{ $t('credited_to_customer') }}: <b dir="ltr">{{ formatCurrency(row.store_credit_amount) }}</b>
                            </span>
                            <span v-if="num(row.settled_amount) < 0.01" class="cell-empty">—</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('not_settled')" min-width="120" align="right">
                    <template #default="{ row }">
                        <strong v-if="row.status !== 'cancelled'" class="amount" :class="num(row.open_amount) > 0.009 ? 'is-open' : 'is-closed'">
                            {{ formatCurrency(row.open_amount) }}
                        </strong>
                        <span v-else class="cell-empty">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="120" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTone(row.status)" size="small" round>{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column width="120" align="center">
                    <template #default="{ row }">
                        <div class="actions-row">
                            <el-tooltip :content="$t('print')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="primary" :aria-label="$t('print')" @click="openPrint(row.id)">
                                    <i class="fas fa-print"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip v-if="canCancel(row)" :content="$t('cn_cancel')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="danger" :aria-label="$t('cn_cancel')" :loading="cancellingId === row.id" @click="cancelNote(row)">
                                    <i class="fas fa-ban"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip v-else-if="row.status !== 'cancelled'" :content="$t('cn_cancel_locked')" placement="top" :enterable="false">
                                <i class="fas fa-lock locked"></i>
                            </el-tooltip>
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

        <CreditNotePrintDialog v-model="printVisible" :credit-note-id="printId" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import CreditNotePrintDialog from '@/components/admin/sales/CreditNotePrintDialog.vue';
import { creditNotesApi } from '@/api/creditNotes';
import { apiErrorMessage, customerName, formatCurrency, formatDate } from '@/utils/sales';

/**
 * Every credit note the returns settlement has raised: what each was worth,
 * where its credit went, and what is still open — with the note itself a
 * click away, and cancelling for the ones nothing has drawn on yet.
 */
const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const STATUSES = ['issued', 'partially_applied', 'applied', 'cancelled'];

const notes = ref([]);
const summary = ref(null);
const total = ref(0);
const loading = ref(false);
const loadError = ref('');

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};

const statusLabel = (status) => (STATUSES.includes(status) ? t(`cn_status_${status}`) : status || '—');
const statusTone = (status) => ({
    issued: 'warning',
    partially_applied: 'primary',
    applied: 'success',
    cancelled: 'info',
}[status] || 'info');

// ── Filters and paging, kept in the URL ───────────────────────────────────
const filters = reactive({ search: '', status: null, openOnly: false, customer: '', customerName: '' });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.status = STATUSES.includes(String(q.status)) ? String(q.status) : null;
    filters.openOnly = q.open === '1';
    filters.customer = q.customer_id ? String(q.customer_id) : '';
    filters.customerName = q.customer_name ? String(q.customer_name) : '';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        status: filters.status || undefined,
        open: filters.openOnly ? '1' : undefined,
        customer_id: filters.customer || undefined,
        customer_name: filters.customer ? filters.customerName || undefined : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.status, filters.openOnly ? '1' : '', filters.customer,
].filter(Boolean).length);

// ── Data ─────────────────────────────────────────────────────────────────
// A slow earlier response must not land over a newer one.
let requestSeq = 0;

const fetchNotes = async () => {
    const seq = ++requestSeq;
    loading.value = true;
    loadError.value = '';
    try {
        const res = await creditNotesApi.getAll({
            page: currentPage.value,
            per_page: pageSize.value,
            search: filters.search || undefined,
            // "Open only" already means issued or partly applied.
            status: filters.openOnly ? undefined : filters.status || undefined,
            open_only: filters.openOnly ? 1 : undefined,
            customer_id: filters.customer || undefined,
        });
        if (seq !== requestSeq) return;
        const data = res.data?.data || {};
        notes.value = data.credit_notes || [];
        total.value = data.pagination?.total || 0;
    } catch (e) {
        if (seq !== requestSeq) return;
        loadError.value = apiErrorMessage(e, t('cn_load_failed'));
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
};

// The cards follow the customer filter only; the server sums nothing else.
let summarySeq = 0;
const fetchSummary = async () => {
    const seq = ++summarySeq;
    try {
        const res = await creditNotesApi.summary({ customer_id: filters.customer || undefined });
        if (seq === summarySeq) summary.value = res.data?.data || null;
    } catch { /* the cards show dashes; the list still works */ }
};

const refreshAll = () => {
    fetchNotes();
    fetchSummary();
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchNotes();
};

let searchTimer = null;
const onSearchInput = (value) => {
    clearTimeout(searchTimer);
    if (!value) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const onOpenOnlyChange = () => {
    if (filters.openOnly) filters.status = null;
    applyFilters();
};

const filterByCustomer = (row) => {
    filters.customer = String(row.customer_id);
    filters.customerName = customerName(row);
    applyFilters();
    fetchSummary();
};

const clearCustomer = () => {
    filters.customer = '';
    filters.customerName = '';
    applyFilters();
    fetchSummary();
};

const resetFilters = () => {
    const hadCustomer = Boolean(filters.customer);
    Object.assign(filters, { search: '', status: null, openOnly: false, customer: '', customerName: '' });
    applyFilters();
    if (hadCustomer) fetchSummary();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchNotes();
};

const rangeFrom = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, total.value));

// ── Cards ────────────────────────────────────────────────────────────────
const statCards = computed(() => {
    const s = summary.value || {};
    const open = num(s.total_open);
    return [
        {
            key: 'issued', icon: 'fas fa-file-circle-minus', tone: 'purple',
            title: t('cn_stat_issued'), value: formatCurrency(s.total_issued),
            sub: t('cn_stat_count', { count: num(s.count) }),
        },
        { key: 'applied', icon: 'fas fa-file-invoice', tone: 'blue', title: t('deducted_from_invoice'), value: formatCurrency(s.total_applied_to_invoices) },
        { key: 'refunded', icon: 'fas fa-money-bill-wave', tone: 'green', title: t('refunded_in_cash'), value: formatCurrency(s.total_refunded) },
        { key: 'store', icon: 'fas fa-wallet', tone: 'teal', title: t('credited_to_customer'), value: formatCurrency(s.total_store_credit) },
        {
            key: 'open', icon: 'fas fa-hourglass-half', tone: open > 0.009 ? 'amber' : 'green',
            title: t('not_settled_yet'), value: formatCurrency(open),
            valueClass: open > 0.009 ? 'is-open' : '',
        },
    ];
});

// ── Rows ─────────────────────────────────────────────────────────────────
const rowClass = ({ row }) => (row.status === 'cancelled' ? 'is-cancelled' : '');

// Nothing drawn on it yet: the server refuses the rest, so the button does too.
const canCancel = (row) => row.status !== 'cancelled' && num(row.settled_amount) <= 0.009;

const cancellingId = ref(null);
const cancelNote = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('cn_cancel_confirm', { number: row.credit_note_number, amount: formatCurrency(row.total) }),
            t('cn_cancel'),
            { type: 'warning', confirmButtonText: t('cn_cancel'), cancelButtonText: t('cancel') },
        );
    } catch {
        return;
    }
    cancellingId.value = row.id;
    try {
        await creditNotesApi.cancel(row.id);
        ElMessage.success(t('cn_cancelled_ok'));
        refreshAll();
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('cn_cancel_failed')));
    } finally {
        cancellingId.value = null;
    }
};

// ── Print ────────────────────────────────────────────────────────────────
const printVisible = ref(false);
const printId = ref(null);
const openPrint = (id) => {
    printId.value = id;
    printVisible.value = true;
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.credit-notes.index' || queryKey(query) === lastQueryKey) return;
    const customerBefore = filters.customer;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchNotes();
    if (filters.customer !== customerBefore) fetchSummary();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    refreshAll();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.credit-notes-page { font-family: 'Cairo', sans-serif; }

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
    font-size: 1.15rem;
}
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.teal { background: #f0fdfa; color: #0d9488; }
.stat-icon.amber { background: #fffbeb; color: #d97706; }
.stat-details { min-width: 0; }
.stat-details h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-details h3.is-open { color: #d97706; }
.stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }
.stat-details small { font-size: 0.75rem; color: #94a3b8; }

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
.customer-chip { font-weight: 600; }
.customer-chip i { margin-inline-end: 0.3rem; }

/* ── Table ── */
.notes-table :deep(.is-cancelled) td { color: #94a3b8; }
.notes-table :deep(.is-cancelled) .amount { text-decoration: line-through; color: #94a3b8; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-primary { font-weight: 600; color: #0f172a; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.cell-empty { color: #94a3b8; }

.ref-link,
.link-button {
    all: unset;
    cursor: pointer;
    color: #2563eb;
    font-weight: 600;
}
.ref-link { font-family: ui-monospace, monospace; font-weight: 700; }
.link-button { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; }
.link-button.plain { color: #0f172a; }
.ref-link:hover,
.link-button:hover { text-decoration: underline; }
.ref-link:focus-visible,
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.reason-tag {
    display: inline-block;
    max-width: 100%;
    margin-top: 0.15rem;
    padding: 0.05rem 0.55rem;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 0.75rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.amount { font-variant-numeric: tabular-nums; white-space: nowrap; }
.amount.is-open { color: #d97706; }
.amount.is-closed { color: #16a34a; }

.settle-list { display: flex; flex-direction: column; gap: 0.1rem; font-size: 0.8rem; color: #475569; }
.settle-list b { font-variant-numeric: tabular-nums; color: #0f172a; }

.actions-row { display: inline-flex; align-items: center; gap: 0.15rem; }
.locked { color: #cbd5e1; font-size: 0.85rem; padding: 0 0.45rem; }

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
    .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .pagination-row { justify-content: center; }
}
</style>
