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
                    :placeholder="$t('search_by_order_number_or_supplier_name')"
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
                        <el-button plain @click="duplicateFromDrawer">
                            <i class="fas fa-copy"></i> {{ $t('po_duplicate_order') }}
                        </el-button>
                        <el-button v-if="canEdit(selectedOrder)" plain @click="editFromDrawer">
                            <i class="fas fa-edit"></i> {{ $t('edit') }}
                        </el-button>
                    </div>
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
                                    <span>{{ money(selectedOrder.discount) }}</span>
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
                                        <div class="product-option">
                                            <span class="product-option-name">
                                                {{ baseName(p) }}
                                                <VariantChip v-if="p.variant_id" :label="p.variant_label" />
                                            </span>
                                            <small>
                                                <template v-if="p.sku">{{ p.sku }} · </template>{{ $t('po_cost_label', { price: money(p.cost_price || p.price) }) }}
                                            </small>
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

                <el-row v-if="!formLocked" :gutter="20" class="mt-3">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('discount')" :error="discountTooLarge ? $t('po_discount_exceeds_total') : ''">
                            <el-input v-model="form.discount" type="number" min="0" :placeholder="$t('discount_amount_placeholder')" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('tax')">
                            <el-input v-model="form.tax" type="number" min="0" :placeholder="$t('tax_amount_placeholder')" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('notes')" class="mt-3">
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
                        <span>− {{ money(form.discount) }}</span>
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
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, onBeforeUnmount, computed, reactive, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { salesOrdersApi } from '@/api/salesOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { useProductsStore } from '@/stores/products';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { productsApi } from '@/api/products';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';
import { Search } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { pickKey, optionKey, baseName, variantLabelOf, optionFromLine, withOptions } from '@/utils/productPick';

const { t } = useI18n();

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
    } catch (e) {
        defaultOptions.value = [];
    }
};
const productSearchLoading = ref(false);
let productSearchTimer = null;

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
    tax: 0,
    notes: '',
    items: []
});

const todayIso = () => {
    // Local date, not toISOString(): that is UTC, so in Damascus an order
    // opened just after midnight was dated the day before.
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const resetForm = () => {
    form.supplier_id = '';
    form.order_date = todayIso();
    form.due_date = '';
    form.discount = 0;
    form.tax = 0;
    form.notes = '';
    form.items = [blankRow()];
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

const normalizeStatus = normalizePurchaseOrderStatus;

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
        form.tax = num(order.tax);
        form.notes = order.notes || '';
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
        return { order_date: form.order_date || null, due_date: form.due_date || null, notes: form.notes || null };
    }
    return {
        supplier_id: form.supplier_id,
        order_date: form.order_date || null,
        due_date: form.due_date || null,
        discount: num(form.discount),
        tax: num(form.tax),
        notes: form.notes || null,
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
        const orderNumber = data?.data?.sales_order?.order_number ?? salesOrderId;

        if (!shortages.length) {
            ElMessage.info(t('sales.shortage_none_left'));
            return;
        }

        resetForm();
        isEditMode.value = false;
        form.items = shortages.map((row) => blankRow({
            pick: pickKey(row.product_id),
            product_id: row.product_id,
            quantity: row.suggested_quantity,
            unit_price: row.unit_price,
        }));
        // Says where these lines came from, so whoever approves the order later
        // can trace it back to the sale that needed them.
        form.notes = t('sales.prefilled_from_order', { order: orderNumber });

        // Clean from here: the prefill is a starting point, not the
        // operator's unsaved work.
        markFormClean();
        formDrawerVisible.value = true;
        ElMessage.success(t('sales.prefilled_from_order', { order: orderNumber }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('sales.shortage_prefill_failed'));
    }
};

onBeforeUnmount(() => window.removeEventListener('resize', onResize));

onMounted(async () => {
    window.addEventListener('resize', onResize);
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
    if (shortageFor) {
        await prefillFromShortage(shortageFor);
        // Cleared so a refresh does not reopen the drawer over work in progress.
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
</style>
