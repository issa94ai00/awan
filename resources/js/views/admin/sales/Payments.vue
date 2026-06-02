<template>
    <div class="sales-page sales-payments">
        <div class="page-header">
            <div class="page-title">
                <h1>المدفوعات</h1>
                <p>تتبع المدفوعات الواردة وعرض التفاصيل المالية بوضوح.</p>
            </div>
            <el-input v-model="searchQuery" placeholder="ابحث برقم العملية أو اسم العميل" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>إجمالي المدفوعات</p>
                    <h3>{{ store.payments.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>المدفوعات المكتملة</p>
                    <h3>{{ completedCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>أحدث المدفوعات</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>قائمة المدفوعات</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="filteredPayments.length" :data="filteredPayments" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="reference" label="#" width="140" />
                    <el-table-column prop="amount" label="المبلغ" width="140" />
                    <el-table-column label="الحالة" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || 'غير محدد' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="payment_date" label="تاريخ الدفع" width="160" />
                    <el-table-column prop="customer.name" label="العميل" />
                </el-table>

                <div v-if="!filteredPayments.length" class="empty-state">لا توجد مدفوعات تطابق البحث.</div>
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
    if (['completed', 'مكتملة', 'paid'].includes(value)) return 'success';
    if (['pending', 'معلقة', 'قيد المعالجة'].includes(value)) return 'warning';
    if (['failed', 'فشل', 'cancelled', 'ملغاة'].includes(value)) return 'danger';
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
    return ['completed', 'مكتملة', 'paid'].includes(value);
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
