<template>
    <div class="sales-report-panel">
        <AdminStatGrid v-if="loading">
            <el-card v-for="n in Math.max(statCards.length, 4)" :key="n" shadow="hover" class="stat-card skeleton-card">
                <el-skeleton :rows="2" animated />
            </el-card>
        </AdminStatGrid>

        <AdminStatGrid v-else>
            <el-card v-for="stat in statCards" :key="stat.key" shadow="hover" class="stat-card">
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

        <el-row v-if="!loading && metrics" :gutter="20" class="metrics-row">
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('sales_revenue') }}</span>
                        <strong>{{ formatMoney(metrics.total_revenue) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('cost_of_goods') }}</span>
                        <strong>{{ formatMoney(metrics.total_cost) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('gross_profit') }}</span>
                        <strong :class="Number(metrics.gross_profit) >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatMoney(metrics.gross_profit) }}
                        </strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('profit_margin') }}</span>
                        <strong>{{ formatPercentage(metrics.gross_margin) }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- One honest chart: a time trend when the grouping is chronological, a
             ranked bar when it's a category. Never both series (sales vs order
             count) on one axis — see the trend/count split below. -->
        <el-card shadow="hover" class="chart-card">
            <template #header>
                <div class="card-header">
                    <span>{{ chartTitle }}</span>
                </div>
            </template>

            <div v-if="chartMode === 'none'" class="chart-empty">
                {{ chartNote || $t('no_chart_for_this_grouping') }}
            </div>
            <div v-else ref="chartRef" class="chart-canvas"></div>
        </el-card>

        <el-row :gutter="20" class="dimension-panels">
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header><span>{{ $t('employees') }}</span></template>
                    <el-table :data="dimensionData?.employee_summary || []" stripe style="width: 100%">
                        <el-table-column prop="employee_name" :label="$t('employee')" />
                        <el-table-column :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatMoney(row[dimensionValueKey]) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header><span>{{ $t('customers') }}</span></template>
                    <el-table :data="dimensionData?.customer_summary || []" stripe style="width: 100%">
                        <el-table-column prop="customer_name" :label="$t('customer')" />
                        <el-table-column :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatMoney(row[dimensionValueKey]) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header><span>{{ $t('warehouses') }}</span></template>
                    <el-table :data="dimensionData?.warehouse_summary || []" stripe style="width: 100%">
                        <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                        <el-table-column :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatMoney(row[dimensionValueKey]) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <el-row v-if="profitability?.summary" :gutter="20" class="metrics-row">
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('most_profitable_product') }}</span>
                        <strong>{{ profitability.summary.top_product?.product_name || '-' }}</strong>
                        <small>{{ formatMoney(profitability.summary.top_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('least_profitable_product') }}</span>
                        <strong>{{ profitability.summary.lowest_product?.product_name || '-' }}</strong>
                        <small>{{ formatMoney(profitability.summary.lowest_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('total_profit') }}</span>
                        <strong>{{ formatMoney(profitability.summary.gross_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('items_count') }}</span>
                        <strong>{{ profitability.summary.product_count || 0 }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="profitability-table-card">
            <template #header>
                <span>{{ $t('product_profitability_by_warehouse') }}</span>
            </template>

            <el-table :data="profitability?.product_summary || []" stripe style="width: 100%">
                <el-table-column prop="product_name" :label="$t('product')" />
                <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                <el-table-column prop="quantity" :label="$t('quantity')" />
                <el-table-column :label="$t('revenue')">
                    <template #default="{ row }">{{ formatMoney(row.total_revenue) }}</template>
                </el-table-column>
                <el-table-column :label="$t('cost')">
                    <template #default="{ row }">{{ formatMoney(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column :label="$t('profit')">
                    <template #default="{ row }">
                        <strong :class="row.gross_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatMoney(row.gross_profit) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('margin')">
                    <template #default="{ row }">{{ formatPercentage(row.gross_margin) }}</template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import * as echarts from 'echarts';
import { formatMoney as formatMoneyWith, formatNumber } from '@/utils/currency';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

/**
 * The block every report tab (sales orders, invoices) repeats: a stat grid,
 * four profit mini-metrics, one chart, three dimension breakdowns and the
 * product-profitability table. It used to be copy-pasted whole between the
 * two tabs — ~350 lines apiece, drifting a little further apart every time
 * one tab got a fix the other didn't. This is that block, parameterised.
 *
 * The chart is deliberately singular: `chartMode` is 'trend' (a time-ordered
 * line — sales revenue only, never plotted against order count on the same
 * axis, which is how the old chart made a handful of orders and a few
 * thousand currency units share one scale and flatten the smaller series to
 * nothing) or 'bar' (a category ranked by magnitude — replacing a pie chart,
 * which the design system's own dataviz guidance rules out for part-to-whole
 * comparisons past a couple of slices), or 'none' when the current grouping
 * has no chart-worthy data for this tab.
 */
const props = defineProps({
    loading: { type: Boolean, default: false },
    statCards: { type: Array, default: () => [] },
    metrics: { type: Object, default: null },
    chartMode: { type: String, default: 'none' }, // 'trend' | 'bar' | 'none'
    chartTitle: { type: String, default: '' },
    chartNote: { type: String, default: '' },
    chartLabels: { type: Array, default: () => [] },
    chartValues: { type: Array, default: () => [] },
    dimensionData: { type: Object, default: null },
    dimensionValueKey: { type: String, default: 'total_sales' },
    profitability: { type: Object, default: null },
});

const chartRef = ref(null);
let chart = null;

// Sequential blue — the validated palette's default single hue for magnitude
// (both the trend line and the ranked bar are one metric across one series).
const SEQUENTIAL_HUE = '#2a78d6';
const SEQUENTIAL_FILL = 'rgba(42, 120, 214, 0.12)';
const GRID_LINE = '#e1e0d9';
const AXIS_LINE = '#c3c2b7';
const AXIS_LABEL = '#898781';

const trendOption = () => ({
    grid: { left: 8, right: 20, top: 24, bottom: 8, containLabel: true },
    tooltip: {
        trigger: 'axis',
        backgroundColor: '#fff',
        borderColor: '#e2e8f0',
        textStyle: { color: '#1f2937' },
        valueFormatter: (value) => formatMoneyWith(value),
    },
    xAxis: {
        type: 'category',
        data: props.chartLabels,
        axisLine: { lineStyle: { color: AXIS_LINE } },
        axisLabel: { color: AXIS_LABEL },
        axisTick: { show: false },
    },
    yAxis: {
        type: 'value',
        splitLine: { lineStyle: { color: GRID_LINE, type: 'solid' } },
        axisLabel: { color: AXIS_LABEL, formatter: (value) => formatNumber(value) },
    },
    series: [{
        name: t('sales_revenue'),
        type: 'line',
        data: props.chartValues,
        smooth: true,
        symbolSize: 6,
        lineStyle: { width: 2, color: SEQUENTIAL_HUE },
        itemStyle: { color: SEQUENTIAL_HUE },
        areaStyle: { color: SEQUENTIAL_FILL },
    }],
});

/** Ranked horizontal bar — the highest value nearest the axis start, capped
 *  to the top 10 so the row count never overtakes the readable case; the full
 *  breakdown is one scroll away in the dimension table beside it. */
const barOption = () => {
    const rows = props.chartLabels
        .map((label, index) => ({ label, value: Number(props.chartValues[index]) || 0 }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 10)
        .reverse();

    return {
        grid: { left: 8, right: 32, top: 8, bottom: 8, containLabel: true },
        tooltip: {
            trigger: 'item',
            backgroundColor: '#fff',
            borderColor: '#e2e8f0',
            textStyle: { color: '#1f2937' },
            valueFormatter: (value) => formatMoneyWith(value),
        },
        xAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: GRID_LINE, type: 'solid' } },
            axisLabel: { color: AXIS_LABEL, formatter: (value) => formatNumber(value) },
        },
        yAxis: {
            type: 'category',
            data: rows.map((row) => row.label),
            axisLine: { lineStyle: { color: AXIS_LINE } },
            axisLabel: { color: '#334155' },
            axisTick: { show: false },
        },
        series: [{
            type: 'bar',
            data: rows.map((row) => row.value),
            barMaxWidth: 22,
            itemStyle: { color: SEQUENTIAL_HUE, borderRadius: [0, 4, 4, 0] },
        }],
    };
};

const renderChart = async () => {
    if (props.chartMode === 'none') {
        if (chart) { chart.dispose(); chart = null; }
        return;
    }

    await nextTick();
    if (!chartRef.value) return;

    if (!chart) {
        chart = echarts.init(chartRef.value);
    }

    chart.setOption(props.chartMode === 'trend' ? trendOption() : barOption(), true);
};

const handleResize = () => chart?.resize();

watch(() => [props.chartMode, props.chartLabels, props.chartValues], renderChart, { deep: true });

onMounted(() => {
    renderChart();
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    chart?.dispose();
});

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

.stat-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
}

.skeleton-card {
    min-height: 116px;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(42, 120, 214, 0.10);
    color: #2a78d6;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.4rem;
    color: #1f2937;
}

.stat-info p {
    margin: 0.25rem 0 0;
    color: #6b7c98;
    font-size: 0.85rem;
}

.metrics-row {
    row-gap: 1rem;
}

.mini-metric {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.2rem 0;
}

.mini-metric span {
    font-size: 0.8rem;
    color: #64748b;
}

.mini-metric strong {
    font-size: 1.1rem;
    color: #0f172a;
}

.mini-metric small {
    color: #94a3b8;
    font-size: 0.75rem;
}

.profit-positive { color: #16a34a; }
.profit-negative { color: #dc2626; }

.chart-card,
.profitability-table-card {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.chart-canvas {
    height: 320px;
}

.chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 160px;
    color: #94a3b8;
    font-size: 0.9rem;
    text-align: center;
}

.dimension-panels {
    row-gap: 1rem;
}
</style>
