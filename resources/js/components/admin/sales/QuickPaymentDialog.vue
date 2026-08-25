<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('record_payment')"
        width="540px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @open="onOpen"
        @closed="onClosed"
    >
        <div class="payment-number" v-if="nextPaymentNumber">
            <span class="label">{{ $t('payment_number') }}</span>
            <strong class="value">{{ nextPaymentNumber }}</strong>
        </div>

        <!-- Fixed invoice context -->
        <div v-if="invoice" class="invoice-context">
            <div class="context-row">
                <span>{{ $t('invoice') }}</span>
                <strong>{{ invoice.invoice_number }}</strong>
            </div>
            <div class="context-row">
                <span>{{ $t('total') }}</span>
                <strong>{{ formatCurrency(invoice.total) }}</strong>
            </div>
            <div class="context-row">
                <span>{{ $t('paid_amount') }}</span>
                <strong class="paid">{{ formatCurrency(invoice.paid_amount) }}</strong>
            </div>
            <div class="context-row highlight">
                <span>{{ $t('due_amount') }}</span>
                <strong class="due">{{ formatCurrency(dueAmount) }}</strong>
            </div>
        </div>

        <el-form ref="formRef" :model="form" label-position="top">
            <!-- Customer quick search -->
            <el-form-item v-if="!invoice" :label="$t('client')" prop="customer_id">
                <el-select
                    v-model="form.customer_id"
                    filterable
                    remote
                    clearable
                    style="width: 100%"
                    :placeholder="$t('search_customer')"
                    :remote-method="searchCustomers"
                    :loading="customersStore.loading"
                    @change="onCustomerChange"
                >
                    <el-option
                        v-for="customer in customersStore.customers"
                        :key="customer.id"
                        :value="customer.id"
                        :label="customer.name"
                    >
                        <div class="customer-option">
                            <div>{{ customer.name }}</div>
                            <small v-if="customer.phone">{{ customer.phone }}</small>
                        </div>
                    </el-option>
                </el-select>
            </el-form-item>

            <!-- Outstanding invoices quick picker -->
            <div v-if="!invoice && form.customer_id" class="outstanding-block">
                <div class="block-title">
                    <span>{{ $t('unpaid_invoices') }}</span>
                    <span v-if="outstandingInvoices.length" class="block-sum">
                        {{ formatCurrency(totalOutstanding) }}
                    </span>
                </div>
                <div v-if="outstandingLoading" class="block-empty">{{ $t('loading') }}</div>
                <template v-else>
                    <div
                        v-for="inv in outstandingInvoices"
                        :key="inv.id"
                        class="invoice-chip"
                        :class="{ active: form.invoice_id === inv.id }"
                        @click="pickInvoice(inv)"
                    >
                        <span class="chip-num">{{ inv.invoice_number }}</span>
                        <span class="chip-due">{{ formatCurrency(invoiceDue(inv)) }}</span>
                    </div>
                    <div v-if="!outstandingInvoices.length" class="block-empty">
                        {{ $t('no_outstanding_invoices') }}
                    </div>
                </template>
            </div>

            <el-form-item :label="$t('amount')" prop="amount">
                <div class="amount-row">
                    <el-input-number
                        v-model="form.amount"
                        :min="0.01"
                        :max="maxAmount"
                        :precision="amountPrecision"
                        :step="10"
                        controls-position="right"
                        style="flex: 1"
                    />
                    <el-select v-model="form.currency" style="width: 110px" @change="onCurrencyChange">
                        <el-option
                            v-for="option in currencyList"
                            :key="option.code"
                            :value="option.code"
                            :label="option.code"
                        />
                    </el-select>
                </div>
                <div v-if="!isBaseCurrency" class="field-hint">
                    <template v-if="baseEquivalent !== null">
                        {{ $t('equivalent_to') }} {{ formatCurrency(baseEquivalent) }}
                        <span class="rate-note">({{ $t('exchange_rate') }}: {{ selectedRate }})</span>
                    </template>
                    <span v-else class="warning">{{ $t('no_exchange_rate_set') }}</span>
                </div>
            </el-form-item>

            <!-- POS-style method buttons -->
            <div class="method-label">{{ $t('payment_method') }}</div>
            <div class="method-grid">
                <button
                    v-for="method in PAYMENT_METHODS"
                    :key="method"
                    type="button"
                    class="method-btn"
                    :class="{ active: form.payment_method === method }"
                    @click="form.payment_method = method"
                >
                    <i :class="methodIcon(method)"></i>
                    <span>{{ paymentMethodLabel(method) }}</span>
                </button>
            </div>

            <el-form-item :label="$t('payment_date')" class="mt-3">
                <el-date-picker
                    v-model="form.payment_date"
                    type="date"
                    value-format="YYYY-MM-DD"
                    style="width: 100%"
                />
            </el-form-item>

            <el-collapse v-if="!invoice" class="advanced-collapse">
                <el-collapse-item :title="$t('advanced_options')">
                    <el-form-item :label="$t('reference')">
                        <el-input v-model="form.reference" maxlength="100" />
                    </el-form-item>
                    <el-form-item :label="$t('notes')">
                        <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
                    </el-form-item>
                </el-collapse-item>
            </el-collapse>
        </el-form>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
            <el-button
                v-if="dueAmount > 0"
                type="success"
                :loading="paymentsStore.saving"
                @click="submit(true)"
            >
                <el-icon class="mr-1"><Checked /></el-icon>
                {{ $t('collect_full_amount') }}
            </el-button>
            <el-button type="primary" :loading="paymentsStore.saving" @click="submit(false)">
                {{ $t('save') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { Checked } from '@element-plus/icons-vue';
import { usePaymentsStore } from '@/stores/payments';
import { useCustomersStore } from '@/stores/customers';
import { invoicesApi } from '@/api/invoices';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/Composables/useCurrency';

const { t } = useI18n();
import {
    formatCurrency,
    paymentMethodLabel,
    PAYMENT_METHODS,
    apiErrorMessage,
    invoiceDue,
    isInvoiceSettled,
} from '@/utils/sales';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // When provided the dialog records against this invoice specifically.
    invoice: { type: Object, default: null },
    // Preselects the customer when opening from a customer row.
    customerId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const paymentsStore = usePaymentsStore();
const customersStore = useCustomersStore();
const settingsStore = useSettingsStore();
const { baseCode } = useCurrency();

const formRef = ref(null);
const outstandingLoading = ref(false);
const outstandingInvoices = ref([]);

const form = reactive({
    customer_id: null,
    invoice_id: null,
    amount: 0,
    currency: baseCode.value,
    payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
});

const METHOD_ICONS = {
    cash: 'fas fa-money-bill-wave',
    card: 'fas fa-credit-card',
    bank_transfer: 'fas fa-university',
    check: 'fas fa-money-check-alt',
};

const methodIcon = (method) => METHOD_ICONS[method] || 'fas fa-wallet';

/** Best-effort preview of the next auto-generated payment number. */
const nextPaymentNumber = computed(() => {
    const count = paymentsStore.pagination.total ?? paymentsStore.payments.length;
    return count ? `PAY-${String(count + 1).padStart(5, '0')}` : '';
});

const selectedInvoice = computed(() => {
    if (props.invoice) return props.invoice;
    return outstandingInvoices.value.find((inv) => inv.id === form.invoice_id) || null;
});

const dueAmount = computed(() => (selectedInvoice.value ? invoiceDue(selectedInvoice.value) : 0));

const totalOutstanding = computed(() =>
    outstandingInvoices.value.reduce((sum, inv) => sum + invoiceDue(inv), 0)
);

const currencyList = computed(() => (
    settingsStore.currencies?.length ? settingsStore.currencies : [{ code: baseCode.value }]
));

const isBaseCurrency = computed(() => form.currency === baseCode.value);

const selectedCurrencyInfo = computed(() => currencyList.value.find((c) => c.code === form.currency));

const amountPrecision = computed(() => {
    const places = Number(selectedCurrencyInfo.value?.decimal_places);
    return Number.isFinite(places) ? places : 2;
});

/** Units of the selected currency per one unit of the base — from CurrencyService. */
const selectedRate = computed(() => Number(selectedCurrencyInfo.value?.rate) || null);

const baseEquivalent = computed(() => {
    if (isBaseCurrency.value) return form.amount;
    if (!selectedRate.value) return null;
    return form.amount / selectedRate.value;
});

const onCurrencyChange = () => {
    if (dueAmount.value > 0) {
        form.amount = isBaseCurrency.value
            ? Number(dueAmount.value.toFixed(2))
            : Number((dueAmount.value * (selectedRate.value || 1)).toFixed(amountPrecision.value));
    }
};

const maxAmount = computed(() => {
    if (!selectedInvoice.value) return 9999999;
    const dueInCurrency = isBaseCurrency.value
        ? dueAmount.value
        : dueAmount.value * (selectedRate.value || 1);
    return Math.max(dueInCurrency * 2, 1);
});

const searchCustomers = (query) => {
    customersStore.fetchCustomers(query ? { search: query, per_page: 30 } : { per_page: 30 }).catch(() => {});
};

const onCustomerChange = (customerId) => {
    form.invoice_id = null;
    form.amount = 0;
    if (!customerId) {
        outstandingInvoices.value = [];
        return;
    }
    loadOutstanding(customerId);
};

const loadOutstanding = async (customerId) => {
    outstandingLoading.value = true;
    try {
        const res = await invoicesApi.getAll({ customer_id: customerId, per_page: 100 });
        const data = res.data?.data || {};
        const rows = data.invoices || (Array.isArray(res.data) ? res.data : []);
        outstandingInvoices.value = rows.filter((inv) => !isInvoiceSettled(inv));
    } catch (err) {
        outstandingInvoices.value = [];
    } finally {
        outstandingLoading.value = false;
    }
};

const pickInvoice = (inv) => {
    form.invoice_id = inv.id;
    const due = invoiceDue(inv);
    form.amount = isBaseCurrency.value
        ? Number(due.toFixed(2))
        : Number((due * (selectedRate.value || 1)).toFixed(amountPrecision.value));
};

const onOpen = () => {
    form.payment_date = new Date().toISOString().slice(0, 10);
    form.reference = '';
    form.notes = '';
    form.payment_method = 'cash';
    form.currency = baseCode.value;
    if (!settingsStore.currencies.length) settingsStore.fetch().catch(() => {});

    if (props.invoice) {
        form.invoice_id = props.invoice.id;
        form.customer_id = props.invoice.customer_id ?? props.invoice.customer?.id ?? null;
        form.amount = dueAmount.value > 0 ? Number(dueAmount.value.toFixed(2)) : 0;
        outstandingInvoices.value = [];
        return;
    }

    form.invoice_id = null;
    form.customer_id = props.customerId ?? null;
    form.amount = 0;

    if (form.customer_id) {
        loadOutstanding(form.customer_id);
    } else {
        outstandingInvoices.value = [];
    }
    searchCustomers('');
};

const onClosed = () => {
    outstandingInvoices.value = [];
};

const submit = async (full) => {
    if (!form.customer_id) {
        ElMessage.warning(t('cannot_determine_payment_customer'));
        return;
    }

    let amount = form.amount;
    if (full && dueAmount.value > 0) {
        amount = isBaseCurrency.value
            ? Number(dueAmount.value.toFixed(2))
            : Number((dueAmount.value * (selectedRate.value || 1)).toFixed(amountPrecision.value));
    }
    if (!amount || amount <= 0) {
        ElMessage.warning(t('enter_amount_above_zero'));
        return;
    }

    try {
        await paymentsStore.createPayment({
            customer_id: form.customer_id,
            invoice_id: form.invoice_id || null,
            amount,
            currency: form.currency,
            payment_method: form.payment_method,
            payment_date: form.payment_date,
            reference: form.reference || null,
            notes: form.notes || null,
        });

        ElMessage.success(t('payment_recorded'));
        emit('update:modelValue', false);
        emit('saved');
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_record_payment')));
    }
};
</script>

<style scoped>
.amount-row {
    display: flex;
    gap: 0.5rem;
}

.field-hint {
    font-size: 0.8rem;
    margin-top: 0.35rem;
    color: var(--el-text-color-regular, #5f6d85);
}

.field-hint.warning,
.field-hint .warning {
    color: var(--el-color-warning, #e6a23c);
}

.rate-note {
    color: var(--el-text-color-secondary, #909399);
}

.payment-number {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--el-color-primary-light-9, #ecf5ff);
    border: 1px dashed var(--el-color-primary-light-5, #b3d8ff);
    border-radius: 0.5rem;
    padding: 0.5rem 0.85rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.payment-number .label {
    color: var(--el-text-color-regular, #5f6d85);
}

.payment-number .value {
    color: var(--el-color-primary, #409eff);
    letter-spacing: 0.03em;
}

.invoice-context {
    background: var(--el-fill-color-light, #f5f7fa);
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
    margin-bottom: 1.25rem;
    display: grid;
    gap: 0.5rem;
}

.context-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: var(--el-text-color-regular, #5f6d85);
}

.context-row.highlight {
    border-top: 1px dashed var(--el-border-color, #dcdfe6);
    padding-top: 0.5rem;
    font-size: 1rem;
}

.context-row .paid {
    color: var(--el-color-success, #67c23a);
}

.context-row .due {
    color: var(--el-color-danger, #f56c6c);
}

.customer-option {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.customer-option small {
    color: var(--el-text-color-secondary, #909399);
    font-size: 0.78rem;
}

.outstanding-block {
    background: var(--el-fill-color-light, #f5f7fa);
    border-radius: 0.75rem;
    padding: 0.75rem 0.85rem;
    margin-bottom: 1.1rem;
}

.block-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: var(--el-text-color-regular, #5f6d85);
    margin-bottom: 0.6rem;
}

.block-sum {
    color: var(--el-color-danger, #f56c6c);
    font-weight: 600;
}

.block-empty {
    font-size: 0.82rem;
    color: var(--el-text-color-secondary, #909399);
    padding: 0.35rem 0;
}

.invoice-chip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px solid var(--el-border-color-lighter, #e4e7ed);
    border-radius: 0.5rem;
    padding: 0.55rem 0.85rem;
    margin-bottom: 0.45rem;
    cursor: pointer;
    transition: all 0.15s ease;
}

.invoice-chip:hover {
    border-color: var(--el-color-primary-light-3, #79bbff);
}

.invoice-chip.active {
    border-color: var(--el-color-primary, #409eff);
    background: var(--el-color-primary-light-9, #ecf5ff);
}

.invoice-chip .chip-num {
    font-size: 0.85rem;
    color: var(--el-text-color-primary, #303133);
}

.invoice-chip .chip-due {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--el-color-danger, #f56c6c);
}

.method-label {
    font-size: 0.8rem;
    color: var(--el-text-color-regular, #5f6d85);
    margin-bottom: 0.5rem;
}

.method-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.method-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.65rem 0.3rem;
    border: 1px solid var(--el-border-color, #dcdfe6);
    border-radius: 0.6rem;
    background: #fff;
    cursor: pointer;
    font-size: 0.75rem;
    color: var(--el-text-color-regular, #5f6d85);
    transition: all 0.15s ease;
}

.method-btn i {
    font-size: 1.05rem;
}

.method-btn:hover {
    border-color: var(--el-color-primary-light-3, #79bbff);
}

.method-btn.active {
    border-color: var(--el-color-primary, #409eff);
    background: var(--el-color-primary-light-9, #ecf5ff);
    color: var(--el-color-primary, #409eff);
    font-weight: 600;
}

.advanced-collapse {
    margin-top: 0.35rem;
}

.mt-3 {
    margin-top: 0.9rem;
}

.mr-1 {
    margin-inline-end: 0.3rem;
}
</style>
