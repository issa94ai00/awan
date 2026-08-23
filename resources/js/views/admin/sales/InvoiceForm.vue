<template>
    <div class="invoice-builder">
        <!-- ── Header: what this is, and the two actions that end it ───────── -->
        <header class="builder-header">
            <div class="header-identity">
                <span class="eyebrow">{{ t('sales') }}</span>
                <h1>{{ isEdit ? t('edit_invoice') : t('create_invoice') }}</h1>
                <p>{{ t('prepare_invoice_with_products') }}</p>
            </div>

            <div class="header-actions">
                <el-button text @click="goBack">
                    <el-icon><ArrowRight /></el-icon>
                    {{ t('back_to_invoices') }}
                </el-button>
                <el-button
                    type="primary"
                    size="large"
                    :loading="submitting"
                    :disabled="!canSubmit"
                    @click="submitInvoice"
                >
                    <el-icon><Check /></el-icon>
                    {{ isEdit ? t('save_changes') : t('create_invoice') }}
                </el-button>
            </div>
        </header>

        <!-- Errors sit above the work, never inside it: a message beside the
             field it concerns is better, but a refusal from the server names
             products rather than fields. -->
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

        <div class="builder-grid">
            <!-- ── Work area ──────────────────────────────────────────────── -->
            <section class="work-area">
                <!-- Search is the first thing on the page because it is the
                     first thing anybody does. -->
                <div class="search-card">
                    <div class="search-wrapper">
                        <el-input
                            ref="searchInputRef"
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
                        >
                            <template #prefix>
                                <el-icon><Search /></el-icon>
                            </template>
                            <template #suffix>
                                <kbd class="kbd-hint">Ctrl+B</kbd>
                            </template>
                        </el-input>

                        <!-- Anchored to the field itself. It used to be
                             position-fixed and re-measured on every scroll and
                             resize, which drifted whenever either happened
                             between keystroke and render. -->
                        <Transition name="drop">
                            <div v-if="showResults && (searchResults.length || searchLoading || searchQuery.length >= 2)" class="results">
                                <div v-if="searchLoading" class="results-state">
                                    <el-icon class="is-loading"><Loading /></el-icon>
                                    {{ t('searching') }}…
                                </div>

                                <ul v-else-if="searchResults.length" class="results-list">
                                    <li
                                        v-for="(product, index) in searchResults"
                                        :key="product.id"
                                        class="result"
                                        :class="{ active: highlightedIndex === index }"
                                        @click="addProduct(product)"
                                        @mouseenter="highlightedIndex = index"
                                    >
                                        <div class="result-thumb">
                                            <img v-if="product.image_main" :src="getImageUrl(product.image_main)" alt="" />
                                            <el-icon v-else><Box /></el-icon>
                                        </div>

                                        <div class="result-body">
                                            <span class="result-name">{{ product.name_ar || product.name_en }}</span>
                                            <span v-if="product.sku" class="result-sku">{{ product.sku }}</span>
                                        </div>

                                        <div class="result-side">
                                            <span class="result-price">{{ money(product.price) }}</span>
                                            <span class="result-stock" :class="stockTone(product.stock_quantity)">
                                                {{ t('available') }} {{ formatNumber(product.stock_quantity || 0) }}
                                            </span>
                                        </div>
                                    </li>
                                </ul>

                                <div v-else class="results-state muted">
                                    {{ t('no_products_found') }}
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <p class="search-hint">{{ t('invoice_search_hint') }}</p>
                </div>

                <!-- Lines -->
                <div class="lines-card">
                    <div class="lines-head">
                        <h2>{{ t('invoice_items') }}</h2>
                        <span class="count">{{ items.length }} {{ t('item') }}</span>
                    </div>

                    <div v-if="!items.length" class="empty">
                        <el-icon class="empty-icon"><ShoppingCart /></el-icon>
                        <p class="empty-title">{{ t('no_items_yet') }}</p>
                        <p class="empty-hint">{{ t('use_search_to_add_products') }}</p>
                    </div>

                    <div v-else class="lines">
                        <article
                            v-for="(item, index) in items"
                            :key="item.product_id"
                            class="line"
                            :class="{ short: isLineShort(item) }"
                        >
                            <div class="line-main">
                                <div class="line-identity">
                                    <span class="line-name">{{ item.name }}</span>
                                    <span v-if="item.sku" class="line-sku">{{ item.sku }}</span>
                                </div>

                                <div class="line-total">
                                    <span class="line-total-value">{{ money(item.price * lineQuantity(item)) }}</span>
                                    <el-button
                                        text
                                        type="danger"
                                        size="small"
                                        :title="t('remove')"
                                        @click="removeItem(index)"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </el-button>
                                </div>
                            </div>

                            <!-- Which shelf this empties, and how much of the
                                 line each takes. The server refuses a line it
                                 cannot cover; choosing here against live figures
                                 means finding out now rather than on submit. A
                                 line can draw from more than one warehouse when
                                 no single one holds the whole quantity. -->
                            <div class="allocations">
                                <div class="allocations-head">
                                    <span class="field-label">{{ t('sales.source_warehouse') }}</span>
                                    <span class="allocations-total">{{ t('quantity') }}: {{ formatNumber(lineQuantity(item)) }}</span>
                                </div>

                                <div v-for="(alloc, aIdx) in item.allocations" :key="aIdx" class="allocation-row">
                                    <el-select
                                        v-model="alloc.warehouse_id"
                                        :placeholder="t('sales.choose_source')"
                                        :loading="item.loadingStock"
                                        size="default"
                                        class="allocation-source"
                                        @change="onSourceChange(index)"
                                    >
                                        <el-option
                                            v-for="row in item.sources"
                                            :key="row.warehouse_id"
                                            :value="row.warehouse_id"
                                            :label="row.warehouse_name"
                                            :disabled="isSourceTaken(item, alloc, row.warehouse_id)"
                                        >
                                            <span class="option-row">
                                                <span>{{ row.warehouse_name }}</span>
                                                <span class="option-stock" :class="{ empty: row.available <= 0 }">
                                                    {{ formatNumber(row.available) }}
                                                </span>
                                            </span>
                                        </el-option>
                                    </el-select>

                                    <div class="stepper allocation-qty">
                                        <button type="button" :disabled="alloc.quantity <= 1" @click="decrementAllocQty(index, aIdx)">
                                            <el-icon><Minus /></el-icon>
                                        </button>
                                        <input v-model.number="alloc.quantity" type="number" min="1" />
                                        <button type="button" @click="incrementAllocQty(index, aIdx)">
                                            <el-icon><Plus /></el-icon>
                                        </button>
                                    </div>

                                    <el-button
                                        v-if="item.allocations.length > 1"
                                        text
                                        type="danger"
                                        size="small"
                                        class="allocation-remove"
                                        :title="t('remove')"
                                        @click="removeAllocation(index, aIdx)"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </el-button>

                                    <!-- Stated on the allocation that caused it,
                                         not collected into a banner the reader
                                         has to map back. -->
                                    <p v-if="isAllocationShort(item, alloc)" class="allocation-warning">
                                        <el-icon><WarningFilled /></el-icon>
                                        {{ t('sales.line_short_at_warehouse', {
                                            product: item.name,
                                            warehouse: warehouseName(item, alloc.warehouse_id),
                                            available: formatNumber(availableAt(item, alloc.warehouse_id)),
                                            required: formatNumber(askedFor(items, item.product_id, alloc.warehouse_id)),
                                        }) }}
                                    </p>
                                    <p v-else-if="!alloc.warehouse_id" class="allocation-warning subtle">
                                        {{ t('sales.choose_source_for_this_line') }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="add-source-btn"
                                    :disabled="!canAddAllocation(item)"
                                    @click="addAllocation(index)"
                                >
                                    <el-icon><Plus /></el-icon> {{ t('sales.add_source_warehouse') }}
                                </button>
                            </div>

                            <div class="line-fields">
                                <label class="field field-unit">
                                    <span class="field-label">{{ t('unit') }}</span>
                                    <el-select
                                        v-model="item.selectedUnit"
                                        value-key="id"
                                        size="default"
                                        @change="onUnitChange(index)"
                                    >
                                        <el-option
                                            v-for="unit in item.units"
                                            :key="unit.id ?? unit.name"
                                            :value="unit"
                                            :label="unit.name_ar || unit.name"
                                        />
                                    </el-select>
                                </label>

                                <label class="field field-price">
                                    <span class="field-label">{{ t('unit_price') }}</span>
                                    <el-input v-model.number="item.price" type="number" min="0" step="0.01" />
                                </label>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ── Summary rail: who, how it settles, what is owed ─────────── -->
            <aside class="summary-rail">
                <div class="rail-card">
                    <h3 class="rail-title"><el-icon><User /></el-icon> {{ t('client') }}</h3>

                    <label class="field">
                        <span class="field-label">{{ t('client') }}</span>
                        <el-select
                            v-model="form.customer_id"
                            filterable
                            clearable
                            :placeholder="t('choose_client')"
                        >
                            <el-option
                                v-for="customer in customers"
                                :key="customer.id"
                                :label="customer.name"
                                :value="customer.id"
                            />
                        </el-select>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ t('sales_representative') }}</span>
                        <el-select
                            v-model="form.assigned_employee_id"
                            filterable
                            clearable
                            :placeholder="t('unassigned')"
                        >
                            <el-option
                                v-for="employee in salesEmployees"
                                :key="employee.id"
                                :label="employee.name"
                                :value="employee.id"
                            />
                        </el-select>
                    </label>
                </div>

                <div class="rail-card">
                    <h3 class="rail-title"><el-icon><Wallet /></el-icon> {{ t('settlement_and_totals') }}</h3>

                    <div class="fields-row">
                        <label class="field">
                            <span class="field-label">{{ t('discount') }}</span>
                            <el-input v-model.number="form.discount" type="number" min="0" step="0.01" />
                        </label>
                        <label class="field">
                            <span class="field-label">{{ t('tax') }}</span>
                            <el-input v-model.number="form.tax" type="number" min="0" step="0.01" />
                        </label>
                    </div>

                    <div class="fields-row">
                        <label class="field">
                            <span class="field-label">{{ t('payment_method') }}</span>
                            <el-select v-model="form.payment_method">
                                <el-option :label="t('cash')" value="cash" />
                                <el-option :label="t('card')" value="card" />
                                <el-option :label="t('bank_transfer')" value="bank_transfer" />
                                <el-option :label="t('cheque')" value="check" />
                            </el-select>
                        </label>
                        <label class="field">
                            <span class="field-label">{{ t('status') }}</span>
                            <el-select v-model="form.status">
                                <el-option
                                    v-for="status in availableStatuses"
                                    :key="status"
                                    :label="statusLabels[status]"
                                    :value="status"
                                />
                            </el-select>
                        </label>
                    </div>

                    <!-- Costs billed on this invoice. Folded away until used, so
                         the common sale is not asked about the rare one. -->
                    <div class="extras">
                        <button type="button" class="extras-toggle" @click="showExpenses = !showExpenses">
                            <el-icon><component :is="showExpenses ? Minus : Plus" /></el-icon>
                            {{ t('additional_charges') }}
                            <span v-if="totalExpenses > 0" class="extras-badge">{{ money(totalExpenses) }}</span>
                        </button>

                        <div v-if="showExpenses" class="extras-body">
                            <div v-for="(expense, index) in form.expenses" :key="index" class="extra-row">
                                <el-input v-model="expense.description" :placeholder="t('description')" size="small" />
                                <el-input v-model.number="expense.amount" type="number" min="0" size="small" style="width: 110px" />
                                <el-button text type="danger" size="small" @click="removeExpense(index)">
                                    <el-icon><Delete /></el-icon>
                                </el-button>
                            </div>
                            <el-button text size="small" @click="addExpense">
                                <el-icon><Plus /></el-icon> {{ t('add_expense') }}
                            </el-button>
                        </div>
                    </div>

                    <dl class="totals">
                        <div class="total-line">
                            <dt>{{ t('subtotal') }}</dt>
                            <dd>{{ money(subtotal) }}</dd>
                        </div>
                        <div v-if="form.discount > 0" class="total-line deduct">
                            <dt>{{ t('discount') }}</dt>
                            <dd>− {{ money(form.discount) }}</dd>
                        </div>
                        <div v-if="form.tax > 0" class="total-line">
                            <dt>{{ t('tax') }}</dt>
                            <dd>+ {{ money(form.tax) }}</dd>
                        </div>
                        <div v-if="totalExpenses > 0" class="total-line">
                            <dt>{{ t('additional_charges') }}</dt>
                            <dd>+ {{ money(totalExpenses) }}</dd>
                        </div>
                        <div class="total-line grand">
                            <dt>{{ t('grand_total_label') }}</dt>
                            <dd>{{ money(total) }}</dd>
                        </div>
                    </dl>

                    <label class="field">
                        <span class="field-label">{{ t('paid_now') }}</span>
                        <el-input v-model.number="form.paid_amount" type="number" min="0" step="0.01" size="large">
                            <template #append>
                                <el-button @click="form.paid_amount = total">{{ t('full_amount') }}</el-button>
                            </template>
                        </el-input>
                    </label>

                    <!-- What the customer's account will say after this is
                         saved, before it is saved. -->
                    <div class="settlement" :class="remainingTone">
                        <span class="settlement-label">
                            {{ remainingTone === 'settled' ? t('fully_settled')
                                : remainingTone === 'owing' ? t('remaining_on_customer')
                                : t('customer_credit') }}
                        </span>
                        <span class="settlement-value">{{ money(Math.abs(remaining)) }}</span>
                    </div>
                </div>

                <div class="rail-card">
                    <h3 class="rail-title"><el-icon><Notebook /></el-icon> {{ t('notes') }}</h3>
                    <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="t('invoice_notes_placeholder')" />
                </div>

                <p class="shortcuts">
                    <kbd>Ctrl+B</kbd> {{ t('search_product') }}
                    <span class="dot">·</span>
                    <kbd>Ctrl+Enter</kbd> {{ t('save') }}
                </p>
            </aside>
        </div>

        <!-- On a phone the summary rail is far below the lines, so the figure
             that decides whether to save follows the screen. -->
        <div class="mobile-bar">
            <div class="mobile-total">
                <span>{{ t('grand_total_label') }}</span>
                <strong>{{ money(total) }}</strong>
            </div>
            <el-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submitInvoice">
                {{ isEdit ? t('save_changes') : t('create_invoice') }}
            </el-button>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useInvoicesStore } from '@/stores/invoices';
import { useCustomersStore } from '@/stores/customers';
import { posApi } from '@/api/pos';
import api from '@/api';
import { formatNumber } from '@/utils/currency';
import {
    summariseSources,
    availableAt,
    askedFor,
    isLineShort as lineIsShort,
    isAllocationShort as allocationIsShort,
    lineQuantity,
    preferredSource,
} from '@/utils/stockSources';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Search, ShoppingCart, User, Wallet, Notebook,
    ArrowRight, Plus, Minus, Delete, Check, Loading,
    Box, WarningFilled,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const invoicesStore = useInvoicesStore();
const customersStore = useCustomersStore();

const isEdit = computed(() => !!route.params.id);
const searchInputRef = ref(null);

const form = reactive({
    customer_id: null,
    // The rep credited with the sale. Null is a valid answer.
    assigned_employee_id: null,
    payment_method: 'cash',
    discount: 0,
    tax: 0,
    // Collected at the moment the invoice is raised; the rest becomes the
    // customer's outstanding balance.
    paid_amount: 0,
    notes: '',
    status: 'pending',
    expenses: [],
});

const statusTransitions = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: [],
};

const statusLabels = {
    pending: t('sales_status_pending'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    shipped: t('sales_status_shipped'),
    delivered: t('sales_status_delivered'),
    cancelled: t('sales_status_cancelled'),
};

const items = ref([]);
const formErrors = ref([]);
const submitting = ref(false);
const showExpenses = ref(false);

const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const showResults = ref(false);
const highlightedIndex = ref(-1);
let searchTimeout = null;

const customers = ref([]);
const salesEmployees = ref([]);

/* ------------------------------------------------------------------ *
 * Where each line comes from
 *
 * A sale has to say which shelf it empties. The server refuses a line it
 * cannot cover, but discovering that on submit is the least useful moment to
 * be told — so the choice is made here against live figures, and a line that
 * does not fit is marked before anyone presses save.
 * ------------------------------------------------------------------ */

const isLineShort = (item) => lineIsShort(items.value, item);
const isAllocationShort = (item, allocation) => allocationIsShort(items.value, item, allocation);
const warehouseName = (item, warehouseId) =>
    item.sources?.find((row) => row.warehouse_id === warehouseId)?.warehouse_name || '';
// Another allocation on the same line already draws from this warehouse — the
// dropdown hides it rather than letting the seller pick one shelf twice.
const isSourceTaken = (item, allocation, warehouseId) =>
    (item.allocations || []).some((row) => row !== allocation && row.warehouse_id === warehouseId);
const canAddAllocation = (item) => (item.sources?.length || 0) > (item.allocations?.length || 0);

const shortLines = computed(() => items.value.filter(isLineShort));
const missingSource = computed(() =>
    items.value.filter((item) => (item.allocations || []).some((allocation) => !allocation.warehouse_id))
);

/**
 * Reads what each warehouse holds of a product, newest figures first.
 *
 * Called when a product joins the sale rather than up front: loading every
 * warehouse's stock for the whole catalogue to fill one dropdown would be a
 * large query for information mostly nobody looks at.
 */
const loadSourcesFor = async (item) => {
    item.loadingStock = true;

    try {
        const { data } = await api.get('/admin/inventory/stock', {
            params: { product_id: item.product_id, per_page: 100 },
        });

        // Rows are summed per warehouse (a product can occupy several bins in
        // one) and ordered by what is free.
        item.sources = summariseSources(data?.data?.stock ?? []);

        // Fills in any allocation still missing a warehouse — preferring one no
        // other allocation on this line already draws from, so two blank
        // allocations do not both default to the same shelf.
        const used = new Set(item.allocations.filter((a) => a.warehouse_id).map((a) => a.warehouse_id));
        for (const allocation of item.allocations) {
            if (allocation.warehouse_id) continue;

            const candidates = item.sources.filter((row) => !used.has(row.warehouse_id));
            const picked = preferredSource(candidates.length ? candidates : item.sources, allocation.quantity);
            allocation.warehouse_id = picked;
            if (picked) used.add(picked);
        }
    } catch (error) {
        item.sources = [];
    } finally {
        item.loadingStock = false;
    }
};

const onSourceChange = (index) => {
    // Nothing to fetch — the figures are already loaded. The handler exists so
    // a line whose sources never loaded gets another chance.
    const item = items.value[index];
    if (item && !item.sources?.length) {
        loadSourcesFor(item);
    }
};

/* ------------------------------------------------------------------ *
 * Money
 * ------------------------------------------------------------------ */

const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100;

const subtotal = computed(() =>
    items.value.reduce((sum, item) => sum + (Number(item.price) || 0) * lineQuantity(item), 0)
);

const totalExpenses = computed(() =>
    form.expenses.reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)
);

const total = computed(() =>
    Math.max(0, subtotal.value - (Number(form.discount) || 0) + (Number(form.tax) || 0) + totalExpenses.value)
);

// Positive: the customer still owes this. Negative: they overpaid and the
// difference becomes credit on their account.
const remaining = computed(() => round2(total.value - (Number(form.paid_amount) || 0)));

const remainingTone = computed(() => {
    if (Math.abs(remaining.value) < 0.005) return 'settled';
    return remaining.value > 0 ? 'owing' : 'credit';
});

const availableStatuses = computed(() => [form.status, ...(statusTransitions[form.status] || [])]);

// The save button says what the form knows: no lines, or a line that cannot be
// covered, and it is not ready. The server still decides.
const canSubmit = computed(() =>
    items.value.length > 0 && missingSource.value.length === 0 && shortLines.value.length === 0
);

const money = (value) =>
    new Intl.NumberFormat('ar-SY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        .format(Number(value) || 0) + ' ' + t('currency');

const stockTone = (quantity) => {
    const value = Number(quantity) || 0;
    if (value <= 0) return 'out';
    return value <= 5 ? 'low' : 'ok';
};

const getImageUrl = (image) => {
    if (!image) return '';
    return image.startsWith('http') ? image : `/storage/${image}`;
};

/* ------------------------------------------------------------------ *
 * Search
 * ------------------------------------------------------------------ */

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
        } catch (error) {
            searchResults.value = [];
        } finally {
            searchLoading.value = false;
        }
    }, 300);
};

const navigateResult = (direction) => {
    if (!searchResults.value.length) return;

    const max = searchResults.value.length - 1;
    highlightedIndex.value = direction === 1
        ? (highlightedIndex.value >= max ? 0 : highlightedIndex.value + 1)
        : (highlightedIndex.value <= 0 ? max : highlightedIndex.value - 1);
};

const selectHighlighted = () => {
    if (highlightedIndex.value >= 0 && highlightedIndex.value < searchResults.value.length) {
        addProduct(searchResults.value[highlightedIndex.value]);
    }
};

/* ------------------------------------------------------------------ *
 * Lines
 * ------------------------------------------------------------------ */

const addProduct = (product) => {
    const existing = items.value.findIndex((line) => line.product_id === product.id);

    if (existing !== -1) {
        // Bumps the first source rather than opening a new allocation — most
        // repeat adds mean "one more of the same"; a split across warehouses is
        // the deliberate choice made with "add another source" below.
        items.value[existing].allocations[0].quantity += 1;
    } else {
        const defaultUnit = {
            id: null,
            name: product.unit || t('piece'),
            name_ar: product.unit || t('piece'),
            base_unit_multiplier: 1,
            price_multiplier: 1,
            barcode: product.barcode || '',
        };

        const line = reactive({
            product_id: product.id,
            name: product.name_ar || product.name_en,
            sku: product.sku || '',
            price: parseFloat(product.price) || 0,
            stock: product.stock_quantity || 0,
            unit: product.unit || '',
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            base_price: parseFloat(product.price) || 0,
            // Where this line is taken from, and how much of it each source
            // covers. Starts as one allocation; "add another source" below
            // splits it across more than one warehouse.
            allocations: [{ warehouse_id: null, quantity: 1 }],
            sources: [],
            loadingStock: false,
        });

        items.value.push(line);
        loadSourcesFor(line);
        loadProductUnits(product.id, items.value.length - 1);
    }

    searchQuery.value = '';
    searchResults.value = [];
    showResults.value = false;

    nextTick(() => searchInputRef.value?.focus());
};

const loadProductUnits = async (productId, itemIndex) => {
    try {
        const { data } = await api.get(`/admin/products/${productId}/units`);
        const rows = data?.data ?? [];

        if (!rows.length || !items.value[itemIndex]) {
            return;
        }

        const units = rows.map((unit) => ({
            id: unit.id,
            name: unit.name,
            name_ar: unit.name_ar || unit.name,
            base_unit_multiplier: parseFloat(unit.base_unit_multiplier),
            price_multiplier: parseFloat(unit.price_multiplier),
            barcode: unit.barcode || '',
        }));

        const defaultUnit = rows.find((unit) => unit.is_default)
            ? units[rows.findIndex((unit) => unit.is_default)]
            : units[0];

        items.value[itemIndex].units = units;
        items.value[itemIndex].selectedUnit = defaultUnit;
        items.value[itemIndex].price = items.value[itemIndex].base_price * defaultUnit.price_multiplier;
    } catch (error) {
        // A product with no unit list keeps the default piece it was added
        // with; the sale does not depend on this call.
    }
};

const onUnitChange = (index) => {
    const item = items.value[index];
    if (item?.selectedUnit) {
        item.price = item.base_price * item.selectedUnit.price_multiplier;
    }
};

const removeItem = (index) => items.value.splice(index, 1);

const incrementAllocQty = (index, allocIndex) => {
    items.value[index].allocations[allocIndex].quantity += 1;
};
const decrementAllocQty = (index, allocIndex) => {
    const allocation = items.value[index].allocations[allocIndex];
    if (allocation.quantity > 1) allocation.quantity -= 1;
};

// Opens a second (or third...) source on the line, defaulting to a warehouse
// no allocation on it already uses.
const addAllocation = (index) => {
    const item = items.value[index];
    if (!item) return;

    const used = new Set(item.allocations.map((a) => a.warehouse_id).filter(Boolean));
    const candidate = (item.sources || []).find((row) => !used.has(row.warehouse_id));
    item.allocations.push({ warehouse_id: candidate?.warehouse_id ?? null, quantity: 1 });
};

const removeAllocation = (index, allocIndex) => {
    const item = items.value[index];
    if (!item || item.allocations.length <= 1) return;
    item.allocations.splice(allocIndex, 1);
};

const addExpense = () => form.expenses.push({ description: '', category: 'other', amount: 0 });
const removeExpense = (index) => form.expenses.splice(index, 1);

/* ------------------------------------------------------------------ *
 * Keyboard
 * ------------------------------------------------------------------ */

const handleKeyboardShortcuts = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }

    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (canSubmit.value) submitInvoice();
    }

    if (e.key === 'Escape') {
        showResults.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Submit
 * ------------------------------------------------------------------ */

const submitInvoice = async () => {
    formErrors.value = [];

    if (!items.value.length) {
        formErrors.value.push(t('add_at_least_one_item'));
        return;
    }

    // Caught here as well as on the server. The server is the authority — it
    // holds the lock and refuses the write — but letting the request go and
    // reporting the refusal afterwards wastes the seller's time in front of a
    // waiting customer.
    if (missingSource.value.length) {
        formErrors.value.push(t('sales.choose_source_for_every_line'));
        return;
    }

    if (shortLines.value.length) {
        formErrors.value = shortLines.value.flatMap((item) =>
            item.allocations
                .filter((allocation) => isAllocationShort(item, allocation))
                .map((allocation) => t('sales.line_short_at_warehouse', {
                    product: item.name,
                    warehouse: warehouseName(item, allocation.warehouse_id),
                    available: formatNumber(availableAt(item, allocation.warehouse_id)),
                    required: formatNumber(askedFor(items.value, item.product_id, allocation.warehouse_id)),
                }))
        );
        return;
    }

    submitting.value = true;

    try {
        const payload = {
            customer_id: form.customer_id,
            assigned_employee_id: form.assigned_employee_id,
            payment_method: form.payment_method,
            discount: form.discount || 0,
            tax: form.tax || 0,
            paid_amount: form.paid_amount || 0,
            notes: form.notes,
            status: form.status,
            // Each allocation becomes its own invoice line — the API already
            // accepts several lines for one product, one per warehouse, which
            // is how a split line is represented on the server.
            items: items.value.flatMap((item) => item.allocations
                .filter((allocation) => allocation.warehouse_id && Number(allocation.quantity) > 0)
                .map((allocation) => ({
                    product_id: item.product_id,
                    quantity: allocation.quantity,
                    unit_price: item.price,
                    warehouse_id: allocation.warehouse_id,
                    product_unit_id: item.selectedUnit?.id || null,
                }))),
            expenses: form.expenses.filter((expense) => expense.description && expense.amount > 0),
        };

        if (isEdit.value) {
            await invoicesStore.updateInvoice(route.params.id, payload);
            ElMessage.success(t('invoice_updated_successfully'));
        } else {
            const created = await invoicesStore.createInvoice(payload);

            // Report what actually happened to the customer's account, rather
            // than a generic "saved".
            const settlement = created?.settlement;
            const parts = [t('invoice_created_successfully')];

            if (settlement?.payment_number) {
                parts.push(`${t('payment')} ${settlement.payment_number}`);
            }

            if (settlement && Math.abs(settlement.remaining) >= 0.005) {
                parts.push(settlement.remaining > 0
                    ? `${t('remaining_on_customer')} ${money(settlement.remaining)}`
                    : `${t('customer_credit')} ${money(Math.abs(settlement.remaining))}`);
            }

            ElMessage.success({ message: parts.join(' • '), duration: 5000 });
        }

        router.push('/admin/sales/invoices');
    } catch (error) {
        // The server refused because a shelf could not cover a line — most
        // often because someone else sold the same stock while this sale was
        // being rung up. Named per product and warehouse, and the fresh figures
        // are pulled back in so the screen stops showing the numbers that were
        // true a minute ago.
        const shortages = error.response?.data?.data?.shortages;

        if (Array.isArray(shortages) && shortages.length) {
            formErrors.value = shortages.map((row) => t('sales.line_short_at_warehouse', {
                product: row.product_name,
                warehouse: row.warehouse_name,
                available: formatNumber(row.available),
                required: formatNumber(row.required),
            }));

            await Promise.all(items.value.map(loadSourcesFor));
        } else if (error.response?.data?.errors) {
            formErrors.value = Object.values(error.response.data.errors).flat();
        } else {
            formErrors.value = [
                error.response?.data?.message || error.message || t('failed_to_save_invoice'),
            ];
        }
    } finally {
        submitting.value = false;
    }
};

const goBack = async () => {
    if (items.value.length && !isEdit.value) {
        try {
            await ElMessageBox.confirm(t('confirm_leave_unsaved_invoice'), t('confirm'), {
                type: 'warning',
                confirmButtonText: t('confirm'),
                cancelButtonText: t('cancel'),
            });
        } catch {
            return;
        }
    }

    router.push('/admin/sales/invoices');
};

const handleClickOutside = (e) => {
    if (!e.target.closest('.search-wrapper')) {
        showResults.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Load
 * ------------------------------------------------------------------ */

const loadInvoice = async () => {
    const invoice = await invoicesStore.fetchInvoice(route.params.id);

    if (!invoice) return;

    form.customer_id = invoice.customer_id;
    form.assigned_employee_id = invoice.assigned_employee_id ?? null;
    form.payment_method = invoice.payment_method || 'cash';
    form.discount = parseFloat(invoice.discount) || 0;
    form.tax = parseFloat(invoice.tax) || 0;
    form.paid_amount = parseFloat(invoice.paid_amount) || 0;
    form.status = invoice.status || 'pending';
    form.notes = invoice.notes || '';

    // Every field the form can change is restored. This used to reload the
    // product, price and quantity only — so reopening an invoice lost the
    // warehouse each line came from and the unit it was priced in, and saving
    // it again wrote those back as empty.
    //
    // Several invoice items can share a product_id — that is how a line split
    // across warehouses was saved — so they are regrouped into one line with
    // several allocations, the same shape the builder edits them in.
    const grouped = new Map();

    for (const item of invoice.items ?? []) {
        let line = grouped.get(item.product_id);

        if (!line) {
            line = reactive({
                product_id: item.product_id,
                name: item.product_name || item.product?.name_ar,
                sku: item.product?.sku || '',
                price: parseFloat(item.unit_price) || 0,
                stock: item.product?.stock_quantity || 0,
                unit: item.product?.unit || '',
                base_price: parseFloat(item.unit_price) || 0,
                selectedUnit: {
                    id: item.product_unit_id ?? null,
                    name: item.unit_name || item.product?.unit || t('piece'),
                    name_ar: item.unit_name || item.product?.unit || t('piece'),
                    base_unit_multiplier: 1,
                    price_multiplier: 1,
                },
                units: [],
                allocations: [],
                sources: [],
                loadingStock: false,
            });
            grouped.set(item.product_id, line);
        }

        line.allocations.push({
            warehouse_id: item.warehouse_id ?? null,
            quantity: item.quantity || 1,
        });
    }

    items.value = [...grouped.values()];

    // The live figures for each line, so a shortage introduced since the
    // invoice was raised is visible immediately rather than on submit.
    await Promise.all(items.value.map(loadSourcesFor));
};

onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyboardShortcuts);

    try {
        await customersStore.fetchCustomers();
        customers.value = customersStore.customers;
    } catch (error) {
        customers.value = [];
    }

    // The reps a sale can be credited to. A failure here leaves the field
    // empty rather than blocking the sale — attribution is optional, and a
    // counter sale must still be possible when the list will not load.
    try {
        const { data } = await api.get('/sales-employees');
        salesEmployees.value = data?.data ?? data ?? [];
    } catch (error) {
        salesEmployees.value = [];
    }

    if (isEdit.value) {
        try {
            await loadInvoice();
        } catch (error) {
            ElMessage.error(t('failed_to_load_invoice'));
        }
    } else {
        nextTick(() => searchInputRef.value?.focus());
    }

    if (form.expenses.length) showExpenses.value = true;
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyboardShortcuts);
    clearTimeout(searchTimeout);
});
</script>

<style scoped>
/* The house palette: navy and gold from vue-custom.css, with semantic colours
   kept separate from the accent so "owing" never reads as "brand". */
.invoice-builder {
    --ink: #121c2c;
    --ink-soft: #475569;
    --ink-mute: #7c8798;
    --line: #e2e8f0;
    --surface: #ffffff;
    --ground: #f8fafc;
    --gold: #d4a84b;
    --ok: #1b6b4c;
    --ok-soft: #e8f3ee;
    --warn: #8a6212;
    --warn-soft: #fbf3e0;
    --bad: #9b2c2c;
    --bad-soft: #fbeceb;

    font-family: 'Cairo', sans-serif;
    color: var(--ink);
    padding-bottom: 5rem;
}

/* ── Header ─────────────────────────────────────────────────────────── */
.builder-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--line);
}

.eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: var(--gold);
    text-transform: uppercase;
}

.header-identity h1 {
    margin: 0.35rem 0 0;
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.header-identity p {
    margin: 0.3rem 0 0;
    color: var(--ink-mute);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.error-list {
    margin: 0.5rem 0 0;
    padding-inline-start: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

/* ── Grid ───────────────────────────────────────────────────────────── */
.builder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 380px;
    gap: 1.5rem;
    align-items: start;
}

.work-area {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
}

/* ── Search ─────────────────────────────────────────────────────────── */
.search-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
}

/* Anchors the dropdown; it used to be positioned by hand against the viewport
   and re-measured on scroll. */
.search-wrapper {
    position: relative;
}

.kbd-hint,
.shortcuts kbd {
    font-family: inherit;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ink-mute);
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 5px;
    padding: 0.1rem 0.4rem;
}

.search-hint {
    margin: 0.65rem 0 0;
    font-size: 0.8rem;
    color: var(--ink-mute);
}

.results {
    position: absolute;
    inset-inline: 0;
    top: calc(100% + 8px);
    z-index: 30;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: 0 18px 40px -18px rgba(18, 28, 44, 0.45);
    overflow: hidden;
    max-height: 22rem;
    overflow-y: auto;
}

.results-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.25rem;
    color: var(--ink-soft);
    font-size: 0.9rem;
}

.results-state.muted { color: var(--ink-mute); }

.results-list {
    list-style: none;
    margin: 0;
    padding: 0.35rem;
}

.result {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.6rem 0.7rem;
    border-radius: 9px;
    cursor: pointer;
    transition: background 0.12s ease;
}

.result.active { background: var(--ground); }

.result-thumb {
    width: 40px;
    height: 40px;
    flex: none;
    display: grid;
    place-items: center;
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 8px;
    overflow: hidden;
    color: var(--ink-mute);
}

.result-thumb img { width: 100%; height: 100%; object-fit: cover; }

.result-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.result-name {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-sku {
    font-size: 0.74rem;
    color: var(--ink-mute);
    font-family: 'Cascadia Mono', Consolas, monospace;
}

.result-side {
    text-align: end;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: none;
}

.result-price { font-weight: 700; font-size: 0.9rem; }

.result-stock { font-size: 0.72rem; }
.result-stock.ok { color: var(--ok); }
.result-stock.low { color: var(--warn); }
.result-stock.out { color: var(--bad); }

.drop-enter-active, .drop-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── Lines ──────────────────────────────────────────────────────────── */
.lines-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
}

.lines-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--line);
}

.lines-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }

.count {
    font-size: 0.8rem;
    color: var(--ink-mute);
    background: var(--ground);
    border-radius: 999px;
    padding: 0.15rem 0.65rem;
}

.empty {
    padding: 3.5rem 1.5rem;
    text-align: center;
    color: var(--ink-mute);
}

.empty-icon { font-size: 2.5rem; opacity: 0.35; }
.empty-title { margin: 1rem 0 0.25rem; font-weight: 700; color: var(--ink-soft); }
.empty-hint { margin: 0; font-size: 0.88rem; }

.lines { display: flex; flex-direction: column; }

.line {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.line:last-child { border-bottom: none; }

/* The rail carries the state; the row itself stays quiet. */
.line.short {
    background: var(--bad-soft);
    box-shadow: inset 3px 0 0 var(--bad);
}

.line-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.line-identity { display: flex; flex-direction: column; min-width: 0; }
.line-name { font-weight: 700; }
.line-sku {
    font-size: 0.74rem;
    color: var(--ink-mute);
    font-family: 'Cascadia Mono', Consolas, monospace;
}

.line-total { display: flex; align-items: center; gap: 0.5rem; flex: none; }
.line-total-value { font-weight: 800; font-size: 1.05rem; font-variant-numeric: tabular-nums; }

.line-fields {
    display: grid;
    grid-template-columns: minmax(150px, 1fr) minmax(110px, 1fr);
    gap: 0.75rem;
}

/* ── Allocations: which shelves a line draws from ─────────────────────── */
.allocations {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 0.75rem;
}

.allocations-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.allocations-total {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ink-soft);
    font-variant-numeric: tabular-nums;
}

.allocation-row {
    display: grid;
    grid-template-columns: minmax(150px, 1.6fr) 140px auto;
    align-items: center;
    gap: 0.6rem;
}

.allocation-row .allocation-warning {
    grid-column: 1 / -1;
}

.allocation-remove {
    justify-self: start;
}

.add-source-btn {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: none;
    border: 1px dashed var(--line);
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ink-soft);
    cursor: pointer;
}

.add-source-btn:hover:not(:disabled) {
    border-color: var(--gold);
    color: var(--ink);
}

.add-source-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }

.field-label {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--ink-mute);
}

.option-row { display: flex; justify-content: space-between; gap: 1rem; width: 100%; }
.option-stock { color: var(--ok); font-variant-numeric: tabular-nums; }
.option-stock.empty { color: var(--bad); }

.stepper {
    display: flex;
    align-items: center;
    border: 1px solid var(--line);
    border-radius: 8px;
    overflow: hidden;
    height: 32px;
    background: var(--surface);
}

.stepper button {
    width: 34px;
    height: 100%;
    border: none;
    background: var(--ground);
    color: var(--ink-soft);
    cursor: pointer;
    display: grid;
    place-items: center;
}

.stepper button:disabled { opacity: 0.4; cursor: not-allowed; }
.stepper button:hover:not(:disabled) { background: var(--line); }

.stepper input {
    flex: 1;
    min-width: 0;
    border: none;
    text-align: center;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    background: transparent;
    color: var(--ink);
    -moz-appearance: textfield;
}

.stepper input::-webkit-outer-spin-button,
.stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

.line-warning,
.line-note,
.allocation-warning {
    margin: 0;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.line-warning,
.allocation-warning { color: var(--bad); font-weight: 600; }
.line-warning.subtle,
.allocation-warning.subtle { color: var(--warn); font-weight: 500; }
.line-note { color: var(--ink-mute); }

/* ── Summary rail ───────────────────────────────────────────────────── */
.summary-rail {
    position: sticky;
    top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.rail-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.rail-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--ink);
}

.fields-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.extras { border-top: 1px dashed var(--line); padding-top: 0.85rem; }

.extras-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    background: none;
    border: none;
    padding: 0;
    font: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ink-soft);
    cursor: pointer;
}

.extras-badge {
    margin-inline-start: auto;
    font-weight: 700;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
}

.extras-body { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem; }
.extra-row { display: flex; align-items: center; gap: 0.4rem; }

.totals {
    margin: 0;
    padding-top: 0.85rem;
    border-top: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.total-line {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.9rem;
}

.total-line dt { color: var(--ink-soft); margin: 0; }
.total-line dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; }
.total-line.deduct dd { color: var(--bad); }

.total-line.grand {
    padding-top: 0.6rem;
    margin-top: 0.2rem;
    border-top: 1px solid var(--line);
    font-size: 1.05rem;
}

.total-line.grand dt { color: var(--ink); font-weight: 700; }
.total-line.grand dd { font-weight: 800; font-size: 1.2rem; }

.settlement {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-radius: 10px;
    font-weight: 700;
}

.settlement-value { font-variant-numeric: tabular-nums; font-size: 1.05rem; }
.settlement.settled { background: var(--ok-soft); color: var(--ok); }
.settlement.owing { background: var(--warn-soft); color: var(--warn); }
.settlement.credit { background: #eef2fb; color: #22406e; }

.shortcuts {
    margin: 0;
    text-align: center;
    font-size: 0.76rem;
    color: var(--ink-mute);
}

.shortcuts .dot { margin: 0 0.4rem; }

/* ── Mobile action bar ──────────────────────────────────────────────── */
.mobile-bar { display: none; }

@media (max-width: 1100px) {
    .builder-grid { grid-template-columns: minmax(0, 1fr); }
    .summary-rail { position: static; }
}

@media (max-width: 720px) {
    .line-fields { grid-template-columns: 1fr 1fr; }

    .header-actions .el-button--large { display: none; }

    .mobile-bar {
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.7rem 1rem;
        background: var(--surface);
        border-top: 1px solid var(--line);
        box-shadow: 0 -6px 20px -12px rgba(18, 28, 44, 0.4);
    }

    .mobile-total { display: flex; flex-direction: column; }
    .mobile-total span { font-size: 0.72rem; color: var(--ink-mute); }
    .mobile-total strong { font-size: 1.1rem; font-variant-numeric: tabular-nums; }
}

@media (max-width: 480px) {
    .line-fields { grid-template-columns: 1fr; }
    .allocation-row { grid-template-columns: 1fr; }
    .allocation-remove { justify-self: end; }
}
</style>
