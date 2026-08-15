<template>
    <div class="reports-inventory">
        <AdminPageHeader
            badge="WMS"
            :title="$t('inventory_report')"
            :subtitle="`${$t('inventory_report')} — ${$t('summary_of_reports')}`"
        >
            <template #actions>
                <el-button :icon="RefreshRight" @click="resetInventoryFilters" class="secondary-action">
                    {{ $t('reset') }}
                </el-button>
                <el-button type="primary" :icon="Download" @click="exportInventoryReport">
                    {{ $t('export') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('warehouse') }}</label>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('all')" clearable @change="loadInventoryDimensions">
                    <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('product') }}</label>
                <el-select v-model="filters.product_id" :placeholder="$t('all')" clearable @change="loadInventoryDimensions">
                    <el-option v-for="product in products" :key="product.id" :label="product.name || product.name_ar || product.name_en" :value="product.id" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('period') }}</label>
                <el-select v-model="filters.date_filter_type" :placeholder="$t('all')" clearable @change="loadInventoryDimensions">
                    <el-option :label="$t('all')" :value="'all'" />
                    <el-option :label="$t('today')" :value="'today'" />
                    <el-option :label="$t('yesterday')" :value="'yesterday'" />
                    <el-option :label="$t('this_week')" :value="'this_week'" />
                    <el-option :label="$t('this_month')" :value="'this_month'" />
                    <el-option :label="$t('last_month')" :value="'last_month'" />
                    <el-option :label="$t('custom')" :value="'custom'" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('date_from') }}</label>
                <el-date-picker v-model="filters.start_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" @change="loadInventoryDimensions" />
            </div>
            <div class="filter-field">
                <label>{{ $t('to') }}</label>
                <el-date-picker v-model="filters.end_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" @change="loadInventoryDimensions" />
            </div>
        </AdminFilterBar>

        <el-alert
            v-if="hasInventoryData === false"
            :title="$t('no_data_for_current_filters')"
            type="info"
            :closable="false"
            show-icon
            class="empty-alert"
        />

        <div v-if="loading" class="empty-state">
            <el-skeleton :rows="6" animated />
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
                <el-col :xs="24" :lg="14">
                    <el-card shadow="hover">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('highest_products_in_terms_of_quantity') }}</span>
                                <el-tag type="success">{{ topProducts.length }}</el-tag>
                            </div>
                        </template>
                        <div ref="stockChartRef" class="chart-box"></div>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="10">
                    <el-card shadow="hover">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('stock_health') }}</span>
                            </div>
                        </template>
                        <div ref="stockHealthChartRef" class="chart-box chart-box-sm"></div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" class="bottom-grid">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('warehouse_summary') }}</span>
                            </div>
                        </template>

                        <el-table :data="dimensionData.warehouse_summary || []" stripe style="width: 100%">
                            <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                            <el-table-column prop="total_quantity" :label="$t('quantity')">
                                <template #default="{ row }">{{ formatNumber(row.total_quantity) }}</template>
                            </el-table-column>
                            <el-table-column prop="total_available" :label="$t('available')">
                                <template #default="{ row }">{{ formatNumber(row.total_available) }}</template>
                            </el-table-column>
                            <el-table-column prop="total_value" :label="$t('value')">
                                <template #default="{ row }">{{ formatCurrency(row.total_value) }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="12">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('grand_total') }}</span>
                            </div>
                        </template>

                        <div class="status-list">
                            <div class="status-item">
                                <div>
                                    <span>{{ $t('total_quantity') }}</span>
                                </div>
                                <strong>{{ formatNumber(dimensionData.overall?.total_quantity || 0) }}</strong>
                            </div>
                            <div class="status-item">
                                <div>
                                    <span>{{ $t('total_available') }}</span>
                                </div>
                                <strong>{{ formatNumber(dimensionData.overall?.total_available || 0) }}</strong>
                            </div>
                            <div class="status-item">
                                <div>
                                    <span>{{ $t('stock_value') }}</span>
                                </div>
                                <strong>{{ formatCurrency(dimensionData.overall?.total_value || 0) }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" class="bottom-grid">
                <el-col :xs="24" :lg="16">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('low_stock_alerts') }}</span>
                                <el-tag type="danger">{{ lowStockProducts.length }}</el-tag>
                            </div>
                        </template>

                        <el-table :data="lowStockProducts" stripe style="width: 100%">
                            <el-table-column prop="name" :label="$t('product')" min-width="150" />
                            <el-table-column prop="sku" :label="'SKU'" width="110" />
                            <el-table-column prop="stock_quantity" :label="$t('current_stock')" width="120" />
                            <el-table-column prop="min_stock" :label="$t('minimum')" width="120" />
                            <el-table-column :label="$t('status')" width="110">
                                <template #default="{ row }">
                                    <el-tag type="danger">{{ $t('low') }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

                <el-col :xs="24" :lg="8">
                    <el-card shadow="hover" class="table-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ $t('top_products') }}</span>
                            </div>
                        </template>

                        <div class="top-products-list">
                            <div v-for="product in topProducts.slice(0, 5)" :key="product.id" class="top-product-item">
                                <div class="product-badge">#{{ product.rank }}</div>
                                <div class="product-info">
                                    <h4>{{ product.name }}</h4>
                                    <span>{{ product.stock_quantity }} {{ $t('unit') }}</span>
                                </div>
                                <strong>{{ formatCurrency(product.price) }}</strong>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </template>
    </div>
</template>

<script setup>
import { formatMoney, formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Box, Goods, Warning, Coin, TrendCharts, Download, RefreshRight } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import api from '@/api';
import { dashboardApi } from '@/api/dashboard';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const loading = ref(true);
const overview = ref({
    products: { total: 0, in_stock: 0, low_stock: 0, featured: 0, active: 0 },
    top_products: [],
    low_stock_products: []
});
const warehouses = ref([]);
const products = ref([]);
const dimensionData = ref({ warehouse_summary: [], overall: { total_quantity: 0, total_available: 0, total_value: 0 } });
const filters = ref({ warehouse_id: null, product_id: null, date_filter_type: 'all', start_date: null, end_date: null });

const stockChartRef = ref(null);
const stockHealthChartRef = ref(null);
let stockChart = null;
let stockHealthChart = null;

const topProducts = computed(() =>
    (overview.value.top_products || []).map((item, index) => ({
        ...item,
        id: item.id,
        name: item.name_ar || item.name_en || 'Product',
        price: Number(item.price || 0),
        stock_quantity: Number(item.stock_quantity || 0),
        rank: index + 1,
    }))
);

const lowStockProducts = computed(() =>
    (overview.value.low_stock_products || []).map((item) => ({
        id: item.id,
        name: item.name_ar || item.name_en || 'Product',
        sku: item.sku || '-',
        stock_quantity: Number(item.stock_quantity || 0),
        min_stock: Number(item.min_stock || 0)
    }))
);

const hasInventoryData = computed(() => {
    const summaryHasRows = (dimensionData.value.warehouse_summary || []).length > 0;
    const overviewHasRows = (overview.value.top_products || []).length > 0 || (overview.value.low_stock_products || []).length > 0;
    return summaryHasRows || overviewHasRows || Number(overview.value.products?.total || 0) > 0;
});

const summaryStats = computed(() => ({
    total_products: {
        label: t('total_products'),
        value: formatNumber(overview.value.products?.total || 0),
        icon: Box
    },
    in_stock: {
        label: t('in_stock_at_warehouse'),
        value: formatNumber(overview.value.products?.in_stock || 0),
        icon: Goods
    },
    low_stock: {
        label: t('low_stock'),
        value: formatNumber(overview.value.products?.low_stock || overview.value.low_stock_products?.length || 0),
        icon: Warning
    },
    inventory_value: {
        label: t('stock_value'),
        value: formatCurrency(getInventoryValue()),
        icon: Coin
    }
}));

const getInventoryValue = () =>
    topProducts.value.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.stock_quantity || 0)), 0);

// Report figures are quoted whole; cents on a stock valuation are noise.
const formatCurrency = (value) => formatMoney(value, { decimals: 0 });

const formatNumber = (value) => formatCount(value);

const exportInventoryReport = async () => {
    try {
        // Goes through the api client so the export carries the bearer token too.
        const response = await api.get('/admin/reports/inventory/export', {
            params: getDimensionParams(),
            responseType: 'blob'
        });

        downloadCsv(response.data, 'inventory-report');
    } catch (error) {
        console.error('Failed to export inventory report:', error);
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

    if (filters.value.warehouse_id) params.warehouse_id = filters.value.warehouse_id;
    if (filters.value.product_id) params.product_id = filters.value.product_id;
    if (filters.value.date_filter_type) params.date_filter_type = filters.value.date_filter_type;
    if (filters.value.start_date) params.start_date = formatDateForApi(filters.value.start_date);
    if (filters.value.end_date) params.end_date = formatDateForApi(filters.value.end_date);

    return params;
};

const loadInventoryDimensions = async () => {
    try {
        const response = await api.get('/admin/reports/inventory/dimensions', { params: getDimensionParams() });

        if (response.data.success) {
            dimensionData.value = {
                warehouse_summary: response.data.data.warehouse_summary || [],
                overall: response.data.data.overall || { total_quantity: 0, total_available: 0, total_value: 0 }
            };
        }
    } catch (error) {
        console.error('Failed to load inventory dimensions:', error);
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

const loadProducts = async () => {
    try {
        const response = await api.get('/products', { params: { per_page: 100 } });
        if (response.data && Array.isArray(response.data.data)) {
            products.value = response.data.data;
        } else if (response.data && response.data.data && Array.isArray(response.data.data.products)) {
            products.value = response.data.data.products;
        }
    } catch (error) {
        console.error('Failed to load products:', error);
    }
};

const renderStockChart = async () => {
    if (!stockChartRef.value) return;
    if (stockChart) stockChart.dispose();
    stockChart = echarts.init(stockChartRef.value);

    const labels = topProducts.value.slice(0, 6).map((product) => product.name);
    const data = topProducts.value.slice(0, 6).map((product) => product.stock_quantity);

    stockChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: 10, right: 12, top: 16, bottom: 30, containLabel: true },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: { rotate: 20, fontSize: 10 }
        },
        yAxis: { type: 'value' },
        series: [{
            data,
            type: 'bar',
            barWidth: '42%',
            itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#60a5fa' },
                    { offset: 1, color: '#2563eb' }
                ])
            },
            radius: [8, 8, 0, 0]
        }]
    });
};

const renderStockHealthChart = async () => {
    if (!stockHealthChartRef.value) return;
    if (stockHealthChart) stockHealthChart.dispose();
    stockHealthChart = echarts.init(stockHealthChartRef.value);

    const inStock = Number(overview.value.products?.in_stock || 0);
    const lowStock = Number(overview.value.products?.low_stock || lowStockProducts.value.length || 0);
    const remaining = Math.max(0, (overview.value.products?.total || 0) - inStock - lowStock);

    stockHealthChart.setOption({
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
            type: 'pie',
            radius: ['55%', '75%'],
            center: ['50%', '42%'],
            data: [
                { value: inStock, name: t('in_stock'), itemStyle: { color: '#22c55e' } },
                { value: lowStock, name: t('low'), itemStyle: { color: '#f59e0b' } },
                { value: remaining, name: t('subject_other'), itemStyle: { color: '#94a3b8' } }
            ],
            label: { show: false }
        }]
    });
};

async function loadOverview() {
    loading.value = true;
    try {
        const res = await dashboardApi.getOverviewStats();
        overview.value = res.data.data || overview.value;
        await nextTick();
        renderStockChart();
        renderStockHealthChart();
    } catch (e) {
        console.error('Failed to load inventory overview', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadOverview();
    loadWarehouses();
    loadProducts();
    loadInventoryDimensions();
    window.addEventListener('resize', () => {
        stockChart?.resize();
        stockHealthChart?.resize();
    });
});
</script>

<style scoped>
.reports-inventory {
    padding: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
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
    background: linear-gradient(135deg, rgba(59,130,246,0.14), rgba(96,165,250,0.18));
    color: #2563eb;
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
    border: 1px solid #eef2f7;
}

.stat-card-total_products { background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(96,165,250,0.18)); }
.stat-card-in_stock { background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(74,222,128,0.18)); }
.stat-card-low_stock { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(253,224,71,0.16)); }
.stat-card-inventory_value { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.16)); }

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
    background: rgba(79, 70, 229, 0.12);
    color: #2563eb;
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

.table-card {
    border-radius: 1rem;
}

.top-products-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 0.25rem 0;
}

.top-product-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.75rem 0.9rem;
    border-radius: 12px;
    background: #f8fafc;
}

.product-badge {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1d4ed8;
    font-weight: 700;
    font-size: 0.8rem;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-info h4 {
    margin: 0;
    font-size: 0.9rem;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-info span {
    display: block;
    margin-top: 0.2rem;
    color: #64748b;
    font-size: 0.78rem;
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
