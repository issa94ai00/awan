<template>
    <div class="accounting-page accounting-index">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-calculator text-primary"></i> {{ $t('accounting_overview') || 'لوحة التحكم المحاسبية' }}</h1>
                <p>متابعة أداء الدورة الدفترية، وتوازن الأستاذ، وإحصائيات الحسابات والقيود اليومية.</p>
            </div>
            <div class="header-actions">
                <el-button-group>
                    <router-link to="/admin/accounting/journal">
                        <el-button type="primary" plain><i class="fas fa-book mr-1"></i> اليومية العامة</el-button>
                    </router-link>
                    <router-link to="/admin/accounting/ledger">
                        <el-button type="success" plain><i class="fas fa-list-ol mr-1"></i> دليل الحسابات</el-button>
                    </router-link>
                    <router-link to="/admin/accounting/trial-balance">
                        <el-button type="warning" plain><i class="fas fa-balance-scale mr-1"></i> ميزان المراجعة</el-button>
                    </router-link>
                </el-button-group>
            </div>
        </div>

        <!-- Metric Indicators Row -->
        <el-row :gutter="16" class="overview-cards">
            <!-- Assets Card -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(assetsSum).toFixed(2) }}</h3>
                            <p>إجمالي الأصول (Assets)</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- Liabilities Card -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box orange-grad">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(liabilitiesSum).toFixed(2) }}</h3>
                            <p>إجمالي الخصوم (Liabilities)</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- Equity Card -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-details">
                            <h3>${{ parseFloat(equitySum).toFixed(2) }}</h3>
                            <p>حقوق الملكية (Equity)</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- Balance Check Card -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-boxpurple-grad" :class="isBalanced ? 'purple-grad' : 'red-grad'">
                            <i class="fas" :class="isBalanced ? 'fa-check-double' : 'fa-exclamation-triangle'"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ isBalanced ? 'متوازن' : 'غير متوازن' }}</h3>
                            <p>حالة الدورة الدفترية</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <!-- Left: Structure & Distribution -->
            <el-col :xs="24" :lg="10">
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-chart-pie text-muted"></i> هيكل الحسابات وتوزيع الأرصـدة</span>
                        </div>
                    </template>
                    <div class="distribution-block">
                        <div class="dist-row">
                            <div class="dist-label"><span>الأصول (Assets)</span><span>${{ parseFloat(assetsSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(assetsSum)" status="success" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>الخصوم (Liabilities)</span><span>${{ parseFloat(liabilitiesSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(liabilitiesSum)" status="warning" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>حقوق الملكية (Equity)</span><span>${{ parseFloat(equitySum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(equitySum)" />
                        </div>
                        <div class="dist-row">
                            <div class="dist-label"><span>الإيرادات (Revenue)</span><span>${{ parseFloat(revenueSum).toFixed(2) }}</span></div>
                            <el-progress :percentage="calculatePercentage(revenueSum)" status="exception" />
                        </div>
                    </div>
                </el-card>

                <!-- Accounts Stats Card -->
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-info-circle text-muted"></i> ملخص دليل الحسابات</span>
                        </div>
                    </template>
                    <div class="summary-list">
                        <div class="summary-item">
                            <span>عدد حسابات دفتر الأستاذ:</span>
                            <strong>{{ accountsStore.accounts.length }} حساب</strong>
                        </div>
                        <div class="summary-item">
                            <span>إجمالي الحركات المحاسبية الموثقة:</span>
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
                            <span><i class="fas fa-history text-muted"></i> آخر القيود المحاسبية المسجلة</span>
                        </div>
                    </template>

                    <div v-if="loadingData" style="padding: 2rem;">
                        <el-skeleton :rows="5" animated />
                    </div>
                    <div v-else>
                        <el-table :data="recentEntries" style="width: 100%" stripe size="small">
                            <el-table-column prop="entry_date" label="التاريخ" width="110" />
                            <el-table-column prop="ledger_account.name" label="الحساب" min-width="120" />
                            <el-table-column prop="description" label="البيان/الوصف" min-width="150" show-overflow-tooltip />
                            <el-table-column prop="debit" label="مدين" width="100">
                                <template #default="{ row }">
                                    <span v-if="row.debit > 0" class="text-success">${{ parseFloat(row.debit).toFixed(2) }}</span>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="credit" label="دائن" width="100">
                                <template #default="{ row }">
                                    <span v-if="row.credit > 0" class="text-warning">${{ parseFloat(row.credit).toFixed(2) }}</span>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div v-if="!recentEntries.length" class="empty-state" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            لا توجد قيود يومية مسجلة حالياً.
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

const accountsStore = useLedgerAccountsStore();
const journalStore = useJournalEntriesStore();
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
    if (!journalStore.trialBalance?.totals) return true;
    const debits = parseFloat(journalStore.trialBalance.totals.debits || 0).toFixed(2);
    const credits = parseFloat(journalStore.trialBalance.totals.credits || 0).toFixed(2);
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

onMounted(async () => {
    loadingData.value = true;
    try {
        await Promise.all([
            accountsStore.fetchAccounts({ per_page: 100 }),
            journalStore.fetchEntries({ per_page: 50 }),
            journalStore.fetchTrialBalance()
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
</style>
