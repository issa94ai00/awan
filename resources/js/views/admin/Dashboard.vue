<template>
    <div class="dashboard" v-loading="loading">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> {{ $t('control_panel_var', { value: siteName }) }}</h1>
            <p>{{ tagline }}</p>
        </div>

        <el-alert
            v-if="error"
            :title="error"
            type="error"
            show-icon
            closable
            class="dashboard-alert"
        >
        </el-alert>
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

                <div v-if="lowStockProducts.length > 0" class="section mt-4">
                    <div class="section-header">
                        <h2><i class="fas fa-exclamation-triangle" style="color: #e6a23c;"></i> {{ $t('low_stock_alerts') }}</h2>
                    </div>
                    <el-card shadow="hover" class="low-stock-card">
                        <el-table :data="lowStockProducts" style="width: 100%" :stripe="true">
                            <el-table-column prop="name" :label="$t('product')"></el-table-column>
                            <el-table-column prop="sku" label="SKU" width="120"></el-table-column>
                            <el-table-column prop="stock_quantity" :label="$t('current_stock')" width="120"></el-table-column>
                            <el-table-column prop="min_stock" :label="$t('minimum')" width="120"></el-table-column>
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
                        <h2>{{ $t('transaction_status') }}</h2>
                    </div>
                    <el-row :gutter="20">
                        <el-col :xs="24" :lg="16">
                            <el-card shadow="hover" class="revenue-card">
                                <template #header>
                                    <div class="status-header">
                                        <span>{{ $t('look_at_revenue') }}</span>
                                        <span class="small-text">{{ $t('latest_update_automatically') }}</span>
                                    </div>
                                </template>
                                <div class="revenue-summary">
                                    <div class="revenue-value">
                                        <span>{{ $t('total_revenue') }}</span>
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
                                        <span>{{ $t('quick_summary') }}</span>
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
                                    <span>{{ $t('recent_sales') }}</span>
                                </div>
                            </template>
                            <el-table :data="recentSales" style="width: 100%" :stripe="true">
                                <el-table-column prop="id" :label="$t('invoice_number')" width="120"></el-table-column>
                                <el-table-column prop="customer" :label="$t('client')"></el-table-column>
                                <el-table-column prop="amount" :label="$t('amount')"></el-table-column>
                                <el-table-column prop="status" :label="$t('status')">
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
                                <span>{{ $t('top_products') }}</span>
                            </template>
                            <div class="top-products">
                                <div v-for="product in topProducts" :key="product.id" class="product-item">
                                    <el-avatar :size="40" :src="product.image"></el-avatar>
                                    <div class="product-info">
                                        <h4>{{ product.name }}</h4>
                                        <span>{{ product.sales }} {{ $t('lonliness') }}</span>
                                    </div>
                                    <span class="product-price">{{ product.price }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 2. WAREHOUSE MANAGEMENT SYSTEM (WMS) TAB -->
            <el-tab-pane name="wms">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Location /></el-icon>
                        <span>{{ $t('wms') }}</span>
                    </span>
                </template>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #409eff;">
                                    <el-icon :size="28" color="white"><Location /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(wmsStats.warehouses_count) }}</h3>
                                    <p>{{ $t('warehouses') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #67c23a;">
                                    <el-icon :size="28" color="white"><List /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(wmsStats.bins_count) }}</h3>
                                    <p>{{ $t('bins') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #e6a23c;">
                                    <el-icon :size="28" color="white"><Checked /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(wmsStats.picking_pending + wmsStats.picking_in_progress) }}</h3>
                                    <p>{{ $t('picking_packing') }} ({{ $t('common.active') }})</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #909399;">
                                    <el-icon :size="28" color="white"><Calendar /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(wmsStats.cycle_counts_count) }}</h3>
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
                                <span>{{ $t('wms_tasks_status') }}</span>
                            </template>
                            <div style="padding: 10px 0;">
                                <h4>{{ $t('wms.picking_lists') }}</h4>
                                <div class="progress-bar-row mt-2">
                                    <div class="progress-label">
                                        <span>{{ $t('pending_count', { count: wmsStats.picking_pending }) }} | {{ $t('in_progress_count', { count: wmsStats.picking_in_progress }) }}</span>
                                        <strong>{{ $t('completed_count', { count: wmsStats.picking_completed }) }}</strong>
                                    </div>
                                    <el-progress :percentage="getPercent(wmsStats.picking_completed, wmsStats.picking_pending + wmsStats.picking_in_progress + wmsStats.picking_completed)" status="success"></el-progress>
                                </div>

                                <h4 class="mt-4">{{ $t('wms.packing_lists') }}</h4>
                                <div class="progress-bar-row mt-2">
                                    <div class="progress-label">
                                        <span>{{ $t('pending_count', { count: wmsStats.packing_pending }) }} | {{ $t('in_progress_count', { count: wmsStats.packing_in_progress }) }}</span>
                                        <strong>{{ $t('completed_count', { count: wmsStats.packing_completed }) }}</strong>
                                    </div>
                                    <el-progress :percentage="getPercent(wmsStats.packing_completed, wmsStats.packing_pending + wmsStats.packing_in_progress + wmsStats.packing_completed)" status="success"></el-progress>
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="12">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ $t('cycle_counts_accuracy') }}</span>
                            </template>
                            <div style="text-align: center; padding: 20px 0;">
                                <el-progress type="circle" :percentage="getPercent(wmsStats.cycle_counts_completed, wmsStats.cycle_counts_count)" :width="130">
                                    <template #default="{ percentage }">
                                        <div style="font-size: 1.5rem; font-weight: bold;">{{ percentage }}%</div>
                                        <div style="font-size: 0.8rem; color: #909399;">{{ $t('completion_rate') }}</div>
                                    </template>
                                </el-progress>
                                <div class="mt-4">
                                    <p>{{ $t('total_cycle_counts') }}: <strong>{{ formatCount(wmsStats.cycle_counts_count) }}</strong></p>
                                    <p>{{ $t('completed_cycle_counts') }}: <strong>{{ formatCount(wmsStats.cycle_counts_completed) }}</strong></p>
                                </div>
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
            </el-tab-pane>

            <!-- 3. RETURNS & RMA MANAGEMENT TAB -->
            <el-tab-pane name="rma">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Refresh /></el-icon>
                        <span>{{ $t('rma') }}</span>
                    </span>
                </template>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #409eff;">
                                    <el-icon :size="28" color="white"><Refresh /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(rmaStats.total) }}</h3>
                                    <p>{{ $t('rma_requests') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #e6a23c;">
                                    <el-icon :size="28" color="white"><Clock /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(rmaStats.pending) }}</h3>
                                    <p>{{ $t('pending_rma') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #f56c6c;">
                                    <el-icon :size="28" color="white"><Wallet /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatAmount(rmaStats.refunded_amount) }}</h3>
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
                                    <el-tag type="warning">{{ formatCount(rmaStats.pending) }}</el-tag>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                    <span>{{ $t('rma_approved') }}</span>
                                    <el-tag type="primary">{{ formatCount(rmaStats.approved) }}</el-tag>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                    <span>{{ $t('rma_rejected') }}</span>
                                    <el-tag type="danger">{{ formatCount(rmaStats.rejected) }}</el-tag>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>{{ $t('rma_completed') }}</span>
                                    <el-tag type="success">{{ formatCount(rmaStats.completed) }}</el-tag>
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="12">
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
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 4. WORKFLOWS & AUTOMATION TAB -->
            <el-tab-pane name="workflows">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Cpu /></el-icon>
                        <span>{{ $t('workflows') }}</span>
                    </span>
                </template>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #409eff;">
                                    <el-icon :size="28" color="white"><Cpu /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(workflowStats.total) }}</h3>
                                    <p>{{ $t('workflows') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #67c23a;">
                                    <el-icon :size="28" color="white"><Checked /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(workflowStats.active) }}</h3>
                                    <p>{{ $t('active_workflows') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #8c6dfd;">
                                    <el-icon :size="28" color="white"><Connection /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(workflowStats.executions_total) }}</h3>
                                    <p>{{ $t('automation_executions') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #67c23a;">
                                    <el-icon :size="28" color="white"><TrendCharts /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ workflowStats.executions_total ? Math.round((workflowStats.executions_completed / workflowStats.executions_total) * 100) : 100 }}%</h3>
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
                                    <el-tag type="success">{{ formatCount(workflowStats.executions_completed) }}</el-tag>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>{{ $t('failed_executions') }}</span>
                                    <el-tag type="danger">{{ formatCount(workflowStats.executions_failed) }}</el-tag>
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="12">
                        <el-card shadow="hover">
                            <template #header>
                                <span>{{ $t('automation_workflow') }}</span>
                            </template>
                            <div style="padding: 20px 0; text-align: center;">
                                <el-icon :size="48" color="#8c6dfd"><Cpu /></el-icon>
                                <p class="mt-2" style="color: #606f8b;">{{ $t('workflow_description') }}</p>
                                <div class="mt-4">
                                    <el-button type="primary" @click="$router.push('/admin/workflows')">{{ $t('view_all_workflows') }}</el-button>
                                    <el-button type="success" plain @click="$router.push('/admin/workflows/create')">{{ $t('create_workflow') }}</el-button>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 5. SECURITY & AUDIT FEED TAB -->
            <el-tab-pane name="audit">
                <template #label>
                    <span class="tab-label">
                        <el-icon><View /></el-icon>
                        <span>{{ $t('audit_logs') }}</span>
                    </span>
                </template>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #409eff;">
                                    <el-icon :size="28" color="white"><View /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(auditStats.total) }}</h3>
                                    <p>{{ $t('total_audited_operations') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #e6a23c;">
                                    <el-icon :size="28" color="white"><Clock /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(auditStats.today) }}</h3>
                                    <p>{{ $t('todays_operations') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <div class="section mt-4">
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
                </div>
            </el-tab-pane>

            <!-- 6. BI & BUSINESS INTELLIGENCE TAB -->
            <el-tab-pane name="analytics">
                <template #label>
                    <span class="tab-label">
                        <el-icon><DataAnalysis /></el-icon>
                        <span>{{ $t('bi_analytics') }}</span>
                    </span>
                </template>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #409eff;">
                                    <el-icon :size="28" color="white"><DataAnalysis /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(analyticsStats.dashboards) }}</h3>
                                    <p>{{ $t('analytics_dashboards') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #67c23a;">
                                    <el-icon :size="28" color="white"><Document /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(analyticsStats.reports) }}</h3>
                                    <p>{{ $t('analytics_reports') }}</p>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-card class="stat-card" shadow="hover">
                            <div class="stat-content">
                                <div class="stat-icon" style="background: #e6a23c;">
                                    <el-icon :size="28" color="white"><Odometer /></el-icon>
                                </div>
                                <div class="stat-info">
                                    <h3>{{ formatCount(analyticsStats.metrics) }}</h3>
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
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Box, ShoppingCart, User, TrendCharts, Location, Checked, Calendar, Cpu, Bell, Connection, View, Setting, Memo, Collection, UserFilled, DataAnalysis
} from '@element-plus/icons-vue';
import { dashboardApi } from '@/api/dashboard';
import { useSettingsStore } from '@/stores/settings';

const { locale } = useI18n();
const settingsStore = useSettingsStore();

const siteName = computed(() => {
    if (!settingsStore.data) return window.t('site_name');
    return locale.value === 'en'
        ? (settingsStore.data.site_name_en || settingsStore.data.site_name)
        : (settingsStore.data.site_name_ar || settingsStore.data.site_name);
});

const tagline = computed(() => {
    if (!settingsStore.data) return window.t('system_performance_overview_and_statistics');
    return locale.value === 'en'
        ? (settingsStore.data.site_tagline_en || settingsStore.data.site_tagline || 'System performance overview and statistics')
        : (settingsStore.data.site_tagline_ar || settingsStore.data.site_tagline || window.t('system_performance_overview_and_statistics'));
});

watch(siteName, (value) => {
    document.title = window.t('control_panel_var').replace('{value}', value);
}, { immediate: true });

const activeTab = ref('overview');

const wmsStats = ref({
    warehouses_count: 0,
    bins_count: 0,
    picking_pending: 0,
    picking_in_progress: 0,
    picking_completed: 0,
    packing_pending: 0,
    packing_in_progress: 0,
    packing_completed: 0,
    cycle_counts_count: 0,
    cycle_counts_completed: 0
});

const rmaStats = ref({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
    completed: 0,
    refunded_amount: 0
});

const workflowStats = ref({
    total: 0,
    active: 0,
    inactive: 0,
    executions_total: 0,
    executions_completed: 0,
    executions_failed: 0
});

const notificationStats = ref({
    total: 0,
    templates: 0
});

const auditStats = ref({
    total: 0,
    today: 0
});

const recentAuditLogs = ref([]);

const analyticsStats = ref({
    dashboards: 0,
    reports: 0,
    metrics: 0
});

const stats = ref([
    { title: window.t('total_products'), value: '...', icon: Box, color: '#409eff' },
    { title: window.t('invoices'), value: '...', icon: ShoppingCart, color: '#67c23a' },
    { title: window.t('customers'), value: '...', icon: User, color: '#e6a23c' },
    { title: window.t('revenue'), value: '...', icon: TrendCharts, color: '#f56c6c' }
]);

const detailStats = ref([]);
const statusGroups = ref([]);
const revenueMetrics = ref([
    { label: window.t('today'), value: window.t('0_sar'), percent: 0 },
    { label: window.t('this_week'), value: window.t('0_sar'), percent: 0 },
    { label: window.t('this_month'), value: window.t('0_sar'), percent: 0 }
]);
const recentSales = ref([]);
const topProducts = ref([]);
const lowStockProducts = ref([]);
const loading = ref(false);
const error = ref(null);

const getStatusType = (status) => {
    if (!status) return 'info';
    const s = String(status).toLowerCase();
    if (s.includes('paid') || s.includes('مكتمل') || s.includes('complete') || s.includes('نجاح') || s.includes('success') || s.includes('processed') || s.includes('delivered') || s.includes('approved') || s.includes('موافق') || s.includes('نشط') || s.includes('active')) {
        return 'success';
    }
    if (s.includes('pending') || s.includes('قيد المعالجة') || s.includes('معلق') || s.includes('under') || s.includes('progress') || s.includes('انتظار') || s.includes('suspended')) {
        return 'warning';
    }
    if (s.includes('cancelled') || s.includes('ملغي') || s.includes('failed') || s.includes('failing') || s.includes('rejected') || s.includes('مرفوض') || s.includes('danger') || s.includes('error')) {
        return 'danger';
    }
    return 'info';
};


const formatAmount = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return window.t('0_sar');
    }
    const currentLocale = locale.value === 'ar' ? 'ar-SA' : 'en-US';
    return new Intl.NumberFormat(currentLocale, {
        style: 'currency',
        currency: 'SAR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(value);
};


const formatCount = (value) => {
    const currentLocale = locale.value === 'ar' ? 'ar-SA' : 'en-US';
    return (value ?? 0).toLocaleString(currentLocale);
};


const getPercent = (value, total) => {
    if (!total || !value) {
        return 0;
    }
    return Math.min(100, Math.round((Number(value) / Number(total)) * 100));
};


const loadDashboard = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await dashboardApi.getOverviewStats();
        const overview = response.data?.data || {};

        stats.value = [
            { title: window.t('total_products'), value: formatCount(overview.products?.total), icon: Box, color: '#409eff' },
            { title: window.t('invoices'), value: formatCount(overview.invoices?.total), icon: ShoppingCart, color: '#67c23a' },
            { title: window.t('customers'), value: formatCount(overview.erp?.active_customers ?? overview.customers?.total), icon: User, color: '#e6a23c' },
            { title: window.t('revenue'), value: formatAmount(overview.erp?.total_revenue ?? overview.invoices?.revenue?.total), icon: TrendCharts, color: '#f56c6c' }
        ];

        detailStats.value = [
            { title: window.t('monthly_sales'), value: formatAmount(overview.erp?.monthly_sales ?? 0), icon: TrendCharts, color: '#67c23a' },
            { title: window.t('expenses'), value: formatAmount(overview.erp?.total_expenses ?? 0), icon: ShoppingCart, color: '#f56c6c' },
            { title: window.t('pending_invoices'), value: formatCount(overview.erp?.pending_invoices ?? 0), icon: Box, color: '#e6a23c' },
            { title: window.t('low_inventory'), value: formatCount(overview.products?.low_stock ?? 0), icon: Box, color: '#f56c6c' },
            { title: window.t('quotes'), value: formatCount(overview.quotes?.total), icon: TrendCharts, color: '#8c6dfd' },
            { title: window.t('sales_orders'), value: formatCount(overview.sales_orders?.total), icon: ShoppingCart, color: '#67c23a' },
            { title: window.t('production_orders'), value: formatCount(overview.production?.total), icon: Box, color: '#f56c6c' },
            { title: window.t('salaries'), value: formatCount(overview.payrolls?.total), icon: User, color: '#409eff' }
        ];

        lowStockProducts.value = (overview.low_stock_products ?? []).map((product) => ({
            name: locale.value === 'en'
                ? (product.name_en || product.name_ar || window.t('project'))
                : (product.name_ar || product.name_en || window.t('project')),
            sku: product.sku || '-',
            stock_quantity: product.stock_quantity ?? 0,
            min_stock: product.min_stock ?? 0
        }));

        const totalRevenue = overview.invoices?.revenue?.total || 0;
        revenueMetrics.value = [
            { label: window.t('today'), value: formatAmount(overview.invoices?.revenue?.today ?? 0), percent: getPercent(overview.invoices?.revenue?.today, totalRevenue) },
            { label: window.t('this_week'), value: formatAmount(overview.invoices?.revenue?.week ?? 0), percent: getPercent(overview.invoices?.revenue?.week, totalRevenue) },
            { label: window.t('this_month'), value: formatAmount(overview.invoices?.revenue?.month ?? 0), percent: getPercent(overview.invoices?.revenue?.month, totalRevenue) }
        ];

        statusGroups.value = [
            {
                title: window.t('billing_status'),
                items: [
                    { label: window.t('paid'), value: overview.invoices?.paid, description: window.t('successfully_completed_invoices')},
                    { label: window.t('suspended'), value: overview.invoices?.pending, description: window.t('invoices_that_need_follow_up')},
                    { label: window.t('canceled'), value: overview.invoices?.cancelled, description: window.t('canceled_or_refunded_invoices')}
                ]
            },
            {
                title: window.t('status_of_payments'),
                items: [
                    { label: window.t('complete'), value: overview.payments?.completed, description: window.t('completed_payment_from_customers')},
                    { label: window.t('suspended'), value: overview.payments?.pending, description: window.t('payment_is_waiting_for_processing')},
                    { label: window.t('refundable'), value: overview.payments?.refunded, description: window.t('refunds_payments_to_customers')}
                ]
            },
            {
                title: window.t('production_status'),
                items: [
                    { label: window.t('suspended'), value: overview.production?.pending, description: window.t('uninitiated_production_orders')},
                    { label: window.t('under_implementation'), value: overview.production?.in_progress, description: window.t('current_production_orders')},
                    { label: window.t('complete'), value: overview.production?.completed, description: window.t('orders_ready_for_delivery')}
                ]
            }
        ];

        recentSales.value = (overview.recent_invoices ?? []).map((invoice) => ({
            id: invoice.invoice_number || invoice.id || '#',
            customer: invoice.customer_name || window.t('client'),
            amount: formatAmount(invoice.total),
            status: invoice.status
        }));

        topProducts.value = (overview.top_products ?? []).map((product) => ({
            id: product.id,
            name: locale.value === 'en'
                ? (product.name_en || product.name_ar || window.t('project'))
                : (product.name_ar || product.name_en || window.t('project')),
            sales: product.stock_quantity ?? 0,
            price: formatAmount(product.price),
            image: product.image || ''
        }));

        // Assignments from step140.txt
        wmsStats.value = overview.wms || wmsStats.value;
        rmaStats.value = overview.rma || rmaStats.value;
        workflowStats.value = overview.workflows || workflowStats.value;
        notificationStats.value = overview.notifications || notificationStats.value;
        auditStats.value = overview.audit || auditStats.value;
        recentAuditLogs.value = overview.recent_audit_logs || [];
        analyticsStats.value = overview.analytics || analyticsStats.value;

    } catch (err) {
        error.value = err.response?.data?.message || err.message || window.t('failed_to_load_dashboard_data');
        console.error('Dashboard load error:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadDashboard();
    if (Object.keys(settingsStore.data).length === 0) {
        settingsStore.fetch().catch(() => {});
    }
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
}

.page-header h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-header p {
    margin: 0.5rem 0 0;
    color: #6b7280;
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
    flex-shrink: 0;
}

.stat-info h3,
.stat-info h4 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #333;
    word-break: break-word;
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
</style>
