<template>
    <div class="reports-sales">
        <AdminPageHeader
            badge="Sales"
            :title="$t('sales_report')"
            :subtitle="$t('sales_report_subtitle')"
        >
            <template #actions>
                <el-button :icon="RefreshRight" @click="loadSummary" class="secondary-action">
                    {{ $t('reset') }}
                </el-button>
                <router-link :to="{ name: 'admin.reports.professional-sales' }">
                    <el-button type="primary" :icon="TrendCharts">
                        {{ $t('professional_sales_report') }}
                    </el-button>
                </router-link>
            </template>
        </AdminPageHeader>

        <div v-if="loading" class="empty-state">
            <el-skeleton :rows="7" animated />
        </div>

        <template v-else>
            <AdminStatGrid>
                <el-card v-for="(stat, key) in summaryStats" :key="key" shadow="hover" class="stat-card" :class="'stat-card-' + key">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <component :is="stat.icon" />
                        </div>
                        <div class="stat-info">
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.label }}</p>
                        </div>
                    </div>
                </el-card>
            </AdminStatGrid>

            <el-row :gutter="20" class="charts-section">
                <el-col :span="24">
                    <el-card shadow="hover">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('revenue_trend_title') }}</span>
                                <el-tag type="primary">30 {{ $t('days') }}</el-tag>
                            </div>
                        </template>
                        <div ref="revenueChartRef" class="chart-box"></div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" class="bottom-grid">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('invoices_count') }}</span>
                            </div>
                        </template>
                        <div class="status-list">
                            <div v-for="item in countList" :key="item.label" class="status-item">
                                <div>
                                    <span>{{ item.label }}</span>
                                    <small>{{ item.caption }}</small>
                                </div>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('today_breakdown') }}</span>
                            </div>
                        </template>
                        <div class="status-list">
                            <div v-for="item in todayBreakdownList" :key="item.label" class="status-item">
                                <div>
                                    <el-tag :type="item.tagType" size="small" effect="light">{{ item.label }}</el-tag>
                                </div>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { Coin, Calendar, Wallet, TrendCharts, Warning, RefreshRight } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import { invoicesApi } from '@/api/invoices';
import { dashboardApi } from '@/api/dashboard';
import { formatMoney, formatNumber as formatCount } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const loading = ref(true);
const summary = ref({ revenue: {}, total_sales: {}, count: {}, today_breakdown: {} });
const salesTrend = ref([]);
const revenueChartRef = ref(null);
let revenueChart = null;

const formatCurrency = (value) => formatMoney(value, { decimals: 0 });
const formatNumber = (value) => formatCount(value);

const summaryStats = computed(() => ({
    revenue_today: {
        label: t('revenue_today'),
        value: formatCurrency(summary.value.revenue?.today || 0),
        icon: Coin
    },
    revenue_this_week: {
        label: t('revenue_this_week'),
        value: formatCurrency(summary.value.revenue?.week || 0),
        icon: Calendar
    },
    revenue_this_month: {
        label: t('revenue_this_month'),
        value: formatCurrency(summary.value.revenue?.month || 0),
        icon: TrendCharts
    },
    total_revenue: {
        label: t('total_revenue'),
        value: formatCurrency(summary.value.revenue?.total || 0),
        icon: Wallet
    },
    // Sales that never became recognized revenue: still pending confirmation,
    // or cancelled outright. Without this the gap between "total invoiced"
    // and "total revenue" elsewhere on the report has no explanation here.
    excluded_from_revenue: {
        label: t('excluded_from_revenue'),
        value: formatCurrency(Math.max(0, (summary.value.total_sales?.total || 0) - (summary.value.revenue?.total || 0))),
        icon: Warning
    }
}));

const countList = computed(() => [
    { label: t('today'), caption: t('invoices_count'), value: formatNumber(summary.value.count?.today || 0) },
    { label: t('this_week'), caption: t('invoices_count'), value: formatNumber(summary.value.count?.week || 0) },
    { label: t('this_month'), caption: t('invoices_count'), value: formatNumber(summary.value.count?.month || 0) },
    { label: t('total'), caption: t('invoices_count'), value: formatNumber(summary.value.count?.total || 0) }
]);

const statusTagTypes = {
    pending: 'info',
    confirmed: 'warning',
    processing: 'primary',
    shipped: 'success',
    delivered: 'success',
    cancelled: 'danger'
};

const statusLabels = () => ({
    pending: t('sales_status_pending'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    shipped: t('sales_status_shipped'),
    delivered: t('sales_status_delivered'),
    cancelled: t('sales_status_cancelled')
});

const todayBreakdownList = computed(() => {
    const labels = statusLabels();
    return Object.keys(labels).map((status) => ({
        label: labels[status],
        tagType: statusTagTypes[status] || 'info',
        value: formatCurrency(summary.value.today_breakdown?.[status] || 0)
    }));
});

const renderRevenueChart = async () => {
    if (!revenueChartRef.value) return;
    if (revenueChart) revenueChart.dispose();
    revenueChart = echarts.init(revenueChartRef.value);

    const labels = salesTrend.value.map((item) => item.date || item.period || '');
    const data = salesTrend.value.map((item) => Number(item.revenue || 0));

    revenueChart.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: 10, right: 14, top: 12, bottom: 28, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: 20, fontSize: 10 }
        },
        yAxis: { type: 'value' },
        series: [{
            data,
            type: 'line',
            smooth: true,
            areaStyle: { color: 'rgba(99, 102, 241, 0.12)' },
            lineStyle: { width: 3, color: '#4f46e5' },
            itemStyle: { color: '#4f46e5' }
        }]
    });
};

async function loadSummary() {
    loading.value = true;
    try {
        const [summaryResponse, trendResponse] = await Promise.all([
            invoicesApi.summary(),
            dashboardApi.getSalesTrend({ days: 30, group_by: 'day' })
        ]);

        summary.value = summaryResponse.data?.data || summary.value;
        salesTrend.value = trendResponse.data?.data || trendResponse.data || [];

        await nextTick();
        renderRevenueChart();
    } catch (e) {
        console.error('Failed to load sales summary', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSummary();
    window.addEventListener('resize', () => {
        revenueChart?.resize();
    });
});
</script>

<style scoped>
.reports-sales {
    padding: 0;
}

.secondary-action {
    border: 1px solid #dfe7f1;
    color: #334155;
    background: #fff;
}

.empty-state {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 1rem;
}

.stat-card {
    border: none;
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(255,255,255,0.96), rgba(248,250,252,0.96));
    border: 1px solid #edf2f7;
}

.stat-card-revenue_today { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(52,211,153,0.16)); }
.stat-card-revenue_this_week { background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(96,165,250,0.16)); }
.stat-card-revenue_this_month { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.18)); }
.stat-card-total_revenue { background: linear-gradient(135deg, rgba(14,165,233,0.08), rgba(125,211,252,0.18)); }
.stat-card-excluded_from_revenue { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(253,224,71,0.18)); }

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
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.4rem;
    color: #1f2d3d;
}

.stat-info p {
    margin: 0.2rem 0 0;
    color: #64748b;
    font-size: 0.88rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.charts-section,
.bottom-grid {
    margin-bottom: 1.5rem;
}

.chart-box {
    width: 100%;
    height: 320px;
}

.status-list {
    display: grid;
    gap: 0.9rem;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: #f8fafc;
}

.status-item span {
    display: block;
    font-weight: 600;
    color: #1f2937;
}

.status-item small {
    display: block;
    margin-top: 0.15rem;
    color: #64748b;
}

.status-item strong {
    color: #0f172a;
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .chart-box {
        height: 260px;
    }
}
</style>
