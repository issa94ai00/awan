<template>
    <div class="invoices-page">
        <AdminPageHeader
            icon="fas fa-file-invoice-dollar"
            :title="$t('invoices')"
            :subtitle="$t('inv_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="fetchInvoices" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="goToCreate">{{ $t('create_invoice') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Money, over the whole search ── -->
        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.billed) }}</h3>
                        <p>{{ $t('inv_billed') }}</p>
                        <span class="stat-sub">{{ $t('inv_invoices_n', { count: liveCount }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.payment === 'paid' }" @click="setPayment('paid')">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.collected) }}</h3>
                        <p>{{ $t('inv_collected') }}</p>
                        <div class="collect-bar" :aria-label="`${collectedShare}%`"><span :style="{ width: `${collectedShare}%` }" /></div>
                        <span class="stat-sub">{{ $t('inv_collected_share', { pct: collectedShare }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.payment === 'due' }" @click="setPayment('due')">
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.outstanding) }}</h3>
                        <p>{{ $t('outstanding_amount') }}</p>
                        <span class="stat-sub">{{ $t('inv_on_invoices_n', { count: summary.outstanding_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                v-if="summary.credit_count"
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': filters.payment === 'credit' }"
                @click="setPayment('credit')"
            >
                <div class="stat-inner">
                    <div class="stat-icon purple"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.credit) }}</h3>
                        <p>{{ $t('inv_customer_credit') }}</p>
                        <span class="stat-sub">{{ $t('inv_overpaid_n', { count: summary.credit_count }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- ── How long the outstanding money has been owed ── -->
        <section v-if="summary.outstanding > 0" class="aging">
            <div class="aging-head">
                <strong>{{ $t('inv_aging_title') }}</strong>
                <span class="stat-sub">{{ $t('inv_aging_hint') }}</span>
            </div>
            <div class="aging-bar" role="group" :aria-label="$t('inv_aging_title')">
                <button
                    v-for="bucket in agingBuckets"
                    v-show="bucket.amount > 0"
                    :key="bucket.key"
                    type="button"
                    class="aging-part"
                    :class="[`a-${bucket.key}`, { 'is-on': bucket.olderThan && filters.older_than === bucket.olderThan }]"
                    :style="{ flexGrow: bucket.amount }"
                    :title="`${bucket.label}: ${formatCurrency(bucket.amount)}`"
                    :disabled="!bucket.olderThan"
                    @click="setOlderThan(bucket.olderThan)"
                />
            </div>
            <div class="aging-legend">
                <button
                    v-for="bucket in agingBuckets"
                    :key="bucket.key"
                    type="button"
                    class="aging-key"
                    :class="{ 'is-on': bucket.olderThan && filters.older_than === bucket.olderThan }"
                    :disabled="!bucket.olderThan"
                    @click="setOlderThan(bucket.olderThan)"
                >
                    <span class="dot" :class="`a-${bucket.key}`" />
                    {{ bucket.label }}
                    <strong>{{ formatCurrency(bucket.amount) }}</strong>
                </button>
            </div>
        </section>

        <section class="panel-card">
            <div class="status-tabs" role="tablist">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    class="status-tab"
                    :class="{ 'is-on': filters.status === tab.value }"
                    :aria-selected="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span class="status-tab-count">{{ tab.count }}</span>
                </button>
            </div>

            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('inv_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.payment" class="filter-select" :placeholder="$t('inv_any_payment')" clearable @change="applyFilters">
                    <el-option v-for="p in PAYMENT_FILTERS" :key="p" :value="p" :label="$t(`inv_pay_filter_${p}`)" />
                </el-select>
                <el-select v-model="filters.source" class="filter-select" :placeholder="$t('inv_any_source')" clearable @change="applyFilters">
                    <el-option value="direct" :label="$t('inv_source_direct')" />
                    <el-option value="order" :label="$t('inv_source_order')" />
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

            <el-result v-if="store.error && !store.invoices.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchInvoices">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.invoices"
                row-key="id"
                style="width: 100%"
                class="invoices-table"
                :row-class-name="({ row }) => (row.status === 'cancelled' ? 'is-cancelled' : '')"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
                @row-click="(row, column) => column?.property !== 'actions' && openInvoice(row)"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && (activeFilterCount || filters.status)" :description="$t('there_are_no_invoices_matching')" :image-size="90">
                        <el-button @click="resetFilters(true)">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('no_invoices_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="goToCreate">{{ $t('create_invoice') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column prop="invoice_number" :label="$t('invoice_number')" min-width="170" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.invoice_number }}</span>
                            <span class="cell-secondary">
                                {{ formatDate(row.created_at) }}
                                <template v-if="row.sales_order">
                                    ·
                                    <button type="button" class="link-button" @click.stop="goToOrder(row.sales_order)">
                                        <span dir="ltr">{{ row.sales_order.order_number }}</span>
                                    </button>
                                </template>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="strong">{{ customerName(row) }}</span>
                            <span v-if="row.customer_phone" class="cell-secondary" dir="ltr">{{ row.customer_phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="total" :label="$t('total')" min-width="120" align="right" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack align-end">
                            <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                            <span class="cell-secondary">{{ $t('inv_items_n', { count: row.items_count ?? 0 }) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="due" :label="$t('payment_status')" min-width="180" sortable="custom">
                    <template #default="{ row }">
                        <div v-if="row.payment_state" class="pay-cell">
                            <div class="pay-line">
                                <span class="pay-state" :class="`p-${row.payment_state}`">{{ $t(`inv_pay_${row.payment_state}`) }}</span>
                                <span v-if="row.payment_state === 'unpaid' || row.payment_state === 'partial'" class="amount due">
                                    {{ formatCurrency(row.outstanding) }}
                                </span>
                                <span v-else-if="row.payment_state === 'credit'" class="amount credit">
                                    +{{ formatCurrency(-row.outstanding) }}
                                </span>
                            </div>
                            <el-progress
                                :percentage="paidPercentage(row)"
                                :status="paidPercentage(row) >= 100 ? 'success' : undefined"
                                :stroke-width="5"
                                :show-text="false"
                            />
                            <span v-if="row.outstanding > 0.009 && row.age_days >= 30" class="age-note" :class="{ bad: row.age_days >= 60 }">
                                {{ $t('inv_owed_days', { count: row.age_days }) }}
                            </span>
                        </div>
                        <span v-else class="cell-secondary">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="140">
                    <template #default="{ row }">
                        <el-dropdown
                            v-if="forwardMoves(row).length"
                            trigger="click"
                            @command="(status) => changeStatus(row, status)"
                            @click.stop
                        >
                            <button type="button" class="status-pill clickable" :class="`s-${row.status}`" @click.stop>
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                                <i class="fas fa-chevron-down chevron"></i>
                            </button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-for="status in forwardMoves(row)" :key="status" :command="status">
                                        <i class="fas" :class="statusIcon(status)"></i> {{ $t('inv_move_to', { status: statusLabel(status) }) }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <span v-else class="status-pill" :class="`s-${row.status}`" :title="row.sales_order ? $t('inv_order_drives_status') : ''">
                            <i class="fas" :class="statusIcon(row.status)"></i>
                            {{ statusLabel(row.status) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column prop="actions" :label="$t('actions')" min-width="170" align="center">
                    <template #default="{ row }">
                        <div class="row-actions" @click.stop>
                            <el-button
                                v-if="canRecordPayment(row)"
                                size="small"
                                type="success"
                                plain
                                @click="openPaymentDialog(row)"
                            >
                                <i class="fas fa-money-bill-wave"></i>&nbsp;{{ $t('inv_collect') }}
                            </el-button>
                            <el-dropdown trigger="click" @command="(cmd) => runCommand(row, cmd)">
                                <el-button size="small" text circle :aria-label="$t('inv_more_actions')">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="open">
                                            <i class="fas" :class="row.status === 'cancelled' ? 'fa-eye' : 'fa-pen'"></i>
                                            {{ row.status === 'cancelled' ? $t('view_details') : $t('edit') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.sales_order" command="order">
                                            <i class="fas fa-cart-shopping"></i> {{ $t('inv_open_order') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item command="payments">
                                            <i class="fas fa-receipt"></i> {{ $t('inv_view_payments') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="canCancel(row)" command="cancel" divided class="danger-item">
                                            <i class="fas fa-ban"></i> {{ $t('inv_cancel') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.status === 'cancelled'" command="delete" divided class="danger-item">
                                            <i class="fas fa-trash"></i> {{ $t('delete') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="store.pagination.total > 0" class="pagination-row">
                <span class="cell-secondary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: store.pagination.total }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="store.pagination.total"
                    :page-sizes="[15, 30, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </section>

        <QuickPaymentDialog
            v-model="paymentDialogVisible"
            :invoice="paymentTarget"
            @saved="onPaymentSaved"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import { useInvoicesStore } from '@/stores/invoices';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import {
    ORDER_STATUSES,
    apiErrorMessage,
    customerName,
    formatCurrency,
    formatDate,
    invoicePaidPercentage,
    localIsoDate,
    statusIcon,
    statusLabel,
} from '@/utils/sales';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useInvoicesStore();

const PAYMENT_FILTERS = ['due', 'unpaid', 'partial', 'paid', 'credit'];

const goToCreate = () => router.push('/admin/sales/invoices/create');
const goToOrder = (order) => router.push({ path: '/admin/sales/sales-orders', query: { open: order.id } });
const openInvoice = (invoice) => router.push(`/admin/sales/invoices/${invoice.id}/edit`);

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary || {
    total: 0, by_status: {}, billed: 0, collected: 0, outstanding: 0, outstanding_count: 0,
    aging: {}, credit: 0, credit_count: 0,
});
const liveCount = computed(() => (summary.value.total || 0) - (summary.value.by_status?.cancelled || 0));
const collectedShare = computed(() => (summary.value.billed > 0
    ? Math.min(100, Math.round((summary.value.collected / summary.value.billed) * 100))
    : 0));

const agingBuckets = computed(() => {
    const a = summary.value.aging || {};
    return [
        { key: '0_30', label: t('inv_age_0_30'), amount: a['0_30'] || 0, olderThan: null },
        { key: '31_60', label: t('inv_age_31_60'), amount: a['31_60'] || 0, olderThan: 30 },
        { key: '61_90', label: t('inv_age_61_90'), amount: a['61_90'] || 0, olderThan: 60 },
        { key: '90_plus', label: t('inv_age_90_plus'), amount: a['90_plus'] || 0, olderThan: 90 },
    ];
});

const statusTabs = computed(() => [
    { value: '', label: t('all'), count: summary.value.total || 0 },
    ...ORDER_STATUSES
        .filter((status) => (summary.value.by_status?.[status] || 0) > 0 || status === 'pending')
        .map((status) => ({ value: status, label: statusLabel(status), count: summary.value.by_status?.[status] || 0 })),
]);

// ── Filters, sorting and paging, kept in the URL ─────────────────────────
const blankFilters = () => ({ search: '', status: '', payment: '', source: '', range: null, older_than: null });
const filters = reactive(blankFilters());
const sort = reactive({ prop: 'created_at', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(15);

const readQuery = () => {
    const q = route.query;
    Object.assign(filters, {
        // Arriving from a sales order, a journal entry or the dashboard: the
        // caller names the invoice it means with ?invoice=.
        search: q.search ? String(q.search) : (q.invoice ? String(q.invoice) : ''),
        status: ORDER_STATUSES.includes(q.status) ? q.status : '',
        payment: PAYMENT_FILTERS.includes(q.payment) ? q.payment : '',
        source: ['direct', 'order'].includes(q.source) ? q.source : '',
        range: q.from && q.to ? [String(q.from), String(q.to)] : null,
        older_than: [30, 60, 90].includes(Number(q.older_than)) ? Number(q.older_than) : null,
    });
    sort.prop = ['total', 'invoice_number', 'due'].includes(q.sort) ? q.sort : 'created_at';
    sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [15, 30, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 15;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        status: filters.status || undefined,
        payment: filters.payment || undefined,
        source: filters.source || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        older_than: filters.older_than || undefined,
        sort: sort.prop !== 'created_at' ? sort.prop : undefined,
        direction: sort.order === 'ascending' ? 'asc' : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 15 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.payment, filters.source, filters.range?.length ? '1' : '', filters.older_than,
].filter(Boolean).length);

const fetchInvoices = () => store.fetchInvoices({
    lean: 1,
    with_summary: 1,
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search.trim() || undefined,
    status: filters.status || undefined,
    payment: filters.payment || undefined,
    source: filters.source || undefined,
    date_from: filters.range?.[0] || undefined,
    date_to: filters.range?.[1] || undefined,
    older_than: filters.older_than || undefined,
    sort: sort.prop,
    direction: sort.order === 'ascending' ? 'asc' : 'desc',
}).catch(() => {});

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = (withStatus = false) => {
    const status = filters.status;
    Object.assign(filters, blankFilters());
    if (withStatus !== true) filters.status = status;
    applyFilters();
};

const setStatus = (status) => {
    filters.status = filters.status === status ? '' : status;
    applyFilters();
};

const setPayment = (payment) => {
    filters.payment = filters.payment === payment ? '' : payment;
    filters.older_than = null;
    applyFilters();
};

const setOlderThan = (days) => {
    if (!days) return;
    filters.older_than = filters.older_than === days ? null : days;
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'created_at';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

const rangeFrom = computed(() => (store.pagination.total ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, store.pagination.total));

const dateShortcuts = computed(() => {
    const now = new Date();
    const at = (y, m, d) => localIsoDate(new Date(y, m, d));
    const y = now.getFullYear();
    const m = now.getMonth();
    return [
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_last_90_days'), value: () => [at(y, m, now.getDate() - 90), localIsoDate(now)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

// ── Row state ────────────────────────────────────────────────────────────
const paidPercentage = invoicePaidPercentage;

const canRecordPayment = (invoice) => invoice.status !== 'cancelled' && Number(invoice.outstanding) > 0.009;

// Moves along the stages from the status pill; cancelling is its own,
// clearly-worded menu item because of what it undoes.
const forwardMoves = (invoice) => (invoice.allowed_statuses || []).filter((s) => s !== 'cancelled');
const canCancel = (invoice) => (invoice.allowed_statuses || []).includes('cancelled');

// ── Actions ──────────────────────────────────────────────────────────────
const changeStatus = async (invoice, status) => {
    try {
        await ElMessageBox.confirm(
            t('inv_move_confirm', { number: invoice.invoice_number, status: statusLabel(status) }),
            t('change_invoice_status'),
            { type: 'info', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.updateInvoiceStatus(invoice.id, status);
        ElMessage.success(t('invoice_status_changed'));
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_change_invoice_status')));
    }
};

const cancelInvoice = async (invoice) => {
    const paid = Number(invoice.paid_amount) || 0;
    try {
        await ElMessageBox.confirm(
            paid > 0.009
                ? t('inv_cancel_confirm_paid', { number: invoice.invoice_number, amount: formatCurrency(paid) })
                : t('inv_cancel_confirm', { number: invoice.invoice_number }),
            t('inv_cancel'),
            {
                type: 'warning',
                confirmButtonText: t('inv_cancel'),
                cancelButtonText: t('inv_keep_invoice'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await store.updateInvoiceStatus(invoice.id, 'cancelled');
        ElMessage.success({ message: t('inv_cancelled_done'), duration: 5000 });
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_change_invoice_status')));
    }
};

const removeInvoice = async (invoice) => {
    try {
        await ElMessageBox.confirm(
            t('inv_delete_confirm', { number: invoice.invoice_number }),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await store.deleteInvoice(invoice.id);
        ElMessage.success(t('invoice_deleted'));
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_invoice')));
    }
};

const runCommand = (invoice, command) => {
    if (command === 'open') return openInvoice(invoice);
    if (command === 'order') return goToOrder(invoice.sales_order);
    if (command === 'payments') return router.push({ path: '/admin/sales/payments', query: { search: invoice.invoice_number } });
    if (command === 'cancel') return cancelInvoice(invoice);
    if (command === 'delete') return removeInvoice(invoice);
    return null;
};

// ── Payment ──────────────────────────────────────────────────────────────
const paymentDialogVisible = ref(false);
const paymentTarget = ref(null);

const openPaymentDialog = (invoice) => {
    paymentTarget.value = invoice;
    paymentDialogVisible.value = true;
};

// A payment changes paid, due and the totals, so the list is refetched.
const onPaymentSaved = () => {
    paymentTarget.value = null;
    fetchInvoices();
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

watch(() => route.query, (query) => {
    if (route.name !== 'admin.sales.invoices' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchInvoices();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    // ?invoice= is folded into the search, so the URL is rewritten to match.
    if (route.query.invoice) writeQuery();
    else lastQueryKey = queryKey(route.query);
    fetchInvoices();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.invoices-page { font-family: 'Cairo', sans-serif; }

/* ── Cards ── */
.stat-card { border-radius: 14px; transition: border-color 0.15s, box-shadow 0.15s; }
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-active { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
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
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-details { min-width: 0; flex: 1; }
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
.stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }
.stat-sub { display: block; font-size: 0.74rem; color: #94a3b8; margin-top: 0.15rem; }
.collect-bar { height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; margin-top: 0.4rem; }
.collect-bar span { display: block; height: 100%; background: #16a34a; border-radius: 999px; }

/* ── Aging ── */
.aging { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem 1.1rem; margin-bottom: 1.25rem; }
.aging-head { display: flex; align-items: baseline; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.6rem; }
.aging-head .stat-sub { margin: 0; }
.aging-bar { display: flex; gap: 3px; height: 12px; border-radius: 999px; overflow: hidden; background: #f1f5f9; }
.aging-part { all: unset; min-width: 6px; cursor: pointer; background: var(--a); }
.aging-part:disabled { cursor: default; }
.aging-part.is-on { outline: 2px solid #0f172a; outline-offset: -2px; }
.a-0_30 { --a: #93c5fd; }
.a-31_60 { --a: #fbbf24; }
.a-61_90 { --a: #f97316; }
.a-90_plus { --a: #dc2626; }
.aging-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; margin-top: 0.6rem; }
.aging-key { all: unset; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #475569; padding: 0.1rem 0.35rem; border-radius: 6px; }
.aging-key:disabled { cursor: default; }
.aging-key:not(:disabled):hover, .aging-key.is-on { background: #f1f5f9; }
.aging-key:focus-visible, .aging-part:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.aging-key strong { font-variant-numeric: tabular-nums; color: #0f172a; }
.dot { width: 9px; height: 9px; border-radius: 50%; background: var(--a); }

/* ── Panel ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }

.status-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.9rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
.status-tab {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    color: #475569;
}
.status-tab:hover { background: #f1f5f9; }
.status-tab.is-on { background: #0f172a; color: #fff; }
.status-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.status-tab-count { font-size: 0.72rem; font-weight: 700; background: rgba(100, 116, 139, 0.14); border-radius: 999px; padding: 0 0.45rem; min-width: 1.2rem; text-align: center; }
.status-tab.is-on .status-tab-count { background: rgba(255, 255, 255, 0.2); }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 170px; }
.filter-dates { max-width: 270px; }

/* ── Table ── */
.invoices-table :deep(.el-table__row) { cursor: pointer; }
.invoices-table :deep(.is-cancelled td) { color: #94a3b8; }
.invoices-table :deep(.is-cancelled .amount) { text-decoration: line-through; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-stack.align-end { align-items: flex-end; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; font-size: 0.85rem; }
.strong { font-weight: 600; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.amount.due { color: #b91c1c; font-size: 0.82rem; }
.amount.credit { color: #7c3aed; font-size: 0.82rem; }

.link-button { all: unset; cursor: pointer; color: #2563eb; font-weight: 600; }
.link-button:hover { text-decoration: underline; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.pay-cell { display: grid; gap: 0.3rem; min-width: 0; }
.pay-line { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.pay-state { font-size: 0.78rem; font-weight: 700; }
.pay-state.p-paid { color: #16a34a; }
.pay-state.p-partial { color: #d97706; }
.pay-state.p-unpaid { color: #dc2626; }
.pay-state.p-credit { color: #7c3aed; }
.age-note { font-size: 0.72rem; color: #d97706; }
.age-note.bad { color: #dc2626; font-weight: 600; }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--c, #64748b);
    background: color-mix(in srgb, var(--c, #64748b) 11%, #fff);
    border: none;
    font-family: inherit;
}
.status-pill.clickable { cursor: pointer; }
.status-pill.clickable:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.status-pill .chevron { font-size: 0.6rem; opacity: 0.7; }
.s-pending { --c: #d97706; }
.s-confirmed { --c: #2563eb; }
.s-processing { --c: #7c3aed; }
.s-shipped { --c: #0891b2; }
.s-delivered { --c: #16a34a; }
.s-cancelled { --c: #64748b; }

.row-actions { display: inline-flex; align-items: center; gap: 0.25rem; }
:deep(.danger-item) { color: #dc2626; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card, .aging { padding: 0.85rem; }
    .status-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .status-tab { white-space: nowrap; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
}
</style>
