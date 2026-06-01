<template>
    <div class="pos-index">
        <el-card shadow="hover">
            <template #header>
                <span>نقطة البيع</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <div style="display:flex;gap:1rem">
                    <el-input placeholder="ابحث عن منتج" v-model="query" @input="onSearch" clearable />
                </div>

                <el-table v-if="store.products.length" :data="store.products" style="width:100%; margin-top:1rem">
                    <el-table-column prop="name_ar" label="الاسم" />
                    <el-table-column prop="sku" label="SKU" width="120" />
                    <el-table-column prop="price" label="السعر" width="120" />
                </el-table>

                <div v-if="!store.products.length" style="padding:1rem">ابحث لعرض نتائج المنتج.</div>
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
