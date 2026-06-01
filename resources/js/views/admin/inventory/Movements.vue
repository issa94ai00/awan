<template>
    <div class="inventory-movements">
        <el-card shadow="hover">
            <template #header>
                <span>حركات المخزون</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.movements.length" :data="store.movements" style="width:100%">
                    <el-table-column prop="product.name_ar" label="المنتج" />
                    <el-table-column prop="movement_type" label="النوع" width="120" />
                    <el-table-column prop="quantity" label="الكمية" width="120" />
                    <el-table-column prop="reference" label="مرجع" width="180" />
                    <el-table-column prop="created_at" label="التاريخ" width="180" />
                </el-table>
                <div v-if="!store.movements.length" style="padding:1rem">لا توجد حركات مخزون لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useStockMovementsStore } from '@/stores/stockMovements';

const store = useStockMovementsStore();

onMounted(() => {
    store.fetchMovements().catch(() => {});
});
</script>

<style scoped>
.inventory-movements {
    padding: 0;
}
</style>
