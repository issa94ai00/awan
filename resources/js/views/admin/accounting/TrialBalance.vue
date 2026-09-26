<template>
    <div class="accounting-page accounting-trial-balance">
        <AdminPageHeader
            icon="fas fa-balance-scale text-primary"
            :title="$t('trial_balance')"
            :subtitle="$t('trial_balance_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag no-print">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :loading="store.loading" @click="load">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button class="no-print" :disabled="!rows.length" @click="exportCsv">
                    <i class="fas fa-file-csv mr-1"></i> {{ $t('tb_export_csv') }}
                </el-button>
                <el-button type="primary" plain class="no-print" :disabled="!rows.length" @click="printPage">
                    <i class="fas fa-print mr-1"></i> {{ $t('print_trial_balance') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Period and view ────────────────────────────────────────────── -->
        <section class="tb-card toolbar-card no-print">
            <div class="toolbar">
                <el-segmented v-model="preset" :options="presetOptions" @change="applyPreset" />
                <el-date-picker
                    v-model="range"
                    type="daterange"
                    unlink-panels
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    :start-placeholder="$t('jr_date_from')"
                    :end-placeholder="$t('jr_date_to')"
                    :clearable="false"
                    class="t-range"
                    @change="onRangePicked"
                />
                <el-input v-model="search" :placeholder="$t('lg_search_placeholder')" clearable class="t-search">
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>
            </div>
            <div class="toolbar-opts">
                <el-checkbox v-model="showOpening">{{ $t('tb_show_opening') }}</el-checkbox>
                <el-checkbox v-model="groupByType">{{ $t('tb_group_by_type') }}</el-checkbox>
                <el-checkbox v-model="showZero">{{ $t('tb_show_zero') }}</el-checkbox>
            </div>
        </section>

        <p class="print-period">{{ $t('tb_period_label', { from: data?.period?.from || '', to: data?.period?.to || '' }) }}</p>

        <el-alert
            v-if="loadError"
            type="error"
            show-icon
            :closable="false"
            class="mb-3 no-print"
            :title="$t('tb_load_failed')"
        >
            <el-button size="small" type="danger" plain @click="load">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ── Verdict ────────────────────────────────────────────────────── -->
        <div v-if="data" class="verdict" :class="allBalanced ? 'is-ok' : 'is-bad'">
            <span class="verdict-icon"><i class="fas" :class="allBalanced ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i></span>
            <div class="verdict-text">
                <h3>{{ allBalanced ? $t('trial_balance_balanced') : $t('trial_balance_unbalanced') }}</h3>
                <p v-if="allBalanced">{{ $t('tb_balanced_detail', { amount: money(data.totals.debits) }) }}</p>
                <p v-else>
                    <template v-if="!data.is_balanced">{{ $t('tb_movement_difference', { amount: money(Math.abs(data.difference)) }) }}</template>
                    <template v-if="closingOff"> {{ $t('tb_closing_difference', { amount: money(Math.abs(data.closing_difference)) }) }}</template>
                    <template v-if="unbalancedEntries.length"> {{ $t('tb_unbalanced_entries_count', { count: unbalancedEntries.length }) }}</template>
                </p>
            </div>
        </div>

        <!-- The entries whose own lines do not add up: the usual reason the
             totals disagree, each one a click from the journal. -->
        <section v-if="unbalancedEntries.length" class="tb-card bad-entries">
            <header class="tb-card-head">
                <h2 class="is-bad"><i class="fas fa-circle-exclamation"></i> {{ $t('tb_unbalanced_entries') }}</h2>
            </header>
            <div class="bad-list">
                <router-link
                    v-for="e in unbalancedEntries"
                    :key="e.id"
                    :to="{ path: '/admin/accounting/journal', query: { search: e.entry_number } }"
                    class="bad-item"
                >
                    <span class="code-badge">{{ e.entry_number }}</span>
                    <span class="cell-date">{{ e.entry_date }}</span>
                    <span class="bad-amounts">
                        <span class="amt is-debit">{{ money(e.debit) }}</span>
                        <span class="amt is-credit">{{ money(e.credit) }}</span>
                    </span>
                    <strong class="bad-diff">{{ $t('jr_difference') }}: {{ money(Math.abs(e.difference)) }}</strong>
                </router-link>
            </div>
        </section>

        <!-- ── Totals ─────────────────────────────────────────────────────── -->
        <div v-if="data" class="tiles">
            <div class="tile is-debit">
                <span>{{ $t('tb_period_debits') }}</span>
                <strong>{{ money(data.totals.debits) }}</strong>
            </div>
            <div class="tile is-credit">
                <span>{{ $t('tb_period_credits') }}</span>
                <strong>{{ money(data.totals.credits) }}</strong>
            </div>
            <div class="tile">
                <span>{{ $t('tb_closing_totals') }}</span>
                <strong>{{ money(data.totals.closing_debits) }}</strong>
                <small :class="{ 'is-negative': closingOff }">
                    {{ closingOff ? $t('tb_vs_credit', { amount: money(data.totals.closing_credits) }) : $t('tb_debit_equals_credit') }}
                </small>
            </div>
            <div class="tile">
                <span>{{ $t('tb_accounts_shown') }}</span>
                <strong>{{ accountRows.length }}</strong>
                <small>{{ $t('lg_shown_of', { shown: accountRows.length, total: allAccounts.length }) }}</small>
            </div>
        </div>

        <!-- ── Table ──────────────────────────────────────────────────────── -->
        <section class="tb-card table-card">
            <el-skeleton v-if="store.loading && !data" :rows="8" animated />

            <div v-else-if="data && !rows.length" class="empty-state">
                <span class="empty-icon"><i class="fas fa-balance-scale"></i></span>
                <p>{{ search ? $t('lg_no_match') : $t('tb_no_activity') }}</p>
            </div>

            <el-table
                v-else-if="rows.length"
                v-loading="store.loading"
                :data="rows"
                row-key="key"
                border
                show-summary
                :summary-method="summary"
                :row-class-name="rowClass"
                class="tb-table"
            >
                <el-table-column :label="$t('lg_col_account')" min-width="260" fixed>
                    <template #default="{ row }">
                        <span v-if="row.kind === 'subtotal'" class="subtotal-label">
                            <span class="type-chip" :class="'tone-' + row.type">{{ typeLabel(row.type) }}</span>
                            {{ $t('tb_subtotal') }}
                        </span>
                        <span v-else class="acc-cell">
                            <span class="code-badge">{{ row.code }}</span>
                            <router-link :to="journalLink(row)" class="acc-name">{{ row.name }}</router-link>
                            <span v-if="!groupByType" class="type-chip is-small" :class="'tone-' + row.type">{{ typeLabel(row.type) }}</span>
                        </span>
                    </template>
                </el-table-column>

                <el-table-column v-if="showOpening" :label="$t('tb_opening')" align="center" class-name="grp-opening">
                    <el-table-column :label="$t('jr_col_debit')" width="135" align="right">
                        <template #default="{ row }"><span class="amt" :class="{ 'is-zero': !row.opening_debit }">{{ cell(row.opening_debit) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('jr_col_credit')" width="135" align="right">
                        <template #default="{ row }"><span class="amt" :class="{ 'is-zero': !row.opening_credit }">{{ cell(row.opening_credit) }}</span></template>
                    </el-table-column>
                </el-table-column>

                <el-table-column :label="$t('tb_movement')" align="center" class-name="grp-movement">
                    <el-table-column :label="$t('jr_col_debit')" width="135" align="right">
                        <template #default="{ row }"><span class="amt is-debit" :class="{ 'is-zero': !row.debits }">{{ cell(row.debits) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('jr_col_credit')" width="135" align="right">
                        <template #default="{ row }"><span class="amt is-credit" :class="{ 'is-zero': !row.credits }">{{ cell(row.credits) }}</span></template>
                    </el-table-column>
                </el-table-column>

                <el-table-column :label="$t('tb_closing')" align="center" class-name="grp-closing">
                    <el-table-column :label="$t('jr_col_debit')" width="140" align="right">
                        <template #default="{ row }"><strong class="amt" :class="{ 'is-zero': !row.closing_debit }">{{ cell(row.closing_debit) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('jr_col_credit')" width="140" align="right">
                        <template #default="{ row }"><strong class="amt" :class="{ 'is-zero': !row.closing_credit }">{{ cell(row.closing_credit) }}</strong></template>
                    </el-table-column>
                </el-table-column>
            </el-table>
        </section>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAccountingReportsStore } from '@/stores/accountingReports';
import { formatMoney, baseCurrencyCode, numberLocale } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t, te } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useAccountingReportsStore();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
/** Table cells: the figure without the code, a dash for nothing. */
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const cell = (value) => (Number(value) ? plain(value) : '—');

const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];
const normType = (type) => String(type || '').toLowerCase().replace(/s$/, '');
const typeLabel = (type) => (te('lg_type_' + normType(type)) ? t('lg_type_' + normType(type)) : type);

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/* ------------------------------------------------------------------ *
 * Period
 *
 * The screen used to have no period at all and silently showed movement since
 * 1 January. It is now chosen here and kept in the URL.
 * ------------------------------------------------------------------ */

const preset = ref('year');
const range = ref([]);

const presetOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'month' },
    { label: t('acc_ov_this_year'), value: 'year' },
    { label: t('lg_last_year'), value: 'last_year' },
    { label: t('lg_custom'), value: 'custom' },
]);

const presetRange = (p) => {
    const now = new Date();
    if (p === 'month') return [localDate(new Date(now.getFullYear(), now.getMonth(), 1)), todayIso];
    if (p === 'last_year') return [`${now.getFullYear() - 1}-01-01`, `${now.getFullYear() - 1}-12-31`];
    return [`${now.getFullYear()}-01-01`, todayIso];
};

const detectPreset = ([from, to]) => ['month', 'year', 'last_year'].find((p) => {
    const [f, tt] = presetRange(p);
    return f === from && tt === to;
}) || 'custom';

const loadError = ref(false);

const load = async () => {
    loadError.value = false;
    router.replace({ query: { ...route.query, date_from: range.value[0], date_to: range.value[1] } });
    try {
        await store.fetchTrialBalance({ date_from: range.value[0], date_to: range.value[1] });
    } catch (e) {
        loadError.value = true;
    }
};

const applyPreset = (p) => {
    if (p === 'custom') return;
    range.value = presetRange(p);
    load();
};

const onRangePicked = () => {
    preset.value = detectPreset(range.value);
    load();
};

/* ------------------------------------------------------------------ *
 * Rows
 * ------------------------------------------------------------------ */

const data = computed(() => store.trialBalance);
const allAccounts = computed(() => data.value?.all_accounts || data.value?.accounts || []);
const unbalancedEntries = computed(() => data.value?.unbalanced_entries || []);
const closingOff = computed(() => Math.abs(Number(data.value?.closing_difference || 0)) >= 0.005);
const allBalanced = computed(() => !!data.value?.is_balanced && !closingOff.value && !unbalancedEntries.value.length);

const search = ref('');
const showOpening = ref(true);
const groupByType = ref(true);
const showZero = ref(false);

const accountRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    const source = showZero.value ? allAccounts.value : (data.value?.accounts || []);
    return source
        .map((a) => ({ ...a, type: normType(a.type), key: 'a' + a.id }))
        .filter((a) => !q || String(a.code).toLowerCase().includes(q) || String(a.name).toLowerCase().includes(q));
});

const AMOUNT_KEYS = ['opening_debit', 'opening_credit', 'debits', 'credits', 'closing_debit', 'closing_credit'];
const sumOf = (list) => Object.fromEntries(AMOUNT_KEYS.map((k) => [k, Math.round(list.reduce((s, r) => s + Number(r[k] || 0), 0) * 100) / 100]));

/** Grouped: each type's accounts followed by its subtotal. Otherwise the chart order. */
const rows = computed(() => {
    if (!groupByType.value) return accountRows.value;
    const out = [];
    const typed = [...TYPES, ...new Set(accountRows.value.map((a) => a.type).filter((tp) => !TYPES.includes(tp)))];
    typed.forEach((tp) => {
        const list = accountRows.value.filter((a) => a.type === tp);
        if (!list.length) return;
        out.push(...list, { kind: 'subtotal', type: tp, key: 'sub-' + tp, ...sumOf(list) });
    });
    return out;
});

const rowClass = ({ row }) => (row.kind === 'subtotal' ? 'is-subtotal tone-' + row.type : '');

/** Totals over the accounts shown, never over the subtotal rows as well. */
const summary = ({ columns }) => {
    const totals = sumOf(accountRows.value);
    const order = showOpening.value ? AMOUNT_KEYS : AMOUNT_KEYS.slice(2);
    return columns.map((col, i) => (i === 0 ? t('tb_grand_total') : plain(totals[order[i - 1]])));
};

const journalLink = (row) => ({
    path: '/admin/accounting/journal',
    query: { ledger_account_id: row.id, date_from: data.value?.period?.from, date_to: data.value?.period?.to },
});

/* ------------------------------------------------------------------ *
 * Export and print
 * ------------------------------------------------------------------ */

const exportCsv = () => {
    const head = [t('account_code'), t('account_name'), t('lg_col_type'),
        `${t('tb_opening')} ${t('jr_col_debit')}`, `${t('tb_opening')} ${t('jr_col_credit')}`,
        `${t('tb_movement')} ${t('jr_col_debit')}`, `${t('tb_movement')} ${t('jr_col_credit')}`,
        `${t('tb_closing')} ${t('jr_col_debit')}`, `${t('tb_closing')} ${t('jr_col_credit')}`];
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [head, ...accountRows.value.map((a) => [a.code, a.name, typeLabel(a.type), ...AMOUNT_KEYS.map((k) => Number(a[k] || 0).toFixed(2))])];
    const totals = sumOf(accountRows.value);
    lines.push(['', t('tb_grand_total'), '', ...AMOUNT_KEYS.map((k) => totals[k].toFixed(2))]);
    // The BOM makes Excel read the Arabic names as UTF-8.
    const blob = new Blob(['﻿' + lines.map((l) => l.map(esc).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `trial-balance_${data.value?.period?.from}_${data.value?.period?.to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const printPage = () => window.print();

onMounted(() => {
    const { date_from: from, date_to: to } = route.query;
    const valid = (d) => /^\d{4}-\d{2}-\d{2}$/.test(String(d || ''));
    range.value = valid(from) && valid(to) ? [String(from), String(to)] : presetRange('year');
    preset.value = detectPreset(range.value);
    load();
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. */
.accounting-page {
    font-family: 'Cairo', sans-serif;
    --ov-radius: 14px;
    --ov-surface: #ffffff;
    --ov-soft: #f8fafc;
    --ov-border: #e2e8f0;
    --ov-text: #0f172a;
    --ov-body: #334155;
    --ov-muted: #475569;
    --ov-subtle: #64748b;
    --c-debit: #047857;
    --c-credit: #b45309;
    --c-loss: #b91c1c;
    color: var(--ov-body);
}

.tone-asset { --tone: #1d4ed8; --tone-bg: #dbeafe; }
.tone-liability { --tone: #b45309; --tone-bg: #fef3c7; }
.tone-equity { --tone: #047857; --tone-bg: #d1fae5; }
.tone-revenue { --tone: #0e7490; --tone-bg: #cffafe; }
.tone-expense { --tone: #be123c; --tone-bg: #ffe4e6; }

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.is-negative { color: var(--c-loss) !important; }
.print-period { display: none; }

.tb-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.tb-card-head { margin: 0 0 0.75rem; }
.tb-card-head h2 { margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.5rem; }
.tb-card-head h2.is-bad, .tb-card-head h2.is-bad i { color: #991b1b; }

/* ── Toolbar ──────────────────────────────────────────────────────────── */
.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.t-range { max-width: 300px; }
.t-search { flex: 1 1 220px; max-width: 360px; margin-inline-start: auto; }
.t-search :deep(.el-input__prefix) { color: var(--ov-subtle); }
.toolbar :deep(.el-input__inner::placeholder) { color: var(--ov-subtle); }
.toolbar-opts { display: flex; flex-wrap: wrap; gap: 0.25rem 1.25rem; margin-top: 0.7rem; padding-top: 0.7rem; border-top: 1px dashed var(--ov-border); }
.toolbar-opts :deep(.el-checkbox__label) { color: var(--ov-body); font-weight: 600; }

@media (max-width: 720px) {
    .t-range, .t-search { max-width: none; width: 100%; margin-inline-start: 0; }
}

/* ── Verdict ──────────────────────────────────────────────────────────── */
.verdict {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 0.95rem 1.1rem;
    margin-bottom: 1rem;
    border-radius: var(--ov-radius);
    border: 1px solid;
}

.verdict.is-ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.verdict.is-bad { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.verdict-icon { font-size: 1.6rem; line-height: 1; margin-top: 0.1rem; }
.verdict-text h3 { margin: 0 0 0.2rem; font-size: 1rem; font-weight: 800; }
.verdict-text p { margin: 0; font-size: 0.86rem; line-height: 1.7; font-weight: 600; }

/* ── Unbalanced entries ───────────────────────────────────────────────── */
.bad-entries { border-color: #fecaca; }
.bad-list { display: flex; flex-direction: column; gap: 0.4rem; }

.bad-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem 0.9rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #fecaca;
    border-inline-start: 4px solid var(--c-loss);
    border-radius: 9px;
    background: #fffafa;
    color: var(--ov-body);
    text-decoration: none;
    font-size: 0.84rem;
}

.bad-item:hover { background: #fef2f2; }
.bad-amounts { display: inline-flex; gap: 0.8rem; }
.bad-diff { margin-inline-start: auto; color: #991b1b; font-variant-numeric: tabular-nums; }

/* ── Tiles ────────────────────────────────────────────────────────────── */
.tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }

.tile {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.8rem 0.95rem;
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    min-width: 0;
}

.tile span { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.tile strong { font-size: 1.15rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.tile small { font-size: 0.74rem; color: var(--ov-subtle); }
.tile.is-debit { border-top: 3px solid var(--c-debit); }
.tile.is-debit strong { color: var(--c-debit); }
.tile.is-credit { border-top: 3px solid var(--c-credit); }
.tile.is-credit strong { color: var(--c-credit); }

/* ── Table ────────────────────────────────────────────────────────────── */
.table-card { padding: 0.6rem; }

.tb-table {
    --el-table-header-bg-color: var(--ov-soft);
    --el-table-border-color: var(--ov-border);
    --el-table-row-hover-bg-color: #f5f8ff;
    font-size: 0.84rem;
}

.tb-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.78rem; }
.tb-table :deep(th.grp-opening .cell),
.tb-table :deep(th.grp-movement .cell),
.tb-table :deep(th.grp-closing .cell) { color: var(--ov-text); font-weight: 800; }
.tb-table :deep(th.grp-closing) { background: #eff6ff !important; }
.tb-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }

.tb-table :deep(.el-table__row.is-subtotal td.el-table__cell) { background: color-mix(in srgb, var(--tone) 6%, #fff); border-top: 1px solid color-mix(in srgb, var(--tone) 30%, #fff); }
.tb-table :deep(.el-table__row.is-subtotal .amt) { font-weight: 800; color: var(--ov-text); }

.tb-table :deep(.el-table__footer-wrapper td.el-table__cell) { background: #f1f5f9; }
.tb-table :deep(.el-table__footer-wrapper .cell) { color: var(--ov-text); font-weight: 800; font-variant-numeric: tabular-nums; text-align: end; }
.tb-table :deep(.el-table__footer-wrapper td:first-child .cell) { text-align: start; }

.acc-cell { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 0.3rem 0.5rem; min-width: 0; }
.acc-name { font-weight: 700; color: var(--ov-text); text-decoration: none; }
.acc-name:hover { color: #1d4ed8; text-decoration: underline; text-underline-offset: 3px; }
.subtotal-label { display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--ov-text); }

.code-badge {
    display: inline-block;
    flex-shrink: 0;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0.05rem 0.45rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
    direction: ltr;
}

.type-chip {
    display: inline-block;
    padding: 0.05rem 0.6rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--tone);
    background: var(--tone-bg);
}

.type-chip.is-small { font-size: 0.68rem; padding: 0 0.45rem; }

.amt { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--ov-text); font-weight: 600; }
strong.amt { font-weight: 800; }
.amt.is-debit { color: var(--c-debit); font-weight: 700; }
.amt.is-credit { color: var(--c-credit); font-weight: 700; }
.amt.is-zero { color: #94a3b8; font-weight: 400; }
.cell-date { font-variant-numeric: tabular-nums; }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.9rem; padding: 3rem 1rem; text-align: center; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; border-radius: 18px; background: #eff6ff; color: #1d4ed8; font-size: 1.5rem; }
.empty-state p { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--ov-muted); }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print, .toolbar-card, .bad-entries { display: none !important; }
    .print-period { display: block; margin: 0 0 0.75rem; font-weight: 700; color: #000; }
    .tb-card, .tile { box-shadow: none; }
    .verdict { padding: 0.5rem 0.75rem; }
    .tb-table { font-size: 0.75rem; }
}
</style>
