<template>
    <div class="purchases-index">
        <div class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">{{ $t('procurement_overview') }}</p>
                <h1>{{ $t('modern_and_elegant_shopping_board') }}</h1>
                <p>{{ $t('view_purchase_orders_suppliers_and') }}</p>
            </div>
            <div class="hero-actions">
                <el-button type="primary" @click="refreshData">{{ $t('data_update') }}</el-button>
            </div>
        </div>

        <el-row :gutter="20" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6" v-for="item in stats" :key="item.title">
                <el-card shadow="hover" class="overview-card">
                    <div class="card-meta">
                        <span>{{ item.title }}</span>
                    </div>
                    <h2>{{ item.value }}</h2>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="activity-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('latest_purchase_orders') }}</span>
                        </div>
                    </template>
                    <el-table :data="ordersStore.orders.slice(0, 5)" style="width: 100%" stripe>
                        <el-table-column prop="order_number" label="#" width="120" />
                        <el-table-column prop="supplier.name" :label="$t('supplier')" />
                        <el-table-column prop="total" :label="$t('total')" width="140" />
                        <el-table-column :label="$t('status')" width="140">
                            <template #default="{ row }">
                                <el-tag :type="statusTagType(row.status)">{{ row.status || $t('undefined') }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!ordersStore.orders.length" class="empty-state">{{ $t('there_are_no_purchase_orders_yet') }}</div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="insight-card">
                    <template #header>
                        <span>{{ $t('quick_summary') }}</span>
                    </template>
                    <div class="insight-list">
                        <div class="insight-item">
                            <span>{{ $t('suppliers') }}</span>
                            <strong>{{ suppliersStore.suppliers.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('receipts') }}</span>
                            <strong>{{ receiptsStore.receipts.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('latest_suppliers') }}</span>
                            <strong>{{ Math.min(suppliersStore.suppliers.length, 5) }}</strong>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';

const ordersStore = usePurchaseOrdersStore();
const suppliersStore = useSuppliersStore();
const receiptsStore = usePurchaseReceiptsStore();

const statusTagType = (status) => {
    const value = String(status || '').toLowerCase();
    if (['completed', window.t('complete'), 'paid'].includes(value)) return 'success';
    if (['pending', window.t('hanging'), window.t('in_process')].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), window.t('canceled')].includes(value)) return 'danger';
    return 'info';
};

const stats = computed(() => [
    { title: window.t('purchase_orders'), value: ordersStore.orders.length },
    { title: window.t('suppliers'), value: suppliersStore.suppliers.length },
    { title: window.t('receipts'), value: receiptsStore.receipts.length },
    { title: window.t('recent_requests'), value: Math.min(ordersStore.orders.length, 5) }
]);

const refreshData = async () => {
    await Promise.all([
        ordersStore.fetchOrders().catch(() => {}),
        suppliersStore.fetchSuppliers().catch(() => {}),
        receiptsStore.fetchReceipts().catch(() => {})
    ]);
};

onMounted(refreshData);
</script>

<style scoped>
.purchases-index {
    padding: 0;
}

.hero-panel {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.75rem;
    border-radius: 1.25rem;
    background: #f5f8ff;
    margin-bottom: 1.75rem;
}

.hero-copy .eyebrow {
    margin: 0 0 0.5rem;
    color: #409eff;
    letter-spacing: 0.08em;
    font-size: 0.85rem;
    font-weight: 700;
}

.hero-copy h1 {
    margin: 0;
    font-size: 2.2rem;
    line-height: 1.1;
    color: #1f2d3d;
}

.hero-copy p {
    margin: 1rem 0 0;
    max-width: 44rem;
    color: #5f6d85;
    font-size: 1rem;
}

.hero-actions {
    display: flex;
    align-items: center;
}

.overview-cards {
    margin-bottom: 1rem;
}

.overview-card {
    min-height: 138px;
    border-radius: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.9rem;
}

.card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #5f6d85;
    font-size: 0.95rem;
}

.overview-card h2 {
    margin: 0;
    font-size: 2rem;
    color: #24314f;
}

.mt-4 {
    margin-top: 1.5rem;
}

.activity-card,
.insight-card {
    border-radius: 1rem;
}

.insight-list {
    display: grid;
    gap: 1rem;
    padding: 1rem 0;
}

.insight-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-radius: 1rem;
    background: #f8fbff;
}

.insight-item strong {
    color: #24314f;
    font-size: 1.15rem;
}

.empty-state {
    padding: 1.5rem;
    text-align: center;
    color: #6b7c98;
}
</style>
