<template>
    <div class="purchases-page purchases-orders">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-file-signature text-primary"
            :title="$t('purchase_orders')"
            :subtitle="$t('follow_current_orders_and_use')"
        >
            <template #actions>
                <!-- Searching hits the API, so an order on any page is found. -->
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('po_search_placeholder')"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                    @input="onSearchInput"
                    @keyup.enter="loadOrders(1)"
                    @clear="loadOrders(1)"
                />
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('new_purchase_order') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Metrics cards. Counted by the API over the whole table, not over
             the twenty rows that happen to be loaded. The two middle cards are
             the work waiting to be done, so they double as filters. -->
        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box blue-grad">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ counts.all }}</h3>
                        <p>{{ $t('total_orders') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card
                shadow="hover"
                class="stat-card-wrapper"
                :class="{ 'attention-card': counts.pending > 0, clickable: counts.pending > 0 }"
                @click="counts.pending > 0 && goToStage('pending')"
            >
                <div class="stat-card-inner">
                    <div class="stat-icon-box orange-grad">
                        <i class="fas fa-stamp"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ counts.pending }}</h3>
                        <p>{{ $t('awaiting_approval') }}</p>
                        <small v-if="counts.pending > 0" class="stat-cta">{{ $t('review_and_approve_them') }}</small>
                    </div>
                </div>
            </el-card>
            <el-card
                shadow="hover"
                class="stat-card-wrapper"
                :class="{ clickable: awaitingReceiptCount > 0 }"
                @click="awaitingReceiptCount > 0 && goToStage('confirmed')"
            >
                <div class="stat-card-inner">
                    <div class="stat-icon-box purple-grad">
                        <i class="fas fa-truck-ramp-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ awaitingReceiptCount }}</h3>
                        <p>{{ $t('awaiting_goods_receipt') }}</p>
                        <small v-if="awaitingReceiptCount > 0" class="stat-cta">{{ $t('record_their_receipts') }}</small>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box green-grad">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ counts.completed }}</h3>
                        <p>{{ $t('received_orders') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- The workflow as a filter: each tab is a stage, badged with how many
             orders are sitting in it across the whole table. -->
        <el-tabs v-model="activeStage" class="stage-tabs" @tab-change="onStageChange">
            <el-tab-pane v-for="tab in stageTabs" :key="tab.name" :name="tab.name">
                <template #label>
                    <span class="stage-tab-label">
                        <i class="fas" :class="tab.icon"></i> {{ tab.label }}
                        <el-badge v-if="tab.count" :value="tab.count" :type="tab.badge" class="stage-badge" />
                    </span>
                </template>
            </el-tab-pane>
        </el-tabs>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('purchase_order_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table
                    v-if="store.orders.length"
                    :data="store.orders"
                    style="width: 100%"
                    stripe
                    highlight-current-row
                    class="custom-table"
                    :row-class-name="rowClassName"
                >
                    <el-table-column prop="order_number" :label="$t('order_number')" width="150">
                        <template #default="{ row }">
                            <span class="order-number-link" @click="openDetailDrawer(row.id)">{{ row.order_number }}</span>
                            <small class="cell-sub">{{ formatDate(row.order_date || row.created_at) }}</small>
                            <!-- The sale it buys in for, when there is one. -->
                            <small v-if="saleLinkOf(row)" class="cell-sub sale-ref" :title="saleLinkOf(row).customer">
                                <i class="fas" :class="saleLinkOf(row).type === 'invoice' ? 'fa-file-invoice-dollar' : 'fa-cart-shopping'"></i>
                                {{ saleLinkOf(row).number }}
                            </small>
                        </template>
                    </el-table-column>
                    <el-table-column prop="supplier.name" :label="$t('supplier')" min-width="200">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-user-tie text-muted"></i>
                                <div>
                                    <span>{{ row.supplier?.name || '-' }}</span>
                                    <!-- What was ordered, at a glance, so telling two
                                         orders to the same supplier apart does not
                                         mean opening both. -->
                                    <small v-if="row.items?.length" class="cell-sub" :title="itemsPreview(row)">
                                        {{ $t('po_items_count', row.items.length) }} · {{ itemsPreview(row) }}
                                    </small>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total" :label="$t('total')" width="160">
                        <template #default="{ row }">
                            <strong class="amount-txt">{{ money(row.total) }}</strong>
                            <div v-if="num(row.discount) > 0" class="discount-table-tag" :title="`${$t('discount')}: ${money(row.discount)}`">
                                <i class="fas fa-tag"></i>
                                <span>-{{ money(row.discount) }}</span>
                                <small v-if="row.discount_percent != null && num(row.discount_percent) > 0">({{ row.discount_percent }}%)</small>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="150" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <!-- A due date only matters while the goods are still
                         outstanding, so lateness is flagged on open orders alone. -->
                    <el-table-column prop="due_date" :label="$t('due_date')" width="150" align="center">
                        <template #default="{ row }">
                            <span v-if="!row.due_date" class="text-muted">-</span>
                            <template v-else>
                                <span :class="{ 'due-late': dueState(row) === 'overdue' }">{{ formatDate(row.due_date) }}</span>
                                <el-tag v-if="dueState(row) === 'overdue'" type="danger" size="small" effect="plain" class="due-tag">
                                    {{ $t('po_overdue') }}
                                </el-tag>
                                <el-tag v-else-if="dueState(row) === 'today'" type="warning" size="small" effect="plain" class="due-tag">
                                    {{ $t('po_due_today') }}
                                </el-tag>
                            </template>
                        </template>
                    </el-table-column>
                    
                    <!-- Actions.
                         One labelled button for the step this order is actually
                         waiting on — approve it, or receive its goods — with
                         everything else folded behind a menu. The row used to
                         show three unlabelled icons and, sometimes, a fourth
                         green button, which left "what do I do with this order"
                         to be worked out from the status tag; approving was not
                         among them at all. -->
                    <el-table-column :label="$t('actions')" width="250" align="center">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-button
                                    v-if="nextStep(row).action"
                                    size="small"
                                    :type="nextStep(row).type"
                                    :loading="busyOrderId === row.id"
                                    class="next-step-btn"
                                    @click="nextStep(row).action(row)"
                                >
                                    <i class="fas" :class="nextStep(row).icon"></i> {{ nextStep(row).label }}
                                </el-button>
                                <el-tag
                                    v-else
                                    :type="nextStep(row).type"
                                    effect="plain"
                                    size="small"
                                    class="next-step-done"
                                >
                                    <i class="fas" :class="nextStep(row).icon"></i> {{ nextStep(row).label }}
                                </el-tag>

                                <el-dropdown trigger="click" @command="(cmd) => onRowCommand(cmd, row)">
                                    <el-button size="small" plain class="more-btn" :title="$t('more_actions')">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="view">
                                                <i class="fas fa-eye"></i> {{ $t('view_details') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item command="print">
                                                <i class="fas fa-print"></i> {{ $t('print') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item command="edit" :disabled="!canEdit(row)">
                                                <i class="fas fa-edit"></i> {{ $t('edit') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item command="duplicate">
                                                <i class="fas fa-copy"></i> {{ $t('po_duplicate_order') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="isApproved(row)"
                                                command="reopen"
                                                divided
                                            >
                                                <i class="fas fa-rotate-left"></i> {{ $t('return_to_pending') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="canCancel(row)"
                                                command="cancel"
                                                :divided="!isApproved(row)"
                                            >
                                                <i class="fas fa-ban"></i> {{ $t('cancel_order') }}
                                            </el-dropdown-item>
                                            <!-- A received order's stock movement
                                                 and journal entry point back at
                                                 it; deleting it leaves both
                                                 referring to nothing. -->
                                            <el-dropdown-item command="delete" divided :disabled="!canDelete(row)">
                                                <i class="fas fa-trash"></i> {{ $t('delete') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.orders.length" class="empty-state-box">
                    <i class="fas fa-file-signature empty-icon"></i>
                    <p>{{ $t('there_are_no_requests_matching') }}</p>
                    <el-button v-if="isFiltered" @click="clearFilters">
                        <i class="fas fa-rotate-left"></i> {{ $t('show_all_orders') }}
                    </el-button>
                    <el-button v-else type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> {{ $t('create_first_purchase_order') }}
                    </el-button>
                </div>

                <!-- Paging is server-side: the list used to stop at the newest
                     twenty orders with no way to reach the ones behind them. -->
                <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-row">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="store.pagination.total"
                        :current-page="store.pagination.current_page"
                        :page-size="store.pagination.per_page"
                        background
                        @current-change="onPageChange"
                    />
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            :title="$t('purchase_order_details')"
            :size="drawerSize"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedOrder" class="drawer-detail-content">
                <div class="drawer-order-head mb-3">
                    <div>
                        <h3>{{ selectedOrder.order_number }}</h3>
                        <el-tag :type="statusTagType(selectedOrder.status)" effect="light" class="status-tag">
                            <i class="fas status-dot-icon" :class="statusIconClass(selectedOrder.status)"></i>
                            {{ getArabicStatus(selectedOrder.status) }}
                        </el-tag>
                    </div>
                    <div class="drawer-head-actions">
                        <el-button type="primary" plain @click="printOrder(selectedOrder)">
                            <i class="fas fa-print"></i> {{ $t('print') }}
                        </el-button>
                        <el-button plain @click="duplicateFromDrawer">
                            <i class="fas fa-copy"></i> {{ $t('po_duplicate_order') }}
                        </el-button>
                        <el-button v-if="canEdit(selectedOrder)" plain @click="editFromDrawer">
                            <i class="fas fa-edit"></i> {{ $t('edit') }}
                        </el-button>
                    </div>
                </div>

                <div v-if="saleLinkOf(selectedOrder)" class="mb-3">
                    <PurchaseSaleLink :model-value="saleLinkOf(selectedOrder)" disabled />
                </div>

                <!-- A cancelled order has no position on the track; drawing it
                     at "created" read as though it were still waiting. -->
                <el-alert
                    v-if="isCancelled(selectedOrder)"
                    type="info"
                    :title="$t('po_cancelled_banner')"
                    :closable="false"
                    show-icon
                    class="mb-4"
                />
                <div v-else class="timeline-step-tracker mb-4">
                    <div class="visual-progress-timeline">
                        <div class="progress-base-bar"></div>
                        <!-- Anchored to the inline start and scaled to the track
                             between the first and last node: it used to be pinned
                             to the left, so in Arabic it filled from the end. -->
                        <div class="progress-fill-bar" :style="{ width: timelineFillWidth }"></div>

                        <div class="timeline-nodes-wrapper">
                            <div
                                v-for="(step, i) in timelineSteps"
                                :key="step.key"
                                class="timeline-node"
                                :class="{ completed: i <= timelineIndex, current: i === timelineIndex }"
                            >
                                <div class="node-icon"><i class="fas" :class="step.icon"></i></div>
                                <span>{{ step.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: items table & billing -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-boxes text-muted mr-1"></i> {{ $t('items_requested_for_purchase') }}</span>
                            </template>
                            <el-table :data="selectedOrder.items || []" style="width: 100%" stripe>
                                <el-table-column :label="$t('item_product')" min-width="160">
                                    <template #default="{ row }">
                                        <!-- The stored name survives the product being
                                             deleted from the catalogue. -->
                                        <span>{{ row.product?.name_ar || row.product_name || '-' }}</span>
                                        <div v-if="row.product_variant_id" class="cell-variant">
                                            <VariantChip :label="variantLabelOf(row.variant) || row.product_name" />
                                        </div>
                                        <small v-if="row.variant?.sku || row.product?.sku" class="cell-sub">{{ $t('po_sku_label', { sku: row.variant?.sku || row.product.sku }) }}</small>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="showReceived ? $t('po_ordered') : $t('quantity_ordered')" width="100" align="center">
                                    <template #default="{ row }">{{ row.quantity }}</template>
                                </el-table-column>
                                <!-- What actually arrived, beside what was asked
                                     for, so a short delivery shows up here instead
                                     of only in the receipt. -->
                                <el-table-column v-if="showReceived" :label="$t('po_received')" width="110" align="center">
                                    <template #default="{ row }">
                                        <span :class="{ 'qty-short': (row.received_quantity || 0) < row.quantity }">{{ row.received_quantity || 0 }}</span>
                                        <small v-if="(row.received_quantity || 0) < row.quantity" class="cell-sub qty-short">
                                            {{ $t('po_receipt_short', { count: row.quantity - (row.received_quantity || 0) }) }}
                                        </small>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('purchase_cost')" width="120">
                                    <template #default="{ row }">{{ money(row.unit_price) }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('sale_price')" width="120">
                                    <template #default="{ row }">{{ row.sale_price != null ? money(row.sale_price) : '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('po_line_total')" width="130">
                                    <template #default="{ row }">{{ money(row.quantity * row.unit_price) }}</template>
                                </el-table-column>
                            </el-table>

                            <div class="financial-summary-block mt-4">
                                <div class="financial-row">
                                    <span>{{ $t('subtotal') }}</span>
                                    <span>{{ money(selectedOrder.subtotal ?? detailSubtotal) }}</span>
                                </div>
                                <div class="financial-row">
                                    <span>{{ $t('discount_label') }}</span>
                                    <span class="discount-figure" :class="{ 'has-discount': num(selectedOrder.discount) > 0 }">
                                        <template v-if="num(selectedOrder.discount) > 0">
                                            − {{ money(selectedOrder.discount) }}
                                            <span v-if="selectedOrder.discount_percent != null && num(selectedOrder.discount_percent) > 0" class="discount-badge-pill">
                                                {{ selectedOrder.discount_percent }}%
                                            </span>
                                        </template>
                                        <template v-else>
                                            {{ money(0) }}
                                        </template>
                                    </span>
                                </div>
                                <div class="financial-row">
                                    <span>{{ $t('tax_label') }}</span>
                                    <span>{{ money(selectedOrder.tax) }}</span>
                                </div>
                                <div class="financial-row grand-total">
                                    <span>{{ $t('grand_total_label') }}</span>
                                    <span>{{ money(selectedOrder.total) }}</span>
                                </div>
                            </div>
                        </el-card>

                        <!-- Notes card -->
                        <el-card v-if="selectedOrder.notes" shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> {{ $t('notes') }}</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedOrder.notes }}</p>
                        </el-card>
                    </el-col>

                    <!-- Right: the next step, then who and when -->
                    <el-col :xs="24" :lg="8">
                        <!-- What this order is waiting on, and the button that
                             does it. The drawer previously only ever offered the
                             receive step, so an order still awaiting approval
                             showed nothing to act on and had to be approved
                             through the edit form. -->
                        <el-card shadow="never" class="next-step-card mb-3" :class="`next-step-${drawerStep.tone}`">
                            <div class="next-step-head">
                                <i class="fas next-step-glyph" :class="drawerStep.icon"></i>
                                <div>
                                    <strong>{{ drawerStep.title }}</strong>
                                    <p>{{ drawerStep.hint }}</p>
                                </div>
                            </div>
                            <el-button
                                v-if="drawerStep.action"
                                :type="drawerStep.type"
                                class="next-step-cta"
                                :loading="busyOrderId === selectedOrder.id"
                                @click="drawerStep.action(selectedOrder)"
                            >
                                <i class="fas" :class="drawerStep.actionIcon"></i> {{ drawerStep.actionLabel }}
                            </el-button>
                            <!-- Approving is the point of no return for the
                                 lines, so the way back out sits next to it. -->
                            <el-button
                                v-if="canCancel(selectedOrder)"
                                text
                                class="next-step-secondary"
                                @click="cancelOrder(selectedOrder)"
                            >
                                <i class="fas fa-ban"></i> {{ $t('cancel_order') }}
                            </el-button>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-tie text-muted mr-1"></i> {{ $t('supplier_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('supplier_name_label') }}</span>
                                    <strong>{{ selectedOrder.supplier?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.supplier?.company">
                                    <span class="lbl">{{ $t('company_label') }}</span>
                                    <strong>{{ selectedOrder.supplier.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.supplier?.phone">
                                    <span class="lbl">{{ $t('phone_label') }}</span>
                                    <strong>{{ selectedOrder.supplier.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-calendar-alt text-muted mr-1"></i> {{ $t('key_dates') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('order_date_label') }}</span>
                                    <strong>{{ formatDate(selectedOrder.order_date || selectedOrder.created_at) }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('due_date_label') }}</span>
                                    <strong :class="{ 'due-late': dueState(selectedOrder) === 'overdue' }">{{ formatDate(selectedOrder.due_date) }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <!-- The receipts that booked this order's goods in, so the
                             trail from order to stock is one click either way. -->
                        <el-card v-if="!isCancelled(selectedOrder)" shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-receipt text-muted mr-1"></i> {{ $t('po_linked_receipts') }}</span>
                            </template>
                            <div v-if="selectedOrder.receipts?.length" class="info-list">
                                <div v-for="r in selectedOrder.receipts" :key="r.id" class="info-item">
                                    <router-link :to="{ path: '/admin/purchases/receipts', query: { search: r.receipt_number } }" class="order-number-link">
                                        {{ r.receipt_number }}
                                    </router-link>
                                    <strong>{{ formatDate(r.receipt_date || r.created_at) }}</strong>
                                </div>
                            </div>
                            <p v-else class="notes-txt-view text-muted">{{ $t('po_no_receipts_yet') }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? $t('edit_purchase_order') : $t('create_purchase_order')"
            :size="drawerSize"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
            :before-close="confirmCloseForm"
            @opened="focusFirstField"
        >
            <!-- Ctrl+Enter saves from anywhere in the form, with the drawer's
                 main action (save and approve, for a new request). -->
            <div v-loading="loadingForm" @keydown.ctrl.enter.prevent="savePrimary" @keydown.meta.enter.prevent="savePrimary">
            <el-form :model="form" label-position="top">
                <!-- A received or cancelled order keeps its lines as they were
                     booked; the API only takes its dates and notes now, so the
                     form stops offering the rest. -->
                <el-alert
                    v-if="formLocked"
                    type="info"
                    :closable="false"
                    show-icon
                    class="mb-3"
                    :title="$t('po_edit_locked_hint', { status: getArabicStatus(editingStatus) })"
                />
                <p v-else-if="!isEditMode" class="quick-add-hint">{{ $t('po_new_order_hint') }}</p>

                <el-row :gutter="20">
                    <el-col :span="24">
                        <el-form-item :label="$t('supplier')" required>
                            <div style="display: flex; gap: 0.5rem; width: 100%;">
                                <el-select ref="supplierSelectRef" v-model="form.supplier_id" :placeholder="$t('select_supplier')" style="flex: 1;" filterable :disabled="formLocked">
                                    <el-option
                                        v-for="s in suppliersStore.suppliers"
                                        :key="s.id"
                                        :label="s.company ? `${s.name} — ${s.company}` : s.name"
                                        :value="s.id"
                                    />
                                </el-select>
                                <el-button
                                    v-if="!formLocked"
                                    type="success"
                                    circle
                                    plain
                                    @click="openQuickAddSupplier"
                                    :title="$t('add_new_supplier')"
                                >
                                    <i class="fas fa-plus"></i>
                                </el-button>
                            </div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- The sale this buys in for. Picking one links it and fills
                     the lines from it; a received order can still be relinked. -->
                <el-form-item :label="$t('po_for_sale')" class="sale-link-item">
                    <PurchaseSaleLink v-model="form.sale_link" @pick="fillFromSale" />
                    <small v-if="!form.sale_link && !formLocked" class="field-hint">{{ $t('po_for_sale_hint') }}</small>
                </el-form-item>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('purchase_order_date')">
                            <el-date-picker v-model="form.order_date" type="date" :placeholder="$t('po_order_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('due_date')">
                            <!-- Goods cannot be due before they were ordered; the
                                 API refuses it, so the picker does not offer it. -->
                            <el-date-picker v-model="form.due_date" type="date" :placeholder="$t('due_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" :disabled-date="isBeforeOrderDate" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Dynamic items grid -->
                <div v-if="!formLocked" class="form-section">
                    <div class="form-section-head">
                        <h3><i class="fas fa-boxes text-primary"></i> {{ $t('items_and_quantities') }}</h3>
                        <el-button type="primary" size="small" plain @click="addItemRow">
                            <i class="fas fa-plus"></i> {{ $t('add_item') }}
                        </el-button>
                    </div>

                    <!-- Fast Suggested Product Search & Multi-Add Bar (Stays Open!) -->
                    <div ref="quickSearchContainerRef" class="po-suggested-search-panel" @mousedown.stop>
                        <div class="search-bar-header">
                            <div class="search-input-box">
                                <el-input
                                    ref="quickSearchInputRef"
                                    v-model="quickSearchQuery"
                                    :placeholder="$t('po_quick_search_placeholder')"
                                    clearable
                                    class="po-search-input"
                                    @focus="onQuickSearchFocus"
                                    @input="onQuickSearchInput"
                                    @clear="onQuickSearchClear"
                                    @keydown.esc="quickSearchOpen = false"
                                >
                                    <template #prefix>
                                        <i v-if="!quickSearchLoading" class="fas fa-search search-icon"></i>
                                        <i v-else class="fas fa-spinner fa-spin search-icon text-primary"></i>
                                    </template>
                                </el-input>
                            </div>

                            <button
                                type="button"
                                class="btn-toggle-suggestions"
                                :class="{ 'is-active': quickSearchOpen }"
                                @click.stop.prevent="quickSearchOpen = !quickSearchOpen"
                                :title="quickSearchOpen ? $t('po_close_search') : $t('po_suggested_products')"
                            >
                                <i class="fas" :class="quickSearchOpen ? 'fa-chevron-up' : 'fa-list-check'"></i>
                                <span>{{ quickSearchOpen ? $t('po_close_search') : $t('po_suggested_products') }}</span>
                                <span v-if="quickSearchResults.length" class="count-badge">{{ quickSearchResults.length }}</span>
                            </button>
                        </div>

                        <!-- Suggested Search Results Dropdown Panel (Stays open while adding!) -->
                        <transition name="el-zoom-in-top">
                            <div
                                v-if="quickSearchOpen"
                                class="suggested-search-dropdown"
                                @click.stop
                            >
                                <!-- Dropdown Banner & Tips -->
                                <div class="dropdown-top-strip">
                                    <div class="strip-left">
                                        <i class="fas fa-boxes-stacked text-primary"></i>
                                        <strong>{{ $t('po_suggested_products') }}</strong>
                                        <span class="results-badge">{{ quickSearchResults.length }}</span>
                                    </div>
                                    <div class="strip-right">
                                        <span class="keep-open-pill">
                                            <i class="fas fa-thumbtack text-success"></i>
                                            {{ $t('po_search_hint') }}
                                        </span>
                                        <button
                                            type="button"
                                            class="close-dropdown-btn"
                                            @click.stop="quickSearchOpen = false"
                                            :title="$t('close')"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- List of Suggested Products with Images & Add Button -->
                                <div class="suggested-list-scroll">
                                    <div v-if="quickSearchLoading" class="suggested-loading-state">
                                        <i class="fas fa-circle-notch fa-spin text-primary"></i>
                                        <span>{{ $t('loading') }}...</span>
                                    </div>

                                    <div v-else-if="!quickSearchResults.length" class="suggested-empty-state">
                                        <i class="fas fa-search-minus empty-icon"></i>
                                        <p>{{ $t('po_no_products_found') }}</p>
                                        <el-button size="small" type="primary" plain @click="openQuickAddProduct(form.items.length - 1)">
                                            <i class="fas fa-plus"></i> {{ $t('add_new_product') }}
                                        </el-button>
                                    </div>

                                    <div v-else class="suggested-products-grid">
                                        <div
                                            v-for="p in quickSearchResults"
                                            :key="optionKey(p)"
                                            class="suggested-item-card"
                                            :class="{ 'item-in-order': getOrderItemCount(p) > 0 }"
                                        >
                                            <!-- Product Thumbnail with fallback and preview -->
                                            <div class="item-card-image">
                                                <EntityImage
                                                    :src="productImageSrc(p)"
                                                    type="product"
                                                    :size="52"
                                                    shape="square"
                                                    class="card-img"
                                                />
                                                <span v-if="getOrderItemCount(p) > 0" class="in-order-tag" :title="$t('po_in_order')">
                                                    {{ getOrderItemCount(p) }}
                                                </span>
                                            </div>

                                            <!-- Product Information -->
                                            <div class="item-card-body">
                                                <div class="item-card-header">
                                                    <span class="item-name" :title="baseName(p)">
                                                        {{ baseName(p) }}
                                                    </span>
                                                    <VariantChip v-if="p.variant_id" :label="p.variant_label" />
                                                </div>

                                                <div class="item-card-tags">
                                                    <span v-if="p.sku" class="sku-chip">
                                                        <i class="fas fa-barcode"></i> {{ p.sku }}
                                                    </span>
                                                    <span v-if="p.category?.name_ar || p.category_name" class="cat-chip">
                                                        {{ p.category?.name_ar || p.category_name }}
                                                    </span>
                                                </div>

                                                <div class="item-card-financials">
                                                    <div class="cost-stat">
                                                        <span class="stat-lbl">{{ $t('purchase_cost') }}:</span>
                                                        <strong class="stat-val text-primary">{{ money(p.cost_price || p.price) }}</strong>
                                                    </div>
                                                    <div v-if="p.price && p.price !== p.cost_price" class="sale-stat">
                                                        <span class="stat-lbl">{{ $t('sale_price') }}:</span>
                                                        <span class="stat-val text-muted">{{ money(p.price) }}</span>
                                                    </div>
                                                    <div
                                                        v-if="p.stock_quantity != null || p.stock != null"
                                                        class="stock-stat"
                                                        :class="(p.stock_quantity || p.stock || 0) > 0 ? 'is-in-stock' : 'is-out-stock'"
                                                    >
                                                        <span class="stat-lbl">{{ $t('available') }}:</span>
                                                        <span class="stat-val">{{ p.stock_quantity ?? p.stock ?? 0 }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action Button / Quantity Controls (Stays Open!) -->
                                            <div class="item-card-action">
                                                <template v-if="getOrderItemCount(p) === 0">
                                                    <button
                                                        type="button"
                                                        class="btn-add-product"
                                                        @click.stop.prevent="addProductToOrder(p, 1)"
                                                    >
                                                        <i class="fas fa-cart-plus"></i>
                                                        <span>{{ $t('po_add_to_order') }}</span>
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <div class="item-stepper" @click.stop>
                                                        <button
                                                            type="button"
                                                            class="stepper-btn stepper-minus"
                                                            @click.stop.prevent="addProductToOrder(p, -1)"
                                                            :title="$t('decrease')"
                                                        >
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <span class="stepper-val">{{ getOrderItemCount(p) }}</span>
                                                        <button
                                                            type="button"
                                                            class="stepper-btn stepper-plus"
                                                            @click.stop.prevent="addProductToOrder(p, 1)"
                                                            :title="$t('increase')"
                                                        >
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dropdown Footer -->
                                <div class="dropdown-footer-strip">
                                    <div class="footer-summary">
                                        <span>
                                            {{ $t('po_items_in_order_summary', filledItemCount) }} &bull;
                                            <strong>{{ money(formSubtotal) }}</strong>
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn-done-search"
                                        @click.stop="quickSearchOpen = false"
                                    >
                                        <i class="fas fa-check"></i> {{ $t('po_close_search') }}
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <div class="items-grid-wrapper">
                        <div
                            v-for="(item, idx) in form.items"
                            :key="item.key"
                            class="item-grid-row"
                            :class="{ 'item-duplicate': duplicatePicks.has(item.pick) }"
                        >
                            <div class="item-row-top">
                                <span class="item-index">{{ idx + 1 }}</span>
                                <!-- Searches the whole catalog on the server instead of
                                     filtering only the first page already in memory, so
                                     a product outside that page is still found. -->
                                <el-select
                                    :ref="(el) => setProductSelectRef(item.key, el)"
                                    v-model="item.pick"
                                    :placeholder="$t('po_select_item_or_variant')"
                                    filterable
                                    remote
                                    reserve-keyword
                                    :remote-method="searchProducts"
                                    :loading="productSearchLoading"
                                    style="flex: 2.5; min-width: 0;"
                                    @change="(val) => updateItemPrice(val, idx)"
                                >
                                    <!-- Labelled by name and code, with the cost the
                                         line will default to: this is a purchase, so
                                         the retail price it used to show was the
                                         wrong figure to choose by. -->
                                    <!-- A product with sizes lists each size as its own
                                         row, so the request says which one is wanted. -->
                                    <el-option
                                        v-for="p in productOptions"
                                        :key="optionKey(p)"
                                        :label="productLabel(p)"
                                        :value="optionKey(p)"
                                    >
                                        <div class="product-option-enhanced">
                                            <EntityImage
                                                :src="productImageSrc(p)"
                                                type="product"
                                                :size="36"
                                                shape="square"
                                                class="option-thumb"
                                            />
                                            <div class="option-details">
                                                <div class="option-title-line">
                                                    <span class="product-option-name">{{ baseName(p) }}</span>
                                                    <VariantChip v-if="p.variant_id" :label="p.variant_label" />
                                                </div>
                                                <div class="option-meta-line">
                                                    <span v-if="p.sku" class="sku-tag"><i class="fas fa-barcode"></i> {{ p.sku }}</span>
                                                    <span class="cost-tag">{{ $t('po_cost_label', { price: money(p.cost_price || p.price) }) }}</span>
                                                    <span v-if="p.stock_quantity != null" class="stock-tag" :class="(p.stock_quantity || 0) > 0 ? 'is-in' : 'is-zero'">
                                                        {{ $t('available') }}: {{ p.stock_quantity }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </el-option>
                                </el-select>
                                <!-- Enter on the last line's quantity starts the next line, so a
                                     long request can be typed without the mouse. -->
                                <el-input-number
                                    v-model="item.quantity"
                                    :min="1"
                                    :placeholder="$t('quantity')"
                                    style="flex: 1; min-width: 120px;"
                                    @keyup.enter.exact="idx === form.items.length - 1 && addItemRow()"
                                />
                                <el-button
                                    type="success"
                                    circle
                                    plain
                                    @click="openQuickAddProduct(idx)"
                                    :title="$t('add_new_product')"
                                >
                                    <i class="fas fa-plus"></i>
                                </el-button>
                                <el-button type="danger" circle plain @click="removeItemRow(idx)" :disabled="form.items.length <= 1">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </div>
                            <!-- Cost is what the supplier is paid; sale price is what
                                 the order plans to retail the line at. Selecting a
                                 product fills both from its current figures so
                                 editing one and leaving the other alone is enough. -->
                            <div class="item-row-prices">
                                <div class="price-field">
                                    <label>{{ $t('purchase_cost') }}</label>
                                    <el-input v-model="item.unit_price" type="number" min="0" step="0.01" placeholder="0.00" />
                                </div>
                                <div class="price-field">
                                    <label>{{ $t('sale_price') }}</label>
                                    <el-input v-model="item.sale_price" type="number" min="0" step="0.01" placeholder="0.00" />
                                </div>
                                <div class="price-field line-total-field">
                                    <label>{{ $t('po_line_total') }}</label>
                                    <strong>{{ money(lineTotal(item)) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row v-if="!formLocked" :gutter="20" class="mt-3 financial-inputs-row">
                    <!-- Discount Field with Mode Switcher (% vs Currency), Quick Presets, and Live Preview -->
                    <el-col :xs="24" :sm="12">
                        <el-form-item :error="discountTooLarge ? $t('po_discount_exceeds_total') : ''" class="financial-form-item discount-form-item">
                            <template #label>
                                <div class="form-label-with-mode">
                                    <span class="label-text">
                                        <i class="fas fa-tag label-icon text-primary"></i>
                                        {{ $t('discount') }}
                                    </span>
                                    <div class="discount-mode-toggle" role="radiogroup" :title="$t('discount')">
                                        <button
                                            type="button"
                                            class="mode-btn"
                                            :class="{ 'is-active': form.discount_mode === 'percent' }"
                                            @click.prevent="setDiscountMode('percent')"
                                        >
                                            %
                                        </button>
                                        <button
                                            type="button"
                                            class="mode-btn"
                                            :class="{ 'is-active': form.discount_mode === 'amount' }"
                                            @click.prevent="setDiscountMode('amount')"
                                        >
                                            {{ currencyCode }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div class="discount-field-container">
                                <!-- Percentage input mode (default & primary) -->
                                <el-input
                                    v-if="form.discount_mode === 'percent'"
                                    v-model="form.discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="any"
                                    :placeholder="$t('discount_percent')"
                                    class="financial-input"
                                    @input="updateDiscountFromPercent"
                                >
                                    <template #append>%</template>
                                </el-input>

                                <!-- Direct amount input mode -->
                                <el-input
                                    v-else
                                    v-model="form.discount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :placeholder="$t('discount_amount_placeholder')"
                                    class="financial-input"
                                    @input="updateDiscountPercentFromAmount"
                                >
                                    <template #append>{{ currencyCode }}</template>
                                </el-input>

                                <!-- Quick Presets & Live Calculated Preview -->
                                <div class="discount-helper-row">
                                    <div v-if="form.discount_mode === 'percent'" class="discount-preset-chips">
                                        <button
                                            v-for="p in DISCOUNT_PRESETS"
                                            :key="p"
                                            type="button"
                                            class="preset-chip"
                                            :class="{ 'is-active': num(form.discount_percent) === p }"
                                            @click.prevent="applyDiscountPreset(p)"
                                        >
                                            {{ p }}%
                                        </button>
                                    </div>
                                    <span v-if="form.discount_mode === 'percent' && num(form.discount) > 0" class="discount-live-val">
                                        = − {{ money(form.discount) }}
                                    </span>
                                    <span v-else-if="form.discount_mode === 'amount' && num(form.discount) > 0 && formSubtotal > 0" class="discount-live-val">
                                        ≈ {{ (Math.round((num(form.discount) / formSubtotal) * 10000) / 100).toFixed(2) }}%
                                    </span>
                                </div>
                            </div>
                        </el-form-item>
                    </el-col>

                    <!-- Tax Field with Quick 15% VAT Action -->
                    <el-col :xs="24" :sm="12">
                        <el-form-item class="financial-form-item tax-form-item">
                            <template #label>
                                <div class="form-label-with-mode">
                                    <span class="label-text">
                                        <i class="fas fa-receipt label-icon text-muted"></i>
                                        {{ $t('tax') }}
                                    </span>
                                    <button
                                        type="button"
                                        class="vat-quick-chip"
                                        :class="{ 'is-active': isVat15Active }"
                                        @click.prevent="toggleVat15"
                                    >
                                        {{ $t('apply_vat_15') }}
                                    </button>
                                </div>
                            </template>
                            <el-input
                                v-model="form.tax"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="$t('tax_amount_placeholder')"
                                class="financial-input"
                            >
                                <template #append>{{ currencyCode }}</template>
                            </el-input>
                            <div class="tax-helper-row">
                                <span v-if="num(form.tax) > 0 && formSubtotal > 0" class="tax-live-val">
                                    ≈ {{ (Math.round((num(form.tax) / Math.max(1, formSubtotal - num(form.discount))) * 10000) / 100).toFixed(2) }}%
                                </span>
                            </div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('notes')" class="mt-2">
                    <el-input v-model="form.notes" type="textarea" :rows="3" maxlength="1000" show-word-limit :placeholder="$t('purchase_order_notes_placeholder')" />
                </el-form-item>

                <!-- The running total, so what the supplier will be owed is on
                     screen while the lines are entered rather than only after
                     saving. -->
                <div v-if="!formLocked" class="financial-summary-block form-totals">
                    <div class="financial-row">
                        <span>{{ $t('subtotal') }}</span>
                        <span>{{ money(formSubtotal) }}</span>
                    </div>
                    <div class="financial-row">
                        <span>{{ $t('discount_label') }}</span>
                        <span class="discount-figure" :class="{ 'has-discount': num(form.discount) > 0 }">
                            <template v-if="num(form.discount) > 0">
                                − {{ money(form.discount) }}
                                <span v-if="form.discount_percent !== '' && form.discount_percent != null && num(form.discount_percent) > 0" class="discount-badge-pill">
                                    {{ form.discount_percent }}%
                                </span>
                            </template>
                            <template v-else>
                                {{ money(0) }}
                            </template>
                        </span>
                    </div>
                    <div class="financial-row">
                        <span>{{ $t('tax_label') }}</span>
                        <span>+ {{ money(form.tax) }}</span>
                    </div>
                    <div class="financial-row grand-total" :class="{ 'total-invalid': discountTooLarge }">
                        <span>{{ $t('grand_total_label') }}</span>
                        <span>{{ money(formTotal) }}</span>
                    </div>
                </div>

            </el-form>
            </div>

            <!-- Pinned under the form: on a request of twenty lines the save
                 buttons sat below all of them, and the total with them. -->
            <template #footer>
                <div class="form-footer">
                    <div v-if="!formLocked" class="footer-total">
                        <span>{{ $t('grand_total_label') }}</span>
                        <strong :class="{ 'total-invalid': discountTooLarge }">{{ money(formTotal) }}</strong>
                        <small>{{ $t('po_items_count', filledItemCount) }}</small>
                    </div>
                    <div class="footer-actions">
                        <small class="shortcut-hint">{{ $t('po_shortcut_hint') }}</small>
                        <el-button @click="confirmCloseForm()">{{ $t('cancel') }}</el-button>
                        <template v-if="!isEditMode">
                            <el-button :loading="submittingForm" @click="saveOrder({ approve: false })">
                                {{ $t('po_save_as_pending') }}
                            </el-button>
                            <!-- Most requests are placed by the person who approves
                                 them; saving and approving in one go spares them
                                 finding the row again to click Approve. -->
                            <el-button type="primary" :loading="submittingForm" @click="saveOrder({ approve: true })">
                                <i class="fas fa-circle-check"></i> {{ $t('po_save_and_approve') }}
                            </el-button>
                        </template>
                        <el-button v-else type="primary" :loading="submittingForm" @click="saveOrder()">{{ $t('save_purchase_order') }}</el-button>
                    </div>
                </div>
            </template>
        </el-drawer>

        <!-- Quick Add Product Dialog: creates a missing item and drops it
             straight into the order line that needed it, so a product that
             does not exist yet no longer means abandoning the order to go
             create it in the catalog first. -->
        <el-dialog
            v-model="quickAddDialogVisible"
            :title="$t('quick_add_product')"
            width="480px"
            append-to-body
            destroy-on-close
        >
            <p class="quick-add-hint">{{ $t('quick_add_product_hint') }}</p>
            <el-form :model="quickAddForm" label-position="top">
                <el-form-item :label="$t('product_name_ar')" required>
                    <el-input v-model="quickAddForm.name_ar" />
                </el-form-item>
                <el-form-item :label="$t('product_name_en')" required>
                    <el-input v-model="quickAddForm.name_en" />
                </el-form-item>
                <el-form-item :label="$t('category')" required>
                    <el-select v-model="quickAddForm.category_id" filterable style="width: 100%">
                        <el-option
                            v-for="c in productsStore.categories"
                            :key="c.id"
                            :label="c.name_ar || c.name"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('purchase_cost')">
                            <el-input v-model="quickAddForm.cost_price" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('sale_price')" required>
                            <el-input v-model="quickAddForm.price" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('sku_optional')">
                            <el-input v-model="quickAddForm.sku" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('unit_optional')">
                            <el-input v-model="quickAddForm.unit" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="quickAddDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="quickAddSubmitting" @click="submitQuickAddProduct">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Quick Add Supplier Dialog: creates a missing supplier and selects
             it right away, so a first-time supplier no longer means
             abandoning the order to go create it in the supplier list first. -->
        <el-dialog
            v-model="quickAddSupplierDialogVisible"
            :title="$t('quick_add_supplier')"
            width="440px"
            append-to-body
            destroy-on-close
        >
            <p class="quick-add-hint">{{ $t('quick_add_supplier_hint') }}</p>
            <el-form :model="quickAddSupplierForm" label-position="top">
                <el-form-item :label="$t('supplier_name_label')" required>
                    <el-input v-model="quickAddSupplierForm.name" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('phone_label')">
                            <el-input v-model="quickAddSupplierForm.phone" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('company_label')">
                            <el-input v-model="quickAddSupplierForm.company" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="quickAddSupplierDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="quickAddSupplierSubmitting" @click="submitQuickAddSupplier">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Purchase Order Printable Modal & Sheet -->
        <el-dialog
            v-model="printOrderDialogVisible"
            :title="$t('print_preview') || 'معاينة أمر الشراء والطباعة'"
            width="900px"
            class="po-print-dialog"
            destroy-on-close
        >
            <!-- Print Settings Toolbar (Screen Only) -->
            <div class="po-print-toolbar po-screen-only">
                <div class="po-print-toolbar-group">
                    <span class="po-toolbar-label">
                        <i class="fas fa-heading"></i>
                        {{ $t('po_header_style') || 'نمط الترويسة' }}:
                    </span>
                    <el-radio-group v-model="printSettings.headerStyle" size="small">
                        <el-radio-button label="official">
                            <i class="fas fa-file-invoice"></i> {{ $t('po_header_official') || 'رسمية معتمدة' }}
                        </el-radio-button>
                        <el-radio-button label="banner">
                            <i class="fas fa-image"></i> {{ $t('po_header_banner') || 'بانر مصور' }}
                        </el-radio-button>
                        <el-radio-button label="compact">
                            <i class="fas fa-compress-alt"></i> {{ $t('po_header_compact') || 'مدمجة' }}
                        </el-radio-button>
                    </el-radio-group>
                </div>

                <div class="po-print-toolbar-group po-toggles-group">
                    <el-checkbox v-model="printSettings.showLogo">
                        {{ $t('po_show_logo') || 'الشعار' }}
                    </el-checkbox>
                    <el-checkbox v-model="printSettings.showContacts">
                        {{ $t('po_show_contacts') || 'بيانات التواصل' }}
                    </el-checkbox>
                    <el-checkbox v-model="printSettings.showImages">
                        {{ $t('po_show_images') || 'صور المنتجات' }}
                    </el-checkbox>
                    <el-checkbox v-model="printSettings.showSignatures">
                        {{ $t('po_show_signatures') || 'التوقيعات والختم' }}
                    </el-checkbox>
                </div>

                <div class="po-print-toolbar-actions">
                    <el-button type="primary" :icon="Printer" size="small" @click="triggerPrintOrder">
                        {{ $t('print_now') || 'طباعة الآن' }}
                    </el-button>
                </div>
            </div>

            <div v-if="printOrderData" id="purchase-order-printable-doc" class="po-printable-sheet">
                <!-- Official Print Header with Logo & Brand Details -->
                <PrintDocumentHeader
                    :header-style="printSettings.headerStyle"
                    :show-logo="printSettings.showLogo"
                    :show-contacts="printSettings.showContacts"
                    :show-meta="false"
                    :title="$t('official_purchase_order') || 'أمر شراء رسمي'"
                    :subtitle="'OFFICIAL PURCHASE ORDER'"
                    :document-number="printOrderData.order_number"
                    :banner-src="'/Header.jpeg'"
                />

                <!-- Unified Order & Supplier Details Card -->
                <div class="po-print-meta-grid">
                    <!-- Supplier Info Box -->
                    <div class="po-meta-card po-meta-supplier">
                        <div class="po-meta-card-header">
                            <i class="fas fa-truck-moving"></i>
                            <span class="po-meta-card-title">{{ $t('supplier_info') || 'بيانات المورد' }}</span>
                        </div>
                        <div class="po-meta-card-body" v-if="printOrderData.supplier">
                            <div class="po-supplier-primary">
                                <strong class="po-supplier-name">{{ printOrderData.supplier.name }}</strong>
                                <span v-if="printOrderData.supplier.company" class="po-supplier-company">
                                    ({{ printOrderData.supplier.company }})
                                </span>
                            </div>
                            <div class="po-supplier-meta-list">
                                <div v-if="printOrderData.supplier.phone" class="po-sm-item">
                                    <i class="fas fa-phone-alt"></i>
                                    <span dir="ltr">{{ printOrderData.supplier.phone }}</span>
                                </div>
                                <div v-if="printOrderData.supplier.email" class="po-sm-item">
                                    <i class="fas fa-envelope"></i>
                                    <span dir="ltr">{{ printOrderData.supplier.email }}</span>
                                </div>
                                <div v-if="printOrderData.supplier.address" class="po-sm-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>{{ printOrderData.supplier.address }}</span>
                                </div>
                                <div v-if="printOrderData.supplier.tax_number || printOrderData.supplier.cr_number" class="po-sm-item">
                                    <i class="fas fa-certificate"></i>
                                    <span>{{ $t('tax_number') || 'الرقم الضريبي' }}: {{ printOrderData.supplier.tax_number || printOrderData.supplier.cr_number }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="po-meta-card-body text-muted">
                            <em>{{ $t('no_supplier_specified') || 'لم يُحدد مورد' }}</em>
                        </div>
                    </div>

                    <!-- Order Details Box -->
                    <div class="po-meta-card po-meta-order">
                        <div class="po-meta-card-header">
                            <i class="fas fa-file-invoice"></i>
                            <span class="po-meta-card-title">{{ $t('order_details') || 'بيانات الأمر' }}</span>
                        </div>
                        <div class="po-meta-card-body">
                            <div class="po-order-meta-grid">
                                <div class="po-om-item">
                                    <span class="po-om-label">{{ $t('order_number') || 'رقم الأمر' }}:</span>
                                    <strong class="po-om-val po-mono" dir="ltr">{{ printOrderData.order_number }}</strong>
                                </div>
                                <div class="po-om-item">
                                    <span class="po-om-label">{{ $t('order_date') || 'تاريخ الأمر' }}:</span>
                                    <span class="po-om-val" dir="ltr">{{ formatDate(printOrderData.order_date || printOrderData.created_at) }}</span>
                                </div>
                                <div v-if="printOrderData.due_date" class="po-om-item">
                                    <span class="po-om-label">{{ $t('due_date') || 'تاريخ الاستحقاق' }}:</span>
                                    <span class="po-om-val" dir="ltr">{{ formatDate(printOrderData.due_date) }}</span>
                                </div>
                                <div class="po-om-item">
                                    <span class="po-om-label">{{ $t('status') || 'الحالة' }}:</span>
                                    <span class="po-om-val po-status-tag" :class="`status-${printOrderData.status}`">
                                        {{ $t(`po_status_${printOrderData.status}`) || printOrderData.status }}
                                    </span>
                                </div>
                                <div v-if="saleLinkOf(printOrderData)" class="po-om-item po-om-full">
                                    <span class="po-om-label">{{ $t('po_for_sale') || 'مرتبط بطلب بيع' }}:</span>
                                    <span class="po-om-val po-sale-badge">
                                        <i class="fas fa-link"></i>
                                        <strong>{{ saleLinkOf(printOrderData).label || saleLinkOf(printOrderData) }}</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <table class="po-print-table">
                    <thead>
                        <tr>
                            <th style="width: 36px; text-align: center;">#</th>
                            <th v-if="printSettings.showImages" style="width: 52px; text-align: center;">{{ $t('image') || 'الصورة' }}</th>
                            <th>{{ $t('product') || 'المنتج / الصنف' }}</th>
                            <th style="width: 120px;">{{ $t('sku') || 'الرمز' }}</th>
                            <th style="width: 70px; text-align: center;">{{ $t('quantity') || 'الكمية' }}</th>
                            <th style="width: 105px; text-align: left;">{{ $t('unit_cost') || 'السعر الإفرادي' }}</th>
                            <th style="width: 115px; text-align: left;">{{ $t('total') || 'الإجمالي' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in (printOrderData.items || [])" :key="item.id || idx">
                            <td style="text-align: center;" class="po-row-idx">{{ idx + 1 }}</td>
                            <td v-if="printSettings.showImages" class="po-print-img-cell" style="text-align: center;">
                                <EntityImage
                                    :src="item.product?.image_main || item.product?.image"
                                    type="product"
                                    :size="38"
                                    shape="square"
                                />
                            </td>
                            <td>
                                <div class="po-item-name">{{ item.product?.name_ar || item.product_name || '-' }}</div>
                                <div v-if="item.product?.name_en" class="po-item-name-en">{{ item.product.name_en }}</div>
                                <div v-if="item.product_variant_id" class="po-item-variant">
                                    <VariantChip :label="variantLabelOf(item.variant) || item.product_name" />
                                </div>
                            </td>
                            <td class="po-sku-cell">
                                <code>{{ item.variant?.sku || item.product?.sku || '—' }}</code>
                            </td>
                            <td style="text-align: center; font-weight: 700;">
                                {{ item.quantity }}
                            </td>
                            <td style="text-align: left;" dir="ltr">
                                {{ money(item.unit_price) }}
                            </td>
                            <td style="text-align: left; font-weight: 800;" dir="ltr">
                                {{ money(item.quantity * item.unit_price) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totals & Notes Block -->
                <div class="po-print-summary-row">
                    <div class="po-print-notes-col">
                        <div v-if="printOrderData.notes" class="po-print-notes-box">
                            <div class="po-notes-header">
                                <i class="fas fa-clipboard-list"></i>
                                <strong>{{ $t('notes') || 'ملاحظات وشروط الطلب' }}:</strong>
                            </div>
                            <p class="po-notes-text">{{ printOrderData.notes }}</p>
                        </div>
                    </div>
                    <div class="po-print-totals-col">
                        <table class="po-totals-table">
                            <tr>
                                <td>{{ $t('subtotal') }}:</td>
                                <td class="val" dir="ltr">{{ money(printOrderData.subtotal ?? detailSubtotal) }}</td>
                            </tr>
                            <tr v-if="num(printOrderData.discount) > 0">
                                <td>{{ $t('discount') }} <span v-if="printOrderData.discount_percent">({{ printOrderData.discount_percent }}%)</span>:</td>
                                <td class="val discount-val" dir="ltr">− {{ money(printOrderData.discount) }}</td>
                            </tr>
                            <tr v-if="num(printOrderData.tax) > 0">
                                <td>{{ $t('tax') }}:</td>
                                <td class="val" dir="ltr">{{ money(printOrderData.tax) }}</td>
                            </tr>
                            <tr class="grand-total-row">
                                <td>{{ $t('total') }}:</td>
                                <td class="val" dir="ltr">{{ money(printOrderData.total) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Official Signatures Block -->
                <div v-if="printSettings.showSignatures" class="po-print-signatures">
                    <div class="sig-card">
                        <span class="sig-title">{{ $t('prepared_by') || 'إعداد وتجهيز' }}</span>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-card">
                        <span class="sig-title">{{ $t('reviewed_by') || 'مراجعة وتدقيق' }}</span>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-card">
                        <span class="sig-title">{{ $t('approved_by') || 'اعتماد الإدارة' }}</span>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-card">
                        <span class="sig-title">{{ $t('company_seal') || 'ختم واعتماد الشركة' }}</span>
                        <div class="sig-seal-box"></div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer po-screen-only">
                    <el-button @click="printOrderDialogVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :icon="Printer" @click="triggerPrintOrder">
                        <i class="fas fa-print"></i> {{ $t('print_now') || 'طباعة الآن' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, onBeforeUnmount, computed, reactive, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { salesOrdersApi } from '@/api/salesOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { useProductsStore } from '@/stores/products';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { productsApi } from '@/api/products';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';

const normalizeStatus = normalizePurchaseOrderStatus;
import { Search, Printer } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import PurchaseSaleLink from '@/components/admin/purchases/PurchaseSaleLink.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { resolveImageUrl } from '@/utils/productImages';
import { invoicesApi } from '@/api/invoices';
import { pickKey, optionKey, baseName, variantLabelOf, optionFromLine, withOptions } from '@/utils/productPick';

const { t } = useI18n();

const productImageSrc = (p) => {
    if (!p) return '';
    const raw = p.image_main || p.image || p.primary_image_url || p.image_url || '';
    return resolveImageUrl(raw);
};

const router = useRouter();
const route = useRoute();
const store = usePurchaseOrdersStore();
const suppliersStore = useSuppliersStore();
const productsStore = useProductsStore();

const searchQuery = ref('');

// A 55% drawer on a phone left a column too narrow to type an item into.
const viewportWidth = ref(window.innerWidth);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 900 ? '100%' : '55%'));

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
// Loading the order into the form is not saving it; sharing the flag spun the
// save button while the fields were still empty.
const loadingForm = ref(false);
const editingOrderId = ref(null);
const editingStatus = ref('');

// Received and cancelled orders keep their lines as booked: the form only
// offers their dates and notes, which is all the API will take for them.
const formLocked = computed(() => isEditMode.value && ['completed', 'cancelled'].includes(normalizeStatus(editingStatus.value)));

// Item-row product search: starts as whatever page loaded on mount, then
// becomes the live server search results once the operator types. Shared
// across rows on purpose — remote-select keeps each row's own already-picked
// label regardless of what the shared option pool currently holds.
const productOptions = ref([]);
// What the line picker offers before anything is typed: the first page of the
// catalogue, one row per size.
const defaultOptions = ref([]);
const loadDefaultOptions = async () => {
    try {
        const res = await productsApi.getAll({ per_page: 100, expand_variants: 1 });
        defaultOptions.value = res.data.data || [];
        productOptions.value = defaultOptions.value;
        if (!quickSearchResults.value.length) {
            quickSearchResults.value = defaultOptions.value;
        }
    } catch (e) {
        defaultOptions.value = [];
    }
};
const productSearchLoading = ref(false);
let productSearchTimer = null;

// Suggested product quick search & multi-add state
const quickSearchQuery = ref('');
const quickSearchLoading = ref(false);
const quickSearchResults = ref([]);
const quickSearchOpen = ref(false);
const quickSearchInputRef = ref(null);
const quickSearchContainerRef = ref(null);
let quickSearchDebounceTimer = null;

const onQuickSearchFocus = () => {
    quickSearchOpen.value = true;
    if (!quickSearchQuery.value && defaultOptions.value.length) {
        quickSearchResults.value = defaultOptions.value;
    }
};

const onQuickSearchInput = (val) => {
    clearTimeout(quickSearchDebounceTimer);
    quickSearchOpen.value = true;
    const query = typeof val === 'string' ? val.trim() : '';
    if (!query) {
        quickSearchResults.value = defaultOptions.value;
        return;
    }
    quickSearchLoading.value = true;
    quickSearchDebounceTimer = setTimeout(async () => {
        try {
            const res = await productsApi.getAll({ search: query, per_page: 50, expand_variants: 1 });
            quickSearchResults.value = res.data.data || [];
            rememberProducts(quickSearchResults.value);
        } catch {
            // Keep current suggestions on error
        } finally {
            quickSearchLoading.value = false;
        }
    }, 250);
};

const onQuickSearchClear = () => {
    quickSearchQuery.value = '';
    quickSearchResults.value = defaultOptions.value;
};

const getOrderItemCount = (prod) => {
    const key = optionKey(prod);
    const existing = form.items.find((item) => item.pick === key);
    return existing ? num(existing.quantity) : 0;
};

const addProductToOrder = (prod, delta = 1) => {
    const key = optionKey(prod);
    const cost = prod.cost_price != null && Number(prod.cost_price) > 0 ? Number(prod.cost_price) : Number(prod.price || 0);
    const sale = prod.price != null ? Number(prod.price) : '';

    rememberProducts([prod]);

    const existingIdx = form.items.findIndex((item) => item.pick === key);

    if (existingIdx !== -1) {
        const currentQty = num(form.items[existingIdx].quantity);
        const newQty = currentQty + delta;
        if (newQty <= 0) {
            if (form.items.length > 1) {
                form.items.splice(existingIdx, 1);
            } else {
                form.items[0] = blankRow();
            }
        } else {
            form.items[existingIdx].quantity = newQty;
        }
    } else {
        if (delta <= 0) return;
        const emptyIdx = form.items.findIndex((item) => !item.product_id && !item.pick);
        if (emptyIdx !== -1) {
            form.items[emptyIdx].pick = key;
            form.items[emptyIdx].product_id = prod.id;
            form.items[emptyIdx].product_variant_id = prod.variant_id || null;
            form.items[emptyIdx].unit_price = cost;
            form.items[emptyIdx].sale_price = sale;
            form.items[emptyIdx].quantity = 1;
        } else {
            form.items.push(blankRow({
                pick: key,
                product_id: prod.id,
                product_variant_id: prod.variant_id || null,
                unit_price: cost,
                sale_price: sale,
                quantity: 1,
            }));
        }
    }
};

const handleClickOutsideQuickSearch = (e) => {
    if (quickSearchContainerRef.value && !quickSearchContainerRef.value.contains(e.target)) {
        quickSearchOpen.value = false;
    }
};

// Quick-add-product state: lets a missing item be created without leaving
// the order form, then drops straight into the row that needed it.
const quickAddDialogVisible = ref(false);
const quickAddSubmitting = ref(false);
const quickAddTargetIndex = ref(null);
const quickAddForm = reactive({
    name_ar: '',
    name_en: '',
    category_id: '',
    cost_price: '',
    price: '',
    sku: '',
    unit: ''
});

// Quick-add-supplier state: lets a missing supplier be created without
// leaving the order form, then selects it right away.
const quickAddSupplierDialogVisible = ref(false);
const quickAddSupplierSubmitting = ref(false);
const quickAddSupplierForm = reactive({
    name: '',
    phone: '',
    company: ''
});

// Rows are keyed by this rather than by their index, so removing a line from
// the middle does not hand its neighbour's selected product to the wrong row.
let rowSeq = 0;
const blankRow = (overrides = {}) => ({
    key: ++rowSeq,
    // What the select binds to: the product, or one variant of it.
    pick: '',
    product_id: '',
    product_variant_id: null,
    quantity: 1,
    unit_price: '',
    sale_price: '',
    ...overrides,
});

const form = reactive({
    supplier_id: '',
    order_date: '',
    due_date: '',
    discount: 0,
    discount_percent: '',
    discount_mode: 'percent',
    tax: 0,
    notes: '',
    // The sale this buys in for: { type: 'sales_order' | 'invoice', id, number, customer }.
    sale_link: null,
    items: []
});

const todayIso = () => {
    // Local date, not toISOString(): that is UTC, so in Damascus an order
    // opened just after midnight was dated the day before.
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// The note the last fill wrote, so filling from another sale replaces it
// instead of stacking a second one, while a note someone typed is kept.
let autoNote = '';

const resetForm = () => {
    form.supplier_id = '';
    form.order_date = todayIso();
    form.due_date = '';
    form.discount = 0;
    form.discount_percent = '';
    form.discount_mode = 'percent';
    form.tax = 0;
    form.notes = '';
    form.sale_link = null;
    autoNote = '';
    form.items = [blankRow()];
    quickSearchQuery.value = '';
    quickSearchOpen.value = false;
    quickSearchResults.value = defaultOptions.value;
};

/* Unsaved-changes guard: the drawer closes on a stray click outside it, and
 * that used to throw away a half-entered order without a word. */
let formSnapshot = '';
const snapshotForm = () => JSON.stringify({ ...form, items: form.items.map(({ key, ...rest }) => rest) });
const markFormClean = () => { formSnapshot = snapshotForm(); };
const formIsDirty = () => formSnapshot !== '' && snapshotForm() !== formSnapshot;

const confirmCloseForm = async (done) => {
    const close = () => (typeof done === 'function' ? done() : (formDrawerVisible.value = false));
    if (!formIsDirty()) return close();
    try {
        await ElMessageBox.confirm(t('po_unsaved_changes'), t('cancel'), {
            type: 'warning',
            confirmButtonText: t('po_discard_changes'),
            cancelButtonText: t('po_keep_editing'),
        });
        close();
    } catch {
        // Kept editing.
    }
};

/* Money and dates, written the way the rest of the admin writes them — this
 * screen alone printed a hardcoded "$" over figures kept in the base currency. */
const money = (value) => formatMoney(value);
const num = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};
const formatDate = (value) => (value ? String(value).slice(0, 10) : '-');

const lineTotal = (item) => num(item.quantity) * num(item.unit_price);
const formSubtotal = computed(() => form.items.reduce((sum, item) => sum + lineTotal(item), 0));

const currencyCode = computed(() => baseCurrencyCode());
const DISCOUNT_PRESETS = [5, 10, 15, 20, 25];

const setDiscountMode = (mode) => {
    form.discount_mode = mode;
    if (mode === 'percent') {
        if (num(form.discount) > 0 && (form.discount_percent === '' || form.discount_percent == null)) {
            updateDiscountPercentFromAmount();
        }
        updateDiscountFromPercent();
    } else {
        if (num(form.discount_percent) > 0 && num(form.discount) === 0) {
            updateDiscountFromPercent();
        }
    }
};

const applyDiscountPreset = (p) => {
    form.discount_mode = 'percent';
    if (num(form.discount_percent) === p) {
        form.discount_percent = '';
        form.discount = 0;
    } else {
        form.discount_percent = p;
        updateDiscountFromPercent();
    }
};

const updateDiscountFromPercent = () => {
    if (form.discount_percent === '' || form.discount_percent == null) {
        form.discount = 0;
        return;
    }
    let pct = num(form.discount_percent);
    if (pct < 0) {
        pct = 0;
        form.discount_percent = 0;
    }
    if (pct > 100) {
        pct = 100;
        form.discount_percent = 100;
    }
    const subtotalVal = formSubtotal.value;
    if (subtotalVal >= 0) {
        form.discount = Math.round((pct / 100) * subtotalVal * 100) / 100;
    }
};

const updateDiscountPercentFromAmount = () => {
    const disc = num(form.discount);
    const subtotalVal = formSubtotal.value;
    if (subtotalVal <= 0 || disc <= 0) {
        form.discount_percent = '';
        return;
    }
    form.discount_percent = Math.round((disc / subtotalVal) * 10000) / 100;
};

const isVat15Active = computed(() => {
    const chargeable = Math.max(0, formSubtotal.value - num(form.discount));
    if (chargeable <= 0 || num(form.tax) <= 0) return false;
    const vat15 = Math.round(chargeable * 0.15 * 100) / 100;
    return Math.abs(num(form.tax) - vat15) < 0.05;
});

const toggleVat15 = () => {
    const chargeable = Math.max(0, formSubtotal.value - num(form.discount));
    const vat15 = Math.round(chargeable * 0.15 * 100) / 100;
    if (isVat15Active.value) {
        form.tax = 0;
    } else {
        form.tax = vat15;
    }
};

watch(formSubtotal, () => {
    if (form.discount_mode === 'percent') {
        if (form.discount_percent !== '' && form.discount_percent != null) {
            updateDiscountFromPercent();
        } else {
            form.discount = 0;
        }
    } else {
        updateDiscountPercentFromAmount();
    }
    if (isVat15Active.value) {
        const chargeable = Math.max(0, formSubtotal.value - num(form.discount));
        form.tax = Math.round(chargeable * 0.15 * 100) / 100;
    }
});

const formTotal = computed(() => formSubtotal.value + num(form.tax) - num(form.discount));
const discountTooLarge = computed(() => num(form.discount) > formSubtotal.value + num(form.tax));

// Two lines for one product (or one variant) split what is really one order
// line, and the receipt then matches its quantity against only one of them.
// Two different sizes of one product are two lines, as they should be.
const duplicatePicks = computed(() => {
    const seen = new Set();
    const dupes = new Set();
    form.items.forEach(({ pick }) => {
        if (!pick) return;
        (seen.has(pick) ? dupes : seen).add(pick);
    });
    return dupes;
});

const isBeforeOrderDate = (date) => {
    if (!form.order_date) return false;
    const [y, m, d] = form.order_date.split('-').map(Number);
    return date < new Date(y, m - 1, d);
};

const productLabel = (p) => [p.name_ar || p.name, p.sku].filter(Boolean).join(' — ');

// A saved line as the select's value and option.
const lineFields = (item) => ({
    pick: pickKey(item.product_id, item.product_variant_id),
    product_id: item.product_id,
    product_variant_id: item.product_variant_id || null,
});

// Names of what was ordered, for the list row.
const itemsPreview = (order) => (order.items || [])
    // A variant line's stored name says which size; the product's does not.
    .map((item) => (item.product_variant_id ? item.product_name : item.product?.name_ar || item.product_name))
    .filter(Boolean)
    .slice(0, 3)
    .join('، ') + ((order.items?.length || 0) > 3 ? '…' : '');

// Only an order still waiting on its goods can be late.
const dueState = (order) => {
    if (!order?.due_date || isClosed(order)) return null;
    const due = formatDate(order.due_date);
    const today = todayIso();
    if (due < today) return 'overdue';
    if (due === today) return 'today';
    return null;
};

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'complete', 'paid', 'delivered'].includes(value)) return 'success';
    if (['pending', 'hanging', 'in_process', 'processing', 'in progress'].includes(value)) return 'warning';
    if (['confirmed', 'shipped'].includes(value)) return 'info';
    if (['cancelled', 'canceled'].includes(value)) return 'danger';
    return 'info';
};

const statusIconClass = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'complete', 'paid', 'delivered'].includes(value)) return 'fa-check-circle';
    if (['pending', 'processing'].includes(value)) return 'fa-clock';
    if (value === 'cancelled') return 'fa-times-circle';
    return 'fa-sync-alt';
};

const getArabicStatus = (status) => {
    const value = normalizeStatus(status);
    const mapping = {
        'pending': t('sales_status_pending'),
        'confirmed': t('sales_status_confirmed'),
        'processing': t('sales_status_processing'),
        'shipped': t('sales_status_shipped'),
        'delivered': t('sales_status_delivered'),
        'completed': t('sales_status_completed'),
        'cancelled': t('sales_status_cancelled'),
        'canceled': t('sales_status_cancelled')
    };
    return mapping[value] || status;
};

// Timeline. 'processing' only appears on the track for an order that is in it:
// most orders go straight from approved to received, and a permanent middle
// step they never visit read as though every one of them had skipped it.
const timelineSteps = computed(() => {
    const steps = [
        { key: 'pending', label: t('po_step_created'), icon: 'fa-file-signature' },
        { key: 'confirmed', label: t('po_step_approved'), icon: 'fa-circle-check' },
    ];
    if (normalizeStatus(selectedOrder.value?.status) === 'processing') {
        steps.push({ key: 'processing', label: t('po_step_processing'), icon: 'fa-gears' });
    }
    steps.push({ key: 'completed', label: t('po_step_received'), icon: 'fa-boxes-packing' });
    return steps;
});

const timelineIndex = computed(() => {
    const stage = normalizeStatus(selectedOrder.value?.status);
    return Math.max(0, timelineSteps.value.findIndex((step) => step.key === stage));
});

// The bar runs between the first and last node (see .progress-base-bar).
const TIMELINE_TRACK = '76%';
const timelineFillWidth = computed(() => {
    const segments = timelineSteps.value.length - 1;
    return `calc(${TIMELINE_TRACK} * ${segments ? timelineIndex.value / segments : 0})`;
});

const detailSubtotal = computed(() => (selectedOrder.value?.items || [])
    .reduce((sum, item) => sum + num(item.quantity) * num(item.unit_price), 0));

// Received quantities are only meaningful once a receipt has been recorded.
const showReceived = computed(() => (selectedOrder.value?.receipts?.length || 0) > 0
    || normalizeStatus(selectedOrder.value?.status) === 'completed');

/* ------------------------------------------------------------------ *
 * The list
 *
 * Filtering, searching and counting used to happen in the browser over the
 * twenty rows the store held — so an order on page two could not be found, let
 * alone approved, and "pending orders" really meant "pending orders on screen".
 * All three are now the server's answers over the whole table.
 * ------------------------------------------------------------------ */

const activeStage = ref('all');
const counts = computed(() => store.statusCounts);

// Confirmed and processing are both "approved, goods not in yet" — the queue
// the receipts screen exists to drain.
const awaitingReceiptCount = computed(() => counts.value.confirmed + counts.value.processing);

const stageTabs = computed(() => [
    { name: 'all', label: t('all'), icon: 'fa-layer-group', count: counts.value.all, badge: 'info' },
    { name: 'pending', label: t('awaiting_approval'), icon: 'fa-stamp', count: counts.value.pending, badge: 'warning' },
    { name: 'confirmed', label: t('sales_status_confirmed'), icon: 'fa-circle-check', count: counts.value.confirmed, badge: 'primary' },
    { name: 'processing', label: t('sales_status_processing'), icon: 'fa-gears', count: counts.value.processing, badge: 'primary' },
    { name: 'completed', label: t('received_orders'), icon: 'fa-boxes-packing', count: counts.value.completed, badge: 'success' },
    { name: 'cancelled', label: t('sales_status_cancelled'), icon: 'fa-ban', count: counts.value.cancelled, badge: 'danger' },
]);

const loadOrders = (page = 1) => {
    const params = { page, per_page: store.pagination.per_page || 20 };

    if (activeStage.value !== 'all') params.status = activeStage.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();

    return store.fetchOrders(params).catch(() => {});
};

const onStageChange = () => loadOrders(1);
const onPageChange = (page) => loadOrders(page);

// Debounced, so typing a nine-character order number is one request, not nine.
let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadOrders(1), 400);
};

const goToStage = (stage) => {
    activeStage.value = stage;
    loadOrders(1);
};

const isFiltered = computed(() => activeStage.value !== 'all' || !!searchQuery.value.trim());

const clearFilters = () => {
    activeStage.value = 'all';
    searchQuery.value = '';
    loadOrders(1);
};

// Rows still waiting on someone are worth spotting from across the table.
const rowClassName = ({ row }) => {
    const stage = normalizeStatus(row.status);
    if (stage === 'pending') return 'row-awaiting-approval';
    if (['confirmed', 'processing'].includes(stage)) return 'row-awaiting-receipt';
    return '';
};

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await purchaseOrdersApi.getById(id);
        selectedOrder.value = res.data.data;
    } catch (e) {
        ElMessage.error(t('failed_to_load_order_details_msg'));
    } finally {
        loadingDetail.value = false;
    }
};

// Print Order Actions & UI Settings
const printOrderData = ref(null);
const printOrderDialogVisible = ref(false);
const printOrderLoading = ref(false);

const printSettings = reactive({
    headerStyle: 'official', // 'official' | 'banner' | 'compact'
    showLogo: true,
    showContacts: true,
    showImages: true,
    showSignatures: true,
});

const PO_PRINT_SETTINGS_STORAGE_KEY = 'po_print_header_settings_v1';
const loadPrintSettings = () => {
    try {
        const saved = localStorage.getItem(PO_PRINT_SETTINGS_STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (['official', 'banner', 'compact'].includes(parsed.headerStyle)) {
                printSettings.headerStyle = parsed.headerStyle;
            }
            if (typeof parsed.showLogo === 'boolean') printSettings.showLogo = parsed.showLogo;
            if (typeof parsed.showContacts === 'boolean') printSettings.showContacts = parsed.showContacts;
            if (typeof parsed.showImages === 'boolean') printSettings.showImages = parsed.showImages;
            if (typeof parsed.showSignatures === 'boolean') printSettings.showSignatures = parsed.showSignatures;
        }
    } catch {
        // ignore storage errors
    }
};

const savePrintSettings = () => {
    try {
        localStorage.setItem(PO_PRINT_SETTINGS_STORAGE_KEY, JSON.stringify(printSettings));
    } catch {
        // ignore
    }
};

loadPrintSettings();

watch(printSettings, () => {
    savePrintSettings();
}, { deep: true });

const printOrder = async (orderOrRow) => {
    if (!orderOrRow) return;
    printOrderLoading.value = true;
    try {
        let full = orderOrRow;
        // If order items or supplier details are incomplete, fetch the full order record
        if (!full.items || !full.items.length) {
            const res = await purchaseOrdersApi.getById(orderOrRow.id);
            if (res.data?.data) {
                full = res.data.data;
            }
        }
        printOrderData.value = full;
        printOrderDialogVisible.value = true;
    } catch {
        printOrderData.value = orderOrRow;
        printOrderDialogVisible.value = true;
    } finally {
        printOrderLoading.value = false;
    }
};

const triggerPrintOrder = async () => {
    await nextTick();
    window.print();
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    editingStatus.value = '';
    resetForm();
    markFormClean();
    formDrawerVisible.value = true;
};

/**
 * A new request with the same supplier and lines as an existing one.
 *
 * Restocking is mostly the same order placed again; building it line by line
 * each time was the slow part of this screen. Dated today and left pending,
 * with a note saying where it came from.
 */
const duplicateOrder = async (id) => {
    try {
        const { data } = await purchaseOrdersApi.getById(id);
        const order = data.data;

        isEditMode.value = false;
        editingStatus.value = '';
        resetForm();
        form.supplier_id = order.supplier_id;
        form.discount = num(order.discount);
        const dupPct = (order.discount_percent != null && order.discount_percent !== '') ? num(order.discount_percent) : null;
        if (dupPct !== null && dupPct > 0) {
            form.discount_mode = 'percent';
            form.discount_percent = dupPct;
        } else if (num(order.discount) > 0) {
            form.discount_mode = 'amount';
            form.discount_percent = '';
        } else {
            form.discount_mode = 'percent';
            form.discount_percent = '';
        }
        form.tax = num(order.tax);
        form.notes = t('po_duplicated_from', { number: order.order_number });
        form.items = (order.items || [])
            // A line whose product left the catalogue cannot be ordered again.
            .filter((item) => item.product_id)
            .map((item) => blankRow({
                ...lineFields(item),
                quantity: item.quantity,
                unit_price: num(item.unit_price),
                sale_price: item.sale_price != null ? num(item.sale_price) : '',
            }));
        if (!form.items.length) form.items = [blankRow()];
        rememberProducts(order.items);

        markFormClean();
        formDrawerVisible.value = true;
    } catch (e) {
        ElMessage.error(apiError(e, t('failed_to_load_order_details_msg')));
    }
};

const duplicateFromDrawer = () => {
    const id = selectedOrder.value?.id;
    detailDrawerVisible.value = false;
    if (id) duplicateOrder(id);
};

// Lines that already name a product (edit, duplicate) need it among the
// options, or the remote select shows the bare id.
const rememberProducts = (items = []) => {
    productOptions.value = withOptions(
        productOptions.value,
        items.filter((item) => item.product_id).map(optionFromLine),
    );
};

/* Focus follows the work: the supplier first on a new request, and each new
 * line's product as soon as the line is added. */
const supplierSelectRef = ref(null);
const productSelectRefs = new Map();
const setProductSelectRef = (key, el) => {
    if (el) productSelectRefs.set(key, el);
    else productSelectRefs.delete(key);
};

const focusFirstField = () => {
    if (formLocked.value) return;
    if (!form.supplier_id) return supplierSelectRef.value?.focus?.();
    const firstEmpty = form.items.find((item) => !item.product_id);
    if (firstEmpty) productSelectRefs.get(firstEmpty.key)?.focus?.();
};

const filledItemCount = computed(() => form.items.filter((item) => item.product_id).length);

const savePrimary = () => {
    if (submittingForm.value || loadingForm.value) return;
    return isEditMode.value ? saveOrder() : saveOrder({ approve: true });
};

const openEditDrawer = async (id) => {
    isEditMode.value = true;
    editingOrderId.value = id;
    editingStatus.value = '';
    formSnapshot = '';
    resetForm();
    formDrawerVisible.value = true;
    loadingForm.value = true;
    try {
        const res = await purchaseOrdersApi.getById(id);
        const order = res.data.data;
        editingStatus.value = order.status;
        form.supplier_id = order.supplier_id;
        form.order_date = formatDate(order.order_date || order.created_at);
        form.due_date = order.due_date ? formatDate(order.due_date) : '';
        form.discount = num(order.discount);
        const editPct = (order.discount_percent != null && order.discount_percent !== '') ? num(order.discount_percent) : null;
        if (editPct !== null && editPct > 0) {
            form.discount_mode = 'percent';
            form.discount_percent = editPct;
        } else if (num(order.discount) > 0) {
            form.discount_mode = 'amount';
            const subtotalVal = order.subtotal ?? (order.items || []).reduce((sum, item) => sum + (num(item.quantity) * num(item.unit_price)), 0);
            form.discount_percent = subtotalVal > 0 ? Math.round((num(order.discount) / subtotalVal) * 10000) / 100 : '';
        } else {
            form.discount_mode = 'percent';
            form.discount_percent = '';
        }
        form.tax = num(order.tax);
        form.notes = order.notes || '';
        form.sale_link = saleLinkOf(order);
        form.items = order.items.map(item => blankRow({
            ...lineFields(item),
            quantity: item.quantity,
            unit_price: num(item.unit_price),
            sale_price: item.sale_price != null ? num(item.sale_price) : '',
        }));

        // A remote select shows the raw id for a value it has no option for,
        // so a product outside the first hundred rendered as a bare number.
        rememberProducts(order.items);

        markFormClean();
    } catch (e) {
        ElMessage.error(t('failed_to_load_order_for_edit'));
        formDrawerVisible.value = false;
    } finally {
        loadingForm.value = false;
    }
};

const editFromDrawer = () => {
    const id = selectedOrder.value?.id;
    detailDrawerVisible.value = false;
    if (id) openEditDrawer(id);
};

// Form Dynamic items grid actions
const addItemRow = () => {
    const row = blankRow();
    form.items.push(row);
    nextTick(() => productSelectRefs.get(row.key)?.focus?.());
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const updateItemPrice = (pick, idx) => {
    // The chosen row may only exist in the current search results, not in
    // the page that loaded on mount, so look there first.
    const prod = productOptions.value.find(p => optionKey(p) === pick)
        || defaultOptions.value.find(p => optionKey(p) === pick);
    form.items[idx].product_id = prod?.id || '';
    form.items[idx].product_variant_id = prod?.variant_id || null;
    if (prod) {
        // A purchase order's price is what the supplier is paid, so it
        // starts from the product's cost — not its retail price, which is
        // what the line is instead defaulted to sell at.
        form.items[idx].unit_price = prod.cost_price || prod.price;
        form.items[idx].sale_price = prod.price;
    }
};

const searchProducts = (query) => {
    clearTimeout(productSearchTimer);

    if (!query) {
        productOptions.value = defaultOptions.value;
        return;
    }

    productSearchLoading.value = true;
    productSearchTimer = setTimeout(async () => {
        try {
            // One row per size, and a size's own code finds it.
            const res = await productsApi.getAll({ search: query, per_page: 100, expand_variants: 1 });
            productOptions.value = res.data.data || [];
        } catch (e) {
            // Keep whatever was showing rather than blanking the list on a
            // transient failure.
        } finally {
            productSearchLoading.value = false;
        }
    }, 300);
};

// Quick-add-product: opens pre-filled with whatever price the operator had
// already typed on this line, since that number is the purchase cost anyway.
const openQuickAddProduct = (idx) => {
    quickAddTargetIndex.value = idx;
    quickAddForm.name_ar = '';
    quickAddForm.name_en = '';
    quickAddForm.category_id = '';
    quickAddForm.cost_price = form.items[idx]?.unit_price || '';
    quickAddForm.price = '';
    quickAddForm.sku = '';
    quickAddForm.unit = '';
    if (!productsStore.categories.length) {
        productsStore.fetchCategories().catch(() => {});
    }
    quickAddDialogVisible.value = true;
};

const slugify = (text) => text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

const submitQuickAddProduct = async () => {
    if (!quickAddForm.name_ar || !quickAddForm.name_en || !quickAddForm.category_id || !quickAddForm.price) {
        ElMessage.warning(t('please_fill_required_product_fields'));
        return;
    }

    quickAddSubmitting.value = true;
    try {
        // The catalog requires a unique slug, but this dialog never shows one —
        // a timestamp suffix keeps two products with the same name from
        // colliding without asking the operator to think about it.
        const baseSlug = slugify(quickAddForm.name_en) || 'product';
        const product = await productsStore.createProduct({
            name_ar: quickAddForm.name_ar,
            name_en: quickAddForm.name_en,
            slug: `${baseSlug}-${Date.now().toString(36)}`,
            category_id: quickAddForm.category_id,
            price: quickAddForm.price,
            cost_price: quickAddForm.cost_price || null,
            sku: quickAddForm.sku || null,
            unit: quickAddForm.unit || null,
            currency: baseCurrencyCode(),
            stock_quantity: 0
        });

        // Drop it into both pools: the search results the row renders from,
        // and the store's list other rows fall back to.
        productOptions.value = [product, ...productOptions.value];

        const idx = quickAddTargetIndex.value;
        if (idx !== null && form.items[idx]) {
            form.items[idx].pick = pickKey(product.id);
            form.items[idx].product_id = product.id;
            form.items[idx].product_variant_id = null;
            if (!form.items[idx].unit_price) {
                form.items[idx].unit_price = quickAddForm.cost_price || product.cost_price || product.price;
            }
            if (!form.items[idx].sale_price) {
                form.items[idx].sale_price = quickAddForm.price || product.price;
            }
        }

        ElMessage.success(t('product_created_and_selected'));
        quickAddDialogVisible.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_create_product'));
    } finally {
        quickAddSubmitting.value = false;
    }
};

const openQuickAddSupplier = () => {
    quickAddSupplierForm.name = '';
    quickAddSupplierForm.phone = '';
    quickAddSupplierForm.company = '';
    quickAddSupplierDialogVisible.value = true;
};

const submitQuickAddSupplier = async () => {
    if (!quickAddSupplierForm.name) {
        ElMessage.warning(t('please_fill_supplier_name'));
        return;
    }

    quickAddSupplierSubmitting.value = true;
    try {
        const supplier = await suppliersStore.createSupplier({
            name: quickAddSupplierForm.name,
            phone: quickAddSupplierForm.phone || null,
            company: quickAddSupplierForm.company || null
        });

        form.supplier_id = supplier.id;

        ElMessage.success(t('supplier_created_and_selected'));
        quickAddSupplierDialogVisible.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_create_supplier'));
    } finally {
        quickAddSupplierSubmitting.value = false;
    }
};

// The API's own reason, where it gave one, rather than a generic failure.
const apiError = (e, fallback) => {
    const errors = e?.response?.data?.errors;
    const first = errors && Object.values(errors).flat()[0];
    return first || e?.response?.data?.message || fallback;
};

const orderPayload = () => {
    if (formLocked.value) {
        return { order_date: form.order_date || null, due_date: form.due_date || null, notes: form.notes || null, ...saleLinkPayload() };
    }
    return {
        supplier_id: form.supplier_id,
        order_date: form.order_date || null,
        due_date: form.due_date || null,
        discount: num(form.discount),
        discount_percent: (form.discount_percent === '' || form.discount_percent == null) ? null : num(form.discount_percent),
        tax: num(form.tax),
        notes: form.notes || null,
        ...saleLinkPayload(),
        items: form.items.map(({ key, pick, ...item }) => ({
            ...item,
            sale_price: item.sale_price === '' || item.sale_price == null ? null : item.sale_price,
        })),
    };
};

const saveOrder = async ({ approve = false } = {}) => {
    if (!formLocked.value) {
        if (!form.supplier_id) {
            ElMessage.warning(t('please_select_supplier_first'));
            return;
        }
        if (form.items.some(item => !item.product_id || !item.quantity || item.unit_price === '' || item.unit_price == null)) {
            ElMessage.warning(t('please_fill_all_item_fields'));
            return;
        }
        if (duplicatePicks.value.size) {
            const pick = [...duplicatePicks.value][0];
            const product = productOptions.value.find((p) => optionKey(p) === pick);
            ElMessage.warning(t('po_duplicate_product', { name: product?.name_ar || product?.name || pick }));
            return;
        }
        if (discountTooLarge.value) {
            ElMessage.warning(t('po_discount_exceeds_total'));
            return;
        }
    }

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await purchaseOrdersApi.update(editingOrderId.value, orderPayload());
            ElMessage.success(t('purchase_order_updated'));
            markFormClean();
            formDrawerVisible.value = false;
            await loadOrders(store.pagination.current_page);
        } else {
            const payload = { ...orderPayload(), status: approve ? 'confirmed' : 'pending' };
            const { data } = await purchaseOrdersApi.create(payload);
            markFormClean();
            formDrawerVisible.value = false;
            await loadOrders(1);
            // Only an approved order can be received, so offering the receipt
            // for one still pending sent the goods in ahead of the approval.
            if (approve) {
                ElMessage.success(t('purchase_order_approved'));
                promptCreateGoodsReceipt(data.data?.id, t('order_approved_receive_now_message'));
            } else {
                ElMessage.success(t('purchase_order_saved'));
            }
        }
    } catch (e) {
        ElMessage.error(apiError(e, t('failed_to_save_purchase_order')));
    } finally {
        submittingForm.value = false;
    }
};

// Asks the operator, right after a new order is placed, whether to jump
// straight into recording the goods receipt for it — skips the extra trip
// back through the list once the supplier confirms delivery.
const promptCreateGoodsReceipt = async (orderId, message) => {
    if (!orderId) return;
    try {
        await ElMessageBox.confirm(
            message || t('create_goods_receipt_now_message'),
            t('record_goods_receipt'),
            { type: 'success', confirmButtonText: t('yes'), cancelButtonText: t('not_now') }
        );
        receiveGoods(orderId);
    } catch {
        // Operator chose not to create the receipt now.
    }
};

const deleteOrder = async (id) => {
    // A native confirm() next to Element Plus dialogs everywhere else on this
    // screen; it also blocks the whole tab and cannot be styled or translated
    // beyond its message.
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_purchase_order'),
            t('delete'),
            {
                type: 'warning',
                confirmButtonText: t('delete'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await purchaseOrdersApi.delete(id);
        ElMessage.success(t('purchase_order_deleted'));
        // Stepping back a page when the last row on this one went, instead of
        // landing on an empty page past the end.
        const { current_page: page } = store.pagination;
        await loadOrders(store.orders.length === 1 && page > 1 ? page - 1 : page);
    } catch (error) {
        ElMessage.error(apiError(error, t('failed_to_delete_purchase_order')));
    }
};

const receiveGoods = (order) => {
    const id = typeof order === 'object' ? order.id : order;
    router.push(`/admin/purchases/receipts?create_for_order=${id}`);
};

/* ------------------------------------------------------------------ *
 * Workflow actions
 *
 * Approving an order meant opening the edit form, picking a status, and saving
 * the whole thing again — which rewrites every line — and the status field was
 * an empty select, so in practice it could not be done from this screen at all.
 * These call the status endpoint, which changes the one word and nothing else.
 * ------------------------------------------------------------------ */

const busyOrderId = ref(null);

const isApproved = (order) => ['confirmed', 'processing'].includes(normalizeStatus(order?.status));
const isClosed = (order) => ['completed', 'cancelled'].includes(normalizeStatus(order?.status));

const isCancelled = (order) => normalizeStatus(order?.status) === 'cancelled';

// Every order can be opened for editing; a received or cancelled one opens
// with its lines locked (see formLocked), since those are what the stock and
// the ledger were built from.
const canEdit = (order) => !!order;
const canCancel = (order) => !isClosed(order);
// A receipt's stock movement and journal entry point back at the order.
const canDelete = (order) => normalizeStatus(order?.status) !== 'completed' && !(order?.receipts_count > 0);

const moveToStatus = async (order, status) => {
    busyOrderId.value = order.id;
    try {
        const updated = await store.updateStatus(order.id, status);
        // The drawer holds its own copy of the order, fetched separately.
        if (selectedOrder.value?.id === order.id && updated) {
            selectedOrder.value = { ...selectedOrder.value, ...updated };
        }
        return updated;
    } finally {
        busyOrderId.value = null;
    }
};

const approveOrder = async (order) => {
    try {
        await ElMessageBox.confirm(
            t('approve_purchase_order_message', {
                number: order.order_number,
                supplier: order.supplier?.name || '-',
                total: money(order.total),
            }),
            t('approve_purchase_order'),
            {
                type: 'success',
                confirmButtonText: t('approve_and_continue'),
                cancelButtonText: t('cancel'),
            }
        );
    } catch {
        return; // Operator backed out.
    }

    try {
        await moveToStatus(order, 'confirmed');
        // Approval exists so the goods can be received, so offer that next
        // instead of leaving the operator to find the receipts screen.
        ElMessage.success(t('purchase_order_approved'));
        promptCreateGoodsReceipt(order.id, t('order_approved_receive_now_message'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_update_order_status'));
    }
};

const reopenOrder = async (order) => {
    try {
        await moveToStatus(order, 'pending');
        ElMessage.success(t('purchase_order_returned_to_pending'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_update_order_status'));
    }
};

const cancelOrder = async (order) => {
    try {
        await ElMessageBox.confirm(
            t('cancel_purchase_order_message', { number: order.order_number }),
            t('cancel_order'),
            {
                type: 'warning',
                confirmButtonText: t('cancel_order'),
                cancelButtonText: t('back'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await moveToStatus(order, 'cancelled');
        ElMessage.success(t('purchase_order_cancelled'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_update_order_status'));
    }
};

/**
 * The one thing this order is waiting on, as a button.
 *
 * Every stage answers "what now?" the same way in the table and in the drawer,
 * so the row never asks the operator to infer the next step from a status tag.
 */
const nextStep = (order) => {
    const stage = normalizeStatus(order.status);

    if (stage === 'pending') {
        return {
            label: t('approve'),
            icon: 'fa-circle-check',
            type: 'success',
            action: approveOrder,
        };
    }

    if (stage === 'confirmed' || stage === 'processing') {
        return {
            label: t('receive'),
            icon: 'fa-truck-ramp-box',
            type: 'primary',
            action: receiveGoods,
        };
    }

    if (stage === 'completed') {
        return { label: t('received_state'), icon: 'fa-boxes-packing', type: 'success', action: null };
    }

    if (stage === 'cancelled') {
        return { label: t('sales_status_cancelled'), icon: 'fa-ban', type: 'info', action: null };
    }

    return { label: getArabicStatus(order.status), icon: 'fa-circle', type: 'info', action: null };
};

const onRowCommand = (command, row) => {
    if (command === 'view') return openDetailDrawer(row.id);
    if (command === 'print') return printOrder(row);
    if (command === 'duplicate') return duplicateOrder(row.id);
    if (command === 'edit') return canEdit(row) ? openEditDrawer(row.id) : undefined;
    if (command === 'reopen') return reopenOrder(row);
    if (command === 'cancel') return cancelOrder(row);
    if (command === 'delete') return canDelete(row) ? deleteOrder(row.id) : undefined;
};

/** The drawer's version of nextStep — same steps, with room to explain them. */
const drawerStep = computed(() => {
    const order = selectedOrder.value;
    if (!order) return {};

    const stage = normalizeStatus(order.status);

    if (stage === 'pending') {
        return {
            tone: 'warning',
            type: 'success',
            icon: 'fa-stamp',
            title: t('awaiting_your_approval'),
            hint: t('approve_to_allow_receiving'),
            actionLabel: t('approve_purchase_order'),
            actionIcon: 'fa-circle-check',
            action: approveOrder,
        };
    }

    if (stage === 'confirmed' || stage === 'processing') {
        return {
            tone: 'primary',
            type: 'primary',
            icon: 'fa-truck-ramp-box',
            title: t('approved_awaiting_goods'),
            hint: t('goods_can_be_received_now'),
            actionLabel: t('record_goods_receipt'),
            actionIcon: 'fa-arrow-alt-circle-down',
            action: receiveGoods,
        };
    }

    if (stage === 'completed') {
        return {
            tone: 'success',
            type: 'success',
            icon: 'fa-boxes-packing',
            title: t('goods_received'),
            hint: order.received_date
                ? t('received_on_date', { date: String(order.received_date).slice(0, 10) })
                : t('quantities_booked_into_stock'),
            action: null,
        };
    }

    return {
        tone: 'muted',
        type: 'info',
        icon: 'fa-ban',
        title: t('sales_status_cancelled'),
        hint: t('cancelled_order_hint'),
        action: null,
    };
});

/**
 * Opens the create drawer already filled in from a sales order's shortfall.
 *
 * Reached from the confirmation that was refused for lack of stock. The order
 * id travels in the URL and the quantities are fetched here rather than passed
 * along with it, so what lands in the form is what is short *now* — stock may
 * have moved between the refusal and this screen opening.
 */
const prefillFromShortage = async (salesOrderId) => {
    try {
        const { data } = await salesOrdersApi.shortages(salesOrderId);
        const shortages = data?.data?.shortages ?? [];
        const order = data?.data?.sales_order || {};
        const orderNumber = order.order_number ?? salesOrderId;

        if (!shortages.length) {
            ElMessage.info(t('sales.shortage_none_left'));
            return;
        }

        isEditMode.value = false;
        editingStatus.value = '';
        resetForm();
        // Filled as the sales-order prefill is: the size that was sold, its
        // last cost and sale price, and the product among the picker's options
        // so the line shows its name rather than an id.
        form.items = shortages.map((row) => blankRow({
            ...lineFields(row),
            quantity: row.suggested_quantity,
            unit_price: num(row.unit_price),
            sale_price: row.sale_price != null ? num(row.sale_price) : '',
        }));
        rememberProducts(shortages.map((row) => ({ ...row, product_name: row.name })));

        // The customer's delivery date is when the goods are needed by, if it
        // has not already passed.
        if (order.expected_delivery && order.expected_delivery >= form.order_date) {
            form.due_date = order.expected_delivery;
        }
        // Says where these lines came from and for whom, so whoever approves
        // the order later can trace it back to the sale that needed them.
        form.notes = [
            order.customer_name
                ? t('po_from_sales_order_note', { number: orderNumber, customer: order.customer_name })
                : t('sales.prefilled_from_order', { order: orderNumber }),
            order.notes,
        ].filter(Boolean).join('\n').slice(0, 1000);
        autoNote = form.notes;
        form.sale_link = { type: 'sales_order', id: Number(order.id ?? salesOrderId), number: orderNumber, customer: order.customer_name || '' };

        // Clean from here: the prefill is a starting point, not the
        // operator's unsaved work.
        markFormClean();
        formDrawerVisible.value = true;
        ElMessage.success(t('sales.prefilled_from_order', { order: orderNumber }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('sales.shortage_prefill_failed'));
    }
};

/**
 * Opens the create drawer with every line of a sales order — the same
 * products, sizes and quantities, costed at the last price paid and carrying
 * what each sells at. Reached from the sales-order list's "purchase request"
 * action; unlike the shortage prefill it asks for the whole order, not only
 * what stock cannot cover. The supplier is left for the buyer to choose.
 */
const prefillFromSalesOrder = async (salesOrderId) => {
    try {
        const draft = await fetchSaleDraft({ type: 'sales_order', id: salesOrderId });

        if (!draft.lines.length) {
            ElMessage.info(t('po_from_sales_order_empty'));
            return;
        }

        isEditMode.value = false;
        editingStatus.value = '';
        resetForm();
        applySaleDraft(draft);
        form.sale_link = draft.link;

        markFormClean();
        formDrawerVisible.value = true;
        ElMessage.success(t('po_from_sales_order_ready', { number: draft.link.number }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('po_from_sales_order_failed'));
    }
};

/**
 * Opens the create drawer prefilled with a specific product, quantity and supplier.
 * Used when navigating from the product search list on sales order or quotes creation.
 */
const openWithProduct = async (query) => {
    try {
        isEditMode.value = false;
        editingStatus.value = '';
        resetForm();
        if (query.supplier_id) form.supplier_id = Number(query.supplier_id);
        if (query.warehouse_id) form.warehouse_id = Number(query.warehouse_id);
        if (query.notes) form.notes = String(query.notes);

        const productId = Number(query.add_product_id);
        const variantId = query.add_variant_id ? Number(query.add_variant_id) : null;
        const qty = Math.max(1, Number(query.add_qty) || 1);
        const price = query.add_price != null && query.add_price !== '' ? Number(query.add_price) : 0;

        let target = defaultOptions.value.find((p) => p.id === productId && (!variantId || p.variant_id === variantId));
        if (!target) {
            try {
                const res = await posApi.productLookup({ q: query.sku || query.name || String(productId), expand_variants: 1 });
                const list = res.data?.data || [];
                target = list.find((p) => p.id === productId && (!variantId || p.variant_id === variantId)) || list.find((p) => p.id === productId) || list[0];
            } catch {}
        }
        if (target) {
            rememberProducts([target]);
            const pickKey = optionKey(target);
            form.items = [{
                key: 1,
                product_id: target.id,
                product_variant_id: target.variant_id || variantId || null,
                pick: pickKey,
                quantity: qty,
                unit_price: price || Number(target.cost_price || target.price) || 0,
                sale_price: Number(target.price) || 0,
                notes: '',
            }];
            markFormClean();
            formDrawerVisible.value = true;
        }
    } catch (e) {
        console.error('Failed to open purchase drawer with product', e);
    }
};

/* The sale a purchase order buys in for ------------------------------ */

/** An order's link as the picker holds it, or null. */
const saleLinkOf = (order) => {
    if (order?.sales_order) {
        return { type: 'sales_order', id: order.sales_order.id, number: order.sales_order.order_number, customer: order.sales_order.customer?.name || '' };
    }
    if (order?.invoice) {
        return { type: 'invoice', id: order.invoice.id, number: order.invoice.invoice_number, customer: order.invoice.customer?.name || '' };
    }
    return null;
};

// Both are always sent, so linking one sale, or none, clears the other.
const saleLinkPayload = () => ({
    sales_order_id: form.sale_link?.type === 'sales_order' ? form.sale_link.id : null,
    invoice_id: form.sale_link?.type === 'invoice' ? form.sale_link.id : null,
});

/** A sale's lines as a purchase draft, with what to say about where they came from. */
const fetchSaleDraft = async (link) => {
    if (link.type === 'invoice') {
        const { data } = await invoicesApi.purchaseDraft(link.id);
        const invoice = data?.data?.invoice || {};
        return {
            link: { type: 'invoice', id: invoice.id ?? link.id, number: invoice.invoice_number, customer: invoice.customer_name || '' },
            lines: data?.data?.items || [],
            note: t('po_from_invoice_note', { number: invoice.invoice_number, customer: invoice.customer_name || '—' }),
            notes: invoice.notes,
            expected: null,
        };
    }
    const { data } = await salesOrdersApi.purchaseDraft(link.id);
    const order = data?.data?.sales_order || {};
    return {
        link: { type: 'sales_order', id: order.id ?? link.id, number: order.order_number, customer: order.customer_name || '' },
        lines: data?.data?.items || [],
        note: t('po_from_sales_order_note', { number: order.order_number, customer: order.customer_name || '—' }),
        notes: order.notes,
        expected: order.expected_delivery,
    };
};

/** A sale's draft onto the form: the same sizes and quantities, both prices, the date and a note. */
const applySaleDraft = ({ lines, note, notes, expected }) => {
    form.items = lines.map((line) => blankRow({
        ...lineFields(line),
        quantity: line.quantity,
        unit_price: num(line.unit_price),
        sale_price: line.sale_price != null ? num(line.sale_price) : '',
    }));
    rememberProducts(lines);

    // The customer's delivery date is when the goods are needed by, if it
    // has not already passed.
    if (expected && expected >= form.order_date) form.due_date = expected;

    if (!form.notes.trim() || form.notes === autoNote) {
        form.notes = [note, notes].filter(Boolean).join('\n').slice(0, 1000);
        autoNote = form.notes;
    }
};

/**
 * A sale picked in the form: linked already by the picker, and its lines
 * filled in — after asking, when the form has lines of its own. Keeping them
 * keeps the link alone.
 */
const fillFromSale = async (link) => {
    if (formLocked.value || !link) return;

    if (form.items.some((item) => item.product_id)) {
        try {
            await ElMessageBox.confirm(
                t('po_sale_replace_lines', { number: link.number }),
                t('po_sale_replace_title'),
                { type: 'info', confirmButtonText: t('po_sale_replace'), cancelButtonText: t('po_sale_keep_lines') },
            );
        } catch {
            return;
        }
    }

    loadingForm.value = true;
    try {
        const draft = await fetchSaleDraft(link);
        if (!draft.lines.length) {
            ElMessage.info(t('po_sale_empty', { number: link.number }));
            return;
        }
        applySaleDraft(draft);
        ElMessage.success(t('po_sale_filled', { number: link.number }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('po_sale_fill_failed'));
    } finally {
        loadingForm.value = false;
    }
};

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    document.removeEventListener('click', handleClickOutsideQuickSearch);
});

onMounted(async () => {
    window.addEventListener('resize', onResize);
    document.addEventListener('click', handleClickOutsideQuickSearch);
    // The purchases hub links here with ?search=<order number>; without this the
    // parameter was dropped and the operator landed on an unfiltered list.
    if (route.query.search) searchQuery.value = String(route.query.search);
    if (route.query.status) activeStage.value = String(route.query.status);

    // Products first: the item rows bind to product ids, and the selects would
    // render blank if the drawer opened before the catalogue arrived.
    await Promise.all([
        loadOrders(1),
        suppliersStore.fetchSuppliers().catch(() => {}),
        loadDefaultOptions(),
    ]);
    productOptions.value = defaultOptions.value;

    const shortageFor = route.query.shortage_for_order;
    const fromSalesOrder = route.query.from_sales_order;
    const addProductId = route.query.add_product_id;
    if (shortageFor) {
        await prefillFromShortage(shortageFor);
        // Cleared so a refresh does not reopen the drawer over work in progress.
        router.replace({ query: {} });
    } else if (fromSalesOrder) {
        await prefillFromSalesOrder(fromSalesOrder);
        router.replace({ query: {} });
    } else if (addProductId) {
        await openWithProduct(route.query);
        router.replace({ query: {} });
    }
});
</script>

<style scoped>
.purchases-page {
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

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.amount-txt {
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

/* One labelled step per row, with the rest behind the menu beside it. */
.row-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

.next-step-btn {
    font-weight: 600;
    min-width: 108px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.next-step-done {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 108px;
    justify-content: center;
    height: 24px;
}

.more-btn {
    padding: 0.4rem 0.55rem;
}

/* Rows with something outstanding, marked down the leading edge rather than
   with a full-row tint that would fight the striping. */
.custom-table :deep(.row-awaiting-approval) td:first-child {
    box-shadow: inset 3px 0 0 var(--el-color-warning, #e6a23c);
}

.custom-table :deep(.row-awaiting-receipt) td:first-child {
    box-shadow: inset 3px 0 0 var(--el-color-primary, #409eff);
}

.pagination-row {
    display: flex;
    justify-content: center;
    margin-top: 1.5rem;
}

.stage-tabs {
    margin-bottom: 0.5rem;
}

.stage-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.stage-badge {
    margin-inline-start: 0.5rem;
}

.purple-grad {
    background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
}

.stat-card-wrapper.clickable {
    cursor: pointer;
}

.stat-card-wrapper.attention-card {
    border: 1px solid #fcd34d;
}

.stat-cta {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--el-color-warning, #b45309);
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
    padding: 1.5rem;
    font-family: 'Cairo', sans-serif;
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
    inset-inline: 12%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    inset-inline-start: 12%;
    height: 4px;
    background: var(--success);
    z-index: 2;
    transition: width 0.4s ease;
}

.timeline-nodes-wrapper {
    display: flex;
    /* Evenly spread with the end nodes centred on 12% and 88%, where the bar
       starts and stops. */
    justify-content: space-between;
    padding-inline: calc(12% - 40px);
    width: 100%;
    z-index: 3;
}

.timeline-node {
    /* Fixed width, so each icon's centre sits exactly where the bar expects. */
    width: 80px;
    text-align: center;
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

/* The drawer's next-step panel: says what the order is waiting on, then
   offers the button that does it. Tinted by stage so it reads before it is
   read. */
.next-step-card {
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}

.next-step-card :deep(.el-card__body) {
    padding: 1.15rem;
}

.next-step-head {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.next-step-head strong {
    display: block;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.next-step-head p {
    margin: 0.3rem 0 0;
    font-size: 0.82rem;
    line-height: 1.5;
    color: var(--text-muted);
}

.next-step-glyph {
    font-size: 1.35rem;
    margin-top: 0.15rem;
}

.next-step-cta {
    width: 100%;
    font-weight: 700;
}

.next-step-secondary {
    width: 100%;
    margin: 0.5rem 0 0;
    color: var(--text-muted);
}

.next-step-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.next-step-warning .next-step-glyph {
    color: #b45309;
}

.next-step-primary {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.next-step-primary .next-step-glyph {
    color: #1d4ed8;
}

.next-step-success {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.next-step-success .next-step-glyph {
    color: #047857;
}

.next-step-muted {
    background: var(--bg-light, #f8fafc);
}

.next-step-muted .next-step-glyph {
    color: var(--text-muted);
}

.quick-add-hint {
    margin: 0 0 1.25rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.item-row-top {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.item-row-prices {
    display: flex;
    gap: 1rem;
    margin-top: 0.75rem;
}

.price-field {
    flex: 1;
    max-width: 220px;
}

.price-field label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}

/* Secondary line under a cell's main value. */
.cell-sub {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cell-sub.sale-ref { color: #2563eb; direction: ltr; text-align: start; }
.cell-sub.sale-ref i { margin-inline-end: 0.2rem; }

/* The "for a sale" field: its hint sits under the picker. */
.sale-link-item :deep(.el-form-item__content) { flex-direction: column; align-items: stretch; gap: 0.3rem; }
.field-hint { font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; }

.supplier-cell > div {
    min-width: 0;
}

.due-late {
    color: var(--el-color-danger, #dc2626);
    font-weight: 700;
}

.due-tag {
    display: table;
    margin: 0.2rem auto 0;
}

.qty-short {
    color: var(--el-color-warning-dark-2, #b45309);
    font-weight: 700;
}

.drawer-order-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.drawer-order-head > div {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.drawer-order-head h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-dark);
}

.timeline-node.current .node-icon {
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25);
}

.form-section {
    border-top: 1px solid var(--border-color);
    margin-top: 1rem;
    padding-top: 1.5rem;
}

.form-section-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
}

.form-section-head h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}

.item-index {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--border-color);
    color: var(--text-medium);
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-grid-row.item-duplicate {
    border-color: var(--el-color-warning, #e6a23c);
    background: #fffbeb;
}

.product-option {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.product-option small {
    color: var(--text-muted);
}

.product-option-name {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    overflow: hidden;
}

/* The size under the product's name in the detail table. */
.cell-variant {
    margin-top: 0.2rem;
}

.line-total-field strong {
    display: flex;
    align-items: center;
    height: 32px;
    color: var(--text-dark);
}

.form-totals {
    margin-top: 1rem;
}

.financial-row.total-invalid {
    color: var(--el-color-danger, #dc2626);
}

.form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem 1.25rem;
    font-family: 'Cairo', sans-serif;
}

.footer-total {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    color: var(--text-muted);
}

.footer-total strong {
    font-size: 1.25rem;
    color: var(--accent-blue);
}

.footer-total strong.total-invalid {
    color: var(--el-color-danger, #dc2626);
}

.footer-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-inline-start: auto;
}

.footer-actions .el-button + .el-button {
    margin-inline-start: 0;
}

.shortcut-hint {
    color: var(--text-light, #94a3b8);
    font-size: 0.75rem;
}

.form-drawer :deep(.el-drawer__footer) {
    border-top: 1px solid var(--border-color);
    padding: 0.9rem 1.5rem;
    background: var(--bg-white, #fff);
}

.drawer-head-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 640px) {
    .shortcut-hint {
        display: none;
    }

    .footer-actions {
        width: 100%;
    }

    .footer-actions .el-button {
        flex: 1;
    }

    .item-row-top,
    .item-row-prices {
        flex-wrap: wrap;
    }

    .price-field {
        max-width: none;
        min-width: 45%;
    }

    .financial-row {
        width: 100%;
    }
}

/* Form Grid row */
.item-grid-row {
    display: block;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}

/* Financial inputs & discount percentage UX */
.financial-inputs-row {
    margin-bottom: 0.5rem;
}

.financial-form-item {
    margin-bottom: 0.75rem;
}

.financial-form-item :deep(.el-form-item__label) {
    width: 100%;
    display: block;
    margin-bottom: 6px;
    padding: 0;
}

.form-label-with-mode {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-dark, #1e293b);
}

.form-label-with-mode .label-text {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.form-label-with-mode .label-icon {
    font-size: 0.82rem;
}

.discount-mode-toggle {
    display: inline-flex;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 2px;
    gap: 2px;
}

.discount-mode-toggle .mode-btn {
    all: unset;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.12rem 0.5rem;
    border-radius: 4px;
    color: #64748b;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.discount-mode-toggle .mode-btn:hover {
    color: #0f172a;
}

.discount-mode-toggle .mode-btn.is-active {
    background: #ffffff;
    color: #2563eb;
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.discount-field-container {
    width: 100%;
}

.financial-input :deep(.el-input-group__append) {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.82rem;
    padding: 0 12px;
    border-color: var(--border-color, #e2e8f0);
}

.discount-helper-row,
.tax-helper-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.4rem;
    min-height: 24px;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.discount-preset-chips {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.preset-chip {
    all: unset;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.preset-chip:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.preset-chip.is-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    font-weight: 700;
}

.discount-live-val {
    font-size: 0.78rem;
    font-weight: 700;
    color: #dc2626;
    margin-inline-start: auto;
}

.tax-live-val {
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
    margin-inline-start: auto;
}

.vat-quick-chip {
    all: unset;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.12rem 0.5rem;
    border-radius: 4px;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.vat-quick-chip:hover {
    background: #dbeafe;
}

.vat-quick-chip.is-active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.discount-figure {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.discount-figure.has-discount {
    color: #dc2626;
    font-weight: 600;
}

.discount-badge-pill {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    line-height: 1.1;
}

.discount-table-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: #dc2626;
    margin-top: 0.2rem;
    font-weight: 600;
}

.discount-table-tag i {
    font-size: 0.65rem;
}

/* ==========================================================================
   Suggested Product Search & Multi-Add Panel (Stays Open)
   ========================================================================== */
.po-suggested-search-panel {
    position: relative;
    margin-bottom: 1.25rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md, 10px);
    padding: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.search-bar-header {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.search-input-box {
    flex: 1;
    min-width: 0;
}

.po-search-input :deep(.el-input__wrapper) {
    background: #ffffff;
    box-shadow: 0 0 0 1px #cbd5e1 inset;
    border-radius: 8px;
    padding: 4px 12px;
    transition: all 0.2s ease;
}

.po-search-input :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2), 0 0 0 1px #2563eb inset;
}

.search-icon {
    font-size: 0.95rem;
    color: #64748b;
    margin-inline-end: 4px;
}

.btn-toggle-suggestions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.5rem 0.9rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.btn-toggle-suggestions:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.btn-toggle-suggestions.is-active {
    background: #1e3a8a;
    border-color: #1e3a8a;
    color: #ffffff;
}

.count-badge {
    background: rgba(30, 58, 138, 0.1);
    color: #1e3a8a;
    font-size: 0.75rem;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    font-weight: 700;
}

.btn-toggle-suggestions.is-active .count-badge {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Floating / Expandable Dropdown */
.suggested-search-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 999;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.15), 0 4px 10px -2px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.dropdown-top-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.84rem;
}

.strip-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #1e293b;
}

.results-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
}

.strip-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.keep-open-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #047857;
    background: #ecfdf5;
    padding: 0.15rem 0.6rem;
    border-radius: 9999px;
    border: 1px solid #a7f3d0;
}

.close-dropdown-btn {
    all: unset;
    cursor: pointer;
    font-size: 0.9rem;
    color: #64748b;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.close-dropdown-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Products List Scroll Container */
.suggested-list-scroll {
    max-height: 400px;
    overflow-y: auto;
    padding: 0.6rem;
}

.suggested-loading-state,
.suggested-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    color: #64748b;
    gap: 0.6rem;
    text-align: center;
}

.empty-icon {
    font-size: 2.2rem;
    color: #cbd5e1;
}

/* Products Grid / Cards */
.suggested-products-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.suggested-item-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    transition: all 0.15s ease;
}

.suggested-item-card:hover {
    border-color: #93c5fd;
    background: #f8fafc;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.suggested-item-card.item-in-order {
    border-color: #86efac;
    background: #f0fdf4;
}

.item-card-image {
    position: relative;
    flex-shrink: 0;
    width: 52px;
    height: 52px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-card-image :deep(.entity-image) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
}

.in-order-tag {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #16a34a;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.item-card-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.item-card-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.item-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
}

.item-card-tags {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    font-size: 0.72rem;
}

.sku-chip {
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-weight: 600;
}

.cat-chip {
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-weight: 500;
}

.item-card-financials {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    margin-top: 0.1rem;
}

.item-card-financials .stat-lbl {
    color: #64748b;
    margin-inline-end: 0.25rem;
}

.cost-stat .stat-val {
    color: #1e3a8a;
    font-weight: 700;
}

.stock-stat.is-in-stock .stat-val {
    color: #16a34a;
    font-weight: 600;
}

.stock-stat.is-out-stock .stat-val {
    color: #dc2626;
    font-weight: 600;
}

/* Action Area */
.item-card-action {
    flex-shrink: 0;
    margin-inline-start: auto;
}

.btn-add-product {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #2563eb;
    color: #ffffff;
    padding: 0.45rem 0.85rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all 0.15s ease;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}

.btn-add-product:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.3);
}

/* Stepper inside card */
.item-stepper {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #16a34a;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(22, 163, 74, 0.15);
}

.item-stepper .stepper-btn {
    all: unset;
    cursor: pointer;
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
    color: #16a34a;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-stepper .stepper-btn:hover {
    background: #dcfce7;
}

.item-stepper .stepper-val {
    min-width: 28px;
    text-align: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: #15803d;
    padding: 0 0.3rem;
}

/* Dropdown Footer */
.dropdown-footer-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 0.82rem;
}

.footer-summary {
    color: #475569;
}

.btn-done-search {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #1e3a8a;
    color: #ffffff;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    transition: all 0.15s ease;
}

.btn-done-search:hover {
    background: #1e40af;
}

/* ==========================================================================
   Enhanced Product Option inside Row Select
   ========================================================================== */
.product-option-enhanced {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 4px 0;
}

.product-option-enhanced .option-thumb {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.product-option-enhanced .option-details {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.product-option-enhanced .option-title-line {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow: hidden;
}

.product-option-enhanced .product-option-name {
    font-size: 0.84rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-option-enhanced .option-meta-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.72rem;
}

.product-option-enhanced .sku-tag {
    color: #64748b;
    font-family: monospace;
}

.product-option-enhanced .cost-tag {
    color: #2563eb;
    font-weight: 600;
}

.product-option-enhanced .stock-tag.is-in {
    color: #16a34a;
}

.product-option-enhanced .stock-tag.is-zero {
    color: #dc2626;
}

:global(.po-select-product-popper .el-select-dropdown__item) {
    height: auto !important;
    line-height: normal !important;
    padding: 6px 12px !important;
}

/* Purchase Order Printable Sheet Styles */
.po-print-dialog {
    --el-dialog-padding-primary: 16px;
}

.po-print-dialog :global(.el-dialog__header) {
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.po-print-dialog :global(.el-dialog__title) {
    font-weight: 700;
    color: #1e3a8a;
    font-size: 16px;
}

/* Print Settings Toolbar */
.po-print-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 14px;
    margin-bottom: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.po-print-toolbar-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.po-toolbar-label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 6px;
}

.po-toolbar-label i {
    color: #2563eb;
}

.po-toggles-group {
    gap: 16px;
}

.po-toggles-group :global(.el-checkbox) {
    margin-right: 0;
    margin-left: 0;
}

.po-toggles-group :global(.el-checkbox__label) {
    font-size: 12px;
    color: #334155;
    font-weight: 600;
}

.po-print-toolbar-actions {
    margin-right: auto;
}

.po-printable-sheet {
    background: #ffffff;
    color: #0f172a;
    font-family: 'Cairo', 'Almarai', Tahoma, sans-serif;
    padding: 10px 14px;
    box-sizing: border-box;
    direction: rtl;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

/* Unified Order & Supplier Details Grid */
.po-print-meta-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 12px;
    margin-bottom: 14px;
}

.po-meta-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.po-meta-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 11.5px;
    font-weight: 700;
    color: #1e3a8a;
}

.po-meta-card-header i {
    color: #2563eb;
    font-size: 11px;
}

.po-meta-card-body {
    padding: 8px 12px;
    font-size: 11.5px;
}

.po-supplier-primary {
    margin-bottom: 6px;
}

.po-supplier-name {
    font-size: 13.5px;
    color: #0f172a;
    font-weight: 800;
}

.po-supplier-company {
    font-size: 12px;
    color: #64748b;
    margin-right: 6px;
}

.po-supplier-meta-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: #334155;
    font-size: 11px;
}

.po-sm-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.po-sm-item i {
    color: #64748b;
    width: 14px;
    text-align: center;
    font-size: 10px;
}

.po-order-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 10px;
}

.po-om-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.po-om-full {
    grid-column: 1 / -1;
}

.po-om-label {
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
}

.po-om-val {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
}

.po-mono {
    font-family: 'JetBrains Mono', monospace;
    color: #1e3a8a;
}

.po-status-tag {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 10.5px;
    font-weight: 700;
    width: fit-content;
    background: #f1f5f9;
    color: #475569;
}

.po-status-tag.status-approved,
.po-status-tag.status-received {
    background: #dcfce7;
    color: #15803d;
}

.po-status-tag.status-pending,
.po-status-tag.status-draft {
    background: #fef3c7;
    color: #b45309;
}

.po-status-tag.status-ordered {
    background: #e0f2fe;
    color: #0369a1;
}

.po-status-tag.status-cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.po-sale-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px;
    border-radius: 4px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 11px;
}

.po-print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 14px;
}

.po-print-table th,
.po-print-table td {
    border: 1px solid #e2e8f0;
    padding: 6px 8px;
    text-align: right;
    vertical-align: middle;
}

.po-print-table th {
    background: #f1f5f9;
    color: #1e3a8a;
    font-weight: 800;
    font-size: 10.5px;
}

.po-print-img-cell {
    padding: 3px !important;
}

.po-item-name {
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.po-item-name-en {
    font-size: 9.5px;
    color: #64748b;
    direction: ltr;
    text-align: right;
}

.po-item-variant {
    font-size: 9.5px;
    color: #0369a1;
    margin-top: 2px;
}

.po-sku-cell code {
    background: #f8fafc;
    padding: 1px 4px;
    border-radius: 4px;
    font-size: 10px;
    color: #475569;
}

.po-print-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.po-print-notes-col {
    flex: 1;
}

.po-print-notes-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 11px;
}

.po-notes-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    color: #475569;
}

.po-notes-header i {
    color: #2563eb;
    font-size: 10.5px;
}

.po-notes-text {
    margin: 0;
    color: #0f172a;
    white-space: pre-wrap;
    line-height: 1.45;
}

.po-print-totals-col {
    width: 280px;
    flex-shrink: 0;
}

.po-totals-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
}

.po-totals-table td {
    padding: 4px 6px;
    color: #334155;
}

.po-totals-table td.val {
    text-align: left;
    font-weight: 700;
}

.po-totals-table td.discount-val {
    color: #dc2626;
}

.po-totals-table .grand-total-row td {
    font-weight: 800;
    font-size: 13px;
    color: #1e3a8a;
    border-top: 2px solid #1e3a8a;
    padding-top: 6px;
}

.po-print-signatures {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 24px;
    padding-top: 12px;
    border-top: 1px dashed #cbd5e1;
}

.sig-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.sig-title {
    font-size: 10.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 30px;
}

.sig-line {
    width: 80%;
    border-bottom: 1px solid #94a3b8;
}

.sig-seal-box {
    width: 60px;
    height: 60px;
    border: 1px dashed #94a3b8;
    border-radius: 50%;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    html, body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        color: #0f172a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Hide background app layout & screen elements */
    :global(.admin-layout),
    :global(.admin-sidebar),
    :global(.admin-header),
    :global(.admin-main-wrapper),
    :global(.sidebar-overlay),
    .po-screen-only,
    .po-print-toolbar,
    :global(.el-dialog__header),
    :global(.el-dialog__footer),
    :global(.dialog-footer),
    :global(.el-dialog__headerbtn) {
        display: none !important;
    }

    /* Keep the dialog overlay visible but transparent & static */
    :global(.el-overlay),
    :global(.el-overlay-dialog) {
        position: static !important;
        display: block !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
        width: 100% !important;
        height: auto !important;
        inset: auto !important;
        z-index: auto !important;
    }

    :global(.el-dialog.po-print-dialog) {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
        border-radius: 0 !important;
    }

    :global(.el-dialog.po-print-dialog .el-dialog__body) {
        padding: 0 !important;
        margin: 0 !important;
    }

    #purchase-order-printable-doc {
        position: static !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        background: #ffffff !important;
        display: block !important;
    }

    .po-print-meta-grid,
    .po-print-table,
    .po-print-summary-row,
    .po-print-signatures {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .po-meta-card-header,
    .po-status-tag,
    .po-sale-badge,
    .po-print-table thead th,
    .po-totals-table .grand-total-row {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
