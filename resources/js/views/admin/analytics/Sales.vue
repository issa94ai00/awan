<template>
  <div ref="pageRef" class="sales-analytics-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-chart-line"
      :title="$t('analytics.sales_analytics')"
      :subtitle="$t('analytics.sales')"
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
        :label="$t('analytics.total_revenue')"
        :value="formatMoney(summary.total_revenue)"
        :icon="Money"
        color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.total_orders')"
        :value="formatNumber(summary.total_orders)"
        :icon="ShoppingCart"
        color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.average_order_value')"
        :value="formatMoney(summary.average_order_value)"
        :icon="Wallet"
        color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.completed_orders')"
        :value="formatNumber(summary.completed_orders)"
        :icon="CircleCheck"
        color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
        :loading="loading"
      />
    </AdminStatGrid>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :lg="16">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.sales_trends') }}</span>
          </template>
          <div ref="trendChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :xs="24" :lg="8">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.sales_by_channel') }}</span>
          </template>
          <div ref="channelChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mt-4">
      <template #header>
        <div class="card-header">
          <span class="card-title">{{ $t('analytics.top_products') }}</span>
          <el-tag v-if="topProducts.length" size="small" effect="plain">{{ formatNumber(topProducts.length) }}</el-tag>
        </div>
      </template>

      <el-table
        :data="topProducts"
        v-loading="loading"
        stripe
        :empty-text="$t('analytics.no_data_for_period')"
      >
        <el-table-column type="index" width="55" align="center" />
        <el-table-column prop="product_name" :label="$t('analytics.product')" min-width="220" show-overflow-tooltip />
        <el-table-column :label="$t('analytics.quantity_sold')" width="140" align="center">
          <template #default="{ row }">{{ formatNumber(row.quantity) }}</template>
        </el-table-column>
        <el-table-column :label="$t('analytics.revenue')" width="180" align="center">
          <template #default="{ row }">
            <strong>{{ formatMoney(row.revenue) }}</strong>
          </template>
        </el-table-column>
        <!--
          The old table had a "growth" column filled from a mock field the API
          never returned; every row rendered an empty tag. Share of the period's
          revenue is something the data actually supports.
        -->
        <el-table-column :label="$t('analytics.revenue_share')" width="200">
          <template #default="{ row }">
            <el-progress
              :percentage="revenueShare(row.revenue)"
              :stroke-width="10"
              :color="CHART_COLORS.primary"
            />
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="never" class="mt-4">
      <template #header>
        <div class="card-header">
          <span class="card-title">{{ $t('analytics.sales_forecast') }}</span>
          <el-select v-model="forecastDays" size="small" style="width: 140px" @change="loadForecast">
            <el-option :value="7" :label="$t('analytics.7_days')" />
            <el-option :value="30" :label="$t('analytics.30_days')" />
            <el-option :value="90" :label="$t('analytics.90_days')" />
          </el-select>
        </div>
      </template>
      <div ref="forecastChartRef" class="chart-box"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Money, ShoppingCart, Wallet, CircleCheck } from '@element-plus/icons-vue';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { useEcharts, CHART_COLORS, donutOption, emptyChartOption } from '@/Composables/useEcharts';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';

/**
 * Sales analytics.
 *
 * Previously drew four charts from arrays written into the file. Everything
 * here now comes from `/analytics/sales/*`, including the top-products table,
 * whose endpoint had never once returned a response — it queried a
 * `products.name` column that does not exist and answered 500.
 */

const { t } = useI18n();
const { baseCode, formatMoney, formatNumber } = useCurrency();

const pageRef = ref(null);
const summary = ref({});
const trend = ref([]);
const byChannel = ref([]);
const topProducts = ref([]);
const forecast = ref({});
const forecastDays = ref(30);

const trendChartRef = ref(null);
const channelChartRef = ref(null);
const forecastChartRef = ref(null);

const { register, renderAll, render, observe } = useEcharts();

const {
  loading, refreshing, exporting, error, range, rangePresets,
  lastUpdatedLabel, fetchAll, refresh, applyRange, exportCsv, params,
} = useAnalyticsPanel({
  exportDomain: 'sales',
  load: async (query) => {
    const [summaryRes, trendRes, channelRes, topRes, forecastRes] = await Promise.all([
      analyticsApi.salesSummary(query),
      analyticsApi.salesTrend({ ...query, days: 30 }),
      analyticsApi.salesByChannel(query),
      analyticsApi.topProducts({ ...query, limit: 10 }),
      analyticsApi.salesForecast({ forecast_days: forecastDays.value }),
    ]);

    summary.value = summaryRes.data ?? {};
    trend.value = trendRes.data ?? [];
    byChannel.value = channelRes.data ?? [];
    topProducts.value = topRes.data ?? [];
    forecast.value = forecastRes.data ?? {};

    await renderAll();
  },
});

/** How much of the period's revenue a single product accounts for. */
const totalTopRevenue = computed(
  () => topProducts.value.reduce((sum, row) => sum + (Number(row.revenue) || 0), 0)
);

const revenueShare = (revenue) => {
  if (!totalTopRevenue.value) return 0;
  return Math.round((Number(revenue || 0) / totalTopRevenue.value) * 100);
};

const loadForecast = async () => {
  try {
    const { data } = await analyticsApi.salesForecast({ forecast_days: forecastDays.value });
    forecast.value = data ?? {};
    await render('forecast');
  } catch (err) {
    console.error('Forecast load failed:', err);
  }
};

/* ------------------------------------------------------------------ *
 * Charts. Options are built inside factories so that a language switch
 * redraws them with the new labels rather than reapplying a snapshot.
 * ------------------------------------------------------------------ */

register('trend', trendChartRef, () => {
  if (!trend.value.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return {
    tooltip: { trigger: 'axis' },
    legend: { data: [t('analytics.revenue'), t('analytics.total_orders')], top: 0 },
    grid: { left: 10, right: 10, top: 40, bottom: 10, containLabel: true },
    xAxis: { type: 'category', data: trend.value.map((d) => d.date ?? d.period ?? '') },
    yAxis: [
      { type: 'value', name: t('analytics.revenue') },
      { type: 'value', name: t('analytics.total_orders'), splitLine: { show: false } },
    ],
    series: [
      {
        name: t('analytics.revenue'),
        type: 'line',
        smooth: true,
        data: trend.value.map((d) => Number(d.revenue) || 0),
        areaStyle: { color: 'rgba(102,126,234,0.15)' },
        lineStyle: { color: CHART_COLORS.primary, width: 3 },
        itemStyle: { color: CHART_COLORS.primary },
      },
      {
        name: t('analytics.total_orders'),
        type: 'bar',
        yAxisIndex: 1,
        data: trend.value.map((d) => Number(d.orders) || 0),
        barWidth: '40%',
        itemStyle: { color: 'rgba(103,194,58,0.55)', borderRadius: [4, 4, 0, 0] },
      },
    ],
  };
});

register('channel', channelChartRef, () => {
  if (!byChannel.value.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return donutOption(
    byChannel.value.map((row) => ({
      name: row.channel ?? row.name ?? t('undefined'),
      value: Number(row.revenue ?? row.total ?? 0),
    })),
    { formatter: (value) => formatMoney(value) }
  );
});

register('forecast', forecastChartRef, () => {
  const historical = forecast.value.historical ?? [];
  const predicted = forecast.value.forecast ?? forecast.value.predicted ?? [];

  if (!historical.length && !predicted.length) {
    return emptyChartOption(t('analytics.no_data_for_period'));
  }

  const labels = [
    ...historical.map((d) => d.date ?? ''),
    ...predicted.map((d) => d.date ?? ''),
  ];

  return {
    tooltip: { trigger: 'axis' },
    legend: { data: [t('analytics.historical'), t('analytics.forecast')], top: 0 },
    grid: { left: 10, right: 10, top: 40, bottom: 10, containLabel: true },
    xAxis: { type: 'category', data: labels },
    yAxis: { type: 'value' },
    series: [
      {
        name: t('analytics.historical'),
        type: 'line',
        smooth: true,
        data: historical.map((d) => Number(d.revenue) || 0),
        lineStyle: { color: CHART_COLORS.primary, width: 3 },
        itemStyle: { color: CHART_COLORS.primary },
      },
      {
        name: t('analytics.forecast'),
        type: 'line',
        smooth: true,
        // Offset so the forecast starts where history ends instead of
        // overlapping it from day zero.
        data: [...new Array(historical.length).fill('-'), ...predicted.map((d) => Number(d.revenue) || 0)],
        lineStyle: { color: CHART_COLORS.warning, width: 3, type: 'dashed' },
        itemStyle: { color: CHART_COLORS.warning },
      },
    ],
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

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
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
