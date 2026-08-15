<template>
    <div class="accounting-page accounting-trial-balance">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-balance-scale text-primary"
            :title="$t('trial_balance')"
            :subtitle="$t('trial_balance_subtitle')"
        >
            <template #actions>
                <el-button type="success" plain @click="printTrialBalance"><i class="fas fa-print"></i> {{ $t('print_trial_balance') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- Verification status indicator alert -->
        <div class="verification-status-box mb-4" :class="isBalanced ? 'balanced-status' : 'out-of-balance-status'">
            <div class="status-icon-wrapper">
                <i class="fas" :class="isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
            </div>
            <div class="status-details">
                <h4 v-if="isBalanced">{{ $t('trial_balance_balanced') }}</h4>
                <h4 v-else>{{ $t('trial_balance_unbalanced') }}</h4>
                <p v-if="isBalanced">{{ $t('debits_match_credits_at') }} <strong>${{ totalDebits.toFixed(2) }}</strong>.</p>
                <p v-else>{{ $t('current_difference_is') }} <strong class="text-danger">${{ discrepancyAmount.toFixed(2) }}</strong>{{ $t('please_review_journal_entries') }}</p>
            </div>
        </div>

        <!-- Main Comparative Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-table text-muted"></i> {{ $t('trial_balance_of_general_ledger') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="store.trialBalance?.accounts?.length" 
                    :data="store.trialBalance.accounts" 
                    style="width: 100%" 
                    stripe 
                    class="custom-table print-table"
                >
                    <el-table-column prop="code" :label="$t('code')" width="130" align="center">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="$t('the_account')" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark);">{{ row.name }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="$t('type')" width="160" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.type)" effect="light">{{ getArabicType(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="debits" :label="$t('debtor')" width="160" align="right">
                        <template #default="{ row }">
                            <span v-if="row.debits > 0" class="text-success" style="font-weight: 600;">${{ parseFloat(row.debits).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="credits" :label="$t('creditor')" width="160" align="right">
                        <template #default="{ row }">
                            <span v-if="row.credits > 0" class="text-warning" style="font-weight: 600;">${{ parseFloat(row.credits).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" :label="$t('balance')" width="160" align="right">
                        <template #default="{ row }">
                            <strong :class="parseFloat(row.balance) >= 0 ? 'text-success' : 'text-danger'">
                                ${{ parseFloat(row.balance || 0).toFixed(2) }}
                            </strong>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Comparative summary card footer -->
                <div v-if="store.trialBalance?.totals" class="totals-summary-footer mt-4">
                    <div class="summary-col">
                        <span>{{ $t('total_debit_side') }}</span>
                        <h3 class="text-success">${{ totalDebits.toFixed(2) }}</h3>
                    </div>
                    <div class="summary-col">
                        <span>{{ $t('total_credit_side') }}</span>
                        <h3 class="text-warning">${{ totalCredits.toFixed(2) }}</h3>
                    </div>
                    <div class="summary-col status-col">
                        <span>{{ $t('reconciliation_state') }}</span>
                        <el-tag :type="isBalanced ? 'success' : 'danger'" effect="dark" style="font-size: 1rem; font-weight: 700; height: 38px; border-radius: 8px;">
                            {{ isBalanced ? 'متطابق ومكتمل' : 'يوجد فروقات' }}
                        </el-tag>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!store.trialBalance?.accounts?.length" class="empty-state-box">
                    <i class="fas fa-balance-scale empty-icon"></i>
                    <p>{{ $t('no_balances_for_trial_balance') }}</p>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { onMounted, computed } from 'vue';
import { useAccountingReportsStore } from '@/stores/accountingReports';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();

const store = useAccountingReportsStore();

const totalDebits = computed(() => {
    return parseFloat(store.trialBalance?.totals?.debits || 0);
});

const totalCredits = computed(() => {
    return parseFloat(store.trialBalance?.totals?.credits || 0);
});

const isBalanced = computed(() => {
    return totalDebits.value.toFixed(2) === totalCredits.value.toFixed(2);
});

const discrepancyAmount = computed(() => {
    return Math.abs(totalDebits.value - totalCredits.value);
});

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return 'success';
    if (val.includes('liability')) return 'warning';
    if (val.includes('equity')) return 'danger';
    if (val.includes('revenue')) return 'info';
    return 'info';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return t('assets_short');
    if (val.includes('liability')) return t('liabilities_short');
    if (val.includes('equity')) return t('equity_plain');
    if (val.includes('revenue')) return t('revenue_short');
    if (val.includes('expense')) return t('expenses_short');
    return type;
};

const printTrialBalance = () => {
    window.print();
};

onMounted(() => {
    store.fetchTrialBalance().catch(() => {});
});
</script>

<style scoped>
.accounting-page {
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.verification-status-box {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
    padding: 1.5rem;
    border-radius: var(--radius-md);
    border: 1px solid;
}

.verification-status-box.balanced-status {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.verification-status-box.out-of-balance-status {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #991b1b;
}

.status-icon-wrapper i {
    font-size: 2rem;
    margin-top: 0.1rem;
}

.status-details h4 {
    margin: 0 0 0.5rem 0;
    font-weight: 700;
    font-size: 1.05rem;
}

.status-details p {
    margin: 0;
    font-size: 0.9rem;
}

.code-badge {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.15rem 0.5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-family: monospace;
    font-size: 0.85rem;
}

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.totals-summary-footer {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.5rem;
    border-radius: var(--radius-md);
    flex-wrap: wrap;
    gap: 1rem;
}

.summary-col {
    text-align: center;
}

.summary-col span {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.summary-col h3 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 800;
}

.summary-col.status-col {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

@media print {
    .page-header, .verification-status-box, .empty-state-box {
        display: none !important;
    }
    .print-table {
        width: 100% !important;
    }
}
</style>
