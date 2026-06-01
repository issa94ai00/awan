<template>
    <div class="sales-orders">
        <el-card shadow="hover">
            <template #header>
                <span>طلبات البيع</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.orders.length" :data="store.orders" style="width:100%">
                    <el-table-column prop="order_number" label="#" width="140" />
                    <el-table-column prop="customer.name" label="العميل" />
                    <el-table-column prop="total" label="الإجمالي" width="140" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                    <el-table-column prop="order_date" label="تاريخ الطلب" width="160" />
                </el-table>
                <div v-if="!store.orders.length" style="padding:1rem">لا توجد طلبات بيع لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSalesOrdersStore } from '@/stores/salesOrders';

const store = useSalesOrdersStore();

onMounted(() => {
    store.fetchOrders().catch(() => {});
});
</script>

<style scoped>
.sales-orders {
    padding: 0;
}
</style>
