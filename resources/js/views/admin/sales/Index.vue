<template>
    <div class="sales-index">
        <div class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">{{ $t('sales_overview') }}</p>
                <h1>{{ $t('new_and_stylish_sales_board') }}</h1>
                <p>{{ $t('track_orders_invoices_offers_and') }}</p>
            </div>
            <div class="hero-actions">
                <el-button type="primary" @click="refreshSummary">{{ $t('data_update') }}</el-button>
            </div>
        </div>

        <el-row :gutter="20" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6" v-for="item in stats" :key="item.title">
                <el-card shadow="hover" class="overview-card">
                    <div class="card-meta">
                        <span>{{ item.title }}</span>
                        <el-tag type="item.type">{{ item.tag }}</el-tag>
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
                            <span>{{ $t('latest_sales_orders') }}</span>
                        </div>
                    </template>
                    <div class="table-wrapper">
                        <el-table :data="salesStore.orders.slice(0, 5)" style="width: 100%" stripe>
                            <el-table-column prop="order_number" label="#" width="120" />
                            <el-table-column prop="customer.name" :label="$t('client')" />
                            <el-table-column prop="total" :label="$t('total')" width="140" />
                            <el-table-column :label="$t('status')" width="140">
                                <template #default="{ row }">
                                    <el-tag :type="statusTagType(row.status)">{{ row.status || $t('undefined') }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="!salesStore.orders.length" class="empty-state">{{ $t('there_are_no_sell_orders_yet') }}</div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="insight-card">
                    <template #header>
                        <span>{{ $t('quick_summary') }}</span>
                    </template>
                    <div class="insight-list">
                        <div class="insight-item">
                            <span>{{ $t('sales_orders') }}</span>
                            <strong>{{ salesStore.orders.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('invoices') }}</span>
                            <strong>{{ invoicesStore.invoices.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('offers') }}</span>
                            <strong>{{ quotesStore.quotes.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('revenue') }}</span>
                            <strong>{{ formatCurrency(revenue) }}</strong>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useSalesOrdersStore } from '@/stores/salesOrders';
import { useQuotesStore } from '@/stores/quotes';
import { useInvoicesStore } from '@/stores/invoices';

const salesStore = useSalesOrdersStore();
const quotesStore = useQuotesStore();
const invoicesStore = useInvoicesStore();

const statusTagType = (status) => {
    const value = String(status || '').toLowerCase();
    if (['completed', window.t('complete'), 'paid', 'delivered'].includes(value)) return 'success';
    if (['pending', window.t('hanging'), window.t('in_process')].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), window.t('canceled')].includes(value)) return 'danger';
    return 'info';
};

// Calculate revenue from invoices (confirmed, processing, shipped, delivered)
const revenue = computed(() => {
    return invoicesStore.invoices
        .filter(invoice => 
            ['confirmed', 'processing', 'shipped', 'delivered'].includes(invoice.status)
        )
        .reduce((sum, invoice) => sum + (parseFloat(invoice.total) || 0), 0);
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0) + ' ' + (window.t('currency') || 'SYP');
};

const stats = computed(() => [
    { title: window.t('sales_orders'), value: salesStore.orders.length, tag: window.t('fast'), type: 'success' },
    { title: window.t('invoices'), value: invoicesStore.invoices.length, tag: window.t('updated'), type: 'info' },
    { title: window.t('quotes'), value: quotesStore.quotes.length, tag: window.t('new'), type: 'warning' },
    { title: window.t('revenue'), value: formatCurrency(revenue.value), tag: window.t('tracking'), type: 'primary' }
]);

const refreshSummary = async () => {
    await Promise.all([
        salesStore.fetchOrders().catch(() => {}),
        quotesStore.fetchQuotes().catch(() => {}),
        invoicesStore.fetchInvoices().catch(() => {})
    ]);
};

onMounted(refreshSummary);
</script>

<style scoped>
.sales-index {
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

.table-wrapper {
    min-height: 240px;
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
