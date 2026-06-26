<template>
    <div class="inventory-movements">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('inventory_movements') }}</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="store.movements.length" :data="store.movements" style="width:100%">
                    <el-table-column prop="product.name_ar" :label="$t('product')" />
                    <el-table-column prop="movement_type" :label="$t('type')" width="120" />
                    <el-table-column prop="quantity" :label="$t('quantity')" width="120" />
                    <el-table-column prop="reference" :label="$t('reference')" width="180" />
                    <el-table-column prop="created_at" :label="$t('the_date')" width="180" />
                </el-table>
                <div v-if="!store.movements.length" style="padding:1rem">{{ $t('there_are_no_inventory_transactions') }}</div>
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
