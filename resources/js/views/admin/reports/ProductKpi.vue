<template>
    <div class="product-kpi-page">
        <div class="page-header">
            <div class="page-title">
                <div class="title-badge">KPI</div>
                <h1>لوحة KPI المنتجات</h1>
                <p>تحليل الربحية والإيراد والتكلفة لكل منتج حسب المستودع</p>
            </div>
            <div class="toolbar-actions">
                <el-button :icon="Refresh" @click="resetFilters" class="secondary-action">
                    إعادة تعيين
                </el-button>
                <el-button :icon="ArrowLeft" @click="goBack">
                    رجوع
                </el-button>
            </div>
        </div>

        <el-card shadow="hover" class="filter-panel">
            <template #header>
                <div class="card-header">
                    <span>خيارات التصفية</span>
                    <el-button type="primary" :icon="Search" @click="applyFilters">
                        تطبيق
                    </el-button>
                </div>
            </template>

            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>الموظف</label>
                        <el-select v-model="filters.employee_id" placeholder="الكل" clearable style="width: 100%">
                            <el-option v-for="employee in employees" :key="employee.id" :label="employee.name" :value="employee.id" />
                        </el-select>
                    </div>
                </el-col>

                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>العميل</label>
                        <el-select v-model="filters.customer_id" placeholder="الكل" clearable style="width: 100%">
                            <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id" />
                        </el-select>
                    </div>
                </el-col>

                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>المستودع</label>
                        <el-select v-model="filters.warehouse_id" placeholder="الكل" clearable style="width: 100%">
                            <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                        </el-select>
                    </div>
                </el-col>

                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>المنتج</label>
                        <el-select v-model="filters.product_id" placeholder="الكل" clearable style="width: 100%">
                            <el-option v-for="product in products" :key="product.id" :label="product.name || product.name_en || product.name_ar" :value="product.id" />
                        </el-select>
                    </div>
                </el-col>

                <el-col :xs="24" :sm="12" :md="6">
                    <div class="form-group">
                        <label>نوع التاريخ</label>
                        <el-select v-model="filters.date_filter_type" style="width: 100%">
                            <el-option label="الكل" value="all" />
                            <el-option label="اليوم" value="today" />
                            <el-option label="أمس" value="yesterday" />
                            <el-option label="هذا الأسبوع" value="this_week" />
                            <el-option label="هذا الشهر" value="this_month" />
                            <el-option label="الشهر الماضي" value="last_month" />
                            <el-option label="مخصص" value="custom" />
                        </el-select>
                    </div>
                </el-col>
            </el-row>

            <el-row v-if="filters.date_filter_type === 'custom'" :gutter="20" style="margin-top: 15px">
                <el-col :xs="24" :sm="12">
                    <div class="form-group">
                        <label>من</label>
                        <el-date-picker v-model="filters.start_date" type="date" style="width: 100%" />
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12">
                    <div class="form-group">
                        <label>إلى</label>
                        <el-date-picker v-model="filters.end_date" type="date" style="width: 100%" />
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <el-row v-if="loading" :gutter="20" class="summary-cards">
            <el-col v-for="n in 4" :key="n" :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card skeleton-card">
                    <el-skeleton :rows="2" animated />
                </el-card>
            </el-col>
        </el-row>

        <el-row v-else :gutter="20" class="summary-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-card-revenue">
                    <div class="stat-content">
                        <div class="stat-icon"><component :is="Coin" /></div>
                        <div class="stat-info">
                            <h3>{{ formatCurrency(summary.total_revenue || 0) }}</h3>
                            <p>إجمالي الإيراد</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-card-cost">
                    <div class="stat-content">
                        <div class="stat-icon"><component :is="PriceTag" /></div>
                        <div class="stat-info">
                            <h3>{{ formatCurrency(summary.total_cost || 0) }}</h3>
                            <p>إجمالي التكلفة</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-card-profit">
                    <div class="stat-content">
                        <div class="stat-icon"><component :is="TrendCharts" /></div>
                        <div class="stat-info">
                            <h3>{{ formatCurrency(summary.gross_profit || 0) }}</h3>
                            <p>إجمالي الربح</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-card-margin">
                    <div class="stat-content">
                        <div class="stat-icon"><component :is="PieChart" /></div>
                        <div class="stat-info">
                            <h3>{{ formatPercentage(summary.gross_margin || 0) }}</h3>
                            <p>هامش الربح</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>أعلى منتج ربحًا</span>
                        <strong>{{ summary.top_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(summary.top_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>أقل منتج ربحًا</span>
                        <strong>{{ summary.lowest_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(summary.lowest_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>عدد المنتجات</span>
                        <strong>{{ summary.product_count || 0 }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>المستودعات المساهمة</span>
                        <strong>{{ uniqueWarehouses.length }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card v-if="!loading && hasRows" shadow="hover" class="chart-card">
            <template #header>
                <div class="card-header">
                    <span>مخطط الربح حسب المنتج</span>
                </div>
            </template>
            <div ref="productChartRef" class="profit-chart" />
        </el-card>

        <el-card v-else-if="!loading" shadow="hover" class="chart-card empty-state-card">
            <el-empty :description="'لا توجد بيانات للربح حسب المنتج في النطاق الحالي'" />
        </el-card>

        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>تفاصيل ربحية المنتجات</span>
                </div>
            </template>

            <el-table v-if="hasRows" :data="sortedRows" stripe style="width: 100%" v-loading="loading">
                <el-table-column prop="product_name" label="المنتج" />
                <el-table-column prop="warehouse_name" label="المستودع" />
                <el-table-column prop="quantity" label="الكمية" />
                <el-table-column label="الإيراد">
                    <template #default="{ row }">{{ formatCurrency(row.total_revenue) }}</template>
                </el-table-column>
                <el-table-column label="التكلفة">
                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column label="الربح">
                    <template #default="{ row }">
                        <strong :class="row.gross_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatCurrency(row.gross_profit) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column label="الهامش">
                    <template #default="{ row }">{{ formatPercentage(row.gross_margin) }}</template>
                </el-table-column>
            </el-table>

            <el-empty v-else :description="'لا توجد منتجات في هذا النطاق'" />
        </el-card>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft, Refresh, Search, Coin, PriceTag, TrendCharts, PieChart } from '@element-plus/icons-vue';
import api from '@/api';
import * as echarts from 'echarts';

const DEFAULT_SUMMARY = Object.freeze({
    total_revenue: 0,
    total_cost: 0,
    gross_profit: 0,
    gross_margin: 0,
    product_count: 0,
    top_product: null,
    lowest_product: null,
});

const router = useRouter();
const loading = ref(false);
const employees = ref([]);
const customers = ref([]);
const warehouses = ref([]);
const products = ref([]);
const rows = ref([]);
const productChartRef = ref(null);
let productChart = null;
const summary = ref({ ...DEFAULT_SUMMARY });

const filters = ref({
    employee_id: null,
    customer_id: null,
    warehouse_id: null,
    product_id: null,
    date_filter_type: 'all',
    start_date: null,
    end_date: null,
});

const normalizeArray = (value) => Array.isArray(value) ? value : [];
const safeNumber = (value) => Number(value ?? 0);
const getProductName = (product) => product?.name || product?.name_en || product?.name_ar || 'غير محدد';
const buildEmptySummary = () => ({ ...DEFAULT_SUMMARY });

const uniqueWarehouses = computed(() => {
    const warehouseMap = new Map();

    rows.value.forEach((item) => {
        if (item?.warehouse_id && item?.warehouse_name) {
            warehouseMap.set(item.warehouse_id, item.warehouse_name);
        }
    });

    return [...warehouseMap.values()];
});

const sortedRows = computed(() => {
    return [...rows.value].sort((a, b) => safeNumber(b.gross_profit) - safeNumber(a.gross_profit));
});

const hasRows = computed(() => sortedRows.value.length > 0);

const topChartRows = computed(() => [...sortedRows.value].slice(0, 10));

const resizeHandler = () => {
    if (productChart && productChartRef.value) {
        productChart.resize();
    }
};

const updateProductProfitChart = async () => {
    await nextTick();

    if (!productChartRef.value) {
        return;
    }

    if (productChart) {
        productChart.dispose();
        productChart = null;
    }

    productChart = echarts.init(productChartRef.value);

    const labels = topChartRows.value.map(item => getProductName(item.product));
    const values = topChartRows.value.map(item => safeNumber(item.gross_profit));

    productChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const point = params[0];
                return `${point.axisValue}<br/>الربح: ${formatCurrency(point.value)}`;
            }
        },
        grid: {
            left: 12,
            right: 18,
            top: 20,
            bottom: 40,
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {
                rotate: 25,
                formatter: (value) => value.length > 10 ? `${value.slice(0, 10)}...` : value
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: (value) => formatCurrency(value)
            }
        },
        series: [{
            data: values,
            type: 'bar',
            barWidth: '48%',
            itemStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#34d399' },
                    { offset: 1, color: '#10b981' }
                ])
            },
            label: {
                show: true,
                position: 'top',
                formatter: (params) => formatCurrency(params.value)
            }
        }],
        noDataOption: {
            title: {
                text: 'لا توجد بيانات',
                left: 'center',
                top: 'center'
            }
        }
    });

    window.removeEventListener('resize', resizeHandler);
    window.addEventListener('resize', resizeHandler);
};

const goBack = () => {
    router.push({ name: 'admin.reports.index' });
};

const loadEmployees = async () => {
    try {
        const response = await api.get('/admin/employees');
        const items = normalizeArray(response?.data?.data?.employees ?? response?.data?.data);
        employees.value = items;
    } catch (error) {
        console.error('Failed to load employees:', error);
        employees.value = [];
    }
};

const loadCustomers = async () => {
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 100 } });
        const items = normalizeArray(response?.data?.data?.customers ?? response?.data?.data);
        customers.value = items.map(item => item?.data || item);
    } catch (error) {
        console.error('Failed to load customers:', error);
        customers.value = [];
    }
};

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        const items = normalizeArray(response?.data?.data ?? response?.data?.data?.data);
        warehouses.value = items;
    } catch (error) {
        console.error('Failed to load warehouses:', error);
        warehouses.value = [];
    }
};

const loadProducts = async () => {
    try {
        const response = await api.get('/products', { params: { per_page: 100 } });
        const items = normalizeArray(response?.data?.data?.products ?? response?.data?.data ?? response?.data?.products);
        products.value = items;
    } catch (error) {
        console.error('Failed to load products:', error);
        products.value = [];
    }
};

const getFilterParams = () => {
    const params = {
        date_filter_type: filters.value.date_filter_type || 'all',
    };

    if (filters.value.employee_id) params.employee_id = filters.value.employee_id;
    if (filters.value.customer_id) params.customer_id = filters.value.customer_id;
    if (filters.value.warehouse_id) params.warehouse_id = filters.value.warehouse_id;
    if (filters.value.product_id) params.product_id = filters.value.product_id;

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
            params.date = formatDateForApi(today);
            break;
        case 'yesterday':
            params.date = formatDateForApi(yesterday);
            break;
        case 'this_week':
            params.start_date = formatDateForApi(startOfWeek);
            params.end_date = formatDateForApi(today);
            break;
        case 'this_month':
            params.start_date = formatDateForApi(startOfMonth);
            params.end_date = formatDateForApi(endOfMonth);
            break;
        case 'last_month':
            params.start_date = formatDateForApi(lastMonthStart);
            params.end_date = formatDateForApi(lastMonthEnd);
            break;
        case 'custom':
            if (filters.value.start_date) params.start_date = formatDateForApi(filters.value.start_date);
            if (filters.value.end_date) params.end_date = formatDateForApi(filters.value.end_date);
            break;
    }

    return params;
};

const formatDateForApi = (date) => {
    return new Date(date).toISOString().split('T')[0];
};

const loadProductKpi = async () => {
    loading.value = true;
    try {
        const response = await api.get('/admin/reports/sales/product-profitability', { params: getFilterParams() });

        if (response.data.success && response.data.data) {
            rows.value = normalizeArray(response.data.data.product_summary);
            summary.value = response.data.data.summary || buildEmptySummary();
        } else {
            rows.value = [];
            summary.value = buildEmptySummary();
        }

        await updateProductProfitChart();
    } catch (error) {
        console.error('Failed to load product KPI:', error);
        rows.value = [];
        summary.value = buildEmptySummary();
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    loadProductKpi();
};

const resetFilters = () => {
    filters.value = {
        employee_id: null,
        customer_id: null,
        warehouse_id: null,
        product_id: null,
        date_filter_type: 'all',
        start_date: null,
        end_date: null,
    };
    loadProductKpi();
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(safeNumber(value));
};

const formatPercentage = (value) => {
    return `${safeNumber(value).toFixed(2)}%`;
};

watch(
    () => topChartRows.value,
    () => {
        updateProductProfitChart();
    },
    { deep: true }
);

onMounted(() => {
    loadEmployees();
    loadCustomers();
    loadWarehouses();
    loadProducts();
    loadProductKpi();
});

onBeforeUnmount(() => {
    if (productChart) {
        productChart.dispose();
        productChart = null;
    }

    window.removeEventListener('resize', resizeHandler);
});
</script>

<style scoped>
.product-kpi-page {
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
    flex-wrap: wrap;
}

.secondary-action {
    border: 1px solid #dfe7f1;
    color: #334155;
    background: #fff;
}

.page-title {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.title-badge {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(45,212,191,0.12));
    color: #0f766e;
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
    color: #5f6d85;
}

.filter-panel {
    margin-bottom: 1.5rem;
    border-radius: 1rem;
    border: 1px solid #eef3f8;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #303133;
}

.summary-cards {
    margin-bottom: 1.5rem;
}

.stat-card {
    border-radius: 1rem;
    overflow: hidden;
    border: 1px solid #edf2f7;
}

.stat-card-revenue { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(52,211,153,0.16)); }
.stat-card-cost { background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(96,165,250,0.16)); }
.stat-card-profit { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.18)); }
.stat-card-margin { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(251,191,36,0.18)); }

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
    background: rgba(15, 23, 42, 0.05);
    color: #0f172a;
    font-size: 1.5rem;
}

.stat-info h3 {
    margin: 0 0 0.25rem;
    font-size: 1.25rem;
    color: #0f172a;
}

.stat-info p {
    margin: 0;
    color: #64748b;
    font-size: 0.82rem;
}

.mini-metric {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.2rem 0;
}

.mini-metric span,
.mini-metric small {
    color: #64748b;
}

.mini-metric strong {
    font-size: 1.15rem;
    color: #0f172a;
}

.profit-positive {
    color: #16a34a;
}

.profit-negative {
    color: #dc2626;
}

.chart-card {
    margin-bottom: 1.5rem;
    border-radius: 1rem;
}

.empty-state-card {
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profit-chart {
    width: 100%;
    height: 360px;
}

.table-card {
    border-radius: 1rem;
}

.skeleton-card {
    min-height: 116px;
}
</style>
