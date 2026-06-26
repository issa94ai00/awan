<template>
    <div class="sales-page sales-invoices">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('invoices') }}</h1>
                <p>{{ $t('quickly_track_invoices_with_instant') }}</p>
            </div>
            <div class="header-actions">
                <el-input v-model="searchQuery" :placeholder="$t('search_by_invoice_number_or')" clearable class="search-input" />
                <el-button type="primary" @click="goToCreate" :icon="Plus">
                    {{ $t('create_invoice') }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_bills') }}</p>
                    <h3>{{ store.invoices.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('pending_invoices') }}</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('delivered_invoices') }}</p>
                    <h3>{{ paidCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('list_of_invoices') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredInvoices.length" :data="filteredInvoices" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="invoice_number" label="#" width="120" />
                    <el-table-column prop="customer_name" :label="$t('client')" />
                    <el-table-column prop="total" :label="$t('total')" width="120" />
                    <el-table-column :label="$t('status')" width="180">
                        <template #default="{ row }">
                            <el-dropdown @command="(status) => handleStatusChange(row, status)">
                                <el-tag :type="statusTagType(row.status)" style="cursor: pointer">
                                    {{ row.status || $t('undefined') }}
                                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                </el-tag>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="status in getAvailableStatuses(row.status)"
                                            :key="status"
                                            :command="status"
                                            :class="{ 'is-active': status === row.status }"
                                        >
                                            <el-tag :type="statusTagType(status)" size="small">{{ statusLabels[status] || status }}</el-tag>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button type="primary" size="small" @click="editInvoice(row.id)">
                                {{ $t('edit') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!filteredInvoices.length" class="empty-state">{{ $t('there_are_no_invoices_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useInvoicesStore } from '@/stores/invoices';
import { Plus, ArrowDown } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const router = useRouter();
const store = useInvoicesStore();
const searchQuery = ref('');

const goToCreate = () => {
    router.push('/admin/sales/invoices/create');
};

const editInvoice = (id) => {
    router.push(`/admin/sales/invoices/${id}/edit`);
};

// Status transitions configuration
const statusTransitions = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: []
};

const statusLabels = {
    pending: 'معلق',
    confirmed: 'مؤكد',
    processing: 'قيد المعالجة',
    shipped: 'تم الشحن',
    delivered: 'تم التسليم',
    cancelled: 'ملغي'
};

const getAvailableStatuses = (currentStatus) => {
    const transitions = statusTransitions[currentStatus] || [];
    return [currentStatus, ...transitions];
};

const handleStatusChange = async (invoice, newStatus) => {
    if (newStatus === invoice.status) return;

    try {
        await ElMessageBox.confirm(
            `هل أنت متأكد من تغيير حالة الفاتورة من "${statusLabels[invoice.status]}" إلى "${statusLabels[newStatus]}"؟`,
            'تغيير حالة الفاتورة',
            {
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
                type: 'warning'
            }
        );

        await store.updateInvoice(invoice.id, { status: newStatus });
        ElMessage.success('تم تغيير حالة الفاتورة بنجاح');
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('فشل تغيير حالة الفاتورة');
        }
    }
};

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['delivered', window.t('delivered'), 'completed', window.t('complete')].includes(value)) return 'success';
    if (['pending', window.t('suspended'), 'pending_payment', window.t('in_process')].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), 'canceled'].includes(value)) return 'danger';
    if (['confirmed', 'processing', 'shipped'].includes(value)) return 'info';
    return 'info';
};

const filteredInvoices = computed(() => {
    if (!searchQuery.value.trim()) return store.invoices;
    const query = searchQuery.value.toLowerCase();
    return store.invoices.filter((invoice) => {
        return [
            invoice.invoice_number,
            invoice.customer_name,
            invoice.status,
            invoice.total
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const pendingCount = computed(() => store.invoices.filter((invoice) => {
    const value = normalizeStatus(invoice.status);
    return ['pending', window.t('suspended'), 'pending_payment', window.t('in_process')].includes(value);
}).length);

const paidCount = computed(() => store.invoices.filter((invoice) => {
    const value = normalizeStatus(invoice.status);
    return ['delivered', window.t('delivered'), 'completed', window.t('complete')].includes(value);
}).length);

onMounted(() => {
    store.fetchInvoices().catch(() => {});
});
</script>

<style scoped>
.sales-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.search-input {
    width: min(100%, 320px);
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.loading-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>
