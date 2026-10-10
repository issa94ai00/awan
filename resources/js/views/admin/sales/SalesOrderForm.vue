<template>
    <div class="order-form-page">
        <AdminPageHeader
            icon="fas fa-file-invoice text-primary"
            :title="isEdit ? t('edit_sales_order') : t('create_sales_order')"
            :subtitle="isEdit && loadedOrder ? `${t('order_number')}: ${loadedOrder.order_number}` : t('create_order_subtitle')"
        >
            <template #actions>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_sales_orders') }}
                </el-button>
                <el-button v-if="!isEdit && form.items.length" text type="danger" @click="clearOrder">
                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('clear_the_form') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!--
            Status bar. The form is one long page, so the running total, the
            shortage count and the list of what is still missing were all either
            below the fold or buried under a single alert at the very bottom.
            Sticking them to the top keeps the answer to "what will this cost and
            can I save yet" one glance away no matter how many lines are added.

            Its own `v-if`, deliberately outside the skeleton/locked/form chain
            below: a chained `v-else-if` here would satisfy itself on every
            editable screen and stop the form from rendering at all.
        -->
        <div
            v-if="!loading && !isLocked"
            class="status-bar"
            :class="issues.length ? 'is-blocked' : 'is-ready'"
        >
            <div class="sb-lead">
                <i :class="issues.length ? 'fas fa-circle-exclamation' : 'fas fa-circle-check'"></i>
                <span class="sb-lead-text">
                    {{ issues.length ? t('so_to_finish') : t('so_ready_to_save') }}
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
                    <span class="sb-metric">{{ t('so_items_n', { count: form.items.length }) }}</span>
                    <span class="sb-dot" aria-hidden="true">·</span>
                    <span class="sb-metric">{{ t('pieces_count', { count: pieces }) }}</span>
                    <span class="sb-total">
                        <span class="sb-total-label">{{ t('total') }}</span>
                        <strong>{{ formatCurrency(total) }}</strong>
                    </span>
                </div>

                <span v-if="overStockLines.length" class="sb-flag is-warn">
                    <i class="fas fa-triangle-exclamation"></i>
                    {{ t('so_lines_over_stock_n', { count: overStockLines.length }) }}
                </span>

                <span v-if="draftSavedAt" class="sb-flag is-muted">
                    <i class="fas fa-floppy-disk"></i>
                    {{ t('so_kept_on_this_device', { time: draftSavedAt }) }}
                </span>
            </div>
        </div>

        <!-- Loading skeleton -->
        <el-skeleton v-if="loading" :rows="8" animated class="order-skeleton" />

        <!-- Locked order: confirmed orders cannot have lines rewritten -->
        <section v-else-if="isLocked" class="locked">
            <i class="fas fa-lock locked-icon"></i>
            <h2>{{ t('order_locked_after_confirmation') }}</h2>
            <p>{{ lockedReason }}</p>
            <dl class="locked-facts">
                <div><dt>{{ t('status') }}</dt><dd>{{ statusLabel(loadedOrder?.status) }}</dd></div>
                <div><dt>{{ t('customer') }}</dt><dd>{{ loadedOrder?.customer?.name || '—' }}</dd></div>
                <div><dt>{{ t('grand_total') }}</dt><dd>{{ formatCurrency(loadedOrder?.total) }}</dd></div>
            </dl>
            <div class="locked-actions">
                <el-button type="primary" @click="openOrder(orderId)">
                    <i class="fas fa-eye"></i>&nbsp;{{ t('open_order_to_follow_stages') }}
                </el-button>
            </div>
            <p class="locked-note">{{ t('why_items_are_locked') }}</p>
        </section>

        <!-- New / Edit Form View Matching Quotes New Form UI/UX -->
        <div v-else ref="formRoot" class="order-form-container panel-card">
            <el-form label-position="top" class="quote-form order-form" @submit.prevent>
                <!-- ── Head Grid: Customer & Delivery Date ── -->
                <div class="head-grid">
                    <!-- Customer Selection -->
                    <el-form-item
                        :label="$t('customer')"
                        required
                        :error="fieldError('customer_id')"
                        :class="{ 'is-flagged': isFlagged('customer_id') }"
                    >
                        <div class="customer-pick-row">
                            <el-select
                                ref="customerSelect"
                                v-model="form.customer_id"
                                filterable
                                remote
                                clearable
                                :remote-method="searchCustomers"
                                :loading="customersLoading"
                                :placeholder="$t('qt_find_customer')"
                                class="customer-select"
                                @focus="!customerOptions.length && searchCustomers('')"
                                @change="onCustomerChange"
                            >
                                <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id">
                                    <div class="option-row">
                                        <span class="option-name">{{ c.name }}</span>
                                        <span class="option-meta" dir="ltr">{{ c.phone || c.company || '' }}</span>
                                    </div>
                                </el-option>

                                <!-- Nothing matched: offer to create the typed client in place -->
                                <template #empty>
                                    <div class="customer-empty">
                                        <template v-if="customersLoading">
                                            <i class="fas fa-spinner fa-spin"></i>&nbsp;{{ $t('loading') }}
                                        </template>
                                        <template v-else>
                                            <p class="customer-empty-text">
                                                {{ customerQuery ? $t('qt_no_customer_match', { query: customerQuery }) : $t('qt_no_customers_yet') }}
                                            </p>
                                            <button type="button" class="customer-empty-add" @mousedown.prevent @click="openQuickCustomer(customerQuery)">
                                                <i class="fas fa-user-plus"></i>
                                                <span>{{ customerQuery ? $t('qt_add_as_customer', { query: customerQuery }) : $t('qt_new_customer') }}</span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </el-select>

                            <el-tooltip :content="$t('qt_new_customer')" placement="top" :show-after="400">
                                <button
                                    type="button"
                                    class="customer-add-btn"
                                    :class="{ 'is-on': quickCustomer.open }"
                                    :aria-expanded="quickCustomer.open"
                                    @click="toggleQuickCustomer"
                                >
                                    <i class="fas" :class="quickCustomer.open ? 'fa-times' : 'fa-user-plus'"></i>
                                    <span class="customer-add-label">{{ quickCustomer.open ? $t('cancel') : $t('qt_new_customer') }}</span>
                                </button>
                            </el-tooltip>
                        </div>

                        <!-- Customer Info Strip -->
                        <div v-if="selectedCustomer && !quickCustomer.open" class="customer-info-strip">
                            <span v-if="selectedCustomer.phone" class="cust-info-item">
                                <i class="fas fa-phone"></i>&nbsp;<span dir="ltr">{{ selectedCustomer.phone }}</span>
                            </span>
                            <span v-if="selectedCustomer.company" class="cust-info-item">
                                <i class="fas fa-building"></i>&nbsp;{{ selectedCustomer.company }}
                            </span>
                            <span v-if="Number(selectedCustomer.balance) > 0" class="cust-info-item warn">
                                <i class="fas fa-wallet"></i>&nbsp;{{ $t('customer_credit') }}: {{ formatCurrency(selectedCustomer.balance) }}
                            </span>
                            <span v-if="creditCheck && creditCheck.over" class="cust-info-item danger">
                                <i class="fas fa-triangle-exclamation"></i>&nbsp;{{ $t('credit_limit_exceeded') }} ({{ formatCurrency(creditCheck.limit) }})
                            </span>
                            <span v-if="selectedCustomer.id === justAddedCustomerId" class="cust-info-item fresh">
                                <i class="fas fa-check-circle"></i>&nbsp;{{ $t('qt_customer_just_added') }}
                            </span>
                        </div>

                        <transition name="qc-slide">
                            <CustomerQuickAdd
                                v-if="quickCustomer.open"
                                :seed="quickCustomer.seed"
                                @select="onQuickCustomerSelect"
                                @close="closeQuickCustomer"
                            />
                        </transition>
                    </el-form-item>

                    <!-- Expected Delivery Date with Quick Day Chips -->
                    <el-form-item
                        :label="$t('expected_delivery_date')"
                        :error="fieldError('expected_delivery')"
                        :class="{ 'is-flagged': isFlagged('expected_delivery') }"
                    >
                        <el-date-picker
                            ref="deliveryPicker"
                            v-model="form.expected_delivery"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="YYYY-MM-DD"
                            :placeholder="$t('choose_delivery_date')"
                            style="width: 100%"
                        />
                        <div class="quick-days" role="group" :aria-label="$t('expected_delivery_date')">
                            <button
                                v-for="days in [0, 3, 7, 14, 30]"
                                :key="days"
                                type="button"
                                class="chip"
                                :aria-pressed="form.expected_delivery === inDays(days)"
                                :class="{ 'is-on': form.expected_delivery === inDays(days) }"
                                @click="form.expected_delivery = inDays(days)"
                            >
                                {{ days === 0 ? $t('today') : $t('qt_days_n', { count: days }) }}
                            </button>
                        </div>
                    </el-form-item>
                </div>

                <!-- ── Secondary Head Grid: Fulfilment, Warehouse, Order Date ── -->
                <div class="head-subgrid">
                    <el-form-item :label="$t('so_fulfillment_type')">
                        <el-select v-model="form.fulfillment_type" style="width: 100%">
                            <el-option value="ship" :label="$t('so_shipping_transfer')" />
                            <el-option value="pickup" :label="$t('so_pickup_branch')" />
                            <el-option value="delivery" :label="$t('so_direct_delivery')" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="$t('choose_warehouse')">
                        <el-select
                            v-model="form.fulfillment_warehouse_id"
                            filterable
                            clearable
                            :placeholder="$t('choose_warehouse_placeholder')"
                            style="width: 100%"
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

                    <el-form-item :label="$t('order_date')">
                        <el-date-picker
                            v-model="form.order_date"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="YYYY-MM-DD"
                            style="width: 100%"
                        />
                    </el-form-item>
                </div>

                <!-- ── Lines Section ── -->
                <div class="lines-head-top">
                    <div class="lines-title">
                        <h4>{{ $t('items') }}</h4>
                        <span v-if="form.items.length" class="lines-badge">
                            {{ form.items.length }} {{ $t('product') }} &bull; {{ pieces }} {{ $t('piece') }}
                        </span>
                        <span v-if="overStockLines.length" class="lines-badge is-warn">
                            <i class="fas fa-triangle-exclamation"></i>
                            {{ $t('so_over_stock_n', { count: overStockLines.length }) }}
                        </span>
                    </div>

                    <div v-if="form.items.length" class="lines-summary-quick">
                        <span class="subtotal-pill">
                            <span class="lbl">{{ $t('subtotal') }}:</span>
                            <strong>{{ formatCurrency(subtotal) }}</strong>
                        </span>
                    </div>
                </div>

                <!-- Modern Interactive Search & Category Filter Panel (Stays Open While Adding!) -->
                <div ref="quickSearchContainerRef" class="quote-search-panel order-search-panel" @mousedown.stop>
                    <div class="quote-search-header-row">
                        <!-- Category Filter Dropdown -->
                        <div class="search-category-select-wrap">
                            <el-select
                                v-model="selectedCategoryId"
                                clearable
                                filterable
                                :placeholder="$t('qt_filter_category') || $t('category')"
                                class="category-filter-select"
                                :loading="categoriesLoading"
                                @change="onCategoryFilterChange"
                            >
                                <template #prefix>
                                    <i class="fas fa-folder-tree text-muted"></i>
                                </template>
                                <el-option
                                    :value="null"
                                    :label="$t('qt_all_categories') || $t('all_categories')"
                                >
                                    <span class="category-all-label">
                                        <i class="fas fa-layer-group"></i> {{ $t('qt_all_categories') || $t('all_categories') }}
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
                                :placeholder="$t('qt_product_search_placeholder')"
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
                            :title="quickSearchOpen ? $t('qt_close_search') : ($t('so_suggested_products') || $t('qt_suggested_products'))"
                            @click.stop.prevent="quickSearchOpen = !quickSearchOpen"
                        >
                            <i class="fas" :class="quickSearchOpen ? 'fa-chevron-up' : 'fa-boxes-stacked'"></i>
                            <span class="btn-text">{{ quickSearchOpen ? $t('qt_close_search') : ($t('so_suggested_products') || $t('qt_suggested_products')) }}</span>
                            <span v-if="quickSearchResults.length" class="count-badge">{{ quickSearchResults.length }}</span>
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
                            <span>{{ $t('qt_all_categories') || $t('all_categories') }}</span>
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
                                    <strong>{{ selectedCategoryName || $t('so_suggested_products') || $t('qt_suggested_products') }}</strong>
                                    <span class="results-badge">{{ quickSearchResults.length }}</span>
                                    <span v-if="quickSearchQuery" class="search-term-badge">
                                        "{{ quickSearchQuery }}"
                                    </span>
                                </div>
                                <div class="strip-right">
                                    <span class="keep-open-pill">
                                        <i class="fas fa-thumbtack text-success"></i>
                                        {{ $t('po_search_hint') || 'القائمة تبقى مفتوحة لتتمكن من إضافة عدة أصناف متتالية بسهولة' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="close-dropdown-btn"
                                        :title="$t('close')"
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
                                    <span>{{ $t('qt_searching_catalog') }}</span>
                                </div>

                                <div v-else-if="!quickSearchResults.length" class="suggested-empty-state">
                                    <i class="fas fa-box-open empty-icon"></i>
                                    <p class="empty-title">{{ $t('qt_no_products_found') }}</p>
                                </div>

                                <div v-else class="suggested-products-grid">
                                    <div
                                        v-for="p in quickSearchResults"
                                        :key="p.listing_key || optionKey(p)"
                                        class="suggested-item-card"
                                        :class="{ 'item-in-order': getOrderItemQuantity(p) > 0 }"
                                    >
                                        <!-- Thumbnail Image with EntityImage -->
                                        <div class="item-card-image">
                                            <EntityImage
                                                :src="productImageSrc(p)"
                                                type="product"
                                                :size="54"
                                                shape="square"
                                                class="card-img"
                                            />
                                            <span v-if="getOrderItemQuantity(p) > 0" class="in-order-tag" :title="$t('so_in_sales_order') || $t('in_order')">
                                                {{ getOrderItemQuantity(p) }}
                                            </span>
                                        </div>

                                        <!-- Product Info -->
                                        <div class="item-card-body">
                                            <div class="item-card-header">
                                                <span class="item-name" :title="productName(p)">
                                                    {{ productName(p) }}
                                                </span>
                                                <VariantChip v-if="p.variant_id" :label="p.variant_label" />
                                            </div>

                                            <div class="item-card-tags">
                                                <span v-if="p.sku" class="sku-chip">
                                                    <i class="fas fa-barcode"></i> {{ p.sku }}
                                                </span>
                                                <span v-if="p.category?.name_ar || p.category_name" class="cat-chip">
                                                    <i class="fas fa-folder"></i> {{ p.category?.name_ar || p.category_name }}
                                                </span>
                                                <span
                                                    v-if="p.stock_quantity != null"
                                                    class="stock-badge"
                                                    :class="stockBadgeClass(p.stock_quantity)"
                                                >
                                                    {{ p.stock_quantity > 0 ? `${$t('available')}: ${p.stock_quantity}` : $t('out_of_stock') }}
                                                </span>
                                            </div>

                                            <div class="item-card-financials">
                                                <div class="price-stat">
                                                    <span class="stat-lbl">{{ $t('price') }}:</span>
                                                    <strong class="stat-val text-primary">{{ formatCurrency(listPrice(p)) }}</strong>
                                                    <span v-if="p.has_sale && p.price && p.price !== p.sale_price" class="old-price">
                                                        {{ formatCurrency(p.price) }}
                                                    </span>
                                                </div>
                                                <div v-if="p.cost_price" class="cost-stat">
                                                    <span class="stat-lbl">{{ $t('cost_price') }}:</span>
                                                    <span class="stat-val text-muted">{{ formatCurrency(p.cost_price) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Actions: Add to Order AND Add to Purchase List -->
                                        <div class="item-card-actions">
                                            <!-- Add to Order Button / Stepper -->
                                            <template v-if="getOrderItemQuantity(p) === 0">
                                                <button
                                                    type="button"
                                                    class="btn-add-product"
                                                    @click.stop.prevent="addProduct(p)"
                                                >
                                                    <i class="fas fa-plus"></i>
                                                    <span>{{ $t('so_add_to_order') || $t('po_add_to_order') || 'إضافة للطلب' }}</span>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <div class="item-stepper" @click.stop>
                                                    <button
                                                        type="button"
                                                        class="stepper-btn stepper-minus"
                                                        :title="$t('decrease')"
                                                        @click.stop.prevent="decrementOrderProduct(p)"
                                                    >
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <span class="stepper-val">{{ getOrderItemQuantity(p) }}</span>
                                                    <button
                                                        type="button"
                                                        class="stepper-btn stepper-plus"
                                                        :title="$t('increase')"
                                                        @click.stop.prevent="addProduct(p)"
                                                    >
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </template>

                                            <!-- Add to Purchase List Button -->
                                            <button
                                                type="button"
                                                class="btn-add-purchase"
                                                :class="{ 'is-highlight': Number(p.stock_quantity) <= 0 }"
                                                :title="$t('so_add_to_purchase_list') || 'إضافة لقائمة الشراء / طلب شراء'"
                                                @click.stop.prevent="openPurchaseDialog(p)"
                                            >
                                                <i class="fas fa-cart-flatbed"></i>
                                                <span>{{ $t('so_add_to_purchase_list') || 'طلب شراء' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dropdown Footer -->
                            <div class="dropdown-footer-strip">
                                <div class="footer-summary">
                                    <span>
                                        <i class="fas fa-shopping-cart text-muted"></i>&nbsp;
                                        <strong>{{ form.items.length }}</strong> {{ $t('product') }} &bull; <strong>{{ pieces }}</strong> {{ $t('piece') }}
                                    </span>
                                </div>
                                <div class="footer-hints">
                                    <span><kbd>Enter</kbd> {{ $t('add_first_result') || 'إضافة أول نتيجة' }}</span>
                                    <span><kbd>Esc</kbd> {{ $t('close') }}</span>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- Empty Lines State -->
                <div
                    v-if="!form.items.length"
                    class="lines-empty"
                    :class="{ 'has-error': fieldError('items') || isFlagged('items') }"
                >
                    <i class="fas fa-box-open"></i>
                    <span>{{ fieldError('items') || $t('qt_no_lines') }}</span>
                </div>

                <!-- Lines Table -->
                <div v-else class="lines">
                    <div class="line line--head" aria-hidden="true">
                        <span>{{ $t('product') }}</span>
                        <span>{{ $t('so_unit') }}</span>
                        <span>{{ $t('quantity') }}</span>
                        <span>{{ $t('unit_price') }}</span>
                        <span>{{ $t('discount') }}</span>
                        <span>{{ $t('tax') }}</span>
                        <span class="num">{{ $t('total') }}</span>
                        <span />
                    </div>

                    <div
                        v-for="(line, i) in form.items"
                        :key="line.key"
                        class="line"
                        :class="{ 'is-flagged': isFlagged(`items.${i}`) }"
                    >
                        <div class="line-product">
                            <div class="line-product-content">
                                <EntityImage
                                    :src="line.image || productImageSrc(line)"
                                    type="product"
                                    :size="42"
                                    shape="square"
                                    class="line-thumb"
                                />
                                <div class="line-info">
                                    <strong :title="line.name">{{ line.name }}</strong>
                                    <div class="line-meta">
                                        <span v-if="line.sku" class="option-meta" dir="ltr">{{ line.sku }}</span>
                                        <span v-if="line.variant_label" class="variant-tag">{{ line.variant_label }}</span>
                                        <span v-if="line.stock !== undefined" :class="['stock-pill', overStock(line) ? 'is-over' : '']">
                                            {{ overStock(line) ? $t('so_stock_shortage') : `${$t('available')}: ${line.stock}` }}
                                        </span>
                                        <button
                                            v-if="overStock(line)"
                                            type="button"
                                            class="line-shortage-btn"
                                            :title="$t('so_add_to_purchase_list') || 'طلب شراء لهذا البند'"
                                            @click.stop="openPurchaseDialogFromLine(line)"
                                        >
                                            <i class="fas fa-cart-flatbed"></i> {{ $t('so_add_to_purchase_list') || 'طلب شراء' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unit column -->
                        <div class="line-fields">
                            <div class="line-field">
                                <span class="mobile-label">{{ $t('so_unit') }}</span>
                                <el-select
                                    v-if="line.units && line.units.length > 1"
                                    v-model="line.unit"
                                    value-key="id"
                                    size="small"
                                    class="unit-select"
                                    :aria-label="fieldLabel(line, $t('so_unit'))"
                                    @change="(u) => onUnitChange(line, u)"
                                >
                                    <el-option v-for="u in line.units" :key="u.id" :label="unitName(u) || u.name_ar || u.name" :value="u" />
                                </el-select>
                                <span v-else class="unit-badge">{{ unitName(line.unit) || $t('piece') }}</span>
                            </div>

                            <!-- Quantity -->
                            <div class="line-field">
                                <span class="mobile-label">{{ $t('quantity') }}</span>
                                <el-input-number
                                    v-model="line.quantity"
                                    :min="1"
                                    :step="1"
                                    step-strictly
                                    size="small"
                                    controls-position="right"
                                    :aria-label="fieldLabel(line, $t('quantity'))"
                                />
                            </div>

                            <!-- Unit Price -->
                            <div class="line-field">
                                <span class="mobile-label">{{ $t('unit_price') }}</span>
                                <el-input-number
                                    v-model="line.price"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :aria-label="fieldLabel(line, $t('unit_price'))"
                                />
                            </div>

                            <!-- Line Discount -->
                            <div class="line-field">
                                <span class="mobile-label">{{ $t('discount') }}</span>
                                <el-input-number
                                    v-model="line.discount"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :aria-label="fieldLabel(line, $t('discount'))"
                                    :class="{ 'is-invalid': lineDiscountTooBig(line) || errors[`items.${i}.discount`] }"
                                />
                            </div>

                            <!-- Line Tax -->
                            <div class="line-field">
                                <span class="mobile-label">{{ $t('tax') }}</span>
                                <el-input-number
                                    v-model="line.tax"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :aria-label="fieldLabel(line, $t('tax'))"
                                />
                            </div>
                        </div>

                        <!-- Line Total -->
                        <div class="line-field line-total-cell">
                            <span class="mobile-label">{{ $t('so_line_total_of') }}</span>
                            <span class="num line-total">{{ formatCurrency(lineTotal(line)) }}</span>
                        </div>

                        <!-- Row Actions: duplicate then remove, most destructive last -->
                        <div class="line-actions">
                            <el-tooltip :content="$t('duplicate_line')" placement="top">
                                <el-button
                                    text
                                    circle
                                    class="action-btn"
                                    :aria-label="`${$t('duplicate_line')}: ${line.name}`"
                                    @click="duplicateLine(i)"
                                >
                                    <i class="fas fa-copy"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('delete')" placement="top">
                                <el-button
                                    text
                                    circle
                                    type="danger"
                                    class="action-btn"
                                    :aria-label="`${$t('delete')}: ${line.name}`"
                                    @click="form.items.splice(i, 1)"
                                >
                                    <i class="fas fa-xmark"></i>
                                </el-button>
                            </el-tooltip>
                        </div>
                    </div>
                </div>

                <!-- ── Additional Expenses & Shipping ── -->
                <OrderExpensesEditor
                    v-model="form.expenses"
                    class="order-expenses-section"
                    @total-change="onExpensesTotalChange"
                />

                <!-- ── Bottom Grid: Notes & Totals ── -->
                <div class="bottom-grid">
                    <div>
                        <el-form-item :label="$t('notes')">
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="2"
                                maxlength="1000"
                                show-word-limit
                                :placeholder="$t('notes')"
                            />
                        </el-form-item>
                        <el-form-item :label="$t('delivery_and_shipping_address')">
                            <el-input
                                v-model="form.shipping_address"
                                type="textarea"
                                :rows="2"
                                maxlength="500"
                                :placeholder="$t('shipping_address_label')"
                            />
                        </el-form-item>
                    </div>

                    <div class="totals" :class="{ 'is-flagged': isFlagged('discount') }">
                        <div class="totals-row">
                            <span>{{ $t('subtotal') }}</span>
                            <span class="num">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="totals-row">
                            <span>{{ $t('qt_extra_discount') }}</span>
                            <el-input-number
                                ref="discountInput"
                                v-model="form.discount"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                size="small"
                                :aria-label="$t('qt_extra_discount')"
                                :class="{ 'is-invalid': discountTooBig || errors.discount }"
                            />
                        </div>
                        <div class="totals-row">
                            <span>{{ $t('qt_extra_tax') }}</span>
                            <el-input-number
                                v-model="form.tax"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                size="small"
                                :aria-label="$t('qt_extra_tax')"
                            />
                        </div>
                        <div class="totals-row">
                            <span>{{ $t('so_shipping_cost') }}</span>
                            <span v-if="form.expenses && form.expenses.length" class="num">{{ formatCurrency(form.shipping_cost) }}</span>
                            <el-input-number
                                v-else
                                v-model="form.shipping_cost"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                size="small"
                                :aria-label="$t('so_shipping_cost')"
                            />
                        </div>
                        <div class="totals-row grand">
                            <span>{{ $t('total') }}</span>
                            <span class="num">{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Server Errors ── -->
                <el-alert
                    v-if="serverErrors.length"
                    type="error"
                    show-icon
                    class="server-errors"
                    :title="$t('please_fix_these_errors')"
                    @close="serverErrors = []"
                >
                    <ul>
                        <li v-for="(err, idx) in serverErrors" :key="idx">{{ err }}</li>
                    </ul>
                </el-alert>

                <!-- ── Form Footer Actions ── -->
                <footer class="form-footer">
                    <div class="footer-start">
                        <el-button @click="goBack">
                            <i class="fas" :class="backArrow"></i>&nbsp;{{ $t('cancel') }}
                        </el-button>
                    </div>

                    <div class="footer-end">
                        <span v-if="issues.length" class="footer-hint">
                            <i class="fas fa-circle-info"></i>&nbsp;{{ $t('so_fix_to_continue') }}
                        </span>
                        <el-tooltip
                            :disabled="!issues.length"
                            :content="$t('so_fix_to_continue')"
                            placement="top"
                        >
                            <span>
                                <el-button
                                    :loading="saving === 'draft'"
                                    :disabled="!!saving"
                                    @click="trySubmit(false)"
                                >
                                    <i class="fas fa-floppy-disk"></i>&nbsp;{{ isEdit ? $t('save_order_changes') : $t('save_sales_order_draft') }}
                                </el-button>
                            </span>
                        </el-tooltip>
                        <el-tooltip
                            :disabled="!issues.length"
                            :content="$t('so_fix_to_continue')"
                            placement="top"
                        >
                            <span>
                                <el-button
                                    type="primary"
                                    :loading="saving === 'confirm'"
                                    :disabled="!!saving"
                                    @click="trySubmit(true)"
                                >
                                    <i class="fas fa-circle-check"></i>&nbsp;{{ $t('so_save_and_confirm') }}
                                </el-button>
                            </span>
                        </el-tooltip>
                    </div>
                </footer>
            </el-form>
        </div>
        <!-- Quick Purchase Dialog -->
        <el-dialog
            v-model="purchaseDialogVisible"
            :title="$t('so_quick_purchase_title') || 'إضافة المنتج لقائمة الشراء / أمر الشراء'"
            width="540px"
            destroy-on-close
            append-to-body
            class="quick-purchase-dialog"
        >
            <div v-if="purchaseDialogProduct" class="purchase-dialog-content">
                <!-- Product Preview Strip -->
                <div class="purchase-product-preview">
                    <EntityImage
                        :src="productImageSrc(purchaseDialogProduct)"
                        type="product"
                        :size="64"
                        shape="square"
                        class="dialog-product-img"
                    />
                    <div class="dialog-product-info">
                        <h4 class="dialog-product-name">{{ productName(purchaseDialogProduct) }}</h4>
                        <div class="dialog-product-meta">
                            <span v-if="purchaseDialogProduct.sku" class="sku-badge"><i class="fas fa-barcode"></i> {{ purchaseDialogProduct.sku }}</span>
                            <VariantChip v-if="purchaseDialogProduct.variant_id" :label="purchaseDialogProduct.variant_label" />
                            <span :class="['stock-pill', Number(purchaseDialogProduct.stock_quantity) <= 0 ? 'is-out' : 'is-ok']">
                                {{ $t('available') }}: {{ purchaseDialogProduct.stock_quantity ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>

                <el-form label-position="top" class="purchase-dialog-form">
                    <div class="purchase-grid-2">
                        <!-- Purchase Quantity -->
                        <el-form-item :label="$t('so_purchase_qty') || 'كمية الشراء'" required>
                            <el-input-number
                                v-model="purchaseForm.quantity"
                                :min="1"
                                :step="1"
                                controls-position="right"
                                style="width: 100%"
                            />
                        </el-form-item>

                        <!-- Purchase Cost / Unit Price -->
                        <el-form-item :label="$t('so_purchase_cost') || 'سعر الشراء للوحدة'">
                            <el-input-number
                                v-model="purchaseForm.unit_price"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </div>

                    <!-- Supplier Selection -->
                    <el-form-item :label="$t('so_select_supplier') || 'المورد'" required>
                        <el-select
                            v-model="purchaseForm.supplier_id"
                            filterable
                            clearable
                            :placeholder="$t('so_select_supplier') || 'اختر المورد...'"
                            :loading="suppliersLoading"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="s in suppliersStore.suppliers"
                                :key="s.id"
                                :label="s.name"
                                :value="s.id"
                            >
                                <div class="supplier-option-row">
                                    <span>{{ s.name }}</span>
                                    <small v-if="s.company" class="text-muted">({{ s.company }})</small>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>

                    <!-- Target Warehouse -->
                    <el-form-item :label="$t('warehouse') || 'المستودع المستهدف'">
                        <el-select
                            v-model="purchaseForm.warehouse_id"
                            clearable
                            filterable
                            :placeholder="$t('choose_warehouse_placeholder')"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="wh in warehouses"
                                :key="wh.id"
                                :label="wh.name"
                                :value="wh.id"
                            />
                        </el-select>
                    </el-form-item>

                    <!-- Notes -->
                    <el-form-item :label="$t('notes')">
                        <el-input
                            v-model="purchaseForm.notes"
                            type="textarea"
                            :rows="2"
                            :placeholder="$t('po_note_placeholder') || 'ملاحظات أمر الشراء...'"
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="dialog-footer-actions">
                    <el-button @click="purchaseDialogVisible = false">
                        {{ $t('cancel') }}
                    </el-button>
                    <el-button
                        type="default"
                        @click="openInPurchasesScreen"
                    >
                        <i class="fas fa-arrow-up-right-from-square"></i>&nbsp;{{ $t('so_open_in_purchases') || 'فتح في شاشة المشتريات' }}
                    </el-button>
                    <el-button
                        type="primary"
                        :loading="submittingPurchase"
                        :disabled="!purchaseForm.supplier_id"
                        @click="submitPurchaseOrder"
                    >
                        <i class="fas fa-check"></i>&nbsp;{{ $t('so_create_purchase_order') || 'إنشاء أمر الشراء' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { categoriesApi } from '@/api/categories';
import { posApi } from '@/api/pos';
import { inventoryApi } from '@/api/inventory';
import { salesOrdersApi } from '@/api/salesOrders';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { resolveImageUrl } from '@/utils/productImages';
import api from '@/api/index';
import { useStockShortage } from '@/Composables/useStockShortage';
import { formatCurrency, localIsoDate, statusLabel } from '@/utils/sales';
import { optionKey, variantLabelOf } from '@/utils/productPick';
import OrderExpensesEditor from '@/components/admin/sales/OrderExpensesEditor.vue';
import CustomerQuickAdd from '@/components/admin/sales/CustomerQuickAdd.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const { handleStockShortage } = useStockShortage();

const orderId = route.params.id || null;
const isEdit = computed(() => !!orderId);

const backArrow = computed(() => (locale.value === 'ar' ? 'fa-arrow-right' : 'fa-arrow-left'));
const DRAFT_KEY = 'sales-order-draft';

const today = () => localIsoDate();
const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

function inDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return localIsoDate(d);
}

const pieceUnit = (name) => ({
    id: null,
    name: name || t('piece'),
    name_ar: name || t('piece'),
    base_unit_multiplier: 1,
    price_multiplier: 1,
    barcode: '',
});

const blank = () => ({
    customer_id: null,
    order_date: today(),
    expected_delivery: inDays(3),
    fulfillment_type: 'ship',
    fulfillment_warehouse_id: null,
    shipping_cost: 0,
    shipping_address: '',
    discount: 0,
    tax: 0,
    notes: '',
    items: [],
    expenses: [],
});

const form = reactive(blank());
const errors = reactive({});
const serverErrors = ref([]);
const saving = ref(null); // null | 'draft' | 'confirm'
const loading = ref(false);
const isLocked = ref(false);
const loadedOrder = ref(null);
const dirty = ref(false);
let lineSeq = 0;

// Element handles used to move focus onto whichever field needs fixing.
const formRoot = ref(null);
const customerSelect = ref(null);
const deliveryPicker = ref(null);
const discountInput = ref(null);

// Warehouses
const warehouses = ref([]);

// Customers lookup
const customerOptions = ref([]);
const customersLoading = ref(false);
const selectedCustomer = ref(null);
let customerTimer = null;

// Product lookup
const productOptions = ref([]);
const productsLoading = ref(false);
const pickedProduct = ref(null);
const productPicker = ref(null);
const productQuery = ref('');
let productTimer = null;

/** MIN_SEARCH_CHARS used to be a silent rule: one letter returned nothing at
 *  all with no explanation, which read as "no products match". */
const MIN_SEARCH_CHARS = 2;

const pickerHint = computed(() => {
    const q = productQuery.value.trim();
    if (productsLoading.value) return { kind: 'is-info', text: t('searching_products') };
    if (q.length && q.length < MIN_SEARCH_CHARS) return { kind: 'is-info', text: t('search_type_to_search') };
    if (q.length && !productOptions.value.length) {
        return { kind: 'is-empty', text: `${t('no_matching_results')} — ${t('try_other_search_terms')}` };
    }
    // Idle: an empty box with no options looks broken, so name what it accepts.
    return { kind: 'is-idle', text: t('qt_add_product') };
});

const productName = (p) => (locale.value === 'en' && p.name_en ? p.name_en : (p.name_ar || p.name || ''));
const productLabel = (p) => `${productName(p)}${p.sku ? ` (${p.sku})` : ''}`;
/** Units carry both spellings; reading name_ar unconditionally showed Arabic
    names under the English locale. Mirrors productName's fallback order. */
const unitName = (u) => (locale.value === 'en' && u?.name ? u.name : (u?.name_ar || u?.name || ''));
const listPrice = (p) => toNum(p.has_sale && p.sale_price ? p.sale_price : p.price);

/** Names every line input after the product it belongs to, because the column
 *  header row is decorative and screen readers otherwise hit four bare numbers. */
const fieldLabel = (line, label) => `${label} — ${line.name || t('unknown_product')}`;

const stockColorClass = (stock) => {
    const qty = Number(stock) || 0;
    if (qty <= 0) return 'text-danger';
    if (qty <= 5) return 'text-warning';
    return 'text-success';
};

/* ── Customer Management ─────────────────────────────────────────── */

const searchCustomers = (query) => {
    customerQuery.value = String(query || '').trim();
    clearTimeout(customerTimer);
    customerTimer = setTimeout(async () => {
        customersLoading.value = true;
        try {
            const res = await posApi.customers({ search: query || undefined, per_page: 30 });
            const found = res.data?.data?.customers || [];
            const current = customerOptions.value.find((c) => c.id === form.customer_id);
            customerOptions.value = current && !found.some((c) => c.id === current.id) ? [current, ...found] : found;
        } catch {
            // Keep existing options
        } finally {
            customersLoading.value = false;
        }
    }, 250);
};

const onCustomerChange = (customerId) => {
    delete errors.customer_id;
    const customer = customerOptions.value.find((c) => c.id === customerId);
    selectedCustomer.value = customer || null;
    if (customer?.address && !form.shipping_address) {
        form.shipping_address = customer.address;
    }
};

/** Put a customer in the options (if missing) and select it. */
const selectCustomer = (customer) => {
    if (!customer?.id) return;
    if (!customerOptions.value.some((c) => c.id === customer.id)) {
        customerOptions.value = [customer, ...customerOptions.value];
    }
    form.customer_id = customer.id;
    onCustomerChange(customer.id);
};

// ── Quick-add customer ─────────────────────────────────────────────────
// The panel itself lives in CustomerQuickAdd.vue (shared with the quote
// form); this form only decides when it is open and what seeds it.
const customerQuery = ref('');
const justAddedCustomerId = ref(null);
const quickCustomer = reactive({ open: false, seed: '' });

const openQuickCustomer = (seed = '') => {
    customerSelect.value?.blur();
    quickCustomer.seed = String(seed || '').trim();
    quickCustomer.open = true;
};

const closeQuickCustomer = () => {
    quickCustomer.open = false;
};

const toggleQuickCustomer = () => {
    if (quickCustomer.open) closeQuickCustomer();
    else openQuickCustomer(customerQuery.value);
};

const onQuickCustomerSelect = (customer, { created } = {}) => {
    justAddedCustomerId.value = created ? customer?.id || null : null;
    selectCustomer(customer);
    closeQuickCustomer();
};

const creditCheck = computed(() => {
    const c = selectedCustomer.value;
    if (!c) return null;
    const balance = Number(c.balance) || 0;
    const limit = Number(c.credit_limit) || 0;
    const after = round2(balance + total.value);
    return { balance, limit, after, over: limit > 0 && after > limit };
});

/* ── Categories & Products Catalog ───────────────────────────────────── */
const productImageSrc = (p) => {
    if (!p) return '';
    const raw = p.image_main || p.image || p.primary_image_url || p.image_url || '';
    return resolveImageUrl(raw);
};

const stockBadgeClass = (stock) => {
    const qty = Number(stock) || 0;
    if (qty <= 0) return 'is-out';
    if (qty <= 5) return 'is-low';
    return 'is-ok';
};

// Categories State
const categories = ref([]);
const categoriesLoading = ref(false);
const selectedCategoryId = ref(null);

const fetchCategories = async () => {
    if (categories.value.length) return;
    categoriesLoading.value = true;
    try {
        const res = await categoriesApi.getAll({ per_page: 500 });
        const list = res.data?.data || (Array.isArray(res.data) ? res.data : []);
        categories.value = list;
    } catch {
        categories.value = [];
    } finally {
        categoriesLoading.value = false;
    }
};

const categoryOptions = computed(() => {
    const name = (c) => (locale.value === 'en' && c.name_en ? c.name_en : (c.name_ar || c.name || ''));
    const roots = categories.value.filter((c) => !c.parent_id);
    return roots.flatMap((root) => [
        { id: root.id, parent_id: null, label: name(root) },
        ...categories.value.filter((c) => c.parent_id === root.id).map((c) => ({ id: c.id, parent_id: c.parent_id, label: name(c) })),
    ]);
});

const topCategories = computed(() => {
    const name = (c) => (locale.value === 'en' && c.name_en ? c.name_en : (c.name_ar || c.name || ''));
    return categories.value
        .filter((c) => !c.parent_id)
        .slice(0, 8)
        .map((c) => ({ id: c.id, label: name(c) }));
});

const selectedCategoryName = computed(() => {
    if (!selectedCategoryId.value) return '';
    const cat = categories.value.find((c) => c.id === selectedCategoryId.value);
    if (!cat) return '';
    return locale.value === 'en' && cat.name_en ? cat.name_en : (cat.name_ar || cat.name || '');
});

const onCategoryFilterChange = () => {
    executeQuickSearch();
    quickSearchOpen.value = true;
};

const setCategoryFilter = (catId) => {
    selectedCategoryId.value = catId;
    executeQuickSearch();
    quickSearchOpen.value = true;
};

// Quick Search & Suggested Catalog State
const quickSearchQuery = ref('');
const quickSearchLoading = ref(false);
const quickSearchResults = ref([]);
const quickSearchOpen = ref(false);
const quickSearchInputRef = ref(null);
const quickSearchContainerRef = ref(null);
let quickSearchDebounceTimer = null;

const onQuickSearchFocus = () => {
    quickSearchOpen.value = true;
    if (!quickSearchResults.value.length) {
        executeQuickSearch();
    }
};

const onQuickSearchInput = () => {
    clearTimeout(quickSearchDebounceTimer);
    quickSearchOpen.value = true;
    quickSearchDebounceTimer = setTimeout(() => {
        executeQuickSearch();
    }, 250);
};

const onQuickSearchClear = () => {
    quickSearchQuery.value = '';
    executeQuickSearch();
};

const onQuickSearchEnter = () => {
    if (quickSearchResults.value.length > 0) {
        addProduct(quickSearchResults.value[0]);
    }
};

const executeQuickSearch = async () => {
    quickSearchLoading.value = true;
    try {
        const params = {
            expand_variants: 1,
        };
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
    } catch {
        quickSearchResults.value = [];
    } finally {
        quickSearchLoading.value = false;
    }
};

const handleClickOutsideQuickSearch = (event) => {
    if (!quickSearchOpen.value) return;
    if (quickSearchContainerRef.value && !quickSearchContainerRef.value.contains(event.target)) {
        quickSearchOpen.value = false;
    }
};

const getOrderItemQuantity = (product) => {
    if (!product) return 0;
    const key = product.listing_key || optionKey(product);
    const existing = form.items.find((line) => line.key === key);
    return existing ? toNum(existing.quantity) : 0;
};

const decrementOrderProduct = (product) => {
    if (!product) return;
    const key = product.listing_key || optionKey(product);
    const index = form.items.findIndex((line) => line.key === key);
    if (index === -1) return;

    if (form.items[index].quantity > 1) {
        form.items[index].quantity -= 1;
    } else {
        form.items.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند من الطلب');
    }
};

const loadUnits = async (line, { keepSelection = false } = {}) => {
    try {
        const { data } = await api.get(`/admin/products/${line.product_id}/units`);
        const units = (data?.data || []).map((u) => ({
            id: u.id,
            name: u.name,
            name_ar: u.name_ar || u.name,
            base_unit_multiplier: parseFloat(u.base_unit_multiplier) || 1,
            price_multiplier: parseFloat(u.price_multiplier) || 1,
            barcode: u.barcode || '',
            is_default: !!u.is_default,
        }));
        if (!units.length) return;

        line.units = [line.unit, ...units.filter((u) => u.id !== line.unit?.id)];
        if (keepSelection) {
            const saved = units.find((u) => u.id === line.unit?.id);
            if (saved) line.unit = saved;
            return;
        }
        const defaultUnit = units.find((u) => u.is_default);
        if (defaultUnit) {
            line.unit = defaultUnit;
            if (line.base_price) {
                line.price = round2(line.base_price * defaultUnit.price_multiplier);
            }
        }
    } catch {
        // Keep piece unit
    }
};

const onUnitChange = (line, unit) => {
    line.unit = unit;
    if (unit?.price_multiplier && line.base_price) {
        line.price = round2(line.base_price * unit.price_multiplier);
    }
};

const addProduct = (product, qtyDelta = 1) => {
    if (!product) return;
    const key = product.listing_key || optionKey(product);
    const existing = form.items.find((line) => line.key === key);

    if (existing) {
        existing.quantity = (toNum(existing.quantity) || 0) + qtyDelta;
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.quantity }));
    } else {
        const unit = pieceUnit(product.unit);
        const line = {
            key,
            product_id: product.id,
            product_variant_id: product.variant_id || null,
            name: productName(product),
            sku: product.sku || '',
            variant_label: product.variant_label || '',
            image: productImageSrc(product),
            quantity: Math.max(1, qtyDelta),
            unit,
            units: [unit],
            base_price: listPrice(product),
            price: listPrice(product),
            stock: product.stock_quantity != null ? Number(product.stock_quantity) : undefined,
            discount: 0,
            tax: 0,
        };
        form.items.unshift(line);
        loadUnits(line);
        ElMessage.success(t('so_added', { name: line.name }));
    }

    delete errors.items;
};

// ── Quick Purchase Dialog State & Methods ──
const purchaseDialogVisible = ref(false);
const purchaseDialogProduct = ref(null);
const suppliersStore = useSuppliersStore();
const suppliersLoading = ref(false);
const submittingPurchase = ref(false);

const purchaseForm = reactive({
    quantity: 1,
    unit_price: 0,
    supplier_id: null,
    warehouse_id: null,
    notes: '',
});

const openPurchaseDialog = async (product) => {
    purchaseDialogProduct.value = product;
    const existingQty = getOrderItemQuantity(product);
    purchaseForm.quantity = existingQty > 0 ? existingQty : 1;
    purchaseForm.unit_price = toNum(product.cost_price || product.price || 0);
    purchaseForm.supplier_id = null;
    purchaseForm.warehouse_id = form.fulfillment_warehouse_id || (warehouses.value[0]?.id || null);
    purchaseForm.notes = locale.value === 'ar'
        ? `طلب شراء للمنتج "${productName(product)}" من شاشة إنشاء أمر البيع`
        : `Purchase request for "${productName(product)}" from sales order screen`;

    purchaseDialogVisible.value = true;

    if (!suppliersStore.suppliers?.length) {
        suppliersLoading.value = true;
        try {
            await suppliersStore.fetchSuppliers({ per_page: 100 });
        } catch {}
        finally {
            suppliersLoading.value = false;
        }
    }
};

const openPurchaseDialogFromLine = (line) => {
    const pseudoProduct = {
        id: line.product_id,
        variant_id: line.product_variant_id,
        name_ar: line.name,
        name_en: line.name,
        name: line.name,
        sku: line.sku,
        variant_label: line.variant_label,
        stock_quantity: line.stock,
        cost_price: line.base_price || line.price,
        price: line.price,
        image_main: line.image,
    };
    openPurchaseDialog(pseudoProduct);
    const shortageNeeded = linePieces(line) - (Number(line.stock) || 0);
    if (shortageNeeded > 0) {
        purchaseForm.quantity = shortageNeeded;
    }
};

const openInPurchasesScreen = () => {
    if (!purchaseDialogProduct.value) return;
    const p = purchaseDialogProduct.value;
    const query = {
        add_product_id: p.id,
        add_variant_id: p.variant_id || undefined,
        add_qty: purchaseForm.quantity || 1,
        add_price: purchaseForm.unit_price || undefined,
        supplier_id: purchaseForm.supplier_id || undefined,
        warehouse_id: purchaseForm.warehouse_id || undefined,
        notes: purchaseForm.notes || undefined,
    };
    purchaseDialogVisible.value = false;
    router.push({ path: '/admin/purchases/orders', query });
};

const submitPurchaseOrder = async () => {
    if (!purchaseDialogProduct.value) return;
    const p = purchaseDialogProduct.value;

    if (!purchaseForm.supplier_id) {
        ElMessage.warning(t('so_select_supplier') || 'يرجى اختيار المورد أولاً');
        return;
    }

    submittingPurchase.value = true;
    try {
        const payload = {
            supplier_id: purchaseForm.supplier_id,
            warehouse_id: purchaseForm.warehouse_id || null,
            order_date: today(),
            status: 'pending',
            notes: purchaseForm.notes || null,
            items: [
                {
                    product_id: p.id,
                    product_variant_id: p.variant_id || null,
                    quantity: Math.max(1, Number(purchaseForm.quantity) || 1),
                    unit_price: Number(purchaseForm.unit_price) || 0,
                    notes: purchaseForm.notes || null,
                },
            ],
        };

        const res = await purchaseOrdersApi.create(payload);
        const createdPO = res.data?.data;
        const poNumber = createdPO?.order_number || ('#' + (createdPO?.id || ''));

        ElMessageBox.alert(
            locale.value === 'ar'
                ? `تم إنشاء أمر الشراء بنجاح برقم: ${poNumber}`
                : `Purchase order created successfully with number: ${poNumber}`,
            t('success') || 'نجاح',
            {
                type: 'success',
                confirmButtonText: t('so_open_in_purchases') || 'عرض في شاشة المشتريات',
                callback: (action) => {
                    if (action === 'confirm') {
                        router.push({ path: '/admin/purchases/orders', query: { search: poNumber } });
                    }
                },
            }
        );

        purchaseDialogVisible.value = false;
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('failed_to_save_purchase_order') || 'تعذر حفظ أمر الشراء');
    } finally {
        submittingPurchase.value = false;
    }
};

const duplicateLine = (index) => {
    const line = form.items[index];
    if (!line) return;
    const copy = {
        ...line,
        key: ++lineSeq,
        units: [...(line.units || [])],
        unit: line.unit,
    };
    form.items.splice(index + 1, 0, copy);
    nextTick(() => {
        const rows = formRoot.value?.querySelectorAll('.line:not(.line--head)') || [];
        rows[index + 1]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

/* ── Totals & Validations ───────────────────────────────────────── */

const lineGross = (line) => round2(toNum(line.price) * toNum(line.quantity));
const lineTotal = (line) => round2(lineGross(line) - toNum(line.discount) + toNum(line.tax));
const lineDiscountTooBig = (line) => toNum(line.discount) > lineGross(line) + 0.00001;

const linePieces = (line) => (toNum(line.quantity) || 0) * (toNum(line.unit?.base_unit_multiplier) || 1);
const overStock = (line) => line.stock !== undefined && line.stock !== null && linePieces(line) > Number(line.stock);

const subtotal = computed(() => round2(form.items.reduce((sum, line) => sum + lineTotal(line), 0)));
const pieces = computed(() => form.items.reduce((sum, line) => sum + toNum(line.quantity), 0));
const discountTooBig = computed(() => toNum(form.discount) > subtotal.value + 0.00001);
const total = computed(() => round2(Math.max(0, subtotal.value - toNum(form.discount)) + toNum(form.tax) + toNum(form.shipping_cost)));

const overStockLines = computed(() => form.items.filter(overStock));

const onExpensesTotalChange = (newTotal) => {
    form.shipping_cost = toNum(newTotal);
    dirty.value = true;
};

/**
 * Everything still standing between the form and a save, in the order the user
 * meets it walking down the page. This used to be a single string, so a new
 * screen showed one problem and then, after fixing it, showed the next one.
 */
const issues = computed(() => {
    const list = [];
    if (!form.customer_id) {
        list.push({ key: 'customer_id', target: 'customer', text: t('qt_need_customer') });
    }
    if (!form.items.length) {
        list.push({ key: 'items', target: 'items', text: t('qt_need_lines') });
    }
    form.items.forEach((line, i) => {
        if (toNum(line.quantity) < 1) {
            list.push({
                key: `items.${i}`,
                target: `line-${i}`,
                text: `${line.name}: ${t('sof_quantity_whole') || 'the quantity must be at least 1.'}`,
            });
        }
        if (lineDiscountTooBig(line)) {
            list.push({
                key: `items.${i}.discount`,
                target: `line-${i}`,
                text: `${line.name}: ${t('qt_line_discount_too_big')}`,
            });
        }
    });
    if (discountTooBig.value) {
        list.push({ key: 'discount', target: 'discount', text: t('qt_discount_too_big') });
    }
    if (form.expected_delivery && form.order_date && form.expected_delivery < form.order_date) {
        list.push({
            key: 'expected_delivery',
            target: 'delivery',
            text: t('sof_delivery_after_order') || 'Delivery cannot be before the order date.',
        });
    }
    return list;
});

const blocker = computed(() => issues.value[0]?.text || '');

/** A field's error text: what the server said, or — once the reader has tried
 *  to save — what we can already see is wrong. An untouched form stays quiet;
 *  the status bar is where a fresh screen says what is missing. */
const fieldError = (key) => errors[key] || (isFlagged(key) ? issues.value.find((i) => i.key === key)?.text : '');

/** Highlights a field until it is corrected, so a failed save leaves a trail. */
const flagged = ref({});
const isFlagged = (key) => flagged.value[key] === true;

const flagIssues = () => {
    flagged.value = Object.fromEntries(issues.value.map((i) => [i.key, true]));
};

const clearFlag = (key) => {
    if (flagged.value[key]) {
        const next = { ...flagged.value };
        delete next[key];
        flagged.value = next;
    }
};

// Drop the highlight as soon as the thing it points at is corrected, so the
// screen does not keep badmouthing a field the reader has already fixed.
watch(() => issues.value.map((i) => i.key).join('|'), (keys, before) => {
    if (keys === before) return;
    const live = new Set(issues.value.map((i) => i.key));
    Object.keys(flagged.value).forEach((key) => {
        if (!live.has(key)) clearFlag(key);
    });
});

/** Takes the reader from the status bar straight to the thing that needs fixing. */
const goToIssue = async (issue) => {
    flagIssues();
    await nextTick();

    const lineMatch = /^line-(\d+)$/.exec(issue.target || '');
    let el = null;
    if (lineMatch) {
        el = formRoot.value?.querySelectorAll('.line:not(.line--head)')[Number(lineMatch[1])];
    } else if (issue.target === 'customer') {
        el = customerSelect.value?.$el || formRoot.value?.querySelector('.head-grid .el-select');
    } else if (issue.target === 'delivery') {
        el = deliveryPicker.value?.$el;
    } else if (issue.target === 'items') {
        el = formRoot.value?.querySelector('.lines-empty') || quickSearchInputRef.value?.$el || formRoot.value?.querySelector('.quote-search-input');
    } else if (issue.target === 'discount') {
        el = discountInput.value?.$el;
    }

    el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el?.querySelector?.('input, textarea')?.focus?.();
};

const trySubmit = async (andConfirm) => {
    if (saving.value) return;
    if (issues.value.length) {
        flagIssues();
        await goToIssue(issues.value[0]);
        return;
    }
    submit(andConfirm);
};

const lockedReason = computed(() => ({
    confirmed: t('lock_reason_confirmed'),
    processing: t('lock_reason_processing'),
    shipped: t('lock_reason_shipped'),
    delivered: t('order_delivered_cycle_complete'),
    cancelled: t('state_hint_cancelled'),
}[loadedOrder.value?.status] || t('status_forbids_item_edits')));

/* ── Warehouses ─────────────────────────────────────────────────── */

const fetchWarehouses = async () => {
    try {
        const res = await inventoryApi.getWarehouses({ per_page: 100, is_active: true });
        const data = res.data?.data || res.data || [];
        warehouses.value = Array.isArray(data) ? data : [];
        if (!form.fulfillment_warehouse_id && warehouses.value.length) {
            form.fulfillment_warehouse_id = warehouses.value[0].id;
        }
    } catch {
        warehouses.value = [];
    }
};

/* ── Draft Support ──────────────────────────────────────────────── */

let draftTimer = null;
const draftSavedAt = ref('');

const saveDraft = () => {
    if (isEdit.value) return;
    if (!form.items.length && !form.customer_id) {
        clearDraft();
        return;
    }
    try {
        const draftData = {
            saved_at: new Date().toISOString(),
            form: {
                ...form,
                items: form.items.map((line) => ({
                    ...line,
                    units: [line.unit].filter(Boolean),
                })),
            },
            selectedCustomer: selectedCustomer.value,
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        // The work is kept on this device whether or not anyone knows it, and
        // the form nags about leaving unsaved — so say when the last write was.
        draftSavedAt.value = new Date().toLocaleTimeString(
            locale.value === 'ar' ? 'ar' : 'en-GB',
            { hour: '2-digit', minute: '2-digit' }
        );
    } catch {
        // Storage full or unavailable
    }
};

const clearDraft = () => {
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignore */ }
    draftSavedAt.value = '';
};

const offerDraft = async () => {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft = null; }
    const draftItems = draft?.form?.items || draft?.lines;
    if (!draftItems?.length) return;

    try {
        await ElMessageBox.confirm(
            t('so_restore_draft_message', { count: draftItems.length }),
            t('so_restore_draft_title'),
            {
                type: 'info',
                confirmButtonText: t('so_restore_draft'),
                cancelButtonText: t('so_discard_draft'),
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
        order_date: saved.order_date || today(),
        expected_delivery: saved.expected_delivery || inDays(3),
        fulfillment_type: saved.fulfillment_type || 'ship',
        fulfillment_warehouse_id: saved.fulfillment_warehouse_id || null,
        shipping_cost: toNum(saved.shipping_cost),
        shipping_address: saved.shipping_address || '',
        discount: toNum(saved.discount),
        tax: toNum(saved.tax),
        notes: saved.notes || '',
        items: draftItems.map((line) => {
            const l = {
                ...line,
                key: ++lineSeq,
                quantity: toNum(line.quantity) || 1,
                price: toNum(line.price),
                discount: toNum(line.discount),
                tax: toNum(line.tax),
            };
            loadUnits(l, { keepSelection: true });
            return l;
        }),
    });

    if (draft.selectedCustomer) {
        selectedCustomer.value = draft.selectedCustomer;
        customerOptions.value = [draft.selectedCustomer];
    } else if (form.customer_id) {
        try {
            const { data } = await posApi.customerShow(form.customer_id);
            if (data?.data) {
                selectedCustomer.value = data.data;
                customerOptions.value = [data.data];
            }
        } catch { /* ignore */ }
    }
};

watch(
    () => [form.customer_id, form.items, form.discount, form.tax, form.shipping_cost, form.notes, form.fulfillment_type, form.fulfillment_warehouse_id],
    () => {
        dirty.value = true;
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 600);
    },
    { deep: true }
);

/* ── Load Existing Order to Edit ─────────────────────────────────── */

const loadOrder = async () => {
    loading.value = true;
    try {
        const { data } = await salesOrdersApi.getById(orderId);
        const order = data?.data || null;
        loadedOrder.value = order;
        if (!order) return;

        if (order.status !== 'pending') {
            isLocked.value = true;
            return;
        }

        form.customer_id = order.customer_id;
        selectedCustomer.value = order.customer || null;
        customerOptions.value = order.customer ? [order.customer] : [];
        form.order_date = order.order_date ? String(order.order_date).slice(0, 10) : today();
        form.expected_delivery = order.expected_delivery ? String(order.expected_delivery).slice(0, 10) : '';
        form.fulfillment_type = order.fulfillment_type || 'ship';
        form.fulfillment_warehouse_id = order.fulfillment_warehouse_id || null;
        form.shipping_address = order.shipping_address || '';
        form.shipping_cost = toNum(order.shipping_cost);
        form.discount = toNum(order.discount);
        form.tax = toNum(order.tax);
        form.notes = order.notes || '';
        form.expenses = (order.expenses || []).map((exp) => ({
            id: exp.id || null,
            category: exp.category || 'shipping',
            description: exp.description || '',
            amount: toNum(exp.amount),
            status: exp.status || 'paid',
            notes: exp.notes || '',
        }));

        if (!form.expenses.length && toNum(order.shipping_cost) > 0) {
            form.expenses.push({
                category: 'shipping',
                description: t('quick_add_shipping') || 'شحن وتوصيل',
                amount: toNum(order.shipping_cost),
                status: 'paid',
                notes: '',
            });
        }

        form.items = (order.items || []).map((item) => {
            const unit = {
                ...pieceUnit(item.unit_name || item.product?.unit),
                id: item.product_unit_id || null,
                base_unit_multiplier: parseFloat(item.unit_multiplier) || 1,
            };
            const line = {
                key: ++lineSeq,
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                name: item.product?.name_ar || item.product?.name_en || item.description || t('unknown_product'),
                sku: item.variant?.sku || item.product?.sku || '',
                variant_label: variantLabelOf(item.variant) || '',
                image: productImageSrc(item.product),
                quantity: toNum(item.quantity) || 1,
                unit,
                units: [unit],
                base_price: toNum(item.product?.price) || toNum(item.unit_price),
                price: toNum(item.unit_price),
                stock: toNum(item.product?.stock_quantity) || 0,
                discount: toNum(item.discount),
                tax: toNum(item.tax),
            };
            loadUnits(line, { keepSelection: true });
            return line;
        });

        await nextTick();
        dirty.value = false;
    } catch {
        ElMessage.error(t('failed_to_load_sales_order_for_edit'));
    } finally {
        loading.value = false;
    }
};

/* ── Save / Submit / Confirm ─────────────────────────────────────── */

const openOrder = (id) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: id, tab: 'execution' } });
};

const submit = async (andConfirm) => {
    if (blocker.value || saving.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    serverErrors.value = [];
    saving.value = andConfirm ? 'confirm' : 'draft';

    const payload = {
        customer_id: form.customer_id,
        order_date: form.order_date || null,
        expected_delivery: form.expected_delivery || null,
        fulfillment_type: form.fulfillment_type || 'ship',
        fulfillment_warehouse_id: form.fulfillment_warehouse_id || null,
        shipping_cost: toNum(form.shipping_cost),
        shipping_address: form.shipping_address || null,
        discount: toNum(form.discount),
        tax: toNum(form.tax),
        notes: form.notes || null,
        items: form.items.map((line) => ({
            product_id: line.product_id,
            product_variant_id: line.product_variant_id || null,
            product_unit_id: line.unit?.id || null,
            quantity: toNum(line.quantity),
            unit_price: round2(line.price),
            discount: toNum(line.discount),
            tax: toNum(line.tax),
        })),
        expenses: (form.expenses || [])
            .filter((exp) => Number(exp.amount) > 0 || (exp.description && exp.description.trim()))
            .map((exp) => ({
                id: exp.id || null,
                category: exp.category || 'shipping',
                description: exp.description?.trim() || t('quick_add_shipping') || 'شحن وتوصيل',
                amount: toNum(exp.amount),
                status: exp.status || 'paid',
                notes: exp.notes || null,
            })),
        ...(andConfirm && !isEdit.value ? { execute: 'confirm' } : {}),
    };

    try {
        let orderIdToOpen = orderId;
        let execution = null;

        if (isEdit.value) {
            await salesOrdersApi.update(orderId, payload);
            orderIdToOpen = orderId;
            if (andConfirm) {
                try {
                    await salesOrdersApi.confirm(orderId);
                    execution = { confirmed: true };
                } catch (error) {
                    execution = {
                        confirmed: false,
                        message: error.response?.data?.message,
                        shortages: error.response?.data?.data?.shortages || [],
                    };
                }
            }
        } else {
            const { data } = await salesOrdersApi.create(payload);
            orderIdToOpen = data?.data?.id;
            execution = data?.execution || null;
        }

        dirty.value = false;
        clearDraft();

        if (execution && !execution.confirmed) {
            const shown = await handleStockShortage(
                { response: { data: { data: { shortages: execution.shortages || [] } } } },
                orderIdToOpen,
            );
            if (!shown) {
                await ElMessageBox.alert(execution.message || t('so_saved_not_confirmed'), t('so_saved_as_draft'), { type: 'warning' }).catch(() => {});
            }
        } else {
            ElMessage.success(execution?.confirmed
                ? t('so_saved_and_confirmed')
                : (isEdit.value ? t('sales_order_updated') : t('sales_order_created')));
        }

        if (orderIdToOpen) {
            openOrder(orderIdToOpen);
        } else {
            router.push('/admin/sales/sales-orders');
        }
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        if (Object.keys(fieldErrors).length) {
            Object.entries(fieldErrors).forEach(([key, messages]) => {
                errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
            });
            serverErrors.value = Object.values(fieldErrors).flat();
        } else {
            const msg = error.response?.data?.message || error.message || t('failed_to_save_sales_order');
            serverErrors.value = [msg];
            ElMessage.error(msg);
        }
    } finally {
        saving.value = null;
    }
};

const clearOrder = async () => {
    try {
        await ElMessageBox.confirm(t('confirm_clear_all_items'), t('clear_the_form'), { type: 'warning' });
    } catch {
        return;
    }
    Object.assign(form, blank());
    selectedCustomer.value = null;
    customerOptions.value = [];
    justAddedCustomerId.value = null;
    closeQuickCustomer();
    clearDraft();
    ElMessage.success(t('form_cleared'));
};

const goBack = async () => {
    if (dirty.value && !isEdit.value && form.items.length) {
        try {
            await ElMessageBox.confirm(t('unsaved_order_warning'), t('leave_without_saving'), {
                confirmButtonText: t('leave'),
                cancelButtonText: t('stay'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    router.push('/admin/sales/sales-orders');
};

/* ── Guards & Shortcuts ─────────────────────────────────────────── */

onBeforeRouteLeave(async () => {
    if (!dirty.value || isLocked.value || saving.value) return true;
    if (!isEdit.value) return true; // Draft is preserved
    try {
        await ElMessageBox.confirm(t('unsaved_order_warning'), t('leave_without_saving'), {
            type: 'warning',
            confirmButtonText: t('leave'),
            cancelButtonText: t('stay'),
        });
        return true;
    } catch {
        return false;
    }
});

const onBeforeUnload = (event) => {
    if (isEdit.value && dirty.value && !saving.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

const onKeydown = (event) => {
    if (isLocked.value || loading.value) return;
    if (event.key === 'F2') {
        event.preventDefault();
        quickSearchOpen.value = true;
        quickSearchInputRef.value?.focus();
        if (!quickSearchResults.value.length) {
            executeQuickSearch();
        }
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        trySubmit(false);
    }
};

onMounted(async () => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('mousedown', handleClickOutsideQuickSearch);
    await Promise.all([
        fetchWarehouses(),
        fetchCategories(),
    ]);

    if (orderId) {
        await loadOrder();
    } else {
        await offerDraft();
        setTimeout(() => {
            quickSearchInputRef.value?.focus();
        }, 200);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', onBeforeUnload);
    document.removeEventListener('mousedown', handleClickOutsideQuickSearch);
    clearTimeout(draftTimer);
    clearTimeout(customerTimer);
    clearTimeout(quickSearchDebounceTimer);
});
</script>

<style scoped>
.order-form-page {
    font-family: 'Cairo', sans-serif;
    padding-bottom: 2rem;
}

.order-form-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.quote-form {
    font-family: 'Cairo', sans-serif;
}

/* ── Status Bar ──────────────────────────────────────────────────────
   The form scrolls as one long column. Everything the reader needs to keep
   re-checking — what is missing, how many items, what it comes to — used to
   live at the very bottom, below the line table. Sticking one row to the top
   of the viewport keeps all three visible at once. */
.status-bar {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    gap: 0.75rem 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    padding: 0.6rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.status-bar.is-blocked {
    border-color: #fcd34d;
    background: #fffdf5;
}

.status-bar.is-ready {
    border-color: #a7f3d0;
    background: #f6fef9;
}

.sb-lead {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.86rem;
    font-weight: 700;
    color: #92400e;
    flex: 0 0 auto;
}

.status-bar.is-ready .sb-lead {
    color: #047857;
}

.sb-lead i {
    font-size: 1rem;
}

.sb-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.3rem;
    border-radius: 999px;
    background: #d97706;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
}

.sb-issues {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
    flex: 1 1 260px;
    min-width: 0;
}

.sb-issue {
    all: unset;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    box-sizing: border-box;
    cursor: pointer;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    color: #78350f;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.5;
}

.sb-issue:hover {
    background: #fde68a;
}

.sb-issue:focus-visible {
    outline: 2px solid #b45309;
    outline-offset: 2px;
}

.sb-issue i {
    font-size: 0.7rem;
    flex: 0 0 auto;
}

.sb-tail {
    display: flex;
    align-items: center;
    gap: 0.5rem 0.9rem;
    flex-wrap: wrap;
    flex: 1 1 300px;
    min-width: 0;
    justify-content: flex-end;
}

.sb-metrics {
    display: inline-flex;
    align-items: baseline;
    gap: 0.45rem;
    font-size: 0.82rem;
    color: #475569;
    font-weight: 600;
}

.sb-dot {
    color: #94a3b8;
}

.sb-total {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    padding: 0.15rem 0.6rem;
    border-radius: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.sb-total-label {
    font-size: 0.74rem;
    font-weight: 600;
    color: #1d4ed8;
}

.sb-total strong {
    font-size: 1rem;
    font-weight: 800;
    color: #1d4ed8;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.sb-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    max-width: 100%;
}

.sb-flag.is-warn {
    color: #9a3412;
    background: #ffedd5;
    border: 1px solid #fdba74;
}

.sb-flag.is-muted {
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.status-bar :deep(.el-select__placeholder.is-transparent),
.picker-hint.is-info {
    color: #64748b;
}

/* Element Plus ships a placeholder grey well under the 4.5:1 contrast floor,
   and the select's own #el-color text inherits it on every screen of this form. */
.product-picker :deep(.el-select__placeholder),
.head-grid :deep(.el-select__placeholder),
.head-subgrid :deep(.el-select__placeholder) {
    color: #64748b;
}

/* ── Field-level "this needs fixing" ─────────────────────────────── */
.is-flagged :deep(.el-input__wrapper),
.is-flagged :deep(.el-textarea__inner),
.is-flagged :deep(.el-select__wrapper) {
    box-shadow: 0 0 0 2px #f59e0b inset;
}

.lines-empty.has-error {
    border-color: #f87171;
    color: #b91c1c;
    background: #fef2f2;
}

.line.is-flagged {
    background: #fffbeb;
    box-shadow: inset 0 0 0 2px #fcd34d;
}

/* Head Grid Layout */
.head-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 0 1.25rem;
    margin-bottom: 0.5rem;
}

.head-subgrid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0 1.25rem;
    margin-bottom: 0.5rem;
}

/* Quick day chips */
.quick-days {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.4rem;
    flex-wrap: wrap;
}

.chip {
    all: unset;
    cursor: pointer;
    font-size: 0.78rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    color: #334155;
    line-height: 1.5;
    background: #ffffff;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.chip:hover {
    border-color: #60a5fa;
    color: #1d4ed8;
    background: #f8fafc;
}

.chip.is-on {
    background: #dbeafe;
    border-color: #2563eb;
    color: #1d4ed8;
    font-weight: 700;
}

.chip:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Customer option & strip */
.option-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
    width: 100%;
}

.option-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.option-meta {
    display: inline-flex;
    gap: 0.6rem;
    font-size: 0.78rem;
    color: #64748b;
    align-items: center;
}

.customer-info-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.4rem;
    padding: 0.35rem 0.65rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #f1f5f9;
    font-size: 0.8rem;
    color: #475569;
}

.cust-info-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.cust-info-item.warn {
    color: #d97706;
    font-weight: 600;
}

.cust-info-item.danger {
    color: #dc2626;
    font-weight: 700;
}

.cust-info-item.fresh {
    color: #15803d;
    font-weight: 600;
}

/* Customer picker row: select + "new client" button */
.customer-pick-row {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
    width: 100%;
}

.customer-select {
    flex: 1 1 auto;
    min-width: 0;
}

.customer-add-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 0.85rem;
    border-radius: 6px;
    border: 1px dashed #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.customer-add-btn:hover {
    border-style: solid;
    background: #dbeafe;
}
.customer-add-btn:focus-visible {
    outline: 2px solid #93c5fd;
    outline-offset: 1px;
}
.customer-add-btn.is-on {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
}

/* Empty dropdown: offer to create what was typed */
.customer-empty {
    padding: 0.85rem 1rem;
    text-align: center;
    color: #64748b;
    font-size: 0.82rem;
}
.customer-empty-text {
    margin: 0 0 0.6rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-empty-add {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    max-width: 100%;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    background: #2563eb;
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    transition: background 0.15s ease;
}
.customer-empty-add span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-empty-add:hover {
    background: #1d4ed8;
}

.qc-slide-enter-active,
.qc-slide-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.qc-slide-enter-from,
.qc-slide-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Lines Section Header */
.lines-head-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1.25rem 0 0.75rem;
    flex-wrap: wrap;
}

.lines-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.lines-title h4 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}

.lines-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.78rem;
    background: #f1f5f9;
    color: #334155;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-weight: 600;
}

.lines-badge.is-warn {
    background: #ffedd5;
    color: #9a3412;
}

.lines-summary-quick {
    display: flex;
    align-items: center;
}

.subtotal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.84rem;
    color: #166534;
}

.subtotal-pill .lbl {
    color: #15803d;
    font-weight: 600;
}

.subtotal-pill strong {
    font-weight: 700;
    font-size: 0.95rem;
}

/* ==========================================================================
   Suggested Product Search & Category Filter Panel (Stays Open)
   ========================================================================== */
.quote-search-panel {
    position: relative;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.65rem 0.75rem;
    margin-bottom: 0.85rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.quote-search-header-row {
    display: flex;
    gap: 0.65rem;
    align-items: center;
}

.search-category-select-wrap {
    width: 230px;
    flex-shrink: 0;
}

.category-filter-select :deep(.el-input__wrapper) {
    background: #ffffff;
    box-shadow: 0 0 0 1px #cbd5e1 inset;
    border-radius: 8px;
    padding: 2px 10px;
    transition: all 0.2s ease;
}

.category-filter-select :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2), 0 0 0 1px #2563eb inset;
}

.category-all-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    color: #2563eb;
}

.category-option-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.84rem;
}

.category-option-row.is-sub {
    padding-inline-start: 1rem;
    color: #475569;
}

.cat-root-icon {
    color: #2563eb;
    font-size: 0.8rem;
}

.cat-sub-icon {
    color: #94a3b8;
    font-size: 0.72rem;
}

.search-input-box {
    flex: 1;
    min-width: 0;
}

.quote-search-input :deep(.el-input__wrapper) {
    background: #ffffff;
    box-shadow: 0 0 0 1px #cbd5e1 inset;
    border-radius: 8px;
    padding: 3px 12px;
    transition: all 0.2s ease;
}

.quote-search-input :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2), 0 0 0 1px #2563eb inset;
}

.search-icon {
    font-size: 0.92rem;
    color: #64748b;
    margin-inline-end: 4px;
}

.key-hint {
    font-size: 0.7rem;
    font-family: monospace;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    padding: 0 4px;
    color: #94a3b8;
}

.btn-toggle-catalog {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.45rem 0.85rem;
    font-size: 0.84rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.btn-toggle-catalog:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.btn-toggle-catalog.is-active {
    background: #1e3a8a;
    border-color: #1e3a8a;
    color: #ffffff;
}

.count-badge {
    background: rgba(30, 58, 138, 0.1);
    color: #1e3a8a;
    font-size: 0.72rem;
    padding: 0.1rem 0.4rem;
    border-radius: 9999px;
    font-weight: 700;
}

.btn-toggle-catalog.is-active .count-badge {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Category Quick Chips */
.category-quick-chips {
    display: flex;
    gap: 0.35rem;
    align-items: center;
    margin-top: 0.5rem;
    overflow-x: auto;
    padding-bottom: 2px;
}

.cat-chip-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    padding: 0.18rem 0.65rem;
    border-radius: 9999px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.cat-chip-btn:hover {
    border-color: #93c5fd;
    color: #2563eb;
    background: #f8fafc;
}

.cat-chip-btn.is-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    font-weight: 600;
}

/* Floating / Expandable Dropdown */
.quote-suggested-panel {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 999;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 14px 30px -4px rgba(0, 0, 0, 0.16), 0 4px 10px -2px rgba(0, 0, 0, 0.08);
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
    flex-wrap: wrap;
}

.results-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
}

.search-term-badge {
    font-size: 0.75rem;
    color: #2563eb;
    font-weight: 600;
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
    font-size: 0.74rem;
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
    max-height: 420px;
    overflow-y: auto;
    padding: 0.65rem;
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
    width: 54px;
    height: 54px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-card-image :deep(.entity-image),
.item-card-image :deep(img) {
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
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.stock-badge {
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-weight: 600;
}
.stock-badge.is-ok {
    color: #16a34a;
    background: #f0fdf4;
}
.stock-badge.is-low {
    color: #d97706;
    background: #fffbeb;
}
.stock-badge.is-out {
    color: #dc2626;
    background: #fef2f2;
}

.item-card-financials {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    margin-top: 0.1rem;
}

.price-stat, .cost-stat {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.price-stat .stat-lbl, .cost-stat .stat-lbl {
    color: #64748b;
}

.price-stat .stat-val {
    color: #2563eb;
    font-size: 0.88rem;
    font-weight: 700;
}

.cost-stat .stat-val {
    font-weight: 600;
}

.old-price {
    font-size: 0.75rem;
    color: #94a3b8;
    text-decoration: line-through;
}

/* Action Area */
.item-card-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    font-weight: 600;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
    white-space: nowrap;
}

.btn-add-product:hover {
    background: #1d4ed8;
}

.btn-add-product:active {
    transform: scale(0.98);
}

/* Stepper Component */
.item-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #93c5fd;
    background: #eff6ff;
    border-radius: 6px;
    overflow: hidden;
}

.stepper-btn {
    all: unset;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    color: #1d4ed8;
    font-size: 0.78rem;
    transition: background 0.15s ease;
}

.stepper-btn:hover {
    background: #dbeafe;
}

.stepper-minus {
    border-inline-end: 1px solid #bfdbfe;
}

.stepper-plus {
    border-inline-start: 1px solid #bfdbfe;
}

.stepper-val {
    padding: 0 0.5rem;
    font-size: 0.84rem;
    font-weight: 700;
    color: #1e3a8a;
    min-width: 24px;
    text-align: center;
}

/* Add to Purchase List Button */
.btn-add-purchase {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 0.45rem 0.75rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.btn-add-purchase:hover {
    background: #e0f2fe;
    border-color: #0284c7;
    color: #0369a1;
}

.btn-add-purchase.is-highlight {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
}

.btn-add-purchase.is-highlight:hover {
    background: #fee2e2;
    border-color: #ef4444;
    color: #b91c1c;
}

/* Dropdown Footer Strip */
.dropdown-footer-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 0.84rem;
}

.footer-summary {
    color: #475569;
    font-size: 0.8rem;
}

.footer-hints {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.74rem;
    color: #64748b;
}

.footer-hints kbd {
    font-size: 0.7rem;
    font-family: monospace;
    background: #e2e8f0;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    padding: 0 4px;
    color: #475569;
}

/* Quick Purchase Dialog Styling */
.purchase-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.purchase-product-preview {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.dialog-product-img {
    width: 64px;
    height: 64px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    overflow: hidden;
    flex-shrink: 0;
}

.dialog-product-img :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dialog-product-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.dialog-product-name {
    margin: 0;
    font-size: 0.96rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
}

.dialog-product-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
}

.sku-badge {
    background: #e2e8f0;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    color: #475569;
    font-weight: 600;
}

.purchase-dialog-form {
    margin-top: 0.25rem;
}

.purchase-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.supplier-option-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.dialog-footer-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Lines Empty State */
.lines-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 2.2rem 1.4rem;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    font-size: 0.9rem;
    background: #fafafa;
}

.lines-empty.has-error {
    border-color: #f87171;
    color: #b91c1c;
    background: #fef2f2;
}

/* Lines Table */
.lines {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #ffffff;
}

/* Eight cells: product, unit, qty, price, discount, tax, total, actions. The
   actions column grew from 36px to 68px when the row gained a duplicate button,
   and the total column now shares its cell with the mobile-only label. */
.line {
    display: grid;
    grid-template-columns: minmax(0, 2fr) 112px 104px 112px 92px 92px minmax(88px, 1fr) 68px;
    gap: 0.5rem;
    align-items: center;
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.line:last-child {
    border-bottom: none;
}

.line--head {
    background: #f8fafc;
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}

.line-product {
    min-width: 0;
}

.line-product-content {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.line-thumb {
    width: 42px;
    height: 42px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
    overflow: hidden;
    background: #f8fafc;
}

.line-thumb :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.line-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.2rem;
}

.line-product strong {
    font-size: 0.88rem;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.line-meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.line-shortage-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fca5a5;
    transition: all 0.15s ease;
}

.line-shortage-btn:hover {
    background: #fee2e2;
    border-color: #ef4444;
}

.variant-tag {
    font-size: 0.72rem;
    background: #e0f2fe;
    color: #075985;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}

.stock-pill {
    font-size: 0.72rem;
    color: #15803d;
    background: #f0fdf4;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-weight: 500;
}

/* #dc2626 on #fef2f2 lands at 4.41:1, just under the 4.5:1 floor. */
.stock-pill.is-over {
    color: #b91c1c;
    background: #fef2f2;
    font-weight: 700;
}

.unit-badge {
    display: inline-block;
    font-size: 0.8rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 0.2rem 0.5rem;
    text-align: center;
    width: 100%;
    box-sizing: border-box;
}

.unit-select :deep(.el-input__wrapper) {
    padding: 0 6px;
}

.line-field :deep(.el-input-number) {
    width: 100%;
}

/* The five editable fields are wrapped only so the narrow layout can wrap them
   as one sub-grid. `display: contents` dissolves the wrapper so at full width
   the fields are still direct children of the row's own eight-column grid. */
.line-fields {
    display: contents;
}

.mobile-label {
    display: none;
}

.num {
    text-align: end;
    font-variant-numeric: tabular-nums;
}

.line-total-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    min-width: 0;
}

.line-total {
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.line-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.1rem;
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

.is-invalid :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px #ef4444 inset !important;
}

/* 12px grey on white is 3.08:1; the character counter is load-bearing when a
   note hits the 1000-character cap, so it needs to be readable. */
.quote-form :deep(.el-input__count) {
    color: #64748b;
}

.order-expenses-section {
    margin-top: 1.5rem;
}

/* Bottom Grid: Notes & Totals */
.bottom-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 1.5rem;
    margin-top: 1.25rem;
}

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

.totals.is-flagged {
    border-color: #fcd34d;
}

.server-errors {
    margin-top: 1rem;
}

.server-errors ul {
    margin: 0.25rem 0 0;
    padding-inline-start: 1.1rem;
}

/* Form Footer */
.form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: 1.75rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e2e8f0;
}

.footer-start {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-end {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.footer-hint {
    display: inline-flex;
    align-items: center;
    font-size: 0.8rem;
    color: #92400e;
    font-weight: 600;
}

/* Locked Section */
.locked {
    max-width: 640px;
    margin: 2rem auto;
    padding: 2.2rem;
    text-align: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.locked-icon {
    font-size: 2.2rem;
    color: #94a3b8;
}

.locked h2 {
    margin: 0.75rem 0 0.5rem;
    font-size: 1.25rem;
    color: #1e293b;
}

.locked p {
    color: #475569;
    font-size: 0.92rem;
    line-height: 1.7;
}

.locked-facts {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin: 1.5rem 0;
}

.locked-facts dt {
    font-size: 0.75rem;
    color: #64748b;
}

.locked-facts dd {
    margin: 0;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.05rem;
}

.locked-actions {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.locked-note {
    margin-top: 1.25rem;
    font-size: 0.82rem !important;
    color: #94a3b8 !important;
}

/* Responsive adjustments */
@media (max-width: 1300px) {
    /* The eight-column row needs roughly 725px of track plus the card's own
       padding. Above that it silently collapses the product name to zero width,
       so below 1300 each line becomes a card before the numbers get squeezed. */
    .line--head {
        display: none;
    }

    /* Rows become independent cards, so the table's own frame has to go or the
       card margins and rounded corners get clipped by its overflow. */
    .lines {
        border: none;
        border-radius: 0;
        background: transparent;
        display: grid;
        gap: 0.6rem;
    }

    .line {
        /* `auto` lets the action column shrink to its two buttons. The total gets
           a row of its own instead of the leftover sliver beside them, which is
           where it was overflowing off the side of the card. */
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            'product actions'
            'fields  fields'
            'total   total';
        row-gap: 0.6rem;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 0;
        background: #ffffff;
    }

    .line-product { grid-area: product; }
    .line-actions { grid-area: actions; align-self: start; }

    /* One sub-grid holding the five editable fields, so they wrap on their own
       instead of stranding the total underneath whichever label landed last. */
    .line-fields {
        grid-area: fields;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.5rem;
    }

    .line-total-cell {
        grid-area: total;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.45rem 0.7rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .line-total-cell .mobile-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #475569;
    }

    .line-total {
        font-size: 1rem;
    }

    .mobile-label {
        display: block;
        font-size: 0.72rem;
        color: #475569;
        margin-bottom: 0.2rem;
    }
}

/* Two columns of fields is the most a phone-width card can carry without the
   labels wrapping; auto-fit's min-content floor was dropping to one very wide
   column and making each line card three times taller than it needed to be. */
@media (max-width: 620px) {
    .line-fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 860px) {
    .order-form-container {
        padding: 1rem;
    }

    .customer-add-label {
        display: none;
    }

    .customer-add-btn {
        padding: 0 0.75rem;
        font-size: 0.95rem;
    }

    .head-grid, .head-subgrid, .bottom-grid {
        grid-template-columns: 1fr;
    }

    .status-bar {
        gap: 0.5rem 0.75rem;
        padding: 0.55rem 0.7rem;
    }

    .sb-issues {
        flex: 1 1 100%;
        order: 3;
    }

    .sb-tail {
        margin-inline-start: 0;
        flex: 1 1 100%;
        justify-content: space-between;
    }

    .product-picker {
        max-width: none;
    }

    .form-footer {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }

    .footer-start, .footer-end {
        width: 100%;
        justify-content: stretch;
    }

    .footer-start button, .footer-end button {
        flex: 1;
    }

    .footer-hint {
        flex: 1 1 100%;
        justify-content: center;
    }
}
</style>
