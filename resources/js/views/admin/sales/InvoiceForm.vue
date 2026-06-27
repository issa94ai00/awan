<template>
    <div class="invoice-form-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>
                    <el-icon><Document /></el-icon>
                    {{ isEdit ? t('edit_invoice') : t('create_invoice') }}
                </h1>
                <p>{{ t('prepare_invoice_with_products') }}</p>
            </div>
            <el-button @click="goBack" :icon="ArrowRight" class="back-btn">
                {{ t('back_to_invoices') }}
            </el-button>
        </div>

        <!-- Validation Errors -->
        <el-alert
            v-if="formErrors.length"
            :title="t('please_fix_errors')"
            type="error"
            :closable="true"
            show-icon
            class="mb-4"
        >
            <ul class="error-list">
                <li v-for="(err, idx) in formErrors" :key="idx">{{ err }}</li>
            </ul>
        </el-alert>

        <div class="invoice-layout">
            <!-- Left Panel: Product Search + Items -->
            <div class="invoice-left-panel">
                <!-- Product Search -->
                <div class="search-container">
                    <div class="search-header">
                        <el-icon><Search /></el-icon>
                        <span>{{ t('search_product') }}</span>
                    </div>
                    <div class="product-search-wrapper">
                        <el-input
                            v-model="searchQuery"
                            :placeholder="t('search_product_placeholder')"
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
                                <el-tooltip :content="t('scan_barcode')" placement="top">
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
                                    <span>{{ t('searching') }}...</span>
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
                                                    <span>{{ product.stock_quantity }} {{ product.unit || t('unit') }}</span>
                                                    <el-tag v-if="product.stock_quantity <= 5 && product.stock_quantity > 0" type="warning" size="small" round>{{ t('low_stock') }}</el-tag>
                                                    <el-tag v-if="product.stock_quantity === 0" type="danger" size="small" round>{{ t('out_of_stock') }}</el-tag>
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
                                        <h4>{{ t('no_products_found') }}</h4>
                                        <p>{{ t('try_different_keywords') }}</p>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Items Table -->
                <el-card shadow="hover" class="items-card">
                    <template #header>
                        <div class="card-header">
                            <div class="header-left">
                                <el-icon><ShoppingCart /></el-icon>
                                <span>{{ t('invoice_items') }}</span>
                            </div>
                            <el-tag type="primary" round>{{ items.length }} {{ t('items') }}</el-tag>
                        </div>
                    </template>

                    <div v-if="items.length === 0" class="empty-items">
                        <el-icon :size="48"><ShoppingCart /></el-icon>
                        <p>{{ t('no_items_added_yet') }}</p>
                        <p class="hint">{{ t('use_search_to_add_products') }}</p>
                    </div>

                    <div v-else class="items-table-wrapper">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>{{ t('product') }}</th>
                                    <th>{{ t('unit') }}</th>
                                    <th>{{ t('quantity') }}</th>
                                    <th>{{ t('unit_price') }}</th>
                                    <th>{{ t('total') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in items" :key="item.product_id">
                                    <td class="product-cell" data-label="{{ t('product') }}">
                                        <div class="product-name">{{ item.name }}</div>
                                        <div class="product-sku" v-if="item.sku">SKU: {{ item.sku }}</div>
                                    </td>
                                    <td class="unit-cell" data-label="{{ t('unit') }}">
                                        <el-select
                                            v-model="item.selectedUnit"
                                            :placeholder="t('select_unit')"
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
                                    <td class="qty-cell" data-label="{{ t('quantity') }}">
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
                                    <td class="price-cell" data-label="{{ t('unit_price') }}">
                                        <el-input-number
                                            v-model="item.price"
                                            :min="0"
                                            :precision="2"
                                            size="small"
                                            @change="updateTotals"
                                            controls-position="right"
                                        />
                                        <span class="currency">{{ t('currency') }}</span>
                                    </td>
                                    <td class="total-cell" data-label="{{ t('total') }}">
                                        {{ formatCurrency(item.price * item.quantity) }}
                                    </td>
                                    <td class="action-cell" data-label="">
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

            <!-- Right Panel: Summary -->
            <div class="invoice-right-panel">
                <!-- Customer Selection -->
                <el-card shadow="hover" class="customer-card">
                    <template #header>
                        <div class="card-header">
                            <el-icon><User /></el-icon>
                            <span>{{ t('customer_info') }}</span>
                        </div>
                    </template>

                    <el-select
                        v-model="form.customer_id"
                        :placeholder="t('select_customer_optional')"
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
                </el-card>

                <!-- Summary Card -->
                <el-card shadow="hover" class="summary-card">
                    <template #header>
                        <div class="card-header">
                            <el-icon><Wallet /></el-icon>
                            <span>{{ t('invoice_summary') }}</span>
                        </div>
                    </template>

                    <div class="summary-body">
                        <div class="summary-row">
                            <span class="label">{{ t('subtotal') }}</span>
                            <span class="value">{{ formatCurrency(subtotal) }}</span>
                        </div>

                        <div class="summary-inputs">
                            <div class="summary-input-row">
                                <label>{{ t('status') }}</label>
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
                                <label>{{ t('discount') }}</label>
                                <el-input-number
                                    v-model="form.discount"
                                    :min="0"
                                    :precision="2"
                                    size="small"
                                    @change="updateTotals"
                                />
                            </div>
                            <div class="summary-input-row">
                                <label>{{ t('tax') }}</label>
                                <el-input-number
                                    v-model="form.tax"
                                    :min="0"
                                    :precision="2"
                                    size="small"
                                    @change="updateTotals"
                                />
                            </div>
                            <div class="summary-input-row">
                                <label>{{ t('payment_method') }}</label>
                                <el-select v-model="form.payment_method" size="small">
                                    <el-option :label="t('cash')" value="cash" />
                                    <el-option :label="t('card')" value="card" />
                                    <el-option :label="t('transfer')" value="transfer" />
                                </el-select>
                            </div>
                        </div>

                        <el-divider />

                        <!-- Additional Expenses Section -->
                        <div class="expenses-section">
                            <div class="expenses-header">
                                <span>{{ t('additional_expenses') }}</span>
                                <el-button type="primary" size="small" @click="addExpense">
                                    <el-icon><Plus /></el-icon>
                                    {{ t('add_expense') }}
                                </el-button>
                            </div>
                            <div v-if="form.expenses.length > 0" class="expenses-list">
                                <div v-for="(expense, index) in form.expenses" :key="index" class="expense-item">
                                    <el-input
                                        v-model="expense.description"
                                        :placeholder="t('description')"
                                        size="small"
                                        class="expense-description"
                                    />
                                    <el-select v-model="expense.category" size="small" class="expense-category">
                                        <el-option value="shipping" :label="t('shipping')" />
                                        <el-option value="packaging" :label="t('packaging')" />
                                        <el-option value="handling" :label="t('handling')" />
                                        <el-option value="other" :label="t('other')" />
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

                        <el-divider />

                        <div class="summary-row discount">
                            <span class="label">{{ t('discount') }}</span>
                            <span class="value negative">-{{ formatCurrency(form.discount) }}</span>
                        </div>
                        <div class="summary-row tax">
                            <span class="label">{{ t('tax') }}</span>
                            <span class="value positive">+{{ formatCurrency(form.tax) }}</span>
                        </div>
                        <div class="summary-row expenses" v-if="totalExpenses > 0">
                            <span class="label">{{ t('additional_expenses') }}</span>
                            <span class="value positive">+{{ formatCurrency(totalExpenses) }}</span>
                        </div>

                        <el-divider />

                        <div class="summary-row total">
                            <span class="label">{{ t('total') }}</span>
                            <span class="value total-value">{{ formatCurrency(total) }}</span>
                        </div>
                    </div>

                    <div class="submit-section">
                        <el-button
                            type="primary"
                            size="large"
                            :loading="submitting"
                            :disabled="items.length === 0"
                            @click="submitInvoice"
                            class="submit-btn"
                        >
                            <el-icon><Check /></el-icon>
                            {{ isEdit ? t('update_invoice') : t('create_invoice') }}
                        </el-button>
                    </div>
                </el-card>

                <!-- Notes -->
                <el-card shadow="hover" class="notes-card">
                    <template #header>
                        <div class="card-header">
                            <el-icon><Notebook /></el-icon>
                            <span>{{ t('notes') }}</span>
                        </div>
                    </template>
                    <el-input
                        v-model="form.notes"
                        type="textarea"
                        :rows="3"
                        :placeholder="t('add_notes_placeholder')"
                    />
                </el-card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useInvoicesStore } from '@/stores/invoices';
import { useCustomersStore } from '@/stores/customers';
import { posApi } from '@/api/pos';
import { ElMessage } from 'element-plus';
import {
    Document, Search, ShoppingCart, User, Wallet, Notebook,
    ArrowRight, Plus, Minus, Delete, Check, Loading,
    Ticket, Box, WarningFilled
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const invoicesStore = useInvoicesStore();
const customersStore = useCustomersStore();

const isEdit = computed(() => !!route.params.id);
const searchInputRef = ref(null);

// Form data
const form = reactive({
    customer_id: null,
    payment_method: 'cash',
    discount: 0,
    tax: 0,
    notes: '',
    status: 'pending',
    items: [],
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
let searchTimeout = null;

// Customers
const customers = ref([]);

// Computed
const subtotal = computed(() => {
    return items.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const total = computed(() => {
    return Math.max(0, subtotal.value - (form.discount || 0) + (form.tax || 0) + totalExpenses.value);
});

// Available status transitions based on current status
const availableStatuses = computed(() => {
    const currentStatus = form.status;
    const transitions = statusTransitions[currentStatus] || [];
    
    // Always include current status
    return [currentStatus, ...transitions];
});

// Methods
const formatCurrency = (value) => {
    return new Intl.NumberFormat('ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0) + ' ' + t('currency');
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
            const data = res.data?.data || res.data || [];
            searchResults.value = Array.isArray(data) ? data : [];
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
        // Default unit info
        const defaultUnit = {
            id: null,
            name: product.unit || t('piece'),
            name_ar: product.unit || t('piece'),
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
            // Unit selection
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            base_price: parseFloat(product.price) || 0
        });

        // Load product units
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

            // Find the default unit or use the first one
            const defaultUnit = units.find(u => u.is_default) || units[0];

            if (items.value[itemIndex]) {
                items.value[itemIndex].units = units;
                items.value[itemIndex].selectedUnit = defaultUnit;
                // Update price based on unit multiplier
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
        // Update price based on unit multiplier
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
    // Trigger reactivity
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

// Expense management
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

const totalExpenses = computed(() => {
    return form.expenses.reduce((sum, expense) => sum + (expense.amount || 0), 0);
});

// Keyboard shortcuts
const handleKeyboardShortcuts = (e) => {
    // Ctrl/Cmd + B: Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
    // Ctrl/Cmd + Enter: Submit invoice
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (items.value.length > 0) {
            submitInvoice();
        }
    }
    // Escape: Close search dropdown
    if (e.key === 'Escape') {
        showResults.value = false;
    }
    // Ctrl/Cmd + N: New invoice (clear form)
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (items.value.length > 0 && confirm(t('confirm_clear_invoice'))) {
            items.value = [];
            form.customer_id = null;
            form.discount = 0;
            form.tax = 0;
            form.notes = '';
            updateTotals();
        }
    }
};

const submitInvoice = async () => {
    formErrors.value = [];

    if (items.value.length === 0) {
        formErrors.value.push(t('add_at_least_one_item'));
        return;
    }

    submitting.value = true;

    try {
        const payload = {
            customer_id: form.customer_id,
            payment_method: form.payment_method,
            discount: form.discount || 0,
            tax: form.tax || 0,
            notes: form.notes,
            status: form.status,
            items: items.value.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.price,
                product_unit_id: item.selectedUnit?.id || null
            })),
            expenses: form.expenses.filter(exp => exp.description && exp.amount > 0)
        };

        if (isEdit.value) {
            await invoicesStore.updateInvoice(route.params.id, payload);
            ElMessage.success(t('invoice_updated_successfully'));
        } else {
            await invoicesStore.createInvoice(payload);
            ElMessage.success(t('invoice_created_successfully'));
        }

        router.push('/admin/sales/invoices');
    } catch (error) {
        console.error('Submit error:', error);
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            formErrors.value = Object.values(errors).flat();
        } else {
            formErrors.value = [error.message || t('failed_to_save_invoice')];
        }
    } finally {
        submitting.value = false;
    }
};

const goBack = () => {
    router.push('/admin/sales/invoices');
};

// Close dropdown on outside click
const handleClickOutside = (e) => {
    const searchWrapper = document.querySelector('.product-search-wrapper');
    const dropdown = document.querySelector('.search-dropdown');
    if (searchWrapper && !searchWrapper.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
        showResults.value = false;
    }
};

// Calculate dropdown position
const updateDropdownPosition = () => {
    const searchInput = searchInputRef.value?.$el;
    const dropdown = document.querySelector('.search-dropdown');
    if (searchInput && dropdown && showResults.value) {
        const rect = searchInput.getBoundingClientRect();
        dropdown.style.top = `${rect.bottom + 8}px`;
    }
};

// Load data
onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyboardShortcuts);
    window.addEventListener('resize', updateDropdownPosition);
    window.addEventListener('scroll', updateDropdownPosition);

    // Load customers
    try {
        await customersStore.fetchCustomers();
        customers.value = customersStore.customers;
    } catch (error) {
        console.error('Failed to load customers:', error);
    }

    // Load invoice for edit mode
    if (isEdit.value) {
        try {
            const invoice = await invoicesStore.fetchInvoice(route.params.id);
            if (invoice) {
                form.customer_id = invoice.customer_id;
                form.payment_method = invoice.payment_method || 'cash';
                form.discount = parseFloat(invoice.discount) || 0;
                form.tax = parseFloat(invoice.tax) || 0;
                form.notes = invoice.notes || '';

                if (invoice.items) {
                    items.value = invoice.items.map(item => ({
                        product_id: item.product_id,
                        name: item.product_name || item.product?.name_ar,
                        sku: item.product?.sku || '',
                        price: parseFloat(item.unit_price) || 0,
                        quantity: item.quantity || 1,
                        stock: item.product?.stock_quantity || 0,
                        unit: item.product?.unit || ''
                    }));
                }
            }
        } catch (error) {
            console.error('Failed to load invoice:', error);
            ElMessage.error(t('failed_to_load_invoice'));
        }
    }

    // Auto-focus search input on mount
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
.invoice-form-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
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
.invoice-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    align-items: start;
}

.invoice-left-panel,
.invoice-right-panel {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.invoice-right-panel {
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

/* Improved focus states */
.el-input:focus-within,
.el-select:focus-within,
.el-input-number:focus-within {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    border-radius: 6px;
}

/* Dropdown Transition */
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

.submit-section {
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
    margin-top: 1rem;
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

/* Responsive */
@media (max-width: 1400px) {
    .invoice-layout {
        grid-template-columns: 1fr 350px;
    }
}

@media (max-width: 1200px) {
    .invoice-layout {
        grid-template-columns: 1fr;
    }

    .invoice-right-panel {
        position: static;
        max-height: none;
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

    .items-table {
        font-size: 0.9rem;
    }

    .items-table thead th {
        padding: 0.6rem 0.8rem;
        font-size: 0.7rem;
    }

    .items-table tbody td {
        padding: 0.8rem;
    }
}

@media (max-width: 768px) {
    .page-title h1 {
        font-size: 1.4rem;
    }

    .page-title p {
        font-size: 0.9rem;
    }

    .search-dropdown {
        width: 95vw;
        max-width: 95vw;
        left: 50%;
        transform: translateX(-50%);
    }

    .items-table thead {
        display: none;
    }

    .items-table tbody tr {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
    }

    .items-table tbody td {
        padding: 0;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    .items-table tbody td::before {
        content: attr(data-label);
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-right: 0.5rem;
    }

    .product-cell {
        flex-direction: column;
        align-items: flex-start;
    }

    .product-cell::before {
        display: none;
    }

    .unit-cell,
    .qty-cell,
    .price-cell {
        flex-direction: column;
        align-items: flex-start;
    }

    .unit-select,
    .qty-control,
    .price-cell .el-input-number {
        width: 100%;
    }

    .action-cell {
        justify-content: flex-end;
    }

    .summary-row {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
    }

    .summary-row.total {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .summary-input-row {
        flex-direction: column;
        gap: 0.5rem;
        align-items: stretch;
    }

    .summary-input-row label {
        min-width: auto;
        text-align: right;
    }

    .summary-input-row .el-input-number,
    .summary-input-row .el-select {
        width: 100%;
    }

    .search-dropdown {
        max-height: 250px;
    }

    .submit-btn {
        height: 52px;
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .invoice-form-page {
        padding: 0.5rem;
    }

    .page-header {
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .page-title h1 {
        font-size: 1.2rem;
    }

    .back-btn {
        width: 100%;
    }

    .card-header {
        font-size: 0.9rem;
    }

    .items-table tbody tr {
        padding: 0.75rem;
        gap: 0.5rem;
    }

    .product-name {
        font-size: 0.9rem;
    }

    .product-sku {
        font-size: 0.7rem;
    }

    .unit-barcode {
        font-size: 0.65rem;
    }

    .summary-row .label {
        font-size: 0.85rem;
    }

    .summary-row.total .total-value {
        font-size: 1.3rem;
    }

    .submit-btn {
        height: 48px;
        font-size: 1rem;
    }

    .search-result-item {
        padding: 0.75rem;
    }

    .product-name {
        font-size: 0.85rem;
    }

    .product-meta {
        font-size: 0.75rem;
        gap: 0.5rem;
    }

    .product-price {
        font-size: 0.9rem;
    }
}

/* Expenses Section Styling */
.expenses-section {
    margin-top: 1rem;
}

.expenses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: #253358;
}

.expenses-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.expense-item {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.expense-description {
    flex: 2;
}

.expense-category {
    flex: 1;
}

.expense-amount {
    flex: 1;
}

.summary-row.expenses {
    font-size: 0.9rem;
    color: #5f6d85;
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
    .search-result-item,
    .items-table tbody tr {
        min-height: 48px;
    }

    .el-button {
        min-height: 44px;
        min-width: 44px;
    }

    .el-input-number {
        min-height: 44px;
    }

    .el-select {
        min-height: 44px;
    }

    .expense-item {
        flex-wrap: wrap;
    }

    .expense-description,
    .expense-category,
    .expense-amount {
        flex: 1 1 100%;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    .dropdown-enter-active,
    .dropdown-leave-active {
        transition: none;
    }

    .search-result-item,
    .items-table tbody tr {
        transition: none;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .page-title h1 {
        color: #e5e7eb;
    }

    .page-title p {
        color: #9ca3af;
    }

    .search-container {
        background: #1f2937;
        border-color: #374151;
    }

    .search-container:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    }

    .search-dropdown {
        background: #1f2937;
        border-color: #374151;
    }

    .search-result-item {
        border-bottom-color: #374151;
    }

    .search-result-item:hover,
    .search-result-item.highlighted {
        background: #374151;
    }

    .search-result-item.low-stock {
        background: #451a03;
        border-left-color: #f59e0b;
    }

    .search-result-item.low-stock:hover,
    .search-result-item.low-stock.highlighted {
        background: #78350f;
    }

    .product-image {
        background: #374151;
    }

    .product-image .el-icon {
        color: #6b7280;
    }

    .product-name {
        color: #f3f4f6;
    }

    .product-sku {
        color: #9ca3af;
    }

    .product-meta {
        color: #9ca3af;
    }

    .product-price {
        color: #60a5fa;
    }

    .no-results-content {
        color: #9ca3af;
    }

    .no-results-content .el-icon {
        color: #4b5563;
    }

    .no-results-content h4 {
        color: #9ca3af;
    }

    .no-results-content p {
        color: #6b7280;
    }

    .items-table thead th {
        background: #1f2937;
        color: #9ca3af;
        border-bottom-color: #374151;
    }

    .items-table tbody td {
        border-bottom-color: #374151;
    }

    .items-table tbody tr:hover {
        background: #1f2937;
    }

    .summary-row .label {
        color: #9ca3af;
    }

    .summary-row .value {
        color: #e5e7eb;
    }
}
</style>
