<template>
    <div class="order-form-page invoice-form-page">
        <!-- ── Top Header ─────────────────────────────────────────── -->
        <AdminPageHeader
            icon="fas fa-file-invoice-dollar text-primary"
            :title="isEdit ? t('edit_invoice') : t('create_invoice')"
            :subtitle="isEdit && loadedInvoice ? `${t('invoice_number')}: ${loadedInvoice.invoice_number}` : t('prepare_invoice_with_products')"
        >
            <template #actions>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_invoices') }}
                </el-button>
                <el-button v-if="!isEdit && items.length" text type="danger" @click="confirmClearInvoice">
                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('clear_the_form') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Top Sticky Status Bar ──────────────────────────────── -->
        <div
            v-if="!loading"
            class="status-bar"
            :class="issues.length ? 'is-blocked' : 'is-ready'"
        >
            <div class="sb-lead">
                <i :class="issues.length ? 'fas fa-circle-exclamation' : 'fas fa-circle-check'"></i>
                <span class="sb-lead-text">
                    {{ issues.length ? (t('so_to_finish') || 'في انتظار استكمال البيانات') : (t('so_ready_to_save') || 'الفاتورة جاهزة للحفظ') }}
                </span>
                <span v-if="issues.length" class="sb-count">{{ issues.length }}</span>
            </div>

            <ul v-if="issues.length" class="sb-issues">
                <li v-for="issue in issues" :key="issue.key">
                    <button type="button" class="sb-issue" @click="goToIssue(issue)">
                        <i class="fas fa-angle-left" aria-hidden="true"></i>
                        {{ issue.text }}
                    </button>
                </li>
            </ul>

            <div class="sb-tail">
                <div class="sb-metrics" aria-live="polite">
                    <span class="sb-metric">{{ t('so_items_n', { count: items.length }) || `${items.length} أصناف` }}</span>
                    <span class="sb-dot" aria-hidden="true">·</span>
                    <span class="sb-metric">{{ t('pieces_count', { count: totalPieces }) || `${totalPieces} قطعة` }}</span>
                    <span class="sb-total">
                        <span class="sb-total-label">{{ t('total') }}</span>
                        <strong>{{ money(total) }}</strong>
                    </span>
                </div>

                <span v-if="shortLines.length" class="sb-flag is-warn">
                    <i class="fas fa-triangle-exclamation"></i>
                    {{ t('so_lines_over_stock_n', { count: shortLines.length }) || `${shortLines.length} بنود غير متوفرة بالكامل` }}
                </span>

                <span v-if="draftSavedAt" class="sb-flag is-muted">
                    <i class="fas fa-floppy-disk"></i>
                    {{ t('so_kept_on_this_device', { time: draftSavedAt }) || `مسودة محفوظة ${draftSavedAt}` }}
                </span>
            </div>
        </div>

        <!-- Loading skeleton -->
        <el-skeleton v-if="loading" :rows="8" animated class="order-skeleton" />

        <!-- ── Main Form Container ────────────────────────────────── -->
        <div v-else ref="formRoot" class="order-form-container panel-card">
            <!-- Server / validation errors -->
            <el-alert
                v-if="formErrors.length"
                :title="t('please_fix_errors')"
                type="error"
                show-icon
                class="mb-4"
                @close="formErrors = []"
            >
                <ul class="error-list">
                    <li v-for="(err, idx) in formErrors" :key="idx">{{ err }}</li>
                </ul>
            </el-alert>

            <el-form label-position="top" class="quote-form order-form" @submit.prevent>
                <!-- ── Head Grid: Client & Due Date ── -->
                <div class="head-grid">
                    <!-- Client Selection -->
                    <el-form-item
                        :label="t('client')"
                        :class="{ 'is-flagged': isFlagged('customer_id') }"
                    >
                        <div class="customer-pick-row">
                            <el-select
                                ref="customerSelectRef"
                                v-model="form.customer_id"
                                filterable
                                clearable
                                remote
                                reserve-keyword
                                :remote-method="searchCustomers"
                                :loading="customersLoading"
                                :placeholder="t('search_or_choose_client')"
                                class="customer-select"
                                popper-class="client-popper"
                                @change="onCustomerPicked"
                                @focus="customerOptions.length <= 1 && searchCustomers('')"
                            >
                                <el-option
                                    v-for="customer in customerOptions"
                                    :key="customer.id"
                                    :label="customer.name"
                                    :value="customer.id"
                                >
                                    <div class="client-option">
                                        <span class="client-option-name">{{ customer.name }}</span>
                                        <span v-if="clientContactLine(customer)" class="client-option-meta">
                                            {{ clientContactLine(customer) }}
                                        </span>
                                    </div>
                                </el-option>

                                <template #loading>
                                    <div class="select-state">
                                        <i class="fas fa-spinner fa-spin text-primary"></i>&nbsp;{{ t('searching') }}…
                                    </div>
                                </template>

                                <template #empty>
                                    <div class="select-empty">
                                        <p>{{ t('no_clients_found') }}</p>
                                        <el-button type="primary" text @click="openQuickCustomer(customerQuery)">
                                            <i class="fas fa-plus"></i>&nbsp;{{ customerQuery ? t('add_client_named', { name: customerQuery }) : t('new_client') }}
                                        </el-button>
                                    </div>
                                </template>
                            </el-select>

                            <el-tooltip :content="t('new_client')" placement="top">
                                <el-button
                                    type="primary"
                                    class="btn-quick-customer"
                                    @click="toggleQuickCustomer"
                                >
                                    <i class="fas fa-user-plus"></i>
                                </el-button>
                            </el-tooltip>
                        </div>

                        <!-- Selected Customer Info & Balance Pill -->
                        <div v-if="selectedCustomer" class="customer-info-strip">
                            <span class="cust-info-item">
                                <i class="fas fa-user"></i>&nbsp;{{ selectedCustomer.name }}
                            </span>
                            <span v-if="selectedCustomer.phone" class="cust-info-item" dir="ltr">
                                <i class="fas fa-phone"></i>&nbsp;{{ selectedCustomer.phone }}
                            </span>
                            <span v-if="clientBalance" class="cust-info-item" :class="clientBalance.tone">
                                <i class="fas fa-wallet"></i>&nbsp;{{ clientBalance.label }}: {{ money(clientBalance.amount) }}
                            </span>
                        </div>

                        <!-- Inline Customer Quick Add Panel -->
                        <transition name="qc-slide">
                            <CustomerQuickAdd
                                v-if="quickCustomerOpen"
                                :seed="quickCustomerSeed"
                                @select="onQuickCustomerSelect"
                                @close="quickCustomerOpen = false"
                            />
                        </transition>
                    </el-form-item>

                    <!-- Due Date with Quick Day Chips -->
                    <el-form-item
                        :label="t('due_date')"
                        :class="{ 'is-flagged': isFlagged('due_date') }"
                    >
                        <el-date-picker
                            ref="deliveryPicker"
                            v-model="form.due_date"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="YYYY-MM-DD"
                            :placeholder="t('due_date')"
                            style="width: 100%"
                        />
                        <div class="quick-days" role="group" :aria-label="t('due_date')">
                            <button
                                v-for="days in [0, 7, 14, 30, 60]"
                                :key="days"
                                type="button"
                                class="chip"
                                :aria-pressed="form.due_date === inDays(days)"
                                :class="{ 'is-on': form.due_date === inDays(days) }"
                                @click="form.due_date = inDays(days)"
                            >
                                {{ days === 0 ? (t('today') || 'اليوم') : (t('qt_days_n', { count: days }) || `${days} يوم`) }}
                            </button>
                        </div>
                    </el-form-item>
                </div>

                <!-- ── Secondary Head Grid: Warehouse, Representative, Payment Method, Status ── -->
                <div class="head-subgrid">
                    <el-form-item :label="t('choose_warehouse') || 'مستودع الصرف الافتراضي'">
                        <el-select
                            v-model="form.default_warehouse_id"
                            filterable
                            clearable
                            :placeholder="t('choose_warehouse_placeholder') || 'اختر مستودع الصرف'"
                            style="width: 100%"
                            @change="onDefaultWarehouseChange"
                        >
                            <el-option
                                v-for="wh in warehouses"
                                :key="wh.id"
                                :label="wh.name"
                                :value="wh.id"
                            >
                                <div class="option-row">
                                    <span>{{ wh.name }}</span>
                                    <span v-if="wh.code" class="option-meta" dir="ltr">{{ wh.code }}</span>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('sales_representative')">
                        <el-select
                            v-model="form.assigned_employee_id"
                            filterable
                            :clearable="!isSalesRep"
                            :disabled="isSalesRep"
                            :placeholder="t('unassigned')"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="employee in salesEmployees"
                                :key="employee.id"
                                :label="employee.name"
                                :value="employee.id"
                            />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('payment_method')">
                        <el-select v-model="form.payment_method" style="width: 100%">
                            <el-option value="cash" :label="t('cash') || 'نقداً'" />
                            <el-option value="transfer" :label="t('bank_transfer') || 'تحويل بنكي'" />
                            <el-option value="card" :label="t('card') || 'بطاقة بنكية / شبكة'" />
                            <el-option value="check" :label="t('check') || 'شيك'" />
                        </el-select>
                    </el-form-item>

                    <el-form-item v-if="isEdit" :label="t('status')">
                        <el-select v-model="form.status" style="width: 100%">
                            <el-option
                                v-for="st in availableStatuses"
                                :key="st"
                                :value="st"
                                :label="statusLabels[st] || st"
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <!-- ── Lines Section ── -->
                <div class="lines-head-top">
                    <div class="lines-title">
                        <h4>{{ t('invoice_items') }}</h4>
                        <span v-if="items.length" class="lines-badge">
                            {{ items.length }} {{ t('product') }} &bull; {{ totalPieces }} {{ t('piece') }}
                        </span>
                        <span v-if="shortLines.length" class="lines-badge is-warn">
                            <i class="fas fa-triangle-exclamation"></i>
                            {{ t('so_over_stock_n', { count: shortLines.length }) || `${shortLines.length} غير متوفرة بالكامل` }}
                        </span>
                    </div>

                    <div v-if="items.length" class="lines-summary-quick">
                        <span class="subtotal-pill">
                            <span class="lbl">{{ t('subtotal') }}:</span>
                            <strong>{{ money(subtotal) }}</strong>
                        </span>
                    </div>
                </div>

                <!-- ── Modern Interactive Search & Category Filter Panel ── -->
                <div ref="quickSearchContainerRef" class="quote-search-panel order-search-panel" @mousedown.stop>
                    <div class="quote-search-header-row">
                        <!-- Category Filter Dropdown -->
                        <div class="search-category-select-wrap">
                            <el-select
                                v-model="selectedCategoryId"
                                clearable
                                filterable
                                :placeholder="t('qt_filter_category') || t('category')"
                                class="category-filter-select"
                                :loading="categoriesLoading"
                                @change="onCategoryFilterChange"
                            >
                                <template #prefix>
                                    <i class="fas fa-folder-tree text-muted"></i>
                                </template>
                                <el-option
                                    :value="null"
                                    :label="t('qt_all_categories') || t('all_categories')"
                                >
                                    <span class="category-all-label">
                                        <i class="fas fa-layer-group"></i> {{ t('qt_all_categories') || t('all_categories') }}
                                    </span>
                                </el-option>
                                <el-option
                                    v-for="cat in categoryOptions"
                                    :key="cat.id"
                                    :value="cat.id"
                                    :label="cat.label"
                                >
                                    <div class="category-option-row" :class="{ 'is-sub': cat.parent_id }">
                                        <i :class="cat.parent_id ? 'fas fa-arrow-turn-down-right cat-sub-icon' : 'fas fa-folder cat-root-icon'"></i>
                                        <span>{{ cat.label }}</span>
                                    </div>
                                </el-option>
                            </el-select>
                        </div>

                        <!-- Fast Search Input -->
                        <div class="search-input-box">
                            <el-input
                                ref="quickSearchInputRef"
                                v-model="quickSearchQuery"
                                :placeholder="t('search_product_placeholder')"
                                clearable
                                class="quote-search-input"
                                @focus="onQuickSearchFocus"
                                @input="onQuickSearchInput"
                                @clear="onQuickSearchClear"
                                @keydown.enter.prevent="onQuickSearchEnter"
                                @keydown.esc="quickSearchOpen = false"
                            >
                                <template #prefix>
                                    <i v-if="!quickSearchLoading" class="fas fa-magnifying-glass search-icon"></i>
                                    <i v-else class="fas fa-spinner fa-spin search-icon text-primary"></i>
                                </template>
                                <template #suffix>
                                    <kbd class="key-hint" title="F2">F2</kbd>
                                </template>
                            </el-input>
                        </div>

                        <!-- Catalog / Suggestions Toggle Button -->
                        <button
                            type="button"
                            class="btn-toggle-catalog"
                            :class="{ 'is-active': quickSearchOpen }"
                            :title="quickSearchOpen ? t('qt_close_search') : (t('so_suggested_products') || t('qt_suggested_products'))"
                            @click.stop.prevent="quickSearchOpen = !quickSearchOpen"
                        >
                            <i class="fas" :class="quickSearchOpen ? 'fa-chevron-up' : 'fa-boxes-stacked'"></i>
                            <span class="btn-text">{{ quickSearchOpen ? t('qt_close_search') : (t('so_suggested_products') || t('qt_suggested_products')) }}</span>
                            <span v-if="quickSearchResults.length" class="count-badge">{{ quickSearchResults.length }}</span>
                        </button>

                        <!-- Quick Add New Product (F4) -->
                        <button
                            type="button"
                            class="btn-new-product"
                            :title="`${t('pqa_title')} (F4)`"
                            @click.stop.prevent="openQuickProduct()"
                        >
                            <i class="fas fa-plus"></i>
                            <span class="btn-text">{{ t('pqa_button') }}</span>
                            <kbd class="key-hint">F4</kbd>
                        </button>
                    </div>

                    <!-- Quick Category Chips Bar -->
                    <div v-if="topCategories.length" class="category-quick-chips">
                        <button
                            type="button"
                            class="cat-chip-btn"
                            :class="{ 'is-active': !selectedCategoryId }"
                            @click="setCategoryFilter(null)"
                        >
                            <i class="fas fa-layer-group"></i>
                            <span>{{ t('qt_all_categories') || t('all_categories') }}</span>
                        </button>
                        <button
                            v-for="cat in topCategories"
                            :key="cat.id"
                            type="button"
                            class="cat-chip-btn"
                            :class="{ 'is-active': selectedCategoryId === cat.id }"
                            @click="setCategoryFilter(cat.id)"
                        >
                            <i class="fas fa-tag"></i>
                            <span>{{ cat.label }}</span>
                        </button>
                    </div>

                    <!-- Suggested Search & Catalog Dropdown Panel (Stays Open While Adding!) -->
                    <transition name="el-zoom-in-top">
                        <div
                            v-if="quickSearchOpen"
                            class="quote-suggested-panel order-suggested-panel"
                            @click.stop
                        >
                            <!-- Top Strip -->
                            <div class="dropdown-top-strip">
                                <div class="strip-left">
                                    <i class="fas fa-boxes-stacked text-primary"></i>
                                    <strong>{{ selectedCategoryName || t('so_suggested_products') || t('qt_suggested_products') }}</strong>
                                    <span class="results-badge">{{ quickSearchResults.length }}</span>
                                    <span v-if="quickSearchQuery" class="search-term-badge">
                                        "{{ quickSearchQuery }}"
                                    </span>
                                </div>
                                <div class="strip-right">
                                    <span class="keep-open-pill">
                                        <i class="fas fa-thumbtack text-success"></i>
                                        {{ t('po_search_hint') || 'القائمة تبقى مفتوحة لتتمكن من إضافة عدة أصناف متتالية بسهولة' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="close-dropdown-btn"
                                        :title="t('close')"
                                        @click.stop="quickSearchOpen = false"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Products List Scroll -->
                            <div class="suggested-list-scroll">
                                <div v-if="quickSearchLoading" class="suggested-loading-state">
                                    <i class="fas fa-circle-notch fa-spin text-primary"></i>
                                    <span>{{ t('qt_searching_catalog') }}</span>
                                </div>

                                <div v-else-if="!quickSearchResults.length" class="suggested-empty-state">
                                    <i class="fas fa-box-open empty-icon"></i>
                                    <p class="empty-title">{{ t('no_products_found') }}</p>
                                    <button type="button" class="empty-new-product" @click.stop="openQuickProduct(quickSearchQuery)">
                                        <i class="fas fa-plus"></i>
                                        <span v-if="quickSearchQuery.trim()">{{ t('pqa_add_named', { name: quickSearchQuery.trim() }) }}</span>
                                        <span v-else>{{ t('pqa_title') }}</span>
                                    </button>
                                    <small class="empty-new-hint">{{ t('pqa_empty_hint') }}</small>
                                </div>

                                <div v-else class="suggested-products-grid">
                                    <template v-for="g in quickSearchGroups" :key="g.key">
                                        <!-- Multi-variant card -->
                                        <div
                                            v-if="g.hasVariants"
                                            class="suggested-item-card is-group"
                                            :class="{ 'item-in-order': getGroupQuantity(g) > 0 }"
                                        >
                                            <div class="group-head">
                                                <div class="item-card-image">
                                                    <EntityImage
                                                        :src="productImageSrc(g.product)"
                                                        type="product"
                                                        :size="54"
                                                        shape="square"
                                                        class="card-img"
                                                    />
                                                    <span v-if="getGroupQuantity(g) > 0" class="in-order-tag" :title="t('quantity')">
                                                        {{ getGroupQuantity(g) }}
                                                    </span>
                                                </div>
                                                <div class="item-card-body">
                                                    <div class="item-card-header">
                                                        <span class="item-name" :title="groupName(g.product)">{{ groupName(g.product) }}</span>
                                                        <span class="variants-count-chip">
                                                            <i class="fas fa-layer-group"></i> {{ t('po_variants_count', g.rows.length) }}
                                                        </span>
                                                    </div>
                                                    <div class="item-card-tags">
                                                        <span v-if="g.product.category?.name_ar || g.product.category_name" class="cat-chip">
                                                            <i class="fas fa-folder"></i> {{ g.product.category?.name_ar || g.product.category_name }}
                                                        </span>
                                                        <span class="pick-size-hint">
                                                            <i class="fas fa-hand-pointer"></i> {{ t('po_pick_size') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="variants-tiles">
                                                <div
                                                    v-for="v in g.rows"
                                                    :key="v.listing_key"
                                                    class="variant-tile"
                                                    :class="{
                                                        'tile-in-order': getItemQuantity(v) > 0,
                                                        'tile-out-stock': v.stock_quantity != null && Number(v.stock_quantity) <= 0,
                                                    }"
                                                >
                                                    <div class="tile-header">
                                                        <span class="tile-size" dir="ltr">{{ v.variant_label || v.sku }}</span>
                                                        <span v-if="v.sku && v.variant_label" class="tile-sku" dir="ltr">{{ v.sku }}</span>
                                                    </div>
                                                    <div class="tile-meta">
                                                        <span class="tile-price">{{ money(v.price) }}</span>
                                                        <span
                                                            v-if="v.stock_quantity != null"
                                                            class="tile-stock"
                                                            :class="{ 'is-low': Number(v.stock_quantity) > 0 && Number(v.stock_quantity) <= 5, 'is-out': Number(v.stock_quantity) <= 0 }"
                                                        >
                                                            {{ t('available') }}: {{ v.stock_quantity }}
                                                        </span>
                                                    </div>
                                                    <OrderCardActions
                                                        :count="getItemQuantity(v)"
                                                        :pending="pendingQtyOf(v)"
                                                        :stock="v.stock_quantity"
                                                        compact
                                                        @add="(qty) => addProductDirect(v, qty)"
                                                        @bump-pending="(d) => bumpPendingQty(v, d)"
                                                        @set-pending="(val) => setPendingQty(v, val)"
                                                        @blur-pending="(el) => normalizePendingQty(v, el)"
                                                        @decrement="decrementProduct(v)"
                                                        @increment="incrementProduct(v)"
                                                        @set-count="(val) => setProductQuantity(v, val)"
                                                        @blur-count="(el) => commitProductQuantity(v, el)"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Single product card -->
                                        <div
                                            v-else
                                            class="suggested-item-card is-single"
                                            :class="{ 'item-in-order': getItemQuantity(g.product) > 0 }"
                                        >
                                            <div class="item-card-image">
                                                <EntityImage
                                                    :src="productImageSrc(g.product)"
                                                    type="product"
                                                    :size="54"
                                                    shape="square"
                                                    class="card-img"
                                                />
                                                <span v-if="getItemQuantity(g.product) > 0" class="in-order-tag" :title="t('quantity')">
                                                    {{ getItemQuantity(g.product) }}
                                                </span>
                                            </div>
                                            <div class="item-card-body">
                                                <div class="item-card-header">
                                                    <span class="item-name" :title="productName(g.product)">{{ productName(g.product) }}</span>
                                                    <span v-if="g.product.sku" class="sku-chip" dir="ltr">{{ g.product.sku }}</span>
                                                </div>
                                                <div class="item-card-tags">
                                                    <span v-if="g.product.category?.name_ar || g.product.category_name" class="cat-chip">
                                                        <i class="fas fa-folder"></i> {{ g.product.category?.name_ar || g.product.category_name }}
                                                    </span>
                                                    <span
                                                        v-if="g.product.stock_quantity != null"
                                                        class="stock-chip"
                                                        :class="{ 'is-low': Number(g.product.stock_quantity) > 0 && Number(g.product.stock_quantity) <= 5, 'is-out': Number(g.product.stock_quantity) <= 0 }"
                                                    >
                                                        <i class="fas fa-boxes-stacked"></i>
                                                        {{ Number(g.product.stock_quantity) <= 0 ? t('out_of_stock') : `${t('available')}: ${g.product.stock_quantity}` }}
                                                    </span>
                                                </div>
                                                <div class="item-card-bottom">
                                                    <div class="price-wrap">
                                                        <strong class="item-price">{{ money(g.product.price) }}</strong>
                                                        <span v-if="g.product.unit" class="unit-text">/ {{ g.product.unit }}</span>
                                                    </div>
                                                    <OrderCardActions
                                                        :count="getItemQuantity(g.product)"
                                                        :pending="pendingQtyOf(g.product)"
                                                        :stock="g.product.stock_quantity"
                                                        @add="(qty) => addProductDirect(g.product, qty)"
                                                        @bump-pending="(d) => bumpPendingQty(g.product, d)"
                                                        @set-pending="(val) => setPendingQty(g.product, val)"
                                                        @blur-pending="(el) => normalizePendingQty(g.product, el)"
                                                        @decrement="decrementProduct(g.product)"
                                                        @increment="incrementProduct(g.product)"
                                                        @set-count="(val) => setProductQuantity(g.product, val)"
                                                        @blur-count="(el) => commitProductQuantity(g.product, el)"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- ── Lines Table ── -->
                <div v-if="!items.length" class="lines-empty">
                    <i class="fas fa-box-open"></i>
                    <span>{{ t('use_search_to_add_products') }}</span>
                </div>

                <div v-else class="lines">
                    <div class="line line--head" aria-hidden="true">
                        <span>{{ t('product') }}</span>
                        <span>{{ t('warehouse') || 'المستودع' }}</span>
                        <span>{{ t('so_unit') || t('unit') }}</span>
                        <span>{{ t('quantity') }}</span>
                        <span>{{ t('unit_price') }}</span>
                        <span class="num">{{ t('total') }}</span>
                        <span />
                    </div>

                    <div
                        v-for="(item, i) in items"
                        :key="item.pick"
                        class="line"
                        :class="{ 'is-flagged': isLineShort(item) }"
                    >
                        <!-- Product info column -->
                        <div class="line-product">
                            <div class="line-product-content">
                                <EntityImage
                                    :src="item.image || getImageUrl(item.image)"
                                    type="product"
                                    :size="42"
                                    shape="square"
                                    class="line-thumb"
                                />
                                <div class="line-info">
                                    <strong :title="item.name">{{ item.name }}</strong>
                                    <div class="line-meta">
                                        <template v-if="!variantInfo(item)">
                                            <span v-if="item.sku" class="option-meta" dir="ltr">{{ item.sku }}</span>
                                            <span v-if="item.variant_label" class="variant-tag">{{ item.variant_label }}</span>
                                        </template>
                                        <span v-if="item.stock !== undefined" :class="['stock-pill', isLineShort(item) ? 'is-over' : '']">
                                            {{ isLineShort(item) ? (t('so_stock_shortage') || 'نقص مخزون') : `${t('available')}: ${item.stock}` }}
                                        </span>
                                    </div>

                                    <VariantDetails
                                        v-if="variantInfo(item)"
                                        :variant="variantInfo(item)"
                                        show-codes
                                        class="line-variant-details"
                                    />

                                    <!-- Switch size chips -->
                                    <div v-if="lineSiblings(item).length > 1" class="line-variant-switch">
                                        <span class="switch-label"><i class="fas fa-tag"></i> {{ t('po_switch_size') }}</span>
                                        <div class="switch-chips">
                                            <button
                                                v-for="v in lineSiblings(item)"
                                                :key="optionKey(v)"
                                                type="button"
                                                class="switch-chip"
                                                :class="{
                                                    'is-active': (v.variant_id || null) === (item.product_variant_id || null),
                                                    'is-out': v.stock_quantity != null && Number(v.stock_quantity) <= 0,
                                                }"
                                                :title="[v.sku, money(v.price)].filter(Boolean).join(' · ')"
                                                @click.stop="switchLineVariant(item, v)"
                                            >
                                                <span class="chip-label" dir="ltr">{{ v.variant_label || v.sku }}</span>
                                                <small v-if="v.stock_quantity != null" class="chip-stock">{{ v.stock_quantity }}</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fields Grid (Warehouse, Unit, Qty, Price) -->
                        <div class="line-fields">
                            <!-- Warehouse Column -->
                            <div class="line-field">
                                <span class="mobile-label">{{ t('warehouse') }}</span>
                                <el-select
                                    v-if="item.allocations && item.allocations[0]"
                                    v-model="item.allocations[0].warehouse_id"
                                    size="small"
                                    class="warehouse-select"
                                    :placeholder="t('sales.choose_source')"
                                    @change="() => onSourceChange(i)"
                                >
                                    <el-option
                                        v-for="wh in warehouses"
                                        :key="wh.id"
                                        :label="wh.name"
                                        :value="wh.id"
                                    />
                                </el-select>
                            </div>

                            <!-- Unit Column -->
                            <div class="line-field">
                                <span class="mobile-label">{{ t('so_unit') }}</span>
                                <el-select
                                    v-if="item.units && item.units.length > 1"
                                    v-model="item.selectedUnit"
                                    value-key="id"
                                    size="small"
                                    class="unit-select"
                                    @change="(u) => onUnitChange(item, u)"
                                >
                                    <el-option
                                        v-for="u in item.units"
                                        :key="u.id ?? u.name"
                                        :label="u.name_ar || u.name"
                                        :value="u"
                                    />
                                </el-select>
                                <span v-else class="unit-badge">{{ item.selectedUnit?.name_ar || item.selectedUnit?.name || t('piece') }}</span>
                            </div>

                            <!-- Quantity Column -->
                            <div class="line-field">
                                <span class="mobile-label">{{ t('quantity') }}</span>
                                <el-input-number
                                    v-if="item.allocations && item.allocations[0]"
                                    v-model="item.allocations[0].quantity"
                                    :min="1"
                                    :step="1"
                                    step-strictly
                                    size="small"
                                    controls-position="right"
                                />
                            </div>

                            <!-- Price Column -->
                            <div class="line-field">
                                <span class="mobile-label">{{ t('unit_price') }}</span>
                                <el-input-number
                                    v-model="item.price"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                />
                            </div>
                        </div>

                        <!-- Line Total -->
                        <div class="line-field line-total-cell">
                            <span class="mobile-label">{{ t('total') }}</span>
                            <span class="num line-total">{{ money(lineTotal(item)) }}</span>
                        </div>

                        <!-- Actions (Duplicate, Delete) -->
                        <div class="line-actions">
                            <el-tooltip :content="t('duplicate_line')" placement="top">
                                <el-button
                                    text
                                    circle
                                    class="action-btn"
                                    @click="duplicateLine(i)"
                                >
                                    <i class="fas fa-copy"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="t('delete')" placement="top">
                                <el-button
                                    text
                                    circle
                                    class="action-btn text-danger"
                                    @click="removeItem(i)"
                                >
                                    <i class="fas fa-trash-can"></i>
                                </el-button>
                            </el-tooltip>
                        </div>
                    </div>
                </div>

                <!-- ── Bottom Grid: Settlement & Notes on left, Totals on right ── -->
                <div class="bottom-grid">
                    <div class="notes-and-terms">
                        <!-- Settlement & Payment info -->
                        <div class="settlement-box">
                            <h5 class="settlement-title">
                                <i class="fas fa-wallet text-primary"></i>&nbsp;{{ t('settlement') || 'التحصيل والدفع' }}
                            </h5>
                            <div class="settlement-row">
                                <label class="field">
                                    <span class="field-label">{{ t('paid_now') || 'المبلغ المقبوض الآن' }}</span>
                                    <el-input-number
                                        v-model="form.paid_amount"
                                        :min="0"
                                        :precision="2"
                                        :controls="false"
                                        size="small"
                                        style="width: 150px;"
                                    />
                                </label>

                                <div class="remaining-badge" :class="remainingTone">
                                    <span class="remaining-lbl">
                                        {{ remaining > 0 ? (t('remaining_on_customer') || 'المتبقي ذمة:') : (remaining < 0 ? (t('customer_credit') || 'رصيد دائن:') : (t('fully_settled') || 'مدفوعة بالكامل')) }}
                                    </span>
                                    <strong class="remaining-val">{{ money(Math.abs(remaining)) }}</strong>
                                </div>
                            </div>

                            <!-- Tafqeet (amount in words) -->
                            <p v-if="total > 0" class="tafqeet-line">
                                <i class="fas fa-receipt text-muted"></i>&nbsp;
                                <span>{{ tafqeet(total) }}</span>
                            </p>
                        </div>

                        <!-- Additional Expenses Editor -->
                        <OrderExpensesEditor
                            v-model="form.expenses"
                            class="mb-3"
                        />

                        <!-- Notes Textarea -->
                        <el-form-item :label="t('notes')">
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="2"
                                maxlength="1000"
                                show-word-limit
                                :placeholder="t('invoice_notes_placeholder') || t('notes')"
                            />
                        </el-form-item>
                    </div>

                    <!-- Totals Box -->
                    <div class="totals" :class="{ 'is-flagged': isFlagged('discount') }">
                        <div class="totals-row">
                            <span>{{ t('subtotal') }}</span>
                            <span class="num">{{ money(subtotal) }}</span>
                        </div>

                        <!-- Discount Row with % vs Amount Mode Switch -->
                        <div class="totals-row discount-row" :class="{ 'has-error': discountError }">
                            <div class="totals-row-label">
                                <span>{{ t('sales.discount_label') || t('discount') }}</span>
                                <el-radio-group
                                    :model-value="form.discount_type"
                                    size="small"
                                    class="discount-mode-switch"
                                    @update:model-value="switchDiscountType"
                                >
                                    <el-radio-button value="percent">%</el-radio-button>
                                    <el-radio-button value="amount">{{ t('sales.discount_as_amount') || 'مبلغ ثابت' }}</el-radio-button>
                                </el-radio-group>
                            </div>
                            <div class="totals-row-input">
                                <el-input-number
                                    v-if="form.discount_type === 'percent'"
                                    ref="discountInput"
                                    v-model="form.discount_percent"
                                    :min="0"
                                    :max="100"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :class="{ 'is-invalid': !!discountError }"
                                />
                                <el-input-number
                                    v-else
                                    ref="discountInput"
                                    v-model="form.discount_amount"
                                    :min="0"
                                    :max="subtotal"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :class="{ 'is-invalid': !!discountError }"
                                />
                            </div>
                        </div>

                        <div v-if="discountError" class="totals-hint is-error">
                            {{ discountError }}
                        </div>
                        <div v-else-if="discountAmount > 0" class="totals-hint is-figure">
                            {{ form.discount_type === 'percent'
                                ? t('sales.discount_equivalent_amount', { amount: money(discountAmount) })
                                : t('sales.discount_equivalent_percent', { amount: money(discountAmount), percent: `${effectiveDiscountRate}%` }) }}
                        </div>

                        <!-- Tax Row -->
                        <div class="totals-row">
                            <span>{{ t('tax_percent') }} (%)</span>
                            <el-input-number
                                v-model="form.tax_percent"
                                :min="0"
                                :max="100"
                                :precision="2"
                                :controls="false"
                                size="small"
                            />
                        </div>
                        <div v-if="taxAmount > 0" class="totals-hint is-figure">
                            + {{ money(taxAmount) }}
                        </div>

                        <!-- Additional Expenses / Shipping -->
                        <div v-if="totalExpenses > 0" class="totals-row">
                            <span>{{ t('additional_charges') || 'مصاريف إضافية' }}</span>
                            <span class="num">+ {{ money(totalExpenses) }}</span>
                        </div>

                        <!-- Grand Total -->
                        <div class="totals-row grand">
                            <span>{{ t('total') }}</span>
                            <span class="num">{{ money(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Action Bar Dock (Bottom Sticky) ── -->
                <div class="action-bar-dock">
                    <div class="action-bar-inner">
                        <div class="dock-left">
                            <el-button @click="goBack">
                                <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_invoices') }}
                            </el-button>
                            <el-button v-if="!isEdit" text type="danger" @click="confirmClearInvoice">
                                <i class="fas fa-trash-can"></i>&nbsp;{{ t('clear_the_form') }}
                            </el-button>
                        </div>

                        <div class="dock-right">
                            <span v-if="issues.length" class="blocker-hint">
                                <i class="fas fa-circle-exclamation text-warning"></i>&nbsp;{{ blockerText }}
                            </span>
                            <el-button
                                type="primary"
                                size="large"
                                :loading="submitting"
                                :disabled="!canSubmit"
                                class="btn-save-primary"
                                @click="submitInvoice"
                            >
                                <i class="fas fa-check"></i>&nbsp;{{ isEdit ? t('save_changes') : t('create_invoice') }}
                            </el-button>
                        </div>
                    </div>
                </div>
            </el-form>
        </div>

        <!-- ── Quick Add Product Modal (F4) ── -->
        <ProductQuickAdd
            v-model="quickProductVisible"
            :initial-name="quickProductSeed"
            @created="onQuickProductCreated"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, onUnmounted, reactive, ref, watch } from 'vue';
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useInvoicesStore } from '@/stores/invoices';
import { useAuthStore } from '@/stores/auth';
import { posApi } from '@/api/pos';
import { categoriesApi } from '@/api/categories';
import api from '@/api';
import { formatMoney, formatNumber, roundUnitPrice, unitPriceDigits } from '@/utils/currency';
import { formatPercent } from '@/utils/numberFormat';
import { tafqeet } from '@/utils/tafqeet';
import { getImageUrl } from '@/utils/imageUrl';
import { resolveImageUrl } from '@/utils/productImages';
import { pickKey, optionKey, baseName, variantLabelOf, variantFieldsOf, lineVariantInfo } from '@/utils/productPick';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import VariantDetails from '@/components/admin/products/VariantDetails.vue';
import OrderCardActions from '@/components/admin/sales/OrderCardActions.vue';
import OrderExpensesEditor from '@/components/admin/sales/OrderExpensesEditor.vue';
import CustomerQuickAdd from '@/components/admin/sales/CustomerQuickAdd.vue';
import ProductQuickAdd from '@/components/admin/sales/ProductQuickAdd.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const { t, locale } = useI18n();
const router = useRouter();
const route = useRoute();
const invoicesStore = useInvoicesStore();
const authStore = useAuthStore();

const invoiceId = route.params.id || null;
const isEdit = computed(() => !!invoiceId);
const isSalesRep = computed(() => !!authStore.user?.is_sales_scoped && !!authStore.user?.employee_id);
const backArrow = computed(() => (locale.value === 'ar' ? 'fa-arrow-right' : 'fa-arrow-left'));
const DRAFT_KEY = 'invoice-draft';

const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;
const money = (val) => formatMoney(val);
const rate = (val) => Math.min(100, Math.max(0, Number(val) || 0));

function inDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + Number(days));
    return d.toISOString().slice(0, 10);
}

const formRoot = ref(null);
const customerSelectRef = ref(null);
const deliveryPicker = ref(null);
const discountInput = ref(null);
const loading = ref(false);
const loadedInvoice = ref(null);
const submitting = ref(false);
const formErrors = ref([]);
const dirty = ref(false);
let draftTimer = null;
let draftTracking = false;
const draftSavedAt = ref('');

/* ------------------------------------------------------------------ *
 * Form State
 * ------------------------------------------------------------------ */

const blankForm = () => ({
    customer_id: null,
    assigned_employee_id: null,
    default_warehouse_id: null,
    payment_method: 'cash',
    due_date: inDays(0),
    discount_type: 'percent',
    discount_percent: 0,
    discount_amount: 0,
    tax_percent: 0,
    paid_amount: 0,
    notes: '',
    status: 'pending',
    expenses: [],
});

const form = reactive(blankForm());
const items = ref([]);

const warehouses = ref([]);
const salesEmployees = ref([]);

/* ------------------------------------------------------------------ *
 * Customers
 * ------------------------------------------------------------------ */

const customers = ref([]);
const selectedCustomer = ref(null);
const customersLoading = ref(false);
const customerQuery = ref('');
let customerSearchToken = 0;

const customerOptions = computed(() => {
    const chosen = selectedCustomer.value;
    if (!chosen || customerQuery.value) return customers.value;
    return customers.value.some((c) => c.id === chosen.id)
        ? customers.value
        : [chosen, ...customers.value];
});

const clientContactLine = (customer) =>
    [customer?.phone, customer?.company].filter(Boolean).join(' · ');

const clientBalance = computed(() => {
    if (!selectedCustomer.value) return null;
    const balance = parseFloat(selectedCustomer.value.balance) || 0;
    if (Math.abs(balance) < 0.005) return null;
    return {
        amount: Math.abs(balance),
        tone: balance > 0 ? 'is-credit' : 'is-owing',
        label: balance > 0 ? (t('customer_credit') || 'رصيد دائن') : (t('customer_balance') || 'رصيد سابق'),
    };
});

const searchCustomers = async (query = '') => {
    customerQuery.value = query;
    const token = ++customerSearchToken;
    customersLoading.value = true;
    try {
        const { data } = await posApi.customers({ q: query, per_page: 30 });
        if (token === customerSearchToken) {
            customers.value = data?.data || (Array.isArray(data) ? data : []);
        }
    } catch {
        if (token === customerSearchToken) customers.value = [];
    } finally {
        if (token === customerSearchToken) customersLoading.value = false;
    }
};

const onCustomerPicked = (id) => {
    selectedCustomer.value = customers.value.find((c) => c.id === id) || null;
    dirty.value = true;
};

// Inline Quick Customer Add
const quickCustomerOpen = ref(false);
const quickCustomerSeed = ref('');

const toggleQuickCustomer = () => {
    quickCustomerOpen.value = !quickCustomerOpen.value;
    quickCustomerSeed.value = customerQuery.value || '';
};

const openQuickCustomer = (seed = '') => {
    quickCustomerSeed.value = seed;
    quickCustomerOpen.value = true;
};

const onQuickCustomerSelect = (customer) => {
    selectedCustomer.value = customer;
    customers.value = [customer, ...customers.value.filter((c) => c.id !== customer.id)];
    form.customer_id = customer.id;
    quickCustomerOpen.value = false;
    dirty.value = true;
    ElMessage.success(t('client_added_and_selected', { name: customer.name }) || 'تمت إضافة العميل واختياره');
};

/* ------------------------------------------------------------------ *
 * Categories & Search
 * ------------------------------------------------------------------ */

const categories = ref([]);
const categoriesLoading = ref(false);
const selectedCategoryId = ref(null);
const quickSearchContainerRef = ref(null);
const quickSearchInputRef = ref(null);
const quickSearchQuery = ref('');
const quickSearchResults = ref([]);
const quickSearchLoading = ref(false);
const quickSearchOpen = ref(false);
let quickSearchTimer = null;

const categoryOptions = computed(() => {
    return categories.value.map((c) => ({
        id: c.id,
        label: c.name_ar || c.name || c.name_en,
        parent_id: c.parent_id,
    }));
});

const topCategories = computed(() => categoryOptions.value.slice(0, 8));

const selectedCategoryName = computed(() => {
    if (!selectedCategoryId.value) return '';
    return categoryOptions.value.find((c) => c.id === selectedCategoryId.value)?.label || '';
});

const fetchCategories = async () => {
    categoriesLoading.value = true;
    try {
        const res = await categoriesApi.getAll({ per_page: 500 });
        categories.value = res.data?.data || (Array.isArray(res.data) ? res.data : []);
    } catch {
        categories.value = [];
    } finally {
        categoriesLoading.value = false;
    }
};

const setCategoryFilter = (id) => {
    selectedCategoryId.value = id;
    executeQuickSearch();
};

const onCategoryFilterChange = () => {
    executeQuickSearch();
};

const onQuickSearchFocus = () => {
    quickSearchOpen.value = true;
    if (!quickSearchResults.value.length) executeQuickSearch();
};

const onQuickSearchInput = () => {
    quickSearchOpen.value = true;
    clearTimeout(quickSearchTimer);
    quickSearchTimer = setTimeout(executeQuickSearch, 250);
};

const onQuickSearchClear = () => {
    quickSearchQuery.value = '';
    executeQuickSearch();
};

const onQuickSearchEnter = () => {
    if (quickSearchResults.value.length === 1) {
        addProductDirect(quickSearchResults.value[0], pendingQtyOf(quickSearchResults.value[0]));
        quickSearchQuery.value = '';
    }
};

const executeQuickSearch = async () => {
    quickSearchLoading.value = true;
    try {
        const params = { expand_variants: 1 };
        if (quickSearchQuery.value && quickSearchQuery.value.trim().length >= 1) {
            params.q = quickSearchQuery.value.trim();
        }
        if (selectedCategoryId.value) {
            params.category_id = selectedCategoryId.value;
        }
        const res = await posApi.productLookup(params);
        const data = res.data?.data || [];
        quickSearchResults.value = (Array.isArray(data) ? data : []).map((p) => ({
            ...p,
            listing_key: optionKey(p),
        }));
        rememberVariantRows(quickSearchResults.value);
    } catch {
        quickSearchResults.value = [];
    } finally {
        quickSearchLoading.value = false;
    }
};

const quickSearchGroups = computed(() => {
    const groups = new Map();
    quickSearchResults.value.forEach((product) => {
        const id = product.id ?? product.product_id;
        if (!groups.has(id)) {
            groups.set(id, { key: String(id), product, rows: [] });
        }
        groups.get(id).rows.push(product);
    });
    return [...groups.values()].map((g) => ({
        ...g,
        hasVariants: g.rows.some((r) => r.variant_id || r.product_variant_id),
    }));
});

/* ------------------------------------------------------------------ *
 * Variants & Sibling Sizes
 * ------------------------------------------------------------------ */

const { lineSiblings, rememberVariantRows, ensureVariantsLoaded } = useVariantSiblings({
    async fetchProduct(productId) {
        const { data } = await posApi.productLookup({ product_id: productId, expand_variants: 1 });
        return data?.data || [];
    },
});

const variantInfo = (line) => lineVariantInfo(line);

const switchLineVariant = (line, row) => {
    const pick = optionKey(row);
    if (pick === line.pick) return;

    const other = items.value.find((l) => l !== line && l.pick === pick);
    if (other) {
        if (other.allocations && line.allocations) {
            other.allocations[0].quantity += line.allocations[0].quantity;
        }
        const index = items.value.indexOf(line);
        if (index !== -1) items.value.splice(index, 1);
        ElMessage.success(t('so_qty_now', { name: other.name, count: other.allocations?.[0]?.quantity }));
        return;
    }

    const multiplier = toNum(line.selectedUnit?.price_multiplier) || 1;
    const priceUntouched = !toNum(line.base_price) || roundUnitPrice(line.price) === roundUnitPrice(toNum(line.base_price) * multiplier);
    line.pick = pick;
    line.product_variant_id = row.variant_id || null;
    line.name = productName(row);
    line.sku = row.sku || '';
    line.variant_label = row.variant_label || '';
    line.variant = variantFieldsOf(row);
    line.image = productImageSrc(row) || line.image;
    line.stock = row.stock_quantity != null ? Number(row.stock_quantity) : undefined;
    line.base_price = parseFloat(row.price) || 0;
    if (priceUntouched) line.price = roundUnitPrice(line.base_price * multiplier);
    dirty.value = true;
};

/* ------------------------------------------------------------------ *
 * Line Item helpers & Quantities
 * ------------------------------------------------------------------ */

const pendingQty = reactive({});
const productKey = (product) => product.listing_key || optionKey(product);
const pendingQtyOf = (product) => toNum(pendingQty[productKey(product)]) || 1;

const setPendingQty = (product, raw) => {
    const key = productKey(product);
    const n = Math.floor(toNum(raw));
    if (n >= 1) pendingQty[key] = n;
};

const bumpPendingQty = (product, delta) => {
    const key = productKey(product);
    pendingQty[key] = Math.max(1, pendingQtyOf(product) + delta);
};

const normalizePendingQty = (product, input) => {
    const shown = pendingQtyOf(product);
    if (input && String(input.value) !== String(shown)) input.value = shown;
};

const lineFor = (product) => {
    if (!product) return undefined;
    const pick = optionKey(product);
    return items.value.find((l) => l.pick === pick);
};

const getItemQuantity = (product) => {
    const line = lineFor(product);
    return line ? (line.allocations?.[0]?.quantity || 0) : 0;
};

const getGroupQuantity = (group) => {
    return group.rows.reduce((sum, r) => sum + getItemQuantity(r), 0);
};

const setProductQuantity = (product, raw) => {
    const line = lineFor(product);
    if (!line || !line.allocations?.length) return;
    const n = Math.floor(toNum(raw));
    if (n >= 1) line.allocations[0].quantity = n;
    dirty.value = true;
};

const commitProductQuantity = (product, input) => {
    const line = lineFor(product);
    if (!line) return;
    const index = items.value.indexOf(line);
    if (index === -1) return;
    const n = Math.floor(toNum(input?.value));
    if (n < 1) {
        items.value.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند');
    } else {
        line.allocations[0].quantity = n;
    }
    dirty.value = true;
};

const decrementProduct = (product) => {
    const line = lineFor(product);
    if (!line || !line.allocations?.length) return;
    const index = items.value.indexOf(line);
    if (index === -1) return;

    if (line.allocations[0].quantity > 1) {
        line.allocations[0].quantity -= 1;
    } else {
        items.value.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند');
    }
    dirty.value = true;
};

const incrementProduct = (product) => {
    const line = lineFor(product);
    if (!line || !line.allocations?.length) return;
    line.allocations[0].quantity += 1;
    dirty.value = true;
};

const addProductDirect = (product, quantity = 1) => {
    const pick = optionKey(product);
    const existing = items.value.find((l) => l.pick === pick);
    if (product.variant_id) ensureVariantsLoaded(product.id);

    const targetWarehouseId = form.default_warehouse_id || (warehouses.value[0]?.id || null);

    if (existing) {
        existing.allocations[0].quantity += Math.max(1, quantity);
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.allocations[0].quantity }));
    } else {
        const defaultUnit = {
            id: null,
            name: product.unit || t('piece'),
            name_ar: product.unit || t('piece'),
            base_unit_multiplier: 1,
            price_multiplier: 1,
        };

        const line = reactive({
            pick,
            product_id: product.id ?? product.product_id,
            product_variant_id: product.variant_id ?? product.product_variant_id ?? null,
            variant_label: product.variant_label || variantLabelOf(product),
            variant: variantFieldsOf(product),
            name: productName(product),
            sku: product.sku || '',
            image: productImageSrc(product),
            price: parseFloat(product.price) || 0,
            base_price: parseFloat(product.price) || 0,
            stock: product.stock_quantity != null ? Number(product.stock_quantity) : undefined,
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            allocations: [
                {
                    warehouse_id: targetWarehouseId,
                    quantity: Math.max(1, quantity),
                },
            ],
            sources: [],
            loadingStock: false,
        });

        items.value.unshift(line);
        loadUnits(line);
        ElMessage.success(t('so_added', { name: line.name }));
    }

    pendingQty[productKey(product)] = 1;
    dirty.value = true;
};

const duplicateLine = (index) => {
    const original = items.value[index];
    if (!original) return;
    const copy = reactive({
        ...original,
        pick: `${original.pick}-copy-${Date.now()}`,
        allocations: original.allocations.map((a) => ({ ...a })),
    });
    items.value.splice(index + 1, 0, copy);
    dirty.value = true;
    ElMessage.success(t('line_duplicated') || 'تم تكرار البند بنجاح');
};

const removeItem = (index) => {
    items.value.splice(index, 1);
    dirty.value = true;
};

const loadUnits = async (line) => {
    try {
        const { data } = await api.get(`/admin/products/${line.product_id}/units`);
        const rows = data?.data || [];
        if (!rows.length) return;
        const units = rows.map((u) => ({
            id: u.id,
            name: u.name,
            name_ar: u.name_ar || u.name,
            base_unit_multiplier: parseFloat(u.base_unit_multiplier) || 1,
            price_multiplier: parseFloat(u.price_multiplier) || 1,
            is_default: !!u.is_default,
        }));
        line.units = [line.selectedUnit, ...units.filter((u) => u.id !== line.selectedUnit?.id)];
        const defaultU = units.find((u) => u.is_default);
        if (defaultU) {
            line.selectedUnit = defaultU;
            if (line.base_price) line.price = roundUnitPrice(line.base_price * defaultU.price_multiplier);
        }
    } catch {
        /* ignore */
    }
};

const onUnitChange = (line, unit) => {
    line.selectedUnit = unit;
    if (line.base_price && unit?.price_multiplier) {
        line.price = roundUnitPrice(line.base_price * unit.price_multiplier);
    }
    dirty.value = true;
};

const onDefaultWarehouseChange = (whId) => {
    if (!whId) return;
    items.value.forEach((item) => {
        if (item.allocations && item.allocations.length) {
            item.allocations[0].warehouse_id = whId;
        }
    });
    dirty.value = true;
};

const onSourceChange = (index) => {
    dirty.value = true;
};

/* ------------------------------------------------------------------ *
 * Naming & Images
 * ------------------------------------------------------------------ */

const productName = (p) => {
    if (!p) return '';
    return p.name_ar || p.name || p.name_en || t('unknown_product');
};

const groupName = (p) => baseName(p) || productName(p);

const productImageSrc = (p) => {
    if (!p) return '';
    const img = p.image || p.image_main || p.images?.[0]?.url || p.images?.[0]?.path;
    return img ? resolveImageUrl(img) : '';
};

/* ------------------------------------------------------------------ *
 * Quick Add Product Modal (F4)
 * ------------------------------------------------------------------ */

const quickProductVisible = ref(false);
const quickProductSeed = ref('');

const openQuickProduct = (seed = '') => {
    quickProductSeed.value = String(seed || '').trim();
    quickProductVisible.value = true;
};

const onQuickProductCreated = (product) => {
    addProductDirect(product, 1);
    quickProductVisible.value = false;
};

/* ------------------------------------------------------------------ *
 * Totals & Calculations
 * ------------------------------------------------------------------ */

const lineTotal = (item) => {
    const qty = item.allocations?.[0]?.quantity || 0;
    return round2(toNum(item.price) * toNum(qty));
};

const totalPieces = computed(() => {
    return items.value.reduce((sum, item) => {
        const qty = item.allocations?.[0]?.quantity || 0;
        const mult = item.selectedUnit?.base_unit_multiplier || 1;
        return sum + (toNum(qty) * toNum(mult));
    }, 0);
});

const subtotal = computed(() => {
    return round2(items.value.reduce((sum, item) => sum + lineTotal(item), 0));
});

const totalExpenses = computed(() => {
    return round2(form.expenses.reduce((sum, exp) => sum + toNum(exp.amount), 0));
});

const discountAmount = computed(() => {
    if (form.discount_type === 'amount') {
        return round2(Math.min(Math.max(0, toNum(form.discount_amount)), Math.max(0, subtotal.value)));
    }
    return round2(Math.max(0, subtotal.value) * Math.min(100, Math.max(0, toNum(form.discount_percent))) / 100);
});

const effectiveDiscountRate = computed(() =>
    subtotal.value > 0 ? Math.round((discountAmount.value / subtotal.value) * 10000) / 100 : 0
);

const discountError = computed(() => {
    if (form.discount_type === 'amount') {
        const amount = toNum(form.discount_amount);
        if (amount < 0) return t('sales.discount_negative');
        if (amount > 0 && amount - subtotal.value > 0.005) {
            return t('sales.discount_amount_exceeds_subtotal', {
                discount: money(amount),
                subtotal: money(subtotal.value),
            });
        }
        return '';
    }
    const val = toNum(form.discount_percent);
    if (val < 0 || val > 100) return t('sales.discount_percent_out_of_range');
    return '';
});

const switchDiscountType = (type) => {
    if (type === form.discount_type) return;
    if (type === 'amount') {
        form.discount_amount = discountAmount.value;
    } else {
        form.discount_percent = effectiveDiscountRate.value;
    }
    form.discount_type = type;
};

const taxAmount = computed(() =>
    round2(Math.max(0, subtotal.value - discountAmount.value) * rate(form.tax_percent) / 100)
);

const total = computed(() =>
    Math.max(0, subtotal.value - discountAmount.value + taxAmount.value + totalExpenses.value)
);

const remaining = computed(() => round2(total.value - toNum(form.paid_amount)));

const remainingTone = computed(() => {
    if (Math.abs(remaining.value) < 0.005) return 'is-settled';
    return remaining.value > 0 ? 'is-owing' : 'is-credit';
});

/* ------------------------------------------------------------------ *
 * Validation & Issues Checklist
 * ------------------------------------------------------------------ */

const isLineShort = (item) => {
    if (item.stock == null) return false;
    const qty = item.allocations?.[0]?.quantity || 0;
    const mult = item.selectedUnit?.base_unit_multiplier || 1;
    return (toNum(qty) * toNum(mult)) > Number(item.stock);
};

const shortLines = computed(() => items.value.filter(isLineShort));

const issues = computed(() => {
    const list = [];
    if (!items.value.length) {
        list.push({ key: 'items', target: 'items', text: t('qt_need_lines') || 'يرجى إضافة بند واحد على الأقل' });
    }
    if (discountError.value) {
        list.push({ key: 'discount', target: 'discount', text: discountError.value });
    }
    return list;
});

const blockerText = computed(() => issues.value[0]?.text || '');

const canSubmit = computed(() => issues.value.length === 0 && !submitting.value);

const isFlagged = (target) => issues.value.some((issue) => issue.target === target);

const goToIssue = (issue) => {
    let el = null;
    if (issue.target === 'items') {
        el = quickSearchInputRef.value?.$el || formRoot.value?.querySelector('.quote-search-panel');
    } else if (issue.target === 'discount') {
        el = discountInput.value?.$el;
    }
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const focusable = el?.querySelector('input, select, textarea') || el;
    focusable?.focus?.();
};

/* ------------------------------------------------------------------ *
 * Status Transitions & Labels
 * ------------------------------------------------------------------ */

const statusTransitions = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: [],
};

const statusLabels = {
    pending: t('sales_status_pending') || 'مسودة',
    confirmed: t('sales_status_confirmed') || 'مؤكدة',
    processing: t('sales_status_processing') || 'قيد التجهيز',
    shipped: t('sales_status_shipped') || 'مشحونة',
    delivered: t('sales_status_delivered') || 'تم التسليم',
    cancelled: t('sales_status_cancelled') || 'ملغاة',
};

const availableStatuses = computed(() => [form.status, ...(statusTransitions[form.status] || [])]);

/* ------------------------------------------------------------------ *
 * Draft Auto-saving & Restoring
 * ------------------------------------------------------------------ */

const saveDraft = () => {
    if (isEdit.value) return;
    if (!items.value.length && !form.customer_id) {
        clearDraft();
        return;
    }
    try {
        const draftData = {
            saved_at: new Date().toISOString(),
            form: {
                ...form,
            },
            items: items.value.map((line) => ({
                ...line,
                units: [line.selectedUnit].filter(Boolean),
            })),
            selectedCustomer: selectedCustomer.value,
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        draftSavedAt.value = new Date().toLocaleTimeString(
            locale.value === 'ar' ? 'ar' : 'en-GB',
            { hour: '2-digit', minute: '2-digit' }
        );
    } catch {
        /* storage unavailable */
    }
};

const clearDraft = () => {
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignore */ }
    draftSavedAt.value = '';
};

const offerDraft = async () => {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft = null; }
    if (!draft?.items?.length) return;

    try {
        await ElMessageBox.confirm(
            t('so_restore_draft_message', { count: draft.items.length }) || `لديك مسودة غير مكتملة تحتوي على ${draft.items.length} صنف. هل تريد استرجاعها؟`,
            t('so_restore_draft_title') || 'استرجاع المسودة',
            {
                type: 'info',
                confirmButtonText: t('so_restore_draft') || 'استرجاع المسودة',
                cancelButtonText: t('so_discard_draft') || 'بدء فاتورة جديدة',
                distinguishCancelAndClose: true,
            }
        );
    } catch (action) {
        if (action === 'cancel') clearDraft();
        return;
    }

    const saved = draft.form || {};
    if (draft.saved_at) {
        const when = new Date(draft.saved_at);
        if (!Number.isNaN(when.getTime())) {
            draftSavedAt.value = when.toLocaleTimeString(
                locale.value === 'ar' ? 'ar' : 'en-GB',
                { hour: '2-digit', minute: '2-digit' }
            );
        }
    }

    Object.assign(form, {
        customer_id: saved.customer_id || null,
        assigned_employee_id: saved.assigned_employee_id || null,
        default_warehouse_id: saved.default_warehouse_id || null,
        payment_method: saved.payment_method || 'cash',
        due_date: saved.due_date || inDays(0),
        discount_type: saved.discount_type || 'percent',
        discount_percent: toNum(saved.discount_percent),
        discount_amount: toNum(saved.discount_amount),
        tax_percent: toNum(saved.tax_percent),
        paid_amount: toNum(saved.paid_amount),
        notes: saved.notes || '',
        status: saved.status || 'pending',
        expenses: saved.expenses || [],
    });

    items.value = (draft.items || []).map((line) => {
        const unit = line.selectedUnit || {
            id: null,
            name: t('piece'),
            name_ar: t('piece'),
            base_unit_multiplier: 1,
            price_multiplier: 1,
        };
        const l = reactive({
            ...line,
            selectedUnit: unit,
            units: [unit],
        });
        loadUnits(l);
        return l;
    });

    if (draft.selectedCustomer) {
        selectedCustomer.value = draft.selectedCustomer;
        customers.value = [draft.selectedCustomer];
    }
};

watch(
    () => [
        form.customer_id,
        items.value,
        form.discount_type,
        form.discount_percent,
        form.discount_amount,
        form.tax_percent,
        form.paid_amount,
        form.default_warehouse_id,
        form.notes,
        form.due_date,
        form.payment_method,
    ],
    () => {
        dirty.value = true;
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 600);
    },
    { deep: true }
);

/* ------------------------------------------------------------------ *
 * Load Existing Invoice
 * ------------------------------------------------------------------ */

const loadInvoice = async () => {
    loading.value = true;
    try {
        const res = await api.get(`/admin/invoices/${invoiceId}`);
        const invoice = res.data?.data || res.data;
        loadedInvoice.value = invoice;
        if (!invoice) return;

        form.customer_id = invoice.customer_id;
        if (invoice.customer) {
            selectedCustomer.value = invoice.customer;
            customers.value = [invoice.customer];
        }
        form.assigned_employee_id = invoice.assigned_employee_id ?? null;
        form.default_warehouse_id = invoice.warehouse_id || null;
        form.payment_method = invoice.payment_method || 'cash';
        form.due_date = invoice.due_date ? String(invoice.due_date).slice(0, 10) : inDays(0);
        form.paid_amount = parseFloat(invoice.paid_amount) || 0;
        form.status = invoice.status || 'pending';
        form.notes = invoice.notes || '';

        const savedSubtotal = parseFloat(invoice.subtotal) || 0;
        const savedDiscount = parseFloat(invoice.discount) || 0;
        const savedTax = parseFloat(invoice.tax) || 0;

        if (invoice.discount_percent !== null && invoice.discount_percent !== undefined) {
            form.discount_type = 'percent';
            form.discount_percent = invoice.discount_percent;
            form.discount_amount = savedDiscount;
        } else {
            form.discount_type = savedDiscount > 0 ? 'amount' : 'percent';
            form.discount_amount = savedDiscount;
            form.discount_percent = savedSubtotal > 0 ? Math.round((savedDiscount / savedSubtotal) * 10000) / 100 : 0;
        }

        form.tax_percent = invoice.tax_percent ?? (savedSubtotal > 0 ? Math.round((savedTax / Math.max(1, savedSubtotal - savedDiscount)) * 10000) / 100 : 0);

        form.expenses = (invoice.expenses || []).map((exp) => ({
            id: exp.id || null,
            category: exp.category || 'shipping',
            description: exp.description || '',
            amount: parseFloat(exp.amount) || 0,
            status: exp.status || 'paid',
            notes: exp.notes || '',
        }));

        items.value = (invoice.items || []).map((item) => {
            const unit = {
                id: item.product_unit_id ?? null,
                name: item.unit_name || item.product?.unit || t('piece'),
                name_ar: item.unit_name || item.product?.unit || t('piece'),
                base_unit_multiplier: 1,
                price_multiplier: 1,
            };
            const line = reactive({
                pick: pickKey(item.product_id, item.product_variant_id),
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                variant_label: item.variant?.label || variantLabelOf(item.variant),
                variant: item.variant ? variantFieldsOf(item.variant) : null,
                name: (item.product_variant_id && item.product?.name_ar) || item.product_name || item.product?.name_ar || t('unknown_product'),
                sku: item.variant?.sku || item.product?.sku || '',
                image: item.product?.image_main || null,
                price: parseFloat(item.unit_price) || 0,
                base_price: parseFloat(item.unit_price) || 0,
                stock: item.product?.stock_quantity || 0,
                selectedUnit: unit,
                units: [unit],
                allocations: [
                    {
                        warehouse_id: item.warehouse_id || invoice.warehouse_id || null,
                        quantity: item.quantity || 1,
                    },
                ],
            });
            loadUnits(line);
            return line;
        });

        await nextTick();
        dirty.value = false;
    } catch {
        ElMessage.error(t('failed_to_load_invoice') || 'فشل تحميل بيانات الفاتورة');
    } finally {
        loading.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Submit
 * ------------------------------------------------------------------ */

const submitInvoice = async () => {
    if (!canSubmit.value) return;
    formErrors.value = [];
    submitting.value = true;

    try {
        const payload = {
            customer_id: form.customer_id,
            warehouse_id: form.default_warehouse_id || null,
            assigned_employee_id: form.assigned_employee_id || null,
            payment_method: form.payment_method || 'cash',
            due_date: form.due_date || null,
            discount_percent: form.discount_type === 'percent' ? rate(form.discount_percent) : null,
            tax_percent: rate(form.tax_percent),
            discount: discountAmount.value,
            tax: taxAmount.value,
            paid_amount: form.paid_amount || 0,
            notes: form.notes || null,
            status: form.status,
            items: items.value.map((item) => ({
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                quantity: item.allocations?.[0]?.quantity || 1,
                unit_price: roundUnitPrice(item.price),
                warehouse_id: item.allocations?.[0]?.warehouse_id || form.default_warehouse_id || null,
                product_unit_id: item.selectedUnit?.id || null,
            })),
            expenses: (form.expenses || [])
                .filter((exp) => exp.description && Number(exp.amount) > 0)
                .map((exp) => ({
                    category: exp.category || 'other',
                    description: exp.description,
                    amount: Number(exp.amount),
                    status: exp.status || 'paid',
                    notes: exp.notes || null,
                })),
        };

        if (isEdit.value) {
            await invoicesStore.updateInvoice(invoiceId, payload);
            ElMessage.success(t('invoice_updated_successfully') || 'تم تحديث الفاتورة بنجاح');
        } else {
            const created = await invoicesStore.createInvoice(payload);
            const settlement = created?.settlement;
            const parts = [t('invoice_created_successfully') || 'تم إصدار الفاتورة بنجاح'];
            if (settlement?.payment_number) {
                parts.push(`${t('payment')} ${settlement.payment_number}`);
            }
            ElMessage.success({ message: parts.join(' • '), duration: 4000 });
            clearDraft();
        }

        router.push('/admin/sales/invoices');
    } catch (err) {
        if (err.response?.status === 422) {
            const errors = err.response?.data?.errors ?? {};
            formErrors.value = Object.values(errors).flat();
        } else {
            formErrors.value = [err.response?.data?.message || t('error_saving_invoice') || 'فشل حفظ الفاتورة'];
        }
    } finally {
        submitting.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Lifecycle & Keyboard Shortcuts
 * ------------------------------------------------------------------ */

const confirmClearInvoice = async () => {
    try {
        await ElMessageBox.confirm(
            t('confirm_clear_order') || 'هل أنت متأكد من تفريغ كافة بنود الفاتورة؟',
            t('confirm') || 'تأكيد',
            { type: 'warning' }
        );
        items.value = [];
        clearDraft();
        dirty.value = false;
        ElMessage.info(t('form_cleared') || 'تم تفريغ الفاتورة');
    } catch {
        /* cancel */
    }
};

const goBack = async () => {
    if (dirty.value && items.value.length) {
        try {
            await ElMessageBox.confirm(
                t('confirm_leave_unsaved_order') || 'لديك تعديلات غير محفوظة. هل تريد المغادرة؟',
                t('confirm') || 'تأكيد',
                { type: 'warning' }
            );
        } catch {
            return;
        }
    }
    router.push('/admin/sales/invoices');
};

const handleGlobalKeydown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        quickSearchInputRef.value?.focus();
        quickSearchOpen.value = true;
    } else if (e.key === 'F4') {
        e.preventDefault();
        openQuickProduct();
    }
};

onMounted(async () => {
    window.addEventListener('keydown', handleGlobalKeydown);

    try {
        const [whRes, empRes] = await Promise.all([
            api.get('/admin/warehouses'),
            api.get('/admin/employees', { params: { per_page: 200 } }),
        ]);
        const whData = whRes.data?.data || whRes.data || [];
        warehouses.value = Array.isArray(whData) ? whData : [];
        if (!form.default_warehouse_id && warehouses.value.length) {
            form.default_warehouse_id = warehouses.value[0].id;
        }

        const empData = empRes.data?.data || empRes.data || [];
        salesEmployees.value = Array.isArray(empData) ? empData : [];
    } catch {
        /* ignore */
    }

    fetchCategories();

    if (isEdit.value) {
        await loadInvoice();
    } else {
        await offerDraft();
        draftTracking = true;
        nextTick(() => quickSearchInputRef.value?.focus());
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    clearTimeout(quickSearchTimer);
    clearTimeout(draftTimer);
});

onBeforeRouteLeave((to, from, next) => {
    if (!isEdit.value && dirty.value && items.value.length) {
        saveDraft();
    }
    next();
});
</script>

<style scoped>
/* ── Page Layout ─────────────────────────────────────────────────── */
.order-form-page {
    padding: 1.25rem 1.5rem 5rem;
    max-width: 1440px;
    margin: 0 auto;
}

/* ── Sticky Top Status Bar ───────────────────────────────────────── */
.status-bar {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.25rem;
    transition: all 0.2s ease;
}

.status-bar.is-ready {
    border-left: 4px solid #10b981;
}

[dir="rtl"] .status-bar.is-ready {
    border-left: 1px solid #e2e8f0;
    border-right: 4px solid #10b981;
}

.status-bar.is-blocked {
    border-left: 4px solid #f59e0b;
}

[dir="rtl"] .status-bar.is-blocked {
    border-left: 1px solid #e2e8f0;
    border-right: 4px solid #f59e0b;
}

.sb-lead {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.92rem;
}

.is-ready .sb-lead { color: #059669; }
.is-blocked .sb-lead { color: #d97706; }

.sb-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    font-size: 0.75rem;
    background: #fef3c7;
    color: #b45309;
}

.sb-issues {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
    overflow-x: auto;
}

.sb-issue {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    color: #92400e;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s ease;
}

.sb-issue:hover {
    background: #fef3c7;
}

.sb-tail {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-inline-start: auto;
}

.sb-metrics {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.88rem;
    color: #64748b;
}

.sb-total strong {
    font-size: 1.15rem;
    color: #1e293b;
    margin-inline-start: 0.35rem;
}

.sb-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
}

.sb-flag.is-warn {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

.sb-flag.is-muted {
    background: #f1f5f9;
    color: #475569;
}

/* ── Form Card ───────────────────────────────────────────────────── */
.order-form-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* ── Head Grids ──────────────────────────────────────────────────── */
.head-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1.2fr);
    gap: 1.25rem;
    margin-bottom: 0.5rem;
}

.head-subgrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding-bottom: 1.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
}

.customer-pick-row {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

.customer-select {
    flex: 1;
}

.btn-quick-customer {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
}

.customer-info-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.4rem;
    font-size: 0.8rem;
}

.cust-info-item {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    background: #f8fafc;
    color: #475569;
}

.cust-info-item.is-credit {
    background: #ecfdf5;
    color: #065f46;
}

.cust-info-item.is-owing {
    background: #fef2f2;
    color: #991b1b;
}

.quick-days {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.quick-days .chip {
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.quick-days .chip.is-on {
    background: #1d4ed8;
    color: #ffffff;
    border-color: #1d4ed8;
}

/* ── Lines Section Head ──────────────────────────────────────────── */
.lines-head-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.lines-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.lines-title h4 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}

.lines-badge {
    font-size: 0.78rem;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
}

.lines-badge.is-warn {
    background: #fef2f2;
    color: #b91c1c;
}

.subtotal-pill {
    font-size: 0.95rem;
    background: #eff6ff;
    color: #1e40af;
    padding: 0.3rem 0.75rem;
    border-radius: 6px;
    border: 1px solid #dbeafe;
}

/* ── Search & Product Catalog Panel ──────────────────────────────── */
.quote-search-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.85rem;
    margin-bottom: 1.25rem;
    position: relative;
}

.quote-search-header-row {
    display: grid;
    grid-template-columns: 220px 1fr auto auto;
    gap: 0.65rem;
    align-items: center;
}

.quote-search-input :deep(.el-input__wrapper) {
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.key-hint {
    background: #e2e8f0;
    color: #475569;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
}

.btn-toggle-catalog,
.btn-new-product {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    height: 36px;
}

.btn-toggle-catalog:hover,
.btn-new-product:hover {
    border-color: #94a3b8;
    background: #f1f5f9;
}

.btn-toggle-catalog.is-active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
}

.count-badge {
    background: #3b82f6;
    color: #ffffff;
    font-size: 0.72rem;
    padding: 1px 6px;
    border-radius: 999px;
}

.category-quick-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px solid #e2e8f0;
}

.cat-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.cat-chip-btn.is-active {
    background: #1d4ed8;
    color: #ffffff;
    border-color: #1d4ed8;
}

/* ── Suggested Catalog Dropdown ──────────────────────────────────── */
.quote-suggested-panel {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    margin-top: 0.75rem;
    overflow: hidden;
}

.dropdown-top-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
}

.strip-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.88rem;
}

.results-badge {
    background: #e2e8f0;
    color: #334155;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.keep-open-pill {
    font-size: 0.75rem;
    color: #475569;
    margin-inline-end: 0.75rem;
}

.close-dropdown-btn {
    border: none;
    background: none;
    color: #64748b;
    font-size: 1rem;
    cursor: pointer;
}

.suggested-list-scroll {
    max-height: 420px;
    overflow-y: auto;
    padding: 0.85rem;
}

.suggested-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}

.suggested-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
    transition: border-color 0.15s ease;
}

.suggested-item-card:hover {
    border-color: #94a3b8;
}

.suggested-item-card.item-in-order {
    border-color: #93c5fd;
    background: #f8fafc;
}

.suggested-item-card.is-single {
    display: flex;
    gap: 0.75rem;
}

.item-card-image {
    position: relative;
    width: 54px;
    height: 54px;
    flex-shrink: 0;
}

.in-order-tag {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #2563eb;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 999px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.item-card-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.item-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.35rem;
}

.item-name {
    font-weight: 700;
    font-size: 0.85rem;
    color: #0f172a;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.sku-chip {
    font-size: 0.72rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
    white-space: nowrap;
}

.item-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.cat-chip, .stock-chip {
    font-size: 0.7rem;
    padding: 1px 5px;
    border-radius: 4px;
}

.cat-chip { background: #f1f5f9; color: #475569; }
.stock-chip { background: #ecfdf5; color: #047857; }
.stock-chip.is-low { background: #fff7ed; color: #c2410c; }
.stock-chip.is-out { background: #fef2f2; color: #b91c1c; }

.item-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.35rem;
}

.item-price {
    color: #1d4ed8;
    font-size: 0.95rem;
}

.unit-text {
    font-size: 0.75rem;
    color: #64748b;
}

/* Multi-variant tiles */
.group-head {
    display: flex;
    gap: 0.65rem;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px dashed #e2e8f0;
}

.variants-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.5rem;
}

.variant-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.45rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.variant-tile.tile-in-order {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.tile-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    font-weight: 700;
    color: #1e293b;
}

.tile-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: #64748b;
}

.tile-price {
    font-weight: 600;
    color: #2563eb;
}

/* ── Lines Table ─────────────────────────────────────────────────── */
.lines-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: #94a3b8;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
}

.lines-empty i {
    font-size: 2.25rem;
    display: block;
    margin-bottom: 0.5rem;
}

.lines {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #ffffff;
    margin-bottom: 1.5rem;
}

.line {
    display: grid;
    grid-template-columns: minmax(0, 2fr) 130px 100px 105px 110px minmax(90px, 1fr) 68px;
    gap: 0.5rem;
    align-items: center;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
}

.line:last-child {
    border-bottom: none;
}

.line--head {
    background: #f8fafc;
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
}

.line-product-content {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
}

.line-thumb {
    width: 42px;
    height: 42px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.line-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.2rem;
}

.line-info strong {
    font-size: 0.88rem;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.line-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
}

.variant-tag {
    background: #eff6ff;
    color: #1d4ed8;
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 600;
}

.stock-pill {
    background: #f1f5f9;
    color: #475569;
    padding: 1px 5px;
    border-radius: 4px;
}

.stock-pill.is-over {
    background: #fef2f2;
    color: #b91c1c;
    font-weight: 700;
}

.line-variant-switch {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.25rem;
    font-size: 0.72rem;
}

.switch-label {
    color: #64748b;
    white-space: nowrap;
}

.switch-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.switch-chip {
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.72rem;
    cursor: pointer;
}

.switch-chip.is-active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.line-fields {
    display: contents;
}

.line-field {
    min-width: 0;
}

.line-field :deep(.el-input-number) {
    width: 100%;
}

.unit-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: #f1f5f9;
    border-radius: 4px;
    font-size: 0.82rem;
    color: #334155;
}

.line-total-cell {
    text-align: end;
}

.line-total {
    font-weight: 800;
    color: #0f172a;
    font-size: 0.95rem;
}

.line-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.15rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    color: #64748b;
}

.action-btn:hover {
    color: #1d4ed8;
    background: #eff6ff;
}

.action-btn.text-danger:hover {
    color: #dc2626;
    background: #fef2f2;
}

/* ── Bottom Grid: Notes & Settlement / Totals ────────────────────── */
.bottom-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.settlement-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
}

.settlement-title {
    margin: 0 0 0.65rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
}

.settlement-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    flex-wrap: wrap;
}

.remaining-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.88rem;
    font-weight: 600;
}

.remaining-badge.is-settled {
    background: #ecfdf5;
    color: #047857;
}

.remaining-badge.is-owing {
    background: #fef2f2;
    color: #b91c1c;
}

.remaining-badge.is-credit {
    background: #eff6ff;
    color: #1d4ed8;
}

.tafqeet-line {
    margin: 0.65rem 0 0;
    font-size: 0.82rem;
    color: #475569;
    border-top: 1px dashed #cbd5e1;
    padding-top: 0.5rem;
}

/* ── Totals Card ─────────────────────────────────────────────────── */
.totals {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    align-self: start;
}

.totals-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.35rem 0;
    font-size: 0.88rem;
    color: #475569;
}

.totals-row :deep(.el-input-number) {
    width: 130px;
}

.totals-row.discount-row {
    flex-wrap: wrap;
    row-gap: 0.35rem;
}

.totals-row-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.discount-mode-switch :deep(.el-radio-button__inner) {
    padding: 2px 7px;
    font-size: 0.72rem;
    height: 24px;
    line-height: 20px;
}

.totals-row-input {
    display: inline-flex;
    justify-content: flex-end;
}

.totals-hint {
    font-size: 0.75rem;
    margin-top: -0.15rem;
    margin-bottom: 0.35rem;
    padding-inline-start: 0.25rem;
}

.totals-hint.is-error {
    color: #ef4444;
    font-weight: 600;
}

.totals-hint.is-figure {
    color: #059669;
    font-weight: 500;
}

.totals-row.grand {
    border-top: 1px solid #e2e8f0;
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}

.totals-row.grand .num {
    color: #1d4ed8;
}

/* ── Bottom Action Dock ──────────────────────────────────────────── */
.action-bar-dock {
    position: sticky;
    bottom: 0;
    z-index: 25;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border-top: 1px solid #e2e8f0;
    padding: 0.75rem 1.5rem;
    margin: 1.5rem -1.5rem -1.5rem;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
}

.action-bar-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1440px;
    margin: 0 auto;
}

.dock-left, .dock-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.blocker-hint {
    font-size: 0.82rem;
    color: #b45309;
    background: #fef3c7;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
}

.btn-save-primary {
    font-weight: 700;
    padding: 0 1.5rem;
}

/* ── Responsive adjustments ─────────────────────────────────────── */
@media (max-width: 992px) {
    .head-grid {
        grid-template-columns: 1fr;
    }
    .quote-search-header-row {
        grid-template-columns: 1fr 1fr;
    }
    .bottom-grid {
        grid-template-columns: 1fr;
    }
    .line {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}
</style>
