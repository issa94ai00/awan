<template>
    <div class="sales-quotes">
        <el-card shadow="hover">
            <template #header>
                <span>عروض الأسعار</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.quotes.length" :data="store.quotes" style="width:100%">
                    <el-table-column prop="quote_number" label="#" width="140" />
                    <el-table-column prop="customer.name" label="العميل" />
                    <el-table-column prop="total" label="الإجمالي" width="140" />
                    <el-table-column prop="status" label="الحالة" width="120" />
                    <el-table-column prop="valid_until" label="ساري حتى" width="160" />
                </el-table>
                <div v-if="!store.quotes.length" style="padding:1rem">لا توجد عروض أسعار لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useQuotesStore } from '@/stores/quotes';

const store = useQuotesStore();

onMounted(() => {
    store.fetchQuotes().catch(() => {});
});
</script>

<style scoped>
.sales-quotes {
    padding: 0;
}
</style>
