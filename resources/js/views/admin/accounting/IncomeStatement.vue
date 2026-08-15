<template>
    <div class="accounting-page accounting-income-statement">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-chart-line text-primary"
            :title="$t('income_statement')"
            :subtitle="$t('income_statement_subtitle')"
        >
            <template #actions>
                <el-date-picker v-model="dateFrom" type="date" :placeholder="$t('date_from')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 150px;" @change="load" />
                <el-date-picker v-model="dateTo" type="date" :placeholder="$t('date_to')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 150px;" @change="load" />
                <el-button type="success" plain @click="printReport"><i class="fas fa-print"></i> {{ $t('print') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- Conditions that make the numbers below misleading even when they add up -->
        <el-alert
            v-for="(warning, i) in warnings"
            :key="i"
            :type="warning.level === 'error' ? 'error' : 'warning'"
            :title="warning.message"
            show-icon
            :closable="false"
            class="mb-3"
        />
        <el-alert
            v-if="unbalancedEntries.length"
            type="error"
            show-icon
            :closable="false"
            class="mb-3"
            :title="`${unbalancedEntries.length} قيد غير متوازن ضمن الفترة — الأرقام أدناه غير موثوقة حتى تُصحَّح.`"
        >
            <div class="unbalanced-list">
                <span v-for="e in unbalancedEntries" :key="e.id">
                    {{ e.entry_number }} ({{ e.entry_date }}): فرق {{ money(e.difference) }}
                </span>
            </div>
        </el-alert>

        <!-- Net result headline -->
        <div class="verification-status-box mb-4" :class="isProfit ? 'balanced-status' : 'out-of-balance-status'">
            <div class="status-icon-wrapper">
                <i class="fas" :class="isProfit ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
            </div>
            <div class="status-details">
                <h4>{{ isProfit ? 'صافي ربح للفترة' : 'صافي خسارة للفترة' }}</h4>
                <p>
                    <strong>{{ money(netIncome) }}</strong>
                    <span v-if="netMarginPct !== null"> — بهامش صافي {{ netMarginPct }}٪ من صافي الإيرادات</span>
                </p>
                <p v-if="comparison" class="comparison-note">
                    مقارنة بالفترة السابقة ({{ comparison.period.from }} — {{ comparison.period.to }}):
                    <span :class="deltaClass(netIncome, comparison.net_income)">
                        {{ deltaLabel(netIncome, comparison.net_income) }}
                    </span>
                </p>
            </div>
        </div>

        <!-- The statement itself, in stepped form -->
        <el-card shadow="hover" class="table-panel mb-4">
            <template #header>
                <div class="card-header"><span><i class="fas fa-list-ol text-muted"></i> {{ $t('income_statement') }}</span></div>
            </template>

            <div v-if="store.loading" class="loading-state"><el-skeleton :rows="7" animated /></div>
            <table v-else class="statement-table print-table">
                <tbody>
                    <tr>
                        <td>{{ $t('total_revenue') }}</td>
                        <td class="num">{{ money(s.gross_revenue?.total) }}</td>
                        <td class="cmp"></td>
                    </tr>
                    <tr v-if="Math.abs(s.contra_revenue?.total || 0) > 0.005" class="deduction">
                        <td>{{ $t('less_sales_returns_and_discounts') }}</td>
                        <td class="num">({{ money(s.contra_revenue?.total) }})</td>
                        <td class="cmp"></td>
                    </tr>
                    <tr class="subtotal">
                        <td>{{ $t('net_revenue') }}</td>
                        <td class="num">{{ money(s.net_revenue) }}</td>
                        <td class="cmp" :class="deltaClass(s.net_revenue, comparison?.net_revenue)">
                            {{ deltaLabel(s.net_revenue, comparison?.net_revenue) }}
                        </td>
                    </tr>
                    <tr class="deduction">
                        <td>{{ $t('less_cost_of_goods_sold') }}</td>
                        <td class="num">({{ money(s.cost_of_sales?.total) }})</td>
                        <td class="cmp"></td>
                    </tr>
                    <tr class="subtotal highlight">
                        <td>
                            {{ $t('gross_profit_amount') }}
                            <el-tag v-if="grossMarginPct !== null" size="small" effect="plain" :type="grossMarginPct >= 0 ? 'success' : 'danger'">
                                هامش {{ grossMarginPct }}٪
                            </el-tag>
                        </td>
                        <td class="num">{{ money(s.gross_profit) }}</td>
                        <td class="cmp" :class="deltaClass(s.gross_profit, comparison?.gross_profit)">
                            {{ deltaLabel(s.gross_profit, comparison?.gross_profit) }}
                        </td>
                    </tr>
                    <tr class="deduction">
                        <td>{{ $t('less_operating_expenses') }}</td>
                        <td class="num">({{ money(s.operating_expenses?.total) }})</td>
                        <td class="cmp" :class="deltaClass(comparison?.operating_expenses, s.operating_expenses?.total)">
                            {{ deltaLabel(s.operating_expenses?.total, comparison?.operating_expenses) }}
                        </td>
                    </tr>
                    <tr class="grand-total" :class="isProfit ? 'profit' : 'loss'">
                        <td>{{ $t('net_income') }}</td>
                        <td class="num">{{ money(netIncome) }}</td>
                        <td class="cmp" :class="deltaClass(netIncome, comparison?.net_income)">
                            {{ deltaLabel(netIncome, comparison?.net_income) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </el-card>

        <!-- Account-level detail behind each step -->
        <el-row :gutter="20">
            <el-col v-for="section in breakdownSections" :key="section.title" :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel breakdown-card">
                    <template #header>
                        <div class="card-header"><span><i class="fas text-muted" :class="section.icon"></i> {{ section.title }}</span></div>
                    </template>
                    <div v-if="store.loading" class="loading-state"><el-skeleton :rows="3" animated /></div>
                    <el-table v-else-if="section.rows.length" :data="section.rows" stripe class="custom-table print-table" style="width: 100%">
                        <el-table-column prop="code" :label="$t('code')" width="90" align="center">
                            <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                        </el-table-column>
                        <el-table-column prop="name" :label="$t('the_account')" min-width="130" />
                        <el-table-column prop="amount" :label="$t('amount')" width="140" align="right">
                            <template #default="{ row }"><strong :class="`text-${section.tone}`">{{ money(row.amount) }}</strong></template>
                        </el-table-column>
                    </el-table>
                    <div v-else class="empty-state-box">{{ section.empty }}</div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useAccountingReportsStore } from '@/stores/accountingReports';
import { formatCurrency } from '@/utils/sales';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();

const store = useAccountingReportsStore();

const today = new Date().toISOString().split('T')[0];
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
const dateTo = ref(today);

const s = computed(() => store.incomeStatement || {});
const comparison = computed(() => s.value.comparison || null);
const warnings = computed(() => s.value.warnings || []);
const unbalancedEntries = computed(() => s.value.unbalanced_entries || []);

const num = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

const netIncome = computed(() => num(s.value.net_income));
const isProfit = computed(() => netIncome.value >= 0);
const grossMarginPct = computed(() => (s.value.gross_margin_pct ?? null));
const netMarginPct = computed(() => (s.value.net_margin_pct ?? null));

/**
 * Amounts were previously printed with a hardcoded "$" while the system runs on
 * the configured currency, so every figure on this page named the wrong money.
 * `formatCurrency` is the same helper the sales screens use.
 */
const money = (v) => formatCurrency(num(v));

/** Revenue detail: gross accounts first, deductions after, in the same list. */
const revenueRows = computed(() => [
    ...(s.value.gross_revenue?.accounts || []),
    ...(s.value.contra_revenue?.accounts || []).map((a) => ({ ...a, amount: -num(a.amount) })),
]);

/**
 * Period-on-period movement. Rendered as a signed change so a figure reads as
 * better or worse rather than merely large.
 */
const deltaLabel = (current, previous) => {
    if (previous === null || previous === undefined) return '';
    const diff = num(current) - num(previous);
    if (Math.abs(diff) < 0.005) return t('no_change');

    const base = Math.abs(num(previous));
    const pct = base > 0.005 ? ` (${Math.abs((diff / base) * 100).toFixed(1)}٪)` : '';
    return `${diff > 0 ? '▲' : '▼'} ${money(Math.abs(diff))}${pct}`;
};

const deltaClass = (current, previous) => {
    if (previous === null || previous === undefined) return '';
    const diff = num(current) - num(previous);
    if (Math.abs(diff) < 0.005) return 'delta-flat';
    return diff > 0 ? 'delta-up' : 'delta-down';
};

/** The three detail tables under the statement, in the order they are stepped. */
const breakdownSections = computed(() => [
    {
        title: t('revenue'),
        icon: 'fa-money-bill-wave',
        tone: 'success',
        rows: revenueRows.value,
        empty: t('no_revenue_this_period'),
    },
    {
        title: t('cost_of_sales'),
        icon: 'fa-boxes-stacked',
        tone: 'danger',
        rows: s.value.cost_of_sales?.accounts || [],
        empty: t('no_cogs_recorded'),
    },
    {
        title: t('operating_expenses'),
        icon: 'fa-receipt',
        tone: 'warning',
        rows: s.value.operating_expenses?.accounts || [],
        empty: t('no_operating_expenses'),
    },
]);

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
.comparison-note { margin-top: 0.4rem !important; opacity: 0.85; }

/* ---- Stepped statement ---- */
.statement-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
.statement-table td { padding: 0.75rem 0.9rem; border-bottom: 1px solid var(--border-color); }
.statement-table td.num { text-align: left; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
.statement-table td.cmp { text-align: left; width: 190px; font-size: 0.8rem; white-space: nowrap; }

/* Deductions are indented so the arithmetic of the column reads top to bottom. */
.statement-table tr.deduction td:first-child { padding-inline-start: 2rem; color: var(--text-muted); }
.statement-table tr.deduction td.num { color: var(--el-color-danger); }

.statement-table tr.subtotal td { background: var(--bg-light); font-weight: 700; }
.statement-table tr.subtotal.highlight td { background: color-mix(in srgb, var(--el-color-primary) 8%, transparent); }
.statement-table tr.subtotal td:first-child { display: flex; align-items: center; gap: 0.5rem; }

.statement-table tr.grand-total td { font-size: 1.1rem; font-weight: 800; border-top: 2px solid var(--text-dark); border-bottom: none; }
.statement-table tr.grand-total.profit td.num { color: var(--el-color-success); }
.statement-table tr.grand-total.loss td.num { color: var(--el-color-danger); }

.delta-up { color: var(--el-color-success); font-weight: 700; }
.delta-down { color: var(--el-color-danger); font-weight: 700; }
.delta-flat { color: var(--text-muted); }

.unbalanced-list { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.82rem; margin-top: 0.35rem; }

.code-badge { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 0.15rem 0.5rem; border-radius: var(--radius-sm); font-weight: 700; font-family: monospace; font-size: 0.85rem; }
.table-panel { border-radius: 1rem; overflow: hidden; }
.breakdown-card { margin-bottom: 1rem; }
.card-header { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-dark); }
.loading-state { padding: 2rem; }
.empty-state-box { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; }

.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }

@media print {
    .page-header, .verification-status-box { display: none !important; }
    .print-table { width: 100% !important; }
    .statement-table td.cmp { display: none; }
}
</style>
