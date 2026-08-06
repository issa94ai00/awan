<template>
    <div class="pos-index">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('point_of_sale') }}</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <div style="display:flex;gap:1rem">
                    <el-input :placeholder="$t('search_for_a_product')" v-model="query" @input="onSearch" clearable />
                </div>

                <el-table v-if="store.products.length" :data="store.products" style="width:100%; margin-top:1rem">
                    <el-table-column prop="name_ar" :label="$t('name')" />
                    <el-table-column prop="sku" label="SKU" width="120" />
                    <el-table-column prop="price" :label="$t('the_price')" width="120" />
                </el-table>

                <div v-if="!store.products.length" style="padding:1rem">{{ $t('search_to_view_product_results') }}</div>
            </div>
        </el-card>
    </div>

</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePosStore } from '@/stores/pos';

const store = usePosStore();
const query = ref('');

onMounted(() => {
    store.loadOptions().catch(() => {});
});

let searchTimer = null;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (!query.value) return;
        store.lookupProducts(query.value).catch(() => {});
    }, 300);
}
</script>

<style scoped>
.pos-index {
    padding: 0;
}
</style>
