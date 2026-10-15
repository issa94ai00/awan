<template>
    <el-dialog
        :model-value="modelValue"
        :width="dialogWidth"
        :fullscreen="isFullscreen"
        class="quote-form-dialog"
        :close-on-click-modal="false"
        :before-close="(done) => requestClose(done)"
        @open="reset"
    >
        <!-- ── Custom Dialog Header ── -->
        <template #header>
            <div class="dialog-custom-header">
                <div class="header-title-box">
                    <span class="header-icon"><i class="fas fa-file-signature"></i></span>
                    <div>
                        <h3 class="header-title">
                            {{ quote ? $t('qt_edit_title', { number: quote.quote_number }) : $t('qt_new_title') }}
                        </h3>
                        <p class="header-sub">
                            <span v-if="quote?.quote_number" class="quote-num-mono" dir="ltr">{{ quote.quote_number }}</span>
                            <span v-if="quote?.status" class="quote-status-pill" :class="`s-${quote.status}`">
                                {{ statusLabel(quote.status) }}
                            </span>
                            <span v-if="selectedCustomer" class="header-customer-tag">
                                <i class="fas fa-user-check"></i>&nbsp;{{ selectedCustomer.name }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="header-tools">
                    <div v-if="form.items.length" class="header-metrics">
                        <span class="metric-pill">
                            <i class="fas fa-cubes"></i>
                            <span>{{ form.items.length }} {{ $t('product') }} &bull; {{ totalPieces }} {{ $t('piece') }}</span>
                        </span>
                        <span class="metric-pill total">
                            <i class="fas fa-coins"></i>
                            <strong>{{ formatCurrency(total) }}</strong>
                        </span>
                    </div>

                    <el-tooltip :content="isFullscreen ? $t('exit_fullscreen') : $t('fullscreen')" placement="bottom">
                        <el-button
                            text
                            circle
                            class="fullscreen-btn"
                            @click="isFullscreen = !isFullscreen"
                        >
                            <i class="fas" :class="isFullscreen ? 'fa-compress' : 'fa-expand'"></i>
                        </el-button>
                    </el-tooltip>
                </div>
            </div>
        </template>

        <el-form label-position="top" class="quote-form" @submit.prevent>
            <!-- ── Head Grid: Customer & Validity ── -->
            <div class="head-grid">
                <!-- Customer Selection -->
                <el-form-item :label="$t('client')" required :error="errors.customer_id" class="customer-field">
                    <div class="customer-pick-row">
                        <el-select
                            ref="customerSelectRef"
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
                                        <button v-if="canAddCustomer" type="button" class="customer-empty-add" @mousedown.prevent @click="openQuickCustomer(customerQuery)">
                                            <i class="fas fa-user-plus"></i>
                                            <span>{{ customerQuery ? $t('qt_add_as_customer', { query: customerQuery }) : $t('qt_new_customer') }}</span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </el-select>

                        <el-tooltip v-if="canAddCustomer" :content="$t('qt_new_customer')" placement="top" :show-after="400">
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
                            <i class="fas fa-wallet"></i>&nbsp;{{ $t('client_balance') || $t('balance') }}: {{ formatCurrency(selectedCustomer.balance) }}
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

                <!-- Valid Until with Quick Chips & Remaining Badge -->
                <el-form-item :label="$t('valid_until')" :error="errors.valid_until">
                    <div class="validity-input-wrap">
                        <el-date-picker
                            v-model="form.valid_until"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="YYYY-MM-DD"
                            :disabled-date="(d) => localIsoDate(d) < localIsoDate()"
                            :placeholder="$t('qt_no_expiry')"
                            style="width: 100%"
                        />
                        <span v-if="remainingDaysText" class="remaining-badge">
                            <i class="fas fa-clock"></i>&nbsp;{{ remainingDaysText }}
                        </span>
                    </div>

                    <div class="quick-days">
                        <button
                            v-for="days in [3, 7, 14, 30, 60]"
                            :key="days"
                            type="button"
                            class="chip"
                            :class="{ 'is-on': form.valid_until === inDays(days) }"
                            @click="form.valid_until = inDays(days)"
                        >
                            {{ $t('qt_days_n', { count: days }) }}
                        </button>
                    </div>
                </el-form-item>
            </div>

            <!-- ── Lines Section ── -->
            <div class="lines-head-wrap">
                <div class="lines-head-top">
                    <div class="lines-title">
                        <h4>{{ $t('items') }}</h4>
                        <span v-if="form.items.length" class="lines-badge">
                            {{ form.items.length }} {{ $t('product') }} &bull; {{ totalPieces }} {{ $t('piece') }}
                        </span>
                    </div>

                    <div v-if="form.items.length" class="lines-summary-quick">
                        <span class="subtotal-pill">
                            <span class="lbl">{{ $t('subtotal') }}:</span>
                            <strong>{{ formatCurrency(subtotal) }}</strong>
                        </span>
                    </div>
                </div>

                <!-- Fast Suggested Product Search & Category Filter Panel (Stays Open!) -->
                <div ref="quickSearchContainerRef" class="quote-search-panel" @mousedown.stop>
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
                            :title="quickSearchOpen ? $t('qt_close_search') : $t('qt_suggested_products')"
                            @click.stop.prevent="quickSearchOpen = !quickSearchOpen"
                        >
                            <i class="fas" :class="quickSearchOpen ? 'fa-chevron-up' : 'fa-boxes-stacked'"></i>
                            <span class="btn-text">{{ quickSearchOpen ? $t('qt_close_search') : $t('qt_suggested_products') }}</span>
                            <span v-if="quickSearchResults.length" class="count-badge">{{ quickSearchResults.length }}</span>
                        </button>

                        <!-- Not in the catalogue yet: create it without leaving the quote -->
                        <button
                            type="button"
                            class="btn-new-product"
                            :title="`${$t('pqa_title')} (F4)`"
                            @click.stop.prevent="openQuickProduct()"
                        >
                            <i class="fas fa-plus"></i>
                            <span class="btn-text">{{ $t('pqa_button') }}</span>
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
                            class="quote-suggested-panel"
                            @click.stop
                        >
                            <!-- Top Strip -->
                            <div class="dropdown-top-strip">
                                <div class="strip-left">
                                    <i class="fas fa-boxes-stacked text-primary"></i>
                                    <strong>{{ selectedCategoryName || $t('qt_suggested_products') }}</strong>
                                    <span class="results-badge">{{ quickSearchResults.length }}</span>
                                    <span v-if="quickSearchQuery" class="search-term-badge">
                                        "{{ quickSearchQuery }}"
                                    </span>
                                </div>
                                <div class="strip-right">
                                    <span class="keep-open-pill">
                                        <i class="fas fa-thumbtack text-success"></i>
                                        {{ $t('po_search_hint') }}
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
                                    <button type="button" class="empty-new-product" @click.stop="openQuickProduct(quickSearchQuery)">
                                        <i class="fas fa-plus"></i>
                                        <span v-if="quickSearchQuery.trim()">{{ $t('pqa_add_named', { name: quickSearchQuery.trim() }) }}</span>
                                        <span v-else>{{ $t('pqa_title') }}</span>
                                    </button>
                                    <small class="empty-new-hint">{{ $t('pqa_empty_hint') }}</small>
                                </div>

                                <div v-else class="suggested-products-grid">
                                    <template v-for="g in quickSearchGroups" :key="g.key">
                                        <!-- A product with sizes is one card with a row per size,
                                             so eight sizes of one drain read as one product with
                                             eight sizes, not as eight unrelated products. -->
                                        <div
                                            v-if="g.hasVariants"
                                            class="suggested-item-card is-group"
                                            :class="{ 'item-in-order': groupQuoteQuantity(g) > 0 }"
                                        >
                                            <div class="group-head">
                                                <div class="item-card-image">
                                                    <EntityImage
                                                        :src="productImageSrc(g.product)"
                                                        type="product"
                                                        :size="52"
                                                        shape="square"
                                                        class="card-img"
                                                    />
                                                    <span v-if="groupQuoteQuantity(g) > 0" class="in-order-tag" :title="$t('qt_in_quote')">
                                                        {{ groupQuoteQuantity(g) }}
                                                    </span>
                                                </div>
                                                <div class="item-card-body">
                                                    <div class="item-card-header">
                                                        <span class="item-name" :title="groupName(g.product)">{{ groupName(g.product) }}</span>
                                                        <span class="variants-count-chip">
                                                            <i class="fas fa-layer-group"></i> {{ $t('po_variants_count', g.rows.length) }}
                                                        </span>
                                                    </div>
                                                    <div class="item-card-tags">
                                                        <span v-if="g.product.category?.name_ar || g.product.category_name" class="cat-chip">
                                                            <i class="fas fa-folder"></i> {{ g.product.category?.name_ar || g.product.category_name }}
                                                        </span>
                                                        <span class="pick-size-hint">
                                                            <i class="fas fa-hand-pointer"></i> {{ $t('po_pick_size') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="variant-grid">
                                                <div
                                                    v-for="p in g.rows"
                                                    :key="p.listing_key || optionKey(p)"
                                                    class="variant-tile"
                                                    :class="{ 'is-in-order': getQuoteItemQuantity(p) > 0, 'is-out': p.stock_quantity != null && Number(p.stock_quantity) <= 0 }"
                                                >
                                                    <div class="variant-tile-info">
                                                        <div class="variant-tile-title">
                                                            <span class="variant-tile-label" dir="ltr">{{ p.variant_label || productName(p) }}</span>
                                                            <span v-if="p.sku" class="sku-chip"><i class="fas fa-barcode"></i> {{ p.sku }}</span>
                                                        </div>
                                                        <div class="item-card-tags">
                                                            <strong class="tile-price">{{ formatCurrency(listPrice(p)) }}</strong>
                                                            <span v-if="p.has_sale && p.price && p.price !== p.sale_price" class="old-price">
                                                                {{ formatCurrency(p.price) }}
                                                            </span>
                                                            <span
                                                                v-if="p.stock_quantity != null"
                                                                class="stock-badge"
                                                                :class="stockBadgeClass(p.stock_quantity)"
                                                            >
                                                                {{ $t('available') }}: {{ p.stock_quantity }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <OrderCardActions
                                                        class="item-card-action"
                                                        compact
                                                        :show-purchase="false"
                                                        :count="getQuoteItemQuantity(p)"
                                                        :pending="pendingQtyOf(p)"
                                                        :over-pending="isOverStock(p, pendingQtyOf(p))"
                                                        :over-count="isOverStock(p, getQuoteItemQuantity(p))"
                                                        :add-label="$t('add')"
                                                        @add="(qty) => addProduct(p, qty)"
                                                        @bump-pending="(delta) => bumpPendingQty(p, delta)"
                                                        @set-pending="(raw) => setPendingQty(p, raw)"
                                                        @blur-pending="(el) => normalizePendingQty(p, el)"
                                                        @decrement="decrementQuoteProduct(p)"
                                                        @set="(raw) => setQuoteProductQuantity(p, raw)"
                                                        @commit="(el) => commitQuoteProductQuantity(p, el)"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- A product without sizes: one card, one set of controls. -->
                                        <div
                                            v-else
                                            class="suggested-item-card"
                                            :class="{ 'item-in-order': getQuoteItemQuantity(g.product) > 0 }"
                                        >
                                            <div class="item-card-image">
                                                <EntityImage
                                                    :src="productImageSrc(g.product)"
                                                    type="product"
                                                    :size="52"
                                                    shape="square"
                                                    class="card-img"
                                                />
                                                <span v-if="getQuoteItemQuantity(g.product) > 0" class="in-order-tag" :title="$t('qt_in_quote')">
                                                    {{ getQuoteItemQuantity(g.product) }}
                                                </span>
                                            </div>

                                            <div class="item-card-body">
                                                <div class="item-card-header">
                                                    <span class="item-name" :title="productName(g.product)">
                                                        {{ productName(g.product) }}
                                                    </span>
                                                </div>

                                                <div class="item-card-tags">
                                                    <span v-if="g.product.sku" class="sku-chip">
                                                        <i class="fas fa-barcode"></i> {{ g.product.sku }}
                                                    </span>
                                                    <span v-if="g.product.category?.name_ar || g.product.category_name" class="cat-chip">
                                                        <i class="fas fa-folder"></i> {{ g.product.category?.name_ar || g.product.category_name }}
                                                    </span>
                                                    <span
                                                        v-if="g.product.stock_quantity != null"
                                                        class="stock-badge"
                                                        :class="stockBadgeClass(g.product.stock_quantity)"
                                                    >
                                                        {{ $t('available') }}: {{ g.product.stock_quantity }}
                                                    </span>
                                                </div>

                                                <div class="item-card-financials">
                                                    <div class="price-stat">
                                                        <span class="stat-lbl">{{ $t('price') }}:</span>
                                                        <strong class="stat-val text-primary">{{ formatCurrency(listPrice(g.product)) }}</strong>
                                                        <span v-if="g.product.has_sale && g.product.price && g.product.price !== g.product.sale_price" class="old-price">
                                                            {{ formatCurrency(g.product.price) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <OrderCardActions
                                                class="item-card-action"
                                                :show-purchase="false"
                                                :count="getQuoteItemQuantity(g.product)"
                                                :pending="pendingQtyOf(g.product)"
                                                :over-pending="isOverStock(g.product, pendingQtyOf(g.product))"
                                                :over-count="isOverStock(g.product, getQuoteItemQuantity(g.product))"
                                                :add-label="$t('qt_add_to_quote')"
                                                @add="(qty) => addProduct(g.product, qty)"
                                                @bump-pending="(delta) => bumpPendingQty(g.product, delta)"
                                                @set-pending="(raw) => setPendingQty(g.product, raw)"
                                                @blur-pending="(el) => normalizePendingQty(g.product, el)"
                                                @decrement="decrementQuoteProduct(g.product)"
                                                @set="(raw) => setQuoteProductQuantity(g.product, raw)"
                                                @commit="(el) => commitQuoteProductQuantity(g.product, el)"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Dropdown Footer Strip -->
                            <div class="dropdown-footer-strip">
                                <div class="footer-summary">
                                    <span>
                                        {{ $t('qt_items_in_quote_summary', { count: form.items.length }) }} &bull;
                                        <strong>{{ formatCurrency(subtotal) }}</strong>
                                    </span>
                                    <button type="button" class="footer-new-product" @click.stop="openQuickProduct(quickSearchQuery)">
                                        <kbd>F4</kbd> {{ $t('pqa_not_listed') }}
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="btn-done-search"
                                    @click.stop="quickSearchOpen = false"
                                >
                                    <i class="fas fa-check"></i> {{ $t('qt_close_search') }}
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!form.items.length" class="lines-empty" :class="{ 'has-error': errors.items }">
                <i class="fas fa-box-open empty-icon"></i>
                <div class="empty-content">
                    <p class="empty-title">{{ errors.items || $t('qt_no_lines') }}</p>
                    <span class="empty-hint">{{ $t('qt_product_search_placeholder') }} (F2)</span>
                </div>
            </div>

            <!-- Lines Table -->
            <div v-else class="lines">
                <div class="line line--head" aria-hidden="true">
                    <span class="col-num">#</span>
                    <span>{{ $t('product') }}</span>
                    <span>{{ $t('so_unit') || $t('unit') }}</span>
                    <span>{{ $t('quantity') }}</span>
                    <span>{{ $t('unit_price') }}</span>
                    <span>{{ $t('discount') }}</span>
                    <span>{{ $t('tax') }}</span>
                    <span class="num">{{ $t('total') }}</span>
                    <span class="col-actions">{{ $t('actions') }}</span>
                </div>

                <div v-for="(line, i) in form.items" :key="line.key" class="line">
                    <span class="col-num line-idx">{{ i + 1 }}</span>

                    <div class="line-product">
                        <div class="line-product-main">
                            <EntityImage
                                :src="line.image"
                                type="product"
                                :size="42"
                                shape="square"
                                class="line-thumb"
                            />
                            <div class="line-product-text">
                                <strong :title="line.name">{{ line.name }}</strong>
                                <div class="line-meta">
                                    <span v-if="line.sku" class="sku-mini" dir="ltr">{{ line.sku }}</span>
                                    <span v-if="line.category_name" class="cat-mini"><i class="fas fa-folder"></i> {{ line.category_name }}</span>
                                    <span v-if="line.variant_label" class="variant-tag">{{ line.variant_label }}</span>
                                    <span v-if="line.stock !== undefined" :class="['stock-pill', overStock(line) ? 'is-over' : '']">
                                        {{ overStock(line) ? ($t('so_stock_shortage') || 'يتجاوز المتوفر') : `${$t('available')}: ${line.stock}` }}
                                    </span>
                                    <span v-if="isPriceEdited(line)" class="price-hint">
                                        {{ $t('qt_catalog_price', { price: formatCurrency(line.base_price) }) }}
                                    </span>
                                </div>
                                <!-- The other sizes of this product, one click away: changing
                                     the size keeps the quantity and a price that was typed. -->
                                <div v-if="lineSiblings(line).length > 1" class="line-variant-switch">
                                    <span class="switch-label"><i class="fas fa-tag"></i> {{ $t('po_switch_size') }}</span>
                                    <div class="switch-chips">
                                        <button
                                            v-for="v in lineSiblings(line)"
                                            :key="v.listing_key || optionKey(v)"
                                            type="button"
                                            class="switch-chip"
                                            :class="{
                                                'is-active': (v.variant_id || null) === (line.product_variant_id || null),
                                                'is-out': v.stock_quantity != null && Number(v.stock_quantity) <= 0,
                                            }"
                                            :title="[v.sku, formatCurrency(listPrice(v))].filter(Boolean).join(' · ')"
                                            @click.stop="switchLineVariant(line, v)"
                                        >
                                            <span class="chip-label" dir="ltr">{{ v.variant_label || v.sku }}</span>
                                            <small v-if="v.stock_quantity != null" class="chip-stock">{{ v.stock_quantity }}</small>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Editable Line Description / Specs -->
                        <div class="line-desc-edit">
                            <el-input
                                v-model="line.description"
                                size="small"
                                :placeholder="$t('item_description_hint') || 'بيان الصنف أو تفاصيل مخصصة في عرض السعر...'"
                                class="desc-input"
                            />
                        </div>
                    </div>

                    <!-- Unit Selection -->
                    <div class="line-field">
                        <span class="mobile-label">{{ $t('so_unit') || $t('unit') }}</span>
                        <el-select
                            v-if="line.units && line.units.length > 1"
                            v-model="line.unit"
                            value-key="id"
                            size="small"
                            class="unit-select"
                            @change="(u) => onUnitChange(line, u)"
                        >
                            <el-option v-for="u in line.units" :key="u.id" :label="u.name_ar || u.name" :value="u" />
                        </el-select>
                        <span v-else class="unit-badge">{{ line.unit?.name_ar || line.unit?.name || $t('piece') }}</span>
                    </div>

                    <!-- Quantity -->
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('quantity') }}</span>
                        <el-input-number
                            v-model="line.quantity"
                            :min="1"
                            :step="1"
                            step-strictly
                            size="small"
                            controls-position="right"
                        />
                    </label>

                    <!-- Unit Price -->
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('unit_price') }}</span>
                        <el-input-number
                            v-model="line.unit_price"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                        />
                    </label>

                    <!-- Discount -->
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('discount') }}</span>
                        <el-input-number
                            v-model="line.discount"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                            :class="{ 'is-invalid': lineDiscountTooBig(line) || errors[`items.${i}.discount`] }"
                        />
                    </label>

                    <!-- Tax -->
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('tax') }}</span>
                        <el-input-number
                            v-model="line.tax"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                        />
                    </label>

                    <!-- Line Total -->
                    <span class="num line-total">{{ formatCurrency(lineTotal(line)) }}</span>

                    <!-- Row Actions (Duplicate & Delete) -->
                    <div class="col-actions line-actions-cell">
                        <el-tooltip :content="$t('duplicate_line')" placement="top">
                            <el-button
                                text
                                circle
                                size="small"
                                class="action-btn"
                                @click="duplicateLine(i)"
                            >
                                <i class="fas fa-copy"></i>
                            </el-button>
                        </el-tooltip>
                        <el-tooltip :content="$t('delete')" placement="top">
                            <el-button
                                text
                                circle
                                size="small"
                                type="danger"
                                class="action-btn"
                                @click="form.items.splice(i, 1)"
                            >
                                <i class="fas fa-trash-can"></i>
                            </el-button>
                        </el-tooltip>
                    </div>
                </div>
            </div>

            <!-- ── Bottom Grid: Terms, Notes & Totals ── -->
            <div class="bottom-grid">
                <div class="terms-notes-box">
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

                    <el-form-item :label="$t('qt_terms')">
                        <!-- Quick Terms Presets -->
                        <div class="quick-terms-bar">
                            <span class="quick-terms-label">{{ $t('qt_quick_terms') }}</span>
                            <button
                                v-for="(term, idx) in termsPresets"
                                :key="idx"
                                type="button"
                                class="term-preset-chip"
                                @click="appendTerm(term)"
                            >
                                + {{ term }}
                            </button>
                        </div>
                        <el-input
                            v-model="form.terms"
                            type="textarea"
                            :rows="3"
                            maxlength="2000"
                            :placeholder="$t('qt_terms_placeholder')"
                        />
                    </el-form-item>
                </div>

                <!-- Totals Card -->
                <div class="totals">
                    <div class="totals-row">
                        <span>{{ $t('subtotal') }}</span>
                        <span class="num">{{ formatCurrency(subtotal) }}</span>
                    </div>

                    <!-- Extra Discount with Amount / Percentage Modes -->
                    <div class="totals-row">
                        <div class="discount-label-group">
                            <span>{{ $t('qt_extra_discount') }}</span>
                            <div class="discount-mode-toggle">
                                <button
                                    type="button"
                                    class="mode-btn"
                                    :class="{ 'is-active': discountMode === 'amount' }"
                                    @click="setDiscountMode('amount')"
                                >
                                    {{ $t('amount') }}
                                </button>
                                <button
                                    type="button"
                                    class="mode-btn"
                                    :class="{ 'is-active': discountMode === 'percent' }"
                                    @click="setDiscountMode('percent')"
                                >
                                    %
                                </button>
                            </div>
                        </div>

                        <div class="discount-input-wrap">
                            <el-input-number
                                v-if="discountMode === 'percent'"
                                v-model="discountRate"
                                :min="0"
                                :max="100"
                                :precision="1"
                                :controls="false"
                                size="small"
                                class="discount-number-input"
                                @change="onDiscountRateChange"
                            />
                            <el-input-number
                                v-else
                                v-model="form.discount"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                size="small"
                                class="discount-number-input"
                                :class="{ 'is-invalid': discountTooBig || errors.discount }"
                            />
                            <span v-if="discountMode === 'percent'" class="discount-calc-preview">
                                = {{ formatCurrency(form.discount) }}
                            </span>
                        </div>
                    </div>

                    <!-- Extra Tax with Quick 15% VAT Action -->
                    <div class="totals-row">
                        <div class="tax-label-group">
                            <span>{{ $t('qt_extra_tax') }}</span>
                            <button
                                type="button"
                                class="vat-quick-chip"
                                @click="applyVat15"
                            >
                                {{ $t('apply_vat_15') }}
                            </button>
                        </div>
                        <el-input-number
                            v-model="form.tax"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                        />
                    </div>

                    <!-- Grand Total -->
                    <div class="totals-row grand">
                        <div>
                            <span>{{ $t('total') }}</span>
                            <span v-if="form.items.length" class="grand-sub">{{ totalPieces }} {{ $t('piece') }}</span>
                        </div>
                        <span class="num">{{ formatCurrency(total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Blocker Alert -->
            <el-alert
                v-if="blocker"
                type="warning"
                :closable="false"
                show-icon
                :title="blocker"
                class="blocker"
            />
        </el-form>

        <!-- ── Dialog Footer ── -->
        <template #footer>
            <div class="dialog-actions-footer">
                <div class="footer-left-group">
                    <el-button @click="requestClose()">{{ $t('cancel') }}</el-button>
                    <el-button
                        v-if="!quote && form.items.length"
                        text
                        type="danger"
                        @click="clearItems"
                    >
                        <i class="fas fa-trash-can"></i>&nbsp;{{ $t('clear_the_form') }}
                    </el-button>
                </div>

                <div class="footer-save-group">
                    <el-button
                        :loading="saving === 'draft'"
                        :disabled="!!blocker || !!saving"
                        @click="submit(false)"
                    >
                        <i class="fas fa-floppy-disk"></i>&nbsp;{{ quote ? $t('save_changes') : $t('qt_save_draft') }}
                    </el-button>
                    <el-button
                        v-if="!quote || quote.status === 'draft'"
                        type="primary"
                        :loading="saving === 'send'"
                        :disabled="!!blocker || !!saving"
                        @click="submit(true)"
                    >
                        <i class="fas fa-paper-plane"></i>&nbsp;{{ $t('qt_save_and_send') }}
                    </el-button>
                </div>
            </div>
        </template>
        <ProductQuickAdd
            v-model="quickProductVisible"
            :seed="quickProductSeed"
            :categories="categoryOptions"
            :default-category-id="selectedCategoryId"
            @created="onQuickProductCreated"
            @use-existing="(row, qty) => addProduct(row, qty)"
        />
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { posApi } from '@/api/pos';
import { categoriesApi } from '@/api/categories';
import api from '@/api/index';
import { useQuotesStore } from '@/stores/quotes';
import { useAuthStore } from '@/stores/auth';
import { apiErrorMessage, formatCurrency, localIsoDate, statusLabel } from '@/utils/sales';
import { optionKey, variantLabelOf } from '@/utils/productPick';
import { resolveImageUrl } from '@/utils/productImages';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import OrderCardActions from '@/components/admin/sales/OrderCardActions.vue';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import CustomerQuickAdd from '@/components/admin/sales/CustomerQuickAdd.vue';
import ProductQuickAdd from '@/components/admin/sales/ProductQuickAdd.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The full quote to edit; null to write a new one. */
    quote: { type: Object, default: null },
    /** Starting customer for a new quote (e.g. the one the list is filtered on). */
    presetCustomer: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'saved']);

const { t, locale } = useI18n();
const store = useQuotesStore();
// Adding a client needs its own permission (customers.create).
const canAddCustomer = useAuthStore().can('customers.create');

const isFullscreen = ref(false);

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
    valid_until: inDays(14),
    discount: 0,
    tax: 0,
    notes: '',
    terms: '',
    items: [],
});

const form = reactive(blank());
const errors = reactive({});
const saving = ref(null);
const dialogWidth = computed(() => (window.innerWidth < 1000 ? '98%' : '1120px'));

let lineSeq = 0;
let snapshot = '';
const serialize = () => JSON.stringify(form);
const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

// ── Customers ──────────────────────────────────────────────────────────
const customerOptions = ref([]);
const customersLoading = ref(false);
const selectedCustomer = ref(null);
let customerTimer = null;

const searchCustomers = (query) => {
    customerQuery.value = String(query || '').trim();
    clearTimeout(customerTimer);
    customerTimer = setTimeout(async () => {
        customersLoading.value = true;
        try {
            const res = await posApi.customers({ search: query || undefined, per_page: 30, mine: 1 });
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
    const c = customerOptions.value.find((cust) => cust.id === customerId);
    selectedCustomer.value = c || null;
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
// The panel itself lives in CustomerQuickAdd.vue (shared with the sales
// order form); this form only decides when it is open and what seeds it.
const customerSelectRef = ref(null);
const customerQuery = ref('');
const justAddedCustomerId = ref(null);
const quickCustomer = reactive({ open: false, seed: '' });

const openQuickCustomer = (seed = '') => {
    customerSelectRef.value?.blur();
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

// ── Categories & Products Catalog ─────────────────────────────────────
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

// Enter adds what the search unambiguously points at: a lone product, or a
// lone size. With several sizes showing it is the seller's call which one.
const onQuickSearchEnter = () => {
    if (quickSearchLoading.value) return;
    const first = quickSearchGroups.value[0];
    // Nothing matched what was typed: offer to create it.
    if (!first && quickSearchQuery.value.trim()) {
        openQuickProduct(quickSearchQuery.value);
        return;
    }
    if (!first) return;
    if (!first.hasVariants || first.rows.length === 1) {
        addProduct(first.rows[0], pendingQtyOf(first.rows[0]));
    }
};

// Search rows regrouped by product: a product with sizes comes back as one
// row per size, and the panel shows those as one card with a tile per size.
const quickSearchGroups = computed(() => {
    const groups = new Map();
    quickSearchResults.value.forEach((row) => {
        if (!groups.has(row.id)) groups.set(row.id, { key: String(row.id), product: row, rows: [] });
        groups.get(row.id).rows.push(row);
    });
    return [...groups.values()].map((g) => ({ ...g, hasVariants: g.rows.some((r) => r.variant_id) }));
});

const groupQuoteQuantity = (group) => group.rows.reduce((sum, row) => sum + getQuoteItemQuantity(row), 0);

// The product's own name on a size row, without the size appended.
const groupName = (row) => (locale.value === 'en' && (row.product_name_en || row.name_en)
    ? row.product_name_en || row.name_en
    : row.product_name_ar || row.name_ar || row.name || '');

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
        rememberVariantRows(quickSearchResults.value);
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

// The line a search row stands for. Lines restored from a saved offer carry
// their own keys, so this goes by product and size rather than by the key
// the search row would have been added under.
const lineFor = (product) => {
    if (!product) return undefined;
    const productId = Number(product.id ?? product.product_id);
    const variantId = product.variant_id ?? product.product_variant_id ?? null;
    return form.items.find((line) => Number(line.product_id) === productId && (line.product_variant_id || null) === (variantId || null));
};

const getQuoteItemQuantity = (product) => {
    const existing = lineFor(product);
    return existing ? toNum(existing.quantity) : 0;
};

// Quantity typed on a result card before the product is on the offer, keyed
// by listing key. Absent means 1.
const pendingQty = reactive({});
const productKey = (product) => product.listing_key || optionKey(product);
const pendingQtyOf = (product) => toNum(pendingQty[productKey(product)]) || 1;

const setPendingQty = (product, raw) => {
    const n = Math.floor(toNum(raw));
    if (n >= 1) pendingQty[productKey(product)] = n;
};

const bumpPendingQty = (product, delta) => {
    pendingQty[productKey(product)] = Math.max(1, pendingQtyOf(product) + delta);
};

const normalizePendingQty = (product, input) => {
    const shown = pendingQtyOf(product);
    if (input && String(input.value) !== String(shown)) input.value = shown;
};

const isOverStock = (product, qty) =>
    product?.stock_quantity != null && toNum(qty) > Number(product.stock_quantity);

/** Direct edit of a line's quantity from its result card; 0 or empty is resolved on blur. */
const setQuoteProductQuantity = (product, raw) => {
    const line = lineFor(product);
    if (!line) return;
    const n = Math.floor(toNum(raw));
    if (n >= 1) line.quantity = n;
};

const commitQuoteProductQuantity = (product, input) => {
    const index = form.items.indexOf(lineFor(product));
    if (index === -1) return;
    const n = Math.floor(toNum(input?.value));
    if (n < 1) {
        form.items.splice(index, 1);
        ElMessage.info(t('item_removed'));
        return;
    }
    form.items[index].quantity = n;
    if (input && String(input.value) !== String(n)) input.value = n;
};

const decrementQuoteProduct = (product) => {
    const index = form.items.indexOf(lineFor(product));
    if (index === -1) return;

    if (form.items[index].quantity > 1) {
        form.items[index].quantity -= 1;
    } else {
        form.items.splice(index, 1);
        ElMessage.info(t('item_removed'));
    }
};

// The sibling sizes of each picked product, for the switcher under a line.
const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();
const lineSiblings = (line) => (line.product_id && line.product_variant_id ? siblingsOf(line.product_id) : []);
const preloadLineSiblings = () => form.items.forEach((line) => line.product_variant_id && ensureVariantsLoaded(line.product_id));

// The description a line starts with; one still equal to it follows a size switch.
const autoDescription = (product) => productName(product) + (product.variant_label ? ` - ${product.variant_label}` : '');

// Switching a line to another size keeps its quantity, unit and a price that
// was typed; a price still at the old size's list price follows the new one.
// If that size is already a line, the two lines become one.
const switchLineVariant = (line, row) => {
    if (!line || (row.variant_id || null) === (line.product_variant_id || null)) return;
    const index = form.items.indexOf(line);
    if (index === -1) return;

    const other = lineFor(row);
    if (other && other !== line) {
        other.quantity = toNum(other.quantity) + toNum(line.quantity);
        form.items.splice(index, 1);
        ElMessage.success(t('so_qty_now', { name: other.name, count: other.quantity }));
        return;
    }

    const multiplier = toNum(line.unit?.price_multiplier) || 1;
    const priceUntouched = !isPriceEdited(line);
    const descriptionUntouched = !line.description || line.description === productName(line) + (line.variant_label ? ` - ${line.variant_label}` : '');
    line.product_variant_id = row.variant_id || null;
    line.name = productName(row);
    if (descriptionUntouched) line.description = autoDescription(row);
    line.sku = row.sku || '';
    line.variant_label = row.variant_label || '';
    line.image = productImageSrc(row) || line.image;
    line.stock = row.stock_quantity != null ? Number(row.stock_quantity) : undefined;
    line.base_price = listPrice(row);
    if (priceUntouched) line.unit_price = round2(line.base_price * multiplier);
};

const productName = (p) => (locale.value === 'en' && p.name_en ? p.name_en : (p.name_ar || p.name || ''));
const listPrice = (p) => toNum(p.has_sale && p.sale_price ? p.sale_price : p.price);

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
                line.unit_price = round2(line.base_price * defaultUnit.price_multiplier);
            }
        }
    } catch {
        // Keep piece unit
    }
};

const onUnitChange = (line, unit) => {
    line.unit = unit;
    if (unit?.price_multiplier && line.base_price) {
        line.unit_price = round2(line.base_price * unit.price_multiplier);
    }
};

const addProduct = (product, qtyDelta = 1) => {
    if (!product) return;
    const key = product.listing_key || optionKey(product);
    const existing = lineFor(product);
    if (product.variant_id) ensureVariantsLoaded(product.id);

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
            description: autoDescription(product),
            sku: product.sku || '',
            variant_label: product.variant_label || '',
            image: productImageSrc(product),
            category_name: product.category?.name_ar || product.category?.name || product.category_name || '',
            quantity: Math.max(1, qtyDelta),
            unit,
            units: [unit],
            base_price: listPrice(product),
            unit_price: listPrice(product),
            stock: product.stock_quantity != null ? Number(product.stock_quantity) : undefined,
            discount: 0,
            tax: 0,
        };
        form.items.unshift(line);
        loadUnits(line);
        ElMessage.success(
            qtyDelta > 1 ? t('so_added_n', { name: line.name, count: qtyDelta }) : t('so_added', { name: line.name }),
        );
    }

    delete pendingQty[key];
    delete errors.items;
};

const duplicateLine = (index) => {
    const original = form.items[index];
    if (!original) return;
    const clone = {
        ...original,
        key: ++lineSeq,
        quantity: toNum(original.quantity),
        discount: toNum(original.discount),
        tax: toNum(original.tax),
        units: [...(original.units || [original.unit])],
    };
    form.items.splice(index + 1, 0, clone);
    ElMessage.success(t('item_duplicated') || 'تم تكرار البند');
};

const clearItems = async () => {
    try {
        await ElMessageBox.confirm(t('confirm_clear_all_items'), t('confirm'), { type: 'warning' });
    } catch {
        return;
    }
    form.items = [];
    ElMessage.success(t('form_cleared'));
};

// ── Totals & Calculations ──────────────────────────────────────────────
const lineGross = (line) => round2(toNum(line.unit_price) * toNum(line.quantity));
const lineTotal = (line) => round2(lineGross(line) - toNum(line.discount) + toNum(line.tax));
const lineDiscountTooBig = (line) => toNum(line.discount) > lineGross(line) + 0.00001;

const linePieces = (line) => (toNum(line.quantity) || 0) * (toNum(line.unit?.base_unit_multiplier) || 1);
const overStock = (line) => line.stock !== undefined && line.stock !== null && linePieces(line) > Number(line.stock);
const isPriceEdited = (line) => line.base_price > 0 && Math.abs(toNum(line.unit_price) - toNum(line.base_price)) >= 0.01;

const subtotal = computed(() => round2(form.items.reduce((sum, line) => sum + lineTotal(line), 0)));
const totalPieces = computed(() => form.items.reduce((sum, line) => sum + toNum(line.quantity), 0));
const discountTooBig = computed(() => toNum(form.discount) > subtotal.value + 0.00001);
const total = computed(() => round2(Math.max(0, subtotal.value - toNum(form.discount)) + toNum(form.tax)));

// Discount Mode Toggle
const discountMode = ref('amount'); // 'amount' | 'percent'
const discountRate = ref(0);

const setDiscountMode = (mode) => {
    discountMode.value = mode;
    if (mode === 'percent' && subtotal.value > 0) {
        discountRate.value = round2((toNum(form.discount) / subtotal.value) * 100);
    }
};

const onDiscountRateChange = () => {
    if (discountMode.value === 'percent') {
        const rate = Math.min(100, Math.max(0, toNum(discountRate.value)));
        form.discount = round2((subtotal.value * rate) / 100);
    }
};

const applyVat15 = () => {
    const net = Math.max(0, subtotal.value - toNum(form.discount));
    form.tax = round2(net * 0.15);
    ElMessage.success(t('vat_applied') || 'تم احتساب ضريبة 15%');
};

const blocker = computed(() => {
    if (!form.customer_id) return t('qt_need_customer');
    if (!form.items.length) return t('qt_need_lines');
    if (form.items.some(lineDiscountTooBig)) return t('qt_line_discount_too_big');
    if (discountTooBig.value) return t('qt_discount_too_big');
    return '';
});

// Remaining days calculation
const remainingDaysText = computed(() => {
    if (!form.valid_until) return '';
    const target = new Date(form.valid_until);
    const now = new Date(localIsoDate());
    const diff = Math.ceil((target - now) / (1000 * 60 * 60 * 24));
    if (diff <= 0) return t('expired') || 'منتهي';
    return t('qt_remaining_days', { days: diff });
});

// Terms Presets
const termsPresets = [
    'الدفع نقداً عند الاستلام',
    'الأسعار شاملة ضريبة القيمة المضافة',
    'العرض ساري لمدة 14 يوماً من تاريخه',
    'التسليم خلال 3 أيام عمل من تأكيد الطلب',
    'الضمان عامان على عيوب الصناعة',
];

const appendTerm = (text) => {
    if (!form.terms) {
        form.terms = text;
    } else if (!form.terms.includes(text)) {
        form.terms += `\n• ${text}`;
    }
};

// ── Open, Reset, Close, Save ──────────────────────────────────────────
const reset = () => {
    closeQuickCustomer();
    justAddedCustomerId.value = null;
    Object.keys(errors).forEach((k) => delete errors[k]);
    saving.value = null;
    discountMode.value = 'amount';
    discountRate.value = 0;
    quickSearchQuery.value = '';
    selectedCategoryId.value = null;
    quickSearchOpen.value = false;

    fetchCategories();
    executeQuickSearch();

    const q = props.quote;
    if (q) {
        Object.assign(form, {
            customer_id: q.customer_id,
            valid_until: q.valid_until && String(q.valid_until).slice(0, 10) >= localIsoDate()
                ? String(q.valid_until).slice(0, 10)
                : null,
            discount: toNum(q.discount),
            tax: toNum(q.tax),
            notes: q.notes || '',
            terms: q.terms || '',
            items: (q.items || []).map((item) => {
                const unit = pieceUnit(item.unit_name || item.product?.unit);
                const line = {
                    key: ++lineSeq,
                    product_id: item.product_id,
                    product_variant_id: item.product_variant_id || item.variant?.id || null,
                    name: item.product?.name_ar || item.description || '—',
                    description: item.description || item.product?.name_ar || '—',
                    sku: item.variant?.sku || item.product?.sku || '',
                    variant_label: variantLabelOf(item.variant) || '',
                    image: productImageSrc(item.product),
                    category_name: item.product?.category?.name_ar || item.product?.category?.name || item.product?.category_name || '',
                    quantity: toNum(item.quantity) || 1,
                    unit,
                    units: [unit],
                    base_price: toNum(item.product?.price) || toNum(item.unit_price),
                    unit_price: toNum(item.unit_price),
                    stock: toNum(item.product?.stock_quantity) || 0,
                    discount: toNum(item.discount),
                    tax: toNum(item.tax),
                };
                loadUnits(line, { keepSelection: true });
                return line;
            }),
        });
        preloadLineSiblings();
        customerOptions.value = q.customer ? [q.customer] : [];
        selectedCustomer.value = q.customer || null;
    } else {
        Object.assign(form, blank());
        form.customer_id = props.presetCustomer?.id || null;
        customerOptions.value = props.presetCustomer ? [props.presetCustomer] : [];
        selectedCustomer.value = props.presetCustomer || null;
    }
    snapshot = serialize();
};

const requestClose = async (done) => {
    if (saving.value) return;
    if (serialize() !== snapshot) {
        try {
            await ElMessageBox.confirm(t('prod_admin_discard_changes'), t('confirm'), {
                confirmButtonText: t('prod_admin_discard'),
                cancelButtonText: t('prod_admin_keep_editing'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    if (typeof done === 'function') done();
    emit('update:modelValue', false);
};

const submit = async (andSend) => {
    if (blocker.value || saving.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    saving.value = andSend ? 'send' : 'draft';

    const payload = {
        customer_id: form.customer_id,
        valid_until: form.valid_until || null,
        discount: toNum(form.discount),
        tax: toNum(form.tax),
        notes: form.notes || null,
        terms: form.terms || null,
        items: form.items.map((line) => ({
            product_id: line.product_id,
            description: line.description || line.name,
            quantity: toNum(line.quantity),
            unit_price: toNum(line.unit_price),
            discount: toNum(line.discount),
            tax: toNum(line.tax),
        })),
    };

    try {
        let saved = props.quote
            ? await store.updateQuote(props.quote.id, payload)
            : await store.createQuote(payload);

        if (andSend && saved?.status === 'draft') {
            try {
                saved = await store.updateQuoteStatus(saved, 'sent');
            } catch (error) {
                ElMessage.warning(apiErrorMessage(error, t('failed_to_update_quote_status')));
            }
        }

        ElMessage.success(props.quote ? t('qt_saved') : t('qt_created', { number: saved?.quote_number || '' }));
        snapshot = serialize();
        emit('saved', saved);
        emit('update:modelValue', false);
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        Object.entries(fieldErrors).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
        ElMessage.error(apiErrorMessage(error, t('qt_save_failed')));
    } finally {
        saving.value = null;
    }
};

// ── Quick "new product" ──
// What the seller searched for seeds the dialog, so a failed search turns
// straight into the product it was looking for.
const quickProductVisible = ref(false);
const quickProductSeed = ref('');

const openQuickProduct = (seed = '') => {
    quickProductSeed.value = String(seed || '').trim();
    quickProductVisible.value = true;
    if (!categories.value.length) fetchCategories();
};

const onQuickProductCreated = (product, quantity = 1) => {
    addProduct({ ...product, listing_key: optionKey(product) }, quantity);
    quickSearchQuery.value = '';
    quickSearchOpen.value = false;
};

// Keyboard shortcuts
const onKeydown = (event) => {
    // The new-product dialog owns the keyboard while it is open.
    if (!props.modelValue || quickProductVisible.value) return;
    if (event.key === 'F2') {
        event.preventDefault();
        quickSearchInputRef.value?.focus();
        quickSearchOpen.value = true;
    }
    if (event.key === 'F4' && !event.altKey) {
        event.preventDefault();
        openQuickProduct(quickSearchQuery.value);
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        if (!saving.value && !blocker.value) submit(false);
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    document.addEventListener('click', handleClickOutsideQuickSearch);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', handleClickOutsideQuickSearch);
    clearTimeout(customerTimer);
    clearTimeout(quickSearchDebounceTimer);
});
</script>

<style scoped>
.quote-form-dialog :deep(.el-dialog__header) {
    padding: 1.25rem 1.5rem 0.75rem;
    margin-right: 0;
    border-bottom: 1px solid #f1f5f9;
}

.dialog-custom-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-title-box {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 10px;
    font-size: 1.25rem;
}

.header-title {
    margin: 0;
    font-size: 1.18rem;
    font-weight: 700;
    color: #0f172a;
}

.header-sub {
    margin: 0.2rem 0 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.quote-num-mono {
    font-family: monospace;
    font-size: 0.85rem;
    color: #475569;
    font-weight: 600;
}

.quote-status-pill {
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
}
.quote-status-pill.s-draft { background: #fef3c7; color: #b45309; }
.quote-status-pill.s-sent { background: #e0f2fe; color: #0369a1; }
.quote-status-pill.s-accepted { background: #dcfce7; color: #15803d; }
.quote-status-pill.s-rejected { background: #fee2e2; color: #b91c1c; }

.header-customer-tag {
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #334155;
    padding: 0.1rem 0.5rem;
    border-radius: 4px;
}

.header-tools {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-metrics {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.metric-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    font-size: 0.82rem;
    color: #475569;
}

.metric-pill.total {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.fullscreen-btn {
    font-size: 1.05rem;
    color: #64748b;
}
.fullscreen-btn:hover {
    color: #2563eb;
    background: #eff6ff;
}

.quote-form {
    font-family: 'Cairo', sans-serif;
    padding: 0.5rem 0.25rem;
}

/* Head Grid */
.head-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 0 1.25rem;
    margin-bottom: 0.5rem;
}

/* Customer Option & Strip */
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
    margin-top: 0.35rem;
    padding: 0.35rem 0.65rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #f1f5f9;
    font-size: 0.78rem;
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

/* Validity & Quick Days */
.validity-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.remaining-badge {
    font-size: 0.75rem;
    color: #2563eb;
    background: #eff6ff;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    white-space: nowrap;
    font-weight: 600;
}

.quick-days {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.4rem;
    flex-wrap: wrap;
}

.chip {
    all: unset;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    color: #475569;
    line-height: 1.6;
    background: #ffffff;
    transition: all 0.15s ease;
}

.chip:hover {
    border-color: #93c5fd;
    color: #2563eb;
    background: #f8fafc;
}

.chip.is-on {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
    font-weight: 600;
}

.chip:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Lines Section Head & Fast Search Panel */
.lines-head-wrap {
    margin: 0.75rem 0 0.85rem;
}

.lines-head-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.lines-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.lines-title h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.lines-badge {
    font-size: 0.78rem;
    background: #f1f5f9;
    color: #475569;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-weight: 500;
}

.lines-summary-quick {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.subtotal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    padding: 0.2rem 0.6rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    color: #1d4ed8;
}

.subtotal-pill .lbl {
    color: #64748b;
    font-weight: 500;
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

.btn-new-product {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    border-radius: 8px;
    padding: 0.45rem 0.85rem;
    font-size: 0.84rem;
    font-weight: 700;
    color: #047857;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.btn-new-product:hover {
    background: #059669;
    border-color: #059669;
    color: #ffffff;
}

.btn-new-product .key-hint {
    background: rgba(255, 255, 255, 0.6);
}

.empty-new-product {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    padding: 0.55rem 1.1rem;
    border: none;
    border-radius: 8px;
    background: #059669;
    color: #ffffff;
    font: inherit;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);
    max-width: 100%;
}

.empty-new-product span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.empty-new-product:hover {
    background: #047857;
}

.empty-new-hint {
    font-size: 0.75rem;
    color: #94a3b8;
}

.footer-new-product {
    background: none;
    border: none;
    padding: 0;
    font: inherit;
    color: #047857;
    font-weight: 600;
    cursor: pointer;
}

.footer-new-product:hover {
    text-decoration: underline;
}

.footer-new-product kbd {
    font-size: 0.7rem;
    font-family: monospace;
    background: #e2e8f0;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    padding: 0 4px;
    color: #475569;
}

@media (max-width: 860px) {
    .quote-search-header-row { flex-wrap: wrap; }
    .btn-new-product .key-hint { display: none; }
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

.price-stat {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.price-stat .stat-lbl {
    color: #64748b;
}

.price-stat .stat-val {
    color: #2563eb;
    font-size: 0.88rem;
    font-weight: 700;
}

.old-price {
    font-size: 0.75rem;
    color: #94a3b8;
    text-decoration: line-through;
}

/* Action Area */
.item-card-action {
    flex-shrink: 0;
    margin-inline-start: auto;
}

/* One product, several sizes */
.suggested-item-card.is-group {
    flex-direction: column;
    align-items: stretch;
    gap: 0.6rem;
}

.group-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.variants-count-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.05rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #6d28d9;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
}

.pick-size-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #64748b;
    font-size: 0.72rem;
}

.variant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.45rem;
}

.variant-tile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.6rem;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #f8fafc;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.variant-tile:hover {
    border-color: #93c5fd;
    background: #ffffff;
}

.variant-tile.is-in-order {
    border-color: #86efac;
    background: #f0fdf4;
}

.variant-tile.is-out {
    background: #fff7ed;
}

.variant-tile-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.variant-tile-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.variant-tile-label {
    font-size: 0.86rem;
    font-weight: 700;
    color: #1e3a8a;
    unicode-bidi: isolate;
}

.tile-price {
    font-size: 0.8rem;
    color: #1e3a8a;
}

.variant-tile .item-card-action {
    margin-inline-start: 0;
}

/* Sibling sizes under a line */
.line-variant-switch {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-top: 0.35rem;
}

.switch-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.switch-chips {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.switch-chip {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.5;
    transition: all 0.15s ease;
}

.switch-chip:hover {
    border-color: #93c5fd;
    color: #1d4ed8;
}

.switch-chip.is-active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    cursor: default;
}

.switch-chip.is-out {
    color: #9a3412;
    border-style: dashed;
}

.switch-chip .chip-label {
    unicode-bidi: isolate;
}

.switch-chip .chip-stock {
    font-size: 0.66rem;
    font-weight: 600;
    color: #16a34a;
    padding-inline-start: 0.3rem;
    border-inline-start: 1px solid #e2e8f0;
}

.switch-chip.is-out .chip-stock {
    color: #dc2626;
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
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem 1rem;
}

.footer-summary strong {
    color: #2563eb;
}

.btn-done-search {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #16a34a;
    color: #ffffff;
    padding: 0.35rem 0.85rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.15s ease;
}

.btn-done-search:hover {
    background: #15803d;
}

/* Lines Empty State */
.lines-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 2.4rem 1.5rem;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    background: #fafafa;
}

.lines-empty.has-error {
    border-color: #f87171;
    color: #b91c1c;
    background: #fef2f2;
}

.empty-icon {
    font-size: 2.2rem;
    color: #94a3b8;
}

.empty-title {
    margin: 0;
    font-weight: 600;
    color: #475569;
    font-size: 0.95rem;
}

.empty-hint {
    font-size: 0.8rem;
    color: #94a3b8;
}

/* Lines Table */
.lines {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #ffffff;
}

.line {
    display: grid;
    grid-template-columns: 32px minmax(0, 2fr) 115px 105px 115px 95px 95px minmax(95px, 1fr) 68px;
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

.col-num {
    text-align: center;
    color: #94a3b8;
    font-size: 0.78rem;
    font-weight: 600;
}

.col-actions {
    text-align: center;
}

.line-actions-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
}

.action-btn {
    font-size: 0.82rem;
}

.line-product {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.35rem;
}

.line-product-main {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.line-thumb {
    width: 42px;
    height: 42px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.line-thumb :deep(.entity-image) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
}

.line-product-text {
    flex: 1;
    min-width: 0;
}

.line-product-text strong {
    font-size: 0.88rem;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}

.sku-mini {
    font-family: monospace;
    font-size: 0.72rem;
    color: #475569;
    background: #f1f5f9;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-weight: 600;
}

.cat-mini {
    font-size: 0.72rem;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 500;
}

.line-meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.15rem;
}

.line-desc-edit {
    margin-top: 0.1rem;
}

.desc-input :deep(.el-input__wrapper) {
    background: #fafafa;
    border: 1px dashed #e2e8f0;
    box-shadow: none !important;
    padding: 0 6px;
    font-size: 0.76rem;
}
.desc-input :deep(.el-input__wrapper:focus-within) {
    background: #ffffff;
    border-style: solid;
    border-color: #93c5fd;
}

.variant-tag {
    font-size: 0.72rem;
    background: #e0f2fe;
    color: #0369a1;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}

.stock-pill {
    font-size: 0.72rem;
    color: #16a34a;
    background: #f0fdf4;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-weight: 500;
}

.stock-pill.is-over {
    color: #dc2626;
    background: #fef2f2;
    font-weight: 700;
}

.price-hint {
    font-size: 0.72rem;
    color: #94a3b8;
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

.mobile-label {
    display: none;
}

.num {
    text-align: end;
    font-variant-numeric: tabular-nums;
}

.line-total {
    font-weight: 700;
    color: #0f172a;
}

.is-invalid :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px #ef4444 inset !important;
}

/* Bottom Grid: Terms & Totals */
.bottom-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 1.25rem;
    margin-top: 1.25rem;
}

.quick-terms-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.quick-terms-label {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
}

.term-preset-chip {
    all: unset;
    cursor: pointer;
    font-size: 0.72rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
}

.term-preset-chip:hover {
    background: #e0f2fe;
    color: #0369a1;
    border-color: #93c5fd;
}

/* Totals Box */
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

.discount-label-group, .tax-label-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.discount-mode-toggle {
    display: inline-flex;
    background: #e2e8f0;
    border-radius: 4px;
    padding: 1px;
}

.mode-btn {
    all: unset;
    cursor: pointer;
    font-size: 0.68rem;
    padding: 0.1rem 0.35rem;
    border-radius: 3px;
    color: #64748b;
    font-weight: 600;
}
.mode-btn.is-active {
    background: #ffffff;
    color: #2563eb;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.discount-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.discount-number-input {
    width: 110px;
}

.discount-calc-preview {
    font-size: 0.75rem;
    color: #2563eb;
    white-space: nowrap;
}

.vat-quick-chip {
    all: unset;
    cursor: pointer;
    font-size: 0.68rem;
    color: #2563eb;
    background: #eff6ff;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}
.vat-quick-chip:hover {
    background: #dbeafe;
}

.totals-row :deep(.el-input-number) {
    width: 120px;
}

.totals-row.grand {
    border-top: 1px solid #e2e8f0;
    margin-top: 0.45rem;
    padding-top: 0.7rem;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}

.grand-sub {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: #64748b;
}

.totals-row.grand .num {
    color: #2563eb;
}

.blocker {
    margin-top: 0.75rem;
}

/* Footer */
.dialog-actions-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    width: 100%;
}

.footer-left-group, .footer-save-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Responsive */
@media (max-width: 820px) {
    .head-grid, .bottom-grid {
        grid-template-columns: 1fr;
    }

    .customer-add-label {
        display: none;
    }

    .customer-add-btn {
        padding: 0 0.75rem;
        font-size: 0.95rem;
    }

    .line--head {
        display: none;
    }

    .line {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 0.5rem;
    }

    .col-num {
        display: none;
    }

    .line-product {
        grid-column: 1 / -1;
    }

    /* The quantity stepper and add button no longer fit beside the thumbnail
       and name on a phone, so the controls drop to their own row. */
    .suggested-item-card:not(.is-group) {
        flex-wrap: wrap;
    }

    .suggested-item-card:not(.is-group) .item-card-action {
        flex: 1 1 100%;
        justify-content: flex-end;
        margin-inline-start: 0;
    }

    .variant-grid {
        grid-template-columns: 1fr;
    }

    .variant-tile {
        flex-wrap: wrap;
    }

    .quote-search-header-row {
        flex-direction: column;
        align-items: stretch;
    }

    .search-category-select-wrap {
        width: 100%;
    }

    .btn-toggle-catalog {
        justify-content: center;
        width: 100%;
    }

    .dialog-actions-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }

    .footer-left-group, .footer-save-group {
        width: 100%;
        justify-content: stretch;
    }

    .footer-left-group button, .footer-save-group button {
        flex: 1;
    }
}
</style>
