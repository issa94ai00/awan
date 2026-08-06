<template>
    <div class="reports-payroll">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('payroll_report') }}</span>
            </template>
            <div v-if="loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <el-row gutter="20">
                    <el-col :span="8">
                        <el-card>
                            <h3>{{ $t('total_marches') }}</h3>
                            <p>{{ payrollCount.total }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>{{ $t('suspended') }}</h3>
                            <p>{{ payrollCount.pending }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>{{ $t('payments') }}</h3>
                            <p>{{ payrollCount.paid }}</p>
                        </el-card>
                    </el-col>
                </el-row>
                <el-table :data="latestPayrolls" style="width:100%; margin-top:1rem">
                    <el-table-column prop="payroll_number" label="#" width="160" />
                    <el-table-column prop="employee.name" :label="$t('employee')" />
                    <el-table-column prop="net_salary" :label="$t('net')" width="140" />
                    <el-table-column prop="status" :label="$t('status')" width="120" />
                </el-table>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePayrollsStore } from '@/stores/payrolls';

const store = usePayrollsStore();
const loading = ref(true);
const payrollCount = ref({ total: 0, pending: 0, paid: 0, processed: 0 });
const latestPayrolls = ref([]);

const loadPayrolls = async () => {
    loading.value = true;
    try {
        await store.fetchPayrolls({ per_page: 10 });
        latestPayrolls.value = store.payrolls;
        payrollCount.value = {
            total: store.pagination.total,
            pending: store.payrolls.filter(p => p.status === 'pending').length,
            paid: store.payrolls.filter(p => p.status === 'paid').length,
            processed: store.payrolls.filter(p => p.status === 'processed').length,
        };
    } catch (e) {
        console.error('Failed to load payrolls', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadPayrolls();
});
</script>

<style scoped>
.reports-payroll {
    padding: 0;
}
</style>
