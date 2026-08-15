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
                <el-input-number
                    v-model="form.amount"
                    :min="0.01"
                    :max="maxAmount"
                    :precision="2"
                    :step="10"
                    style="width: 100%"
                />
                <div v-if="invoice && form.amount > dueAmount" class="field-hint warning">
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

const formRef = ref(null);

const form = reactive({
    customer_id: null,
    invoice_id: null,
    amount: 0,
    payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
});

const dueAmount = computed(() => (props.invoice ? invoiceDue(props.invoice) : 0));

// Leave headroom for over-payments/refund corrections rather than hard-blocking.
const maxAmount = computed(() => (props.invoice ? Math.max(dueAmount.value * 2, 1) : 9999999));

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

.field-hint.warning {
    color: var(--el-color-warning, #e6a23c);
}
</style>
