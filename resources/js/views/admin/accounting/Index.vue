<template>
    <div class="accounting-page accounting-index">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-calculator text-primary"
            :title="$t('accounting_overview')"
            :subtitle="$t('accounting_index_subtitle')"
        >
            <template #actions>
                <el-button-group>
                    <router-link to="/admin/accounting/journal">
                        <el-button type="primary" plain><i class="fas fa-book mr-1"></i> {{ $t('general_journal') }}</el-button>
                    </router-link>
                    <router-link to="/admin/accounting/ledger">
                        <el-button type="success" plain><i class="fas fa-list-ol mr-1"></i> {{ $t('chart_of_accounts') }}</el-button>
                    </router-link>
                    <router-link to="/admin/accounting/trial-balance">
                        <el-button type="warning" plain><i class="fas fa-balance-scale mr-1"></i> {{ $t('trial_balance') }}</el-button>
                    </router-link>
                </el-button-group>
            </template>
        </AdminPageHeader>

        <!-- Cross-module consistency. Each module could already answer for
             itself; nothing asked the question across the system, so a single
             order whose invoice never reached the ledger stayed invisible until
             somebody happened to open it. -->
        <el-card shadow="hover" class="health-panel mb-4" v-loading="healthLoading">
            <template #header>
                <div class="health-header">
                    <span class="card-title-txt">
                        <i class="fas fa-heart-pulse"></i> {{ $t('system_integrity_checks') }}
                    </span>
                    <div class="health-header-right">
                        <span v-if="health.checked_at" class="health-time">آخر فحص {{ health.checked_at }}</span>
                        <el-button text size="small" :loading="healthLoading" @click="loadHealth">
                            <i class="fas fa-sync-alt"></i> {{ $t('run_checks_again') }}
                        </el-button>
                    </div>
                </div>
            </template>

            <div v-if="health.is_healthy" class="health-clear">
                <i class="fas fa-circle-check"></i>
                {{ $t('all_checks_passed') }}
            </div>

            <div v-else class="health-summary">
                <i class="fas fa-triangle-exclamation"></i>
                <span>
                    <strong>{{ health.issue_count }}</strong> {{ $t('checks_needing_attention') }} <strong>{{ health.affected_records }}</strong> {{ $t('records_suffix') }}
                </span>
            </div>

            <div class="health-grid">
                <div
                    v-for="check in orderedChecks"
                    :key="check.code"
                    class="health-check"
                    :class="check.ok ? 'is-ok' : 'is-bad'"
                >
                    <div class="check-head">
                        <i class="fas" :class="check.ok ? 'fa-circle-check' : 'fa-circle-exclamation'"></i>
                        <span class="check-title">{{ check.title }}</span>
                        <strong class="check-count">{{ check.count }}</strong>
                    </div>
                    <template v-if="!check.ok">
                        <p class="check-detail">{{ check.detail }}</p>
                        <p class="check-action"><i class="fas fa-arrow-turn-down"></i> {{ check.action }}</p>
                    </template>
                </div>
            </div>
        </el-card>

        <!-- Metric Indicators Row -->
        <AdminStatGrid>
            <!-- Assets Card -->
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(assetsSum).toFixed(2) }}</h3>
                            <p>{{ $t('total_assets_label') }}</p>
                        </div>
                    </div>
                </el-card>
            <!-- Liabilities Card -->
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box orange-grad">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(liabilitiesSum).toFixed(2) }}</h3>
                            <p>{{ $t('total_liabilities_label') }}</p>
                        </div>
                    </div>
                </el-card>
            <!-- Equity Card -->
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(equitySum).toFixed(2) }}</h3>
                            <p>{{ $t('type_equity') }}</p>
                        </div>
                    </div>
                </el-card>
            <!-- Balance Check Card -->
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-boxpurple-grad" :class="isBalanced ? 'purple-grad' : 'red-grad'">
                            <i class="fas" :class="isBalanced ? 'fa-check-double' : 'fa-exclamation-triangle'"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ isBalanced ? 'متوازن' : 'غير متوازن' }}</h3>
                            <p>{{ $t('bookkeeping_cycle_state') }}</p>
                        </div>
                    </div>
                </el-card>
        </AdminStatGrid>

        <el-row :gutter="20" class="mt-4">
            <!-- Left: Structure & Distribution -->
            <el-col :xs="24" :lg="10">
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-chart-pie text-muted"></i> {{ $t('account_structure_and_balances') }}</span>
                        </div>
                    </template>
                    <div class="distribution-block">
                        <div class="dist-row">
                            <div class="dist-label"><span>{{ $t('assets_group') }}</span><span>${{ parseFloat(assetsSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(assetsSum)" status="success" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>{{ $t('liabilities_group') }}</span><span>${{ parseFloat(liabilitiesSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(liabilitiesSum)" status="warning" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>{{ $t('type_equity') }}</span><span>${{ parseFloat(equitySum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(equitySum)" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>{{ $t('revenue_group') }}</span><span>${{ parseFloat(revenueSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(revenueSum)" status="exception" />
                        </div>
                    </div>
                </el-card>

                <!-- Accounts Stats Card -->
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-info-circle text-muted"></i> {{ $t('chart_of_accounts_summary') }}</span>
                        </div>
                    </template>
                    <div class="summary-list">
                        <div class="summary-item">
                            <span>{{ $t('ledger_accounts_count_label') }}</span>
                            <strong>{{ accountsStore.accounts.length }} حساب</strong>
                        </div>
                        <div class="summary-item">
                            <span>{{ $t('posted_entries_count_label') }}</span>
                            <strong>{{ journalStore.entries.length }} قيد</strong>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- Right: Recent Entries Table -->
            <el-col :xs="24" :lg="14">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-history text-muted"></i> {{ $t('latest_journal_entries') }}</span>
                        </div>
                    </template>

                    <div v-if="loadingData" style="padding: 2rem;">
                        <el-skeleton :rows="5" animated />
                    </div>
                    <div v-else>
                        <el-table :data="recentEntries" style="width: 100%" stripe size="small">
                            <el-table-column prop="entry_number" :label="$t('entry_number')" width="110" />
                            <el-table-column prop="entry_date" :label="$t('date')" width="110" />
                            <el-table-column prop="description" :label="$t('narration_description')" min-width="150" show-overflow-tooltip />
                            <el-table-column prop="total_debit" :label="$t('total_debit_amount')" width="120">
                                <template #default="{ row }">
                                    <span class="text-success">${{ parseFloat(row.total_debit).toFixed(2) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="total_credit" :label="$t('total_credit_amount')" width="120">
                                <template #default="{ row }">
                                    <span class="text-warning">${{ parseFloat(row.total_credit).toFixed(2) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="!recentEntries.length" class="empty-state" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            {{ $t('no_journal_entries_yet') }}
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { useJournalEntriesStore } from '@/stores/journalEntries';
import { useAccountingReportsStore } from '@/stores/accountingReports';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const accountsStore = useLedgerAccountsStore();
const journalStore = useJournalEntriesStore();
const reportsStore = useAccountingReportsStore();
const loadingData = ref(false);

const checkType = (type, target) => {
    const val = String(type || '').toLowerCase();
    const tgt = String(target || '').toLowerCase();
    return val === tgt || val === tgt + 's' || val + 's' === tgt;
};

const assetsSum = computed(() => {
    return accountsStore.accounts
        .filter(acc => checkType(acc.type, 'asset') || checkType(acc.account_type, 'asset'))
        .reduce((sum, acc) => sum + parseFloat(acc.balance || 0), 0);
});

const liabilitiesSum = computed(() => {
    return accountsStore.accounts
        .filter(acc => checkType(acc.type, 'liability') || checkType(acc.account_type, 'liability'))
        .reduce((sum, acc) => sum + parseFloat(acc.balance || 0), 0);
});

const equitySum = computed(() => {
    return accountsStore.accounts
        .filter(acc => checkType(acc.type, 'equity') || checkType(acc.account_type, 'equity'))
        .reduce((sum, acc) => sum + parseFloat(acc.balance || 0), 0);
});

const revenueSum = computed(() => {
    return accountsStore.accounts
        .filter(acc => checkType(acc.type, 'revenue') || checkType(acc.account_type, 'revenue'))
        .reduce((sum, acc) => sum + parseFloat(acc.balance || 0), 0);
});

const expenseSum = computed(() => {
    return accountsStore.accounts
        .filter(acc => checkType(acc.type, 'expense') || checkType(acc.account_type, 'expense'))
        .reduce((sum, acc) => sum + parseFloat(acc.balance || 0), 0);
});

const isBalanced = computed(() => {
    if (!reportsStore.trialBalance?.totals) return true;
    const debits = parseFloat(reportsStore.trialBalance.totals.debits || 0).toFixed(2);
    const credits = parseFloat(reportsStore.trialBalance.totals.credits || 0).toFixed(2);
    return debits === credits;
});

const recentEntries = computed(() => {
    return journalStore.entries.slice(0, 5);
});

const calculatePercentage = (val) => {
    const total = assetsSum.value + liabilitiesSum.value + equitySum.value + revenueSum.value + expenseSum.value;
    if (total === 0) return 0;
    return Math.min(Math.round((val / total) * 100), 100);
};

/* ------------------------------------------------------------------ *
 * System health
 *
 * Read-only by design: it reports what disagrees and how to fix it, and never
 * repairs anything itself. Writing to the books is not something a dashboard
 * should do while nobody is looking.
 * ------------------------------------------------------------------ */

const health = ref({ is_healthy: true, issue_count: 0, affected_records: 0, checks: [] });
const healthLoading = ref(false);

// Failures first: a clean check is confirmation, a failing one is work.
const orderedChecks = computed(() =>
    [...(health.value.checks || [])].sort((a, b) => Number(a.ok) - Number(b.ok))
);

const loadHealth = async () => {
    healthLoading.value = true;
    try {
        health.value = await reportsStore.fetchSystemHealth();
    } catch (e) {
        console.error('System health check failed', e);
    } finally {
        healthLoading.value = false;
    }
};

onMounted(async () => {
    loadingData.value = true;
    loadHealth();
    try {
        await Promise.all([
            accountsStore.fetchAccounts({ per_page: 100 }),
            journalStore.fetchEntries({ per_page: 50 }),
            reportsStore.fetchTrialBalance()
        ]);
    } catch (e) {
        console.error('Accounting index failed to load store data', e);
    } finally {
        loadingData.value = false;
    }
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

.overview-cards {
    margin-bottom: 1.5rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s ease;
}

.stat-card-wrapper:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.stat-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.blue-grad {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-light) 100%);
}

.orange-grad {
    background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
}

.green-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
}

.purple-grad {
    background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
}

.red-grad {
    background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 500;
}

.distribution-block {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.dist-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.dist-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-medium);
}

.summary-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: var(--text-medium);
}

.summary-item strong {
    color: var(--text-dark);
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}
/* ---- System health ---- */
.health-panel { border-radius: 1rem; }

.health-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.health-header-right { display: flex; align-items: center; gap: 0.75rem; }
.health-time { font-size: 0.75rem; color: var(--text-muted); }
.card-title-txt { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; }
.card-title-txt i { color: var(--el-color-primary); }

.health-clear,
.health-summary {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    font-size: 0.88rem;
}

.health-clear { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.health-summary { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 0.75rem;
}

.health-check {
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    border-inline-start-width: 4px;
}

/* A passing check stays quiet; a failing one carries its own weight. */
.health-check.is-ok { border-inline-start-color: var(--el-color-success); opacity: 0.75; }
.health-check.is-bad { border-inline-start-color: var(--el-color-danger); background: #fffbfb; }

.check-head { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
.check-head i { flex-shrink: 0; }
.health-check.is-ok .check-head i { color: var(--el-color-success); }
.health-check.is-bad .check-head i { color: var(--el-color-danger); }
.check-title { flex: 1; min-width: 0; }
.check-count { font-size: 1rem; font-variant-numeric: tabular-nums; }

.check-detail { margin: 0.5rem 0 0; font-size: 0.78rem; line-height: 1.7; color: var(--text-muted); }

.check-action {
    margin: 0.35rem 0 0;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--el-color-danger);
    overflow-wrap: anywhere;
}
</style>
