<template>
    <div class="rma-builder">
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <header class="builder-header">
            <div class="header-identity">
                <span class="eyebrow">{{ t('rma') }}</span>
                <h1>{{ isEdit ? t('edit_return_request') : t('create_return_request') }}</h1>
                <p>{{ t('rma_form_subtitle') }}</p>
            </div>

            <div class="header-actions">
                <el-button text @click="goBack">
                    <el-icon><ArrowRight /></el-icon>
                    {{ t('back_to_home') }}
                </el-button>
                <el-button
                    type="primary"
                    size="large"
                    :loading="saving"
                    :disabled="!canSubmit"
                    @click="submitForm"
                >
                    <el-icon><Check /></el-icon>
                    {{ isEdit ? t('update_and_save_changes') : t('create_the_return_request') }}
                </el-button>
            </div>
        </header>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" v-loading="loading">
            <div class="builder-grid">
                <!-- ── Work area ──────────────────────────────────────────── -->
                <section class="work-area">
                    <!-- Customer -->
                    <div class="panel-card">
                        <h2 class="panel-title">{{ t('returning_customer') }}</h2>

                        <el-form-item prop="customer_id" class="bare-item">
                            <el-select
                                v-model="form.customer_id"
                                :placeholder="t('search_customer_by_name_or_phone')"
                                filterable
                                remote
                                reserve-keyword
                                clearable
                                :remote-method="searchCustomers"
                                :loading="customersLoading"
                                class="full-width"
                                :disabled="isEdit"
                                @change="onCustomerChange"
                                @focus="onCustomerFocus"
                            >
                                <el-option
                                    v-for="customer in customers"
                                    :key="customer.id"
                                    :value="customer.id"
                                    :label="customer.name"
                                >
                                    <div class="customer-option">
                                        <div class="customer-option-header">
                                            <span class="customer-option-name">{{ customer.name }}</span>
                                            <span class="customer-option-id">#{{ customer.id }}</span>
                                        </div>
                                        <div class="customer-option-details">
                                            <span class="customer-option-meta" v-if="customer.phone">
                                                <el-icon><Phone /></el-icon> <span>{{ customer.phone }}</span>
                                            </span>
                                            <span class="customer-option-meta" v-if="customer.email">
                                                <el-icon><Message /></el-icon> <span>{{ customer.email }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </el-option>
                            </el-select>
                        </el-form-item>

                        <div class="customer-summary" v-if="selectedCustomer">
                            <div class="customer-summary-header">
                                <div class="customer-avatar"><el-icon><User /></el-icon></div>
                                <div class="customer-identity">
                                    <strong>{{ selectedCustomer.name }}</strong>
                                    <span>{{ t('customer_number_label') }}: #{{ selectedCustomer.id }}</span>
                                </div>
                                <div class="customer-stats">
                                    <div class="stat-pill">
                                        <span class="stat-value">{{ customerStats.totalOrders }}</span>
                                        <span class="stat-label">{{ t('total_orders') }}</span>
                                    </div>
                                    <div class="stat-pill">
                                        <span class="stat-value">{{ customerStats.totalReturns }}</span>
                                        <span class="stat-label">{{ t('rma') }}</span>
                                    </div>
                                    <div class="stat-pill">
                                        <span class="stat-value">{{ customerStats.availableOrders }}</span>
                                        <span class="stat-label">{{ t('returnable_orders') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="customer-contact" v-if="selectedCustomer.phone || selectedCustomer.email">
                                <span v-if="selectedCustomer.phone"><el-icon><Phone /></el-icon> {{ selectedCustomer.phone }}</span>
                                <span v-if="selectedCustomer.email"><el-icon><Message /></el-icon> {{ selectedCustomer.email }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice -->
                    <div class="panel-card">
                        <h2 class="panel-title">{{ t('linked_original_invoice') }}</h2>

                        <el-form-item prop="invoice_id" class="bare-item">
                            <el-select
                                v-model="form.invoice_id"
                                :placeholder="t('select_original_invoice')"
                                filterable
                                clearable
                                class="full-width"
                                :disabled="!form.customer_id || isEdit"
                                :loading="invoicesLoading"
                                @change="loadInvoiceItems"
                            >
                                <el-option
                                    v-for="invoice in invoices"
                                    :key="invoice.id"
                                    :value="invoice.id"
                                    :label="invoice.invoice_number"
                                />
                            </el-select>
                        </el-form-item>
                        <p class="helper-text" v-if="!form.customer_id">{{ t('select_customer_to_see_orders') }}</p>
                        <p class="helper-text" v-else-if="!invoicesLoading && !invoices.length">
                            {{ t('no_delivered_orders_for_customer') }}
                        </p>
                    </div>

                    <!-- Returned items -->
                    <div class="panel-card items-panel">
                        <div class="panel-header-row">
                            <h2 class="panel-title">{{ t('returned_products') }}</h2>
                            <span class="count-badge" v-if="invoiceItems.length">
                                {{ t('selected_of_total', { selected: selectedItemsCount, total: invoiceItems.length }) }}
                            </span>
                        </div>

                        <div v-if="!invoiceItems.length" class="empty-state">
                            <el-icon><ShoppingCart /></el-icon>
                            <p>{{ t('select_customer_and_invoice_for_items') }}</p>
                        </div>

                        <div v-else class="return-lines">
                            <article
                                v-for="item in invoiceItems"
                                :key="item.invoice_item_id"
                                class="return-line"
                                :class="{ 'is-selected': item.selected }"
                            >
                                <label class="line-select">
                                    <el-checkbox v-model="item.selected" />
                                    <div class="line-identity">
                                        <span class="line-name">{{ item.product_name }}</span>
                                        <span class="line-meta">
                                            {{ formatCurrency(item.unit_price) }}
                                            <span class="dot">·</span>
                                            {{ t('invoice_quantity_label', { count: item.original_quantity }) }}
                                        </span>
                                    </div>
                                    <span class="line-refund" v-if="item.selected">{{ formatCurrency(lineRefundEstimate(item)) }}</span>
                                </label>

                                <div class="line-fields" v-if="item.selected">
                                    <label class="field">
                                        <span class="field-label">{{ t('return_quantity') }}</span>
                                        <el-input-number
                                            v-model="item.quantity"
                                            :min="1"
                                            :max="item.original_quantity"
                                            size="default"
                                            class="full-width"
                                        />
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ t('received_condition') }}</span>
                                        <el-select v-model="item.condition" class="full-width">
                                            <el-option value="new" :label="t('condition_new')" />
                                            <el-option value="used" :label="t('condition_used')" />
                                            <el-option value="damaged" :label="t('condition_damaged')" />
                                            <el-option value="missing" :label="t('condition_missing')" />
                                        </el-select>
                                    </label>

                                    <label class="field">
                                        <span class="field-label">{{ t('settlement_method') }}</span>
                                        <el-select v-model="item.resolution" class="full-width">
                                            <el-option value="refund" :label="t('refund')" />
                                            <el-option value="exchange" :label="t('exchange')" />
                                            <el-option value="repair" :label="t('repair')" />
                                            <el-option value="discard" :label="t('scrap')" />
                                        </el-select>
                                    </label>

                                    <label class="field" v-if="item.resolution === 'exchange'">
                                        <span class="field-label">{{ t('replacement_product') }}</span>
                                        <el-select
                                            v-model="item.exchange_product_id"
                                            :placeholder="t('search_replacement_product')"
                                            filterable
                                            remote
                                            reserve-keyword
                                            clearable
                                            :remote-method="searchExchangeProducts"
                                            :loading="catalogLoading"
                                            class="full-width"
                                            @focus="onExchangeFocus"
                                        >
                                            <el-option
                                                v-for="prod in catalogProducts"
                                                :key="prod.id"
                                                :value="prod.id"
                                                :label="prod.name_ar || prod.name_en"
                                            >
                                                <div class="product-option">
                                                    <span class="product-option-name">{{ prod.name_ar || prod.name_en }}</span>
                                                    <span class="product-option-stock" :class="stockTone(prod.stock_quantity)">
                                                        {{ t('available') }} {{ prod.stock_quantity ?? 0 }}
                                                    </span>
                                                </div>
                                            </el-option>
                                        </el-select>
                                    </label>
                                </div>

                                <label class="line-note-field" v-if="item.selected">
                                    <span class="field-label">{{ t('notes') }}</span>
                                    <el-input v-model="item.notes" :placeholder="t('internal_notes_placeholder')" size="default" />
                                </label>
                            </article>
                        </div>
                    </div>
                </section>

                <!-- ── Summary rail ───────────────────────────────────────── -->
                <aside class="summary-rail">
                    <div class="rail-card">
                        <h3 class="rail-title">{{ t('settlement_type_and_handling') }}</h3>

                        <label class="field">
                            <el-select v-model="form.return_type" :placeholder="t('select_default_handling')" class="full-width">
                                <el-option value="refund" :label="t('refund_compensation')" />
                                <el-option value="exchange" :label="t('exchange_for_another_product')" />
                                <el-option value="store_credit" :label="t('store_credit_to_wallet')" />
                            </el-select>
                        </label>

                        <el-form-item prop="reason" class="bare-item">
                            <label class="field">
                                <span class="field-label">{{ t('main_return_reason') }}</span>
                                <el-select v-model="form.reason" :placeholder="t('select_main_return_reason')" class="full-width">
                                    <el-option value="defective" :label="t('reason_defective')" />
                                    <el-option value="damaged" :label="t('reason_damaged')" />
                                    <el-option value="wrong_item" :label="t('reason_wrong_item')" />
                                    <el-option value="not_as_described" :label="t('reason_not_as_described')" />
                                    <el-option value="changed_mind" :label="t('reason_changed_mind')" />
                                    <el-option value="other" :label="t('reason_other')" />
                                </el-select>
                            </label>
                        </el-form-item>

                        <el-form-item prop="reason_description" class="bare-item">
                            <label class="field">
                                <span class="field-label">{{ t('additional_return_details') }}</span>
                                <el-input
                                    v-model="form.reason_description"
                                    type="textarea"
                                    :rows="3"
                                    :placeholder="t('additional_return_details_placeholder')"
                                />
                            </label>
                        </el-form-item>
                    </div>

                    <div class="rail-card">
                        <button type="button" class="extras-toggle" @click="showAddress = !showAddress">
                            <el-icon><component :is="showAddress ? Minus : Plus" /></el-icon>
                            {{ t('pickup_address_optional') }}
                        </button>

                        <div v-if="showAddress" class="extras-body">
                            <el-form-item prop="return_address.address_line1" class="bare-item">
                                <label class="field">
                                    <span class="field-label">{{ t('main_address') }}</span>
                                    <el-input v-model="form.return_address.address_line1" :placeholder="t('address_line_placeholder')" />
                                </label>
                            </el-form-item>
                            <div class="fields-row">
                                <el-form-item prop="return_address.city" class="bare-item">
                                    <label class="field">
                                        <span class="field-label">{{ t('city') }}</span>
                                        <el-input v-model="form.return_address.city" :placeholder="t('city')" />
                                    </label>
                                </el-form-item>
                                <el-form-item prop="return_address.country" class="bare-item">
                                    <label class="field">
                                        <span class="field-label">{{ t('country') }}</span>
                                        <el-input v-model="form.return_address.country" :placeholder="t('country')" />
                                    </label>
                                </el-form-item>
                            </div>
                        </div>
                    </div>

                    <div class="rail-card">
                        <h3 class="rail-title">{{ t('handling_and_follow_up_notes') }}</h3>
                        <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="t('internal_notes_placeholder')" />
                    </div>

                    <div class="rail-card totals-card" v-if="selectedItemsCount">
                        <dl class="totals">
                            <div class="total-line grand">
                                <dt>{{ t('estimated_compensation_summary') }}</dt>
                                <dd>{{ formatCurrency(estimatedRefundTotal) }}</dd>
                            </div>
                        </dl>
                        <p class="totals-note">{{ t('compensation_rate_note') }}</p>
                    </div>
                </aside>
            </div>
        </el-form>

        <!-- On a phone the summary rail is far below the lines, so the figure
             that decides whether to save follows the screen. -->
        <div class="mobile-bar">
            <div class="mobile-total">
                <span>{{ t('estimated_compensation_summary') }}</span>
                <strong>{{ formatCurrency(estimatedRefundTotal) }}</strong>
            </div>
            <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submitForm">
                {{ isEdit ? t('update_and_save_changes') : t('create_the_return_request') }}
            </el-button>
        </div>
    </div>
</template>

<script setup>
import { formatMoney } from '@/utils/currency'
import { ref, reactive, computed, onMounted } from 'vue'
import { ArrowRight, Check, Minus, Plus, ShoppingCart, User, Phone, Message } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import rmaService from '@/services/rma'
import api from '@/api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const rmaId = computed(() => (route.params.id ? parseInt(route.params.id) : null))
const isEdit = computed(() => !!rmaId.value)
const goBack = () => router.push('/admin/rma')

// Mirrors RmaController::RMA_ELIGIBLE_INVOICE_STATUSES — confirmed is where
// an invoice actually sits once a sale is finalized in this business; the
// shipping stages (processing/shipped) never happen, and restricting to
// delivered-only left the picker permanently empty.
const RMA_ELIGIBLE_INVOICE_STATUSES = ['confirmed', 'delivered']

const formRef = ref(null)
const loading = ref(false)
const saving = ref(false)
const showAddress = ref(false)

const customersLoading = ref(false)
const invoicesLoading = ref(false)
const catalogLoading = ref(false)

const customers = ref([])
const invoices = ref([])
const invoiceItems = ref([])
const catalogProducts = ref([])
const selectedCustomer = ref(null)
const customerStats = ref({ totalOrders: 0, totalReturns: 0, availableOrders: 0 })

const form = reactive({
    customer_id: null,
    invoice_id: null,
    reason: 'defective',
    return_type: 'refund',
    reason_description: '',
    notes: '',
    return_address: { address_line1: '', city: '', country: '', postal_code: '' },
})

/** Only enforced once the operator has started filling in a pickup address — the whole section is optional. */
const addressStarted = () => {
    const addr = form.return_address
    return !!(addr.address_line1 || addr.city || addr.country)
}
const requiredIfAddressStarted = (message) => (rule, value, callback) => {
    if (addressStarted() && !value) {
        callback(new Error(message))
    } else {
        callback()
    }
}

const rules = {
    customer_id: [{ required: true, message: t('please_select_returning_customer'), trigger: 'change' }],
    invoice_id: [{ required: true, message: t('please_select_original_invoice'), trigger: 'change' }],
    reason: [{ required: true, message: t('return_reason_required'), trigger: 'change' }],
    reason_description: [{ max: 1000, message: t('description_max_1000'), trigger: 'blur' }],
    'return_address.address_line1': [{ validator: requiredIfAddressStarted(t('address_required_for_pickup')), trigger: 'blur' }],
    'return_address.city': [{ validator: requiredIfAddressStarted(t('city_required_for_pickup')), trigger: 'blur' }],
    'return_address.country': [{ validator: requiredIfAddressStarted(t('country_required_for_pickup')), trigger: 'blur' }],
}

const selectedItemsCount = computed(() => invoiceItems.value.filter((i) => i.selected).length)

const canSubmit = computed(() => !saving.value && !!form.customer_id && !!form.invoice_id && selectedItemsCount.value > 0)

const lineRefundEstimate = (item) => {
    const multiplier = { new: 1.0, used: 0.7, damaged: 0.5, missing: 0.0 }[item.condition] ?? 0.5
    return item.unit_price * multiplier * (item.quantity || 0)
}

const estimatedRefundTotal = computed(() =>
    invoiceItems.value.filter((i) => i.selected).reduce((sum, item) => sum + lineRefundEstimate(item), 0)
)

// Debounced so typing a name doesn't fire a request per keystroke.
let customerSearchTimer = null
const searchCustomers = (query = '') => {
    clearTimeout(customerSearchTimer)
    customersLoading.value = true

    customerSearchTimer = setTimeout(async () => {
        try {
            const params = { per_page: 50 }
            if (query) params.search = query

            const response = await rmaService.getCustomersWithOrders(params)
            const customersData = response.data.data?.data || response.data.data || response.data || []

            // Keep the already-picked customer in the list so el-select doesn't hide it.
            const list = Array.isArray(customersData) ? customersData : []
            if (selectedCustomer.value && !list.some((c) => c.id === selectedCustomer.value.id)) {
                list.push(selectedCustomer.value)
            }
            customers.value = list
        } catch (error) {
            console.error('Failed to search customers:', error)
            customers.value = selectedCustomer.value ? [selectedCustomer.value] : []
        } finally {
            customersLoading.value = false
        }
    }, 300)
}

const onCustomerFocus = () => {
    if (customers.value.length === 0) searchCustomers('')
}

/**
 * Loads the invoices a return can be raised against.
 *
 * RMA is filed against invoices, not sales orders — this business creates
 * invoices directly and the sales-orders table is never populated.
 * RmaController::store() rejects any invoice that is not confirmed/delivered
 * and any invoice that does not belong to the chosen customer, so both
 * constraints are pushed to the API.
 */
const loadInvoices = async (customerId = null) => {
    invoicesLoading.value = true
    try {
        const params = { per_page: 200, status: RMA_ELIGIBLE_INVOICE_STATUSES }
        if (customerId) params.customer_id = customerId

        const response = await api.get('/admin/invoices', { params })
        const invoicesData = response.data.data?.invoices || response.data.data || response.data || []
        invoices.value = Array.isArray(invoicesData) ? invoicesData : []
    } catch (error) {
        console.error('Failed to load invoices:', error)
        ElMessage.error(t('failed_to_load_sales_orders'))
        invoices.value = []
    } finally {
        invoicesLoading.value = false
    }
}

const onCustomerChange = async () => {
    form.invoice_id = null
    invoiceItems.value = []

    if (!form.customer_id) {
        selectedCustomer.value = null
        customerStats.value = { totalOrders: 0, totalReturns: 0, availableOrders: 0 }
        invoices.value = []
        return
    }

    const customerId = form.customer_id
    selectedCustomer.value = customers.value.find((c) => c.id === customerId)

    await loadInvoices(customerId)
    await loadCustomerStats(customerId)
}

const loadCustomerStats = async (customerId) => {
    // invoices.value already holds exactly this customer's return-eligible
    // invoices (loadInvoices() requested { customer_id, status: RMA_ELIGIBLE_INVOICE_STATUSES }).
    const availableOrders = invoices.value.length

    // The customer's real invoice history, across every status — a separate,
    // lightweight (per_page: 1) call just for the count.
    let totalOrders = availableOrders
    try {
        const totalResponse = await api.get('/admin/invoices', { params: { customer_id: customerId, per_page: 1 } })
        totalOrders = totalResponse.data.data?.pagination?.total ?? availableOrders
    } catch (error) {
        // Fall back to the delivered count rather than showing nothing.
    }

    let totalReturns = 0
    try {
        const response = await api.get('/admin/rma', { params: { customer_id: customerId, per_page: 1 } })
        totalReturns = response.data.data?.total || 0
    } catch (error) {
        // If RMA stats fail, the order stats still stand.
    }

    customerStats.value = { totalOrders, totalReturns, availableOrders }
}

const mapInvoiceItem = (item, overrides = {}) => ({
    invoice_item_id: item.id,
    product_id: item.product_id,
    product_name: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
    original_quantity: item.quantity,
    quantity: 1,
    unit_price: parseFloat(item.unit_price) || 0,
    condition: 'new',
    resolution: form.return_type,
    selected: false,
    exchange_product_id: null,
    notes: '',
    ...overrides,
})

const loadInvoiceItems = async () => {
    if (!form.invoice_id) {
        invoiceItems.value = []
        return
    }
    loading.value = true
    try {
        const response = await api.get(`/admin/invoices/${form.invoice_id}`)
        const invoice = response.data.data || response.data

        if (!invoice || !invoice.items?.length) {
            invoiceItems.value = []
            ElMessage.warning(t('no_products_in_invoice'))
            return
        }

        if (!RMA_ELIGIBLE_INVOICE_STATUSES.includes(invoice.status)) {
            ElMessage.warning(t('invoice_must_be_delivered'))
            invoiceItems.value = []
            return
        }

        invoiceItems.value = invoice.items.map((item) => mapInvoiceItem(item))
    } catch (error) {
        console.error('Failed to load invoice items:', error)
        ElMessage.error(t('failed_to_load_invoice_products'))
        invoiceItems.value = []
    } finally {
        loading.value = false
    }
}

// Debounced for the same reason the customer search is.
let exchangeSearchTimer = null
const searchExchangeProducts = (query = '') => {
    clearTimeout(exchangeSearchTimer)
    catalogLoading.value = true

    exchangeSearchTimer = setTimeout(async () => {
        try {
            const response = await api.get('/admin/products', { params: { search: query, per_page: 50 } })
            const data = response.data.data || response.data || []
            catalogProducts.value = Array.isArray(data) ? data : []
        } catch (error) {
            console.error('Failed to search replacement products:', error)
        } finally {
            catalogLoading.value = false
        }
    }, 300)
}

const onExchangeFocus = () => {
    if (catalogProducts.value.length === 0) searchExchangeProducts('')
}

const stockTone = (quantity) => {
    const value = Number(quantity) || 0
    if (value <= 0) return 'out'
    return value <= 5 ? 'low' : 'ok'
}

const loadRma = async () => {
    loading.value = true
    try {
        const response = await rmaService.getRmaRequest(rmaId.value)
        const rma = response.data.data || response.data

        form.customer_id = rma.customer_id
        form.invoice_id = rma.invoice_id
        form.reason = rma.reason || 'defective'
        form.return_type = rma.type || 'refund'
        form.reason_description = rma.reason_description || ''
        form.notes = rma.admin_notes || ''
        form.return_address = rma.return_address || { address_line1: '', city: '', country: '', postal_code: '' }
        showAddress.value = addressStarted()

        if (rma.customer) {
            selectedCustomer.value = rma.customer
            if (!customers.value.some((c) => c.id === rma.customer.id)) {
                customers.value.push(rma.customer)
            }
        }

        // Fetch this customer's invoices so the picker can render the current
        // selection (invoices are no longer preloaded globally on mount).
        await loadInvoices(rma.customer_id)

        // The linked invoice may no longer be `delivered` (or may sit outside
        // the page); keep it in the list so editing does not silently blank
        // the field.
        if (rma.invoice_id && !invoices.value.some((inv) => inv.id === rma.invoice_id)) {
            invoices.value = [
                ...invoices.value,
                rma.invoice || { id: rma.invoice_id, invoice_number: `#${rma.invoice_id}` },
            ]
        }

        await loadCustomerStats(rma.customer_id)

        // Load the original invoice's lines so quantity/price context survives
        // even for a line the customer returned all of.
        const invoiceResponse = await api.get(`/admin/invoices/${rma.invoice_id}`)
        const invoice = invoiceResponse.data.data || invoiceResponse.data
        const originalItemsById = {}
        invoice?.items?.forEach((item) => { originalItemsById[item.id] = item })

        invoiceItems.value = (rma.items || []).map((item) => {
            const originalItem = originalItemsById[item.invoice_item_id]
            return {
                invoice_item_id: item.invoice_item_id,
                product_id: item.product_id,
                product_name: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
                original_quantity: originalItem ? originalItem.quantity : item.quantity_requested,
                quantity: item.quantity_requested,
                unit_price: parseFloat(originalItem?.unit_price ?? (item.refund_amount / item.quantity_requested)) || 0,
                condition: item.condition || 'new',
                resolution: item.resolution || 'refund',
                selected: true,
                exchange_product_id: item.exchange_product_id,
                notes: item.notes || '',
            }
        })

        const exchangeItem = (rma.items || []).find((i) => i.exchange_product)
        if (exchangeItem) catalogProducts.value = [exchangeItem.exchange_product]
    } catch (error) {
        console.error('Failed to load RMA data:', error)
        ElMessage.error(t('failed_to_load_return_request'))
        router.back()
    } finally {
        loading.value = false
    }
}

const submitForm = async () => {
    if (!formRef.value) return

    const selectedItems = invoiceItems.value.filter((i) => i.selected)
    if (selectedItems.length === 0) {
        ElMessage.warning(t('select_at_least_one_product_to_return'))
        return
    }

    for (const item of selectedItems) {
        if (item.resolution === 'exchange' && !item.exchange_product_id) {
            ElMessage.error(t('select_replacement_for_product', { product: item.product_name }))
            return
        }
        if (item.quantity > item.original_quantity) {
            ElMessage.error(t('return_quantity_exceeds_original', {
                quantity: item.quantity,
                original: item.original_quantity,
                product: item.product_name,
            }))
            return
        }
    }

    try {
        await formRef.value.validate()
    } catch {
        return
    }

    saving.value = true
    try {
        const data = {
            customer_id: form.customer_id,
            invoice_id: form.invoice_id,
            reason: form.reason,
            type: form.return_type,
            reason_description: form.reason_description,
            admin_notes: form.notes,
            return_address: form.return_address,
            items: selectedItems.map((item) => ({
                invoice_item_id: item.invoice_item_id,
                quantity_requested: item.quantity,
                condition: item.condition,
                resolution: item.resolution,
                exchange_product_id: item.exchange_product_id,
                notes: item.notes || '',
            })),
        }

        if (isEdit.value) {
            await rmaService.updateRmaRequest(rmaId.value, data)
            ElMessage.success(t('return_request_updated'))
        } else {
            await rmaService.createRmaRequest(data)
            ElMessage.success(t('return_request_created'))
        }
        router.push('/admin/rma')
    } catch (error) {
        console.error('Failed to save RMA:', error)
        const errors = error.response?.data?.errors
        if (errors) {
            ElMessage.error(Object.values(errors).flat()[0] || t('failed_to_save_return_request'))
        } else {
            ElMessage.error(error.response?.data?.message || t('failed_to_save_return_request'))
        }
    } finally {
        saving.value = false
    }
}

const formatCurrency = (val) => formatMoney(val)

onMounted(async () => {
    // Customers and invoices are both remote-searched (see onCustomerFocus /
    // onCustomerChange); there is nothing useful to preload here.
    if (isEdit.value) {
        await loadRma()
    }
})
</script>

<style scoped>
.rma-builder {
    --surface: #ffffff;
    --ground: #f8fafc;
    --line: #e2e8f0;
    --ink: #0f172a;
    --ink-soft: #334155;
    --ink-mute: #64748b;
    --primary: #2563eb;
    --primary-soft: #eff6ff;
    --ok: #16a34a;
    --ok-soft: #f0fdf4;
    --warn: #d97706;
    --bad: #dc2626;

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
    color: var(--primary);
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

/* ── Shared panel chrome ────────────────────────────────────────────── */
.panel-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
}

.panel-title {
    margin: 0 0 0.85rem;
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--ink);
}

.panel-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
}

.panel-header-row .panel-title { margin: 0; }

.count-badge {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-soft);
    border-radius: 999px;
    padding: 0.2rem 0.7rem;
    white-space: nowrap;
}

.bare-item :deep(.el-form-item__label) { display: none; }
.bare-item { margin-bottom: 0; }

.full-width { width: 100%; }

.field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.field-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ink-mute);
}

.helper-text {
    margin: 0.65rem 0 0;
    font-size: 0.82rem;
    color: var(--ink-mute);
    line-height: 1.5;
}

/* ── Customer search + summary ─────────────────────────────────────── */
.customer-option { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.2rem 0; }
.customer-option-header { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.customer-option-name {
    font-weight: 700;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-option-id {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--ink-mute);
    background: var(--ground);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    flex: none;
}
.customer-option-details { display: flex; gap: 0.75rem; align-items: center; min-width: 0; }
.customer-option-meta {
    font-size: 0.76rem;
    color: var(--ink-mute);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 0;
    overflow: hidden;
}
.customer-option-meta span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.customer-summary {
    margin-top: 1rem;
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 1rem;
}

.customer-summary-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.customer-avatar {
    width: 44px;
    height: 44px;
    flex: none;
    display: grid;
    place-items: center;
    background: var(--primary-soft);
    color: var(--primary);
    border-radius: 10px;
    font-size: 1.15rem;
}

.customer-identity { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.customer-identity strong { font-size: 0.95rem; }
.customer-identity span { font-size: 0.78rem; color: var(--ink-mute); }

.customer-stats { display: flex; gap: 0.6rem; margin-inline-start: auto; flex-wrap: wrap; }
.stat-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    padding: 0.4rem 0.75rem;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 8px;
    min-width: 64px;
}
.stat-value { font-weight: 800; font-size: 1.05rem; color: var(--primary); }
.stat-label { font-size: 0.68rem; color: var(--ink-mute); font-weight: 600; }

.customer-contact {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 0.75rem;
    margin-top: 0.75rem;
    border-top: 1px solid var(--line);
    font-size: 0.82rem;
    color: var(--ink-soft);
}
.customer-contact span { display: flex; align-items: center; gap: 0.35rem; }

/* ── Return lines ───────────────────────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--ink-mute);
    background: var(--ground);
    border-radius: 12px;
    border: 2px dashed var(--line);
}
.empty-state .el-icon { font-size: 2.25rem; color: #bfdbfe; }
.empty-state p { margin: 0; font-size: 0.9rem; font-weight: 600; max-width: 22rem; line-height: 1.6; }

.return-lines { display: flex; flex-direction: column; gap: 0.75rem; }

.return-line {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.return-line.is-selected { border-color: #bfdbfe; background: var(--primary-soft); }

.line-select {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.line-identity { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; flex: 1; }
.line-name { font-weight: 700; }
.line-meta { font-size: 0.78rem; color: var(--ink-mute); }
.line-meta .dot { margin: 0 0.3rem; }

.line-refund {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--ok);
    flex: none;
}

.line-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--line);
}

.line-note-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.6rem;
}

/* ── Exchange product option ───────────────────────────────────────── */
.product-option { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; width: 100%; }
.product-option-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.product-option-stock { font-size: 0.78rem; flex: none; font-variant-numeric: tabular-nums; }
.product-option-stock.ok { color: var(--ok); }
.product-option-stock.low { color: var(--warn); }
.product-option-stock.out { color: var(--bad); }

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

.rail-title { margin: 0; font-size: 0.95rem; font-weight: 700; }

.fields-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.extras-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    background: none;
    border: none;
    padding: 0;
    font: inherit;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ink-soft);
    cursor: pointer;
}

.extras-body { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem; }

.totals-card { background: var(--ok-soft); border-color: #bbf7d0; }

.totals { margin: 0; }

.total-line.grand { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.total-line.grand dt { margin: 0; font-weight: 700; color: #166534; }
.total-line.grand dd { margin: 0; font-weight: 800; font-size: 1.2rem; color: #15803d; font-variant-numeric: tabular-nums; }

.totals-note { margin: 0; font-size: 0.78rem; color: #166534; opacity: 0.85; line-height: 1.5; }

/* ── Mobile action bar ──────────────────────────────────────────────── */
.mobile-bar { display: none; }

@media (max-width: 1100px) {
    .builder-grid { grid-template-columns: minmax(0, 1fr); }
    .summary-rail { position: static; }
}

@media (max-width: 720px) {
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
    .mobile-total strong { font-size: 1.05rem; font-variant-numeric: tabular-nums; }

    .customer-stats { margin-inline-start: 0; width: 100%; justify-content: flex-start; }
}

@media (max-width: 480px) {
    .fields-row { grid-template-columns: 1fr; }
    .line-fields { grid-template-columns: 1fr; }
}
</style>
