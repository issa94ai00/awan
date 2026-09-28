<template>
    <div class="accounting-page cash-flow">
        <AdminPageHeader
            icon="fas fa-water text-primary"
            :title="$t('cash_flow_statement')"
            :subtitle="$t('cash_flow_subtitle')"
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
        <section class="cf-card toolbar no-print">
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
            <span v-if="report?.accounts?.length" class="accounts-note">
                <i class="fas fa-wallet"></i>
                {{ $t('cf_cash_accounts', { names: listOf(report.accounts) }) }}
            </span>
        </section>

        <p class="print-only print-period">{{ $t('tb_period_label', { from: report?.period?.from || '', to: report?.period?.to || '' }) }}</p>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <div v-if="loading && !report" class="cf-card"><el-skeleton :rows="7" animated /></div>

        <template v-else-if="report">
            <!-- ── Opening → change → closing ───────────────────────────── -->
            <!-- A cash flow statement that does not tie the opening to the
                 closing is a list, not a statement. -->
            <div class="tiles" v-loading="loading">
                <div class="tile">
                    <span>{{ $t('opening_balance') }}</span>
                    <strong>{{ money(report.opening_balance) }}</strong>
                    <small>{{ report.period.from }}</small>
                </div>
                <div class="tile is-in">
                    <span>{{ $t('cf_total_in') }}</span>
                    <strong>{{ money(totalIn) }}</strong>
                </div>
                <div class="tile is-out">
                    <span>{{ $t('cf_total_out') }}</span>
                    <strong>{{ money(totalOut) }}</strong>
                </div>
                <div class="tile is-closing">
                    <span>{{ $t('closing_balance') }}</span>
                    <strong>{{ money(report.closing_balance) }}</strong>
                    <small :class="report.net_change >= 0 ? 'is-up' : 'is-down'">
                        <i class="fas" :class="report.net_change >= 0 ? 'fa-caret-up' : 'fa-caret-down'"></i>
                        {{ $t('cf_change', { amount: money(Math.abs(report.net_change)) }) }}
                    </small>
                </div>
            </div>

            <!-- Only meaningful when the period runs to today: an earlier
                 closing figure is not supposed to equal what the accounts hold
                 now, and the old screen warned about every past period. -->
            <el-alert
                v-if="reachesToday && !ties"
                type="warning"
                show-icon
                :closable="false"
                class="mb-3"
                :title="$t('does_not_tie_to_accounts')"
            >
                {{ $t('cf_tie_detail', { closing: money(report.closing_balance), stored: money(report.stored_balance) }) }}
            </el-alert>

            <!-- ── Bridge ───────────────────────────────────────────────── -->
            <section class="cf-card">
                <header class="cf-card-head">
                    <h2><i class="fas fa-bridge"></i> {{ $t('cf_bridge') }}</h2>
                    <span v-if="reachesToday" class="tie-pill" :class="ties ? 'is-ok' : 'is-bad'">
                        <i class="fas" :class="ties ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                        {{ ties ? $t('cf_ties') : $t('cf_does_not_tie') }}
                    </span>
                </header>

                <!-- Each step starts where the last one ended, so the bars read
                     as the balance being carried from opening to closing. -->
                <div class="bridge">
                    <div v-for="step in bridge" :key="step.key" class="b-row" :class="['is-' + step.kind, { 'is-neg': step.value < 0 }]">
                        <span class="b-label">
                            <i v-if="step.icon" :class="step.icon"></i>
                            {{ step.label }}
                        </span>
                        <span class="b-track">
                            <span class="b-bar" :style="{ insetInlineStart: step.start + '%', width: Math.max(step.width, 0.6) + '%' }"></span>
                        </span>
                        <strong class="b-value">{{ step.kind === 'delta' && step.value > 0 ? '+' : '' }}{{ plain(step.value) }}</strong>
                    </div>
                </div>
            </section>

            <!-- ── Activities ───────────────────────────────────────────── -->
            <section v-for="section in sections" :key="section.key" class="cf-card activity" :class="'act-' + section.key">
                <header class="cf-card-head">
                    <h2><i :class="section.icon"></i> {{ section.title }}</h2>
                    <strong class="net-pill" :class="section.data.net >= 0 ? 'is-up' : 'is-down'">
                        {{ $t('cf_net') }}: {{ section.data.net > 0 ? '+' : '' }}{{ plain(section.data.net) }}
                    </strong>
                </header>

                <p class="section-note">{{ section.note }}</p>

                <div v-if="!section.inflows.length && !section.outflows.length" class="empty-state">
                    <p>{{ $t('no_movement_in_this_activity') }}</p>
                </div>

                <div v-else class="flows">
                    <div v-for="side in ['in', 'out']" :key="side" class="flow-col" :class="'is-' + side">
                        <div class="flow-head">
                            <span><i class="fas" :class="side === 'in' ? 'fa-arrow-down' : 'fa-arrow-up'"></i> {{ side === 'in' ? $t('cash_in') : $t('cash_out') }}</span>
                            <strong>{{ plain(side === 'in' ? section.data.total_in : section.data.total_out) }}</strong>
                        </div>
                        <div v-if="!(side === 'in' ? section.inflows : section.outflows).length" class="flow-empty">—</div>
                        <!-- Largest first, each with its share of the side it is on. -->
                        <div v-for="row in (side === 'in' ? section.inflows : section.outflows)" :key="row.label" class="flow-row">
                            <div class="flow-line">
                                <span class="flow-label">{{ row.label }}</span>
                                <span class="flow-amt">{{ plain(row.amount) }}</span>
                            </div>
                            <div class="flow-bar"><span :style="{ width: share(row.amount, side === 'in' ? section.data.total_in : section.data.total_out) + '%' }"></span></div>
                            <small class="flow-share">{{ share(row.amount, side === 'in' ? section.data.total_in : section.data.total_out) }}%</small>
                        </div>
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
import { formatMoney, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

/** "Cash, Bank" / "الصندوق والبنك", joined the way each language joins a list. */
const listOf = (items) => new Intl.ListFormat(locale.value === 'en' ? 'en' : 'ar', { type: 'conjunction' }).format(items || []);

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/* ------------------------------------------------------------------ *
 * Period
 * ------------------------------------------------------------------ */

const preset = ref('this_year');
const range = ref([]);

const presetOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'this_month' },
    { label: t('vr_last_month'), value: 'last_month' },
    { label: t('vr_this_quarter'), value: 'this_quarter' },
    { label: t('acc_ov_this_year'), value: 'this_year' },
    { label: t('lg_last_year'), value: 'last_year' },
    { label: t('lg_custom'), value: 'custom' },
]);

const presetRange = (p) => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    switch (p) {
        case 'this_month': return [localDate(new Date(y, m, 1)), todayIso];
        case 'last_month': return [localDate(new Date(y, m - 1, 1)), localDate(new Date(y, m, 0))];
        case 'this_quarter': return [localDate(new Date(y, Math.floor(m / 3) * 3, 1)), todayIso];
        case 'last_year': return [`${y - 1}-01-01`, `${y - 1}-12-31`];
        default: return [`${y}-01-01`, todayIso];
    }
};

const detectPreset = ([from, to]) => ['this_month', 'last_month', 'this_quarter', 'this_year', 'last_year']
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
    router.replace({ query: preset.value === 'this_year' ? {} : { date_from: range.value[0], date_to: range.value[1] } });
    try {
        const res = await accountingReportsApi.cashFlow({ date_from: range.value[0], date_to: range.value[1] });
        report.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const reachesToday = computed(() => !report.value?.period?.to || report.value.period.to >= todayIso);
const ties = computed(() => Math.abs(Number(report.value?.closing_balance || 0) - Number(report.value?.stored_balance || 0)) < 0.005);

const ACTIVITIES = ['operating', 'investing', 'financing'];

const activityMeta = computed(() => ({
    operating: { title: t('operating_activities'), note: t('operating_activities_note'), icon: 'fas fa-cart-shopping' },
    investing: { title: t('investing_activities'), note: t('investing_activities_note'), icon: 'fas fa-building-columns' },
    financing: { title: t('financing_activities'), note: t('financing_activities_note'), icon: 'fas fa-hand-holding-dollar' },
}));

const sections = computed(() => ACTIVITIES.map((key) => {
    const data = report.value?.activities?.[key] || { inflows: [], outflows: [], total_in: 0, total_out: 0, net: 0 };
    const byAmount = (list) => [...(list || [])].sort((a, b) => b.amount - a.amount);
    return { key, ...activityMeta.value[key], data, inflows: byAmount(data.inflows), outflows: byAmount(data.outflows) };
}));

const totalIn = computed(() => sections.value.reduce((s, x) => s + Number(x.data.total_in || 0), 0));
const totalOut = computed(() => sections.value.reduce((s, x) => s + Number(x.data.total_out || 0), 0));

const share = (amount, total) => (Number(total) ? Math.round((Number(amount) / Number(total)) * 100) : 0);

/**
 * Opening, one step per activity, closing — laid out on one scale so each
 * step's bar begins where the running balance stood before it.
 */
const bridge = computed(() => {
    const r = report.value;
    if (!r) return [];
    const steps = [];
    let running = Number(r.opening_balance || 0);
    const points = [0, running];

    steps.push({ key: 'opening', kind: 'total', label: t('opening_balance'), from: 0, to: running, value: running });
    sections.value.forEach((s) => {
        const next = running + Number(s.data.net || 0);
        steps.push({ key: s.key, kind: 'delta', label: s.title, icon: s.icon, from: running, to: next, value: Number(s.data.net || 0) });
        points.push(next);
        running = next;
    });
    steps.push({ key: 'closing', kind: 'total', label: t('closing_balance'), from: 0, to: Number(r.closing_balance || 0), value: Number(r.closing_balance || 0) });
    points.push(Number(r.closing_balance || 0));

    const min = Math.min(...points);
    const max = Math.max(...points);
    const span = max - min || 1;
    const pos = (v) => ((v - min) / span) * 100;

    return steps.map((s) => {
        const a = pos(Math.min(s.from, s.to));
        const b = pos(Math.max(s.from, s.to));
        return { ...s, start: a, width: b - a };
    });
});

const printPage = () => window.print();

onMounted(() => {
    const { date_from: from, date_to: to } = route.query;
    const valid = (d) => /^\d{4}-\d{2}-\d{2}$/.test(String(d || ''));
    range.value = valid(from) && valid(to) ? [String(from), String(to)] : presetRange('this_year');
    preset.value = detectPreset(range.value);
    reload();
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. Money in is green, money out amber. */
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
    --c-in: #047857;
    --c-out: #b45309;
    color: var(--ov-body);
}

.act-operating { --a: #1d4ed8; --a-bg: #dbeafe; }
.act-investing { --a: #6d28d9; --a-bg: #ede9fe; }
.act-financing { --a: #0e7490; --a-bg: #cffafe; }

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.print-only { display: none; }

.cf-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.cf-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.cf-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.cf-card-head h2 i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: var(--a, #1d4ed8); background: var(--a-bg, #dbeafe); }
.activity { border-top: 3px solid var(--a); }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.t-range { max-width: 300px; }
.toolbar :deep(.el-range-input::placeholder) { color: var(--ov-subtle); }
.accounts-note { display: inline-flex; align-items: center; gap: 0.4rem; margin-inline-start: auto; font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); }
.accounts-note i { color: var(--ov-subtle); }

@media (max-width: 640px) {
    .t-range { max-width: none; width: 100%; }
    .accounts-note { margin-inline-start: 0; }
}

/* ── Tiles ────────────────────────────────────────────────────────────── */
.tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }

.tile {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.85rem 1rem;
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    min-width: 0;
}

.tile span { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.tile strong { font-size: 1.25rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; unicode-bidi: isolate; }
.tile small { font-size: 0.76rem; font-weight: 600; color: var(--ov-subtle); font-variant-numeric: tabular-nums; }
.tile.is-in { border-top: 3px solid var(--c-in); }
.tile.is-in strong { color: var(--c-in); }
.tile.is-out { border-top: 3px solid var(--c-out); }
.tile.is-out strong { color: var(--c-out); }
.tile.is-closing { background: #eff6ff; border-color: #bfdbfe; }
.is-up { color: var(--c-in) !important; }
.is-down { color: #b91c1c !important; }

.tie-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.1rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; }
.tie-pill.is-ok { color: #065f46; background: #d1fae5; }
.tie-pill.is-bad { color: #991b1b; background: #fee2e2; }

/* ── Bridge ───────────────────────────────────────────────────────────── */
.bridge { display: flex; flex-direction: column; gap: 0.35rem; }

.b-row {
    display: grid;
    grid-template-columns: minmax(130px, 200px) minmax(0, 1fr) 140px;
    gap: 0.9rem;
    align-items: center;
    padding: 0.45rem 0.25rem;
    border-bottom: 1px dashed var(--ov-border);
}

.b-row:last-child { border-bottom: 0; }
.b-row.is-total { background: var(--ov-soft); border-radius: 8px; border-bottom: 0; }
.b-label { display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.86rem; font-weight: 700; color: var(--ov-text); }
.b-label i { color: var(--ov-subtle); font-size: 0.8rem; }
.b-track { position: relative; height: 14px; border-radius: 4px; background: #f1f5f9; }
.b-bar { position: absolute; top: 0; bottom: 0; border-radius: 4px; background: #64748b; }
.b-row.is-delta .b-bar { background: #10b981; }
.b-row.is-delta.is-neg .b-bar { background: #f59e0b; }
.b-row.is-total .b-bar { background: #3b82f6; }
.b-value { text-align: end; font-size: 0.9rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.b-row.is-delta .b-value { color: var(--c-in); }
.b-row.is-delta.is-neg .b-value { color: #b91c1c; }

@media (max-width: 640px) {
    .b-row { grid-template-columns: minmax(0, 1fr) auto; }
    .b-track { grid-column: 1 / -1; grid-row: 2; }
}

/* ── Activities ───────────────────────────────────────────────────────── */
.net-pill { padding: 0.1rem 0.7rem; border-radius: 999px; background: #f1f5f9; font-size: 0.85rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.section-note { margin: 0 0 0.9rem; font-size: 0.82rem; line-height: 1.7; color: var(--ov-muted); }

.flows { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
.flow-col { padding: 0.75rem 0.85rem; border: 1px solid var(--ov-border); border-radius: 10px; background: var(--ov-soft); }
.flow-col.is-in { --f: var(--c-in); --f-bar: #10b981; }
.flow-col.is-out { --f: var(--c-out); --f-bar: #f59e0b; }

.flow-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--ov-border); }
.flow-head span { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.84rem; font-weight: 800; color: var(--f); }
.flow-head strong { font-size: 0.98rem; font-weight: 800; color: var(--f); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.flow-empty { color: #94a3b8; text-align: center; padding: 0.5rem 0; }

.flow-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.15rem 0.6rem; align-items: center; padding: 0.35rem 0; }
.flow-line { grid-column: 1 / -1; display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.84rem; }
.flow-label { color: var(--ov-text); font-weight: 600; min-width: 0; overflow-wrap: anywhere; }
.flow-amt { color: var(--ov-text); font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; unicode-bidi: isolate; }
.flow-bar { height: 5px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.flow-bar span { display: block; height: 100%; border-radius: inherit; background: var(--f-bar); }
.flow-share { font-size: 0.7rem; font-weight: 700; color: var(--ov-subtle); font-variant-numeric: tabular-nums; min-width: 2.5rem; text-align: end; }

.empty-state { padding: 1.25rem 1rem; text-align: center; }
.empty-state p { margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--ov-muted); }

/* ── Direction and print ──────────────────────────────────────────────── */
.mr-1 { margin-inline-end: 0.35rem; }

@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }
    .print-period { margin: 0 0 0.75rem; font-weight: 700; color: #000; }
    .cf-card, .tile { box-shadow: none; break-inside: avoid-page; }
    .b-bar, .flow-bar span { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
