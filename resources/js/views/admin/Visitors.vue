<template>
    <div ref="pageRef" class="visitors-analytics-page">
        <AdminPageHeader
            badge="BI"
            icon="fas fa-users"
            :title="$t('analytics.visitors_analytics')"
            :subtitle="$t('visitors')"
        />

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

        <AdminStatGrid :min="220">
            <KpiCard
                :label="$t('analytics.total_visits')"
                :value="formatNumber(summary.total_visits?.current)"
                :comparison="summary.total_visits"
                :icon="View"
                color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
                :loading="loading"
            />
            <KpiCard
                :label="$t('analytics.unique_visitors')"
                :value="formatNumber(summary.unique_visitors?.current)"
                :comparison="summary.unique_visitors"
                :icon="UserFilled"
                color="linear-gradient(135deg, #11998e 0%, #38ef7d 100%)"
                :loading="loading"
            />
            <KpiCard
                :label="$t('analytics.bot_traffic')"
                :value="formatPercent(summary.bot_share?.current)"
                :comparison="summary.bot_share"
                :rise-is-good="false"
                :icon="Warning"
                color="linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%)"
                :loading="loading"
            />
            <KpiCard
                :label="$t('analytics.avg_daily_visits')"
                :value="formatNumber(summary.avg_daily_visits)"
                :icon="Odometer"
                color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
                :loading="loading"
            />
        </AdminStatGrid>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="never">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.visits_trend') }}</span>
                    </template>
                    <div ref="trendChartRef" class="chart-box"></div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="never">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.by_device') }}</span>
                    </template>
                    <div ref="deviceChartRef" class="chart-box"></div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :md="12">
                <el-card shadow="never" class="full-height">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.by_browser') }}</span>
                    </template>
                    <BarList :items="browserBars" empty-icon="fab fa-chrome" />
                </el-card>
            </el-col>

            <el-col :xs="24" :md="12">
                <el-card shadow="never" class="full-height">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.by_os') }}</span>
                    </template>
                    <BarList :items="osBars" empty-icon="fas fa-desktop" />
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :md="12">
                <el-card shadow="never" class="full-height">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.top_pages') }}</span>
                    </template>
                    <BarList :items="pageBars" empty-icon="fas fa-file-alt" ltr />
                </el-card>
            </el-col>

            <el-col :xs="24" :md="12">
                <el-card shadow="never" class="full-height">
                    <template #header>
                        <span class="card-title">{{ $t('analytics.top_referrers') }}</span>
                    </template>
                    <BarList :items="referrerBars" empty-icon="fas fa-link" ltr />
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span class="card-title">{{ $t('analytics.visitor_log') }}</span>
                    <div class="card-header-actions">
                        <el-tag v-if="logTotal" size="small" effect="plain">{{ formatNumber(logTotal) }}</el-tag>
                        <el-button size="small" :loading="exporting" @click="exportVisitors">
                            <el-icon class="mr-1"><Download /></el-icon>
                            {{ $t('export_to_excel') }}
                        </el-button>
                    </div>
                </div>
            </template>

            <AdminFilterBar dense>
                <div class="filter-field">
                    <label>{{ $t('search') }}</label>
                    <el-input
                        v-model="logFilters.search"
                        clearable
                        :prefix-icon="Search"
                        :placeholder="$t('analytics.search_visitors_placeholder')"
                    />
                </div>

                <div class="filter-field">
                    <label>{{ $t('analytics.device_type') }}</label>
                    <el-select v-model="logFilters.device_type" clearable :placeholder="$t('all')">
                        <el-option v-for="type in filterOptions.device_types" :key="type" :value="type" :label="deviceLabel(type)" />
                    </el-select>
                </div>

                <div class="filter-field">
                    <label>{{ $t('analytics.browser') }}</label>
                    <el-select v-model="logFilters.browser" clearable :placeholder="$t('all')">
                        <el-option v-for="name in filterOptions.browsers" :key="name" :value="name" :label="name" />
                    </el-select>
                </div>

                <div class="filter-field">
                    <label>{{ $t('analytics.include_bots') }}</label>
                    <el-select v-model="logFilters.is_bot" :placeholder="$t('all')">
                        <el-option value="all" :label="$t('all')" />
                        <el-option value="0" :label="$t('analytics.humans_only')" />
                        <el-option value="1" :label="$t('analytics.bots_only')" />
                    </el-select>
                </div>
            </AdminFilterBar>

            <el-table
                :data="logRows"
                v-loading="logLoading"
                stripe
                :empty-text="$t('analytics.no_data_for_period')"
            >
                <el-table-column :label="$t('analytics.visited_at')" width="150">
                    <template #default="{ row }">
                        <span dir="ltr">{{ formatDateTime(row.visited_at) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('analytics.ip_address')" width="140">
                    <template #default="{ row }">
                        <span class="ip-chip" dir="ltr">{{ row.ip_address }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('analytics.page')" min-width="220" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span class="ltr-cell">{{ shortPath(row.page_url) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('analytics.device_type')" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.device_type" size="small" :type="deviceTagType(row.device_type)" effect="plain">
                            <i :class="deviceIcon(row.device_type)" class="fas"></i>
                            {{ deviceLabel(row.device_type) }}
                        </el-tag>
                        <span v-else class="muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('analytics.browser')" width="110">
                    <template #default="{ row }">{{ row.browser || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('analytics.operating_system')" width="100">
                    <template #default="{ row }">{{ row.os || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('analytics.referrer')" width="150" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span v-if="row.referrer" class="ltr-cell muted-strong" :title="row.referrer">
                            {{ referrerHost(row.referrer) }}
                        </span>
                        <span v-else class="muted">{{ $t('analytics.direct_traffic') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('analytics.traffic_type')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_bot ? 'warning' : 'success'" size="small" effect="light">
                            {{ row.is_bot ? $t('analytics.bot') : $t('analytics.human') }}
                        </el-tag>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="logTotal > logPerPage" class="pagination-bar">
                <el-pagination
                    background
                    layout="prev, pager, next, total"
                    :total="logTotal"
                    :page-size="logPerPage"
                    :current-page="logPage"
                    @current-change="(page) => { logPage = page; }"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { View, UserFilled, Warning, Odometer, Search, Download } from '@element-plus/icons-vue';
import analyticsApi from '@/api/analytics';
import { useCurrency } from '@/Composables/useCurrency';
import { useAnalyticsPanel } from '@/Composables/useAnalyticsPanel';
import { useEcharts, donutOption, emptyChartOption } from '@/Composables/useEcharts';
import { downloadBlob, filenameFromResponse } from '@/utils/download';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AnalyticsToolbar from '@/components/admin/analytics/AnalyticsToolbar.vue';
import KpiCard from '@/components/admin/analytics/KpiCard.vue';
import BarList from '@/components/admin/analytics/BarList.vue';

/**
 * Site-traffic analytics, reading `visitors` (written by `TrackVisitors` on
 * every public request) through `/analytics/visitors/*`.
 *
 * Replaces a screen that only ever rendered `$t('visitors_page_under_development')`
 * — the data was being collected the whole time; nothing displayed it.
 */

const { t, locale } = useI18n();
const { formatNumber } = useCurrency();

const pageRef = ref(null);
const summary = ref({});
const trend = ref([]);
const breakdown = ref({ devices: [], browsers: [], os: [] });
const topPages = ref([]);
const topReferrers = ref([]);
const filterOptions = ref({ device_types: [], browsers: [] });

const trendChartRef = ref(null);
const deviceChartRef = ref(null);

const { register, renderAll, observe } = useEcharts();

const {
    loading, refreshing, error, range, rangePresets,
    lastUpdatedLabel, fetchAll, refresh, applyRange, params,
} = useAnalyticsPanel({
    load: async (query) => {
        const [summaryRes, trendRes, breakdownRes, pagesRes] = await Promise.all([
            analyticsApi.visitorsSummary(query),
            analyticsApi.visitorsTrend(query),
            analyticsApi.visitorsBreakdown(query),
            analyticsApi.visitorsTopPages({ ...query, limit: 8 }),
        ]);

        summary.value = summaryRes.data ?? {};
        trend.value = trendRes.data ?? [];
        breakdown.value = breakdownRes.data ?? { devices: [], browsers: [], os: [] };
        topPages.value = pagesRes.data?.pages ?? [];
        topReferrers.value = pagesRes.data?.referrers ?? [];

        await renderAll();
        await loadLog(1);
    },
});

/* ---------------------------------------------------------------- *
 * Visitor log — its own loading/pagination, kept in step with the
 * toolbar's date range but not with its loading spinner: changing a
 * table filter should not blank out the charts above it.
 * ---------------------------------------------------------------- */

const logFilters = reactive({ search: '', device_type: '', browser: '', is_bot: 'all' });
const logRows = ref([]);
const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = 20;
const logLoading = ref(false);
const exporting = ref(false);

const loadLog = async (page = logPage.value) => {
    logLoading.value = true;
    try {
        const { data } = await analyticsApi.visitorsLog({
            ...params.value,
            ...logFilters,
            page,
            per_page: logPerPage,
        });
        logRows.value = data?.data ?? [];
        logTotal.value = data?.total ?? 0;
        logPage.value = data?.current_page ?? page;
    } catch (err) {
        console.error('Visitor log load failed:', err);
    } finally {
        logLoading.value = false;
    }
};

let searchDebounce = null;
watch(() => ({ ...logFilters }), () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadLog(1), 300);
});

watch(logPage, (page) => loadLog(page));

const exportVisitors = async () => {
    exporting.value = true;
    try {
        const response = await analyticsApi.exportDomain('visitors', { ...params.value, ...logFilters });
        const fallback = `analytics-visitors-${params.value.from_date}-to-${params.value.to_date}.csv`;
        downloadBlob(response.data, filenameFromResponse(response, fallback));
        ElMessage.success(t('analytics.export_ready'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('analytics.export_failed'));
    } finally {
        exporting.value = false;
    }
};

/* ------------------------------- Formatting ------------------------------ */

const formatPercent = (value) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';
    return `${formatNumber(Math.round(number * 10) / 10)}%`;
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', {
        month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    }).format(date);
};

const shortPath = (url) => {
    if (!url) return '—';
    try {
        const parsed = new URL(url);
        return `${parsed.pathname}${parsed.search}` || '/';
    } catch {
        return url;
    }
};

const referrerHost = (referrer) => {
    try {
        return new URL(referrer).host;
    } catch {
        return referrer;
    }
};

const deviceLabel = (name) => {
    const key = `analytics.device_${String(name || '').toLowerCase()}`;
    const translated = t(key);
    return translated === key ? (name || t('unknown')) : translated;
};

const deviceIcon = (name) => ({
    desktop: 'fa-desktop', mobile: 'fa-mobile-alt', tablet: 'fa-tablet-alt',
}[String(name || '').toLowerCase()] || 'fa-globe');

const deviceTagType = (name) => ({
    desktop: 'info', mobile: 'success', tablet: '',
}[String(name || '').toLowerCase()] ?? '');

/* --------------------------------- Charts --------------------------------- */

register('trend', trendChartRef, () => {
    if (!trend.value.length) return emptyChartOption(t('analytics.no_data_for_period'));

    return {
        tooltip: { trigger: 'axis' },
        legend: { data: [t('analytics.total_visits'), t('analytics.unique_visitors')], top: 0 },
        grid: { left: 10, right: 10, top: 40, bottom: 10, containLabel: true },
        xAxis: { type: 'category', data: trend.value.map((d) => d.date) },
        yAxis: { type: 'value' },
        series: [
            {
                name: t('analytics.total_visits'),
                type: 'line',
                smooth: true,
                data: trend.value.map((d) => d.visits),
                areaStyle: { color: 'rgba(102,126,234,0.15)' },
                lineStyle: { color: '#667eea', width: 3 },
                itemStyle: { color: '#667eea' },
            },
            {
                name: t('analytics.unique_visitors'),
                type: 'line',
                smooth: true,
                data: trend.value.map((d) => d.unique_visitors),
                lineStyle: { color: '#67c23a', width: 2, type: 'dashed' },
                itemStyle: { color: '#67c23a' },
            },
        ],
    };
});

register('device', deviceChartRef, () => {
    if (!breakdown.value.devices.length) return emptyChartOption(t('analytics.no_data_for_period'));

    return donutOption(
        breakdown.value.devices.map((row) => ({ name: deviceLabel(row.name), value: Number(row.count) }))
    );
});

/* Secondary breakdowns read better as ranked bars than a second row of donuts. */

const toBars = (rows, nameKey, valueKey) => {
    const max = Math.max(1, ...rows.map((r) => Number(r[valueKey]) || 0));
    return rows.map((r) => ({
        label: r[nameKey] || t('unknown'),
        value: Number(r[valueKey]) || 0,
        percentage: Math.round(((Number(r[valueKey]) || 0) / max) * 100),
    }));
};

const browserBars = computed(() => toBars(breakdown.value.browsers, 'name', 'count'));
const osBars = computed(() => toBars(breakdown.value.os, 'name', 'count'));
const pageBars = computed(() => toBars(
    topPages.value.map((p) => ({ ...p, label: shortPath(p.page_url) })), 'label', 'visits'
));
const referrerBars = computed(() => toBars(topReferrers.value, 'referrer', 'visits'));

onMounted(async () => {
    try {
        const { data } = await analyticsApi.visitorsFilters();
        filterOptions.value = data ?? { device_types: [], browsers: [] };
    } catch (err) {
        console.error('Visitor filter options load failed:', err);
    }

    await fetchAll();
    observe(pageRef.value);
});
</script>

<style scoped>
.visitors-analytics-page {
    padding: 0;
}

.chart-box {
    width: 100%;
    height: 300px;
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
    flex-wrap: wrap;
}

.card-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.full-height {
    height: 100%;
}

.ip-chip {
    font-family: monospace;
    font-size: 0.8rem;
    background: #f1f5f9;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
}

.ltr-cell {
    direction: ltr;
    text-align: left;
    display: inline-block;
    max-width: 100%;
}

.muted {
    color: #94a3b8;
    font-size: 0.8rem;
}

.muted-strong {
    color: #475569;
    font-size: 0.8rem;
}

.pagination-bar {
    display: flex;
    justify-content: center;
    padding-top: 1.25rem;
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

.mr-1 {
    margin-inline-end: 0.25rem;
}

@media (max-width: 768px) {
    .chart-box {
        height: 240px;
    }
}
</style>
