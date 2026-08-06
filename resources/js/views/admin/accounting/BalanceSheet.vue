<template>
    <div class="accounting-page accounting-balance-sheet">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-scale-balanced text-primary"></i> {{ $t('balance_sheet') || 'الميزانية العمومية' }}</h1>
                <p>الأصول = الخصوم + حقوق الملكية، كما في تاريخ اليوم.</p>
            </div>
            <div class="header-actions">
                <el-button type="success" plain @click="printReport"><i class="fas fa-print"></i> طباعة</el-button>
            </div>
        </div>

        <!-- Verification status indicator -->
        <div class="verification-status-box mb-4" :class="isBalanced ? 'balanced-status' : 'out-of-balance-status'">
            <div class="status-icon-wrapper">
                <i class="fas" :class="isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
            </div>
            <div class="status-details">
                <h4 v-if="isBalanced">الميزانية متوازنة</h4>
                <h4 v-else>الميزانية غير متوازنة! يوجد فروقات محاسبية</h4>
                <p>الأصول: <strong>${{ totalAssets.toFixed(2) }}</strong> — الخصوم + حقوق الملكية: <strong>${{ (totalLiabilities + totalEquity).toFixed(2) }}</strong></p>
            </div>
        </div>

        <el-row :gutter="20">
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header"><span><i class="fas fa-building-columns text-muted"></i> الأصول</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="4" animated /></div>
                    <el-table v-else :data="assetAccounts" style="width: 100%" stripe class="custom-table print-table">
                        <el-table-column prop="code" label="الرمز" width="100" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" label="الحساب" min-width="140" />
                        <el-table-column prop="balance" label="الرصيد" width="120" align="right">
                            <template #default="{ row }"><strong class="text-success">${{ parseFloat(row.balance).toFixed(2) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div class="section-total">الإجمالي: <strong>${{ totalAssets.toFixed(2) }}</strong></div>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header"><span><i class="fas fa-hand-holding-dollar text-muted"></i> الخصوم</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="4" animated /></div>
                    <el-table v-else :data="liabilityAccounts" style="width: 100%" stripe class="custom-table print-table">
                        <el-table-column prop="code" label="الرمز" width="100" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" label="الحساب" min-width="140" />
                        <el-table-column prop="balance" label="الرصيد" width="120" align="right">
                            <template #default="{ row }"><strong class="text-warning">${{ parseFloat(row.balance).toFixed(2) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div class="section-total">الإجمالي: <strong>${{ totalLiabilities.toFixed(2) }}</strong></div>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header"><span><i class="fas fa-shield-halved text-muted"></i> حقوق الملكية</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="4" animated /></div>
                    <el-table v-else :data="equityAccounts" style="width: 100%" stripe class="custom-table print-table">
                        <el-table-column prop="code" label="الرمز" width="100" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" label="الحساب" min-width="140" />
                        <el-table-column prop="balance" label="الرصيد" width="120" align="right">
                            <template #default="{ row }"><strong class="text-info">${{ parseFloat(row.balance).toFixed(2) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!store.loading && !equityAccounts.length" class="empty-state-box">
                        لا توجد حسابات حقوق ملكية مسجلة بعد.
                    </div>
                    <div class="section-total">الإجمالي: <strong>${{ totalEquity.toFixed(2) }}</strong></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Summary footer -->
        <div class="totals-summary-footer mt-4">
            <div class="summary-col">
                <span>إجمالي الأصول</span>
                <h3 class="text-success">${{ totalAssets.toFixed(2) }}</h3>
            </div>
            <div class="summary-col">
                <span>إجمالي الخصوم + حقوق الملكية</span>
                <h3 class="text-warning">${{ (totalLiabilities + totalEquity).toFixed(2) }}</h3>
            </div>
            <div class="summary-col status-col">
                <span>حالة التوازن</span>
                <el-tag :type="isBalanced ? 'success' : 'danger'" effect="dark" style="font-size: 1rem; font-weight: 700; height: 38px; border-radius: 8px;">
                    {{ isBalanced ? 'متوازن' : 'غير متوازن' }}
                </el-tag>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useAccountingReportsStore } from '@/stores/accountingReports';

const store = useAccountingReportsStore();

const assetAccounts = computed(() => store.balanceSheet?.assets?.accounts || []);
const liabilityAccounts = computed(() => store.balanceSheet?.liabilities?.accounts || []);
const equityAccounts = computed(() => store.balanceSheet?.equity?.accounts || []);
const totalAssets = computed(() => parseFloat(store.balanceSheet?.assets?.total || 0));
const totalLiabilities = computed(() => parseFloat(store.balanceSheet?.liabilities?.total || 0));
const totalEquity = computed(() => parseFloat(store.balanceSheet?.equity?.total || 0));
const isBalanced = computed(() => totalAssets.value.toFixed(2) === (totalLiabilities.value + totalEquity.value).toFixed(2));

const printReport = () => window.print();

onMounted(() => {
    store.fetchBalanceSheet().catch(() => {});
});
</script>

<style scoped>
.accounting-page { font-family: 'Cairo', sans-serif; }
.page-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1.25rem; margin-bottom: 2rem; padding-bottom: 1.25rem; border-bottom: 2px solid var(--border-color); }
.page-title h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
.page-title p { margin: 0.5rem 0 0; color: var(--text-muted); font-size: 0.9rem; }
.verification-status-box { display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid; }
.verification-status-box.balanced-status { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.verification-status-box.out-of-balance-status { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.status-icon-wrapper i { font-size: 2rem; margin-top: 0.1rem; }
.status-details h4 { margin: 0 0 0.5rem 0; font-weight: 700; font-size: 1.05rem; }
.status-details p { margin: 0; font-size: 0.9rem; }
.code-badge { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 0.15rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; font-family: monospace; font-size: 0.85rem; }
.table-panel { border-radius: 1rem; overflow: hidden; margin-bottom: 1.25rem; }
.card-header { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-dark); }
.loading-state { padding: 2rem; }
.empty-state-box { padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
.section-total { padding: 0.75rem 1.25rem; border-top: 1px solid var(--border-color); font-weight: 700; display: flex; justify-content: space-between; }
.totals-summary-footer { display: flex; justify-content: space-around; align-items: center; background: var(--bg-light); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-md); flex-wrap: wrap; gap: 1rem; }
.summary-col { text-align: center; }
.summary-col span { font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.5rem; font-weight: 600; }
.summary-col h3 { margin: 0; font-size: 1.6rem; font-weight: 800; }
.summary-col.status-col { display: flex; flex-direction: column; align-items: center; }

@media print {
    .page-header, .verification-status-box { display: none !important; }
    .print-table { width: 100% !important; }
}
</style>
