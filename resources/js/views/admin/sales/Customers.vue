<template>
    <div class="sales-customers">
        <el-card shadow="hover">
            <template #header>
                <span>العملاء</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.customers.length" :data="store.customers" style="width:100%">
                    <el-table-column prop="name" label="الاسم" width="180" />
                    <el-table-column prop="company" label="الشركة" width="180" />
                    <el-table-column prop="email" label="البريد الإلكتروني" width="220" />
                    <el-table-column prop="phone" label="الهاتف" width="160" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                </el-table>
                <div v-if="!store.customers.length" style="padding:1rem">لا يوجد عملاء لعرضهم.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useCustomersStore } from '@/stores/customers';

const store = useCustomersStore();

onMounted(() => {
    store.fetchCustomers().catch(() => {});
});
</script>

<style scoped>
.sales-customers {
    padding: 0;
}
</style>
