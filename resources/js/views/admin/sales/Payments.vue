<template>
    <div class="sales-page sales-payments">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('payments') }}</h1>
                <p>{{ $t('track_incoming_payments_and_clearly') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_transaction_number_or')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_payments') }}</p>
                    <h3>{{ store.payments.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('completed_payments') }}</p>
                    <h3>{{ completedCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('latest_payments') }}</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('list_of_payments') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredPayments.length" :data="filteredPayments" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="reference" label="#" width="140" />
                    <el-table-column prop="amount" :label="$t('amount')" width="140" />
                    <el-table-column :label="$t('status')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || $t('undefined') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="payment_date" :label="$t('payment_history')" width="160" />
                    <el-table-column prop="customer.name" :label="$t('client')" />
                </el-table>

                <div v-if="!filteredPayments.length" class="empty-state">{{ $t('there_are_no_payments_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePaymentsStore } from '@/stores/payments';

const store = usePaymentsStore();
const searchQuery = ref('');

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', window.t('complete'), 'paid'].includes(value)) return 'success';
    if (['pending', window.t('suspended'), window.t('in_process')].includes(value)) return 'warning';
    if (['failed', window.t('to_fail'), 'cancelled', window.t('canceled')].includes(value)) return 'danger';
    return 'info';
};

const filteredPayments = computed(() => {
    if (!searchQuery.value.trim()) return store.payments;
    const query = searchQuery.value.toLowerCase();
    return store.payments.filter((payment) => {
        return [
            payment.reference,
            payment.amount,
            payment.status,
            payment.payment_date,
            payment.customer?.name
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const completedCount = computed(() => store.payments.filter((payment) => {
    const value = normalizeStatus(payment.status);
    return ['completed', window.t('complete'), 'paid'].includes(value);
}).length);

const recentCount = computed(() => Math.min(store.payments.length, 5));

onMounted(() => {
    store.fetchPayments().catch(() => {});
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
