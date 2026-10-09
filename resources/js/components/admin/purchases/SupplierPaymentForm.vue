<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('record_supplier_payment')"
        :width="dialogWidth"
        class="supplier-payment-dialog"
        :close-on-click-modal="false"
        :before-close="(done) => requestClose(done)"
        @open="reset"
    >
        <el-form label-position="top" class="pay-form" @submit.prevent>
            <el-form-item :label="$t('supplier')" required>
                <el-select
                    v-model="form.supplier_id"
                    filterable
                    :placeholder="$t('pret_choose_supplier')"
                    style="width: 100%"
                    @change="onSupplierChange"
                >
                    <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id">
                        <div class="supplier-option">
                            <span>{{ s.name }}</span>
                            <span class="supplier-option-balance" :class="balanceTone(s.balance)">
                                {{ balanceText(s) }}
                            </span>
                        </div>
                    </el-option>
                </el-select>
            </el-form-item>

            <!-- Where the supplier stands, and what to pay against -->
            <div v-if="selectedSupplier" class="position">
                <div class="position-row">
                    <span class="position-label">{{ balanceLabel }}</span>
                    <strong class="position-value" :class="balanceTone(balance)">
                        {{ formatCurrency(Math.abs(balance), currency) }}
                    </strong>
                </div>

                <div class="allocate">
                    <span class="allocate-title">{{ $t('spay_pay_against') }}</span>
                    <el-skeleton v-if="receiptsLoading" :rows="1" animated />
                    <div v-else class="allocate-options" role="radiogroup">
                        <button
                            type="button"
                            role="radio"
                            class="allocate-option"
                            :class="{ 'is-on': !form.purchase_receipt_id }"
                            :aria-checked="!form.purchase_receipt_id"
                            @click="chooseAccount"
                        >
                            <span class="allocate-name">{{ $t('spay_on_account') }}</span>
                            <span class="allocate-meta">{{ $t('spay_on_account_hint') }}</span>
                        </button>
                        <button
                            v-for="receipt in openReceipts"
                            :key="receipt.id"
                            type="button"
                            role="radio"
                            class="allocate-option"
                            :class="{ 'is-on': form.purchase_receipt_id === receipt.id }"
                            :aria-checked="form.purchase_receipt_id === receipt.id"
                            @click="chooseReceipt(receipt)"
                        >
                            <span class="allocate-name" dir="ltr">{{ receipt.receipt_number }}</span>
                            <span class="allocate-meta">
                                {{ formatDate(receipt.receipt_date) }} ·
                                {{ $t('spay_due_of', {
                                    due: formatCurrency(receipt.due_amount, receipt.currency || currency),
                                    total: formatCurrency(receipt.total_amount, receipt.currency || currency),
                                }) }}
                            </span>
                        </button>
                    </div>
                    <span v-if="!receiptsLoading && !openReceipts.length" class="field-hint">{{ $t('spay_no_open_receipts') }}</span>
                </div>
            </div>

            <div class="field-grid">
                <el-form-item :label="$t('amount')" required :error="amountError">
                    <el-input-number
                        v-model="form.amount"
                        :min="0"
                        :precision="2"
                        :controls="false"
                        :placeholder="'0.00'"
                        style="width: 100%"
                        :value-on-clear="null"
                    />
                    <div class="quick-amounts">
                        <button v-if="selectedReceipt" type="button" class="chip" @click="form.amount = round2(selectedReceipt.due_amount)">
                            {{ $t('spay_fill_receipt_due') }}
                        </button>
                        <button v-else-if="balance > 0" type="button" class="chip" @click="form.amount = round2(balance)">
                            {{ $t('spay_fill_full_balance') }}
                        </button>
                        <button
                            v-if="payable > 0"
                            type="button"
                            class="chip"
                            @click="form.amount = round2(payable / 2)"
                        >
                            {{ $t('spay_fill_half') }}
                        </button>
                    </div>
                </el-form-item>

                <el-form-item :label="$t('payment_date')">
                    <el-date-picker
                        v-model="form.payment_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                        :disabled-date="isFuture"
                        style="width: 100%"
                    />
                </el-form-item>
            </div>

            <el-form-item :label="$t('payment_method')" required>
                <div class="methods" role="radiogroup">
                    <button
                        v-for="method in METHODS"
                        :key="method.value"
                        type="button"
                        role="radio"
                        class="method"
                        :class="{ 'is-on': form.payment_method === method.value }"
                        :aria-checked="form.payment_method === method.value"
                        @click="form.payment_method = method.value"
                    >
                        <el-icon :size="18"><component :is="method.icon" /></el-icon>
                        <span>{{ paymentMethodLabel(method.value) }}</span>
                    </button>
                </div>
            </el-form-item>

            <el-form-item :label="referenceLabel">
                <el-input v-model="form.reference" maxlength="100" :placeholder="referencePlaceholder" dir="auto" />
            </el-form-item>

            <el-form-item :label="$t('notes')">
                <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
            </el-form-item>

            <!-- Paying more than is owed is allowed — it becomes an advance
                 against future purchases — but it should never be silent. -->
            <div v-if="selectedSupplier && amount > 0" class="after" :class="{ 'is-advance': balanceAfter < -0.009 }">
                <el-icon><component :is="balanceAfter < -0.009 ? WarningFilled : InfoFilled" /></el-icon>
                <span v-if="balanceAfter > 0.009">{{ $t('spay_after_owed', { amount: formatCurrency(balanceAfter, currency) }) }}</span>
                <span v-else-if="balanceAfter < -0.009">{{ $t('spay_after_advance', { amount: formatCurrency(-balanceAfter, currency) }) }}</span>
                <span v-else>{{ $t('spay_after_settled') }}</span>
            </div>
        </el-form>

        <template #footer>
            <span v-if="blocker" class="blocker">{{ blocker }}</span>
            <el-button @click="requestClose()">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :loading="saving" :disabled="!!blocker" @click="submit">
                {{ amount > 0 ? $t('spay_record_amount', { amount: formatCurrency(amount, currency) }) : $t('record_supplier_payment') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CreditCard, InfoFilled, Money, Tickets, WarningFilled } from '@element-plus/icons-vue';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { useSupplierPaymentsStore } from '@/stores/supplierPayments';
import { apiErrorMessage, formatCurrency, formatDate, localIsoDate, paymentMethodLabel } from '@/utils/sales';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** Suppliers with their balances (the outstanding list). */
    suppliers: { type: Array, default: () => [] },
    presetSupplierId: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);
const { t } = useI18n();
const store = useSupplierPaymentsStore();

// No card here: paying a supplier by card is not something this business does,
// and offering it would put a method on the document that the ledger would
// then have to treat as a bank movement anyway.
const METHODS = [
    { value: 'bank_transfer', icon: CreditCard },
    { value: 'cash', icon: Money },
    { value: 'check', icon: Tickets },
];

const form = reactive({
    supplier_id: null,
    purchase_receipt_id: null,
    amount: null,
    payment_method: 'bank_transfer',
    payment_date: localIsoDate(),
    reference: '',
    notes: '',
});

const saving = computed(() => store.saving);
const dialogWidth = computed(() => (window.innerWidth < 640 ? '94%' : '580px'));

let snapshot = '';
const serialize = () => JSON.stringify(form);

const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100;

// ── Supplier position ────────────────────────────────────────────────────
const selectedSupplier = computed(() => props.suppliers.find((s) => s.id === form.supplier_id) || null);
const balance = computed(() => Number(selectedSupplier.value?.balance) || 0);
const currency = computed(() => selectedSupplier.value?.currency || undefined);
const amount = computed(() => Number(form.amount) || 0);
const balanceAfter = computed(() => balance.value - amount.value);

const balanceLabel = computed(() => {
    if (balance.value > 0.009) return t('spay_currently_owed');
    if (balance.value < -0.009) return t('spay_currently_advance');
    return t('spay_currently_settled');
});

const balanceTone = (value) => (Number(value) > 0.009 ? 'is-owed' : Number(value) < -0.009 ? 'is-advance' : 'is-settled');

const balanceText = (supplier) => {
    const value = Number(supplier.balance) || 0;
    if (Math.abs(value) < 0.01) return t('spay_settled');
    const text = formatCurrency(Math.abs(value), supplier.currency);
    return value > 0 ? text : t('spay_advance_short', { amount: text });
};

// ── Receipts still owing ─────────────────────────────────────────────────
const receipts = ref([]);
const receiptsLoading = ref(false);

const openReceipts = computed(() => receipts.value
    .filter((r) => Number(r.due_amount) > 0.009)
    // Oldest first: settling the oldest debt is the usual order.
    .sort((a, b) => String(a.receipt_date).localeCompare(String(b.receipt_date))));

const selectedReceipt = computed(() => openReceipts.value.find((r) => r.id === form.purchase_receipt_id) || null);

// What this payment can reasonably cover: the receipt when one is chosen,
// otherwise the whole balance.
const payable = computed(() => (selectedReceipt.value ? Number(selectedReceipt.value.due_amount) : Math.max(0, balance.value)));

const loadReceipts = async () => {
    const supplierId = form.supplier_id;
    receipts.value = [];
    if (!supplierId) return;
    receiptsLoading.value = true;
    try {
        const res = await purchaseReceiptsApi.getAll({ supplier_id: supplierId, per_page: 100 });
        if (form.supplier_id === supplierId) receipts.value = res.data?.data?.receipts || [];
    } catch {
        receipts.value = [];
    } finally {
        receiptsLoading.value = false;
    }
};

const onSupplierChange = () => {
    form.purchase_receipt_id = null;
    // Settling in full is the common case; the field stays editable.
    form.amount = balance.value > 0 ? round2(balance.value) : null;
    loadReceipts();
};

const chooseAccount = () => {
    form.purchase_receipt_id = null;
    form.amount = balance.value > 0 ? round2(balance.value) : form.amount;
};

const chooseReceipt = (receipt) => {
    form.purchase_receipt_id = receipt.id;
    form.amount = round2(receipt.due_amount);
};

// ── Method-specific wording ──────────────────────────────────────────────
const referenceLabel = computed(() => ({
    bank_transfer: t('spay_ref_transfer'),
    check: t('spay_ref_check'),
    cash: t('spay_ref_cash'),
}[form.payment_method] || t('reference')));

const referencePlaceholder = computed(() => ({
    bank_transfer: t('spay_ref_transfer_ph'),
    check: t('spay_ref_check_ph'),
    cash: t('spay_ref_cash_ph'),
}[form.payment_method] || ''));

// ── Validation ───────────────────────────────────────────────────────────
// The server refuses a receipt paid past what it still owes; say so here.
const amountError = computed(() => {
    if (selectedReceipt.value && amount.value > Number(selectedReceipt.value.due_amount) + 0.01) {
        return t('spay_more_than_receipt', { amount: formatCurrency(selectedReceipt.value.due_amount, currency.value) });
    }
    return '';
});

const blocker = computed(() => {
    if (!form.supplier_id) return t('pret_need_supplier');
    if (!(amount.value > 0)) return t('spay_need_amount');
    if (amountError.value) return amountError.value;
    return '';
});

const isFuture = (date) => date.getTime() > Date.now();

// ── Open, close, save ────────────────────────────────────────────────────
const reset = () => {
    Object.assign(form, {
        supplier_id: props.presetSupplierId,
        purchase_receipt_id: null,
        amount: null,
        payment_method: 'bank_transfer',
        payment_date: localIsoDate(),
        reference: '',
        notes: '',
    });
    receipts.value = [];
    if (form.supplier_id) {
        form.amount = balance.value > 0 ? round2(balance.value) : null;
        loadReceipts();
    }
    snapshot = serialize();
};

const requestClose = async (done) => {
    if (saving.value) return;
    if (serialize() !== snapshot) {
        try {
            await ElMessageBox.confirm(t('spay_discard_confirm'), t('confirm'), {
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

const submit = async () => {
    if (blocker.value) return;
    try {
        const { payment, supplierBalance } = await store.createPayment({
            supplier_id: form.supplier_id,
            purchase_receipt_id: form.purchase_receipt_id || undefined,
            amount: amount.value,
            payment_method: form.payment_method,
            payment_date: form.payment_date,
            reference: form.reference || null,
            notes: form.notes || null,
        });

        ElMessage.success(supplierBalance !== undefined
            ? t('spay_recorded_balance', { amount: formatCurrency(supplierBalance, currency.value) })
            : t('supplier_payment_recorded'));
        snapshot = serialize();
        emit('saved', payment);
        emit('update:modelValue', false);
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_record_supplier_payment')));
    }
};
</script>

<style scoped>
.supplier-option { display: flex; justify-content: space-between; gap: 1rem; width: 100%; }
.supplier-option-balance { font-size: 0.8rem; font-variant-numeric: tabular-nums; }

.is-owed { color: #b91c1c; }
.is-advance { color: #7c3aed; }
.is-settled { color: #64748b; }

.position {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.8rem 0.9rem;
    margin-bottom: 1rem;
}
.position-row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
.position-label { font-size: 0.82rem; color: #64748b; }
.position-value { font-size: 1.15rem; font-variant-numeric: tabular-nums; }

.allocate { margin-top: 0.75rem; }
.allocate-title { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem; }
.allocate-options { display: flex; flex-direction: column; gap: 0.35rem; max-height: 210px; overflow-y: auto; }
.allocate-option {
    all: unset;
    box-sizing: border-box;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.5rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
}
.allocate-option:hover { border-color: #93c5fd; }
.allocate-option:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.allocate-option.is-on { border-color: #2563eb; background: #eff6ff; }
.allocate-name { font-weight: 600; color: #0f172a; }
.allocate-meta { font-size: 0.78rem; color: #64748b; }

.field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0 1rem; }
.field-hint { display: block; font-size: 0.78rem; color: #64748b; margin-top: 0.35rem; }

.quick-amounts { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.4rem; }
.chip {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    border-radius: 999px;
    padding: 0.1rem 0.65rem;
    font-size: 0.76rem;
    cursor: pointer;
    font-family: inherit;
}
.chip:hover { border-color: #93c5fd; color: #2563eb; }

.methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; width: 100%; }
.method {
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.85rem;
    color: #475569;
    text-align: center;
}
.method:hover { border-color: #93c5fd; }
.method:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.method.is-on { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 600; }

.after {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.6rem 0.8rem;
    border-radius: 10px;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.85rem;
    line-height: 1.6;
}
.after .el-icon { margin-top: 0.2rem; flex-shrink: 0; }
.after.is-advance { background: #fffbeb; color: #92400e; }

.blocker { font-size: 0.8rem; color: #64748b; margin-inline-end: auto; }
:deep(.el-dialog__footer) { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; }
</style>
