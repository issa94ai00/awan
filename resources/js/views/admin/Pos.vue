<template>
    <div class="pos-workspace" @keydown="handleGlobalKeydown">
        <!-- ── Top Bar ─────────────────────────────────────────── -->
        <header class="pos-topbar">
            <div class="pos-brand">
                <i class="fas fa-cash-register text-primary"></i>
                <div>
                    <h1 class="pos-title">{{ $t('point_of_sale') || 'نقطة البيع (POS)' }}</h1>
                    <span class="pos-subtitle">{{ $t('pos_subtitle') || 'إصدار فواتير نقدية وسريعة مع خصومات وطباعة فورية' }}</span>
                </div>
            </div>

            <div class="pos-topbar-controls">
                <div class="control-group">
                    <span class="control-label"><i class="fas fa-warehouse mr-1"></i> {{ $t('warehouse') || 'المستودع' }}</span>
                    <el-select
                        v-model="selectedWarehouseId"
                        size="small"
                        class="warehouse-select"
                        :placeholder="$t('choose_warehouse')"
                    >
                        <el-option
                            v-for="wh in warehouses"
                            :key="wh.id"
                            :label="wh.name"
                            :value="wh.id"
                        />
                    </el-select>
                </div>

                <div class="shortcuts-hint">
                    <span class="kbd-pill"><kbd>F2</kbd> {{ $t('search') || 'بحث' }}</span>
                    <span class="kbd-pill"><kbd>F9</kbd> {{ $t('checkout') || 'دفع' }}</span>
                </div>

                <el-button
                    type="primary"
                    plain
                    size="small"
                    :icon="Refresh"
                    :loading="loadingProducts"
                    @click="fetchProducts"
                >
                    {{ $t('refresh') }}
                </el-button>
            </div>
        </header>

        <!-- ── Main Split View ─────────────────────────────────── -->
        <div class="pos-main">
            <!-- ── Left: Catalog & Search ───────────────────────── -->
            <section class="pos-catalog">
                <!-- Search & Barcode Bar -->
                <div class="catalog-search-bar">
                    <el-input
                        ref="barcodeInputRef"
                        v-model="searchQuery"
                        class="barcode-input"
                        size="large"
                        clearable
                        :placeholder="$t('search_barcode_or_product') || 'امسح الباركود أو ابحث بالاسم / SKU (F2)...'"
                        :prefix-icon="Search"
                        @input="onSearchInput"
                        @keydown.enter="handleBarcodeEnter"
                    >
                        <template #suffix>
                            <i class="fas fa-barcode barcode-icon" title="Barcode Scanner Ready"></i>
                        </template>
                    </el-input>
                </div>

                <!-- Category Filter Pills -->
                <div class="category-pills">
                    <button
                        type="button"
                        class="cat-pill"
                        :class="{ 'is-active': selectedCategoryId === null }"
                        @click="selectCategory(null)"
                    >
                        <i class="fas fa-th-large"></i>
                        <span>{{ $t('all_categories') || 'الكل' }}</span>
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        class="cat-pill"
                        :class="{ 'is-active': selectedCategoryId === cat.id }"
                        @click="selectCategory(cat.id)"
                    >
                        <span>{{ cat.name_ar || cat.name }}</span>
                    </button>
                </div>

                <!-- Products Grid -->
                <div v-loading="loadingProducts" class="products-grid-scroll">
                    <div v-if="products.length" class="products-grid">
                        <div
                            v-for="prod in products"
                            :key="productKey(prod)"
                            class="product-card"
                            :class="{ 'out-of-stock': isOutOfStock(prod) }"
                            @click="addToCart(prod)"
                        >
                            <div class="card-thumb">
                                <EntityImage
                                    :src="productImageSrc(prod)"
                                    type="product"
                                    :size="72"
                                    shape="square"
                                    class="thumb-img"
                                />
                                <span
                                    class="stock-badge"
                                    :class="stockBadgeClass(prod)"
                                >
                                    {{ stockText(prod) }}
                                </span>
                            </div>

                            <div class="card-info">
                                <h3 class="card-name" :title="prod.name_ar || prod.name">
                                    {{ prod.name_ar || prod.name }}
                                </h3>
                                <span v-if="prod.sku" class="card-sku" dir="ltr">{{ prod.sku }}</span>
                                <div class="card-price-row">
                                    <strong class="card-price">{{ formatMoney(prod.price) }}</strong>
                                    <span v-if="prod.variant_label" class="card-variant-pill">
                                        {{ prod.variant_label }}
                                    </span>
                                </div>
                            </div>

                            <button type="button" class="card-add-btn" :title="$t('add_to_cart') || 'إضافة للسلة'">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="!loadingProducts" class="empty-catalog">
                        <i class="fas fa-box-open empty-icon"></i>
                        <p>{{ $t('no_products_found') || 'لم يتم العثور على منتجات مطابقة' }}</p>
                        <el-button v-if="searchQuery || selectedCategoryId" size="small" @click="clearFilters">
                            {{ $t('cat_admin_clear_filters') || 'إلغاء التصفية' }}
                        </el-button>
                    </div>
                </div>
            </section>

            <!-- ── Right: Register & Cart ───────────────────────── -->
            <aside class="pos-register">
                <!-- Customer Picker Row -->
                <div class="register-header">
                    <div class="customer-picker">
                        <div class="customer-label">
                            <i class="fas fa-user mr-1"></i>
                            <span>{{ $t('client') || 'العميل' }}:</span>
                        </div>
                        <el-select
                            v-model="selectedCustomerId"
                            filterable
                            remote
                            clearable
                            :remote-method="searchCustomers"
                            :loading="loadingCustomers"
                            :placeholder="$t('walk_in_customer') || 'عميل نقدي / عام'"
                            class="customer-select"
                            @change="onCustomerChange"
                            @focus="customerOptions.length <= 1 && searchCustomers('')"
                        >
                            <el-option
                                v-for="c in customerOptions"
                                :key="c.id"
                                :label="c.name"
                                :value="c.id"
                            >
                                <div class="customer-opt">
                                    <span class="customer-name">{{ c.name }}</span>
                                    <small v-if="c.phone" dir="ltr">{{ c.phone }}</small>
                                </div>
                            </el-option>
                        </el-select>
                    </div>

                    <div v-if="selectedCustomer && Number(selectedCustomer.balance) !== 0" class="customer-balance-badge" :class="Number(selectedCustomer.balance) > 0 ? 'is-due' : 'is-credit'">
                        <span>{{ $t('balance') }}:</span>
                        <strong>{{ formatMoney(selectedCustomer.balance) }}</strong>
                    </div>
                </div>

                <!-- Cart Items Table / List -->
                <div class="cart-scroll">
                    <div v-if="cart.length" class="cart-items">
                        <div
                            v-for="(item, idx) in cart"
                            :key="item.key"
                            class="cart-row"
                        >
                            <div class="item-visual">
                                <EntityImage
                                    :src="item.image"
                                    type="product"
                                    :size="40"
                                    shape="square"
                                    class="item-thumb"
                                />
                            </div>

                            <div class="item-meta">
                                <h4 class="item-name">{{ item.name }}</h4>
                                <div class="item-subline">
                                    <span v-if="item.variant_label" class="item-variant">{{ item.variant_label }}</span>
                                    <span v-if="item.sku" class="item-sku" dir="ltr">{{ item.sku }}</span>
                                </div>
                                <div class="item-unit-price">
                                    <span class="price-val">{{ formatMoney(item.unit_price) }}</span>
                                </div>
                            </div>

                            <!-- Qty Stepper -->
                            <div class="item-stepper">
                                <button type="button" class="step-btn" @click="decrementQty(item)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    class="step-input"
                                    @change="normalizeQty(item)"
                                />
                                <button type="button" class="step-btn" @click="incrementQty(item)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <!-- Row Total & Remove -->
                            <div class="item-total-col">
                                <strong class="row-total">{{ formatMoney(item.quantity * item.unit_price) }}</strong>
                                <button type="button" class="remove-btn" @click="removeFromCart(idx)" :title="$t('delete')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-cart">
                        <i class="fas fa-shopping-basket empty-cart-icon"></i>
                        <p class="empty-cart-title">{{ $t('cart_is_empty') || 'السلة فارغة' }}</p>
                        <p class="empty-cart-hint">{{ $t('cart_empty_hint') || 'اختر أصنافاً من الكتالوج أو امسح الباركود للبدء' }}</p>
                    </div>
                </div>

                <!-- Register Footer & Checkout -->
                <div class="register-footer">
                    <!-- Totals Breakdown -->
                    <div class="totals-breakdown">
                        <div class="breakdown-row">
                            <span>{{ $t('subtotal') || 'المجموع الفرعي' }}</span>
                            <strong>{{ formatMoney(cartSubtotal) }}</strong>
                        </div>

                        <!-- Modern Discount Block with Default Percentage -->
                        <div class="discount-block">
                            <div class="discount-header">
                                <span class="discount-title">
                                    <i class="fas fa-percent mr-1"></i>
                                    {{ $t('discount') || 'الخصم' }}
                                </span>
                                <div class="discount-mode-toggle">
                                    <button
                                        type="button"
                                        class="mode-btn"
                                        :class="{ 'is-active': discountType === 'percent' }"
                                        @click="setDiscountType('percent')"
                                    >
                                        % {{ $t('percentage') || 'نسبة' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="mode-btn"
                                        :class="{ 'is-active': discountType === 'amount' }"
                                        @click="setDiscountType('amount')"
                                    >
                                        {{ baseCode }} {{ $t('fixed_amount') || 'مبلغ' }}
                                    </button>
                                </div>
                            </div>

                            <div class="discount-input-row">
                                <el-input-number
                                    v-if="discountType === 'percent'"
                                    v-model="discountPercent"
                                    :min="0"
                                    :max="100"
                                    :precision="1"
                                    :step="5"
                                    controls-position="right"
                                    class="discount-input"
                                />
                                <el-input-number
                                    v-else
                                    v-model="discountAmount"
                                    :min="0"
                                    :max="cartSubtotal"
                                    :precision="2"
                                    :step="10"
                                    controls-position="right"
                                    class="discount-input"
                                />

                                <span v-if="effectiveDiscountValue > 0" class="discount-preview-val">
                                    -{{ formatMoney(effectiveDiscountValue) }}
                                </span>
                            </div>

                            <!-- Quick Preset Chips -->
                            <div class="discount-chips">
                                <button
                                    v-for="p in [0, 5, 10, 15, 20]"
                                    :key="p"
                                    type="button"
                                    class="chip-btn"
                                    :class="{ 'is-selected': discountType === 'percent' && Number(discountPercent) === p }"
                                    @click="applyDiscountPreset(p)"
                                >
                                    {{ p === 0 ? ($t('no_discount') || 'بدون خصم') : `${p}%` }}
                                </button>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="grand-total-row">
                            <span class="grand-label">{{ $t('grand_total') || 'الإجمالي النهائي' }}</span>
                            <span class="grand-value">{{ formatMoney(grandTotal) }}</span>
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="payment-method-tabs">
                        <button
                            type="button"
                            class="pay-tab"
                            :class="{ 'is-active': paymentMethod === 'cash' }"
                            @click="paymentMethod = 'cash'"
                        >
                            <i class="fas fa-money-bill-wave"></i>
                            <span>{{ $t('cash') || 'نقداً' }}</span>
                        </button>
                        <button
                            type="button"
                            class="pay-tab"
                            :class="{ 'is-active': paymentMethod === 'card' }"
                            @click="paymentMethod = 'card'"
                        >
                            <i class="fas fa-credit-card"></i>
                            <span>{{ $t('card') || 'شبكة / بطاقة' }}</span>
                        </button>
                        <button
                            type="button"
                            class="pay-tab"
                            :class="{ 'is-active': paymentMethod === 'bank_transfer' }"
                            @click="paymentMethod = 'bank_transfer'"
                        >
                            <i class="fas fa-university"></i>
                            <span>{{ $t('bank_transfer') || 'تحويل' }}</span>
                        </button>
                        <button
                            type="button"
                            class="pay-tab"
                            :class="{ 'is-active': paymentMethod === 'check' }"
                            @click="paymentMethod = 'check'"
                        >
                            <i class="fas fa-user-clock"></i>
                            <span>{{ $t('on_account') || 'آجل' }}</span>
                        </button>
                    </div>

                    <!-- Cash Calculation (When Cash Selected) -->
                    <div v-if="paymentMethod === 'cash' && grandTotal > 0" class="tendered-cash-box">
                        <div class="tendered-input-row">
                            <span class="tendered-lbl">{{ $t('amount_tendered') || 'المدفوع من العميل' }}:</span>
                            <el-input-number
                                v-model="tenderedAmount"
                                :min="0"
                                :precision="2"
                                :step="50"
                                controls-position="right"
                                class="tendered-input"
                            />
                        </div>

                        <!-- Quick Denomination Buttons -->
                        <div class="cash-quick-chips">
                            <button
                                type="button"
                                class="cash-chip"
                                @click="tenderedAmount = grandTotal"
                            >
                                {{ $t('exact_amount') || 'بالتمام' }} ({{ formatMoney(grandTotal) }})
                            </button>
                            <button
                                v-for="bill in quickBills"
                                :key="bill"
                                type="button"
                                class="cash-chip"
                                @click="tenderedAmount = bill"
                            >
                                {{ bill }} {{ baseCode }}
                            </button>
                        </div>

                        <!-- Change Due -->
                        <div v-if="changeDue > 0" class="change-due-row">
                            <span>{{ $t('change_due') || 'المتبقي للعميل' }}:</span>
                            <strong class="change-val">{{ formatMoney(changeDue) }}</strong>
                        </div>
                    </div>

                    <!-- Checkout Action Button -->
                    <div class="checkout-actions">
                        <el-button
                            size="large"
                            :disabled="!cart.length || isSubmitting"
                            @click="clearCart"
                        >
                            <i class="fas fa-trash-alt mr-1"></i> {{ $t('clear') || 'إلغاء' }}
                        </el-button>

                        <el-button
                            type="primary"
                            size="large"
                            class="btn-checkout"
                            :disabled="!canCheckout"
                            :loading="isSubmitting"
                            @click="submitCheckout"
                        >
                            <i class="fas fa-check-circle mr-1"></i>
                            <span>{{ $t('complete_sale') || 'إتمام البيع (F9)' }}</span>
                            <strong class="btn-checkout-total">{{ formatMoney(grandTotal) }}</strong>
                        </el-button>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ── Receipt & Success Modal ─────────────────────────── -->
        <el-dialog
            v-model="successModalVisible"
            :title="$t('sale_completed_successfully') || 'تم إتمام البيع بنجاح'"
            width="480px"
            append-to-body
            destroy-on-close
            class="pos-success-dialog"
        >
            <div v-if="lastInvoice" class="receipt-preview-content">
                <div class="receipt-success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <h3 class="receipt-inv-number" dir="ltr">{{ lastInvoice.invoice_number }}</h3>
                <p class="receipt-date">{{ formatDate(lastInvoice.created_at) }}</p>

                <div class="receipt-data-summary">
                    <div class="summary-line">
                        <span>{{ $t('total') }}:</span>
                        <strong>{{ formatMoney(lastInvoice.total) }}</strong>
                    </div>
                    <div class="summary-line">
                        <span>{{ $t('paid_amount') }}:</span>
                        <strong class="text-success">{{ formatMoney(lastInvoice.paid_amount) }}</strong>
                    </div>
                    <div v-if="lastChangeDue > 0" class="summary-line">
                        <span>{{ $t('change_due') || 'المتبقي للعميل' }}:</span>
                        <strong class="text-primary">{{ formatMoney(lastChangeDue) }}</strong>
                    </div>
                </div>

                <div class="receipt-dialog-actions">
                    <el-button
                        type="primary"
                        size="large"
                        :icon="Printer"
                        @click="printReceipt(lastInvoice)"
                    >
                        {{ $t('print_receipt') || 'طباعة الإيصال' }}
                    </el-button>
                    <el-button
                        size="large"
                        @click="startNewSale"
                    >
                        {{ $t('new_sale') || 'فاتورة جديدة' }}
                    </el-button>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Printer, Refresh, Search } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api/index';
import { posApi } from '@/api/pos';
import { categoriesApi } from '@/api/categories';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { resolveImageUrl } from '@/utils/productImages';
import { formatDate } from '@/utils/sales';
import EntityImage from '@/components/admin/EntityImage.vue';

const { t } = useI18n();
const baseCode = computed(() => baseCurrencyCode());

// ── State ────────────────────────────────────────────────────────
const barcodeInputRef = ref(null);
const searchQuery = ref('');
const selectedCategoryId = ref(null);
const categories = ref([]);
const products = ref([]);
const loadingProducts = ref(false);

const warehouses = ref([]);
const selectedWarehouseId = ref(null);

const customerOptions = ref([]);
const selectedCustomerId = ref(null);
const selectedCustomer = ref(null);
const loadingCustomers = ref(false);

const cart = ref([]);
const discountType = ref('percent'); // 'percent' | 'amount'
const discountPercent = ref(0);
const discountAmount = ref(0);
const paymentMethod = ref('cash');
const tenderedAmount = ref(0);
const isSubmitting = ref(false);

const successModalVisible = ref(false);
const lastInvoice = ref(null);
const lastChangeDue = ref(0);

let searchDebounceTimer = null;

// ── Methods: Catalog & Search ────────────────────────────────────
const fetchCategories = async () => {
    try {
        const res = await categoriesApi.getAll({ per_page: 100 });
        const data = res.data?.data || res.data || [];
        categories.value = Array.isArray(data) ? data : [];
    } catch {
        categories.value = [];
    }
};

const fetchWarehouses = async () => {
    try {
        const res = await api.get('/admin/warehouses');
        const data = res.data?.data || res.data || [];
        warehouses.value = Array.isArray(data) ? data : [];
        if (!selectedWarehouseId.value && warehouses.value.length) {
            selectedWarehouseId.value = warehouses.value[0].id;
        }
    } catch {
        warehouses.value = [];
    }
};

const fetchProducts = async () => {
    loadingProducts.value = true;
    try {
        const params = {
            per_page: 60,
            expand_variants: 1,
        };
        if (searchQuery.value.trim()) {
            params.q = searchQuery.value.trim();
        }
        if (selectedCategoryId.value) {
            params.category_id = selectedCategoryId.value;
        }

        const res = await posApi.productLookup(params);
        const data = res.data?.data || res.data || [];
        products.value = Array.isArray(data) ? data : [];
    } catch {
        products.value = [];
    } finally {
        loadingProducts.value = false;
    }
};

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(fetchProducts, 280);
};

const handleBarcodeEnter = () => {
    if (!searchQuery.value.trim()) return;
    // If only one product returned or first exact match, add it immediately
    if (products.value.length >= 1) {
        addToCart(products.value[0]);
        searchQuery.value = '';
        fetchProducts();
    }
};

const selectCategory = (catId) => {
    selectedCategoryId.value = catId;
    fetchProducts();
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedCategoryId.value = null;
    fetchProducts();
};

const productKey = (p) => `${p.id}-${p.variant_id || 'main'}`;

const productImageSrc = (p) => {
    if (!p) return '';
    const raw = p.image_main || p.image || p.primary_image_url || p.image_url || '';
    return resolveImageUrl(raw);
};

const isOutOfStock = (p) => {
    if (p.stock_quantity === null || p.stock_quantity === undefined) return false;
    return Number(p.stock_quantity) <= 0;
};

const stockBadgeClass = (p) => {
    const qty = Number(p.stock_quantity);
    if (qty <= 0) return 'stock-none';
    if (qty <= 5) return 'stock-low';
    return 'stock-ok';
};

const stockText = (p) => {
    const qty = Number(p.stock_quantity);
    if (qty <= 0) return t('out_of_stock') || 'نفد';
    return `${qty} ${p.unit || ''}`.trim();
};

// ── Customer Search ──────────────────────────────────────────────
const searchCustomers = async (q = '') => {
    loadingCustomers.value = true;
    try {
        const res = await posApi.customers({ search: q, per_page: 30 });
        const list = res.data?.data?.customers || res.data?.data || [];
        customerOptions.value = Array.isArray(list) ? list : [];
    } catch {
        customerOptions.value = [];
    } finally {
        loadingCustomers.value = false;
    }
};

const onCustomerChange = (cid) => {
    selectedCustomer.value = customerOptions.value.find((c) => c.id === cid) || null;
};

// ── Cart Operations ──────────────────────────────────────────────
const addToCart = (prod) => {
    const key = productKey(prod);
    const existing = cart.value.find((item) => item.key === key);

    if (existing) {
        existing.quantity += 1;
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.quantity }) || `${existing.name}: ${existing.quantity}`);
    } else {
        cart.value.unshift({
            key,
            product_id: prod.id,
            product_variant_id: prod.variant_id || null,
            name: prod.name_ar || prod.name,
            sku: prod.sku || '',
            variant_label: prod.variant_label || '',
            image: productImageSrc(prod),
            unit_price: Number(prod.price) || 0,
            quantity: 1,
            unit: prod.unit || t('piece'),
        });
        ElMessage.success(t('so_added', { name: prod.name_ar || prod.name }) || `تمت إضافة ${prod.name_ar || prod.name}`);
    }

    // Default tendered cash to the new grand total
    nextTick(() => {
        if (paymentMethod.value === 'cash') {
            tenderedAmount.value = grandTotal.value;
        }
    });
};

const incrementQty = (item) => {
    item.quantity += 1;
};

const decrementQty = (item) => {
    if (item.quantity > 1) {
        item.quantity -= 1;
    }
};

const normalizeQty = (item) => {
    if (!item.quantity || item.quantity < 1) item.quantity = 1;
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    cart.value = [];
    discountPercent.value = 0;
    discountAmount.value = 0;
    tenderedAmount.value = 0;
};

// ── Totals & Calculations ────────────────────────────────────────
const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.unit_price)), 0);
});

const effectiveDiscountValue = computed(() => {
    const sub = cartSubtotal.value;
    if (sub <= 0) return 0;
    if (discountType.value === 'percent') {
        const rate = Math.min(100, Math.max(0, Number(discountPercent.value) || 0));
        return Math.round(sub * rate) / 100;
    }
    const fixed = Math.max(0, Number(discountAmount.value) || 0);
    return Math.min(sub, fixed);
});

const grandTotal = computed(() => {
    return Math.max(0, cartSubtotal.value - effectiveDiscountValue.value);
});

const changeDue = computed(() => {
    if (paymentMethod.value !== 'cash') return 0;
    const paid = Number(tenderedAmount.value) || 0;
    return Math.max(0, Math.round((paid - grandTotal.value) * 100) / 100);
});

const quickBills = computed(() => {
    const total = grandTotal.value;
    if (total <= 0) return [100, 200, 500];
    const next100 = Math.ceil(total / 100) * 100;
    const next200 = Math.ceil(total / 200) * 200;
    const next500 = Math.ceil(total / 500) * 500;
    return [...new Set([next100, next200, next500])].filter((b) => b > total).slice(0, 3);
});

const setDiscountType = (type) => {
    discountType.value = type;
    if (type === 'percent') {
        discountPercent.value = cartSubtotal.value > 0
            ? Math.round((discountAmount.value / cartSubtotal.value) * 1000) / 10
            : 0;
    } else {
        discountAmount.value = effectiveDiscountValue.value;
    }
};

const applyDiscountPreset = (percent) => {
    discountType.value = 'percent';
    discountPercent.value = percent;
};

const canCheckout = computed(() => {
    return cart.value.length > 0 && !isSubmitting.value;
});

// ── Checkout Submission ──────────────────────────────────────────
const submitCheckout = async () => {
    if (!canCheckout.value) return;

    isSubmitting.value = true;
    try {
        const payload = {
            customer_id: selectedCustomerId.value || null,
            warehouse_id: selectedWarehouseId.value || null,
            payment_method: paymentMethod.value,
            paid_amount: grandTotal.value,
            status: 'delivered', // Immediate delivery and settlement
            discount_percent: discountType.value === 'percent' ? Number(discountPercent.value) : null,
            discount: effectiveDiscountValue.value,
            tax_percent: 0,
            tax: 0,
            items: cart.value.map((item) => ({
                product_id: item.product_id,
                product_variant_id: item.product_variant_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                warehouse_id: selectedWarehouseId.value || null,
            })),
        };

        const res = await api.post('/admin/invoices', payload);
        const inv = res.data?.data || res.data;

        lastInvoice.value = inv;
        lastChangeDue.value = changeDue.value;
        successModalVisible.value = true;

        // Reset cart for next transaction
        clearCart();
    } catch (err) {
        const msg = err.response?.data?.message || t('error_saving_invoice') || 'فشل تسجيل عملية البيع';
        ElMessage.error(msg);
    } finally {
        isSubmitting.value = false;
    }
};

const printReceipt = (invoice) => {
    window.print();
};

const startNewSale = () => {
    successModalVisible.value = false;
    lastInvoice.value = null;
    lastChangeDue.value = 0;
    nextTick(() => {
        barcodeInputRef.value?.focus();
    });
};

// ── Keyboard Shortcuts ───────────────────────────────────────────
const handleGlobalKeydown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        barcodeInputRef.value?.focus();
    } else if (e.key === 'F9') {
        e.preventDefault();
        if (canCheckout.value) submitCheckout();
    }
};

// ── Lifecycle ────────────────────────────────────────────────────
onMounted(async () => {
    window.addEventListener('keydown', handleGlobalKeydown);
    await Promise.all([
        fetchCategories(),
        fetchWarehouses(),
        searchCustomers(''),
        fetchProducts(),
    ]);
    nextTick(() => {
        barcodeInputRef.value?.focus();
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    clearTimeout(searchDebounceTimer);
});
</script>

<style scoped>
.pos-workspace {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 70px);
    background: #f1f5f9;
    font-family: 'Cairo', sans-serif;
    overflow: hidden;
}

/* ── Top Bar ── */
.pos-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1.25rem;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    z-index: 10;
}

.pos-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.pos-brand i {
    font-size: 1.6rem;
    color: #2563eb;
}
.pos-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.pos-subtitle {
    font-size: 0.75rem;
    color: #64748b;
}

.pos-topbar-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.control-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.control-label {
    font-size: 0.8rem;
    color: #475569;
    font-weight: 600;
}
.warehouse-select {
    width: 160px;
}
.shortcuts-hint {
    display: flex;
    gap: 0.5rem;
}
.kbd-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.15rem 0.45rem;
}
kbd {
    background: #e2e8f0;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    font-weight: 700;
    color: #0f172a;
}

/* ── Main Split ── */
.pos-main {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* ── Catalog (Left) ── */
.pos-catalog {
    flex: 1.5;
    display: flex;
    flex-direction: column;
    padding: 1rem;
    overflow: hidden;
    background: #f8fafc;
    border-inline-end: 1px solid #e2e8f0;
}

.catalog-search-bar {
    margin-bottom: 0.75rem;
}
.barcode-input :deep(.el-input__wrapper) {
    border-radius: 10px;
    padding: 0.4rem 0.8rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.barcode-icon {
    color: #2563eb;
    font-size: 1.2rem;
}

.category-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    margin-bottom: 0.75rem;
}
.cat-pill {
    all: unset;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.cat-pill:hover {
    border-color: #93c5fd;
    color: #2563eb;
}
.cat-pill.is-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

.products-grid-scroll {
    flex: 1;
    overflow-y: auto;
    padding-inline-end: 0.25rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.product-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.product-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(37, 99, 235, 0.08);
}
.product-card.out-of-stock {
    opacity: 0.65;
}

.card-thumb {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f8fafc;
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}
.thumb-img {
    object-fit: contain;
}

.stock-badge {
    position: absolute;
    top: 4px;
    inset-inline-end: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    border: 1px solid transparent;
}
.stock-ok {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}
.stock-low {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}
.stock-none {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}

.card-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.card-name {
    margin: 0 0 0.25rem 0;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.card-sku {
    font-size: 0.72rem;
    color: #94a3b8;
    font-family: monospace;
    margin-bottom: 0.35rem;
}
.card-price-row {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-price {
    font-size: 0.95rem;
    color: #16a34a;
    font-weight: 800;
}
.card-variant-pill {
    font-size: 0.68rem;
    color: #3b82f6;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
    padding: 0.1rem 0.35rem;
}

.card-add-btn {
    all: unset;
    position: absolute;
    bottom: 8px;
    inset-inline-end: 8px;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.15s ease;
}
.product-card:hover .card-add-btn {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.empty-catalog {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}
.empty-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    color: #cbd5e1;
}

/* ── Register / Cart (Right) ── */
.pos-register {
    flex: 1.1;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    overflow: hidden;
}

.register-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.customer-picker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}
.customer-label {
    font-size: 0.8rem;
    color: #475569;
    font-weight: 700;
    white-space: nowrap;
}
.customer-select {
    flex: 1;
}
.customer-opt {
    display: flex;
    justify-content: space-between;
    width: 100%;
}
.customer-balance-badge {
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    display: flex;
    gap: 0.3rem;
}
.customer-balance-badge.is-due {
    background: #fee2e2;
    color: #991b1b;
}
.customer-balance-badge.is-credit {
    background: #dcfce7;
    color: #166534;
}

.cart-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem 1rem;
}

.cart-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.cart-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    transition: all 0.15s ease;
}
.cart-row:hover {
    border-color: #cbd5e1;
    background: #fcfcfd;
}

.item-visual {
    flex-shrink: 0;
}
.item-meta {
    flex: 1;
    min-width: 0;
}
.item-name {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-subline {
    display: flex;
    gap: 0.4rem;
    font-size: 0.7rem;
    color: #64748b;
    margin: 0.15rem 0;
}
.item-variant {
    color: #2563eb;
    font-weight: 600;
}
.item-unit-price {
    font-size: 0.75rem;
    color: #475569;
}

.item-stepper {
    display: flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    overflow: hidden;
}
.step-btn {
    all: unset;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    color: #475569;
    cursor: pointer;
    font-size: 0.7rem;
    transition: background 0.15s;
}
.step-btn:hover {
    background: #e2e8f0;
}
.step-input {
    width: 32px;
    text-align: center;
    border: none;
    background: transparent;
    font-weight: 700;
    font-size: 0.85rem;
    color: #0f172a;
    -moz-appearance: textfield;
}
.step-input::-webkit-outer-spin-button,
.step-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.item-total-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    min-width: 70px;
}
.row-total {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
}
.remove-btn {
    all: unset;
    cursor: pointer;
    color: #94a3b8;
    font-size: 0.75rem;
    padding: 0.2rem;
    transition: color 0.15s;
}
.remove-btn:hover {
    color: #ef4444;
}

.empty-cart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    text-align: center;
    padding: 2rem 1rem;
}
.empty-cart-icon {
    font-size: 2.8rem;
    margin-bottom: 0.6rem;
    color: #cbd5e1;
}
.empty-cart-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #475569;
}
.empty-cart-hint {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

/* ── Register Footer ── */
.register-footer {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.03);
}

.totals-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.breakdown-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #475569;
}

/* Discount Block */
.discount-block {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
}
.discount-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.35rem;
}
.discount-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
}
.discount-mode-toggle {
    display: flex;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
}
.mode-btn {
    all: unset;
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
    cursor: pointer;
    background: #ffffff;
    color: #64748b;
    font-weight: 600;
    transition: all 0.15s;
}
.mode-btn.is-active {
    background: #2563eb;
    color: #ffffff;
}

.discount-input-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}
.discount-input {
    flex: 1;
}
.discount-preview-val {
    font-size: 0.82rem;
    font-weight: 700;
    color: #ef4444;
}

.discount-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.chip-btn {
    all: unset;
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.chip-btn:hover {
    border-color: #93c5fd;
    color: #2563eb;
}
.chip-btn.is-selected {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}

/* Grand Total Row */
.grand-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.3rem;
    border-top: 1px dashed #cbd5e1;
}
.grand-label {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}
.grand-value {
    font-size: 1.4rem;
    font-weight: 900;
    color: #16a34a;
}

/* Payment Tabs */
.payment-method-tabs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.35rem;
}
.pay-tab {
    all: unset;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.45rem 0.2rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.pay-tab:hover {
    border-color: #93c5fd;
    color: #2563eb;
}
.pay-tab.is-active {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}
.pay-tab i {
    font-size: 0.95rem;
}

/* Cash Calculation Box */
.tendered-cash-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
}
.tendered-input-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}
.tendered-lbl {
    font-size: 0.78rem;
    font-weight: 700;
    color: #166534;
}
.tendered-input {
    width: 130px;
}
.cash-quick-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-bottom: 0.35rem;
}
.cash-chip {
    all: unset;
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #86efac;
    color: #15803d;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.cash-chip:hover {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
}
.change-due-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.25rem;
    border-top: 1px dashed #bbf7d0;
    font-size: 0.85rem;
    color: #166534;
}
.change-val {
    font-size: 1.05rem;
    font-weight: 900;
    color: #15803d;
}

/* Checkout Button */
.checkout-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-checkout {
    flex: 1;
    display: flex !important;
    align-items: center;
    justify-content: space-between !important;
    background: #16a34a !important;
    border-color: #16a34a !important;
    font-weight: 800;
    border-radius: 10px;
    font-size: 0.95rem;
}
.btn-checkout:hover {
    background: #15803d !important;
    border-color: #15803d !important;
}
.btn-checkout-total {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    font-size: 1rem;
}

/* ── Receipt Modal ── */
.receipt-preview-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1rem 0;
}
.receipt-success-icon {
    font-size: 3.5rem;
    color: #16a34a;
    margin-bottom: 0.5rem;
}
.receipt-inv-number {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 900;
    color: #0f172a;
}
.receipt-date {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0.25rem 0 1.25rem 0;
}
.receipt-data-summary {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.summary-line {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #334155;
}
.receipt-dialog-actions {
    display: flex;
    gap: 0.75rem;
    width: 100%;
}
.receipt-dialog-actions .el-button {
    flex: 1;
}

@media (max-width: 992px) {
    .pos-workspace {
        height: auto;
        overflow: visible;
    }
    .pos-main {
        flex-direction: column;
        overflow: visible;
    }
    .pos-catalog, .pos-register {
        flex: none;
        width: 100%;
        overflow: visible;
    }
    .products-grid-scroll, .cart-scroll {
        overflow: visible;
    }
}
</style>