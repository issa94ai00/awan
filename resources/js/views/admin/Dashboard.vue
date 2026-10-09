<template>
    <div class="dashboard" ref="dashboardRef">
        <header class="dash-hero">
            <div class="dash-hero-text">
                <span class="dash-date">{{ todayLabel }}</span>
                <h1>{{ greeting }}</h1>
                <p>{{ siteName }} · {{ tagline }}</p>
                <!-- Says out loud which currency the figures below are in, so a
                     total is never read as a number in the wrong money. -->
                <div class="dash-hero-meta">
                    <el-tag size="small" effect="plain" round class="currency-tag">
                        {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                    </el-tag>
                    <button type="button" class="updated-chip" :disabled="refreshing" @click="refresh">
                        <el-icon :class="{ spinning: refreshing }"><Refresh /></el-icon>
                        <span v-if="updatedAgo">{{ $t('dash_updated_ago', { time: updatedAgo }) }}</span>
                        <span v-else>{{ $t('refresh') }}</span>
                    </button>
                </div>
            </div>
            <div class="dash-hero-actions">
                <el-button type="primary" :icon="DocumentAdd" @click="$router.push('/admin/sales/invoices/create')">
                    {{ $t('dash_new_invoice') }}
                </el-button>
                <el-button :icon="Tickets" @click="$router.push('/admin/sales/sales-orders')">
                    {{ $t('sales_orders') }}
                </el-button>
                <el-button type="success" :icon="Checked" @click="paymentDialogVisible = true">
                    {{ $t('quick_payment') }}
                </el-button>
            </div>
        </header>

        <QuickPaymentDialog v-model="paymentDialogVisible" @saved="refresh" />

        <el-alert
            v-if="error"
            :title="error"
            type="error"
            show-icon
            closable
            class="dashboard-alert"
        >
            <el-button size="small" type="danger" plain @click="loadDashboard()">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ERP Features Dashboard Tabs -->
        <el-tabs v-model="activeTab" class="dashboard-tabs">

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
                    <!-- Headline figures -->
                    <div class="kpi-grid">
                        <router-link
                            v-for="kpi in kpis"
                            :key="kpi.key"
                            :to="kpi.route"
                            class="kpi-card"
                            :class="`tone-${kpi.tone}`"
                        >
                            <div class="kpi-top">
                                <span class="kpi-label">{{ kpi.label }}</span>
                                <span class="kpi-icon"><el-icon><component :is="kpi.icon" /></el-icon></span>
                            </div>
                            <div class="kpi-value">{{ kpi.value }}</div>
                            <div class="kpi-foot">
                                <span
                                    v-if="kpi.delta"
                                    class="kpi-delta"
                                    :class="kpi.delta.direction"
                                    :title="$t('dash_vs_last_month')"
                                >
                                    <el-icon><component :is="kpi.delta.direction === 'down' ? Bottom : Top" /></el-icon>
                                    {{ kpi.delta.label }}
                                </span>
                                <span class="kpi-sub">{{ kpi.sub }}</span>
                            </div>
                        </router-link>
                    </div>

                    <div class="dash-grid">
                        <!-- Revenue trend -->
                        <section class="dash-card span-8">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('revenue_trend_title') }}</h3>
                                    <p>{{ $t('dash_revenue_trend_hint', { days: trendDays }) }}</p>
                                </div>
                                <el-segmented v-model="trendDays" :options="trendRangeOptions" size="small" />
                            </header>
                            <div class="revenue-strip">
                                <div v-for="metric in revenueMetrics" :key="metric.label" class="revenue-strip-item">
                                    <span>{{ metric.label }}</span>
                                    <strong>{{ metric.value }}</strong>
                                </div>
                                <div class="revenue-strip-item">
                                    <span>{{ $t('total_revenue') }}</span>
                                    <!-- Reads the figure, not the fourth stat card. -->
                                    <strong>{{ formatMoney(totalRevenue) }}</strong>
                                </div>
                            </div>
                            <div class="chart-wrap" v-loading="trendLoading">
                                <div ref="revenueTrendChartRef" class="chart-box"></div>
                                <div v-if="!trendLoading && !hasTrendData" class="chart-empty">
                                    <el-icon><TrendCharts /></el-icon>
                                    <span>{{ $t('dash_no_sales_in_range') }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- Needs attention -->
                        <section class="dash-card span-4">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('dash_needs_attention') }}</h3>
                                    <p>{{ $t('dash_needs_attention_hint') }}</p>
                                </div>
                                <span v-if="attentionItems.length" class="count-pill">{{ formatNumber(attentionTotal) }}</span>
                            </header>
                            <ul v-if="attentionItems.length" class="attention-list">
                                <li v-for="item in attentionItems" :key="item.key">
                                    <router-link :to="item.route" class="attention-item" :class="`tone-${item.tone}`">
                                        <span class="attention-icon"><el-icon><component :is="item.icon" /></el-icon></span>
                                        <span class="attention-label">{{ item.label }}</span>
                                        <span class="attention-count">{{ formatNumber(item.count) }}</span>
                                        <el-icon class="attention-go"><component :is="goIcon" /></el-icon>
                                    </router-link>
                                </li>
                            </ul>
                            <div v-else class="all-clear">
                                <el-icon><CircleCheck /></el-icon>
                                <strong>{{ $t('dash_all_clear') }}</strong>
                                <span>{{ $t('dash_all_clear_hint') }}</span>
                            </div>
                        </section>

                        <!-- Recent invoices -->
                        <section class="dash-card span-8">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('recent_sales') }}</h3>
                                </div>
                                <router-link to="/admin/sales/invoices" class="head-link">
                                    {{ $t('view_all') }}<el-icon><component :is="goIcon" /></el-icon>
                                </router-link>
                            </header>
                            <el-table
                                v-if="recentSales.length"
                                :data="recentSales"
                                class="dash-table"
                                row-class-name="clickable-row"
                                @row-click="openInvoice"
                            >
                                <el-table-column prop="number" :label="$t('invoice_number')" min-width="130">
                                    <template #default="{ row }"><span class="mono">{{ row.number }}</span></template>
                                </el-table-column>
                                <el-table-column prop="customer" :label="$t('client')" min-width="160" show-overflow-tooltip />
                                <el-table-column prop="date" :label="$t('date')" min-width="110" />
                                <el-table-column prop="amount" :label="$t('amount')" min-width="130" align="end">
                                    <template #default="{ row }"><strong>{{ row.amount }}</strong></template>
                                </el-table-column>
                                <el-table-column prop="status" :label="$t('status')" min-width="120">
                                    <template #default="{ row }">
                                        <!-- Status is matched as the model identifier the API sent
                                             and translated only for display, so the colour no longer
                                             depends on an English word appearing in an Arabic label. -->
                                        <el-tag :type="statusTagType(row.status)" size="small" round>
                                            {{ statusLabel(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else :image-size="70" :description="$t('dash_no_invoices_yet')">
                                <el-button type="primary" @click="$router.push('/admin/sales/invoices/create')">{{ $t('dash_new_invoice') }}</el-button>
                            </el-empty>
                        </section>

                        <!-- Best sellers -->
                        <section class="dash-card span-4">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('dash_best_sellers') }}</h3>
                                    <p>{{ $t('dash_last_30_days') }}</p>
                                </div>
                            </header>
                            <ol v-if="bestSellers.length" class="rank-list">
                                <li v-for="(product, index) in bestSellers" :key="product.id">
                                    <router-link :to="`/admin/products/${product.id}`" class="rank-item">
                                        <span class="rank-no">{{ index + 1 }}</span>
                                        <EntityImage :src="product.image" type="product" :size="38" shape="square" />
                                        <span class="rank-info">
                                            <strong>{{ product.name }}</strong>
                                            <small>{{ $t('dash_units_sold', { count: formatNumber(product.units) }) }}</small>
                                        </span>
                                        <span class="rank-value">{{ product.revenue }}</span>
                                    </router-link>
                                    <div class="rank-bar"><span :style="{ width: product.share + '%' }"></span></div>
                                </li>
                            </ol>
                            <el-empty v-else :image-size="70" :description="$t('dash_no_sales_30_days')" />
                        </section>

                        <!-- Invoice stages -->
                        <section class="dash-card span-4">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('invoice_status_breakdown') }}</h3>
                                    <p>{{ $t('dash_invoices_total', { count: formatNumber(invoicesTotal) }) }}</p>
                                </div>
                            </header>
                            <div class="chart-wrap">
                                <div ref="invoiceStatusChartRef" class="chart-box chart-box-sm"></div>
                                <div v-if="!invoicesTotal" class="chart-empty">
                                    <el-icon><PieChart /></el-icon>
                                    <span>{{ $t('dash_no_invoices_yet') }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- Low stock -->
                        <section class="dash-card span-8">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('low_stock_alerts') }}</h3>
                                    <p>{{ $t('dash_low_stock_hint', { count: formatNumber(lowStockCount) }) }}</p>
                                </div>
                                <router-link to="/admin/stock" class="head-link">
                                    {{ $t('view_all') }}<el-icon><component :is="goIcon" /></el-icon>
                                </router-link>
                            </header>
                            <ul v-if="lowStockProducts.length" class="stock-list">
                                <li v-for="product in lowStockProducts" :key="product.id">
                                    <router-link :to="`/admin/products/${product.id}`" class="stock-item">
                                        <span class="stock-name">
                                            <strong>{{ product.name }}</strong>
                                            <small class="mono">{{ product.sku }}</small>
                                        </span>
                                        <span class="stock-meter">
                                            <span class="stock-bar" :class="product.level"><span :style="{ width: product.ratio + '%' }"></span></span>
                                            <small>
                                                {{ formatNumber(product.stock_quantity) }} / {{ formatNumber(product.min_stock) }}
                                                <template v-if="product.stock_quantity <= 0"> · {{ $t('dash_out_of_stock') }}</template>
                                            </small>
                                        </span>
                                    </router-link>
                                </li>
                            </ul>
                            <div v-else class="all-clear compact">
                                <el-icon><CircleCheck /></el-icon>
                                <strong>{{ $t('dash_stock_healthy') }}</strong>
                            </div>
                        </section>

                        <!-- Business snapshot -->
                        <section class="dash-card span-12">
                            <header class="dash-card-head">
                                <div>
                                    <h3>{{ $t('summary_of_reports') }}</h3>
                                </div>
                            </header>
                            <div class="snapshot-grid">
                                <router-link v-for="item in detailStats" :key="item.title" :to="item.route" class="snapshot-item">
                                    <span class="snapshot-icon" :style="{ color: item.color, background: item.color + '1a' }">
                                        <el-icon><component :is="item.icon" /></el-icon>
                                    </span>
                                    <span class="snapshot-text">
                                        <small>{{ item.title }}</small>
                                        <strong>{{ item.value }}</strong>
                                    </span>
                                </router-link>
                            </div>

                            <div class="status-columns">
                                <div v-for="group in statusGroups" :key="group.title" class="status-column">
                                    <h4>{{ group.title }}</h4>
                                    <div v-for="item in group.items" :key="item.label" class="status-row">
                                        <span class="status-dot" :style="{ background: item.color }"></span>
                                        <span class="status-row-label">
                                            {{ item.label }}
                                            <small>{{ item.description }}</small>
                                        </span>
                                        <strong>{{ formatNumber(item.value) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
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
                            <div class="action-row">
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
                                <div class="kv-list">
                                    <div class="kv-row">
                                        <span>{{ $t('rma_pending_approval') }}</span>
                                        <el-tag type="warning">{{ formatNumber(rmaStats.pending) }}</el-tag>
                                    </div>
                                    <div class="kv-row">
                                        <span>{{ $t('rma_approved') }}</span>
                                        <el-tag type="primary">{{ formatNumber(rmaStats.approved) }}</el-tag>
                                    </div>
                                    <div class="kv-row">
                                        <span>{{ $t('rma_rejected') }}</span>
                                        <el-tag type="danger">{{ formatNumber(rmaStats.rejected) }}</el-tag>
                                    </div>
                                    <div class="kv-row">
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
                            <div class="cta-block">
                                <el-icon :size="48" color="#409eff"><Refresh /></el-icon>
                                <p class="cta-text">{{ $t('rma_description') }}</p>
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
                                <div class="kv-list">
                                    <div class="kv-row">
                                        <span>{{ $t('successfully_executed') }}</span>
                                        <el-tag type="success">{{ formatNumber(workflowStats.executions_completed) }}</el-tag>
                                    </div>
                                    <div class="kv-row">
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
                                    <div class="card-head-row">
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
import {
    Box, ShoppingCart, User, TrendCharts, Wallet, Warning, Tickets, Tools,
    ChatLineRound, Cpu, Refresh, Money, Top, Bottom, ArrowLeft, ArrowRight,
    DocumentAdd, Checked, Clock, Coin
} from '@element-plus/icons-vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { dashboardApi } from '@/api/dashboard';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/Composables/useCurrency';
import { statusTagType, statusLabel } from '@/utils/sales';
import DashboardSkeleton from '@/components/admin/DashboardSkeleton.vue';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

// Chevrons point the way the reading direction goes.
const goIcon = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight));
const dateLocale = computed(() => (locale.value === 'en' ? 'en-GB' : 'ar-SY'));

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

/* ---- Greeting ---- */

// Ticks so "updated 3 minutes ago" and the greeting stay true on an open tab.
const now = ref(Date.now());

const greeting = computed(() => {
    const hour = new Date(now.value).getHours();
    const key = hour < 12 ? 'dash_good_morning' : hour < 18 ? 'dash_good_afternoon' : 'dash_good_evening';
    const firstName = (authStore.user?.name || '').trim().split(/\s+/)[0];
    return firstName ? t(`${key}_name`, { name: firstName }) : t(key);
});

const todayLabel = computed(() => new Intl.DateTimeFormat(dateLocale.value, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}).format(new Date(now.value)));

/* ---- Tabs, kept in the URL so a refresh or a shared link reopens the same one ---- */

const TABS = ['overview', 'wms', 'rma', 'workflows', 'audit', 'analytics'];
const activeTab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'overview');

watch(activeTab, (tab) => {
    const query = { ...route.query };
    if (tab === 'overview') delete query.tab; else query.tab = tab;
    router.replace({ query });
});

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
const rawCount = (value) => toNumber(value);
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

/** Month-to-date revenue against the same stretch of last month. */
const monthDelta = computed(() => {
    const current = toNumber(revenueBreakdown.value.month);
    const previous = toNumber(revenueBreakdown.value.previous_month_to_date);
    if (previous <= 0) return null;
    const change = ((current - previous) / previous) * 100;
    const rounded = Math.round(change);
    return {
        direction: change >= 0 ? 'up' : 'down',
        label: `${formatNumber(Math.abs(rounded))}%`,
    };
});

const pendingInvoicesCount = computed(() => toNumber(erp.value.pending_invoices ?? invoices.value.pending));

const kpis = computed(() => [
    {
        key: 'month_revenue',
        label: t('dash_revenue_this_month'),
        value: formatMoney(revenueBreakdown.value.month),
        sub: t('dash_today_value', { value: formatMoney(revenueBreakdown.value.today) }),
        delta: monthDelta.value,
        icon: TrendCharts,
        tone: 'teal',
        route: '/admin/sales/reports',
    },
    {
        key: 'invoices',
        label: t('invoices'),
        value: formatNumber(invoicesTotal.value),
        sub: t('dash_pending_count', { count: formatNumber(pendingInvoicesCount.value) }),
        icon: Tickets,
        tone: 'blue',
        route: '/admin/sales/invoices',
    },
    {
        key: 'customers',
        label: t('customers'),
        value: formatNumber(customersTotal.value),
        sub: t('dash_sales_orders_count', { count: formatNumber(overview.value.sales_orders?.total) }),
        icon: User,
        tone: 'violet',
        route: '/admin/sales/customers',
    },
    {
        key: 'low_stock',
        label: t('low_inventory'),
        value: formatNumber(lowStockCount.value),
        sub: t('dash_of_products', { count: formatNumber(productsTotal.value) }),
        icon: Warning,
        tone: lowStockCount.value > 0 ? 'amber' : 'green',
        route: '/admin/stock',
    },
]);

/**
 * Work waiting on someone, largest queue first. Only non-zero queues are
 * listed, so an empty list genuinely means there is nothing to chase.
 */
const attentionItems = computed(() => [
    { key: 'invoices', label: t('pending_invoices'), count: pendingInvoicesCount.value, icon: Tickets, tone: 'amber', route: '/admin/sales/invoices' },
    { key: 'orders', label: t('dash_pending_sales_orders'), count: rawCount(overview.value.sales_orders?.pending), icon: ShoppingCart, tone: 'blue', route: '/admin/sales/sales-orders' },
    { key: 'stock', label: t('low_inventory'), count: lowStockCount.value, icon: Box, tone: 'red', route: '/admin/stock' },
    { key: 'payments', label: t('dash_pending_payments'), count: rawCount(overview.value.payments?.pending), icon: Wallet, tone: 'amber', route: '/admin/sales/payments' },
    { key: 'rma', label: t('pending_rma'), count: rawCount(rmaStats.value.pending), icon: Refresh, tone: 'violet', route: '/admin/rma' },
    { key: 'production', label: t('dash_pending_production'), count: rawCount(overview.value.production?.pending), icon: Tools, tone: 'blue', route: '/admin/production' },
    { key: 'inquiries', label: t('dash_new_inquiries'), count: rawCount(overview.value.inquiries?.new), icon: ChatLineRound, tone: 'teal', route: '/admin/inquiries' },
    { key: 'workflows', label: t('failed_executions'), count: rawCount(workflowStats.value.executions_failed), icon: Cpu, tone: 'red', route: '/admin/workflows' },
].filter((item) => item.count > 0).sort((a, b) => b.count - a.count));

const attentionTotal = computed(() => attentionItems.value.reduce((sum, item) => sum + item.count, 0));

const detailStats = computed(() => [
    { title: t('monthly_sales'), value: formatMoney(erp.value.monthly_sales), icon: TrendCharts, color: '#67c23a', route: '/admin/reports/sales' },
    { title: t('expenses'), value: formatMoney(erp.value.total_expenses), icon: Money, color: '#f56c6c', route: '/admin/accounting' },
    { title: t('dash_payments_received'), value: formatMoney(overview.value.payments?.amounts?.completed), icon: Wallet, color: '#0d9488', route: '/admin/sales/payments' },
    { title: t('dash_purchase_receipts'), value: formatNumber(overview.value.purchase_receipts?.total), icon: Box, color: '#409eff', route: '/admin/purchases/receipts' },
    { title: t('quotes'), value: formatNumber(overview.value.quotes?.total), icon: TrendCharts, color: '#8c6dfd', route: '/admin/sales/quotes' },
    { title: t('sales_orders'), value: formatNumber(overview.value.sales_orders?.total), icon: ShoppingCart, color: '#67c23a', route: '/admin/sales/sales-orders' },
    { title: t('production_orders'), value: formatNumber(overview.value.production?.total), icon: Tools, color: '#e6a23c', route: '/admin/production' },
    { title: t('salaries'), value: formatNumber(overview.value.payrolls?.total), icon: Coin, color: '#409eff', route: '/admin/hr/payrolls' },
]);

const revenueMetrics = computed(() => [
    { label: t('today'), value: formatMoney(revenueBreakdown.value.today) },
    { label: t('this_week'), value: formatMoney(revenueBreakdown.value.week) },
    { label: t('this_month'), value: formatMoney(revenueBreakdown.value.month) },
]);

const statusGroups = computed(() => [
    {
        title: t('status_of_payments'),
        items: [
            { label: t('complete'), value: overview.value.payments?.completed, description: t('completed_payment_from_customers'), color: '#10b981' },
            { label: t('suspended'), value: overview.value.payments?.pending, description: t('payment_is_waiting_for_processing'), color: '#f59e0b' },
            { label: t('refundable'), value: overview.value.payments?.refunded, description: t('refunds_payments_to_customers'), color: '#ef4444' },
        ],
    },
    {
        title: t('production_status'),
        items: [
            { label: t('suspended'), value: overview.value.production?.pending, description: t('uninitiated_production_orders'), color: '#f59e0b' },
            { label: t('under_implementation'), value: overview.value.production?.in_progress, description: t('current_production_orders'), color: '#3b82f6' },
            { label: t('complete'), value: overview.value.production?.completed, description: t('orders_ready_for_delivery'), color: '#10b981' },
        ],
    },
]);

const formatDay = (value) => (value
    ? new Intl.DateTimeFormat(dateLocale.value, { day: 'numeric', month: 'short' }).format(new Date(value))
    : '—');

const recentSales = computed(() => (overview.value.recent_invoices || []).map((invoice) => ({
    id: invoice.id,
    number: invoice.invoice_number || `#${invoice.id}`,
    customer: invoice.customer_name || t('client'),
    date: formatDay(invoice.created_at),
    amount: formatMoney(invoice.total),
    // Kept as the raw identifier; the table translates it for display, so the
    // tag colour is decided by what the model returned rather than by matching
    // an English word against an already-translated label.
    status: invoice.status,
})));

// Opens the invoice list already filtered to this invoice.
const openInvoice = (row) => {
    router.push({ path: '/admin/sales/invoices', query: { invoice: row.number } });
};

const bestSellers = computed(() => {
    const rows = overview.value.best_sellers || [];
    const top = Math.max(...rows.map((row) => toNumber(row.units_sold)), 0);
    return rows.map((row) => ({
        id: row.id,
        name: localizedName(row),
        units: toNumber(row.units_sold),
        revenue: formatMoney(row.revenue),
        image: row.image || '',
        share: top > 0 ? Math.max(4, Math.round((toNumber(row.units_sold) / top) * 100)) : 0,
    }));
});

const lowStockProducts = computed(() => (overview.value.low_stock_products || []).map((product) => {
    const stock = toNumber(product.stock_quantity);
    const min = toNumber(product.min_stock);
    const ratio = min > 0 ? Math.max(0, Math.min(100, Math.round((stock / min) * 100))) : 0;
    return {
        id: product.id,
        name: localizedName(product),
        sku: product.sku || '—',
        stock_quantity: stock,
        min_stock: min,
        ratio,
        level: stock <= 0 ? 'empty' : ratio < 50 ? 'critical' : 'low',
    };
}));

// Every invoice stage the model has; there is no "paid" stage (payment is
// tracked by paid/due amounts), which is why the old paid slice was always 0.
const INVOICE_STAGE_COLORS = {
    pending: '#f59e0b',
    confirmed: '#06b6d4',
    processing: '#3b82f6',
    shipped: '#8b5cf6',
    delivered: '#10b981',
    cancelled: '#ef4444',
};

const invoiceStages = computed(() => {
    const breakdown = invoices.value.status_breakdown || {};
    return Object.entries(INVOICE_STAGE_COLORS)
        .map(([status, color]) => ({ name: statusLabel(status), value: toNumber(breakdown[status]), color }))
        .filter((stage) => stage.value > 0);
});

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

/** How long ago the figures on screen were fetched, so "updated" means something. */
const updatedAgo = computed(() => {
    if (!lastUpdatedAt.value) return '';
    const seconds = Math.round((lastUpdatedAt.value.getTime() - now.value) / 1000);
    if (Math.abs(seconds) < 60) return t('dash_just_now');
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });
    const minutes = Math.round(seconds / 60);
    if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute');
    return rtf.format(Math.round(minutes / 60), 'hour');
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

    const compact = new Intl.NumberFormat(dateLocale.value, { notation: 'compact', maximumFractionDigits: 1 });
    const axisLabel = (value) => {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : formatDay(value);
    };

    renderChart(revenueTrendChartRef, 'revenueTrend', {
        tooltip: {
            trigger: 'axis',
            formatter: (points) => {
                const title = axisLabel(points[0]?.axisValue);
                const lines = points.map((point) => {
                    const value = point.seriesIndex === 0 ? formatMoney(point.value) : formatNumber(point.value);
                    return `${point.marker} ${point.seriesName}: <b>${value}</b>`;
                });
                return [title, ...lines].join('<br/>');
            },
        },
        legend: { data: [t('revenue'), t('dash_orders_count')], top: 0, icon: 'roundRect', itemWidth: 12, itemHeight: 8 },
        grid: { left: 8, right: 8, top: 36, bottom: 4, containLabel: true },
        xAxis: {
            type: 'category',
            data: dates,
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisTick: { show: false },
            axisLabel: { color: '#94a3b8', formatter: axisLabel, hideOverlap: true },
        },
        yAxis: [
            { type: 'value', splitLine: { lineStyle: { color: '#f1f5f9' } }, axisLabel: { color: '#94a3b8', formatter: (v) => compact.format(v) } },
            { type: 'value', splitLine: { show: false }, axisLabel: { show: false }, minInterval: 1 }
        ],
        series: [
            {
                name: t('revenue'),
                type: 'line',
                smooth: true,
                showSymbol: false,
                yAxisIndex: 0,
                data: revenue,
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(13,148,136,0.28)' },
                        { offset: 1, color: 'rgba(13,148,136,0)' },
                    ]),
                },
                lineStyle: { color: '#0d9488', width: 2.5 },
                itemStyle: { color: '#0d9488' }
            },
            {
                name: t('dash_orders_count'),
                type: 'bar',
                yAxisIndex: 1,
                data: orders,
                barMaxWidth: 14,
                itemStyle: { color: 'rgba(99,102,241,0.35)', borderRadius: [4, 4, 0, 0] }
            }
        ]
    });
};

const renderInvoiceStatusChart = () => {
    renderChart(invoiceStatusChartRef, 'invoiceStatus', donutOption(invoiceStages.value));
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

const trendDays = ref(30);
const trendLoading = ref(false);
const trendRangeOptions = computed(() => [7, 30, 90].map((days) => ({ label: t('dash_days_short', { days }), value: days })));
const hasTrendData = computed(() => salesTrend.value.some((row) => toNumber(row.revenue) > 0 || toNumber(row.orders) > 0));

const loadSalesTrend = async () => {
    try {
        const response = await dashboardApi.getSalesTrend({ days: trendDays.value, group_by: 'day' });
        const rows = response.data?.data ?? response.data ?? [];
        salesTrend.value = Array.isArray(rows) ? rows : [];
    } catch (err) {
        salesTrend.value = [];
    }
};

// Changing the range reloads just the chart, not the whole screen.
watch(trendDays, async () => {
    trendLoading.value = true;
    await loadSalesTrend();
    trendLoading.value = false;
    await nextTick();
    renderRevenueTrend();
});

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

/**
 * Coming back to a tab left open for a while refreshes quietly, so figures
 * read after lunch aren't the ones fetched in the morning.
 */
const STALE_AFTER_MS = 5 * 60 * 1000;
let clockTimer = null;

const onVisibilityChange = () => {
    if (document.visibilityState !== 'visible') return;
    now.value = Date.now();
    if (!loading.value && !refreshing.value && lastUpdatedAt.value && now.value - lastUpdatedAt.value.getTime() > STALE_AFTER_MS) {
        refresh();
    }
};

onMounted(async () => {
    clockTimer = setInterval(() => { now.value = Date.now(); }, 30000);
    document.addEventListener('visibilitychange', onVisibilityChange);

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
    clearInterval(clockTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);

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

.mt-4 {
    margin-top: 1.5rem;
}

.mt-2 {
    margin-top: 0.5rem;
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

.dashboard-alert {
    margin-bottom: 1.5rem;
}

@media (max-width: 992px) {

    .stat-icon {
        width: 48px;
        height: 48px;
    }

    .stat-info h3,
    .stat-info h4 {
        font-size: 1.3rem;
    }

    .section-header h2 {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {

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

    .chart-box {
        height: 240px;
    }
}

@media (max-width: 576px) {

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

/* ================================================================
 * Overview redesign
 * ================================================================ */
.dashboard {
    --dz-teal: #0d9488;
    --dz-border: var(--border-color, #e7ebf0);
    --dz-text: var(--text-dark, #1e293b);
    --dz-muted: var(--text-muted, #64748b);
    --dz-surface: #fff;
    --dz-radius: 16px;
}

/* ---- Hero ---- */
.dash-hero {
    position: relative;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
    padding: 1.6rem 1.75rem;
    margin-bottom: 1.25rem;
    border-radius: 20px;
    color: #e2e8f0;
    overflow: hidden;
    background:
        radial-gradient(50% 140% at 90% 0%, rgba(103, 232, 249, 0.28), transparent 60%),
        radial-gradient(45% 120% at 0% 100%, rgba(129, 140, 248, 0.25), transparent 60%),
        linear-gradient(120deg, #0a0f1e 0%, #0f2a3a 55%, #0d4f4a 100%);
    box-shadow: 0 18px 40px -24px rgba(13, 79, 74, 0.8);
}

.dash-hero-text {
    min-width: 0;
}

.dash-date {
    display: block;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    color: rgba(165, 243, 252, 0.8);
    margin-bottom: 0.35rem;
}

.dash-hero h1 {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
}

.dash-hero p {
    margin: 0.3rem 0 0;
    font-size: 0.9rem;
    color: rgba(203, 213, 225, 0.8);
}

.dash-hero-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.9rem;
}

.dash-hero .currency-tag {
    --el-tag-bg-color: rgba(255, 255, 255, 0.08);
    --el-tag-border-color: rgba(255, 255, 255, 0.18);
    --el-tag-text-color: #e2e8f0;
}

.updated-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 24px;
    padding: 0 0.7rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
    font-size: 0.75rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.updated-chip:hover:not(:disabled) {
    background: rgba(45, 212, 191, 0.18);
    border-color: rgba(45, 212, 191, 0.5);
}

.updated-chip:disabled {
    cursor: progress;
}

.spinning {
    animation: dz-spin 0.9s linear infinite;
}

@keyframes dz-spin {
    to { transform: rotate(360deg); }
}

.dash-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.dash-hero-actions .el-button {
    margin: 0;
}

.dash-hero-actions .el-button:not(.el-button--primary):not(.el-button--success) {
    --el-button-bg-color: rgba(255, 255, 255, 0.08);
    --el-button-border-color: rgba(255, 255, 255, 0.2);
    --el-button-text-color: #e2e8f0;
    --el-button-hover-bg-color: rgba(255, 255, 255, 0.16);
    --el-button-hover-border-color: rgba(255, 255, 255, 0.3);
    --el-button-hover-text-color: #fff;
}

.dashboard-alert :deep(.el-alert__description) {
    margin-top: 0.4rem;
}

/* ---- KPI cards ---- */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.kpi-card {
    --tone: #0d9488;
    --tone-soft: rgba(13, 148, 136, 0.1);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 1.1rem 1.2rem;
    border-radius: var(--dz-radius);
    background: var(--dz-surface);
    border: 1px solid var(--dz-border);
    text-decoration: none;
    color: inherit;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.kpi-card::before {
    content: '';
    position: absolute;
    inset-block-start: 0;
    inset-inline: 0;
    height: 3px;
    background: var(--tone);
    opacity: 0.85;
}

.kpi-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--tone) 35%, var(--dz-border));
    box-shadow: 0 14px 30px -18px color-mix(in srgb, var(--tone) 70%, transparent);
}

.kpi-card:focus-visible {
    outline: 2px solid var(--tone);
    outline-offset: 2px;
}

.tone-teal { --tone: #0d9488; --tone-soft: rgba(13, 148, 136, 0.1); }
.tone-blue { --tone: #3b82f6; --tone-soft: rgba(59, 130, 246, 0.1); }
.tone-violet { --tone: #8b5cf6; --tone-soft: rgba(139, 92, 246, 0.1); }
.tone-amber { --tone: #f59e0b; --tone-soft: rgba(245, 158, 11, 0.12); }
.tone-red { --tone: #ef4444; --tone-soft: rgba(239, 68, 68, 0.1); }
.tone-green { --tone: #10b981; --tone-soft: rgba(16, 185, 129, 0.1); }

.kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.kpi-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--dz-muted);
}

.kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: var(--tone);
    background: var(--tone-soft);
}

.kpi-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--dz-text);
    line-height: 1.15;
    word-break: break-word;
    font-variant-numeric: tabular-nums;
}

.kpi-foot {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: var(--dz-muted);
}

.kpi-delta {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-weight: 700;
}

.kpi-delta.up {
    color: #047857;
    background: rgba(16, 185, 129, 0.12);
}

.kpi-delta.down {
    color: #b91c1c;
    background: rgba(239, 68, 68, 0.1);
}

/* ---- Card grid ---- */
.dash-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 1rem;
}

.span-4 { grid-column: span 4; }
.span-8 { grid-column: span 8; }
.span-12 { grid-column: span 12; }

.dash-card {
    min-width: 0;
    display: flex;
    flex-direction: column;
    padding: 1.15rem 1.25rem;
    border-radius: var(--dz-radius);
    background: var(--dz-surface);
    border: 1px solid var(--dz-border);
    box-shadow: 0 6px 18px -16px rgba(15, 23, 42, 0.35);
}

.dash-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}

.dash-card-head h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--dz-text);
}

.dash-card-head p {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: var(--dz-muted);
}

.head-link {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--dz-teal);
    text-decoration: none;
}

.head-link:hover {
    text-decoration: underline;
}

.count-pill {
    flex-shrink: 0;
    min-width: 28px;
    height: 24px;
    padding: 0 0.55rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
}

.mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82em;
}

/* ---- Revenue ---- */
.revenue-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.revenue-strip-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.6rem 0.75rem;
    border-radius: 12px;
    background: var(--bg-light, #f8fafc);
    min-width: 0;
}

.revenue-strip-item span {
    font-size: 0.72rem;
    color: var(--dz-muted);
}

.revenue-strip-item strong {
    font-size: 0.95rem;
    color: var(--dz-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-variant-numeric: tabular-nums;
}

.chart-wrap {
    position: relative;
    flex: 1;
}

.chart-empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: var(--dz-muted);
    background: color-mix(in srgb, var(--dz-surface) 80%, transparent);
    pointer-events: none;
}

.chart-empty .el-icon {
    font-size: 1.8rem;
    color: #cbd5e1;
}

/* ---- Needs attention ---- */
.attention-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.attention-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 0.7rem;
    border-radius: 12px;
    border: 1px solid transparent;
    text-decoration: none;
    color: var(--dz-text);
    transition: background 0.18s ease, border-color 0.18s ease;
}

.attention-item:hover,
.attention-item:focus-visible {
    background: var(--tone-soft);
    border-color: color-mix(in srgb, var(--tone) 25%, transparent);
    outline: none;
}

.attention-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--tone);
    background: var(--tone-soft);
}

.attention-label {
    flex: 1;
    min-width: 0;
    font-size: 0.87rem;
}

.attention-count {
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--tone);
    font-variant-numeric: tabular-nums;
}

.attention-go {
    font-size: 0.8rem;
    color: #cbd5e1;
    transition: transform 0.18s ease, color 0.18s ease;
}

.attention-item:hover .attention-go {
    color: var(--tone);
    transform: translateX(-2px);
}

[dir="ltr"] .attention-item:hover .attention-go {
    transform: translateX(2px);
}

.all-clear {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 1.5rem 0.5rem;
    text-align: center;
    color: var(--dz-muted);
    font-size: 0.82rem;
}

.all-clear .el-icon {
    font-size: 2rem;
    color: #10b981;
}

.all-clear strong {
    color: var(--dz-text);
    font-size: 0.95rem;
}

.all-clear.compact {
    flex-direction: row;
    padding: 1rem;
}

.all-clear.compact .el-icon {
    font-size: 1.3rem;
}

/* ---- Tables ---- */
.dash-table :deep(.clickable-row) {
    cursor: pointer;
}

.dash-table :deep(th.el-table__cell) {
    background: var(--bg-light, #f8fafc);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--dz-muted);
}

/* ---- Best sellers ---- */
.rank-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.rank-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
    color: inherit;
    border-radius: 10px;
}

.rank-item:hover strong {
    color: var(--dz-teal);
}

.rank-no {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--dz-muted);
    background: #f1f5f9;
}

.rank-list li:first-child .rank-no {
    color: #92400e;
    background: rgba(245, 158, 11, 0.18);
}

.rank-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.rank-info strong {
    font-size: 0.85rem;
    color: var(--dz-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.18s ease;
}

.rank-info small {
    font-size: 0.74rem;
    color: var(--dz-muted);
}

.rank-value {
    flex-shrink: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--dz-text);
    font-variant-numeric: tabular-nums;
}

.rank-bar {
    height: 4px;
    margin-top: 0.4rem;
    margin-inline-start: 30px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.rank-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #2dd4bf, #0d9488);
}

/* ---- Low stock ---- */
.stock-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.stock-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: 12px;
    border: 1px solid var(--dz-border);
    text-decoration: none;
    color: inherit;
    transition: border-color 0.18s ease, background 0.18s ease;
}

.stock-item:hover {
    border-color: #fca5a5;
    background: rgba(239, 68, 68, 0.03);
}

.stock-name {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.stock-name strong {
    font-size: 0.84rem;
    color: var(--dz-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stock-name small {
    color: var(--dz-muted);
}

.stock-meter {
    flex-shrink: 0;
    width: 110px;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: end;
}

.stock-meter small {
    font-size: 0.72rem;
    color: var(--dz-muted);
    font-variant-numeric: tabular-nums;
}

.stock-bar {
    height: 6px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.stock-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #f59e0b;
}

.stock-bar.critical span { background: #ef4444; }
.stock-bar.empty span { background: #ef4444; }

/* ---- Snapshot ---- */
.snapshot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 0.6rem;
}

.snapshot-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.75rem 0.85rem;
    border-radius: 12px;
    border: 1px solid var(--dz-border);
    text-decoration: none;
    color: inherit;
    transition: border-color 0.18s ease, transform 0.18s ease;
}

.snapshot-item:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.snapshot-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
}

.snapshot-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.snapshot-text small {
    font-size: 0.74rem;
    color: var(--dz-muted);
}

.snapshot-text strong {
    font-size: 1rem;
    color: var(--dz-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-variant-numeric: tabular-nums;
}

.status-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem 2rem;
    margin-top: 1.1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--dz-border);
}

.status-column h4 {
    margin: 0 0 0.5rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--dz-text);
}

.status-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.4rem 0;
}

.status-row + .status-row {
    border-top: 1px solid #f1f5f9;
}

.status-dot {
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    border-radius: 999px;
}

.status-row-label {
    flex: 1;
    min-width: 0;
    font-size: 0.85rem;
    color: var(--dz-text);
}

.status-row-label small {
    display: block;
    font-size: 0.72rem;
    color: var(--dz-muted);
}

/* ---- Other tabs ---- */
.kv-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    padding: 0.6rem 0;
}

.kv-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}

.action-row .el-button {
    margin: 0;
}

.cta-block {
    padding: 1.25rem 0;
    text-align: center;
}

.cta-text {
    margin: 0.5rem 0 1.25rem;
    color: var(--dz-muted);
}

.card-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

/* ---- Responsive ---- */
@media (max-width: 1200px) {
    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 992px) {
    .span-4,
    .span-8 {
        grid-column: span 12;
    }

    .stock-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .dash-hero {
        padding: 1.2rem;
        border-radius: 16px;
    }

    .dash-hero h1 {
        font-size: 1.3rem;
    }

    .dash-hero-actions {
        width: 100%;
    }

    .dash-hero-actions .el-button {
        flex: 1 1 auto;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
    }

    .kpi-card {
        padding: 0.9rem;
    }

    .kpi-value {
        font-size: 1.2rem;
    }

    .kpi-icon {
        display: none;
    }

    .revenue-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .status-columns {
        grid-template-columns: 1fr;
    }

    .dash-card {
        padding: 1rem;
    }

    .dash-card-head {
        flex-wrap: wrap;
    }
}
</style>
