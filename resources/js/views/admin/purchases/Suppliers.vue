<template>
    <div class="purchases-page purchases-suppliers">
        <div class="page-header">
            <div class="page-title">
                <h1>الموردين</h1>
                <p>واجهة الموردين تم تصميمها لتسريع عرض البيانات مع فلترة سهلة.</p>
            </div>
            <el-input v-model="searchQuery" placeholder="ابحث بالاسم أو البريد أو الهاتف" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>إجمالي الموردين</p>
                    <h3>{{ store.suppliers.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>أحدث الموردين</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>قائمة الموردين</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="filteredSuppliers.length" :data="filteredSuppliers" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="name" label="الاسم" width="200" />
                    <el-table-column prop="company" label="الشركة" width="180" />
                    <el-table-column prop="email" label="البريد الإلكتروني" width="220" />
                    <el-table-column prop="phone" label="الهاتف" width="160" />
                    <el-table-column label="الحالة" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status || 'غير محدد' }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!filteredSuppliers.length" class="empty-state">لا يوجد موردين تطابق البحث.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useSuppliersStore } from '@/stores/suppliers';

const store = useSuppliersStore();
const searchQuery = ref('');

const filteredSuppliers = computed(() => {
    if (!searchQuery.value.trim()) return store.suppliers;
    const query = searchQuery.value.toLowerCase();
    return store.suppliers.filter((supplier) => {
        return [
            supplier.name,
            supplier.company,
            supplier.email,
            supplier.phone,
            supplier.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.suppliers.length, 5));

onMounted(() => {
    store.fetchSuppliers().catch(() => {});
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
