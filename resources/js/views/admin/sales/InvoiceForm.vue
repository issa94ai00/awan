<template>
    <div class="order-form-page invoice-form-page" :class="{ 'is-focus': focusMode }">
        <!-- ── Focus mode bar (replaces the page header) ──────────── -->
        <div v-if="focusMode" class="focus-bar">
            <div class="focus-bar-title">
                <i class="fas fa-file-invoice-dollar"></i>
                <strong>{{ isEdit ? (t('edit_invoice') || 'تعديل الفاتورة') : (t('sinv_new_invoice')) }}</strong>
                <span class="focus-badge"><i class="fas fa-bullseye"></i> {{ t('sinv_focus_mode') }}</span>
            </div>
            <button type="button" class="focus-toggle" @click="focusMode = false">
                <i class="fas fa-up-right-and-down-left-from-center"></i>
                {{ t('sinv_show_all') }}
                <kbd class="key-hint">F8</kbd>
            </button>
        </div>

        <!-- ── Top Header ─────────────────────────────────────────── -->
        <AdminPageHeader
            v-else
            icon="fas fa-file-invoice-dollar text-primary"
            :title="isEdit ? (t('edit_invoice') || 'تعديل الفاتورة') : (t('create_invoice') || 'إصدار فاتورة مبيعات')"
            :subtitle="isEdit && loadedInvoice ? `${t('invoice_number') || 'رقم الفاتورة'}: ${loadedInvoice.invoice_number}` : (t('prepare_invoice_with_products') || 'إعداد فاتورة مبيعات وإدارة الأصناف والتحصيل')"
        >
            <template #actions>
                <el-button type="primary" plain @click="focusMode = true">
                    <i class="fas fa-bullseye"></i>&nbsp;{{ t('sinv_focus_mode') }}
                </el-button>
                <el-button text @click="shortcutsModalVisible = true">
                    <i class="fas fa-keyboard"></i>&nbsp;{{ t('keyboard_shortcuts') || 'اختصارات لوحة المفاتيح' }}
                </el-button>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_invoices') || 'العودة للفواتير' }}
                </el-button>
                <el-button v-if="!isEdit && items.length" text type="danger" @click="confirmClearInvoice">
                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('sinv_clear') }}
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
                    {{ issues.length ? (t('sinv_to_finish')) : (t('sinv_ready')) }}
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
                    <span class="sb-metric">{{ t('sinv_items_count', { count: items.length }) }}</span>
                    <span class="sb-dot" aria-hidden="true">·</span>
                    <span class="sb-metric">{{ t('sinv_pieces_count', { count: totalPieces }) }}</span>
                    <span class="sb-total">
                        <span class="sb-total-label">{{ t('total') || 'الإجمالي' }}</span>
                        <strong>{{ money(total) }}</strong>
                    </span>
                </div>

                <span v-if="shortLines.length" class="sb-flag is-warn">
                    <i class="fas fa-triangle-exclamation"></i>
                    {{ t('sinv_lines_over_stock', { count: shortLines.length }) }}
                </span>

                <span v-if="draftSavedAt" class="sb-flag is-muted fx-hide">
                    <i class="fas fa-floppy-disk"></i>
                    {{ t('sinv_draft_saved', { time: draftSavedAt }) }}
                </span>
            </div>
        </div>

        <!-- Loading skeleton -->
        <el-skeleton v-if="loading" :rows="8" animated class="order-skeleton" />

        <!-- ── Main Form Container ────────────────────────────────── -->
        <div v-else ref="formRoot" class="order-form-container panel-card">
            <!-- Server / validation errors & shortage notices -->
            <el-alert
                v-if="formErrors.length"
                :title="t('sinv_save_problems')"
                type="error"
                show-icon
                class="mb-4 form-error-alert"
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
                        :label="t('client') || 'العميل'"
                        :class="{ 'is-flagged': isFlagged('customer_id') }"
                    >
                        <div class="customer-pick-row">
                            <!-- Walk-in Customer Button -->
                            <el-tooltip
                                :content="isWalkInCustomer ? t('sinv_walk_in_on_hint') : t('sinv_walk_in_off_hint')"
                                placement="top"
                            >
                                <button
                                    type="button"
                                    class="btn-walkin"
                                    :class="{ 'is-active': isWalkInCustomer }"
                                    @click="toggleWalkInCustomer"
                                >
                                    <i class="fas fa-cash-register"></i>
                                    <span>{{ t('sinv_walk_in') }}</span>
                                </button>
                            </el-tooltip>

                            <!-- Registered Customer Select -->
                            <el-select
                                v-if="!isWalkInCustomer"
                                ref="customerSelectRef"
                                v-model="form.customer_id"
                                filterable
                                clearable
                                remote
                                reserve-keyword
                                :remote-method="searchCustomers"
                                :loading="customersLoading"
                                :placeholder="t('search_or_choose_client') || 'ابحث باسم العميل، الهاتف، أو الشركة…'"
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
                                        <div class="client-option-main">
                                            <span class="client-option-name">{{ customer.name }}</span>
                                            <span v-if="customer.phone" class="client-option-phone" dir="ltr">
                                                <i class="fas fa-phone"></i> {{ customer.phone }}
                                            </span>
                                        </div>
                                        <span v-if="clientContactLine(customer)" class="client-option-meta">
                                            {{ clientContactLine(customer) }}
                                        </span>
                                    </div>
                                </el-option>

                                <template #loading>
                                    <div class="select-state">
                                        <i class="fas fa-spinner fa-spin text-primary"></i>&nbsp;{{ t('searching') || 'جاري البحث…' }}
                                    </div>
                                </template>

                                <template #empty>
                                    <div class="select-empty">
                                        <p>{{ t('no_clients_found') || 'لم يتم العثور على عملاء مطابقين' }}</p>
                                        <el-button type="primary" text @click="openQuickCustomer(customerQuery)">
                                            <i class="fas fa-plus"></i>&nbsp;{{ customerQuery ? (t('add_client_named', { name: customerQuery }) || `إضافة "${customerQuery}" كعميل جديد`) : (t('new_client') || 'إضافة عميل جديد') }}
                                        </el-button>
                                    </div>
                                </template>
                            </el-select>

                            <!-- Walk-in active display -->
                            <div v-else class="walkin-active-box">
                                <i class="fas fa-user-tag text-success"></i>
                                <span>{{ t('sinv_walk_in_active') }}</span>
                                <el-button text size="small" type="primary" @click="isWalkInCustomer = false">
                                    {{ t('sinv_pick_registered') }}
                                </el-button>
                            </div>

                            <el-tooltip v-if="!isWalkInCustomer" :content="t('new_client') || 'إضافة عميل جديد'" placement="top">
                                <el-button
                                    type="primary"
                                    class="btn-quick-customer"
                                    @click="toggleQuickCustomer"
                                >
                                    <i class="fas fa-user-plus"></i>
                                </el-button>
                            </el-tooltip>
                        </div>

                        <!-- Selected Customer Info & Balance Strip -->
                        <div v-if="selectedCustomer && !isWalkInCustomer" class="customer-info-strip">
                            <span class="cust-info-item">
                                <i class="fas fa-user text-primary"></i>&nbsp;{{ selectedCustomer.name }}
                            </span>
                            <span v-if="selectedCustomer.phone" class="cust-info-item" dir="ltr">
                                <i class="fas fa-phone text-muted"></i>&nbsp;{{ selectedCustomer.phone }}
                            </span>
                            <span v-if="selectedCustomer.company" class="cust-info-item">
                                <i class="fas fa-building text-muted"></i>&nbsp;{{ selectedCustomer.company }}
                            </span>
                            <span v-if="clientBalance" class="cust-info-item" :class="clientBalance.tone">
                                <i class="fas fa-wallet"></i>&nbsp;{{ clientBalance.label }}: {{ money(clientBalance.amount) }}
                            </span>

                            <!-- Use Customer Credit Button if Available -->
                            <button
                                v-if="hasCustomerCredit"
                                type="button"
                                class="btn-use-credit"
                                @click="applyCustomerCredit"
                            >
                                <i class="fas fa-arrow-down"></i>&nbsp;{{ t('sinv_use_credit') }}
                            </button>

                            <button
                                type="button"
                                class="btn-clear-customer"
                                :title="t('sinv_clear_customer')"
                                @click="clearCustomerSelection"
                            >
                                <i class="fas fa-xmark"></i>
                            </button>
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
                        :label="t('due_date') || 'تاريخ الاستحقاق'"
                        :class="{ 'is-flagged': isFlagged('due_date'), 'fx-hide': !isFlagged('due_date') }"
                    >
                        <el-date-picker
                            ref="deliveryPicker"
                            v-model="form.due_date"
                            type="date"
                            value-format="YYYY-MM-DD"
                            format="YYYY-MM-DD"
                            :placeholder="t('due_date') || 'اختر التاريخ'"
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
                                {{ days === 0 ? t('today') : t(days <= 10 ? 'sinv_days_few' : 'sinv_days_many', { count: days }) }}
                            </button>
                        </div>
                    </el-form-item>
                </div>

                <!-- ── Secondary options (warehouse, rep, status): collapsed summary by default ── -->
                <button
                    type="button"
                    class="more-options-toggle"
                    :class="{ 'is-open': moreOptionsOpen, 'fx-hide': !!form.default_warehouse_id }"
                    :aria-expanded="moreOptionsOpen"
                    @click="moreOptionsOpen = !moreOptionsOpen"
                >
                    <span class="mot-title">
                        <i class="fas fa-sliders"></i>&nbsp;{{ t('sinv_more_options') }}
                    </span>
                    <span class="mot-summary">
                        <span class="mot-chip">
                            <i class="fas fa-warehouse"></i>
                            {{ selectedWarehouseName || (t('sinv_no_warehouse')) }}
                        </span>
                        <span class="mot-chip">
                            <i class="fas fa-user-tie"></i>
                            {{ selectedEmployeeName || t('sinv_unassigned_rep') }}
                        </span>
                        <span v-if="isEdit" class="mot-chip">
                            <i class="fas fa-flag"></i>
                            {{ statusLabels[form.status] || form.status }}
                        </span>
                    </span>
                    <i class="fas fa-chevron-down mot-caret"></i>
                </button>

                <div v-show="moreOptionsOpen" class="head-subgrid" :class="{ 'fx-hide': !!form.default_warehouse_id }">
                    <el-form-item :label="t('sinv_default_warehouse')">
                        <el-select
                            v-model="form.default_warehouse_id"
                            filterable
                            clearable
                            :loading="warehousesLoading"
                            :placeholder="t('choose_warehouse_placeholder') || 'اختر مستودع الصرف'"
                            style="width: 100%"
                            @change="onDefaultWarehouseChange"
                        >
                            <template #prefix>
                                <i class="fas fa-warehouse text-primary"></i>
                            </template>
                            <el-option
                                v-for="wh in warehouses"
                                :key="wh.id"
                                :label="wh.name"
                                :value="wh.id"
                            >
                                <div class="warehouse-option-row">
                                    <span class="wh-name">
                                        <i class="fas fa-warehouse text-muted me-1"></i>
                                        {{ wh.name }}
                                    </span>
                                    <span v-if="wh.code" class="option-meta" dir="ltr">{{ wh.code }}</span>
                                </div>
                            </el-option>
                            <template #empty>
                                <div class="wh-empty-state">
                                    <i class="fas fa-warehouse-slash"></i>
                                    <span>{{ t('sinv_no_warehouses') }}</span>
                                    <el-button size="small" text type="primary" @click="fetchWarehouses">
                                        <i class="fas fa-rotate"></i>&nbsp;{{ t('sinv_retry') }}
                                    </el-button>
                                </div>
                            </template>
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('sales_representative') || 'مندوب المبيعات'">
                        <el-select
                            v-model="form.assigned_employee_id"
                            filterable
                            :clearable="!isSalesRep"
                            :disabled="isSalesRep"
                            :loading="employeesLoading"
                            :placeholder="t('unassigned') || 'غير محدد (اختر المندوب)'"
                            style="width: 100%"
                        >
                            <template #prefix>
                                <i class="fas fa-user-tie text-primary"></i>
                            </template>
                            <el-option
                                v-for="employee in salesEmployees"
                                :key="employee.id"
                                :label="employee.name"
                                :value="employee.id"
                            >
                                <div class="employee-option-row">
                                    <span class="emp-name">
                                        <i class="fas fa-user-tie text-muted me-1"></i>
                                        {{ employee.name }}
                                    </span>
                                    <span v-if="employee.department" class="option-meta">{{ employee.department }}</span>
                                </div>
                            </el-option>
                            <template #empty>
                                <div class="wh-empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <span>{{ t('sinv_no_reps') }}</span>
                                    <el-button size="small" text type="primary" @click="fetchEmployees">
                                        <i class="fas fa-rotate"></i>&nbsp;{{ t('sinv_retry') }}
                                    </el-button>
                                </div>
                            </template>
                        </el-select>
                    </el-form-item>

                    <el-form-item v-if="isEdit" :label="t('sinv_status')">
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
                        <h4>{{ t('invoice_items') || 'بنود الفاتورة' }}</h4>
                        <span v-if="items.length" class="lines-badge">
                            {{ t('sinv_items_count', { count: items.length }) }} &bull; {{ t('sinv_pieces_count', { count: totalPieces }) }}
                        </span>
                        <span v-if="shortLines.length" class="lines-badge is-warn">
                            <i class="fas fa-triangle-exclamation"></i>
                            {{ t('sinv_lines_over_stock', { count: shortLines.length }) }}
                        </span>
                    </div>

                    <div v-if="items.length" class="lines-summary-quick fx-hide">
                        <span class="subtotal-pill">
                            <span class="lbl">{{ t('subtotal') || 'المجموع الفرعي' }}:</span>
                            <strong>{{ money(subtotal) }}</strong>
                        </span>
                        <el-popconfirm
                            :title="t('confirm_clear_all_lines') || 'هل تريد تفريغ وحذف جميع البنود؟'"
                            :confirm-button-text="t('yes_delete_all') || 'نعم، حذف الكل'"
                            :cancel-button-text="t('cancel') || 'إلغاء'"
                            confirm-button-type="danger"
                            @confirm="clearAllLines"
                        >
                            <template #reference>
                                <button type="button" class="btn-clear-lines" :title="t('clear_all_items') || 'تفريغ كافة البنود'">
                                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('clear_all_items') || 'تفريغ البنود' }}
                                </button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>

                <!-- ── Modern Interactive Search & Category Filter Panel ── -->
                <div ref="quickSearchContainerRef" class="quote-search-panel order-search-panel" @mousedown.stop>
                    <div class="quote-search-header-row">
                        <!-- Category Filter Dropdown -->
                        <div class="search-category-select-wrap fx-hide">
                            <el-select
                                v-model="selectedCategoryId"
                                clearable
                                filterable
                                :placeholder="t('qt_filter_category') || t('category') || 'كافة التصنيفات'"
                                class="category-filter-select"
                                :loading="categoriesLoading"
                                @change="onCategoryFilterChange"
                            >
                                <template #prefix>
                                    <i class="fas fa-folder-tree text-muted"></i>
                                </template>
                                <el-option
                                    :value="null"
                                    :label="t('qt_all_categories') || t('all_categories') || 'جميع التصنيفات'"
                                >
                                    <span class="category-all-label">
                                        <i class="fas fa-layer-group"></i> {{ t('qt_all_categories') || t('all_categories') || 'جميع التصنيفات' }}
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

                        <!-- Fast Search Input with Barcode & Enter Handling -->
                        <div class="search-input-box">
                            <el-input
                                ref="quickSearchInputRef"
                                v-model="quickSearchQuery"
                                :placeholder="t('search_product_placeholder') || 'ابحث بالاسم، الباركود، الكود (F2)...'"
                                clearable
                                class="quote-search-input"
                                @focus="onQuickSearchFocus"
                                @input="onQuickSearchInput"
                                @clear="onQuickSearchClear"
                                @keydown.enter.prevent="onQuickSearchEnter"
                                @keydown.down.prevent="onSearchArrowDown"
                                @keydown.up.prevent="onSearchArrowUp"
                                @keydown.esc="quickSearchOpen = false"
                            >
                                <template #prefix>
                                    <i v-if="!quickSearchLoading" class="fas fa-barcode search-icon text-primary"></i>
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
                            :title="quickSearchOpen ? (t('qt_close_search') || 'إغلاق القائمة') : (t('so_suggested_products') || 'كتالوج المنتجات')"
                            @click.stop.prevent="quickSearchOpen = !quickSearchOpen"
                        >
                            <i class="fas" :class="quickSearchOpen ? 'fa-chevron-up' : 'fa-boxes-stacked'"></i>
                            <span class="btn-text">{{ quickSearchOpen ? (t('qt_close_search') || 'إغلاق') : (t('so_suggested_products') || 'الكتالوج') }}</span>
                            <span v-if="quickSearchResults.length" class="count-badge">{{ quickSearchResults.length }}</span>
                        </button>

                        <!-- Quick Add New Product (F4) -->
                        <button
                            type="button"
                            class="btn-new-product fx-hide"
                            :title="`${t('pqa_title') || 'إضافة منتج جديد'} (F4)`"
                            @click.stop.prevent="openQuickProduct()"
                        >
                            <i class="fas fa-plus"></i>
                            <span class="btn-text">{{ t('pqa_button') || 'منتج جديد' }}</span>
                            <kbd class="key-hint">F4</kbd>
                        </button>
                    </div>

                    <!-- Quick Category Chips Bar -->
                    <div v-if="topCategories.length" class="category-quick-chips fx-hide">
                        <button
                            type="button"
                            class="cat-chip-btn"
                            :class="{ 'is-active': !selectedCategoryId }"
                            @click="setCategoryFilter(null)"
                        >
                            <i class="fas fa-layer-group"></i>
                            <span>{{ t('qt_all_categories') || t('all_categories') || 'الكل' }}</span>
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

                    <!-- Suggested Search & Catalog Dropdown Panel -->
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
                                    <strong>{{ selectedCategoryName || t('so_suggested_products') || 'المنتجات المقترحة' }}</strong>
                                    <span class="results-badge">{{ quickSearchResults.length }}</span>
                                    <span v-if="quickSearchQuery" class="search-term-badge">
                                        "{{ quickSearchQuery }}"
                                    </span>
                                </div>
                                <div class="strip-right">
                                    <span class="keep-open-pill">
                                        <i class="fas fa-thumbtack text-success"></i>
                                        {{ t('po_search_hint') || 'القائمة تبقى مفتوحة لإضافة عدة أصناف متتالية' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="close-dropdown-btn"
                                        :title="t('close') || 'إغلاق'"
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
                                    <span>{{ t('qt_searching_catalog') || 'جاري استعراض المنتجات…' }}</span>
                                </div>

                                <div v-else-if="!quickSearchResults.length" class="suggested-empty-state">
                                    <i class="fas fa-box-open empty-icon"></i>
                                    <p class="empty-title">{{ t('no_products_found') || 'لم يتم العثور على نتائج' }}</p>
                                    <button type="button" class="empty-new-product" @click.stop="openQuickProduct(quickSearchQuery)">
                                        <i class="fas fa-plus"></i>
                                        <span v-if="quickSearchQuery.trim()">{{ t('pqa_add_named', { name: quickSearchQuery.trim() }) || `إضافة "${quickSearchQuery.trim()}" كمنتج جديد` }}</span>
                                        <span v-else>{{ t('pqa_title') || 'إضافة منتج جديد' }}</span>
                                    </button>
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
                                                    <span v-if="getGroupQuantity(g) > 0" class="in-order-tag" :title="t('quantity') || 'الكمية بالفاتورة'">
                                                        {{ getGroupQuantity(g) }}
                                                    </span>
                                                </div>
                                                <div class="item-card-body">
                                                    <div class="item-card-header">
                                                        <span class="item-name" :title="groupName(g.product)">{{ groupName(g.product) }}</span>
                                                        <span class="variants-count-chip">
                                                            <i class="fas fa-layer-group"></i> {{ t('sinv_variants_count', { count: g.rows.length }) }}
                                                        </span>
                                                    </div>
                                                    <div class="item-card-tags">
                                                        <span v-if="g.product.category?.name_ar || g.product.category_name" class="cat-chip">
                                                            <i class="fas fa-folder"></i> {{ g.product.category?.name_ar || g.product.category_name }}
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
                                                            {{ t('available') || 'متوفر' }}: {{ v.stock_quantity }}
                                                        </span>
                                                    </div>
                                                    <WarehouseStockChips
                                                        :stock="v.warehouse_stock"
                                                        :default-id="form.default_warehouse_id"
                                                        compact
                                                    />
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
                                                <span v-if="getItemQuantity(g.product) > 0" class="in-order-tag" :title="t('quantity') || 'الكمية بالفاتورة'">
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
                                                        {{ Number(g.product.stock_quantity) <= 0 ? (t('out_of_stock') || 'نفد المخزون') : `${t('available') || 'متوفر'}: ${g.product.stock_quantity}` }}
                                                    </span>
                                                </div>
                                                <WarehouseStockChips
                                                    :stock="g.product.warehouse_stock"
                                                    :default-id="form.default_warehouse_id"
                                                />
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
                    <div class="empty-icon-wrap">
                        <i class="fas fa-boxes-packing"></i>
                    </div>
                    <h4>{{ t('sinv_empty_title') }}</h4>
                    <p>{{ t('sinv_empty_hint') }}</p>
                </div>

                <!-- Undo strip for the last removed line -->
                <transition name="el-zoom-in-top">
                    <div v-if="lastRemoved" class="undo-strip" role="status">
                        <span>
                            <i class="fas fa-trash-can"></i>&nbsp;{{ t('sinv_line_removed') }}:
                            <strong>{{ lastRemoved.item.name }}</strong>
                        </span>
                        <el-button size="small" type="primary" text @click="undoRemove">
                            <i class="fas fa-rotate-left"></i>&nbsp;{{ t('undo') || 'تراجع' }}
                        </el-button>
                    </div>
                </transition>

                <div v-if="items.length" class="lines no-wh">
                    <div class="line line--head" aria-hidden="true">
                        <span class="col-num">#</span>
                        <span class="col-product">{{ t('product') || 'المنتج ومواصفاته' }}</span>
                        <span class="col-unit">{{ t('so_unit') || t('unit') || 'الوحدة' }}</span>
                        <span class="col-qty">{{ t('quantity') || 'الكمية' }}</span>
                        <span class="col-price">{{ t('unit_price') || 'سعر الوحدة' }}</span>
                        <span class="col-total num">{{ t('total') || 'الإجمالي' }}</span>
                        <span class="col-actions actions-col">{{ t('actions') || 'إجراءات' }}</span>
                    </div>

                    <TransitionGroup name="line-anim" tag="div" class="lines-body">
                    <div
                        v-for="(item, i) in items"
                        :key="item.pick"
                        class="line-row-wrapper"
                        :class="{ 'is-flagged': isLineShort(item), 'is-free': Number(item.price) === 0 }"
                    >
                        <div class="line">
                            <!-- Index / Line Number -->
                            <div class="line-index-col">
                                <span class="line-index-badge">{{ i + 1 }}</span>
                            </div>

                            <!-- Product info column -->
                            <div class="line-product">
                                <div class="line-product-content">
                                    <div class="line-thumb-wrap">
                                        <EntityImage
                                            :src="item.image || getImageUrl(item.image)"
                                            type="product"
                                            :size="46"
                                            shape="square"
                                            class="line-thumb"
                                        />
                                    </div>
                                    <div class="line-info">
                                        <div class="line-title-row">
                                            <strong class="product-title" :title="item.name">{{ item.name }}</strong>
                                            <span v-if="Number(item.price) === 0" class="badge-free">
                                                <i class="fas fa-gift"></i> {{ t('sinv_free_item') }}
                                            </span>
                                        </div>
                                        <div class="line-meta">
                                            <el-dropdown
                                                v-if="item.allocations && item.allocations[0]"
                                                trigger="click"
                                                max-height="280px"
                                                @command="(whId) => setLineWarehouse(item, whId)"
                                            >
                                                <button
                                                    type="button"
                                                    class="wh-source-chip"
                                                    :class="{
                                                        'is-missing': !item.allocations[0].warehouse_id || lineWarehouseShort(item),
                                                        'is-custom': item.allocations[0].warehouse_id && item.allocations[0].warehouse_id !== form.default_warehouse_id,
                                                    }"
                                                    :title="t('sinv_source_warehouse')"
                                                >
                                                    <i class="fas fa-warehouse"></i>
                                                    <span>{{ warehouseName(item.allocations[0].warehouse_id) || (t('choose_warehouse') || 'اختر المستودع') }}</span>
                                                    <small
                                                        v-if="item.warehouse_stock && item.allocations[0].warehouse_id"
                                                        class="wh-qty-inline"
                                                        :class="{ 'is-short': lineWarehouseShort(item) }"
                                                        :title="t('sinv_available_in_warehouse')"
                                                    >· {{ lineWarehouseQty(item, item.allocations[0].warehouse_id) }}</small>
                                                    <i class="fas fa-chevron-down wh-caret"></i>
                                                </button>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            v-for="wh in warehouses"
                                                            :key="wh.id"
                                                            :command="wh.id"
                                                            :class="{ 'is-current-wh': wh.id === item.allocations[0].warehouse_id }"
                                                        >
                                                            <i class="fas" :class="wh.id === item.allocations[0].warehouse_id ? 'fa-check text-primary' : 'fa-warehouse text-muted'"></i>
                                                            &nbsp;{{ wh.name }}
                                                            <small v-if="wh.id === form.default_warehouse_id" class="text-muted">&nbsp;({{ t('sinv_default') }})</small>
                                                            <small
                                                                v-if="item.warehouse_stock"
                                                                class="wh-qty-tag"
                                                                :class="{ 'is-zero': lineWarehouseQty(item, wh.id) <= 0 }"
                                                            >{{ lineWarehouseQty(item, wh.id) }}</small>
                                                        </el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                            <template v-if="!variantInfo(item)">
                                                <span v-if="item.sku" class="sku-tag" dir="ltr" :title="t('sinv_sku')">
                                                    <i class="fas fa-barcode"></i> {{ item.sku }}
                                                </span>
                                                <span v-if="item.variant_label" class="variant-tag">{{ item.variant_label }}</span>
                                            </template>
                                            <span v-if="item.stock !== undefined" :class="['stock-pill', isLineShort(item) ? 'is-over' : 'is-ok']">
                                                <i :class="isLineShort(item) ? 'fas fa-triangle-exclamation' : 'fas fa-check-circle'"></i>
                                                {{ isLineShort(item) ? t('sinv_stock_short', { stock: item.stock }) : t('sinv_available', { stock: item.stock }) }}
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
                                            <span class="switch-label"><i class="fas fa-tags text-muted"></i> {{ t('po_switch_size') || 'المقاس:' }}</span>
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

                            <!-- Unit Column -->
                            <div class="line-field unit-field">
                                <span class="mobile-label">{{ t('so_unit') || 'الوحدة' }}</span>
                                <el-select
                                    v-if="item.units && item.units.length > 1"
                                    v-model="item.selectedUnit"
                                    value-key="id"
                                    size="default"
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
                                <span v-else class="unit-badge">
                                    <i class="fas fa-box text-muted me-1"></i>
                                    {{ item.selectedUnit?.name_ar || item.selectedUnit?.name || t('piece') || 'حبة' }}
                                </span>
                            </div>

                            <!-- Quantity Column -->
                            <div class="line-field qty-cell">
                                <span class="mobile-label">{{ t('quantity') || 'الكمية' }}</span>
                                <div class="qty-stepper-wrap">
                                    <button
                                        type="button"
                                        class="qty-step-btn is-dec"
                                        :class="{ 'is-remove': (item.allocations?.[0]?.quantity || 1) <= 1 }"
                                        :title="(item.allocations?.[0]?.quantity || 1) <= 1 ? (t('sinv_delete_line') || 'حذف البند') : (t('decrement') || 'إنقاص')"
                                        @click.stop="decrementQty(item, i)"
                                    >
                                        <i :class="(item.allocations?.[0]?.quantity || 1) <= 1 ? 'fas fa-trash-can text-danger' : 'fas fa-minus'"></i>
                                    </button>
                                    <el-input-number
                                        v-if="item.allocations && item.allocations[0]"
                                        v-model="item.allocations[0].quantity"
                                        :min="1"
                                        :step="1"
                                        step-strictly
                                        size="default"
                                        :controls="false"
                                        class="line-qty-input"
                                        :class="{ 'is-over': isLineShort(item) }"
                                    />
                                    <button
                                        type="button"
                                        class="qty-step-btn is-inc"
                                        :title="t('increment') || 'زيادة'"
                                        @click.stop="incrementQty(item)"
                                    >
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <small v-if="unitMult(item) > 1" class="cell-hint">
                                    = {{ formatNumber(linePieces(item)) }} {{ t('piece') || 'قطعة' }}
                                </small>
                            </div>

                            <!-- Price Column -->
                            <div class="line-field price-cell">
                                <span class="mobile-label">{{ t('unit_price') || 'السعر' }}</span>
                                <el-input-number
                                    v-model="item.price"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="default"
                                    class="line-price-input"
                                    :class="{ 'is-changed': priceChanged(item) }"
                                />
                                <small v-if="priceChanged(item)" class="cell-hint is-changed">
                                    <span class="was-price">{{ money(listPrice(item)) }}</span>
                                    <button
                                        type="button"
                                        class="restore-price"
                                        :title="t('sinv_restore_list_price')"
                                        @click="restoreListPrice(item)"
                                    >
                                        <i class="fas fa-rotate-left"></i>
                                    </button>
                                </small>
                            </div>

                            <!-- Line Total -->
                            <div class="line-field line-total-cell">
                                <span class="mobile-label">{{ t('total') || 'الإجمالي' }}</span>
                                <div class="line-total-stack">
                                    <span class="num line-total">{{ money(lineTotal(item)) }}</span>
                                    <small class="cell-hint" dir="ltr">
                                        {{ formatNumber(item.allocations?.[0]?.quantity || 0) }} × {{ money(item.price) }}
                                    </small>
                                </div>
                            </div>

                            <!-- Actions: delete always visible, rarer actions in a menu -->
                            <div class="line-actions">
                                <button
                                    type="button"
                                    class="action-btn is-delete"
                                    :title="t('sinv_delete_line') || 'حذف البند'"
                                    :aria-label="t('sinv_delete_line') || 'حذف البند'"
                                    @click.stop="removeItem(i)"
                                >
                                    <i class="fas fa-trash-can"></i>
                                    <span class="del-label">{{ t('delete') || 'حذف' }}</span>
                                </button>
                                <el-dropdown trigger="click" @command="(cmd) => onLineCommand(cmd, item, i)">
                                    <button
                                        type="button"
                                        class="action-btn is-more"
                                        :class="{ 'has-note': !!item.notes }"
                                        :title="t('sinv_more')"
                                        :aria-label="t('sinv_more')"
                                    >
                                        <i class="fas fa-ellipsis-vertical"></i>
                                        <span v-if="item.notes" class="note-dot"></span>
                                    </button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="note">
                                                <i class="fas fa-comment-dots"></i>&nbsp;{{ t('sinv_line_note') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item command="duplicate">
                                                <i class="fas fa-copy"></i>&nbsp;{{ t('duplicate_line') || 'تكرار البند' }}
                                            </el-dropdown-item>
                                            <el-dropdown-item command="free">
                                                <i class="fas fa-gift"></i>&nbsp;{{ t('sinv_mark_free') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                        </div>

                        <!-- Expandable Line Note Input -->
                        <transition name="el-zoom-in-top">
                            <div v-if="item._showNote || item.notes" class="line-note-drawer">
                                <el-input
                                    v-model="item.notes"
                                    size="small"
                                    maxlength="500"
                                    show-word-limit
                                    clearable
                                    :placeholder="t('sinv_line_note_placeholder')"
                                >
                                    <template #prefix>
                                        <i class="fas fa-pen-to-square text-primary"></i>
                                    </template>
                                </el-input>
                            </div>
                        </transition>
                    </div>
                    </TransitionGroup>
                </div>

                <!-- ── Bottom Grid: Settlement & Notes on left, Totals on right ── -->
                <div class="bottom-grid">
                    <div class="notes-and-terms">
                        <!-- Settlement & Payment info Box -->
                        <div class="settlement-box">
                            <div class="settlement-header">
                                <h5 class="settlement-title">
                                    <i class="fas fa-wallet text-primary"></i>&nbsp;{{ t('sinv_settlement') }}
                                </h5>
                                <div class="remaining-badge" :class="remainingTone">
                                    <span class="remaining-lbl">
                                        {{ remaining > 0 ? (t('sinv_remaining_due')) : (remaining < 0 ? t('sinv_credit_left') : (t('fully_settled') || 'مدفوعة بالكامل')) }}
                                    </span>
                                    <strong class="remaining-val">{{ money(Math.abs(remaining)) }}</strong>
                                </div>
                            </div>

                            <!-- Payment Method Selector -->
                            <div class="payment-method-row">
                                <span class="field-label">{{ t('payment_method') || 'طريقة الدفع' }}:</span>
                                <div class="method-pills">
                                    <button
                                        type="button"
                                        class="method-pill"
                                        :class="{ 'is-active': form.payment_method === 'cash' }"
                                        @click="form.payment_method = 'cash'"
                                    >
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>{{ t('cash') || 'نقداً' }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="method-pill"
                                        :class="{ 'is-active': form.payment_method === 'card' }"
                                        @click="form.payment_method = 'card'"
                                    >
                                        <i class="fas fa-credit-card"></i>
                                        <span>{{ t('sinv_pay_card') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="method-pill"
                                        :class="{ 'is-active': form.payment_method === 'transfer' }"
                                        @click="form.payment_method = 'transfer'"
                                    >
                                        <i class="fas fa-building-columns"></i>
                                        <span>{{ t('bank_transfer') || 'تحويل بنكي' }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="method-pill"
                                        :class="{ 'is-active': form.payment_method === 'check' }"
                                        @click="form.payment_method = 'check'"
                                    >
                                        <i class="fas fa-money-check-dollar"></i>
                                        <span>{{ t('sinv_pay_check') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Paid amount row with quick action buttons -->
                            <div class="settlement-row">
                                <label class="field">
                                    <span class="field-label">{{ t('sinv_paid_now') }}</span>
                                    <el-input-number
                                        v-model="form.paid_amount"
                                        :min="0"
                                        :precision="2"
                                        :controls="false"
                                        size="default"
                                        class="paid-amount-input"
                                    />
                                </label>

                                <div class="settlement-quick-actions">
                                    <button
                                        type="button"
                                        class="quick-pay-chip"
                                        :class="{ 'is-active': form.paid_amount === total && total > 0 }"
                                        @click="form.paid_amount = total"
                                    >
                                        <i class="fas fa-check-double"></i>&nbsp;{{ t('sinv_pay_full') }}
                                    </button>
                                    <button
                                        v-if="!isWalkInCustomer"
                                        type="button"
                                        class="quick-pay-chip is-credit"
                                        :class="{ 'is-active': form.paid_amount === 0 }"
                                        @click="form.paid_amount = 0"
                                    >
                                        <i class="fas fa-clock"></i>&nbsp;{{ t('sinv_pay_on_credit') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Cash Tendered & Change Calculator (if paying cash) -->
                            <div v-if="form.payment_method === 'cash' && form.paid_amount > 0" class="cash-change-calculator">
                                <div class="cash-calc-input-wrap">
                                    <span class="field-label">{{ t('sinv_cash_tendered') }}</span>
                                    <el-input-number
                                        v-model="cashTendered"
                                        :min="0"
                                        :precision="2"
                                        :controls="false"
                                        size="small"
                                        :placeholder="money(form.paid_amount)"
                                    />
                                </div>
                                <div v-if="cashChangeAmount > 0" class="cash-change-badge">
                                    <span class="lbl">{{ t('sinv_change_due') }}:</span>
                                    <strong class="val">{{ money(cashChangeAmount) }}</strong>
                                </div>
                            </div>

                            <!-- Tafqeet (amount in words) -->
                            <p v-if="total > 0" class="tafqeet-line fx-hide">
                                <i class="fas fa-receipt text-primary"></i>&nbsp;
                                <span>{{ tafqeet(total) }}</span>
                            </p>
                        </div>

                        <!-- Rarely used extras: open on demand -->
                        <div v-if="!showExpenses || !showNotes" class="extras-toggles fx-hide">
                            <button v-if="!showExpenses" type="button" class="extra-chip" @click="expensesOpen = true">
                                <i class="fas fa-plus"></i>&nbsp;{{ t('sinv_add_charges') }}
                            </button>
                            <button v-if="!showNotes" type="button" class="extra-chip" @click="notesOpen = true">
                                <i class="fas fa-plus"></i>&nbsp;{{ t('sinv_notes') }}
                            </button>
                        </div>

                        <!-- Additional Expenses Editor -->
                        <OrderExpensesEditor
                            v-if="showExpenses"
                            v-model="form.expenses"
                            class="mb-3"
                        />

                        <!-- Notes Textarea -->
                        <el-form-item v-if="showNotes" :label="t('sinv_notes')">
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="2"
                                maxlength="1000"
                                show-word-limit
                                :placeholder="t('sinv_notes_placeholder')"
                            />
                        </el-form-item>
                    </div>

                    <!-- Totals Box -->
                    <div class="totals" :class="{ 'is-flagged': isFlagged('discount') }">
                        <div class="totals-row">
                            <span>{{ t('subtotal') || 'إجمالي الأصناف' }}</span>
                            <span class="num">{{ money(subtotal) }}</span>
                        </div>

                        <!-- Discount Row with % vs Amount Mode Switch & Presets -->
                        <div class="totals-row discount-row" :class="{ 'has-error': discountError }">
                            <div class="totals-row-label">
                                <span>{{ t('sales.discount_label') || t('discount') || 'الخصم' }}</span>
                                <el-radio-group
                                    :model-value="form.discount_type"
                                    size="small"
                                    class="discount-mode-switch"
                                    @update:model-value="switchDiscountType"
                                >
                                    <el-radio-button value="percent">%</el-radio-button>
                                    <el-radio-button value="amount">{{ t('sales.discount_as_amount') || 'مبلغ' }}</el-radio-button>
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

                        <!-- Quick Discount Percentage Chips -->
                        <div class="discount-quick-chips fx-hide">
                            <button
                                v-for="d in [0, 5, 10, 15, 20]"
                                :key="d"
                                type="button"
                                class="disc-chip"
                                :class="{ 'is-active': form.discount_type === 'percent' && Number(form.discount_percent) === d }"
                                @click="setDiscountPreset(d)"
                            >
                                {{ d }}%
                            </button>
                        </div>

                        <div v-if="discountError" class="totals-hint is-error">
                            {{ discountError }}
                        </div>
                        <div v-else-if="discountAmount > 0" class="totals-hint is-figure">
                            {{ form.discount_type === 'percent'
                                ? t('sinv_discount_value', { amount: money(discountAmount) })
                                : t('sinv_discount_rate', { rate: effectiveDiscountRate }) }}
                        </div>

                        <!-- Tax Row (hidden until needed) -->
                        <button v-if="!showTax" type="button" class="extra-chip tax-add-chip fx-hide" @click="taxOpen = true">
                            <i class="fas fa-plus"></i>&nbsp;{{ t('sinv_add_tax') }}
                        </button>
                        <div v-if="showTax" class="totals-row">
                            <div class="totals-row-label">
                                <span>{{ t('tax_percent') }}</span>
                                <div class="tax-quick-pills">
                                    <button
                                        type="button"
                                        class="tax-pill"
                                        :class="{ 'is-on': Number(form.tax_percent) === 0 }"
                                        @click="form.tax_percent = 0"
                                    >0%</button>
                                    <button
                                        type="button"
                                        class="tax-pill"
                                        :class="{ 'is-on': Number(form.tax_percent) === 15 }"
                                        @click="form.tax_percent = 15"
                                    >15%</button>
                                </div>
                            </div>
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
                            <span>{{ t('sinv_add_charges') }}</span>
                            <span class="num text-warning">+ {{ money(totalExpenses) }}</span>
                        </div>

                        <!-- Grand Total -->
                        <div class="totals-row grand">
                            <span>{{ t('sinv_net_total') }}</span>
                            <span class="num">{{ money(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Action Bar Dock (Bottom Sticky) ── -->
                <div class="action-bar-dock">
                    <div class="action-bar-inner">
                        <div class="dock-left fx-hide">
                            <el-button @click="goBack">
                                <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_invoices') || 'الفواتير' }}
                            </el-button>
                            <el-button v-if="!isEdit" text type="danger" @click="confirmClearInvoice">
                                <i class="fas fa-trash-can"></i>&nbsp;{{ t('sinv_clear') }}
                            </el-button>
                        </div>

                        <div class="dock-right">
                            <span v-if="issues.length" class="blocker-hint">
                                <i class="fas fa-circle-exclamation text-warning"></i>&nbsp;{{ blockerText }}
                            </span>

                            <!-- Save and Print -->
                            <el-button
                                v-if="!isEdit"
                                type="success"
                                size="large"
                                :loading="submitting"
                                :disabled="!canSubmit"
                                class="btn-save-print"
                                @click="submitInvoice('print')"
                            >
                                <i class="fas fa-print"></i>&nbsp;{{ t('sinv_save_and_print') }}
                            </el-button>

                            <!-- Save and New -->
                            <el-button
                                v-if="!isEdit"
                                class="fx-hide"
                                type="info"
                                plain
                                size="large"
                                :loading="submitting"
                                :disabled="!canSubmit"
                                @click="submitInvoice('new')"
                            >
                                <i class="fas fa-plus"></i>&nbsp;{{ t('sinv_save_and_new') }}
                            </el-button>

                            <!-- Primary Save Button -->
                            <el-button
                                type="primary"
                                size="large"
                                :loading="submitting"
                                :disabled="!canSubmit"
                                class="btn-save-primary"
                                @click="submitInvoice('close')"
                            >
                                <i class="fas fa-check"></i>&nbsp;{{ isEdit ? (t('save_changes') || 'حفظ التعديلات') : t('sinv_issue') }}
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

        <!-- ── Keyboard Shortcuts Modal ── -->
        <el-dialog
            v-model="shortcutsModalVisible"
            :title="t('keyboard_shortcuts') || 'اختصارات لوحة المفاتيح'"
            width="460px"
            destroy-on-close
            append-to-body
        >
            <div class="shortcuts-guide-list">
                <div class="sc-guide-row">
                    <span class="sc-label">{{ t('sinv_sc_search') }}</span>
                    <kbd class="key-hint">F2</kbd>
                </div>
                <div class="sc-guide-row">
                    <span class="sc-label">{{ t('sinv_sc_new_product') }}</span>
                    <kbd class="key-hint">F4</kbd>
                </div>
                <div class="sc-guide-row">
                    <span class="sc-label">{{ t('sinv_sc_save') }}</span>
                    <div class="sc-keys-combo">
                        <kbd class="key-hint">Ctrl</kbd> + <kbd class="key-hint">Enter</kbd>
                        <span>{{ t('sinv_sc_or') }}</span>
                        <kbd class="key-hint">F9</kbd>
                    </div>
                </div>
                <div class="sc-guide-row">
                    <span class="sc-label">{{ t('sinv_toggle_focus') }}</span>
                    <kbd class="key-hint">F8</kbd>
                </div>
                <div class="sc-guide-row">
                    <span class="sc-label">{{ t('sinv_sc_close') }}</span>
                    <kbd class="key-hint">Esc</kbd>
                </div>
            </div>
            <template #footer>
                <el-button type="primary" @click="shortcutsModalVisible = false">{{ t('close') || 'إغلاق' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, inject, nextTick, onMounted, onBeforeUnmount, onUnmounted, reactive, ref, watch } from 'vue';
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
import WarehouseStockChips from '@/components/admin/sales/WarehouseStockChips.vue';
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
 * Focus mode: essentials only (customer, search, items, totals, pay,
 * save). On by default for a new invoice; F8 or the header toggles it.
 * It also folds the admin sidebar, restoring the user's choice on exit.
 * ------------------------------------------------------------------ */
const focusMode = ref(!route.params.id);
const layoutSidebarCollapsed = inject('adminSidebarCollapsed', null);
let sidebarBeforeFocus = null;

const applyFocusSidebar = (on) => {
    if (!layoutSidebarCollapsed) return;
    if (on) {
        if (sidebarBeforeFocus === null) sidebarBeforeFocus = layoutSidebarCollapsed.value;
        layoutSidebarCollapsed.value = true;
    } else if (sidebarBeforeFocus !== null) {
        layoutSidebarCollapsed.value = sidebarBeforeFocus;
        sidebarBeforeFocus = null;
    }
};

watch(focusMode, applyFocusSidebar, { immediate: true });
const shortcutsModalVisible = ref(false);

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
const isWalkInCustomer = ref(false);

const warehouses = ref([]);
const warehousesLoading = ref(false);
const salesEmployees = ref([]);
const employeesLoading = ref(false);

/* ------------------------------------------------------------------ *
 * Progressive disclosure: rarely used fields stay collapsed until needed
 * ------------------------------------------------------------------ */

const moreOptionsOpen = ref(false);
const expensesOpen = ref(false);
const notesOpen = ref(false);
const taxOpen = ref(false);

const selectedWarehouseName = computed(
    () => warehouses.value.find((w) => w.id === form.default_warehouse_id)?.name || '',
);
const selectedEmployeeName = computed(
    () => salesEmployees.value.find((e) => e.id === form.assigned_employee_id)?.name || '',
);

const warehouseName = (id) => warehouses.value.find((w) => w.id === id)?.name || '';

// Sellable quantity of a line's product in one warehouse (from the search
// breakdown). Base units, so compare against quantity × unit factor.
const lineWarehouseQty = (item, whId) =>
    (item.warehouse_stock || []).find((w) => w.warehouse_id === whId)?.available || 0;

const lineWarehouseShort = (item) => {
    const alloc = item.allocations?.[0];
    if (!item.warehouse_stock || !alloc?.warehouse_id) return false;
    const mult = toNum(item.selectedUnit?.base_unit_multiplier) || 1;
    return toNum(alloc.quantity) * mult > lineWarehouseQty(item, alloc.warehouse_id);
};

const setLineWarehouse = (item, whId) => {
    if (!item.allocations?.[0] || item.allocations[0].warehouse_id === whId) return;
    item.allocations[0].warehouse_id = whId;
    dirty.value = true;
};

const showExpenses = computed(() => expensesOpen.value || (form.expenses || []).length > 0);
const showNotes = computed(() => notesOpen.value || !!form.notes);
const showTax = computed(() => taxOpen.value || Number(form.tax_percent) > 0);

const resetDisclosure = () => {
    moreOptionsOpen.value = false;
    expensesOpen.value = false;
    notesOpen.value = false;
    taxOpen.value = false;
    lastRemoved.value = null;
};

/* Undo for the last removed line */
const lastRemoved = ref(null);
let undoTimer = null;

const fetchWarehouses = async () => {
    warehousesLoading.value = true;
    try {
        let res;
        try {
            res = await api.get('/admin/wms/warehouses', { params: { per_page: 200, is_active: true } });
        } catch {
            try {
                res = await api.get('/admin/warehouses', { params: { per_page: 200, is_active: true } });
            } catch {
                res = await api.get('/wms/warehouses', { params: { per_page: 200, is_active: true } });
            }
        }
        const data = res?.data?.data || res?.data || [];
        warehouses.value = Array.isArray(data) ? data : [];

        // Pre-select employee warehouse if sales rep, otherwise first active warehouse
        if (authStore.isSalesRep && authStore.user?.employee_warehouse_id) {
            if (!isEdit.value || !form.default_warehouse_id) {
                form.default_warehouse_id = authStore.user.employee_warehouse_id;
            }
        } else if (!form.default_warehouse_id && warehouses.value.length) {
            form.default_warehouse_id = warehouses.value[0].id;
        }

        // Propagate to any line missing warehouse_id
        if (form.default_warehouse_id) {
            items.value.forEach((item) => {
                if (item.allocations && item.allocations[0] && !item.allocations[0].warehouse_id) {
                    item.allocations[0].warehouse_id = form.default_warehouse_id;
                }
            });
        }
    } catch (e) {
        console.error('Failed to load warehouses:', e);
        warehouses.value = [];
    } finally {
        warehousesLoading.value = false;
    }
};

const fetchEmployees = async () => {
    employeesLoading.value = true;
    try {
        let empRes;
        try {
            empRes = await api.get('/sales-employees');
        } catch {
            try {
                empRes = await api.get('/admin/sales-employees');
            } catch {
                empRes = await api.get('/admin/employees', { params: { per_page: 200 } });
            }
        }

        const raw = empRes?.data;
        let list = [];
        if (Array.isArray(raw?.data)) {
            list = raw.data;
        } else if (Array.isArray(raw?.data?.employees)) {
            list = raw.data.employees;
        } else if (Array.isArray(raw)) {
            list = raw;
        } else if (Array.isArray(raw?.employees)) {
            list = raw.employees;
        }

        salesEmployees.value = list.map((e) => ({
            id: e.id,
            name: e.name || [e.first_name, e.last_name].filter(Boolean).join(' ') || e.email || `#${e.id}`,
            email: e.email || '',
            department: e.department || '',
        }));

        // If the current logged-in user is a sales rep, auto-select their employee id
        if (authStore.isSalesRep && authStore.user?.employee_id) {
            if (!isEdit.value || !form.assigned_employee_id) {
                form.assigned_employee_id = authStore.user.employee_id;
            }
            if (!salesEmployees.value.some((e) => e.id === authStore.user.employee_id)) {
                salesEmployees.value.unshift({
                    id: authStore.user.employee_id,
                    name: authStore.user.name || t('sales_representative') || 'مندوب المبيعات',
                });
            }
        }
    } catch (e) {
        console.error('Failed to load employees:', e);
        salesEmployees.value = [];
    } finally {
        employeesLoading.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Customers (Logic & UX with Walk-in & Search Fixes)
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
        label: balance > 0 ? t('sinv_customer_credit') : (t('sinv_customer_owes')),
    };
});

const hasCustomerCredit = computed(() => {
    if (!selectedCustomer.value) return false;
    const balance = parseFloat(selectedCustomer.value.balance) || 0;
    return balance > 0.01;
});

const applyCustomerCredit = () => {
    if (!selectedCustomer.value) return;
    const credit = Math.max(0, parseFloat(selectedCustomer.value.balance) || 0);
    if (credit <= 0) return;
    // Set paid amount up to total or credit balance
    form.paid_amount = round2(Math.min(total.value, credit));
    dirty.value = true;
    ElMessage.success(t('sinv_credit_applied', { amount: money(form.paid_amount) }));
};

const searchCustomers = async (query = '') => {
    customerQuery.value = query;
    const token = ++customerSearchToken;
    customersLoading.value = true;
    try {
        const { data } = await posApi.customers({ search: query || undefined, per_page: 30 });
        if (token === customerSearchToken) {
            const list = data?.data?.customers || data?.data || (Array.isArray(data) ? data : []);
            customers.value = Array.isArray(list) ? list : [];
        }
    } catch {
        if (token === customerSearchToken) customers.value = [];
    } finally {
        if (token === customerSearchToken) customersLoading.value = false;
    }
};

// The customer's own rep is who the sale is credited to — unless the seller is
// a rep, whose sales are always their own. A rep filled in for the previous
// customer is cleared when the new one has none; one chosen by hand is kept.
const repFromCustomer = ref(null);
const applyCustomerRep = (customer) => {
    const previous = repFromCustomer.value;
    repFromCustomer.value = customer?.employee_id || null;
    if (isSalesRep.value) return;
    if (customer?.employee_id) {
        form.assigned_employee_id = customer.employee_id;
        if (!salesEmployees.value.some((e) => e.id === customer.employee_id)) {
            salesEmployees.value = [...salesEmployees.value, {
                id: customer.employee_id,
                name: customer.sales_rep?.name || `#${customer.employee_id}`,
                email: '',
                department: '',
            }];
        }
    } else if (previous && form.assigned_employee_id === previous) {
        form.assigned_employee_id = null;
    }
};

const onCustomerPicked = (id) => {
    if (id) {
        selectedCustomer.value = customers.value.find((c) => c.id === id) || null;
        applyCustomerRep(selectedCustomer.value);
        isWalkInCustomer.value = false;
    } else {
        selectedCustomer.value = null;
    }
    dirty.value = true;
};

const clearCustomerSelection = () => {
    form.customer_id = null;
    selectedCustomer.value = null;
    dirty.value = true;
};

const toggleWalkInCustomer = () => {
    isWalkInCustomer.value = !isWalkInCustomer.value;
    if (isWalkInCustomer.value) {
        form.customer_id = null;
        selectedCustomer.value = null;
        form.payment_method = 'cash';
        // By default, walk-in pays full total
        if (total.value > 0) {
            form.paid_amount = total.value;
        }
    }
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
    applyCustomerRep(customer);
    isWalkInCustomer.value = false;
    quickCustomerOpen.value = false;
    dirty.value = true;
    ElMessage.success(t('client_added_and_selected', { name: customer.name }) || 'تمت إضافة العميل واختياره');
};

/* ------------------------------------------------------------------ *
 * Categories & Search (Supercharged Barcode & Catalog)
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

// Immediate Barcode Scan or Enter handler
const onQuickSearchEnter = async () => {
    const raw = quickSearchQuery.value.trim();
    if (!raw) return;

    clearTimeout(quickSearchTimer);

    // If results are not loaded or query changed, search immediately
    if (quickSearchLoading.value || !quickSearchResults.value.length) {
        await executeQuickSearch();
    }

    const trimmed = raw.toLowerCase();
    // Try exact barcode / SKU match first
    const exactMatch = quickSearchResults.value.find((p) =>
        (p.barcode && String(p.barcode).trim().toLowerCase() === trimmed) ||
        (p.sku && String(p.sku).trim().toLowerCase() === trimmed)
    );

    if (exactMatch) {
        addProductDirect(exactMatch, 1);
        quickSearchQuery.value = '';
        executeQuickSearch();
        nextTick(() => quickSearchInputRef.value?.focus());
        return;
    }

    // If exactly 1 group or single item
    if (quickSearchResults.value.length === 1) {
        addProductDirect(quickSearchResults.value[0], pendingQtyOf(quickSearchResults.value[0]));
        quickSearchQuery.value = '';
        executeQuickSearch();
        nextTick(() => quickSearchInputRef.value?.focus());
        return;
    }

    // If nothing found, offer to create
    if (!quickSearchResults.value.length && raw) {
        openQuickProduct(raw);
    }
};

const onSearchArrowDown = () => {
    // Keep focus inside panel
    quickSearchOpen.value = true;
};

const onSearchArrowUp = () => {
    quickSearchOpen.value = true;
};

const executeQuickSearch = async () => {
    quickSearchLoading.value = true;
    try {
        const params = { expand_variants: 1, with_warehouses: 1 };
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

const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();
const lineSiblings = (line) => (line?.product_id && line?.product_variant_id ? siblingsOf(line.product_id) : []);
const variantInfo = (line) => lineVariantInfo(line, lineSiblings(line));

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
        ElMessage.success(t('so_qty_now', { name: other.name, count: other.allocations?.[0]?.quantity }) || `أصبحت كمية ${other.name}: ${other.allocations?.[0]?.quantity}`);
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
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.allocations[0].quantity }) || `أصبحت كمية ${existing.name}: ${existing.allocations[0].quantity}`);
    } else {
        const defaultUnit = {
            id: null,
            name: product.unit || t('piece') || 'حبة',
            name_ar: product.unit || t('piece') || 'حبة',
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
            warehouse_stock: Array.isArray(product.warehouse_stock) ? product.warehouse_stock : null,
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            notes: '',
            _showNote: false,
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
        ElMessage.success(t('so_added', { name: line.name }) || `تمت إضافة ${line.name}`);
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
    ElMessage.success(t('sinv_line_duplicated'));
};

const removeItem = (index) => {
    const [removed] = items.value.splice(index, 1);
    if (!removed) return;
    dirty.value = true;
    lastRemoved.value = { item: removed, index };
    clearTimeout(undoTimer);
    undoTimer = setTimeout(() => { lastRemoved.value = null; }, 8000);
    ElMessage({
        message: `${t('sinv_line_removed') || 'تم حذف البند'}: ${removed.name || ''}`,
        type: 'info',
        duration: 2500,
    });
};

const decrementQty = (item, index) => {
    if (!item.allocations || !item.allocations[0]) return;
    if (item.allocations[0].quantity <= 1) {
        removeItem(index);
        return;
    }
    item.allocations[0].quantity -= 1;
    dirty.value = true;
};

const incrementQty = (item) => {
    if (!item.allocations || !item.allocations[0]) return;
    item.allocations[0].quantity = (Number(item.allocations[0].quantity) || 0) + 1;
    dirty.value = true;
};

const clearAllLines = () => {
    if (!items.value.length) return;
    const count = items.value.length;
    items.value = [];
    dirty.value = true;
    lastRemoved.value = null;
    clearTimeout(undoTimer);
    ElMessage({
        message: t('sinv_all_lines_cleared') || `تم تفريغ كافة البنود (${count} أصناف)`,
        type: 'info',
        duration: 2500,
    });
};

const undoRemove = () => {
    if (!lastRemoved.value) return;
    const { item, index } = lastRemoved.value;
    items.value.splice(Math.min(index, items.value.length), 0, item);
    lastRemoved.value = null;
    clearTimeout(undoTimer);
    dirty.value = true;
};

const onLineCommand = (cmd, item, index) => {
    if (cmd === 'note') {
        item._showNote = !item._showNote;
    } else if (cmd === 'duplicate') {
        duplicateLine(index);
    } else if (cmd === 'free') {
        item.price = 0;
        dirty.value = true;
    }
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
    if (items.value.length) {
        ElMessage.info(t('lines_moved_to_warehouse', { name: warehouseName(whId) }) || `تم تحويل مصدر كل البنود إلى: ${warehouseName(whId)}`);
    }
};

/* ------------------------------------------------------------------ *
 * Naming & Images
 * ------------------------------------------------------------------ */

const productName = (p) => {
    if (!p) return '';
    return p.name_ar || p.name || p.name_en || t('unknown_product') || 'منتج غير محدد';
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

// Row helpers for the item design: unit factor, piece count, list-price tracking.
const unitMult = (item) => toNum(item.selectedUnit?.base_unit_multiplier) || 1;
const linePieces = (item) => toNum(item.allocations?.[0]?.quantity) * unitMult(item);
const listPrice = (item) => roundUnitPrice(toNum(item.base_price) * (toNum(item.selectedUnit?.price_multiplier) || 1));
const priceChanged = (item) => toNum(item.base_price) > 0 && roundUnitPrice(toNum(item.price)) !== listPrice(item);
const restoreListPrice = (item) => {
    item.price = listPrice(item);
    dirty.value = true;
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
        if (amount < 0) return t('sales.discount_negative') || 'قيمة الخصم لا يمكن أن تكون سالبة';
        if (amount > 0 && amount - subtotal.value > 0.005) {
            return t('sales.discount_amount_exceeds_subtotal', {
                discount: money(amount),
                subtotal: money(subtotal.value),
            }) || `قيمة الخصم (${money(amount)}) تتجاوز المجموع الفرعي (${money(subtotal.value)})`;
        }
        return '';
    }
    const val = toNum(form.discount_percent);
    if (val < 0 || val > 100) return t('sales.discount_percent_out_of_range') || 'نسبة الخصم يجب أن تكون بين 0 و 100%';
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

const setDiscountPreset = (percent) => {
    form.discount_type = 'percent';
    form.discount_percent = percent;
    dirty.value = true;
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

// Cash Tendered & Change Calculator
const cashTendered = ref(null);
const cashChangeAmount = computed(() => {
    if (form.payment_method !== 'cash') return 0;
    const given = toNum(cashTendered.value);
    const paid = toNum(form.paid_amount);
    return given > paid ? round2(given - paid) : 0;
});

// Auto-align paid amount with total for walk-in cash sales if desired
watch(
    () => total.value,
    (newTotal) => {
        if (isWalkInCustomer.value && (form.paid_amount === 0 || form.paid_amount == null)) {
            form.paid_amount = newTotal;
        }
    }
);

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
        list.push({ key: 'items', target: 'items', text: t('qt_need_lines') || 'يرجى إضافة بند واحد على الأقل للفاتورة' });
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
    } else if (issue.target === 'customer') {
        el = customerSelectRef.value?.$el || formRoot.value?.querySelector('.customer-pick-row');
    } else if (issue.target === 'settlement') {
        el = formRoot.value?.querySelector('.settlement-box');
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
    if (!items.value.length && !form.customer_id && !isWalkInCustomer.value) {
        clearDraft();
        return;
    }
    try {
        const draftData = {
            saved_at: new Date().toISOString(),
            isWalkInCustomer: isWalkInCustomer.value,
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
            t('sinv_draft_message', { count: draft.items.length }),
            t('sinv_draft_title'),
            {
                type: 'info',
                confirmButtonText: t('sinv_draft_continue'),
                cancelButtonText: t('sinv_draft_discard'),
                distinguishCancelAndClose: true,
            }
        );
    } catch {
        clearDraft();
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

    isWalkInCustomer.value = !!draft.isWalkInCustomer;

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
            name: t('piece') || 'حبة',
            name_ar: t('piece') || 'حبة',
            base_unit_multiplier: 1,
            price_multiplier: 1,
        };
        const l = reactive({
            ...line,
            selectedUnit: unit,
            units: [unit],
            notes: line.notes || '',
            _showNote: !!line.notes,
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
        isWalkInCustomer.value,
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
            isWalkInCustomer.value = false;
        } else {
            isWalkInCustomer.value = true;
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
                name: item.unit_name || item.product?.unit || t('piece') || 'حبة',
                name_ar: item.unit_name || item.product?.unit || t('piece') || 'حبة',
                base_unit_multiplier: 1,
                price_multiplier: 1,
            };
            const line = reactive({
                pick: pickKey(item.product_id, item.product_variant_id),
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                variant_label: item.variant?.label || variantLabelOf(item.variant),
                variant: item.variant ? variantFieldsOf(item.variant) : null,
                name: (item.product_variant_id && item.product?.name_ar) || item.product_name || item.product?.name_ar || t('unknown_product') || 'منتج',
                sku: item.variant?.sku || item.product?.sku || '',
                image: item.product?.image_main || null,
                price: parseFloat(item.unit_price) || 0,
                base_price: parseFloat(item.unit_price) || 0,
                stock: item.product?.stock_quantity || 0,
                selectedUnit: unit,
                units: [unit],
                notes: item.notes || '',
                _showNote: !!item.notes,
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
 * Submit with multiple modes (Save, Save & Print, Save & New)
 * ------------------------------------------------------------------ */

const resetFormToNew = () => {
    Object.assign(form, blankForm());
    items.value = [];
    selectedCustomer.value = null;
    isWalkInCustomer.value = false;
    formErrors.value = [];
    cashTendered.value = null;
    resetDisclosure();
    clearDraft();
    dirty.value = false;
    if (warehouses.value.length) {
        form.default_warehouse_id = warehouses.value[0].id;
    }
    nextTick(() => {
        quickSearchInputRef.value?.focus();
    });
};

const submitInvoice = async (afterAction = 'close') => {
    if (!canSubmit.value) {
        if (issues.value.length) {
            goToIssue(issues.value[0]);
        }
        return;
    }

    if (!form.customer_id && !isWalkInCustomer.value && remaining.value > 0.005) {
        formErrors.value = [t('sinv_need_customer_or_walk_in')];
        customerSelectRef.value?.$el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    formErrors.value = [];
    submitting.value = true;

    try {
        const payload = {
            customer_id: isWalkInCustomer.value ? null : form.customer_id,
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
                notes: item.notes || null,
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
            router.push('/admin/sales/invoices');
        } else {
            const created = await invoicesStore.createInvoice(payload);
            const settlement = created?.settlement;
            const parts = [t('invoice_created_successfully') || `تم إصدار الفاتورة رقم ${created?.invoice_number || ''} بنجاح`];
            if (settlement?.payment_number) {
                parts.push(`${t('payment') || 'سند قبض'} ${settlement.payment_number}`);
            }
            ElMessage.success({ message: parts.join(' • '), duration: 4000 });
            clearDraft();

            if (afterAction === 'print') {
                // Navigate to invoice list with print preview trigger
                router.push({ path: '/admin/sales/invoices', query: { print: created.id } });
            } else if (afterAction === 'new') {
                resetFormToNew();
                ElMessage.info(t('sinv_ready_next'));
            } else {
                router.push('/admin/sales/invoices');
            }
        }
    } catch (err) {
        if (err.response?.status === 422) {
            const respData = err.response?.data || {};
            const errors = respData.errors ?? {};
            const shortages = respData.data?.shortages || [];
            const list = Object.values(errors).flat();

            if (shortages.length) {
                shortages.forEach((s) => {
                    list.push(t('sinv_stock_short_line', { product: s.product_name, sku: s.sku || '—', warehouse: s.warehouse_name }));
                });
            }

            if (!list.length && respData.message) {
                list.push(respData.message);
            }

            formErrors.value = list;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            formErrors.value = [err.response?.data?.message || t('sinv_save_failed')];
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
            t('sinv_confirm_clear'),
            t('confirm') || 'تأكيد',
            { type: 'warning' }
        );
        items.value = [];
        lastRemoved.value = null;
        clearDraft();
        dirty.value = false;
        ElMessage.info(t('sinv_cleared'));
    } catch {
        /* cancel */
    }
};

const goBack = async () => {
    if (dirty.value && items.value.length) {
        try {
            await ElMessageBox.confirm(
                t('sinv_confirm_leave'),
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
    } else if (e.key === 'F8') {
        e.preventDefault();
        focusMode.value = !focusMode.value;
    } else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
        e.preventDefault();
        if (canSubmit.value) {
            submitInvoice('close');
        } else if (issues.value.length) {
            goToIssue(issues.value[0]);
        }
    }
};

onMounted(async () => {
    window.addEventListener('keydown', handleGlobalKeydown);

    await Promise.allSettled([
        fetchWarehouses(),
        fetchEmployees(),
    ]);

    fetchCategories();

    if (isEdit.value) {
        await loadInvoice();
    } else {
        await offerDraft();
        const cid = route.query.customer_id ? Number(route.query.customer_id) : null;
        if (cid) {
            try {
                const { data } = await posApi.customerShow(cid);
                const cust = data?.data || data;
                if (cust) {
                    customers.value = [cust];
                    form.customer_id = cust.id;
                    selectedCustomer.value = cust;
                    isWalkInCustomer.value = false;
                    applyCustomerRep(cust);
                }
            } catch {
                /* ignore */
            }
        }
        draftTracking = true;
        nextTick(() => quickSearchInputRef.value?.focus());
    }
});

onUnmounted(() => {
    applyFocusSidebar(false);
    window.removeEventListener('keydown', handleGlobalKeydown);
    clearTimeout(quickSearchTimer);
    clearTimeout(draftTimer);
    clearTimeout(undoTimer);
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

.form-error-alert {
    border-radius: 8px;
}

.error-list {
    margin: 0.35rem 0 0;
    padding-inline-start: 1.25rem;
    font-size: 0.85rem;
    line-height: 1.6;
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
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

.btn-walkin {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 0.85rem;
    height: 38px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.18s ease;
    flex-shrink: 0;
}

.btn-walkin:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.btn-walkin.is-active {
    background: #ecfdf5;
    border-color: #10b981;
    color: #065f46;
    box-shadow: 0 0 0 1px #10b981;
}

.walkin-active-box {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0.85rem;
    height: 38px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #065f46;
}

.customer-select {
    flex: 1;
}

.client-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.client-option-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.client-option-name {
    font-weight: 600;
    color: #1e293b;
}

.client-option-phone {
    font-size: 0.78rem;
    color: #64748b;
}

.client-option-meta {
    font-size: 0.75rem;
    color: #94a3b8;
}

.btn-quick-customer {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 8px;
}

.customer-info-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
}

.cust-info-item {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.cust-info-item.is-credit {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
    font-weight: 600;
}

.cust-info-item.is-owing {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
    font-weight: 600;
}

.btn-use-credit {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    background: #10b981;
    color: #ffffff;
    border: none;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.btn-use-credit:hover {
    background: #059669;
}

.btn-clear-customer {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.15s ease;
}

.btn-clear-customer:hover {
    background: #fef2f2;
    color: #dc2626;
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
    height: 38px;
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

.suggested-loading-state,
.suggested-empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #64748b;
}

.suggested-empty-state .empty-icon {
    font-size: 2.5rem;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
    display: block;
}

.empty-new-product {
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.85rem;
    border-radius: 6px;
    border: 1px solid #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 600;
    cursor: pointer;
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
    padding: 3.5rem 1rem;
    text-align: center;
    color: #64748b;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
}

.lines-empty i {
    font-size: 2.75rem;
    color: #94a3b8;
    display: block;
    margin-bottom: 0.75rem;
}

.lines-empty h4 {
    margin: 0 0 0.35rem;
    font-weight: 700;
    color: #334155;
}

.lines-empty p {
    margin: 0;
    font-size: 0.85rem;
    color: #94a3b8;
}

.lines {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto; /* never clip the actions column (delete) on narrow screens */
    background: #ffffff;
    margin-bottom: 1.5rem;
}

.line-row-wrapper {
    position: relative;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}

/* Status marker on the leading edge of the row */
.line-row-wrapper::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 3px;
    background: transparent;
    transition: background 0.15s ease;
}

.line-row-wrapper:hover {
    background: #fafcff;
}

.line-row-wrapper:hover::before {
    background: #93c5fd;
}

.line-row-wrapper:last-child {
    border-bottom: none;
}

.line-row-wrapper.is-free::before {
    background: #22c55e;
}

.line-row-wrapper.is-flagged {
    background: #fff7ed;
}

.line-row-wrapper.is-flagged::before {
    background: #f97316;
}

/* Add / remove animation */
.lines-body {
    position: relative;
}
.line-anim-enter-from {
    opacity: 0;
    transform: translateY(-6px);
    background: #eff6ff;
}
.line-anim-enter-active {
    transition: opacity 0.25s ease, transform 0.25s ease, background 1.2s ease;
}
.line-anim-leave-active {
    position: absolute;
    width: 100%;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 0;
}
.line-anim-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
.line-anim-move {
    transition: transform 0.25s ease;
}

.warehouse-option-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.wh-name {
    display: flex;
    align-items: center;
    font-weight: 500;
}

.employee-option-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.emp-name {
    display: flex;
    align-items: center;
    font-weight: 500;
}

.wh-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    color: #64748b;
    font-size: 0.85rem;
}

.wh-empty-state i {
    font-size: 1.75rem;
    color: #94a3b8;
}

.line {
    display: grid;
    grid-template-columns: 36px minmax(0, 2.4fr) 150px 110px 125px 125px minmax(105px, 1fr) 118px;
    gap: 0.75rem;
    align-items: start;
    padding: 0.85rem 0.9rem;
    min-width: 820px;
}

.lines.no-wh .line {
    grid-template-columns: 36px minmax(0, 2.6fr) 110px 125px 125px minmax(105px, 1fr) 118px;
    min-width: 680px;
}

.line--head {
    align-items: center;
    padding-block: 0.55rem;
    background: #f8fafc;
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
}

.line--head .actions-col {
    text-align: end;
}

.col-num {
    text-align: center;
}

.col-qty, .col-price, .col-total {
    text-align: end;
}

.line-index-col {
    display: flex;
    justify-content: center;
    align-items: center;
    padding-top: 0.45rem;
}

.line-index-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 700;
}

.line-row-wrapper:hover .line-index-badge {
    background: #e2e8f0;
    color: #1e293b;
}

.line-product-content {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
}

.line-thumb-wrap {
    flex-shrink: 0;
}

.line-thumb {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    object-fit: cover;
    background: #f8fafc;
}

.line-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.2rem;
}

.line-title-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.product-title {
    font-size: 0.92rem;
    color: #0f172a;
    font-weight: 700;
    line-height: 1.4;
    /* long sanitary product names: wrap to two lines instead of cutting off */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

.badge-free {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #dcfce7;
    color: #15803d;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

.line-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
}

.sku-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #f1f5f9;
    color: #475569;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-family: monospace;
    border: 1px solid #e2e8f0;
}

.variant-tag {
    background: #eff6ff;
    color: #1d4ed8;
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 600;
}

.stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
}

.stock-pill.is-ok {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.stock-pill.is-over {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
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

.line-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.line-field :deep(.el-input-number) {
    width: 100%;
}

.line-field :deep(.el-input__inner) {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.line-qty-input.is-over :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px #f97316 inset;
    background: #fff7ed;
}

.line-price-input.is-changed :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px #f59e0b inset;
    background: #fffbeb;
}

.cell-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.qty-cell .cell-hint,
.price-cell .cell-hint {
    justify-content: center;
}

.cell-hint.is-changed {
    color: #b45309;
}

.was-price {
    text-decoration: line-through;
}

.restore-price {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: none;
    border-radius: 4px;
    background: #fef3c7;
    color: #b45309;
    cursor: pointer;
    font-size: 0.68rem;
}

.restore-price:hover {
    background: #f59e0b;
    color: #ffffff;
}

.unit-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.6rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-size: 0.82rem;
    color: #334155;
    font-weight: 500;
    border: 1px solid #e2e8f0;
}

.line-total-cell {
    text-align: end;
}

.line-total-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    padding-top: 0.35rem;
}

.line-total {
    font-weight: 800;
    color: #0f172a;
    font-size: 1.02rem;
    font-variant-numeric: tabular-nums;
}

.line-row-wrapper.is-free .line-total {
    color: #15803d;
}

.line-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.2rem;
}

.action-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    min-width: 32px;
    height: 32px;
    padding: 0 0.4rem;
    border: 1px solid transparent;
    background: transparent;
    color: #64748b;
    border-radius: 6px;
    cursor: pointer;
    font: inherit;
    transition: all 0.15s ease;
}

.action-btn.is-delete {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 32px;
    padding: 0 0.65rem;
    color: #dc2626;
    border: 1px solid #fecaca;
    background: #fff5f5;
    font-size: 0.8rem;
    font-weight: 700;
    border-radius: 6px;
}

.action-btn.is-delete:hover {
    color: #ffffff;
    background: #dc2626;
    border-color: #dc2626;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
}

.btn-clear-lines {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border: 1px solid #fecaca;
    background: #fff5f5;
    color: #dc2626;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-clear-lines:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* ── Quantity Stepper Controls ─────────────────────────────────── */
.qty-stepper-wrap {
    display: flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    overflow: hidden;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.qty-stepper-wrap:focus-within {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

.qty-step-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 32px;
    border: none;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    font-size: 0.72rem;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}

.qty-step-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.qty-step-btn.is-remove {
    background: #fff5f5;
    color: #dc2626;
}

.qty-step-btn.is-remove:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.qty-stepper-wrap .line-qty-input {
    flex: 1;
    min-width: 0;
}

.qty-stepper-wrap .line-qty-input :deep(.el-input__wrapper) {
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 2px;
}

.qty-stepper-wrap .line-qty-input :deep(.el-input__inner) {
    text-align: center;
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0;
}

.line-actions {
    align-items: center;
}

/* ── Source warehouse chip ─────────────────────────────────────── */
.wh-source-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 1px 7px;
    border: 1px solid #c7d2fe;
    border-radius: 4px;
    background: #eef2ff;
    color: #3730a3;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    max-width: 220px;
}

.wh-source-chip span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wh-source-chip .wh-caret {
    font-size: 0.6rem;
    opacity: 0.7;
}

.wh-source-chip:hover {
    background: #e0e7ff;
    border-color: #818cf8;
}

/* line ships from a warehouse other than the invoice default */
.wh-source-chip.is-custom {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

.wh-source-chip.is-missing {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #b91c1c;
}

.wh-qty-inline {
    font-weight: 700;
    opacity: 0.85;
}

.wh-qty-tag {
    margin-inline-start: 0.5rem;
    padding: 0 6px;
    border-radius: 999px;
    background: #dcfce7;
    color: #15803d;
    font-weight: 700;
}

.wh-qty-tag.is-zero {
    background: #f1f5f9;
    color: #94a3b8;
}

/* ── Focus mode ─────────────────────────────────────────────────── */
.is-focus .fx-hide {
    display: none !important;
}

/* With due date gone, the customer takes the whole head row. */
.is-focus .head-grid {
    grid-template-columns: minmax(0, 1fr);
}

/* Category select and "new product" leave the search row. */
.is-focus .quote-search-header-row {
    grid-template-columns: 1fr auto;
}

.is-focus .order-form-container {
    max-width: 1280px;
    margin-inline: auto;
}

.focus-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    max-width: 1280px;
    margin: 0 auto 0.75rem;
    padding: 0.55rem 0.9rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.focus-bar-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #0f172a;
    font-size: 1rem;
}

.focus-bar-title > i {
    color: #2563eb;
}

.focus-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 1px 8px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 700;
}

.focus-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    font: inherit;
    font-size: 0.82rem;
    cursor: pointer;
}

.focus-toggle:hover {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
}

/* ── Undo strip ─────────────────────────────────────────────────── */
.undo-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.85rem;
    margin-bottom: 0.6rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #92400e;
}

/* ── Collapsed "more options" bar ───────────────────────────────── */
.more-options-toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.5rem 0.85rem;
    margin: 0.25rem 0 1rem;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #334155;
    font: inherit;
    font-size: 0.85rem;
    cursor: pointer;
    text-align: start;
}

.more-options-toggle:hover {
    border-color: #93c5fd;
    background: #eff6ff;
}

.more-options-toggle.is-open {
    margin-bottom: 0.75rem;
}

.mot-title {
    font-weight: 700;
    white-space: nowrap;
}

.mot-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    flex: 1;
    min-width: 0;
}

.mot-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.55rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    font-size: 0.78rem;
    color: #475569;
}

.mot-chip i {
    color: #94a3b8;
}

.mot-caret {
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.more-options-toggle.is-open .mot-caret {
    transform: rotate(180deg);
}

/* ── On-demand extras (expenses, notes, tax) ────────────────────── */
.extras-toggles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.extra-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.75rem;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 999px;
    color: #475569;
    font: inherit;
    font-size: 0.8rem;
    cursor: pointer;
}

.extra-chip:hover {
    color: #1d4ed8;
    border-color: #93c5fd;
    background: #eff6ff;
}

.tax-add-chip {
    margin: 0.35rem 0 0.5rem;
}

.action-btn:hover {
    color: #1d4ed8;
    background: #eff6ff;
}

.action-btn.has-note {
    color: #2563eb;
    background: #eff6ff;
}

.note-dot {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #2563eb;
}

.action-btn.text-danger:hover {
    color: #dc2626;
    background: #fef2f2;
}

.line-note-drawer {
    /* align with the product column (index col 36px + gap + padding) */
    padding: 0.45rem 1rem 0.65rem;
    padding-inline-start: calc(36px + 0.75rem + 0.9rem);
    background: #f8fafc;
    border-top: 1px dashed #e2e8f0;
}

.mobile-label {
    display: none;
}

/* ── Bottom Grid: Notes & Settlement / Totals ────────────────────── */
.bottom-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.settlement-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.15rem;
    margin-bottom: 1rem;
}

.settlement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.settlement-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
}

.payment-method-row {
    margin-bottom: 0.85rem;
}

.method-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-top: 0.35rem;
}

.method-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.method-pill:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.method-pill.is-active {
    background: #eff6ff;
    border-color: #2563eb;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #2563eb;
}

.settlement-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    flex-wrap: wrap;
}

.paid-amount-input {
    width: 170px !important;
}

.settlement-quick-actions {
    display: flex;
    gap: 0.4rem;
}

.quick-pay-chip {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.8rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}

.quick-pay-chip:hover {
    background: #f1f5f9;
}

.quick-pay-chip.is-active {
    background: #ecfdf5;
    border-color: #10b981;
    color: #065f46;
}

.quick-pay-chip.is-credit.is-active {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
}

.cash-change-calculator {
    margin-top: 0.85rem;
    padding: 0.65rem 0.85rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.cash-calc-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cash-change-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.88rem;
}

.cash-change-badge .lbl {
    color: #166534;
}

.cash-change-badge .val {
    font-size: 1.1rem;
    color: #15803d;
}

.remaining-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.remaining-badge.is-settled {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.remaining-badge.is-owing {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.remaining-badge.is-credit {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.tafqeet-line {
    margin: 0.85rem 0 0;
    font-size: 0.85rem;
    color: #334155;
    border-top: 1px dashed #cbd5e1;
    padding-top: 0.65rem;
}

/* ── Totals Card ─────────────────────────────────────────────────── */
.totals {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.15rem 1.25rem;
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
    width: 120px;
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

.discount-quick-chips {
    display: flex;
    gap: 0.3rem;
    margin-top: 0.15rem;
    margin-bottom: 0.4rem;
}

.disc-chip {
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.7rem;
    color: #475569;
    cursor: pointer;
}

.disc-chip.is-active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.tax-quick-pills {
    display: inline-flex;
    gap: 0.25rem;
}

.tax-pill {
    padding: 1px 5px;
    border-radius: 3px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.7rem;
    color: #475569;
    cursor: pointer;
}

.tax-pill.is-on {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
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
    margin-top: 0.65rem;
    padding-top: 0.85rem;
    font-size: 1.25rem;
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
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(8px);
    border-top: 1px solid #e2e8f0;
    padding: 0.85rem 1.5rem;
    margin: 1.5rem -1.5rem -1.5rem;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
}

.action-bar-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1440px;
    margin: 0 auto;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.dock-left, .dock-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.blocker-hint {
    font-size: 0.82rem;
    color: #b45309;
    background: #fef3c7;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
}

.btn-save-print {
    font-weight: 700;
}

.btn-save-primary {
    font-weight: 700;
    padding: 0 1.5rem;
}

/* ── Keyboard Shortcuts Guide List ───────────────────────────────── */
.shortcuts-guide-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.sc-guide-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}

.sc-guide-row:last-child {
    border-bottom: none;
}

.sc-label {
    font-size: 0.88rem;
    color: #334155;
    font-weight: 600;
}

.sc-keys-combo {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #64748b;
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
    .line--head {
        display: none !important;
    }
    .line {
        grid-template-columns: 1fr;
        min-width: 0;
        gap: 0.75rem;
        padding: 1rem;
    }
    .line-row-wrapper {
        margin-bottom: 0.85rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #ffffff;
    }
    .line-index-col {
        display: none;
    }
    .mobile-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    .line-total-cell {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        padding: 0.6rem 0.85rem;
        border-radius: 8px;
    }
    .line-actions {
        border-top: 1px dashed #e2e8f0;
        padding-top: 0.5rem;
        justify-content: flex-end;
    }
    .lines.no-wh .line {
        grid-template-columns: 1fr;
        min-width: 0;
    }
    .line-total-stack {
        padding-top: 0;
    }
    .qty-cell .cell-hint,
    .price-cell .cell-hint {
        justify-content: flex-start;
    }
    .line-note-drawer {
        padding-inline-start: 1rem;
    }
    .mot-summary {
        display: none;
    }
}
</style>
