<template>
    <div class="product-kpi-page">
        <AdminPageHeader
            badge="KPI"
            :title="$t('product_kpi_dashboard')"
            :subtitle="$t('product_kpi_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :disabled="!activeFilterCount" class="secondary-action" @click="resetFilters">
                    {{ $t('reset') }}
                </el-button>
                <el-button type="primary" :icon="Download" :disabled="!tableRows.length" @click="exportCsv">
                    {{ $t('export') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Filters apply as they change and live in the address bar; there
             used to be an Apply button, and numbers still showing the last
             filters under freshly changed ones read as an answer to the new
             question. -->
        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('pkpi_source') }}</label>
                <el-segmented v-model="filters.source" :options="sourceOptions" block />
            </div>
            <div class="filter-field">
                <label>{{ $t('product') }}</label>
                <el-select
                    v-model="filters.product_id"
                    :placeholder="$t('pkpi_all_products')"
                    clearable
                    filterable
                    remote
                    remote-show-suffix
                    :remote-method="searchProducts"
                    :loading="productSearching"
                >
                    <el-option v-for="product in products" :key="product.id" :label="productLabel(product)" :value="product.id">
                        <span>{{ productLabel(product) }}</span>
                        <span v-if="product.sku" class="option-hint" dir="ltr">{{ product.sku }}</span>
                    </el-option>
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

            <template #advanced>
                <div class="filter-field">
                    <label>{{ $t('employee') }}</label>
                    <el-select v-model="filters.employee_id" :placeholder="$t('all_employees')" clearable filterable>
                        <el-option v-for="employee in employees" :key="employee.id" :label="employee.name" :value="employee.id" />
                    </el-select>
                </div>
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
            </template>

            <template #actions>
                <span v-if="loading && ready" class="updating-label" aria-live="polite">
                    <el-icon class="is-loading"><Loading /></el-icon>{{ $t('sr_updating') }}
                </span>
            </template>
        </AdminFilterBar>

        <!-- The advanced filters fold away; what they are set to should not. -->
        <div v-if="hiddenFilterChips.length" class="scope-chips">
            <el-tag
                v-for="chip in hiddenFilterChips"
                :key="chip.key"
                closable
                effect="plain"
                round
                @close="filters[chip.key] = null"
            >
                <span class="chip-name">{{ chip.label }}:</span> {{ chip.value }}
            </el-tag>
        </div>

        <el-result v-if="loadError" icon="error" :title="$t('failed_to_load_report')">
            <template #extra>
                <el-button type="primary" :icon="Refresh" :loading="loading" @click="loadReport">{{ $t('cat_admin_retry') }}</el-button>
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
                    :class="[`stat-card-${stat.key}`, { 'is-refreshing': ready && loading }]"
                >
                    <el-skeleton v-if="!ready" :rows="2" animated />
                    <div v-else class="stat-content">
                        <div class="stat-icon"><component :is="stat.icon" /></div>
                        <div class="stat-info">
                            <h3 :class="stat.valueClass">{{ stat.value }}</h3>
                            <p>{{ stat.label }}</p>
                            <small v-if="stat.sub" :class="stat.subClass">{{ stat.sub }}</small>
                        </div>
                    </div>
                </el-card>
            </AdminStatGrid>

            <el-empty
                v-if="ready && !loading && !rows.length"
                class="empty-report"
                :description="$t('no_profit_data_in_range')"
                :image-size="100"
            >
                <el-button v-if="activeFilterCount" @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
            </el-empty>

            <template v-else>
                <!-- ── Best and worst ── -->
                <!-- Ranked per product across its warehouses: the endpoint
                     ranks product-and-warehouse rows, so the "least profitable
                     product" could be one that did well everywhere but one
                     branch. -->
                <div class="highlights" :class="{ 'is-refreshing': ready && loading }">
                    <template v-if="ready">
                        <button
                            v-for="card in highlights"
                            :key="card.key"
                            type="button"
                            class="highlight"
                            :class="`highlight-${card.key}`"
                            :disabled="!card.product"
                            @click="card.product && selectProduct(card.product)"
                        >
                            <span class="highlight-label">{{ card.label }}</span>
                            <strong class="highlight-name">{{ card.product?.product_name || '—' }}</strong>
                            <span v-if="card.product" class="highlight-figure" :class="profitTone(card.product)">
                                {{ formatCurrency(card.product.gross_profit) }}
                                <small v-if="!card.product.uncosted"> · {{ formatPercent(card.product.gross_margin) }}</small>
                            </span>
                        </button>
                    </template>
                    <el-skeleton v-else :rows="1" animated class="highlight-skeleton" />
                </div>

                <!-- ── Chart ── -->
                <CollapsibleCard id="product-kpi-chart" :title="$t('profit_by_product_chart')" class="section-card">
                    <template #extra>
                        <el-segmented v-model="chartMetric" :options="chartMetricOptions" size="small" />
                    </template>
                    <el-skeleton v-if="!ready" :rows="7" animated />
                    <div v-else class="chart-wrap" :class="{ 'is-refreshing': loading }">
                        <div ref="chartRef" class="chart-box" :style="{ height: `${chartHeight}px` }"></div>
                    </div>
                </CollapsibleCard>

                <!-- ── Detail ── -->
                <CollapsibleCard
                    id="product-kpi-table"
                    :title="$t('product_profitability_details')"
                    :count="tableRows.length || null"
                    class="section-card"
                >
                    <div class="table-toolbar">
                        <el-input
                            v-model="search"
                            :prefix-icon="Search"
                            clearable
                            :placeholder="$t('pkpi_search_table')"
                            class="table-search"
                        />
                        <el-segmented v-model="view" :options="viewOptions" size="small" />
                        <el-checkbox v-model="lossesOnly" :disabled="!lossCount">
                            {{ $t('pkpi_losses_only', { count: formatNumber(lossCount) }) }}
                        </el-checkbox>
                    </div>

                    <el-skeleton v-if="!ready" :rows="8" animated />
                    <template v-else>
                        <!-- Sorted and paged here: the endpoint returns every row
                             at once. A row narrows the report to its product. -->
                        <el-table
                            :data="pagedRows"
                            stripe
                            style="width: 100%"
                            :class="{ 'is-refreshing': loading }"
                            :default-sort="{ prop: sort.prop, order: sort.order }"
                            :row-class-name="rowClass"
                            @sort-change="onSort"
                            @row-click="selectProduct"
                        >
                            <el-table-column prop="product_name" :label="$t('product')" min-width="200" sortable="custom" show-overflow-tooltip />
                            <el-table-column v-if="view === 'warehouse'" prop="warehouse_name" :label="$t('warehouse')" min-width="140" show-overflow-tooltip />
                            <el-table-column v-else :label="$t('warehouses')" width="110" align="center">
                                <template #default="{ row }">
                                    <el-tooltip :content="row.warehouses.join('، ')" placement="top" :disabled="row.warehouses.length < 2">
                                        <span class="muted">{{ formatNumber(row.warehouses.length) }}</span>
                                    </el-tooltip>
                                </template>
                            </el-table-column>
                            <el-table-column prop="quantity" :label="$t('quantity')" width="110" align="right" sortable="custom">
                                <template #default="{ row }"><span class="num">{{ formatNumber(row.quantity) }}</span></template>
                            </el-table-column>
                            <el-table-column prop="total_revenue" :label="$t('revenue')" min-width="120" align="right" sortable="custom">
                                <template #default="{ row }"><span class="num">{{ formatCurrency(row.total_revenue) }}</span></template>
                            </el-table-column>
                            <el-table-column prop="total_cost" :label="$t('cost')" min-width="120" align="right" sortable="custom">
                                <template #default="{ row }">
                                    <el-tooltip v-if="row.uncosted" :content="$t('pkpi_no_cost_hint')" placement="top">
                                        <span class="num uncosted">{{ $t('pkpi_no_cost') }}</span>
                                    </el-tooltip>
                                    <span v-else class="num">{{ formatCurrency(row.total_cost) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="gross_profit" :label="$t('profit')" min-width="120" align="right" sortable="custom">
                                <template #default="{ row }">
                                    <strong class="num" :class="profitTone(row)">{{ formatCurrency(row.gross_profit) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column prop="gross_margin" :label="$t('margin')" width="150" sortable="custom">
                                <template #default="{ row }">
                                    <span v-if="row.uncosted" class="muted">—</span>
                                    <div v-else class="margin-cell" :class="profitTone(row)">
                                        <span class="num">{{ formatPercent(row.gross_margin) }}</span>
                                        <div class="margin-bar"><span :style="{ width: `${Math.min(100, Math.abs(Number(row.gross_margin) || 0))}%` }" /></div>
                                    </div>
                                </template>
                            </el-table-column>
                            <template #empty>
                                <span class="muted">{{ $t('no_data_for_current_filters') }}</span>
                            </template>
                        </el-table>

                        <div v-if="tableRows.length > 10" class="pagination-row">
                            <el-pagination
                                v-model:current-page="page"
                                v-model:page-size="pageSize"
                                :page-sizes="[10, 20, 50, 100]"
                                :total="tableRows.length"
                                layout="total, sizes, prev, pager, next"
                                background
                            />
                        </div>
                    </template>
                </CollapsibleCard>
            </template>
        </template>
    </div>
</template>

<script setup>
import { formatMoney, formatNumber as formatCount, numberLocale } from '@/utils/currency';
import { matchesSearch } from '@/utils/search';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Refresh, Search, Coin, PriceTag, TrendCharts, PieChart, Download, Loading } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const PERIOD_PRESETS = ['all', 'today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];
const SOURCES = ['invoices', 'orders'];

/* ------------------------------------------------------------------ *
 * Filters, kept in the URL
 *
 * Presets travel as their name and the server works out the dates. The page
 * used to compute them itself and send them through toISOString(), which is
 * UTC — so in Damascus "today" arrived as yesterday — and began its week on
 * Sunday while the server begins it on Saturday.
 * ------------------------------------------------------------------ */
const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
const toId = (value) => (/^\d+$/.test(String(value || '')) ? Number(value) : null);

const filtersFromQuery = (query) => {
    const period = PERIOD_PRESETS.includes(query.period) ? query.period : 'all';
    return {
        source: SOURCES.includes(query.source) ? query.source : 'invoices',
        product_id: toId(query.product),
        employee_id: toId(query.employee),
        customer_id: toId(query.customer),
        warehouse_id: toId(query.warehouse),
        date_filter_type: period,
        start_date: period === 'custom' && isIsoDate(query.from) ? query.from : null,
        end_date: period === 'custom' && isIsoDate(query.to) ? query.to : null,
    };
};

const filters = reactive(filtersFromQuery(route.query));

const filtersToQuery = () => {
    const query = {};
    if (filters.source !== 'invoices') query.source = filters.source;
    if (filters.product_id) query.product = String(filters.product_id);
    if (filters.employee_id) query.employee = String(filters.employee_id);
    if (filters.customer_id) query.customer = String(filters.customer_id);
    if (filters.warehouse_id) query.warehouse = String(filters.warehouse_id);
    if (filters.date_filter_type !== 'all') query.period = filters.date_filter_type;
    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) query.from = filters.start_date;
        if (filters.end_date) query.to = filters.end_date;
    }
    return query;
};

/**
 * Invoices by default: what was actually billed. The pipeline of sales orders
 * — what this page reported alone, cancelled orders included — is one switch
 * away, for margins on work not yet invoiced.
 */
const sourceOptions = computed(() => [
    { label: t('pkpi_source_invoices'), value: 'invoices' },
    { label: t('pkpi_source_orders'), value: 'orders' },
]);

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
    filters.product_id,
    filters.employee_id,
    filters.customer_id,
    filters.warehouse_id,
    filters.date_filter_type !== 'all' ? filters.date_filter_type : null,
].filter(Boolean).length);

const params = () => {
    const out = { date_filter_type: filters.date_filter_type || 'all', exclude_cancelled: 1 };
    for (const key of ['product_id', 'employee_id', 'customer_id', 'warehouse_id']) {
        if (filters[key]) out[key] = filters[key];
    }
    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) out.start_date = filters.start_date;
        if (filters.end_date) out.end_date = filters.end_date;
    }
    return out;
};

const resetFilters = () => {
    Object.assign(filters, {
        product_id: null, employee_id: null, customer_id: null, warehouse_id: null,
        date_filter_type: 'all', start_date: null, end_date: null,
    });
};

/* ------------------------------------------------------------------ *
 * Reference data — employees and warehouses are short lists; products and
 * customers are searched on the server as you type, where the page used to
 * preload the first hundred of each and offer nothing past them.
 * ------------------------------------------------------------------ */
const employees = ref([]);
const warehouses = ref([]);
const products = ref([]);
const customers = ref([]);
const productSearching = ref(false);
const customerSearching = ref(false);

const productLabel = (product) => product?.name_ar || product?.name || product?.name_en || '—';

const loadEmployees = async () => {
    try {
        const response = await api.get('/admin/employees');
        employees.value = response.data?.data?.employees || [];
    } catch {
        employees.value = [];
    }
};

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        const data = response.data;
        warehouses.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data?.data?.data) ? data.data.data : []);
    } catch {
        warehouses.value = [];
    }
};

/**
 * A server-side search for a select: debounced, latest-only, and keeping the
 * selected option among the results so the select never falls back to an id.
 */
const remoteSearch = ({ list, searching, selectedId, fetch }) => {
    let token = 0;
    let timer = null;
    const run = async (term = '') => {
        const mine = ++token;
        searching.value = true;
        try {
            const found = await fetch(term);
            if (mine !== token) return;
            const selected = list.value.find((row) => Number(row.id) === Number(selectedId()));
            list.value = selected && !found.some((row) => row.id === selected.id) ? [selected, ...found] : found;
        } catch {
            if (mine === token) list.value = [];
        } finally {
            if (mine === token) searching.value = false;
        }
    };
    return {
        run,
        search: (query) => {
            clearTimeout(timer);
            timer = setTimeout(() => run(query?.trim() || ''), 250);
        },
        cancel: () => clearTimeout(timer),
    };
};

const productSearch = remoteSearch({
    list: products,
    searching: productSearching,
    selectedId: () => filters.product_id,
    fetch: async (search) => {
        const response = await api.get('/products', { params: { per_page: 30, ...(search ? { search } : {}) } });
        const data = response.data?.data;
        return Array.isArray(data) ? data : (Array.isArray(data?.products) ? data.products : []);
    },
});

const customerSearch = remoteSearch({
    list: customers,
    searching: customerSearching,
    selectedId: () => filters.customer_id,
    fetch: async (search) => {
        const response = await api.get('/pos/customers', { params: { per_page: 30, ...(search ? { search } : {}) } });
        const rows = response.data?.data?.customers;
        return Array.isArray(rows) ? rows.map((item) => item.data || item) : [];
    },
});

const searchProducts = productSearch.search;
const searchCustomers = customerSearch.search;

/** A product or customer chosen before its option was loaded needs a name. */
const remember = async (list, id, name, url, pick) => {
    if (!id || list.value.some((row) => Number(row.id) === Number(id))) return;
    if (name) {
        list.value = [{ id: Number(id), name, name_ar: name }, ...list.value];
        return;
    }
    try {
        const record = pick((await api.get(url)).data);
        if (record?.id) list.value = [record, ...list.value];
    } catch {
        // The select shows the bare id; the report is unaffected.
    }
};

const rememberProduct = (id, name = null) => remember(products, id, name, `/products/${id}`, (data) => data?.data?.product || data?.data);
const rememberCustomer = (id) => remember(customers, id, null, `/pos/customers/${id}`, (data) => data?.data?.data || data?.data);

const hiddenFilterChips = computed(() => {
    const nameOf = (list, id) => list.value.find((row) => Number(row.id) === Number(id))?.name || `#${id}`;
    const chips = [];
    if (filters.employee_id) chips.push({ key: 'employee_id', label: t('employee'), value: nameOf(employees, filters.employee_id) });
    if (filters.customer_id) chips.push({ key: 'customer_id', label: t('customer'), value: nameOf(customers, filters.customer_id) });
    if (filters.warehouse_id) chips.push({ key: 'warehouse_id', label: t('warehouse'), value: nameOf(warehouses, filters.warehouse_id) });
    return chips;
});

/* ------------------------------------------------------------------ *
 * Report
 * ------------------------------------------------------------------ */
const ready = ref(false);
const loading = ref(false);
const loadError = ref(false);
const rows = ref([]);
const summary = ref({});

let reportToken = 0;
const loadReport = async () => {
    const token = ++reportToken;
    loading.value = true;
    try {
        const endpoint = filters.source === 'orders'
            ? '/admin/reports/sales/product-profitability'
            : '/admin/reports/invoices/product-profitability';
        const response = await api.get(endpoint, { params: params() });
        if (token !== reportToken) return;
        rows.value = response.data?.data?.product_summary || [];
        summary.value = response.data?.data?.summary || {};
        loadError.value = false;
    } catch {
        if (token === reportToken) loadError.value = true;
    } finally {
        if (token === reportToken) {
            loading.value = false;
            ready.value = true;
        }
    }
};

/**
 * A line sold with no cost on file costs nothing in the report, so its margin
 * reads 100% — the best on the page and the least true. Marked, not trusted.
 */
const withFlags = (row) => ({
    ...row,
    uncosted: Number(row.total_cost) <= 0 && Number(row.total_revenue) > 0,
});

/** product × warehouse rows, as the endpoint returns them. */
const warehouseRows = computed(() => rows.value.map((row) => withFlags({ ...row, key: `${row.product_id}-${row.warehouse_id}` })));

/** One row per product, summed across its warehouses. */
const productRows = computed(() => {
    const byProduct = new Map();
    for (const row of rows.value) {
        const entry = byProduct.get(row.product_id) || {
            key: String(row.product_id),
            product_id: row.product_id,
            product_name: row.product_name,
            warehouses: [],
            quantity: 0,
            total_revenue: 0,
            total_cost: 0,
        };
        entry.quantity += Number(row.quantity) || 0;
        entry.total_revenue += Number(row.total_revenue) || 0;
        entry.total_cost += Number(row.total_cost) || 0;
        if (row.warehouse_name && !entry.warehouses.includes(row.warehouse_name)) entry.warehouses.push(row.warehouse_name);
        byProduct.set(row.product_id, entry);
    }
    return [...byProduct.values()].map((entry) => {
        const profit = entry.total_revenue - entry.total_cost;
        return withFlags({
            ...entry,
            gross_profit: profit,
            gross_margin: entry.total_revenue > 0 ? (profit / entry.total_revenue) * 100 : 0,
        });
    });
});

const uncostedCount = computed(() => productRows.value.filter((row) => row.uncosted).length);
const lossCount = computed(() => productRows.value.filter((row) => row.gross_profit < 0).length);

const stats = computed(() => {
    const s = summary.value || {};
    const margin = Number(s.gross_margin) || 0;
    return [
        {
            key: 'revenue',
            icon: Coin,
            label: t('total_revenue'),
            value: formatCurrency(s.total_revenue),
            sub: t('pkpi_products_sold', { count: formatNumber(productRows.value.length) }),
        },
        {
            key: 'cost',
            icon: PriceTag,
            label: t('total_cost'),
            value: formatCurrency(s.total_cost),
            sub: uncostedCount.value ? t('pkpi_uncosted_n', { count: formatNumber(uncostedCount.value) }) : '',
            subClass: 'sub-warn',
        },
        {
            key: 'profit',
            icon: TrendCharts,
            label: t('total_profit'),
            value: formatCurrency(s.gross_profit),
            valueClass: Number(s.gross_profit) < 0 ? 'tone-bad' : '',
            sub: lossCount.value ? t('pkpi_loss_makers_n', { count: formatNumber(lossCount.value) }) : '',
            subClass: 'sub-bad',
        },
        {
            key: 'margin',
            icon: PieChart,
            label: t('profit_margin'),
            value: formatPercent(margin),
            valueClass: margin < 0 ? 'tone-bad' : '',
            // An uncosted product lifts the whole page's margin with it.
            sub: uncostedCount.value ? t('pkpi_margin_overstated') : '',
            subClass: 'sub-warn',
        },
    ];
});

const highlights = computed(() => {
    const costed = productRows.value.filter((row) => !row.uncosted);
    const byProfit = [...costed].sort((a, b) => b.gross_profit - a.gross_profit);
    const byMargin = [...costed].filter((row) => row.total_revenue > 0).sort((a, b) => b.gross_margin - a.gross_margin);
    const byRevenue = [...productRows.value].sort((a, b) => b.total_revenue - a.total_revenue);
    return [
        { key: 'best', label: t('most_profitable_product'), product: byProfit[0] || null },
        { key: 'worst', label: t('least_profitable_product'), product: byProfit.length > 1 ? byProfit[byProfit.length - 1] : null },
        { key: 'margin', label: t('pkpi_best_margin'), product: byMargin[0] || null },
        { key: 'revenue', label: t('pkpi_top_seller'), product: byRevenue[0] || null },
    ];
});

const selectProduct = (row) => {
    if (!row?.product_id) return;
    const id = Number(row.product_id);
    if (Number(filters.product_id) === id) {
        filters.product_id = null;
        return;
    }
    rememberProduct(id, row.product_name);
    filters.product_id = id;
};

/* ------------------------------------------------------------------ *
 * Table — searched, sorted and paged client-side
 * ------------------------------------------------------------------ */
const view = ref('product');
const viewOptions = computed(() => [
    { label: t('pkpi_by_product'), value: 'product' },
    { label: t('pkpi_by_warehouse'), value: 'warehouse' },
]);
const search = ref('');
const lossesOnly = ref(false);
const sort = reactive({ prop: 'gross_profit', order: 'descending' });
const page = ref(1);
const pageSize = ref(20);

const tableRows = computed(() => {
    const term = search.value.trim();
    let list = view.value === 'warehouse' ? warehouseRows.value : productRows.value;
    if (term) {
        list = list.filter((row) => matchesSearch([row.product_name, row.warehouse_name], term));
    }
    if (lossesOnly.value) list = list.filter((row) => row.gross_profit < 0);
    const { prop, order } = sort;
    if (prop && order) {
        const direction = order === 'ascending' ? 1 : -1;
        list = [...list].sort((a, b) => (prop === 'product_name'
            ? String(a.product_name).localeCompare(String(b.product_name), locale.value) * direction
            : ((Number(a[prop]) || 0) - (Number(b[prop]) || 0)) * direction));
    }
    return list;
});

const pagedRows = computed(() => tableRows.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value));

const onSort = ({ prop, order }) => {
    sort.prop = prop;
    sort.order = order;
    page.value = 1;
};

watch([search, view, lossesOnly, rows], () => { page.value = 1; });
watch(lossCount, (count) => { if (!count) lossesOnly.value = false; });

const rowClass = ({ row }) => [
    'product-row',
    row.gross_profit < 0 ? 'row-loss' : '',
    Number(filters.product_id) === Number(row.product_id) ? 'is-active-row' : '',
].join(' ');

/* ------------------------------------------------------------------ *
 * Chart — the ten products at the top of the chosen measure, drawn as
 * ranked horizontal bars (product names are long, and read level there).
 * It used to label each bar from a field the rows do not carry, so every
 * bar was called "not specified".
 * ------------------------------------------------------------------ */
const chartMetric = ref('gross_profit');
const chartMetricOptions = computed(() => [
    { label: t('profit'), value: 'gross_profit' },
    { label: t('revenue'), value: 'total_revenue' },
]);

const chartRows = computed(() => [...productRows.value]
    .sort((a, b) => b[chartMetric.value] - a[chartMetric.value])
    .slice(0, 10)
    .reverse());

const chartHeight = computed(() => Math.max(220, chartRows.value.length * 34 + 40));

const chartRef = ref(null);
let chart = null;
let chartObserver = null;
let echartsLoader = null;
const loadEcharts = () => {
    echartsLoader ??= import('@/utils/echartsLite').then((module) => module.default);
    return echartsLoader;
};

const disposeChart = () => {
    chartObserver?.disconnect();
    chartObserver = null;
    chart?.dispose();
    chart = null;
};

const renderChart = async () => {
    if (!chartRows.value.length) {
        chart?.clear();
        return;
    }
    const echarts = await loadEcharts();
    await nextTick();
    const element = chartRef.value;
    if (!element) return;
    if (!chart || chart.getDom() !== element) {
        disposeChart();
        chart = echarts.init(element);
        if (typeof ResizeObserver !== 'undefined') {
            chartObserver = new ResizeObserver(() => chart?.resize());
            chartObserver.observe(element);
        }
    }

    const list = chartRows.value;
    const truncate = (name) => (name.length > 30 ? `${name.slice(0, 29)}…` : name);
    chart.setOption({
        grid: { left: 8, right: 28, top: 8, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (items) => {
                const row = list[items[0]?.dataIndex];
                if (!row) return '';
                return `<strong>${row.product_name}</strong><br/>`
                    + `${t('revenue')}: ${formatCurrency(row.total_revenue)}<br/>`
                    + `${t('profit')}: ${formatCurrency(row.gross_profit)}`
                    + (row.uncosted ? `<br/><em>${t('pkpi_no_cost_hint')}</em>` : `<br/>${t('margin')}: ${formatPercent(row.gross_margin)}`);
            },
        },
        xAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#f1f5f9' } },
            axisLabel: { color: '#94a3b8', formatter: (value) => compactNumber(value) },
        },
        yAxis: {
            type: 'category',
            data: list.map((row) => truncate(row.product_name || '—')),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisLabel: { color: '#334155' },
        },
        series: [{
            type: 'bar',
            barMaxWidth: 20,
            data: list.map((row) => ({
                value: Number(row[chartMetric.value]) || 0,
                // Losses in red; profit on an unknown cost in grey, since it is not one.
                itemStyle: {
                    color: row[chartMetric.value] < 0 ? '#dc2626' : (row.uncosted && chartMetric.value === 'gross_profit' ? '#94a3b8' : '#16a34a'),
                    borderRadius: [0, 4, 4, 0],
                },
            })),
        }],
    }, true);
    chart.resize();
};

watch([chartRows, locale], renderChart);
watch(chartRef, (element) => { if (element) renderChart(); });

/* ------------------------------------------------------------------ *
 * Orchestration
 * ------------------------------------------------------------------ */
let filterTimer = null;
watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            router.replace({ query: filtersToQuery() });
            if (awaitingRange.value) return;
            loadReport();
        }, 300);
    },
);

/** The table as it stands — view, search and sort — as a CSV file. */
const exportCsv = () => {
    const warehouseView = view.value === 'warehouse';
    const header = [t('product'), warehouseView ? t('warehouse') : t('warehouses'), t('quantity'), t('revenue'), t('cost'), t('profit'), t('margin')];
    const lines = tableRows.value.map((row) => [
        row.product_name,
        warehouseView ? row.warehouse_name : row.warehouses.join(' / '),
        Number(row.quantity) || 0,
        (Number(row.total_revenue) || 0).toFixed(2),
        row.uncosted ? '' : (Number(row.total_cost) || 0).toFixed(2),
        (Number(row.gross_profit) || 0).toFixed(2),
        row.uncosted ? '' : (Number(row.gross_margin) || 0).toFixed(2),
    ]);
    const escape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const csv = [header, ...lines].map((line) => line.map(escape).join(',')).join('\r\n');
    // A BOM so Excel opens the Arabic names as UTF-8.
    const url = window.URL.createObjectURL(new Blob(['﻿', csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `product-kpi-${filters.source}-${new Date().toLocaleDateString('en-CA')}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

/* ------------------------------------------------------------------ *
 * Formatting
 * ------------------------------------------------------------------ */
const formatCurrency = (value) => formatMoney(Number(value) || 0);
const formatNumber = (value) => formatCount(Number(value) || 0);
const formatPercent = (value) => `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 1 }).format(Number(value) || 0)}%`;
const compactNumber = (value) => new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const profitTone = (row) => (Number(row.gross_profit) < 0 ? 'tone-bad' : (row.uncosted ? 'tone-muted' : 'tone-good'));

onMounted(() => {
    loadEmployees();
    loadWarehouses();
    productSearch.run().then(() => rememberProduct(filters.product_id));
    customerSearch.run().then(() => rememberCustomer(filters.customer_id));
    loadReport();
});

onBeforeUnmount(() => {
    clearTimeout(filterTimer);
    productSearch.cancel();
    customerSearch.cancel();
    disposeChart();
});
</script>

<style scoped>
.product-kpi-page {
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

.updating-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.82rem;
    color: #2563eb;
}

.scope-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: -0.5rem 0 1rem;
}

.chip-name {
    color: #64748b;
    font-weight: 600;
}

/* Last figures, kept while new ones load: still readable, visibly not current. */
.stat-card,
.highlights,
.chart-wrap,
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

.stat-card-revenue { background: linear-gradient(135deg, rgba(16,185,129,0.06), rgba(52,211,153,0.14)); }
.stat-card-cost { background: linear-gradient(135deg, rgba(59,130,246,0.06), rgba(96,165,250,0.14)); }
.stat-card-profit { background: linear-gradient(135deg, rgba(168,85,247,0.06), rgba(216,180,254,0.16)); }
.stat-card-margin { background: linear-gradient(135deg, rgba(245,158,11,0.06), rgba(251,191,36,0.16)); }

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
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
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

.stat-info small.sub-warn { color: #b45309; }
.stat-info small.sub-bad { color: #dc2626; }

.empty-report {
    margin-top: 1.5rem;
    padding: 2.5rem 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

/* ── Best and worst ── */
.highlights {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1.25rem 0;
}

.highlight-skeleton {
    grid-column: 1 / -1;
}

.highlight {
    all: unset;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    padding: 0.85rem 1rem;
    border: 1px solid #e2e8f0;
    border-inline-start: 4px solid var(--accent);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
}

.highlight:hover:not(:disabled) {
    background: #f8fafc;
}

.highlight:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.highlight:disabled {
    cursor: default;
}

.highlight-best { --accent: #16a34a; }
.highlight-worst { --accent: #dc2626; }
.highlight-margin { --accent: #7c3aed; }
.highlight-revenue { --accent: #2563eb; }

.highlight-label {
    font-size: 0.78rem;
    color: #64748b;
}

.highlight-name {
    font-size: 0.98rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.highlight-figure {
    font-size: 0.85rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.highlight-figure small {
    font-weight: 400;
    color: #64748b;
}

/* ── Cards ── */
.section-card {
    margin-bottom: 1.25rem;
    border-radius: 1rem;
}

.chart-wrap {
    position: relative;
}

.chart-box {
    width: 100%;
}

.table-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1rem;
    margin-bottom: 0.9rem;
}

.table-search {
    max-width: 280px;
}

/* ── Table ── */
.num {
    font-variant-numeric: tabular-nums;
}

.muted {
    color: #94a3b8;
}

.uncosted {
    color: #b45309;
    border-bottom: 1px dashed currentColor;
    cursor: help;
    font-size: 0.82rem;
}

.tone-good { color: #16a34a; }
.tone-bad { color: #dc2626 !important; }
.tone-muted { color: #64748b; }

.margin-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-weight: 600;
}

.margin-bar {
    height: 4px;
    border-radius: 2px;
    background: #f1f5f9;
    overflow: hidden;
}

.margin-bar span {
    display: block;
    height: 100%;
    background: currentColor;
}

.section-card :deep(.product-row) {
    cursor: pointer;
}

.section-card :deep(.is-active-row) td {
    background: #dbeafe !important;
    font-weight: 700;
}

/* A product sold at a loss, marked down the leading edge. */
.section-card :deep(.row-loss) td:first-child {
    box-shadow: inset 3px 0 0 #dc2626;
}

[dir='rtl'] .section-card :deep(.row-loss) td:first-child {
    box-shadow: inset -3px 0 0 #dc2626;
}

.pagination-row {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .filter-field--range {
        grid-column: auto;
    }

    .table-search {
        max-width: none;
        flex-basis: 100%;
    }
}
</style>
