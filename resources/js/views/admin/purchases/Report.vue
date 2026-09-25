<template>
    <div class="reports-page purchases-report">
        <AdminPageHeader
            badge="PROCUREMENT"
            :title="$t('professional_purchases_report')"
            :subtitle="$t('purchases_report_subtitle')"
        >
            <template #actions>
                <el-button :icon="Download" :loading="exporting" @click="exportReport">
                    {{ $t('prpt_export_csv') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Filters: applied as they change, kept in the URL ── -->
        <div class="panel-card filters">
            <el-date-picker
                v-model="filters.range"
                type="daterange"
                class="filter-dates"
                value-format="YYYY-MM-DD"
                format="YYYY-MM-DD"
                unlink-panels
                :start-placeholder="$t('prpt_all_time')"
                :end-placeholder="$t('prpt_all_time')"
                :shortcuts="dateShortcuts"
                @change="applyFilters"
            />
            <el-select
                v-model="filters.supplier_id"
                class="filter-select"
                :placeholder="$t('all_suppliers')"
                filterable
                clearable
                @change="applyFilters"
            >
                <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
            </el-select>
            <el-select
                v-model="filters.status"
                class="filter-select"
                :placeholder="$t('prpt_all_but_cancelled')"
                clearable
                @change="applyFilters"
            >
                <el-option v-for="s in STATUSES" :key="s" :label="statusText(s)" :value="s" />
            </el-select>
            <span class="period-label">
                <el-icon><Calendar /></el-icon>{{ periodLabel }}
            </span>
            <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
            </el-button>
        </div>

        <el-result v-if="loadError" icon="error" :title="$t('failed_to_load_report')">
            <template #extra>
                <el-button type="primary" :icon="Refresh" @click="loadAll">{{ $t('cat_admin_retry') }}</el-button>
            </template>
        </el-result>

        <template v-else>
            <!-- ── KPIs ── -->
            <AdminStatGrid :min="200">
                <el-card v-for="card in kpis" :key="card.key" shadow="hover" class="stat-card">
                    <el-skeleton v-if="firstLoad" :rows="2" animated />
                    <div v-else class="stat-inner">
                        <div class="stat-icon" :class="card.tone"><el-icon><component :is="card.icon" /></el-icon></div>
                        <div class="stat-details">
                            <p class="stat-title">
                                {{ card.title }}
                                <el-tooltip v-if="card.hint" :content="card.hint" placement="top">
                                    <el-icon class="hint-icon"><InfoFilled /></el-icon>
                                </el-tooltip>
                            </p>
                            <h3 :class="card.valueClass">{{ card.value }}</h3>
                            <span v-if="card.sub" class="stat-sub">{{ card.sub }}</span>
                        </div>
                    </div>
                </el-card>
            </AdminStatGrid>

            <el-empty
                v-if="!firstLoad && !loading && !summary.total_orders"
                class="panel-card empty-report"
                :description="activeFilterCount ? $t('prpt_no_orders_filtered') : $t('prpt_no_orders')"
                :image-size="100"
            >
                <el-button v-if="activeFilterCount" @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
            </el-empty>

            <template v-else>
                <!-- ── Trend and where the money went ── -->
                <div class="charts-row">
                    <section class="panel-card trend-card">
                        <header class="card-head">
                            <h3>{{ $t('prpt_spend_over_time') }}</h3>
                            <el-segmented v-model="groupBy" :options="groupOptions" size="small" @change="onGroupChange" />
                        </header>
                        <div v-loading="trendLoading" class="chart-wrap">
                            <div ref="trendChartRef" class="chart" />
                            <p v-if="!trendLoading && !trend.length" class="chart-empty">{{ $t('no_data_for_current_filters') }}</p>
                        </div>
                    </section>

                    <section class="panel-card share-card">
                        <header class="card-head">
                            <h3>{{ $t('prpt_spend_by_supplier') }}</h3>
                        </header>
                        <el-skeleton v-if="firstLoad" :rows="5" animated />
                        <ul v-else class="share-list">
                            <li v-for="row in supplierShare" :key="row.key">
                                <button
                                    v-if="row.supplier_id"
                                    type="button"
                                    class="share-name link-button"
                                    :class="{ 'is-on': filters.supplier_id === row.supplier_id }"
                                    :title="$t('spay_filter_by_supplier')"
                                    @click="toggleSupplier(row.supplier_id)"
                                >
                                    {{ row.name }}
                                </button>
                                <span v-else class="share-name muted">{{ row.name }}</span>
                                <span class="share-amount">{{ formatCurrency(row.total_spend) }}</span>
                                <div class="share-bar"><span :style="{ width: `${Math.max(1.5, row.share)}%` }" /></div>
                                <span class="share-pct">{{ formatPercent(row.share, 0) }}</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- ── Detail ── -->
                <section class="panel-card detail-card">
                    <el-tabs v-model="tab" class="detail-tabs" @tab-change="writeQuery">
                        <!-- Orders -->
                        <el-tab-pane name="orders">
                            <template #label>
                                {{ $t('prpt_tab_orders') }} <span class="tab-count">{{ formatCount(pagination.total) }}</span>
                            </template>

                            <div v-if="statusSummary.length" class="status-strip">
                                <button
                                    v-for="row in statusSummary"
                                    :key="row.status"
                                    type="button"
                                    class="status-chip"
                                    :class="[`s-${row.status}`, { 'is-on': filters.status === row.status }]"
                                    @click="toggleStatus(row.status)"
                                >
                                    <span class="dot" />
                                    {{ statusText(row.status) }}
                                    <strong>{{ formatCount(row.total_orders) }}</strong>
                                    <span class="muted">· {{ formatCurrency(row.total_spend) }}</span>
                                </button>
                                <span v-if="!filters.status && summary.cancelled_orders" class="cancelled-note">
                                    {{ $t('prpt_cancelled_excluded', { count: summary.cancelled_orders }) }}
                                    <el-button link type="primary" size="small" @click="toggleStatus('cancelled')">{{ $t('view') }}</el-button>
                                </span>
                            </div>

                            <el-table
                                v-loading="ordersLoading"
                                :data="orders"
                                row-key="id"
                                style="width: 100%"
                                :row-class-name="orderRowClass"
                                :default-sort="ordersDefaultSort"
                                @sort-change="onSortChange"
                            >
                                <template #empty>
                                    <span class="muted">{{ $t('no_data_for_current_filters') }}</span>
                                </template>

                                <el-table-column :label="$t('order_number')" min-width="140">
                                    <template #default="{ row }">
                                        <div class="cell-stack">
                                            <router-link :to="orderLink(row)" class="mono order-link" dir="ltr">{{ row.order_number }}</router-link>
                                            <span class="cell-secondary">{{ formatDate(row.order_date) }}</span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('supplier')" min-width="160">
                                    <template #default="{ row }">{{ row.supplier?.name || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('status')" width="130">
                                    <template #default="{ row }">
                                        <span class="status-pill" :class="`s-${row.status}`">{{ statusText(row.status) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('total')" min-width="140" align="right">
                                    <template #default="{ row }">
                                        <div class="cell-stack num">
                                            <strong>{{ formatCurrency(row.total) }}</strong>
                                            <span v-if="Number(row.tax) || Number(row.discount)" class="cell-secondary">
                                                <template v-if="Number(row.tax)">{{ $t('pret_incl_tax', { amount: formatCurrency(row.tax) }) }}</template>
                                                <template v-if="Number(row.discount)"> · −{{ formatCurrency(row.discount) }}</template>
                                            </span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="landed_cost" min-width="140" align="right" sortable="custom">
                                    <template #header>
                                        <el-tooltip :content="$t('landed_cost_basis_hint')" placement="top">
                                            <span class="hinted-header">{{ $t('landed_cost') }} <el-icon><InfoFilled /></el-icon></span>
                                        </el-tooltip>
                                    </template>
                                    <template #default="{ row }">
                                        <!-- A line still on order carries the price it was placed
                                             at, which the figure alone would not admit to. -->
                                        <el-tooltip
                                            v-if="Number(row.pending_lines) > 0"
                                            :content="$t('pending_receipt_hint', { count: row.pending_lines, total: row.line_count })"
                                            placement="top"
                                        >
                                            <span class="num is-estimated">≈ {{ formatCurrency(row.landed_cost) }}</span>
                                        </el-tooltip>
                                        <span v-else class="num">{{ formatCurrency(row.landed_cost) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('cost_variance')" prop="cost_variance" min-width="150" align="right" sortable="custom">
                                    <template #default="{ row }">
                                        <span v-if="Math.abs(Number(row.cost_variance)) < 0.005" class="muted">—</span>
                                        <span v-else class="num" :class="Number(row.cost_variance) > 0 ? 'tone-bad' : 'tone-good'">
                                            <strong>{{ signedCurrency(row.cost_variance) }}</strong>
                                            <small v-if="Number(row.ordered_cost) > 0"> {{ signedPercent(row.cost_variance_percent) }}</small>
                                        </span>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div v-if="pagination.total > 0" class="pagination-row">
                                <span class="muted">
                                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: formatCount(pagination.total) }) }}
                                </span>
                                <el-pagination
                                    v-model:current-page="pagination.current_page"
                                    v-model:page-size="pagination.per_page"
                                    :page-sizes="[10, 20, 50, 100]"
                                    :total="pagination.total"
                                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                                    background
                                    @size-change="onPageChange(true)"
                                    @current-change="onPageChange(false)"
                                />
                            </div>
                        </el-tab-pane>

                        <!-- Suppliers -->
                        <el-tab-pane name="suppliers">
                            <template #label>
                                {{ $t('suppliers') }} <span class="tab-count">{{ formatCount(supplierRows.length) }}</span>
                            </template>
                            <el-table :data="supplierRows" style="width: 100%" :default-sort="{ prop: 'total_spend', order: 'descending' }">
                                <el-table-column type="index" width="50" align="center" />
                                <el-table-column :label="$t('supplier')" min-width="180" prop="supplier_name" sortable>
                                    <template #default="{ row }">
                                        <button type="button" class="link-button" @click="toggleSupplier(row.supplier_id)">{{ row.supplier_name }}</button>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('total_orders')" prop="total_orders" width="110" align="center" sortable />
                                <el-table-column :label="$t('total_spend')" prop="total_spend" min-width="170" align="right" sortable>
                                    <template #default="{ row }">
                                        <div class="cell-stack num">
                                            <strong>{{ formatCurrency(row.total_spend) }}</strong>
                                            <span class="cell-secondary">{{ formatPercent(row.share, 1) }}</span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('average_order_value')" prop="average_order_value" min-width="140" align="right" sortable>
                                    <template #default="{ row }"><span class="num">{{ formatCurrency(row.average_order_value) }}</span></template>
                                </el-table-column>
                                <el-table-column prop="planned_margin" min-width="130" align="right" sortable>
                                    <template #header>
                                        <el-tooltip :content="$t('prpt_planned_margin_hint')" placement="top">
                                            <span class="hinted-header">{{ $t('planned_margin') }} <el-icon><InfoFilled /></el-icon></span>
                                        </el-tooltip>
                                    </template>
                                    <template #default="{ row }">
                                        <span v-if="row.planned_margin === null" class="muted">—</span>
                                        <span v-else class="num" :class="marginTone(row.planned_margin)">{{ formatPercent(row.planned_margin, 1) }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>

                        <!-- Products -->
                        <el-tab-pane name="products">
                            <template #label>
                                {{ $t('prpt_tab_products') }} <span class="tab-count">{{ formatCount(products.length) }}</span>
                            </template>

                            <div class="products-toolbar">
                                <el-input
                                    v-model="productSearch"
                                    :prefix-icon="Search"
                                    clearable
                                    :placeholder="$t('prpt_search_products')"
                                    class="product-search"
                                    @input="productPage = 1"
                                />
                                <div v-if="productSpend.summary?.top_spend_product" class="callout">
                                    <span class="callout-label">{{ $t('top_spend_product') }}</span>
                                    <strong>{{ productSpend.summary.top_spend_product.product_name }}</strong>
                                    <span class="muted">{{ formatCurrency(productSpend.summary.top_spend_product.total_cost) }}</span>
                                </div>
                                <div v-if="productSpend.summary?.lowest_margin_product" class="callout is-warn">
                                    <span class="callout-label">{{ $t('lowest_margin_product') }}</span>
                                    <strong>{{ productSpend.summary.lowest_margin_product.product_name }}</strong>
                                    <span :class="marginTone(productSpend.summary.lowest_margin_product.planned_margin)">
                                        {{ formatPercent(productSpend.summary.lowest_margin_product.planned_margin, 1) }}
                                    </span>
                                </div>
                            </div>

                            <el-table :data="pagedProducts" style="width: 100%" @sort-change="onProductSort">
                                <template #empty>
                                    <span class="muted">{{ $t('no_data_for_current_filters') }}</span>
                                </template>
                                <el-table-column :label="$t('product')" prop="product_name" min-width="200" sortable="custom" />
                                <el-table-column :label="$t('quantity')" prop="quantity" width="100" align="center" sortable="custom">
                                    <template #default="{ row }">{{ formatCount(row.quantity) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('cost')" prop="total_cost" min-width="150" align="right" sortable="custom">
                                    <template #default="{ row }">
                                        <div class="cell-stack num">
                                            <strong>{{ formatCurrency(row.total_cost) }}</strong>
                                            <span class="cell-secondary">{{ formatPercent(row.share, 1) }}</span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('planned_revenue')" prop="total_planned_revenue" min-width="140" align="right" sortable="custom">
                                    <template #default="{ row }"><span class="num">{{ formatCurrency(row.total_planned_revenue) }}</span></template>
                                </el-table-column>
                                <el-table-column :label="$t('planned_profit')" prop="planned_profit" min-width="140" align="right" sortable="custom">
                                    <template #default="{ row }">
                                        <span class="num" :class="row.planned_profit >= 0 ? 'tone-good' : 'tone-bad'">{{ formatCurrency(row.planned_profit) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('margin')" prop="planned_margin" width="110" align="right" sortable="custom">
                                    <template #default="{ row }">
                                        <span class="num" :class="marginTone(row.planned_margin)">{{ formatPercent(row.planned_margin, 1) }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div v-if="filteredProducts.length > PRODUCT_PAGE" class="pagination-row">
                                <span class="muted">
                                    {{ $t('prod_admin_range', {
                                        from: (productPage - 1) * PRODUCT_PAGE + 1,
                                        to: Math.min(productPage * PRODUCT_PAGE, filteredProducts.length),
                                        total: formatCount(filteredProducts.length),
                                    }) }}
                                </span>
                                <el-pagination
                                    v-model:current-page="productPage"
                                    :page-size="PRODUCT_PAGE"
                                    :total="filteredProducts.length"
                                    layout="prev, pager, next"
                                    background
                                />
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </section>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
    Calendar, Coin, DataLine, Download, InfoFilled, PieChart, Refresh, RefreshLeft, Search, ShoppingCart, TrendCharts,
} from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { useSuppliersStore } from '@/stores/suppliers';
import { formatMoney, formatNumber as formatCount, numberLocale } from '@/utils/currency';
import { formatDate, localIsoDate } from '@/utils/sales';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const suppliersStore = useSuppliersStore();
const suppliers = computed(() => suppliersStore.suppliers || []);

const STATUSES = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];
const GROUPS = ['day', 'week', 'month', 'supplier'];
const PRODUCT_PAGE = 20;

// ── Filters, kept in the URL ──────────────────────────────────────────────
const filters = reactive({ range: null, supplier_id: null, status: '' });
const groupBy = ref('day');
const tab = ref('orders');
const ordersSort = ref(null);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const SORTS = ['landed_asc', 'landed_desc', 'variance_asc', 'variance_desc'];

const readQuery = () => {
    const q = route.query;
    filters.range = q.from && q.to ? [String(q.from), String(q.to)] : null;
    filters.supplier_id = Number(q.supplier_id) || null;
    filters.status = STATUSES.includes(q.status) ? q.status : '';
    groupBy.value = GROUPS.includes(q.group) ? q.group : suggestedGroup();
    tab.value = ['orders', 'suppliers', 'products'].includes(q.tab) ? q.tab : 'orders';
    ordersSort.value = SORTS.includes(q.sort) ? q.sort : null;
    pagination.current_page = Math.max(1, Number(q.page) || 1);
    pagination.per_page = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        supplier_id: filters.supplier_id || undefined,
        status: filters.status || undefined,
        group: groupBy.value !== suggestedGroup() ? groupBy.value : undefined,
        tab: tab.value !== 'orders' ? tab.value : undefined,
        sort: ordersSort.value || undefined,
        page: pagination.current_page > 1 ? pagination.current_page : undefined,
        per_page: pagination.per_page !== 20 ? pagination.per_page : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [filters.range?.length ? '1' : '', filters.supplier_id, filters.status].filter(Boolean).length);

/** Days for a short range, weeks for a quarter, months beyond. */
function suggestedGroup() {
    if (!filters.range?.length) return 'month';
    const days = (new Date(filters.range[1]) - new Date(filters.range[0])) / 86400000;
    if (days <= 45) return 'day';
    if (days <= 190) return 'week';
    return 'month';
}

const baseParams = () => {
    const params = {};
    if (filters.range?.length) {
        params.date_filter_type = 'custom';
        params.start_date = filters.range[0];
        params.end_date = filters.range[1];
    }
    if (filters.supplier_id) params.supplier_id = filters.supplier_id;
    if (filters.status) params.status = filters.status;
    return params;
};

const dateShortcuts = computed(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const d = (yy, mm, dd) => localIsoDate(new Date(yy, mm, dd));
    const today = localIsoDate(now);
    const quarterStart = Math.floor(m / 3) * 3;
    return [
        { text: t('today'), value: () => [today, today] },
        { text: t('pret_this_month'), value: () => [d(y, m, 1), today] },
        { text: t('pret_last_month'), value: () => [d(y, m - 1, 1), d(y, m, 0)] },
        { text: t('prpt_this_quarter'), value: () => [d(y, quarterStart, 1), today] },
        { text: t('pret_this_year'), value: () => [d(y, 0, 1), today] },
        { text: t('prpt_last_year'), value: () => [d(y - 1, 0, 1), d(y - 1, 11, 31)] },
        { text: t('prpt_last_12_months'), value: () => [d(y, m - 11, 1), today] },
    ];
});

const periodLabel = computed(() => (filters.range?.length
    ? `${formatDate(filters.range[0])} – ${formatDate(filters.range[1])}`
    : t('prpt_all_time')));

// ── Data ─────────────────────────────────────────────────────────────────
const firstLoad = ref(true);
const loading = ref(false);
const loadError = ref(false);
const ordersLoading = ref(false);
const trendLoading = ref(false);
const exporting = ref(false);

const orders = ref([]);
const summary = ref({});
const trend = ref([]);
const dimensions = ref({ supplier_summary: [], status_summary: [] });
const performance = ref({ summary: {}, supplier_summary: [] });
const productSpend = ref({ summary: {}, product_summary: [] });

// A slow earlier response must not land over a newer one.
let loadSeq = 0;
let ordersSeq = 0;
let trendSeq = 0;

const fetchOrders = async () => {
    const seq = ++ordersSeq;
    ordersLoading.value = true;
    try {
        const res = await api.get('/admin/reports/purchases', {
            params: {
                ...baseParams(),
                page: pagination.current_page,
                per_page: pagination.per_page,
                ...(ordersSort.value ? { sort: ordersSort.value } : {}),
            },
        });
        if (seq !== ordersSeq) return;
        const data = res.data?.data || {};
        orders.value = data.purchase_orders || [];
        summary.value = data.summary || {};
        pagination.total = data.pagination?.total || 0;
    } finally {
        if (seq === ordersSeq) ordersLoading.value = false;
    }
};

const fetchTrend = async () => {
    const seq = ++trendSeq;
    trendLoading.value = true;
    try {
        const res = await api.get('/admin/reports/purchases/summary', { params: { ...baseParams(), group_by: groupBy.value } });
        if (seq !== trendSeq) return;
        trend.value = res.data?.data?.summary || [];
        await nextTick();
        renderTrend();
    } catch {
        if (seq === trendSeq) trend.value = [];
    } finally {
        if (seq === trendSeq) trendLoading.value = false;
    }
};

/** Everything that depends on the filters, fetched side by side. */
const loadAll = async () => {
    const seq = ++loadSeq;
    loading.value = true;
    loadError.value = false;
    const params = baseParams();
    try {
        const [, dims, perf, prod] = await Promise.all([
            fetchOrders(),
            api.get('/admin/reports/purchases/dimensions', { params }),
            api.get('/admin/reports/purchases/performance', { params }),
            api.get('/admin/reports/purchases/product-spend', { params }),
            fetchTrend(),
        ]);
        if (seq !== loadSeq) return;
        dimensions.value = dims.data?.data || { supplier_summary: [], status_summary: [] };
        performance.value = perf.data?.data || { summary: {}, supplier_summary: [] };
        productSpend.value = prod.data?.data || { summary: {}, product_summary: [] };
    } catch {
        if (seq === loadSeq) loadError.value = true;
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
            firstLoad.value = false;
            await nextTick();
            renderTrend();
        }
    }
};

const applyFilters = () => {
    pagination.current_page = 1;
    productPage.value = 1;
    groupBy.value = suggestedGroup();
    writeQuery();
    loadAll();
};

const resetFilters = () => {
    Object.assign(filters, { range: null, supplier_id: null, status: '' });
    applyFilters();
};

const toggleSupplier = (id) => {
    filters.supplier_id = filters.supplier_id === id ? null : id;
    applyFilters();
};

const toggleStatus = (status) => {
    filters.status = filters.status === status ? '' : status;
    tab.value = 'orders';
    applyFilters();
};

const onGroupChange = () => {
    writeQuery();
    fetchTrend();
};

// Page and sort only touch the orders table.
const onPageChange = (sizeChanged) => {
    if (sizeChanged) pagination.current_page = 1;
    writeQuery();
    fetchOrders().catch(() => ElMessage.error(t('failed_to_load_report')));
};

const SORT_FIELDS = { landed_cost: 'landed', cost_variance: 'variance' };
const onSortChange = ({ prop, order }) => {
    const field = SORT_FIELDS[prop];
    ordersSort.value = field && order ? `${field}_${order === 'ascending' ? 'asc' : 'desc'}` : null;
    pagination.current_page = 1;
    writeQuery();
    fetchOrders().catch(() => ElMessage.error(t('failed_to_load_report')));
};

const ordersDefaultSort = computed(() => {
    if (!ordersSort.value) return {};
    const [field, dir] = ordersSort.value.split('_');
    const prop = Object.keys(SORT_FIELDS).find((k) => SORT_FIELDS[k] === field);
    return { prop, order: dir === 'asc' ? 'ascending' : 'descending' };
});

const rangeFrom = computed(() => (pagination.total ? (pagination.current_page - 1) * pagination.per_page + 1 : 0));
const rangeTo = computed(() => Math.min(pagination.current_page * pagination.per_page, pagination.total));

// ── KPIs ─────────────────────────────────────────────────────────────────
const kpis = computed(() => {
    const s = summary.value || {};
    const perf = performance.value?.summary || {};
    const variance = Number(s.cost_variance) || 0;
    const open = Number(s.pending_orders) || 0;
    const done = Number(s.completed_orders) || 0;

    const extras = [
        Number(s.total_tax) ? t('prpt_tax_n', { amount: formatCurrency(s.total_tax) }) : '',
        Number(s.total_discount) ? t('prpt_discount_n', { amount: formatCurrency(s.total_discount) }) : '',
    ].filter(Boolean).join(' · ');

    return [
        { key: 'spend', icon: Coin, tone: 'green', title: t('total_spend'), value: formatCurrency(s.total_spend), sub: extras },
        {
            key: 'orders',
            icon: ShoppingCart,
            tone: 'blue',
            title: t('total_orders'),
            value: formatCount(s.total_orders || 0),
            sub: t('prpt_open_done', { open: formatCount(open), done: formatCount(done) }),
        },
        { key: 'avg', icon: DataLine, tone: 'purple', title: t('average_order_value'), value: formatCurrency(s.average_order_value) },
        {
            key: 'variance',
            icon: TrendCharts,
            tone: variance > 0.005 ? 'red' : 'green',
            title: t('prpt_landed_vs_ordered'),
            hint: t('landed_cost_basis_hint'),
            value: Math.abs(variance) < 0.005 ? t('prpt_on_budget') : signedCurrency(variance),
            valueClass: variance > 0.005 ? 'tone-bad' : variance < -0.005 ? 'tone-good' : '',
            sub: Number(s.pending_lines)
                ? t('prpt_pending_lines', { count: s.pending_lines, total: s.line_count })
                : (Number(s.ordered_cost) ? signedPercent(s.cost_variance_percent) : ''),
        },
        {
            key: 'margin',
            icon: PieChart,
            tone: 'amber',
            title: t('planned_margin'),
            hint: t('prpt_planned_margin_hint'),
            value: formatPercent(perf.planned_margin || 0, 1),
            valueClass: marginTone(perf.planned_margin || 0),
            sub: t('prpt_planned_profit_n', { amount: formatCurrency(perf.planned_profit || 0) }),
        },
    ];
});

// ── Supplier share and table ──────────────────────────────────────────────
const supplierRows = computed(() => {
    const rows = dimensions.value?.supplier_summary || [];
    const total = rows.reduce((sum, r) => sum + Number(r.total_spend || 0), 0) || 1;
    const margins = new Map((performance.value?.supplier_summary || []).map((r) => [r.supplier_id, r.planned_margin]));
    return rows.map((r) => ({
        ...r,
        share: (Number(r.total_spend || 0) / total) * 100,
        planned_margin: margins.has(r.supplier_id) ? Number(margins.get(r.supplier_id)) : null,
    })).sort((a, b) => b.total_spend - a.total_spend);
});

const SHARE_TOP = 6;
const supplierShare = computed(() => {
    const rows = supplierRows.value;
    const top = rows.slice(0, SHARE_TOP).map((r) => ({ key: r.supplier_id, supplier_id: r.supplier_id, name: r.supplier_name, total_spend: r.total_spend, share: r.share }));
    const rest = rows.slice(SHARE_TOP);
    if (rest.length) {
        top.push({
            key: 'rest',
            supplier_id: null,
            name: t('prpt_other_suppliers', { count: rest.length }),
            total_spend: rest.reduce((sum, r) => sum + Number(r.total_spend || 0), 0),
            share: rest.reduce((sum, r) => sum + r.share, 0),
        });
    }
    return top;
});

const statusSummary = computed(() => {
    const rows = dimensions.value?.status_summary || [];
    return STATUSES.map((s) => rows.find((r) => r.status === s)).filter(Boolean);
});

// ── Products ─────────────────────────────────────────────────────────────
const productSearch = ref('');
const productPage = ref(1);
const productSort = ref({ prop: 'total_cost', order: 'descending' });

const products = computed(() => {
    const rows = productSpend.value?.product_summary || [];
    const total = rows.reduce((sum, r) => sum + Number(r.total_cost || 0), 0) || 1;
    return rows.map((r) => ({ ...r, share: (Number(r.total_cost || 0) / total) * 100 }));
});

const filteredProducts = computed(() => {
    const q = productSearch.value.trim().toLowerCase();
    const list = q ? products.value.filter((p) => String(p.product_name || '').toLowerCase().includes(q)) : products.value.slice();
    const { prop, order } = productSort.value;
    if (prop && order) {
        const dir = order === 'ascending' ? 1 : -1;
        list.sort((a, b) => (prop === 'product_name'
            ? String(a[prop]).localeCompare(String(b[prop]), locale.value) * dir
            : (Number(a[prop]) - Number(b[prop])) * dir));
    }
    return list;
});

const pagedProducts = computed(() => filteredProducts.value.slice((productPage.value - 1) * PRODUCT_PAGE, productPage.value * PRODUCT_PAGE));

const onProductSort = ({ prop, order }) => {
    productSort.value = { prop, order };
    productPage.value = 1;
};

// ── Trend chart ──────────────────────────────────────────────────────────
const trendChartRef = ref(null);
let trendChart = null;

const groupOptions = computed(() => [
    { label: t('daily'), value: 'day' },
    { label: t('weekly'), value: 'week' },
    { label: t('monthly'), value: 'month' },
    { label: t('by_supplier'), value: 'supplier' },
]);

const periodName = (item) => {
    if (groupBy.value === 'supplier') return item.supplier_name || '—';
    if (groupBy.value === 'month' && item.year) {
        return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', { month: 'short', year: 'numeric' })
            .format(new Date(item.year, item.month - 1, 1));
    }
    if (groupBy.value === 'week' && item.year) return t('prpt_week_label', { week: item.week, year: item.year });
    if (item.date) {
        return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', { day: 'numeric', month: 'short' })
            .format(new Date(`${item.date}T00:00:00`));
    }
    return item.period || '—';
};

const renderTrend = () => {
    if (!trendChartRef.value) return;
    if (!trendChart || trendChart.getDom() !== trendChartRef.value) {
        trendChart?.dispose();
        trendChart = echarts.init(trendChartRef.value);
    }
    const rows = groupBy.value === 'supplier'
        ? trend.value.slice().sort((a, b) => b.total_spend - a.total_spend).slice(0, 12)
        : trend.value;
    const labels = rows.map(periodName);
    const compact = new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: 1 });

    trendChart.setOption({
        grid: { left: 8, right: 8, top: 36, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            // Either series can be switched off in the legend, so each line is
            // formatted by which series it is, not by its position.
            formatter: (items) => `<strong>${items[0]?.axisValue ?? ''}</strong><br/>` + items
                .map((item) => `${item.marker} ${item.seriesName}: ${item.seriesIndex === 0 ? formatCurrency(item.value) : formatCount(item.value)}`)
                .join('<br/>'),
        },
        legend: { top: 0, data: [t('total_spend'), t('orders_count')], textStyle: { color: '#64748b' } },
        xAxis: {
            type: 'category',
            data: labels,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisLabel: { color: '#64748b', hideOverlap: true },
        },
        yAxis: [
            { type: 'value', axisLabel: { color: '#94a3b8', formatter: (v) => compact.format(v) }, splitLine: { lineStyle: { color: '#f1f5f9' } } },
            { type: 'value', minInterval: 1, axisLabel: { color: '#94a3b8' }, splitLine: { show: false } },
        ],
        series: [
            {
                name: t('total_spend'),
                type: 'bar',
                data: rows.map((r) => Number(r.total_spend || 0)),
                barMaxWidth: 28,
                itemStyle: { color: '#2563eb', borderRadius: [4, 4, 0, 0] },
            },
            {
                name: t('orders_count'),
                type: 'line',
                yAxisIndex: 1,
                data: rows.map((r) => Number(r.total_orders || 0)),
                smooth: true,
                symbolSize: 6,
                itemStyle: { color: '#f59e0b' },
                lineStyle: { width: 2 },
            },
        ],
    }, true);
};

// ── Export ───────────────────────────────────────────────────────────────
const exportReport = async () => {
    exporting.value = true;
    try {
        const response = await api.get('/admin/reports/purchases/export', { params: baseParams(), responseType: 'blob' });
        // A BOM so Excel opens the Arabic names as UTF-8 rather than mojibake.
        const blob = new Blob(['﻿', response.data], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const span = filters.range?.length ? `${filters.range[0]}_${filters.range[1]}` : localIsoDate();
        link.download = `purchases-report-${span}.csv`;
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

// ── Formatting ───────────────────────────────────────────────────────────
const formatCurrency = (value) => formatMoney(value);
const formatPercent = (value, digits = 1) => `${new Intl.NumberFormat(numberLocale(), {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
}).format(Number(value) || 0)}%`;

const signedCurrency = (value) => {
    const amount = Number(value) || 0;
    return `${amount > 0 ? '+' : '−'}${formatCurrency(Math.abs(amount))}`;
};
const signedPercent = (value) => {
    const percent = Number(value) || 0;
    return `${percent > 0 ? '+' : '−'}${formatPercent(Math.abs(percent), 1)}`;
};

const marginTone = (value) => {
    const v = Number(value) || 0;
    if (v < 0) return 'tone-bad';
    if (v < 15) return 'tone-warn';
    return 'tone-good';
};

const statusText = (status) => ({
    pending: t('sales_status_pending'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    completed: t('completed'),
    cancelled: t('sales_status_cancelled'),
}[status] || status);

// Over budget is the case worth spotting, so it is marked on the row.
const orderRowClass = ({ row }) => (Number(row.cost_variance) > 0.005 ? 'row-over-budget' : '');

const orderLink = (row) => ({ name: 'admin.purchases.orders', query: { search: row.order_number } });

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => {
    isNarrow.value = window.innerWidth < 768;
    trendChart?.resize();
};

watch(() => route.query, (query) => {
    if (route.name !== 'admin.purchases.report' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    loadAll();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    if (!suppliers.value.length) suppliersStore.fetchSuppliers?.()?.catch?.(() => {});
    loadAll();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    trendChart?.dispose();
    trendChart = null;
});
</script>

<style scoped>
.purchases-report { font-family: 'Cairo', sans-serif; }

.panel-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
    margin-bottom: 1.25rem;
}

/* ── Filters ── */
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; padding: 0.85rem 1rem; }
.filter-dates { max-width: 290px; }
.filter-select { width: 200px; }
.period-label { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; color: #64748b; margin-inline-start: auto; }

/* ── KPIs ── */
.stat-card { border-radius: 14px; }
.stat-inner { display: flex; align-items: flex-start; gap: 0.85rem; }
.stat-icon {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.amber { background: #fffbeb; color: #d97706; }
.stat-details { min-width: 0; flex: 1; }
.stat-title { margin: 0; font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 0.3rem; }
.hint-icon { color: #cbd5e1; cursor: help; }
.stat-details h3 {
    margin: 0.15rem 0 0;
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-sub { display: block; font-size: 0.74rem; color: #94a3b8; margin-top: 0.2rem; line-height: 1.5; }

.empty-report { padding: 2.5rem 1rem; }

/* ── Charts row ── */
.charts-row { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr); gap: 1.25rem; }
.charts-row .panel-card { margin-bottom: 1.25rem; }
.card-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.card-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a; }
.chart-wrap { position: relative; }
.chart { height: 300px; width: 100%; }
.chart-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #94a3b8; margin: 0; }

.share-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.75rem; }
.share-list li { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: 'name amount' 'bar pct'; gap: 0.25rem 0.75rem; align-items: center; }
.share-name { grid-area: name; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.88rem; }
.share-name.is-on { color: #2563eb; }
.share-amount { grid-area: amount; font-weight: 700; font-size: 0.85rem; font-variant-numeric: tabular-nums; }
.share-bar { grid-area: bar; height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
.share-bar span { display: block; height: 100%; background: #2563eb; border-radius: 999px; }
.share-pct { grid-area: pct; font-size: 0.75rem; color: #64748b; text-align: end; font-variant-numeric: tabular-nums; }

/* ── Detail ── */
.detail-card { padding-top: 0.4rem; }
.tab-count {
    display: inline-block;
    margin-inline-start: 0.3rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1.5;
}

.status-strip { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; margin-bottom: 0.9rem; }
.status-chip {
    all: unset;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    font-size: 0.8rem;
    cursor: pointer;
    color: #334155;
}
.status-chip:hover { border-color: #93c5fd; }
.status-chip:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.status-chip.is-on { border-color: #2563eb; background: #eff6ff; }
.status-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--s); }
.cancelled-note { font-size: 0.78rem; color: #94a3b8; }

.s-pending { --s: #94a3b8; }
.s-confirmed { --s: #d97706; }
.s-processing { --s: #2563eb; }
.s-completed { --s: #16a34a; }
.s-cancelled { --s: #dc2626; }

.status-pill {
    display: inline-block;
    padding: 0.1rem 0.6rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--s);
    background: color-mix(in srgb, var(--s) 12%, #fff);
}

.cell-stack { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.cell-stack.num { align-items: flex-end; }
.cell-secondary { font-size: 0.76rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; }
.order-link { color: #2563eb; text-decoration: none; }
.order-link:hover { text-decoration: underline; }
.num { font-variant-numeric: tabular-nums; }
.muted { color: #94a3b8; }
.is-estimated { border-bottom: 1px dashed currentColor; cursor: help; color: #64748b; }

.hinted-header { display: inline-flex; align-items: center; gap: 0.25rem; cursor: help; }
.hinted-header .el-icon { font-size: 0.8rem; opacity: 0.55; }

.tone-good { color: #16a34a; }
.tone-bad { color: #dc2626; }
.tone-warn { color: #d97706; }

.link-button { all: unset; cursor: pointer; font-weight: 600; color: #0f172a; }
.link-button:hover { color: #2563eb; text-decoration: underline; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

:deep(.row-over-budget) td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
[dir='rtl'] :deep(.row-over-budget) td:first-child { box-shadow: inset -3px 0 0 #dc2626; }

.products-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: stretch; margin-bottom: 0.9rem; }
.product-search { max-width: 280px; }
.callout {
    display: flex;
    flex-direction: column;
    padding: 0.35rem 0.8rem;
    border-radius: 10px;
    background: #f0fdf4;
    font-size: 0.82rem;
    min-width: 0;
}
.callout.is-warn { background: #fffbeb; }
.callout-label { font-size: 0.72rem; color: #64748b; }
.callout strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    font-size: 0.85rem;
}

@media (max-width: 1100px) {
    .charts-row { grid-template-columns: 1fr; gap: 0; }
}

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-icon { display: none; }
    .stat-details h3 { font-size: 1.05rem; }

    .panel-card { padding: 0.85rem; }
    .filter-dates { max-width: none; width: 100% !important; }
    .filter-select { width: calc(50% - 0.375rem); }
    .period-label { margin-inline-start: 0; }
    .chart { height: 240px; }
    .product-search { max-width: none; flex-basis: 100%; }
    .pagination-row { justify-content: center; }
}
</style>
