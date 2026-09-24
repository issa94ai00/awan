<template>
    <div class="purchases-page purchases-receipts">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-truck-loading text-primary"
            :title="$t('receipts')"
            :subtitle="$t('clearly_view_receipts_with_live')"
        >
            <template #actions>
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_receipt_number_or')" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" @click="openCreateDrawer" class="create-btn">
                    <i class="fas fa-plus-circle"></i> {{ $t('new_receipt') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Metrics cards row -->
        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box blue-grad">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ store.receipts.length }}</h3>
                        <p>{{ $t('total_receipts') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box green-grad">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ recentCount }}</h3>
                        <p>{{ $t('latest_receipts') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('list_of_receipts') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="filteredReceipts.length" 
                    :data="filteredReceipts" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="receipt_number" :label="$t('receipt_number')" width="140">
                        <template #default="{ row }">
                            <span class="receipt-link-txt" @click="openDetailDrawer(row.id)">{{ row.receipt_number }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="supplier.name" :label="$t('supplier')">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-truck text-muted"></i>
                                <span>{{ row.supplier?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="receipt_date" :label="$t('the_date')" width="180" align="center" />
                    <el-table-column prop="notes" :label="$t('comments')" min-width="200" show-overflow-tooltip />
                    
                    <!-- Actions Column -->
                    <el-table-column :label="$t('actions')" width="220" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openDetailDrawer(row.id)" :title="$t('view_details')">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="warning" plain @click="openEditDrawer(row.id)" :title="$t('edit')">
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain @click="deleteReceipt(row.id)" :title="$t('delete')">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredReceipts.length" class="empty-state-box">
                    <i class="fas fa-receipt empty-icon"></i>
                    <p>{{ $t('there_are_no_receipts_matching') }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus-circle"></i> {{ $t('create_new_receipt') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            :title="$t('goods_receipt_details')"
            :size="drawerSize"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedReceipt" class="drawer-detail-content">
                <!-- Timeline status step -->
                <div class="timeline-step-tracker mb-4">
                    <div class="visual-progress-timeline">
                        <div class="progress-base-bar"></div>
                        <div class="progress-fill-bar" style="width: 100%;"></div>
                        
                        <div class="timeline-nodes-wrapper">
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-file-signature"></i></div>
                                <span>{{ $t('linked_purchase_order') }}</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-truck-loading"></i></div>
                                <span>{{ $t('document_the_receipt') }}</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-boxes"></i></div>
                                <span>{{ $t('update_the_warehouse') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: items table & totals -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-list text-muted mr-1"></i> {{ $t('items_and_quantities_received') }}</span>
                            </template>
                            <el-table :data="selectedReceipt.items || []" style="width: 100%" stripe>
                                <el-table-column prop="product.name_ar" :label="$t('item_product')" />
                                <el-table-column prop="quantity" :label="$t('quantity_received')" width="140" align="center" />
                                <el-table-column prop="unit_price" :label="$t('purchase_price')" width="130">
                                    <template #default="{ row }">${{ parseFloat(row.unit_price || 0).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column prop="sale_price" :label="$t('sale_price')" width="130">
                                    <template #default="{ row }">{{ row.sale_price != null ? '$' + parseFloat(row.sale_price).toFixed(2) : '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('grand_total')" width="130">
                                    <template #default="{ row }">${{ (row.quantity * row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                            </el-table>

                            <div class="financial-summary-block mt-4">
                                <div class="financial-row grand-total">
                                    <span>{{ $t('grand_total_label') }}</span>
                                    <span>${{ parseFloat(selectedReceipt.total || 0).toFixed(2) }}</span>
                                </div>
                            </div>
                        </el-card>

                        <!-- Stock Alert Log -->
                        <div class="alert-success-box mb-4">
                            <i class="fas fa-check-circle text-success"></i>
                            <div>
                                <h4>{{ $t('quantities_booked_into_stock') }}</h4>
                                <ul>
                                    <li v-for="item in selectedReceipt.items" :key="item.id">
                                        {{ item.product?.name_ar }}: <strong>+{{ item.quantity }} {{ ('unit_suffix') }}</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </el-col>

                    <!-- Right: supplier & reference orders info -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-tie text-muted mr-1"></i> {{ $t('supplier_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('supplier_name_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.company">
                                    <span class="lbl">{{ $t('company_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.phone">
                                    <span class="lbl">{{ $t('phone_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-info-circle text-muted mr-1"></i> {{ $t('shipment_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('receipt_date_label') }}</span>
                                    <strong>{{ selectedReceipt.receipt_date || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('linked_to_purchase_order_label') }}</span>
                                    <strong v-if="selectedReceipt.purchase_order" style="color: var(--accent-blue);">
                                        #{{ selectedReceipt.purchase_order.order_number }}
                                    </strong>
                                    <strong v-else>{{ $t('direct_receipt') }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card v-if="selectedReceipt.notes" shadow="never">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> {{ $t('notes') }}</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedReceipt.notes }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? $t('edit_goods_receipt') : $t('create_goods_receipt')"
            :size="drawerSize"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
            :before-close="confirmCloseForm"
        >
            <div v-loading="loadingForm" @keydown.ctrl.enter.prevent="saveReceipt" @keydown.meta.enter.prevent="saveReceipt">
            <el-form :model="form" label-position="top">
                <!-- Step 1: who delivered, and against which request. Picking the
                     request fills everything below from it. -->
                <div class="form-step">
                    <div class="form-step-head">
                        <span class="step-num">1</span>
                        <h3>{{ $t('rc_step_source') }}</h3>
                    </div>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('supplier')" required>
                                <el-select v-model="form.supplier_id" :placeholder="$t('select_supplier')" style="width: 100%" filterable :disabled="isEditMode" @change="handleSupplierChange">
                                    <el-option
                                        v-for="s in suppliersStore.suppliers"
                                        :key="s.id"
                                        :label="s.company ? `${s.name} — ${s.company}` : s.name"
                                        :value="s.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <!-- Scoped to the chosen supplier, and to requests that can
                                 still take goods: listing received and cancelled ones
                                 invited receiving the same delivery twice. -->
                            <el-form-item :label="$t('linked_purchase_order')">
                                <el-select
                                    v-model="form.purchase_order_id"
                                    :placeholder="!form.supplier_id ? $t('select_supplier_first') : (openOrders.length ? $t('select_purchase_order_or_direct') : $t('rc_no_open_orders'))"
                                    style="width: 100%"
                                    filterable
                                    clearable
                                    :disabled="!form.supplier_id || isEditMode"
                                    :loading="ordersLoading"
                                    @change="handlePurchaseOrderChange"
                                >
                                    <el-option
                                        v-for="o in openOrders"
                                        :key="o.id"
                                        :label="o.order_number"
                                        :value="o.id"
                                    >
                                        <div class="order-option">
                                            <span class="order-option-main">
                                                <strong>{{ o.order_number }}</strong>
                                                <el-tag size="small" :type="orderStageTag(o.status)" effect="light">{{ orderStageLabel(o.status) }}</el-tag>
                                            </span>
                                            <small>{{ formatDate(o.order_date || o.created_at) }} · {{ money(o.total) }}</small>
                                        </div>
                                    </el-option>
                                </el-select>
                                <small v-if="form.supplier_id && !isEditMode && openOrders.length" class="field-hint">
                                    {{ $t('rc_open_orders_hint', openOrders.length) }}
                                </small>
                            </el-form-item>
                        </el-col>
                    </el-row>

                    <!-- What the request asked for, beside the form that records
                         what came, so the two can be checked against each other. -->
                    <div v-if="linkedOrder" class="linked-order-card">
                        <div class="linked-order-head">
                            <div>
                                <i class="fas fa-file-signature"></i>
                                <strong>{{ linkedOrder.order_number }}</strong>
                                <el-tag size="small" :type="orderStageTag(linkedOrder.status)" effect="light">{{ orderStageLabel(linkedOrder.status) }}</el-tag>
                            </div>
                            <div class="linked-order-actions">
                                <el-button v-if="!isEditMode" size="small" text type="primary" @click="refillFromOrder">
                                    <i class="fas fa-rotate"></i> {{ $t('rc_refill_from_order') }}
                                </el-button>
                                <router-link :to="{ path: '/admin/purchases/orders', query: { search: linkedOrder.order_number } }" class="linked-order-link">
                                    {{ $t('view_details') }} <i class="fas fa-arrow-up-right-from-square"></i>
                                </router-link>
                            </div>
                        </div>
                        <div class="linked-order-facts">
                            <div><span>{{ $t('po_order_date') }}</span><strong>{{ formatDate(linkedOrder.order_date || linkedOrder.created_at) }}</strong></div>
                            <div><span>{{ $t('due_date') }}</span><strong :class="{ 'text-danger': linkedOrderOverdue }">{{ formatDate(linkedOrder.due_date) }}</strong></div>
                            <div><span>{{ $t('rc_order_total') }}</span><strong>{{ money(linkedOrder.total) }}</strong></div>
                            <div><span>{{ $t('items_count') }}</span><strong>{{ linkedOrder.items?.length || 0 }}</strong></div>
                        </div>
                        <el-alert v-if="orderPendingApproval" type="warning" :closable="false" show-icon class="mt-2" :title="$t('rc_order_not_approved')" />
                        <el-alert v-if="orderAlreadyReceived" type="info" :closable="false" show-icon class="mt-2" :title="$t('rc_order_already_received')" />
                        <!-- The receipt carries no discount of its own, so an order
                             discount is not in the supplier's balance unless it is
                             priced into the lines. -->
                        <el-alert
                            v-if="num(linkedOrder.discount) > 0"
                            type="info"
                            :closable="false"
                            show-icon
                            class="mt-2"
                            :title="$t('rc_order_discount_note', { amount: money(linkedOrder.discount) })"
                        />
                    </div>
                </div>

                <!-- Step 2: when and where it arrived. -->
                <div class="form-step">
                    <div class="form-step-head">
                        <span class="step-num">2</span>
                        <h3>{{ $t('shipment_details') }}</h3>
                    </div>
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('actual_receipt_date')">
                                <el-date-picker v-model="form.receipt_date" type="date" :placeholder="$t('receipt_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <!-- The receipt puts the goods on the balance sheet, so where
                                 they land is the operator's call rather than a silent
                                 fallback to whichever warehouse happens to be first. -->
                            <el-form-item :label="$t('receiving_warehouse')" required>
                                <el-select v-model="form.warehouse_id" :placeholder="$t('choose_warehouse')" style="width: 100%" :disabled="isEditMode">
                                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                                </el-select>
                            </el-form-item>
                        </el-col>
                    </el-row>
                </div>

                <!-- Step 3: what came. -->
                <div class="form-step">
                    <div class="form-step-head">
                        <span class="step-num">3</span>
                        <h3>{{ $t('incoming_items_and_quantities') }}</h3>
                        <div class="form-step-tools">
                            <el-button v-if="!isEditMode && linkedOrder" size="small" plain @click="receiveAllAsOrdered">
                                <i class="fas fa-check-double"></i> {{ $t('rc_all_arrived') }}
                            </el-button>
                            <el-button v-if="!isEditMode" type="primary" size="small" plain @click="addItemRow">
                                <i class="fas fa-plus"></i> {{ $t('add_item') }}
                            </el-button>
                        </div>
                    </div>

                    <!-- Lines are locked once the receipt exists: the stock was taken
                         in against them and their cost posted to the ledger, so
                         changing them here would leave both describing a delivery
                         that never happened. -->
                    <el-alert
                        v-if="isEditMode"
                        type="info"
                        show-icon
                        :closable="false"
                        class="mb-3"
                        :title="$t('receipt_lines_locked_notice')"
                    />

                    <div class="items-grid-wrapper">
                        <div
                            v-for="(item, idx) in form.items"
                            :key="item.key"
                            class="item-grid-row"
                            :class="{ 'item-duplicate': duplicateProductIds.has(item.product_id), 'item-off-order': linkedOrder && item.product_id && lineVsOrder(item).state === 'extra' }"
                        >
                            <div class="item-row-top">
                                <span class="item-index">{{ idx + 1 }}</span>
                                <el-select
                                    v-model="item.product_id"
                                    :placeholder="$t('select_item')"
                                    filterable
                                    remote
                                    reserve-keyword
                                    :remote-method="searchProducts"
                                    :loading="productSearchLoading"
                                    style="flex: 2.5; min-width: 0;"
                                    :disabled="isEditMode"
                                    @change="(val) => updateItemPrice(val, idx)"
                                >
                                    <el-option
                                        v-for="p in productOptions"
                                        :key="p.id"
                                        :label="[p.name_ar || p.name, p.sku].filter(Boolean).join(' — ')"
                                        :value="p.id"
                                    />
                                </el-select>
                                <el-input-number v-model="item.quantity" :min="1" :placeholder="$t('quantity')" style="flex: 1; min-width: 120px;" :disabled="isEditMode" />
                                <el-button
                                    v-if="!isEditMode"
                                    type="success"
                                    circle
                                    plain
                                    @click="openQuickAddProduct(idx)"
                                    :title="$t('add_new_product')"
                                >
                                    <i class="fas fa-plus"></i>
                                </el-button>
                                <el-button v-if="!isEditMode" type="danger" circle plain @click="removeItemRow(idx)" :disabled="form.items.length <= 1">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </div>

                            <!-- Against the request: how much was asked for, how much
                                 earlier deliveries brought, and whether this one is
                                 short or over. -->
                            <div v-if="linkedOrder && item.product_id && !isEditMode" class="line-vs-order">
                                <template v-if="lineVsOrder(item).state === 'extra'">
                                    <el-tag size="small" type="warning" effect="plain">{{ $t('rc_not_in_order') }}</el-tag>
                                </template>
                                <template v-else>
                                    <span>{{ $t('rc_ordered_n', { n: lineVsOrder(item).ordered }) }}</span>
                                    <span v-if="lineVsOrder(item).before">· {{ $t('rc_received_before_n', { n: lineVsOrder(item).before }) }}</span>
                                    <el-tag v-if="lineVsOrder(item).state === 'short'" size="small" type="warning" effect="plain">
                                        {{ $t('rc_short_n', { n: lineVsOrder(item).diff }) }}
                                    </el-tag>
                                    <el-tag v-else-if="lineVsOrder(item).state === 'over'" size="small" type="danger" effect="plain">
                                        {{ $t('rc_over_n', { n: lineVsOrder(item).diff }) }}
                                    </el-tag>
                                    <el-tag v-else size="small" type="success" effect="plain">
                                        <i class="fas fa-check"></i> {{ $t('rc_matches_order') }}
                                    </el-tag>
                                    <span v-if="lineVsOrder(item).priceChanged" class="price-changed">
                                        · {{ $t('rc_price_was', { price: money(lineVsOrder(item).orderPrice) }) }}
                                    </span>
                                </template>
                            </div>

                            <!-- Cost is rolled into the product's weighted-average
                                 cost on save; sale price, if set, replaces the
                                 product's shelf price outright — receiving this
                                 purchase is what puts both into effect. -->
                            <div class="item-row-prices">
                                <div class="price-field">
                                    <label>{{ $t('purchase_cost') }}</label>
                                    <el-input v-model="item.unit_price" type="number" min="0" step="0.01" placeholder="0.00" :disabled="isEditMode" />
                                </div>
                                <div class="price-field">
                                    <label>{{ $t('sale_price') }}</label>
                                    <el-input v-model="item.sale_price" type="number" min="0" step="0.01" placeholder="0.00" :disabled="isEditMode" />
                                </div>
                                <div class="price-field line-total-field">
                                    <label>{{ $t('po_line_total') }}</label>
                                    <strong>{{ money(num(item.quantity) * num(item.unit_price)) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ordered lines nothing on this receipt covers yet, one
                         click each to bring back. -->
                    <div v-if="missingOrderLines.length && !isEditMode" class="missing-lines">
                        <span>{{ $t('rc_missing_lines') }}</span>
                        <el-button
                            v-for="line in missingOrderLines"
                            :key="line.product_id"
                            size="small"
                            plain
                            @click="restoreOrderLine(line)"
                        >
                            <i class="fas fa-plus"></i> {{ line.product?.name_ar || line.product_name }} ({{ line.remaining_quantity }})
                        </el-button>
                    </div>
                </div>

                <!-- Step 4: tax and notes. -->
                <div class="form-step">
                    <div class="form-step-head">
                        <span class="step-num">4</span>
                        <h3>{{ $t('rc_step_finish') }}</h3>
                    </div>
                    <!-- Kept out of the item prices on purpose: tax paid to a
                         supplier is recoverable from the authority, so booking it
                         into the cost of the goods overstates the stock and hides
                         the claim. -->
                    <el-form-item :label="$t('purchase_tax_amount')">
                        <el-input v-model="form.tax_amount" type="number" min="0" step="0.01" :disabled="isEditMode" />
                        <small class="field-hint">{{ $t('purchase_tax_hint') }}</small>
                    </el-form-item>

                    <el-form-item :label="$t('receipt_notes')">
                        <el-input v-model="form.notes" type="textarea" :rows="3" maxlength="1000" show-word-limit :placeholder="$t('receipt_notes_placeholder')" />
                    </el-form-item>

                    <div v-if="!isEditMode" class="financial-summary-block">
                        <div class="financial-row">
                            <span>{{ $t('rc_goods_value') }}</span>
                            <span>{{ money(goodsTotal) }}</span>
                        </div>
                        <div class="financial-row">
                            <span>{{ $t('tax_label') }}</span>
                            <span>+ {{ money(form.tax_amount) }}</span>
                        </div>
                        <div class="financial-row grand-total">
                            <span>{{ $t('rc_owed_to_supplier') }}</span>
                            <span>{{ money(receiptTotal) }}</span>
                        </div>
                        <div v-if="linkedOrder && Math.abs(receiptTotal - num(linkedOrder.total)) >= 0.01" class="financial-row order-diff">
                            <span>{{ $t('rc_vs_order_total') }}</span>
                            <span>{{ receiptTotal > num(linkedOrder.total) ? '+' : '−' }} {{ money(Math.abs(receiptTotal - num(linkedOrder.total))) }}</span>
                        </div>
                    </div>
                </div>
            </el-form>
            </div>

            <template #footer>
                <div class="form-footer">
                    <div v-if="!isEditMode" class="footer-total">
                        <span>{{ $t('rc_owed_to_supplier') }}</span>
                        <strong>{{ money(receiptTotal) }}</strong>
                        <small>{{ $t('po_items_count', filledItemCount) }} · {{ $t('rc_units_n', { n: totalUnits }) }}</small>
                    </div>
                    <div class="footer-actions">
                        <small class="shortcut-hint">{{ $t('po_shortcut_hint') }}</small>
                        <el-button @click="confirmCloseForm()">{{ $t('cancel') }}</el-button>
                        <el-button type="primary" :loading="submittingForm" @click="saveReceipt">
                            <i class="fas fa-truck-ramp-box"></i> {{ $t('save_receipt') }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-drawer>

        <!-- Quick Add Product Dialog: lets an unlisted item be created and
             dropped straight into the receipt line that needed it, so a
             missing product no longer means abandoning the whole receipt to
             go create it in the catalog first. -->
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
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, onBeforeUnmount, computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';
import { useSuppliersStore } from '@/stores/suppliers';
import { useProductsStore } from '@/stores/products';
import { useInventoryStore } from '@/stores/inventory';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';
import { productsApi } from '@/api/products';
import { Search } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const store = usePurchaseReceiptsStore();
const suppliersStore = useSuppliersStore();
const productsStore = useProductsStore();
const inventoryStore = useInventoryStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedReceipt = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
// Loading a receipt into the form is not saving it.
const loadingForm = ref(false);
const editingReceiptId = ref(null);

// A 55% drawer on a phone left too narrow a column to receive into.
const viewportWidth = ref(window.innerWidth);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 900 ? '100%' : '55%'));

// Quick-add-product state: lets a missing item be created without leaving
// the receipt form, then drops straight into the row that needed it.
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

// Rows are keyed by this rather than by index, so removing a middle line does
// not hand its neighbour's product to the wrong row.
let rowSeq = 0;
const blankRow = (overrides = {}) => ({
    key: ++rowSeq,
    product_id: '',
    quantity: 1,
    unit_price: '',
    sale_price: '',
    ...overrides,
});

const form = reactive({
    supplier_id: '',
    purchase_order_id: '',
    warehouse_id: '',
    receipt_date: '',
    tax_amount: 0,
    notes: '',
    items: []
});

const warehouses = computed(() => inventoryStore.warehouses);

// The primary warehouse, or the only one — otherwise the operator chooses.
const defaultWarehouseId = () => {
    const list = warehouses.value;
    if (list.length === 1) return list[0].id;
    return list.find((w) => w.is_primary)?.id || '';
};

const num = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};
const money = (value) => formatMoney(value);
const formatDate = (value) => (value ? String(value).slice(0, 10) : '-');

// Local date: toISOString() is UTC, which dated a delivery booked just after
// midnight in Damascus to the day before.
const todayIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const resetForm = () => {
    form.supplier_id = '';
    form.purchase_order_id = '';
    form.warehouse_id = defaultWarehouseId();
    form.receipt_date = todayIso();
    form.tax_amount = 0;
    form.notes = '';
    form.items = [blankRow()];
    linkedOrder.value = null;
    orderLines.value = [];
};

// Warehouses load alongside the page; a drawer opened first (from a request's
// "receive" button) would otherwise stay without one.
watch(warehouses, () => {
    if (formDrawerVisible.value && !isEditMode.value && !form.warehouse_id) {
        form.warehouse_id = defaultWarehouseId();
    }
});

/* Unsaved-changes guard: a stray click outside the drawer used to discard a
 * half-counted delivery without a word. */
let formSnapshot = '';
const snapshotForm = () => JSON.stringify({ ...form, items: form.items.map(({ key, ...rest }) => rest) });
const markFormClean = () => { formSnapshot = snapshotForm(); };
const formIsDirty = () => formSnapshot !== '' && snapshotForm() !== formSnapshot;

const confirmCloseForm = async (done) => {
    const close = () => (typeof done === 'function' ? done() : (formDrawerVisible.value = false));
    if (!formIsDirty()) return close();
    try {
        await ElMessageBox.confirm(t('rc_unsaved_changes'), t('cancel'), {
            type: 'warning',
            confirmButtonText: t('po_discard_changes'),
            cancelButtonText: t('po_keep_editing'),
        });
        close();
    } catch {
        // Kept editing.
    }
};

/* ------------------------------------------------------------------ *
 * The request a receipt is recorded against
 * ------------------------------------------------------------------ */

// Fetched straight from the API rather than through the purchase-orders
// store: that store backs the orders screen, and loading one supplier's
// requests into it replaced that screen's list and its stage counts.
const supplierOrders = ref([]);
const ordersLoading = ref(false);

const linkedOrder = ref(null);
// The request's lines with what earlier receipts already brought in.
const orderLines = ref([]);

const stageOf = (order) => normalizePurchaseOrderStatus(order?.status);

const remainingOf = (order) => (order?.items || [])
    .reduce((sum, item) => sum + Math.max(0, num(item.quantity) - num(item.received_quantity)), 0);

// Requests that can still take goods, the approved ones first. A received
// request stays listed only while part of it is still owed.
const openOrders = computed(() => {
    const rank = { confirmed: 0, processing: 0, pending: 1, completed: 2 };
    const list = supplierOrders.value.filter((order) => {
        const stage = stageOf(order);
        if (stage === 'cancelled') return false;
        if (stage === 'completed') return remainingOf(order) > 0;
        return true;
    });
    // The request this receipt already links must stay selectable.
    if (linkedOrder.value && !list.some((o) => o.id === linkedOrder.value.id)) list.push(linkedOrder.value);
    return list.sort((a, b) => (rank[stageOf(a)] ?? 3) - (rank[stageOf(b)] ?? 3) || b.id - a.id);
});

const orderStageLabel = (status) => ({
    pending: t('awaiting_approval'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    completed: t('received_state'),
    cancelled: t('sales_status_cancelled'),
}[normalizePurchaseOrderStatus(status)] || status);

const orderStageTag = (status) => ({
    pending: 'warning',
    confirmed: 'primary',
    processing: 'primary',
    completed: 'success',
    cancelled: 'danger',
}[normalizePurchaseOrderStatus(status)] || 'info');

const orderPendingApproval = computed(() => stageOf(linkedOrder.value) === 'pending');
const orderAlreadyReceived = computed(() => orderLines.value.some((line) => line.received_quantity > 0));
const linkedOrderOverdue = computed(() => {
    const due = linkedOrder.value?.due_date;
    return !!due && formatDate(due) < todayIso() && stageOf(linkedOrder.value) !== 'completed';
});

const loadSupplierOrders = async (supplierId) => {
    if (!supplierId) {
        supplierOrders.value = [];
        return;
    }
    ordersLoading.value = true;
    try {
        const res = await purchaseOrdersApi.getAll({ supplier_id: supplierId, per_page: 100 });
        supplierOrders.value = res.data?.data?.orders || [];
    } catch (e) {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    } finally {
        ordersLoading.value = false;
    }
};

const orderLineFor = (productId) => orderLines.value.find((line) => line.product_id === productId);

/** How a receipt line compares with what the request still expects. */
const lineVsOrder = (item) => {
    const line = orderLineFor(item.product_id);
    if (!line) return { state: 'extra' };
    const expected = line.remaining_quantity || line.quantity;
    const diff = Math.abs(num(item.quantity) - expected);
    return {
        ordered: line.quantity,
        before: line.received_quantity,
        diff,
        state: num(item.quantity) < expected ? 'short' : (num(item.quantity) > expected ? 'over' : 'match'),
        orderPrice: line.unit_price,
        priceChanged: item.unit_price !== '' && Math.abs(num(item.unit_price) - num(line.unit_price)) >= 0.005,
    };
};

// Requested lines still owed that nothing on this receipt covers.
const missingOrderLines = computed(() => orderLines.value.filter((line) => line.product_id
    && line.remaining_quantity > 0
    && !form.items.some((item) => item.product_id === line.product_id)));

const rowFromOrderLine = (line, quantity) => blankRow({
    product_id: line.product_id,
    quantity: Math.max(1, quantity),
    unit_price: num(line.unit_price),
    sale_price: line.sale_price != null ? num(line.sale_price) : '',
});

const fillLinesFromOrder = () => {
    const owed = orderLines.value.filter((line) => line.product_id && line.remaining_quantity > 0);
    // Nothing left owed (a repeat delivery): offer the whole request again
    // rather than an empty receipt, and let the lines say "over".
    const source = owed.length ? owed : orderLines.value.filter((line) => line.product_id);
    form.items = source.length
        ? source.map((line) => rowFromOrderLine(line, owed.length ? line.remaining_quantity : line.quantity))
        : [blankRow()];
};

const hasEnteredLines = () => form.items.some((item) => item.product_id);

/**
 * Fills the receipt from its request: supplier, lines at the quantities still
 * owed and the prices agreed, the request's tax on a first delivery, a note
 * naming the request, and the receiving warehouse.
 */
const applyOrder = async (orderId, { askBeforeReplacing = true } = {}) => {
    const response = await purchaseReceiptsApi.getPurchaseOrderDetails(orderId);
    const data = response.data?.data;
    if (!data) return;

    if (data.receivable === false) {
        ElMessage.warning(t('rc_order_cancelled'));
        form.purchase_order_id = '';
        return;
    }

    if (askBeforeReplacing && hasEnteredLines()) {
        try {
            await ElMessageBox.confirm(t('rc_replace_lines_message'), t('linked_purchase_order'), {
                type: 'warning',
                confirmButtonText: t('rc_replace_lines'),
                cancelButtonText: t('rc_keep_lines'),
            });
        } catch {
            // Keep what was typed; still link the request and compare against it.
            askBeforeReplacing = 'keep';
        }
    }

    linkedOrder.value = data.purchase_order;
    orderLines.value = (data.items || []).map((line) => ({
        ...line,
        quantity: num(line.quantity),
        received_quantity: num(line.received_quantity),
        remaining_quantity: num(line.remaining_quantity ?? line.quantity),
    }));
    rememberProducts(orderLines.value);

    if (data.supplier_id && !form.supplier_id) form.supplier_id = data.supplier_id;
    if (askBeforeReplacing !== 'keep') fillLinesFromOrder();

    // Tax belongs to the delivery it was invoiced on; a later delivery of the
    // same request would book it twice.
    if (!orderAlreadyReceived.value && !(data.purchase_order?.receipts_count > 0)) {
        form.tax_amount = num(data.purchase_order?.tax);
    }
    if (!form.notes) form.notes = t('rc_default_note', { number: data.purchase_order?.order_number });
    if (!form.warehouse_id) form.warehouse_id = defaultWarehouseId();

    ElMessage.success(t('items_filled_from_purchase_order'));
};

const refillFromOrder = async () => {
    try {
        await ElMessageBox.confirm(t('rc_replace_lines_message'), t('rc_refill_from_order'), {
            type: 'warning',
            confirmButtonText: t('rc_replace_lines'),
            cancelButtonText: t('cancel'),
        });
    } catch {
        return;
    }
    fillLinesFromOrder();
};

// The common case — everything came as asked — in one click.
const receiveAllAsOrdered = () => {
    form.items.forEach((item) => {
        const line = orderLineFor(item.product_id);
        if (line) item.quantity = Math.max(1, line.remaining_quantity || line.quantity);
    });
    missingOrderLines.value.forEach((line) => restoreOrderLine(line));
};

const restoreOrderLine = (line) => {
    const empty = form.items.findIndex((item) => !item.product_id);
    const row = rowFromOrderLine(line, line.remaining_quantity);
    if (empty !== -1) form.items.splice(empty, 1, row);
    else form.items.push(row);
};

/* ------------------------------------------------------------------ *
 * Lines
 * ------------------------------------------------------------------ */

// A remote search over the whole catalogue: the list used to be the first
// hundred products, so a request's line for any other product showed its id.
const productOptions = ref([]);
const productSearchLoading = ref(false);
let productSearchTimer = null;

const rememberProducts = (items = []) => {
    const known = new Set(productOptions.value.map((p) => p.id));
    const missing = items.map((item) => item.product).filter((p) => p && !known.has(p.id));
    if (missing.length) productOptions.value = [...missing, ...productOptions.value];
};

const searchProducts = (query) => {
    clearTimeout(productSearchTimer);
    if (!query) {
        productOptions.value = productsStore.products;
        rememberProducts(orderLines.value);
        return;
    }
    productSearchLoading.value = true;
    productSearchTimer = setTimeout(async () => {
        try {
            const res = await productsApi.getAll({ search: query, per_page: 100 });
            productOptions.value = res.data.data || [];
        } catch (e) {
            // Keep whatever was showing on a transient failure.
        } finally {
            productSearchLoading.value = false;
        }
    }, 300);
};

const goodsTotal = computed(() => form.items.reduce((sum, item) => sum + num(item.quantity) * num(item.unit_price), 0));
const receiptTotal = computed(() => goodsTotal.value + num(form.tax_amount));
const filledItemCount = computed(() => form.items.filter((item) => item.product_id).length);
const totalUnits = computed(() => form.items.reduce((sum, item) => sum + (item.product_id ? num(item.quantity) : 0), 0));

// Stock is taken in once per product per receipt, so a second line for the
// same product never reached the warehouse.
const duplicateProductIds = computed(() => {
    const seen = new Set();
    const dupes = new Set();
    form.items.forEach(({ product_id: id }) => {
        if (!id) return;
        (seen.has(id) ? dupes : seen).add(id);
    });
    return dupes;
});

const filteredReceipts = computed(() => {
    if (!searchQuery.value.trim()) return store.receipts;
    const query = searchQuery.value.toLowerCase();
    return store.receipts.filter((receipt) => {
        return [
            receipt.receipt_number,
            receipt.supplier?.name,
            receipt.purchase_order?.order_number,
            receipt.receipt_date,
            receipt.notes
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.receipts.length, 5));

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        selectedReceipt.value = res.data.data;
    } catch (e) {
        ElMessage.error(t('failed_to_load_receipt_details'));
    } finally {
        loadingDetail.value = false;
    }
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    markFormClean();
    supplierOrders.value = [];
    formDrawerVisible.value = true;
};

// Reached from a request's "receive" button: opens already filled from it
// instead of leaving the operator to find it in the picker.
const openCreateDrawerForOrder = async (orderId) => {
    isEditMode.value = false;
    resetForm();
    formSnapshot = '';
    formDrawerVisible.value = true;
    loadingForm.value = true;
    try {
        const res = await purchaseOrdersApi.getById(orderId);
        const order = res.data.data;
        form.supplier_id = order.supplier_id;
        await loadSupplierOrders(order.supplier_id);
        form.purchase_order_id = order.id;
        await applyOrder(order.id, { askBeforeReplacing: false });
        // Filled from the request is the starting point, not unsaved work.
        markFormClean();
    } catch (e) {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    } finally {
        loadingForm.value = false;
    }
};

const openEditDrawer = async (id) => {
    isEditMode.value = true;
    editingReceiptId.value = id;
    resetForm();
    formSnapshot = '';
    formDrawerVisible.value = true;
    loadingForm.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        const receipt = res.data.data;
        form.supplier_id = receipt.supplier_id;
        form.purchase_order_id = receipt.purchase_order_id || '';
        form.warehouse_id = receipt.warehouse_id;
        form.receipt_date = formatDate(receipt.receipt_date);
        // Shown but locked, like the lines: the tax was posted with them.
        form.tax_amount = receipt.tax_amount ?? 0;
        form.notes = receipt.notes || '';
        form.items = receipt.items.map(item => blankRow({
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            sale_price: item.sale_price
        }));
        rememberProducts(receipt.items);
        if (receipt.purchase_order) linkedOrder.value = receipt.purchase_order;

        if (form.supplier_id) await loadSupplierOrders(form.supplier_id);
        markFormClean();
    } catch (e) {
        ElMessage.error(t('failed_to_load_receipt_for_edit'));
        formDrawerVisible.value = false;
    } finally {
        loadingForm.value = false;
    }
};

// Purchase requests are scoped to the chosen supplier: another supplier's
// request could never be received against this receipt (the API refuses it).
const handleSupplierChange = async (supplierId) => {
    if (form.purchase_order_id) {
        form.purchase_order_id = '';
        linkedOrder.value = null;
        orderLines.value = [];
        form.items = [blankRow()];
    }

    await loadSupplierOrders(supplierId);

    // One request waiting on this supplier is almost certainly this delivery.
    const receivable = openOrders.value.filter((o) => ['confirmed', 'processing'].includes(stageOf(o)));
    if (receivable.length === 1 && !hasEnteredLines()) {
        form.purchase_order_id = receivable[0].id;
        await handlePurchaseOrderChange(receivable[0].id);
    }
};

// Form Dynamic items grid actions
const addItemRow = () => {
    form.items.push(blankRow());
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const updateItemPrice = (productId, idx) => {
    // A product on the request is priced as agreed there, not at its
    // catalogue cost.
    const line = orderLineFor(productId);
    if (line) {
        form.items[idx].unit_price = num(line.unit_price);
        form.items[idx].sale_price = line.sale_price != null ? num(line.sale_price) : '';
        if (line.remaining_quantity > 0) form.items[idx].quantity = line.remaining_quantity;
        return;
    }
    const prod = productOptions.value.find(p => p.id === productId)
        || productsStore.products.find(p => p.id === productId);
    if (prod) {
        // The receipt's price is what the supplier is paid, so it starts
        // from the product's cost — not its retail price, which is what the
        // line is instead defaulted to sell at.
        form.items[idx].unit_price = prod.cost_price || prod.price;
        form.items[idx].sale_price = prod.price;
    }
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

        // Into the options the row renders from, or it shows the new id.
        productOptions.value = [product, ...productOptions.value];

        const idx = quickAddTargetIndex.value;
        if (idx !== null && form.items[idx]) {
            form.items[idx].product_id = product.id;
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

const handlePurchaseOrderChange = async (purchaseOrderId) => {
    if (!purchaseOrderId) {
        // Unlinked: a direct purchase. What was typed stays.
        linkedOrder.value = null;
        orderLines.value = [];
        return;
    }

    try {
        await applyOrder(purchaseOrderId);
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_load_purchase_order_data'));
    }
};

const apiError = (e, fallback) => {
    const errors = e?.response?.data?.errors;
    const first = errors && Object.values(errors).flat()[0];
    return first || e?.response?.data?.message || fallback;
};

const saveReceipt = async () => {
    if (submittingForm.value || loadingForm.value) return;
    if (!form.supplier_id) {
        ElMessage.warning(t('please_select_supplier_first'));
        return;
    }

    if (!isEditMode.value) {
        if (!form.warehouse_id) {
            ElMessage.warning(t('please_select_receiving_warehouse'));
            return;
        }
        if (form.items.some(item => !item.product_id || !item.quantity || item.unit_price === '' || item.unit_price == null)) {
            ElMessage.warning(t('please_fill_all_item_fields'));
            return;
        }
        if (duplicateProductIds.value.size) {
            const id = [...duplicateProductIds.value][0];
            const product = productOptions.value.find((p) => p.id === id);
            ElMessage.warning(t('po_duplicate_product', { name: product?.name_ar || product?.name || id }));
            return;
        }

        // A delivery that differs from its request is normal, but it should
        // be a decision rather than a slip.
        const off = form.items.filter((item) => linkedOrder.value && lineVsOrder(item).state !== 'match');
        if (off.length || missingOrderLines.value.length) {
            try {
                await ElMessageBox.confirm(
                    t('rc_differs_from_order_message', { lines: off.length + missingOrderLines.value.length }),
                    t('rc_differs_from_order'),
                    { type: 'warning', confirmButtonText: t('save_receipt'), cancelButtonText: t('rc_review_lines') }
                );
            } catch {
                return;
            }
        }
    }

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            // Only the descriptive fields travel: the lines and the warehouse
            // are fixed once the goods are in and their cost is posted.
            await purchaseReceiptsApi.update(editingReceiptId.value, {
                purchase_order_id: form.purchase_order_id || null,
                receipt_date: form.receipt_date,
                notes: form.notes,
            });
            ElMessage.success(t('receipt_updated'));
        } else {
            await purchaseReceiptsApi.create({
                ...form,
                purchase_order_id: form.purchase_order_id || null,
                items: form.items.map(({ key, ...item }) => ({
                    ...item,
                    sale_price: item.sale_price === '' || item.sale_price == null ? null : item.sale_price,
                })),
            });
            ElMessage.success(t('receipt_saved_stock_and_entry'));
        }
        markFormClean();
        formDrawerVisible.value = false;
        await store.fetchReceipts();
    } catch (e) {
        // The API explains precisely why a receipt cannot be saved; echoing a
        // generic failure here would hide the reason and the way forward.
        ElMessage.error(apiError(e, t('failed_to_save_receipt')));
    } finally {
        submittingForm.value = false;
    }
};

const deleteReceipt = async (id) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_receipt'),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await purchaseReceiptsApi.delete(id);
        ElMessage.success(t('receipt_deleted'));
        await store.fetchReceipts();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_receipt'));
    }
};

onBeforeUnmount(() => window.removeEventListener('resize', onResize));

onMounted(async () => {
    window.addEventListener('resize', onResize);
    // A purchase order's detail links here with ?search=<receipt number>.
    if (route.query.search) searchQuery.value = String(route.query.search);
    store.fetchReceipts().catch(() => {});
    suppliersStore.fetchSuppliers().catch(() => {});
    // Purchase orders load once a supplier is chosen (handleSupplierChange) or
    // when editing a receipt that already has one, so the list is always
    // scoped to a supplier instead of dumping every order in the system.
    productsStore.fetchProducts({ per_page: 100 })
        .then(() => {
            productOptions.value = productsStore.products;
            rememberProducts(orderLines.value);
        })
        .catch(() => {});
    inventoryStore.fetchSummary().catch(() => {});

    const orderId = route.query.create_for_order;
    if (orderId) {
        // Drop the query param so a refresh/back-nav doesn't reopen the drawer.
        router.replace({ query: { ...route.query, create_for_order: undefined } });
        openCreateDrawerForOrder(orderId);
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

.receipt-link-txt {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
}

.receipt-link-txt:hover {
    text-decoration: underline;
}

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
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
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--success);
    z-index: 2;
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
    background: var(--success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
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

.alert-success-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem 1.5rem;
    border-radius: var(--radius-md);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    color: #065f46;
}

.alert-success-box i {
    font-size: 1.4rem;
    margin-top: 0.15rem;
}

.alert-success-box h4 {
    margin: 0 0 0.5rem 0;
    font-weight: 700;
}

.alert-success-box ul {
    margin: 0;
    padding-right: 1.25rem;
    font-size: 0.9rem;
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

.quick-add-hint {
    margin: 0 0 1.25rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Form Grid row */
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

.item-grid-row {
    display: block;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}
/* The form in four numbered steps, in the order a delivery is checked. */
.form-step {
    padding-bottom: 1.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.form-step:last-child {
    border-bottom: 0;
    margin-bottom: 0;
}

.form-step-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.form-step-head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-dark);
}

.form-step-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-inline-start: auto;
}

.form-step-tools .el-button + .el-button {
    margin-inline-start: 0;
}

.step-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--accent-blue);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.field-hint {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--text-muted);
}

.order-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.order-option-main {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.order-option small {
    color: var(--text-muted);
}

.linked-order-card {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    border-radius: var(--radius-md);
    padding: 0.9rem 1rem;
}

.linked-order-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.linked-order-head > div {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.linked-order-head i.fa-file-signature {
    color: #1d4ed8;
}

.linked-order-link {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--accent-blue);
    text-decoration: none;
}

.linked-order-facts {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.linked-order-facts div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.linked-order-facts span {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.linked-order-facts strong {
    font-size: 0.9rem;
    color: var(--text-dark);
}

.text-danger {
    color: var(--el-color-danger, #dc2626) !important;
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

.item-grid-row.item-off-order {
    border-style: dashed;
}

.line-vs-order {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.6rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.price-changed {
    color: var(--el-color-warning-dark-2, #b45309);
    font-weight: 600;
}

.line-total-field strong {
    display: flex;
    align-items: center;
    height: 32px;
    color: var(--text-dark);
}

.missing-lines {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: 1px dashed var(--el-color-warning, #e6a23c);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    color: var(--text-medium);
}

.missing-lines .el-button + .el-button {
    margin-inline-start: 0;
}

.financial-row.order-diff {
    font-size: 0.8rem;
    color: var(--text-muted);
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
    flex-wrap: wrap;
    gap: 0.5rem;
    color: var(--text-muted);
}

.footer-total strong {
    font-size: 1.25rem;
    color: var(--accent-blue);
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

@media (max-width: 640px) {
    .linked-order-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .item-row-top,
    .item-row-prices {
        flex-wrap: wrap;
    }

    .price-field {
        max-width: none;
        min-width: 45%;
    }

    .shortcut-hint {
        display: none;
    }

    .footer-actions {
        width: 100%;
    }

    .footer-actions .el-button {
        flex: 1;
    }

    .financial-row {
        width: 100%;
    }
}
</style>
