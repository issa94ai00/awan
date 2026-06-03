<template>
    <div class="dashboard" v-loading="loading">
        <el-alert
            v-if="error"
            :title="error"
            type="error"
            show-icon
            closable
            class="dashboard-alert"
        >
        </el-alert>

        <el-row :gutter="20">
            <el-col :xs="24" :sm="12" :md="6" v-for="stat in stats" :key="stat.title">
                <el-card class="stat-card" shadow="hover">
                    <div class="stat-content">
                        <div class="stat-icon" :style="{ background: stat.color }">
                            <el-icon :size="28" color="white">
                                <component :is="stat.icon"></component>
                            </el-icon>
                        </div>
                        <div class="stat-info">
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.title }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <div class="section mt-4">
            <div class="section-header">
                <h2>ملخص التقارير</h2>
            </div>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="8" v-for="item in detailStats" :key="item.title">
                    <el-card class="stat-card" shadow="hover">
                        <div class="stat-content">
                            <div class="stat-icon" :style="{ background: item.color }">
                                <el-icon :size="24" color="white">
                                    <component :is="item.icon"></component>
                                </el-icon>
                            </div>
                            <div class="stat-info">
                                <h4>{{ item.value }}</h4>
                                <p>{{ item.title }}</p>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <div class="section mt-4 dashboard-overview">
            <div class="section-header">
                <h2>حالة المعاملات</h2>
            </div>
            <el-row :gutter="20">
                <el-col :xs="24" :lg="16">
                    <el-card shadow="hover" class="revenue-card">
                        <template #header>
                            <div class="status-header">
                                <span>نظرة على الإيرادات</span>
                                <span class="small-text">آخر تحديث تلقائيًا</span>
                            </div>
                        </template>
                        <div class="revenue-summary">
                            <div class="revenue-value">
                                <span>الإيرادات الكلية</span>
                                <strong>{{ stats[3].value }}</strong>
                            </div>
                            <div class="revenue-metrics">
                                <div class="metric-item" v-for="metric in revenueMetrics" :key="metric.label">
                                    <span>{{ metric.label }}</span>
                                    <strong>{{ metric.value }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="progress-group">
                            <div v-for="metric in revenueMetrics" :key="metric.label" class="progress-bar-row">
                                <div class="progress-label">
                                    <span>{{ metric.label }}</span>
                                    <strong>{{ metric.percent }}%</strong>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" :style="{ width: metric.percent + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="8">
                    <el-card class="stats-grid-card" shadow="hover">
                        <template #header>
                            <div class="status-header">
                                <span>ملخص سريع</span>
                            </div>
                        </template>
                        <div class="quick-stats">
                            <div class="quick-stat" v-for="item in detailStats.slice(0, 4)" :key="item.title">
                                <span>{{ item.title }}</span>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <div class="section mt-4">
            <div class="section-header">
                <h2>حالة المعاملات</h2>
            </div>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="8" v-for="group in statusGroups" :key="group.title">
                    <el-card shadow="hover" class="status-card">
                        <template #header>
                            <div class="status-header">
                                <span>{{ group.title }}</span>
                            </div>
                        </template>
                        <div class="status-list">
                            <div v-for="item in group.items" :key="item.label" class="status-item">
                                <div>
                                    <span>{{ item.label }}</span>
                                    <div class="status-description">{{ item.description }}</div>
                                </div>
                                <strong>{{ formatCount(item.value) }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span>المبيعات الأخيرة</span>
                        </div>
                    </template>
                    <el-table :data="recentSales" style="width: 100%" :stripe="true">
                        <el-table-column prop="id" label="رقم الفاتورة" width="120"></el-table-column>
                        <el-table-column prop="customer" label="العميل"></el-table-column>
                        <el-table-column prop="amount" label="المبلغ"></el-table-column>
                        <el-table-column prop="status" label="الحالة">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)">
                                    {{ row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>المنتجات الأعلى</span>
                    </template>
                    <div class="top-products">
                        <div v-for="product in topProducts" :key="product.id" class="product-item">
                            <el-avatar :size="40" :src="product.image"></el-avatar>
                            <div class="product-info">
                                <h4>{{ product.name }}</h4>
                                <span>{{ product.sales }} وحدة</span>
                            </div>
                            <span class="product-price">{{ product.price }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
    Box, ShoppingCart, User, TrendCharts
} from '@element-plus/icons-vue';
import { dashboardApi } from '@/api/dashboard';

const stats = ref([
    { title: 'إجمالي المنتجات', value: '...', icon: Box, color: '#409eff' },
    { title: 'الفواتير', value: '...', icon: ShoppingCart, color: '#67c23a' },
    { title: 'العملاء', value: '...', icon: User, color: '#e6a23c' },
    { title: 'الإيرادات', value: '...', icon: TrendCharts, color: '#f56c6c' }
]);

const detailStats = ref([]);
const statusGroups = ref([]);
const revenueMetrics = ref([
    { label: 'اليوم', value: '0 ر.س', percent: 0 },
    { label: 'هذا الأسبوع', value: '0 ر.س', percent: 0 },
    { label: 'هذا الشهر', value: '0 ر.س', percent: 0 }
]);
const recentSales = ref([]);
const topProducts = ref([]);
const loading = ref(false);
const error = ref(null);

const getStatusType = (status) => {
    const types = {
        'مكتمل': 'success',
        'قيد المعالجة': 'warning',
        'ملغي': 'danger',
        'paid': 'success',
        'pending': 'warning',
        'cancelled': 'danger',
        'processed': 'success',
        'delivered': 'success',
        'failed': 'danger'
    };
    return types[status] || 'info';
};

const formatAmount = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '0 ر.س';
    }

    return new Intl.NumberFormat('ar-EG', {
        style: 'currency',
        currency: 'SAR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(value);
};

const getPercent = (value, total) => {
    if (!total || !value) {
        return 0;
    }
    return Math.min(100, Math.round((Number(value) / Number(total)) * 100));
};

const formatCount = (value) => {
    return (value ?? 0).toLocaleString('ar-EG');
};

const loadDashboard = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await dashboardApi.getOverviewStats();
        const overview = response.data?.data || {};

        stats.value = [
            { title: 'إجمالي المنتجات', value: formatCount(overview.products?.total), icon: Box, color: '#409eff' },
            { title: 'الفواتير', value: formatCount(overview.invoices?.total), icon: ShoppingCart, color: '#67c23a' },
            { title: 'العملاء', value: formatCount(overview.customers?.total), icon: User, color: '#e6a23c' },
            { title: 'الإيرادات', value: formatAmount(overview.invoices?.revenue?.total), icon: TrendCharts, color: '#f56c6c' }
        ];

        detailStats.value = [
            { title: 'عروض الأسعار', value: formatCount(overview.quotes?.total), icon: TrendCharts, color: '#8c6dfd' },
            { title: 'طلبات البيع', value: formatCount(overview.sales_orders?.total), icon: ShoppingCart, color: '#67c23a' },
            { title: 'أوامر الإنتاج', value: formatCount(overview.production?.total), icon: Box, color: '#f56c6c' },
            { title: 'الرواتب', value: formatCount(overview.payrolls?.total), icon: User, color: '#409eff' },
            { title: 'إيصالات الشراء', value: formatCount(overview.purchase_receipts?.total), icon: ShoppingCart, color: '#67c23a' },
            { title: 'الاستفسارات', value: formatCount(overview.inquiries?.total), icon: Box, color: '#e6a23c' }
        ];

        const totalRevenue = overview.invoices?.revenue?.total || 0;
        revenueMetrics.value = [
            { label: 'اليوم', value: formatAmount(overview.invoices?.revenue?.today ?? 0), percent: getPercent(overview.invoices?.revenue?.today, totalRevenue) },
            { label: 'هذا الأسبوع', value: formatAmount(overview.invoices?.revenue?.week ?? 0), percent: getPercent(overview.invoices?.revenue?.week, totalRevenue) },
            { label: 'هذا الشهر', value: formatAmount(overview.invoices?.revenue?.month ?? 0), percent: getPercent(overview.invoices?.revenue?.month, totalRevenue) }
        ];

        statusGroups.value = [
            {
                title: 'حالة الفواتير',
                items: [
                    { label: 'مدفوعة', value: overview.invoices?.paid, description: 'الفواتير المكتملة بنجاح' },
                    { label: 'معلقة', value: overview.invoices?.pending, description: 'الفواتير التي تحتاج متابعة' },
                    { label: 'ملغاة', value: overview.invoices?.cancelled, description: 'الفواتير الملغاة أو المسترجعة' }
                ]
            },
            {
                title: 'حالة المدفوعات',
                items: [
                    { label: 'مكتملة', value: overview.payments?.completed, description: 'الدفع المكتمل من العملاء' },
                    { label: 'معلقة', value: overview.payments?.pending, description: 'الدفع في انتظار المعالجة' },
                    { label: 'مستردة', value: overview.payments?.refunded, description: 'المدفوعات المستردة للعملاء' }
                ]
            },
            {
                title: 'حالة الإنتاج',
                items: [
                    { label: 'معلقة', value: overview.production?.pending, description: 'طلبات الإنتاج غير المبدوءة' },
                    { label: 'قيد التنفيذ', value: overview.production?.in_progress, description: 'طلبات الإنتاج الحالية' },
                    { label: 'مكتملة', value: overview.production?.completed, description: 'الطلبات الجاهزة للتسليم' }
                ]
            }
        ];

        recentSales.value = (overview.recent_invoices ?? []).map((invoice) => ({
            id: invoice.invoice_number || invoice.id || '#',
            customer: invoice.customer_name || 'عميل',
            amount: formatAmount(invoice.total),
            status: invoice.status
        }));

        topProducts.value = (overview.top_products ?? []).map((product) => ({
            id: product.id,
            name: product.name_ar || product.name_en || 'منتج',
            sales: product.stock_quantity ?? 0,
            price: formatAmount(product.price),
            image: product.image || ''
        }));
    } catch (err) {
        error.value = err.response?.data?.message || err.message || 'فشل في تحميل بيانات لوحة القيادة';
        console.error('Dashboard load error:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(loadDashboard);
</script>

<style scoped>
.dashboard {
    padding: 0;
}

.section {
    padding: 1rem 0;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.section-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: #2f3b52;
    font-weight: 700;
}

.stat-card,
.status-card {
    margin-bottom: 1rem;
    border: none;
    border-radius: 12px;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-info h3,
.stat-info h4 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #333;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.status-list {
    display: grid;
    gap: 0.75rem;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    background: #f8fafc;
}

.status-item strong {
    font-size: 0.95rem;
    color: #1f2d3d;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mt-4 {
    margin-top: 1.5rem;
}

.top-products {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.product-item:hover {
    background: #f5f7fa;
}

.product-info {
    flex: 1;
}

.product-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    color: #333;
}

.product-info span {
    font-size: 0.82rem;
    color: #666;
}

.product-price {
    font-weight: 600;
    color: #409eff;
}

.revenue-card,
.stats-grid-card {
    border-radius: 16px;
    background: #ffffff;
}

.revenue-summary {
    display: grid;
    gap: 1.5rem;
    padding: 1rem 0;
}

.revenue-value {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.revenue-value span {
    color: #606f8b;
    font-size: 0.95rem;
}

.revenue-value strong {
    font-size: 2rem;
    color: #1f2d3d;
}

.revenue-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.metric-item {
    padding: 1rem;
    border-radius: 12px;
    background: #f5f7fb;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.metric-item strong {
    color: #2f3b52;
    font-size: 1rem;
}

.progress-group {
    display: grid;
    gap: 1rem;
    padding-top: 0.5rem;
}

.progress-bar-row {
    display: grid;
    gap: 0.5rem;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #4b5c7a;
}

.progress-track {
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: #ebedf3;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #409eff 0%, #67c23a 100%);
}

.quick-stats {
    display: grid;
    gap: 1rem;
    padding: 1rem 0;
}

.quick-stat {
    padding: 1rem;
    border-radius: 12px;
    background: #f8fbff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.quick-stat span {
    color: #6f7d92;
}

.quick-stat strong {
    color: #1f2d3d;
}

.dashboard-alert {
    margin-bottom: 1.5rem;
}

.status-description {
    display: block;
    font-size: 0.78rem;
    color: #8b96a7;
    margin-top: 0.2rem;
}

@media (max-width: 768px) {
    .revenue-metrics {
        grid-template-columns: 1fr;
    }
}
</style>
