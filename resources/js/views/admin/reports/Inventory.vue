<template>
    <div class="reports-inventory">
        <AdminPageHeader
            badge="WMS"
            :title="$t('inventory_report')"
            :subtitle="$t('irpt_subtitle')"
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

        <!-- Filters apply as they change and live in the address bar, so a
             refresh or a shared link shows the same stock. -->
        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('warehouse') }}</label>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable filterable>
                    <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                </el-select>
            </div>
            <!-- Searched on the server: the list used to be the first hundred
                 products, so most of the catalogue could not be picked. -->
            <div class="filter-field">
                <label>{{ $t('product') }}</label>
                <el-select
                    v-model="filters.product_id"
                    :placeholder="$t('irpt_search_product')"
                    clearable
                    filterable
                    remote
                    remote-show-suffix
                    :remote-method="searchProducts"
                    :loading="productSearching"
                >
                    <el-option v-for="product in products" :key="product.id" :label="productName(product)" :value="product.id">
                        <span>{{ productName(product) }}</span>
                        <span v-if="product.sku" class="option-hint" dir="ltr">{{ product.sku }}</span>
                    </el-option>
                </el-select>
            </div>
            <!-- A stock report is a snapshot; the period narrows it to rows
                 that last changed within it, and says so. -->
            <div class="filter-field">
                <label>
                    {{ $t('irpt_updated_within') }}
                    <el-tooltip :content="$t('irpt_updated_hint')" placement="top">
                        <el-icon class="label-hint"><InfoFilled /></el-icon>
                    </el-tooltip>
                </label>
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
                <span v-if="refreshing && ready" class="updating-label" aria-live="polite">
                    <el-icon class="is-loading"><Loading /></el-icon>{{ $t('sr_updating') }}
                </span>
            </template>
        </AdminFilterBar>

        <el-result v-if="loadError" icon="error" :title="$t('failed_to_load_report')">
            <template #extra>
                <el-button type="primary" :icon="RefreshRight" :loading="loading" @click="loadReport">{{ $t('cat_admin_retry') }}</el-button>
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
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.label }}</p>
                            <small v-if="stat.sub" :class="stat.subClass">{{ stat.sub }}</small>
                        </div>
                    </div>
                </el-card>
            </AdminStatGrid>

            <el-empty
                v-if="ready && !loading && !overall.product_count"
                class="empty-report"
                :description="$t('no_data_for_current_filters')"
                :image-size="100"
            >
                <el-button v-if="activeFilterCount" @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
            </el-empty>

            <template v-else>
                <el-row :gutter="20" class="charts-section">
                    <!-- ── Largest holdings ── -->
                    <el-col :xs="24" :lg="14">
                        <el-card shadow="hover" class="panel-card">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ $t('irpt_top_holdings') }}</span>
                                    <span v-if="topProducts.length" class="count-pill">{{ topProducts.length }}</span>
                                </div>
                            </template>
                            <el-skeleton v-if="!ready" :rows="7" animated />
                            <div v-else class="chart-wrap" :class="{ 'is-refreshing': loading }">
                                <div ref="stockChartRef" class="chart-box"></div>
                                <p v-if="!topProducts.length" class="chart-empty">{{ $t('no_data_for_current_filters') }}</p>
                            </div>
                        </el-card>
                    </el-col>

                    <!-- ── Stock health ── -->
                    <!-- A proportion bar and three counts in place of a donut:
                         three slices read no better as angles, and a count you
                         can click through to the rows behind it does more. -->
                    <el-col :xs="24" :lg="10">
                        <el-card shadow="hover" class="panel-card">
                            <template #header>
                                <div class="card-header">
                                    <span>
                                        {{ $t('stock_health') }}
                                        <el-tooltip :content="$t('irpt_health_hint')" placement="top">
                                            <el-icon class="label-hint"><InfoFilled /></el-icon>
                                        </el-tooltip>
                                    </span>
                                </div>
                            </template>
                            <el-skeleton v-if="!ready" :rows="5" animated />
                            <div v-else class="health" :class="{ 'is-refreshing': loading }">
                                <div class="health-bar" role="img" :aria-label="healthSummary">
                                    <span
                                        v-for="segment in healthSegments"
                                        :key="segment.key"
                                        :class="`tone-${segment.key}`"
                                        :style="{ width: `${segment.share}%` }"
                                    />
                                </div>
                                <ul class="health-list">
                                    <li v-for="segment in healthSegments" :key="segment.key">
                                        <component
                                            :is="segment.alert ? 'button' : 'div'"
                                            :type="segment.alert ? 'button' : undefined"
                                            class="health-row"
                                            :class="{ 'is-link': segment.alert && segment.count }"
                                            :disabled="segment.alert && !segment.count ? true : undefined"
                                            @click="segment.alert && segment.count && showAlerts(segment.alert)"
                                        >
                                            <span class="dot" :class="`tone-${segment.key}`" />
                                            <span class="health-label">{{ segment.label }}</span>
                                            <strong>{{ formatNumber(segment.count) }}</strong>
                                            <span class="health-share">{{ formatPercent(segment.share) }}</span>
                                        </component>
                                    </li>
                                </ul>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- ── By warehouse ── -->
                <el-card shadow="hover" class="panel-card section-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('warehouse_summary') }}</span>
                            <el-button v-if="filters.warehouse_id" size="small" text type="primary" @click="filters.warehouse_id = null">
                                {{ $t('clear_filter') }}
                            </el-button>
                        </div>
                    </template>
                    <el-skeleton v-if="!ready" :rows="4" animated />
                    <!-- A row narrows the whole report to that warehouse. -->
                    <el-table
                        v-else
                        :data="warehouseRows"
                        stripe
                        style="width: 100%"
                        max-height="420"
                        :class="{ 'is-refreshing': loading }"
                        :row-class-name="warehouseRowClass"
                        @row-click="toggleWarehouse"
                    >
                        <el-table-column prop="warehouse_name" :label="$t('warehouse')" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('quantity')" min-width="110" align="right">
                            <template #default="{ row }"><span class="num">{{ formatNumber(row.total_quantity) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('irpt_available')" min-width="110" align="right">
                            <template #default="{ row }"><span class="num">{{ formatNumber(row.total_available) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('value')" min-width="200">
                            <template #default="{ row }">
                                <div class="value-cell">
                                    <span class="num">{{ formatCurrency(row.total_value) }}</span>
                                    <div class="share-bar"><span :style="{ width: `${Math.max(1, row.share)}%` }" /></div>
                                    <small class="num">{{ formatPercent(row.share) }}</small>
                                </div>
                            </template>
                        </el-table-column>
                        <template #empty>
                            <span class="muted">{{ $t('no_data_for_current_filters') }}</span>
                        </template>
                    </el-table>
                </el-card>

                <!-- ── What needs reordering ── -->
                <!-- The row list is fetched only once this card is on screen and
                     open, and page by page: it used to be a slice of the
                     dashboard's catalogue-wide list, whatever the filters said. -->
                <CollapsibleCard
                    id="inventory-alerts"
                    ref="alertsCardRef"
                    :title="$t('stock_alerts')"
                    :count="alertsPagination.total || null"
                    class="section-card"
                    @active-change="alertsSection.setActive"
                >
                    <template #extra>
                        <el-segmented v-model="alertsStatus" :options="alertOptions" size="small" />
                    </template>

                    <el-skeleton v-if="!alertsReady" :rows="6" animated />
                    <template v-else>
                        <el-table
                            v-loading="alertsLoading"
                            :data="alerts"
                            stripe
                            style="width: 100%"
                        >
                            <el-table-column :label="$t('product')" min-width="220">
                                <template #default="{ row }">
                                    <div class="cell-stack">
                                        <span class="cell-primary">{{ productName(row.product) }}</span>
                                        <span v-if="row.product?.sku" class="cell-secondary" dir="ltr">{{ row.product.sku }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('warehouse')" min-width="140" show-overflow-tooltip>
                                <template #default="{ row }">{{ row.warehouse?.name || '—' }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('irpt_on_hand')" width="110" align="right">
                                <template #default="{ row }"><span class="num">{{ formatNumber(row.quantity) }}</span></template>
                            </el-table-column>
                            <el-table-column :label="$t('irpt_reserved')" width="100" align="right">
                                <template #default="{ row }">
                                    <span class="num" :class="{ muted: !Number(row.reserved_quantity) }">{{ formatNumber(row.reserved_quantity) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('irpt_available')" width="110" align="right">
                                <template #default="{ row }">
                                    <strong class="num" :class="Number(row.available) > 0 ? 'tone-text-low' : 'tone-text-out'">
                                        {{ formatNumber(row.available) }}
                                    </strong>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('irpt_reorder_point')" width="130" align="right">
                                <template #default="{ row }"><span class="num">{{ formatNumber(row.reorder_point) }}</span></template>
                            </el-table-column>
                            <template #empty>
                                <span class="muted">{{ $t('irpt_no_alerts') }}</span>
                            </template>
                        </el-table>

                        <div v-if="alertsPagination.total > 0" class="pagination-row">
                            <router-link :to="inventoryLink" class="inventory-link">
                                {{ $t('irpt_open_in_inventory') }}
                                <el-icon><Right /></el-icon>
                            </router-link>
                            <el-pagination
                                v-model:current-page="alertsPagination.current_page"
                                v-model:page-size="alertsPagination.per_page"
                                :page-sizes="[10, 20, 50]"
                                :total="alertsPagination.total"
                                layout="total, sizes, prev, pager, next"
                                background
                                @size-change="onAlertsPage(true)"
                                @current-change="onAlertsPage(false)"
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
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import { Box, Goods, Warning, Coin, Download, RefreshRight, InfoFilled, Loading, Right } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

/* ------------------------------------------------------------------ *
 * Filters, kept in the URL
 * ------------------------------------------------------------------ */
const PERIOD_PRESETS = ['all', 'today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];
const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
const toId = (value) => (/^\d+$/.test(String(value || '')) ? Number(value) : null);

const filtersFromQuery = (query) => {
    const period = PERIOD_PRESETS.includes(query.period) ? query.period : 'all';
    return {
        warehouse_id: toId(query.warehouse),
        product_id: toId(query.product),
        date_filter_type: period,
        start_date: period === 'custom' && isIsoDate(query.from) ? query.from : null,
        end_date: period === 'custom' && isIsoDate(query.to) ? query.to : null,
    };
};

const filters = reactive(filtersFromQuery(route.query));

const filtersToQuery = () => {
    const query = {};
    if (filters.warehouse_id) query.warehouse = String(filters.warehouse_id);
    if (filters.product_id) query.product = String(filters.product_id);
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

/** "Custom" chosen with no dates yet: nothing new to report until there are. */
const awaitingRange = computed(() => filters.date_filter_type === 'custom' && !filters.start_date && !filters.end_date);

const activeFilterCount = computed(() => [
    filters.warehouse_id,
    filters.product_id,
    filters.date_filter_type !== 'all' ? filters.date_filter_type : null,
].filter(Boolean).length);

const params = () => {
    const out = { date_filter_type: filters.date_filter_type || 'all' };
    if (filters.warehouse_id) out.warehouse_id = filters.warehouse_id;
    if (filters.product_id) out.product_id = filters.product_id;
    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) out.start_date = filters.start_date;
        if (filters.end_date) out.end_date = filters.end_date;
    }
    return out;
};

const resetFilters = () => {
    Object.assign(filters, { warehouse_id: null, product_id: null, date_filter_type: 'all', start_date: null, end_date: null });
};

/* ------------------------------------------------------------------ *
 * Reference data
 * ------------------------------------------------------------------ */
const warehouses = ref([]);
const products = ref([]);
const productSearching = ref(false);
let productSearchToken = 0;
let productSearchTimer = null;

const productName = (product) => product?.name_ar || product?.name || product?.name_en || '—';

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        const data = response.data;
        warehouses.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data?.data?.data) ? data.data.data : []);
    } catch {
        warehouses.value = [];
    }
};

const fetchProducts = async (search = '') => {
    const token = ++productSearchToken;
    productSearching.value = true;
    try {
        const response = await api.get('/products', { params: { per_page: 30, ...(search ? { search } : {}) } });
        if (token !== productSearchToken) return;
        const data = response.data?.data;
        const found = Array.isArray(data) ? data : (Array.isArray(data?.products) ? data.products : []);
        // The chosen product stays among the options whatever the search found,
        // or the select would fall back to showing its bare id.
        const selected = products.value.find((row) => Number(row.id) === Number(filters.product_id));
        products.value = selected && !found.some((row) => row.id === selected.id) ? [selected, ...found] : found;
    } catch {
        if (token === productSearchToken) products.value = [];
    } finally {
        if (token === productSearchToken) productSearching.value = false;
    }
};

const searchProducts = (query) => {
    clearTimeout(productSearchTimer);
    productSearchTimer = setTimeout(() => fetchProducts(query?.trim() || ''), 250);
};

/** A product set from the URL needs a name to show in the select. */
const rememberProduct = async (id) => {
    if (!id || products.value.some((row) => Number(row.id) === Number(id))) return;
    try {
        const response = await api.get(`/products/${id}`);
        const product = response.data?.data?.product || response.data?.data;
        if (product?.id) products.value = [product, ...products.value];
    } catch {
        // Falls back to the bare id; the report itself is unaffected.
    }
};

/* ------------------------------------------------------------------ *
 * Report
 *
 * One request, filtered, for every figure above the alerts table. The page
 * used to call the dashboard's /dashboard/stats for its counts, top products
 * and low-stock list — some sixty queries over invoices, payroll, quotes and
 * the rest, none of which followed the filters on this page — and valued the
 * stock off the top ten products alone.
 * ------------------------------------------------------------------ */
const ready = ref(false);
const loading = ref(false);
const loadError = ref(false);

const EMPTY_OVERALL = {
    total_quantity: 0, total_available: 0, total_value: 0, total_cost_value: 0,
    product_count: 0, uncosted_products: 0, healthy_rows: 0, low_stock_rows: 0, out_of_stock_rows: 0,
};
const report = ref({ warehouse_summary: [], top_products: [], overall: { ...EMPTY_OVERALL } });
const overall = computed(() => ({ ...EMPTY_OVERALL, ...(report.value.overall || {}) }));

let reportToken = 0;
const loadReport = async () => {
    const token = ++reportToken;
    loading.value = true;
    try {
        const response = await api.get('/admin/reports/inventory/dimensions', { params: params() });
        if (token !== reportToken) return;
        const data = response.data?.data || {};
        report.value = {
            warehouse_summary: data.warehouse_summary || [],
            top_products: data.top_products || [],
            overall: data.overall || {},
        };
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

const stats = computed(() => {
    const o = overall.value;
    const alerts = o.low_stock_rows + o.out_of_stock_rows;
    return [
        {
            key: 'products',
            icon: Box,
            label: t('irpt_products_in_stock'),
            value: formatNumber(o.product_count),
        },
        {
            key: 'units',
            icon: Goods,
            label: t('irpt_units'),
            value: formatNumber(o.total_quantity),
            sub: t('irpt_available_of', { available: formatNumber(o.total_available) }),
        },
        {
            key: 'value',
            icon: Coin,
            label: t('irpt_value_at_price'),
            value: formatCurrency(o.total_value),
            // A value at cost that silently leaves out whatever has no cost on
            // file reads as a margin that is not there.
            sub: o.uncosted_products
                ? `${t('irpt_value_at_cost', { amount: formatCurrency(o.total_cost_value) })} · ${t('irpt_uncosted', { count: formatNumber(o.uncosted_products) })}`
                : t('irpt_value_at_cost', { amount: formatCurrency(o.total_cost_value) }),
            subClass: o.uncosted_products ? 'sub-warn' : '',
        },
        {
            key: 'alerts',
            icon: Warning,
            label: t('irpt_needs_reorder'),
            value: formatNumber(alerts),
            sub: t('irpt_needs_reorder_sub', { low: formatNumber(o.low_stock_rows), out: formatNumber(o.out_of_stock_rows) }),
            subClass: alerts ? 'sub-warn' : '',
        },
    ];
});

const topProducts = computed(() => report.value.top_products || []);

const healthSegments = computed(() => {
    const o = overall.value;
    const total = o.healthy_rows + o.low_stock_rows + o.out_of_stock_rows || 1;
    return [
        { key: 'ok', label: t('irpt_healthy'), count: o.healthy_rows, alert: null },
        { key: 'low', label: t('low_stock'), count: o.low_stock_rows, alert: 'low' },
        { key: 'out', label: t('out_of_stock'), count: o.out_of_stock_rows, alert: 'out' },
    ].map((segment) => ({ ...segment, share: (segment.count / total) * 100 }));
});

const healthSummary = computed(() => healthSegments.value
    .map((segment) => `${segment.label}: ${formatNumber(segment.count)}`).join(', '));

const warehouseRows = computed(() => {
    const rows = report.value.warehouse_summary || [];
    const total = rows.reduce((sum, row) => sum + Number(row.total_value || 0), 0) || 1;
    return rows.map((row) => ({ ...row, share: (Number(row.total_value || 0) / total) * 100 }));
});

const toggleWarehouse = (row) => {
    filters.warehouse_id = Number(filters.warehouse_id) === Number(row.warehouse_id) ? null : row.warehouse_id;
};

const warehouseRowClass = ({ row }) => (Number(filters.warehouse_id) === Number(row.warehouse_id) ? 'is-active-row' : '');

/* ------------------------------------------------------------------ *
 * Stock alerts — loaded only while their card is on screen and open
 * ------------------------------------------------------------------ */
const alertsStatus = ref(['low', 'out'].includes(route.query.alerts) ? route.query.alerts : 'low');
const alertOptions = computed(() => [
    { label: t('low_stock'), value: 'low' },
    { label: t('out_of_stock'), value: 'out' },
]);

const alerts = ref([]);
const alertsLoading = ref(false);
const alertsReady = ref(false);
const alertsPagination = reactive({ current_page: 1, per_page: 10, total: 0 });
const alertsCardRef = ref(null);

let alertsToken = 0;
const loadAlerts = async () => {
    const token = ++alertsToken;
    alertsLoading.value = true;
    try {
        const response = await api.get('/admin/inventory/stock', {
            params: {
                ...(filters.warehouse_id ? { warehouse_id: filters.warehouse_id } : {}),
                ...(filters.product_id ? { product_id: filters.product_id } : {}),
                status: alertsStatus.value,
                // The emptiest first: those are the ones to order today.
                sort_by: 'available',
                sort_dir: 'asc',
                page: alertsPagination.current_page,
                per_page: alertsPagination.per_page,
            },
        });
        if (token !== alertsToken) return;
        alerts.value = response.data?.data?.stock || [];
        alertsPagination.total = response.data?.data?.pagination?.total || 0;
    } catch {
        if (token !== alertsToken) return;
        alerts.value = [];
        alertsPagination.total = 0;
        ElMessage.error(t('failed_to_load_report'));
    } finally {
        if (token === alertsToken) {
            alertsLoading.value = false;
            alertsReady.value = true;
        }
    }
};

/** Loads while showing; a filter change while it is out of view waits for it. */
const alertsSection = (() => {
    let active = false;
    let stale = true;
    const run = () => { stale = false; loadAlerts(); };
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

const onAlertsPage = (sizeChanged) => {
    if (sizeChanged) alertsPagination.current_page = 1;
    loadAlerts();
};

watch(alertsStatus, () => {
    alertsPagination.current_page = 1;
    alertsSection.invalidate();
});

/** From the health card: open the matching list and bring it into view. */
const showAlerts = async (status) => {
    alertsStatus.value = status;
    await nextTick();
    alertsCardRef.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

// The inventory screen reads the same filters from its address bar.
const inventoryLink = computed(() => ({
    name: 'admin.inventory.index',
    query: {
        status: alertsStatus.value,
        ...(filters.warehouse_id ? { warehouse_id: filters.warehouse_id } : {}),
    },
}));

/* ------------------------------------------------------------------ *
 * Chart — ECharts arrives the first time there is something to draw
 * ------------------------------------------------------------------ */
const stockChartRef = ref(null);
let stockChart = null;
let chartObserver = null;
let echartsLoader = null;
const loadEcharts = () => {
    echartsLoader ??= import('@/utils/echartsLite').then((module) => module.default);
    return echartsLoader;
};

const disposeChart = () => {
    chartObserver?.disconnect();
    chartObserver = null;
    stockChart?.dispose();
    stockChart = null;
};

/** Ranked horizontal bars: product names are long, and read level there. */
const renderChart = async () => {
    if (!topProducts.value.length) {
        stockChart?.clear();
        return;
    }
    const echarts = await loadEcharts();
    await nextTick();
    const element = stockChartRef.value;
    if (!element) return;
    if (!stockChart || stockChart.getDom() !== element) {
        disposeChart();
        stockChart = echarts.init(element);
        if (typeof ResizeObserver !== 'undefined') {
            chartObserver = new ResizeObserver(() => stockChart?.resize());
            chartObserver.observe(element);
        }
    }

    const rows = topProducts.value.slice().reverse();
    const truncate = (name) => (name.length > 28 ? `${name.slice(0, 27)}…` : name);

    stockChart.setOption({
        grid: { left: 8, right: 24, top: 8, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (items) => {
                const row = rows[items[0]?.dataIndex];
                if (!row) return '';
                return `<strong>${row.product_name || '—'}</strong><br/>`
                    + `${t('quantity')}: ${formatNumber(row.total_quantity)}<br/>`
                    + `${t('irpt_available')}: ${formatNumber(row.total_available)}<br/>`
                    + `${t('value')}: ${formatCurrency(row.total_value)}`;
            },
        },
        xAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#f1f5f9' } },
            axisLabel: { color: '#94a3b8', formatter: (value) => compactNumber(value) },
        },
        yAxis: {
            type: 'category',
            data: rows.map((row) => truncate(row.product_name || '—')),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisLabel: { color: '#334155' },
        },
        series: [{
            type: 'bar',
            data: rows.map((row) => Number(row.total_quantity || 0)),
            barMaxWidth: 20,
            itemStyle: { color: '#2563eb', borderRadius: [0, 4, 4, 0] },
        }],
    }, true);
};

watch([topProducts, locale], renderChart);
watch(stockChartRef, (element) => { if (element) renderChart(); });

/* ------------------------------------------------------------------ *
 * Orchestration
 * ------------------------------------------------------------------ */
const refreshing = computed(() => loading.value || alertsLoading.value);

let filterTimer = null;
watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            router.replace({ query: { ...filtersToQuery(), ...(alertsStatus.value !== 'low' ? { alerts: alertsStatus.value } : {}) } });
            if (awaitingRange.value) return;
            loadReport();
            alertsPagination.current_page = 1;
            alertsSection.invalidate();
        }, 300);
    },
);

watch(alertsStatus, (status) => {
    router.replace({ query: { ...filtersToQuery(), ...(status !== 'low' ? { alerts: status } : {}) } });
});

/* ------------------------------------------------------------------ *
 * Export
 * ------------------------------------------------------------------ */
const exporting = ref(false);
const exportReport = async () => {
    exporting.value = true;
    try {
        const response = await api.get('/admin/reports/inventory/export', { params: params(), responseType: 'blob' });
        // A BOM so Excel opens the Arabic names as UTF-8.
        const url = window.URL.createObjectURL(new Blob(['﻿', response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `inventory-report-${new Date().toLocaleDateString('en-CA')}.csv`;
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
// Report figures are quoted whole; cents on a stock valuation are noise.
const formatCurrency = (value) => formatMoney(value, { decimals: 0 });
const formatNumber = (value) => formatCount(Number(value) || 0);
const formatPercent = (value) => `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 1 }).format(Number(value) || 0)}%`;
const compactNumber = (value) => new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: 1 }).format(value);

onMounted(() => {
    loadWarehouses();
    fetchProducts().then(() => rememberProduct(filters.product_id));
    loadReport();
});

onBeforeUnmount(() => {
    clearTimeout(filterTimer);
    clearTimeout(productSearchTimer);
    disposeChart();
});
</script>

<style scoped>
.reports-inventory {
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

.label-hint {
    margin-inline-start: 0.25rem;
    color: #94a3b8;
    font-size: 0.8rem;
    vertical-align: middle;
    cursor: help;
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

/* Last figures, kept while new ones load: still readable, visibly not current. */
.stat-card,
.chart-wrap,
.health,
.panel-card :deep(.el-table) {
    transition: opacity 0.2s ease;
}

.is-refreshing {
    opacity: 0.55;
    pointer-events: none;
}

/* ── Headline figures ── */
.stat-card {
    border-radius: 1rem;
    border: 1px solid #eef2f7;
}

.stat-card-products { background: linear-gradient(135deg, rgba(59,130,246,0.06), rgba(96,165,250,0.14)); }
.stat-card-units { background: linear-gradient(135deg, rgba(34,197,94,0.06), rgba(74,222,128,0.14)); }
.stat-card-value { background: linear-gradient(135deg, rgba(168,85,247,0.06), rgba(216,180,254,0.14)); }
.stat-card-alerts { background: linear-gradient(135deg, rgba(245,158,11,0.06), rgba(253,224,71,0.14)); }

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
    gap: 0.5rem;
    width: 100%;
    font-weight: 600;
}

.count-pill {
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.78rem;
    background: #eef2ff;
    color: #4338ca;
}

.chart-wrap {
    position: relative;
}

.chart-box {
    width: 100%;
    height: 340px;
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

/* ── Stock health ── */
.health-bar {
    display: flex;
    height: 14px;
    border-radius: 999px;
    overflow: hidden;
    background: #f1f5f9;
    margin: 0.5rem 0 1.25rem;
}

.health-bar span {
    display: block;
    height: 100%;
    transition: width 0.3s ease;
}

.tone-ok { background: #16a34a; }
.tone-low { background: #f59e0b; }
.tone-out { background: #dc2626; }

.health-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.health-row {
    all: unset;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: auto 1fr auto 4rem;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.9rem;
}

.health-row.is-link {
    cursor: pointer;
}

.health-row.is-link:hover {
    background: #eff6ff;
}

.health-row.is-link:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.health-row .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.health-row strong {
    font-variant-numeric: tabular-nums;
}

.health-share {
    text-align: end;
    font-size: 0.8rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

/* ── Tables ── */
.num {
    font-variant-numeric: tabular-nums;
}

.muted {
    color: #94a3b8;
}

.value-cell {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.6rem;
}

.value-cell small {
    color: #64748b;
    font-size: 0.75rem;
}

.share-bar {
    height: 6px;
    min-width: 40px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.share-bar span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: #2563eb;
}

.panel-card :deep(.el-table__row) {
    cursor: pointer;
}

.panel-card :deep(.is-active-row) td {
    background: #dbeafe !important;
    font-weight: 700;
}

.cell-stack {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.cell-primary {
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cell-secondary {
    font-size: 0.76rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

.tone-text-low { color: #b45309; }
.tone-text-out { color: #dc2626; }

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.inventory-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
}

.inventory-link:hover {
    text-decoration: underline;
}

/* The arrow points along the reading direction. */
[dir='rtl'] .inventory-link .el-icon {
    transform: scaleX(-1);
}

@media (max-width: 768px) {
    .filter-field--range {
        grid-column: auto;
    }

    .chart-box {
        height: 280px;
    }

    .pagination-row {
        justify-content: center;
    }
}
</style>
