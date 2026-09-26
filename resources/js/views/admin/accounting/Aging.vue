<template>
    <div class="accounting-page accounting-aging">
        <AdminPageHeader
            icon="fas fa-hourglass-half text-primary"
            :title="$t('aging_report')"
            :subtitle="$t('aging_report_subtitle')"
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

        <!-- ── As of ──────────────────────────────────────────────────────── -->
        <section class="ag-card toolbar no-print">
            <span class="toolbar-label"><i class="far fa-calendar"></i> {{ $t('as_of_date') }}</span>
            <el-date-picker
                v-model="asOf"
                type="date"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :clearable="false"
                :shortcuts="dateShortcuts"
                class="t-date"
                @change="reload"
            />
            <el-button v-if="asOf !== todayIso" text class="link-btn" @click="asOf = todayIso; reload()">
                {{ $t('ag_back_to_today') }}
            </el-button>
            <el-input v-model="search" :placeholder="$t('ag_search_placeholder')" clearable class="t-search">
                <template #prefix><i class="fas fa-search"></i></template>
            </el-input>
        </section>

        <p class="print-only print-asof">{{ $t('ag_as_of_label', { date: report?.as_of || asOf }) }}</p>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ── Summary: one card per side ─────────────────────────────────── -->
        <div class="summary-grid">
            <template v-if="loading && !report">
                <div v-for="n in 2" :key="n" class="sum-card"><el-skeleton :rows="3" animated /></div>
            </template>
            <template v-else-if="report">
            <button
                v-for="section in sections"
                :key="section.key"
                type="button"
                class="sum-card"
                :class="'side-' + section.key"
                @click="scrollTo(section.key)"
            >
                <span class="sum-head">
                    <span class="sum-icon"><i :class="section.icon"></i></span>
                    <span class="sum-title">{{ section.title }}</span>
                    <span v-if="section.data?.control_account" class="recon-pill" :class="reconClass(section.data)">
                        <i class="fas" :class="section.data.reconciled === false ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
                        {{ section.data.reconciled === false ? $t('ag_not_reconciled_short') : $t('ag_reconciled_short') }}
                    </span>
                </span>
                <strong class="sum-total">{{ money(section.data?.total) }}</strong>
                <span class="sum-stats">
                    <span>{{ $t('ag_parties_count', { count: section.data?.parties?.length || 0 }) }}</span>
                    <span :class="{ 'is-late': overdueOf(section.data) > 0 }">
                        {{ $t('ag_overdue_share', { amount: money(overdueOf(section.data)), pct: pctOf(overdueOf(section.data), section.data?.total) }) }}
                    </span>
                    <span v-if="Number(section.data?.buckets?.over_90) > 0" class="is-severe">
                        {{ $t('ag_over_90_amount', { amount: money(section.data.buckets.over_90) }) }}
                    </span>
                </span>
                <AgingBar :buckets="section.data?.buckets" :order="buckets" :total="section.data?.total" />
            </button>
            </template>
        </div>

        <!-- ── Sections ───────────────────────────────────────────────────── -->
        <template v-if="report">
            <section
                v-for="section in sections"
                :id="'aging-' + section.key"
                :key="section.key"
                class="ag-card section"
                :class="'side-' + section.key"
            >
                <header class="ag-card-head">
                    <h2><i :class="section.icon"></i> {{ section.title }}</h2>
                    <span class="count-pill">{{ money(section.data?.total) }}</span>
                </header>

                <!-- The reconciliation is the point of the report: a list of
                     who owes what is only worth something if it adds up to the
                     account the ledger keeps for it. -->
                <div v-if="section.data?.control_account" class="recon" :class="reconClass(section.data)">
                    <div class="recon-cell">
                        <span>{{ $t('subsidiary_total') }}</span>
                        <strong>{{ money(section.data.total) }}</strong>
                    </div>
                    <span class="recon-op">{{ section.data.reconciled === false ? '≠' : '=' }}</span>
                    <div class="recon-cell">
                        <span>{{ $t('control_account') }} <span class="code-badge">{{ section.data.control_account.code }}</span></span>
                        <strong>{{ money(section.data.control_account.balance) }}</strong>
                    </div>
                    <div class="recon-verdict">
                        <i class="fas" :class="section.data.reconciled === false ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
                        <span>
                            {{ section.data.reconciled === false
                                ? $t('not_reconciled_with_ledger', { amount: money(Math.abs(section.data.difference)) })
                                : $t('reconciled_with_ledger') }}
                        </span>
                    </div>
                </div>

                <p v-if="section.key === 'payables'" class="method-note">
                    <i class="fas fa-circle-info"></i>
                    <span>{{ $t('ag_payables_method') }}</span>
                </p>

                <!-- Bucket chips double as a filter: "who is over 90 days?" is
                     the question a collector actually starts from. -->
                <div v-if="section.data?.parties?.length" class="bucket-chips no-print">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'is-active': !bucketFilter[section.key] }"
                        @click="bucketFilter[section.key] = ''"
                    >
                        {{ $t('jr_status_all') }}
                        <span class="chip-count">{{ section.data.parties.length }}</span>
                    </button>
                    <button
                        v-for="bucket in buckets"
                        :key="bucket"
                        type="button"
                        class="chip"
                        :class="['b-' + bucket, { 'is-active': bucketFilter[section.key] === bucket }]"
                        :disabled="!partiesIn(section, bucket)"
                        @click="bucketFilter[section.key] = bucketFilter[section.key] === bucket ? '' : bucket"
                    >
                        <span class="chip-dot"></span>
                        {{ $t('bucket_' + bucket) }}
                        <span class="chip-count">{{ partiesIn(section, bucket) }}</span>
                    </button>
                </div>

                <div v-if="!section.data?.parties?.length" class="empty-state">
                    <span class="empty-icon"><i class="fas fa-circle-check"></i></span>
                    <p>{{ $t('nothing_outstanding') }}</p>
                </div>

                <div v-else-if="!visibleParties(section).length" class="empty-state is-compact">
                    <p>{{ $t('ag_no_match') }}</p>
                </div>

                <el-table
                    v-else
                    :data="visibleParties(section)"
                    :row-key="(row) => section.key + '-' + (row.id ?? 0)"
                    show-summary
                    :summary-method="(p) => summary(p, section)"
                    class="ag-table"
                    :class="{ 'has-docs': section.key === 'receivables' }"
                >
                    <!-- The invoices behind a customer's figure are already in
                         the answer; opening the row shows them. -->
                    <el-table-column v-if="section.key === 'receivables'" type="expand" width="40">
                        <template #default="{ row }">
                            <div class="docs">
                                <div class="doc is-head">
                                    <span>{{ $t('ag_invoice') }}</span>
                                    <span>{{ $t('ag_issued') }}</span>
                                    <span>{{ $t('ag_due') }}</span>
                                    <span>{{ $t('ag_age') }}</span>
                                    <span class="ta-end">{{ $t('ag_outstanding') }}</span>
                                </div>
                                <div v-for="doc in sortedDocs(row)" :key="doc.number" class="doc">
                                    <span class="code-badge">{{ doc.number }}</span>
                                    <span class="cell-date">{{ doc.date }}</span>
                                    <span class="cell-date">{{ doc.due_date || '—' }}</span>
                                    <span><span class="age-chip" :class="'b-' + doc.bucket">{{ ageLabel(doc) }}</span></span>
                                    <span class="ta-end amt">{{ money(doc.amount) }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="section.key === 'receivables' ? $t('ag_customer') : $t('ag_supplier')" min-width="220">
                        <template #default="{ row }">
                            <span class="party-cell">
                                <router-link
                                    v-if="row.id"
                                    :to="{ path: '/admin/accounting/party-statement', query: { type: section.partyType, party_id: row.id } }"
                                    class="party-name"
                                    @click.stop
                                >{{ row.name }}</router-link>
                                <span v-else class="party-name is-plain">{{ row.name }}</span>
                                <span v-if="row.documents?.length" class="party-sub">{{ $t('ag_invoices_count', { count: row.documents.length }) }}</span>
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column
                        v-for="bucket in buckets"
                        :key="bucket"
                        :label="$t('bucket_' + bucket)"
                        width="128"
                        class-name="col-end"
                        :label-class-name="'col-end head-' + bucket"
                    >
                        <template #default="{ row }">
                            <span v-if="Number(row.buckets?.[bucket] || 0) > 0" class="amt" :class="'b-' + bucket">
                                {{ plain(row.buckets[bucket]) }}
                            </span>
                            <span v-else class="amt is-zero">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('total')" width="150" class-name="col-end col-total" label-class-name="col-end">
                        <template #default="{ row }">
                            <strong class="amt">{{ plain(row.total) }}</strong>
                            <span class="row-bar"><AgingBar :buckets="row.buckets" :order="buckets" :total="row.total" thin /></span>
                        </template>
                    </el-table-column>
                </el-table>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { accountingReportsApi } from '@/api/accountingReports';
import { formatMoney, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const pctOf = (part, whole) => {
    const w = Number(whole || 0);
    if (!w) return '0%';
    return `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 0 }).format((Number(part || 0) / w) * 100)}%`;
};

/** Local calendar date: `toISOString()` is UTC and names yesterday until 03:00 here. */
const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/**
 * How old each part of a balance is, as one stacked bar.
 * Segments run in the reading direction, youngest first.
 */
const AgingBar = defineComponent({
    props: { buckets: Object, order: Array, total: [Number, String], thin: Boolean },
    setup(props) {
        return () => {
            const total = Number(props.total || 0);
            const segments = total > 0
                ? (props.order || []).map((b) => ({ b, v: Number(props.buckets?.[b] || 0) })).filter((s) => s.v > 0)
                : [];
            return h('span', { class: ['aging-bar', { 'is-thin': props.thin }], role: 'img', 'aria-hidden': 'true' },
                segments.map((s) => h('span', { class: 'seg b-' + s.b, style: { width: `${(s.v / total) * 100}%` } })));
        };
    },
});

/* ------------------------------------------------------------------ *
 * Loading
 * ------------------------------------------------------------------ */

const asOf = ref(todayIso);
const report = ref(null);
const loading = ref(false);
const error = ref('');

const dateShortcuts = computed(() => [
    { text: t('ag_today'), value: () => new Date() },
    { text: t('ag_end_of_last_month'), value: () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 0); } },
    { text: t('ag_end_of_last_year'), value: () => new Date(new Date().getFullYear() - 1, 11, 31) },
]);

const reload = async () => {
    loading.value = true;
    error.value = '';
    router.replace({ query: asOf.value === todayIso ? {} : { as_of: asOf.value } });
    try {
        const res = await accountingReportsApi.aging({ as_of: asOf.value });
        report.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

// The server decides which buckets exist, so adding one there does not mean
// editing a hardcoded list here.
const buckets = computed(() => report.value?.buckets || ['current', '1_30', '31_60', '61_90', 'over_90']);

const sections = computed(() => [
    { key: 'receivables', partyType: 'customer', title: t('receivables_aging'), icon: 'fas fa-hand-holding-dollar', data: report.value?.receivables },
    { key: 'payables', partyType: 'supplier', title: t('payables_aging'), icon: 'fas fa-file-invoice-dollar', data: report.value?.payables },
]);

const overdueOf = (data) => buckets.value.filter((b) => b !== 'current').reduce((s, b) => s + Number(data?.buckets?.[b] || 0), 0);
const reconClass = (data) => (data?.reconciled === false ? 'is-bad' : 'is-ok');

/* ------------------------------------------------------------------ *
 * Filtering
 * ------------------------------------------------------------------ */

const search = ref('');
const bucketFilter = reactive({ receivables: '', payables: '' });

const partiesIn = (section, bucket) => (section.data?.parties || []).filter((p) => Number(p.buckets?.[bucket] || 0) > 0).length;

const visibleParties = (section) => {
    const q = search.value.trim().toLowerCase();
    const bucket = bucketFilter[section.key];
    return (section.data?.parties || []).filter((p) =>
        (!q || String(p.name || '').toLowerCase().includes(q))
        && (!bucket || Number(p.buckets?.[bucket] || 0) > 0));
};

/** Totals of the rows shown, so a filtered table still adds up on screen. */
const summary = ({ columns }, section) => {
    const rows = visibleParties(section);
    const sum = (fn) => plain(rows.reduce((s, r) => s + Number(fn(r) || 0), 0));
    const offset = section.key === 'receivables' ? 1 : 0;
    return columns.map((col, i) => {
        if (i < offset) return '';
        if (i === offset) return t('total');
        const bucket = buckets.value[i - offset - 1];
        return bucket ? sum((r) => r.buckets?.[bucket]) : sum((r) => r.total);
    });
};

/* ------------------------------------------------------------------ *
 * Documents
 * ------------------------------------------------------------------ */

const daysBetween = (from, to) => Math.round((new Date(to) - new Date(from)) / 86400000);

/** Oldest debt first: that is the one to chase. */
const sortedDocs = (row) => [...(row.documents || [])].sort((a, b) => String(a.due_date || a.date).localeCompare(String(b.due_date || b.date)));

const ageLabel = (doc) => {
    const due = doc.due_date || doc.date;
    const days = daysBetween(due, report.value?.as_of || asOf.value);
    if (days <= 0) return days === 0 ? t('ag_due_today') : t('ag_due_in', { days: -days });
    return t('ag_days_overdue', { days });
};

/* ------------------------------------------------------------------ *
 * Misc
 * ------------------------------------------------------------------ */

const scrollTo = (key) => document.getElementById('aging-' + key)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
const printPage = () => window.print();

onMounted(() => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(String(route.query.as_of || ''))) asOf.value = String(route.query.as_of);
    reload();
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. The five buckets run from green to red. */
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
    --ov-link: color-mix(in srgb, var(--el-color-primary, #409eff) 68%, #000);
    --b-current: #047857;
    --b-1_30: #a16207;
    --b-31_60: #c2410c;
    --b-61_90: #b91c1c;
    --b-over_90: #7f1d1d;
    color: var(--ov-body);
}

.b-current { --b: var(--b-current); --b-bg: #d1fae5; --b-bar: #10b981; }
.b-1_30 { --b: var(--b-1_30); --b-bg: #fef9c3; --b-bar: #eab308; }
.b-31_60 { --b: var(--b-31_60); --b-bg: #ffedd5; --b-bar: #f97316; }
.b-61_90 { --b: var(--b-61_90); --b-bg: #fee2e2; --b-bar: #ef4444; }
.b-over_90 { --b: var(--b-over_90); --b-bg: #fecaca; --b-bar: #991b1b; }

.side-receivables { --side: #1d4ed8; --side-bg: #dbeafe; }
.side-payables { --side: #b45309; --side-bg: #fef3c7; }

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.print-only { display: none; }

/* ── Cards ────────────────────────────────────────────────────────────── */
.ag-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.ag-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.ag-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }

.ag-card-head h2 i {
    width: 30px;
    height: 30px;
    display: inline-grid;
    place-items: center;
    border-radius: 9px;
    font-size: 0.85rem;
    color: var(--side);
    background: var(--side-bg);
}

.count-pill { padding: 0.1rem 0.7rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-text); font-size: 0.85rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.section { scroll-margin-top: 1rem; border-top: 3px solid var(--side); }

/* ── Toolbar ──────────────────────────────────────────────────────────── */
.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem 0.75rem; }
.toolbar-label { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.86rem; font-weight: 700; color: var(--ov-muted); }
.toolbar-label i { color: var(--ov-subtle); }
.t-date { width: 170px; }
.t-search { flex: 1 1 220px; max-width: 340px; margin-inline-start: auto; }
.t-search :deep(.el-input__prefix) { color: var(--ov-subtle); }
.toolbar :deep(.el-input__inner::placeholder) { color: var(--ov-subtle); }
.link-btn { color: var(--ov-link); font-weight: 700; }

@media (max-width: 640px) {
    .t-search { max-width: none; margin-inline-start: 0; }
}

/* ── Summary ──────────────────────────────────────────────────────────── */
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 0.9rem; margin-bottom: 1rem; }

.sum-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.45rem;
    padding: 1rem 1.1rem;
    text-align: start;
    font: inherit;
    color: inherit;
    background: linear-gradient(180deg, color-mix(in srgb, var(--side, #64748b) 6%, #fff) 0%, #fff 70%);
    border: 1px solid var(--ov-border);
    border-top: 3px solid var(--side, #64748b);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    min-width: 0;
}

.sum-card:hover { box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
.sum-card:focus-visible { outline: 2px solid var(--side); outline-offset: 2px; }

.sum-head { display: flex; align-items: center; gap: 0.55rem; }
.sum-icon { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; color: var(--side); background: var(--side-bg); }
.sum-title { font-size: 0.9rem; font-weight: 800; color: var(--ov-text); }
.sum-total { font-size: 1.55rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.sum-stats { display: flex; flex-wrap: wrap; gap: 0.2rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); }
.sum-stats .is-late { color: #9a3412; }
.sum-stats .is-severe { color: #991b1b; font-weight: 700; }

.recon-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-inline-start: auto;
    padding: 0.05rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.recon-pill.is-ok { color: #065f46; background: #d1fae5; }
.recon-pill.is-bad { color: #991b1b; background: #fee2e2; }

/* ── Aging bar ────────────────────────────────────────────────────────── */
:deep(.aging-bar) { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: #eef2f7; margin-top: 0.2rem; }
:deep(.aging-bar.is-thin) { height: 4px; margin-top: 0.3rem; }
:deep(.aging-bar .seg) { display: block; height: 100%; }
:deep(.aging-bar .seg.b-current) { background: #10b981; }
:deep(.aging-bar .seg.b-1_30) { background: #eab308; }
:deep(.aging-bar .seg.b-31_60) { background: #f97316; }
:deep(.aging-bar .seg.b-61_90) { background: #ef4444; }
:deep(.aging-bar .seg.b-over_90) { background: #991b1b; }

/* ── Reconciliation ───────────────────────────────────────────────────── */
.recon {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem 1rem;
    padding: 0.75rem 0.95rem;
    margin-bottom: 0.9rem;
    border-radius: 10px;
    border: 1px solid;
}

.recon.is-ok { background: #f0fdf4; border-color: #bbf7d0; }
.recon.is-bad { background: #fef2f2; border-color: #fecaca; }
.recon-cell { display: flex; flex-direction: column; gap: 0.1rem; }
.recon-cell span { font-size: 0.76rem; font-weight: 700; color: var(--ov-muted); }
.recon-cell strong { font-size: 1rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; }
.recon-op { font-size: 1.3rem; font-weight: 800; color: var(--ov-subtle); }
.recon.is-bad .recon-op { color: #b91c1c; }
.recon-verdict { display: inline-flex; align-items: center; gap: 0.4rem; margin-inline-start: auto; font-size: 0.84rem; font-weight: 700; }
.recon.is-ok .recon-verdict { color: #065f46; }
.recon.is-bad .recon-verdict { color: #991b1b; }

.method-note {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    font-size: 0.8rem;
    line-height: 1.7;
    color: var(--ov-muted);
}

.method-note i { color: var(--ov-subtle); }

/* ── Bucket chips ─────────────────────────────────────────────────────── */
.bucket-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.8rem; }

.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.7rem;
    border: 1px solid var(--ov-border);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ov-body);
    cursor: pointer;
}

.chip:hover:not(:disabled) { border-color: var(--b, #94a3b8); }
.chip:disabled { opacity: 0.45; cursor: not-allowed; }
.chip.is-active { color: var(--b, var(--ov-text)); background: var(--b-bg, #f1f5f9); border-color: var(--b, #94a3b8); }
.chip-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--b-bar); }
.chip-count { min-width: 1.4rem; padding: 0 0.35rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.72rem; text-align: center; font-variant-numeric: tabular-nums; }
.chip.is-active .chip-count { background: #fff; }

/* ── Table ────────────────────────────────────────────────────────────── */
.ag-table { --el-table-header-bg-color: var(--ov-soft); --el-table-border-color: var(--ov-border); --el-table-row-hover-bg-color: #f5f8ff; font-size: 0.84rem; }
.ag-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.76rem; }
.ag-table :deep(th.head-over_90 .cell), .ag-table :deep(th.head-61_90 .cell) { color: #991b1b; }
.ag-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }
.ag-table :deep(.el-table__expanded-cell) { background: var(--ov-soft); }
.ag-table :deep(.el-table__footer-wrapper td.el-table__cell) { background: #f1f5f9; }
.ag-table :deep(.el-table__footer-wrapper .cell) { color: var(--ov-text); font-weight: 800; font-variant-numeric: tabular-nums; }
.ag-table :deep(td.col-total) { background: #fafbfc; }

.party-cell { display: flex; flex-direction: column; min-width: 0; }
.party-name { font-weight: 700; color: var(--ov-text); text-decoration: none; }
a.party-name:hover { color: var(--ov-link); text-decoration: underline; text-underline-offset: 3px; }
.party-sub { font-size: 0.72rem; color: var(--ov-subtle); }

.amt { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 700; color: var(--b, var(--ov-text)); unicode-bidi: isolate; }
strong.amt { font-weight: 800; color: var(--ov-text); }
.amt.is-zero { color: #94a3b8; font-weight: 400; }
.row-bar { display: block; }

.code-badge {
    display: inline-block;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0 0.4rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.76rem;
    direction: ltr;
}

.docs { padding-block: 0.3rem 0.6rem; padding-inline: 2.75rem 1rem; }

.doc {
    display: grid;
    grid-template-columns: 140px 100px 100px minmax(0, 1fr) 130px;
    gap: 0.6rem;
    align-items: center;
    padding: 0.35rem 0;
    font-size: 0.8rem;
    border-bottom: 1px dashed var(--ov-border);
}

.doc.is-head { font-size: 0.72rem; font-weight: 700; color: var(--ov-subtle); border-bottom-style: solid; }
.doc:last-child { border-bottom: 0; }
.ta-end { text-align: end; }
.cell-date { font-variant-numeric: tabular-nums; }

.age-chip { display: inline-block; padding: 0 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; color: var(--b); background: var(--b-bg); }

@media (max-width: 720px) {
    .docs { padding-inline: 0.5rem; }
    .doc { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .doc.is-head { display: none; }
}

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; padding: 2.5rem 1rem; text-align: center; }
.empty-state.is-compact { padding: 1.5rem 1rem; }
.empty-icon { width: 56px; height: 56px; display: grid; place-items: center; border-radius: 16px; background: #ecfdf5; color: #047857; font-size: 1.4rem; }
.empty-state p { margin: 0; font-size: 0.92rem; font-weight: 600; color: var(--ov-muted); }

/* ── Direction ────────────────────────────────────────────────────────────
   Element Plus has no RTL mode: cells are text-align:left and the expand
   chevron points right. `mr-1` is not generated by Tailwind here. */
.mr-1 { margin-inline-end: 0.35rem; }
.ag-table :deep(.el-table__cell) { text-align: start; }
.ag-table :deep(.el-table__cell.col-end) { text-align: end; }
[dir="rtl"] .ag-table :deep(.el-table__expand-icon:not(.el-table__expand-icon--expanded)) { transform: rotate(180deg); }
.ag-table :deep(.el-table__footer-wrapper td.el-table__cell:not(:first-child) .cell) { text-align: end; }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }
    .print-asof { margin: 0 0 0.75rem; font-weight: 700; color: #000; }
    .ag-card, .sum-card { box-shadow: none; break-inside: avoid-page; }
    .sum-card:hover { transform: none; }
    :deep(.aging-bar .seg) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
