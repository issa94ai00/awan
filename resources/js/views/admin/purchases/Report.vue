<template>
    <div class="reports-page professional-purchases">
        <AdminPageHeader
            badge="PROCUREMENT"
            :title="$t('professional_purchases_report')"
            :subtitle="$t('purchases_report_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" @click="resetFilters" class="secondary-action">
                    {{ $t('reset') }}
                </el-button>
                <el-button type="primary" :icon="Download" @click="exportReport">
                    {{ $t('export_report') }}
                </el-button>
                <el-button :icon="ArrowLeft" @click="goBack">
                    {{ $t('back') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('supplier') }}</label>
                <el-select v-model="filters.supplier_id" :placeholder="$t('all_suppliers')" clearable filterable>
                    <el-option
                        v-for="supplier in suppliers"
                        :key="supplier.id"
                        :label="supplier.name"
                        :value="supplier.id"
                    />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('order_status') }}</label>
                <el-select v-model="filters.status" :placeholder="$t('all_statuses')" clearable>
                    <el-option :label="$t('pending')" value="pending" />
                    <el-option :label="$t('confirmed')" value="confirmed" />
                    <el-option :label="$t('processing')" value="processing" />
                    <el-option :label="$t('completed')" value="completed" />
                    <el-option :label="$t('cancelled')" value="cancelled" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('date_filter_type') }}</label>
                <el-select v-model="filters.date_filter_type">
                    <el-option :label="$t('all')" value="all" />
                    <el-option :label="$t('today')" value="today" />
                    <el-option :label="$t('yesterday')" value="yesterday" />
                    <el-option :label="$t('this_week')" value="this_week" />
                    <el-option :label="$t('this_month')" value="this_month" />
                    <el-option :label="$t('last_month')" value="last_month" />
                    <el-option :label="$t('custom')" value="custom" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('group_by') }}</label>
                <el-select v-model="filters.group_by">
                    <el-option :label="$t('daily')" value="day" />
                    <el-option :label="$t('weekly')" value="week" />
                    <el-option :label="$t('monthly')" value="month" />
                    <el-option :label="$t('by_supplier')" value="supplier" />
                    <el-option :label="$t('by_status')" value="status" />
                </el-select>
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('start_date') }}</label>
                <el-date-picker v-model="filters.start_date" type="date" :placeholder="$t('start_date')" />
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('end_date') }}</label>
                <el-date-picker v-model="filters.end_date" type="date" :placeholder="$t('end_date')" />
            </div>

            <template #actions>
                <el-button type="primary" :icon="Search" @click="applyFilters">
                    {{ $t('apply_filters') }}
                </el-button>
            </template>
        </AdminFilterBar>

        <el-alert
            v-if="hasData === false && !loading"
            :title="$t('no_data_for_current_filters')"
            type="info"
            :closable="false"
            show-icon
            class="empty-alert"
        />

        <AdminStatGrid v-if="loading">
            <el-card v-for="n in 8" :key="n" shadow="hover" class="stat-card skeleton-card">
                <el-skeleton :rows="2" animated />
            </el-card>
        </AdminStatGrid>

        <AdminStatGrid v-else>
            <el-card v-for="(stat, key) in summaryStats" :key="key" shadow="hover" class="stat-card" :class="'stat-card-' + key">
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

        <el-row v-if="!loading && performanceData.summary" :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('cost_of_goods') }}</span>
                        <strong>{{ formatCurrency(performanceData.summary.total_cost || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('planned_revenue') }}</span>
                        <strong>{{ formatCurrency(performanceData.summary.total_planned_revenue || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('planned_profit') }}</span>
                        <strong>{{ formatCurrency(performanceData.summary.planned_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('planned_margin') }}</span>
                        <strong>{{ formatPercentage(performanceData.summary.planned_margin || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Charts -->
        <el-row :gutter="20" class="charts-section">
            <el-col :xs="24" :md="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('purchases_by_period') }}</span>
                    </template>
                    <div ref="purchasesChartRef" style="height: 300px"></div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('distribution_by_criteria') }}</span>
                    </template>
                    <div ref="distributionChartRef" style="height: 300px"></div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="dimension-panels" style="margin-top: 20px; margin-bottom: 20px;">
            <el-col :xs="24" :md="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('suppliers') }}</span>
                    </template>
                    <el-table :data="dimensionData.supplier_summary || []" stripe style="width: 100%">
                        <el-table-column prop="supplier_name" :label="$t('supplier')" />
                        <el-table-column prop="total_orders" :label="$t('total_orders')" width="110" align="center" />
                        <el-table-column :label="$t('total_spend')">
                            <template #default="{ row }">{{ formatCurrency(row.total_spend) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('status') }}</span>
                    </template>
                    <el-table :data="dimensionData.status_summary || []" stripe style="width: 100%">
                        <el-table-column :label="$t('status')">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)" size="small">{{ row.status_text || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="total_orders" :label="$t('total_orders')" width="110" align="center" />
                        <el-table-column :label="$t('total_spend')">
                            <template #default="{ row }">{{ formatCurrency(row.total_spend) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <el-row v-if="productSpendData.summary" :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('top_spend_product') }}</span>
                        <strong>{{ productSpendData.summary.top_spend_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(productSpendData.summary.top_spend_product?.total_cost || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('lowest_margin_product') }}</span>
                        <strong>{{ productSpendData.summary.lowest_margin_product?.product_name || '-' }}</strong>
                        <small>{{ formatPercentage(productSpendData.summary.lowest_margin_product?.planned_margin || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('total_planned_profit') }}</span>
                        <strong>{{ formatCurrency(productSpendData.summary.planned_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('items_count') }}</span>
                        <strong>{{ productSpendData.summary.product_count || 0 }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="profitability-table-card" style="margin-bottom: 20px;">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('product_spend_breakdown') }}</span>
                </div>
            </template>

            <el-table :data="productSpendData.product_summary || []" stripe style="width: 100%">
                <el-table-column prop="product_name" :label="$t('product')" />
                <el-table-column prop="quantity" :label="$t('quantity')" width="110" align="center" />
                <el-table-column :label="$t('cost')">
                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column :label="$t('revenue')">
                    <template #default="{ row }">{{ formatCurrency(row.total_planned_revenue) }}</template>
                </el-table-column>
                <el-table-column :label="$t('profit')">
                    <template #default="{ row }">
                        <strong :class="row.planned_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatCurrency(row.planned_profit) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('margin')">
                    <template #default="{ row }">{{ formatPercentage(row.planned_margin) }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Detailed Report Table -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('detailed_report') }}</span>
                    <el-button type="success" :icon="Download" @click="exportReport">
                        {{ $t('export_report') }}
                    </el-button>
                </div>
            </template>

            <el-table v-loading="loading" :data="reportData" style="width: 100%" stripe highlight-current-row>
                <el-table-column prop="order_number" :label="$t('order_number')" width="120" />
                <el-table-column :label="$t('date')" width="120">
                    <template #default="{ row }">{{ formatDate(row.order_date) }}</template>
                </el-table-column>
                <el-table-column :label="$t('supplier')">
                    <template #default="{ row }">{{ row.supplier ? row.supplier.name : '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">{{ getStatusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('subtotal')">
                    <template #default="{ row }">{{ formatCurrency(row.subtotal) }}</template>
                </el-table-column>
                <el-table-column :label="$t('discount')">
                    <template #default="{ row }">{{ formatCurrency(row.discount) }}</template>
                </el-table-column>
                <el-table-column :label="$t('tax')">
                    <template #default="{ row }">{{ formatCurrency(row.tax) }}</template>
                </el-table-column>
                <el-table-column :label="$t('total')" width="120">
                    <template #default="{ row }"><strong>{{ formatCurrency(row.total) }}</strong></template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-if="pagination.total > 0"
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :page-sizes="[10, 20, 50, 100]"
                :total="pagination.total"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
                style="margin-top: 20px; justify-content: center"
            />
        </el-card>

        <!-- Top Suppliers -->
        <el-card shadow="hover" class="top-performers-card">
            <template #header>
                <span>{{ $t('top_suppliers_by_spend') }}</span>
            </template>

            <el-table v-loading="loadingTopSuppliers" :data="topSuppliers" style="width: 100%" stripe>
                <el-table-column :label="$t('rank')" width="80">
                    <template #default="{ $index }">
                        <el-tag :type="getRankType($index)" size="small">{{ $index + 1 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="supplier_name" :label="$t('supplier')" />
                <el-table-column prop="total_orders" :label="$t('total_orders')" />
                <el-table-column :label="$t('total_spend')">
                    <template #default="{ row }"><strong>{{ formatCurrency(row.total_spend) }}</strong></template>
                </el-table-column>
                <el-table-column :label="$t('average_order_value')">
                    <template #default="{ row }">{{ formatCurrency(row.average_order_value) }}</template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { formatMoney, formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { ArrowLeft, Search, Refresh, Download, ShoppingCart, Coin, PriceTag, PieChart, TrendCharts, Document, Wallet, Warning } from '@element-plus/icons-vue';
import api from '@/api';
import * as echarts from 'echarts';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { useSuppliersStore } from '@/stores/suppliers';

const { t } = useI18n();

const router = useRouter();
const suppliersStore = useSuppliersStore();
const suppliers = computed(() => suppliersStore.suppliers);

const loading = ref(false);
const loadingTopSuppliers = ref(false);
const reportData = ref([]);
const topSuppliers = ref([]);
const summary = ref({});
const dimensionData = ref({ supplier_summary: [], status_summary: [] });
const performanceData = ref({ summary: {}, supplier_summary: [] });
const productSpendData = ref({ summary: {}, product_summary: [] });

const purchasesChartRef = ref(null);
const distributionChartRef = ref(null);
let purchasesChart = null;
let distributionChart = null;

const filters = ref({
    supplier_id: null,
    status: '',
    date_filter_type: 'all',
    start_date: null,
    end_date: null,
    group_by: 'day'
});

const pagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0
});

const hasData = computed(() => {
    const rows = (reportData.value || []).length > 0;
    const dimensionRows = (dimensionData.value.supplier_summary || []).length > 0 || (dimensionData.value.status_summary || []).length > 0;
    return rows || dimensionRows || Number(summary.value.total_orders || 0) > 0;
});

const summaryStats = computed(() => ({
    total_orders: {
        label: t('total_orders'),
        value: summary.value.total_orders || 0,
        icon: ShoppingCart,
        format: 'number'
    },
    total_spend: {
        label: t('total_spend'),
        value: summary.value.total_spend || 0,
        icon: Coin,
        format: 'currency'
    },
    average_order_value: {
        label: t('average_order_value'),
        value: summary.value.average_order_value || 0,
        icon: TrendCharts,
        format: 'currency'
    },
    total_tax: {
        label: t('total_tax'),
        value: summary.value.total_tax || 0,
        icon: Wallet,
        format: 'currency'
    },
    total_discount: {
        label: t('total_discount'),
        value: summary.value.total_discount || 0,
        icon: PriceTag,
        format: 'currency'
    },
    pending_orders: {
        label: t('pending_orders'),
        value: summary.value.pending_orders || 0,
        icon: Document,
        format: 'number'
    },
    completed_orders: {
        label: t('completed_orders'),
        value: summary.value.completed_orders || 0,
        icon: PieChart,
        format: 'number'
    },
    cancelled_orders: {
        label: t('cancelled_orders'),
        value: summary.value.cancelled_orders || 0,
        icon: Warning,
        format: 'number'
    }
}));

const goBack = () => {
    router.push({ name: 'admin.purchases.index' });
};

const getFilterParams = () => {
    const params = {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        date_filter_type: filters.value.date_filter_type || 'all'
    };

    if (filters.value.supplier_id) params.supplier_id = filters.value.supplier_id;
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.group_by) params.group_by = filters.value.group_by;

    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay());

    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);

    switch (filters.value.date_filter_type) {
        case 'today':
            params.date = formatDateForAPI(today);
            break;
        case 'yesterday':
            params.date = formatDateForAPI(yesterday);
            break;
        case 'this_week':
            params.start_date = formatDateForAPI(startOfWeek);
            params.end_date = formatDateForAPI(today);
            break;
        case 'this_month':
            params.start_date = formatDateForAPI(startOfMonth);
            params.end_date = formatDateForAPI(endOfMonth);
            break;
        case 'last_month':
            params.start_date = formatDateForAPI(lastMonthStart);
            params.end_date = formatDateForAPI(lastMonthEnd);
            break;
        case 'custom':
            if (filters.value.start_date) params.start_date = formatDateForAPI(filters.value.start_date);
            if (filters.value.end_date) params.end_date = formatDateForAPI(filters.value.end_date);
            break;
    }

    return params;
};

const formatDateForAPI = (date) => date.toISOString().split('T')[0];

const applyFilters = () => {
    pagination.value.current_page = 1;
    loadReport();
    loadTopSuppliers();
};

const resetFilters = () => {
    filters.value = {
        supplier_id: null,
        status: '',
        date_filter_type: 'all',
        start_date: null,
        end_date: null,
        group_by: 'day'
    };
    pagination.value.current_page = 1;
    loadReport();
    loadTopSuppliers();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = getFilterParams();
        const response = await api.get('/admin/reports/purchases', { params });

        if (response.data.success && response.data.data) {
            reportData.value = Array.isArray(response.data.data.purchase_orders) ? response.data.data.purchase_orders : [];
            summary.value = response.data.data.summary || {};
            pagination.value = response.data.data.pagination || { current_page: 1, per_page: 20, total: 0 };

            await loadSummaryCharts();
            await loadDimensionSummary();
            await loadPerformanceMetrics();
            await loadProductSpend();
        } else {
            reportData.value = [];
            summary.value = {};
            pagination.value = { current_page: 1, per_page: 20, total: 0 };
        }
    } catch (error) {
        console.error('Failed to load purchase report:', error);
        ElMessage.error(t('failed_to_load_report'));
        reportData.value = [];
        summary.value = {};
        pagination.value = { current_page: 1, per_page: 20, total: 0 };
    } finally {
        loading.value = false;
    }
};

const loadSummaryCharts = async () => {
    const params = getFilterParams();
    delete params.page;
    delete params.per_page;

    try {
        const response = await api.get('/admin/reports/purchases/summary', { params });
        if (response.data.success && response.data.data) {
            await nextTick();
            updateCharts(response.data.data.summary || [], response.data.data.group_by || 'day');
        }
    } catch (error) {
        console.error('Failed to load purchase summary charts:', error);
    }
};

const loadDimensionSummary = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/purchases/dimensions', { params });
        if (response.data.success && response.data.data) {
            dimensionData.value = {
                supplier_summary: response.data.data.supplier_summary || [],
                status_summary: response.data.data.status_summary || []
            };
        } else {
            dimensionData.value = { supplier_summary: [], status_summary: [] };
        }
    } catch (error) {
        console.error('Failed to load purchase dimensions:', error);
        dimensionData.value = { supplier_summary: [], status_summary: [] };
    }
};

const loadPerformanceMetrics = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/purchases/performance', { params });
        if (response.data.success && response.data.data) {
            performanceData.value = {
                summary: response.data.data.summary || {},
                supplier_summary: response.data.data.supplier_summary || []
            };
        } else {
            performanceData.value = { summary: {}, supplier_summary: [] };
        }
    } catch (error) {
        console.error('Failed to load purchase performance:', error);
        performanceData.value = { summary: {}, supplier_summary: [] };
    }
};

const loadProductSpend = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/purchases/product-spend', { params });
        if (response.data.success && response.data.data) {
            productSpendData.value = {
                summary: response.data.data.summary || {},
                product_summary: response.data.data.product_summary || []
            };
        } else {
            productSpendData.value = { summary: {}, product_summary: [] };
        }
    } catch (error) {
        console.error('Failed to load product spend:', error);
        productSpendData.value = { summary: {}, product_summary: [] };
    }
};

const loadTopSuppliers = async () => {
    loadingTopSuppliers.value = true;
    try {
        const params = getFilterParams();
        delete params.group_by;
        delete params.page;
        delete params.per_page;
        delete params.supplier_id;
        delete params.status;

        const response = await api.get('/admin/reports/purchases/top-suppliers', { params });
        topSuppliers.value = response.data.success && Array.isArray(response.data.data) ? response.data.data : [];
    } catch (error) {
        console.error('Failed to load top suppliers:', error);
        topSuppliers.value = [];
    } finally {
        loadingTopSuppliers.value = false;
    }
};

const updateCharts = (summaryData, groupBy) => {
    if (!summaryData || !Array.isArray(summaryData)) return;

    const labels = summaryData.map(item => {
        switch (groupBy) {
            case 'supplier': return item.supplier_name || 'Unknown';
            case 'status': return item.status_text || item.status || 'Unknown';
            default: return item.date || item.period || 'Unknown';
        }
    });

    const spendData = summaryData.map(item => item.total_spend || 0);
    const ordersData = summaryData.map(item => item.total_orders || 0);

    if (purchasesChartRef.value) {
        if (purchasesChart) purchasesChart.dispose();
        purchasesChart = echarts.init(purchasesChartRef.value);

        purchasesChart.setOption({
            tooltip: { trigger: 'axis' },
            legend: { data: [t('total_spend'), t('orders_count')] },
            xAxis: { type: 'category', data: labels },
            yAxis: { type: 'value' },
            series: [
                {
                    name: t('total_spend'),
                    type: 'line',
                    data: spendData,
                    smooth: true,
                    areaStyle: { color: 'rgba(102, 126, 234, 0.1)' },
                    itemStyle: { color: '#667eea' }
                },
                {
                    name: t('orders_count'),
                    type: 'line',
                    data: ordersData,
                    smooth: true,
                    areaStyle: { color: 'rgba(240, 147, 251, 0.1)' },
                    itemStyle: { color: '#f093fb' }
                }
            ]
        });
    }

    if (distributionChartRef.value) {
        if (distributionChart) distributionChart.dispose();
        distributionChart = echarts.init(distributionChartRef.value);

        distributionChart.setOption({
            tooltip: { trigger: 'item' },
            legend: { orient: 'vertical', right: 10 },
            series: [
                {
                    name: t('total_spend'),
                    type: 'pie',
                    radius: '50%',
                    data: labels.map((label, index) => ({ value: spendData[index], name: label })),
                    emphasis: {
                        itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' }
                    }
                }
            ]
        });
    }
};

const handlePageChange = (page) => {
    pagination.value.current_page = page;
    loadReport();
};

const handleSizeChange = (size) => {
    pagination.value.per_page = size;
    pagination.value.current_page = 1;
    loadReport();
};

const exportReport = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/purchases/export', { params, responseType: 'blob' });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `purchases-report-${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Failed to export purchase report:', error);
        ElMessage.error(t('failed_to_export_report'));
    }
};

const formatValue = (value, format) => {
    if (format === 'currency') return formatCurrency(value);
    if (format === 'percent') return formatPercentage(value);
    return formatNumber(value);
};

const formatCurrency = (value) => formatMoney(value);

const formatPercentage = (value) => `${Number(value || 0).toFixed(2)}%`;

const formatNumber = (value) => formatCount(value);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('ar-SA');
};

const getStatusType = (status) => {
    const types = {
        pending: 'info',
        confirmed: 'warning',
        processing: 'primary',
        completed: 'success',
        cancelled: 'danger'
    };
    return types[status] || 'info';
};

const getStatusText = (status) => {
    const texts = {
        pending: t('sales_status_pending'),
        confirmed: t('sales_status_confirmed'),
        processing: t('sales_status_processing'),
        completed: t('completed'),
        cancelled: t('sales_status_cancelled')
    };
    return texts[status] || status;
};

const getRankType = (index) => {
    if (index === 0) return 'danger';
    if (index === 1) return 'warning';
    if (index === 2) return 'success';
    return 'info';
};

onMounted(() => {
    suppliersStore.fetchSuppliers().catch(() => {});
    loadReport();
    loadTopSuppliers();
});
</script>

<style scoped>
.reports-page {
    padding: 0;
}

.secondary-action {
    border: 1px solid #dfe7f1;
    color: #334155;
    background: #fff;
}

.mini-metric {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.2rem 0;
}

.mini-metric span {
    font-size: 0.82rem;
    color: #64748b;
}

.mini-metric strong {
    font-size: 1.15rem;
    color: #0f172a;
}

.mini-metric small {
    font-size: 0.78rem;
    color: #94a3b8;
}

.profit-positive {
    color: #16a34a;
}

.profit-negative {
    color: #dc2626;
}

.empty-alert {
    margin-bottom: 1.5rem;
    border-radius: 0.9rem;
}

.stat-card {
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(255,255,255,0.96), rgba(248,250,252,0.96));
    border: 1px solid #edf2f7;
    overflow: hidden;
}

.skeleton-card {
    min-height: 116px;
}

.stat-card-total_orders { background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(96,165,250,0.16)); }
.stat-card-total_spend { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(52,211,153,0.16)); }
.stat-card-average_order_value { background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(252,165,165,0.18)); }
.stat-card-total_tax { background: linear-gradient(135deg, rgba(14,165,233,0.08), rgba(125,211,252,0.18)); }
.stat-card-total_discount { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(251,191,36,0.18)); }
.stat-card-pending_orders { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.18)); }
.stat-card-completed_orders { background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(134,239,172,0.18)); }
.stat-card-cancelled_orders { background: linear-gradient(135deg, rgba(220,38,38,0.08), rgba(252,165,165,0.18)); }

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
    background: rgba(102, 126, 234, 0.10);
    color: #4f46e5;
    font-size: 1.5rem;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.5rem;
    color: #1f2937;
}

.stat-info p {
    margin: 0.25rem 0 0;
    color: #6b7c98;
    font-size: 0.9rem;
}

.charts-section {
    margin-bottom: 1.5rem;
}

.table-card {
    border-radius: 1rem;
    margin-bottom: 1.5rem;
}

.top-performers-card {
    border-radius: 1rem;
}
</style>
