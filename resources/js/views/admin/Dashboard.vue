<template>
    <div class="dashboard" ref="dashboardRef">
        <div class="page-header">
            <div class="page-header-icon"><i class="fas fa-tachometer-alt"></i></div>
            <div class="page-header-text">
                <h1>{{ $t('control_panel_var', { value: siteName }) }}</h1>
                <p>{{ tagline }}</p>
                <!-- Says out loud which currency the figures below are in, so a
                     total is never read as a number in the wrong money. -->
                <span class="page-header-meta">
                    <el-tag size="small" type="info" effect="plain">
                        {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                    </el-tag>
                    <span v-if="lastUpdatedLabel" class="small-text">
                        {{ $t('last_updated_at', { time: lastUpdatedLabel }) }}
                    </span>
                </span>
            </div>
            <div class="page-header-actions">
                <el-button size="default" :loading="refreshing" @click="refresh">
                    <el-icon class="mr-1"><Refresh /></el-icon>
                    {{ $t('refresh') }}
                </el-button>
                <el-button type="success" size="default" @click="paymentDialogVisible = true">
                    <el-icon class="mr-1"><Checked /></el-icon>
                    {{ $t('quick_payment') }}
                </el-button>
            </div>
        </div>

        <QuickPaymentDialog v-model="paymentDialogVisible" @saved="loadDashboard" />

        <el-alert
            v-if="error"
            :title="error"
            type="error"
            show-icon
            closable
            class="dashboard-alert"
        >
        </el-alert>

        <div class="executive-strip" v-if="!loading">
            <div v-for="item in executiveHighlights" :key="item.label" class="executive-card">
                <span class="executive-label">{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
            </div>
        </div>

        <!-- ERP Features Dashboard Tabs -->
        <el-tabs v-model="activeTab" class="dashboard-tabs mt-4">

            <!-- 1. GENERAL OVERVIEW TAB -->
            <el-tab-pane name="overview">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Odometer /></el-icon>
                        <span>{{ $t('dashboard') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="4" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12" :md="6" v-for="stat in stats" :key="stat.title">
                            <el-card
                                class="stat-card"
                                :class="{ clickable: !!stat.route }"
                                shadow="hover"
                                @click="stat.route && $router.push(stat.route)"
                            >
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

                    <div v-if="lowStockProducts.length > 0" class="section mt-4">
                        <div class="section-header">
                            <h2><i class="fas fa-exclamation-triangle" style="color: #e6a23c;"></i> {{ $t('low_stock_alerts') }}</h2>
                        </div>
                        <el-card shadow="hover" class="low-stock-card">
                            <el-table :data="lowStockProducts" style="width: 100%" :stripe="true">
                                <el-table-column prop="name" :label="$t('product')"></el-table-column>
                                <el-table-column prop="sku" label="SKU" width="120"></el-table-column>
                                <el-table-column prop="stock_quantity" :label="$t('current_stock')" width="120">
                                    <template #default="{ row }">{{ formatNumber(row.stock_quantity) }}</template>
                                </el-table-column>
                                <el-table-column prop="min_stock" :label="$t('minimum')" width="120">
                                    <template #default="{ row }">{{ formatNumber(row.min_stock) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('status')" width="120">
                                    <template #default>
                                        <el-tag type="danger">{{ $t('low') }}</el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </div>

                    <div class="section mt-4">
                        <div class="section-header">
                            <h2>{{ $t('summary_of_reports') }}</h2>
                        </div>
                        <el-row :gutter="20">
                            <el-col :xs="24" :sm="12" :md="8" v-for="item in detailStats" :key="item.title">
                                <el-card
                                    class="stat-card"
                                    :class="{ clickable: !!item.route }"
                                    shadow="hover"
                                    @click="item.route && $router.push(item.route)"
                                >
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
                            <h2>{{ $t('look_at_revenue') }}</h2>
                        </div>
                        <el-row :gutter="20">
                            <el-col :xs="24" :lg="16">
                                <el-card shadow="hover" class="revenue-card">
                                    <template #header>
                                        <div class="status-header">
                                            <span>{{ $t('revenue_trend_title') }}</span>
                                            <span class="small-text" v-if="lastUpdatedLabel">
                                                {{ $t('last_updated_at', { time: lastUpdatedLabel }) }}
                                            </span>
                                        </div>
                                    </template>
                                    <div class="revenue-summary">
                                        <div class="revenue-value">
                                            <span>{{ $t('total_revenue') }}</span>
                                            <!-- Reads the figure, not the fourth stat card. -->
                                            <strong>{{ formatMoney(totalRevenue) }}</strong>
                                        </div>
                                        <div class="revenue-metrics">
                                            <div class="metric-item" v-for="metric in revenueMetrics" :key="metric.label">
                                                <span>{{ metric.label }}</span>
                                                <strong>{{ metric.value }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div ref="revenueTrendChartRef" class="chart-box"></div>
                                </el-card>
                            </el-col>

                            <el-col :xs="24" :lg="8">
                                <el-card class="stats-grid-card" shadow="hover">
                                    <template #header>
                                        <div class="status-header">
                                            <span>{{ $t('invoice_status_breakdown') }}</span>
                                        </div>
                                    </template>
                                    <div ref="invoiceStatusChartRef" class="chart-box chart-box-sm"></div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </div>

                    <div class="section mt-4">
                        <div class="section-header">
                            <h2>{{ $t('transaction_status') }}</h2>
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
                                            <strong>{{ formatNumber(item.value) }}</strong>
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
                                        <span>{{ $t('recent_sales') }}</span>
                                    </div>
                                </template>
                                <el-table :data="recentSales" style="width: 100%" :stripe="true">
                                    <el-table-column prop="id" :label="$t('invoice_number')" width="120"></el-table-column>
                                    <el-table-column prop="customer" :label="$t('client')"></el-table-column>
                                    <el-table-column prop="amount" :label="$t('amount')"></el-table-column>
                                    <el-table-column prop="status" :label="$t('status')">
                                        <template #default="{ row }">
                                            <!-- Status is matched as the model identifier the API sent
                                                 and translated only for display, so the colour no longer
                                                 depends on an English word appearing in an Arabic label. -->
                                            <el-tag :type="statusTagType(row.status)">
                                                {{ statusLabel(row.status) }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <el-col :xs="24" :lg="8">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('top_products') }}</span>
                                </template>
                                <div class="top-products">
                                    <div v-for="product in topProducts" :key="product.id" class="product-item">
                                        <EntityImage :src="product.image" type="product" :size="40" shape="circle" />
                                        <div class="product-info">
                                            <h4>{{ product.name }}</h4>
                                            <span>{{ formatNumber(product.sales) }} {{ $t('lonliness') }}</span>
                                        </div>
                                        <span class="product-price">{{ product.price }}</span>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </template>
            </el-tab-pane>

            <!-- 2. WAREHOUSE MANAGEMENT SYSTEM (WMS) TAB -->
            <el-tab-pane name="wms">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Location /></el-icon>
                        <span>{{ $t('wms') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="4" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/wms/warehouses')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #409eff;">
                                        <el-icon :size="28" color="white"><Location /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(wmsStats.warehouses_count) }}</h3>
                                        <p>{{ $t('warehouses') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/wms/bins')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #67c23a;">
                                        <el-icon :size="28" color="white"><List /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(wmsStats.bins_count) }}</h3>
                                        <p>{{ $t('bins') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/wms/picking')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #e6a23c;">
                                        <el-icon :size="28" color="white"><Checked /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(activePickingCount) }}</h3>
                                        <p>{{ $t('picking_packing') }} ({{ $t('common.active') }})</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/wms/cycle-counts')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #909399;">
                                        <el-icon :size="28" color="white"><Calendar /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(wmsStats.cycle_counts_count) }}</h3>
                                        <p>{{ $t('cycle_counts') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" class="mt-4">
                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('picking_packing_status') }}</span>
                                </template>
                                <div ref="wmsStatusChartRef" class="chart-box"></div>
                            </el-card>
                        </el-col>

                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('cycle_count_accuracy_chart') }}</span>
                                </template>
                                <div ref="wmsAccuracyChartRef" class="chart-box"></div>
                                <div class="mt-2 gauge-legend">
                                    <p>{{ $t('total_cycle_counts') }}: <strong>{{ formatNumber(wmsStats.cycle_counts_count) }}</strong></p>
                                    <p>{{ $t('completed_cycle_counts') }}: <strong>{{ formatNumber(wmsStats.cycle_counts_completed) }}</strong></p>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <div class="section mt-4">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ $t('wms_quick_actions') }}</span>
                            </template>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <el-button type="primary" @click="$router.push('/admin/wms/warehouses')">{{ $t('wms.warehouses') }}</el-button>
                                <el-button type="success" @click="$router.push('/admin/wms/bins')">{{ $t('wms.bins') }}</el-button>
                                <el-button type="warning" @click="$router.push('/admin/wms/picking')">{{ $t('wms.picking_lists') }}</el-button>
                                <el-button type="danger" @click="$router.push('/admin/wms/packing')">{{ $t('wms.packing_lists') }}</el-button>
                                <el-button type="info" @click="$router.push('/admin/wms/cycle-counts')">{{ $t('wms.cycle_counts') }}</el-button>
                                <el-button type="primary" plain @click="$router.push('/admin/wms/performance')">{{ $t('wms_performance') }}</el-button>
                            </div>
                        </el-card>
                    </div>
                </template>
            </el-tab-pane>

            <!-- 3. RETURNS & RMA MANAGEMENT TAB -->
            <el-tab-pane name="rma">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Refresh /></el-icon>
                        <span>{{ $t('rma') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="3" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/rma')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #409eff;">
                                        <el-icon :size="28" color="white"><Refresh /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(rmaStats.total) }}</h3>
                                        <p>{{ $t('rma_requests') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/rma')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #e6a23c;">
                                        <el-icon :size="28" color="white"><Clock /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(rmaStats.pending) }}</h3>
                                        <p>{{ $t('pending_rma') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/rma')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #f56c6c;">
                                        <el-icon :size="28" color="white"><Wallet /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatMoney(rmaStats.refunded_amount) }}</h3>
                                        <p>{{ $t('returned_amount') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" class="mt-4">
                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('rma_status_overview') }}</span>
                                </template>
                                <div style="padding: 10px 0;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                        <span>{{ $t('rma_pending_approval') }}</span>
                                        <el-tag type="warning">{{ formatNumber(rmaStats.pending) }}</el-tag>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                        <span>{{ $t('rma_approved') }}</span>
                                        <el-tag type="primary">{{ formatNumber(rmaStats.approved) }}</el-tag>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                        <span>{{ $t('rma_rejected') }}</span>
                                        <el-tag type="danger">{{ formatNumber(rmaStats.rejected) }}</el-tag>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span>{{ $t('rma_completed') }}</span>
                                        <el-tag type="success">{{ formatNumber(rmaStats.completed) }}</el-tag>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>

                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('rma_status_breakdown') }}</span>
                                </template>
                                <div ref="rmaStatusChartRef" class="chart-box"></div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <div class="section mt-4">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ $t('rma_quick_actions') }}</span>
                            </template>
                            <div style="padding: 20px 0; text-align: center;">
                                <el-icon :size="48" color="#409eff"><Refresh /></el-icon>
                                <p class="mt-2" style="color: #606f8b;">{{ $t('rma_description') }}</p>
                                <div class="mt-4">
                                    <el-button type="primary" @click="$router.push('/admin/rma')">{{ $t('view_rma_requests') }}</el-button>
                                    <el-button type="success" plain @click="$router.push('/admin/rma/create')">{{ $t('create_rma_request') }}</el-button>
                                </div>
                            </div>
                        </el-card>
                    </div>
                </template>
            </el-tab-pane>

            <!-- 4. WORKFLOWS & AUTOMATION TAB -->
            <el-tab-pane name="workflows">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Cpu /></el-icon>
                        <span>{{ $t('workflows') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="4" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/workflows')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #409eff;">
                                        <el-icon :size="28" color="white"><Cpu /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(workflowStats.total) }}</h3>
                                        <p>{{ $t('workflows') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/workflows')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #67c23a;">
                                        <el-icon :size="28" color="white"><Checked /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(workflowStats.active) }}</h3>
                                        <p>{{ $t('active_workflows') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/workflows')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #8c6dfd;">
                                        <el-icon :size="28" color="white"><Connection /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(workflowStats.executions_total) }}</h3>
                                        <p>{{ $t('automation_executions') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/workflows')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #67c23a;">
                                        <el-icon :size="28" color="white"><TrendCharts /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ automationSuccessRate }}</h3>
                                        <p>{{ $t('automation_success_rate') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" class="mt-4">
                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('workflow_run_logs') }}</span>
                                </template>
                                <div style="padding: 10px 0;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                        <span>{{ $t('successfully_executed') }}</span>
                                        <el-tag type="success">{{ formatNumber(workflowStats.executions_completed) }}</el-tag>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span>{{ $t('failed_executions') }}</span>
                                        <el-tag type="danger">{{ formatNumber(workflowStats.executions_failed) }}</el-tag>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>

                        <el-col :xs="24" :md="12">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('workflow_execution_breakdown') }}</span>
                                </template>
                                <div ref="workflowChartRef" class="chart-box"></div>
                            </el-card>
                        </el-col>
                    </el-row>
                </template>
            </el-tab-pane>

            <!-- 5. SECURITY & AUDIT FEED TAB -->
            <el-tab-pane name="audit">
                <template #label>
                    <span class="tab-label">
                        <el-icon><View /></el-icon>
                        <span>{{ $t('audit_logs') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="2" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/audit')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #409eff;">
                                        <el-icon :size="28" color="white"><View /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(auditStats.total) }}</h3>
                                        <p>{{ $t('total_audited_operations') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/audit')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #e6a23c;">
                                        <el-icon :size="28" color="white"><Clock /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(auditStats.today) }}</h3>
                                        <p>{{ $t('todays_operations') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" class="mt-4">
                        <el-col :xs="24" :lg="16">
                            <el-card shadow="hover">
                                <template #header>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>{{ $t('recent_system_activities') }}</span>
                                        <el-button type="primary" size="small" @click="$router.push('/admin/audit')">{{ $t('view_all') }}</el-button>
                                    </div>
                                </template>

                                <el-table :data="recentAuditLogs" style="width: 100%" :stripe="true">
                                    <el-table-column prop="user_name" :label="$t('name')" width="180"></el-table-column>
                                    <el-table-column prop="action_text" :label="$t('status')" width="140">
                                        <template #default="{ row }">
                                            <el-tag :type="row.action === 'delete' ? 'danger' : (row.action === 'create' ? 'success' : 'primary')">
                                                {{ row.action_text }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="module_text" :label="$t('module_column')" width="140"></el-table-column>
                                    <el-table-column prop="description" :label="$t('operation_details_column')"></el-table-column>
                                    <el-table-column prop="created_at" :label="$t('date')" width="180"></el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <el-col :xs="24" :lg="8">
                            <el-card shadow="hover">
                                <template #header>
                                    <span>{{ $t('recent_activity_breakdown') }}</span>
                                </template>
                                <div ref="auditActionChartRef" class="chart-box"></div>
                            </el-card>
                        </el-col>
                    </el-row>
                </template>
            </el-tab-pane>

            <!-- 6. BI & BUSINESS INTELLIGENCE TAB -->
            <el-tab-pane name="analytics">
                <template #label>
                    <span class="tab-label">
                        <el-icon><DataAnalysis /></el-icon>
                        <span>{{ $t('bi_analytics') }}</span>
                    </span>
                </template>

                <DashboardSkeleton v-if="loading" :cards="3" />
                <template v-else>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/analytics/dashboards')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #409eff;">
                                        <el-icon :size="28" color="white"><DataAnalysis /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(analyticsStats.dashboards) }}</h3>
                                        <p>{{ $t('analytics_dashboards') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/analytics/reports')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #67c23a;">
                                        <el-icon :size="28" color="white"><Document /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(analyticsStats.reports) }}</h3>
                                        <p>{{ $t('analytics_reports') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :xs="24" :sm="8">
                            <el-card class="stat-card clickable" shadow="hover" @click="$router.push('/admin/analytics/metrics')">
                                <div class="stat-content">
                                    <div class="stat-icon" style="background: #e6a23c;">
                                        <el-icon :size="28" color="white"><Odometer /></el-icon>
                                    </div>
                                    <div class="stat-info">
                                        <h3>{{ formatNumber(analyticsStats.metrics) }}</h3>
                                        <p>{{ $t('analytics_metrics') }}</p>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <div class="section mt-4">
                        <div class="section-header">
                            <h2>{{ $t('bi_gateways_title') }}</h2>
                        </div>
                        <el-row :gutter="20">
                            <el-col :xs="24" :sm="12" :md="6">
                                <el-card class="gateway-card" shadow="hover" @click="$router.push('/admin/analytics/sales')">
                                    <el-icon :size="32" color="#67c23a"><TrendCharts /></el-icon>
                                    <h3>{{ $t('analytics_sales') }}</h3>
                                    <p>{{ $t('bi_sales_description') }}</p>
                                </el-card>
                            </el-col>
                            <el-col :xs="24" :sm="12" :md="6">
                                <el-card class="gateway-card" shadow="hover" @click="$router.push('/admin/analytics/inventory')">
                                    <el-icon :size="32" color="#409eff"><Box /></el-icon>
                                    <h3>{{ $t('analytics_inventory') }}</h3>
                                    <p>{{ $t('bi_inventory_description') }}</p>
                                </el-card>
                            </el-col>
                            <el-col :xs="24" :sm="12" :md="6">
                                <el-card class="gateway-card" shadow="hover" @click="$router.push('/admin/analytics/warehouse')">
                                    <el-icon :size="32" color="#8c6dfd"><Location /></el-icon>
                                    <h3>{{ $t('analytics_warehouse') }}</h3>
                                    <p>{{ $t('bi_warehouse_description') }}</p>
                                </el-card>
                            </el-col>
                            <el-col :xs="24" :sm="12" :md="6">
                                <el-card class="gateway-card" shadow="hover" @click="$router.push('/admin/analytics/financial')">
                                    <el-icon :size="32" color="#e6a23c"><Coin /></el-icon>
                                    <h3>{{ $t('analytics_financial') }}</h3>
                                    <p>{{ $t('bi_financial_description') }}</p>
                                </el-card>
                            </el-col>
                        </el-row>
                    </div>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import EntityImage from '@/components/admin/EntityImage.vue';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import * as echarts from 'echarts';
// Only the icons bound as values (`:is="stat.icon"`) need importing; the ones
// written as tags resolve through the global registration in app.js.
import { Box, ShoppingCart, User, TrendCharts } from '@element-plus/icons-vue';
import { dashboardApi } from '@/api/dashboard';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/Composables/useCurrency';
import { statusTagType, statusLabel } from '@/utils/sales';
import DashboardSkeleton from '@/components/admin/DashboardSkeleton.vue';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();

// Amounts here are ledger figures, so they are written in the currency the
// ledger keeps them in rather than a code baked into this file.
const { baseCode, formatMoney, formatNumber } = useCurrency();

const paymentDialogVisible = ref(false);

const siteName = computed(() => {
    const settings = settingsStore.data || {};
    const name = locale.value === 'en'
        ? (settings.site_name_en || settings.site_name)
        : (settings.site_name_ar || settings.site_name);

    return name || t('site_name');
});

const tagline = computed(() => {
    const settings = settingsStore.data || {};
    const line = locale.value === 'en'
        ? (settings.site_tagline_en || settings.site_tagline)
        : (settings.site_tagline_ar || settings.site_tagline);

    return line || t('system_performance_overview_and_statistics');
});

// Re-runs on a language switch too, because `siteName` is itself locale-derived.
watch(siteName, (value) => {
    document.title = t('control_panel_var', { value });
}, { immediate: true });

const activeTab = ref('overview');

const loading = ref(false);
const refreshing = ref(false);
const error = ref(null);
const lastUpdatedAt = ref(null);

/**
 * The `/dashboard/stats` payload, held raw.
 *
 * Every label and every formatted total used to be built inside the loader and
 * parked in a ref, which froze the whole screen at the language and currency in
 * force when the request returned: switching to English relabelled the tabs and
 * left the figures, their headings and their status text in Arabic until a
 * reload. Keeping the response and deriving the display below it makes the page
 * a function of (data, locale, base currency), so a change in any of the three
 * re-renders what depends on it and nothing else.
 */
const overview = ref({});
const salesTrend = ref([]);

/* ---- Slices of the payload, each defaulted so the template never branches ---- */

const products = computed(() => overview.value.products || {});
const invoices = computed(() => overview.value.invoices || {});
const erp = computed(() => overview.value.erp || {});
const revenueBreakdown = computed(() => invoices.value.revenue || {});

const wmsStats = computed(() => ({
    warehouses_count: 0,
    bins_count: 0,
    picking_pending: 0,
    picking_in_progress: 0,
    picking_completed: 0,
    packing_pending: 0,
    packing_in_progress: 0,
    packing_completed: 0,
    cycle_counts_count: 0,
    cycle_counts_completed: 0,
    ...(overview.value.wms || {}),
}));

const rmaStats = computed(() => ({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
    completed: 0,
    refunded_amount: 0,
    ...(overview.value.rma || {}),
}));

const workflowStats = computed(() => ({
    total: 0,
    active: 0,
    inactive: 0,
    executions_total: 0,
    executions_completed: 0,
    executions_failed: 0,
    ...(overview.value.workflows || {}),
}));

const auditStats = computed(() => ({
    total: 0,
    today: 0,
    ...(overview.value.audit || {}),
}));

const analyticsStats = computed(() => ({
    dashboards: 0,
    reports: 0,
    metrics: 0,
    ...(overview.value.analytics || {}),
}));

const recentAuditLogs = computed(() => overview.value.recent_audit_logs || []);

/** Summed rather than added in the template, where a string count would concatenate. */
const activePickingCount = computed(() => (
    toNumber(wmsStats.value.picking_pending) + toNumber(wmsStats.value.picking_in_progress)
));

/**
 * Reads as a dash until something has actually run: a fresh install with no
 * executions was being congratulated with a 100% success rate.
 */
const automationSuccessRate = computed(() => {
    const total = toNumber(workflowStats.value.executions_total);
    if (total <= 0) return '—';

    const rate = Math.round((toNumber(workflowStats.value.executions_completed) / total) * 100);
    return `${formatNumber(rate)}%`;
});

/* ---- Headline figures, kept numeric so both the cards and the strip read them ---- */

const toNumber = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const productsTotal = computed(() => toNumber(products.value.total));
const lowStockCount = computed(() => toNumber(products.value.low_stock));
const invoicesTotal = computed(() => toNumber(invoices.value.total));
const customersTotal = computed(() => toNumber(erp.value.active_customers ?? overview.value.customers?.total));
const totalRevenue = computed(() => toNumber(erp.value.total_revenue ?? revenueBreakdown.value.total));

const localizedName = (row) => {
    const name = locale.value === 'en'
        ? (row?.name_en || row?.name_ar)
        : (row?.name_ar || row?.name_en);

    return name || t('undefined');
};

const stats = computed(() => [
    { title: t('total_products'), value: formatNumber(productsTotal.value), icon: Box, color: '#409eff', route: '/admin/products' },
    { title: t('invoices'), value: formatNumber(invoicesTotal.value), icon: ShoppingCart, color: '#67c23a', route: '/admin/sales/invoices' },
    { title: t('customers'), value: formatNumber(customersTotal.value), icon: User, color: '#e6a23c', route: '/admin/sales/customers' },
    { title: t('revenue'), value: formatMoney(totalRevenue.value), icon: TrendCharts, color: '#f56c6c', route: '/admin/analytics/financial' },
]);

/**
 * The executive strip reads the same figures the cards do.
 *
 * It used to re-read the rendered card strings â€” taking the *product* count for
 * the low-stock tile, then stripping non-ASCII digits from it, which erases an
 * Arabic-Indic number completely and left the tile blank. Reading the numbers
 * instead of their own formatting removes both faults at once.
 */
const executiveHighlights = computed(() => [
    { label: t('revenue'), value: formatMoney(totalRevenue.value) },
    { label: t('low_inventory'), value: formatNumber(lowStockCount.value) },
    { label: t('customers'), value: formatNumber(customersTotal.value) },
    { label: t('invoices'), value: formatNumber(invoicesTotal.value) },
]);

const detailStats = computed(() => [
    { title: t('monthly_sales'), value: formatMoney(erp.value.monthly_sales), icon: TrendCharts, color: '#67c23a', route: '/admin/reports/sales' },
    { title: t('expenses'), value: formatMoney(erp.value.total_expenses), icon: ShoppingCart, color: '#f56c6c', route: '/admin/accounting' },
    { title: t('pending_invoices'), value: formatNumber(erp.value.pending_invoices), icon: Box, color: '#e6a23c', route: '/admin/sales/invoices' },
    { title: t('low_inventory'), value: formatNumber(lowStockCount.value), icon: Box, color: '#f56c6c', route: '/admin/inventory' },
    { title: t('quotes'), value: formatNumber(overview.value.quotes?.total), icon: TrendCharts, color: '#8c6dfd', route: '/admin/sales/quotes' },
    { title: t('sales_orders'), value: formatNumber(overview.value.sales_orders?.total), icon: ShoppingCart, color: '#67c23a', route: '/admin/sales/sales-orders' },
    { title: t('production_orders'), value: formatNumber(overview.value.production?.total), icon: Box, color: '#f56c6c', route: '/admin/production' },
    { title: t('salaries'), value: formatNumber(overview.value.payrolls?.total), icon: User, color: '#409eff', route: '/admin/hr/payrolls' },
]);

const revenueMetrics = computed(() => [
    { label: t('today'), value: formatMoney(revenueBreakdown.value.today), percent: getPercent(revenueBreakdown.value.today, totalRevenue.value) },
    { label: t('this_week'), value: formatMoney(revenueBreakdown.value.week), percent: getPercent(revenueBreakdown.value.week, totalRevenue.value) },
    { label: t('this_month'), value: formatMoney(revenueBreakdown.value.month), percent: getPercent(revenueBreakdown.value.month, totalRevenue.value) },
]);

const statusGroups = computed(() => [
    {
        title: t('billing_status'),
        items: [
            { label: t('paid'), value: invoices.value.paid, description: t('successfully_completed_invoices') },
            { label: t('suspended'), value: invoices.value.pending, description: t('invoices_that_need_follow_up') },
            { label: t('canceled'), value: invoices.value.cancelled, description: t('canceled_or_refunded_invoices') },
        ],
    },
    {
        title: t('status_of_payments'),
        items: [
            { label: t('complete'), value: overview.value.payments?.completed, description: t('completed_payment_from_customers') },
            { label: t('suspended'), value: overview.value.payments?.pending, description: t('payment_is_waiting_for_processing') },
            { label: t('refundable'), value: overview.value.payments?.refunded, description: t('refunds_payments_to_customers') },
        ],
    },
    {
        title: t('production_status'),
        items: [
            { label: t('suspended'), value: overview.value.production?.pending, description: t('uninitiated_production_orders') },
            { label: t('under_implementation'), value: overview.value.production?.in_progress, description: t('current_production_orders') },
            { label: t('complete'), value: overview.value.production?.completed, description: t('orders_ready_for_delivery') },
        ],
    },
]);

const recentSales = computed(() => (overview.value.recent_invoices || []).map((invoice) => ({
    id: invoice.invoice_number || invoice.id || '#',
    customer: invoice.customer_name || t('client'),
    amount: formatMoney(invoice.total),
    // Kept as the raw identifier; the table translates it for display, so the
    // tag colour is decided by what the model returned rather than by matching
    // an English word against an already-translated label.
    status: invoice.status,
})));

const topProducts = computed(() => (overview.value.top_products || []).map((product) => ({
    id: product.id,
    name: localizedName(product),
    sales: toNumber(product.stock_quantity),
    price: formatMoney(product.price),
    image: product.image || '',
})));

const lowStockProducts = computed(() => (overview.value.low_stock_products || []).map((product) => ({
    name: localizedName(product),
    sku: product.sku || '-',
    stock_quantity: toNumber(product.stock_quantity),
    min_stock: toNumber(product.min_stock),
})));

const invoiceStatusCounts = computed(() => ({
    paid: toNumber(invoices.value.paid),
    pending: toNumber(invoices.value.pending),
    cancelled: toNumber(invoices.value.cancelled),
}));

const auditActionCounts = computed(() => {
    const counts = { create: 0, update: 0, delete: 0, other: 0 };
    recentAuditLogs.value.forEach((log) => {
        if (counts[log.action] !== undefined) {
            counts[log.action] += 1;
        } else {
            counts.other += 1;
        }
    });
    return counts;
});

/** The moment the figures on screen were fetched, so "last update" means something. */
const lastUpdatedLabel = computed(() => {
    if (!lastUpdatedAt.value) return '';

    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(lastUpdatedAt.value);
});


const getPercent = (value, total) => {
    if (!total || !value) {
        return 0;
    }
    return Math.min(100, Math.round((Number(value) / Number(total)) * 100));
};

// ---------------------------------------------------------------------------
// Charts (ECharts) - lazily initialized per tab so containers have real size
// ---------------------------------------------------------------------------
const revenueTrendChartRef = ref(null);
const invoiceStatusChartRef = ref(null);
const wmsStatusChartRef = ref(null);
const wmsAccuracyChartRef = ref(null);
const rmaStatusChartRef = ref(null);
const workflowChartRef = ref(null);
const auditActionChartRef = ref(null);

const chartInstances = {};

const renderChart = (chartRef, key, option) => {
    const element = chartRef.value;
    if (!element) return;

    const existing = chartInstances[key];

    // A tab pane that was torn down and rebuilt leaves the cached instance bound
    // to a node no longer in the document, where `setOption` paints into nothing
    // and the card shows an empty box.
    if (existing && existing.getDom() !== element) {
        existing.dispose();
        delete chartInstances[key];
    }

    if (!chartInstances[key]) {
        chartInstances[key] = echarts.init(element);
    }

    chartInstances[key].setOption(option, true);
    // The pane is measured only once it is visible; a chart drawn on the tick a
    // tab opened would otherwise keep the zero width it was initialised at.
    chartInstances[key].resize();
};

const donutOption = (items) => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0, textStyle: { fontSize: 12 } },
    series: [{
        type: 'pie',
        radius: ['55%', '78%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: false },
        data: items.map((i) => ({ name: i.name, value: i.value, itemStyle: { color: i.color } }))
    }]
});

const renderRevenueTrend = () => {
    const dates = salesTrend.value.map((d) => d.date || d.period || '');
    const revenue = salesTrend.value.map((d) => Number(d.revenue) || 0);
    const orders = salesTrend.value.map((d) => Number(d.orders) || 0);

    renderChart(revenueTrendChartRef, 'revenueTrend', {
        tooltip: { trigger: 'axis' },
        legend: { data: [t('revenue'), t('sales_orders')], top: 0 },
        grid: { left: 10, right: 10, top: 40, bottom: 10, containLabel: true },
        xAxis: { type: 'category', data: dates, axisLine: { lineStyle: { color: '#cbd5e1' } } },
        yAxis: [
            { type: 'value', name: t('revenue'), splitLine: { lineStyle: { color: '#f1f5f9' } } },
            { type: 'value', name: t('sales_orders'), splitLine: { show: false } }
        ],
        series: [
            {
                name: t('revenue'),
                type: 'line',
                smooth: true,
                yAxisIndex: 0,
                data: revenue,
                areaStyle: { color: 'rgba(102,126,234,0.15)' },
                lineStyle: { color: '#667eea', width: 3 },
                itemStyle: { color: '#667eea' }
            },
            {
                name: t('sales_orders'),
                type: 'bar',
                yAxisIndex: 1,
                data: orders,
                barWidth: '40%',
                itemStyle: { color: 'rgba(103,194,58,0.55)', borderRadius: [4, 4, 0, 0] }
            }
        ]
    });
};

const renderInvoiceStatusChart = () => {
    renderChart(invoiceStatusChartRef, 'invoiceStatus', donutOption([
        { name: t('chart_paid'), value: invoiceStatusCounts.value.paid || 0, color: '#67c23a' },
        { name: t('chart_pending'), value: invoiceStatusCounts.value.pending || 0, color: '#e6a23c' },
        { name: t('chart_cancelled'), value: invoiceStatusCounts.value.cancelled || 0, color: '#f56c6c' }
    ]));
};

const renderWmsStatusChart = () => {
    renderChart(wmsStatusChartRef, 'wmsStatus', {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { bottom: 0 },
        grid: { left: 10, right: 20, top: 20, bottom: 40, containLabel: true },
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: [t('wms.picking_lists'), t('wms.packing_lists')] },
        series: [
            {
                name: t('chart_pending'),
                type: 'bar',
                stack: 'total',
                data: [wmsStats.value.picking_pending, wmsStats.value.packing_pending],
                itemStyle: { color: '#e6a23c' }
            },
            {
                name: t('chart_in_progress'),
                type: 'bar',
                stack: 'total',
                data: [wmsStats.value.picking_in_progress, wmsStats.value.packing_in_progress],
                itemStyle: { color: '#409eff' }
            },
            {
                name: t('chart_completed'),
                type: 'bar',
                stack: 'total',
                data: [wmsStats.value.picking_completed, wmsStats.value.packing_completed],
                itemStyle: { color: '#67c23a' }
            }
        ]
    });
};

const renderWmsAccuracyGauge = () => {
    const percent = getPercent(wmsStats.value.cycle_counts_completed, wmsStats.value.cycle_counts_count);
    renderChart(wmsAccuracyChartRef, 'wmsAccuracy', {
        series: [{
            type: 'gauge',
            startAngle: 210,
            endAngle: -30,
            min: 0,
            max: 100,
            progress: { show: true, width: 14, itemStyle: { color: '#67c23a' } },
            axisLine: { lineStyle: { width: 14, color: [[1, '#ebedf3']] } },
            pointer: { show: false },
            axisTick: { show: false },
            splitLine: { show: false },
            axisLabel: { show: false },
            detail: {
                valueAnimation: true,
                fontSize: 26,
                fontWeight: 700,
                color: '#1f2d3d',
                formatter: '{value}%',
                offsetCenter: [0, '5%']
            },
            data: [{ value: percent, name: t('completion_rate') }]
        }]
    });
};

const renderRmaStatusChart = () => {
    renderChart(rmaStatusChartRef, 'rmaStatus', donutOption([
        { name: t('chart_pending'), value: rmaStats.value.pending || 0, color: '#e6a23c' },
        { name: t('chart_approved'), value: rmaStats.value.approved || 0, color: '#409eff' },
        { name: t('chart_rejected'), value: rmaStats.value.rejected || 0, color: '#f56c6c' },
        { name: t('chart_completed'), value: rmaStats.value.completed || 0, color: '#67c23a' }
    ]));
};

const renderWorkflowChart = () => {
    renderChart(workflowChartRef, 'workflow', donutOption([
        { name: t('chart_completed'), value: workflowStats.value.executions_completed || 0, color: '#67c23a' },
        { name: t('chart_failed'), value: workflowStats.value.executions_failed || 0, color: '#f56c6c' }
    ]));
};

const renderAuditActionChart = () => {
    const counts = auditActionCounts.value;
    renderChart(auditActionChartRef, 'auditAction', donutOption([
        { name: t('chart_create'), value: counts.create, color: '#67c23a' },
        { name: t('chart_update'), value: counts.update, color: '#409eff' },
        { name: t('chart_delete'), value: counts.delete, color: '#f56c6c' },
        { name: t('chart_other'), value: counts.other, color: '#909399' }
    ]));
};

const tabChartRenderers = {
    overview: () => { renderRevenueTrend(); renderInvoiceStatusChart(); },
    wms: () => { renderWmsStatusChart(); renderWmsAccuracyGauge(); },
    rma: () => renderRmaStatusChart(),
    workflows: () => renderWorkflowChart(),
    audit: () => renderAuditActionChart(),
    analytics: null
};

const renderTabCharts = async (tab) => {
    if (loading.value) return;
    const renderer = tabChartRenderers[tab];
    if (!renderer) return;
    await nextTick();
    renderer();
};

const resizeCharts = () => {
    Object.values(chartInstances).forEach((chart) => chart && chart.resize());
};

watch(activeTab, (tab) => {
    renderTabCharts(tab);
});

// Series names, axis titles and legends are baked into the option object when a
// chart is drawn, so a language switch has to redraw it. Only the open tab is
// redrawn â€” the others are rebuilt from scratch the moment they are selected.
watch(locale, () => {
    renderTabCharts(activeTab.value);
});

/**
 * Charts also have to follow the *width* they are given, and the sidebar
 * collapsing is not a window resize. Observing the page element catches both,
 * and the frame guard keeps a drag from queueing a redraw per pixel.
 */
const dashboardRef = ref(null);
let resizeObserver = null;
let resizeFrame = null;

const scheduleResize = () => {
    if (resizeFrame) cancelAnimationFrame(resizeFrame);
    resizeFrame = requestAnimationFrame(() => {
        resizeFrame = null;
        resizeCharts();
    });
};

const loadSalesTrend = async () => {
    try {
        const response = await dashboardApi.getSalesTrend({ days: 30, group_by: 'day' });
        salesTrend.value = response.data?.data ?? response.data ?? [];
    } catch (err) {
        salesTrend.value = [];
    }
};

/**
 * Fetches the whole screen.
 *
 * `silent` is what the refresh button uses: replacing a populated dashboard with
 * skeletons on every manual refresh loses the figures the user was reading and
 * makes a two-second request feel like a page load. The button carries its own
 * spinner instead, and the numbers stay put until new ones arrive.
 */
const loadDashboard = async ({ silent = false } = {}) => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }
    error.value = null;

    try {
        const [overviewResponse] = await Promise.all([
            dashboardApi.getOverviewStats(),
            loadSalesTrend(),
        ]);

        overview.value = overviewResponse.data?.data || {};
        lastUpdatedAt.value = new Date();
    } catch (err) {
        error.value = err.response?.data?.message || err.message || t('failed_to_load_dashboard_data');
        console.error('Dashboard load error:', err);
    } finally {
        loading.value = false;
        refreshing.value = false;
        await renderTabCharts(activeTab.value);
    }
};

const refresh = () => loadDashboard({ silent: true });

onMounted(async () => {
    // Settings carry the base currency every amount below is written in, so this
    // is started alongside the figures rather than after them.
    if (Object.keys(settingsStore.data).length === 0) {
        settingsStore.fetch().catch(() => {});
    }

    await loadDashboard();

    if (typeof ResizeObserver !== 'undefined' && dashboardRef.value) {
        resizeObserver = new ResizeObserver(scheduleResize);
        resizeObserver.observe(dashboardRef.value);
    } else {
        window.addEventListener('resize', scheduleResize);
    }
});

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    } else {
        window.removeEventListener('resize', scheduleResize);
    }

    if (resizeFrame) cancelAnimationFrame(resizeFrame);

    Object.values(chartInstances).forEach((chart) => chart && chart.dispose());
});

</script>

<style scoped>
.dashboard {
    padding: 0;
}

.page-header {
    padding: 1.5rem 2rem;
    background: #fff;
    border-bottom: 1px solid #ebedf2;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header-icon {
    flex-shrink: 0;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: white;
    font-size: 1.35rem;
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.35);
}

.page-header-text {
    min-width: 0;
}

.page-header-actions {
    margin-inline-start: auto;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-header-meta {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.small-text {
    font-size: 0.8rem;
    color: #8b96a7;
}

.page-header h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a202c;
    line-height: 1.3;
}

.page-header p {
    margin: 0.2rem 0 0;
    color: #6b7280;
    font-size: 0.9rem;
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
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(30, 41, 59, 0.1) !important;
}

.stat-card.clickable {
    cursor: pointer;
}

.stat-card.clickable:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px rgba(102, 126, 234, 0.18) !important;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
}

.stat-info h3,
.stat-info h4 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a202c;
    word-break: break-word;
}

.stat-info p {
    margin: 0.15rem 0 0;
    color: #6b7280;
    font-size: 0.88rem;
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
    flex-shrink: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mt-4 {
    margin-top: 1.5rem;
}

.mt-2 {
    margin-top: 0.5rem;
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
    min-width: 0;
}

.product-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-info span {
    font-size: 0.82rem;
    color: #666;
}

.product-price {
    font-weight: 600;
    color: #409eff;
    flex-shrink: 0;
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
    word-break: break-word;
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

.chart-box {
    width: 100%;
    height: 300px;
}

.chart-box-sm {
    height: 280px;
}

.gauge-legend {
    text-align: center;
    color: #606f8b;
    font-size: 0.85rem;
}

.gauge-legend p {
    margin: 0.2rem 0;
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

.executive-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin: 1rem 0 1.5rem;
}

.executive-card {
    padding: 1rem 1.25rem;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(255,255,255,0.96), rgba(248,250,252,0.96));
    border: 1px solid #edf2f7;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.executive-label {
    font-size: 0.78rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.executive-card strong {
    font-size: 1.1rem;
    color: #1f2d3d;
}

.low-stock-card {
    border: 1px solid #f56c6c;
    border-radius: 12px;
}

.low-stock-card .el-table th {
    background-color: #fef0f0 !important;
}

.status-description {
    display: block;
    font-size: 0.78rem;
    color: #8b96a7;
    margin-top: 0.2rem;
}

@media (max-width: 992px) {
    .page-header {
        padding: 1rem 1.25rem;
    }

    .executive-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .page-header h1 {
        font-size: 1.35rem;
    }

    .page-header p {
        font-size: 0.85rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
    }

    .stat-info h3,
    .stat-info h4 {
        font-size: 1.3rem;
    }

    .revenue-value strong {
        font-size: 1.5rem;
    }

    .section-header h2 {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .revenue-metrics {
        grid-template-columns: 1fr;
    }

    .page-header h1 {
        font-size: 1.15rem;
    }

    .executive-strip {
        grid-template-columns: 1fr;
    }

    .stat-icon {
        width: 42px;
        height: 42px;
    }

    .stat-icon :deep(.el-icon) {
        font-size: 20px !important;
    }

    .stat-info h3,
    .stat-info h4 {
        font-size: 1.1rem;
    }

    .stat-info p {
        font-size: 0.8rem;
    }

    .revenue-value {
        flex-direction: column;
    }

    .revenue-value strong {
        font-size: 1.25rem;
    }

    .status-item {
        padding: 0.6rem 0.75rem;
    }

    .chart-box {
        height: 240px;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }

    .page-header h1 {
        font-size: 1rem;
    }

    .section-header h2 {
        font-size: 0.9rem;
    }

    .section {
        padding: 0.5rem 0;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .stat-card,
    .status-card {
        margin-bottom: 0.75rem;
    }

    .stat-content {
        gap: 0.75rem;
    }
}

.gateway-card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
    border: 1px solid #ebedf2;
    margin-bottom: 1rem;
    height: calc(100% - 1rem);
}
.gateway-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
    border-color: #409eff;
}
.gateway-card h3 {
    margin: 1rem 0 0.5rem;
    font-size: 1.1rem;
    color: #1f2d3d;
}
.gateway-card p {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0;
}
.tab-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 600;
}
.dashboard-tabs :deep(.el-tabs__item) {
    padding: 0 20px;
    height: 48px;
    line-height: 48px;
}

.dashboard-tabs :deep(.el-tabs__nav-wrap) {
    padding: 0 2rem;
}

.dashboard-tabs :deep(.el-tabs__header) {
    margin-bottom: 1.5rem;
    background: #fff;
    border-bottom: 1px solid #ebedf2;
}

.dashboard-tabs :deep(.el-tabs__item.is-active) {
    color: #667eea;
}

.dashboard-tabs :deep(.el-tabs__item:hover) {
    color: #764ba2;
}

.dashboard-tabs :deep(.el-tabs__active-bar) {
    background: var(--admin-gradient-primary, linear-gradient(90deg, #667eea 0%, #764ba2 100%));
    height: 3px;
}

.dashboard-tabs :deep(.el-tabs__content) {
    padding: 0 2rem 2rem;
}
</style>
