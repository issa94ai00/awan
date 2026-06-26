<template>
    <div class="accounting-trial-balance">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('trial_balance') }}</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="store.trialBalance?.accounts?.length" :data="store.trialBalance.accounts" style="width:100%">
                    <el-table-column prop="code" :label="$t('code')" width="120" />
                    <el-table-column prop="name" :label="$t('the_account')" />
                    <el-table-column prop="type" :label="$t('type')" width="140" />
                    <el-table-column prop="debits" :label="$t('debtor')" width="160" />
                    <el-table-column prop="credits" :label="$t('creditor')" width="160" />
                    <el-table-column prop="balance" :label="$t('balance')" width="160" />
                </el-table>
                <div v-if="!store.trialBalance?.accounts?.length" style="padding:1rem">{{ $t('there_is_no_trial_balance') }}</div>
                <div v-if="store.trialBalance?.totals" style="margin-top:1rem; display:flex; gap:1rem;">
                    <el-tag type="success">{{ $t('total_debit') }} {{ store.trialBalance.totals.debits }}</el-tag>
                    <el-tag type="warning">{{ $t('total_credit') }} {{ store.trialBalance.totals.credits }}</el-tag>
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
