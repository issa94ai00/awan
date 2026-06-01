<template>
    <div class="purchases-orders">
        <el-card shadow="hover">
            <template #header>
                <span>طلبات الشراء</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.orders.length" :data="store.orders" style="width:100%">
                    <el-table-column prop="order_number" label="#" width="140" />
                    <el-table-column prop="supplier.name" label="المورد" />
                    <el-table-column prop="total" label="الإجمالي" width="140" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                    <el-table-column prop="due_date" label="تاريخ الاستحقاق" width="160" />
                </el-table>
                <div v-if="!store.orders.length" style="padding:1rem">لا توجد طلبات شراء لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';

const store = usePurchaseOrdersStore();

onMounted(() => {
    store.fetchOrders().catch(() => {});
});
</script>

<style scoped>
.purchases-orders {
    padding: 0;
}
</style>
