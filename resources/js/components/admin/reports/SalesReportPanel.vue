<template>
    <div class="sales-report-panel">
        <!-- Skeletons only for the very first load. After that a refresh keeps
             the previous figures on screen, dimmed, until the new ones land. -->
        <AdminStatGrid v-if="stats.pending">
            <el-card v-for="n in Math.max(statCards.length, 4)" :key="n" shadow="hover" class="stat-card skeleton-card">
                <el-skeleton :rows="2" animated />
            </el-card>
        </AdminStatGrid>

        <AdminStatGrid v-else :class="{ 'is-refreshing': stats.refreshing }">
            <el-card
                v-for="stat in statCards"
                :key="stat.key"
                shadow="hover"
                class="stat-card"
                :class="`theme-${stat.color || statTheme(stat.key)}`"
            >
                <div class="stat-content">
                    <div class="stat-icon">
                        <component :is="stat.icon" />
                    </div>
                    <div class="stat-info">
                        <h3>{{ formatValue(stat.value, stat.format) }}</h3>
                        <p>{{ stat.label }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Executive Financial & Profitability Ribbon -->
        <div v-if="!insights.pending && metrics" class="financial-ribbon" :class="{ 'is-refreshing': insights.refreshing }">
            <div class="financial-ribbon-header">
                <div class="ribbon-title-wrap">
                    <span class="ribbon-icon"><i class="fas fa-coins"></i></span>
                    <span class="ribbon-title">{{ $t('financial_performance') }}</span>
                </div>
                <div class="ribbon-status-badge" :class="Number(metrics.gross_profit) >= 0 ? 'is-profit' : 'is-loss'">
                    <i :class="Number(metrics.gross_profit) >= 0 ? 'fas fa-arrow-trend-up' : 'fas fa-arrow-trend-down'"></i>
                    <span>{{ Number(metrics.gross_profit) >= 0 ? $t('gross_profit') : $t('gross_profit') }}</span>
                    <strong>{{ formatPercentage(metrics.gross_margin) }}</strong>
                </div>
            </div>

            <el-row :gutter="14" class="financial-cards-row">
                <el-col :xs="12" :sm="6">
                    <div class="financial-card financial-card--revenue">
                        <div class="financial-card-header">
                            <span class="financial-label">{{ $t('sales_revenue') }}</span>
                            <span class="financial-icon-badge"><i class="fas fa-wallet"></i></span>
                        </div>
                        <strong class="financial-value">{{ formatMoney(metrics.total_revenue) }}</strong>
                    </div>
                </el-col>
                <el-col :xs="12" :sm="6">
                    <div class="financial-card financial-card--cost">
                        <div class="financial-card-header">
                            <span class="financial-label">{{ $t('cost_of_goods') }}</span>
                            <span class="financial-icon-badge"><i class="fas fa-tags"></i></span>
                        </div>
                        <strong class="financial-value">{{ formatMoney(metrics.total_cost) }}</strong>
                    </div>
                </el-col>
                <el-col :xs="12" :sm="6">
                    <div class="financial-card financial-card--profit">
                        <div class="financial-card-header">
                            <span class="financial-label">{{ $t('gross_profit') }}</span>
                            <span class="financial-icon-badge" :class="Number(metrics.gross_profit) >= 0 ? 'text-success' : 'text-danger'">
                                <i :class="Number(metrics.gross_profit) >= 0 ? 'fas fa-arrow-up-right-dots' : 'fas fa-arrow-down-right-dots'"></i>
                            </span>
                        </div>
                        <strong class="financial-value" :class="Number(metrics.gross_profit) >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatMoney(metrics.gross_profit) }}
                        </strong>
                    </div>
                </el-col>
                <el-col :xs="12" :sm="6">
                    <div class="financial-card financial-card--margin">
                        <div class="financial-card-header">
                            <span class="financial-label">{{ $t('profit_margin') }}</span>
                            <span class="financial-icon-badge"><i class="fas fa-chart-pie"></i></span>
                        </div>
                        <div class="financial-margin-group">
                            <strong class="financial-value" :class="Number(metrics.gross_margin) >= 0 ? 'profit-positive' : 'profit-negative'">
                                {{ formatPercentage(metrics.gross_margin) }}
                            </strong>
                            <div class="financial-margin-gauge">
                                <div
                                    class="financial-margin-gauge-fill"
                                    :class="Number(metrics.gross_margin) >= 20 ? 'is-good' : (Number(metrics.gross_margin) >= 0 ? 'is-modest' : 'is-negative')"
                                    :style="{ width: Math.min(Math.max(Number(metrics.gross_margin) || 0, 0), 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>

        <!-- The visual Chart Card -->
        <el-card shadow="hover" class="chart-card" :class="{ 'is-refreshing': chart.refreshing && chartMode !== 'none' }">
            <template #header>
                <div class="card-header">
                    <div class="chart-header-title">
                        <i class="fas fa-chart-line chart-header-icon"></i>
                        <span>{{ chartTitle }}</span>
                    </div>
                    <el-tag v-if="chartMode === 'trend'" size="small" type="primary" effect="plain" round>
                        {{ $t('chart_group_trend') }}
                    </el-tag>
                    <el-tag v-else-if="chartMode === 'bar'" size="small" type="info" effect="plain" round>
                        {{ $t('chart_group_breakdown') }}
                    </el-tag>
                </div>
            </template>

            <!-- A chart that cannot be drawn should offer groupings that would draw it. -->
            <div v-if="chartMode === 'none'" class="chart-empty">
                <i class="fas fa-chart-pie chart-empty-icon"></i>
                <p class="chart-empty-note">{{ chartNote || $t('no_chart_for_this_grouping') }}</p>
                <div v-if="chartSuggestions.length" class="chart-empty-actions">
                    <el-button
                        v-for="suggestion in chartSuggestions"
                        :key="suggestion.value"
                        size="small"
                        type="primary"
                        plain
                        @click="emit('select-grouping', suggestion.value)"
                    >
                        {{ suggestion.label }}
                    </el-button>
                </div>
            </div>
            <el-skeleton v-else-if="chart.pending" :rows="6" animated class="chart-skeleton" />
            <div v-else-if="!chartLabels.length && !chartLoading" class="chart-empty">
                <i class="fas fa-inbox chart-empty-icon"></i>
                <p class="chart-empty-note">{{ $t('no_data_for_current_filters') }}</p>
            </div>
            <!-- Kept mounted through a refresh so the chart can animate to new values. -->
            <div v-show="chartMode !== 'none' && !chart.pending && chartLabels.length" ref="chartRef" class="chart-canvas"></div>
        </el-card>

        <!-- Dimension Panels: Employees, Customers, Warehouses -->
        <el-row :gutter="20" class="dimension-panels" :class="{ 'is-refreshing': insights.refreshing }">
            <el-col v-for="card in dimensionCards" :key="card.key" :xs="24" :md="8">
                <CollapsibleCard :id="`breakdown-${card.key}`" :title="card.title" :count="card.rows.length || null">
                    <template v-if="card.activeId" #extra>
                        <el-button
                            size="small"
                            type="danger"
                            link
                            @click="emit('select-dimension', { type: card.key, id: null })"
                        >
                            <i class="fas fa-circle-xmark mr-1"></i>
                            {{ $t('clear_filter') }}
                        </el-button>
                    </template>

                    <el-skeleton v-if="insights.pending" :rows="4" animated />
                    <el-table
                        v-else
                        :data="card.rows"
                        stripe
                        max-height="390"
                        style="width: 100%"
                        :row-class-name="({ row }) => dimensionRowClass(card, row)"
                        @row-click="(row) => selectDimensionRow(card, row)"
                    >
                        <el-table-column :prop="card.nameKey" :label="card.rowLabel" min-width="120" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="dim-name-cell">
                                    <i :class="card.icon" class="dim-icon"></i>
                                    <span class="dim-name-text">{{ row[card.nameKey] || $t('undefined') }}</span>
                                    <span v-if="String(card.activeId) === String(row[card.idKey])" class="dim-active-dot" :title="$t('click_to_clear_filter')"></span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column v-if="dimensionCountKey" :label="countLabel" width="68" align="center">
                            <template #default="{ row }">
                                <span class="dim-count-badge">{{ formatCount(row[dimensionCountKey]) }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="valueLabel" min-width="110">
                            <template #default="{ row }">
                                <div class="dim-value-wrapper">
                                    <div class="dim-value-row">
                                        <span class="dim-money-text">{{ formatMoney(row[dimensionValueKey]) }}</span>
                                        <span class="dim-share-text">{{ getSharePercent(card, row) }}%</span>
                                    </div>
                                    <div class="dim-share-track">
                                        <div class="dim-share-fill" :style="{ width: getSharePercent(card, row) + '%' }"></div>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Only the invoice report knows what is still owed. -->
                        <el-table-column v-if="dimensionDueKey" :label="dueLabel" min-width="95">
                            <template #default="{ row }">
                                <span
                                    class="dim-due-text"
                                    :class="Number(row[dimensionDueKey]) > 0 ? 'profit-negative' : 'profit-positive'"
                                >
                                    {{ formatMoney(row[dimensionDueKey]) }}
                                </span>
                            </template>
                        </el-table-column>

                        <template #empty>
                            <span class="table-empty">{{ $t('no_data_for_current_filters') }}</span>
                        </template>
                    </el-table>
                </CollapsibleCard>
            </el-col>
        </el-row>

        <!-- Product Profitability by Warehouse -->
        <CollapsibleCard
            id="product-profitability"
            :title="$t('product_profitability_by_warehouse')"
            :count="profitability?.product_summary?.length || null"
            class="profitability-table-card"
            @active-change="emit('profitability-active', $event)"
        >
            <el-skeleton v-if="profit.pending" :rows="6" animated />

            <div v-else :class="{ 'is-refreshing': profit.refreshing }">
                <el-row v-if="profitability?.summary" :gutter="16" class="profit-summary-grid">
                    <el-col :xs="12" :md="6">
                        <div class="profit-highlight-card profit-highlight-card--top">
                            <span class="profit-card-label">{{ $t('most_profitable_product') }}</span>
                            <strong class="profit-card-title">{{ profitability.summary.top_product?.product_name || '-' }}</strong>
                            <span class="profit-card-badge is-positive">
                                <i class="fas fa-trophy"></i>
                                {{ formatMoney(profitability.summary.top_product?.gross_profit || 0) }}
                            </span>
                        </div>
                    </el-col>
                    <el-col :xs="12" :md="6">
                        <div class="profit-highlight-card">
                            <span class="profit-card-label">{{ $t('least_profitable_product') }}</span>
                            <strong class="profit-card-title">{{ profitability.summary.lowest_product?.product_name || '-' }}</strong>
                            <span class="profit-card-badge is-negative">
                                {{ formatMoney(profitability.summary.lowest_product?.gross_profit || 0) }}
                            </span>
                        </div>
                    </el-col>
                    <el-col :xs="12" :md="6">
                        <div class="profit-highlight-card">
                            <span class="profit-card-label">{{ $t('total_profit') }}</span>
                            <strong class="profit-card-big-value" :class="Number(profitability.summary.gross_profit) >= 0 ? 'profit-positive' : 'profit-negative'">
                                {{ formatMoney(profitability.summary.gross_profit || 0) }}
                            </strong>
                        </div>
                    </el-col>
                    <el-col :xs="12" :md="6">
                        <div class="profit-highlight-card">
                            <span class="profit-card-label">{{ $t('items_count') }}</span>
                            <strong class="profit-card-big-value">{{ profitability.summary.product_count || 0 }}</strong>
                        </div>
                    </el-col>
                </el-row>

                <el-table :data="productPage" stripe style="width: 100%" @sort-change="handleProductSort">
                    <el-table-column prop="product_name" :label="$t('product')" min-width="180" show-overflow-tooltip />
                    <el-table-column prop="warehouse_name" :label="$t('warehouse')" min-width="120" show-overflow-tooltip />
                    <el-table-column prop="quantity" :label="$t('quantity')" sortable="custom" width="105" align="center" />
                    <el-table-column prop="total_revenue" :label="$t('revenue')" sortable="custom" min-width="120">
                        <template #default="{ row }">{{ formatMoney(row.total_revenue) }}</template>
                    </el-table-column>
                    <el-table-column prop="total_cost" :label="$t('cost')" sortable="custom" min-width="120">
                        <template #default="{ row }">{{ formatMoney(row.total_cost) }}</template>
                    </el-table-column>
                    <el-table-column prop="gross_profit" :label="$t('profit')" sortable="custom" min-width="125">
                        <template #default="{ row }">
                            <strong :class="row.gross_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                                {{ formatMoney(row.gross_profit) }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="gross_margin" :label="$t('margin')" sortable="custom" width="130">
                        <template #default="{ row }">
                            <div class="table-margin-cell">
                                <span :class="Number(row.gross_margin) >= 0 ? 'profit-positive' : 'profit-negative'">
                                    {{ formatPercentage(row.gross_margin) }}
                                </span>
                                <div class="table-margin-bar">
                                    <span
                                        :class="Number(row.gross_margin) >= 20 ? 'bg-success' : (Number(row.gross_margin) >= 0 ? 'bg-warning' : 'bg-danger')"
                                        :style="{ width: Math.min(Math.max(Number(row.gross_margin) || 0, 0), 100) + '%' }"
                                    ></span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <template #empty>
                        <span class="table-empty">{{ $t('no_data_for_current_filters') }}</span>
                    </template>
                </el-table>

                <el-pagination
                    v-if="productRows.length > 10"
                    v-model:current-page="productPageNumber"
                    v-model:page-size="productPageSize"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="productRows.length"
                    layout="total, sizes, prev, pager, next"
                    class="table-pagination"
                />
            </div>
        </CollapsibleCard>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMoney as formatMoneyWith, formatNumber } from '@/utils/currency';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';

const { t, locale } = useI18n();

const props = defineProps({
    statsLoading: { type: Boolean, default: false },
    insightsLoading: { type: Boolean, default: false },
    chartLoading: { type: Boolean, default: false },
    profitabilityLoading: { type: Boolean, default: false },
    statCards: { type: Array, default: () => [] },
    metrics: { type: Object, default: null },
    chartMode: { type: String, default: 'none' }, // 'trend' | 'bar' | 'none'
    chartTitle: { type: String, default: '' },
    chartNote: { type: String, default: '' },
    chartLabels: { type: Array, default: () => [] },
    chartValues: { type: Array, default: () => [] },
    dimensionData: { type: Object, default: null },
    dimensionValueKey: { type: String, default: 'total_sales' },
    dimensionValueLabel: { type: String, default: '' },
    dimensionCountKey: { type: String, default: '' },
    dimensionCountLabel: { type: String, default: '' },
    dimensionDueKey: { type: String, default: '' },
    dimensionDueLabel: { type: String, default: '' },
    activeDimensions: { type: Object, default: () => ({}) },
    chartSuggestions: { type: Array, default: () => [] },
    profitability: { type: Object, default: null },
});

const emit = defineEmits(['select-grouping', 'select-dimension', 'profitability-active']);

const loadState = (isLoading) => {
    const state = reactive({ pending: true, refreshing: false });
    watch(isLoading, (now, before) => {
        if (before && !now) state.pending = false;
        state.refreshing = now && !state.pending;
    });
    return state;
};

const stats = loadState(() => props.statsLoading);
const insights = loadState(() => props.insightsLoading);
const chart = loadState(() => props.chartLoading);
const profit = loadState(() => props.profitabilityLoading);

/* Stat card color theme selector */
const statTheme = (key) => {
    switch (key) {
        case 'total_invoices':
        case 'total_orders':
            return 'primary';
        case 'total_invoiced':
        case 'total_revenue':
            return 'success';
        case 'paid_amount':
            return 'teal';
        case 'due_amount':
        case 'uninvoiced_amount':
            return 'danger';
        case 'average_invoice_value':
        case 'average_order_value':
            return 'purple';
        case 'cost_of_goods':
        case 'total_cost':
            return 'warning';
        case 'gross_margin':
            return 'indigo';
        default:
            return 'primary';
    }
};

/* Product profitability, sorted and paged client-side */
const productSort = ref({ prop: null, order: null });
const productPageNumber = ref(1);
const productPageSize = ref(20);

const productRows = computed(() => {
    const rows = props.profitability?.product_summary || [];
    const { prop, order } = productSort.value;
    if (!prop || !order) return rows;
    const direction = order === 'ascending' ? 1 : -1;
    return [...rows].sort((a, b) => ((Number(a[prop]) || 0) - (Number(b[prop]) || 0)) * direction);
});

const productPage = computed(() => {
    const from = (productPageNumber.value - 1) * productPageSize.value;
    return productRows.value.slice(from, from + productPageSize.value);
});

const handleProductSort = ({ prop, order }) => {
    productSort.value = { prop, order };
    productPageNumber.value = 1;
};

watch(() => props.profitability?.product_summary, () => { productPageNumber.value = 1; });

const valueLabel = computed(() => props.dimensionValueLabel || t('total_sales'));
const countLabel = computed(() => props.dimensionCountLabel || t('count'));
const dueLabel = computed(() => props.dimensionDueLabel || t('due_amount'));

const formatCount = (value) => Number(value || 0).toLocaleString();

const dimensionCards = computed(() => [
    {
        key: 'employee',
        title: t('employees'),
        rowLabel: t('employee'),
        nameKey: 'employee_name',
        idKey: 'employee_id',
        icon: 'fas fa-user-tie',
        rows: props.dimensionData?.employee_summary || [],
        activeId: props.activeDimensions?.employee || null,
    },
    {
        key: 'customer',
        title: t('customers'),
        rowLabel: t('customer'),
        nameKey: 'customer_name',
        idKey: 'customer_id',
        icon: 'fas fa-building',
        rows: props.dimensionData?.customer_summary || [],
        activeId: props.activeDimensions?.customer || null,
    },
    {
        key: 'warehouse',
        title: t('warehouses'),
        rowLabel: t('warehouse'),
        nameKey: 'warehouse_name',
        idKey: 'warehouse_id',
        icon: 'fas fa-warehouse',
        rows: props.dimensionData?.warehouse_summary || [],
        activeId: props.activeDimensions?.warehouse || null,
    },
]);

const dimensionTotals = computed(() => {
    const totals = {};
    for (const card of dimensionCards.value) {
        totals[card.key] = (card.rows || []).reduce(
            (sum, row) => sum + (Number(row[props.dimensionValueKey]) || 0),
            0
        ) || 1;
    }
    return totals;
});

const getSharePercent = (card, row) => {
    const total = dimensionTotals.value[card.key] || 1;
    const value = Number(row[props.dimensionValueKey]) || 0;
    if (total <= 0 || value <= 0) return 0;
    return Math.min(Math.round((value / total) * 100), 100);
};

const selectDimensionRow = (card, row) => {
    const id = row?.[card.idKey];
    if (!id) return;

    emit('select-dimension', {
        type: card.key,
        id: String(card.activeId) === String(id) ? null : id,
        name: row[card.nameKey] || null,
    });
};

const dimensionRowClass = (card, row) => {
    const id = row?.[card.idKey];
    if (!id) return 'dimension-row-inert';
    return String(card.activeId) === String(id) ? 'dimension-row is-active' : 'dimension-row';
};

const chartRef = ref(null);
let chartInstance = null;
let resizeObserver = null;
let cachedEcharts = null;

const AXIS_LINE = '#cbd5e1';
const AXIS_LABEL = '#64748b';

const trendOption = (echartsLib) => {
    const lib = echartsLib || cachedEcharts;
    const areaGradient = lib?.graphic?.LinearGradient
        ? new lib.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: 'rgba(37, 99, 235, 0.24)' },
            { offset: 1, color: 'rgba(37, 99, 235, 0.01)' },
        ])
        : 'rgba(37, 99, 235, 0.12)';

    return {
        grid: { left: 16, right: 28, top: 28, bottom: 14, containLabel: true },
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(255, 255, 255, 0.98)',
            borderColor: '#e2e8f0',
            borderWidth: 1,
            padding: [12, 16],
            textStyle: { color: '#0f172a', fontSize: 13 },
            extraCssText: 'box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12); border-radius: 10px;',
            valueFormatter: (value) => formatMoneyWith(value),
        },
        xAxis: {
            type: 'category',
            data: props.chartLabels,
            boundaryGap: false,
            axisLine: { lineStyle: { color: AXIS_LINE } },
            axisLabel: { color: AXIS_LABEL, fontSize: 12 },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
            axisLabel: { color: AXIS_LABEL, fontSize: 12, formatter: (value) => formatNumber(value) },
        },
        series: [{
            name: props.chartTitle || t('sales_revenue'),
            type: 'line',
            data: props.chartValues,
            smooth: 0.35,
            showSymbol: props.chartLabels.length <= 31,
            symbol: 'circle',
            symbolSize: 6,
            lineStyle: {
                width: 3,
                color: '#2563eb',
                shadowColor: 'rgba(37, 99, 235, 0.25)',
                shadowBlur: 8,
                shadowOffsetY: 4,
            },
            itemStyle: {
                color: '#2563eb',
                borderColor: '#ffffff',
                borderWidth: 2,
            },
            areaStyle: {
                color: areaGradient,
            },
        }],
    };
};

const barOption = (echartsLib) => {
    const lib = echartsLib || cachedEcharts;
    const barGradient = lib?.graphic?.LinearGradient
        ? new lib.graphic.LinearGradient(0, 0, 1, 0, [
            { offset: 0, color: '#60a5fa' },
            { offset: 1, color: '#2563eb' },
        ])
        : '#2563eb';

    const rows = props.chartLabels
        .map((label, index) => ({ label, value: Number(props.chartValues[index]) || 0 }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 10)
        .reverse();

    return {
        grid: { left: 16, right: 64, top: 16, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'item',
            backgroundColor: 'rgba(255, 255, 255, 0.98)',
            borderColor: '#e2e8f0',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#0f172a', fontSize: 13 },
            extraCssText: 'box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12); border-radius: 10px;',
            valueFormatter: (value) => formatMoneyWith(value),
        },
        xAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
            axisLabel: { color: AXIS_LABEL, fontSize: 12, formatter: (value) => formatNumber(value) },
        },
        yAxis: {
            type: 'category',
            data: rows.map((row) => row.label),
            axisLine: { lineStyle: { color: AXIS_LINE } },
            axisLabel: { color: '#334155', fontSize: 12 },
            axisTick: { show: false },
        },
        series: [{
            type: 'bar',
            data: rows.map((row) => row.value),
            barMaxWidth: 20,
            itemStyle: {
                color: barGradient,
                borderRadius: [0, 6, 6, 0],
            },
            label: {
                show: true,
                position: 'right',
                formatter: (params) => formatMoneyWith(params.value),
                color: '#64748b',
                fontSize: 11,
                distance: 8,
            },
        }],
    };
};

let echartsLoader = null;
const loadEcharts = () => {
    echartsLoader ??= import('@/utils/echartsLite').then((module) => {
        cachedEcharts = module.default;
        return module.default;
    });
    return echartsLoader;
};

const disposeChart = () => {
    resizeObserver?.disconnect();
    resizeObserver = null;
    chartInstance?.dispose();
    chartInstance = null;
};

const renderChart = async () => {
    if (props.chartMode === 'none' || !props.chartLabels.length) {
        disposeChart();
        return;
    }

    if (chart.pending) return;

    const echarts = await loadEcharts();
    await nextTick();
    const element = chartRef.value;
    if (!element || props.chartMode === 'none') return;

    if (chartInstance && chartInstance.getDom() !== element) disposeChart();

    if (!chartInstance) {
        chartInstance = echarts.init(element);
        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => chartInstance?.resize());
            resizeObserver.observe(element);
        }
    }

    chartInstance.setOption(props.chartMode === 'trend' ? trendOption(echarts) : barOption(echarts), true);
    chartInstance.resize();
};

watch(() => [props.chartMode, props.chartLabels, props.chartValues], renderChart, { deep: true });
watch(locale, renderChart);
watch(() => chart.pending, renderChart);

onMounted(renderChart);
onBeforeUnmount(disposeChart);

const formatMoney = (value) => formatMoneyWith(value || 0);
const formatPercentage = (value) => `${Number(value || 0).toFixed(2)}%`;
const formatValue = (value, format) => {
    if (format === 'currency') return formatMoney(value);
    if (format === 'percent') return formatPercentage(value);
    return formatNumber(value);
};
</script>

<style scoped>
.sales-report-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.sales-report-panel > *,
.financial-ribbon,
.dimension-panels {
    transition: opacity 0.2s ease;
}

/* Stat Cards */
.stat-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.05);
}

.skeleton-card {
    min-height: 116px;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1.1rem;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 14px;
    font-size: 1.45rem;
    flex-shrink: 0;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #1e293b;
    font-variant-numeric: tabular-nums;
}

.stat-info p {
    margin: 0.25rem 0 0;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Color theme variations for stat cards */
.theme-primary .stat-icon { background: #eff6ff; color: #2563eb; }
.theme-primary { border-top: 3px solid #3b82f6; }

.theme-success .stat-icon { background: #ecfdf5; color: #059669; }
.theme-success { border-top: 3px solid #10b981; }

.theme-warning .stat-icon { background: #fffbeb; color: #d97706; }
.theme-warning { border-top: 3px solid #f59e0b; }

.theme-danger .stat-icon { background: #fef2f2; color: #dc2626; }
.theme-danger { border-top: 3px solid #ef4444; }

.theme-purple .stat-icon { background: #faf5ff; color: #7c3aed; }
.theme-purple { border-top: 3px solid #8b5cf6; }

.theme-teal .stat-icon { background: #f0fdfa; color: #0d9488; }
.theme-teal { border-top: 3px solid #14b8a6; }

.theme-indigo .stat-icon { background: #eef2ff; color: #4f46e5; }
.theme-indigo { border-top: 3px solid #6366f1; }

/* Financial Ribbon (Executive Profitability Ribbon) */
.financial-ribbon {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.15rem 1.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.financial-ribbon-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.9rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.ribbon-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ribbon-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #fef3c7;
    color: #d97706;
    font-size: 0.9rem;
}

.ribbon-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
}

.ribbon-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
}

.ribbon-status-badge.is-profit {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.ribbon-status-badge.is-loss {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.financial-cards-row {
    row-gap: 0.75rem;
}

.financial-card {
    background: #f8fafc;
    border: 1px solid #edf2f7;
    border-radius: 0.75rem;
    padding: 0.75rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.financial-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.financial-label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}

.financial-icon-badge {
    color: #94a3b8;
    font-size: 0.85rem;
}

.financial-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.financial-margin-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.financial-margin-gauge {
    height: 5px;
    border-radius: 3px;
    background: #e2e8f0;
    overflow: hidden;
}

.financial-margin-gauge-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.financial-margin-gauge-fill.is-good { background: #10b981; }
.financial-margin-gauge-fill.is-modest { background: #f59e0b; }
.financial-margin-gauge-fill.is-negative { background: #ef4444; }

.bg-success { background: #10b981; }
.bg-warning { background: #f59e0b; }
.bg-danger { background: #ef4444; }
.text-success { color: #10b981; }
.text-danger { color: #ef4444; }

.profit-positive { color: #16a34a; }
.profit-negative { color: #dc2626; }

/* Chart Card */
.chart-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.chart-header-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1rem;
    color: #1e293b;
}

.chart-header-icon {
    color: #2563eb;
}

.chart-canvas {
    height: 330px;
}

.chart-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    height: 180px;
    color: #94a3b8;
    text-align: center;
}

.chart-empty-icon {
    font-size: 2rem;
    color: #cbd5e1;
}

.chart-empty-note {
    margin: 0;
    max-width: 44ch;
    line-height: 1.6;
    font-size: 0.9rem;
    color: #64748b;
}

.chart-empty-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
}

.chart-skeleton {
    height: 330px;
    padding: 0.5rem 0;
}

/* Dimension Panels */
.dimension-panels {
    row-gap: 1.25rem;
}

.dim-name-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dim-icon {
    font-size: 0.85rem;
    color: #94a3b8;
    width: 16px;
    text-align: center;
}

.dim-name-text {
    font-weight: 600;
    color: #1e293b;
}

.dim-active-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #2563eb;
    margin-inline-start: auto;
}

.dim-count-badge {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    background: #f1f5f9;
    color: #475569;
    border-radius: 12px;
    font-size: 0.78rem;
    font-weight: 600;
}

.dim-value-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.dim-value-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
}

.dim-money-text {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.85rem;
}

.dim-share-text {
    font-size: 0.74rem;
    color: #64748b;
    font-weight: 600;
}

.dim-share-track {
    height: 4px;
    border-radius: 2px;
    background: #f1f5f9;
    overflow: hidden;
}

.dim-share-fill {
    height: 100%;
    border-radius: 2px;
    background: #3b82f6;
    transition: width 0.3s ease;
}

.dim-due-text {
    font-weight: 700;
    font-size: 0.85rem;
}

.dimension-panels :deep(.dimension-row) {
    cursor: pointer;
    transition: background 0.15s ease;
}

.dimension-panels :deep(.dimension-row:hover) td {
    background: #f0f9ff;
}

.dimension-panels :deep(.dimension-row.is-active) td {
    background: #e0f2fe;
    font-weight: 700;
}

.dimension-panels :deep(.dimension-row-inert) {
    cursor: default;
    color: #94a3b8;
}

/* Product Profitability Card */
.profitability-table-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
}

.profit-summary-grid {
    row-gap: 1rem;
    margin-bottom: 1.25rem;
}

.profit-highlight-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.profit-highlight-card--top {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.profit-card-label {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}

.profit-card-title {
    font-size: 0.95rem;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profit-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    font-weight: 700;
    width: fit-content;
}

.profit-card-badge.is-positive { color: #16a34a; }
.profit-card-badge.is-negative { color: #dc2626; }

.profit-card-big-value {
    font-size: 1.25rem;
    font-weight: 700;
}

.table-margin-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.table-margin-bar {
    height: 4px;
    border-radius: 2px;
    background: #e2e8f0;
    overflow: hidden;
}

.table-margin-bar span {
    display: block;
    height: 100%;
    border-radius: 2px;
}

.sales-report-panel :deep(.el-table) {
    font-variant-numeric: tabular-nums;
}

.table-empty {
    color: #94a3b8;
    font-size: 0.85rem;
}

.table-pagination {
    margin-top: 1rem;
    justify-content: center;
}

.is-refreshing {
    opacity: 0.55;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
</style>
