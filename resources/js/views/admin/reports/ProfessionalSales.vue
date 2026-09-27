<template>
    <div class="reports-page professional-sales">
        <AdminPageHeader
            icon="fas fa-chart-line"
            badge="CRM"
            :title="$t('professional_sales_reports')"
            :subtitle="$t('advanced_sales_filtering_and_analytics')"
        >
            <template #actions>
                <el-button :icon="Refresh" :disabled="!activeFilterChips.length" @click="resetFilters">
                    {{ $t('reset') }}
                </el-button>
                <!-- One button that exports whichever tab is open. It never said
                     which, so naming the dataset is the difference between an
                     export and a guess. -->
                <el-button type="primary" :icon="Download" :loading="exporting" :disabled="awaitingRange" @click="exportActiveTab">
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
                <el-select
                    v-model="filters.customer_id"
                    :placeholder="$t('all_customers')"
                    clearable
                    filterable
                    remote
                    remote-show-suffix
                    :remote-method="searchCustomers"
                    :loading="customerSearching"
                >
                    <el-option v-for="customer in customers" :key="customer.id" :label="customer.name" :value="customer.id">
                        <span>{{ customer.name }}</span>
                        <span v-if="customer.phone" class="option-hint">{{ customer.phone }}</span>
                    </el-option>
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('sr_period') }}</label>
                <el-select v-model="filters.date_filter_type">
                    <el-option v-for="preset in PERIOD_PRESETS" :key="preset" :label="periodLabel(preset)" :value="preset" />
                </el-select>
            </div>
            <!-- One picker for both ends, with the common spans one click away.
                 Dates travel as plain YYYY-MM-DD strings: the two Date pickers
                 this replaces were sent through toISOString(), which is UTC, so
                 in Damascus the day picked arrived as the day before. -->
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field filter-field--range">
                <label>{{ $t('date_range') }}</label>
                <el-date-picker
                    v-model="customRange"
                    type="daterange"
                    value-format="YYYY-MM-DD"
                    unlink-panels
                    :shortcuts="rangeShortcuts"
                    :start-placeholder="$t('start_date')"
                    :end-placeholder="$t('end_date')"
                    :range-separator="$t('to')"
                />
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

        </AdminFilterBar>

        <!-- What the numbers below are for, in words, with each part one click
             from undone. Filters now apply as they change, and several are set
             from elsewhere — a breakdown row, the collapsed advanced panel, a
             shared link — so the scope has to be readable without opening
             anything. -->
        <div class="active-filters" aria-live="polite">
            <span class="active-filters-label">{{ $t('sr_showing') }}</span>
            <template v-if="activeFilterChips.length">
                <el-tag
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    closable
                    effect="plain"
                    round
                    class="active-filter-chip"
                    @close="clearFilter(chip.key)"
                >
                    <span class="active-filter-name">{{ chip.label }}:</span> {{ chip.value }}
                </el-tag>
                <el-button link type="primary" class="active-filters-clear" @click="resetFilters">
                    {{ $t('clear_all') }}
                </el-button>
            </template>
            <span v-else-if="!awaitingRange" class="active-filters-all">{{ $t('sr_everything') }}</span>
            <span v-if="awaitingRange" class="active-filters-hint">{{ $t('sr_pick_range_hint') }}</span>
            <!-- Old figures stay on screen, dimmed, while new ones load; this
                 says so in words rather than leaving the dimming to explain it. -->
            <span v-else-if="refreshing" class="active-filters-updating">
                <el-icon class="is-loading"><Loading /></el-icon>
                {{ $t('sr_updating') }}
            </span>
            <span class="active-filters-spacer"></span>
            <el-button link type="primary" class="active-filters-clear" @click="setAllCollapsed(!allCollapsed)">
                <el-icon class="collapse-all-icon"><component :is="allCollapsed ? Expand : Fold" /></el-icon>
                {{ allCollapsed ? $t('expand_all') : $t('collapse_all') }}
            </el-button>
        </div>

        <el-tabs v-model="activeTab" class="report-tabs">
            <!-- lazy: a tab's tables and chart are built the first time it is
                 opened, not both on arrival with one of them hidden. -->
            <el-tab-pane :label="$t('invoices')" name="invoices" lazy>
                <el-alert
                    v-if="!invoicesLoading && !hasInvoicesData"
                    :title="$t('no_data_for_current_filters')"
                    type="info"
                    :closable="false"
                    show-icon
                    class="empty-alert"
                />

                <SalesReportPanel
                    :stats-loading="invoicesListLoading"
                    :insights-loading="invoicesInsightsLoading"
                    :chart-loading="invoicesInsightsLoading"
                    :profitability-loading="invoicesProfitLoading"
                    :stat-cards="invoiceStatCards"
                    :metrics="invoicePerformanceData.summary"
                    :chart-mode="invoicesChartMode"
                    :chart-title="invoicesChartTitle"
                    :chart-note="$t('chart_unavailable_for_invoices')"
                    :chart-labels="invoicesChartLabels"
                    :chart-values="invoicesChartValues"
                    :dimension-data="invoiceDimensionData"
                    dimension-value-key="total_invoiced"
                    :dimension-value-label="$t('total_invoiced')"
                    dimension-count-key="total_invoices"
                    :dimension-count-label="$t('count')"
                    dimension-due-key="due_amount"
                    :dimension-due-label="$t('due_amount')"
                    :active-dimensions="activeDimensions"
                    :chart-suggestions="invoiceChartSuggestions"
                    :profitability="invoiceProductProfitabilityData"
                    @select-grouping="applyGrouping"
                    @select-dimension="applyDimensionFilter"
                    @profitability-active="invoicesProfitSection.setActive"
                />

                <!-- Both tabs used to head this card with a bare "detailed
                     report", which named neither what was listed nor how much
                     of it the filters had matched. -->
                <CollapsibleCard
                    id="detail-invoices"
                    :title="`${$t('detailed_report')} — ${$t('invoices')}`"
                    :count="invoicePagination.total || null"
                    class="table-card"
                >

                    <el-table
                        ref="invoiceTableRef"
                        v-loading="invoicesListLoading"
                        :data="invoiceReportData"
                        style="width: 100%"
                        stripe
                        highlight-current-row
                        :row-class-name="invoiceRowClass"
                        @sort-change="handleInvoiceSortChange"
                    >
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
                        <!-- The warehouse breakdown ranks by this and the row was
                             already eager-loaded, but the detail table never
                             showed it — so drilling into a warehouse produced a
                             list with nothing on it naming the warehouse. -->
                        <el-table-column :label="$t('warehouse')" min-width="130">
                            <template #default="{ row }">
                                <span v-if="row.warehouse">{{ row.warehouse.name }}</span>
                                <span v-else class="table-sub-note">{{ $t('unassigned') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)" size="small">{{ getStatusText(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('total')" width="120">
                            <template #default="{ row }">
                                <strong>{{ formatMoney(row.total) }}</strong>
                                <!-- Profit is measured against revenue net of
                                     tax, so a taxed invoice needs to show which
                                     of the two numbers the margin is off. -->
                                <p v-if="Number(row.tax) > 0" class="table-sub-note">
                                    {{ $t('net_of_tax') }} {{ formatMoney(row.net_revenue) }}
                                </p>
                            </template>
                        </el-table-column>

                        <!-- Cost, profit and margin per invoice. The report
                             carried profit only in aggregate and per product,
                             so "which sale actually made money" could not be
                             answered from the document it was made on. -->
                        <el-table-column width="120">
                            <template #header>
                                <el-tooltip :content="$t('invoice_cost_basis_hint')" placement="top">
                                    <span class="hinted-header">
                                        {{ $t('cost_of_goods') }}
                                        <i class="fas fa-circle-info"></i>
                                    </span>
                                </el-tooltip>
                            </template>
                            <template #default="{ row }">
                                <!-- A cost the sale measured and one the report
                                     had to work out from today's catalogue are
                                     different claims, and the figure alone
                                     cannot tell them apart. -->
                                <el-tooltip
                                    v-if="Number(row.estimated_lines) > 0"
                                    :content="$t('estimated_cost_hint', { count: row.estimated_lines, total: row.line_count })"
                                    placement="top"
                                >
                                    <span class="cost-cell is-estimated">≈ {{ formatMoney(row.total_cost) }}</span>
                                </el-tooltip>
                                <span v-else class="cost-cell">{{ formatMoney(row.total_cost) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('gross_profit')"
                            prop="gross_profit"
                            width="140"
                            sortable="custom"
                        >
                            <template #default="{ row }">
                                <strong :class="profitClass(row)">{{ formatMoney(row.gross_profit) }}</strong>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('profit_margin')"
                            prop="gross_margin"
                            width="170"
                            sortable="custom"
                        >
                            <template #default="{ row }">
                                <div class="margin-cell">
                                    <div class="margin-cell-top">
                                        <span :class="profitClass(row)">{{ formatMarginPercent(row.gross_margin) }}</span>
                                        <!-- A margin computed over lines whose
                                             product has no cost on file is not a
                                             high margin, it is an unknown one.
                                             Without this the two look alike. -->
                                        <el-tooltip
                                            v-if="Number(row.uncosted_lines) > 0"
                                            :content="$t('uncosted_lines_hint', { count: row.uncosted_lines, total: row.line_count })"
                                            placement="top"
                                        >
                                            <i class="fas fa-triangle-exclamation margin-warning"></i>
                                        </el-tooltip>
                                    </div>
                                    <div class="margin-bar" :class="profitClass(row)">
                                        <span :style="{ width: marginBarWidth(row.gross_margin) }"></span>
                                    </div>
                                </div>
                            </template>
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
                </CollapsibleCard>

                <TopPerformersTable
                    :title="$t('top_performing_employees')"
                    :loading="loadingInvoiceTopPerformers"
                    :rows="invoiceTopPerformers"
                    count-key="total_invoices"
                    :count-label="$t('invoices_count')"
                    average-key="average_invoice_value"
                    :average-label="$t('average_invoice_value')"
                    @active-change="invoicesTopSection.setActive"
                />
            </el-tab-pane>

            <el-tab-pane :label="$t('sales_orders_pipeline')" name="orders" lazy>
                <el-alert
                    v-if="!ordersLoading && !hasOrdersData"
                    :title="$t('no_data_for_current_filters')"
                    type="info"
                    :closable="false"
                    show-icon
                    class="empty-alert"
                />

                <SalesReportPanel
                    :stats-loading="ordersListLoading"
                    :insights-loading="ordersInsightsLoading"
                    :chart-loading="ordersChartLoading"
                    :profitability-loading="ordersProfitLoading"
                    :stat-cards="orderStatCards"
                    :metrics="performanceData.summary"
                    :chart-mode="ordersChartMode"
                    :chart-title="ordersChartTitle"
                    :chart-labels="ordersChartLabels"
                    :chart-values="ordersChartValues"
                    :dimension-data="dimensionData"
                    dimension-value-key="total_sales"
                    dimension-count-key="total_orders"
                    :dimension-count-label="$t('count')"
                    :active-dimensions="activeDimensions"
                    :profitability="productProfitabilityData"
                    @select-dimension="applyDimensionFilter"
                    @profitability-active="ordersProfitSection.setActive"
                />

                <CollapsibleCard
                    id="detail-orders"
                    :title="`${$t('detailed_report')} — ${$t('sales_orders_pipeline')}`"
                    :count="pagination.total || null"
                    class="table-card"
                >

                    <el-table
                        ref="ordersTableRef"
                        v-loading="ordersListLoading"
                        :data="reportData"
                        style="width: 100%"
                        stripe
                        highlight-current-row
                        :row-class-name="orderRowClass"
                        @sort-change="handleOrdersSortChange"
                    >
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
                            <template #default="{ row }">
                                <strong>{{ formatMoney(row.total) }}</strong>
                                <p v-if="Number(row.tax) > 0" class="table-sub-note">
                                    {{ $t('net_of_tax') }} {{ formatMoney(row.net_revenue) }}
                                </p>
                            </template>
                        </el-table-column>
                        <el-table-column width="120">
                            <template #header>
                                <el-tooltip :content="$t('order_cost_basis_hint')" placement="top">
                                    <span class="hinted-header">
                                        {{ $t('cost_of_goods') }}
                                        <i class="fas fa-circle-info"></i>
                                    </span>
                                </el-tooltip>
                            </template>
                            <template #default="{ row }">
                                <!-- Nothing costs anything until it ships, so on
                                     a pipeline of pending orders this is a
                                     projection far more often than not — which
                                     the figure alone would not admit. -->
                                <el-tooltip
                                    v-if="Number(row.estimated_lines) > 0"
                                    :content="$t('projected_cost_hint', { count: row.estimated_lines, total: row.line_count })"
                                    placement="top"
                                >
                                    <span class="cost-cell is-estimated">≈ {{ formatMoney(row.total_cost) }}</span>
                                </el-tooltip>
                                <span v-else class="cost-cell">{{ formatMoney(row.total_cost) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('gross_profit')"
                            prop="gross_profit"
                            width="140"
                            sortable="custom"
                        >
                            <template #default="{ row }">
                                <strong :class="profitClass(row)">{{ formatMoney(row.gross_profit) }}</strong>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('profit_margin')"
                            prop="gross_margin"
                            width="170"
                            sortable="custom"
                        >
                            <template #default="{ row }">
                                <div class="margin-cell">
                                    <div class="margin-cell-top">
                                        <span :class="profitClass(row)">{{ formatMarginPercent(row.gross_margin) }}</span>
                                        <el-tooltip
                                            v-if="Number(row.uncosted_lines) > 0"
                                            :content="$t('uncosted_lines_hint', { count: row.uncosted_lines, total: row.line_count })"
                                            placement="top"
                                        >
                                            <i class="fas fa-triangle-exclamation margin-warning"></i>
                                        </el-tooltip>
                                    </div>
                                    <div class="margin-bar" :class="profitClass(row)">
                                        <span :style="{ width: marginBarWidth(row.gross_margin) }"></span>
                                    </div>
                                </div>
                            </template>
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
                </CollapsibleCard>

                <TopPerformersTable
                    :title="$t('top_performing_employees')"
                    :loading="loadingTopPerformers"
                    :rows="topPerformers"
                    count-key="total_orders"
                    :count-label="$t('total_orders')"
                    average-key="average_order_value"
                    :average-label="$t('average_order_value')"
                    @active-change="ordersTopSection.setActive"
                />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { formatMoney as formatMoneyWith } from '@/utils/currency';
import { formatDate as formatSalesDate } from '@/utils/sales';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Fold, Expand, Loading, ShoppingCart, Coin, PriceTag, PieChart, TrendCharts, Document, Wallet, Warning } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import SalesReportPanel from '@/components/admin/reports/SalesReportPanel.vue';
import TopPerformersTable from '@/components/admin/reports/TopPerformersTable.vue';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';
import { allCollapsed, setAllCollapsed } from '@/utils/collapsedSections';

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

/**
 * Customers are searched on the server as you type. The list used to be the
 * newest hundred, fetched whole on mount — so anyone older than that could not
 * be picked at all, and every visit paid for a hundred rows to show none.
 */
const customerSearching = ref(false);
let customerSearchToken = 0;
let customerSearchTimer = null;

const fetchCustomers = async (search = '') => {
    const token = ++customerSearchToken;
    customerSearching.value = true;
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 30, ...(search ? { search } : {}) } });
        if (token !== customerSearchToken) return;
        const rows = response.data?.data?.customers;
        const found = Array.isArray(rows) ? rows.map((item) => item.data || item) : [];
        // The chosen customer stays among the options whatever the search
        // returned, or the select would fall back to showing its bare id.
        const selected = customers.value.find((row) => Number(row.id) === Number(filters.customer_id));
        customers.value = selected && !found.some((row) => row.id === selected.id) ? [selected, ...found] : found;
    } catch (error) {
        if (token === customerSearchToken) customers.value = [];
    } finally {
        if (token === customerSearchToken) customerSearching.value = false;
    }
};

const searchCustomers = (query) => {
    clearTimeout(customerSearchTimer);
    customerSearchTimer = setTimeout(() => fetchCustomers(query?.trim() || ''), 250);
};

/** Makes sure a customer set from the URL or a breakdown row has a name to show. */
const rememberCustomer = async (id, name = null) => {
    if (!id || customers.value.some((row) => Number(row.id) === Number(id))) return;
    if (name) {
        customers.value = [{ id: Number(id), name }, ...customers.value];
        return;
    }
    try {
        const response = await api.get(`/pos/customers/${id}`);
        const customer = response.data?.data?.data || response.data?.data;
        if (customer?.id && !customers.value.some((row) => row.id === customer.id)) {
            customers.value = [customer, ...customers.value];
        }
    } catch (error) {
        // The chip falls back to "#id"; the report itself is unaffected.
    }
};

const loadCustomers = async () => {
    await fetchCustomers();
    rememberCustomer(filters.customer_id);
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
const PERIOD_PRESETS = ['all', 'today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];
const GROUPINGS = ['day', 'week', 'month', 'employee', 'customer', 'warehouse', 'status'];

const emptyFilters = () => ({
    employee_id: null,
    customer_id: null,
    warehouse_id: null,
    status: '',
    date_filter_type: 'all',
    start_date: null,
    end_date: null,
    group_by: 'day',
});

/* ------------------------------------------------------------------ *
 * The filters live in the address bar, so a refresh keeps the report on
 * screen and a link hands someone else exactly this view. Short names keep
 * the URL readable; empty filters are left out of it.
 * ------------------------------------------------------------------ */
const URL_KEYS = {
    employee_id: 'employee',
    customer_id: 'customer',
    warehouse_id: 'warehouse',
    status: 'status',
    date_filter_type: 'period',
    start_date: 'from',
    end_date: 'to',
    group_by: 'group',
};

const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));

const filtersFromQuery = (query) => {
    const next = emptyFilters();
    const id = (value) => (/^\d+$/.test(String(value || '')) ? Number(value) : null);
    next.employee_id = id(query.employee);
    next.customer_id = id(query.customer);
    next.warehouse_id = id(query.warehouse);
    if (ORDER_STATUSES.includes(query.status)) next.status = query.status;
    if (PERIOD_PRESETS.includes(query.period)) next.date_filter_type = query.period;
    if (next.date_filter_type === 'custom') {
        if (isIsoDate(query.from)) next.start_date = query.from;
        if (isIsoDate(query.to)) next.end_date = query.to;
    }
    if (GROUPINGS.includes(query.group)) next.group_by = query.group;
    return next;
};

const filtersToQuery = () => {
    const defaults = emptyFilters();
    const query = {};
    for (const [field, key] of Object.entries(URL_KEYS)) {
        const value = filters[field];
        if (value === null || value === '' || value === defaults[field]) continue;
        if ((field === 'start_date' || field === 'end_date') && filters.date_filter_type !== 'custom') continue;
        query[key] = String(value);
    }
    return query;
};

const filters = reactive(filtersFromQuery(route.query));

/** A calendar date as YYYY-MM-DD in local time — not toISOString(), which is UTC. */
const toApiDate = (date) => {
    if (isIsoDate(date)) return date;
    const d = date instanceof Date ? date : new Date(date);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const periodLabel = (preset) => (preset === 'all' ? t('sr_all_time') : t(preset));

/** Both ends of the custom period, as the range picker reads and writes them. */
const customRange = computed({
    get: () => (filters.start_date && filters.end_date ? [filters.start_date, filters.end_date] : null),
    set: (range) => {
        filters.start_date = range?.[0] || null;
        filters.end_date = range?.[1] || null;
    },
});

/** "Custom" is chosen but no dates yet: nothing to report until there are. */
const awaitingRange = computed(() => filters.date_filter_type === 'custom' && !filters.start_date && !filters.end_date);

const daysAgo = (n) => {
    const d = new Date();
    d.setDate(d.getDate() - n);
    return d;
};

const rangeShortcuts = computed(() => [
    { text: t('sr_last_7_days'), value: () => [daysAgo(6), new Date()] },
    { text: t('sr_last_30_days'), value: () => [daysAgo(29), new Date()] },
    { text: t('sr_last_90_days'), value: () => [daysAgo(89), new Date()] },
    {
        text: t('sr_this_quarter'),
        value: () => {
            const now = new Date();
            return [new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1), now];
        },
    },
    {
        text: t('sr_last_year'),
        value: () => {
            const year = new Date().getFullYear() - 1;
            return [new Date(year, 0, 1), new Date(year, 11, 31)];
        },
    },
]);

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

/**
 * Wraps a loader so that only its latest call gets to land. A slow response
 * from an old filter must not overwrite a newer one, and only the latest call
 * may turn the spinner off — or it goes off while the current request is still
 * out. Each loader gets its own counter: a single counter shared between
 * loaders that run side by side once left every one but the last spinning
 * forever, since each saw the counter had moved on.
 *
 * `run` receives `superseded()`, to check after each await.
 */
const latestOnly = (loading, run) => {
    let token = 0;
    return async () => {
        const mine = ++token;
        loading.value = true;
        try {
            await run(() => mine !== token);
        } finally {
            if (mine === token) loading.value = false;
        }
    };
};

/**
 * A section that loads only while it is on screen and open (see
 * CollapsibleCard's `active-change`). A filter change marks it stale; it
 * reloads then if it is showing, or the next time it comes into view.
 *
 * Product profitability and the top performers are the costliest reports on
 * the page and the furthest down it; they were fetched on every filter change
 * whether or not anyone scrolled that far.
 */
const deferredSection = (load) => {
    let active = false;
    let stale = true;
    const run = () => { stale = false; load(); };
    return {
        setActive(value) {
            active = value;
            if (active && stale) run();
        },
        invalidate() {
            stale = true;
            if (active) run();
        },
    };
};

/* ------------------------------------------------------------------ *
 * Orders tab
 * ------------------------------------------------------------------ */
const ordersListLoading = ref(false);
const ordersInsightsLoading = ref(false);
const ordersChartLoading = ref(false);
const ordersProfitLoading = ref(false);
const ordersLoading = computed(() => ordersListLoading.value || ordersInsightsLoading.value);
const ordersChartStale = ref(true);

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

/* ------------------------------------------------------------------ *
 * Per-order profit
 *
 * The same question the invoice tab answers, asked of the pipeline: which
 * orders are worth having. Sorted server-side for the same reason — profit is
 * not a column, and the orders being sold at a loss are never the newest rows.
 * ------------------------------------------------------------------ */
const ordersSort = ref(null);
const ordersTableRef = ref(null);

const handleOrdersSortChange = ({ prop, order }) => {
    const field = SORTABLE_PROPS[prop];
    ordersSort.value = field && order ? `${field}_${order === 'ascending' ? 'asc' : 'desc'}` : null;
    pagination.current_page = 1;
    loadOrdersList();
};

// An order being filled at a loss is the finding these columns exist to
// surface, so it is marked on the row rather than left to be spotted.
const orderRowClass = ({ row }) => (Number(row.gross_profit) < 0 ? 'row-loss' : '');

/** Just the paginated list — its summary feeds the stat cards, and page,
 *  size and sort changes need nothing else re-fetched. */
const loadOrdersList = latestOnly(ordersListLoading, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/sales', {
            params: {
                ...baseFilterParams(),
                page: pagination.current_page,
                per_page: pagination.per_page,
                ...(ordersSort.value ? { sort: ordersSort.value } : {}),
            },
        });
        if (superseded()) return;
        const data = response.data?.data;
        reportData.value = Array.isArray(data?.sales_orders) ? data.sales_orders : [];
        summary.value = data?.summary || {};
        Object.assign(pagination, data?.pagination || { current_page: 1, per_page: 20, total: 0 });
    } catch (error) {
        if (superseded()) return;
        ElMessage.error(t('failed_to_load_report'));
        reportData.value = [];
        summary.value = {};
    }
});

const loadOrdersInsights = latestOnly(ordersInsightsLoading, async (superseded) => {
    const params = baseFilterParams();
    const [dimRes, perfRes] = await Promise.allSettled([
        api.get('/admin/reports/sales/dimensions', { params }),
        api.get('/admin/reports/sales/performance', { params }),
    ]);
    if (superseded()) return;

    // Settled one by one: a failure in one report no longer blanks the other.
    const dims = dimRes.value?.data?.data || {};
    dimensionData.value = {
        employee_summary: dims.employee_summary || [],
        customer_summary: dims.customer_summary || [],
        warehouse_summary: dims.warehouse_summary || [],
    };
    performanceData.value = { summary: perfRes.value?.data?.data?.summary || null };
});

/** The trend is the only request that depends on the grouping, so changing
 *  the grouping re-fetches this and nothing else. */
const loadOrdersChart = latestOnly(ordersChartLoading, async (superseded) => {
    ordersChartStale.value = false;
    try {
        const response = await api.get('/admin/reports/sales/summary', {
            params: { ...baseFilterParams(), group_by: filters.group_by },
        });
        if (superseded()) return;
        summaryChart.value = {
            rows: Array.isArray(response.data?.data?.summary) ? response.data.data.summary : [],
            group_by: response.data?.data?.group_by || filters.group_by,
        };
    } catch (error) {
        if (superseded()) return;
        summaryChart.value = { rows: [], group_by: filters.group_by };
    }
});

const ordersProfitSection = deferredSection(latestOnly(ordersProfitLoading, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/sales/product-profitability', { params: baseFilterParams() });
        if (superseded()) return;
        productProfitabilityData.value = {
            summary: response.data?.data?.summary || null,
            product_summary: response.data?.data?.product_summary || [],
        };
    } catch (error) {
        if (superseded()) return;
        productProfitabilityData.value = { summary: null, product_summary: [] };
    }
}));

const ordersTopSection = deferredSection(latestOnly(loadingTopPerformers, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/sales/top-performers', { params: baseFilterParams() });
        if (superseded()) return;
        topPerformers.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error) {
        if (superseded()) return;
        topPerformers.value = [];
    }
}));

const loadOrdersTab = async () => {
    ordersStale.value = false;
    await Promise.all([loadOrdersList(), loadOrdersInsights(), loadOrdersChart()]);
};

const handleOrdersPageChange = (page) => { pagination.current_page = page; loadOrdersList(); };
const handleOrdersSizeChange = (size) => { pagination.per_page = size; pagination.current_page = 1; loadOrdersList(); };

/* ------------------------------------------------------------------ *
 * Invoices tab
 * ------------------------------------------------------------------ */
const invoicesListLoading = ref(false);
const invoicesInsightsLoading = ref(false);
const invoicesProfitLoading = ref(false);
const invoicesLoading = computed(() => invoicesListLoading.value || invoicesInsightsLoading.value);

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

/**
 * The groupings this tab *can* chart, offered from the empty chart itself.
 *
 * The default grouping is 'day' — a trend, which the invoice report has no
 * series for — so the tab that now opens the page also opens with a blank
 * chart. The note explained that; these make it one click to fix instead of a
 * hunt through the collapsed advanced filters.
 */
const GROUPING_LABELS = { employee: 'by_employee', customer: 'by_customer', warehouse: 'by_warehouse' };

const invoiceChartSuggestions = computed(() =>
    BREAKDOWN_GROUPINGS.map((value) => ({ value, label: t(GROUPING_LABELS[value]) }))
);

const invoicesChartTitle = computed(() => {
    const key = GROUPING_LABELS[filters.group_by];
    return key ? t(key) : t('distribution_by_criteria');
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

/* ------------------------------------------------------------------ *
 * Per-invoice profit
 *
 * The report knew what the whole filtered set earned and what each product
 * earned, but nothing about the document in between — so the invoice that lost
 * money was invisible unless someone re-derived it by hand. Sorting is
 * server-side because profit is not a column: it is revenue net of tax less
 * what the lines cost, and the loss-makers are never the newest rows.
 * ------------------------------------------------------------------ */
const invoiceSort = ref(null);
const invoiceTableRef = ref(null);

const SORTABLE_PROPS = { gross_profit: 'profit', gross_margin: 'margin' };

const handleInvoiceSortChange = ({ prop, order }) => {
    const field = SORTABLE_PROPS[prop];
    invoiceSort.value = field && order ? `${field}_${order === 'ascending' ? 'asc' : 'desc'}` : null;
    invoicePagination.current_page = 1;
    loadInvoicesList();
};

const profitClass = (row) => (Number(row.gross_profit) < 0 ? 'profit-negative' : 'profit-positive');

// An invoice sold below cost is the finding this column exists to surface, so
// it is marked on the row rather than left to be spotted in a column of numbers.
const invoiceRowClass = ({ row }) => (Number(row.gross_profit) < 0 ? 'row-loss' : '');

const formatMarginPercent = (value) => `${Number(value || 0).toFixed(1)}%`;

// Clamped: a margin can exceed 100% only when cost is missing, and can run far
// negative on a bad sale — neither should draw a bar off the end of the cell.
const marginBarWidth = (value) => `${Math.min(Math.abs(Number(value) || 0), 100)}%`;

const loadInvoicesList = latestOnly(invoicesListLoading, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/invoices', {
            params: {
                ...baseFilterParams(),
                page: invoicePagination.current_page,
                per_page: invoicePagination.per_page,
                ...(invoiceSort.value ? { sort: invoiceSort.value } : {}),
            },
        });
        if (superseded()) return;
        const data = response.data?.data;
        invoiceReportData.value = Array.isArray(data?.invoices) ? data.invoices : [];
        invoiceSummary.value = data?.summary || {};
        Object.assign(invoicePagination, data?.pagination || { current_page: 1, per_page: 20, total: 0 });
    } catch (error) {
        if (superseded()) return;
        ElMessage.error(t('failed_to_load_report'));
        invoiceReportData.value = [];
        invoiceSummary.value = {};
    }
});

/** Performance figures and the three breakdowns — which also feed the chart,
 *  so a grouping change on this tab needs no request at all. */
const loadInvoicesInsights = latestOnly(invoicesInsightsLoading, async (superseded) => {
    const params = baseFilterParams();
    const [dimRes, perfRes] = await Promise.allSettled([
        api.get('/admin/reports/invoices/dimensions', { params }),
        api.get('/admin/reports/invoices/performance', { params }),
    ]);
    if (superseded()) return;

    const dims = dimRes.value?.data?.data || {};
    invoiceDimensionData.value = {
        employee_summary: dims.employee_summary || [],
        customer_summary: dims.customer_summary || [],
        warehouse_summary: dims.warehouse_summary || [],
    };
    invoicePerformanceData.value = { summary: perfRes.value?.data?.data?.summary || null };
});

const invoicesProfitSection = deferredSection(latestOnly(invoicesProfitLoading, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/invoices/product-profitability', { params: baseFilterParams() });
        if (superseded()) return;
        invoiceProductProfitabilityData.value = {
            summary: response.data?.data?.summary || null,
            product_summary: response.data?.data?.product_summary || [],
        };
    } catch (error) {
        if (superseded()) return;
        invoiceProductProfitabilityData.value = { summary: null, product_summary: [] };
    }
}));

const invoicesTopSection = deferredSection(latestOnly(loadingInvoiceTopPerformers, async (superseded) => {
    try {
        const response = await api.get('/admin/reports/invoices/top-performers', { params: baseFilterParams() });
        if (superseded()) return;
        invoiceTopPerformers.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error) {
        if (superseded()) return;
        invoiceTopPerformers.value = [];
    }
}));

const loadInvoicesTab = async () => {
    invoicesStale.value = false;
    await Promise.all([loadInvoicesList(), loadInvoicesInsights()]);
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
    else if (tab === 'orders' && ordersChartStale.value) loadOrdersChart();

    // replace, not push: flipping a tab is not a step to be walked back
    // through, and stacking history entries would trap the back button here.
    if (route.query.tab !== tab) {
        router.replace({ query: { ...route.query, tab } });
    }
});

const DEFERRED_SECTIONS = [ordersProfitSection, ordersTopSection, invoicesProfitSection, invoicesTopSection];

/** The filters that decide which documents are counted — everything but the
 *  chart grouping, which only changes how they are drawn. */
const scopeKey = () => JSON.stringify(baseFilterParams());
let appliedScope = null;

const applyFilters = () => {
    appliedScope = scopeKey();
    pagination.current_page = 1;
    invoicePagination.current_page = 1;
    ordersStale.value = true;
    invoicesStale.value = true;
    loadActiveTab();
    // Below the fold, these reload only if they are actually showing.
    DEFERRED_SECTIONS.forEach((section) => section.invalidate());
};

/** A new grouping re-draws the chart and leaves every other figure alone.
 *  The invoice chart is built from breakdowns already loaded; only the orders
 *  trend has to be asked for again. */
const applyGroupingChange = () => {
    ordersChartStale.value = true;
    if (activeTab.value === 'orders') loadOrdersChart();
};

/**
 * Filters apply as they change: there used to be an Apply button, and a
 * report showing the old numbers under freshly changed filters looked like an
 * answer to the new question. Changes are gathered for a moment, so clearing
 * two chips or stepping through a select is one reload, not several.
 */
let filterReloadTimer = null;
watch(
    () => ({ ...filters }),
    () => {
        clearTimeout(filterReloadTimer);
        filterReloadTimer = setTimeout(() => {
            const query = { ...filtersToQuery(), ...(route.query.tab ? { tab: route.query.tab } : {}) };
            router.replace({ query });
            // A custom period with no dates yet would report on everything —
            // the opposite of what picking "custom" asked for.
            if (awaitingRange.value) return;
            if (scopeKey() !== appliedScope) applyFilters();
            else applyGroupingChange();
        }, 300);
    },
);

/** Anything on the open tab still on its way, for the "updating" note. */
const refreshing = computed(() => (activeTab.value === 'invoices'
    ? invoicesLoading.value || invoicesProfitLoading.value || loadingInvoiceTopPerformers.value
    : ordersLoading.value || ordersChartLoading.value || ordersProfitLoading.value || loadingTopPerformers.value));

/** Switch grouping from the empty chart's own suggestion. */
const applyGrouping = (grouping) => {
    filters.group_by = grouping;
};

/** Which breakdown row each panel should show as the current scope. */
const activeDimensions = computed(() => ({
    employee: filters.employee_id,
    customer: filters.customer_id,
    warehouse: filters.warehouse_id,
}));

/**
 * Drill into one row of a breakdown.
 *
 * The breakdown could say a warehouse had billed 884.88 across six invoices
 * but not which six. The filters that answer that already existed — they were
 * just in the collapsed advanced panel, to be found and set by hand. Clicking
 * the row sets the same filter, so the detail table underneath becomes exactly
 * the documents behind the number that was clicked.
 */
const DIMENSION_FILTERS = { employee: 'employee_id', customer: 'customer_id', warehouse: 'warehouse_id' };

const applyDimensionFilter = ({ type, id, name }) => {
    const field = DIMENSION_FILTERS[type];
    if (!field) return;
    // Customers are searched, not preloaded, so the one clicked may not be
    // among the select's options yet — the breakdown row already has its name.
    if (type === 'customer' && id) rememberCustomer(id, name);

    filters[field] = id ?? null;
};

const resetFilters = () => {
    // The chart grouping is a way of looking, not part of the scope: it stays.
    Object.assign(filters, { ...emptyFilters(), group_by: filters.group_by });
    invoiceSort.value = null;
    ordersSort.value = null;
    // The header arrow is the table's own state; clearing ours would otherwise
    // leave it pointing at a sort no longer being applied.
    invoiceTableRef.value?.clearSort();
    ordersTableRef.value?.clearSort();
};

/* ------------------------------------------------------------------ *
 * Active filter chips
 * ------------------------------------------------------------------ */
const nameOf = (list, id) => list.value.find((row) => Number(row.id) === Number(id))?.name || `#${id}`;

const periodChipValue = () => {
    if (filters.date_filter_type !== 'custom') return periodLabel(filters.date_filter_type);
    const from = filters.start_date ? formatDate(filters.start_date) : '…';
    const to = filters.end_date ? formatDate(filters.end_date) : '…';
    return `${from} – ${to}`;
};

const activeFilterChips = computed(() => {
    const chips = [];
    if (filters.date_filter_type !== 'all' && !awaitingRange.value) {
        chips.push({ key: 'period', label: t('sr_period'), value: periodChipValue() });
    }
    if (filters.employee_id) chips.push({ key: 'employee_id', label: t('employee'), value: nameOf(employees, filters.employee_id) });
    if (filters.customer_id) chips.push({ key: 'customer_id', label: t('customer'), value: nameOf(customers, filters.customer_id) });
    if (filters.warehouse_id) chips.push({ key: 'warehouse_id', label: t('warehouse'), value: nameOf(warehouses, filters.warehouse_id) });
    if (filters.status) chips.push({ key: 'status', label: t('status'), value: getStatusText(filters.status) });
    return chips;
});

const clearFilter = (key) => {
    if (key === 'period') {
        Object.assign(filters, { date_filter_type: 'all', start_date: null, end_date: null });
        return;
    }
    filters[key] = key === 'status' ? '' : null;
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


/** Names the dataset the export button is about to produce. */
const activeTabLabel = computed(() =>
    activeTab.value === 'invoices' ? t('invoices') : t('sales_orders_pipeline')
);

// The shared sales formatter: Gregorian, in the interface's language. This
// used toLocaleDateString('ar-SA'), which most browsers render in the Hijri
// calendar — so the report dated its invoices differently from every other
// sales screen.
const formatDate = (value) => (value ? formatSalesDate(value) : '-');

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
    appliedScope = scopeKey();
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

.filter-field--range {
    /* Two dates and a separator do not fit the grid's one-field minimum. */
    grid-column: span 2;
}

.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px 8px;
    margin: -0.5rem 0 1rem;
    min-height: 28px;
}

.active-filters-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}

.active-filter-chip {
    font-size: 0.82rem;
}

.active-filter-name {
    color: #64748b;
    font-weight: 600;
}

.active-filters-all {
    font-size: 0.85rem;
    color: #64748b;
}

.active-filters-clear {
    font-size: 0.82rem;
}

.active-filters-spacer {
    flex: 1 1 auto;
}

.collapse-all-icon {
    margin-inline-end: 4px;
}

.active-filters-updating {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.82rem;
    color: var(--el-color-primary);
}

.option-hint {
    margin-inline-start: 0.5rem;
    font-size: 0.78rem;
    color: #94a3b8;
}

.active-filters-hint {
    font-size: 0.82rem;
    color: var(--el-color-warning-dark-2, #b45309);
}

@media (max-width: 640px) {
    .filter-field--range {
        grid-column: auto;
    }
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

/* Per-invoice profitability cells. */
.hinted-header {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    cursor: help;
}

.hinted-header i {
    font-size: 0.75rem;
    opacity: 0.55;
}

.cost-cell {
    color: var(--text-muted);
}

.margin-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.margin-cell-top {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 600;
}

/* The bar is the column's whole reason for existing at a glance: a page of
   percentages all read the same until one of them is drawn shorter. */
.margin-bar {
    height: 4px;
    border-radius: 2px;
    background: var(--el-fill-color-light, #f0f2f5);
    overflow: hidden;
}

.margin-bar span {
    display: block;
    height: 100%;
    border-radius: 2px;
    background: currentColor;
}

.margin-warning {
    color: #d97706;
    font-size: 0.8rem;
    cursor: help;
}

/* Marked rather than coloured: an estimated cost is not a problem with the
   invoice, only a statement about where the number came from. */
.cost-cell.is-estimated {
    border-bottom: 1px dashed currentColor;
    cursor: help;
    opacity: 0.75;
}

/* An invoice sold below cost, marked down the leading edge rather than tinted
   across the row, which would fight the striping. */
:deep(.row-loss) td:first-child {
    box-shadow: inset 3px 0 0 #dc2626;
}

.profit-positive { color: #16a34a; }
.profit-negative { color: #dc2626; }
</style>
