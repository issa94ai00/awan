<template>
    <div class="sales-invoices">
        <el-card shadow="hover">
            <template #header>
                <span>الفواتير</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.invoices.length" :data="store.invoices" style="width: 100%">
                    <el-table-column prop="invoice_number" label="#" width="120" />
                    <el-table-column prop="customer_name" label="العميل" />
                    <el-table-column prop="total" label="الإجمالي" width="120" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                </el-table>

                <div v-if="!store.invoices.length" style="padding:1rem">
                    لا توجد فواتير لعرضها.
                </div>
            </div>
        </el-card>
    </div>

</template>

<script setup>
import { onMounted } from 'vue';
import { useInvoicesStore } from '@/stores/invoices';

const store = useInvoicesStore();

onMounted(() => {
    store.fetchInvoices().catch(() => {});
});
</script>

<style scoped>
.sales-invoices {
    padding: 0;
}
</style>
