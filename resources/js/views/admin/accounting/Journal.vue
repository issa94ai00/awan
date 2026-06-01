<template>
    <div class="accounting-journal">
        <el-card shadow="hover">
            <template #header>
                <span>دفتر اليومية</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.entries.length" :data="store.entries" style="width:100%">
                    <el-table-column prop="entry_date" label="التاريخ" width="160" />
                    <el-table-column prop="ledger_account.name" label="حساب" />
                    <el-table-column prop="description" label="الوصف" />
                    <el-table-column prop="debit" label="مدين" width="140" />
                    <el-table-column prop="credit" label="دائن" width="140" />
                    <el-table-column prop="reference" label="مرجع" width="180" />
                </el-table>
                <div v-if="!store.entries.length" style="padding:1rem">لا توجد قيود دفتر يومية لعرضها.</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useJournalEntriesStore } from '@/stores/journalEntries';

const store = useJournalEntriesStore();

onMounted(() => {
    store.fetchEntries().catch(() => {});
});
</script>

<style scoped>
.accounting-journal {
    padding: 0;
}
</style>
