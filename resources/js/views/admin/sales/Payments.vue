<template>
    <div class="sales-page sales-payments">
        <AdminPageHeader
            icon="fas fa-money-bill-transfer"
            :title="$t('payments')"
            :subtitle="$t('track_incoming_payments_and_clearly')"
        >
            <template #actions>
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_transaction_number_or')"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="paymentDialogVisible = true">
                    {{ $t('record_payment') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminStatGrid>
            <el-card v-for="card in summaryCards" :key="card.key" shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon" :class="card.tone">
                        <i class="fas" :class="card.icon"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ card.value }}</h3>
                        <p>{{ card.label }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Cash by currency — each currency read as its own wallet, never
             blended into one converted figure. -->
        <el-card v-if="store.wallets.length" shadow="hover" class="table-panel wallets-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-wallet"></i> {{ $t('cash_by_currency') }}</span>
                </div>
            </template>
            <div class="wallet-grid">
                <div v-for="wallet in store.wallets" :key="wallet.currency" class="wallet-card" :class="{ 'is-base': wallet.is_base }">
                    <div class="wallet-head">
                        <span class="wallet-code">{{ wallet.currency }}</span>
                        <el-tag v-if="wallet.is_base" size="small" type="primary" effect="plain">
                            {{ $t('base_currency_tag') }}
                        </el-tag>
                    </div>
                    <div class="wallet-amount">{{ formatWalletTotal(wallet) }}</div>
                    <div class="wallet-meta">
                        {{ wallet.name || wallet.currency }} · {{ wallet.payments_count }} {{ $t('payments_count') }}
                    </div>
                </div>
            </div>
        </el-card>

        <!-- Payments -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-receipt"></i> {{ $t('list_of_payments') }}</span>
                    <span class="result-count">{{ filteredPayments.length }} / {{ store.payments.length }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="5" animated />
            <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

            <template v-else>
                <el-table v-if="filteredPayments.length" :data="filteredPayments" style="width:100%" stripe>
                    <el-table-column :label="$t('payment_number')" width="150">
                        <template #default="{ row }">
                            <span class="mono">{{ row.payment_number || row.reference || '—' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('client')" min-width="150">
                        <template #default="{ row }">
                            <div class="customer-cell">
                                <i class="fas fa-user-circle"></i>
                                <span>{{ customerName(row) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- The link back to the invoice this payment settled. -->
                    <el-table-column :label="$t('invoice')" width="150">
                        <template #default="{ row }">
                            <button
                                v-if="row.invoice"
                                type="button"
                                class="record-link"
                                @click="goToInvoice(row.invoice)"
                            >
                                {{ row.invoice.invoice_number }}
                            </button>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('amount')" width="150">
                        <template #default="{ row }">
                            <strong class="amount paid">{{ formatCurrency(row.amount) }}</strong>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_method')" width="140" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ paymentMethodLabel(row.payment_method) }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_date')" width="140" align="center">
                        <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('actions')" width="90" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-tooltip :content="$t('delete')" placement="top">
                                <el-button size="small" type="danger" plain @click="removePayment(row)">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty
                    v-else
                    :description="store.payments.length
                        ? ($t('there_are_no_payments_matching'))
                        : ($t('no_payments_yet'))"
                />

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

        <!-- Expenses -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-arrow-trend-down"></i> {{ $t('list_of_expenses') }}</span>
                    <el-button type="primary" size="small" plain :icon="Plus" @click="openExpenseDialog">
                        {{ $t('add_expense') }}
                    </el-button>
                </div>
            </template>

            <el-skeleton v-if="expensesLoading" :rows="4" animated />
            <el-alert v-else-if="expensesError" type="error" show-icon :closable="false" :title="expensesError" />

            <template v-else>
                <el-table v-if="filteredExpenses.length" :data="filteredExpenses" style="width:100%" stripe>
                    <el-table-column prop="expense_number" label="#" width="140" />
                    <el-table-column prop="description" :label="$t('description')" min-width="180" />
                    <el-table-column :label="$t('amount')" width="150">
                        <template #default="{ row }">
                            <strong class="amount due">{{ formatCurrency(row.amount) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('category')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ expenseCategoryLabel(row.category) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="expenseStatusTagType(row.status)" size="small">
                                {{ row.status || $t('undefined') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('date')" width="140" align="center">
                        <template #default="{ row }">{{ formatDate(row.expense_date) }}</template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('there_are_no_expenses_matching')" />
            </template>
        </el-card>

        <QuickPaymentDialog v-model="paymentDialogVisible" @saved="onPaymentSaved" />

        <!-- Expense dialog -->
        <el-dialog v-model="showExpenseDialog" :title="$t('add_expense')" width="500px">
            <el-form :model="expenseForm" label-position="top">
                <el-form-item :label="$t('description')">
                    <el-input v-model="expenseForm.description" />
                </el-form-item>
                <el-form-item :label="$t('amount')">
                    <el-input-number v-model="expenseForm.amount" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('category')">
                    <el-select v-model="expenseForm.category" style="width: 100%">
                        <el-option
                            v-for="category in EXPENSE_CATEGORIES"
                            :key="category"
                            :value="category"
                            :label="expenseCategoryLabel(category)"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('date')">
                    <el-date-picker
                        v-model="expenseForm.expense_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="expenseForm.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showExpenseDialog = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="savingExpense" @click="addExpense">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Refresh } from '@element-plus/icons-vue';
import { usePaymentsStore } from '@/stores/payments';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();
import {
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    paymentMethodLabel,
    formatCurrency,
    formatDate,
    customerName,
    sumBy,
    apiErrorMessage,
} from '@/utils/sales';
import { formatMoney } from '@/utils/currency';

/** Each wallet is written in its own currency's precision — never the base's. */
const formatWalletTotal = (wallet) =>
    formatMoney(wallet.total, { code: wallet.currency, decimals: wallet.decimal_places });

const router = useRouter();
const store = usePaymentsStore();

const searchQuery = ref('');
const paymentDialogVisible = ref(false);

const EXPENSE_CATEGORIES = ['shipping', 'packaging', 'handling', 'other'];
const EXPENSE_CATEGORY_LABELS = {
    shipping: t('shipping'),
    packaging: t('packaging'),
    handling: t('process'),
    other: t('subject_other'),
};
const expenseCategoryLabel = (category) =>
    EXPENSE_CATEGORY_LABELS[normalizeStatus(category)] || category || '—';

const expenses = ref([]);
const expensesLoading = ref(false);
const expensesError = ref('');
const savingExpense = ref(false);
const showExpenseDialog = ref(false);

const expenseForm = reactive({
    description: '',
    amount: 0,
    category: 'other',
    expense_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

const completedPayments = computed(() =>
    store.payments.filter((p) => normalizeStatus(p.status) === 'completed')
);

const summaryCards = computed(() => [
    {
        key: 'count',
        label: window.t?.('total_payments') || t('payments_count'),
        value: store.payments.length,
        icon: 'fa-receipt',
        tone: 'blue',
    },
    {
        key: 'collected',
        label: window.t?.('collected_amount') || t('total_collected'),
        value: formatCurrency(sumBy(completedPayments.value, 'amount')),
        icon: 'fa-sack-dollar',
        tone: 'green',
    },
    {
        key: 'completed',
        label: statusLabel('completed'),
        value: completedPayments.value.length,
        icon: 'fa-circle-check',
        tone: 'teal',
    },
    {
        key: 'expenses',
        label: window.t?.('total_expenses') || t('total_expenses'),
        value: formatCurrency(sumBy(expenses.value, 'amount')),
        icon: 'fa-arrow-trend-down',
        tone: 'red',
    },
]);

const filteredPayments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return store.payments;
    return store.payments.filter((payment) =>
        [payment.payment_number, payment.reference, customerName(payment), payment.invoice?.invoice_number]
            .some((field) => String(field || '').toLowerCase().includes(query))
    );
});

const filteredExpenses = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return expenses.value;
    return expenses.value.filter((expense) =>
        [expense.expense_number, expense.description, expense.category]
            .some((field) => String(field || '').toLowerCase().includes(query))
    );
});

const expenseStatusTagType = (status) => {
    const value = normalizeStatus(status);
    if (value === 'approved') return 'success';
    if (value === 'pending') return 'warning';
    if (value === 'rejected') return 'danger';
    return 'info';
};

const goToInvoice = (invoice) => router.push(`/admin/sales/invoices/${invoice.id}/edit`);

const reload = () => store.fetchPayments({ page: store.pagination.current_page }).catch(() => {});
const changePage = (page) => store.fetchPayments({ page }).catch(() => {});

const onPaymentSaved = () => {
    reload();
    store.fetchCurrencyWallets().catch(() => {});
};

const removePayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            // Deleting reverses the invoice balance server-side, so say so.
            t('confirm_delete_payment'),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.deletePayment(payment.id);
        ElMessage.success(t('payment_deleted'));
        store.fetchCurrencyWallets().catch(() => {});
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_payment')));
    }
};

const fetchExpenses = async () => {
    expensesLoading.value = true;
    expensesError.value = '';
    try {
        const response = await axios.get('/api/v1/expenses');
        const payload = response.data?.data;
        expenses.value = Array.isArray(payload) ? payload : (payload?.expenses || []);
    } catch (error) {
        // Previously this failed silently to the console, leaving an empty table
        // that looked like "no expenses".
        expensesError.value = apiErrorMessage(error, t('failed_to_load_expenses'));
    } finally {
        expensesLoading.value = false;
    }
};

const openExpenseDialog = () => {
    expenseForm.description = '';
    expenseForm.amount = 0;
    expenseForm.category = 'other';
    expenseForm.expense_date = new Date().toISOString().slice(0, 10);
    expenseForm.notes = '';
    showExpenseDialog.value = true;
};

const addExpense = async () => {
    if (!expenseForm.description.trim()) {
        ElMessage.warning(t('enter_expense_description'));
        return;
    }
    if (!expenseForm.amount || expenseForm.amount <= 0) {
        ElMessage.warning(t('enter_amount_above_zero'));
        return;
    }

    savingExpense.value = true;
    try {
        await axios.post('/api/v1/expenses', { ...expenseForm });
        showExpenseDialog.value = false;
        ElMessage.success(t('expense_added'));
        // Refetch rather than pushing the response, so server-generated fields
        // (expense_number, status) are correct.
        await fetchExpenses();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_add_expense')));
    } finally {
        savingExpense.value = false;
    }
};

onMounted(() => {
    store.fetchPayments().catch(() => {});
    store.fetchCurrencyWallets().catch(() => {});
    fetchExpenses();
});
</script>

<style scoped>
.mono {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.muted {
    color: var(--el-text-color-placeholder, #c0c4cc);
}

.wallets-panel {
    margin-bottom: 1.25rem;
}

.wallet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.85rem;
}

.wallet-card {
    border: 1px solid var(--el-border-color-lighter, #e4e7ed);
    border-radius: 0.85rem;
    padding: 0.9rem 1rem;
    background: var(--el-fill-color-light, #f5f7fa);
}

.wallet-card.is-base {
    border-color: var(--el-color-primary-light-5, #a0cfff);
    background: var(--el-color-primary-light-9, #ecf5ff);
}

.wallet-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
}

.wallet-code {
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--el-text-color-primary, #303133);
}

.wallet-amount {
    font-size: 1.4rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--el-color-success, #059669);
    margin-bottom: 0.25rem;
}

.wallet-meta {
    font-size: 0.78rem;
    color: var(--el-text-color-secondary, #909399);
}
</style>
