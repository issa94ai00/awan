<template>
    <div class="reports-page professional-sales">
        <AdminPageHeader
            icon="fas fa-chart-line"
            badge="CRM"
            :title="$t('professional_sales_reports')"
            :subtitle="$t('advanced_sales_filtering_and_analytics')"
        >
            <template #actions>
                <el-button :icon="Refresh" @click="resetFilters">
                    {{ $t('reset') }}
                </el-button>
                <!-- One button that exports whichever tab is open. It never said
                     which, so naming the dataset is the difference between an
                     export and a guess. -->
                <el-button type="primary" :icon="Download" :loading="exporting" @click="exportActiveTab">
                    {{ $t('export_report') }} — {{ activeTabLabel }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminFilterBar>
            <div class="filter-field">
                <label>{{ $t('employee') }}</label>
                <el-select v-model="filters.employee_id" :placeholder="$t('all_employees')" clearable filterable>
                    <el-option v-for="employee in employees" :key="employee.id" :label="employee.name" :value="employee.id" />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('customer') }}</label>
                <el-select v-model="filters.customer_id" :placeholder="$t('all_customers')" clearable filterable>
                    <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id" />
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
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('start_date') }}</label>
                <el-date-picker v-model="filters.start_date" type="date" :placeholder="$t('start_date')" />
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('end_date') }}</label>
                <el-date-picker v-model="filters.end_date" type="date" :placeholder="$t('end_date')" />
            </div>

            <template #advanced>
                <div class="filter-field">
                    <label>{{ $t('warehouse') }}</label>
                    <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable filterable>
                        <el-option v-for="warehouse in warehouses" :key="warehouse.id" :label="warehouse.name" :value="warehouse.id" />
                    </el-select>
                </div>
                <!-- Labelled generically: this one filter feeds both tabs, and
                     orders and invoices share the same status vocabulary. -->
                <div class="filter-field">
                    <label>{{ $t('status') }}</label>
                    <el-select v-model="filters.status" :placeholder="$t('all_statuses')" clearable>
                        <el-option v-for="status in ORDER_STATUSES" :key="status" :label="getStatusText(status)" :value="status" />
                    </el-select>
                </div>
                <div class="filter-field">
                    <label>{{ $t('group_by') }}</label>
                    <el-select v-model="filters.group_by">
                        <el-option-group :label="$t('chart_group_trend')">
                            <el-option :label="$t('daily')" value="day" />
                            <el-option :label="$t('weekly')" value="week" />
                            <el-option :label="$t('monthly')" value="month" />
                        </el-option-group>
                        <el-option-group :label="$t('chart_group_breakdown')">
                            <el-option :label="$t('by_employee')" value="employee" />
                            <el-option :label="$t('by_customer')" value="customer" />
                            <el-option :label="$t('by_warehouse')" value="warehouse" />
                            <el-option :label="$t('by_status')" value="status" />
                        </el-option-group>
                    </el-select>
                </div>
            </template>

            <template #actions>
                <el-button type="primary" :icon="Search" @click="applyFilters">
                    {{ $t('apply_filters') }}
                </el-button>
            </template>
        </AdminFilterBar>

        <el-tabs v-model="activeTab" class="report-tabs">
            <el-tab-pane :label="$t('invoices')" name="invoices">
                <el-alert
                    v-if="!invoicesLoading && !hasInvoicesData"
                    :title="$t('no_data_for_current_filters')"
                    type="info"
                    :closable="false"
                    show-icon
                    class="empty-alert"
                />

                <SalesReportPanel
                    :loading="invoicesLoading"
                    :stat-cards="invoiceStatCards"
                    :metrics="invoicePerformanceData.summary"
                    :chart-mode="invoicesChartMode"
                    :chart-title="invoicesChartTitle"
                    :chart-note="$t('chart_unavailable_for_invoices')"
                    :chart-labels="invoicesChartLabels"
                    :chart-values="invoicesChartValues"
                    :dimension-data="invoiceDimensionData"
                    dimension-value-key="total_invoiced"
                    :profitability="invoiceProductProfitabilityData"
                />

                <el-card shadow="hover" class="table-card">
                    <!-- Both tabs used to head this card with a bare "detailed
                         report", which named neither what was listed nor how much
                         of it the filters had matched. -->
                    <template #header>
                        <div class="table-card-header">
                            <span>{{ $t('detailed_report') }} — {{ $t('invoices') }}</span>
                            <span v-if="invoicePagination.total" class="table-card-count">
                                {{ formatCount(invoicePagination.total) }}
                            </span>
                        </div>
                    </template>

                    <el-table v-loading="invoicesListLoading" :data="invoiceReportData" style="width: 100%" stripe highlight-current-row>
                        <!-- The identifier is the way into the record. Only the
                             number is a link, rather than the whole row: the sole
                             detail screen these have is the edit form, and a
                             stray click on a report row should not land in it. -->
                        <el-table-column :label="$t('invoice_number')" width="130">
                            <template #default="{ row }">
                                <router-link class="record-link" :to="`/admin/sales/invoices/${row.id}/edit`">
                                    {{ row.invoice_number }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('source_order')" width="120">
                            <template #default="{ row }">
                                <router-link
                                    v-if="row.sales_order"
                                    class="record-link"
                                    :to="`/admin/sales/sales-orders/${row.sales_order.id}/edit`"
                                >
                                    {{ row.sales_order.order_number }}
                                </router-link>
                                <span v-else class="table-sub-note">{{ $t('direct_sale') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('date')" width="120">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('customer')">
                            <template #default="{ row }">{{ row.customer ? row.customer.name : '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('employee')">
                            <template #default="{ row }">{{ row.assigned_employee ? row.assigned_employee.name : '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)" size="small">{{ getStatusText(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('total')" width="120">
                            <template #default="{ row }"><strong>{{ formatMoney(row.total) }}</strong></template>
                        </el-table-column>
                        <el-table-column :label="$t('paid_amount')" width="120">
                            <template #default="{ row }">{{ formatMoney(row.paid_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('due_amount')" width="120">
                            <template #default="{ row }">
                                <strong :class="Number(row.due_amount) > 0 ? 'profit-negative' : 'profit-positive'">
                                    {{ formatMoney(row.due_amount) }}
                                </strong>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-if="invoicePagination.total > 0"
                        v-model:current-page="invoicePagination.current_page"
                        v-model:page-size="invoicePagination.per_page"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="invoicePagination.total"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="table-pagination"
                        @size-change="handleInvoiceSizeChange"
                        @current-change="handleInvoicePageChange"
                    />
                </el-card>

                <TopPerformersTable
                    :title="$t('top_performing_employees')"
                    :loading="loadingInvoiceTopPerformers"
                    :rows="invoiceTopPerformers"
                    count-key="total_invoices"
                    :count-label="$t('invoices_count')"
                    average-key="average_invoice_value"
                    :average-label="$t('average_invoice_value')"
                />
            </el-tab-pane>

            <el-tab-pane :label="$t('sales_orders_pipeline')" name="orders">
                <el-alert
                    v-if="!ordersLoading && !hasOrdersData"
                    :title="$t('no_data_for_current_filters')"
                    type="info"
                    :closable="false"
                    show-icon
                    class="empty-alert"
                />

                <SalesReportPanel
                    :loading="ordersLoading"
                    :stat-cards="orderStatCards"
                    :metrics="performanceData.summary"
                    :chart-mode="ordersChartMode"
                    :chart-title="ordersChartTitle"
                    :chart-labels="ordersChartLabels"
                    :chart-values="ordersChartValues"
                    :dimension-data="dimensionData"
                    dimension-value-key="total_sales"
                    :profitability="productProfitabilityData"
                />

                <el-card shadow="hover" class="table-card">
                    <template #header>
                        <div class="table-card-header">
                            <span>{{ $t('detailed_report') }} — {{ $t('sales_orders_pipeline') }}</span>
                            <span v-if="pagination.total" class="table-card-count">
                                {{ formatCount(pagination.total) }}
                            </span>
                        </div>
                    </template>

                    <el-table v-loading="ordersListLoading" :data="reportData" style="width: 100%" stripe highlight-current-row>
                        <el-table-column :label="$t('order_number')" width="120">
                            <template #default="{ row }">
                                <router-link class="record-link" :to="`/admin/sales/sales-orders/${row.id}/edit`">
                                    {{ row.order_number }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('date')" width="120">
                            <template #default="{ row }">{{ formatDate(row.order_date) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('customer')">
                            <template #default="{ row }">{{ row.customer ? row.customer.name : '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('employee')">
                            <template #default="{ row }">{{ row.assigned_employee ? row.assigned_employee.name : '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)" size="small">{{ getStatusText(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('subtotal')">
                            <template #default="{ row }">{{ formatMoney(row.subtotal) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('total')" width="120">
                            <template #default="{ row }"><strong>{{ formatMoney(row.total) }}</strong></template>
                        </el-table-column>
                        <el-table-column :label="$t('invoiced')" width="150">
                            <template #default="{ row }">
                                <el-tag :type="invoiceCoverageType(row)" size="small">{{ invoiceCoverageText(row) }}</el-tag>
                                <p v-if="Number(row.invoices_count) > 0" class="table-sub-note">{{ formatMoney(row.invoiced_total) }}</p>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-if="pagination.total > 0"
                        v-model:current-page="pagination.current_page"
                        v-model:page-size="pagination.per_page"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="pagination.total"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="table-pagination"
                        @size-change="handleOrdersSizeChange"
                        @current-change="handleOrdersPageChange"
                    />
                </el-card>

                <TopPerformersTable
                    :title="$t('top_performing_employees')"
                    :loading="loadingTopPerformers"
                    :rows="topPerformers"
                    count-key="total_orders"
                    :count-label="$t('total_orders')"
                    average-key="average_order_value"
                    :average-label="$t('average_order_value')"
                />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { formatMoney as formatMoneyWith } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Search, Refresh, Download, ShoppingCart, Coin, PriceTag, PieChart, TrendCharts, Document, Wallet, Warning } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import SalesReportPanel from '@/components/admin/reports/SalesReportPanel.vue';
import TopPerformersTable from '@/components/admin/reports/TopPerformersTable.vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

/* ------------------------------------------------------------------ *
 * Reference data
 * ------------------------------------------------------------------ */
const employees = ref([]);
const customers = ref([]);
const warehouses = ref([]);

const loadEmployees = async () => {
    try {
        const response = await api.get('/admin/employees');
        employees.value = response.data?.success ? (response.data.data?.employees || []) : [];
    } catch (error) {
        employees.value = [];
    }
};

const loadCustomers = async () => {
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 100 } });
        const rows = response.data?.data?.customers;
        customers.value = Array.isArray(rows) ? rows.map((item) => item.data || item) : [];
    } catch (error) {
        customers.value = [];
    }
};

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        const data = response.data;
        warehouses.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data?.data?.data) ? data.data.data : []);
    } catch (error) {
        warehouses.value = [];
    }
};

/* ------------------------------------------------------------------ *
 * Filters — date_filter_type presets (today/this_week/this_month/…) are
 * resolved server-side (SalesReportController::applyDateFilters), against
 * the server's own clock. The client used to recompute the same boundaries
 * itself and send explicit start/end dates for every preset — meaning "this
 * week" could disagree with the server's answer across a timezone
 * difference, for no benefit, since the server already does this correctly.
 * Only 'custom' has dates the server cannot derive on its own.
 * ------------------------------------------------------------------ */
const filters = reactive({
    employee_id: null,
    customer_id: null,
    warehouse_id: null,
    status: '',
    date_filter_type: 'all',
    start_date: null,
    end_date: null,
    group_by: 'day',
});

const toApiDate = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    return d.toISOString().split('T')[0];
};

const baseFilterParams = () => {
    const params = { date_filter_type: filters.date_filter_type || 'all' };
    if (filters.employee_id) params.employee_id = filters.employee_id;
    if (filters.customer_id) params.customer_id = filters.customer_id;
    if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
    if (filters.status) params.status = filters.status;

    if (filters.date_filter_type === 'custom') {
        if (filters.start_date) params.start_date = toApiDate(filters.start_date);
        if (filters.end_date) params.end_date = toApiDate(filters.end_date);
    }

    return params;
};

/* ------------------------------------------------------------------ *
 * Tabs — lazily loaded. The invoices tab used to fire five requests on
 * mount alongside the orders tab's five, whether or not anyone ever opened
 * it; it now loads once, the first time it's actually opened, and again
 * only when a filter change has made it stale.
 * ------------------------------------------------------------------ */
const TABS = ['invoices', 'orders'];

/**
 * Invoices open first: they are the money actually billed, which is what this
 * report is consulted for. The pipeline is the leading indicator you go to
 * afterwards.
 *
 * The choice still lives in the URL, so the tab survives a refresh and either
 * view can be linked to or bookmarked — without that, making invoices the
 * default would have left the pipeline with no address at all.
 */
const activeTab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'invoices');
const ordersStale = ref(true);
const invoicesStale = ref(true);

// Each loader gets its own counter, bumped every time that loader starts —
// a loader that resolves after a newer call of *itself* has already started
// discards its result instead of overwriting it, guarding against a slow
// response from an old filter clobbering the current one when Apply is
// clicked more than once in quick succession.
//
// This used to be one counter shared by all four loaders. loadOrdersTab()
// runs loadOrdersList() and loadOrdersExtras() concurrently via Promise.all,
// and each bumped the same counter at its own start — so whichever grabbed
// the lower number found the shared counter had already moved on by the
// time it resolved, and its `finally` block's `token === requestToken`
// guard never matched. That block is the only place ordersListLoading gets
// set back to false, so every load left the stat cards showing their
// loading skeleton forever, even after the data had arrived.
let ordersListToken = 0;
let ordersExtrasToken = 0;
let invoicesListToken = 0;
let invoicesExtrasToken = 0;

/* ------------------------------------------------------------------ *
 * Orders tab
 * ------------------------------------------------------------------ */
const ordersListLoading = ref(false);
const ordersExtrasLoading = ref(false);
const ordersLoading = computed(() => ordersListLoading.value || ordersExtrasLoading.value);

const reportData = ref([]);
const summary = ref({});
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const summaryChart = ref({ rows: [], group_by: 'day' });
const dimensionData = ref({ employee_summary: [], customer_summary: [], warehouse_summary: [] });
const performanceData = ref({ summary: null });
const productProfitabilityData = ref({ summary: null, product_summary: [] });
const topPerformers = ref([]);
const loadingTopPerformers = ref(false);

const hasOrdersData = computed(() => {
    const rows = reportData.value.length > 0;
    const dims = ['employee_summary', 'customer_summary', 'warehouse_summary']
        .some((key) => (dimensionData.value[key] || []).length > 0);
    return rows || dims || Number(summary.value.total_orders || 0) > 0;
});

const orderStatCards = computed(() => [
    { key: 'total_orders', label: t('total_orders'), value: summary.value.total_orders || 0, icon: ShoppingCart, format: 'number' },
    { key: 'total_revenue', label: t('total_revenue'), value: performanceData.value.summary?.total_revenue ?? summary.value.total_sales ?? 0, icon: Coin, format: 'currency' },
    { key: 'total_cost', label: t('cost_of_goods'), value: performanceData.value.summary?.total_cost ?? 0, icon: PriceTag, format: 'currency' },
    { key: 'gross_margin', label: t('profit_margin'), value: performanceData.value.summary?.gross_margin ?? 0, icon: PieChart, format: 'percent' },
    { key: 'average_order_value', label: t('average_order_value'), value: summary.value.average_order_value || 0, icon: TrendCharts, format: 'currency' },
    { key: 'invoiced_orders', label: t('invoiced_orders'), value: summary.value.invoiced_orders || 0, icon: Document, format: 'number' },
    { key: 'uninvoiced_amount', label: t('uninvoiced_amount'), value: summary.value.uninvoiced_amount || 0, icon: Warning, format: 'currency' },
]);

const TREND_GROUPINGS = ['day', 'week', 'month'];

const ordersChartMode = computed(() => (TREND_GROUPINGS.includes(filters.group_by) ? 'trend' : 'bar'));

const ordersChartTitle = computed(() => {
    const labels = {
        day: t('sales_by_day'), week: t('sales_by_week'), month: t('sales_by_month'),
        employee: t('by_employee'), customer: t('by_customer'), warehouse: t('by_warehouse'), status: t('by_status'),
    };
    return labels[filters.group_by] || t('sales_by_period');
});

const summaryLabel = (row, groupBy) => {
    switch (groupBy) {
        case 'employee': return row.employee_name || t('undefined');
        case 'customer': return row.customer_name || t('undefined');
        case 'warehouse': return row.warehouse_name || t('undefined');
        case 'status': return row.status_text || row.status || t('undefined');
        case 'week': return row.year && row.week !== undefined ? `${row.year}-W${row.week}` : '-';
        case 'month': return row.year && row.month ? `${row.year}-${String(row.month).padStart(2, '0')}` : '-';
        default: return row.date || '-';
    }
};

const ordersChartLabels = computed(() => summaryChart.value.rows.map((row) => summaryLabel(row, summaryChart.value.group_by)));
const ordersChartValues = computed(() => summaryChart.value.rows.map((row) => Number(row.total_sales) || 0));

const invoiceCoverageType = (row) => {
    if (Number(row.invoices_count || 0) === 0) return 'info';
    return Number(row.invoiced_total || 0) >= Number(row.total || 0) ? 'success' : 'warning';
};

const invoiceCoverageText = (row) => {
    if (Number(row.invoices_count || 0) === 0) return t('not_invoiced');
    return Number(row.invoiced_total || 0) >= Number(row.total || 0) ? t('fully_invoiced') : t('partially_invoiced');
};

/** Just the paginated list — used for page/size changes, which don't need
 *  the charts, dimensions or performance figures re-fetched. */
const loadOrdersList = async () => {
    ordersListLoading.value = true;
    const token = ++ordersListToken;
    try {
        const response = await api.get('/admin/reports/sales', {
            params: { ...baseFilterParams(), page: pagination.current_page, per_page: pagination.per_page },
        });
        if (token !== ordersListToken) return;
        const data = response.data?.data;
        reportData.value = Array.isArray(data?.sales_orders) ? data.sales_orders : [];
        summary.value = data?.summary || {};
        Object.assign(pagination, data?.pagination || { current_page: 1, per_page: 20, total: 0 });
    } catch (error) {
        if (token !== ordersListToken) return;
        ElMessage.error(t('failed_to_load_report'));
        reportData.value = [];
        summary.value = {};
    } finally {
        if (token === ordersListToken) ordersListLoading.value = false;
    }
};

const loadOrdersExtras = async () => {
    ordersExtrasLoading.value = true;
    const token = ++ordersExtrasToken;
    const params = baseFilterParams();

    try {
        const [chartRes, dimRes, perfRes, profitRes, topRes] = await Promise.all([
            api.get('/admin/reports/sales/summary', { params: { ...params, group_by: filters.group_by } }),
            api.get('/admin/reports/sales/dimensions', { params }),
            api.get('/admin/reports/sales/performance', { params }),
            api.get('/admin/reports/sales/product-profitability', { params }),
            api.get('/admin/reports/sales/top-performers', { params }),
        ]);
        if (token !== ordersExtrasToken) return;

        summaryChart.value = {
            rows: Array.isArray(chartRes.data?.data?.summary) ? chartRes.data.data.summary : [],
            group_by: chartRes.data?.data?.group_by || filters.group_by,
        };
        dimensionData.value = {
            employee_summary: dimRes.data?.data?.employee_summary || [],
            customer_summary: dimRes.data?.data?.customer_summary || [],
            warehouse_summary: dimRes.data?.data?.warehouse_summary || [],
        };
        performanceData.value = { summary: perfRes.data?.data?.summary || null };
        productProfitabilityData.value = {
            summary: profitRes.data?.data?.summary || null,
            product_summary: profitRes.data?.data?.product_summary || [],
        };
        topPerformers.value = Array.isArray(topRes.data?.data) ? topRes.data.data : [];
    } catch (error) {
        if (token !== ordersExtrasToken) return;
        summaryChart.value = { rows: [], group_by: filters.group_by };
        dimensionData.value = { employee_summary: [], customer_summary: [], warehouse_summary: [] };
        performanceData.value = { summary: null };
        productProfitabilityData.value = { summary: null, product_summary: [] };
        topPerformers.value = [];
    } finally {
        if (token === ordersExtrasToken) ordersExtrasLoading.value = false;
    }
};

const loadOrdersTab = async () => {
    await Promise.all([loadOrdersList(), loadOrdersExtras()]);
    ordersStale.value = false;
};

const handleOrdersPageChange = (page) => { pagination.current_page = page; loadOrdersList(); };
const handleOrdersSizeChange = (size) => { pagination.per_page = size; pagination.current_page = 1; loadOrdersList(); };

/* ------------------------------------------------------------------ *
 * Invoices tab
 * ------------------------------------------------------------------ */
const invoicesListLoading = ref(false);
const invoicesExtrasLoading = ref(false);
const invoicesLoading = computed(() => invoicesListLoading.value || invoicesExtrasLoading.value);

const invoiceReportData = ref([]);
const invoiceSummary = ref({});
const invoicePagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const invoiceDimensionData = ref({ employee_summary: [], customer_summary: [], warehouse_summary: [] });
const invoicePerformanceData = ref({ summary: null });
const invoiceProductProfitabilityData = ref({ summary: null, product_summary: [] });
const invoiceTopPerformers = ref([]);
const loadingInvoiceTopPerformers = ref(false);

const hasInvoicesData = computed(() => {
    const rows = invoiceReportData.value.length > 0;
    const dims = ['employee_summary', 'customer_summary', 'warehouse_summary']
        .some((key) => (invoiceDimensionData.value[key] || []).length > 0);
    return rows || dims || Number(invoiceSummary.value.total_invoices || 0) > 0;
});

const invoiceStatCards = computed(() => [
    { key: 'total_invoices', label: t('invoices_count'), value: invoiceSummary.value.total_invoices || 0, icon: Document, format: 'number' },
    { key: 'total_invoiced', label: t('total_invoiced'), value: invoiceSummary.value.total_invoiced || 0, icon: Coin, format: 'currency' },
    { key: 'paid_amount', label: t('paid_amount'), value: invoiceSummary.value.paid_amount || 0, icon: Wallet, format: 'currency' },
    { key: 'due_amount', label: t('due_amount'), value: invoiceSummary.value.due_amount || 0, icon: Warning, format: 'currency' },
    { key: 'average_invoice_value', label: t('average_invoice_value'), value: invoiceSummary.value.average_invoice_value || 0, icon: TrendCharts, format: 'currency' },
]);

// Invoices have no day/week/month/status trend endpoint (see
// SalesReportController) — only the three dimensions both tabs share.
const BREAKDOWN_GROUPINGS = ['employee', 'customer', 'warehouse'];

const invoicesChartMode = computed(() => (BREAKDOWN_GROUPINGS.includes(filters.group_by) ? 'bar' : 'none'));

const invoicesChartTitle = computed(() => {
    const labels = { employee: t('by_employee'), customer: t('by_customer'), warehouse: t('by_warehouse') };
    return labels[filters.group_by] || t('distribution_by_criteria');
});

const invoicesChartLabels = computed(() => {
    const key = `${filters.group_by}_summary`;
    const nameKey = { employee: 'employee_name', customer: 'customer_name', warehouse: 'warehouse_name' }[filters.group_by];
    return (invoiceDimensionData.value[key] || []).map((row) => row[nameKey] || t('undefined'));
});

const invoicesChartValues = computed(() => {
    const key = `${filters.group_by}_summary`;
    return (invoiceDimensionData.value[key] || []).map((row) => Number(row.total_invoiced) || 0);
});

const loadInvoicesList = async () => {
    invoicesListLoading.value = true;
    const token = ++invoicesListToken;
    try {
        const response = await api.get('/admin/reports/invoices', {
            params: { ...baseFilterParams(), page: invoicePagination.current_page, per_page: invoicePagination.per_page },
        });
        if (token !== invoicesListToken) return;
        const data = response.data?.data;
        invoiceReportData.value = Array.isArray(data?.invoices) ? data.invoices : [];
        invoiceSummary.value = data?.summary || {};
        Object.assign(invoicePagination, data?.pagination || { current_page: 1, per_page: 20, total: 0 });
    } catch (error) {
        if (token !== invoicesListToken) return;
        ElMessage.error(t('failed_to_load_report'));
        invoiceReportData.value = [];
        invoiceSummary.value = {};
    } finally {
        if (token === invoicesListToken) invoicesListLoading.value = false;
    }
};

const loadInvoicesExtras = async () => {
    invoicesExtrasLoading.value = true;
    const token = ++invoicesExtrasToken;
    const params = baseFilterParams();

    try {
        const [dimRes, perfRes, profitRes, topRes] = await Promise.all([
            api.get('/admin/reports/invoices/dimensions', { params }),
            api.get('/admin/reports/invoices/performance', { params }),
            api.get('/admin/reports/invoices/product-profitability', { params }),
            api.get('/admin/reports/invoices/top-performers', { params }),
        ]);
        if (token !== invoicesExtrasToken) return;

        invoiceDimensionData.value = {
            employee_summary: dimRes.data?.data?.employee_summary || [],
            customer_summary: dimRes.data?.data?.customer_summary || [],
            warehouse_summary: dimRes.data?.data?.warehouse_summary || [],
        };
        invoicePerformanceData.value = { summary: perfRes.data?.data?.summary || null };
        invoiceProductProfitabilityData.value = {
            summary: profitRes.data?.data?.summary || null,
            product_summary: profitRes.data?.data?.product_summary || [],
        };
        invoiceTopPerformers.value = Array.isArray(topRes.data?.data) ? topRes.data.data : [];
    } catch (error) {
        if (token !== invoicesExtrasToken) return;
        invoiceDimensionData.value = { employee_summary: [], customer_summary: [], warehouse_summary: [] };
        invoicePerformanceData.value = { summary: null };
        invoiceProductProfitabilityData.value = { summary: null, product_summary: [] };
        invoiceTopPerformers.value = [];
    } finally {
        if (token === invoicesExtrasToken) invoicesExtrasLoading.value = false;
    }
};

const loadInvoicesTab = async () => {
    await Promise.all([loadInvoicesList(), loadInvoicesExtras()]);
    invoicesStale.value = false;
};

const handleInvoicePageChange = (page) => { invoicePagination.current_page = page; loadInvoicesList(); };
const handleInvoiceSizeChange = (size) => { invoicePagination.per_page = size; invoicePagination.current_page = 1; loadInvoicesList(); };

/* ------------------------------------------------------------------ *
 * Orchestration
 * ------------------------------------------------------------------ */
const loadActiveTab = () => (activeTab.value === 'invoices' ? loadInvoicesTab() : loadOrdersTab());

watch(activeTab, (tab) => {
    if (tab === 'invoices' && invoicesStale.value) loadInvoicesTab();
    if (tab === 'orders' && ordersStale.value) loadOrdersTab();

    // replace, not push: flipping a tab is not a step to be walked back
    // through, and stacking history entries would trap the back button here.
    if (route.query.tab !== tab) {
        router.replace({ query: { ...route.query, tab } });
    }
});

const applyFilters = () => {
    pagination.current_page = 1;
    invoicePagination.current_page = 1;
    ordersStale.value = true;
    invoicesStale.value = true;
    loadActiveTab();
};

const resetFilters = () => {
    Object.assign(filters, {
        employee_id: null, customer_id: null, warehouse_id: null, status: '',
        date_filter_type: 'all', start_date: null, end_date: null, group_by: 'day',
    });
    applyFilters();
};

const exporting = ref(false);
const exportActiveTab = async () => {
    exporting.value = true;
    try {
        const isInvoices = activeTab.value === 'invoices';
        const endpoint = isInvoices ? '/admin/reports/invoices/export' : '/admin/reports/sales/export';
        const response = await api.get(endpoint, { params: baseFilterParams(), responseType: 'blob' });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `${isInvoices ? 'invoice' : 'sales'}-report-${toApiDate(new Date())}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        ElMessage.error(t('failed_to_export_report'));
    } finally {
        exporting.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Formatting helpers
 * ------------------------------------------------------------------ */
const formatMoney = (value) => formatMoneyWith(value || 0);

/** Row counts read as quantities, so they get thousands separators. */
const formatCount = (value) => Number(value || 0).toLocaleString();

/** Names the dataset the export button is about to produce. */
const activeTabLabel = computed(() =>
    activeTab.value === 'invoices' ? t('invoices') : t('sales_orders_pipeline')
);

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('ar-SA');
};

const STATUS_TAG_TYPES = { pending: 'info', confirmed: 'warning', processing: 'primary', shipped: 'success', delivered: 'success', cancelled: 'danger' };
const getStatusType = (status) => STATUS_TAG_TYPES[status] || 'info';
const getStatusText = (status) => t(`sales_status_${status}`);

onMounted(() => {
    loadEmployees();
    loadCustomers();
    loadWarehouses();
    // Whichever tab is actually on screen, not always the orders one: the
    // watcher above only fires on a *change*, so loading the wrong tab here
    // would leave the visible one empty until you clicked away and back.
    loadActiveTab();
});
</script>

<style scoped>
.reports-page {
    padding: 0;
}

.empty-alert {
    margin-bottom: 1.25rem;
    border-radius: 0.9rem;
}

.report-tabs :deep(.el-tabs__content) {
    padding-top: 0.5rem;
}

.table-card {
    border-radius: 1rem;
    margin-top: 1.25rem;
    margin-bottom: 1.25rem;
}

.table-pagination {
    margin-top: 1.25rem;
    justify-content: center;
}

.table-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.table-card-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    border-radius: 999px;
    padding: 0.1rem 0.6rem;
    font-variant-numeric: tabular-nums;
}

/* The way into the underlying order or invoice. */
.record-link {
    color: #2563eb;
    font-weight: 700;
    text-decoration: none;
}

.record-link:hover {
    text-decoration: underline;
}

/* Figures in a report are read down the column, not across the row, so the
   digits have to keep the same width or the decimal points wander. */
.table-card :deep(.el-table) {
    font-variant-numeric: tabular-nums;
}

.table-sub-note {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #94a3b8;
}

.profit-positive { color: #16a34a; }
.profit-negative { color: #dc2626; }
</style>
