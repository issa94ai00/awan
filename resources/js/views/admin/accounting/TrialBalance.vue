<template>
    <div class="accounting-page accounting-trial-balance">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-balance-scale text-primary"></i> {{ $t('trial_balance') || 'ميزان المراجعة بالأرصدة والمجاميع' }}</h1>
                <p>التحقق من صحة القيد المزدوج ومطابقة إجمالي أرصدة الحسابات المدينة والدائنة.</p>
            </div>
            <div class="header-actions">
                <el-button type="success" plain @click="printTrialBalance"><i class="fas fa-print"></i> طباعة ميزان المراجعة</el-button>
            </div>
        </div>

        <!-- Verification status indicator alert -->
        <div class="verification-status-box mb-4" :class="isBalanced ? 'balanced-status' : 'out-of-balance-status'">
            <div class="status-icon-wrapper">
                <i class="fas" :class="isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
            </div>
            <div class="status-details">
                <h4 v-if="isBalanced">ميزان المراجعة متوازن بالكامل</h4>
                <h4 v-else>ميزان المراجعة غير متوازن! يوجد فروقات محاسبية</h4>
                <p v-if="isBalanced">يتطابق إجمالي الحسابات المدينة والدائنة بقيمة <strong>${{ totalDebits.toFixed(2) }}</strong>.</p>
                <p v-else>الفرق الحالي بين الجانبين هو: <strong class="text-danger">${{ discrepancyAmount.toFixed(2) }}</strong>. يرجى مراجعة قيود اليومية العامة.</p>
            </div>
        </div>

        <!-- Main Comparative Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-table text-muted"></i> أرصدة ميزان المراجعة للأستاذ العام</span>
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
                    <el-table-column prop="code" :label="$t('code') || 'رمز الحساب'" width="130" align="center">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="$t('the_account') || 'الحساب'" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark);">{{ row.name }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="$t('type') || 'التصنيف'" width="160" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.type)" effect="light">{{ getArabicType(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="debits" :label="$t('debtor') || 'الجانب المدين (Debits)'" width="160" align="right">
                        <template #default="{ row }">
                            <span v-if="row.debits > 0" class="text-success" style="font-weight: 600;">${{ parseFloat(row.debits).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="credits" :label="$t('creditor') || 'الجانب الدائن (Credits)'" width="160" align="right">
                        <template #default="{ row }">
                            <span v-if="row.credits > 0" class="text-warning" style="font-weight: 600;">${{ parseFloat(row.credits).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" :label="$t('balance') || 'صافي الرصيد الدفتري'" width="160" align="right">
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
                        <span>إجمالي الجانب المدين</span>
                        <h3 class="text-success">${{ totalDebits.toFixed(2) }}</h3>
                    </div>
                    <div class="summary-col">
                        <span>إجمالي الجانب الدائن</span>
                        <h3 class="text-warning">${{ totalCredits.toFixed(2) }}</h3>
                    </div>
                    <div class="summary-col status-col">
                        <span>حالة المطابقة المحاسبية</span>
                        <el-tag :type="isBalanced ? 'success' : 'danger'" effect="dark" style="font-size: 1rem; font-weight: 700; height: 38px; border-radius: 8px;">
                            {{ isBalanced ? 'متطابق ومكتمل' : 'يوجد فروقات' }}
                        </el-tag>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!store.trialBalance?.accounts?.length" class="empty-state-box">
                    <i class="fas fa-balance-scale empty-icon"></i>
                    <p>لا يوجد رصيد حالي لتكوين ميزان المراجعة. يرجى تسجيل قيود أولاً.</p>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';
import { useJournalEntriesStore } from '@/stores/journalEntries';

const store = useJournalEntriesStore();

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
    if (val.includes('asset')) return 'أصول';
    if (val.includes('liability')) return 'خصوم';
    if (val.includes('equity')) return 'حقوق ملكية';
    if (val.includes('revenue')) return 'إيرادات';
    if (val.includes('expense')) return 'مصروفات';
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
