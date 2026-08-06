<template>
    <div class="hr-page hr-payrolls">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('salary_marches') }}</h1>
                <p>{{ $t('comprehensive_display_of_benefits_status') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_job_number_or_employee_name')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_marches') }}</p>
                    <h3>{{ store.payrolls.length }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('completed_marches') }}</p>
                    <h3>{{ completedCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('hanging_marches') }}</p>
                    <h3>{{ pendingCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('employee_payroll_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredPayrolls.length" :data="filteredPayrolls" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="payroll_number" label="#" width="140" />
                    <el-table-column prop="employee.name" :label="$t('employee')" />
                    <el-table-column prop="net_salary" :label="$t('net')" width="140" />
                    <el-table-column :label="$t('status')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ row.status || $t('undefined') }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!filteredPayrolls.length" class="empty-state">{{ $t('there_are_no_paths_matching_your_search') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePayrollsStore } from '@/stores/payrolls';

const store = usePayrollsStore();
const searchQuery = ref('');

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', window.t('complete'), 'paid'].includes(value)) return 'success';
    if (['pending', window.t('hanging'), window.t('in_process')].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), window.t('canceled')].includes(value)) return 'danger';
    return 'info';
};

const filteredPayrolls = computed(() => {
    if (!searchQuery.value.trim()) return store.payrolls;
    const query = searchQuery.value.toLowerCase();
    return store.payrolls.filter((payroll) => {
        return [
            payroll.payroll_number,
            payroll.employee?.name,
            payroll.net_salary,
            payroll.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const completedCount = computed(() => store.payrolls.filter((payroll) => {
    const value = normalizeStatus(payroll.status);
    return ['completed', window.t('complete'), 'paid'].includes(value);
}).length);

const pendingCount = computed(() => store.payrolls.filter((payroll) => {
    const value = normalizeStatus(payroll.status);
    return ['pending', window.t('hanging'), window.t('in_process')].includes(value);
}).length);

onMounted(() => {
    store.fetchPayrolls().catch(() => {});
});
</script>

<style scoped>
.hr-page {
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
