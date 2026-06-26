<template>
    <div class="purchases-page purchases-receipts">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('receipts') }}</h1>
                <p>{{ $t('clearly_view_receipts_with_live') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_receipt_number_or')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_receipts') }}</p>
                    <h3>{{ store.receipts.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('latest_receipts') }}</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('list_of_receipts') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredReceipts.length" :data="filteredReceipts" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="receipt_number" label="#" width="140" />
                    <el-table-column prop="supplier.name" :label="$t('supplier')" />
                    <el-table-column prop="receipt_date" :label="$t('the_date')" width="160" />
                    <el-table-column prop="notes" :label="$t('comments')" />
                </el-table>
                <div v-if="!filteredReceipts.length" class="empty-state">{{ $t('there_are_no_receipts_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';

const store = usePurchaseReceiptsStore();
const searchQuery = ref('');

const filteredReceipts = computed(() => {
    if (!searchQuery.value.trim()) return store.receipts;
    const query = searchQuery.value.toLowerCase();
    return store.receipts.filter((receipt) => {
        return [
            receipt.receipt_number,
            receipt.supplier?.name,
            receipt.receipt_date,
            receipt.notes
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.receipts.length, 5));

onMounted(() => {
    store.fetchReceipts().catch(() => {});
});
</script>

<style scoped>
.purchases-page {
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
