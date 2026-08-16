<template>
  <div ref="pageRef" class="financial-analytics-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-coins"
      :title="$t('analytics.financial_analytics')"
      :subtitle="$t('analytics.financial')"
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
        :label="$t('analytics.revenue')"
        :value="formatMoney(summary.revenue)"
        :icon="Money"
        color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.gross_profit')"
        :value="formatMoney(summary.gross_profit)"
        :icon="TrendCharts"
        color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
        :loading="loading"
      />
      <!-- Expenses rising is not good news, so the delta colouring inverts. -->
      <KpiCard
        :label="$t('analytics.operating_expenses')"
        :value="formatMoney(summary.operating_expenses)"
        :icon="Wallet"
        color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
        :rise-is-good="false"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.operating_profit')"
        :value="formatMoney(summary.operating_profit)"
        :caption="formatPercent(summary.operating_margin)"
        :icon="Coin"
        color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
        :loading="loading"
      />
    </AdminStatGrid>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :lg="14">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.revenue_by_category') }}</span>
          </template>
          <div ref="revenueChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :xs="24" :lg="10">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.expense_breakdown') }}</span>
          </template>
          <div ref="expenseChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.cash_flow') }}</span>
          </template>
          <div class="metric-list">
            <div class="metric-row">
              <span>{{ $t('analytics.inflows') }}</span>
              <strong class="is-positive">{{ formatMoney(cashFlow.cash_inflows) }}</strong>
            </div>
            <div class="metric-row">
              <span>{{ $t('analytics.outflows') }}</span>
              <strong class="is-negative">{{ formatMoney(cashFlow.cash_outflows) }}</strong>
            </div>
            <div class="metric-row metric-row--total">
              <span>{{ $t('analytics.net_cash_flow') }}</span>
              <strong :class="Number(cashFlow.net_cash_flow) >= 0 ? 'is-positive' : 'is-negative'">
                {{ formatMoney(cashFlow.net_cash_flow) }}
              </strong>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.financial_ratios') }}</span>
          </template>
          <div class="metric-list">
            <div v-for="row in ratioRows" :key="row.label" class="metric-row">
              <span>{{ row.label }}</span>
              <strong>{{ row.value }}</strong>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mt-4">
      <template #header>
        <span class="card-title">{{ $t('analytics.accounts_aging') }}</span>
      </template>
      <div ref="agingChartRef" class="chart-box"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Money, TrendCharts, Wallet, Coin } from '@element-plus/icons-vue';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { useEcharts, CHART_COLORS, donutOption, emptyChartOption } from '@/Composables/useEcharts';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';

/**
 * Financial analytics, from `/analytics/financial/*`.
 *
 * Revenue by category was reporting zero for every category regardless of
 * sales: it summed a `total_price` column that does not exist on
 * `sales_order_items` (the column is `total`), and summing an absent field
 * returns 0 without raising anything.
 */

const { t } = useI18n();
const { baseCode, formatMoney, formatNumber } = useCurrency();

const pageRef = ref(null);
const summary = ref({});
const revenueByCategory = ref([]);
const expenses = ref([]);
const cashFlow = ref({});
const ratios = ref({});
const aging = ref({});

const revenueChartRef = ref(null);
const expenseChartRef = ref(null);
const agingChartRef = ref(null);

const { register, renderAll, observe } = useEcharts();

const {
  loading, refreshing, exporting, error, range, rangePresets,
  lastUpdatedLabel, fetchAll, refresh, applyRange, exportCsv,
} = useAnalyticsPanel({
  exportDomain: 'financial',
  load: async (query) => {
    const [summaryRes, revenueRes, expensesRes, cashRes, ratiosRes, agingRes] = await Promise.all([
      analyticsApi.financialSummary(query),
      analyticsApi.revenueByCategory(query),
      analyticsApi.expenseBreakdown(query),
      analyticsApi.cashFlow(query),
      analyticsApi.financialRatios(query),
      analyticsApi.accountsAging(query),
    ]);

    summary.value = summaryRes.data ?? {};
    revenueByCategory.value = revenueRes.data ?? [];
    expenses.value = expensesRes.data ?? [];
    cashFlow.value = cashRes.data ?? {};
    ratios.value = ratiosRes.data ?? {};
    aging.value = agingRes.data ?? {};

    await renderAll();
  },
});

const formatPercent = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  return `${formatNumber(Math.round(number * 10) / 10)}%`;
};

const formatRatio = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  return formatNumber(Math.round(number * 100) / 100);
};

const ratioRows = computed(() => [
  { label: t('analytics.current_ratio'), value: formatRatio(ratios.value.current_ratio) },
  { label: t('analytics.quick_ratio'), value: formatRatio(ratios.value.quick_ratio) },
  { label: t('analytics.gross_profit_margin'), value: formatPercent(ratios.value.gross_profit_margin) },
  { label: t('analytics.operating_margin'), value: formatPercent(ratios.value.operating_margin) },
]);

register('revenue', revenueChartRef, () => {
  if (!revenueByCategory.value.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return {
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => formatMoney(v) },
    grid: { left: 10, right: 20, top: 20, bottom: 10, containLabel: true },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: revenueByCategory.value.map((r) => r.category ?? t('undefined')) },
    series: [{
      type: 'bar',
      data: revenueByCategory.value.map((r) => Number(r.revenue) || 0),
      itemStyle: { color: CHART_COLORS.primary, borderRadius: [0, 4, 4, 0] },
      barMaxWidth: 26,
    }],
  };
});

register('expenses', expenseChartRef, () => {
  if (!expenses.value.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return donutOption(
    expenses.value.map((row) => ({
      name: row.category ?? row.name ?? t('undefined'),
      value: Number(row.amount ?? row.total ?? 0),
    })),
    { formatter: (value) => formatMoney(value) }
  );
});

register('aging', agingChartRef, () => {
  const buckets = [
    { key: 'current', label: t('analytics.aging_current'), color: CHART_COLORS.success },
    { key: '1_30_days', label: t('analytics.aging_1_30'), color: CHART_COLORS.info },
    { key: '31_60_days', label: t('analytics.aging_31_60'), color: CHART_COLORS.warning },
    { key: '61_90_days', label: t('analytics.aging_61_90'), color: '#fa8c16' },
    { key: 'over_90_days', label: t('analytics.aging_over_90'), color: CHART_COLORS.danger },
  ];

  if (!buckets.some((b) => Number(aging.value[b.key]) > 0)) {
    return emptyChartOption(t('analytics.no_data_for_period'));
  }

  return {
    tooltip: { trigger: 'axis', valueFormatter: (v) => formatMoney(v) },
    grid: { left: 10, right: 20, top: 20, bottom: 10, containLabel: true },
    xAxis: { type: 'category', data: buckets.map((b) => b.label) },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar',
      data: buckets.map((b) => ({
        value: Number(aging.value[b.key]) || 0,
        itemStyle: { color: b.color, borderRadius: [4, 4, 0, 0] },
      })),
      barMaxWidth: 60,
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

.metric-row--total {
  background: #eef4ff;
  border: 1px solid #dbe6fb;
}

.metric-row span {
  color: #64748b;
  font-size: 0.9rem;
}

.metric-row strong {
  color: #1f2d3d;
}

.is-positive { color: #16a34a; }
.is-negative { color: #dc2626; }

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
