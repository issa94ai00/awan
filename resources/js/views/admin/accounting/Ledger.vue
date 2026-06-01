<template>
    <div class="accounting-ledger">
        <el-card shadow="hover">
            <template #header>
                <span>دفتر الأستاذ</span>
            </template>
            <div v-if="store.loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-table v-if="store.accounts.length" :data="store.accounts" style="width:100%">
                    <el-table-column prop="code" label="الكود" width="120" />
                    <el-table-column prop="name" label="الحساب" />
                    <el-table-column prop="type" label="النوع" width="140" />
                    <el-table-column prop="balance" label="الرصيد" width="160" />
                    <el-table-column prop="is_active" label="نشط" width="120">
                        <template #default="{ row }">
                            <span>{{ row.is_active ? 'نعم' : 'لا' }}</span>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!store.accounts.length" style="padding:1rem">لا توجد حسابات دفتر الأستاذ لعرضها.</div>
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
