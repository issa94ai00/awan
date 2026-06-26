<template>
    <div class="accounting-journal">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('journal') }}</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="store.entries.length" :data="store.entries" style="width:100%">
                    <el-table-column prop="entry_date" :label="$t('the_date')" width="160" />
                    <el-table-column prop="ledger_account.name" :label="$t('account')" />
                    <el-table-column prop="description" :label="$t('description')" />
                    <el-table-column prop="debit" :label="$t('debtor')" width="140" />
                    <el-table-column prop="credit" :label="$t('creditor')" width="140" />
                    <el-table-column prop="reference" :label="$t('reference')" width="180" />
                </el-table>
                <div v-if="!store.entries.length" style="padding:1rem">{{ $t('there_are_no_journal_entries_to_view') }}</div>
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
