<template>
    <div class="sales-page sales-invoices">
        <div class="page-header">
            <div class="page-title">
                <h1>الفواتير</h1>
                <p>متابعة الفواتير بسرعة مع بحث فوري وعرض مركّز للحالة والمبلغ.</p>
            </div>
            <el-input v-model="searchQuery" placeholder="ابحث برقم الفاتورة أو اسم العميل" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>إجمالي الفواتير</p>
                    <h3>{{ store.invoices.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الفواتير المعلقة</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الفواتير المدفوعة</p>
                    <h3>{{ paidCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>قائمة الفواتير</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="filteredInvoices.length" :data="filteredInvoices" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="invoice_number" label="#" width="120" />
                    <el-table-column prop="customer_name" label="العميل" />
                    <el-table-column prop="total" label="الإجمالي" width="120" />
                    <el-table-column label="الحالة" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || 'غير محدد' }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!filteredInvoices.length" class="empty-state">لا توجد فواتير تطابق البحث.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useInvoicesStore } from '@/stores/invoices';

const store = useInvoicesStore();
const searchQuery = ref('');

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['paid', 'مدفوعة', 'completed', 'مكتمل'].includes(value)) return 'success';
    if (['pending', 'معلقة', 'pending_payment', 'قيد المعالجة'].includes(value)) return 'warning';
    if (['cancelled', 'ملغاة', 'canceled'].includes(value)) return 'danger';
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
    return ['pending', 'معلقة', 'pending_payment', 'قيد المعالجة'].includes(value);
}).length);

const paidCount = computed(() => store.invoices.filter((invoice) => {
    const value = normalizeStatus(invoice.status);
    return ['paid', 'مدفوعة', 'completed', 'مكتمل'].includes(value);
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
