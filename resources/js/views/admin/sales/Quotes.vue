<template>
    <div class="sales-page sales-quotes">
        <div class="page-header">
            <div class="page-title">
                <h1>عروض الأسعار</h1>
                <p>إدارة عروض الأسعار بسرعة مع بحث فوري وحالة واضحة لكل عرض.</p>
            </div>
            <el-input v-model="searchQuery" placeholder="ابحث برقم العرض أو اسم العميل" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>إجمالي العروض</p>
                    <h3>{{ store.quotes.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>العروض المعلقة</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>العروض المقبولة</p>
                    <h3>{{ acceptedCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>قائمة عروض الأسعار</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="filteredQuotes.length" :data="filteredQuotes" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="quote_number" label="#" width="140" />
                    <el-table-column prop="customer.name" label="العميل" />
                    <el-table-column prop="total" label="الإجمالي" width="140" />
                    <el-table-column label="الحالة" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || 'غير محدد' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="valid_until" label="ساري حتى" width="160" />
                </el-table>

                <div v-if="!filteredQuotes.length" class="empty-state">لا توجد عروض تطابق البحث.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useQuotesStore } from '@/stores/quotes';

const store = useQuotesStore();
const searchQuery = ref('');

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['accepted', 'مقبول', 'approved'].includes(value)) return 'success';
    if (['draft', 'pending', 'معلق', 'قيد المعالجة'].includes(value)) return 'warning';
    if (['rejected', 'ملغى', 'cancelled'].includes(value)) return 'danger';
    return 'info';
};

const filteredQuotes = computed(() => {
    if (!searchQuery.value.trim()) return store.quotes;
    const query = searchQuery.value.toLowerCase();
    return store.quotes.filter((quote) => {
        return [
            quote.quote_number,
            quote.customer?.name,
            quote.status,
            quote.valid_until
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const pendingCount = computed(() => store.quotes.filter((quote) => {
    const value = normalizeStatus(quote.status);
    return ['draft', 'pending', 'معلق', 'قيد المعالجة'].includes(value);
}).length);

const acceptedCount = computed(() => store.quotes.filter((quote) => {
    const value = normalizeStatus(quote.status);
    return ['accepted', 'approved', 'مقبول'].includes(value);
}).length);

onMounted(() => {
    store.fetchQuotes().catch(() => {});
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
