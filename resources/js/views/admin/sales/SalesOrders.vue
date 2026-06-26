<template>
    <div class="sales-page sales-orders">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('sales_orders') }}</h1>
                <p>{{ $t('view_current_orders_with_quick') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_order_number_or_customer_name')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_orders') }}</p>
                    <h3>{{ store.orders.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('orders_are_being_processed') }}</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('completed_orders') }}</p>
                    <h3>{{ completedCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('list_of_sales_orders') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredOrders.length" :data="filteredOrders" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="order_number" label="#" width="140" />
                    <el-table-column prop="customer.name" :label="$t('client')" />
                    <el-table-column prop="total" :label="$t('total')" width="140" />
                    <el-table-column :label="$t('status')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || $t('undefined') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="order_date" :label="$t('order_date')" width="160" />
                </el-table>

                <div v-if="!filteredOrders.length" class="empty-state">{{ $t('there_are_no_requests_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useSalesOrdersStore } from '@/stores/salesOrders';

const store = useSalesOrdersStore();
const searchQuery = ref('');

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', window.t('complete'), 'paid', 'done'].includes(value)) return 'success';
    if (['pending', window.t('in_process'), window.t('suspended'), 'in progress'].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), 'canceled'].includes(value)) return 'danger';
    return 'info';
};

const filteredOrders = computed(() => {
    if (!searchQuery.value.trim()) {
        return store.orders;
    }

    const query = searchQuery.value.toLowerCase();
    return store.orders.filter((order) => {
        return [
            order.order_number,
            order.customer?.name,
            order.status,
            order.order_date
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const pendingCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['pending', window.t('in_process'), window.t('suspended'), 'in progress'].includes(value);
}).length);

const completedCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['completed', window.t('complete'), 'paid', 'done'].includes(value);
}).length);

onMounted(() => {
    store.fetchOrders().catch(() => {});
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
