<template>
  <div ref="pageRef" class="inventory-analytics-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-boxes-stacked"
      :title="$t('analytics.inventory_analytics')"
      :subtitle="$t('analytics.inventory')"
    >
      <template #actions>
        <el-tag size="small" type="info" effect="plain">
          {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
        </el-tag>
      </template>
    </AdminPageHeader>

    <AnalyticsToolbar
      v-model="range"
      :presets="rangePresets"
      :refreshing="refreshing"
      :exporting="exporting"
      can-export
      :last-updated-label="lastUpdatedLabel"
      @apply="applyRange"
      @refresh="refresh"
      @export="exportCsv"
    />

    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-4">
      <template #default>
        <el-button size="small" type="danger" plain class="mt-1" @click="fetchAll()">
          {{ $t('analytics.retry') }}
        </el-button>
      </template>
    </el-alert>

    <AdminStatGrid :min="230">
      <KpiCard
        :label="$t('analytics.total_products')"
        :value="formatNumber(summary.total_products)"
        :icon="Box"
        color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.inventory_value')"
        :value="formatMoney(summary.total_value)"
        :icon="Money"
        color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.low_stock')"
        :value="formatNumber(summary.low_stock_items)"
        :icon="Warning"
        color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
        to="/admin/inventory"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.health_score')"
        :value="formatPercent(health.health_score)"
        :icon="CircleCheck"
        color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
        :loading="loading"
      />
    </AdminStatGrid>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.abc_analysis') }}</span>
          </template>
          <div ref="abcChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.inventory_value_by_category') }}</span>
          </template>
          <div ref="valuationChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.inventory_turnover') }}</span>
          </template>
          <div class="metric-list">
            <div v-for="item in turnoverRows" :key="item.label" class="metric-row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.stockout_analysis') }}</span>
          </template>
          <div class="metric-list">
            <div v-for="item in stockoutRows" :key="item.label" class="metric-row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mt-4">
      <template #header>
        <span class="card-title">{{ $t('analytics.slow_moving_items') }}</span>
      </template>

      <el-table
        :data="slowMoving"
        v-loading="loading"
        stripe
        :empty-text="$t('analytics.no_data_for_period')"
      >
        <el-table-column prop="product_name" :label="$t('analytics.product')" min-width="220" show-overflow-tooltip />
        <el-table-column :label="$t('current_stock')" width="150" align="center">
          <template #default="{ row }">{{ formatNumber(row.stock_quantity) }}</template>
        </el-table-column>
        <el-table-column :label="$t('analytics.days_since_last_sale')" width="180" align="center">
          <template #default="{ row }">
            <el-tag :type="row.days_since_last_sale > 90 ? 'danger' : 'warning'" size="small">
              {{ formatNumber(row.days_since_last_sale) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('analytics.value')" width="180" align="center">
          <template #default="{ row }">{{ formatMoney(row.value ?? row.stock_value) }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Box, Money, Warning, CircleCheck } from '@element-plus/icons-vue';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { useEcharts, CHART_COLORS, donutOption, emptyChartOption } from '@/Composables/useEcharts';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';

/**
 * Inventory analytics, from `/analytics/inventory/*`.
 *
 * The export button here previously showed "export started" and issued no
 * request at all; it now downloads the ABC breakdown as CSV.
 */

const { t } = useI18n();
const { baseCode, formatMoney, formatNumber } = useCurrency();

const pageRef = ref(null);
const summary = ref({});
const health = ref({});
const abc = ref({});
const valuation = ref({});
const turnover = ref({});
const stockout = ref({});
const slowMoving = ref([]);

const abcChartRef = ref(null);
const valuationChartRef = ref(null);

const { register, renderAll, observe } = useEcharts();

const {
  loading, refreshing, exporting, error, range, rangePresets,
  lastUpdatedLabel, fetchAll, refresh, applyRange, exportCsv,
} = useAnalyticsPanel({
  exportDomain: 'inventory',
  load: async (query) => {
    const [summaryRes, healthRes, abcRes, valuationRes, turnoverRes, stockoutRes, slowRes] = await Promise.all([
      analyticsApi.inventorySummary(),
      analyticsApi.inventoryHealthScore(),
      analyticsApi.abcAnalysis(),
      analyticsApi.inventoryValuation(),
      analyticsApi.inventoryTurnover(query),
      analyticsApi.stockoutAnalysis(query),
      analyticsApi.slowMovingInventory(query),
    ]);

    summary.value = summaryRes.data ?? {};
    health.value = healthRes.data ?? {};
    abc.value = abcRes.data ?? {};
    valuation.value = valuationRes.data ?? {};
    turnover.value = turnoverRes.data ?? {};
    stockout.value = stockoutRes.data ?? {};
    slowMoving.value = slowRes.data ?? [];

    await renderAll();
  },
});

const formatPercent = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  return `${formatNumber(Math.round(number * 10) / 10)}%`;
};

const turnoverRows = computed(() => [
  { label: t('analytics.turnover_rate'), value: formatNumber(turnover.value.turnover_rate) },
  { label: t('analytics.annualized_turnover'), value: formatNumber(turnover.value.annualized_turnover) },
  { label: t('analytics.total_sold'), value: formatNumber(turnover.value.total_sold) },
  { label: t('analytics.average_inventory'), value: formatNumber(turnover.value.average_inventory) },
]);

const stockoutRows = computed(() => [
  { label: t('analytics.total_alerts'), value: formatNumber(stockout.value.total_alerts) },
  { label: t('analytics.pending'), value: formatNumber(stockout.value.pending) },
  { label: t('analytics.resolved'), value: formatNumber(stockout.value.resolved) },
  { label: t('analytics.resolution_rate'), value: formatPercent(stockout.value.resolution_rate) },
]);

register('abc', abcChartRef, () => {
  const items = [
    { name: t('analytics.class_a'), value: Number(abc.value.a_items) || 0, color: CHART_COLORS.success },
    { name: t('analytics.class_b'), value: Number(abc.value.b_items) || 0, color: CHART_COLORS.warning },
    { name: t('analytics.class_c'), value: Number(abc.value.c_items) || 0, color: CHART_COLORS.danger },
  ];

  if (!items.some((i) => i.value > 0)) return emptyChartOption(t('analytics.no_data_for_period'));

  return donutOption(items);
});

register('valuation', valuationChartRef, () => {
  const rows = valuation.value.by_category ?? [];
  if (!rows.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return {
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => formatMoney(v) },
    grid: { left: 10, right: 20, top: 20, bottom: 10, containLabel: true },
    xAxis: { type: 'value' },
    yAxis: {
      type: 'category',
      data: rows.map((r) => r.category ?? t('undefined')),
    },
    series: [{
      type: 'bar',
      data: rows.map((r) => Number(r.total_value ?? r.value) || 0),
      itemStyle: { color: CHART_COLORS.primary, borderRadius: [0, 4, 4, 0] },
      barMaxWidth: 26,
    }],
  };
});

onMounted(async () => {
  await fetchAll();
  observe(pageRef.value);
});
</script>

<style scoped>
.chart-box {
  width: 100%;
  height: 320px;
}

.card-title {
  font-weight: 700;
  color: #1f2d3d;
}

.metric-list {
  display: grid;
  gap: 0.65rem;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  background: #f8fafc;
}

.metric-row span {
  color: #64748b;
  font-size: 0.9rem;
}

.metric-row strong {
  color: #1f2d3d;
}

.mt-4 {
  margin-top: 1.5rem;
}

.mt-1 {
  margin-top: 0.5rem;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
  .chart-box {
    height: 260px;
  }
}
</style>
