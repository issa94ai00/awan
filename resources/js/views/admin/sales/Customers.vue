<template>
    <div class="sales-page sales-customers">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('customers') }}</h1>
                <p>{{ $t('customer_database_with_clear_font') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_customer_name_email_or_phone')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_customers') }}</p>
                    <h3>{{ store.customers.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('latest_clients') }}</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('customer_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredCustomers.length" :data="filteredCustomers" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="name" :label="$t('name')" width="180" />
                    <el-table-column prop="company" :label="$t('company')" width="180" />
                    <el-table-column prop="email" :label="$t('email')" width="220" />
                    <el-table-column prop="phone" :label="$t('phone')" width="160" />
                    <el-table-column :label="$t('status')" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.status ? 'success' : 'info'">{{ row.status || $t('undefined') }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!filteredCustomers.length" class="empty-state">{{ $t('there_are_no_clients_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useCustomersStore } from '@/stores/customers';

const store = useCustomersStore();
const searchQuery = ref('');

const filteredCustomers = computed(() => {
    if (!searchQuery.value.trim()) return store.customers;
    const query = searchQuery.value.toLowerCase();
    return store.customers.filter((customer) => {
        return [
            customer.name,
            customer.company,
            customer.email,
            customer.phone,
            customer.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.customers.length, 5));

onMounted(() => {
    store.fetchCustomers().catch(() => {});
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
