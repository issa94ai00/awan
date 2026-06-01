<template>
    <div class="sales-payments">
        <el-card shadow="hover">
            <template #header>
                <span>المدفوعات</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.payments.length" :data="store.payments" style="width:100%">
                    <el-table-column prop="reference" label="#" width="140" />
                    <el-table-column prop="amount" label="المبلغ" width="140" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                    <el-table-column prop="payment_date" label="تاريخ الدفع" width="160" />
                    <el-table-column prop="customer.name" label="العميل" />
                </el-table>
                <div v-if="!store.payments.length" style="padding:1rem">لا توجد مدفوعات لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { usePaymentsStore } from '@/stores/payments';

const store = usePaymentsStore();

onMounted(() => {
    store.fetchPayments().catch(() => {});
});
</script>

<style scoped>
.sales-payments {
    padding: 0;
}
</style>
