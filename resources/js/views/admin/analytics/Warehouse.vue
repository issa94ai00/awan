<template>
  <div ref="pageRef" class="warehouse-analytics-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-warehouse"
      :title="$t('analytics.warehouse_analytics')"
      :subtitle="$t('analytics.warehouse')"
    />

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
    >
      <div class="filter-field">
        <label>{{ $t('warehouse') }}</label>
        <el-select v-model="warehouseId" clearable :placeholder="$t('all_warehouses')" @change="applyRange">
          <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
        </el-select>
      </div>
    </AnalyticsToolbar>

    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-4">
      <template #default>
        <el-button size="small" type="danger" plain class="mt-1" @click="fetchAll()">
          {{ $t('analytics.retry') }}
        </el-button>
      </template>
    </el-alert>

    <AdminStatGrid :min="230">
      <KpiCard
        :label="$t('analytics.picking_completion')"
        :value="formatPercent(performance.picking?.completion_rate)"
        :icon="Checked"
        color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.packing_completion')"
        :value="formatPercent(performance.packing?.completion_rate)"
        :icon="Box"
        color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.capacity_utilization')"
        :value="formatPercent(capacity.utilization_percentage)"
        :icon="Management"
        color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.cycle_count_accuracy')"
        :value="formatPercent(accuracy.accuracy_rate)"
        :icon="CircleCheck"
        color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
        :loading="loading"
      />
    </AdminStatGrid>

    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.bin_utilization') }}</span>
          </template>
          <div ref="binChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header>
            <span class="card-title">{{ $t('analytics.by_zone') }}</span>
          </template>
          <div ref="zoneChartRef" class="chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mt-4">
      <template #header>
        <span class="card-title">{{ $t('analytics.picker_performance') }}</span>
      </template>

      <el-table
        :data="pickers"
        v-loading="loading"
        stripe
        :empty-text="$t('analytics.no_data_for_period')"
      >
        <el-table-column type="index" width="55" align="center" />
        <el-table-column prop="picker_name" :label="$t('analytics.picker')" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ row.picker_name || row.name || '—' }}</template>
        </el-table-column>
        <el-table-column :label="$t('analytics.lists_completed')" width="170" align="center">
          <template #default="{ row }">{{ formatNumber(row.completed_lists ?? row.lists) }}</template>
        </el-table-column>
        <el-table-column :label="$t('analytics.items_picked')" width="170" align="center">
          <template #default="{ row }">{{ formatNumber(row.items_picked ?? row.total_items) }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Box, Checked, Management, CircleCheck } from '@element-plus/icons-vue';
import api from '@/api';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { useEcharts, CHART_COLORS, donutOption, emptyChartOption } from '@/Composables/useEcharts';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';

/**
 * Warehouse analytics, from `/analytics/warehouse/*`.
 *
 * The warehouse picker used to be a single hardcoded entry — `{ id: 1, name:
 * 'Main Warehouse' }` — so filtering was impossible on any install with more
 * than one site. It reads the real list now.
 */

const { t } = useI18n();
const { formatNumber } = useCurrency();

const pageRef = ref(null);
const warehouses = ref([]);
const warehouseId = ref(null);
const performance = ref({});
const capacity = ref({});
const accuracy = ref({});
const bins = ref({});
const pickers = ref([]);

const binChartRef = ref(null);
const zoneChartRef = ref(null);

const { register, renderAll, observe } = useEcharts();

const {
  loading, refreshing, exporting, error, range, rangePresets,
  lastUpdatedLabel, fetchAll, refresh, applyRange, exportCsv,
} = useAnalyticsPanel({
  exportDomain: 'warehouse',
  load: async (query) => {
    const scoped = { ...query, warehouse_id: warehouseId.value || undefined };

    const [perfRes, capacityRes, accuracyRes, binsRes, pickersRes] = await Promise.all([
      analyticsApi.warehousePerformance(scoped),
      analyticsApi.capacityPlanning(scoped),
      analyticsApi.cycleCountAccuracy(scoped),
      analyticsApi.binUtilization(scoped),
      analyticsApi.pickerPerformance(scoped),
    ]);

    performance.value = perfRes.data ?? {};
    capacity.value = capacityRes.data ?? {};
    accuracy.value = accuracyRes.data ?? {};
    bins.value = binsRes.data ?? {};
    pickers.value = pickersRes.data ?? [];

    await renderAll();
  },
});

const formatPercent = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  return `${formatNumber(Math.round(number * 10) / 10)}%`;
};

const loadWarehouses = async () => {
  try {
    const { data } = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
    warehouses.value = data?.data ?? data ?? [];
  } catch (err) {
    // A missing filter list must not take the whole screen down with it.
    console.error('Failed to load warehouses:', err);
    warehouses.value = [];
  }
};

register('bins', binChartRef, () => {
  const items = [
    { name: t('analytics.full_bins'), value: Number(bins.value.full_bins) || 0, color: CHART_COLORS.danger },
    { name: t('analytics.empty_bins'), value: Number(bins.value.empty_bins) || 0, color: CHART_COLORS.success },
  ];
  const used = (Number(bins.value.total_bins) || 0) - items[0].value - items[1].value;
  if (used > 0) {
    items.splice(1, 0, { name: t('analytics.partially_filled'), value: used, color: CHART_COLORS.warning });
  }

  if (!items.some((i) => i.value > 0)) return emptyChartOption(t('analytics.no_data_for_period'));

  return donutOption(items);
});

register('zone', zoneChartRef, () => {
  const rows = bins.value.by_zone ?? [];
  if (!rows.length) return emptyChartOption(t('analytics.no_data_for_period'));

  return {
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (v) => `${v}%` },
    grid: { left: 10, right: 20, top: 20, bottom: 10, containLabel: true },
    xAxis: { type: 'value', max: 100 },
    yAxis: { type: 'category', data: rows.map((r) => r.zone ?? r.name ?? t('undefined')) },
    series: [{
      type: 'bar',
      data: rows.map((r) => Number(r.utilization ?? r.avg_utilization) || 0),
      itemStyle: { color: CHART_COLORS.info, borderRadius: [0, 4, 4, 0] },
      barMaxWidth: 26,
    }],
  };
});

onMounted(async () => {
  await loadWarehouses();
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
