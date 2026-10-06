<template>
    <el-dialog
        :model-value="modelValue"
        :title="dialogTitle"
        :width="isNarrow ? '94%' : '560px'"
        :close-on-click-modal="false"
        class="expense-dialog"
        @update:model-value="$emit('update:modelValue', $event)"
        @open="onOpen"
    >
        <!-- Context banner when editing -->
        <div v-if="isEditing" class="edit-context-banner">
            <div class="banner-item">
                <span class="label">{{ $t('expense_number') }}</span>
                <strong class="mono" dir="ltr">{{ expense?.expense_number }}</strong>
            </div>
            <div v-if="expense?.creator" class="banner-item">
                <span class="label">{{ $t('pret_recorded_by') }}</span>
                <span>{{ expense.creator.name }}</span>
            </div>
            <div class="banner-item">
                <span class="label">{{ $t('spay_recorded_at') }}</span>
                <span>{{ formatDate(expense?.created_at) }}</span>
            </div>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
            <!-- Description -->
            <el-form-item :label="$t('description')" prop="description" required>
                <el-input
                    v-model="form.description"
                    maxlength="255"
                    show-word-limit
                    :placeholder="$t('pay_expense_search')"
                    clearable
                />
            </el-form-item>

            <!-- Amount and Date in two columns -->
            <div class="dialog-grid-2">
                <el-form-item :label="$t('amount')" prop="amount" required>
                    <el-input-number
                        v-model="form.amount"
                        :min="0.01"
                        :precision="2"
                        :step="10"
                        :controls="false"
                        style="width: 100%"
                    />
                </el-form-item>

                <el-form-item :label="$t('expense_date')" prop="expense_date" required>
                    <el-date-picker
                        v-model="form.expense_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
            </div>

            <!-- Category selection with icons -->
            <el-form-item :label="$t('category')" prop="category" required>
                <div class="category-selector">
                    <button
                        v-for="cat in CATEGORIES"
                        :key="cat.value"
                        type="button"
                        class="category-card"
                        :class="[cat.value, { 'is-selected': form.category === cat.value }]"
                        @click="form.category = cat.value"
                    >
                        <i :class="cat.icon"></i>
                        <span class="cat-label">{{ cat.label }}</span>
                    </button>
                </div>
            </el-form-item>

            <!-- Payment / Accrual status -->
            <el-form-item :label="$t('expense_status')" prop="status" required>
                <div class="status-selector">
                    <button
                        type="button"
                        class="status-card s-paid"
                        :class="{ 'is-selected': form.status === 'paid' }"
                        @click="form.status = 'paid'"
                    >
                        <div class="card-head">
                            <i class="fas fa-circle-check"></i>
                            <strong>{{ $t('pay_expense_status_paid') }}</strong>
                        </div>
                        <p class="card-desc">{{ $t('pay_expense_paid_desc') }}</p>
                    </button>

                    <button
                        type="button"
                        class="status-card s-pending"
                        :class="{ 'is-selected': form.status === 'pending' }"
                        @click="form.status = 'pending'"
                    >
                        <div class="card-head">
                            <i class="fas fa-clock"></i>
                            <strong>{{ $t('pay_expense_status_pending') }}</strong>
                        </div>
                        <p class="card-desc">{{ $t('pay_expense_pending_desc') }}</p>
                    </button>
                </div>
                <div v-if="isEditing" class="status-secondary-row">
                    <el-radio-group v-model="form.status" size="small">
                        <el-radio-button value="paid">{{ $t('expense_paid') }}</el-radio-button>
                        <el-radio-button value="pending">{{ $t('expense_pending') }}</el-radio-button>
                        <el-radio-button value="approved">{{ $t('pay_expense_status_approved') }}</el-radio-button>
                        <el-radio-button value="rejected">{{ $t('pay_expense_status_rejected') }}</el-radio-button>
                    </el-radio-group>
                </div>
            </el-form-item>

            <!-- Optional links: Invoice & Customer -->
            <div class="dialog-grid-2">
                <el-form-item :label="$t('pay_expense_link_invoice')" prop="invoice_id">
                    <el-select
                        v-model="form.invoice_id"
                        filterable
                        remote
                        clearable
                        style="width: 100%"
                        :placeholder="$t('invoice')"
                        :remote-method="searchInvoices"
                        :loading="loadingInvoices"
                        @change="onInvoiceSelected"
                    >
                        <el-option
                            v-for="inv in invoiceOptions"
                            :key="inv.id"
                            :value="inv.id"
                            :label="inv.invoice_number"
                        >
                            <div class="select-option-row">
                                <span class="mono" dir="ltr">{{ inv.invoice_number }}</span>
                                <span class="option-muted" v-if="inv.customer?.name">{{ inv.customer.name }}</span>
                                <strong class="option-amount">{{ formatCurrency(inv.total) }}</strong>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('pay_expense_link_customer')" prop="customer_id">
                    <el-select
                        v-model="form.customer_id"
                        filterable
                        remote
                        clearable
                        style="width: 100%"
                        :placeholder="$t('client')"
                        :remote-method="searchCustomers"
                        :loading="loadingCustomers"
                    >
                        <el-option
                            v-for="cust in customerOptions"
                            :key="cust.id"
                            :value="cust.id"
                            :label="cust.name"
                        >
                            <div class="select-option-row">
                                <span>{{ cust.name }}</span>
                                <small v-if="cust.phone" class="option-muted" dir="ltr">{{ cust.phone }}</small>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>
            </div>

            <!-- Notes -->
            <el-form-item :label="$t('notes')" prop="notes">
                <el-input
                    v-model="form.notes"
                    type="textarea"
                    :rows="2"
                    maxlength="1000"
                    show-word-limit
                    :placeholder="$t('notes')"
                />
            </el-form-item>

            <!-- Ledger hint when editing -->
            <el-alert
                v-if="isEditing"
                type="info"
                show-icon
                :closable="false"
                class="ledger-notice"
                :title="$t('pay_expense_ledger_edit_hint')"
            />
        </el-form>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="submit">
                {{ isEditing ? $t('save_changes') : $t('save') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { expensesApi } from '@/api/expenses';
import { invoicesApi } from '@/api/invoices';
import { customersApi } from '@/api/customers';
import { apiErrorMessage, formatCurrency, formatDate, localIsoDate } from '@/utils/sales';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    expense: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const { t } = useI18n();

const formRef = ref(null);
const saving = ref(false);
const isNarrow = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);

const isEditing = computed(() => !!props.expense?.id);

const dialogTitle = computed(() => {
    if (isEditing.value) {
        return t('pay_expense_edit_title', { number: props.expense.expense_number || `#${props.expense.id}` });
    }
    return t('pay_expense_new_title');
});

const CATEGORIES = computed(() => [
    { value: 'shipping', label: t('shipping'), icon: 'fas fa-truck' },
    { value: 'packaging', label: t('packaging'), icon: 'fas fa-box-open' },
    { value: 'handling', label: t('process'), icon: 'fas fa-dolly' },
    { value: 'other', label: t('subject_other'), icon: 'fas fa-receipt' },
]);

const form = reactive({
    description: '',
    amount: 0,
    category: 'shipping',
    status: 'paid',
    expense_date: localIsoDate(),
    invoice_id: null,
    customer_id: null,
    notes: '',
});

const rules = {
    description: [{ required: true, message: t('enter_expense_description'), trigger: 'blur' }],
    amount: [{ required: true, message: t('enter_amount_above_zero'), trigger: 'change' }],
    expense_date: [{ required: true, message: t('date'), trigger: 'change' }],
};

// Invoices search
const loadingInvoices = ref(false);
const invoiceOptions = ref([]);

const searchInvoices = async (query = '') => {
    loadingInvoices.value = true;
    try {
        const res = await invoicesApi.getAll({ search: query?.trim() || undefined, per_page: 20 });
        const data = res.data?.data;
        const list = Array.isArray(data?.invoices) ? data.invoices : (Array.isArray(data) ? data : []);
        invoiceOptions.value = list;
    } catch {
        invoiceOptions.value = [];
    } finally {
        loadingInvoices.value = false;
    }
};

const onInvoiceSelected = (invId) => {
    if (!invId) return;
    const inv = invoiceOptions.value.find((i) => i.id === invId);
    if (inv && (inv.customer_id || inv.customer?.id) && !form.customer_id) {
        form.customer_id = inv.customer_id || inv.customer?.id;
        if (inv.customer && !customerOptions.value.some((c) => c.id === form.customer_id)) {
            customerOptions.value.push(inv.customer);
        }
    }
};

// Customers search
const loadingCustomers = ref(false);
const customerOptions = ref([]);

const searchCustomers = async (query = '') => {
    loadingCustomers.value = true;
    try {
        const res = await customersApi.getAll({ search: query?.trim() || undefined, per_page: 20 });
        const data = res.data?.data;
        const list = Array.isArray(data?.customers) ? data.customers : (Array.isArray(data) ? data : []);
        customerOptions.value = list;
    } catch {
        customerOptions.value = [];
    } finally {
        loadingCustomers.value = false;
    }
};

const onOpen = () => {
    if (props.expense) {
        form.description = props.expense.description || '';
        form.amount = Number(props.expense.amount || 0);
        form.category = props.expense.category || 'other';
        form.status = props.expense.status || 'paid';
        form.expense_date = String(props.expense.expense_date || localIsoDate()).slice(0, 10);
        form.invoice_id = props.expense.invoice_id || null;
        form.customer_id = props.expense.customer_id || null;
        form.notes = props.expense.notes || '';

        if (props.expense.invoice) {
            invoiceOptions.value = [props.expense.invoice];
        } else {
            searchInvoices();
        }

        if (props.expense.customer) {
            customerOptions.value = [props.expense.customer];
        } else {
            searchCustomers();
        }
    } else {
        form.description = '';
        form.amount = 0;
        form.category = 'shipping';
        form.status = 'paid';
        form.expense_date = localIsoDate();
        form.invoice_id = null;
        form.customer_id = null;
        form.notes = '';
        searchInvoices();
        searchCustomers();
    }
};

const submit = async () => {
    if (!formRef.value) return;
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    if (!(Number(form.amount) > 0)) {
        ElMessage.warning(t('enter_amount_above_zero'));
        return;
    }

    saving.value = true;
    try {
        const payload = {
            description: form.description.trim(),
            amount: Number(form.amount),
            category: form.category,
            status: form.status,
            expense_date: form.expense_date,
            invoice_id: form.invoice_id || null,
            customer_id: form.customer_id || null,
            notes: form.notes ? form.notes.trim() : null,
        };

        let result;
        if (isEditing.value) {
            result = await expensesApi.update(props.expense.id, payload);
            ElMessage.success(t('pay_expense_updated'));
        } else {
            result = await expensesApi.create(payload);
            ElMessage.success(t('pay_expense_saved'));
        }

        emit('saved', result.data?.data || result.data);
        emit('update:modelValue', false);
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('pay_save_failed')));
    } finally {
        saving.value = false;
    }
};
</script>

<style scoped>
.expense-dialog { font-family: 'Cairo', sans-serif; }
.dialog-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 0.85rem; }

.edit-context-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    margin-bottom: 1.1rem;
    font-size: 0.85rem;
}
.banner-item { display: flex; flex-direction: column; gap: 0.15rem; }
.banner-item .label { font-size: 0.72rem; color: #64748b; font-weight: 500; }
.banner-item strong { color: #0f172a; }

.category-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    width: 100%;
}
.category-card {
    all: unset;
    cursor: pointer;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.75rem 0.5rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: all 0.15s ease-in-out;
    text-align: center;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}
.category-card i { font-size: 1.15rem; }
.category-card:hover { border-color: #cbd5e1; background: #f8fafc; }
.category-card.is-selected {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #2563eb;
}
.category-card.shipping.is-selected i { color: #2563eb; }
.category-card.packaging.is-selected i { color: #d97706; }
.category-card.handling.is-selected i { color: #7c3aed; }
.category-card.other.is-selected i { color: #475569; }

.status-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    width: 100%;
}
.status-card {
    all: unset;
    cursor: pointer;
    box-sizing: border-box;
    padding: 0.75rem 0.9rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: all 0.15s ease-in-out;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.status-card .card-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.86rem;
    color: #1e293b;
}
.status-card .card-desc {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.35;
}
.status-card:hover { border-color: #cbd5e1; background: #f8fafc; }
.status-card.s-paid.is-selected {
    border-color: #16a34a;
    background: #f0fdf4;
    box-shadow: 0 0 0 1px #16a34a;
}
.status-card.s-paid.is-selected .card-head { color: #15803d; }
.status-card.s-pending.is-selected {
    border-color: #d97706;
    background: #fffbeb;
    box-shadow: 0 0 0 1px #d97706;
}
.status-card.s-pending.is-selected .card-head { color: #b45309; }

.status-secondary-row { margin-top: 0.5rem; }

.select-option-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
}
.option-muted { color: #64748b; font-size: 0.78rem; }
.option-amount { font-weight: 700; color: #0f172a; font-size: 0.82rem; }
.mono { font-family: ui-monospace, monospace; }

.ledger-notice { margin-top: 0.5rem; }

@media (max-width: 768px) {
    .dialog-grid-2 { grid-template-columns: 1fr; }
    .category-selector { grid-template-columns: repeat(2, 1fr); }
    .status-selector { grid-template-columns: 1fr; }
}
</style>
