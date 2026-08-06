<template>
    <div class="sales-order-form-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>
                    <el-icon><Document /></el-icon>
                    {{ isEdit ? 'تعديل طلب البيع' : 'إنشاء طلب بيع جديد' }}
                </h1>
                <p>تجهيز طلب بيع متكامل على مراحل متسلسلة لضمان دقة العمليات وحسابات المخازن والعملاء.</p>
            </div>
            <div class="header-actions">
                <el-button @click="showShortcutsHelp = true" :icon="Key" class="shortcuts-btn">
                    اختصارات لوحة المفاتيح
                </el-button>
                <el-button @click="goBack" :icon="ArrowRight" class="back-btn">
                    الرجوع لطلبات البيع
                </el-button>
            </div>
        </div>

        <!-- Keyboard Shortcuts Help Dialog -->
        <el-dialog v-model="showShortcutsHelp" title="اختصارات لوحة المفاتيح" width="600px" class="shortcuts-dialog">
            <div class="shortcuts-grid">
                <div class="shortcut-item">
                    <div class="shortcut-key">Ctrl + B</div>
                    <div class="shortcut-desc">التركيز على البحث عن المنتجات</div>
                </div>
                <div class="shortcut-item">
                    <div class="shortcut-key">Ctrl + Enter</div>
                    <div class="shortcut-desc">التالي / تأكيد الطلب</div>
                </div>
                <div class="shortcut-item">
                    <div class="shortcut-key">Escape</div>
                    <div class="shortcut-desc">إغلاق قائمة البحث</div>
                </div>
                <div class="shortcut-item">
                    <div class="shortcut-key">Ctrl + N</div>
                    <div class="shortcut-desc">تفريغ النموذج</div>
                </div>
                <div class="shortcut-item">
                    <div class="shortcut-key">↑ / ↓</div>
                    <div class="shortcut-desc">التنقل في نتائج البحث</div>
                </div>
                <div class="shortcut-item">
                    <div class="shortcut-key">Enter</div>
                    <div class="shortcut-desc">إضافة المنتج المحدد</div>
                </div>
            </div>
        </el-dialog>

        <!-- Steps Indicator -->
        <el-steps :active="activeStep" finish-status="success" align-center class="mb-4 sales-steps">
            <el-step title="اختيار المنتجات" description="تحديد المنتجات والوحدات والكميات" />
            <el-step title="بيانات العميل والشحن" description="تخصيص العميل وتفاصيل التسليم والمالية" />
            <el-step title="معاينة وتأكيد" description="مراجعة الحسابات وتأكيد حفظ طلب البيع" />
        </el-steps>

        <!-- Validation Errors -->
        <el-alert
            v-if="formErrors.length"
            title="يرجى إصلاح الأخطاء التالية:"
            type="error"
            :closable="true"
            show-icon
            class="mb-4"
        >
            <ul class="error-list">
                <li v-for="(err, idx) in formErrors" :key="idx">{{ err }}</li>
            </ul>
        </el-alert>

        <!-- Transition Wrapper for Steps -->
        <Transition name="fade" mode="out-in">
            <!-- Step 1: Select Products -->
            <div v-if="activeStep === 0" :key="0" class="sales-order-layout step-1-container">
                <!-- Left Panel: Product Search + Items Table -->
                <div class="sales-order-left-panel">
                    <!-- Product Search -->
                    <div class="search-container">
                        <div class="search-header">
                            <el-icon><Search /></el-icon>
                            <span>البحث عن المنتجات وتجهيز الأصناف</span>
                            <div class="search-filters">
                                <el-select v-model="searchCategory" placeholder="الفئة" size="small" clearable @change="onSearchInput" class="filter-select">
                                    <el-option label="جميع الفئات" :value="null" />
                                    <el-option v-for="cat in categories" :key="cat.id" :label="cat.name_ar || cat.name" :value="cat.id" />
                                </el-select>
                                <el-select v-model="searchStockFilter" placeholder="المخزون" size="small" clearable @change="onSearchInput" class="filter-select">
                                    <el-option label="الكل" :value="null" />
                                    <el-option label="متوفر" value="available" />
                                    <el-option label="منخفض" value="low" />
                                    <el-option label="نفذ" value="out" />
                                </el-select>
                            </div>
                        </div>
                        <div class="product-search-wrapper">
                            <el-input
                                v-model="searchQuery"
                                placeholder="ابحث عن منتج بالاسم، الرمز SKU أو الباركود..."
                                size="large"
                                clearable
                                @input="onSearchInput"
                                @focus="showResults = true"
                                @keydown.down.prevent="navigateResult(1)"
                                @keydown.up.prevent="navigateResult(-1)"
                                @keydown.enter.prevent="selectHighlighted"
                                @keydown.esc.prevent="showResults = false"
                                ref="searchInputRef"
                                :loading="searchLoading"
                            >
                                <template #prefix>
                                    <el-icon><Search /></el-icon>
                                </template>
                                <template #suffix>
                                    <el-tooltip content="تفعيل محاكي الباركود" placement="top">
                                        <el-button
                                            :icon="Ticket"
                                            circle
                                            size="small"
                                            @click="focusBarcodeInput"
                                            class="barcode-btn"
                                        />
                                    </el-tooltip>
                                </template>
                            </el-input>

                            <!-- Search Results Dropdown -->
                            <Transition name="dropdown">
                                <div v-if="showResults && (searchResults.length || searchLoading)" class="search-dropdown">
                                    <div v-if="searchLoading" class="search-loading">
                                        <el-icon class="is-loading"><Loading /></el-icon>
                                        <span>جاري البحث عن المنتجات...</span>
                                    </div>
                                    <div v-else-if="searchResults.length" class="search-results-list">
                                        <div
                                            v-for="(product, index) in searchResults"
                                            :key="product.id"
                                            class="search-result-item"
                                            :class="{ highlighted: highlightedIndex === index, 'low-stock': product.stock_quantity <= 5 }"
                                            @click="addProduct(product)"
                                            @mouseenter="highlightedIndex = index"
                                        >
                                            <div class="product-image" v-if="product.image_main">
                                                <img :src="getImageUrl(product.image_main)" :alt="product.name_ar || product.name_en" />
                                            </div>
                                            <div class="product-image" v-else>
                                                <el-icon><Box /></el-icon>
                                            </div>
                                            <div class="product-info">
                                                <div class="product-name">{{ product.name_ar || product.name_en }}</div>
                                                <div class="product-sku" v-if="product.sku">SKU: {{ product.sku }}</div>
                                                <div class="product-meta">
                                                    <span class="stock-indicator" :class="{ 'low-stock': product.stock_quantity <= 5, 'out-of-stock': product.stock_quantity === 0 }">
                                                        <el-icon><Box /></el-icon> 
                                                        <span>{{ product.stock_quantity }} وحدة متوفرة</span>
                                                        <el-tag v-if="product.stock_quantity <= 5 && product.stock_quantity > 0" type="warning" size="small" round>مخزون منخفض</el-tag>
                                                        <el-tag v-if="product.stock_quantity === 0" type="danger" size="small" round>نفذ من المخزن</el-tag>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="product-actions">
                                                <div class="product-price">
                                                    {{ formatCurrency(product.price) }}
                                                </div>
                                                <el-button
                                                    type="primary"
                                                    :icon="Plus"
                                                    size="small"
                                                    circle
                                                    @click.stop="addProduct(product)"
                                                    class="add-btn"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="no-results">
                                        <div class="no-results-content">
                                            <el-icon :size="48"><WarningFilled /></el-icon>
                                            <h4>لا توجد نتائج مطابقة</h4>
                                            <p>يرجى تجربة كلمات بحث أخرى أو التأكد من تهجئة اسم المنتج.</p>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Items Table Card -->
                    <el-card shadow="hover" class="items-card">
                        <template #header>
                            <div class="card-header">
                                <div class="header-left">
                                    <el-icon><ShoppingCart /></el-icon>
                                    <span>أصناف طلب البيع المختارة</span>
                                </div>
                                <div class="header-right">
                                    <el-tag type="primary" round>{{ items.length }} أصناف</el-tag>
                                    <el-dropdown @command="handleQuickAction" trigger="click" class="quick-actions-dropdown">
                                        <el-button size="small" :icon="MoreFilled" circle />
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item command="duplicate-last" :disabled="items.length === 0">
                                                    <el-icon><CopyDocument /></el-icon> تكرار آخر صنف
                                                </el-dropdown-item>
                                                <el-dropdown-item command="clear-all" :disabled="items.length === 0">
                                                    <el-icon><Delete /></el-icon> تفريغ الكل
                                                </el-dropdown-item>
                                                <el-dropdown-item command="save-draft">
                                                    <el-icon><DocumentCopy /></el-icon> حفظ كمسودة
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </div>
                        </template>

                        <div v-if="items.length === 0" class="empty-items">
                            <el-icon :size="48"><ShoppingCart /></el-icon>
                            <p>لم يتم إضافة أي منتج للطلب بعد.</p>
                            <p class="hint">استخدم خانة البحث لإضافة المنتجات وتحديد الكميات والوحدات.</p>
                        </div>

                        <div v-else class="items-table-wrapper">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>المنتج / الصنف</th>
                                        <th>الوحدة</th>
                                        <th>الكمية</th>
                                        <th>سعر الوحدة</th>
                                        <th>الإجمالي</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in items" :key="item.product_id" class="item-table-row">
                                        <td class="product-cell">
                                            <div class="product-name">{{ item.name }}</div>
                                            <div class="product-sku" v-if="item.sku">SKU: {{ item.sku }}</div>
                                        </td>
                                        <td class="unit-cell">
                                            <el-select
                                                v-model="item.selectedUnit"
                                                placeholder="اختر الوحدة"
                                                size="small"
                                                @change="onUnitChange(index)"
                                                value-key="id"
                                                class="unit-select"
                                            >
                                                <el-option
                                                    v-for="unit in item.units"
                                                    :key="unit.id"
                                                    :label="`${unit.name_ar || unit.name}${unit.base_unit_multiplier > 1 ? ' (' + unit.base_unit_multiplier + ')' : ''}`"
                                                    :value="unit"
                                                />
                                            </el-select>
                                            <div v-if="item.selectedUnit?.barcode" class="unit-barcode">
                                                <el-icon><Ticket /></el-icon> {{ item.selectedUnit.barcode }}
                                            </div>
                                        </td>
                                        <td class="qty-cell">
                                            <div class="qty-control">
                                                <el-button
                                                    :icon="Minus"
                                                    size="small"
                                                    circle
                                                    @click="decrementQty(index)"
                                                    :disabled="item.quantity <= 1"
                                                />
                                                <el-input-number
                                                    v-model="item.quantity"
                                                    :min="1"
                                                    :max="item.stock || 9999"
                                                    size="small"
                                                    @change="updateTotals"
                                                    controls-position="inline"
                                                />
                                                <el-button
                                                    :icon="Plus"
                                                    size="small"
                                                    circle
                                                    @click="incrementQty(index)"
                                                />
                                            </div>
                                        </td>
                                        <td class="price-cell">
                                            <el-input-number
                                                v-model="item.price"
                                                :min="0"
                                                :precision="2"
                                                size="small"
                                                @change="updateTotals"
                                                controls-position="right"
                                            />
                                            <span class="currency">ل.س</span>
                                        </td>
                                        <td class="total-cell">
                                            {{ formatCurrency(item.price * item.quantity) }}
                                        </td>
                                        <td class="action-cell">
                                            <el-button
                                                type="danger"
                                                :icon="Delete"
                                                size="small"
                                                circle
                                                @click="removeItem(index)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </el-card>
                </div>

                <!-- Right Panel: Step 1 Navigation & Summary -->
                <div class="sales-order-right-panel">
                    <el-card shadow="hover" class="summary-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><Wallet /></el-icon>
                                <span>خلاصة الأصناف</span>
                            </div>
                        </template>
                        <div class="summary-body">
                            <div class="summary-row">
                                <span class="label">المجموع الفرعي للأصناف</span>
                                <span class="value">{{ formatCurrency(subtotal) }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="label">إجمالي عدد السلع</span>
                                <span class="value">{{ totalItemsQuantity }} قطعة</span>
                            </div>
                            <el-divider />
                            <el-button
                                type="primary"
                                size="large"
                                class="w-full submit-btn step-nav-btn"
                                :disabled="items.length === 0"
                                @click="goToStep(1)"
                            >
                                التالي: بيانات العميل والشحن <el-icon class="el-icon--right"><ArrowLeft /></el-icon>
                            </el-button>
                        </div>
                    </el-card>
                </div>
            </div>

            <!-- Step 2: Customer Selection & Financial Options -->
            <div v-else-if="activeStep === 1" :key="1" class="sales-order-layout step-2-container">
                <!-- Left Panel: Customer, Dates, Delivery address -->
                <div class="sales-order-left-panel">
                    <el-card shadow="hover" class="customer-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><User /></el-icon>
                                <span>اختيار العميل المشتري للطلب</span>
                            </div>
                        </template>

                        <el-select
                            v-model="form.customer_id"
                            placeholder="ابحث واختر العميل المشتري..."
                            filterable
                            clearable
                            size="large"
                            class="w-full"
                        >
                            <el-option
                                v-for="customer in customers"
                                :key="customer.id"
                                :label="`${customer.name} - ${customer.phone || customer.email}`"
                                :value="customer.id"
                            />
                        </el-select>

                        <!-- Selected Customer Profile Card (WOW effect!) -->
                        <Transition name="fade-slide">
                            <div v-if="selectedCustomer" class="selected-customer-profile-card">
                                <div class="avatar-wrapper">
                                    <div class="avatar-circle">
                                        <span>{{ selectedCustomer.name[0]?.toUpperCase() }}</span>
                                    </div>
                                </div>
                                <div class="profile-info-details">
                                    <h4>{{ selectedCustomer.name }}</h4>
                                    <div class="contact-grid">
                                        <div class="contact-item" v-if="selectedCustomer.phone">
                                            <el-icon><Phone /></el-icon>
                                            <span>{{ selectedCustomer.phone }}</span>
                                        </div>
                                        <div class="contact-item" v-if="selectedCustomer.email">
                                            <el-icon><Message /></el-icon>
                                            <span>{{ selectedCustomer.email }}</span>
                                        </div>
                                        <div class="contact-item full-width-grid" v-if="selectedCustomer.address">
                                            <el-icon><Location /></el-icon>
                                            <span>{{ selectedCustomer.address }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </el-card>

                    <el-card shadow="hover" class="delivery-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><Notebook /></el-icon>
                                <span>جدولة الشحن وعناوين التسليم</span>
                            </div>
                        </template>
                        <div class="delivery-card-body">
                            <el-row :gutter="20">
                                <el-col :span="12">
                                    <div class="summary-input-row mb-3">
                                        <label>تاريخ الطلب</label>
                                        <el-date-picker
                                            v-model="form.order_date"
                                            type="date"
                                            placeholder="تاريخ الطلب"
                                            format="YYYY-MM-DD"
                                            value-format="YYYY-MM-DD"
                                            class="w-full"
                                        />
                                    </div>
                                </el-col>
                                <el-col :span="12">
                                    <div class="summary-input-row mb-3">
                                        <label>التسليم المتوقع</label>
                                        <el-date-picker
                                            v-model="form.expected_delivery"
                                            type="date"
                                            placeholder="تاريخ التسليم المتوقع"
                                            format="YYYY-MM-DD"
                                            value-format="YYYY-MM-DD"
                                            class="w-full"
                                        />
                                    </div>
                                </el-col>
                            </el-row>
                            <div class="summary-input-row">
                                <label>عنوان التسليم والشحن</label>
                                <el-input
                                    v-model="form.shipping_address"
                                    type="textarea"
                                    :rows="3"
                                    placeholder="أدخل تفاصيل ومكان شحن البضاعة للعميل..."
                                />
                            </div>
                        </div>
                    </el-card>

                    <!-- Additional Expenses Section -->
                    <el-card shadow="hover" class="expenses-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><Plus /></el-icon>
                                <span>مصاريف شحن وتوصيل إضافية للطلب</span>
                            </div>
                        </template>
                        <div class="expenses-section">
                            <div class="expenses-header">
                                <span>قائمة بنود المصاريف الإدارية والشحن</span>
                                <el-button type="primary" size="small" @click="addExpense">
                                    <el-icon><Plus /></el-icon> إضافة مصاريف
                                </el-button>
                            </div>
                            <div v-if="form.expenses.length === 0" class="empty-expenses-box">
                                لا توجد أي مصاريف إضافية مضافة على هذا الطلب حالياً.
                            </div>
                            <div v-else class="expenses-list">
                                <div v-for="(expense, index) in form.expenses" :key="index" class="expense-item">
                                    <el-input
                                        v-model="expense.description"
                                        placeholder="الوصف / البيان (مثال: شحن سريع)"
                                        size="small"
                                        class="expense-description"
                                    />
                                    <el-select v-model="expense.category" size="small" class="expense-category">
                                        <el-option value="shipping" label="شحن وتوصيل" />
                                        <el-option value="packaging" label="تغليف وتحضير" />
                                        <el-option value="handling" label="نقل وتداول" />
                                        <el-option value="other" label="أخرى" />
                                    </el-select>
                                    <el-input-number
                                        v-model="expense.amount"
                                        :min="0"
                                        :precision="2"
                                        size="small"
                                        @change="updateTotals"
                                        class="expense-amount"
                                    />
                                    <el-button
                                        type="danger"
                                        :icon="Delete"
                                        size="small"
                                        circle
                                        @click="removeExpense(index)"
                                    />
                                </div>
                            </div>
                        </div>
                    </el-card>
                </div>

                <!-- Right Panel: Step 2 Calculations & Navigation -->
                <div class="sales-order-right-panel">
                    <el-card shadow="hover" class="summary-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><Wallet /></el-icon>
                                <span>الحسابات والمالية</span>
                            </div>
                        </template>
                        <div class="summary-body">
                            <div class="summary-row">
                                <span class="label">المجموع الفرعي للأصناف</span>
                                <span class="value">{{ formatCurrency(subtotal) }}</span>
                            </div>
                            
                            <div class="summary-inputs">
                                <div class="summary-input-row">
                                    <label>حالة الطلب</label>
                                    <el-select v-model="form.status" size="small" class="status-select">
                                        <el-option
                                            v-for="status in availableStatuses"
                                            :key="status"
                                            :value="status"
                                            :label="statusLabels[status]"
                                        >
                                            <div class="status-option">
                                                <el-tag :type="statusColors[status]" size="small">{{ statusLabels[status] }}</el-tag>
                                            </div>
                                        </el-option>
                                    </el-select>
                                </div>
                                <div class="summary-input-row">
                                    <label>خصم إضافي</label>
                                    <el-input-number
                                        v-model="form.discount"
                                        :min="0"
                                        :precision="2"
                                        size="small"
                                        @change="updateTotals"
                                    />
                                </div>
                                <div class="summary-input-row">
                                    <label>ضريبة إضافية</label>
                                    <el-input-number
                                        v-model="form.tax"
                                        :min="0"
                                        :precision="2"
                                        size="small"
                                        @change="updateTotals"
                                    />
                                </div>
                                <div class="summary-input-row">
                                    <label>طريقة الدفع</label>
                                    <el-select v-model="form.payment_method" size="small">
                                        <el-option label="نقداً" value="cash" />
                                        <el-option label="بطاقة ائتمانية" value="card" />
                                        <el-option label="تحويل بنكي" value="transfer" />
                                    </el-select>
                                </div>
                            </div>

                            <el-divider />

                            <div class="summary-row discount">
                                <span class="label">إجمالي الخصم</span>
                                <span class="value negative">-{{ formatCurrency(form.discount) }}</span>
                            </div>
                            <div class="summary-row tax">
                                <span class="label">إجمالي الضريبة</span>
                                <span class="value positive">+{{ formatCurrency(form.tax) }}</span>
                            </div>
                            <div class="summary-row expenses" v-if="totalExpenses > 0">
                                <span class="label">مصاريف إضافية</span>
                                <span class="value positive">+{{ formatCurrency(totalExpenses) }}</span>
                            </div>

                            <el-divider />

                            <div class="summary-row total">
                                <span class="label">المبلغ النهائي</span>
                                <span class="value total-value">{{ formatCurrency(total) }}</span>
                            </div>

                            <el-divider />

                            <div class="step-nav-buttons">
                                <el-button @click="goToStep(0)" size="large" class="w-full mb-2 step-back-btn">
                                    <el-icon class="el-icon--left"><ArrowRight /></el-icon> السابق: المنتجات
                                </el-button>
                                <el-button
                                    type="primary"
                                    size="large"
                                    class="w-full submit-btn step-nav-btn"
                                    :disabled="!form.customer_id"
                                    @click="goToStep(2)"
                                >
                                    التالي: مراجعة وتأكيد <el-icon class="el-icon--right"><ArrowLeft /></el-icon>
                                </el-button>
                            </div>
                        </div>
                    </el-card>
                </div>
            </div>

            <!-- Step 3: Preview & Confirm -->
            <div v-else-if="activeStep === 2" :key="2" class="step-3-container preview-layout">
                <el-card shadow="hover" class="preview-card mb-4">
                    <template #header>
                        <div class="card-header">
                            <el-icon><Check /></el-icon>
                            <span>مراجعة ومعاينة كافة تفاصيل طلب البيع</span>
                        </div>
                    </template>

                    <div class="preview-paper-layout">
                        <!-- Preview Header / Watermark style layout -->
                        <div class="preview-corporate-header">
                            <div class="corp-logo-name">
                                <div class="logo-circle">
                                    <el-icon :size="24"><ShoppingCart /></el-icon>
                                </div>
                                <div class="logo-text">
                                    <h2>مؤسسة أوان التجارية</h2>
                                    <span>نظام إدارة موارد المؤسسات ERP</span>
                                </div>
                            </div>
                            <div class="preview-document-tag">
                                <h3>طلب مبيعات معلق</h3>
                                <span>تاريخ السند: {{ form.order_date }}</span>
                            </div>
                        </div>

                        <el-divider />

                        <div class="preview-section-grid">
                            <!-- Left Grid Column: Customer & Delivery Info -->
                            <div class="preview-info-box">
                                <h3 class="preview-sub-title">العميل ومعلومات الشحن والتسليم</h3>
                                <div class="preview-details-list">
                                    <div class="preview-detail-row">
                                        <span class="lbl">العميل المشتري:</span>
                                        <strong>{{ selectedCustomerName }}</strong>
                                    </div>
                                    <div class="preview-detail-row">
                                        <span class="lbl">تاريخ الطلب:</span>
                                        <strong>{{ form.order_date }}</strong>
                                    </div>
                                    <div class="preview-detail-row">
                                        <span class="lbl">التسليم المتوقع:</span>
                                        <strong>{{ form.expected_delivery || 'غير محدد' }}</strong>
                                    </div>
                                    <div class="preview-detail-row">
                                        <span class="lbl">طريقة الدفع:</span>
                                        <el-tag size="small" type="info">{{ paymentMethodLabel }}</el-tag>
                                    </div>
                                    <div class="preview-detail-row">
                                        <span class="lbl">حالة الطلب:</span>
                                        <el-tag size="small" :type="statusColors[form.status]">{{ statusLabels[form.status] }}</el-tag>
                                    </div>
                                    <div class="preview-detail-row full-width-row">
                                        <span class="lbl">عنوان الشحن والتسليم:</span>
                                        <span class="address-txt">{{ form.shipping_address || 'لا يوجد عنوان تسليم مضاف.' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Grid Column: Financial Summary Details -->
                            <div class="preview-financial-box">
                                <h3 class="preview-sub-title">الفاتورة والحسابات المالية</h3>
                                <div class="preview-financial-summary">
                                    <div class="preview-fin-row">
                                        <span>المجموع الفرعي للأصناف</span>
                                        <strong>{{ formatCurrency(subtotal) }}</strong>
                                    </div>
                                    <div class="preview-fin-row discount text-danger">
                                        <span>الخصومات الإضافية (-)</span>
                                        <strong>-{{ formatCurrency(form.discount) }}</strong>
                                    </div>
                                    <div class="preview-fin-row tax text-success">
                                        <span>الضرائب المضافة (+)</span>
                                        <strong>+{{ formatCurrency(form.tax) }}</strong>
                                    </div>
                                    <div class="preview-fin-row expenses text-success" v-if="totalExpenses > 0">
                                        <span>مصاريف الشحن والتسليم (+)</span>
                                        <strong>+{{ formatCurrency(totalExpenses) }}</strong>
                                    </div>
                                    <el-divider class="my-2" />
                                    <div class="preview-fin-row total-row">
                                        <span>المبلغ النهائي المستحق</span>
                                        <span class="total-price">{{ formatCurrency(total) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Items Grid List -->
                        <div class="preview-items-list-container mt-4">
                            <h3 class="preview-sub-title mb-2">قائمة السلع والمنتجات المطلوبة</h3>
                            <table class="preview-items-table">
                                <thead>
                                    <tr>
                                        <th>اسم المنتج / الصنف</th>
                                        <th>الوحدة المختارة</th>
                                        <th>الكمية المطلوبة</th>
                                        <th>سعر Unit</th>
                                        <th>الإجمالي الفرعي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in items" :key="item.product_id">
                                        <td class="item-name-cell">
                                            <strong>{{ item.name }}</strong>
                                            <span class="sku-block" v-if="item.sku">SKU: {{ item.sku }}</span>
                                        </td>
                                        <td>{{ item.selectedUnit?.name_ar || item.selectedUnit?.name }}</td>
                                        <td>{{ item.quantity }} قطعة</td>
                                        <td>{{ formatCurrency(item.price) }}</td>
                                        <td class="total-txt">{{ formatCurrency(item.price * item.quantity) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Administration Notes -->
                        <div class="preview-notes-box mt-4">
                            <h3 class="preview-sub-title mb-1">ملاحظات إدارية وتوجيهات للطلب</h3>
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="2"
                                placeholder="أدخل أي ملاحظات خاصة بالطلب أو شروط إدارية إضافية للتوثيق..."
                            />
                        </div>
                    </div>

                    <!-- Step 3 Action Navigation -->
                    <div class="step-3-nav-bar mt-4">
                        <el-button @click="goToStep(1)" size="large" class="step-back-btn">
                            <el-icon class="el-icon--left"><ArrowRight /></el-icon> السابق: تعديل البيانات
                        </el-button>
                        <el-button
                            type="success"
                            size="large"
                            :loading="submitting"
                            @click="submitSalesOrder"
                            class="confirm-order-btn"
                        >
                            <el-icon class="el-icon--left"><Check /></el-icon> تأكيد وإنشاء طلب البيع النهائي
                        </el-button>
                    </div>
                </el-card>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useCustomersStore } from '@/stores/customers';
import { useProductsStore } from '@/stores/products';
import { posApi } from '@/api/pos';
import { salesOrdersApi } from '@/api/salesOrders';
import { ElMessage } from 'element-plus';
import {
    Document, Search, ShoppingCart, User, Wallet, Notebook,
    ArrowRight, ArrowLeft, Plus, Minus, Delete, Check, Loading,
    Ticket, Box, WarningFilled, Phone, Message, Location, Key,
    MoreFilled, CopyDocument, DocumentCopy
} from '@element-plus/icons-vue';

const router = useRouter();
const route = useRoute();
const customersStore = useCustomersStore();
const productsStore = useProductsStore();

const isEdit = computed(() => !!route.params.id);
const searchInputRef = ref(null);
const showShortcutsHelp = ref(false);

// Wizard Active Step
const activeStep = ref(0);

// Form data
const form = reactive({
    customer_id: null,
    discount: 0,
    tax: 0,
    notes: '',
    status: 'pending',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery: '',
    shipping_address: '',
    payment_method: 'cash',
    expenses: []
});

// Status transitions configuration
const statusTransitions = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: []
};

const statusLabels = {
    pending: 'معلق',
    confirmed: 'مؤكد',
    processing: 'قيد المعالجة',
    shipped: 'تم الشحن',
    delivered: 'تم التسليم',
    cancelled: 'ملغي'
};

const statusColors = {
    pending: 'warning',
    confirmed: 'primary',
    processing: 'info',
    shipped: 'success',
    delivered: 'success',
    cancelled: 'danger'
};

const items = ref([]);
const formErrors = ref([]);
const submitting = ref(false);

// Search
const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const showResults = ref(false);
const highlightedIndex = ref(-1);
const searchCategory = ref(null);
const searchStockFilter = ref(null);
const categories = ref([]);
let searchTimeout = null;

// Customers
const customers = ref([]);

// Calculations
const subtotal = computed(() => {
    return items.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const totalItemsQuantity = computed(() => {
    return items.value.reduce((sum, item) => sum + item.quantity, 0);
});

const totalExpenses = computed(() => {
    return form.expenses.reduce((sum, exp) => sum + (exp.amount || 0), 0);
});

const total = computed(() => {
    return Math.max(0, subtotal.value - (form.discount || 0) + (form.tax || 0) + totalExpenses.value);
});

// Available status transitions based on current status
const availableStatuses = computed(() => {
    const currentStatus = form.status;
    const transitions = statusTransitions[currentStatus] || [];
    return [currentStatus, ...transitions];
});

// Customer object details for selected profile card
const selectedCustomer = computed(() => {
    return customers.value.find(c => c.id === form.customer_id) || null;
});

// Customer name for preview step
const selectedCustomerName = computed(() => {
    const cust = selectedCustomer.value;
    return cust ? `${cust.name} (${cust.phone || cust.email || ''})` : 'غير محدد';
});

// Payment method labels for preview
const paymentMethodLabel = computed(() => {
    const methods = {
        cash: 'نقداً',
        card: 'بطاقة ائتمانية',
        transfer: 'تحويل بنكي'
    };
    return methods[form.payment_method] || form.payment_method;
});

// Methods
const formatCurrency = (value) => {
    return new Intl.NumberFormat('ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0) + ' ل.س';
};

const getImageUrl = (image) => {
    if (!image) return '';
    if (image.startsWith('http')) return image;
    return `/storage/${image}`;
};

const onSearchInput = (query) => {
    clearTimeout(searchTimeout);
    highlightedIndex.value = -1;

    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }

    searchLoading.value = true;
    showResults.value = true;

    searchTimeout = setTimeout(async () => {
        try {
            const res = await posApi.productLookup({ q: query });
            let data = res.data?.data || res.data || [];
            data = Array.isArray(data) ? data : [];
            
            // Apply stock filter
            if (searchStockFilter.value) {
                data = data.filter(product => {
                    if (searchStockFilter.value === 'available') return product.stock_quantity > 5;
                    if (searchStockFilter.value === 'low') return product.stock_quantity > 0 && product.stock_quantity <= 5;
                    if (searchStockFilter.value === 'out') return product.stock_quantity === 0;
                    return true;
                });
            }
            
            searchResults.value = data;
            await nextTick();
            updateDropdownPosition();
        } catch (error) {
            console.error('Search error:', error);
            searchResults.value = [];
        } finally {
            searchLoading.value = false;
        }
    }, 300);
};

const addProduct = (product) => {
    const existingIndex = items.value.findIndex(i => i.product_id === product.id);

    if (existingIndex !== -1) {
        items.value[existingIndex].quantity += 1;
    } else {
        const defaultUnit = {
            id: null,
            name: product.unit || 'قطعة',
            name_ar: product.unit || 'قطعة',
            base_unit_multiplier: 1,
            price_multiplier: 1,
            barcode: product.barcode || ''
        };

        items.value.push({
            product_id: product.id,
            name: product.name_ar || product.name_en,
            sku: product.sku || '',
            price: parseFloat(product.price) || 0,
            quantity: 1,
            stock: product.stock_quantity || 0,
            unit: product.unit || '',
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            base_price: parseFloat(product.price) || 0
        });

        loadProductUnits(product.id, items.value.length - 1);
    }

    searchQuery.value = '';
    searchResults.value = [];
    showResults.value = false;
    updateTotals();
};

const loadProductUnits = async (productId, itemIndex) => {
    try {
        const token = localStorage.getItem('token');
        const res = await fetch(`/api/v1/admin/products/${productId}/units`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            }
        });
        const data = await res.json();

        if (data.success && data.data && data.data.length > 0) {
            const units = data.data.map(u => ({
                id: u.id,
                name: u.name,
                name_ar: u.name_ar || u.name,
                base_unit_multiplier: parseFloat(u.base_unit_multiplier),
                price_multiplier: parseFloat(u.price_multiplier),
                barcode: u.barcode || ''
            }));

            const defaultUnit = units.find(u => u.is_default) || units[0];

            if (items.value[itemIndex]) {
                items.value[itemIndex].units = units;
                items.value[itemIndex].selectedUnit = defaultUnit;
                items.value[itemIndex].price = items.value[itemIndex].base_price * defaultUnit.price_multiplier;
                updateTotals();
            }
        }
    } catch (error) {
        console.error('Failed to load units:', error);
    }
};

const onUnitChange = (index) => {
    const item = items.value[index];
    if (item && item.selectedUnit) {
        item.price = item.base_price * item.selectedUnit.price_multiplier;
        updateTotals();
    }
};

const removeItem = (index) => {
    items.value.splice(index, 1);
    updateTotals();
};

const incrementQty = (index) => {
    items.value[index].quantity += 1;
    updateTotals();
};

const decrementQty = (index) => {
    if (items.value[index].quantity > 1) {
        items.value[index].quantity -= 1;
        updateTotals();
    }
};

const updateTotals = () => {
    items.value = [...items.value];
};

const navigateResult = (direction) => {
    if (!searchResults.value.length) return;

    const max = searchResults.value.length - 1;
    if (direction === 1) {
        highlightedIndex.value = highlightedIndex.value >= max ? 0 : highlightedIndex.value + 1;
    } else {
        highlightedIndex.value = highlightedIndex.value <= 0 ? max : highlightedIndex.value - 1;
    }
};

const selectHighlighted = () => {
    if (highlightedIndex.value >= 0 && highlightedIndex.value < searchResults.value.length) {
        addProduct(searchResults.value[highlightedIndex.value]);
    }
};

const focusBarcodeInput = () => {
    searchInputRef.value?.focus();
};

const addExpense = () => {
    form.expenses.push({
        description: '',
        category: 'other',
        amount: 0
    });
};

const removeExpense = (index) => {
    form.expenses.splice(index, 1);
    updateTotals();
};

const handleQuickAction = (command) => {
    switch (command) {
        case 'duplicate-last':
            if (items.value.length > 0) {
                const lastItem = { ...items.value[items.value.length - 1] };
                items.value.push(lastItem);
                updateTotals();
                ElMessage.success('تم تكرار آخر صنف');
            }
            break;
        case 'clear-all':
            if (items.value.length > 0 && confirm('هل أنت متأكد من تفريغ كافة الأصناف المضافة؟')) {
                items.value = [];
                form.customer_id = null;
                form.discount = 0;
                form.tax = 0;
                form.notes = '';
                form.expenses = [];
                updateTotals();
                ElMessage.success('تم تفريغ النموذج');
            }
            break;
        case 'save-draft':
            localStorage.setItem('sales-order-draft', JSON.stringify({
                form: form.value,
                items: items.value
            }));
            ElMessage.success('تم حفظ المسودة');
            break;
    }
};

// Wizard Navigation logic
const goToStep = (step) => {
    formErrors.value = [];
    if (step === 1 && items.value.length === 0) {
        ElMessage.warning('يرجى إضافة صنف منتج واحد على الأقل للطلب قبل الانتقال للمرحلة التالية.');
        return;
    }
    if (step === 2 && !form.customer_id) {
        ElMessage.warning('يرجى تحديد العميل المشتري أولاً قبل الانتقال للمرحلة النهائية.');
        return;
    }
    activeStep.value = step;

    // Focus input on Step 0 mount
    if (step === 0) {
        setTimeout(() => {
            searchInputRef.value?.focus();
        }, 150);
    }
};

const submitSalesOrder = async () => {
    formErrors.value = [];

    if (!form.customer_id) {
        formErrors.value.push('الرجاء اختيار العميل المشتري للطلب.');
        return;
    }

    if (items.value.length === 0) {
        formErrors.value.push('يرجى إضافة صنف منتج واحد على الأقل للطلب.');
        return;
    }

    submitting.value = true;

    try {
        const payload = {
            customer_id: form.customer_id,
            discount: form.discount || 0,
            tax: form.tax || 0,
            notes: form.notes,
            status: form.status,
            order_date: form.order_date,
            expected_delivery: form.expected_delivery || null,
            shipping_address: form.shipping_address,
            payment_method: form.payment_method,
            items: items.value.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.price,
                product_unit_id: item.selectedUnit?.id || null
            }))
        };

        if (isEdit.value) {
            await salesOrdersApi.update(route.params.id, payload);
            ElMessage.success('تم تحديث طلب البيع بنجاح.');
        } else {
            await salesOrdersApi.create(payload);
            ElMessage.success('تم إنشاء طلب البيع بنجاح.');
        }

        router.push('/admin/sales/sales-orders');
    } catch (error) {
        console.error('Submit error:', error);
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            formErrors.value = Object.values(errors).flat();
        } else {
            formErrors.value = [error.message || 'فشل في حفظ طلب البيع.'];
        }
    } finally {
        submitting.value = false;
    }
};

const goBack = () => {
    router.push('/admin/sales/sales-orders');
};

const handleKeyboardShortcuts = (e) => {
    // Ctrl/Cmd + B: Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
    // Ctrl/Cmd + Enter: Confirm/Submit order
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (activeStep.value < 2) {
            goToStep(activeStep.value + 1);
        } else if (items.value.length > 0) {
            submitSalesOrder();
        }
    }
    // Escape: Close search dropdown
    if (e.key === 'Escape') {
        showResults.value = false;
    }
    // Ctrl/Cmd + N: Clear items
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (items.value.length > 0 && confirm('هل أنت متأكد من تفريغ كافة الأصناف المضافة؟')) {
            items.value = [];
            form.customer_id = null;
            form.discount = 0;
            form.tax = 0;
            form.notes = '';
            form.expenses = [];
            updateTotals();
        }
    }
};

const handleClickOutside = (e) => {
    const searchWrapper = document.querySelector('.product-search-wrapper');
    const dropdown = document.querySelector('.search-dropdown');
    if (searchWrapper && !searchWrapper.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
        showResults.value = false;
    }
};

const updateDropdownPosition = () => {
    const searchInput = searchInputRef.value?.$el;
    const dropdown = document.querySelector('.search-dropdown');
    if (searchInput && dropdown && showResults.value) {
        const rect = searchInput.getBoundingClientRect();
        dropdown.style.top = `${rect.bottom + 8}px`;
    }
};

onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyboardShortcuts);
    window.addEventListener('resize', updateDropdownPosition);
    window.addEventListener('scroll', updateDropdownPosition);

    try {
        await customersStore.fetchCustomers();
        customers.value = customersStore.customers;
    } catch (error) {
        console.error('Failed to load customers:', error);
    }

    if (isEdit.value) {
        try {
            const res = await salesOrdersApi.getById(route.params.id);
            const order = res.data.data;
            if (order) {
                form.customer_id = order.customer_id;
                form.discount = parseFloat(order.discount) || 0;
                form.tax = parseFloat(order.tax) || 0;
                form.notes = order.notes || '';
                form.status = order.status || 'pending';
                form.order_date = order.order_date || '';
                form.expected_delivery = order.expected_delivery || '';
                form.shipping_address = order.shipping_address || '';
                form.payment_method = order.payment_method || 'cash';

                if (order.items) {
                    items.value = order.items.map(item => {
                        const defaultUnit = {
                            id: item.product_unit_id || null,
                            name: item.product?.unit || 'قطعة',
                            name_ar: item.product?.unit || 'قطعة',
                            base_unit_multiplier: 1,
                            price_multiplier: 1,
                            barcode: item.product?.barcode || ''
                        };

                        return {
                            product_id: item.product_id,
                            name: item.product?.name_ar || item.product?.name_en || 'منتج غير معروف',
                            sku: item.product?.sku || '',
                            price: parseFloat(item.unit_price) || 0,
                            quantity: item.quantity || 1,
                            stock: item.product?.stock_quantity || 0,
                            selectedUnit: defaultUnit,
                            units: [defaultUnit],
                            base_price: parseFloat(item.unit_price) || 0
                        };
                    });

                    for (let i = 0; i < items.value.length; i++) {
                        loadProductUnits(items.value[i].product_id, i);
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load sales order:', error);
            ElMessage.error('فشل في تحميل تفاصيل طلب البيع للتعديل.');
        }
    }

    if (!isEdit.value) {
        setTimeout(() => {
            searchInputRef.value?.focus();
        }, 100);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyboardShortcuts);
    window.removeEventListener('resize', updateDropdownPosition);
    window.removeEventListener('scroll', updateDropdownPosition);
    clearTimeout(searchTimeout);
});
</script>

<style scoped>
.sales-order-form-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title {
    display: flex;
    flex-direction: column;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #1f2d3d 0%, #475569 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.back-btn {
    border-radius: 8px;
    transition: all 0.2s ease;
}

.back-btn:hover {
    transform: translateX(3px);
}

.sales-steps {
    padding: 1.5rem 1rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

:deep(.sales-steps .el-step__head.is-success .el-step__line) {
    background-color: #3b82f6;
    background-image: linear-gradient(90deg, #3b82f6, #8b5cf6);
}

:deep(.sales-steps .el-step__line-inner) {
    right: 0 !important;
    left: auto !important;
    border-style: none !important;
}

:deep(.sales-steps .el-step__head.is-success .el-step__icon.is-text) {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    border: none;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
}

:deep(.sales-steps .el-step__head.is-process .el-step__icon.is-text) {
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

:deep(.sales-steps .el-step__head.is-wait .el-step__icon.is-text) {
    background: #fff;
    color: #94a3b8;
    border: 2px solid #cbd5e1;
}

:deep(.sales-steps .el-step__title) {
    font-family: 'Cairo', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
}

:deep(.sales-steps .el-step__title.is-success) {
    color: #10b981;
}

:deep(.sales-steps .el-step__title.is-process) {
    color: #3b82f6;
    font-weight: 800;
}

:deep(.sales-steps .el-step__title.is-wait) {
    color: #64748b;
}

:deep(.sales-steps .el-step__description) {
    font-family: 'Cairo', sans-serif;
    font-size: 0.75rem;
    line-height: 1.4;
    margin-top: 0.25rem;
}

:deep(.sales-steps .el-step__description.is-success) {
    color: #059669;
}

:deep(.sales-steps .el-step__description.is-process) {
    color: #475569;
    font-weight: 600;
}

:deep(.sales-steps .el-step__description.is-wait) {
    color: #94a3b8;
}

.mb-4 {
    margin-bottom: 1rem;
}

.error-list {
    margin: 0;
    padding-left: 1.25rem;
}

.error-list li {
    margin: 0.25rem 0;
}

/* Layout */
.sales-order-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    align-items: start;
}

.sales-order-left-panel,
.sales-order-right-panel {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.sales-order-right-panel {
    position: sticky;
    top: 90px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}

/* Card Headers */
.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Product Search */
.search-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    position: relative;
    z-index: 50;
    transition: all 0.3s ease;
}

.search-container:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.search-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
}

.search-header .el-icon {
    font-size: 1.2rem;
}

.product-search-wrapper {
    position: relative;
    padding: 1.25rem;
    min-height: 80px;
}

.search-dropdown {
    position: fixed;
    top: auto;
    left: 50%;
    transform: translateX(-50%);
    width: 600px;
    max-width: 90vw;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    max-height: 500px;
    overflow-y: auto;
    z-index: 1000;
    border: 1px solid #e5e7eb;
    margin-top: 0.5rem;
}

.search-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.5rem;
    color: #6b7280;
}

.search-results-list {
    max-height: 400px;
    overflow-y: auto;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.2s ease;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover,
.search-result-item.highlighted {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    transform: translateX(2px);
}

.search-result-item.low-stock {
    background: #fff7ed;
    border-left: 3px solid #f59e0b;
}

.search-result-item.low-stock:hover,
.search-result-item.low-stock.highlighted {
    background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
}

.product-image {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    overflow: hidden;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image .el-icon {
    font-size: 1.5rem;
    color: #9ca3af;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.product-sku {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.product-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: #6b7280;
}

.stock-indicator {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stock-indicator.low-stock {
    color: #d97706;
}

.stock-indicator.out-of-stock {
    color: #dc2626;
}

.product-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
    flex-shrink: 0;
}

.product-price {
    font-weight: 700;
    color: #3b82f6;
    font-size: 1rem;
    white-space: nowrap;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.add-btn {
    transition: all 0.2s ease;
}

.add-btn:hover {
    transform: scale(1.1);
}

.no-results {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.no-results-content {
    text-align: center;
    color: #9ca3af;
}

.no-results-content .el-icon {
    margin-bottom: 1rem;
    color: #d1d5db;
}

.no-results-content h4 {
    margin: 0 0 0.5rem;
    color: #6b7280;
    font-size: 1rem;
}

.no-results-content p {
    margin: 0;
    font-size: 0.875rem;
    color: #9ca3af;
}

.barcode-btn {
    margin-left: 0.5rem;
}

/* Selected Customer Profile Card */
.selected-customer-profile-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
    border-radius: 12px;
    border: 1px solid #dbeafe;
    margin-top: 1.25rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.05);
}

.avatar-wrapper {
    flex-shrink: 0;
}

.avatar-circle {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.4rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
}

.profile-info-details {
    flex: 1;
    min-width: 0;
}

.profile-info-details h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem 1rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: #64748b;
}

.contact-item .el-icon {
    font-size: 0.95rem;
    color: #3b82f6;
}

.contact-item.full-width-grid {
    grid-column: span 2;
}

/* Improved focus states */
.el-input:focus-within,
.el-select:focus-within,
.el-input-number:focus-within {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    border-radius: 6px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* Empty State */
.empty-items {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}

.empty-items .el-icon {
    margin-bottom: 1rem;
    color: #d1d5db;
}

.empty-items p {
    margin: 0;
    font-size: 1rem;
}

.empty-items .hint {
    font-size: 0.875rem;
    margin-top: 0.5rem;
    color: #d1d5db;
}

/* Items Table */
.items-table-wrapper {
    overflow-x: auto;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table thead th {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.75rem 1rem;
    text-align: right;
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #cbd5e1;
}

.items-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.items-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.product-cell .product-name {
    font-weight: 600;
    color: #1e293b;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.product-cell .product-sku {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

.unit-cell {
    min-width: 150px;
}

.unit-select {
    width: 100%;
}

.unit-barcode {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.qty-cell {
    white-space: nowrap;
}

.qty-control {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.price-cell {
    white-space: nowrap;
}

.price-cell .currency {
    margin-right: 0.5rem;
    color: #94a3b8;
    font-size: 0.8rem;
}

.total-cell {
    font-weight: 700;
    color: #3b82f6;
    white-space: nowrap;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.action-cell {
    text-align: center;
}

/* Expenses */
.empty-expenses-box {
    text-align: center;
    padding: 1.5rem;
    color: #94a3b8;
    font-style: italic;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px dashed #cbd5e1;
}

.expenses-section {
    padding: 0.5rem 0;
}

.expenses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: #475569;
}

.expenses-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.expense-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.expense-description {
    flex: 2;
}

.expense-category {
    flex: 1.5;
}

.expense-amount {
    flex: 1.5;
}

/* Summary Card */
.summary-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-row .label {
    color: #64748b;
    font-size: 0.95rem;
    font-weight: 500;
}

.summary-row .value {
    font-weight: 700;
    color: #1e293b;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.summary-row.total .label {
    font-size: 1.1rem;
    font-weight: 800;
}

.summary-row.total .total-value {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.summary-row.discount .value.negative {
    color: #dc2626;
}

.summary-row.tax .value.positive {
    color: #16a34a;
}

.summary-inputs {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.75rem 0;
}

.summary-input-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

.summary-input-row label {
    color: #475569;
    font-size: 0.875rem;
    font-weight: 600;
    min-width: 60px;
}

.status-select {
    width: 100%;
}

.status-select .el-select__wrapper {
    border-radius: 8px;
}

.status-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.step-nav-btn {
    transition: all 0.3s ease;
}

.step-nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(59, 130, 246, 0.25);
}

.step-back-btn {
    transition: all 0.2s ease;
}

.step-back-btn:hover {
    transform: translateY(-1px);
    background-color: #f1f5f9;
}

.submit-btn {
    width: 100%;
    height: 48px;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 10px;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    border: none;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.w-full {
    width: 100%;
}

/* Step 3 Preview Styling */
.preview-layout {
    max-width: 1200px;
    margin: 0 auto;
}

.preview-card {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.preview-paper-layout {
    background: #fff;
    padding: 2.5rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    position: relative;
    overflow: hidden;
}

/* Watermark logo background */
.preview-paper-layout::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 400px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23eff6ff' width='200' height='200'%3E%3Cpath d='M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.4;
    pointer-events: none;
    z-index: 1;
}

.preview-corporate-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 2;
    position: relative;
}

.corp-logo-name {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.logo-circle {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
}

.logo-text h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
    color: #1e293b;
}

.logo-text span {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
}

.preview-document-tag {
    text-align: left;
}

.preview-document-tag h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.3rem;
    font-weight: 800;
    color: #3b82f6;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.preview-document-tag span {
    font-size: 0.85rem;
    color: #64748b;
}

.preview-section-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
    z-index: 2;
    position: relative;
}

.preview-sub-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
}

.preview-details-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.preview-detail-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.preview-detail-row.full-width-row {
    grid-column: span 2;
}

.preview-detail-row .lbl {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
}

.preview-detail-row strong {
    font-size: 0.95rem;
    color: #1e293b;
}

.preview-detail-row .address-txt {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 0.9rem;
    line-height: 1.5;
}

.preview-financial-box {
    z-index: 2;
    position: relative;
}

.preview-financial-summary {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.preview-fin-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.95rem;
    color: #475569;
}

.preview-fin-row.total-row {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e293b;
    align-items: center;
}

.preview-fin-row .total-price {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.preview-items-list-container {
    z-index: 2;
    position: relative;
}

.preview-items-table {
    width: 100%;
    border-collapse: collapse;
}

.preview-items-table th {
    background: #f1f5f9;
    padding: 0.75rem 1rem;
    text-align: right;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    border-bottom: 2px solid #cbd5e1;
}

.preview-items-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.preview-items-table .item-name-cell {
    display: flex;
    flex-direction: column;
}

.preview-items-table .sku-block {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

.preview-items-table .total-txt {
    font-weight: 700;
    color: #1e293b;
}

.preview-notes-box {
    z-index: 2;
    position: relative;
}

.step-3-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #e2e8f0;
    padding-top: 1.5rem;
}

.confirm-order-btn {
    height: 48px;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 10px;
    padding: 0 2rem;
    transition: all 0.3s ease;
}

.confirm-order-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
}

/* Responsive */
@media (max-width: 1400px) {
    .sales-order-layout {
        grid-template-columns: 1fr 350px;
    }
}

@media (max-width: 1200px) {
    .sales-order-layout {
        grid-template-columns: 1fr;
    }

    .sales-order-right-panel {
        position: static;
        max-height: none;
    }
    
    .preview-section-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-title h1 {
        font-size: 1.6rem;
    }

    .items-table, .preview-items-table {
        font-size: 0.9rem;
    }

    .items-table thead th, .preview-items-table th {
        padding: 0.6rem 0.8rem;
        font-size: 0.7rem;
    }

    .items-table tbody td, .preview-items-table td {
        padding: 0.8rem;
    }
}

@media (max-width: 768px) {
    .page-title h1 {
        font-size: 1.4rem;
    }

    .page-title p {
        font-size: 0.8rem;
    }
}

/* Header Actions */
.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.shortcuts-btn {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 1px solid #cbd5e1;
    color: #475569;
    font-weight: 600;
    transition: all 0.2s ease;
}

.shortcuts-btn:hover {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    transform: translateY(-1px);
}

/* Shortcuts Dialog */
.shortcuts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.shortcut-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.shortcut-key {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    font-weight: 700;
    color: #3b82f6;
    background: #eff6ff;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
    align-self: flex-start;
}

.shortcut-desc {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

/* Search Filters */
.search-filters {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.filter-select {
    width: 120px;
}

.filter-select .el-select__wrapper {
    border-radius: 6px;
    font-size: 0.85rem;
}

/* Quick Actions */
.header-right {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.quick-actions-dropdown {
    margin-right: 0.5rem;
}

/* Mobile Responsiveness Enhancements */
@media (max-width: 768px) {
    .search-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .search-filters {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-select {
        flex: 1;
        width: auto;
    }
    
    .header-actions {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
    }
    
    .header-actions .el-button {
        width: 100%;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .header-right {
        width: 100%;
        justify-content: space-between;
    }
    
    .items-table {
        font-size: 0.8rem;
    }
    
    .items-table thead th {
        padding: 0.5rem 0.3rem;
        font-size: 0.7rem;
    }
    
    .items-table tbody td {
        padding: 0.5rem 0.3rem;
    }
    
    .qty-control {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .unit-select {
        width: 100%;
    }
    
    .sales-order-layout {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .sales-order-right-panel {
        position: static;
        max-height: none;
    }
    
    .preview-section-grid {
        grid-template-columns: 1fr;
    }
    
    .preview-details-list {
        grid-template-columns: 1fr;
    }
    
    .step-3-nav-bar {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .step-3-nav-bar .el-button {
        width: 100%;
    }
}
</style>
