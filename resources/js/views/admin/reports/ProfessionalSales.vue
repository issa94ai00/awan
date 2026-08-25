<template>
    <div class="reports-page professional-sales">
        <AdminPageHeader
            badge="CRM"
            :title="$t('professional_sales_reports')"
            :subtitle="$t('advanced_sales_filtering_and_analytics')"
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
                <label>{{ $t('employee') }}</label>
                <el-select v-model="filters.employee_id" :placeholder="$t('all_employees')" clearable>
                    <el-option
                        v-for="employee in employees"
                        :key="employee.id"
                        :label="employee.name"
                        :value="employee.id"
                    />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('customer') }}</label>
                <el-select v-model="filters.customer_id" :placeholder="$t('all_customers')" clearable>
                    <el-option
                        v-for="customer in customers"
                        :key="customer.id"
                        :label="customer.name"
                        :value="customer.id"
                    />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('warehouse') }}</label>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable>
                    <el-option
                        v-for="warehouse in warehouses"
                        :key="warehouse.id"
                        :label="warehouse.name"
                        :value="warehouse.id"
                    />
                </el-select>
            </div>
            <div class="filter-field">
                <label>{{ $t('order_status') }}</label>
                <el-select v-model="filters.status" :placeholder="$t('all_statuses')" clearable>
                    <el-option :label="$t('pending')" value="pending" />
                    <el-option :label="$t('confirmed')" value="confirmed" />
                    <el-option :label="$t('processing')" value="processing" />
                    <el-option :label="$t('shipped')" value="shipped" />
                    <el-option :label="$t('delivered')" value="delivered" />
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
                    <el-option :label="$t('by_employee')" value="employee" />
                    <el-option :label="$t('by_customer')" value="customer" />
                    <el-option :label="$t('by_warehouse')" value="warehouse" />
                    <el-option :label="$t('by_status')" value="status" />
                </el-select>
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('start_date') }}</label>
                <el-date-picker
                    v-model="filters.start_date"
                    type="date"
                    :placeholder="$t('start_date')"
                />
            </div>
            <div v-if="filters.date_filter_type === 'custom'" class="filter-field">
                <label>{{ $t('end_date') }}</label>
                <el-date-picker
                    v-model="filters.end_date"
                    type="date"
                    :placeholder="$t('end_date')"
                />
            </div>

            <template #actions>
                <el-button type="primary" :icon="Search" @click="applyFilters">
                {{ $t('apply_filters') }}
                </el-button>
            </template>
        </AdminFilterBar>

        <el-tabs v-model="activeTab" class="report-tabs">
        <el-tab-pane :label="$t('sales_orders_pipeline')" name="orders">

        <el-alert
            v-if="hasSalesData === false && !loading"
            :title="$t('no_data_for_current_filters')"
            type="info"
            :closable="false"
            show-icon
            class="empty-alert"
        />

        <AdminStatGrid v-if="loading">
            <el-card v-for="n in 6" :key="n" shadow="hover" class="stat-card skeleton-card">
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
                        <span>{{ $t('sales_revenue') }}</span>
                        <strong>{{ formatCurrency(performanceData.summary.total_revenue || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
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
                        <span>{{ $t('gross_profit') }}</span>
                        <strong>{{ formatCurrency(performanceData.summary.gross_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('profit_margin') }}</span>
                        <strong>{{ formatPercentage(performanceData.summary.gross_margin || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Charts -->
        <el-row :gutter="20" class="charts-section">
            <el-col :xs="24" :md="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('sales_by_period') }}</span>
                    </template>
                    <div ref="salesChartRef" style="height: 300px"></div>
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
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('employees') }}</span>
                    </template>
                    <el-table :data="dimensionData.employee_summary || []" stripe style="width: 100%">
                        <el-table-column prop="employee_name" :label="$t('employee')" />
                        <el-table-column prop="total_sales" :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatCurrency(row.total_sales) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('customers') }}</span>
                    </template>
                    <el-table :data="dimensionData.customer_summary || []" stripe style="width: 100%">
                        <el-table-column prop="customer_name" :label="$t('customer')" />
                        <el-table-column prop="total_sales" :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatCurrency(row.total_sales) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('warehouses') }}</span>
                    </template>
                    <el-table :data="dimensionData.warehouse_summary || []" stripe style="width: 100%">
                        <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                        <el-table-column prop="total_sales" :label="$t('total_sales')">
                            <template #default="{ row }">{{ formatCurrency(row.total_sales) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <el-row v-if="productProfitabilityData.summary" :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('most_profitable_product') }}</span>
                        <strong>{{ productProfitabilityData.summary.top_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(productProfitabilityData.summary.top_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('least_profitable_product') }}</span>
                        <strong>{{ productProfitabilityData.summary.lowest_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(productProfitabilityData.summary.lowest_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('total_profit') }}</span>
                        <strong>{{ formatCurrency(productProfitabilityData.summary.gross_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('items_count') }}</span>
                        <strong>{{ productProfitabilityData.summary.product_count || 0 }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="profitability-table-card" style="margin-bottom: 20px;">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('product_profitability_by_warehouse') }}</span>
                </div>
            </template>

            <el-table :data="productProfitabilityData.product_summary || []" stripe style="width: 100%">
                <el-table-column prop="product_name" :label="$t('product')" />
                <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                <el-table-column prop="quantity" :label="$t('quantity')" />
                <el-table-column :label="$t('revenue')">
                    <template #default="{ row }">{{ formatCurrency(row.total_revenue) }}</template>
                </el-table-column>
                <el-table-column :label="$t('cost')">
                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column :label="$t('profit')">
                    <template #default="{ row }">
                        <strong :class="row.gross_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatCurrency(row.gross_profit) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('margin')">
                    <template #default="{ row }">{{ formatPercentage(row.gross_margin) }}</template>
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

            <el-table
                v-loading="loading"
                :data="reportData"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column prop="order_number" :label="$t('order_number')" width="120" />
                <el-table-column :label="$t('date')" width="120">
                    <template #default="{ row }">
                        {{ formatDate(row.order_date) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('customer')">
                    <template #default="{ row }">
                        {{ row.customer ? row.customer.name : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('employee')">
                    <template #default="{ row }">
                        {{ row.assigned_employee ? row.assigned_employee.name : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusText(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('subtotal')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.subtotal) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('discount')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.discount) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('tax')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.tax) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('total')" width="120">
                    <template #default="{ row }">
                        <strong>{{ formatCurrency(row.total) }}</strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('invoiced')" width="150">
                    <template #default="{ row }">
                        <el-tag :type="invoiceCoverageType(row)" size="small">
                            {{ invoiceCoverageText(row) }}
                        </el-tag>
                        <p v-if="Number(row.invoices_count) > 0" class="table-sub-note">
                            {{ formatCurrency(row.invoiced_total) }}
                        </p>
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
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
                style="margin-top: 20px; justify-content: center"
            />
        </el-card>

        <!-- Top Performers -->
        <el-card shadow="hover" class="top-performers-card">
            <template #header>
                <span>{{ $t('top_performing_employees') }}</span>
            </template>

            <el-table
                v-loading="loadingTopPerformers"
                :data="topPerformers"
                style="width: 100%"
                stripe
            >
                <el-table-column :label="$t('rank')" width="80">
                    <template #default="{ $index }">
                        <el-tag :type="getRankType($index)" size="small">
                            {{ $index + 1 }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="employee_name" :label="$t('employee')" />
                <el-table-column prop="total_orders" :label="$t('total_orders')" />
                <el-table-column :label="$t('total_sales')">
                    <template #default="{ row }">
                        <strong>{{ formatCurrency(row.total_sales) }}</strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('average_order_value')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.average_order_value) }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        </el-tab-pane>

        <el-tab-pane :label="$t('invoices')" name="invoices">

        <el-alert
            v-if="hasInvoiceData === false && !loadingInvoices"
            :title="$t('no_data_for_current_filters')"
            type="info"
            :closable="false"
            show-icon
            class="empty-alert"
        />

        <AdminStatGrid v-if="loadingInvoices">
            <el-card v-for="n in 5" :key="n" shadow="hover" class="stat-card skeleton-card">
                <el-skeleton :rows="2" animated />
            </el-card>
        </AdminStatGrid>

        <AdminStatGrid v-else>
            <el-card v-for="(stat, key) in invoiceSummaryStats" :key="key" shadow="hover" class="stat-card" :class="'stat-card-' + key">
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

        <el-row v-if="!loadingInvoices && invoicePerformanceData.summary" :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('sales_revenue') }}</span>
                        <strong>{{ formatCurrency(invoicePerformanceData.summary.total_revenue || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('cost_of_goods') }}</span>
                        <strong>{{ formatCurrency(invoicePerformanceData.summary.total_cost || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('gross_profit') }}</span>
                        <strong>{{ formatCurrency(invoicePerformanceData.summary.gross_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('profit_margin') }}</span>
                        <strong>{{ formatPercentage(invoicePerformanceData.summary.gross_margin || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="dimension-panels" style="margin-top: 20px; margin-bottom: 20px;">
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('employees') }}</span>
                    </template>
                    <el-table :data="invoiceDimensionData.employee_summary || []" stripe style="width: 100%">
                        <el-table-column prop="employee_name" :label="$t('employee')" />
                        <el-table-column :label="$t('total_invoiced')">
                            <template #default="{ row }">{{ formatCurrency(row.total_invoiced) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('customers') }}</span>
                    </template>
                    <el-table :data="invoiceDimensionData.customer_summary || []" stripe style="width: 100%">
                        <el-table-column prop="customer_name" :label="$t('customer')" />
                        <el-table-column :label="$t('total_invoiced')">
                            <template #default="{ row }">{{ formatCurrency(row.total_invoiced) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('warehouses') }}</span>
                    </template>
                    <el-table :data="invoiceDimensionData.warehouse_summary || []" stripe style="width: 100%">
                        <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                        <el-table-column :label="$t('total_invoiced')">
                            <template #default="{ row }">{{ formatCurrency(row.total_invoiced) }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <el-row v-if="invoiceProductProfitabilityData.summary" :gutter="20" style="margin-top: 20px; margin-bottom: 10px;">
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('most_profitable_product') }}</span>
                        <strong>{{ invoiceProductProfitabilityData.summary.top_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(invoiceProductProfitabilityData.summary.top_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('least_profitable_product') }}</span>
                        <strong>{{ invoiceProductProfitabilityData.summary.lowest_product?.product_name || '-' }}</strong>
                        <small>{{ formatCurrency(invoiceProductProfitabilityData.summary.lowest_product?.gross_profit || 0) }}</small>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('total_profit') }}</span>
                        <strong>{{ formatCurrency(invoiceProductProfitabilityData.summary.gross_profit || 0) }}</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :md="6">
                <el-card shadow="hover">
                    <div class="mini-metric">
                        <span>{{ $t('items_count') }}</span>
                        <strong>{{ invoiceProductProfitabilityData.summary.product_count || 0 }}</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="profitability-table-card" style="margin-bottom: 20px;">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('product_profitability_by_warehouse') }}</span>
                </div>
            </template>

            <el-table :data="invoiceProductProfitabilityData.product_summary || []" stripe style="width: 100%">
                <el-table-column prop="product_name" :label="$t('product')" />
                <el-table-column prop="warehouse_name" :label="$t('warehouse')" />
                <el-table-column prop="quantity" :label="$t('quantity')" />
                <el-table-column :label="$t('revenue')">
                    <template #default="{ row }">{{ formatCurrency(row.total_revenue) }}</template>
                </el-table-column>
                <el-table-column :label="$t('cost')">
                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column :label="$t('profit')">
                    <template #default="{ row }">
                        <strong :class="row.gross_profit >= 0 ? 'profit-positive' : 'profit-negative'">
                            {{ formatCurrency(row.gross_profit) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('margin')">
                    <template #default="{ row }">{{ formatPercentage(row.gross_margin) }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Detailed Invoice Table -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('detailed_report') }}</span>
                    <el-button type="success" :icon="Download" @click="exportInvoiceReport">
                        {{ $t('export_report') }}
                    </el-button>
                </div>
            </template>

            <el-table
                v-loading="loadingInvoices"
                :data="invoiceReportData"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column prop="invoice_number" :label="$t('invoice_number')" width="130" />
                <el-table-column :label="$t('source_order')" width="120">
                    <template #default="{ row }">
                        <span v-if="row.sales_order">{{ row.sales_order.order_number }}</span>
                        <span v-else class="table-sub-note">{{ $t('direct_sale') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="120">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('customer')">
                    <template #default="{ row }">
                        {{ row.customer ? row.customer.name : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('employee')">
                    <template #default="{ row }">
                        {{ row.assigned_employee ? row.assigned_employee.name : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusText(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('total')" width="120">
                    <template #default="{ row }">
                        <strong>{{ formatCurrency(row.total) }}</strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('paid_amount')" width="120">
                    <template #default="{ row }">
                        {{ formatCurrency(row.paid_amount) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('due_amount')" width="120">
                    <template #default="{ row }">
                        <strong :class="Number(row.due_amount) > 0 ? 'profit-negative' : 'profit-positive'">
                            {{ formatCurrency(row.due_amount) }}
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
                @size-change="handleInvoiceSizeChange"
                @current-change="handleInvoicePageChange"
                style="margin-top: 20px; justify-content: center"
            />
        </el-card>

        <!-- Top Performers (invoiced) -->
        <el-card shadow="hover" class="top-performers-card">
            <template #header>
                <span>{{ $t('top_performing_employees') }}</span>
            </template>

            <el-table
                v-loading="loadingInvoiceTopPerformers"
                :data="invoiceTopPerformers"
                style="width: 100%"
                stripe
            >
                <el-table-column :label="$t('rank')" width="80">
                    <template #default="{ $index }">
                        <el-tag :type="getRankType($index)" size="small">
                            {{ $index + 1 }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="employee_name" :label="$t('employee')" />
                <el-table-column prop="total_invoices" :label="$t('invoices_count')" />
                <el-table-column :label="$t('total_invoiced')">
                    <template #default="{ row }">
                        <strong>{{ formatCurrency(row.total_sales) }}</strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('average_invoice_value')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.average_invoice_value) }}
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        </el-tab-pane>
        </el-tabs>
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

const { t } = useI18n();

const router = useRouter();
const loading = ref(false);
const loadingTopPerformers = ref(false);
const employees = ref([]);
const customers = ref([]);
const warehouses = ref([]);
const reportData = ref([]);
const topPerformers = ref([]);
const summary = ref({});
const dimensionData = ref({
    employee_summary: [],
    customer_summary: [],
    warehouse_summary: []
});
const performanceData = ref({
    summary: {},
    employee_summary: [],
    customer_summary: [],
    warehouse_summary: []
});
const productProfitabilityData = ref({
    summary: {},
    product_summary: []
});
const activeTab = ref('orders');
const loadingInvoices = ref(false);
const invoiceReportData = ref([]);
const invoiceSummary = ref({});
const invoiceDimensionData = ref({
    employee_summary: [],
    customer_summary: [],
    warehouse_summary: []
});
const invoicePagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0
});
const invoicePerformanceData = ref({
    summary: {},
    employee_summary: [],
    customer_summary: [],
    warehouse_summary: []
});
const invoiceProductProfitabilityData = ref({
    summary: {},
    product_summary: []
});
const invoiceTopPerformers = ref([]);
const loadingInvoiceTopPerformers = ref(false);

const salesChartRef = ref(null);
const distributionChartRef = ref(null);
let salesChart = null;
let distributionChart = null;

const filters = ref({
    employee_id: null,
    customer_id: null,
    warehouse_id: null,
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

const hasSalesData = computed(() => {
    const summaryRows = (reportData.value || []).length > 0;
    const dimensionRows = (dimensionData.value.employee_summary || []).length > 0 || (dimensionData.value.customer_summary || []).length > 0 || (dimensionData.value.warehouse_summary || []).length > 0;
    return summaryRows || dimensionRows || Number(summary.value.total_orders || 0) > 0 || Number(summary.value.total_sales || 0) > 0;
});

const hasInvoiceData = computed(() => {
    const rows = (invoiceReportData.value || []).length > 0;
    const dimensionRows = (invoiceDimensionData.value.employee_summary || []).length > 0
        || (invoiceDimensionData.value.customer_summary || []).length > 0
        || (invoiceDimensionData.value.warehouse_summary || []).length > 0;
    return rows || dimensionRows || Number(invoiceSummary.value.total_invoices || 0) > 0;
});

const invoiceSummaryStats = computed(() => ({
    total_invoices: {
        label: t('invoices_count'),
        value: invoiceSummary.value.total_invoices || 0,
        icon: Document,
        format: 'number'
    },
    total_invoiced: {
        label: t('total_invoiced'),
        value: invoiceSummary.value.total_invoiced || 0,
        icon: Coin,
        format: 'currency'
    },
    paid_amount: {
        label: t('paid_amount'),
        value: invoiceSummary.value.paid_amount || 0,
        icon: Wallet,
        format: 'currency'
    },
    due_amount: {
        label: t('due_amount'),
        value: invoiceSummary.value.due_amount || 0,
        icon: Warning,
        format: 'currency'
    },
    average_invoice_value: {
        label: t('average_invoice_value'),
        value: invoiceSummary.value.average_invoice_value || 0,
        icon: TrendCharts,
        format: 'currency'
    }
}));

const summaryStats = computed(() => ({
    total_orders: {
        label: t('total_orders'),
        value: summary.value.total_orders || 0,
        icon: ShoppingCart,
        format: 'number'
    },
    total_revenue: {
        label: t('total_revenue'),
        value: performanceData.value.summary?.total_revenue ?? summary.value.total_sales ?? 0,
        icon: Coin,
        format: 'currency'
    },
    total_cost: {
        label: t('cost_of_goods'),
        value: performanceData.value.summary?.total_cost ?? 0,
        icon: PriceTag,
        format: 'currency'
    },
    gross_profit: {
        label: t('gross_profit'),
        value: performanceData.value.summary?.gross_profit ?? 0,
        icon: TrendCharts,
        format: 'currency'
    },
    gross_margin: {
        label: t('profit_margin'),
        value: performanceData.value.summary?.gross_margin ?? 0,
        icon: PieChart,
        format: 'percent'
    },
    total_subtotal: {
        label: t('total_subtotal'),
        value: summary.value.total_subtotal || 0,
        icon: Coin,
        format: 'currency'
    },
    average_order_value: {
        label: t('average_order_value'),
        value: summary.value.average_order_value || 0,
        icon: TrendCharts,
        format: 'currency'
    },
    invoiced_orders: {
        label: t('invoiced_orders'),
        value: summary.value.invoiced_orders || 0,
        icon: Document,
        format: 'number'
    },
    uninvoiced_amount: {
        label: t('uninvoiced_amount'),
        value: summary.value.uninvoiced_amount || 0,
        icon: Warning,
        format: 'currency'
    }
}));

/** Whether an order has been billed yet, and how much of it. */
const invoiceCoverageType = (row) => {
    const invoicesCount = Number(row.invoices_count || 0);
    if (invoicesCount === 0) return 'info';
    const invoicedTotal = Number(row.invoiced_total || 0);
    return invoicedTotal >= Number(row.total || 0) ? 'success' : 'warning';
};

const invoiceCoverageText = (row) => {
    const invoicesCount = Number(row.invoices_count || 0);
    if (invoicesCount === 0) return t('not_invoiced');
    const invoicedTotal = Number(row.invoiced_total || 0);
    return invoicedTotal >= Number(row.total || 0) ? t('fully_invoiced') : t('partially_invoiced');
};

const goBack = () => {
    router.push({ name: 'admin.reports.index' });
};

const loadEmployees = async () => {
    try {
        const response = await api.get('/admin/employees');
        if (response.data.success && response.data.data) {
            employees.value = response.data.data.employees || [];
        } else {
            employees.value = [];
        }
    } catch (error) {
        console.error('Failed to load employees:', error);
        employees.value = [];
    }
};

const loadCustomers = async () => {
    try {
        const response = await api.get('/pos/customers', { params: { per_page: 100 } });
        if (response.data.success && response.data.data && Array.isArray(response.data.data.customers)) {
            customers.value = response.data.data.customers.map(item => item.data || item);
        } else {
            customers.value = [];
        }
    } catch (error) {
        console.error('Failed to load customers:', error);
        customers.value = [];
    }
};

const loadWarehouses = async () => {
    try {
        const response = await api.get('/admin/wms/warehouses', { params: { per_page: 100 } });
        if (response.data && Array.isArray(response.data.data)) {
            warehouses.value = response.data.data;
        } else if (response.data && response.data.data && Array.isArray(response.data.data.data)) {
            warehouses.value = response.data.data.data;
        } else {
            warehouses.value = [];
        }
    } catch (error) {
        console.error('Failed to load warehouses:', error);
        warehouses.value = [];
    }
};

const getFilterParams = () => {
    const params = {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        date_filter_type: filters.value.date_filter_type || 'all'
    };

    if (filters.value.employee_id) params.employee_id = filters.value.employee_id;
    if (filters.value.customer_id) params.customer_id = filters.value.customer_id;
    if (filters.value.warehouse_id) params.warehouse_id = filters.value.warehouse_id;
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.group_by) params.group_by = filters.value.group_by;

    // Date filters
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

const formatDateForAPI = (date) => {
    return date.toISOString().split('T')[0];
};

const applyFilters = () => {
    pagination.value.current_page = 1;
    invoicePagination.value.current_page = 1;
    loadReport();
    loadTopPerformers();
    loadDimensionSummary();
    loadPerformanceMetrics();
    loadProductProfitability();
    loadInvoiceReport();
    loadInvoiceDimensionsData();
    loadInvoicePerformance();
    loadInvoiceProductProfitability();
    loadInvoiceTopPerformers();
};

const resetFilters = () => {
    filters.value = {
        employee_id: null,
        customer_id: null,
        warehouse_id: null,
        status: '',
        date_filter_type: 'all',
        start_date: null,
        end_date: null,
        group_by: 'day'
    };
    pagination.value.current_page = 1;
    invoicePagination.value.current_page = 1;
    loadReport();
    loadTopPerformers();
    loadDimensionSummary();
    loadPerformanceMetrics();
    loadProductProfitability();
    loadInvoiceReport();
    loadInvoiceDimensionsData();
    loadInvoicePerformance();
    loadInvoiceProductProfitability();
    loadInvoiceTopPerformers();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = getFilterParams();
        const response = await api.get('/admin/reports/sales', { params });

        if (response.data.success && response.data.data) {
            reportData.value = Array.isArray(response.data.data.sales_orders) ? response.data.data.sales_orders : [];
            summary.value = response.data.data.summary || {};
            pagination.value = response.data.data.pagination || { current_page: 1, per_page: 20, total: 0 };

            await loadSummaryCharts();
            await loadDimensionSummary();
            await loadPerformanceMetrics();
            await loadProductProfitability();
        } else {
            reportData.value = [];
            summary.value = {};
            pagination.value = { current_page: 1, per_page: 20, total: 0 };
        }
    } catch (error) {
        console.error('Failed to load report:', error);
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
        const response = await api.get('/admin/reports/sales/summary', { params });

        if (response.data.success && response.data.data) {
            await nextTick();
            updateCharts(response.data.data.summary || [], response.data.data.group_by || 'day');
        }
    } catch (error) {
        console.error('Failed to load summary charts:', error);
    }
};

const loadDimensionSummary = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/sales/dimensions', { params });
        if (response.data.success && response.data.data) {
            dimensionData.value = {
                employee_summary: response.data.data.employee_summary || [],
                customer_summary: response.data.data.customer_summary || [],
                warehouse_summary: response.data.data.warehouse_summary || []
            };
        } else {
            dimensionData.value = {
                employee_summary: [],
                customer_summary: [],
                warehouse_summary: []
            };
        }
    } catch (error) {
        console.error('Failed to load sales dimensions:', error);
        dimensionData.value = {
            employee_summary: [],
            customer_summary: [],
            warehouse_summary: []
        };
    }
};

const loadPerformanceMetrics = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/sales/performance', { params });
        if (response.data.success && response.data.data) {
            performanceData.value = {
                summary: response.data.data.summary || {},
                employee_summary: response.data.data.employee_summary || [],
                customer_summary: response.data.data.customer_summary || [],
                warehouse_summary: response.data.data.warehouse_summary || []
            };
        } else {
            performanceData.value = {
                summary: {},
                employee_summary: [],
                customer_summary: [],
                warehouse_summary: []
            };
        }
    } catch (error) {
        console.error('Failed to load sales performance:', error);
        performanceData.value = {
            summary: {},
            employee_summary: [],
            customer_summary: [],
            warehouse_summary: []
        };
    }
};

const loadProductProfitability = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/sales/product-profitability', { params });
        if (response.data.success && response.data.data) {
            productProfitabilityData.value = {
                summary: response.data.data.summary || {},
                product_summary: response.data.data.product_summary || []
            };
        } else {
            productProfitabilityData.value = {
                summary: {},
                product_summary: []
            };
        }
    } catch (error) {
        console.error('Failed to load product profitability:', error);
        productProfitabilityData.value = {
            summary: {},
            product_summary: []
        };
    }
};

const loadInvoiceReport = async () => {
    loadingInvoices.value = true;
    try {
        const params = getFilterParams();
        delete params.group_by;
        params.page = invoicePagination.value.current_page;
        params.per_page = invoicePagination.value.per_page;

        const response = await api.get('/admin/reports/invoices', { params });

        if (response.data.success && response.data.data) {
            invoiceReportData.value = Array.isArray(response.data.data.invoices) ? response.data.data.invoices : [];
            invoiceSummary.value = response.data.data.summary || {};
            invoicePagination.value = response.data.data.pagination || { current_page: 1, per_page: 20, total: 0 };
        } else {
            invoiceReportData.value = [];
            invoiceSummary.value = {};
        }
    } catch (error) {
        console.error('Failed to load invoice report:', error);
        ElMessage.error(t('failed_to_load_report'));
        invoiceReportData.value = [];
        invoiceSummary.value = {};
    } finally {
        loadingInvoices.value = false;
    }
};

const loadInvoiceDimensionsData = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/invoices/dimensions', { params });
        if (response.data.success && response.data.data) {
            invoiceDimensionData.value = {
                employee_summary: response.data.data.employee_summary || [],
                customer_summary: response.data.data.customer_summary || [],
                warehouse_summary: response.data.data.warehouse_summary || []
            };
        } else {
            invoiceDimensionData.value = { employee_summary: [], customer_summary: [], warehouse_summary: [] };
        }
    } catch (error) {
        console.error('Failed to load invoice dimensions:', error);
        invoiceDimensionData.value = { employee_summary: [], customer_summary: [], warehouse_summary: [] };
    }
};

const loadInvoicePerformance = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/invoices/performance', { params });
        if (response.data.success && response.data.data) {
            invoicePerformanceData.value = {
                summary: response.data.data.summary || {},
                employee_summary: response.data.data.employee_summary || [],
                customer_summary: response.data.data.customer_summary || [],
                warehouse_summary: response.data.data.warehouse_summary || []
            };
        }
    } catch (error) {
        console.error('Failed to load invoice performance:', error);
        invoicePerformanceData.value = { summary: {}, employee_summary: [], customer_summary: [], warehouse_summary: [] };
    }
};

const loadInvoiceProductProfitability = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/invoices/product-profitability', { params });
        if (response.data.success && response.data.data) {
            invoiceProductProfitabilityData.value = {
                summary: response.data.data.summary || {},
                product_summary: response.data.data.product_summary || []
            };
        }
    } catch (error) {
        console.error('Failed to load invoice product profitability:', error);
        invoiceProductProfitabilityData.value = { summary: {}, product_summary: [] };
    }
};

const loadInvoiceTopPerformers = async () => {
    loadingInvoiceTopPerformers.value = true;
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/invoices/top-performers', { params });
        if (response.data.success && response.data.data) {
            invoiceTopPerformers.value = Array.isArray(response.data.data) ? response.data.data : [];
        } else {
            invoiceTopPerformers.value = [];
        }
    } catch (error) {
        console.error('Failed to load invoice top performers:', error);
        invoiceTopPerformers.value = [];
    } finally {
        loadingInvoiceTopPerformers.value = false;
    }
};

const handleInvoicePageChange = (page) => {
    invoicePagination.value.current_page = page;
    loadInvoiceReport();
};

const handleInvoiceSizeChange = (size) => {
    invoicePagination.value.per_page = size;
    invoicePagination.value.current_page = 1;
    loadInvoiceReport();
};

const exportInvoiceReport = async () => {
    try {
        const params = getFilterParams();
        delete params.page;
        delete params.per_page;
        delete params.group_by;

        const response = await api.get('/admin/reports/invoices/export', { params, responseType: 'blob' });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `invoice-report-${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Failed to export invoice report:', error);
        ElMessage.error(t('failed_to_export_report'));
    }
};

const updateCharts = (summaryData, groupBy) => {
    if (!summaryData || !Array.isArray(summaryData)) return;

    const labels = summaryData.map(item => {
        switch (groupBy) {
            case 'employee': return item.employee_name || 'Unknown';
            case 'customer': return item.customer_name || 'Unknown';
            case 'warehouse': return item.warehouse_name || 'Unknown';
            case 'status': return item.status_text || item.status || 'Unknown';
            default: return item.date || item.period || 'Unknown';
        }
    });

    const salesData = summaryData.map(item => item.total_sales || 0);
    const ordersData = summaryData.map(item => item.total_orders || 0);

    // Sales Chart
    if (salesChartRef.value) {
        if (salesChart) salesChart.dispose();
        salesChart = echarts.init(salesChartRef.value);
        
        salesChart.setOption({
            tooltip: {
                trigger: 'axis'
            },
            legend: {
                data: [t('nav_sales'), t('orders_count')]
            },
            xAxis: {
                type: 'category',
                data: labels
            },
            yAxis: {
                type: 'value'
            },
            series: [
                {
                    name: t('nav_sales'),
                    type: 'line',
                    data: salesData,
                    smooth: true,
                    areaStyle: {
                        color: 'rgba(102, 126, 234, 0.1)'
                    },
                    itemStyle: {
                        color: '#667eea'
                    }
                },
                {
                    name: t('orders_count'),
                    type: 'line',
                    data: ordersData,
                    smooth: true,
                    areaStyle: {
                        color: 'rgba(240, 147, 251, 0.1)'
                    },
                    itemStyle: {
                        color: '#f093fb'
                    }
                }
            ]
        });
    }

    // Distribution Chart
    if (distributionChartRef.value) {
        if (distributionChart) distributionChart.dispose();
        distributionChart = echarts.init(distributionChartRef.value);
        
        distributionChart.setOption({
            tooltip: {
                trigger: 'item'
            },
            legend: {
                orient: 'vertical',
                right: 10
            },
            series: [
                {
                    name: t('nav_sales'),
                    type: 'pie',
                    radius: '50%',
                    data: labels.map((label, index) => ({
                        value: salesData[index],
                        name: label
                    })),
                    emphasis: {
                        itemStyle: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowColor: 'rgba(0, 0, 0, 0.5)'
                        }
                    }
                }
            ]
        });
    }
};

const loadTopPerformers = async () => {
    loadingTopPerformers.value = true;
    try {
        const params = getFilterParams();
        delete params.group_by;
        delete params.page;
        delete params.per_page;

        const response = await api.get('/admin/reports/sales/top-performers', { params });

        if (response.data.success && response.data.data) {
            topPerformers.value = Array.isArray(response.data.data) ? response.data.data : [];
        } else {
            topPerformers.value = [];
        }
    } catch (error) {
        console.error('Failed to load top performers:', error);
        ElMessage.error(t('failed_to_load_top_performers'));
        topPerformers.value = [];
    } finally {
        loadingTopPerformers.value = false;
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

        // Goes through the api client so the export carries the bearer token too.
        const response = await api.get('/admin/reports/sales/export', { params, responseType: 'blob' });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `sales-report-${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Failed to export report:', error);
        ElMessage.error(t('failed_to_export_report'));
    }
};

const formatValue = (value, format) => {
    if (format === 'currency') {
        return formatCurrency(value);
    }
    if (format === 'percent') {
        return formatPercentage(value);
    }
    return formatNumber(value);
};

const formatCurrency = (value) => formatMoney(value);

const formatPercentage = (value) => {
    return `${Number(value || 0).toFixed(2)}%`;
};

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
        shipped: 'success',
        delivered: 'success',
        cancelled: 'danger'
    };
    return types[status] || 'info';
};

const getStatusText = (status) => {
    const texts = {
        pending: t('sales_status_pending'),
        confirmed: t('sales_status_confirmed'),
        processing: t('sales_status_processing'),
        shipped: t('sales_status_shipped'),
        delivered: t('sales_status_delivered'),
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
    loadEmployees();
    loadCustomers();
    loadWarehouses();
    loadReport();
    loadTopPerformers();
    loadDimensionSummary();
    loadPerformanceMetrics();
    loadProductProfitability();
    loadInvoiceReport();
    loadInvoiceDimensionsData();
    loadInvoicePerformance();
    loadInvoiceProductProfitability();
    loadInvoiceTopPerformers();
});
</script>

<style scoped>
.reports-page {
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
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.12), rgba(118, 75, 162, 0.12));
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
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

.profit-positive {
    color: #16a34a;
}

.profit-negative {
    color: #dc2626;
}

.table-sub-note {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #94a3b8;
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
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
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

.empty-alert {
    margin-bottom: 1.5rem;
    border-radius: 0.9rem;
}

.summary-cards {
    margin-bottom: 1.5rem;
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
.stat-card-total_sales { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(52,211,153,0.16)); }
.stat-card-total_subtotal { background: linear-gradient(135deg, rgba(168,85,247,0.08), rgba(216,180,254,0.18)); }
.stat-card-total_discount { background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(251,191,36,0.18)); }
.stat-card-total_tax { background: linear-gradient(135deg, rgba(14,165,233,0.08), rgba(125,211,252,0.18)); }
.stat-card-average_order_value { background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(252,165,165,0.18)); }

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
