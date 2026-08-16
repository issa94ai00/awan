<template>
  <div ref="pageRef" class="analytics-dashboard">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-chart-line"
      :title="$t('analytics.title')"
      :subtitle="$t('analytics.description')"
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
      :last-updated-label="lastUpdatedLabel"
      @apply="applyRange"
      @refresh="refresh"
    />

    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-4">
      <template #default>
        <el-button size="small" type="danger" plain class="mt-1" @click="fetchAll()">
          {{ $t('analytics.retry') }}
        </el-button>
      </template>
    </el-alert>

    <!-- Headline figures, each measured against the equal-length window before it. -->
    <AdminStatGrid :min="240">
      <KpiCard
        :label="$t('analytics.total_revenue')"
        :value="formatMoney(overview.revenue?.current)"
        :comparison="overview.revenue"
        :icon="Money"
        color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
        to="/admin/analytics/financial"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.orders')"
        :value="formatNumber(overview.orders?.current)"
        :comparison="overview.orders"
        :icon="ShoppingCart"
        color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
        to="/admin/analytics/sales"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.gross_margin')"
        :value="formatPercent(overview.gross_margin?.current)"
        :comparison="overview.gross_margin"
        :icon="TrendCharts"
        color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
        to="/admin/analytics/financial"
        :loading="loading"
      />
      <!--
        Stock and capacity are readings of right now, not of the selected range,
        so they carry a caption instead of a period comparison the query cannot
        honestly support.
      -->
      <KpiCard
        :label="$t('analytics.total_products')"
        :value="formatNumber(overview.inventory?.total_products)"
        :caption="`${formatNumber(overview.inventory?.low_stock_items)} ${$t('analytics.low_stock')}`"
        :icon="Box"
        color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
        to="/admin/analytics/inventory"
        :loading="loading"
      />
      <KpiCard
        :label="$t('analytics.warehouse_utilization')"
        :value="formatPercent(overview.warehouse?.utilization_percentage)"
        :icon="Management"
        color="linear-gradient(135deg, #fa709a 0%, #fee140 100%)"
        to="/admin/analytics/warehouse"
        :loading="loading"
      />
    </AdminStatGrid>

    <!-- Quick Links -->
    <el-card class="quick-links-card" shadow="never">
      <template #header>
        <span class="card-title">{{ $t('analytics.quick_links') }}</span>
      </template>
      <div class="quick-links">
        <button
          v-for="link in quickLinks"
          :key="link.to"
          type="button"
          class="quick-link"
          @click="$router.push(link.to)"
        >
          <el-icon :size="26"><component :is="link.icon" /></el-icon>
          <span>{{ link.label }}</span>
        </button>
      </div>
    </el-card>

    <!-- Recent Reports -->
    <el-card shadow="never" class="mt-4">
      <template #header>
        <div class="card-header">
          <span class="card-title">{{ $t('analytics.recent_reports') }}</span>
          <el-button text type="primary" @click="$router.push('/admin/analytics/reports')">
            {{ $t('view_all') }}
          </el-button>
        </div>
      </template>

      <el-table
        :data="recentReports"
        v-loading="loading"
        :empty-text="$t('analytics.no_data_for_period')"
      >
        <el-table-column prop="name" :label="$t('analytics.report_name')" min-width="200" />
        <el-table-column prop="type" :label="$t('analytics.type')" width="160">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('date')" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column :label="$t('actions')" width="110" align="center">
          <template #default="{ row }">
            <el-button size="small" text type="primary" @click="openReport(row)">
              <el-icon><View /></el-icon>
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import {
  TrendCharts, ShoppingCart, Box, Management, Money, Document, DataBoard, View, Odometer,
} from '@element-plus/icons-vue';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { formatDate } from '@/utils/sales';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';

/**
 * The BI landing screen.
 *
 * Everything here was previously invented: revenue was the literal 150,000,
 * the product count 500, and the trend chips read `+12.5%` on every install
 * forever. `/analytics/overview` now answers the whole card row in one request,
 * with each figure measured against the equal-length period before it.
 */

const { t } = useI18n();
const router = useRouter();
const { baseCode, formatMoney, formatNumber } = useCurrency();

const pageRef = ref(null);
const overview = ref({});
const recentReports = ref([]);

const {
  loading, refreshing, error, range, rangePresets,
  lastUpdatedLabel, fetchAll, refresh, applyRange,
} = useAnalyticsPanel({
  defaultDays: 30,
  load: async (params) => {
    // Both are needed for the screen, so they are awaited together rather than
    // in series — and a failure in either surfaces as the screen's error state
    // instead of being swallowed the way it used to be.
    const [overviewResponse, reportsResponse] = await Promise.all([
      analyticsApi.overview(params),
      analyticsApi.reports({ per_page: 5 }),
    ]);

    overview.value = overviewResponse.data ?? {};
    // The reports endpoint paginates; the rows live under `data`.
    recentReports.value = reportsResponse.data?.data ?? reportsResponse.data ?? [];
  },
});

/** Percentages come off the API as plain numbers, not pre-formatted strings. */
const formatPercent = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';

  return `${formatNumber(Math.round(number * 10) / 10)}%`;
};

const quickLinks = computed(() => [
  { to: '/admin/analytics/sales', icon: TrendCharts, label: t('analytics.sales') },
  { to: '/admin/analytics/inventory', icon: Box, label: t('analytics.inventory') },
  { to: '/admin/analytics/warehouse', icon: Management, label: t('analytics.warehouse') },
  { to: '/admin/analytics/financial', icon: Money, label: t('analytics.financial') },
  { to: '/admin/analytics/metrics', icon: Odometer, label: t('analytics.metrics') },
  { to: '/admin/analytics/reports', icon: Document, label: t('analytics.reports') },
  { to: '/admin/analytics/dashboards', icon: DataBoard, label: t('analytics.dashboards') },
]);

/**
 * Was `$router.push(...)` inside `<script setup>`, where `$router` is not
 * defined — every click on the view button threw a ReferenceError.
 */
const openReport = (report) => {
  router.push(`/admin/analytics/reports?report=${report.id}`);
};

onMounted(fetchAll);
</script>

<style scoped>
.analytics-dashboard {
  padding: 0;
}

.card-title {
  font-weight: 700;
  color: #1f2d3d;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.quick-links-card {
  margin-top: 1.5rem;
  border-radius: 14px;
}

.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

/* A real <button>: these were divs, so they could not be tabbed to or
   activated from the keyboard. */
.quick-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  padding: 1.25rem 0.75rem;
  background: #f5f7fa;
  border: 1px solid transparent;
  border-radius: 12px;
  cursor: pointer;
  color: #334155;
  font: inherit;
  font-size: 0.85rem;
  text-align: center;
  transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.quick-link:hover {
  background: #eef4ff;
  border-color: #c7d7fb;
  transform: translateY(-3px);
}

.quick-link:focus-visible {
  outline: 2px solid #667eea;
  outline-offset: 2px;
}

.quick-link .el-icon {
  color: #667eea;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

.mt-1 {
  margin-top: 0.5rem;
}

.mt-4 {
  margin-top: 1.5rem;
}
</style>
