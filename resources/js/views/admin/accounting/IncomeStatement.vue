<template>
    <div class="accounting-page accounting-income-statement">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-chart-line text-primary"></i> {{ $t('income_statement') || 'قائمة الدخل (الأرباح والخسائر)' }}</h1>
                <p>صافي الربح أو الخسارة عن فترة محددة: الإيرادات مطروحاً منها المصروفات.</p>
            </div>
            <div class="header-actions">
                <el-date-picker v-model="dateFrom" type="date" placeholder="من تاريخ" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 150px;" @change="load" />
                <el-date-picker v-model="dateTo" type="date" placeholder="إلى تاريخ" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 150px;" @change="load" />
                <el-button type="success" plain @click="printReport"><i class="fas fa-print"></i> طباعة</el-button>
            </div>
        </div>

        <!-- Net income status indicator -->
        <div class="verification-status-box mb-4" :class="isProfit ? 'balanced-status' : 'out-of-balance-status'">
            <div class="status-icon-wrapper">
                <i class="fas" :class="isProfit ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
            </div>
            <div class="status-details">
                <h4 v-if="isProfit">صافي ربح للفترة</h4>
                <h4 v-else>صافي خسارة للفترة</h4>
                <p>صافي الدخل: <strong>${{ netIncome.toFixed(2) }}</strong> (الإيرادات ${{ totalRevenue.toFixed(2) }} − المصروفات ${{ totalExpenses.toFixed(2) }})</p>
            </div>
        </div>

        <el-row :gutter="20">
            <el-col :xs="24" :lg="12">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header"><span><i class="fas fa-money-bill-wave text-muted"></i> الإيرادات</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="4" animated /></div>
                    <el-table v-else :data="revenueAccounts" style="width: 100%" stripe class="custom-table print-table">
                        <el-table-column prop="code" label="الرمز" width="110" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" label="الحساب" min-width="160" />
                        <el-table-column prop="amount" label="المبلغ" width="130" align="right">
                            <template #default="{ row }"><strong class="text-success">${{ parseFloat(row.amount).toFixed(2) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!store.loading && !revenueAccounts.length" class="empty-state-box">لا توجد إيرادات في هذه الفترة.</div>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="12">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header"><span><i class="fas fa-receipt text-muted"></i> المصروفات</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="4" animated /></div>
                    <el-table v-else :data="expenseAccounts" style="width: 100%" stripe class="custom-table print-table">
                        <el-table-column prop="code" label="الرمز" width="110" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" label="الحساب" min-width="160" />
                        <el-table-column prop="amount" label="المبلغ" width="130" align="right">
                            <template #default="{ row }"><strong class="text-warning">${{ parseFloat(row.amount).toFixed(2) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!store.loading && !expenseAccounts.length" class="empty-state-box">لا توجد مصروفات في هذه الفترة.</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Summary footer -->
        <div class="totals-summary-footer mt-4">
            <div class="summary-col">
                <span>إجمالي الإيرادات</span>
                <h3 class="text-success">${{ totalRevenue.toFixed(2) }}</h3>
            </div>
            <div class="summary-col">
                <span>إجمالي المصروفات</span>
                <h3 class="text-warning">${{ totalExpenses.toFixed(2) }}</h3>
            </div>
            <div class="summary-col status-col">
                <span>صافي الدخل</span>
                <el-tag :type="isProfit ? 'success' : 'danger'" effect="dark" style="font-size: 1rem; font-weight: 700; height: 38px; border-radius: 8px;">
                    ${{ netIncome.toFixed(2) }}
                </el-tag>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAccountingReportsStore } from '@/stores/accountingReports';

const store = useAccountingReportsStore();

const today = new Date().toISOString().split('T')[0];
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
const dateTo = ref(today);

const revenueAccounts = computed(() => store.incomeStatement?.revenue?.accounts || []);
const expenseAccounts = computed(() => store.incomeStatement?.expenses?.accounts || []);
const totalRevenue = computed(() => parseFloat(store.incomeStatement?.revenue?.total || 0));
const totalExpenses = computed(() => parseFloat(store.incomeStatement?.expenses?.total || 0));
const netIncome = computed(() => parseFloat(store.incomeStatement?.net_income ?? (totalRevenue.value - totalExpenses.value)));
const isProfit = computed(() => netIncome.value >= 0);

const load = () => {
    store.fetchIncomeStatement({ date_from: dateFrom.value, date_to: dateTo.value }).catch(() => {});
};

const printReport = () => window.print();

onMounted(load);
</script>

<style scoped>
.accounting-page { font-family: 'Cairo', sans-serif; }
.page-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1.25rem; margin-bottom: 2rem; padding-bottom: 1.25rem; border-bottom: 2px solid var(--border-color); }
.header-actions { display: flex; gap: 0.5rem; align-items: center; }
.page-title h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
.page-title p { margin: 0.5rem 0 0; color: var(--text-muted); font-size: 0.9rem; }
.verification-status-box { display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid; }
.verification-status-box.balanced-status { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.verification-status-box.out-of-balance-status { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.status-icon-wrapper i { font-size: 2rem; margin-top: 0.1rem; }
.status-details h4 { margin: 0 0 0.5rem 0; font-weight: 700; font-size: 1.05rem; }
.status-details p { margin: 0; font-size: 0.9rem; }
.code-badge { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 0.15rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; font-family: monospace; font-size: 0.85rem; }
.table-panel { border-radius: 1rem; overflow: hidden; }
.card-header { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-dark); }
.loading-state { padding: 2rem; }
.empty-state-box { padding: 2rem; text-align: center; color: var(--text-muted); }
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
