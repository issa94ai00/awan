<template>
    <div class="accounting-page party-statement">
        <AdminPageHeader
            icon="fas fa-file-lines text-primary"
            :title="$t('party_statement')"
            :subtitle="$t('party_statement_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag no-print">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :disabled="!report" @click="exportCsv">
                    <i class="fas fa-file-csv mr-1"></i> {{ $t('tb_export_csv') }}
                </el-button>
                <el-button type="primary" plain class="no-print" :disabled="!report" @click="printReport">
                    <i class="fas fa-print mr-1"></i> {{ $t('print_statement') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Who and when ───────────────────────────────────────────────── -->
        <section class="ps-card picker no-print">
            <div class="picker-row">
                <div class="type-switch" role="radiogroup">
                    <button
                        v-for="opt in typeOptions"
                        :key="opt.value"
                        type="button"
                        role="radio"
                        class="type-btn"
                        :class="['side-' + opt.value, { 'is-active': type === opt.value }]"
                        :aria-checked="type === opt.value"
                        @click="setType(opt.value)"
                    >
                        <i :class="opt.icon"></i> {{ opt.label }}
                    </button>
                </div>

                <!-- Searches the server as you type: the lists are paged, and
                     the old picker only ever saw the first page of them. -->
                <el-select
                    v-model="partyId"
                    filterable
                    remote
                    clearable
                    :remote-method="searchParties"
                    :loading="partiesLoading"
                    :placeholder="type === 'customer' ? $t('ps_search_customer') : $t('ps_search_supplier')"
                    class="p-party"
                    @change="onPartyChange"
                    @visible-change="(open) => open && !parties.length && searchParties('')"
                >
                    <template #prefix><i class="fas fa-search"></i></template>
                    <el-option v-for="party in parties" :key="party.id" :label="party.name" :value="party.id">
                        <span class="opt-name">{{ party.name }}</span>
                        <span v-if="party.phone || party.company" class="opt-sub">{{ party.company || party.phone }}</span>
                    </el-option>
                </el-select>
            </div>

            <div class="picker-row">
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
                    class="p-range"
                    @change="onRangePicked"
                />
            </div>
        </section>

        <!-- ── Nothing chosen yet ─────────────────────────────────────────── -->
        <div v-if="!partyId" class="ps-card empty-state no-print">
            <span class="empty-icon"><i class="fas fa-file-invoice"></i></span>
            <p>{{ $t('choose_a_party_to_see_its_statement') }}</p>
            <small>{{ $t('ps_choose_hint') }}</small>
        </div>

        <template v-else>
            <el-alert v-if="error" type="error" show-icon :closable="false" :title="error" class="mb-3 no-print">
                <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
            </el-alert>

            <div v-if="loading && !report" class="ps-card"><el-skeleton :rows="8" animated /></div>

            <template v-else-if="report">
                <!-- Printed heading: the screen shows the same through the
                     picker, which does not print. -->
                <div class="print-head print-only">
                    <h2>{{ report.party.name }}</h2>
                    <p>{{ typeLabel }} · {{ $t('tb_period_label', { from: report.period.from, to: report.period.to }) }}</p>
                </div>

                <!-- ── Position ─────────────────────────────────────────── -->
                <div class="position" :class="'side-' + type" v-loading="loading">
                    <div class="position-id">
                        <span class="party-kind"><i :class="type === 'customer' ? 'fas fa-user' : 'fas fa-truck'"></i> {{ typeLabel }}</span>
                        <h2>{{ report.party.name }}</h2>
                        <span class="position-period"><i class="far fa-calendar"></i> {{ report.period.from }} → {{ report.period.to }}</span>
                    </div>
                    <div class="position-balance" :class="stanceClass">
                        <span>{{ $t('closing_balance') }}</span>
                        <strong>{{ money(Math.abs(report.closing_balance)) }}</strong>
                        <span class="stance">{{ balanceNarrative }}</span>
                    </div>
                </div>

                <div class="tiles">
                    <div class="tile">
                        <span>{{ $t('opening_balance') }}</span>
                        <strong>{{ money(Math.abs(report.opening_balance)) }}</strong>
                        <small>{{ sideLabel(report.opening_balance) }} · {{ report.period.from }}</small>
                    </div>
                    <div class="tile is-debit">
                        <span>{{ $t('jr_col_debit') }}</span>
                        <strong>{{ money(report.totals.debits) }}</strong>
                        <small>{{ type === 'customer' ? $t('ps_hint_customer_debit') : $t('ps_hint_supplier_debit') }}</small>
                    </div>
                    <div class="tile is-credit">
                        <span>{{ $t('jr_col_credit') }}</span>
                        <strong>{{ money(report.totals.credits) }}</strong>
                        <small>{{ type === 'customer' ? $t('ps_hint_customer_credit') : $t('ps_hint_supplier_credit') }}</small>
                    </div>
                    <div class="tile is-closing">
                        <span>{{ $t('closing_balance') }}</span>
                        <strong>{{ money(Math.abs(report.closing_balance)) }}</strong>
                        <small>{{ sideLabel(report.closing_balance) }} · {{ report.period.to }}</small>
                    </div>
                </div>

                <el-alert
                    v-if="!report.matches_stored_balance && reachesToday"
                    type="warning"
                    show-icon
                    :closable="false"
                    class="mb-3"
                    :title="$t('statement_does_not_match_party_record')"
                >
                    {{ $t('statement_mismatch_difference', { amount: money(mismatchDifference) }) }}
                </el-alert>

                <!-- ── Movements ────────────────────────────────────────── -->
                <section class="ps-card">
                    <header class="ps-card-head">
                        <h2><i class="fas fa-receipt"></i> {{ $t('ps_movements') }}</h2>
                        <span class="count-pill">{{ $t('movements_count', { count: report.movements.length }) }}</span>
                    </header>

                    <div v-if="docTypes.length > 1" class="doc-chips no-print">
                        <button type="button" class="chip" :class="{ 'is-active': !docFilter }" @click="docFilter = ''">
                            {{ $t('jr_status_all') }} <span class="chip-count">{{ report.movements.length }}</span>
                        </button>
                        <button
                            v-for="d in docTypes"
                            :key="d.kind"
                            type="button"
                            class="chip"
                            :class="['doc-' + d.kind, { 'is-active': docFilter === d.kind }]"
                            @click="docFilter = docFilter === d.kind ? '' : d.kind"
                        >
                            <span class="chip-dot"></span> {{ d.label }} <span class="chip-count">{{ d.count }}</span>
                        </button>
                    </div>

                    <div v-if="!report.movements.length" class="empty-state is-compact">
                        <p>{{ $t('no_movements_in_this_period') }}</p>
                    </div>

                    <!-- Oldest first, from the opening figure down to the
                         closing one, the way a statement is read and checked. -->
                    <el-table v-else :data="tableRows" :row-class-name="rowClass" class="ps-table">
                        <el-table-column :label="$t('jr_col_date')" width="110">
                            <template #default="{ row }"><span class="cell-date">{{ row.edge ? '' : row.date }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('ps_document')" min-width="230">
                            <template #default="{ row }">
                                <strong v-if="row.edge" class="edge-label">{{ row.edge === 'opening' ? $t('opening_balance') : $t('closing_balance') }}</strong>
                                <span v-else class="doc-cell">
                                    <span class="doc-chip" :class="'doc-' + docKind(row)">{{ docLabel(row) }}</span>
                                    <span class="code-badge">{{ row.number }}</span>
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_debit')" width="140" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span v-if="row.debit > 0" class="amt is-debit">{{ plain(row.debit) }}</span>
                                <span v-else-if="!row.edge" class="amt is-zero">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_credit')" width="140" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span v-if="row.credit > 0" class="amt is-credit">{{ plain(row.credit) }}</span>
                                <span v-else-if="!row.edge" class="amt is-zero">—</span>
                            </template>
                        </el-table-column>
                        <!-- Shown unsigned with its side: a bare minus reads as
                             "bad" on a customer and "normal" on a supplier. -->
                        <el-table-column :label="$t('running_balance')" width="190" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span class="bal">
                                    <strong class="amt">{{ plain(Math.abs(row.balance)) }}</strong>
                                    <span v-if="Math.abs(row.balance) >= 0.005" class="side-chip" :class="row.balance > 0 ? 'is-dr' : 'is-cr'">
                                        {{ row.balance > 0 ? $t('ps_dr') : $t('ps_cr') }}
                                    </span>
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>

                    <p v-if="docFilter" class="filter-note no-print">
                        <i class="fas fa-filter"></i> {{ $t('ps_filter_note') }}
                    </p>
                </section>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { accountingReportsApi } from '@/api/accountingReports';
import { customersApi } from '@/api/customers';
import { suppliersApi } from '@/api/suppliers';
import { formatMoney, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/* ------------------------------------------------------------------ *
 * Party
 * ------------------------------------------------------------------ */

const type = ref('customer');
const partyId = ref(null);
const parties = ref([]);
const partiesLoading = ref(false);

const typeOptions = computed(() => [
    { value: 'customer', label: t('client'), icon: 'fas fa-user' },
    { value: 'supplier', label: t('supplier'), icon: 'fas fa-truck' },
]);
const typeLabel = computed(() => (type.value === 'customer' ? t('client') : t('supplier')));

let searchSeq = 0;
const searchParties = async (query) => {
    const seq = ++searchSeq;
    partiesLoading.value = true;
    try {
        const params = { per_page: 50, search: query || undefined };
        const res = type.value === 'customer' ? await customersApi.getAll(params) : await suppliersApi.getAll(params);
        if (seq !== searchSeq) return;
        const data = res.data?.data || {};
        const list = data.customers || data.suppliers || [];
        // Keep the chosen party in the list, or the select shows its bare id.
        const chosen = parties.value.find((p) => p.id === partyId.value);
        parties.value = chosen && !list.some((p) => p.id === chosen.id) ? [chosen, ...list] : list;
    } catch (e) {
        if (seq === searchSeq) parties.value = [];
    } finally {
        if (seq === searchSeq) partiesLoading.value = false;
    }
};

const setType = (value) => {
    if (type.value === value) return;
    type.value = value;
    partyId.value = null;
    parties.value = [];
    report.value = null;
    syncUrl();
    searchParties('');
};

const onPartyChange = () => {
    report.value = null;
    docFilter.value = '';
    syncUrl();
    reload();
};

/* ------------------------------------------------------------------ *
 * Period
 * ------------------------------------------------------------------ */

const preset = ref('year');
const range = ref([]);

const presetOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'month' },
    { label: t('acc_ov_this_year'), value: 'year' },
    { label: t('lg_last_year'), value: 'last_year' },
    { label: t('ps_all_time'), value: 'all' },
    { label: t('lg_custom'), value: 'custom' },
]);

const presetRange = (p) => {
    const now = new Date();
    if (p === 'month') return [localDate(new Date(now.getFullYear(), now.getMonth(), 1)), todayIso];
    if (p === 'last_year') return [`${now.getFullYear() - 1}-01-01`, `${now.getFullYear() - 1}-12-31`];
    if (p === 'all') return ['2000-01-01', todayIso];
    return [`${now.getFullYear()}-01-01`, todayIso];
};

const detectPreset = ([from, to]) => ['month', 'year', 'last_year', 'all'].find((p) => {
    const [f, tt] = presetRange(p);
    return f === from && tt === to;
}) || 'custom';

const applyPreset = (p) => {
    if (p === 'custom') return;
    range.value = presetRange(p);
    syncUrl();
    reload();
};

const onRangePicked = () => {
    preset.value = detectPreset(range.value);
    syncUrl();
    reload();
};

const syncUrl = () => {
    const query = { type: type.value };
    if (partyId.value) query.party_id = String(partyId.value);
    if (preset.value !== 'year') Object.assign(query, { date_from: range.value[0], date_to: range.value[1] });
    router.replace({ query });
};

/* ------------------------------------------------------------------ *
 * Statement
 * ------------------------------------------------------------------ */

const report = ref(null);
const loading = ref(false);
const error = ref('');

const reload = async () => {
    if (!partyId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const res = await accountingReportsApi.partyStatement({
            type: type.value,
            party_id: partyId.value,
            date_from: range.value[0],
            date_to: range.value[1],
        });
        report.value = res.data?.data || null;
        const party = report.value?.party;
        if (party && !parties.value.some((p) => p.id === party.id)) parties.value = [{ id: party.id, name: party.name }, ...parties.value];
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const reachesToday = computed(() => !report.value?.period?.to || report.value.period.to >= todayIso);

const mismatchDifference = computed(() => Math.abs((report.value?.closing_balance || 0) - (report.value?.stored_balance || 0)));

/**
 * The balance runs debit minus credit for both kinds of party: positive means
 * the party owes us, negative means we owe the party. The old wording read a
 * supplier the other way round and told the reader a supplier they owed
 * money to owed them.
 */
const balanceNarrative = computed(() => {
    const balance = report.value?.closing_balance || 0;
    const amount = money(Math.abs(balance));
    if (Math.abs(balance) < 0.005) return t('account_balance_settled');
    if (type.value === 'customer') {
        return balance > 0 ? t('customer_owes_balance', { amount }) : t('customer_credit_balance', { amount });
    }
    return balance < 0 ? t('we_owe_supplier_balance', { amount }) : t('supplier_owes_us_balance', { amount });
});

/** Green when the position is in our favour, amber when we owe, grey when settled. */
const stanceClass = computed(() => {
    const balance = report.value?.closing_balance || 0;
    if (Math.abs(balance) < 0.005) return 'is-settled';
    return balance > 0 ? 'is-owed-to-us' : 'is-we-owe';
});

const sideLabel = (balance) => {
    if (Math.abs(Number(balance || 0)) < 0.005) return t('ps_settled');
    return Number(balance) > 0 ? t('ps_dr') : t('ps_cr');
};

/* ------------------------------------------------------------------ *
 * Documents
 *
 * The server sends its own Arabic label with each row; the kind is worked out
 * here instead so the English screen does not show Arabic, and a refund is
 * told apart from a collection.
 * ------------------------------------------------------------------ */

const docKind = (row) => {
    if (row.type === 'payment') {
        if (type.value === 'supplier') return 'supplier_payment';
        return row.debit > 0 ? 'refund' : 'collection';
    }
    return row.type;
};

const DOC_KEYS = {
    invoice: 'ps_doc_invoice',
    collection: 'ps_doc_collection',
    refund: 'ps_doc_refund',
    credit_note: 'ps_doc_credit_note',
    receipt: 'ps_doc_receipt',
    landed_cost: 'ps_doc_landed_cost',
    supplier_payment: 'ps_doc_supplier_payment',
    return: 'ps_doc_purchase_return',
};

const docLabel = (row) => (DOC_KEYS[docKind(row)] ? t(DOC_KEYS[docKind(row)]) : row.label);

const docFilter = ref('');

const docTypes = computed(() => {
    const counts = {};
    (report.value?.movements || []).forEach((row) => {
        const kind = docKind(row);
        counts[kind] ??= { kind, label: docLabel(row), count: 0 };
        counts[kind].count += 1;
    });
    return Object.values(counts);
});

/** Filtering hides rows but keeps each row's own running balance, which stays true. */
const tableRows = computed(() => {
    const r = report.value;
    if (!r) return [];
    const rows = docFilter.value ? r.movements.filter((row) => docKind(row) === docFilter.value) : r.movements;
    return [
        { edge: 'opening', balance: r.opening_balance },
        ...rows,
        { edge: 'closing', balance: r.closing_balance, debit: r.totals.debits, credit: r.totals.credits },
    ];
});

const rowClass = ({ row }) => (row.edge ? 'is-edge' : '');

/* ------------------------------------------------------------------ *
 * Export and print
 * ------------------------------------------------------------------ */

const exportCsv = () => {
    const r = report.value;
    if (!r) return;
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [
        [r.party.name, typeLabel.value, `${r.period.from} → ${r.period.to}`],
        [],
        [t('jr_col_date'), t('ps_document'), t('jr_col_entry'), t('jr_col_debit'), t('jr_col_credit'), t('running_balance')],
        ['', t('opening_balance'), '', '', '', Number(r.opening_balance).toFixed(2)],
        ...r.movements.map((m) => [m.date, docLabel(m), m.number, Number(m.debit).toFixed(2), Number(m.credit).toFixed(2), Number(m.balance).toFixed(2)]),
        ['', t('closing_balance'), '', Number(r.totals.debits).toFixed(2), Number(r.totals.credits).toFixed(2), Number(r.closing_balance).toFixed(2)],
    ];
    // The BOM makes Excel read Arabic names as UTF-8.
    const blob = new Blob(['﻿' + lines.map((l) => l.map(esc).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `statement_${type.value}_${r.party.id}_${r.period.from}_${r.period.to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const printReport = () => window.print();

// `?type=supplier&party_id=12` opens straight on that party: how the aging
// report's names link here instead of making the reader pick them again.
onMounted(async () => {
    const q = route.query;
    if (['customer', 'supplier'].includes(q.type)) type.value = q.type;
    const valid = (d) => /^\d{4}-\d{2}-\d{2}$/.test(String(d || ''));
    range.value = valid(q.date_from) && valid(q.date_to) ? [String(q.date_from), String(q.date_to)] : presetRange('year');
    preset.value = detectPreset(range.value);

    const id = Number(q.party_id);
    if (id) {
        partyId.value = id;
        reload();
    }
    searchParties('');
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
    color: var(--ov-body);
}

.side-customer { --side: #1d4ed8; --side-bg: #dbeafe; }
.side-supplier { --side: #b45309; --side-bg: #fef3c7; }

.doc-invoice { --d: #1e40af; --d-bg: #dbeafe; }
.doc-collection { --d: #065f46; --d-bg: #d1fae5; }
.doc-refund { --d: #9f1239; --d-bg: #ffe4e6; }
.doc-credit_note { --d: #5b21b6; --d-bg: #ede9fe; }
.doc-receipt { --d: #92400e; --d-bg: #fef3c7; }
.doc-landed_cost { --d: #9a3412; --d-bg: #ffedd5; }
.doc-supplier_payment { --d: #065f46; --d-bg: #d1fae5; }
.doc-return { --d: #155e75; --d-bg: #cffafe; }

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.print-only { display: none; }

.ps-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.ps-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.ps-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.ps-card-head h2 i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }
.count-pill { padding: 0.1rem 0.65rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.8rem; font-weight: 700; }

/* ── Picker ───────────────────────────────────────────────────────────── */
.picker { display: flex; flex-direction: column; gap: 0.75rem; }
.picker-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.picker-row + .picker-row { padding-top: 0.75rem; border-top: 1px dashed var(--ov-border); }

.type-switch { display: inline-flex; padding: 3px; border-radius: 10px; background: #f1f5f9; }

.type-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border: 0;
    border-radius: 8px;
    background: transparent;
    font: inherit;
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--ov-muted);
    cursor: pointer;
}

.type-btn.is-active { background: #fff; color: var(--side); box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12); }
.type-btn:focus-visible { outline: 2px solid var(--side); outline-offset: 1px; }

.p-party { flex: 1 1 280px; max-width: 480px; }
.p-party :deep(.el-select__prefix) { color: var(--ov-subtle); }
.p-range { max-width: 300px; }
.picker :deep(.el-select__placeholder:not(.is-transparent)) { color: var(--ov-body); }
.picker :deep(.el-select__placeholder.is-transparent) { color: var(--ov-subtle); }
.picker :deep(.el-range-input::placeholder) { color: var(--ov-subtle); }

.opt-name { font-weight: 600; color: var(--ov-text); }
.opt-sub { margin-inline-start: 0.5rem; font-size: 0.76rem; color: var(--ov-subtle); }

@media (max-width: 640px) {
    .p-party, .p-range { max-width: none; width: 100%; }
}

/* ── Position ─────────────────────────────────────────────────────────── */
.position {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.2rem;
    margin-bottom: 1rem;
    border: 1px solid var(--ov-border);
    border-top: 3px solid var(--side);
    border-radius: var(--ov-radius);
    background: linear-gradient(180deg, color-mix(in srgb, var(--side) 6%, #fff) 0%, #fff 80%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.position-id { display: flex; flex-direction: column; gap: 0.25rem; min-width: 0; }
.party-kind { display: inline-flex; align-items: center; gap: 0.35rem; width: fit-content; padding: 0.05rem 0.55rem; border-radius: 999px; font-size: 0.74rem; font-weight: 700; color: var(--side); background: var(--side-bg); }
.position-id h2 { margin: 0; font-size: 1.3rem; font-weight: 800; color: var(--ov-text); overflow-wrap: anywhere; }
.position-period { font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); font-variant-numeric: tabular-nums; }
.position-period i { color: var(--ov-subtle); margin-inline-end: 0.25rem; }

.position-balance { display: flex; flex-direction: column; align-items: flex-end; gap: 0.1rem; text-align: end; }
.position-balance > span:first-child { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.position-balance strong { font-size: 1.7rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.stance { padding: 0.1rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
.is-owed-to-us .stance { color: #065f46; background: #d1fae5; }
.is-we-owe .stance { color: #92400e; background: #fef3c7; }
.is-settled .stance { color: var(--ov-muted); background: #f1f5f9; }

@media (max-width: 640px) {
    .position-balance { align-items: flex-start; text-align: start; }
}

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
.tile strong { font-size: 1.15rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; unicode-bidi: isolate; }
.tile small { font-size: 0.74rem; color: var(--ov-subtle); line-height: 1.5; }
.tile.is-debit { border-top: 3px solid var(--c-debit); }
.tile.is-debit strong { color: var(--c-debit); }
.tile.is-credit { border-top: 3px solid var(--c-credit); }
.tile.is-credit strong { color: var(--c-credit); }
.tile.is-closing { background: #eff6ff; border-color: #bfdbfe; }

/* ── Chips ────────────────────────────────────────────────────────────── */
.doc-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.8rem; }

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

.chip:hover { border-color: var(--d, #94a3b8); }
.chip.is-active { color: var(--d, var(--ov-text)); background: var(--d-bg, #f1f5f9); border-color: var(--d, #94a3b8); }
.chip-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--d); }
.chip-count { min-width: 1.4rem; padding: 0 0.35rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.72rem; text-align: center; }
.chip.is-active .chip-count { background: #fff; }

.filter-note { display: flex; align-items: center; gap: 0.4rem; margin: 0.6rem 0 0; font-size: 0.78rem; color: var(--ov-muted); }

/* ── Table ────────────────────────────────────────────────────────────── */
.ps-table { --el-table-header-bg-color: var(--ov-soft); --el-table-border-color: var(--ov-border); --el-table-row-hover-bg-color: #f5f8ff; font-size: 0.84rem; }
.ps-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.76rem; }
.ps-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }
.ps-table :deep(.el-table__row.is-edge td.el-table__cell) { background: #f1f5f9; }
.edge-label { color: var(--ov-text); font-weight: 800; }

.doc-cell { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
.doc-chip { display: inline-block; padding: 0 0.55rem; border-radius: 999px; font-size: 0.74rem; font-weight: 700; line-height: 1.8; color: var(--d); background: var(--d-bg); }

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

.cell-date { font-variant-numeric: tabular-nums; white-space: nowrap; }
.amt { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; color: var(--ov-text); unicode-bidi: isolate; }
strong.amt { font-weight: 800; }
.amt.is-debit { color: var(--c-debit); font-weight: 700; }
.amt.is-credit { color: var(--c-credit); font-weight: 700; }
.amt.is-zero { color: #94a3b8; font-weight: 400; }

.bal { display: inline-flex; align-items: center; gap: 0.4rem; }
.side-chip { padding: 0 0.4rem; border-radius: 5px; font-size: 0.68rem; font-weight: 800; line-height: 1.7; }
.side-chip.is-dr { color: #065f46; background: #d1fae5; }
.side-chip.is-cr { color: #92400e; background: #fef3c7; }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.6rem; padding: 3rem 1rem; text-align: center; }
.empty-state.is-compact { padding: 1.5rem 1rem; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; border-radius: 18px; background: #eff6ff; color: #1d4ed8; font-size: 1.5rem; }
.empty-state p { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--ov-text); }
.empty-state small { font-size: 0.82rem; color: var(--ov-muted); }

/* ── Direction ────────────────────────────────────────────────────────────
   Element Plus cells are text-align:left; `mr-1` is not generated here. */
.mr-1 { margin-inline-end: 0.35rem; }
.ps-table :deep(.el-table__cell) { text-align: start; }
.ps-table :deep(.el-table__cell.col-end) { text-align: end; }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }
    .print-head h2 { margin: 0; font-size: 1.2rem; color: #000; }
    .print-head p { margin: 0.2rem 0 0.8rem; color: #000; }
    .position { display: none; }
    .ps-card, .tile { box-shadow: none; }
}
</style>
