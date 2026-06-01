<template>
    <div class="purchases-suppliers">
        <el-card shadow="hover">
            <template #header>
                <span>الموردين</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.suppliers.length" :data="store.suppliers" style="width:100%">
                    <el-table-column prop="name" label="الاسم" width="200" />
                    <el-table-column prop="company" label="الشركة" width="180" />
                    <el-table-column prop="email" label="البريد الإلكتروني" width="220" />
                    <el-table-column prop="phone" label="الهاتف" width="160" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                </el-table>
                <div v-if="!store.suppliers.length" style="padding:1rem">لا يوجد موردين لعرضهم.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSuppliersStore } from '@/stores/suppliers';

const store = useSuppliersStore();

onMounted(() => {
    store.fetchSuppliers().catch(() => {});
});
</script>

<style scoped>
.purchases-suppliers {
    padding: 0;
}
</style>
