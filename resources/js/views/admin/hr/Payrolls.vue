<template>
    <div class="hr-payrolls">
        <el-card shadow="hover">
            <template #header>
                <span>الرواتب</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.payrolls.length" :data="store.payrolls" style="width:100%">
                    <el-table-column prop="payroll_number" label="#" width="140" />
                    <el-table-column prop="employee.name" label="الموظف" />
                    <el-table-column prop="net_salary" label="الصافي" width="140" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                </el-table>

                <div v-if="!store.payrolls.length" style="padding:1rem">لا توجد مسيرات لعرضها.</div>
            </div>
        </el-card>
    </div>

</template>

<script setup>
import { onMounted } from 'vue';
import { usePayrollsStore } from '@/stores/payrolls';

const store = usePayrollsStore();

onMounted(() => {
    store.fetchPayrolls().catch(() => {});
});
</script>

<style scoped>
.hr-payrolls {
    padding: 0;
}
</style>
