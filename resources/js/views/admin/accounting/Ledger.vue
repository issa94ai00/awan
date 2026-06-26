<template>
    <div class="accounting-ledger">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('ledger') }}</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="store.accounts.length" :data="store.accounts" style="width:100%">
                    <el-table-column prop="code" :label="$t('code')" width="120" />
                    <el-table-column prop="name" :label="$t('the_account')" />
                    <el-table-column prop="type" :label="$t('type')" width="140" />
                    <el-table-column prop="balance" :label="$t('balance')" width="160" />
                    <el-table-column prop="is_active" :label="$t('active')" width="120">
                        <template #default="{ row }">
                            <span>{{ row.is_active ? $t('yes') : $t('no') }}</span>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!store.accounts.length" style="padding:1rem">{{ $t('there_are_no_ledger_accounts_to_display') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';

const store = useLedgerAccountsStore();

onMounted(() => {
    store.fetchAccounts().catch(() => {});
});
</script>

<style scoped>
.accounting-ledger {
    padding: 0;
}
</style>
