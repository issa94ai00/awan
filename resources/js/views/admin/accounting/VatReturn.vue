<template>
    <div class="accounting-page vat-return">
        <AdminPageHeader
            icon="fas fa-percent text-primary"
            :title="$t('vat_return')"
            :subtitle="$t('vat_return_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag no-print">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :loading="loading" @click="reload">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" plain class="no-print" :disabled="!report" @click="printPage">
                    <i class="fas fa-print mr-1"></i> {{ $t('ag_print') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Period ─────────────────────────────────────────────────────── -->
        <section class="vr-card toolbar no-print">
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
        </section>

        <p class="print-only print-period">{{ $t('tb_period_label', { from: report?.period?.from || '', to: report?.period?.to || '' }) }}</p>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <div v-if="loading && !report" class="vr-card"><el-skeleton :rows="6" animated /></div>

        <template v-else-if="report">
            <!-- ── The answer ───────────────────────────────────────────── -->
            <section class="result" :class="resultClass" v-loading="loading">
                <div class="result-main">
                    <span class="result-label">
                        <i class="fas" :class="report.direction === 'payable' ? 'fa-building-columns' : 'fa-rotate-left'"></i>
                        {{ isZero ? $t('vr_nothing_due') : report.direction === 'payable' ? $t('tax_due_to_authority') : $t('tax_recoverable') }}
                    </span>
                    <strong class="result-amount">{{ money(Math.abs(report.net)) }}</strong>
                    <span class="result-period"><i class="far fa-calendar"></i> {{ report.period.from }} → {{ report.period.to }}</span>
                </div>

                <!-- The whole return in one line: what was collected, less
                     what was paid, is what is owed. -->
                <div class="formula" aria-hidden="true">
                    <span class="f-term is-out">
                        <small>{{ $t('output_tax') }}</small>
                        <b>{{ plain(report.output_tax.amount) }}</b>
                    </span>
                    <span class="f-op">−</span>
                    <span class="f-term is-in">
                        <small>{{ $t('input_tax') }}</small>
                        <b>{{ plain(report.input_tax.amount) }}</b>
                    </span>
                    <span class="f-op">=</span>
                    <span class="f-term is-net">
                        <small>{{ $t('vr_net') }}</small>
                        <b>{{ plain(report.net) }}</b>
                    </span>
                </div>

                <span class="recon-pill" :class="allMatch ? 'is-ok' : 'is-bad'">
                    <i class="fas" :class="allMatch ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                    {{ allMatch ? $t('vr_documents_agree') : $t('vr_documents_disagree') }}
                </span>
            </section>

            <!-- ── The two sides ────────────────────────────────────────── -->
            <div class="sides">
                <section class="vr-card side is-out">
                    <header class="side-head">
                        <span class="side-icon"><i class="fas fa-cash-register"></i></span>
                        <div>
                            <h2>{{ $t('output_tax') }}</h2>
                            <p>{{ $t('vr_output_hint') }}</p>
                        </div>
                    </header>
                    <strong class="side-amount">{{ money(report.output_tax.amount) }}</strong>
                    <dl class="facts">
                        <div><dt>{{ $t('vr_charged') }}</dt><dd>{{ money(report.output_tax.credits) }}</dd></div>
                        <div v-if="report.output_tax.debits"><dt>{{ $t('vr_reduced') }}</dt><dd>− {{ money(report.output_tax.debits) }}</dd></div>
                        <div><dt>{{ $t('sales_base') }}</dt><dd>{{ money(report.sales_base) }}</dd></div>
                        <div v-if="effectiveRate !== null"><dt>{{ $t('vr_effective_rate') }}</dt><dd>{{ effectiveRate }}</dd></div>
                        <div><dt>{{ $t('vr_invoices') }}</dt><dd>{{ formatNumber(report.documents.invoice_count ?? 0) }}</dd></div>
                    </dl>
                    <p v-if="report.output_tax.account" class="account-line">
                        <span class="code-badge">{{ report.output_tax.account.code }}</span> {{ report.output_tax.account.name }}
                    </p>
                    <p v-else class="account-line is-missing"><i class="fas fa-triangle-exclamation"></i> {{ $t('vr_no_account') }}</p>
                </section>

                <section class="vr-card side is-in">
                    <header class="side-head">
                        <span class="side-icon"><i class="fas fa-truck-ramp-box"></i></span>
                        <div>
                            <h2>{{ $t('input_tax') }}</h2>
                            <p>{{ $t('vr_input_hint') }}</p>
                        </div>
                    </header>
                    <strong class="side-amount">{{ money(report.input_tax.amount) }}</strong>
                    <dl class="facts">
                        <div><dt>{{ $t('vr_on_receipts') }}</dt><dd>{{ money(report.documents.receipt_tax) }}</dd></div>
                        <div v-if="report.documents.purchase_return_tax"><dt>{{ $t('vr_given_back_on_returns') }}</dt><dd>− {{ money(report.documents.purchase_return_tax) }}</dd></div>
                        <div><dt>{{ $t('vr_receipts') }}</dt><dd>{{ formatNumber(report.documents.receipt_count ?? 0) }}</dd></div>
                        <div v-if="report.documents.purchase_return_count"><dt>{{ $t('vr_returns') }}</dt><dd>{{ formatNumber(report.documents.purchase_return_count) }}</dd></div>
                    </dl>
                    <p v-if="report.input_tax.account" class="account-line">
                        <span class="code-badge">{{ report.input_tax.account.code }}</span> {{ report.input_tax.account.name }}
                    </p>
                    <p v-else class="account-line is-missing"><i class="fas fa-triangle-exclamation"></i> {{ $t('vr_no_account') }}</p>
                </section>
            </div>

            <!-- ── Documents against the ledger ─────────────────────────── -->
            <!-- The documents of the period, added up independently of the
                 ledger. When the two disagree something posted wrong, and that
                 is worth knowing before the return is filed rather than after. -->
            <section class="vr-card">
                <header class="vr-card-head">
                    <h2><i class="fas fa-scale-balanced"></i> {{ $t('documents_vs_ledger') }}</h2>
                </header>

                <div class="recon-grid">
                    <div v-for="row in reconciliationRows" :key="row.key" class="recon-row" :class="row.matches ? 'is-ok' : 'is-bad'">
                        <span class="recon-side">{{ row.label }}</span>
                        <div class="recon-cell"><small>{{ $t('per_documents') }}</small><b>{{ plain(row.documents) }}</b></div>
                        <span class="recon-op">{{ row.matches ? '=' : '≠' }}</span>
                        <div class="recon-cell"><small>{{ $t('per_ledger') }}</small><b>{{ plain(row.ledger) }}</b></div>
                        <span class="recon-status">
                            <i class="fas" :class="row.matches ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                            {{ row.matches ? $t('matching') : $t('vr_difference_of', { amount: money(Math.abs(row.difference)) }) }}
                        </span>
                    </div>
                </div>

                <p v-if="!allMatch" class="recon-help">
                    <i class="fas fa-lightbulb"></i>
                    <span>{{ $t('vr_mismatch_help') }}</span>
                    <router-link to="/admin/accounting" class="help-link">{{ $t('system_integrity_checks') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
                </p>
            </section>

            <!-- ── Month by month ───────────────────────────────────────── -->
            <section v-if="months.length > 1" class="vr-card">
                <header class="vr-card-head">
                    <h2><i class="fas fa-calendar-days"></i> {{ $t('vr_by_month') }}</h2>
                    <span class="legend">
                        <span><i class="dot is-out"></i> {{ $t('output_tax') }}</span>
                        <span><i class="dot is-in"></i> {{ $t('input_tax') }}</span>
                    </span>
                </header>

                <div class="months">
                    <div class="month is-head">
                        <span>{{ $t('vr_month') }}</span>
                        <span></span>
                        <span class="ta-end">{{ $t('output_tax') }}</span>
                        <span class="ta-end">{{ $t('input_tax') }}</span>
                        <span class="ta-end">{{ $t('vr_net') }}</span>
                    </div>
                    <div v-for="m in months" :key="m.month" class="month">
                        <span class="m-name">{{ monthLabel(m.month) }}</span>
                        <span class="m-bars">
                            <span class="m-bar is-out" :style="{ width: barWidth(m.output) }"></span>
                            <span class="m-bar is-in" :style="{ width: barWidth(m.input) }"></span>
                        </span>
                        <span class="ta-end amt is-out">{{ plain(m.output) }}</span>
                        <span class="ta-end amt is-in">{{ plain(m.input) }}</span>
                        <strong class="ta-end amt" :class="{ 'is-refund': m.net < 0 }">{{ plain(m.net) }}</strong>
                    </div>
                    <div class="month is-total">
                        <span>{{ $t('total') }}</span>
                        <span></span>
                        <span class="ta-end amt">{{ plain(report.output_tax.amount) }}</span>
                        <span class="ta-end amt">{{ plain(report.input_tax.amount) }}</span>
                        <strong class="ta-end amt">{{ plain(report.net) }}</strong>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { accountingReportsApi } from '@/api/accountingReports';
import { formatMoney, formatNumber, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/* ------------------------------------------------------------------ *
 * Period
 *
 * Returns are filed by month or by quarter, so those are the presets. The
 * screen used to open on an empty picker that silently meant "since 1 January".
 * ------------------------------------------------------------------ */

const preset = ref('this_quarter');
const range = ref([]);

const presetOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'this_month' },
    { label: t('vr_last_month'), value: 'last_month' },
    { label: t('vr_this_quarter'), value: 'this_quarter' },
    { label: t('vr_last_quarter'), value: 'last_quarter' },
    { label: t('acc_ov_this_year'), value: 'this_year' },
    { label: t('lg_custom'), value: 'custom' },
]);

const presetRange = (p) => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const q = Math.floor(m / 3) * 3;
    switch (p) {
        case 'this_month': return [localDate(new Date(y, m, 1)), todayIso];
        case 'last_month': return [localDate(new Date(y, m - 1, 1)), localDate(new Date(y, m, 0))];
        case 'last_quarter': return [localDate(new Date(y, q - 3, 1)), localDate(new Date(y, q, 0))];
        case 'this_year': return [`${y}-01-01`, todayIso];
        default: return [localDate(new Date(y, q, 1)), todayIso];
    }
};

const detectPreset = ([from, to]) => ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year']
    .find((p) => { const [f, tt] = presetRange(p); return f === from && tt === to; }) || 'custom';

const applyPreset = (p) => {
    if (p === 'custom') return;
    range.value = presetRange(p);
    reload();
};

const onRangePicked = () => {
    preset.value = detectPreset(range.value);
    reload();
};

/* ------------------------------------------------------------------ *
 * Report
 * ------------------------------------------------------------------ */

const report = ref(null);
const loading = ref(false);
const error = ref('');

const reload = async () => {
    loading.value = true;
    error.value = '';
    router.replace({ query: preset.value === 'this_quarter' ? {} : { date_from: range.value[0], date_to: range.value[1] } });
    try {
        const res = await accountingReportsApi.vatReturn({ date_from: range.value[0], date_to: range.value[1] });
        report.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const isZero = computed(() => Math.abs(Number(report.value?.net || 0)) < 0.005);
const resultClass = computed(() => (isZero.value ? 'is-zero' : report.value?.direction === 'payable' ? 'is-payable' : 'is-refundable'));

const effectiveRate = computed(() => {
    const base = Number(report.value?.sales_base || 0);
    if (!base) return null;
    const pct = (Number(report.value.output_tax.amount || 0) / base) * 100;
    return `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 2 }).format(pct)}%`;
});

const reconciliationRows = computed(() => {
    const r = report.value;
    if (!r) return [];
    return [
        {
            key: 'output',
            label: t('output_tax'),
            documents: r.documents.invoice_tax,
            ledger: r.output_tax.amount,
            difference: r.reconciliation.output_difference,
            matches: r.reconciliation.output_matches,
        },
        {
            key: 'input',
            label: t('input_tax'),
            documents: r.documents.net_input_tax ?? r.documents.receipt_tax,
            ledger: r.input_tax.amount,
            difference: r.reconciliation.input_difference,
            matches: r.reconciliation.input_matches,
        },
    ];
});

const allMatch = computed(() => reconciliationRows.value.every((row) => row.matches));

/* ------------------------------------------------------------------ *
 * Months
 * ------------------------------------------------------------------ */

const months = computed(() => report.value?.months || []);
const monthMax = computed(() => Math.max(1, ...months.value.flatMap((m) => [Math.abs(m.output), Math.abs(m.input)])));
const barWidth = (v) => `${Math.round((Math.abs(Number(v || 0)) / monthMax.value) * 100)}%`;

const monthLabel = (ym) => {
    const [y, m] = ym.split('-').map(Number);
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'ar-SY', { month: 'long', year: 'numeric' }).format(new Date(y, m - 1, 1));
};

const printPage = () => window.print();

onMounted(() => {
    const { date_from: from, date_to: to } = route.query;
    const valid = (d) => /^\d{4}-\d{2}-\d{2}$/.test(String(d || ''));
    range.value = valid(from) && valid(to) ? [String(from), String(to)] : presetRange('this_quarter');
    preset.value = detectPreset(range.value);
    reload();
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. Output tax (held for the state) is blue, input tax
   (a claim on it) is teal. */
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
    --c-out: #1d4ed8;
    --c-out-bg: #dbeafe;
    --c-in: #0f766e;
    --c-in-bg: #ccfbf1;
    color: var(--ov-body);
}

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.print-only { display: none; }

.vr-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.vr-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.vr-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.vr-card-head h2 i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.t-range { max-width: 300px; }
.toolbar :deep(.el-range-input::placeholder) { color: var(--ov-subtle); }

@media (max-width: 640px) { .t-range { max-width: none; width: 100%; } }

/* ── Result ───────────────────────────────────────────────────────────── */
.result {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem 1.5rem;
    padding: 1.2rem 1.3rem;
    margin-bottom: 1rem;
    border: 1px solid;
    border-radius: var(--ov-radius);
}

.result.is-payable { background: #fff7ed; border-color: #fed7aa; --r: #9a3412; }
.result.is-refundable { background: #ecfdf5; border-color: #a7f3d0; --r: #065f46; }
.result.is-zero { background: var(--ov-soft); border-color: var(--ov-border); --r: var(--ov-muted); }

.result-main { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.result-label { display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.9rem; font-weight: 800; color: var(--r); }
.result-amount { font-size: 2rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; line-height: 1.2; unicode-bidi: isolate; }
.result-period { font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); font-variant-numeric: tabular-nums; }
.result-period i { margin-inline-end: 0.25rem; color: var(--ov-subtle); }

.formula { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-inline-start: auto; }
.f-term { display: flex; flex-direction: column; padding: 0.45rem 0.75rem; border-radius: 10px; background: #fff; border: 1px solid var(--ov-border); min-width: 110px; }
.f-term small { font-size: 0.7rem; font-weight: 700; color: var(--ov-muted); }
.f-term b { font-size: 0.98rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.f-term.is-out b { color: var(--c-out); }
.f-term.is-in b { color: var(--c-in); }
.f-term.is-net { border-color: var(--r); }
.f-op { font-size: 1.2rem; font-weight: 800; color: var(--ov-subtle); }

.recon-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.7rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; flex-basis: 100%; width: fit-content; max-width: fit-content; }
.recon-pill.is-ok { color: #065f46; background: #d1fae5; }
.recon-pill.is-bad { color: #991b1b; background: #fee2e2; }

@media (max-width: 720px) { .formula { margin-inline-start: 0; } }

/* ── Sides ────────────────────────────────────────────────────────────── */
.sides { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.sides .vr-card { margin-bottom: 0; }
.side.is-out { --s: var(--c-out); --s-bg: var(--c-out-bg); border-top: 3px solid var(--c-out); }
.side.is-in { --s: var(--c-in); --s-bg: var(--c-in-bg); border-top: 3px solid var(--c-in); }

.side-head { display: flex; align-items: flex-start; gap: 0.7rem; }
.side-icon { width: 36px; height: 36px; flex-shrink: 0; display: grid; place-items: center; border-radius: 10px; color: var(--s); background: var(--s-bg); }
.side-head h2 { margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--ov-text); }
.side-head p { margin: 0.1rem 0 0; font-size: 0.78rem; color: var(--ov-muted); line-height: 1.5; }
.side-amount { display: block; margin: 0.7rem 0 0.5rem; font-size: 1.5rem; font-weight: 800; color: var(--s); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }

.facts { margin: 0; display: flex; flex-direction: column; }
.facts div { display: flex; justify-content: space-between; gap: 1rem; padding: 0.4rem 0; border-bottom: 1px dashed var(--ov-border); font-size: 0.84rem; }
.facts dt { color: var(--ov-muted); font-weight: 600; }
.facts dd { margin: 0; color: var(--ov-text); font-weight: 700; font-variant-numeric: tabular-nums; unicode-bidi: isolate; }

.account-line { margin: 0.7rem 0 0; font-size: 0.78rem; color: var(--ov-muted); }
.account-line.is-missing { color: #991b1b; font-weight: 700; }

.code-badge {
    display: inline-block;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0 0.4rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.74rem;
    direction: ltr;
}

/* ── Reconciliation ───────────────────────────────────────────────────── */
.recon-grid { display: flex; flex-direction: column; gap: 0.5rem; }

.recon-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1rem;
    padding: 0.7rem 0.9rem;
    border: 1px solid;
    border-inline-start-width: 4px;
    border-radius: 10px;
}

.recon-row.is-ok { background: #f0fdf4; border-color: #bbf7d0; border-inline-start-color: #047857; }
.recon-row.is-bad { background: #fef2f2; border-color: #fecaca; border-inline-start-color: #b91c1c; }
.recon-side { min-width: 130px; font-weight: 800; color: var(--ov-text); }
.recon-cell { display: flex; flex-direction: column; }
.recon-cell small { font-size: 0.72rem; font-weight: 700; color: var(--ov-muted); }
.recon-cell b { font-size: 0.95rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.recon-op { font-size: 1.2rem; font-weight: 800; color: var(--ov-subtle); }
.recon-row.is-bad .recon-op { color: #b91c1c; }
.recon-status { display: inline-flex; align-items: center; gap: 0.35rem; margin-inline-start: auto; font-size: 0.84rem; font-weight: 700; }
.recon-row.is-ok .recon-status { color: #065f46; }
.recon-row.is-bad .recon-status { color: #991b1b; }

.recon-help { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.4rem; margin: 0.8rem 0 0; font-size: 0.82rem; line-height: 1.7; color: var(--ov-body); }
.recon-help > i { color: #b45309; }
.help-link { font-weight: 700; color: #1d4ed8; text-decoration: none; }
.help-link:hover { text-decoration: underline; }
.dir-arrow { font-size: 0.7em; margin-inline-start: 0.25rem; }
[dir="rtl"] .dir-arrow { transform: scaleX(-1); }

/* ── Months ───────────────────────────────────────────────────────────── */
.legend { display: flex; gap: 1rem; font-size: 0.78rem; font-weight: 600; color: var(--ov-muted); }
.dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-inline-end: 0.3rem; }
.dot.is-out, .m-bar.is-out { background: #3b82f6; }
.dot.is-in, .m-bar.is-in { background: #14b8a6; }

.months { display: flex; flex-direction: column; }

.month {
    display: grid;
    grid-template-columns: 140px minmax(80px, 1fr) 130px 130px 130px;
    gap: 0.75rem;
    align-items: center;
    padding: 0.5rem 0.25rem;
    border-bottom: 1px dashed var(--ov-border);
    font-size: 0.84rem;
}

.month.is-head { font-size: 0.74rem; font-weight: 700; color: var(--ov-subtle); border-bottom-style: solid; padding-top: 0; }
.month.is-total { border-bottom: 0; border-top: 1px solid var(--ov-border); background: #f1f5f9; border-radius: 8px; margin-top: 0.3rem; font-weight: 800; color: var(--ov-text); }
.m-name { font-weight: 700; color: var(--ov-text); }
.m-bars { display: flex; flex-direction: column; gap: 3px; }
.m-bar { display: block; height: 6px; border-radius: 999px; min-width: 2px; }
.ta-end { text-align: end; }
.amt { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--ov-text); unicode-bidi: isolate; }
.amt.is-out { color: var(--c-out); font-weight: 700; }
.amt.is-in { color: var(--c-in); font-weight: 700; }
.amt.is-refund { color: #065f46; }

@media (max-width: 720px) {
    .month { grid-template-columns: minmax(0, 1fr) repeat(3, minmax(0, 1fr)); }
    .m-bars, .month > span:nth-child(2) { display: none; }
}

/* ── Direction and print ──────────────────────────────────────────────── */
.mr-1 { margin-inline-end: 0.35rem; }

@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }
    .print-period { margin: 0 0 0.75rem; font-weight: 700; color: #000; }
    .vr-card, .result { box-shadow: none; break-inside: avoid-page; }
    .m-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
