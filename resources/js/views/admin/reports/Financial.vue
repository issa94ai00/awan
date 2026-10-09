<template>
    <div class="reports-financial">
        <AdminPageHeader
            badge="Finance"
            :title="$t('financial_report')"
            :subtitle="$t('frpt_subtitle')"
        >
            <template #actions>
                <el-button :icon="RefreshRight" :disabled="!activeFilterCount" class="secondary-action" @click="resetFilters">
                    {{ $t('reset') }}
                </el-button>
                <el-button type="primary" :icon="Download" :loading="exporting" @click="exportReport">
                    {{ $t('export') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Filters apply as they change and live in the address bar. Every
             figure below follows them: the page used to take its headline
             numbers from the dashboard's store-wide stats, so only the two
             tables at the bottom ever changed when a filter did. -->
        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('customer') }}</label>
                <el-select
                    v-model="filters.customer_id"
                    :placeholder="$t('all_customers')"
                    clearable
                    filterable
                    remote
                    remote-show-suffix
                    :remote-method="searchCustomers"
                    :loading="customerSearching"
                >
                    <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id">
                        <span>{{ customer.name }}</span>
                        <span v-if="customer.phone" class="option-hint" dir="ltr">{{ customer.phone }}</span>
                    </el-option>
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('warehouse') }}</label>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable filterable>
                    <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('status') }}</label>
                <el-select v-model="filters.status" :placeholder="$t('frpt_all_but_cancelled')" clearable>
                    <el-option v-for="status in STATUSES" :key="status" :label="statusText(status)" :value="status" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('sr_period') }}</label>
                <el-select v-model="filters.date_filter_type">
                    <el-option v-for="preset in PERIOD_PRESETS" :key="preset" :label="periodLabel(preset)" :value="preset" />
                </el-select>
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field filter-field--range">
                <label>{{ $t('date_range') }}</label>
                <el-date-picker
                    v-model="customRange"
                    type="daterange"
                    value-format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('start_date')"
                    :end-placeholder="$t('end_date')"
                    :range-separator="$t('to')"
                />
            </div>

            <template #actions>
                <span v-if="refreshing && ready.dims" class="updating-label" aria-live="polite">
                    <el-icon class="is-loading"><Loading /></el-icon>{{ $t('sr_updating') }}
                </span>
                <router-link :to="detailedReportLink" class="detail-link">
                    {{ $t('frpt_open_detailed') }}
                    <el-icon><Right /></el-icon>
                </router-link>
            </template>
        </AdminFilterBar>

        <el-result v-if="loadError" icon="error" :title="$t('failed_to_load_report')">
            <template #extra>
                <el-button type="primary" :icon="RefreshRight" :loading="dimsLoading" @click="loadAll">{{ $t('cat_admin_retry') }}</el-button>
            </template>
        </el-result>

        <template v-else>
            <!-- ── Headline figures ── -->
            <AdminStatGrid>
                <el-card
                    v-for="stat in stats"
                    :key="stat.key"
                    shadow="hover"
                    class="stat-card"
                    :class="[`stat-card-${stat.key}`, { 'is-refreshing': ready.dims && dimsLoading }]"
                >
                    <el-skeleton v-if="!ready.dims" :rows="2" animated />
                    <div v-else class="stat-content">
                        <div class="stat-icon"><component :is="stat.icon" /></div>
                        <div class="stat-info">
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.label }}</p>
                            <small v-if="stat.sub" :class="stat.subClass">{{ stat.sub }}</small>
                        </div>
                    </div>
                </el-card>
            </AdminStatGrid>

            <el-empty
                v-if="ready.dims && !dimsLoading && !totals.count && !cancelled.total_invoices"
                class="empty-report"
                :description="$t('no_data_for_current_filters')"
                :image-size="100"
            >
                <el-button v-if="activeFilterCount" @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
            </el-empty>

            <template v-else>
                <el-row :gutter="20" class="charts-section">
                    <!-- ── Billed and collected over time ── -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="hover" class="panel-card">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ $t('frpt_billing_trend') }}</span>
                                    <el-segmented v-model="groupBy" :options="groupOptions" size="small" />
                                </div>
                            </template>
                            <el-skeleton v-if="!ready.trend" :rows="7" animated />
                            <div v-else class="chart-wrap" :class="{ 'is-refreshing': trendLoading }">
                                <div ref="trendChartRef" class="chart-box"></div>
                                <p v-if="!hasTrend" class="chart-empty">{{ $t('no_data_for_current_filters') }}</p>
                            </div>
                        </el-card>
                    </el-col>

                    <!-- ── Collection and status ── -->
                    <!-- A progress bar and a ranked list in place of the donut:
                         how much of what was billed has come in is one
                         proportion, and each status is one click from being
                         the report's filter. -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="hover" class="panel-card">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ $t('frpt_collection') }}</span>
                                    <strong class="collect-rate" :class="collectionTone">{{ formatPercent(collectedShare) }}</strong>
                                </div>
                            </template>
                            <el-skeleton v-if="!ready.dims" :rows="6" animated />
                            <div v-else :class="{ 'is-refreshing': dimsLoading }">
                                <div class="collect-bar" role="img" :aria-label="`${$t('frpt_collected')}: ${formatPercent(collectedShare)}`">
                                    <span class="paid" :style="{ width: `${collectedShare}%` }" />
                                </div>
                                <div class="collect-legend">
                                    <span><i class="dot paid" />{{ $t('frpt_collected') }} · {{ formatCurrency(totals.paid) }}</span>
                                    <span><i class="dot due" />{{ $t('frpt_outstanding') }} · {{ formatCurrency(totals.due) }}</span>
                                </div>

                                <h4 class="list-title">{{ $t('frpt_by_status') }}</h4>
                                <ul class="status-list">
                                    <li v-for="row in statusRows" :key="row.status">
                                        <button
                                            type="button"
                                            class="status-row"
                                            :class="[`s-${row.status}`, { 'is-on': filters.status === row.status }]"
                                            @click="toggleStatus(row.status)"
                                        >
                                            <span class="status-name"><i class="dot" />{{ statusText(row.status) }}</span>
                                            <span class="status-count">{{ formatNumber(row.total_invoices) }}</span>
                                            <strong class="status-amount">{{ formatCurrency(row.total_invoiced) }}</strong>
                                            <span class="status-bar"><span :style="{ width: `${Math.max(1.5, row.share)}%` }" /></span>
                                        </button>
                                    </li>
                                </ul>
                                <p v-if="!statusRows.length" class="muted empty-line">{{ $t('no_data_for_current_filters') }}</p>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- ── Profitability ── -->
                <!-- Costing every invoice line is the slowest report here, so it
                     is fetched only while this card is on screen and open. -->
                <CollapsibleCard
                    id="financial-profitability"
                    :title="$t('frpt_profitability')"
                    class="section-card"
                    @active-change="profitSection.setActive"
                >
                    <template #extra>
                        <el-tooltip :content="$t('frpt_profit_hint')" placement="top">
                            <el-icon class="label-hint"><InfoFilled /></el-icon>
                        </el-tooltip>
                    </template>
                    <el-skeleton v-if="!ready.profit" :rows="2" animated />
                    <div v-else class="profit-grid" :class="{ 'is-refreshing': profitLoading }">
                        <div class="profit-metric">
                            <span>{{ $t('sales_revenue') }}</span>
                            <strong>{{ formatCurrency(profit.total_revenue) }}</strong>
                        </div>
                        <div class="profit-metric">
                            <span>{{ $t('cost_of_goods') }}</span>
                            <strong>{{ formatCurrency(profit.total_cost) }}</strong>
                        </div>
                        <div class="profit-metric">
                            <span>{{ $t('gross_profit') }}</span>
                            <strong :class="Number(profit.gross_profit) < 0 ? 'tone-bad' : 'tone-good'">{{ formatCurrency(profit.gross_profit) }}</strong>
                        </div>
                        <div class="profit-metric">
                            <span>{{ $t('profit_margin') }}</span>
                            <strong :class="Number(profit.gross_margin) < 0 ? 'tone-bad' : ''">{{ formatPercent(profit.gross_margin) }}</strong>
                            <div class="margin-bar"><span :style="{ width: `${Math.min(100, Math.abs(Number(profit.gross_margin) || 0))}%` }" /></div>
                        </div>
                    </div>
                </CollapsibleCard>

                <!-- ── Who and where ── -->
                <el-row :gutter="20">
                    <el-col v-for="table in breakdowns" :key="table.key" :xs="24" :lg="12">
                        <CollapsibleCard
                            :id="`financial-${table.key}`"
                            :title="table.title"
                            :count="table.rows.length || null"
                            class="section-card"
                        >
                            <template v-if="filters[table.filter]" #extra>
                                <el-button size="small" text type="primary" @click="filters[table.filter] = null">
                                    {{ $t('clear_filter') }}
                                </el-button>
                            </template>
                            <el-skeleton v-if="!ready.dims" :rows="5" animated />
                            <!-- A row narrows the whole report to it. -->
                            <el-table
                                v-else
                                :data="table.rows"
                                stripe
                                max-height="400"
                                style="width: 100%"
                                :class="{ 'is-refreshing': dimsLoading }"
                                :default-sort="{ prop: 'total_invoiced', order: 'descending' }"
                                :row-class-name="({ row }) => breakdownRowClass(table, row)"
                                @row-click="(row) => toggleBreakdown(table, row)"
                            >
                                <el-table-column :prop="table.nameKey" :label="table.label" min-width="140" show-overflow-tooltip />
                                <el-table-column prop="total_invoices" :label="$t('count')" width="80" align="center" sortable />
                                <el-table-column prop="total_invoiced" :label="$t('total_invoiced')" min-width="120" align="right" sortable>
                                    <template #default="{ row }"><span class="num">{{ formatCurrency(row.total_invoiced) }}</span></template>
                                </el-table-column>
                                <el-table-column prop="due_amount" :label="$t('due_amount')" min-width="110" align="right" sortable>
                                    <template #default="{ row }">
                                        <span class="num" :class="Number(row.due_amount) > 0 ? 'tone-warn' : 'muted'">{{ formatCurrency(row.due_amount) }}</span>
                                    </template>
                                </el-table-column>
                                <template #empty>
                                    <span class="muted">{{ $t('no_data_for_current_filters') }}</span>
                                </template>
                            </el-table>
                        </CollapsibleCard>
                    </el-col>
                </el-row>
            </template>
        </template>
    </div>
</template>

<script setup>
import { formatMoney, formatNumber as formatCount, numberLocale } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import { Coin, Wallet, CircleCheck, TrendCharts, Download, RefreshRight, InfoFilled, Loading, Right } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
const PERIOD_PRESETS = ['all', 'today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];
const GROUPS = ['day', 'week', 'month'];

/* ------------------------------------------------------------------ *
 * Filters, kept in the URL — the same keys the detailed sales report
 * reads, so "open the detailed report" carries them across as they are.
 * ------------------------------------------------------------------ */
const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
const toId = (value) => (/^\d+$/.test(String(value || '')) ? Number(value) : null);

const filtersFromQuery = (query) => {
    const period = PERIOD_PRESETS.includes(query.period) ? query.period : 'all';
    return {
        customer_id: toId(query.customer),
        warehouse_id: toId(query.warehouse),
        status: STATUSES.includes(query.status) ? query.status : '',
        date_filter_type: period,
        start_date: period === 'custom' && isIsoDate(query.from) ? query.from : null,
        end_date: period === 'custom' && isIsoDate(query.to) ? query.to : null,
    };
};

const filters = reactive(filtersFromQuery(route.query));

const filtersToQuery = () => {
    const query = {};
    if (filters.customer_id) query.customer = String(filters.customer_id);
    if (filters.warehouse_id) query.warehouse = String(filters.warehouse_id);
    if (filters.status) query.status = filters.status;
    if (filters.date_filter_type !== 'all') query.period = filters.date_filter_type;
    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) query.from = filters.start_date;
        if (filters.end_date) query.to = filters.end_date;
    }
    return query;
};

const periodLabel = (preset) => (preset === 'all' ? t('sr_all_time') : t(preset));

const customRange = computed({
    get: () => (filters.start_date && filters.end_date ? [filters.start_date, filters.end_date] : null),
    set: (range) => {
        filters.start_date = range?.[0] || null;
        filters.end_date = range?.[1] || null;
    },
});

const awaitingRange = computed(() => filters.date_filter_type === 'custom' && !filters.start_date && !filters.end_date);

const activeFilterCount = computed(() => [
    filters.customer_id,
    filters.warehouse_id,
    filters.status,
    filters.date_filter_type !== 'all' ? filters.date_filter_type : null,
].filter(Boolean).length);

const params = () => {
    const out = { date_filter_type: filters.date_filter_type || 'all' };
    if (filters.customer_id) out.customer_id = filters.customer_id;
    if (filters.warehouse_id) out.warehouse_id = filters.warehouse_id;
    if (filters.status) out.status = filters.status;
    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) out.start_date = filters.start_date;
        if (filters.end_date) out.end_date = filters.end_date;
    }
    return out;
};

const resetFilters = () => {
    Object.assign(filters, { customer_id: null, warehouse_id: null, status: '', date_filter_type: 'all', start_date: null, end_date: null });
};

const toggleStatus = (status) => {
    filters.status = filters.status === status ? '' : status;
};

const detailedReportLink = computed(() => ({
    name: 'admin.reports.professional-sales',
    query: { ...filtersToQuery(), tab: 'invoices' },
}));

/* ------------------------------------------------------------------ *
 * Chart grouping: days for a short period, weeks for a quarter, months
 * beyond — unless someone has picked one, which the URL then keeps.
 * ------------------------------------------------------------------ */
const suggestedGroup = () => {
    switch (filters.date_filter_type) {
        case 'today':
        case 'yesterday':
        case 'this_week':
        case 'this_month':
        case 'last_month':
            return 'day';
        case 'custom': {
            if (!filters.start_date || !filters.end_date) return 'day';
            const days = (new Date(filters.end_date) - new Date(filters.start_date)) / 86400000;
            if (days <= 45) return 'day';
            return days <= 190 ? 'week' : 'month';
        }
        default:
            return 'month';
    }
};

const groupBy = ref(GROUPS.includes(route.query.group) ? route.query.group : suggestedGroup());

const groupOptions = computed(() => [
    { label: t('daily'), value: 'day' },
    { label: t('weekly'), value: 'week' },
    { label: t('monthly'), value: 'month' },
]);

/* ------------------------------------------------------------------ *
 * Reference data
 * ------------------------------------------------------------------ */
const warehouses = ref([]);
const customers = ref([]);
const customerSearching = ref(false);
let customerSearchToken = 0;
let customerSearchTimer = null;

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        const data = response.data;
        warehouses.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data?.data?.data) ? data.data.data : []);
    } catch {
        warehouses.value = [];
    }
};

/** Searched on the server; the list used to be the newest hundred customers. */
const fetchCustomers = async (search = '') => {
    const token = ++customerSearchToken;
    customerSearching.value = true;
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 30, ...(search ? { search } : {}) } });
        if (token !== customerSearchToken) return;
        const rows = response.data?.data?.customers;
        const found = Array.isArray(rows) ? rows.map((item) => item.data || item) : [];
        const selected = customers.value.find((row) => Number(row.id) === Number(filters.customer_id));
        customers.value = selected && !found.some((row) => row.id === selected.id) ? [selected, ...found] : found;
    } catch {
        if (token === customerSearchToken) customers.value = [];
    } finally {
        if (token === customerSearchToken) customerSearching.value = false;
    }
};

const searchCustomers = (query) => {
    clearTimeout(customerSearchTimer);
    customerSearchTimer = setTimeout(() => fetchCustomers(query?.trim() || ''), 250);
};

/** A customer set from the URL or a table row needs a name in the select. */
const rememberCustomer = async (id, name = null) => {
    if (!id || customers.value.some((row) => Number(row.id) === Number(id))) return;
    if (name) {
        customers.value = [{ id: Number(id), name }, ...customers.value];
        return;
    }
    try {
        const response = await api.get(`/pos/customers/${id}`);
        const customer = response.data?.data?.data || response.data?.data;
        if (customer?.id) customers.value = [customer, ...customers.value];
    } catch {
        // The select falls back to the bare id; the report is unaffected.
    }
};

/* ------------------------------------------------------------------ *
 * Data — three requests that land independently
 *
 *   dims    headline figures, status, customers and warehouses
 *   trend   the chart; the only one a grouping change re-asks for
 *   profit  costed revenue — slow, so fetched only while shown
 * ------------------------------------------------------------------ */
const ready = reactive({ dims: false, trend: false, profit: false });
const loadError = ref(false);
const dimsLoading = ref(false);
const trendLoading = ref(false);
const profitLoading = ref(false);

const dims = ref({ customer_summary: [], warehouse_summary: [], status_summary: [], overall: {} });
const trend = ref([]);
const profit = ref({});

const refreshing = computed(() => dimsLoading.value || trendLoading.value || profitLoading.value);

/** Only a loader's latest call may land, or clear its spinner. */
const latestOnly = (loading, readyKey, run) => {
    let token = 0;
    return async () => {
        const mine = ++token;
        loading.value = true;
        try {
            await run(() => mine !== token);
        } finally {
            if (mine === token) {
                loading.value = false;
                ready[readyKey] = true;
            }
        }
    };
};

const loadDims = latestOnly(dimsLoading, 'dims', async (superseded) => {
    try {
        // Cancelled invoices stay out of the breakdowns, as they do out of
        // the headline figures; the status list still counts them.
        const response = await api.get('/admin/reports/invoices/dimensions', { params: { ...params(), exclude_cancelled: 1 } });
        if (superseded()) return;
        const data = response.data?.data || {};
        dims.value = {
            customer_summary: data.customer_summary || [],
            warehouse_summary: data.warehouse_summary || [],
            status_summary: data.status_summary || [],
            overall: data.overall || {},
        };
        loadError.value = false;
    } catch {
        if (!superseded()) loadError.value = true;
    }
});

const loadTrend = latestOnly(trendLoading, 'trend', async (superseded) => {
    try {
        const response = await api.get('/admin/reports/invoices/trend', { params: { ...params(), group_by: groupBy.value } });
        if (superseded()) return;
        trend.value = response.data?.data?.trend || [];
    } catch {
        if (!superseded()) trend.value = [];
    }
});

const loadProfit = latestOnly(profitLoading, 'profit', async (superseded) => {
    try {
        const response = await api.get('/admin/reports/invoices/performance', { params: { ...params(), exclude_cancelled: 1 } });
        if (superseded()) return;
        profit.value = response.data?.data?.summary || {};
    } catch {
        if (superseded()) return;
        profit.value = {};
        ElMessage.error(t('failed_to_load_report'));
    }
});

/** Loads while on screen and open; a filter change out of view waits for it. */
const profitSection = (() => {
    let active = false;
    let stale = true;
    const run = () => { stale = false; loadProfit(); };
    return {
        setActive(value) {
            active = value;
            if (active && stale) run();
        },
        invalidate() {
            stale = true;
            if (active) run();
        },
    };
})();

const loadAll = () => {
    loadDims();
    loadTrend();
    profitSection.invalidate();
};

/* ------------------------------------------------------------------ *
 * Figures
 *
 * Cancelled invoices are counted in the endpoint's `overall`, so billing
 * and collection are summed from the status rows with them left out —
 * unless cancelled is the status asked for.
 * ------------------------------------------------------------------ */
const statusSummary = computed(() => dims.value.status_summary || []);
const cancelled = computed(() => statusSummary.value.find((row) => row.status === 'cancelled') || { total_invoices: 0, total_invoiced: 0 });

const totals = computed(() => {
    const rows = filters.status === 'cancelled'
        ? statusSummary.value
        : statusSummary.value.filter((row) => row.status !== 'cancelled');
    return rows.reduce((sum, row) => ({
        count: sum.count + Number(row.total_invoices || 0),
        invoiced: sum.invoiced + Number(row.total_invoiced || 0),
        paid: sum.paid + Number(row.paid_amount || 0),
        due: sum.due + Number(row.due_amount || 0),
    }), { count: 0, invoiced: 0, paid: 0, due: 0 });
});

const collectedShare = computed(() => {
    const { paid, due } = totals.value;
    return paid + due > 0 ? (paid / (paid + due)) * 100 : 0;
});

const collectionTone = computed(() => {
    if (!totals.value.count) return '';
    if (collectedShare.value >= 80) return 'tone-good';
    return collectedShare.value >= 50 ? 'tone-warn' : 'tone-bad';
});

const stats = computed(() => {
    const { count, invoiced, paid, due } = totals.value;
    const cancelledNote = filters.status !== 'cancelled' && cancelled.value.total_invoices
        ? ` · ${t('frpt_cancelled_excluded', { count: formatNumber(cancelled.value.total_invoices) })}`
        : '';
    return [
        {
            key: 'invoiced',
            icon: Coin,
            label: t('frpt_invoiced'),
            value: formatCurrency(invoiced),
            sub: t('frpt_invoices_n', { count: formatNumber(count) }) + cancelledNote,
        },
        {
            key: 'collected',
            icon: CircleCheck,
            label: t('frpt_collected'),
            value: formatCurrency(paid),
            sub: invoiced ? t('frpt_of_invoiced', { percent: formatPercent(collectedShare.value) }) : '',
        },
        {
            key: 'outstanding',
            icon: Wallet,
            label: t('frpt_outstanding'),
            value: formatCurrency(due),
            subClass: due > 0 ? 'sub-warn' : '',
            sub: due > 0 ? t('frpt_still_to_collect', { percent: formatPercent(100 - collectedShare.value) }) : '',
        },
        {
            key: 'average',
            icon: TrendCharts,
            label: t('average_invoice_value'),
            value: formatCurrency(count ? invoiced / count : 0),
        },
    ];
});

const statusRows = computed(() => {
    const rows = STATUSES.map((status) => statusSummary.value.find((row) => row.status === status)).filter(Boolean);
    const max = Math.max(...rows.map((row) => Number(row.total_invoiced || 0)), 1);
    return rows.map((row) => ({ ...row, share: (Number(row.total_invoiced || 0) / max) * 100 }));
});

const breakdowns = computed(() => [
    {
        key: 'customers',
        title: t('customers'),
        label: t('customer'),
        nameKey: 'customer_name',
        idKey: 'customer_id',
        filter: 'customer_id',
        rows: dims.value.customer_summary || [],
    },
    {
        key: 'warehouses',
        title: t('warehouses'),
        label: t('warehouse'),
        nameKey: 'warehouse_name',
        idKey: 'warehouse_id',
        filter: 'warehouse_id',
        rows: dims.value.warehouse_summary || [],
    },
]);

const toggleBreakdown = (table, row) => {
    const id = row[table.idKey];
    if (!id) return;
    const next = Number(filters[table.filter]) === Number(id) ? null : id;
    if (next && table.filter === 'customer_id') rememberCustomer(next, row.customer_name);
    filters[table.filter] = next;
};

const breakdownRowClass = (table, row) => {
    if (!row[table.idKey]) return 'row-inert';
    return Number(filters[table.filter]) === Number(row[table.idKey]) ? 'is-active-row' : '';
};

/* ------------------------------------------------------------------ *
 * Chart — ECharts arrives the first time there is something to draw
 * ------------------------------------------------------------------ */
const trendChartRef = ref(null);
let trendChart = null;
let chartObserver = null;
let echartsLoader = null;
const loadEcharts = () => {
    echartsLoader ??= import('@/utils/echartsLite').then((module) => module.default);
    return echartsLoader;
};

const hasTrend = computed(() => trend.value.some((row) => Number(row.total_invoiced) || Number(row.paid_amount)));

const dateFormat = (options) => new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', options);

const periodName = (period) => {
    if (groupBy.value === 'month') {
        const [year, month] = period.split('-').map(Number);
        return dateFormat({ month: 'short', year: 'numeric' }).format(new Date(year, month - 1, 1));
    }
    const day = dateFormat({ day: 'numeric', month: 'short' }).format(new Date(`${period}T00:00:00`));
    return groupBy.value === 'week' ? t('frpt_week_of', { date: day }) : day;
};

const disposeChart = () => {
    chartObserver?.disconnect();
    chartObserver = null;
    trendChart?.dispose();
    trendChart = null;
};

const renderTrend = async () => {
    if (!hasTrend.value) {
        trendChart?.clear();
        return;
    }
    const echarts = await loadEcharts();
    await nextTick();
    const element = trendChartRef.value;
    if (!element) return;
    if (!trendChart || trendChart.getDom() !== element) {
        disposeChart();
        trendChart = echarts.init(element);
        if (typeof ResizeObserver !== 'undefined') {
            chartObserver = new ResizeObserver(() => trendChart?.resize());
            chartObserver.observe(element);
        }
    }

    const rows = trend.value;
    // Both series are money, so they share one axis honestly.
    trendChart.setOption({
        grid: { left: 8, right: 12, top: 36, bottom: 8, containLabel: true },
        legend: { top: 0, textStyle: { color: '#64748b' } },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (items) => {
                const row = rows[items[0]?.dataIndex];
                if (!row) return '';
                return `<strong>${items[0].axisValue}</strong><br/>`
                    + items.map((item) => `${item.marker} ${item.seriesName}: ${formatCurrency(item.value)}`).join('<br/>')
                    + `<br/>${t('invoices_count')}: ${formatNumber(row.total_invoices)}`;
            },
        },
        xAxis: {
            type: 'category',
            data: rows.map((row) => periodName(row.period)),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisLabel: { color: '#64748b', hideOverlap: true },
        },
        yAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#f1f5f9' } },
            axisLabel: { color: '#94a3b8', formatter: (value) => compactNumber(value) },
        },
        series: [
            {
                name: t('frpt_invoiced'),
                type: 'bar',
                data: rows.map((row) => Number(row.total_invoiced || 0)),
                barMaxWidth: 26,
                itemStyle: { color: '#2563eb', borderRadius: [4, 4, 0, 0] },
            },
            {
                name: t('frpt_collected'),
                type: 'line',
                data: rows.map((row) => Number(row.paid_amount || 0)),
                smooth: true,
                symbolSize: 6,
                lineStyle: { width: 2, color: '#16a34a' },
                itemStyle: { color: '#16a34a' },
            },
        ],
    }, true);
};

watch([trend, locale], renderTrend);
watch(trendChartRef, (element) => { if (element) renderTrend(); });

/* ------------------------------------------------------------------ *
 * Orchestration
 * ------------------------------------------------------------------ */
const writeQuery = () => {
    const query = filtersToQuery();
    if (groupBy.value !== suggestedGroup()) query.group = groupBy.value;
    router.replace({ query });
};

let filterTimer = null;
let lastPeriodKey = `${filters.date_filter_type}|${filters.start_date}|${filters.end_date}`;
watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            // A new period brings its natural grouping with it.
            const periodKey = `${filters.date_filter_type}|${filters.start_date}|${filters.end_date}`;
            let regrouped = false;
            if (periodKey !== lastPeriodKey) {
                lastPeriodKey = periodKey;
                regrouped = groupBy.value !== suggestedGroup();
                groupBy.value = suggestedGroup();
            }
            writeQuery();
            if (awaitingRange.value) return;
            loadDims();
            profitSection.invalidate();
            // A new grouping fetches the trend through its own watcher below.
            if (!regrouped) loadTrend();
        }, 300);
    },
);

// A grouping change re-asks for the chart and nothing else.
watch(groupBy, () => {
    writeQuery();
    if (!awaitingRange.value) loadTrend();
});

/* ------------------------------------------------------------------ *
 * Export
 * ------------------------------------------------------------------ */
const exporting = ref(false);
const exportReport = async () => {
    exporting.value = true;
    try {
        const response = await api.get('/admin/reports/invoices/export', { params: params(), responseType: 'blob' });
        // A BOM so Excel opens the Arabic names as UTF-8.
        const url = window.URL.createObjectURL(new Blob(['﻿', response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `financial-report-${new Date().toLocaleDateString('en-CA')}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        ElMessage.error(t('failed_to_export_report'));
    } finally {
        exporting.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Formatting
 * ------------------------------------------------------------------ */
// Report figures are quoted whole; cents on a year's revenue are noise.
const formatCurrency = (value) => formatMoney(Number(value) || 0, { decimals: 0 });
const formatNumber = (value) => formatCount(Number(value) || 0);
const formatPercent = (value) => `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 1 }).format(Number(value) || 0)}%`;
const compactNumber = (value) => new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const statusText = (status) => t(`sales_status_${status}`);

onMounted(() => {
    loadWarehouses();
    fetchCustomers().then(() => rememberCustomer(filters.customer_id));
    loadDims();
    loadTrend();
});

onBeforeUnmount(() => {
    clearTimeout(filterTimer);
    clearTimeout(customerSearchTimer);
    disposeChart();
});
</script>

<style scoped>
.reports-financial {
    padding: 0;
}

.secondary-action {
    border: 1px solid #dfe7f1;
    color: #334155;
    background: #fff;
}

.filter-field--range {
    grid-column: span 2;
}

.option-hint {
    margin-inline-start: 0.5rem;
    font-size: 0.78rem;
    color: #94a3b8;
}

.label-hint {
    color: #94a3b8;
    font-size: 0.9rem;
    cursor: help;
}

.updating-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.82rem;
    color: #2563eb;
}

.detail-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
}

.detail-link:hover {
    text-decoration: underline;
}

/* The arrow points along the reading direction. */
[dir='rtl'] .detail-link .el-icon {
    transform: scaleX(-1);
}

/* Last figures, kept while new ones load: still readable, visibly not current. */
.stat-card,
.chart-wrap,
.profit-grid,
.section-card :deep(.el-table) {
    transition: opacity 0.2s ease;
}

.is-refreshing {
    opacity: 0.55;
    pointer-events: none;
}

/* ── Headline figures ── */
.stat-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
}

.stat-card-invoiced { background: linear-gradient(135deg, rgba(59,130,246,0.06), rgba(96,165,250,0.14)); }
.stat-card-collected { background: linear-gradient(135deg, rgba(16,185,129,0.06), rgba(52,211,153,0.14)); }
.stat-card-outstanding { background: linear-gradient(135deg, rgba(245,158,11,0.06), rgba(253,224,71,0.16)); }
.stat-card-average { background: linear-gradient(135deg, rgba(168,85,247,0.06), rgba(216,180,254,0.16)); }

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    font-size: 1.4rem;
}

.stat-info {
    min-width: 0;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.4rem;
    color: #1f2d3d;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-info p {
    margin: 0.2rem 0 0;
    color: #64748b;
    font-size: 0.86rem;
}

.stat-info small {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.5;
}

.stat-info small.sub-warn {
    color: #b45309;
}

.empty-report {
    margin-top: 1.5rem;
    padding: 2.5rem 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

/* ── Cards ── */
.panel-card,
.section-card {
    border-radius: 1rem;
}

.charts-section {
    margin: 1.25rem 0;
    row-gap: 1.25rem;
}

.charts-section .panel-card {
    height: 100%;
}

.section-card {
    margin-bottom: 1.25rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    width: 100%;
    font-weight: 600;
}

.chart-wrap {
    position: relative;
}

.chart-box {
    width: 100%;
    height: 330px;
}

.chart-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    color: #94a3b8;
}

/* ── Collection ── */
.collect-rate {
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}

.collect-bar {
    height: 12px;
    border-radius: 999px;
    background: #fde68a;
    overflow: hidden;
}

.collect-bar .paid {
    display: block;
    height: 100%;
    background: #16a34a;
    transition: width 0.3s ease;
}

.collect-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.25rem 1rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: #475569;
}

.collect-legend .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    margin-inline-end: 0.35rem;
    border-radius: 50%;
}

.dot.paid { background: #16a34a; }
.dot.due { background: #f59e0b; }

.list-title {
    margin: 1.25rem 0 0.5rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
}

.status-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.status-row {
    all: unset;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: 1fr auto auto;
    grid-template-areas: 'name count amount' 'bar bar bar';
    align-items: center;
    gap: 0.3rem 0.75rem;
    width: 100%;
    padding: 0.5rem 0.65rem;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.86rem;
}

.status-row:hover {
    background: #f8fafc;
}

.status-row:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.status-row.is-on {
    background: #eff6ff;
}

.status-name {
    grid-area: name;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.status-name .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--s);
}

.status-count {
    grid-area: count;
    color: #64748b;
    font-size: 0.78rem;
    font-variant-numeric: tabular-nums;
}

.status-amount {
    grid-area: amount;
    font-variant-numeric: tabular-nums;
}

.status-bar {
    grid-area: bar;
    height: 4px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.status-bar span {
    display: block;
    height: 100%;
    background: var(--s);
}

.s-pending { --s: #94a3b8; }
.s-confirmed { --s: #d97706; }
.s-processing { --s: #2563eb; }
.s-shipped { --s: #0891b2; }
.s-delivered { --s: #16a34a; }
.s-cancelled { --s: #dc2626; }

.empty-line {
    margin: 1rem 0 0;
    text-align: center;
    font-size: 0.85rem;
}

/* ── Profitability ── */
.profit-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.profit-metric {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    background: #f8fafc;
}

.profit-metric span {
    font-size: 0.8rem;
    color: #64748b;
}

.profit-metric strong {
    font-size: 1.15rem;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.margin-bar {
    height: 4px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.margin-bar span {
    display: block;
    height: 100%;
    background: #16a34a;
}

/* ── Tables ── */
.num {
    font-variant-numeric: tabular-nums;
}

.muted {
    color: #94a3b8;
}

.tone-good { color: #16a34a !important; }
.tone-warn { color: #b45309 !important; }
.tone-bad { color: #dc2626 !important; }

.section-card :deep(.el-table__row) {
    cursor: pointer;
}

.section-card :deep(.row-inert) {
    cursor: default;
    color: #94a3b8;
}

.section-card :deep(.is-active-row) td {
    background: #dbeafe !important;
    font-weight: 700;
}

@media (max-width: 992px) {
    .profit-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .filter-field--range {
        grid-column: auto;
    }

    .chart-box {
        height: 270px;
    }
}
</style>
