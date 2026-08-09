<template>
    <div class="sales-page sales-invoices">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-file-invoice-dollar"></i> {{ $t('invoices') || 'الفواتير' }}</h1>
                <p>{{ $t('quickly_track_invoices_with_instant') || 'تابع الفواتير وحصّل المدفوعات وحدّث حالات التسليم.' }}</p>
            </div>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_invoice_number_or') || 'ابحث برقم الفاتورة أو العميل...'"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-select v-model="statusFilter" clearable class="status-filter" :placeholder="$t('status') || 'الحالة'">
                    <el-option
                        v-for="status in ORDER_STATUSES"
                        :key="status"
                        :value="status"
                        :label="statusLabel(status)"
                    />
                </el-select>
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="goToCreate">
                    {{ $t('create_invoice') || 'فاتورة جديدة' }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col v-for="card in summaryCards" :key="card.key" :xs="12" :sm="12" :md="6">
                <el-card
                    shadow="hover"
                    class="stat-card"
                    :class="{ 'is-active': card.status !== null && statusFilter === card.status }"
                    @click="card.status !== null && toggleFilter(card.status)"
                >
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

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list"></i> {{ $t('list_of_invoices') || 'قائمة الفواتير' }}</span>
                    <span class="result-count">{{ filteredInvoices.length }} / {{ store.invoices.length }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="6" animated />

            <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

            <template v-else>
                <el-table v-if="filteredInvoices.length" :data="filteredInvoices" style="width:100%" stripe highlight-current-row>
                    <el-table-column :label="$t('invoice_number') || 'رقم الفاتورة'" width="150">
                        <template #default="{ row }">
                            <button type="button" class="record-link" @click="editInvoice(row.id)">
                                {{ row.invoice_number }}
                            </button>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('client') || 'العميل'" min-width="160">
                        <template #default="{ row }">
                            <div class="customer-cell">
                                <i class="fas fa-user-circle"></i>
                                <span>{{ customerName(row) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('total') || 'الإجمالي'" width="140">
                        <template #default="{ row }">
                            <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                        </template>
                    </el-table-column>

                    <!-- Payment progress: the backend already tracks paid/due on
                         every invoice, but the UI never surfaced it. -->
                    <el-table-column :label="$t('payment_status') || 'التحصيل'" width="190">
                        <template #default="{ row }">
                            <div class="payment-progress">
                                <el-progress
                                    :percentage="paidPercentage(row)"
                                    :status="paidPercentage(row) >= 100 ? 'success' : undefined"
                                    :stroke-width="6"
                                    :show-text="false"
                                />
                                <div class="payment-figures">
                                    <span class="amount paid">{{ formatCurrency(paidAmount(row)) }}</span>
                                    <span class="sep">/</span>
                                    <span class="amount due">{{ formatCurrency(dueAmount(row)) }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status') || 'الحالة'" width="175" align="center">
                        <template #default="{ row }">
                            <el-dropdown
                                v-if="transitionsFor(row.status).length > 1"
                                trigger="click"
                                @command="(status) => changeStatus(row, status)"
                            >
                                <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag clickable">
                                    <i class="fas" :class="statusIcon(row.status)"></i>
                                    {{ statusLabel(row.status) }}
                                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                </el-tag>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="status in transitionsFor(row.status)"
                                            :key="status"
                                            :command="status"
                                            :disabled="status === normalizeStatus(row.status)"
                                        >
                                            <el-tag :type="statusTagType(status)" size="small">{{ statusLabel(status) }}</el-tag>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-tag v-else :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('actions') || 'الإجراءات'" width="190" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-tooltip :content="$t('edit') || 'تعديل'" placement="top">
                                    <el-button size="small" plain @click="editInvoice(row.id)">
                                        <i class="fas fa-pen"></i>
                                    </el-button>
                                </el-tooltip>

                                <el-tooltip :content="recordPaymentHint(row)" placement="top">
                                    <span>
                                        <el-button
                                            size="small"
                                            type="success"
                                            plain
                                            :disabled="!canRecordPayment(row)"
                                            @click="openPaymentDialog(row)"
                                        >
                                            <i class="fas fa-money-bill-wave"></i>
                                        </el-button>
                                    </span>
                                </el-tooltip>

                                <el-tooltip :content="$t('delete') || 'حذف'" placement="top">
                                    <el-button size="small" type="danger" plain @click="removeInvoice(row)">
                                        <i class="fas fa-trash"></i>
                                    </el-button>
                                </el-tooltip>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty
                    v-else
                    :description="store.invoices.length
                        ? ($t('there_are_no_invoices_matching') || 'لا توجد فواتير مطابقة للبحث')
                        : ($t('no_invoices_yet') || 'لا توجد فواتير بعد')"
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

        <QuickPaymentDialog
            v-model="paymentDialogVisible"
            :invoice="paymentTarget"
            @saved="onPaymentSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Refresh, ArrowDown } from '@element-plus/icons-vue';
import { useInvoicesStore } from '@/stores/invoices';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import {
    ORDER_STATUSES,
    ORDER_TRANSITIONS,
    availableTransitions,
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    formatCurrency,
    customerName,
    apiErrorMessage,
    invoicePaid,
    invoiceDue,
    isInvoiceSettled,
    invoicePaidPercentage,
} from '@/utils/sales';

const router = useRouter();
const route = useRoute();
const store = useInvoicesStore();

const searchQuery = ref('');
const statusFilter = ref('');
const paymentDialogVisible = ref(false);
const paymentTarget = ref(null);

const goToCreate = () => router.push('/admin/sales/invoices/create');
const editInvoice = (id) => router.push(`/admin/sales/invoices/${id}/edit`);

// Balance maths lives in utils/sales.js so the dialog, the list and the sales
// dashboard all agree on what "settled" means.
const paidAmount = invoicePaid;
const dueAmount = invoiceDue;
const paidPercentage = invoicePaidPercentage;

const canRecordPayment = (invoice) =>
    !isInvoiceSettled(invoice) && normalizeStatus(invoice.status) !== 'cancelled';

const recordPaymentHint = (invoice) => {
    if (normalizeStatus(invoice.status) === 'cancelled') return 'الفاتورة ملغاة';
    if (isInvoiceSettled(invoice)) return 'الفاتورة مسددة بالكامل';
    return window.t?.('record_payment') || 'تسجيل دفعة';
};

const outstandingTotal = computed(() =>
    store.invoices
        .filter((invoice) => normalizeStatus(invoice.status) !== 'cancelled')
        .reduce((sum, invoice) => sum + Math.max(0, dueAmount(invoice)), 0)
);

const summaryCards = computed(() => [
    {
        key: 'total',
        status: '',
        label: window.t?.('total_bills') || 'إجمالي الفواتير',
        value: store.invoices.length,
        icon: 'fa-file-invoice',
        tone: 'blue',
    },
    {
        key: 'pending',
        status: 'pending',
        label: statusLabel('pending'),
        value: store.invoices.filter((i) => normalizeStatus(i.status) === 'pending').length,
        icon: 'fa-clock',
        tone: 'orange',
    },
    {
        key: 'delivered',
        status: 'delivered',
        label: statusLabel('delivered'),
        value: store.invoices.filter((i) => normalizeStatus(i.status) === 'delivered').length,
        icon: 'fa-circle-check',
        tone: 'green',
    },
    {
        // Not a status filter — a money figure, so it is not clickable.
        key: 'outstanding',
        status: null,
        label: window.t?.('outstanding_amount') || 'المبالغ المستحقة',
        value: formatCurrency(outstandingTotal.value),
        icon: 'fa-hand-holding-dollar',
        tone: 'red',
    },
]);

const filteredInvoices = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const status = statusFilter.value;

    return store.invoices.filter((invoice) => {
        if (status && normalizeStatus(invoice.status) !== status) return false;
        if (!query) return true;
        return [invoice.invoice_number, customerName(invoice), invoice.total]
            .some((field) => String(field ?? '').toLowerCase().includes(query));
    });
});

const transitionsFor = (status) => availableTransitions(status, ORDER_TRANSITIONS);

const toggleFilter = (status) => {
    statusFilter.value = statusFilter.value === status ? '' : status;
};

const reload = () => store.fetchInvoices({ page: store.pagination.current_page }).catch(() => {});
const changePage = (page) => store.fetchInvoices({ page }).catch(() => {});

const changeStatus = async (invoice, status) => {
    if (normalizeStatus(invoice.status) === status) return;

    try {
        await ElMessageBox.confirm(
            `تغيير حالة الفاتورة ${invoice.invoice_number} من "${statusLabel(invoice.status)}" إلى "${statusLabel(status)}"؟`,
            'تغيير حالة الفاتورة',
            { type: 'warning', confirmButtonText: 'تأكيد', cancelButtonText: 'إلغاء' }
        );
    } catch {
        return;
    }

    try {
        await store.updateInvoiceStatus(invoice.id, status);
        ElMessage.success('تم تغيير حالة الفاتورة بنجاح.');
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, 'فشل تغيير حالة الفاتورة.'));
    }
};

const openPaymentDialog = (invoice) => {
    paymentTarget.value = invoice;
    paymentDialogVisible.value = true;
};

/**
 * Recording a payment changes paid/due server-side and can flip the invoice to
 * delivered, so the list has to be refetched rather than patched locally.
 */
const onPaymentSaved = async () => {
    paymentTarget.value = null;
    await store.fetchInvoices({ page: store.pagination.current_page }).catch(() => {});
};

const removeInvoice = async (invoice) => {
    try {
        await ElMessageBox.confirm(
            `حذف الفاتورة ${invoice.invoice_number}؟ لا يمكن التراجع عن هذا الإجراء.`,
            'تأكيد الحذف',
            { type: 'warning', confirmButtonText: 'حذف', cancelButtonText: 'إلغاء' }
        );
    } catch {
        return;
    }

    try {
        await store.deleteInvoice(invoice.id);
        ElMessage.success('تم حذف الفاتورة.');
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, 'تعذّر حذف الفاتورة.'));
    }
};

onMounted(() => {
    // Arriving from a sales order, a journal entry or a stock movement: the
    // caller names the invoice it means, so the list opens on that row instead
    // of dropping the user into an unfiltered table to hunt for it.
    if (route.query.invoice) {
        searchQuery.value = String(route.query.invoice);
    }

    store.fetchInvoices().catch(() => {});
});
</script>

<style scoped>
/* Layout comes from resources/css/sales-shared.css. Only what is unique here. */
.payment-progress {
    display: grid;
    gap: 0.3rem;
}

.payment-figures {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.8rem;
}

.payment-figures .sep {
    color: var(--el-text-color-placeholder, #c0c4cc);
}
</style>
