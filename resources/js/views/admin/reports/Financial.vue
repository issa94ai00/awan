<template>
    <div class="reports-financial">
        <div class="page-header">
            <div class="page-title">
                <div class="title-badge">Finance</div>
                <h1>{{ $t('financial_report') }}</h1>
                <p>{{ $t('financial_report') }} - {{ $t('summary_of_reports') }}</p>
            </div>
            <div class="toolbar-actions">
                <el-button :icon="RefreshRight" @click="resetFinancialFilters" class="secondary-action">
                    إعادة الضبط
                </el-button>
                <el-button type="primary" :icon="Download" @click="exportFinancialReport">
                    تصدير
                </el-button>
            </div>
        </div>

        <el-card shadow="hover" class="filter-card">
            <el-row :gutter="20" align="middle">
                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>العميل</label>
                        <el-select v-model="filters.customer_id" placeholder="الكل" clearable style="width: 100%" @change="loadInvoiceDimensions">
                            <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id" />
                        </el-select>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>المستودع</label>
                        <el-select v-model="filters.warehouse_id" placeholder="الكل" clearable style="width: 100%" @change="loadInvoiceDimensions">
                            <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                        </el-select>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>الفترة</label>
                        <el-select v-model="filters.date_filter_type" placeholder="الكل" clearable style="width: 100%" @change="loadInvoiceDimensions">
                            <el-option label="الكل" :value="'all'" />
                            <el-option label="اليوم" :value="'today'" />
                            <el-option label="الأمس" :value="'yesterday'" />
                            <el-option label="هذا الأسبوع" :value="'this_week'" />
                            <el-option label="هذا الشهر" :value="'this_month'" />
                            <el-option label="الشهر الماضي" :value="'last_month'" />
                            <el-option label="مخصص" :value="'custom'" />
                        </el-select>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>التاريخ من</label>
                        <el-date-picker v-model="filters.start_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" @change="loadInvoiceDimensions" />
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6" style="margin-top: 18px;">
                    <div class="form-group">
                        <label>إلى</label>
                        <el-date-picker v-model="filters.end_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" @change="loadInvoiceDimensions" />
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <el-alert
            v-if="hasFinancialData === false"
            title="لا توجد بيانات في الفلاتر الحالية"
            type="info"
            :closable="false"
            show-icon
            class="empty-alert"
        />

        <div v-if="loading" class="empty-state">
            <el-skeleton :rows="7" animated />
        </div>

        <template v-else>
            <el-row :gutter="20" class="summary-cards">
                <el-col v-for="(stat, key) in summaryStats" :key="key" :xs="24" :sm="12" :md="6">
                    <el-card shadow="hover" class="stat-card" :class="'stat-card-' + key">
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
                </el-col>
            </el-row>

            <el-row :gutter="20" class="charts-section">
                <el-col :xs="24" :lg="16">
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

                <el-col :xs="24" :lg="8">
                    <el-card shadow="hover">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('payment_status') }}</span>
                            </div>
                        </template>
                        <div ref="paymentChartRef" class="chart-box chart-box-sm"></div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" class="bottom-grid">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>ملخص العملاء</span>
                            </div>
                        </template>
                        <el-table :data="dimensionData.customer_summary || []" stripe style="width: 100%">
                            <el-table-column prop="customer_name" label="العميل" />
                            <el-table-column prop="total_invoices" label="عدد الفواتير">
                                <template #default="{ row }">{{ formatNumber(row.total_invoices) }}</template>
                            </el-table-column>
                            <el-table-column prop="total_invoiced" label="الإجمالي">
                                <template #default="{ row }">{{ formatCurrency(row.total_invoiced) }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>ملخص المستودعات</span>
                            </div>
                        </template>
                        <el-table :data="dimensionData.warehouse_summary || []" stripe style="width: 100%">
                            <el-table-column prop="warehouse_name" label="المستودع" />
                            <el-table-column prop="total_invoices" label="عدد الفواتير">
                                <template #default="{ row }">{{ formatNumber(row.total_invoices) }}</template>
                            </el-table-column>
                            <el-table-column prop="total_invoiced" label="الإجمالي">
                                <template #default="{ row }">{{ formatCurrency(row.total_invoiced) }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" class="bottom-grid">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('invoice_status_breakdown') }}</span>
                            </div>
                        </template>
                        <div class="status-list">
                            <div v-for="item in invoiceStatusList" :key="item.label" class="status-item">
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
                                <span>{{ $t('cash_flow_summary') }}</span>
                            </div>
                        </template>
                        <div class="status-list">
                            <div v-for="item in cashFlowList" :key="item.label" class="status-item">
                                <div>
                                    <span>{{ item.label }}</span>
                                    <small>{{ item.caption }}</small>
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
import { Coin, Wallet, TrendingUp, CircleCheck, Document, Ticket, Download, RefreshRight } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import api from '@/api';
import { dashboardApi } from '@/api/dashboard';

const loading = ref(true);
const overview = ref({
    invoices: { total: 0, paid: 0, pending: 0, cancelled: 0, revenue: { today: 0, week: 0, month: 0, total: 0 } },
    payments: { total: 0, completed: 0, pending: 0, refunded: 0, amounts: { completed: 0, pending: 0, refunded: 0 } },
    erp: { total_revenue: 0, total_sales: 0, monthly_revenue: 0 }
});
const customers = ref([]);
const warehouses = ref([]);
const dimensionData = ref({ customer_summary: [], warehouse_summary: [], overall: { total_invoices: 0, total_invoiced: 0 } });
const filters = ref({ customer_id: null, warehouse_id: null, date_filter_type: 'all', start_date: null, end_date: null });
const salesTrend = ref([]);
const revenueChartRef = ref(null);
const paymentChartRef = ref(null);
let revenueChart = null;
let paymentChart = null;

const hasFinancialData = computed(() => {
    const summaryRows = (dimensionData.value.customer_summary || []).length > 0 || (dimensionData.value.warehouse_summary || []).length > 0;
    return summaryRows || Number(overview.value.invoices?.total || 0) > 0 || Number(overview.value.erp?.total_revenue || 0) > 0;
});

const summaryStats = computed(() => ({
    total_revenue: {
        label: 'إجمالي الإيرادات',
        value: formatCurrency(overview.value.erp?.total_revenue || overview.value.invoices?.revenue?.total || 0),
        icon: Coin
    },
    payments_completed: {
        label: 'المدفوعات المكتملة',
        value: formatCurrency(overview.value.payments?.amounts?.completed || overview.value.payments?.completed || 0),
        icon: CircleCheck
    },
    pending_amount: {
        label: 'المبالغ المعلقة',
        value: formatCurrency(overview.value.payments?.amounts?.pending || overview.value.payments?.pending || 0),
        icon: Wallet
    },
    invoice_count: {
        label: 'عدد الفواتير',
        value: formatNumber(overview.value.invoices?.total || 0),
        icon: Document
    }
}));

const invoiceStatusList = computed(() => [
    { label: 'مدفوعة', caption: 'الفواتير المكتملة', value: formatNumber(overview.value.invoices?.paid || 0) },
    { label: 'معلّقة', caption: 'قيد المتابعة', value: formatNumber(overview.value.invoices?.pending || 0) },
    { label: 'ملغاة', caption: 'تمت إلغاؤها', value: formatNumber(overview.value.invoices?.cancelled || 0) }
]);

const cashFlowList = computed(() => [
    { label: 'إيراد اليوم', caption: 'أحدث الإيرادات', value: formatCurrency(overview.value.invoices?.revenue?.today || 0) },
    { label: 'إيراد الأسبوع', caption: 'التراكم الحالي', value: formatCurrency(overview.value.invoices?.revenue?.week || 0) },
    { label: 'إيراد الشهر', caption: 'الشهر الحالي', value: formatCurrency(overview.value.invoices?.revenue?.month || 0) }
]);

const formatCurrency = (value) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(Number(value || 0));
const formatNumber = (value) => new Intl.NumberFormat('ar-SA').format(Number(value || 0));

const resetFinancialFilters = () => {
    filters.value = { customer_id: null, warehouse_id: null, date_filter_type: 'all', start_date: null, end_date: null };
    loadInvoiceDimensions();
};

const exportFinancialReport = async () => {
    try {
        // Goes through the api client so the export carries the bearer token too.
        const response = await api.get('/admin/reports/invoices/export', {
            params: getDimensionParams(),
            responseType: 'blob'
        });

        downloadCsv(response.data, 'financial-report');
    } catch (error) {
        console.error('Failed to export financial report:', error);
    }
};

const downloadCsv = (data, prefix) => {
    const url = window.URL.createObjectURL(new Blob([data], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${prefix}-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
};

const formatDateForApi = (date) => {
    if (!date) return null;
    const parsed = new Date(date + 'T00:00:00');
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString().split('T')[0];
};

const getDimensionParams = () => {
    const params = {};

    if (filters.value.customer_id) params.customer_id = filters.value.customer_id;
    if (filters.value.warehouse_id) params.warehouse_id = filters.value.warehouse_id;
    if (filters.value.date_filter_type) params.date_filter_type = filters.value.date_filter_type;
    if (filters.value.start_date) params.start_date = formatDateForApi(filters.value.start_date);
    if (filters.value.end_date) params.end_date = formatDateForApi(filters.value.end_date);

    return params;
};

const loadCustomers = async () => {
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 100 } });
        if (response.data.success && response.data.data && Array.isArray(response.data.data.customers)) {
            customers.value = response.data.data.customers.map(item => item.data || item);
        }
    } catch (error) {
        console.error('Failed to load customers:', error);
    }
};

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        if (response.data && Array.isArray(response.data.data)) {
            warehouses.value = response.data.data;
        } else if (response.data && response.data.data && Array.isArray(response.data.data.data)) {
            warehouses.value = response.data.data.data;
        }
    } catch (error) {
        console.error('Failed to load warehouses:', error);
    }
};

const loadInvoiceDimensions = async () => {
    try {
        const response = await api.get('/admin/reports/invoices/dimensions', { params: getDimensionParams() });

        if (response.data.success) {
            dimensionData.value = {
                customer_summary: response.data.data.customer_summary || [],
                warehouse_summary: response.data.data.warehouse_summary || [],
                overall: response.data.data.overall || { total_invoices: 0, total_invoiced: 0 }
            };
        }
    } catch (error) {
        console.error('Failed to load invoice dimensions:', error);
    }
};

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

const renderPaymentChart = async () => {
    if (!paymentChartRef.value) return;
    if (paymentChart) paymentChart.dispose();
    paymentChart = echarts.init(paymentChartRef.value);

    const completed = Number(overview.value.payments?.amounts?.completed || overview.value.payments?.completed || 0);
    const pending = Number(overview.value.payments?.amounts?.pending || overview.value.payments?.pending || 0);
    const refunded = Number(overview.value.payments?.amounts?.refunded || overview.value.payments?.refunded || 0);

    paymentChart.setOption({
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
            type: 'pie',
            radius: ['55%', '75%'],
            center: ['50%', '42%'],
            data: [
                { value: completed, name: 'مكتمل', itemStyle: { color: '#22c55e' } },
                { value: pending, name: 'معلق', itemStyle: { color: '#f59e0b' } },
                { value: refunded, name: 'مسترد', itemStyle: { color: '#ef4444' } }
            ],
            label: { show: false }
        }]
    });
};

async function loadSummary() {
    loading.value = true;
    try {
        const [overviewResponse, trendResponse] = await Promise.all([
            dashboardApi.getOverviewStats(),
            dashboardApi.getSalesTrend({ days: 30, group_by: 'day' })
        ]);

        overview.value = overviewResponse.data?.data || overview.value;
        salesTrend.value = trendResponse.data?.data || trendResponse.data || [];
        await nextTick();
        renderRevenueChart();
        renderPaymentChart();
    } catch (e) {
        console.error('Failed to load financial summary', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSummary();
    loadCustomers();
    loadWarehouses();
    loadInvoiceDimensions();
    window.addEventListener('resize', () => {
        revenueChart?.resize();
        paymentChart?.resize();
    });
});
</script>

<style scoped>
.reports-financial {
    padding: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.secondary-action {
    border: 1px solid #dfe7f1;
    color: #334155;
    background: #fff;
}

.filter-card {
    margin-bottom: 20px;
    border-radius: 16px;
    border: 1px solid #eef3f8;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
}

.page-title {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.title-badge {
    display: inline-flex;
    width: fit-content;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(16,185,129,0.14), rgba(52,211,153,0.18));
    color: #047857;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0;
    color: #667085;
}

.summary-cards {
    margin-bottom: 1.5rem;
}

.stat-card {
    border: none;
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(255,255,255,0.96), rgba(248,250,252,0.96));
    border: 1px solid #edf2f7;
}

.stat-card-total_revenue { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(52,211,153,0.16)); }
.stat-card-payments_completed { background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(96,165,250,0.16)); }
.stat-card-pending_amount { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(253,224,71,0.18)); }
.stat-card-invoice_count { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.18)); }

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
}

.stat-info h3 {
    margin: 0;
    font-size: 1.45rem;
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

.chart-box-sm {
    height: 280px;
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

.empty-state {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 1rem;
}

.empty-alert {
    margin-bottom: 1rem;
    border-radius: 12px;
}

@media (max-width: 768px) {
    .page-title h1 {
        font-size: 1.45rem;
    }

    .chart-box {
        height: 260px;
    }
}
</style>
