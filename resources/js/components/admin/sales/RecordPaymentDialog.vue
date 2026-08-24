<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('record_payment')"
        width="520px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @open="onOpen"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <!-- When opened from an invoice the target is fixed, so show it as
                 context rather than an editable field. -->
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

            <el-form-item v-if="!invoice" :label="$t('client')" prop="customer_id">
                <el-select
                    v-model="form.customer_id"
                    filterable
                    clearable
                    style="width: 100%"
                    :placeholder="$t('select_customer')"
                    :loading="customersStore.loading"
                >
                    <el-option
                        v-for="customer in customersStore.customers"
                        :key="customer.id"
                        :value="customer.id"
                        :label="customer.name"
                    />
                </el-select>
            </el-form-item>

            <el-form-item v-if="!invoice" :label="$t('invoice')">
                <el-select
                    v-model="form.invoice_id"
                    filterable
                    clearable
                    style="width: 100%"
                    :placeholder="$t('optional_link_to_invoice')"
                >
                    <el-option
                        v-for="option in linkableInvoices"
                        :key="option.id"
                        :value="option.id"
                        :label="`${option.invoice_number} — ${formatCurrency(option.due_amount ?? option.total)}`"
                    />
                </el-select>
            </el-form-item>

            <el-form-item :label="$t('amount')" prop="amount">
                <div class="amount-row">
                    <el-input-number
                        v-model="form.amount"
                        :min="0.01"
                        :max="maxAmount"
                        :precision="amountPrecision"
                        :step="10"
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
                <div v-if="invoice && isBaseCurrency && form.amount > dueAmount" class="field-hint warning">
                    {{ $t('amount_exceeds_due') }}
                </div>
            </el-form-item>

            <el-form-item :label="$t('payment_method')" prop="payment_method">
                <el-select v-model="form.payment_method" style="width: 100%">
                    <el-option
                        v-for="method in PAYMENT_METHODS"
                        :key="method"
                        :value="method"
                        :label="paymentMethodLabel(method)"
                    />
                </el-select>
            </el-form-item>

            <el-form-item :label="$t('payment_date')">
                <el-date-picker
                    v-model="form.payment_date"
                    type="date"
                    value-format="YYYY-MM-DD"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item :label="$t('reference')">
                <el-input v-model="form.reference" maxlength="100" show-word-limit />
            </el-form-item>

            <el-form-item :label="$t('notes')">
                <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :loading="paymentsStore.saving" @click="submit">
                {{ $t('save') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { usePaymentsStore } from '@/stores/payments';
import { useCustomersStore } from '@/stores/customers';
import { useInvoicesStore } from '@/stores/invoices';
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
const invoicesStore = useInvoicesStore();
const settingsStore = useSettingsStore();
const { baseCode } = useCurrency();

const formRef = ref(null);

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

const dueAmount = computed(() => (props.invoice ? invoiceDue(props.invoice) : 0));

/**
 * Every currency the payer may hand over — the base currency first, so it
 * stays the default, followed by whichever others an admin has priced.
 * A currency with no rate on file still appears here (the field is only
 * about *what was handed over*); the server is the one that refuses it if
 * there is no rate to convert it with.
 */
const currencyList = computed(() => (
    settingsStore.currencies?.length ? settingsStore.currencies : [{ code: baseCode.value }]
));

const isBaseCurrency = computed(() => form.currency === baseCode.value);

const selectedCurrencyInfo = computed(() => currencyList.value.find((c) => c.code === form.currency));

/** decimal_places belongs to the currency — SYP is quoted in whole units. */
const amountPrecision = computed(() => {
    const places = Number(selectedCurrencyInfo.value?.decimal_places);
    return Number.isFinite(places) ? places : 2;
});

/** Units of the selected currency per one unit of the base — from CurrencyService. */
const selectedRate = computed(() => Number(selectedCurrencyInfo.value?.rate) || null);

/** What the typed amount comes to in the base currency, for the payer's own reference — the server converts authoritatively when it saves. */
const baseEquivalent = computed(() => {
    if (isBaseCurrency.value) return form.amount;
    if (!selectedRate.value) return null;
    return form.amount / selectedRate.value;
});

const onCurrencyChange = () => {
    // Re-express the prefilled due amount in the newly chosen currency so the
    // field does not keep showing a base-currency figure under a foreign
    // currency's label.
    if (props.invoice && dueAmount.value > 0) {
        form.amount = isBaseCurrency.value
            ? Number(dueAmount.value.toFixed(2))
            : Number((dueAmount.value * (selectedRate.value || 1)).toFixed(amountPrecision.value));
    }
};

// Leave headroom for over-payments/refund corrections rather than hard-blocking.
// Expressed in the selected currency, since the due amount itself is in base.
const maxAmount = computed(() => {
    if (!props.invoice) return 9999999;
    const dueInCurrency = isBaseCurrency.value
        ? dueAmount.value
        : dueAmount.value * (selectedRate.value || 1);
    return Math.max(dueInCurrency * 2, 1);
});

/** Only invoices that still owe something are worth linking a payment to. */
const linkableInvoices = computed(() => {
    const rows = invoicesStore.invoices || [];
    if (!form.customer_id) return rows;
    return rows.filter((invoice) => {
        const owner = invoice.customer_id ?? invoice.customer?.id;
        return !owner || owner === form.customer_id;
    });
});

const rules = {
    customer_id: [{ required: true, message: t('select_customer'), trigger: 'change' }],
    amount: [{ required: true, message: t('enter_the_amount'), trigger: 'blur' }],
    payment_method: [{ required: true, message: t('choose_payment_method'), trigger: 'change' }],
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
    } else {
        form.invoice_id = null;
        form.customer_id = props.customerId ?? null;
        form.amount = 0;
        if (!customersStore.customers.length) customersStore.fetchCustomers().catch(() => {});
        if (!invoicesStore.invoices.length) invoicesStore.fetchInvoices().catch(() => {});
    }
};

const submit = async () => {
    // The API requires customer_id even when an invoice is supplied.
    if (!form.customer_id) {
        ElMessage.warning(t('cannot_determine_payment_customer'));
        return;
    }
    if (!form.amount || form.amount <= 0) {
        ElMessage.warning(t('enter_amount_above_zero'));
        return;
    }

    try {
        await paymentsStore.createPayment({
            customer_id: form.customer_id,
            invoice_id: form.invoice_id || null,
            amount: form.amount,
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

.rate-note {
    color: var(--el-text-color-secondary, #909399);
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

.field-hint {
    font-size: 0.8rem;
    margin-top: 0.35rem;
}

.field-hint.warning,
.field-hint .warning {
    color: var(--el-color-warning, #e6a23c);
}
</style>
