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
                <el-button class="no-print" :disabled="!report" @click="copyStatementSummary">
                    <i class="far fa-copy mr-1"></i> {{ $t('ps_copy_statement') }}
                </el-button>
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

                <!-- Searches the server as you type -->
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
                <!-- ── Print-only formal header ───────────────────────────────────── -->
                <div class="print-head print-only">
                    <div class="print-company-bar">
                        <div class="print-company-info">
                            <h1 class="print-title">{{ $t('party_statement') }}</h1>
                            <p class="print-subtitle">{{ report.party.name }} ({{ typeLabel }})</p>
                        </div>
                        <div class="print-meta">
                            <div><strong>{{ $t('sr_period') }}:</strong> {{ report.period.from }} → {{ report.period.to }}</div>
                            <div><strong>{{ $t('currency') }}:</strong> {{ baseCode }}</div>
                            <div><strong>{{ $t('date') }}:</strong> {{ printTimestamp }}</div>
                        </div>
                    </div>

                    <div class="print-party-grid">
                        <div class="print-grid-item">
                            <span class="print-label">{{ $t('party_type') }}:</span>
                            <span class="print-value">{{ typeLabel }}</span>
                        </div>
                        <div class="print-grid-item">
                            <span class="print-label">{{ $t('name') }}:</span>
                            <span class="print-value"><strong>{{ report.party.name }}</strong></span>
                        </div>
                        <div v-if="report.party.phone" class="print-grid-item">
                            <span class="print-label">{{ $t('ps_phone') }}:</span>
                            <span class="print-value" dir="ltr">{{ report.party.phone }}</span>
                        </div>
                        <div v-if="report.party.address" class="print-grid-item">
                            <span class="print-label">{{ $t('ps_address') }}:</span>
                            <span class="print-value">{{ report.party.address }}</span>
                        </div>
                        <div v-if="report.party.company" class="print-grid-item">
                            <span class="print-label">{{ $t('ps_company') }}:</span>
                            <span class="print-value">{{ report.party.company }}</span>
                        </div>
                    </div>

                    <div class="print-summary-box">
                        <div class="print-summary-cell">
                            <span>{{ $t('opening_balance') }}</span>
                            <strong>{{ money(Math.abs(report.opening_balance)) }}</strong>
                            <small>{{ sideLabel(report.opening_balance) }}</small>
                        </div>
                        <div class="print-summary-cell">
                            <span>{{ $t('jr_col_debit') }}</span>
                            <strong>{{ money(report.totals.debits) }}</strong>
                        </div>
                        <div class="print-summary-cell">
                            <span>{{ $t('jr_col_credit') }}</span>
                            <strong>{{ money(report.totals.credits) }}</strong>
                        </div>
                        <div class="print-summary-cell">
                            <span>{{ $t('ps_net_period_change') }}</span>
                            <strong>{{ money(Math.abs(netPeriodChange)) }}</strong>
                            <small>{{ netPeriodNarrative }}</small>
                        </div>
                        <div class="print-summary-cell highlight">
                            <span>{{ $t('closing_balance') }}</span>
                            <strong>{{ money(Math.abs(report.closing_balance)) }}</strong>
                            <small>{{ sideLabel(report.closing_balance) }}</small>
                        </div>
                    </div>
                </div>

                <!-- ── Interactive Position / Hero Card ────────────────────────── -->
                <div class="position no-print" :class="'side-' + type" v-loading="loading">
                    <div class="position-id">
                        <div class="party-badges">
                            <span class="party-kind">
                                <i :class="type === 'customer' ? 'fas fa-user' : 'fas fa-truck'"></i>
                                {{ typeLabel }}
                            </span>
                            <span v-if="report.party.code" class="party-code-badge">
                                #{{ report.party.code }}
                            </span>
                        </div>
                        <h2>{{ report.party.name }}</h2>

                        <div class="party-meta-row">
                            <span class="position-period">
                                <i class="far fa-calendar"></i> {{ report.period.from }} → {{ report.period.to }}
                            </span>

                            <a
                                v-if="report.party.phone"
                                :href="`tel:${report.party.phone}`"
                                class="party-contact-pill"
                                :title="$t('ps_call')"
                            >
                                <i class="fas fa-phone-alt"></i>
                                <span dir="ltr">{{ report.party.phone }}</span>
                            </a>

                            <a
                                v-if="whatsappUrl"
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="party-contact-pill is-whatsapp"
                                :title="$t('ps_whatsapp')"
                            >
                                <i class="fab fa-whatsapp"></i>
                                <span>{{ $t('ps_whatsapp') }}</span>
                            </a>

                            <span v-if="report.party.address" class="party-contact-pill is-location" :title="$t('ps_address')">
                                <i class="fas fa-location-dot"></i>
                                <span>{{ report.party.address }}</span>
                            </span>

                            <span v-if="report.party.company" class="party-contact-pill is-company" :title="$t('ps_company')">
                                <i class="fas fa-building"></i>
                                <span>{{ report.party.company }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="position-balance" :class="stanceClass">
                        <span class="balance-title">{{ $t('closing_balance') }}</span>
                        <strong class="balance-amount">{{ money(Math.abs(report.closing_balance)) }}</strong>
                        <div class="stance-wrap">
                            <span class="side-chip" :class="report.closing_balance > 0 ? 'is-dr' : (report.closing_balance < 0 ? 'is-cr' : 'is-zero')">
                                {{ sideLabel(report.closing_balance) }}
                            </span>
                            <span class="stance">{{ balanceNarrative }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Financial Summary Tiles ──────────────────────────────────── -->
                <div class="tiles no-print">
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
                    <div class="tile is-net">
                        <span>{{ $t('ps_net_period_change') }}</span>
                        <strong>{{ money(Math.abs(netPeriodChange)) }}</strong>
                        <small>{{ netPeriodNarrative }}</small>
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
                    class="mb-3 no-print"
                    :title="$t('statement_does_not_match_party_record')"
                >
                    {{ $t('statement_mismatch_difference', { amount: money(mismatchDifference) }) }}
                </el-alert>

                <!-- ── Movements Section ────────────────────────────────────────── -->
                <section class="ps-card">
                    <header class="ps-card-head">
                        <div class="head-title-wrap">
                            <h2><i class="fas fa-receipt"></i> {{ $t('ps_movements') }}</h2>
                            <span class="count-pill">{{ $t('movements_count', { count: filteredMovements.length }) }}</span>
                            <span v-if="filteredMovements.length !== report.movements.length" class="count-sub-pill">
                                ({{ report.movements.length }})
                            </span>
                        </div>

                        <!-- Table Toolbar: Search and Sort Direction -->
                        <div class="head-controls no-print">
                            <el-input
                                v-model="filterQuery"
                                clearable
                                size="default"
                                :placeholder="$t('ps_search_movements_placeholder')"
                                class="movements-search"
                            >
                                <template #prefix><i class="fas fa-search text-muted"></i></template>
                            </el-input>

                            <div class="sort-segmented" role="radiogroup">
                                <button
                                    type="button"
                                    role="radio"
                                    class="sort-btn"
                                    :class="{ 'is-active': sortOrder === 'asc' }"
                                    :aria-checked="sortOrder === 'asc'"
                                    :title="$t('ps_sort_oldest_first')"
                                    @click="sortOrder = 'asc'"
                                >
                                    <i class="fas fa-arrow-down-short-wide"></i>
                                    <span>{{ $t('ps_sort_oldest_first') }}</span>
                                </button>
                                <button
                                    type="button"
                                    role="radio"
                                    class="sort-btn"
                                    :class="{ 'is-active': sortOrder === 'desc' }"
                                    :aria-checked="sortOrder === 'desc'"
                                    :title="$t('ps_sort_newest_first')"
                                    @click="sortOrder = 'desc'"
                                >
                                    <i class="fas fa-arrow-up-wide-short"></i>
                                    <span>{{ $t('ps_sort_newest_first') }}</span>
                                </button>
                            </div>
                        </div>
                    </header>

                    <!-- Document Type Chips -->
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
                            <i :class="d.icon" class="chip-icon"></i>
                            {{ d.label }}
                            <span class="chip-count">{{ d.count }}</span>
                        </button>
                    </div>

                    <div v-if="!report.movements.length" class="empty-state is-compact">
                        <p>{{ $t('no_movements_in_this_period') }}</p>
                    </div>

                    <div v-else-if="!filteredMovements.length" class="empty-state is-compact">
                        <p>{{ $t('no_matching_records') }}</p>
                    </div>

                    <!-- Statement Movements Table -->
                    <el-table v-else :data="tableRows" :row-class-name="rowClass" class="ps-table">
                        <!-- Date & Time Column -->
                        <el-table-column :label="$t('jr_col_date')" width="140">
                            <template #default="{ row }">
                                <div v-if="row.edge" class="edge-date">
                                    <span class="cell-date">{{ row.date }}</span>
                                </div>
                                <div v-else class="cell-date-time">
                                    <span class="cell-date">
                                        <i class="far fa-calendar-alt text-muted mr-1 no-print"></i>{{ row.date }}
                                    </span>
                                    <span v-if="row.time" class="cell-time" :title="row.datetime">
                                        <i class="far fa-clock mr-1 no-print"></i>{{ row.time }}
                                    </span>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Document & Notes Column -->
                        <el-table-column :label="$t('ps_document')" min-width="260">
                            <template #default="{ row }">
                                <strong v-if="row.edge" class="edge-label">
                                    <i :class="row.edge === 'opening' ? 'fas fa-hourglass-start mr-1' : 'fas fa-flag-checkered mr-1'"></i>
                                    {{ row.edge === 'opening' ? $t('opening_balance') : $t('closing_balance') }}
                                </strong>
                                <div v-else class="doc-cell">
                                    <div class="doc-header-line">
                                        <span class="doc-chip" :class="'doc-' + docKind(row)">
                                            <i :class="docIcon(row)" class="mr-1"></i>
                                            {{ docLabel(row) }}
                                        </span>

                                        <button
                                            v-if="canOpenDocument(row)"
                                            type="button"
                                            class="code-badge is-clickable"
                                            :title="$t('ps_view_document')"
                                            @click="openDocument(row)"
                                        >
                                            <span>{{ row.number }}</span>
                                            <i class="fas fa-arrow-up-right-from-square doc-link-icon no-print"></i>
                                        </button>
                                        <span v-else class="code-badge">{{ row.number }}</span>

                                        <span
                                            v-if="row.payment_method && paymentMethodInfo(row.payment_method)"
                                            class="method-pill"
                                            :class="paymentMethodInfo(row.payment_method).class"
                                        >
                                            <i :class="paymentMethodInfo(row.payment_method).icon"></i>
                                            <span>{{ paymentMethodInfo(row.payment_method).label }}</span>
                                        </span>

                                        <span v-if="row.due_date" class="due-pill" :title="$t('ps_due_date')">
                                            <i class="far fa-clock mr-1"></i> {{ row.due_date }}
                                        </span>
                                    </div>

                                    <div v-if="row.notes || row.reference" class="doc-notes-line">
                                        <i class="far fa-comment-dots mr-1 text-muted"></i>
                                        <span v-if="row.reference" class="doc-ref-text">[{{ row.reference }}]</span>
                                        <span v-if="row.notes" class="doc-notes-text">{{ row.notes }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>

                        <!-- Debit Column -->
                        <el-table-column :label="$t('jr_col_debit')" width="140" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span v-if="row.debit > 0" class="amt is-debit">{{ plain(row.debit) }}</span>
                                <span v-else-if="!row.edge" class="amt is-zero">—</span>
                            </template>
                        </el-table-column>

                        <!-- Credit Column -->
                        <el-table-column :label="$t('jr_col_credit')" width="140" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span v-if="row.credit > 0" class="amt is-credit">{{ plain(row.credit) }}</span>
                                <span v-else-if="!row.edge" class="amt is-zero">—</span>
                            </template>
                        </el-table-column>

                        <!-- Running Balance Column -->
                        <el-table-column :label="$t('running_balance')" width="190" class-name="col-end" label-class-name="col-end">
                            <template #default="{ row }">
                                <span class="bal">
                                    <strong class="amt">{{ plain(Math.abs(row.balance)) }}</strong>
                                    <span v-if="Math.abs(row.balance) >= 0.005" class="side-chip" :class="row.balance > 0 ? 'is-dr' : 'is-cr'">
                                        {{ row.balance > 0 ? $t('ps_dr') : $t('ps_cr') }}
                                    </span>
                                    <span v-else class="side-chip is-zero">
                                        {{ $t('ps_settled') }}
                                    </span>
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>

                    <p v-if="docFilter || filterQuery" class="filter-note no-print">
                        <i class="fas fa-filter"></i> {{ $t('ps_filter_note') }}
                    </p>
                </section>

                <!-- ── Print Signatures Footer ─────────────────────────────────── -->
                <div class="print-signatures print-only">
                    <div class="sig-box">
                        <p class="sig-title">{{ $t('ps_print_prepared_by') }}</p>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-box">
                        <p class="sig-title">{{ $t('ps_print_approved_by') }}</p>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-box">
                        <p class="sig-title">{{ $t('ps_print_party_signature') }}</p>
                        <div class="sig-line"></div>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
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

const printTimestamp = computed(() => {
    const now = new Date();
    return `${localDate(now)} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
});

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
    filterQuery.value = '';
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
        if (party && !parties.value.some((p) => p.id === party.id)) {
            parties.value = [{ id: party.id, name: party.name, phone: party.phone, company: party.company }, ...parties.value];
        }
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const reachesToday = computed(() => !report.value?.period?.to || report.value.period.to >= todayIso);
const mismatchDifference = computed(() => Math.abs((report.value?.closing_balance || 0) - (report.value?.stored_balance || 0)));

const netPeriodChange = computed(() => {
    const r = report.value;
    if (!r) return 0;
    return (r.totals?.debits || 0) - (r.totals?.credits || 0);
});

const netPeriodNarrative = computed(() => {
    const net = netPeriodChange.value;
    if (Math.abs(net) < 0.005) return t('ps_settled');
    return net > 0 ? t('ps_net_debit') : t('ps_net_credit');
});

const balanceNarrative = computed(() => {
    const balance = report.value?.closing_balance || 0;
    const amount = money(Math.abs(balance));
    if (Math.abs(balance) < 0.005) return t('account_balance_settled');
    if (type.value === 'customer') {
        return balance > 0 ? t('customer_owes_balance', { amount }) : t('customer_credit_balance', { amount });
    }
    return balance < 0 ? t('we_owe_supplier_balance', { amount }) : t('supplier_owes_us_balance', { amount });
});

const stanceClass = computed(() => {
    const balance = report.value?.closing_balance || 0;
    if (Math.abs(balance) < 0.005) return 'is-settled';
    return balance > 0 ? 'is-owed-to-us' : 'is-we-owe';
});

const sideLabel = (balance) => {
    if (Math.abs(Number(balance || 0)) < 0.005) return t('ps_settled');
    return Number(balance) > 0 ? t('ps_dr') : t('ps_cr');
};

const whatsappUrl = computed(() => {
    const p = report.value?.party?.phone;
    if (!p) return null;
    let digits = String(p).replace(/[^0-9]/g, '');
    if (digits.startsWith('09') && digits.length === 10) {
        digits = '963' + digits.substring(1);
    } else if (digits.startsWith('9') && digits.length === 9) {
        digits = '963' + digits;
    }
    return `https://wa.me/${digits}`;
});

/* ------------------------------------------------------------------ *
 * Document Helpers & Navigation
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

const docIcon = (row) => {
    const kind = typeof row === 'string' ? row : docKind(row);
    const icons = {
        invoice: 'fas fa-file-invoice-dollar',
        collection: 'fas fa-hand-holding-dollar',
        refund: 'fas fa-rotate-left',
        credit_note: 'fas fa-file-circle-minus',
        receipt: 'fas fa-boxes-packing',
        landed_cost: 'fas fa-truck-ramp-box',
        supplier_payment: 'fas fa-money-bill-transfer',
        return: 'fas fa-arrow-rotate-left',
    };
    return icons[kind] || 'fas fa-file-lines';
};

const paymentMethodInfo = (method) => {
    if (!method) return null;
    const m = String(method).toLowerCase();
    const map = {
        cash: { label: t('payment_method_cash', 'نقداً'), icon: 'fas fa-money-bill-wave', class: 'm-cash' },
        card: { label: t('payment_method_card', 'بطاقة'), icon: 'fas fa-credit-card', class: 'm-card' },
        bank_transfer: { label: t('payment_method_transfer', 'حوالة بنكية'), icon: 'fas fa-building-columns', class: 'm-bank' },
        check: { label: t('payment_method_check', 'شيك'), icon: 'fas fa-money-check', class: 'm-check' },
    };
    return map[m] || { label: m, icon: 'fas fa-receipt', class: 'm-other' };
};

const canOpenDocument = (row) => {
    if (!row || row.edge) return false;
    const kind = docKind(row);
    return ['invoice', 'collection', 'refund', 'payment', 'supplier_payment', 'receipt', 'return'].includes(kind) || row.type === 'payment';
};

const openDocument = (row) => {
    if (!canOpenDocument(row)) return;
    const kind = docKind(row);
    if (kind === 'invoice' && row.id) {
        const routeData = router.resolve({ name: 'admin.sales.invoices.edit', params: { id: row.id } });
        window.open(routeData.href, '_blank');
        return;
    }
    if ((kind === 'collection' || kind === 'refund' || row.type === 'payment') && type.value === 'customer') {
        const routeData = router.resolve({ name: 'admin.payments.index', query: { search: row.number } });
        window.open(routeData.href, '_blank');
        return;
    }
    if (kind === 'supplier_payment' || (row.type === 'payment' && type.value === 'supplier')) {
        const routeData = router.resolve({ name: 'admin.supplier-payments.index', query: { search: row.number } });
        window.open(routeData.href, '_blank');
        return;
    }
    if (kind === 'receipt') {
        const routeData = router.resolve({ name: 'admin.purchase-receipts.index', query: { search: row.number } });
        window.open(routeData.href, '_blank');
        return;
    }
    if (kind === 'return') {
        const routeData = router.resolve({ name: 'admin.purchase-returns.index', query: { search: row.number } });
        window.open(routeData.href, '_blank');
        return;
    }
};

/* ------------------------------------------------------------------ *
 * Filtering, Sorting & Table Rows
 * ------------------------------------------------------------------ */

const docFilter = ref('');
const filterQuery = ref('');
const sortOrder = ref('asc'); // 'asc' = oldest first (chronological), 'desc' = newest first

const docTypes = computed(() => {
    const counts = {};
    (report.value?.movements || []).forEach((row) => {
        const kind = docKind(row);
        counts[kind] ??= { kind, label: docLabel(row), count: 0, icon: docIcon(kind) };
        counts[kind].count += 1;
    });
    return Object.values(counts);
});

const filteredMovements = computed(() => {
    const movements = report.value?.movements || [];
    let list = movements;
    if (docFilter.value) {
        list = list.filter((m) => docKind(m) === docFilter.value);
    }
    const q = filterQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter((m) => {
            const numMatch = String(m.number || '').toLowerCase().includes(q);
            const notesMatch = String(m.notes || '').toLowerCase().includes(q);
            const refMatch = String(m.reference || '').toLowerCase().includes(q);
            const methodMatch = String(m.payment_method || '').toLowerCase().includes(q);
            const labelMatch = String(docLabel(m) || '').toLowerCase().includes(q);
            const dateMatch = String(m.date || '').includes(q) || String(m.time || '').includes(q);
            const debitMatch = String(m.debit || '').includes(q);
            const creditMatch = String(m.credit || '').includes(q);
            return numMatch || notesMatch || refMatch || methodMatch || labelMatch || dateMatch || debitMatch || creditMatch;
        });
    }
    return list;
});

/**
 * Running balance is computed strictly chronologically by the backend.
 * When asc: Opening balance -> movements (1..N) -> Closing balance.
 * When desc: Closing balance -> movements (N..1 reversed) -> Opening balance.
 * Each movement displays its own historically accurate running balance.
 */
const tableRows = computed(() => {
    const r = report.value;
    if (!r) return [];
    const list = filteredMovements.value;

    if (sortOrder.value === 'desc') {
        return [
            { edge: 'closing', balance: r.closing_balance, debit: r.totals.debits, credit: r.totals.credits, date: r.period.to },
            ...[...list].reverse(),
            { edge: 'opening', balance: r.opening_balance, date: r.period.from },
        ];
    }

    return [
        { edge: 'opening', balance: r.opening_balance, date: r.period.from },
        ...list,
        { edge: 'closing', balance: r.closing_balance, debit: r.totals.debits, credit: r.totals.credits, date: r.period.to },
    ];
});

const rowClass = ({ row }) => (row.edge ? 'is-edge' : '');

/* ------------------------------------------------------------------ *
 * Actions: Copy, Export and Print
 * ------------------------------------------------------------------ */

const copyStatementSummary = async () => {
    const r = report.value;
    if (!r) return;
    const p = r.party;
    const lines = [
        `📄 ${t('party_statement')}: ${p.name}`,
        p.phone ? `📞 ${t('ps_phone')}: ${p.phone}` : null,
        p.address ? `📍 ${t('ps_address')}: ${p.address}` : null,
        `📅 ${t('sr_period')}: ${r.period.from} → ${r.period.to}`,
        `─────────────────────`,
        `▪ ${t('opening_balance')}: ${money(Math.abs(r.opening_balance))} (${sideLabel(r.opening_balance)})`,
        `▪ ${t('jr_col_debit')}: ${money(r.totals.debits)}`,
        `▪ ${t('jr_col_credit')}: ${money(r.totals.credits)}`,
        `▪ ${t('ps_net_period_change')}: ${money(Math.abs(netPeriodChange.value))} (${netPeriodNarrative.value})`,
        `▪ ${t('closing_balance')}: ${money(Math.abs(r.closing_balance))} (${sideLabel(r.closing_balance)})`,
        `─────────────────────`,
        `📌 ${balanceNarrative.value}`,
        `📊 ${t('movements_count', { count: filteredMovements.value.length })}`,
    ].filter(Boolean).join('\n');

    try {
        await navigator.clipboard.writeText(lines);
        ElMessage.success(t('ps_copied_to_clipboard'));
    } catch {
        ElMessage.info(lines);
    }
};

const exportCsv = () => {
    const r = report.value;
    if (!r) return;
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const movementsToExport = sortOrder.value === 'desc' ? [...filteredMovements.value].reverse() : filteredMovements.value;

    const lines = [
        [r.party.name, typeLabel.value, `${r.period.from} → ${r.period.to}`],
        r.party.phone ? [t('ps_phone'), r.party.phone] : [],
        r.party.address ? [t('ps_address'), r.party.address] : [],
        [],
        [t('jr_col_date'), t('ps_time'), t('ps_document'), t('jr_col_entry'), t('ps_payment_method'), t('jr_col_debit'), t('jr_col_credit'), t('running_balance'), t('ps_notes')],
        sortOrder.value === 'desc'
            ? ['', '', t('closing_balance'), '', '', Number(r.totals.debits).toFixed(2), Number(r.totals.credits).toFixed(2), Number(r.closing_balance).toFixed(2), '']
            : ['', '', t('opening_balance'), '', '', '', '', Number(r.opening_balance).toFixed(2), ''],
        ...movementsToExport.map((m) => [
            m.date,
            m.time || '',
            docLabel(m),
            m.number,
            paymentMethodInfo(m.payment_method)?.label || m.payment_method || '',
            Number(m.debit).toFixed(2),
            Number(m.credit).toFixed(2),
            Number(m.balance).toFixed(2),
            m.notes || m.reference || '',
        ]),
        sortOrder.value === 'desc'
            ? ['', '', t('opening_balance'), '', '', '', '', Number(r.opening_balance).toFixed(2), '']
            : ['', '', t('closing_balance'), '', '', Number(r.totals.debits).toFixed(2), Number(r.totals.credits).toFixed(2), Number(r.closing_balance).toFixed(2), ''],
    ].filter((l) => l.length > 0);

    const blob = new Blob(['\uFEFF' + lines.map((l) => l.map(esc).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `statement_${type.value}_${r.party.id}_${r.period.from}_${r.period.to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const printReport = () => window.print();

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
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    padding: 1.1rem 1.2rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.ps-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin: 0 0 1rem;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid var(--ov-border);
}

.head-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.head-title-wrap h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ov-text);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.head-title-wrap h2 i {
    width: 32px;
    height: 32px;
    display: inline-grid;
    place-items: center;
    border-radius: 9px;
    font-size: 0.9rem;
    color: #1d4ed8;
    background: #dbeafe;
}

.count-pill {
    padding: 0.15rem 0.65rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: var(--ov-muted);
    font-size: 0.8rem;
    font-weight: 700;
}

.count-sub-pill {
    font-size: 0.78rem;
    color: var(--ov-subtle);
    font-weight: 600;
}

.head-controls {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.movements-search {
    width: 260px;
}

.sort-segmented {
    display: inline-flex;
    padding: 3px;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid var(--ov-border);
}

.sort-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border: 0;
    border-radius: 7px;
    background: transparent;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ov-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}

.sort-btn.is-active {
    background: #fff;
    color: #1d4ed8;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
}

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
    transition: all 0.15s ease;
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
    .p-party, .p-range, .movements-search { max-width: none; width: 100%; }
}

/* ── Position / Hero Card ─────────────────────────────────────────────── */
.position {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1.2rem;
    padding: 1.25rem 1.4rem;
    margin-bottom: 1rem;
    border: 1px solid var(--ov-border);
    border-top: 4px solid var(--side);
    border-radius: var(--ov-radius);
    background: linear-gradient(180deg, color-mix(in srgb, var(--side) 5%, #fff) 0%, #fff 85%);
    box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
}

.position-id {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
    flex: 1 1 320px;
}

.party-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.party-kind {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.1rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--side);
    background: var(--side-bg);
}

.party-code-badge {
    padding: 0.08rem 0.5rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    font-family: ui-monospace, monospace;
    background: #f1f5f9;
    color: var(--ov-muted);
}

.position-id h2 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--ov-text);
    overflow-wrap: anywhere;
}

.party-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
}

.position-period {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ov-muted);
    font-variant-numeric: tabular-nums;
    background: #f8fafc;
    border: 1px solid var(--ov-border);
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
}

.party-contact-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.18rem 0.6rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #f1f5f9;
    color: var(--ov-body);
    text-decoration: none;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}

.party-contact-pill:hover {
    border-color: #cbd5e1;
    color: var(--ov-text);
}

.party-contact-pill.is-whatsapp {
    background: #dcfce7;
    color: #15803d;
}

.party-contact-pill.is-whatsapp:hover {
    background: #bbf7d0;
}

.party-contact-pill.is-location {
    background: #fef3c7;
    color: #92400e;
}

.party-contact-pill.is-company {
    background: #e0e7ff;
    color: #3730a3;
}

.position-balance {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    text-align: end;
}

.balance-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ov-muted);
}

.balance-amount {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--ov-text);
    font-variant-numeric: tabular-nums;
    unicode-bidi: isolate;
}

.stance-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.stance {
    padding: 0.12rem 0.65rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.is-owed-to-us .stance { color: #065f46; background: #d1fae5; }
.is-we-owe .stance { color: #92400e; background: #fef3c7; }
.is-settled .stance { color: var(--ov-muted); background: #f1f5f9; }

@media (max-width: 640px) {
    .position-balance { align-items: flex-start; text-align: start; }
}

/* ── Tiles ────────────────────────────────────────────────────────────── */
.tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.tile {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.85rem 1rem;
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    min-width: 0;
}

.tile span { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.tile strong { font-size: 1.18rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; unicode-bidi: isolate; }
.tile small { font-size: 0.74rem; color: var(--ov-subtle); line-height: 1.4; }
.tile.is-debit { border-top: 3px solid var(--c-debit); }
.tile.is-debit strong { color: var(--c-debit); }
.tile.is-credit { border-top: 3px solid var(--c-credit); }
.tile.is-credit strong { color: var(--c-credit); }
.tile.is-net { border-top: 3px solid #6366f1; background: #fdfefe; }
.tile.is-net strong { color: #4338ca; }
.tile.is-closing { background: #eff6ff; border-color: #bfdbfe; border-top: 3px solid #2563eb; }

/* ── Chips ────────────────────────────────────────────────────────────── */
.doc-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.85rem; }

.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.32rem 0.75rem;
    border: 1px solid var(--ov-border);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ov-body);
    cursor: pointer;
    transition: all 0.15s ease;
}

.chip:hover { border-color: var(--d, #94a3b8); }
.chip.is-active { color: var(--d, var(--ov-text)); background: var(--d-bg, #f1f5f9); border-color: var(--d, #94a3b8); }
.chip-icon { font-size: 0.76rem; color: var(--d); }
.chip-count { min-width: 1.4rem; padding: 0 0.35rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.72rem; text-align: center; }
.chip.is-active .chip-count { background: #fff; }

.filter-note { display: flex; align-items: center; gap: 0.4rem; margin: 0.75rem 0 0; font-size: 0.78rem; color: var(--ov-muted); }

/* ── Table ────────────────────────────────────────────────────────────── */
.ps-table {
    --el-table-header-bg-color: var(--ov-soft);
    --el-table-border-color: var(--ov-border);
    --el-table-row-hover-bg-color: #f5f8ff;
    font-size: 0.85rem;
}

.ps-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.78rem; }
.ps-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }
.ps-table :deep(.el-table__row.is-edge td.el-table__cell) { background: #f8fafc; }
.edge-label { color: var(--ov-text); font-weight: 800; font-size: 0.88rem; }
.edge-date { color: var(--ov-subtle); font-size: 0.78rem; font-variant-numeric: tabular-nums; }

.cell-date-time {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.cell-date {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    font-weight: 600;
    color: var(--ov-text);
}

.cell-time {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--ov-subtle);
}

.doc-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.doc-header-line {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
}

.doc-chip {
    display: inline-flex;
    align-items: center;
    padding: 0 0.55rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    line-height: 1.8;
    color: var(--d);
    background: var(--d-bg);
}

.code-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0 0.45rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.76rem;
    line-height: 1.7;
    direction: ltr;
}

.code-badge.is-clickable {
    cursor: pointer;
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #3730a3;
    transition: all 0.15s ease;
}

.code-badge.is-clickable:hover {
    background: #e0e7ff;
    border-color: #818cf8;
    color: #1e1b4b;
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.2);
}

.doc-link-icon {
    font-size: 0.65rem;
    color: #6366f1;
}

.method-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0 0.45rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1.7;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: var(--ov-muted);
}

.method-pill.m-cash { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.method-pill.m-card { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.method-pill.m-bank { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
.method-pill.m-check { background: #fffbeb; border-color: #fde68a; color: #92400e; }

.due-pill {
    display: inline-flex;
    align-items: center;
    padding: 0 0.4rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-weight: 600;
    color: #b45309;
    background: #fef3c7;
    line-height: 1.6;
}

.doc-notes-line {
    font-size: 0.76rem;
    color: var(--ov-subtle);
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    line-height: 1.4;
}

.doc-ref-text {
    font-family: ui-monospace, monospace;
    font-weight: 600;
    color: var(--ov-muted);
}

.doc-notes-text {
    color: var(--ov-body);
}

.amt {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    font-weight: 600;
    color: var(--ov-text);
    unicode-bidi: isolate;
}

strong.amt { font-weight: 800; }
.amt.is-debit { color: var(--c-debit); font-weight: 700; }
.amt.is-credit { color: var(--c-credit); font-weight: 700; }
.amt.is-zero { color: #94a3b8; font-weight: 400; }

.bal { display: inline-flex; align-items: center; gap: 0.4rem; }
.side-chip { padding: 0 0.4rem; border-radius: 5px; font-size: 0.68rem; font-weight: 800; line-height: 1.7; }
.side-chip.is-dr { color: #065f46; background: #d1fae5; }
.side-chip.is-cr { color: #92400e; background: #fef3c7; }
.side-chip.is-zero { color: var(--ov-muted); background: #f1f5f9; }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.6rem; padding: 3rem 1rem; text-align: center; }
.empty-state.is-compact { padding: 1.5rem 1rem; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; border-radius: 18px; background: #eff6ff; color: #1d4ed8; font-size: 1.5rem; }
.empty-state p { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--ov-text); }
.empty-state small { font-size: 0.82rem; color: var(--ov-muted); }

/* ── Direction & Alignment ────────────────────────────────────────────── */
.mr-1 { margin-inline-end: 0.35rem; }
.text-muted { color: var(--ov-subtle); }
.ps-table :deep(.el-table__cell) { text-align: start; }
.ps-table :deep(.el-table__cell.col-end) { text-align: end; }

/* ── Print Styling ────────────────────────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }

    .accounting-page {
        font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
        color: #000;
        background: #fff;
    }

    .ps-card, .tile, .position {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
    }

    .print-head {
        margin-bottom: 1.2rem;
        padding-bottom: 0.8rem;
        border-bottom: 2px solid #000;
    }

    .print-company-bar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.8rem;
    }

    .print-title {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 900;
        color: #000;
    }

    .print-subtitle {
        margin: 0.2rem 0 0;
        font-size: 1rem;
        font-weight: 700;
        color: #333;
    }

    .print-meta {
        font-size: 0.8rem;
        line-height: 1.5;
        text-align: end;
    }

    .print-party-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        padding: 0.6rem;
        background: #f8fafc;
        border: 1px solid #ccc;
        border-radius: 6px;
        margin-bottom: 0.8rem;
        font-size: 0.82rem;
    }

    .print-grid-item {
        display: flex;
        gap: 0.3rem;
    }

    .print-label { font-weight: 700; color: #555; }
    .print-value { color: #000; }

    .print-summary-box {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.4rem;
        margin-bottom: 0.8rem;
    }

    .print-summary-cell {
        display: flex;
        flex-direction: column;
        padding: 0.4rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        text-align: center;
        font-size: 0.76rem;
    }

    .print-summary-cell.highlight {
        background: #f1f5f9;
        font-weight: 700;
    }

    .print-summary-cell strong {
        font-size: 0.95rem;
        margin: 0.15rem 0;
    }

    .ps-table {
        border: 1px solid #ccc !important;
    }

    .ps-table :deep(th.el-table__cell),
    .ps-table :deep(td.el-table__cell) {
        border-color: #ddd !important;
        padding: 4px 6px !important;
        color: #000 !important;
    }

    .code-badge {
        border: 1px solid #999;
        background: transparent !important;
    }

    .print-signatures {
        display: flex;
        justify-content: space-between;
        margin-top: 2.5rem;
        padding-top: 1rem;
    }

    .sig-box {
        width: 28%;
        text-align: center;
    }

    .sig-title {
        margin: 0 0 2.5rem;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .sig-line {
        border-bottom: 1px dashed #666;
    }
}
</style>
