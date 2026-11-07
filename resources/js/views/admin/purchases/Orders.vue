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
                <el-button v-if="!readOnly" type="primary" class="create-btn" @click="openCreateDrawer">
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
                    ref="ordersTable"
                    :data="store.orders"
                    row-key="id"
                    style="width: 100%"
                    stripe
                    highlight-current-row
                    class="custom-table"
                    :row-class-name="rowClassName"
                    @expand-change="onExpandChange"
                >
                    <!-- Every line of the order in full: each size under its
                         product with its size, colour, material, specs and
                         codes, and what has been received so far. -->
                    <el-table-column type="expand" width="44">
                        <template #default="{ row }">
                            <DocumentLinesPanel :lines="orderLines(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="order_number" :label="$t('order_number')" width="150">
                        <template #default="{ row }">
                            <span class="order-number-link" @click="openDetailDrawer(row.id)">{{ row.order_number }}</span>
                            <small class="cell-sub">{{ formatDate(row.order_date || row.created_at) }}</small>
                            <small v-if="row.creator" class="cell-sub" :title="$t('po_requested_by')">
                                <i class="fas fa-user"></i> {{ row.creator.name }}
                            </small>
                            <!-- The sale it buys in for, when there is one. -->
                            <small v-if="saleLinkOf(row)" class="cell-sub sale-ref" :title="saleLinkOf(row).customer">
                                <i class="fas" :class="saleLinkOf(row).type === 'invoice' ? 'fa-file-invoice-dollar' : 'fa-cart-shopping'"></i>
                                {{ saleLinkOf(row).number }}
                            </small>
                        </template>
                    </el-table-column>
                    <el-table-column prop="supplier.name" :label="$t('supplier')" min-width="170">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-user-tie text-muted"></i>
                                <div>
                                    <span>{{ row.supplier?.name || '-' }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <!-- What was ordered, grouped by product with each size or
                         colour of it set apart, so two orders to one supplier —
                         or three sizes of one floor drain — tell apart without
                         opening either. The supplier cell used to carry this as
                         a run of names in which a variant's size was buried in
                         the name; the full list, with codes and what has been
                         received, is a hover away. -->
                    <el-table-column :label="$t('po_items_col')" min-width="270" class-name="items-col">
                        <template #default="{ row }">
                            <el-popover
                                v-if="row.items?.length"
                                placement="bottom-start"
                                :width="400"
                                trigger="hover"
                                :show-after="200"
                                popper-class="po-items-popper"
                            >
                                <template #reference>
                                    <div class="items-preview" @click="openDetailDrawer(row.id)">
                                        <span
                                            v-for="group in groupedLines(row).slice(0, PREVIEW_GROUPS)"
                                            :key="group.key"
                                            class="line-pill"
                                            :class="{ 'has-variants': group.variants.length }"
                                        >
                                            <span class="line-pill-name">{{ group.name }}</span>
                                            <template v-if="group.variants.length">
                                                <span
                                                    v-for="line in group.variants.slice(0, PREVIEW_VARIANTS)"
                                                    :key="line.id"
                                                    class="line-pill-variant"
                                                >
                                                    <span class="line-pill-variant-label">{{ lineVariantLabel(line) }}</span>
                                                    <span class="line-pill-qty">×{{ line.quantity }}</span>
                                                </span>
                                                <span v-if="group.variants.length > PREVIEW_VARIANTS" class="line-pill-variant line-pill-more">
                                                    +{{ group.variants.length - PREVIEW_VARIANTS }}
                                                </span>
                                            </template>
                                            <span v-else class="line-pill-qty">×{{ group.quantity }}</span>
                                        </span>
                                        <span v-if="groupedLines(row).length > PREVIEW_GROUPS" class="line-pill line-pill-rest">
                                            +{{ groupedLines(row).length - PREVIEW_GROUPS }}
                                        </span>
                                    </div>
                                </template>

                                <div class="po-items-pop">
                                    <div class="po-items-pop-head">
                                        <strong>{{ $t('po_items_count', row.items.length) }}</strong>
                                        <span class="po-items-pop-meta">
                                            {{ $t('po_products_count', groupedLines(row).length) }}
                                            <template v-if="variantLineCount(row)">
                                                · <i class="fas fa-tag"></i> {{ $t('po_variant_lines', variantLineCount(row)) }}
                                            </template>
                                        </span>
                                    </div>
                                    <ul class="po-items-pop-list">
                                        <li v-for="group in groupedLines(row)" :key="group.key" class="po-items-pop-group">
                                            <div class="po-items-pop-product">
                                                <span class="po-items-pop-name">{{ group.name }}</span>
                                                <strong v-if="!group.variants.length" class="po-items-pop-qty">×{{ group.quantity }}</strong>
                                                <small v-else class="po-items-pop-sum">{{ $t('po_variant_lines', group.variants.length) }} · ×{{ group.quantity }}</small>
                                            </div>
                                            <small v-if="!group.variants.length && lineSku(group.lines[0])" class="po-items-pop-sku">{{ $t('po_sku_label', { sku: lineSku(group.lines[0]) }) }}</small>
                                            <div v-if="group.variants.length" class="po-items-pop-variants">
                                                <div v-for="line in group.lines" :key="line.id" class="po-items-pop-line">
                                                    <VariantChip v-if="line.product_variant_id" :label="lineVariantLabel(line)" />
                                                    <span v-else class="po-items-pop-plain">{{ $t('po_base_product') }}</span>
                                                    <small v-if="lineSku(line)" class="po-items-pop-sku">{{ lineSku(line) }}</small>
                                                    <span class="po-items-pop-line-qty">
                                                        <strong>×{{ line.quantity }}</strong>
                                                        <small
                                                            v-if="rowShowsReceived(row) && num(line.received_quantity) < num(line.quantity)"
                                                            class="qty-short"
                                                        >{{ $t('po_receipt_short', { count: num(line.quantity) - num(line.received_quantity) }) }}</small>
                                                    </span>
                                                </div>
                                            </div>
                                            <small
                                                v-else-if="rowShowsReceived(row) && num(group.lines[0].received_quantity) < num(group.lines[0].quantity)"
                                                class="po-items-pop-short qty-short"
                                            >{{ $t('po_receipt_short', { count: num(group.lines[0].quantity) - num(group.lines[0].received_quantity) }) }}</small>
                                        </li>
                                    </ul>
                                </div>
                            </el-popover>
                            <span v-else class="text-muted">-</span>
                            <button
                                v-if="row.items?.length"
                                type="button"
                                class="lines-toggle"
                                :title="$t('po_show_lines')"
                                @click.stop="toggleLines(row)"
                            >
                                {{ $t('po_items_count', row.items.length) }}
                                <template v-if="variantLineCount(row)">· {{ $t('po_variant_lines', variantLineCount(row)) }}</template>
                                <i class="fas fa-chevron-down lines-chevron" :class="{ open: expandedIds.has(row.id) }"></i>
                            </button>
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
                                    v-if="nextStep(row).action && !readOnly"
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
                                            <el-dropdown-item v-if="!readOnly" command="edit" :disabled="!canEdit(row)">
                                                <i class="fas fa-edit"></i> {{ $t('edit') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item v-if="!readOnly" command="duplicate">
                                                <i class="fas fa-copy"></i> {{ $t('po_duplicate_order') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="isApproved(row) && !readOnly"
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
                                            <el-dropdown-item v-if="!readOnly" command="delete" divided :disabled="!canDelete(row)">
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
                    <el-button v-else-if="!readOnly" type="primary" size="medium" @click="openCreateDrawer">
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
                        <small v-if="selectedOrder.creator" class="cell-sub">
                            <i class="fas fa-user"></i> {{ $t('po_requested_by') }}: {{ selectedOrder.creator.name }}
                        </small>
                    </div>
                    <div class="drawer-head-actions">
                        <el-button type="primary" plain @click="printOrder(selectedOrder)">
                            <i class="fas fa-print"></i> {{ $t('print') }}
                        </el-button>
                        <el-button v-if="!readOnly" plain @click="duplicateFromDrawer">
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
                                v-if="drawerStep.action && !readOnly"
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

        <!-- Create / edit: the order workspace (lines beside the catalogue). -->
        <PurchaseOrderForm ref="orderFormRef" @saved="onOrderSaved" />

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
                            <th style="width: 72px; text-align: center;">{{ $t('quantity') || 'الكمية' }}</th>
                            <th style="width: 105px; text-align: left;">{{ $t('unit_cost') || 'السعر الإفرادي' }}</th>
                            <th style="width: 115px; text-align: left;">{{ $t('total') || 'الإجمالي' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in (printOrderData.items || [])" :key="item.id || idx" class="po-item-row">
                            <td style="text-align: center;" class="po-row-idx">{{ idx + 1 }}</td>
                            <td v-if="printSettings.showImages" class="po-print-img-cell" style="text-align: center;">
                                <EntityImage
                                    :src="item.product?.image_main || item.product?.image"
                                    type="product"
                                    :size="38"
                                    shape="square"
                                />
                            </td>
                            <td class="po-item-cell">
                                <div class="po-item-name">{{ item.product?.name_ar || item.product_name || '-' }}</div>
                                <div v-if="item.product?.name_en" class="po-item-name-en">{{ item.product.name_en }}</div>
                                <div v-if="item.product_variant_id" class="po-item-variant">
                                    <VariantChip :label="variantLabelOf(item.variant) || item.product_name" />
                                </div>
                            </td>
                            <td class="po-sku-cell">
                                <code>{{ item.variant?.sku || item.product?.sku || '—' }}</code>
                            </td>
                            <td style="text-align: center;" class="po-qty-cell">
                                <span class="po-qty-num">{{ item.quantity }}</span>
                                <span v-if="printUnitOf(item)" class="po-unit-label">{{ printUnitOf(item) }}</span>
                            </td>
                            <td style="text-align: left;" class="po-price-cell" dir="ltr">
                                {{ money(item.unit_price) }}
                                <span v-if="printUnitOf(item)" class="po-per-unit">/ {{ printUnitOf(item) }}</span>
                            </td>
                            <td style="text-align: left;" class="po-total-cell" dir="ltr">
                                {{ money(item.quantity * item.unit_price) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="po-table-summary-row">
                            <td :colspan="(printSettings.showImages ? 1 : 0) + 4" class="po-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="po-sum-count">{{ (printOrderData.items || []).length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="po-sum-count">{{ printQuantitySummary }}</strong>
                            </td>
                            <td colspan="2" class="po-sum-total" dir="ltr">
                                <span class="po-sum-subtext">{{ $t('subtotal') }}:</span>
                                <strong>{{ money(printOrderData.subtotal ?? printSubtotal) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
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
import { useAuthStore } from '@/stores/auth';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { formatMoney } from '@/utils/currency';
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
import DocumentLinesPanel from '@/components/admin/sales/DocumentLinesPanel.vue';
import { variantLabelOf } from '@/utils/productPick';
import PurchaseOrderForm from '@/components/admin/purchases/PurchaseOrderForm.vue';

const { t } = useI18n();

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const store = usePurchaseOrdersStore();
const suppliersStore = useSuppliersStore();

// A sales rep looks at purchase orders but does not raise or move them
// (User::isSalesRep); the API refuses the writes either way.
const readOnly = computed(() => authStore.isSalesRep);

const canApprove = computed(() => Boolean(authStore.isSuperAdmin || authStore.user?.is_admin || authStore.hasRole(['admin', 'master'])));

const searchQuery = ref('');

// A 55% drawer on a phone left a column too narrow to type an item into.
const viewportWidth = ref(window.innerWidth);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 900 ? '100%' : '55%'));

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

// Opened through its exposed methods; it reports back through `saved`.
const orderFormRef = ref(null);

const todayIso = () => {
    // Local date, not toISOString(): that is UTC, so in Damascus an order
    // opened just after midnight was dated the day before.
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/* Money and dates, written the way the rest of the admin writes them — this
 * screen alone printed a hardcoded "$" over figures kept in the base currency. */
const money = (value) => formatMoney(value);
const num = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};
const formatDate = (value) => (value ? String(value).slice(0, 10) : '-');

// The list row's items cell: how many products, and how many sizes of
// each, fit before the rest folds into a "+N" that the popover unfolds.
const PREVIEW_GROUPS = 2;
const PREVIEW_VARIANTS = 3;

// The product a line is for, by its catalogue name; the stored name is the
// fallback once the product is gone.
const lineBaseName = (item) => item.product?.name_ar || item.product_name || item.description || '';

// Which size or colour a variant line is for. When the variant itself has
// been deleted, the line's stored name — "floor drain - 4\"" — still carries
// its label after the product's name.
const lineVariantLabel = (item) => {
    const fromVariant = variantLabelOf(item.variant);
    if (fromVariant) return fromVariant;
    const base = (item.product?.name_ar || '').trim();
    const stored = (item.product_name || '').trim();
    if (base && stored.startsWith(base) && stored.length > base.length) {
        return stored.slice(base.length).replace(/^\s*[-–—]\s*/, '').trim();
    }
    return '';
};

const lineSku = (item) => item.variant?.sku || item.product?.sku || '';

// Lines grouped by product, in order of first appearance: an order for three
// sizes of one floor drain reads as that drain with its sizes, not as three
// unrelated names.
const groupedLines = (order) => {
    const groups = new Map();
    (order.items || []).forEach((item) => {
        const key = item.product_id || `line-${item.id}`;
        if (!groups.has(key)) {
            groups.set(key, { key, name: lineBaseName(item), lines: [], variants: [], quantity: 0 });
        }
        const group = groups.get(key);
        group.lines.push(item);
        if (item.product_variant_id) group.variants.push(item);
        group.quantity += num(item.quantity);
    });
    return [...groups.values()];
};

const variantLineCount = (order) => (order.items || []).filter((item) => item.product_variant_id).length;

// ── Expandable lines ─────────────────────────────────────────────────────
// The index already carries each order's lines with their variants, so an
// opened row shows them straight away.
const ordersTable = ref(null);
const expandedIds = reactive(new Set());

const onExpandChange = (row, expandedRows) => {
    const open = Array.isArray(expandedRows) ? expandedRows.some((r) => r.id === row.id) : !!expandedRows;
    if (open) expandedIds.add(row.id);
    else expandedIds.delete(row.id);
};

const toggleLines = (row) => ordersTable.value?.toggleRowExpansion(row);

const orderLines = (order) => {
    const showReceived = rowShowsReceived(order);
    return (order.items || []).map((item) => ({
        id: item.id,
        productId: item.product_id,
        image: item.product?.image_main || item.product?.image,
        name: lineBaseName(item),
        sku: item.product?.sku,
        variant: item.variant,
        variantId: item.product_variant_id,
        quantity: num(item.quantity),
        unitPrice: item.unit_price,
        total: num(item.total_price) || num(item.unit_price) * num(item.quantity),
        received: showReceived ? num(item.received_quantity) : null,
    }));
};

// Whether a row's lines have anything received to compare against: the
// index carries a receipts count rather than the receipts themselves.
const rowShowsReceived = (order) => (order.receipts_count || 0) > 0
    || (order.receipts?.length || 0) > 0
    || normalizeStatus(order.status) === 'completed';

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

// The unit a line is bought in, as the supplier reads it.
const printUnitOf = (item) => item?.unit_name || item?.product?.unit || '';

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة", never "15 قطعة".
const printQuantitySummary = computed(() => {
    const totals = new Map();
    (printOrderData.value?.items || []).forEach((item) => {
        const unit = printUnitOf(item) || t('qt_print_pcs');
        totals.set(unit, (totals.get(unit) || 0) + num(item.quantity));
    });
    if (!totals.size) return '0';
    return [...totals].map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
});

const printSubtotal = computed(() => (printOrderData.value?.items || [])
    .reduce((sum, item) => sum + num(item.quantity) * num(item.unit_price), 0));

const PO_PRINT_IFRAME_ID = 'po-print-hidden-iframe';

const PO_PRINT_STYLES = `
    @page { size: A4 portrait; margin: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important; direction: rtl !important; height: auto !important; overflow: visible !important; }
    a[href]:after, abbr[title]:after { content: none !important; }
    .brand-domain-badge, .domain-badge, .po-screen-only, .po-print-toolbar { display: none !important; }
    /* A print stylesheet another screen left loaded may hide every element. */
    #purchase-order-printable-doc, #purchase-order-printable-doc * { visibility: visible !important; }
    #purchase-order-printable-doc { width: 100% !important; margin: 0 !important; padding: 10mm 12mm !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; display: block !important; }
`;

/*
 * Print through an isolated iframe holding only the sheet. The dialog renders
 * inside the admin layout, so printing the page meant hiding that layout —
 * and the sheet with it.
 */
const triggerPrintOrder = async () => {
    await nextTick();
    const sheet = document.getElementById('purchase-order-printable-doc');
    if (!sheet) return;

    document.getElementById(PO_PRINT_IFRAME_ID)?.remove();
    const iframe = document.createElement('iframe');
    iframe.id = PO_PRINT_IFRAME_ID;
    Object.assign(iframe.style, { position: 'fixed', left: '0', top: '0', width: '100vw', height: '100vh', border: '0', opacity: '0', pointerEvents: 'none', zIndex: '-9999' });
    document.body.appendChild(iframe);

    const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map((node) => node.outerHTML).join('\n');
    const title = `${t('official_purchase_order') || 'أمر شراء'} ${printOrderData.value?.order_number || ''}`
        .replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
        <base href="${window.location.origin}/"><title>${title}</title>
        ${styles}<style>${PO_PRINT_STYLES}</style>
        </head><body>${sheet.outerHTML}</body></html>`);
    doc.close();

    // Images and webfonts have to arrive before the print dialog snapshots the page.
    const pending = Array.from(doc.images).filter((img) => !img.complete);
    await Promise.race([
        Promise.all(pending.map((img) => new Promise((resolve) => { img.onload = resolve; img.onerror = resolve; }))),
        new Promise((resolve) => setTimeout(resolve, 1500)),
    ]);
    await Promise.race([doc.fonts?.ready, new Promise((resolve) => setTimeout(resolve, 1500))]);

    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    setTimeout(() => iframe.remove(), 60000);
};

const openCreateDrawer = () => orderFormRef.value?.openCreate();
const openEditDrawer = (id) => orderFormRef.value?.openEdit(id);
const duplicateOrder = (id) => orderFormRef.value?.openDuplicate(id);

const duplicateFromDrawer = () => {
    const id = selectedOrder.value?.id;
    detailDrawerVisible.value = false;
    if (id) duplicateOrder(id);
};

const editFromDrawer = () => {
    const id = selectedOrder.value?.id;
    detailDrawerVisible.value = false;
    if (id) openEditDrawer(id);
};

// The form saved: refresh the list where the operator was (the first page for
// a new order), and offer the receipt for one approved as it was placed —
// only an approved order can be received.
const onOrderSaved = async ({ id, edited, approved }) => {
    await loadOrders(edited ? store.pagination.current_page : 1);
    if (detailDrawerVisible.value && selectedOrder.value?.id === id) openDetailDrawer(id);
    if (approved) promptCreateGoodsReceipt(id, t('order_approved_receive_now_message'));
};

// The API's own reason, where it gave one, rather than a generic failure.
const apiError = (e, fallback) => {
    const errors = e?.response?.data?.errors;
    const first = errors && Object.values(errors).flat()[0];
    return first || e?.response?.data?.message || fallback;
};

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
const canEdit = (order) => !!order && !readOnly.value;
const canCancel = (order) => !isClosed(order) && !readOnly.value;
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
    if (!canApprove.value) {
        ElMessage.error(t('only_admin_can_approve') || 'فقط المسؤول يمكنه اعتماد طلب الشراء');
        return;
    }
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
        if (!canApprove.value) {
            return {
                label: t('awaiting_approval'),
                icon: 'fa-hourglass-half',
                type: 'warning',
                action: null,
            };
        }
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
        if (!canApprove.value) {
            return {
                tone: 'warning',
                type: 'warning',
                icon: 'fa-hourglass-half',
                title: t('awaiting_approval'),
                hint: t('awaiting_admin_approval') || 'بانتظار اعتماد الإدارة لطلب الشراء',
                actionLabel: null,
                action: null,
            };
        }
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

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    document.getElementById(PO_PRINT_IFRAME_ID)?.remove();
});

onMounted(async () => {
    window.addEventListener('resize', onResize);
    // The purchases hub links here with ?search=<order number>; without this the
    // parameter was dropped and the operator landed on an unfiltered list.
    if (route.query.search) searchQuery.value = String(route.query.search);
    if (route.query.status) activeStage.value = String(route.query.status);

    await Promise.all([
        loadOrders(1),
        suppliersStore.fetchSuppliers().catch(() => {}),
    ]);

    // Links that open the form already filled in. Each query is cleared once
    // used, so a refresh does not reopen the drawer over work in progress.
    // A product link may carry a supplier too, so it is looked at first.
    const { shortage_for_order: shortageFor, from_sales_order: fromSalesOrder, add_product_id: addProductId } = route.query;
    const presetSupplierId = route.query.supplier_id ? Number(route.query.supplier_id) : null;
    if (!(shortageFor || fromSalesOrder || addProductId || presetSupplierId)) return;
    const query = { ...route.query };
    router.replace({ query: {} });
    if (readOnly.value) return;
    if (addProductId) await orderFormRef.value?.openWithProduct(query);
    else if (shortageFor) await orderFormRef.value?.openFromShortage(shortageFor);
    else if (fromSalesOrder) await orderFormRef.value?.openFromSalesOrder(fromSalesOrder);
    else await orderFormRef.value?.openCreate({ supplier_id: presetSupplierId });
});
</script>

<style scoped>
.purchases-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
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

.supplier-cell > div {
    min-width: 0;
}

/* The items cell: one pill per product, its sizes set inside it. */
.lines-toggle { all: unset; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.35rem; font-size: 0.75rem; font-weight: 600; color: #2563eb; }
.lines-toggle:hover { text-decoration: underline; }
.lines-toggle:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }
.lines-chevron { font-size: 0.6rem; transition: transform 0.2s ease; }
.lines-chevron.open { transform: rotate(180deg); }

.items-preview {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem;
    cursor: pointer;
    max-height: 52px;
    overflow: hidden;
}

.line-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    min-width: 0;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    border: 1px solid var(--el-border-color-lighter, #e5e7eb);
    background: var(--el-fill-color-light, #f5f7fa);
    font-size: 0.78rem;
    line-height: 1.25rem;
    color: var(--text-dark);
    white-space: nowrap;
}

.line-pill.has-variants {
    border-color: var(--el-color-primary-light-7);
    background: var(--el-color-primary-light-9);
}

.line-pill-name {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.line-pill-qty {
    color: var(--text-muted);
    font-weight: 600;
    direction: ltr;
    unicode-bidi: isolate;
}

/* A size inside its product's pill: own small tag so "2" ×4  4" ×8" scan
   as two sizes rather than one run of figures. */
.line-pill-variant {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--el-color-primary-light-7);
    color: var(--el-color-primary);
    font-size: 0.72rem;
    line-height: 1.1rem;
}

.line-pill-variant .line-pill-qty {
    color: var(--el-color-primary);
    opacity: 0.8;
}

.line-pill-variant-label {
    font-weight: 700;
    direction: ltr;
    unicode-bidi: isolate;
}

.line-pill-more,
.line-pill-rest {
    color: var(--text-muted);
    font-weight: 700;
    direction: ltr;
    unicode-bidi: isolate;
}

.line-pill-rest {
    background: transparent;
    border-style: dashed;
}

.custom-table :deep(.items-col .cell) {
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
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

/* The size under the product's name in the detail table. */
.cell-variant {
    margin-top: 0.2rem;
}

.drawer-head-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 640px) {
    

    

    

    

    

    .financial-row {
        width: 100%;
    }

    

    

    

    
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

.empty-icon {
    font-size: 2.2rem;
    color: #cbd5e1;
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

.po-print-table tbody td {
    border-inline-color: #f1f5f9;
}

.po-print-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.po-print-table tbody tr.po-item-row td {
    border-bottom-color: #cbd5e1;
}

.po-print-table tbody tr.po-item-row {
    break-inside: avoid;
    page-break-inside: avoid;
}

/* Long product names wrap inside their column instead of stretching the table. */
.po-item-cell {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.po-qty-cell {
    font-weight: 800;
    background: #f8fafc;
}

.po-qty-num {
    display: block;
    font-size: 13px;
    line-height: 1.2;
    color: #0f172a;
}

.po-unit-label {
    display: block;
    font-size: 9px;
    font-weight: 600;
    color: #64748b;
    line-height: 1.2;
}

.po-price-cell,
.po-total-cell {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.po-total-cell {
    font-weight: 800;
}

.po-per-unit {
    display: block;
    font-size: 8.5px;
    font-weight: 500;
    color: #94a3b8;
}

.po-table-summary-row td {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    padding: 6px 10px;
    font-size: 11px;
}

.po-sum-label {
    color: #334155;
}

.po-sum-label i,
.po-sum-count {
    color: #1e3a8a;
}

.po-sum-count {
    font-weight: 800;
    margin-inline: 2px;
}

.po-sum-total {
    text-align: left !important;
    font-size: 12px;
    color: #1e3a8a;
}

.po-sum-subtext {
    font-size: 10px;
    color: #64748b;
    margin-inline-end: 4px;
}

.po-print-img-cell {
    padding: 3px !important;
}

.po-item-name {
    font-weight: 700;
    font-size: 11.5px;
    color: #0f172a;
    line-height: 1.45;
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
        margin: 0 !important;
    }

    html, body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        color: #0f172a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    a[href]:after {
        content: none !important;
    }

    .brand-domain-badge,
    .domain-badge {
        display: none !important;
    }

    .po-screen-only,
    .po-print-toolbar {
        display: none !important;
    }

    #purchase-order-printable-doc {
        position: static !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 10mm 12mm !important;
        box-shadow: none !important;
        border: none !important;
        background: #ffffff !important;
        display: block !important;
    }

    /* Rows stay whole, but the table may run onto the next page — holding all
       of it together pushed a long order off the sheet. */
    .po-print-meta-grid,
    .po-print-table tr,
    .po-print-summary-row,
    .po-print-signatures {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .po-print-table thead {
        display: table-header-group !important;
    }

    .po-print-table tfoot {
        display: table-row-group !important;
    }

    .po-table-summary-row {
        break-before: avoid !important;
        page-break-before: avoid !important;
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

<style>
/* The items popover is teleported to <body>, so it is styled unscoped. */
.po-items-popper.el-popover.el-popper {
    padding: 0;
    overflow: hidden;
}

.po-items-popper .po-items-pop-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.9rem;
    background: var(--el-fill-color-light, #f5f7fa);
    border-bottom: 1px solid var(--el-border-color-lighter, #e5e7eb);
    font-size: 0.82rem;
}

.po-items-popper .po-items-pop-meta {
    color: var(--el-text-color-secondary);
    font-size: 0.75rem;
}

.po-items-popper .po-items-pop-meta i {
    font-size: 0.65rem;
    color: var(--el-color-primary);
}

.po-items-popper .po-items-pop-list {
    list-style: none;
    margin: 0;
    padding: 0.25rem 0;
    max-height: 340px;
    overflow-y: auto;
}

.po-items-popper .po-items-pop-group {
    padding: 0.5rem 0.9rem;
}

.po-items-popper .po-items-pop-group + .po-items-pop-group {
    border-top: 1px solid var(--el-border-color-lighter, #e5e7eb);
}

.po-items-popper .po-items-pop-product {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.85rem;
}

.po-items-popper .po-items-pop-name {
    font-weight: 700;
    color: var(--el-text-color-primary);
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.po-items-popper .po-items-pop-qty,
.po-items-popper .po-items-pop-line-qty strong {
    direction: ltr;
    unicode-bidi: isolate;
    white-space: nowrap;
}

.po-items-popper .po-items-pop-sum {
    color: var(--el-text-color-secondary);
    white-space: nowrap;
    font-size: 0.72rem;
}

.po-items-popper .po-items-pop-sku {
    display: block;
    color: var(--el-text-color-secondary);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.7rem;
    direction: ltr;
    text-align: start;
    unicode-bidi: isolate;
}

/* Each size of the product on its own line, indented under it. */
.po-items-popper .po-items-pop-variants {
    margin-top: 0.35rem;
    padding-inline-start: 0.6rem;
    border-inline-start: 2px solid var(--el-color-primary-light-7);
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.po-items-popper .po-items-pop-line {
    display: grid;
    grid-template-columns: minmax(0, auto) minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.po-items-popper .po-items-pop-line .po-items-pop-sku {
    display: inline;
}

.po-items-popper .po-items-pop-plain {
    font-size: 0.75rem;
    color: var(--el-text-color-secondary);
}

.po-items-popper .po-items-pop-line-qty {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: 1.2;
}

.po-items-popper .po-items-pop-short {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.72rem;
}

.po-items-popper .qty-short {
    color: var(--el-color-danger, #dc2626);
    font-weight: 600;
    font-size: 0.7rem;
}


</style>
