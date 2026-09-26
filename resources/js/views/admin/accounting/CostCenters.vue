<template>
    <div class="accounting-page cost-centers">
        <AdminPageHeader
            icon="fas fa-diagram-project text-primary"
            :title="$t('cost_centers')"
            :subtitle="$t('cost_centers_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag no-print">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :loading="loading" @click="reload">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" plain class="no-print" :disabled="!statement" @click="printPage">
                    <i class="fas fa-print mr-1"></i> {{ $t('ag_print') }}
                </el-button>
                <el-button type="primary" class="no-print" @click="openForm()">
                    <i class="fas fa-plus mr-1"></i> {{ $t('add_cost_center') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Period ─────────────────────────────────────────────────────── -->
        <section class="cc-card toolbar no-print">
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

        <p class="print-only print-period">{{ $t('tb_period_label', { from: statement?.period?.from || '', to: statement?.period?.to || '' }) }}</p>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <div v-if="loading && !statement" class="cc-card"><el-skeleton :rows="6" animated /></div>

        <template v-else-if="statement">
            <!-- ── The period in five figures ───────────────────────────── -->
            <section class="kpis" v-loading="loading">
                <div class="kpi">
                    <small>{{ $t('revenue') }}</small>
                    <strong>{{ money(totals.revenue) }}</strong>
                </div>
                <div class="kpi">
                    <small>{{ $t('gross_profit') }}</small>
                    <strong>{{ money(totals.gross_profit) }}</strong>
                    <span v-if="totals.margin !== null" class="kpi-sub">{{ $t('margin') }} {{ pct(totals.margin) }}</span>
                </div>
                <div class="kpi">
                    <small>{{ $t('operating_expenses') }}</small>
                    <strong>{{ money(totals.operating_expenses) }}</strong>
                </div>
                <div class="kpi" :class="totals.net_result >= 0 ? 'is-up' : 'is-down'">
                    <small>{{ $t('net_result') }}</small>
                    <strong>{{ money(totals.net_result) }}</strong>
                </div>

                <!-- How much of the period a branch can be held to. A low
                     figure means the dimension is not being captured, and every
                     per-centre figure below is that much less complete. -->
                <div v-if="statement.unattributed_share !== null" class="kpi coverage" :class="coverageClass">
                    <small>
                        {{ $t('cc_attributed') }}
                        <el-tooltip :content="$t('unattributed_share_notice', { percent: statement.unattributed_share })" placement="top">
                            <i class="far fa-circle-question"></i>
                        </el-tooltip>
                    </small>
                    <strong>{{ pct(100 - statement.unattributed_share) }}</strong>
                    <span class="meter"><span :style="{ width: (100 - statement.unattributed_share) + '%' }"></span></span>
                </div>
            </section>

            <el-alert
                v-if="statement.unattributed_share > 40"
                type="warning"
                show-icon
                :closable="false"
                class="mb-3"
                :title="$t('unattributed_share_notice', { percent: statement.unattributed_share })"
            />

            <!-- ── Result per centre ────────────────────────────────────── -->
            <section class="cc-card">
                <header class="cc-card-head">
                    <h2><i class="fas fa-chart-column"></i> {{ $t('result_per_center') }}</h2>
                    <span class="muted small">{{ $t('cc_expand_hint') }}</span>
                </header>

                <el-table
                    v-if="rows.length"
                    ref="resultTable"
                    :data="rows"
                    row-key="key"
                    :row-class-name="resultRowClass"
                    show-summary
                    :summary-method="summaryRow"
                    style="width: 100%"
                    @row-click="(row) => resultTable?.toggleRowExpansion(row)"
                >
                    <el-table-column type="expand" width="36">
                        <template #default="{ row }">
                            <div class="breakdown">
                                <div v-for="section in breakdown(row)" :key="section.key" class="b-section">
                                    <h4>
                                        {{ section.label }}
                                        <span class="num">{{ plain(section.total) }}</span>
                                    </h4>
                                    <div v-for="a in section.accounts" :key="a.id" class="b-row">
                                        <span class="code-badge">{{ a.code }}</span>
                                        <span class="b-name">{{ a.name }}</span>
                                        <span class="b-bar"><span :style="{ width: sectionShare(a, section) + '%' }" :class="'is-' + section.key"></span></span>
                                        <span class="num b-amt" :class="{ 'is-neg': a.amount < 0 }">{{ plain(a.amount) }}</span>
                                    </div>
                                </div>
                                <p v-if="!row.accounts?.length" class="muted small">{{ $t('no_activity_in_this_period') }}</p>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('the_center')" min-width="200">
                        <template #default="{ row }">
                            <div class="center-cell">
                                <span class="center-name" :class="{ 'is-residue': row.id === null }">
                                    <i v-if="row.id === null" class="fas fa-layer-group"></i>
                                    {{ row.id === null ? $t('cc_unattributed') : row.name }}
                                </span>
                                <span class="center-meta">
                                    <span v-if="row.code" class="code-badge">{{ row.code }}</span>
                                    <span v-if="row.is_active === false" class="mini-tag is-muted">{{ $t('inactive') }}</span>
                                    <span v-if="row.id === null" class="muted small">{{ $t('cc_unattributed_hint') }}</span>
                                </span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('revenue')" min-width="150" align="right" header-align="right">
                        <template #default="{ row }">
                            <span class="num">{{ plain(row.revenue) }}</span>
                            <span v-if="row.revenue > 0 && totals.revenue > 0" class="share">
                                <span class="share-bar"><span :style="{ width: revenueShare(row) + '%' }"></span></span>
                                <small>{{ pct(revenueShare(row)) }}</small>
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('cost_of_sales')" min-width="130" align="right" header-align="right">
                        <template #default="{ row }"><span class="num muted-num">{{ plain(row.cost_of_sales) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('gross_profit')" min-width="130" align="right" header-align="right">
                        <template #default="{ row }"><strong class="num">{{ plain(row.gross_profit) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('margin')" width="100" align="center">
                        <template #default="{ row }">
                            <span v-if="row.margin_percentage !== null" class="margin-pill" :class="marginClass(row.margin_percentage)">
                                {{ pct(row.margin_percentage) }}
                            </span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('operating_expenses')" min-width="140" align="right" header-align="right">
                        <template #default="{ row }"><span class="num muted-num">{{ plain(row.operating_expenses) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('net_result')" min-width="140" align="right" header-align="right">
                        <template #default="{ row }">
                            <strong class="num" :class="row.net_result >= 0 ? 'is-up' : 'is-down'">{{ plain(row.net_result) }}</strong>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_activity_in_this_period')" />
            </section>

            <!-- ── Who carried the result ───────────────────────────────── -->
            <section v-if="rows.length > 1" class="cc-card">
                <header class="cc-card-head">
                    <h2><i class="fas fa-scale-unbalanced"></i> {{ $t('cc_contribution') }}</h2>
                    <span class="legend">
                        <span><i class="dot is-up"></i> {{ $t('cc_profit') }}</span>
                        <span><i class="dot is-down"></i> {{ $t('cc_loss') }}</span>
                    </span>
                </header>

                <div class="contrib">
                    <div v-for="row in contributionRows" :key="row.key" class="c-row">
                        <span class="c-name" :class="{ 'is-residue': row.id === null }">{{ row.id === null ? $t('cc_unattributed') : row.name }}</span>
                        <span class="c-track">
                            <span class="c-half is-neg"><span v-if="row.net_result < 0" class="c-bar is-down" :style="{ width: netWidth(row) }"></span></span>
                            <span class="c-axis"></span>
                            <span class="c-half is-pos"><span v-if="row.net_result >= 0" class="c-bar is-up" :style="{ width: netWidth(row) }"></span></span>
                        </span>
                        <strong class="num c-amt" :class="row.net_result >= 0 ? 'is-up' : 'is-down'">{{ plain(row.net_result) }}</strong>
                    </div>
                </div>
            </section>
        </template>

        <!-- ── The centres themselves ───────────────────────────────────── -->
        <section class="cc-card no-print">
            <header class="cc-card-head">
                <h2><i class="fas fa-sitemap"></i> {{ $t('the_centers') }}
                    <span class="count">{{ formatNumber(centers.length) }}</span>
                </h2>
                <div class="toolbar-inline">
                    <el-input v-model="search" clearable class="t-search" :placeholder="$t('cc_search_placeholder')">
                        <template #prefix><i class="fas fa-search"></i></template>
                    </el-input>
                    <el-switch v-if="inactiveCount" v-model="showInactive" :active-text="$t('cc_show_inactive', { n: formatNumber(inactiveCount) })" />
                </div>
            </header>

            <!-- A warehouse with no centre sends every sale and cost of sale
                 from it to "unattributed". Offer to fix it where it is noticed. -->
            <div v-if="availableWarehouses.length" class="unlinked">
                <span class="unlinked-text">
                    <i class="fas fa-warehouse"></i>
                    {{ $t('cc_unlinked_warehouses', { n: formatNumber(availableWarehouses.length) }) }}
                </span>
                <span class="unlinked-actions">
                    <el-button
                        v-for="w in availableWarehouses.slice(0, 6)"
                        :key="w.id"
                        size="small"
                        @click="openForm(null, w)"
                    >
                        <i class="fas fa-plus mr-1"></i> {{ w.name }}
                    </el-button>
                </span>
            </div>

            <el-table v-if="visibleCenters.length" :data="visibleCenters" :row-class-name="({ row }) => (row.is_active ? '' : 'is-inactive')" style="width: 100%">
                <el-table-column :label="$t('cc_code')" width="120">
                    <template #default="{ row }"><span class="code-badge">{{ row.code }}</span></template>
                </el-table-column>
                <el-table-column :label="$t('name')" min-width="200">
                    <template #default="{ row }">
                        <span class="center-name">{{ row.name }}</span>
                        <small v-if="row.notes" class="row-notes">{{ row.notes }}</small>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('linked_warehouse')" min-width="170">
                    <template #default="{ row }">
                        <span v-if="row.warehouse" class="link-chip is-wh"><i class="fas fa-warehouse"></i> {{ row.warehouse.name }}</span>
                        <!-- A centre with no warehouse cannot be attributed to
                             automatically; it carries what is posted to it by
                             hand, which is what overheads are. -->
                        <el-tooltip v-else :content="$t('center_without_warehouse_hint')" placement="top">
                            <span class="link-chip is-overhead"><i class="fas fa-building"></i> {{ $t('overhead_center') }}</span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('entry_lines')" width="110" align="center">
                    <template #default="{ row }"><span class="num">{{ formatNumber(row.journal_entry_lines_count) }}</span></template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="130" align="center">
                    <template #default="{ row }">
                        <el-tooltip :content="row.is_active ? $t('cc_deactivate_hint') : $t('cc_activate_hint')" placement="top">
                            <el-switch
                                :model-value="row.is_active"
                                :loading="togglingId === row.id"
                                inline-prompt
                                :active-text="$t('active')"
                                :inactive-text="$t('inactive')"
                                @change="toggleActive(row)"
                            />
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column width="110" align="center">
                    <template #default="{ row }">
                        <div class="row-actions">
                            <el-button size="small" text :aria-label="$t('edit_cost_center')" @click="openForm(row)">
                                <i class="fas fa-pen"></i>
                            </el-button>
                            <el-tooltip :disabled="!row.journal_entry_lines_count" :content="$t('cc_cannot_delete_used')" placement="top">
                                <span>
                                    <el-button
                                        size="small"
                                        text
                                        type="danger"
                                        :aria-label="$t('delete')"
                                        :disabled="row.journal_entry_lines_count > 0"
                                        @click="remove(row)"
                                    ><i class="fas fa-trash"></i></el-button>
                                </span>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-else :description="centers.length ? $t('cc_no_match') : $t('no_cost_centers_yet')">
                <el-button v-if="!centers.length" type="primary" @click="openForm()">{{ $t('add_cost_center') }}</el-button>
            </el-empty>
        </section>

        <!-- ── Add / edit ───────────────────────────────────────────────── -->
        <el-dialog
            v-model="formVisible"
            :title="editingId ? $t('edit_cost_center') : $t('add_cost_center')"
            width="500px"
            destroy-on-close
        >
            <el-form :model="form" label-position="top" @submit.prevent="submit">
                <el-form-item :label="$t('linked_warehouse')" :error="fieldErrors.warehouse_id">
                    <el-select
                        v-model="form.warehouse_id"
                        clearable
                        filterable
                        style="width: 100%"
                        :placeholder="$t('cc_no_warehouse_overhead')"
                        @change="onWarehousePicked"
                    >
                        <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                    <small class="hint">{{ $t('linked_warehouse_hint') }}</small>
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :xs="24" :sm="9">
                        <el-form-item :label="$t('cc_code')" required :error="fieldErrors.code">
                            <el-input v-model="form.code" maxlength="30" class="code-input" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="15">
                        <el-form-item :label="$t('name')" required :error="fieldErrors.name">
                            <el-input v-model="form.name" maxlength="255" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item v-if="editingId" :label="$t('status')">
                    <el-switch v-model="form.is_active" :active-text="$t('active')" :inactive-text="$t('inactive')" />
                    <small v-if="!form.is_active" class="hint">{{ $t('cc_deactivate_hint') }}</small>
                </el-form-item>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="formVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { costCentersApi } from '@/api/costCenters';
import { accountingReportsApi } from '@/api/accountingReports';
import { formatMoney, formatNumber, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const pct = (value) => `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 1 }).format(Number(value || 0))}%`;

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

const loading = ref(false);
const error = ref('');

/* ------------------------------------------------------------------ *
 * Period — the screen used to open on an empty picker that silently
 * meant "since 1 January"; now the range is named and kept in the URL.
 * ------------------------------------------------------------------ */

const preset = ref('this_year');
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
        case 'this_quarter': return [localDate(new Date(y, q, 1)), todayIso];
        case 'last_quarter': return [localDate(new Date(y, q - 3, 1)), localDate(new Date(y, q, 0))];
        default: return [`${y}-01-01`, todayIso];
    }
};

const detectPreset = ([from, to]) => ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year']
    .find((p) => { const [f, tt] = presetRange(p); return f === from && tt === to; }) || 'custom';

const applyPreset = (p) => {
    if (p === 'custom') return;
    range.value = presetRange(p);
    loadStatement();
};

const onRangePicked = () => {
    preset.value = detectPreset(range.value);
    loadStatement();
};

/* ------------------------------------------------------------------ *
 * Statement
 * ------------------------------------------------------------------ */

const statement = ref(null);
const resultTable = ref(null);

const loadStatement = async () => {
    loading.value = true;
    error.value = '';
    router.replace({ query: preset.value === 'this_year' ? {} : { date_from: range.value[0], date_to: range.value[1] } });
    try {
        const res = await accountingReportsApi.costCenterStatement({ date_from: range.value[0], date_to: range.value[1] });
        statement.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const rows = computed(() => (statement.value?.centers || []).map((c) => ({ ...c, key: c.id ?? 'none' })));

const totals = computed(() => {
    const tt = statement.value?.totals || {};
    const revenue = Number(tt.revenue || 0);
    const gross = revenue - Number(tt.cost_of_sales || 0);
    return {
        revenue,
        cost_of_sales: Number(tt.cost_of_sales || 0),
        gross_profit: gross,
        operating_expenses: Number(tt.operating_expenses || 0),
        net_result: Number(tt.net_result || 0),
        margin: Math.abs(revenue) > 0.005 ? (gross / revenue) * 100 : null,
    };
});

const coverageClass = computed(() => {
    const share = Number(statement.value?.unattributed_share || 0);
    return share > 40 ? 'is-bad' : share > 15 ? 'is-warn' : 'is-ok';
});

const revenueShare = (row) => (totals.value.revenue > 0 ? Math.max(0, (row.revenue / totals.value.revenue) * 100) : 0);

const marginClass = (m) => (m < 0 ? 'is-bad' : m < 15 ? 'is-warn' : 'is-ok');

const resultRowClass = ({ row }) => [
    'is-clickable',
    row.id === null ? 'is-residue' : '',
    row.is_active === false ? 'is-inactive' : '',
].join(' ');

const summaryRow = ({ columns }) => columns.map((col, i) => {
    if (i === 1) return t('total');
    const map = { 2: totals.value.revenue, 3: totals.value.cost_of_sales, 4: totals.value.gross_profit, 6: totals.value.operating_expenses, 7: totals.value.net_result };
    if (i === 5) return totals.value.margin === null ? '—' : pct(totals.value.margin);
    return i in map ? plain(map[i]) : '';
});

const breakdown = (row) => {
    const sections = [
        { key: 'revenue', label: t('revenue') },
        { key: 'cost_of_sales', label: t('cost_of_sales') },
        { key: 'operating_expenses', label: t('operating_expenses') },
    ];
    return sections
        .map((s) => {
            const accounts = (row.accounts || []).filter((a) => a.section === s.key);
            return { ...s, accounts, total: accounts.reduce((sum, a) => sum + Number(a.amount), 0) };
        })
        .filter((s) => s.accounts.length);
};

const sectionShare = (account, section) => {
    const max = Math.max(...section.accounts.map((a) => Math.abs(a.amount)), 0.01);
    return Math.round((Math.abs(account.amount) / max) * 100);
};

const contributionRows = computed(() => [...rows.value].sort((a, b) => b.net_result - a.net_result));
const netMax = computed(() => Math.max(0.01, ...rows.value.map((r) => Math.abs(r.net_result))));
const netWidth = (row) => `${Math.max(1, Math.round((Math.abs(row.net_result) / netMax.value) * 100))}%`;

/* ------------------------------------------------------------------ *
 * Centres
 * ------------------------------------------------------------------ */

const centers = ref([]);
const availableWarehouses = ref([]);
const search = ref('');
const showInactive = ref(false);

const inactiveCount = computed(() => centers.value.filter((c) => !c.is_active).length);

const visibleCenters = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return centers.value.filter((c) => {
        if (!c.is_active && !showInactive.value && !needle) return false;
        if (!needle) return true;
        return [c.code, c.name, c.warehouse?.name, c.notes].some((v) => String(v || '').toLowerCase().includes(needle));
    });
});

const loadCenters = async () => {
    const res = await costCentersApi.getAll();
    const data = res.data?.data || {};
    centers.value = data.centers || [];
    availableWarehouses.value = data.available_warehouses || [];
};

const reload = async () => {
    try {
        await Promise.all([loadCenters(), loadStatement()]);
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    }
};

const togglingId = ref(null);

// Deactivating keeps every figure the centre already carries and stops it
// claiming new postings — the safe alternative to deleting one that was used.
const toggleActive = async (row) => {
    togglingId.value = row.id;
    try {
        await costCentersApi.update(row.id, {
            code: row.code,
            name: row.name,
            warehouse_id: row.warehouse_id || null,
            notes: row.notes || null,
            is_active: !row.is_active,
        });
        row.is_active = !row.is_active;
        ElMessage.success(t('cost_center_saved'));
        loadStatement();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_cost_center'));
    } finally {
        togglingId.value = null;
    }
};

/* ------------------------------------------------------------------ *
 * Form
 * ------------------------------------------------------------------ */

const formVisible = ref(false);
const editingId = ref(null);
const saving = ref(false);
const fieldErrors = reactive({ code: '', name: '', warehouse_id: '' });
const form = reactive({ code: '', name: '', warehouse_id: null, is_active: true, notes: '' });

const canSubmit = computed(() => form.code.trim() && form.name.trim());

// A warehouse belongs to one centre only, so the list offers the free ones —
// plus the one this centre already holds, which would otherwise vanish from
// its own edit form.
const warehouseOptions = computed(() => {
    const options = [...availableWarehouses.value];
    const current = centers.value.find((c) => c.id === editingId.value);

    if (current?.warehouse && !options.some((w) => w.id === current.warehouse.id)) {
        options.unshift(current.warehouse);
    }

    return options;
});

// The next free code in the CC-### series the migration started.
const suggestCode = () => {
    const taken = new Set(centers.value.map((c) => String(c.code).toUpperCase()));
    let n = Math.max(0, ...centers.value.map((c) => Number(String(c.code).match(/(\d+)\s*$/)?.[1] || 0))) + 1;
    while (taken.has(`CC-${String(n).padStart(3, '0')}`)) n += 1;
    return `CC-${String(n).padStart(3, '0')}`;
};

const clearErrors = () => Object.assign(fieldErrors, { code: '', name: '', warehouse_id: '' });

const openForm = (center = null, warehouse = null) => {
    clearErrors();
    editingId.value = center?.id ?? null;
    form.code = center?.code ?? suggestCode();
    form.name = center?.name ?? warehouse?.name ?? '';
    form.warehouse_id = center?.warehouse_id ?? warehouse?.id ?? null;
    form.is_active = center ? Boolean(center.is_active) : true;
    form.notes = center?.notes ?? '';
    formVisible.value = true;
};

// Naming a new centre after the warehouse it stands for is nearly always right.
const onWarehousePicked = (id) => {
    const warehouse = warehouseOptions.value.find((w) => w.id === id);
    if (!editingId.value && warehouse && !form.name.trim()) form.name = warehouse.name;
};

const submit = async () => {
    if (!canSubmit.value) return;
    clearErrors();
    saving.value = true;
    try {
        const payload = {
            code: form.code.trim(),
            name: form.name.trim(),
            warehouse_id: form.warehouse_id || null,
            notes: form.notes || null,
        };

        if (editingId.value) {
            await costCentersApi.update(editingId.value, { ...payload, is_active: form.is_active });
        } else {
            await costCentersApi.create(payload);
        }

        formVisible.value = false;
        ElMessage.success(t('cost_center_saved'));
        await reload();
    } catch (e) {
        const errors = e.response?.data?.errors;
        if (errors) {
            Object.keys(fieldErrors).forEach((k) => { fieldErrors[k] = errors[k]?.[0] || ''; });
        } else {
            ElMessage.error(e.response?.data?.message || t('failed_to_save_cost_center'));
        }
    } finally {
        saving.value = false;
    }
};

const remove = async (center) => {
    try {
        await ElMessageBox.confirm(t('confirm_delete_cost_center'), t('confirm_deletion'), {
            type: 'warning',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
            confirmButtonClass: 'el-button--danger',
        });
    } catch {
        return;
    }

    try {
        await costCentersApi.remove(center.id);
        ElMessage.success(t('cost_center_deleted'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_cost_center'));
    }
};

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
    --c-up: #047857;
    --c-down: #b91c1c;
    --c-warn: #92400e;
    color: var(--ov-body);
}

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.print-only { display: none; }
.mb-3 { margin-bottom: 1rem; }
.mr-1 { margin-inline-end: 0.35rem; }
.muted { color: var(--ov-subtle); }
.small { font-size: 0.78rem; }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; unicode-bidi: isolate; }
.muted-num { color: var(--ov-muted); }
.is-up { color: var(--c-up); }
.is-down { color: var(--c-down); }

.cc-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.cc-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.cc-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.cc-card-head h2 > i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }
.count { padding: 0 0.5rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.75rem; font-weight: 700; }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem; }
.t-range { max-width: 300px; }
.toolbar-inline { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.t-search { width: 230px; }

@media (max-width: 640px) {
    .t-range, .t-search { max-width: none; width: 100%; }
}

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
    unicode-bidi: isolate;
}

.mini-tag { display: inline-flex; align-items: center; padding: 0 0.45rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
.mini-tag.is-muted { color: var(--ov-muted); background: #f1f5f9; }

/* ── KPIs ─────────────────────────────────────────────────────────────── */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.85rem 1rem; background: var(--ov-surface); border: 1px solid var(--ov-border); border-radius: 12px; min-width: 0; }
.kpi small { font-size: 0.76rem; font-weight: 700; color: var(--ov-muted); display: flex; align-items: center; gap: 0.3rem; }
.kpi strong { font-size: 1.25rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kpi.is-up strong { color: var(--c-up); }
.kpi.is-down strong { color: var(--c-down); }
.kpi-sub { font-size: 0.74rem; font-weight: 600; color: var(--ov-subtle); }
.coverage .meter { display: block; height: 6px; margin-top: 0.2rem; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.coverage .meter span { display: block; height: 100%; border-radius: inherit; }
.coverage.is-ok .meter span { background: #10b981; }
.coverage.is-warn .meter span { background: #f59e0b; }
.coverage.is-bad .meter span { background: #ef4444; }
.coverage.is-bad strong { color: var(--c-down); }

/* ── Result table ─────────────────────────────────────────────────────── */
.center-cell { display: flex; flex-direction: column; gap: 0.15rem; }
.center-name { font-weight: 700; color: var(--ov-text); }
.center-name.is-residue { color: var(--ov-muted); font-style: italic; }
.center-name i { margin-inline-end: 0.3rem; }
.center-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }

.share { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; margin-top: 0.2rem; }
.share small { font-size: 0.7rem; color: var(--ov-subtle); font-weight: 600; min-width: 2.6rem; text-align: end; }
.share-bar { display: inline-block; width: 70px; height: 5px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.share-bar span { display: block; height: 100%; background: #3b82f6; border-radius: inherit; }

.margin-pill { display: inline-block; padding: 0.05rem 0.55rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.margin-pill.is-ok { color: #065f46; background: #d1fae5; }
.margin-pill.is-warn { color: var(--c-warn); background: #fef3c7; }
.margin-pill.is-bad { color: #991b1b; background: #fee2e2; }

:deep(.el-table .is-clickable) { cursor: pointer; }
:deep(.el-table .is-residue > td.el-table__cell) { background: var(--ov-soft); }
:deep(.el-table .is-inactive > td.el-table__cell) { opacity: 0.7; }
:deep(.el-table__footer-wrapper td.el-table__cell) { font-weight: 800; color: var(--ov-text); background: #f1f5f9; font-variant-numeric: tabular-nums; }

.breakdown { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem 1.5rem; padding: 0.4rem 1rem 0.8rem; }
.b-section h4 { display: flex; justify-content: space-between; margin: 0 0 0.4rem; padding-bottom: 0.3rem; border-bottom: 1px solid var(--ov-border); font-size: 0.8rem; font-weight: 800; color: var(--ov-text); }
.b-row { display: grid; grid-template-columns: auto minmax(0, 1fr) 60px auto; align-items: center; gap: 0.5rem; padding: 0.25rem 0; font-size: 0.8rem; }
.b-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ov-body); }
.b-bar { height: 5px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.b-bar span { display: block; height: 100%; border-radius: inherit; }
.b-bar .is-revenue { background: #3b82f6; }
.b-bar .is-cost_of_sales { background: #f59e0b; }
.b-bar .is-operating_expenses { background: #a855f7; }
.b-amt { font-weight: 700; color: var(--ov-text); text-align: end; }
.b-amt.is-neg { color: var(--c-down); }

/* ── Contribution ─────────────────────────────────────────────────────── */
.legend { display: flex; gap: 1rem; font-size: 0.78rem; font-weight: 600; color: var(--ov-muted); }
.dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-inline-end: 0.3rem; }
.dot.is-up, .c-bar.is-up { background: #10b981; }
.dot.is-down, .c-bar.is-down { background: #ef4444; }

.contrib { display: flex; flex-direction: column; gap: 0.35rem; }
.c-row { display: grid; grid-template-columns: minmax(110px, 180px) minmax(0, 1fr) 130px; align-items: center; gap: 0.75rem; font-size: 0.84rem; }
.c-name { font-weight: 700; color: var(--ov-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-name.is-residue { color: var(--ov-muted); font-style: italic; }
.c-track { display: grid; grid-template-columns: 1fr 2px 1fr; align-items: center; height: 14px; }
.c-half { display: flex; height: 100%; }
.c-half.is-neg { justify-content: flex-end; }
.c-axis { height: 100%; background: #cbd5e1; }
.c-bar { display: block; height: 100%; border-radius: 3px; }
.c-amt { text-align: end; }

@media (max-width: 640px) {
    .c-row { grid-template-columns: minmax(0, 1fr) 110px; }
    .c-track { grid-column: 1 / -1; grid-row: 2; }
}

/* ── Centres ──────────────────────────────────────────────────────────── */
.unlinked { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; padding: 0.65rem 0.85rem; margin-bottom: 0.9rem; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; }
.unlinked-text { font-size: 0.84rem; font-weight: 700; color: var(--c-warn); }
.unlinked-text i { margin-inline-end: 0.35rem; }
.unlinked-actions { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.unlinked-actions .el-button + .el-button { margin-inline-start: 0; }

.row-notes { display: block; font-size: 0.74rem; color: var(--ov-subtle); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.link-chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.05rem 0.6rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; }
.link-chip.is-wh { color: #0f766e; background: #ccfbf1; }
.link-chip.is-overhead { color: #6b21a8; background: #f3e8ff; cursor: help; }
.row-actions { display: inline-flex; align-items: center; gap: 0.1rem; }
.row-actions .el-button + .el-button, .row-actions span .el-button { margin-inline-start: 0; }

.hint { display: block; margin-top: 0.35rem; color: var(--ov-muted); font-size: 0.78rem; line-height: 1.5; }
.code-input :deep(input) { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    .print-only { display: block; }
    .print-period { margin: 0 0 0.75rem; font-weight: 700; color: #000; }
    .cc-card, .kpi { box-shadow: none; break-inside: avoid-page; }
    .c-bar, .share-bar span, .meter span { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
