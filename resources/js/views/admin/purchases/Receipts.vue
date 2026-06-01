<template>
    <div class="purchases-receipts">
        <el-card shadow="hover">
            <template #header>
                <span>إيصالات الاستلام</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.receipts.length" :data="store.receipts" style="width:100%">
                    <el-table-column prop="receipt_number" label="#" width="140" />
                    <el-table-column prop="supplier.name" label="المورد" />
                    <el-table-column prop="receipt_date" label="التاريخ" width="160" />
                    <el-table-column prop="notes" label="ملاحظات" />
                </el-table>
                <div v-if="!store.receipts.length" style="padding:1rem">لا توجد إيصالات للاستلام لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';

const store = usePurchaseReceiptsStore();

onMounted(() => {
    store.fetchReceipts().catch(() => {});
});
</script>

<style scoped>
.purchases-receipts {
    padding: 0;
}
</style>
