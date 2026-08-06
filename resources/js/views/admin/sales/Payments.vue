<template>
    <div class="sales-page sales-payments">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-money-bill-transfer"></i> {{ $t('payments') || 'المدفوعات' }}</h1>
                <p>{{ $t('track_incoming_payments_and_clearly') || 'سجّل الدفعات الواردة واربطها بالفواتير وتابع المصاريف.' }}</p>
            </div>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_transaction_number_or') || 'ابحث برقم العملية أو العميل...'"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="paymentDialogVisible = true">
                    {{ $t('record_payment') || 'تسجيل دفعة' }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col v-for="card in summaryCards" :key="card.key" :xs="12" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card">
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
            </el-col>
        </el-row>

        <!-- Payments -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-receipt"></i> {{ $t('list_of_payments') || 'قائمة المدفوعات' }}</span>
                    <span class="result-count">{{ filteredPayments.length }} / {{ store.payments.length }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="5" animated />
            <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

            <template v-else>
                <el-table v-if="filteredPayments.length" :data="filteredPayments" style="width:100%" stripe>
                    <el-table-column :label="$t('payment_number') || 'رقم الدفعة'" width="150">
                        <template #default="{ row }">
                            <span class="mono">{{ row.payment_number || row.reference || '—' }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('client') || 'العميل'" min-width="150">
                        <template #default="{ row }">
                            <div class="customer-cell">
                                <i class="fas fa-user-circle"></i>
                                <span>{{ customerName(row) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- The link back to the invoice this payment settled. -->
                    <el-table-column :label="$t('invoice') || 'الفاتورة'" width="150">
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

                    <el-table-column :label="$t('amount') || 'المبلغ'" width="150">
                        <template #default="{ row }">
                            <strong class="amount paid">{{ formatCurrency(row.amount) }}</strong>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_method') || 'طريقة الدفع'" width="140" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ paymentMethodLabel(row.payment_method) }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status') || 'الحالة'" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_date') || 'التاريخ'" width="140" align="center">
                        <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('actions') || 'الإجراءات'" width="90" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-tooltip :content="$t('delete') || 'حذف'" placement="top">
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
                        ? ($t('there_are_no_payments_matching') || 'لا توجد مدفوعات مطابقة للبحث')
                        : ($t('no_payments_yet') || 'لم تُسجَّل أي دفعات بعد')"
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
                    <span><i class="fas fa-arrow-trend-down"></i> {{ $t('list_of_expenses') || 'قائمة المصاريف' }}</span>
                    <el-button type="primary" size="small" plain :icon="Plus" @click="openExpenseDialog">
                        {{ $t('add_expense') || 'إضافة مصروف' }}
                    </el-button>
                </div>
            </template>

            <el-skeleton v-if="expensesLoading" :rows="4" animated />
            <el-alert v-else-if="expensesError" type="error" show-icon :closable="false" :title="expensesError" />

            <template v-else>
                <el-table v-if="filteredExpenses.length" :data="filteredExpenses" style="width:100%" stripe>
                    <el-table-column prop="expense_number" label="#" width="140" />
                    <el-table-column prop="description" :label="$t('description') || 'الوصف'" min-width="180" />
                    <el-table-column :label="$t('amount') || 'المبلغ'" width="150">
                        <template #default="{ row }">
                            <strong class="amount due">{{ formatCurrency(row.amount) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('category') || 'الفئة'" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ expenseCategoryLabel(row.category) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status') || 'الحالة'" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="expenseStatusTagType(row.status)" size="small">
                                {{ row.status || $t('undefined') || 'غير محدد' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('date') || 'التاريخ'" width="140" align="center">
                        <template #default="{ row }">{{ formatDate(row.expense_date) }}</template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('there_are_no_expenses_matching') || 'لا توجد مصاريف'" />
            </template>
        </el-card>

        <RecordPaymentDialog v-model="paymentDialogVisible" @saved="onPaymentSaved" />

        <!-- Expense dialog -->
        <el-dialog v-model="showExpenseDialog" :title="$t('add_expense') || 'إضافة مصروف'" width="500px">
            <el-form :model="expenseForm" label-position="top">
                <el-form-item :label="$t('description') || 'الوصف'">
                    <el-input v-model="expenseForm.description" />
                </el-form-item>
                <el-form-item :label="$t('amount') || 'المبلغ'">
                    <el-input-number v-model="expenseForm.amount" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('category') || 'الفئة'">
                    <el-select v-model="expenseForm.category" style="width: 100%">
                        <el-option
                            v-for="category in EXPENSE_CATEGORIES"
                            :key="category"
                            :value="category"
                            :label="expenseCategoryLabel(category)"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('date') || 'التاريخ'">
                    <el-date-picker
                        v-model="expenseForm.expense_date"
                        type="date"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('notes') || 'ملاحظات'">
                    <el-input v-model="expenseForm.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showExpenseDialog = false">{{ $t('cancel') || 'إلغاء' }}</el-button>
                <el-button type="primary" :loading="savingExpense" @click="addExpense">
                    {{ $t('save') || 'حفظ' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Refresh } from '@element-plus/icons-vue';
import { usePaymentsStore } from '@/stores/payments';
import RecordPaymentDialog from '@/components/admin/sales/RecordPaymentDialog.vue';
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

const router = useRouter();
const store = usePaymentsStore();

const searchQuery = ref('');
const paymentDialogVisible = ref(false);

const EXPENSE_CATEGORIES = ['shipping', 'packaging', 'handling', 'other'];
const EXPENSE_CATEGORY_LABELS = {
    shipping: 'شحن',
    packaging: 'تغليف',
    handling: 'معالجة',
    other: 'أخرى',
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
        label: window.t?.('total_payments') || 'عدد الدفعات',
        value: store.payments.length,
        icon: 'fa-receipt',
        tone: 'blue',
    },
    {
        key: 'collected',
        label: window.t?.('collected_amount') || 'إجمالي المحصّل',
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
        label: window.t?.('total_expenses') || 'إجمالي المصاريف',
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
};

const removePayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            // Deleting reverses the invoice balance server-side, so say so.
            'حذف هذه الدفعة سيعيد المبلغ إلى رصيد الفاتورة والعميل. هل تريد المتابعة؟',
            'تأكيد الحذف',
            { type: 'warning', confirmButtonText: 'حذف', cancelButtonText: 'إلغاء' }
        );
    } catch {
        return;
    }

    try {
        await store.deletePayment(payment.id);
        ElMessage.success('تم حذف الدفعة.');
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, 'تعذّر حذف الدفعة.'));
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
        expensesError.value = apiErrorMessage(error, 'تعذّر تحميل المصاريف.');
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
        ElMessage.warning('أدخل وصف المصروف.');
        return;
    }
    if (!expenseForm.amount || expenseForm.amount <= 0) {
        ElMessage.warning('أدخل مبلغاً أكبر من صفر.');
        return;
    }

    savingExpense.value = true;
    try {
        await axios.post('/api/v1/expenses', { ...expenseForm });
        showExpenseDialog.value = false;
        ElMessage.success('تمت إضافة المصروف.');
        // Refetch rather than pushing the response, so server-generated fields
        // (expense_number, status) are correct.
        await fetchExpenses();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, 'تعذّر إضافة المصروف.'));
    } finally {
        savingExpense.value = false;
    }
};

onMounted(() => {
    store.fetchPayments().catch(() => {});
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
</style>
