<template>
    <div class="accounting-trial-balance">
        <el-card shadow="hover">
            <template #header>
                <span>ميزان المراجعة</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.trialBalance?.accounts?.length" :data="store.trialBalance.accounts" style="width:100%">
                    <el-table-column prop="code" label="الكود" width="120" />
                    <el-table-column prop="name" label="الحساب" />
                    <el-table-column prop="type" label="النوع" width="140" />
                    <el-table-column prop="debits" label="المدين" width="160" />
                    <el-table-column prop="credits" label="الدائن" width="160" />
                    <el-table-column prop="balance" label="الرصيد" width="160" />
                </el-table>
                <div v-if="!store.trialBalance?.accounts?.length" style="padding:1rem">لا توجد بيانات ميزان المراجعة لعرضها.</div>
                <div v-if="store.trialBalance?.totals" style="margin-top:1rem; display:flex; gap:1rem;">
                    <el-tag type="success">إجمالي المدين: {{ store.trialBalance.totals.debits }}</el-tag>
                    <el-tag type="warning">إجمالي الدائن: {{ store.trialBalance.totals.credits }}</el-tag>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useJournalEntriesStore } from '@/stores/journalEntries';

const store = useJournalEntriesStore();

onMounted(() => {
    store.fetchTrialBalance().catch(() => {});
});
</script>

<style scoped>
.accounting-trial-balance {
    padding: 0;
}
</style>
