<template>
    <div class="sales-page sales-orders">
        <AdminPageHeader
            icon="fas fa-shopping-cart text-primary"
            :title="$t('sales_orders')"
            :subtitle="$t('so_list_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="loadOrders()" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="openCreateDrawer">{{ $t('new_sales_order') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- Where the orders stand, over the whole search rather than the page.
             Each card is also the filter for what it counts. -->
        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="so-stat is-clickable" :class="{ 'is-active': filters.stage === 'pending' }" @click="setStage('pending')">
                <div class="so-stat-inner">
                    <div class="so-stat-icon orange"><i class="fas fa-clock"></i></div>
                    <div class="so-stat-details">
                        <h3>{{ counts.pending }}</h3>
                        <p>{{ $t('awaiting_confirmation') }}</p>
                        <span class="so-stat-sub">{{ $t('so_confirm_to_reserve') }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="so-stat is-clickable" :class="{ 'is-active': filters.stage === 'open' }" @click="setStage('open')">
                <div class="so-stat-inner">
                    <div class="so-stat-icon blue"><i class="fas fa-truck-fast"></i></div>
                    <div class="so-stat-details">
                        <h3>{{ formatCurrency(totals.open_value) }}</h3>
                        <p>{{ $t('so_in_progress_n', { count: counts.confirmed + counts.processing + counts.shipped }) }}</p>
                        <span class="so-stat-sub">{{ $t('so_open_split', { confirmed: counts.confirmed, processing: counts.processing, shipped: counts.shipped }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="so-stat is-clickable" :class="{ 'is-active': filters.stage === 'delivered' }" @click="setStage('delivered')">
                <div class="so-stat-inner">
                    <div class="so-stat-icon green"><i class="fas fa-box-open"></i></div>
                    <div class="so-stat-details">
                        <h3>{{ formatCurrency(totals.delivered_month_value) }}</h3>
                        <p>{{ $t('so_delivered_this_month') }}</p>
                        <span class="so-stat-sub">{{ $t('so_orders_n', { count: totals.delivered_month_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="so-stat is-clickable" :class="{ 'is-active': filters.payment === 'due' }" @click="togglePayment('due')">
                <div class="so-stat-inner">
                    <div class="so-stat-icon red"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="so-stat-details">
                        <h3>{{ formatCurrency(totals.to_collect) }}</h3>
                        <p>{{ $t('so_to_collect') }}</p>
                        <span class="so-stat-sub">{{ $t('so_invoices_n', { count: totals.to_collect_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- The one thing on this screen that is an instruction, not a figure -->
        <button
            v-if="counts.attention && !filters.attention"
            type="button"
            class="so-attention"
            @click="toggleAttention"
        >
            <i class="fas fa-triangle-exclamation"></i>
            <span>
                {{ $t('so_attention_n', { count: counts.attention }) }}
                <template v-if="counts.overdue"> · {{ $t('so_overdue_n', { count: counts.overdue }) }}</template>
            </span>
            <span class="so-attention-cta">{{ $t('so_show') }}</span>
        </button>

        <section class="so-panel">
            <!-- Stages, with counts across the whole search -->
            <div class="so-stages" role="tablist">
                <button
                    v-for="tab in stageTabs"
                    :key="tab.name"
                    type="button"
                    role="tab"
                    class="so-stage"
                    :class="{ 'is-on': filters.stage === tab.name }"
                    :aria-selected="filters.stage === tab.name"
                    @click="setStage(tab.name)"
                >
                    <i class="fas" :class="tab.icon"></i>
                    {{ tab.label }}
                    <span class="so-stage-count">{{ tab.count }}</span>
                </button>
            </div>

            <div class="so-filters">
                <el-input
                    v-model="filters.search"
                    class="so-filter-search"
                    :placeholder="$t('so_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select
                    v-if="store.options.warehouses?.length > 1"
                    v-model="filters.warehouse_id"
                    class="so-filter-select"
                    :placeholder="$t('so_all_warehouses')"
                    clearable
                    filterable
                    @change="applyFilters"
                >
                    <el-option v-for="w in store.options.warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-select v-model="filters.fulfillment_type" class="so-filter-select" :placeholder="$t('so_any_fulfilment')" clearable @change="applyFilters">
                    <el-option value="ship" :label="$t('shipping')" />
                    <el-option value="delivery" :label="$t('courier_delivery')" />
                    <el-option value="pickup" :label="$t('branch_pickup')" />
                </el-select>
                <el-select
                    v-if="store.options.employees?.length > 1"
                    v-model="filters.employee_id"
                    class="so-filter-select"
                    :placeholder="$t('so_all_reps')"
                    clearable
                    filterable
                    @change="applyFilters"
                >
                    <el-option v-for="e in store.options.employees" :key="e.id" :label="e.name" :value="e.id" />
                </el-select>
                <el-select v-model="filters.payment" class="so-filter-select" :placeholder="$t('so_any_payment')" clearable @change="applyFilters">
                    <el-option value="due" :label="$t('so_payment_due')" />
                    <el-option value="paid" :label="$t('so_payment_paid')" />
                </el-select>
                <el-date-picker
                    v-model="filters.range"
                    type="daterange"
                    class="so-filter-dates"
                    value-format="YYYY-MM-DD"
                    format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('pret_from')"
                    :end-placeholder="$t('pret_to')"
                    :shortcuts="dateShortcuts"
                    @change="applyFilters"
                />
                <el-check-tag :checked="filters.attention" class="so-attention-tag" @change="toggleAttention">
                    <i class="fas fa-triangle-exclamation"></i> {{ $t('so_needs_attention') }}
                </el-check-tag>
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="store.error && !store.orders.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="loadOrders()">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.orders"
                row-key="id"
                style="width: 100%"
                class="so-table"
                :row-class-name="rowClassName"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
                @row-click="(row, column) => column?.property !== 'actions' && openDetailDrawer(row.id)"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && (activeFilterCount || filters.stage !== 'all')" :description="$t('there_are_no_requests_matching')" :image-size="90">
                        <el-button @click="resetFilters(true)">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('so_no_orders_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="openCreateDrawer">{{ $t('create_new_order') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column prop="order_number" :label="$t('order_number')" min-width="140" sortable="custom">
                    <template #default="{ row }">
                        <div class="so-stack">
                            <span class="so-mono" dir="ltr">
                                {{ row.order_number }}
                                <el-tooltip v-if="row.follow_up?.needs_attention" :content="row.follow_up.attention_reasons.join(' — ')" placement="top">
                                    <i class="fas fa-triangle-exclamation so-flag"></i>
                                </el-tooltip>
                            </span>
                            <span class="so-sub">
                                {{ formatDate(row.order_date || row.created_at) }}
                                <template v-if="row.quote"> · <span dir="ltr">{{ row.quote.quote_number }}</span></template>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="so-stack">
                            <span class="so-strong">{{ row.customer?.name || '—' }}</span>
                            <span v-if="row.customer?.phone" class="so-sub" dir="ltr">{{ row.customer.phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="total" :label="$t('total')" min-width="130" align="right" sortable="custom">
                    <template #default="{ row }">
                        <div class="so-stack so-end">
                            <strong class="so-amount">{{ formatCurrency(row.total) }}</strong>
                            <span class="so-sub">{{ $t('so_items_n', { count: row.items_count ?? 0 }) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="150">
                    <template #default="{ row }">
                        <div class="so-stack">
                            <span class="so-pill" :class="`s-${normalizeStatus(row.status)}`">
                                <i class="fas" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </span>
                            <span class="so-sub" :class="stageAgeClass(row.follow_up)">
                                <template v-if="row.follow_up?.is_overdue">{{ $t('overdue_by_days', { days: row.follow_up.days_overdue }) }}</template>
                                <template v-else-if="row.follow_up?.is_open">{{ $t('so_in_stage', { age: stageAgeText(row.follow_up) }) }}</template>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('so_payment')" min-width="140">
                    <template #default="{ row }">
                        <div v-if="row.payment" class="so-stack">
                            <span class="so-pay" :class="`p-${row.payment.state}`">{{ $t(`so_pay_${row.payment.state}`) }}</span>
                            <span v-if="row.payment.due > 0.009" class="so-sub">{{ $t('so_due_amount', { amount: formatCurrency(row.payment.due) }) }}</span>
                        </div>
                        <span v-else class="so-sub">{{ normalizeStatus(row.status) === 'cancelled' ? '—' : $t('so_not_invoiced') }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('routing')" min-width="160">
                    <template #default="{ row }">
                        <div class="so-stack">
                            <span><i class="fas fa-warehouse so-muted-icon"></i> {{ row.fulfillment_warehouse?.name || $t('so_not_routed') }}</span>
                            <span class="so-sub">
                                {{ fulfillmentLabel(row.fulfillment_type) }}
                                <template v-if="row.expected_delivery"> · {{ $t('so_due_on', { date: formatDate(row.expected_delivery) }) }}</template>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="actions" :label="$t('actions')" min-width="190" align="center">
                    <template #default="{ row }">
                        <div class="so-row-actions" @click.stop>
                            <el-button
                                v-if="ROW_NEXT[normalizeStatus(row.status)]"
                                size="small"
                                :type="STAGE_ACTIONS[ROW_NEXT[normalizeStatus(row.status)]].type"
                                plain
                                :loading="advancingId === row.id"
                                @click="advanceOrder(row)"
                            >
                                <i class="fas" :class="STAGE_ACTIONS[ROW_NEXT[normalizeStatus(row.status)]].icon"></i>&nbsp;{{ STAGE_ACTIONS[ROW_NEXT[normalizeStatus(row.status)]].short }}
                            </el-button>
                            <el-dropdown trigger="click" @command="(cmd) => runRowCommand(row, cmd)">
                                <el-button size="small" text circle :aria-label="$t('so_more_actions')">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="view"><i class="fas fa-eye"></i> {{ $t('view_details') }}</el-dropdown-item>
                                        <el-dropdown-item command="execution"><i class="fas fa-diagram-project"></i> {{ $t('fulfilment_and_routing') }}</el-dropdown-item>
                                        <el-dropdown-item command="documents"><i class="fas fa-file-invoice-dollar"></i> {{ $t('documents_and_entries') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="normalizeStatus(row.status) === 'pending'" command="edit"><i class="fas fa-edit"></i> {{ $t('edit') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="mayPurchase && normalizeStatus(row.status) !== 'cancelled'" command="purchase">
                                            <i class="fas fa-cart-plus"></i> {{ $t('so_create_purchase_request') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="['pending', 'cancelled'].includes(normalizeStatus(row.status))"
                                            command="delete"
                                            divided
                                            class="so-danger-item"
                                        >
                                            <i class="fas fa-trash"></i> {{ $t('delete') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="store.pagination.total > 0" class="so-pagination">
                <span class="so-sub">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: store.pagination.total }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="store.pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </section>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            :size="isNarrow ? '100%' : '64%'"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <template #header>
                <div class="drawer-title">
                    <i class="fas fa-file-lines"></i>
                    <span>{{ $t('sales_order_details') }}</span>
                    <strong v-if="selectedOrder">{{ selectedOrder.order_number }}</strong>
                    <!-- Same as the list row's cart action, from inside the order. -->
                    <el-button
                        v-if="selectedOrder && mayPurchase && normalizeStatus(selectedOrder.status) !== 'cancelled'"
                        size="small"
                        type="success"
                        plain
                        class="drawer-purchase-btn"
                        @click="createPurchaseRequest(selectedOrder)"
                    >
                        <i class="fas fa-cart-plus"></i>&nbsp;{{ $t('so_create_purchase_request') }}
                    </el-button>
                </div>
            </template>

            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 320px;"></div>
            <div v-else-if="selectedOrder" class="drawer-detail-content">
                <!-- Masthead: the four facts read first, before any detail -->
                <div class="order-masthead">
                    <div class="masthead-cell">
                        <span class="lbl">{{ $t('status') }}</span>
                        <el-tag :type="statusTagType(selectedOrder.status)" effect="dark" class="status-tag">
                            <i class="fas" :class="statusIconClass(selectedOrder.status)"></i>
                            {{ getArabicStatus(selectedOrder.status) }}
                        </el-tag>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">{{ $t('customer') }}</span>
                        <strong>{{ selectedOrder.customer?.name || '—' }}</strong>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">{{ $t('grand_total') }}</span>
                        <strong class="amount">{{ formatCurrency(selectedOrder.total) }}</strong>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">{{ invoice ? $t('remaining_on_customer_label') : $t('invoice') }}</span>
                        <strong v-if="invoice" :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">
                            {{ invoiceDueAmount > 0.01 ? formatCurrency(invoiceDueAmount) : $t('fully_paid') }}
                        </strong>
                        <span v-else class="muted">{{ $t('not_created_yet') }}</span>
                    </div>
                </div>

                <!-- What is inconsistent about this order, stated plainly -->
                <div v-if="diagnostics.length" class="diagnostics-panel">
                    <div
                        v-for="issue in diagnostics"
                        :key="issue.code"
                        class="diagnostic"
                        :class="`level-${issue.level}`"
                    >
                        <i class="fas diagnostic-icon" :class="diagnosticIcon(issue.level)"></i>
                        <div class="diagnostic-body">
                            <strong>{{ issue.title }}</strong>
                            <p>{{ issue.detail }}</p>
                            <p v-if="issue.action" class="diagnostic-action">
                                <i class="fas fa-arrow-turn-down"></i> {{ issue.action }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="diagnostics-clear">
                    <i class="fas fa-shield-check"></i>
                    {{ $t('records_consistent_notice') }}
                </div>

                <el-tabs v-model="detailTab" class="detail-tabs">
                    <!-- ---------------- Overview ---------------- -->
                    <el-tab-pane name="overview">
                        <template #label><i class="fas fa-list-ul"></i> {{ $t('items_and_amounts') }}</template>

                        <el-table :data="selectedOrder.items || []" stripe class="items-table" style="width: 100%">
                            <el-table-column :label="$t('item')" min-width="200">
                                <template #default="{ row }">
                                    <strong>{{ row.product?.name_ar || row.product?.name_en || row.product?.name || row.description || '—' }}</strong>
                                    <div v-if="row.product_variant_id" class="row-variant">
                                        <VariantChip :label="variantLabelOf(row.variant) || row.description" />
                                    </div>
                                    <p class="row-sub">{{ row.variant?.sku || row.product?.sku || '—' }}</p>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('quantity')" width="80" align="center">
                                <template #default="{ row }">{{ row.quantity }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('unit_price')" width="120" align="center">
                                <template #default="{ row }">{{ formatCurrency(row.unit_price) }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('discount')" width="100" align="center">
                                <template #default="{ row }">
                                    <span :class="{ muted: !toNum(row.discount) }">{{ formatCurrency(row.discount) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('tax')" width="100" align="center">
                                <template #default="{ row }">
                                    <span :class="{ muted: !toNum(row.tax) }">{{ formatCurrency(row.tax) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('grand_total')" width="130" align="center">
                                <template #default="{ row }"><strong>{{ formatCurrency(lineTotal(row)) }}</strong></template>
                            </el-table-column>
                        </el-table>

                        <!-- Amounts, in the order they build up to the total -->
                        <div class="amounts-block">
                            <div class="amount-row">
                                <span>{{ $t('items_subtotal') }}</span><span>{{ formatCurrency(selectedOrder.subtotal) }}</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.discount)">
                                <span>{{ $t('less_discount') }}</span><span class="text-danger">({{ formatCurrency(selectedOrder.discount) }})</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.tax)">
                                <span>{{ $t('plus_tax') }}</span><span>{{ formatCurrency(selectedOrder.tax) }}</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.shipping_cost)">
                                <span>{{ $t('plus_shipping_cost') }}</span><span>{{ formatCurrency(selectedOrder.shipping_cost) }}</span>
                            </div>
                            <div class="amount-row grand">
                                <span>{{ $t('grand_total_amount') }}</span><span>{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                            <template v-if="invoice">
                                <div class="amount-row paid">
                                    <span>{{ $t('collected') }}</span><span>{{ formatCurrency(invoice.paid_amount) }}</span>
                                </div>
                                <div class="amount-row due" :class="invoiceDueAmount > 0.01 ? 'unpaid' : 'settled'">
                                    <span>{{ $t('due_amount') }}</span><span>{{ formatCurrency(invoiceDueAmount) }}</span>
                                </div>
                            </template>
                        </div>

                        <el-row :gutter="16" class="mt-4">
                            <el-col :xs="24" :md="12">
                                <el-card shadow="never" class="info-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-user-circle"></i> {{ $t('customer') }}</span></template>
                                    <div class="info-list">
                                        <div class="info-item"><span class="lbl">{{ $t('name') }}</span><strong>{{ selectedOrder.customer?.name || '—' }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.company"><span class="lbl">{{ $t('company') }}</span><strong>{{ selectedOrder.customer.company }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.phone"><span class="lbl">{{ $t('phone') }}</span><strong dir="ltr">{{ selectedOrder.customer.phone }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.email"><span class="lbl">{{ $t('mail') }}</span><strong dir="ltr">{{ selectedOrder.customer.email }}</strong></div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-card shadow="never" class="info-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-truck"></i> {{ $t('delivery') }}</span></template>
                                    <div class="info-list">
                                        <div class="info-item"><span class="lbl">{{ $t('address') }}</span><strong>{{ shippingAddressText || '—' }}</strong></div>
                                        <div class="info-item"><span class="lbl">{{ $t('order_date') }}</span><strong>{{ formatDate(selectedOrder.order_date) }}</strong></div>
                                        <div class="info-item"><span class="lbl">{{ $t('expected_delivery') }}</span><strong>{{ formatDate(selectedOrder.expected_delivery) }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.carrier"><span class="lbl">{{ $t('shipping_company') }}</span><strong>{{ selectedOrder.carrier }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.tracking_number"><span class="lbl">{{ $t('tracking_number') }}</span><strong dir="ltr">{{ selectedOrder.tracking_number }}</strong></div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <el-card v-if="selectedOrder.notes" shadow="never" class="info-card mt-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-sticky-note"></i> {{ $t('notes') }}</span></template>
                            <p class="notes-txt-view">{{ selectedOrder.notes }}</p>
                        </el-card>
                    </el-tab-pane>

                    <!-- ---------------- Execution ---------------- -->
                    <el-tab-pane name="execution">
                        <template #label><i class="fas fa-diagram-project"></i> {{ $t('fulfilment_and_routing') }}</template>

                        <!-- Numbered stage tracker. The number is the point: it
                             says how far along the order is and how far is left,
                             which an icon alone never did. -->
                        <div class="stage-timeline">
                            <div
                                v-for="(step, i) in timelineSteps"
                                :key="step.key"
                                class="stage-step"
                                :class="{ done: step.done, current: step.current, skipped: isCancelled }"
                            >
                                <div class="step-marker">
                                    <span v-if="step.done && !step.current" class="step-check"><i class="fas fa-check"></i></span>
                                    <span v-else class="step-number">{{ i + 1 }}</span>
                                </div>
                                <div class="step-text">
                                    <strong>{{ step.label }}</strong>
                                    <span class="step-date">{{ step.at ? formatDate(step.at) : '—' }}</span>
                                    <span v-if="step.current" class="step-now">{{ $t('current_stage') }}</span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="step-connector" :class="{ filled: step.done }"></div>
                            </div>
                        </div>

                        <!-- What this stage produces, and what the next one needs -->
                        <div v-if="nextStage" class="next-stage-bar">
                            <div class="next-stage-head">
                                <span class="next-badge">{{ $t('stage_x_of_y', { current: currentStageNumber, total: timelineSteps.length }) }}</span>
                                <strong>{{ $t('next_label', { stage: nextStage.label }) }}</strong>
                            </div>
                            <p class="next-stage-effect"><i class="fas fa-arrow-turn-down"></i> {{ nextStage.effect }}</p>
                            <el-button
                                :type="STAGE_ACTIONS[nextStage.status]?.type || 'primary'"
                                size="small"
                                class="next-stage-go"
                                :loading="store.saving"
                                @click="handleStageMove({ status: nextStage.status, ...STAGE_ACTIONS[nextStage.status] })"
                            >
                                <i class="fas" :class="STAGE_ACTIONS[nextStage.status]?.icon"></i>&nbsp;{{ nextStage.label }}
                            </el-button>
                        </div>
                        <el-alert v-if="isCancelled" type="info" show-icon :closable="false" class="mb-3"
                            :title="$t('order_cancelled_effects_reversed')" />

                        <!-- Follow-up: how long the order has sat where it is -->
                        <div class="follow-up-bar" :class="followUpClass">
                            <i class="fas" :class="followUp.needs_attention ? 'fa-triangle-exclamation' : 'fa-hourglass-half'"></i>
                            <div>
                                <strong v-if="followUp.attention_reasons?.length">{{ followUp.attention_reasons.join(' — ') }}</strong>
                                <strong v-else-if="!followUp.is_open">{{ $t('order_path_completed') }}</strong>
                                <strong v-else>{{ $t('in_this_stage_for', { age: stageAgeText(followUp) }) }}</strong>
                                <p v-if="followUp.is_open && followUp.stage_threshold_days">
                                    {{ $t('usual_stage_limit_days', { days: followUp.stage_threshold_days }) }}
                                </p>
                            </div>
                        </div>

                        <!-- The append-only record of who moved this order and why -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-clock-rotate-left"></i> {{ $t('stage_history') }}</span></template>
                            <div v-if="history.length" class="history-list">
                                <div v-for="entry in history" :key="entry.id" class="history-entry">
                                    <span class="history-dot" :class="`dot-${entry.to_status}`"></span>
                                    <div class="history-body">
                                        <div class="history-head">
                                            <strong>
                                                <template v-if="entry.from_status">
                                                    {{ getArabicStatus(entry.from_status) }} ← {{ getArabicStatus(entry.to_status) }}
                                                </template>
                                                <template v-else>{{ getArabicStatus(entry.to_status) }}</template>
                                            </strong>
                                            <span class="history-when">{{ formatDateTime(entry.created_at) }}</span>
                                        </div>
                                        <p class="history-meta">
                                            <i class="fas fa-user"></i> {{ entry.user?.name || $t('the_system') }}
                                        </p>
                                        <p v-if="entry.note" class="history-note">{{ entry.note }}</p>
                                    </div>
                                </div>
                            </div>
                            <el-empty v-else :description="$t('no_stage_history')" :image-size="52" />
                        </el-card>

                        <el-row :gutter="16">
                            <el-col :xs="24" :md="14">
                                <el-card shadow="never" class="info-card routing-card">
                                    <template #header>
                                        <div class="routing-header">
                                            <span class="card-title-txt"><i class="fas fa-route"></i> {{ $t('order_routing') }}</span>
                                            <el-button text size="small" :loading="routingLoading" @click="loadRouting(selectedOrder.id)">
                                                <i class="fas fa-sync-alt"></i>
                                            </el-button>
                                        </div>
                                    </template>

                                    <div class="routing-field">
                                        <span class="lbl">{{ $t('fulfilment_type') }}</span>
                                        <el-radio-group
                                            :model-value="selectedOrder.fulfillment_type || 'ship'"
                                            size="small"
                                            :disabled="!routing.can_change_fulfillment_type || store.saving"
                                            @change="handleFulfillmentTypeChange"
                                        >
                                            <el-radio-button value="ship">{{ $t('shipping') }}</el-radio-button>
                                            <el-radio-button value="delivery">{{ $t('courier_delivery') }}</el-radio-button>
                                            <el-radio-button value="pickup">{{ $t('branch_pickup') }}</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                    <p v-if="!routing.can_change_fulfillment_type" class="routing-locked-note">
                                        <i class="fas fa-lock"></i> {{ $t('fulfilment_type_locked_after_shipping') }}
                                    </p>

                                    <el-divider />

                                    <!-- A routing is a set, not a single choice:
                                         an order split across two branches is
                                         routed to both, and only those two can
                                         then source its lines. -->
                                    <div class="routing-select-head">
                                        <span class="lbl">{{ $t('routed_warehouses') }}</span>
                                        <el-button
                                            v-if="routingsDirty && sourcing.editable"
                                            type="primary" size="small" :loading="savingRoutings"
                                            @click="saveRoutings"
                                        >{{ $t('save_routing') }}</el-button>
                                    </div>
                                    <p class="routing-hint">
                                        <i class="fas fa-circle-info"></i>
                                        {{ $t('routing_hint') }}
                                    </p>

                                    <div v-loading="routingLoading" class="warehouse-options">
                                        <div
                                            v-for="wh in routing.warehouses"
                                            :key="wh.warehouse_id"
                                            class="warehouse-option"
                                            :class="{ current: wh.is_current, selected: selectedRoutingIds.includes(wh.warehouse_id), short: !wh.covers_all }"
                                        >
                                            <div class="wh-head">
                                                <div class="wh-name">
                                                    <el-checkbox
                                                        :model-value="selectedRoutingIds.includes(wh.warehouse_id)"
                                                        :disabled="!sourcing.editable || savingRoutings"
                                                        @change="(checked) => toggleRouting(wh.warehouse_id, checked)"
                                                    />
                                                    <strong>{{ wh.name }}</strong>
                                                    <el-tag v-if="wh.is_current" size="small" type="primary" effect="dark">{{ $t('responsible') }}</el-tag>
                                                    <el-tag v-else-if="wh.is_recommended" size="small" type="success" effect="plain">{{ $t('suggested') }}</el-tag>
                                                </div>
                                                <span class="wh-type">{{ wh.location_type_text }}</span>
                                            </div>

                                            <el-progress :percentage="wh.coverage_percentage" :stroke-width="6"
                                                :status="wh.covers_all ? 'success' : 'warning'" :show-text="false" />
                                            <div class="wh-coverage">
                                                <span :class="wh.covers_all ? 'text-success' : 'text-warning'">
                                                    <i class="fas" :class="wh.covers_all ? 'fa-check-circle' : 'fa-triangle-exclamation'"></i>
                                                    {{ $t('covers_items_of_total', { covered: wh.covered_items, total: wh.total_items }) }}
                                                </span>
                                                <el-button
                                                    v-if="!wh.is_current && routing.can_change_fulfillment_type"
                                                    size="small" text type="primary" :loading="store.saving"
                                                    @click="routeToWarehouse(wh.warehouse_id)"
                                                >{{ $t('make_it_primary') }}</el-button>
                                            </div>

                                            <ul v-if="!wh.covers_all" class="shortfall-list">
                                                <li v-for="item in wh.items.filter((i) => i.shortfall > 0)" :key="item.product_id">
                                                    {{ item.product_name }} — {{ $t('short_by', { count: item.shortfall }) }}
                                                    <span class="muted">{{ $t('required_available', { required: item.required, available: item.available }) }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </el-card>

                                <!-- Where each line's goods actually come from.
                                     Routing above picks a warehouse for the
                                     whole order; this decides it per line, and
                                     lets one line be split across sources. -->
                                <el-card shadow="never" class="info-card sourcing-card">
                                    <template #header>
                                        <div class="routing-header">
                                            <span class="card-title-txt"><i class="fas fa-code-branch"></i> {{ $t('source_per_item') }}</span>
                                            <el-button
                                                v-if="sourcing.editable && sourcingDirty"
                                                type="primary" size="small" :loading="savingSourcing"
                                                @click="saveSourcing"
                                            >{{ $t('save_sources') }}</el-button>
                                        </div>
                                    </template>

                                    <p v-if="!sourcing.editable" class="routing-locked-note">
                                        <i class="fas fa-lock"></i> {{ $t('source_locked_after_shipping') }}
                                    </p>
                                    <!-- Says which list the operator is choosing
                                         from, so a warehouse missing here reads
                                         as "not routed" rather than "not found". -->
                                    <p v-else-if="sourcing.selected_warehouse_ids" class="routing-hint">
                                        <i class="fas fa-filter"></i>
                                        {{ $t('sources_are_order_routing', { count: sourcing.selected_warehouse_ids.length }) }}
                                    </p>
                                    <p v-else class="routing-hint">
                                        <i class="fas fa-circle-info"></i>
                                        {{ $t('no_routing_yet_all_shown') }}
                                    </p>

                                    <div v-loading="sourcingLoading" class="sourcing-lines">
                                        <div v-for="l in sourcing.lines" :key="l.item_id" class="sourcing-line">
                                            <div class="sl-head">
                                                <div>
                                                    <strong>{{ l.product_name }}</strong>
                                                    <span class="muted"> · {{ l.sku || '—' }}</span>
                                                </div>
                                                <!-- The gap is the number that matters: unsourced units
                                                     cannot ship, so it is stated rather than implied. -->
                                                <el-tag
                                                    :type="l.allocated === l.quantity ? 'success' : 'danger'"
                                                    size="small"
                                                    effect="plain"
                                                >
                                                    {{ l.allocated }} / {{ l.quantity }}
                                                    <template v-if="l.allocated !== l.quantity">
                                                        — {{ $t('short_by', { count: l.quantity - l.allocated }) }}
                                                    </template>
                                                </el-tag>
                                            </div>

                                            <div class="sl-sources">
                                                <div v-for="s in l.sources" :key="s.warehouse_id" class="sl-source">
                                                    <div class="sl-wh">
                                                        <span>{{ s.warehouse_name }}</span>
                                                        <el-tag v-if="s.is_primary" size="small" effect="plain">{{ $t('primary_label') }}</el-tag>
                                                        <span class="sl-avail" :class="{ 'text-danger': s.available <= 0 }">
                                                            {{ $t('available_count', { count: s.available }) }}
                                                        </span>
                                                    </div>
                                                    <el-input-number
                                                        :model-value="s.allocated"
                                                        :min="0"
                                                        :max="s.available"
                                                        size="small"
                                                        controls-position="right"
                                                        :disabled="!sourcing.editable || s.available <= 0"
                                                        @change="(v) => setSource(l, s, v)"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <el-empty v-if="!sourcing.lines?.length" :description="$t('no_items')" :image-size="46" />
                                    </div>
                                </el-card>
                            </el-col>

                            <el-col :xs="24" :md="10">
                                <el-card shadow="never" class="info-card stage-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-forward"></i> {{ $t('next_stage') }}</span></template>
                                    <p class="stage-explainer">{{ stageExplainer }}</p>
                                    <div class="stage-actions">
                                        <el-button
                                            v-for="action in stageActions"
                                            :key="action.status"
                                            :type="action.type"
                                            :plain="action.plain"
                                            :loading="store.saving"
                                            class="stage-btn"
                                            @click="handleStageMove(action)"
                                        >
                                            <i class="fas" :class="action.icon"></i> {{ action.label }}
                                        </el-button>
                                        <el-empty v-if="!stageActions.length" :description="$t('no_next_stages')" :image-size="46" />
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <!-- ---------------- Documents ---------------- -->
                    <el-tab-pane name="documents">
                        <template #label>
                            <i class="fas fa-file-invoice-dollar"></i> {{ $t('documents_and_entries') }}
                            <el-badge v-if="documentIssueCount" :value="documentIssueCount" type="danger" class="tab-badge" />
                        </template>

                        <!-- Invoice -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-file-invoice"></i> {{ $t('sales_invoice') }}</span></template>
                            <div v-if="invoice" class="doc-invoice">
                                <div class="doc-line">
                                    <span class="doc-number">{{ invoice.invoice_number }}</span>
                                    <el-tag :type="statusTagType(invoice.status)" size="small" effect="plain">
                                        {{ getArabicStatus(invoice.status) }}
                                    </el-tag>
                                    <el-tag :type="invoicePosted ? 'success' : 'danger'" size="small" effect="dark">
                                        {{ invoicePosted ? $t('posted_to_accounts') : $t('not_posted') }}
                                    </el-tag>
                                </div>
                                <div class="doc-figures">
                                    <div><span>{{ $t('grand_total') }}</span><strong>{{ formatCurrency(invoice.total) }}</strong></div>
                                    <div><span>{{ $t('collected') }}</span><strong>{{ formatCurrency(invoice.paid_amount) }}</strong></div>
                                    <div><span>{{ $t('due_amount') }}</span><strong :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">{{ formatCurrency(invoiceDueAmount) }}</strong></div>
                                </div>
                                <el-button type="success" plain class="mt-2" @click="goToInvoices">
                                    <i class="fas fa-arrow-left"></i> {{ $t('open_invoice_number', { number: invoice.invoice_number }) }}
                                </el-button>
                            </div>
                            <el-empty v-else :description="$t('no_invoice_yet')" :image-size="60" />
                        </el-card>

                        <!-- Payments -->
                        <el-card v-if="payments.length" shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-hand-holding-dollar"></i> {{ $t('payments_list') }}</span></template>
                            <el-table :data="payments" stripe size="small" style="width: 100%">
                                <el-table-column :label="$t('number')" min-width="130">
                                    <template #default="{ row }">{{ row.payment_number || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('date')" width="130" align="center">
                                    <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('method')" width="120" align="center">
                                    <template #default="{ row }">{{ paymentMethodLabel(row.payment_method) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('amount')" width="130" align="center">
                                    <template #default="{ row }"><strong>{{ formatCurrency(row.amount) }}</strong></template>
                                </el-table-column>
                            </el-table>
                        </el-card>

                        <!-- Journal entries -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-book"></i> {{ $t('accounting_entries') }}</span></template>
                            <div v-if="journalEntries.length" class="entry-list">
                                <div v-for="entry in journalEntries" :key="entry.id" class="entry" :class="{ reversed: entry.status === 'reversed' }">
                                    <div class="entry-head">
                                        <strong>{{ entry.entry_number }}</strong>
                                        <span class="entry-date">{{ formatDate(entry.entry_date) }}</span>
                                        <el-tag v-if="entry.status === 'reversed'" size="small" type="info" effect="plain">{{ $t('reversed') }}</el-tag>
                                        <span class="entry-desc">{{ entry.description }}</span>
                                    </div>
                                    <table class="entry-lines">
                                        <tr v-for="l in entry.lines || []" :key="l.id">
                                            <td class="acc-code">{{ l.ledger_account?.code || '—' }}</td>
                                            <td>{{ l.ledger_account?.name || l.description }}</td>
                                            <td class="num">{{ toNum(l.debit) ? formatCurrency(l.debit) : '' }}</td>
                                            <td class="num">{{ toNum(l.credit) ? formatCurrency(l.credit) : '' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <el-empty v-else :description="$t('no_entries_posted')" :image-size="60" />
                        </el-card>

                        <!-- Stock movements -->
                        <el-card shadow="never" class="info-card">
                            <template #header><span class="card-title-txt"><i class="fas fa-dolly"></i> {{ $t('stock_movements') }}</span></template>
                            <el-table v-if="stockMovements.length" :data="stockMovements" stripe size="small" style="width: 100%">
                                <el-table-column :label="$t('item')" min-width="160">
                                    <template #default="{ row }">{{ productName(row.product_id) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('warehouse')" min-width="120">
                                    <template #default="{ row }">{{ row.warehouse?.name || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('type')" width="100" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.movement_type === 'in' ? 'success' : 'danger'" size="small" effect="plain">
                                            {{ row.movement_type === 'in' ? $t('movement_return') : $t('movement_out') }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('quantity')" width="80" align="center">
                                    <template #default="{ row }">{{ row.quantity }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('cost')" width="120" align="center">
                                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('date')" width="130" align="center">
                                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else :description="$t('no_stock_movements_recorded')" :image-size="60" />
                        </el-card>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-drawer>

        <!-- Fulfilment type change: needs a delivery fee for ship/delivery -->
        <el-dialog v-model="typeDialogVisible" :title="$t('change_fulfilment_type')" width="440px" :close-on-click-modal="false">
            <p class="dialog-lead">
                {{ $t('rerouting_notice') }}
            </p>
            <el-form label-position="top">
                <el-form-item :label="$t('shipping_cost_charged_to_customer')">
                    <el-input-number v-model="typeForm.shipping_cost" :min="0" :step="5" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="typeDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" @click="submitFulfillmentType">{{ $t('confirm_change') }}</el-button>
            </template>
        </el-dialog>

        <!-- Delivery: the goods arrive and, usually, the money comes back -->
        <el-dialog v-model="deliverDialogVisible" :title="$t('deliver_and_settle')" width="480px" :close-on-click-modal="false">
            <div v-if="invoice" class="settle-summary">
                <div><span>{{ $t('invoice') }}</span><strong>{{ invoice.invoice_number }}</strong></div>
                <div><span>{{ $t('invoice_total') }}</span><strong>{{ formatCurrency(invoice.total) }}</strong></div>
                <div><span>{{ $t('due_amount') }}</span><strong :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">{{ formatCurrency(invoiceDueAmount) }}</strong></div>
            </div>
            <el-alert v-else type="warning" :closable="false" show-icon class="mb-3"
                :title="$t('no_invoice_delivery_only')" />

            <el-alert
                v-if="invoice && invoiceDueAmount <= 0.01"
                type="success"
                :closable="false"
                show-icon
                class="mb-3"
                :title="$t('invoice_already_paid')"
            />

            <el-form v-else-if="invoice" label-position="top" class="mt-3">
                <el-form-item>
                    <el-checkbox v-model="deliverForm.settle">
                        {{ $t('collect_on_delivery') }}
                    </el-checkbox>
                    <p class="field-hint">
                        {{ $t('leave_empty_for_credit_customer') }}
                    </p>
                </el-form-item>

                <template v-if="deliverForm.settle">
                    <el-form-item :label="$t('amount_collected')">
                        <el-input-number
                            v-model="deliverForm.settlement_amount"
                            :min="0.01"
                            :max="invoiceDueAmount"
                            :step="10"
                            :precision="2"
                            style="width: 100%"
                        />
                        <p class="field-hint">{{ $t('cannot_exceed_remaining') }}</p>
                    </el-form-item>
                    <el-form-item :label="$t('payment_method')">
                        <el-radio-group v-model="deliverForm.payment_method">
                            <el-radio-button value="cash">{{ $t('payment_method_cash') }}</el-radio-button>
                            <el-radio-button value="card">{{ $t('payment_method_card') }}</el-radio-button>
                            <el-radio-button value="bank_transfer">{{ $t('payment_method_transfer') }}</el-radio-button>
                            <el-radio-button value="check">{{ $t('payment_method_check') }}</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item :label="$t('payment_reference')">
                        <el-input v-model="deliverForm.payment_reference" :placeholder="$t('receipt_or_transfer_number_optional')" />
                    </el-form-item>
                </template>
            </el-form>

            <template #footer>
                <el-button @click="deliverDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="success" :loading="store.saving" @click="submitDelivery">
                    {{ deliverForm.settle && invoice && invoiceDueAmount > 0.01 ? $t('deliver_and_collect') : $t('confirm_delivery') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Shipping: capture the tracking details as the goods leave -->
        <el-dialog v-model="shipDialogVisible" :title="$t('confirm_shipping')" width="440px" :close-on-click-modal="false">
            <el-alert
                type="warning"
                :closable="false"
                show-icon
                :title="$t('stock_leaves_and_cogs_posted')"
                class="mb-3"
            />
            <el-form label-position="top">
                <el-form-item :label="$t('shipping_company')">
                    <el-input v-model="shipForm.carrier" :placeholder="$t('carrier_example')" />
                </el-form-item>
                <el-form-item :label="$t('tracking_number')">
                    <el-input v-model="shipForm.tracking_number" placeholder="TRK-000000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="shipDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" @click="submitShipment">{{ $t('confirm_shipping') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, onBeforeUnmount, computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSalesOrdersStore } from '@/stores/salesOrders';
import { salesOrdersApi } from '@/api/salesOrders';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { variantLabelOf } from '@/utils/productPick';
import { useStockShortage } from '@/Composables/useStockShortage';

const { t } = useI18n();
import {
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    formatCurrency,
    formatDate,
    localIsoDate,
    paymentMethodLabel,
    apiErrorMessage,
} from '@/utils/sales';

const router = useRouter();
const route = useRoute();
const { handleStockShortage, canRaisePurchaseOrder } = useStockShortage();
const store = useSalesOrdersStore();


// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

// Status presentation is shared across the sales module so tags, icons and
// labels stay identical on every screen — see resources/js/utils/sales.js.
const statusIconClass = (status) => statusIcon(status);
const getArabicStatus = (status) => statusLabel(status);

const FULFILLMENT_LABELS = { ship: t('shipping'), pickup: t('branch_pickup'), delivery: t('courier_delivery') };
const fulfillmentLabel = (type) => FULFILLMENT_LABELS[normalizeStatus(type)] || t('not_specified');
const fulfillmentTagType = (type) => ({ ship: 'primary', delivery: 'warning', pickup: 'success' }[normalizeStatus(type)] || 'info');

/* ------------------------------------------------------------------ *
 * Pipeline view
 *
 * Filtering, searching and counting all used to happen in the browser over
 * whatever page was loaded — so an order on page two could not be found, and
 * t('total_sales_orders') was really "orders currently on screen". All three are
 * now the server's answers over the whole table.
 * ------------------------------------------------------------------ */

const counts = computed(() => store.statusCounts);
const totals = computed(() => store.totals || { open_value: 0, delivered_month_value: 0, delivered_month_count: 0, to_collect: 0, to_collect_count: 0 });

const stageTabs = computed(() => [
    { name: 'all', label: t('all'), icon: 'fa-layer-group', count: counts.value.all },
    { name: 'pending', label: t('sales_status_pending'), icon: 'fa-clock', count: counts.value.pending },
    { name: 'confirmed', label: t('sales_status_confirmed'), icon: 'fa-circle-check', count: counts.value.confirmed },
    { name: 'processing', label: t('being_prepared'), icon: 'fa-gears', count: counts.value.processing },
    { name: 'shipped', label: t('shipped_state'), icon: 'fa-truck-fast', count: counts.value.shipped },
    { name: 'delivered', label: t('delivered_state'), icon: 'fa-box-open', count: counts.value.delivered },
    { name: 'cancelled', label: t('sales_status_cancelled'), icon: 'fa-ban', count: counts.value.cancelled },
]);

// 'open' is confirmed + processing + shipped: the in-progress card, sent to
// the API as its own flag.
const STAGES = ['all', 'open', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

// ── Filters, sorting and paging, kept in the URL ─────────────────────────
const blankFilters = () => ({
    stage: 'all', search: '', warehouse_id: null, fulfillment_type: '', employee_id: null,
    payment: '', range: null, attention: false,
});
const filters = reactive(blankFilters());
const sort = reactive({ prop: 'created_at', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    // Links from before the redesign sent ?overdue=1; it now lives under attention.
    Object.assign(filters, {
        stage: STAGES.includes(q.stage) ? q.stage : 'all',
        search: q.search ? String(q.search) : '',
        warehouse_id: Number(q.warehouse_id) || null,
        fulfillment_type: ['ship', 'delivery', 'pickup'].includes(q.fulfillment) ? q.fulfillment : '',
        employee_id: Number(q.employee_id) || null,
        payment: ['due', 'paid'].includes(q.payment) ? q.payment : '',
        range: q.from && q.to ? [String(q.from), String(q.to)] : null,
        attention: q.attention === '1' || q.overdue === '1',
    });
    sort.prop = ['total', 'order_number', 'order_date', 'expected_delivery'].includes(q.sort) ? q.sort : 'created_at';
    sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        stage: filters.stage !== 'all' ? filters.stage : undefined,
        search: filters.search || undefined,
        warehouse_id: filters.warehouse_id || undefined,
        fulfillment: filters.fulfillment_type || undefined,
        employee_id: filters.employee_id || undefined,
        payment: filters.payment || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        attention: filters.attention ? '1' : undefined,
        sort: sort.prop !== 'created_at' ? sort.prop : undefined,
        direction: sort.order === 'ascending' ? 'asc' : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.warehouse_id, filters.fulfillment_type, filters.employee_id,
    filters.payment, filters.range?.length ? '1' : '', filters.attention ? '1' : '',
].filter(Boolean).length);

let optionsLoaded = false;

const loadOrders = () => {
    const params = {
        page: currentPage.value,
        per_page: pageSize.value,
        search: filters.search.trim() || undefined,
        warehouse_id: filters.warehouse_id || undefined,
        fulfillment_type: filters.fulfillment_type || undefined,
        employee_id: filters.employee_id || undefined,
        payment: filters.payment || undefined,
        date_from: filters.range?.[0] || undefined,
        date_to: filters.range?.[1] || undefined,
        attention: filters.attention ? 1 : undefined,
        sort: sort.prop,
        direction: sort.order === 'ascending' ? 'asc' : 'desc',
        with_options: optionsLoaded ? undefined : 1,
    };

    if (filters.stage === 'open') params.open = 1;
    else if (filters.stage !== 'all') params.status = filters.stage;

    return store.fetchOrders(params)
        .then(() => { optionsLoaded = true; })
        .catch(() => {});
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    loadOrders();
};

// Debounced, so typing a nine-character order number is one request and not nine.
let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = (withStage = false) => {
    const stage = filters.stage;
    Object.assign(filters, blankFilters());
    if (withStage !== true) filters.stage = stage;
    applyFilters();
};

const setStage = (stage) => {
    filters.stage = filters.stage === stage && stage !== 'all' ? 'all' : stage;
    applyFilters();
};

const togglePayment = (payment) => {
    filters.payment = filters.payment === payment ? '' : payment;
    applyFilters();
};

const toggleAttention = () => {
    filters.attention = !filters.attention;
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'created_at';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    loadOrders();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    loadOrders();
};

const rangeFrom = computed(() => (store.pagination.total ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, store.pagination.total));

const dateShortcuts = computed(() => {
    const now = new Date();
    const at = (y, m, d) => localIsoDate(new Date(y, m, d));
    const y = now.getFullYear();
    const m = now.getMonth();
    return [
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_last_90_days'), value: () => [at(y, m, now.getDate() - 90), localIsoDate(now)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

/** How long the order has sat where it is. */
const stageAgeText = (followUp) => {
    if (!followUp) return '—';
    if (!followUp.is_open) return t('sales_status_completed');

    const days = followUp.days_in_stage;
    if (days === 0) return t('today');
    if (days === 1) return t('a_day_ago');
    if (days === 2) return t('two_days_ago');
    return t('days_ago', { days });
};

const stageAgeClass = (followUp) => {
    if (!followUp?.is_open) return 'age-done';
    if (followUp.is_overdue) return 'age-overdue';
    if (followUp.is_stalled) return 'age-stalled';
    return 'age-ok';
};

const rowClassName = ({ row }) => (row.follow_up?.needs_attention ? 'row-needs-attention' : '');

const runRowCommand = (row, command) => {
    if (command === 'view') return openDetailDrawer(row.id);
    if (command === 'execution' || command === 'documents') return openDetailDrawer(row.id, command);
    if (command === 'edit') return openEditDrawer(row.id);
    if (command === 'purchase') return createPurchaseRequest(row);
    if (command === 'delete') return deleteOrder(row.id);
    return null;
};

const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

/* ------------------------------------------------------------------ *
 * Routing and execution stages
 *
 * Confirming an order reserves stock, raises an invoice and posts to the
 * ledger — so the operator needs to see, before committing, whether the
 * warehouse serving the order can actually cover its lines. That check used to
 * be invisible: the warehouse was silently defaulted to "the first active one"
 * and the shortfall only surfaced as a failed confirmation.
 * ------------------------------------------------------------------ */

const routing = ref({ warehouses: [], can_change_fulfillment_type: true, recommended_warehouse_id: null });
const routingLoading = ref(false);

const typeDialogVisible = ref(false);
const typeForm = reactive({ fulfillment_type: 'ship', shipping_cost: 0, fulfillment_warehouse_id: null });

const shipDialogVisible = ref(false);
const shipForm = reactive({ carrier: '', tracking_number: '' });

const deliverDialogVisible = ref(false);
const deliverForm = reactive({
    settle: true,
    settlement_amount: 0,
    payment_method: 'cash',
    payment_reference: '',
    note: '',
});

/* ------------------------------------------------------------------ *
 * Source selection
 *
 * Routing chooses a warehouse for the whole order. This chooses it per line,
 * and lets a single line draw from more than one place — the seller's own
 * stock first, the remainder from the main warehouse. The split is what the
 * ledger then credits, so it has to be visible and editable here rather than
 * being decided silently.
 * ------------------------------------------------------------------ */

const sourcing = ref({ lines: [], editable: false, selected_warehouse_ids: null });
const sourcingLoading = ref(false);
const savingSourcing = ref(false);
const sourcingDirty = ref(false);

/* ------------------------------------------------------------------ *
 * Routing selection
 *
 * The order may be routed through several warehouses at once, and the sourcing
 * editor below offers exactly those. The selection is edited locally and
 * committed in one call, so ticking three boxes is one decision rather than
 * three separate saves the server would have to reconcile.
 * ------------------------------------------------------------------ */

const selectedRoutingIds = ref([]);
const savingRoutings = ref(false);
const routingsDirty = ref(false);

const sameIds = (a, b) => a.length === b.length && [...a].sort().every((v, i) => v === [...b].sort()[i]);

/** Mirrors the saved selection back into the local one, clearing the dirty flag. */
const syncRoutingSelection = () => {
    // `null` means nothing has been narrowed down; the boxes start empty rather
    // than pretending every warehouse was deliberately chosen.
    selectedRoutingIds.value = [...(sourcing.value.selected_warehouse_ids || [])];
    routingsDirty.value = false;
};

const toggleRouting = (warehouseId, checked) => {
    const next = new Set(selectedRoutingIds.value);
    if (checked) {
        next.add(warehouseId);
    } else {
        next.delete(warehouseId);
    }
    selectedRoutingIds.value = [...next];
    routingsDirty.value = !sameIds(selectedRoutingIds.value, sourcing.value.selected_warehouse_ids || []);
};

const saveRoutings = async () => {
    if (!selectedRoutingIds.value.length) {
        ElMessage.warning(t('select_at_least_one_warehouse'));
        return;
    }

    savingRoutings.value = true;
    try {
        const res = await salesOrdersApi.saveRoutings(selectedOrder.value.id, selectedRoutingIds.value);
        sourcing.value = res.data?.data || sourcing.value;
        syncRoutingSelection();
        sourcingDirty.value = false;
        ElMessage.success(res.data?.message || t('routing_saved'));
        // The coverage panel and the order header both read the owning
        // warehouse, which may have moved with the selection.
        await Promise.all([loadRouting(selectedOrder.value.id), refreshDetail()]);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_save_routing')));
        // Put the boxes back to what the server actually holds, so the screen
        // never shows a selection that was refused.
        syncRoutingSelection();
    } finally {
        savingRoutings.value = false;
    }
};

const loadSourcing = async (id) => {
    sourcingLoading.value = true;
    try {
        const res = await salesOrdersApi.sourcing(id);
        sourcing.value = res.data?.data || { lines: [], editable: false, selected_warehouse_ids: null };
        sourcingDirty.value = false;
        syncRoutingSelection();
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_load_sources')));
    } finally {
        sourcingLoading.value = false;
    }
};

/** Edits one warehouse's share locally; nothing is committed until saved. */
const setSource = (line, source, value) => {
    source.allocated = Number(value) || 0;
    line.allocated = line.sources.reduce((sum, s) => sum + (Number(s.allocated) || 0), 0);
    sourcingDirty.value = true;
};

const saveSourcing = async () => {
    // Refused server-side too, but catching it here spares a round trip and
    // names the line that is short.
    const incomplete = (sourcing.value.lines || []).find((l) => l.allocated !== l.quantity);
    if (incomplete) {
        ElMessage.warning(
            t('incomplete_sourcing_warning', { product: incomplete.product_name, allocated: incomplete.allocated, quantity: incomplete.quantity })
        );
        return;
    }

    savingSourcing.value = true;
    try {
        const res = await salesOrdersApi.saveSourcing(selectedOrder.value.id, {
            lines: sourcing.value.lines.map((l) => ({
                item_id: l.item_id,
                sources: l.sources
                    .filter((s) => Number(s.allocated) > 0)
                    .map((s) => ({ warehouse_id: s.warehouse_id, quantity: Number(s.allocated) })),
            })),
        });

        sourcing.value = res.data?.data || sourcing.value;
        sourcingDirty.value = false;
        syncRoutingSelection();
        ElMessage.success(res.data?.message || t('sources_saved'));
        await refreshDetail();
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_save_sources')));
    } finally {
        savingSourcing.value = false;
    }
};

const loadRouting = async (id) => {
    routingLoading.value = true;
    try {
        routing.value = await store.fetchRouting(id);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_load_routing')));
    } finally {
        routingLoading.value = false;
    }
};

/** What each stage move will actually do, said plainly before it is clicked. */
const STAGE_ACTIONS = {
    confirmed: { label: t('confirm_order'), short: t('so_step_confirm'), icon: 'fa-circle-check', type: 'primary', plain: false,
        confirm: t('confirm_order_effects') },
    processing: { label: t('start_preparation'), short: t('so_step_prepare'), icon: 'fa-gears', type: 'warning', plain: true,
        confirm: t('move_to_preparation_confirm') },
    shipped: { label: t('confirm_shipping'), short: t('so_step_ship'), icon: 'fa-truck-fast', type: 'success', plain: false, dialog: 'ship' },
    // Delivery opens the settlement dialog rather than a plain confirm: the
    // money usually comes back at the door, and asking after the fact means it
    // gets recorded from memory or not at all.
    delivered: { label: t('deliver_and_settle_action'), short: t('so_step_deliver'), icon: 'fa-hand-holding-dollar', type: 'success', plain: false, dialog: 'deliver' },
    cancelled: { label: t('cancel_the_request'), icon: 'fa-ban', type: 'danger', plain: true,
        confirm: t('cancel_order_effects') },
};

const stageActions = computed(() =>
    (routing.value.allowed_transitions || []).map((status) => ({ status, ...STAGE_ACTIONS[status] })).filter((a) => a.label)
);

/** Where the order sits in the 1..5 sequence, for the numbered tracker. */
const STAGE_SEQUENCE = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

const currentStageNumber = computed(() => {
    const i = STAGE_SEQUENCE.indexOf(normalizeStatus(selectedOrder.value?.status));
    return i === -1 ? 0 : i + 1;
});

/**
 * The next stage and what entering it will do. Named from the sequence rather
 * than from `allowed_transitions`, because cancelling is an exit, not progress.
 */
const NEXT_STAGE_EFFECT = {
    confirmed: t('stage_hint_confirm'),
    processing: t('stage_hint_processing'),
    shipped: t('stage_hint_shipping'),
    delivered: t('stage_hint_delivery'),
};

const nextStage = computed(() => {
    const current = normalizeStatus(selectedOrder.value?.status);
    const i = STAGE_SEQUENCE.indexOf(current);
    if (i === -1 || i >= STAGE_SEQUENCE.length - 1) return null;

    const next = STAGE_SEQUENCE[i + 1];
    if (!(routing.value.allowed_transitions || []).includes(next)) return null;

    return { status: next, label: STAGE_ACTIONS[next]?.label || next, effect: NEXT_STAGE_EFFECT[next] || '' };
});

/** The forward step a list row offers, by the order's stage. */
const ROW_NEXT = {
    pending: 'confirmed',
    confirmed: 'processing',
    processing: 'shipped',
    shipped: 'delivered',
};

const advancingId = ref(null);

// Purchasing is an admin area; the action is not offered to whoever it would refuse.
const mayPurchase = computed(() => canRaisePurchaseOrder());

/** A purchase request with this order's lines, opened on the purchases screen. */
const createPurchaseRequest = (row) => {
    router.push({ path: '/admin/purchases/orders', query: { from_sales_order: row.id } });
};

/**
 * From the list straight into the order's next step. The drawer opens on the
 * execution tab first, so the dialogs that need the order (tracking on ship,
 * the balance on delivery) have it — and so a refusal lands where it can be
 * fixed.
 */
const advanceOrder = async (row) => {
    advancingId.value = row.id;
    try {
        await openDetailDrawer(row.id, 'execution');
        const target = ROW_NEXT[normalizeStatus(selectedOrder.value?.status)];
        if (target && (routing.value.allowed_transitions || []).includes(target)) {
            await handleStageMove({ status: target, ...STAGE_ACTIONS[target] });
        }
    } finally {
        advancingId.value = null;
    }
};

const stageExplainer = computed(() => {
    switch (normalizeStatus(selectedOrder.value?.status)) {
        case 'pending': return t('state_hint_pending');
        case 'confirmed': return t('state_hint_confirmed');
        case 'processing': return t('state_hint_processing');
        case 'shipped': return t('state_hint_shipped');
        case 'delivered': return invoiceDueAmount.value > 0.01
            ? t('delivered_with_outstanding', { amount: formatCurrency(invoiceDueAmount.value) })
            : t('state_hint_delivered');
        case 'cancelled': return t('state_hint_cancelled');
        default: return '';
    }
});

/** Applies whatever the API says a move changed, and reports it in plain terms. */
const afterStageChange = async (result) => {
    selectedOrder.value = { ...selectedOrder.value, ...(result.sales_order || {}) };
    // A stage move rewrites the documents behind the order, so the whole detail
    // payload is refetched rather than just the routing figures.
    await refreshDetail();

    const effects = result.effects || result.transition?.effects || {};
    const notes = [];
    if (effects.invoice_number) notes.push(t('effect_invoice', { number: effects.invoice_number }));
    if (effects.cost_of_goods_sold) notes.push(t('effect_cost_of_goods', { amount: effects.cost_of_goods_sold }));
    if (effects.stock_movements?.length) notes.push(t('effect_stock_movements', { count: effects.stock_movements.length }));
    if (effects.reservation_released) notes.push(t('release_reservation'));
    if (effects.stock_returned) notes.push(t('return_goods_to_stock'));
    if (effects.invoice_cancelled) notes.push(t('effect_invoice_cancelled', { number: effects.invoice_cancelled }));
    if (effects.invoice_restated) notes.push(t('effect_invoice_restated', { number: effects.invoice_restated }));
    if (effects.settlement?.payment_number) {
        notes.push(t('effect_collected', { amount: formatCurrency(effects.settlement.amount), number: effects.settlement.payment_number }));
        if (effects.settlement.remaining > 0.01) notes.push(t('effect_remaining', { amount: formatCurrency(effects.settlement.remaining) }));
    }

    ElMessage.success({
        message: notes.length ? `${result.message} (${notes.join(t('list_separator'))})` : result.message,
        duration: 5000,
    });
    // The row moved stage, and with it the counts, the totals and possibly
    // whether it still belongs in the filtered list at all.
    loadOrders();
};

const handleStageMove = async (action) => {
    if (action.dialog === 'ship') {
        shipForm.carrier = selectedOrder.value?.carrier || '';
        shipForm.tracking_number = selectedOrder.value?.tracking_number || '';
        shipDialogVisible.value = true;
        return;
    }

    if (action.dialog === 'deliver') {
        // Pre-filled with the whole outstanding balance, which is what
        // collecting on delivery normally means; a partial amount is a typed
        // correction rather than something the operator has to assemble.
        deliverForm.settle = invoiceDueAmount.value > 0.01;
        deliverForm.settlement_amount = invoiceDueAmount.value;
        deliverForm.payment_method = 'cash';
        deliverForm.payment_reference = '';
        deliverDialogVisible.value = true;
        return;
    }

    let note = null;

    if (action.status === 'cancelled') {
        // Cancelling reverses stock and posts reversing entries. The reason goes
        // onto the stage history, so the record explains itself later.
        const prompted = await ElMessageBox.prompt(action.confirm, action.label, {
            type: 'warning',
            confirmButtonText: t('cancel_the_request'),
            cancelButtonText: t('undo'),
            inputPlaceholder: t('cancellation_reason_required_label'),
            inputValidator: (v) => (v && v.trim().length >= 3) || t('please_give_cancellation_reason'),
        }).catch(() => null);

        if (!prompted) return;
        note = prompted.value;
    } else {
        try {
            await ElMessageBox.confirm(action.confirm, action.label, {
                type: 'info',
                confirmButtonText: action.label,
                cancelButtonText: t('cancel'),
            });
        } catch {
            return;
        }
    }

    const orderId = selectedOrder.value.id;

    try {
        await afterStageChange(await store.moveToStage(orderId, action.status, note ? { note } : {}));
    } catch (e) {
        // A confirmation refused for lack of stock offers to raise the purchase
        // order instead of only naming what is missing.
        if (await handleStockShortage(e, orderId)) return;

        ElMessage.error(apiErrorMessage(e, t('failed_to_move_stage')));
    }
};

const submitDelivery = async () => {
    const payload = { note: deliverForm.note || undefined };

    if (deliverForm.settle) {
        payload.settle = true;
        payload.settlement_amount = deliverForm.settlement_amount;
        payload.payment_method = deliverForm.payment_method;
        if (deliverForm.payment_reference) payload.payment_reference = deliverForm.payment_reference;
    }

    try {
        const result = await store.moveToStage(selectedOrder.value.id, 'delivered', payload);
        deliverDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_confirm_delivery')));
    }
};

const submitShipment = async () => {
    try {
        const result = await store.moveToStage(selectedOrder.value.id, 'shipped', { ...shipForm });
        shipDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_confirm_shipping')));
    }
};

const handleFulfillmentTypeChange = async (type) => {
    typeForm.fulfillment_type = type;
    typeForm.fulfillment_warehouse_id = null;

    // Collecting in person carries no delivery fee, so there is nothing to ask.
    if (type === 'pickup') {
        typeForm.shipping_cost = 0;
        await submitFulfillmentType();
        return;
    }

    typeForm.shipping_cost = Number(selectedOrder.value?.shipping_cost || 0);
    typeDialogVisible.value = true;
};

const routeToWarehouse = async (warehouseId) => {
    typeForm.fulfillment_type = selectedOrder.value?.fulfillment_type || 'ship';
    typeForm.fulfillment_warehouse_id = warehouseId;
    typeForm.shipping_cost = Number(selectedOrder.value?.shipping_cost || 0);
    await submitFulfillmentType();
};

const submitFulfillmentType = async () => {
    try {
        const result = await store.changeFulfillmentType(selectedOrder.value.id, { ...typeForm });
        typeDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_change_fulfilment_type')));
    }
};

/* ------------------------------------------------------------------ *
 * Detail view
 *
 * The drawer used to render the order row on its own. An order could look
 * finished — delivered, stock gone — while the revenue behind it had never
 * reached the ledger, and nothing on the screen said so. The documents that are
 * supposed to follow an order are now shown beside it, together with a plain
 * statement of which one is missing.
 * ------------------------------------------------------------------ */

const detailTab = ref('overview');
const detail = ref({});

const invoice = computed(() => detail.value.invoice || null);
const payments = computed(() => detail.value.payments || []);
const journalEntries = computed(() => detail.value.journal_entries || []);
const stockMovements = computed(() => detail.value.stock_movements || []);
const diagnostics = computed(() => detail.value.diagnostics || []);
const history = computed(() => detail.value.history || []);
const followUp = computed(() => detail.value.follow_up || {});

const followUpClass = computed(() => {
    if (!followUp.value.is_open) return 'fu-done';
    if (followUp.value.is_overdue) return 'fu-overdue';
    if (followUp.value.is_stalled) return 'fu-stalled';
    return 'fu-ok';
});

/** History rows carry a full timestamp; the date alone would hide same-day moves. */
const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return `${formatDate(value)} — ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
};

const toNum = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

const lineTotal = (row) => toNum(row.unit_price) * toNum(row.quantity) - toNum(row.discount) + toNum(row.tax);

const invoiceDueAmount = computed(() =>
    invoice.value ? Math.max(0, toNum(invoice.value.total) - toNum(invoice.value.paid_amount)) : 0
);

/**
 * Whether the invoice reached the ledger — the check the diagnostics turn on.
 * Matched exactly or on a colon-anchored suffix, since a bare `invoice:1`
 * prefix would also match `invoice:10`.
 */
const invoicePosted = computed(() => {
    if (!invoice.value) return false;
    const key = `invoice:${invoice.value.id}`;

    return journalEntries.value.some((e) => {
        const k = String(e.posting_key || '');
        return k === key || k.startsWith(`${key}:`);
    });
});

const documentIssueCount = computed(() =>
    diagnostics.value.filter((d) => d.level === 'error').length
);

const diagnosticIcon = (level) =>
    ({ error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' }[level] || 'fa-circle-info');

const isCancelled = computed(() => normalizeStatus(selectedOrder.value?.status) === 'cancelled');

/** Stage tracker carrying the date each stage actually happened. */
const timelineSteps = computed(() => {
    const status = normalizeStatus(selectedOrder.value?.status);
    const order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const reached = order.indexOf(status);
    // Named apart from the i18n `t`: shadowing it made every label below a
    // call on this object, and the execution tab threw as soon as it opened.
    const times = detail.value.timeline || {};

    return [
        { key: 'pending', label: t('sales_status_pending'), icon: 'fa-clock', at: times.order_date },
        { key: 'confirmed', label: t('sales_status_confirmed'), icon: 'fa-circle-check', at: times.confirmed_at },
        { key: 'processing', label: t('prepare'), icon: 'fa-gears', at: null },
        { key: 'shipped', label: t('shipping'), icon: 'fa-truck-fast', at: times.shipped_at },
        { key: 'delivered', label: t('deliver'), icon: 'fa-box-open', at: times.delivered_at },
    ].map((step, i) => ({
        ...step,
        done: reached >= i && reached !== -1,
        current: reached === i,
    }));
});

/** `shipping_address` is a JSON column on some rows and a plain string on others. */
const shippingAddressText = computed(() => {
    const value = selectedOrder.value?.shipping_address;
    if (!value) return '';
    if (typeof value === 'string') return value;
    return [value.line1, value.city, value.country].filter(Boolean).join(t('list_separator'));
});

const productName = (productId) =>
    selectedOrder.value?.items?.find((i) => i.product_id === productId)?.product?.name_ar
    || selectedOrder.value?.items?.find((i) => i.product_id === productId)?.product?.name
    || `#${productId}`;

/**
 * Opens the invoices screen on *this* order's invoice. It previously pushed the
 * bare list, leaving the user to find the row themselves — the link knew which
 * invoice it meant and threw that away.
 */
const goToInvoices = () => {
    detailDrawerVisible.value = false;
    router.push({
        path: '/admin/sales/invoices',
        query: invoice.value ? { invoice: invoice.value.invoice_number } : {},
    });
};

// Drawer Actions
const openDetailDrawer = async (id, tab = 'overview') => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    detailTab.value = tab;
    detail.value = {};
    try {
        // One request for the whole screen: the order, its documents and the
        // diagnosis of what does not line up.
        detail.value = await store.fetchDetail(id);
        selectedOrder.value = detail.value.sales_order;
        routing.value = detail.value.routing || routing.value;
        await loadSourcing(id);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_load_order_details_msg')));
    } finally {
        loadingDetail.value = false;
    }
};

/** Reloads the documents and diagnosis after a stage move changed them. */
const refreshDetail = async () => {
    if (!selectedOrder.value?.id) return;
    try {
        detail.value = await store.fetchDetail(selectedOrder.value.id);
        selectedOrder.value = detail.value.sales_order;
        routing.value = detail.value.routing || routing.value;
    } catch {
        /* the stage move already reported its own outcome */
    }
};

const openCreateDrawer = () => {
    router.push('/admin/sales/sales-orders/create');
};

const openEditDrawer = (id) => {
    router.push(`/admin/sales/sales-orders/${id}/edit`);
};

// Detail view actions

const deleteOrder = async (id) => {
    // Was a native confirm(), which is unstyled, not RTL-aware and blocks the tab.
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_sales_order'),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.deleteOrder(id);
        ElMessage.success(t('sales_order_deleted'));
        if (selectedOrder.value?.id === id) detailDrawerVisible.value = false;
        loadOrders();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_sales_order')));
    }
};

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.sales-orders.index' || query.open || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    loadOrders();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});

onMounted(async () => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    // The one-off `open` link is stripped from the URL below; the filters are not.
    const { open: _open, tab: _tab, do: _do, ...listQuery } = route.query;
    lastQueryKey = queryKey(listQuery);
    loadOrders();

    // A link to one order — the new-order wizard sends here after saving —
    // opens it, on the tab asked for.
    // With `do`, it also starts that step — how the customer-requests screens
    // hand a stage move to the one place that carries it out properly.
    const openId = Number(route.query.open);
    if (openId) {
        const tab = ['overview', 'execution', 'documents'].includes(String(route.query.tab)) ? String(route.query.tab) : 'overview';
        const target = route.query.do ? String(route.query.do) : null;
        router.replace({ query: { ...route.query, open: undefined, tab: undefined, do: undefined } });

        await openDetailDrawer(openId, target ? 'execution' : tab);
        if (target && STAGE_ACTIONS[target] && (routing.value.allowed_transitions || []).includes(target)) {
            await handleStageMove({ status: target, ...STAGE_ACTIONS[target] });
        }
    }
});
</script>

<style scoped>
/* ── List (cards, stages, filters, table) ── */
.so-stat { border-radius: 14px; transition: border-color 0.15s, box-shadow 0.15s; }
.so-stat.is-clickable { cursor: pointer; }
.so-stat.is-active { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
.so-stat-inner { display: flex; align-items: center; gap: 0.9rem; }
.so-stat-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}
.so-stat-icon.orange { background: #fff7ed; color: #ea580c; }
.so-stat-icon.blue { background: #eff6ff; color: #2563eb; }
.so-stat-icon.green { background: #f0fdf4; color: #16a34a; }
.so-stat-icon.red { background: #fef2f2; color: #dc2626; }
.so-stat-details { min-width: 0; flex: 1; }
.so-stat-details h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.so-stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }
.so-stat-sub { display: block; font-size: 0.74rem; color: #94a3b8; margin-top: 0.15rem; }

.so-attention {
    all: unset;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    box-sizing: border-box;
    margin-bottom: 1rem;
    padding: 0.6rem 0.9rem;
    border-radius: 10px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.88rem;
}
.so-attention:hover { filter: brightness(0.98); }
.so-attention:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.so-attention-cta { margin-inline-start: auto; font-weight: 700; text-decoration: underline; }

.so-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }

.so-stages { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.9rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
.so-stage {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    color: #475569;
}
.so-stage i { font-size: 0.78rem; opacity: 0.75; }
.so-stage:hover { background: #f1f5f9; }
.so-stage.is-on { background: #0f172a; color: #fff; }
.so-stage:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.so-stage-count { font-size: 0.72rem; font-weight: 700; background: rgba(100, 116, 139, 0.14); border-radius: 999px; padding: 0 0.45rem; min-width: 1.2rem; text-align: center; }
.so-stage.is-on .so-stage-count { background: rgba(255, 255, 255, 0.2); }

.so-filters { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; margin-bottom: 1rem; }
.so-filter-search { flex: 1 1 240px; max-width: 340px; }
.so-filter-select { width: 160px; }
.so-filter-dates { max-width: 260px; }
.so-attention-tag { display: inline-flex; align-items: center; gap: 0.35rem; }

.so-table :deep(.el-table__row) { cursor: pointer; }
.so-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.so-stack.so-end { align-items: flex-end; }
.so-sub { font-size: 0.78rem; color: #64748b; }
.so-mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; }
.so-strong { font-weight: 600; }
.so-amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.so-flag { color: #dc2626; margin-inline-start: 0.3rem; font-size: 0.8rem; }
.so-muted-icon { color: #94a3b8; font-size: 0.8rem; }

.so-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--c, #64748b);
    background: color-mix(in srgb, var(--c, #64748b) 11%, #fff);
}
.so-pill.s-pending { --c: #d97706; }
.so-pill.s-confirmed { --c: #2563eb; }
.so-pill.s-processing { --c: #7c3aed; }
.so-pill.s-shipped { --c: #0891b2; }
.so-pill.s-delivered { --c: #16a34a; }
.so-pill.s-cancelled { --c: #64748b; }

.so-pay { font-size: 0.78rem; font-weight: 700; }
.so-pay.p-paid { color: #16a34a; }
.so-pay.p-partial { color: #d97706; }
.so-pay.p-unpaid { color: #dc2626; }

.so-row-actions { display: inline-flex; align-items: center; gap: 0.25rem; }
:deep(.so-danger-item) { color: #dc2626; }

.so-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .so-stat :deep(.el-card__body) { padding: 0.75rem; }
    .so-stat-inner { gap: 0.6rem; }
    .so-stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .so-stat-details h3 { font-size: 1rem; }
    .so-panel { padding: 0.85rem; }
    .so-stages { flex-wrap: nowrap; overflow-x: auto; }
    .so-stage { white-space: nowrap; }
    .so-filter-search { max-width: none; flex-basis: 100%; }
    .so-filter-select { width: calc(50% - 0.35rem); }
    .so-filter-dates { max-width: none; width: 100% !important; }
    .so-pagination { justify-content: center; }
}

.next-stage-go { margin-top: 0.5rem; }
.drawer-purchase-btn { margin-inline-start: auto; }
.drawer-title .drawer-purchase-btn i { color: inherit; }

/* The size under a line's product name. */
.row-variant {
    margin: 0.2rem 0 0.1rem;
}

.sales-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: min(100%, 280px);
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-cards {
    margin-bottom: 2rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card-wrapper:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.stat-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.blue-grad {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-light) 100%);
}

.orange-grad {
    background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
}

.green-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 500;
}

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.order-number-link {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}

.order-number-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}

.customer-info-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.total-amount {
    color: var(--text-dark);
    font-size: 0.95rem;
}

.status-tag {
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot-icon {
    font-size: 0.8rem;
}

.action-btn-group .el-button {
    padding: 0.4rem 0.6rem;
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}

/* Detail Drawer Styles */
.drawer-detail-content {
    padding: 1.25rem 1.5rem 2rem;
    font-family: 'Cairo', sans-serif;
}

.drawer-title {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
    font-weight: 700;
    color: var(--text-dark);
}

.drawer-title i { color: var(--el-color-primary); }
.drawer-title strong { font-family: monospace; color: var(--el-color-primary); }

/* ---- Masthead: the four facts read before any detail ---- */
.order-masthead {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.masthead-cell {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

.masthead-cell .lbl {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
}

.masthead-cell strong {
    font-size: 1rem;
    color: var(--text-dark);
    overflow-wrap: anywhere;
}

.masthead-cell strong.amount { font-size: 1.2rem; font-weight: 800; }
.masthead-cell .status-tag { align-self: flex-start; }

/* ---- Diagnostics ---- */
.diagnostics-panel {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.diagnostic {
    display: flex;
    gap: 0.8rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid;
    /* A coloured edge on the reading side makes severity scannable in a stack. */
    border-inline-start-width: 4px;
}

.diagnostic.level-error { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.diagnostic.level-warning { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.diagnostic.level-info { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }

.diagnostic-icon { font-size: 1.05rem; margin-top: 0.15rem; flex-shrink: 0; }
.diagnostic-body strong { font-size: 0.92rem; display: block; }
.diagnostic-body p { margin: 0.3rem 0 0; font-size: 0.83rem; line-height: 1.7; }
.diagnostic-action { font-weight: 600; opacity: 0.9; }

.diagnostics-clear {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 1rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    font-size: 0.85rem;
}

.detail-tabs { margin-top: 0.25rem; }
.tab-badge { margin-inline-start: 0.35rem; }

/* ---- Items and amounts ---- */
.items-table .row-sub {
    margin: 0.15rem 0 0;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.amounts-block {
    margin-top: 1rem;
    margin-inline-start: auto;
    max-width: 380px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: hidden;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.6rem 0.9rem;
    font-size: 0.88rem;
    border-bottom: 1px solid var(--border-color);
}

.amount-row:last-child { border-bottom: none; }
.amount-row.grand { background: var(--bg-light); font-weight: 800; font-size: 1rem; }
.amount-row.paid { color: var(--el-color-success); }
.amount-row.due.unpaid { color: var(--el-color-danger); font-weight: 700; }
.amount-row.due.settled { color: var(--el-color-success); font-weight: 700; }

.info-card { border-radius: 12px; margin-bottom: 0.75rem; }
.info-list { display: flex; flex-direction: column; gap: 0.55rem; }
.info-item { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.86rem; }
.info-item .lbl { color: var(--text-muted); flex-shrink: 0; }
.info-item strong { text-align: end; overflow-wrap: anywhere; }
.card-title-txt { display: flex; align-items: center; gap: 0.45rem; font-weight: 700; }
.card-title-txt i { color: var(--el-color-primary); }
.notes-txt-view { margin: 0; font-size: 0.86rem; line-height: 1.8; color: var(--text-dark); }

/* ---- Stage timeline ---- */
.stage-timeline {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.stage-step {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
    text-align: center;
    min-width: 0;
}

.step-marker {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 2px solid var(--border-color);
    color: var(--text-muted);
    z-index: 1;
}

.stage-step.done .step-marker { background: var(--el-color-success); border-color: var(--el-color-success); color: #fff; }
.stage-step.current .step-marker { box-shadow: 0 0 0 4px color-mix(in srgb, var(--el-color-primary) 22%, transparent); }
.stage-step.skipped .step-marker { opacity: 0.45; }

.step-text { display: flex; flex-direction: column; gap: 0.1rem; }
.step-text strong { font-size: 0.8rem; }
.step-date { font-size: 0.7rem; color: var(--text-muted); }

/* The number carries the sequence; a tick replaces it only once the stage is
   behind you, so "how far along am I" stays readable at a glance. */
.step-number { font-weight: 800; font-size: 0.95rem; }
.step-check { font-size: 0.85rem; }

.step-now {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--el-color-primary);
    margin-top: 0.1rem;
}

/* ---- Next stage ---- */
.next-stage-bar {
    padding: 0.8rem 1rem;
    border: 1px solid var(--border-color);
    border-inline-start: 4px solid var(--el-color-primary);
    border-radius: 10px;
    background: color-mix(in srgb, var(--el-color-primary) 5%, transparent);
    margin-bottom: 1rem;
}

.next-stage-head { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.9rem; }

.next-badge {
    background: var(--el-color-primary);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    white-space: nowrap;
}

.next-stage-effect { margin: 0.4rem 0 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.7; }

/* ---- Settlement dialog ---- */
.settle-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 10px;
}

.settle-summary > div { display: flex; flex-direction: column; gap: 0.2rem; }
.settle-summary span { font-size: 0.72rem; color: var(--text-muted); }
.settle-summary strong { font-size: 0.95rem; }

.field-hint { margin: 0.3rem 0 0; font-size: 0.74rem; color: var(--text-muted); line-height: 1.6; }

/* Drawn between markers rather than behind them, so a filled segment reads as
   the transition that happened and not merely as a completed dot. */
.step-connector {
    position: absolute;
    top: 19px;
    inset-inline-start: calc(50% + 19px);
    width: calc(100% - 38px);
    height: 2px;
    background: var(--border-color);
}

.step-connector.filled { background: var(--el-color-success); }

/* ---- Documents ---- */
.doc-invoice { display: flex; flex-direction: column; gap: 0.75rem; }
.doc-line { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.doc-number { font-family: monospace; font-weight: 700; font-size: 1rem; }

.doc-figures {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
}

.doc-figures > div { display: flex; flex-direction: column; gap: 0.2rem; }
.doc-figures span { font-size: 0.75rem; color: var(--text-muted); }
.doc-figures strong { font-size: 1rem; }

.entry-list { display: flex; flex-direction: column; gap: 0.9rem; }

.entry {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
}

/* A reversed entry is still part of the record; it is dimmed, never hidden. */
.entry.reversed { opacity: 0.62; background: var(--bg-light); }

.entry-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.entry-head strong { font-family: monospace; }
.entry-date, .entry-desc { color: var(--text-muted); font-size: 0.78rem; }
.entry-lines { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.entry-lines td { padding: 0.3rem 0.4rem; border-top: 1px solid var(--border-color); }
.entry-lines td.num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.entry-lines td.acc-code { font-family: monospace; color: var(--text-muted); width: 60px; }

.muted { color: var(--text-muted); }

/* ---- Source selection ---- */
.sourcing-card { margin-top: 0.75rem; }
.sourcing-lines { display: flex; flex-direction: column; gap: 0.9rem; min-height: 50px; }

.sourcing-line {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
}

.sl-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
    font-size: 0.86rem;
}

.sl-sources { display: flex; flex-direction: column; gap: 0.45rem; }

.sl-source {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.35rem 0.5rem;
    background: var(--bg-light);
    border-radius: 8px;
}

.sl-wh { display: flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; min-width: 0; }

/* What the shelf can give sits beside the box that spends it, so the ceiling
   is visible while typing rather than discovered on save. */
.sl-avail { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; }

.text-danger { color: var(--el-color-danger); }

/* ---- Pipeline tabs and follow-up ---- */
.stage-tabs { margin-bottom: 0.5rem; }
.stage-tab-label { display: inline-flex; align-items: center; gap: 0.4rem; }
.stage-badge { margin-inline-start: 0.5rem; }

.purple-grad { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); }

.stat-card-wrapper.clickable { cursor: pointer; }
.stat-card-wrapper.attention-card { border: 1px solid #fca5a5; }

.follow-up-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.8rem;
}

.age-ok { color: var(--text-muted); }
.age-done { color: var(--el-color-success); }
.age-stalled { color: var(--el-color-warning); font-weight: 700; }
.age-overdue { color: var(--el-color-danger); font-weight: 700; }
.overdue-note { font-size: 0.72rem; color: var(--el-color-danger); }

.attention-flag { color: var(--el-color-danger); margin-inline-start: 0.4rem; }

/* A tinted row carries the warning even when the flag column is scrolled off. */
:deep(.row-needs-attention) { background: #fff7ed !important; }

.follow-up-bar {
    display: flex;
    gap: 0.8rem;
    align-items: flex-start;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    border: 1px solid;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}

.follow-up-bar p { margin: 0.2rem 0 0; font-size: 0.78rem; opacity: 0.85; }
.follow-up-bar.fu-ok { background: var(--bg-light); border-color: var(--border-color); color: var(--text-dark); }
.follow-up-bar.fu-done { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.follow-up-bar.fu-stalled { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.follow-up-bar.fu-overdue { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

/* ---- Stage history ---- */
.history-list { display: flex; flex-direction: column; }

.history-entry {
    display: flex;
    gap: 0.9rem;
    padding-bottom: 1rem;
    position: relative;
}

/* Connecting line between markers, stopping at the last entry. */
.history-entry:not(:last-child)::before {
    content: '';
    position: absolute;
    inset-inline-start: 5px;
    top: 14px;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
}

.history-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: 3px;
    flex-shrink: 0;
    background: var(--text-muted);
    z-index: 1;
}

.dot-pending { background: var(--el-color-warning); }
.dot-confirmed, .dot-processing, .dot-shipped { background: var(--el-color-primary); }
.dot-delivered { background: var(--el-color-success); }
.dot-cancelled { background: var(--el-color-danger); }

.history-body { flex: 1; min-width: 0; }
.history-head { display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; font-size: 0.86rem; }
.history-when { color: var(--text-muted); font-size: 0.76rem; white-space: nowrap; }
.history-meta { margin: 0.2rem 0 0; font-size: 0.75rem; color: var(--text-muted); }

.history-note {
    margin: 0.35rem 0 0;
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
    background: var(--bg-light);
    border-radius: 6px;
    border-inline-start: 3px solid var(--border-color);
}

.timeline-step-tracker {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.75rem 1.25rem;
    border-radius: var(--radius-md);
}

.visual-progress-timeline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.progress-base-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    right: 8%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    height: 4px;
    background: var(--success);
    z-index: 2;
    transition: width 0.4s ease;
}

.timeline-nodes-wrapper {
    display: flex;
    justify-content: space-around;
    width: 100%;
    z-index: 3;
}

.timeline-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.node-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--text-light);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
    transition: background 0.3s ease;
}

.timeline-node.completed .node-icon {
    background: var(--success);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
}

.timeline-node.completed span {
    color: var(--text-dark);
}

.card-title-txt {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.financial-summary-block {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.financial-row {
    display: flex;
    justify-content: space-between;
    width: 250px;
    color: var(--text-medium);
    font-size: 0.9rem;
}

.financial-row.grand-total {
    border-top: 2px solid var(--border-color);
    padding-top: 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--accent-blue);
}

.notes-txt-view {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-medium);
    line-height: 1.6;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.info-item .lbl {
    color: var(--text-muted);
}

.info-item strong {
    color: var(--text-dark);
}

.convert-card-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    text-align: center;
}

.convert-tip {
    font-size: 0.85rem;
    color: #065f46;
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.convert-btn-invoice {
    width: 100%;
    font-weight: 700;
}

/* ---- Order routing ---- */
.routing-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.85rem;
}

.routing-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.routing-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.routing-field .lbl {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
}

.routing-locked-note {
    margin: 0.6rem 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.routing-select-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-block-start: 0.4rem;
}

.routing-hint {
    margin: 0.35rem 0 0.7rem;
    font-size: 0.76rem;
    line-height: 1.7;
    color: var(--text-muted);
}

.warehouse-options {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-height: 60px;
}

.warehouse-option {
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    transition: border-color 0.2s ease, background 0.2s ease;
}

/* A chosen routing is one of the places this order may draw on. */
.warehouse-option.selected {
    border-color: color-mix(in srgb, var(--el-color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--el-color-primary) 4%, transparent);
}

/* The warehouse actually serving the order has to be findable at a glance, and
   outranks the plain "selected" tint when it is both. */
.warehouse-option.current {
    border-color: var(--el-color-primary);
    background: color-mix(in srgb, var(--el-color-primary) 6%, transparent);
}

.warehouse-option.short {
    border-inline-start: 3px solid var(--el-color-warning);
}

.wh-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.45rem;
    gap: 0.5rem;
}

.wh-name {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9rem;
}

.wh-type {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.wh-coverage {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.4rem;
    font-size: 0.78rem;
    gap: 0.5rem;
}

.shortfall-list {
    margin: 0.5rem 0 0;
    padding-inline-start: 1.1rem;
    font-size: 0.76rem;
    color: var(--el-color-warning-dark-2, #b88230);
}

.shortfall-list .muted {
    color: var(--text-muted);
}

/* ---- Execution stages ---- */
.stage-explainer {
    margin: 0 0 0.9rem;
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.7;
}

.stage-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Element stacks buttons horizontally and strips the margin off siblings;
   these are a vertical list, so that reset has to be undone. */
.stage-actions .stage-btn {
    width: 100%;
    margin: 0;
    font-weight: 600;
}

.dialog-lead {
    margin: 0 0 1rem;
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.7;
}

.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }

/* Form Grid row */
.item-grid-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}
</style>
