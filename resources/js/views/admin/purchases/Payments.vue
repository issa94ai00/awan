<template>
    <div class="purchases-page supplier-payments">
        <AdminPageHeader
            icon="fas fa-money-check-dollar"
            :title="$t('supplier_payments')"
            :subtitle="$t('supplier_payments_subtitle')"
        >
            <template #actions>
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search')"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openDialog()">
                    {{ $t('record_supplier_payment') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(store.totalOutstanding) }}</h3>
                        <p>{{ $t('outstanding_to_suppliers') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(store.totalPaid) }}</h3>
                        <p>{{ $t('paid_in_period') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details">
                        <h3>{{ store.pagination.total }}</h3>
                        <p>{{ $t('payments') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-list"></i> {{ $t('supplier_payments') }}</span>
                            <span class="result-count">{{ filteredPayments.length }} / {{ store.payments.length }}</span>
                        </div>
                    </template>

                    <el-skeleton v-if="store.loading" :rows="5" animated />
                    <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

                    <template v-else>
                        <el-table v-if="filteredPayments.length" :data="filteredPayments" style="width:100%" stripe>
                            <el-table-column :label="$t('payment_number')" width="140">
                                <template #default="{ row }">
                                    <span class="mono">{{ row.payment_number }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('supplier')" min-width="150">
                                <template #default="{ row }">
                                    <div class="supplier-cell">
                                        <i class="fas fa-truck-field"></i>
                                        <span>{{ row.supplier?.name || '—' }}</span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('amount')" width="140">
                                <template #default="{ row }">
                                    <strong class="amount">{{ formatCurrency(row.amount, row.currency) }}</strong>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('payment_method')" width="130" align="center">
                                <template #default="{ row }">
                                    <el-tag size="small" effect="plain">{{ paymentMethodLabel(row.payment_method) }}</el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('payment_date')" width="130" align="center">
                                <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
                            </el-table-column>

                            <el-table-column :label="$t('reference')" min-width="120" show-overflow-tooltip>
                                <template #default="{ row }">{{ row.reference || '—' }}</template>
                            </el-table-column>

                            <el-table-column :label="$t('actions')" width="90" align="center" fixed="right">
                                <template #default="{ row }">
                                    <el-tooltip :content="$t('cancel_the_payment')" placement="top">
                                        <el-button size="small" type="danger" plain @click="cancelPayment(row)">
                                            <i class="fas fa-rotate-left"></i>
                                        </el-button>
                                    </el-tooltip>
                                </template>
                            </el-table-column>
                        </el-table>

                        <el-empty v-else :description="$t('no_supplier_payments_yet')" />

                        <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-bar">
                            <el-pagination
                                layout="prev, pager, next, total"
                                :total="store.pagination.total"
                                :page-size="store.pagination.per_page"
                                :current-page="store.pagination.current_page"
                                @current-change="changePage"
                            />
                        </div>
                    </template>
                </el-card>
            </el-col>

            <!-- What is still owed, so a payment starts from the debt rather
                 than from a blank form. -->
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-scale-unbalanced"></i> {{ $t('supplier_balances') }}</span>
                        </div>
                    </template>

                    <el-empty v-if="!owedSuppliers.length" :description="$t('nothing_owed_to_suppliers')" :image-size="80" />

                    <ul v-else class="balances-list">
                        <li v-for="supplier in owedSuppliers" :key="supplier.id">
                            <div class="balance-name">{{ supplier.name }}</div>
                            <div class="balance-actions">
                                <strong class="amount owed">{{ formatCurrency(supplier.balance, supplier.currency) }}</strong>
                                <el-button size="small" type="primary" plain @click="openDialog(supplier)">
                                    {{ $t('pay') }}
                                </el-button>
                            </div>
                        </li>
                    </ul>
                </el-card>
            </el-col>
        </el-row>

        <el-dialog v-model="dialogVisible" :title="$t('record_supplier_payment')" width="520px" destroy-on-close>
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('supplier')" required>
                    <el-select v-model="form.supplier_id" filterable :placeholder="$t('supplier')" style="width:100%">
                        <el-option
                            v-for="supplier in store.outstanding"
                            :key="supplier.id"
                            :label="supplier.name + ' — ' + formatCurrency(supplier.balance, supplier.currency)"
                            :value="supplier.id"
                        />
                    </el-select>
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('amount')" required>
                            <el-input v-model="form.amount" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('payment_method')" required>
                            <el-select v-model="form.payment_method" style="width:100%">
                                <el-option
                                    v-for="method in METHODS"
                                    :key="method"
                                    :label="paymentMethodLabel(method)"
                                    :value="method"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('payment_date')">
                            <el-date-picker
                                v-model="form.payment_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width:100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('reference')">
                            <el-input v-model="form.reference" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>

                <!-- Paying more than is owed is allowed — it becomes an advance
                     against future purchases — but it should never be silent. -->
                <el-alert
                    v-if="exceedsBalance"
                    type="warning"
                    show-icon
                    :closable="false"
                    :title="$t('payment_exceeds_supplier_balance')"
                />
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Search } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { useSupplierPaymentsStore } from '@/stores/supplierPayments';
import { apiErrorMessage, formatCurrency, formatDate, paymentMethodLabel } from '@/utils/sales';

const { t } = useI18n();
const store = useSupplierPaymentsStore();

// No card here: paying a supplier by card is not something this business does,
// and offering it would put a method on the document that the ledger would
// then have to treat as a bank movement anyway.
const METHODS = ['cash', 'bank_transfer', 'check'];

const searchQuery = ref('');
const dialogVisible = ref(false);

const form = reactive({
    supplier_id: null,
    amount: '',
    payment_method: 'bank_transfer',
    payment_date: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
});

/** Suppliers we still owe. A zero or negative balance is nothing to pay. */
const owedSuppliers = computed(() => store.outstanding.filter((supplier) => Number(supplier.balance) > 0));

const selectedSupplier = computed(() =>
    store.outstanding.find((supplier) => supplier.id === form.supplier_id)
);

const exceedsBalance = computed(() => {
    const amount = Number(form.amount);
    const balance = Number(selectedSupplier.value?.balance ?? 0);

    return amount > 0 && balance >= 0 && amount - balance > 0.009;
});

const canSubmit = computed(() => Boolean(form.supplier_id) && Number(form.amount) > 0);

const filteredPayments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return store.payments;

    return store.payments.filter((payment) =>
        [payment.payment_number, payment.reference, payment.supplier?.name]
            .some((field) => String(field || '').toLowerCase().includes(query))
    );
});

const reload = () => Promise.all([
    store.fetchPayments({ page: store.pagination.current_page }).catch(() => {}),
    store.fetchOutstanding().catch(() => {}),
]);

const changePage = (page) => store.fetchPayments({ page }).catch(() => {});

const openDialog = (supplier = null) => {
    form.supplier_id = supplier?.id ?? null;
    // Prefilled with the whole debt: settling in full is the common case, and
    // the field stays editable for a part payment.
    form.amount = supplier ? Number(supplier.balance).toFixed(2) : '';
    form.payment_method = 'bank_transfer';
    form.payment_date = new Date().toISOString().slice(0, 10);
    form.reference = '';
    form.notes = '';
    dialogVisible.value = true;
};

const submit = async () => {
    try {
        await store.createPayment({
            supplier_id: form.supplier_id,
            amount: Number(form.amount),
            payment_method: form.payment_method,
            payment_date: form.payment_date,
            reference: form.reference || null,
            notes: form.notes || null,
        });

        dialogVisible.value = false;
        ElMessage.success(t('supplier_payment_recorded'));
        await reload();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_record_supplier_payment')));
    }
};

const cancelPayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_cancel_supplier_payment'),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.cancelPayment(payment.id);
        ElMessage.success(t('supplier_payment_cancelled'));
        await reload();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_cancel_supplier_payment')));
    }
};

onMounted(reload);
</script>

<style scoped>
.supplier-payments {
    font-family: 'Cairo', sans-serif;
}

.search-input {
    width: 240px;
}

.stat-inner {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.15rem;
}

.stat-icon.red { background: linear-gradient(135deg, #ef4444, #b91c1c); }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #047857); }
.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.stat-details h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
}

.stat-details p {
    margin: 0.2rem 0 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
    gap: 0.5rem;
}

.result-count {
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 500;
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.amount {
    font-weight: 700;
}

.amount.owed {
    color: #b91c1c;
}

.balances-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.balances-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0;
    border-bottom: 1px solid var(--border-color);
}

.balances-list li:last-child {
    border-bottom: none;
}

.balance-name {
    font-weight: 600;
}

.balance-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pagination-bar {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
}
</style>
