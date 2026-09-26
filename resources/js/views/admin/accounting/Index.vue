<template>
    <div class="accounting-page accounting-index">
        <AdminPageHeader
            icon="fas fa-calculator text-primary"
            :title="$t('accounting_overview')"
            :subtitle="$t('accounting_index_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button :loading="refreshing" @click="refresh">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" @click="$router.push({ path: '/admin/accounting/journal', query: { new: 1 } })">
                    <i class="fas fa-plus mr-1"></i> {{ $t('acc_ov_new_entry') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Conditions that change what the reader should do today, ahead of
             every figure: a closed period refuses new documents, and books that
             do not balance make every number below suspect. -->
        <el-alert
            v-if="todayIsClosed"
            type="warning"
            show-icon
            :closable="false"
            class="ov-alert"
            :title="$t('acc_ov_today_closed')"
        >
            <router-link to="/admin/accounting/periods" class="ov-alert-link">{{ $t('accounting_periods') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
        </el-alert>

        <el-alert
            v-if="position && !position.is_balanced"
            type="error"
            show-icon
            :closable="false"
            class="ov-alert"
            :title="$t('acc_ov_books_unbalanced', { amount: formatMoney(Math.abs(position.difference)) })"
        >
            <router-link to="/admin/accounting/trial-balance" class="ov-alert-link">{{ $t('trial_balance') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
        </el-alert>

        <el-alert
            v-if="loadErrors.length"
            type="error"
            show-icon
            :closable="false"
            class="ov-alert"
            :title="$t('acc_ov_partial_load')"
        >
            <el-button size="small" type="danger" plain @click="refresh">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ── KPIs ───────────────────────────────────────────────────────── -->
        <div class="kpi-toolbar">
            <span class="kpi-toolbar-label">
                {{ $t('acc_ov_position_as_of', { date: todayLabel }) }}
            </span>
            <el-segmented v-model="plRange" :options="rangeOptions" size="small" @change="loadIncome" />
        </div>

        <div class="kpi-grid">
            <template v-if="positionLoading && !position">
                <div v-for="n in 5" :key="n" class="kpi-card"><el-skeleton :rows="2" animated /></div>
            </template>
            <template v-else>
                <router-link to="/admin/accounting/balance-sheet" class="kpi-card kpi-assets">
                    <span class="kpi-icon"><i class="fas fa-coins"></i></span>
                    <span class="kpi-label">{{ $t('total_assets_label') }}</span>
                    <strong class="kpi-value">{{ formatMoney(position?.assets?.total) }}</strong>
                    <span class="kpi-sub">{{ $t('acc_ov_accounts_count', { count: position?.assets?.accounts?.length || 0 }) }}</span>
                </router-link>

                <router-link to="/admin/accounting/balance-sheet" class="kpi-card kpi-liabilities">
                    <span class="kpi-icon"><i class="fas fa-hand-holding-usd"></i></span>
                    <span class="kpi-label">{{ $t('total_liabilities_label') }}</span>
                    <strong class="kpi-value">{{ formatMoney(position?.liabilities?.total) }}</strong>
                    <span class="kpi-sub">{{ $t('acc_ov_accounts_count', { count: position?.liabilities?.accounts?.length || 0 }) }}</span>
                </router-link>

                <router-link to="/admin/accounting/balance-sheet" class="kpi-card kpi-equity">
                    <span class="kpi-icon"><i class="fas fa-shield-alt"></i></span>
                    <span class="kpi-label">{{ $t('type_equity') }}</span>
                    <strong class="kpi-value" :class="{ 'is-negative': (position?.equity?.total || 0) < 0 }">
                        {{ formatMoney(position?.equity?.total) }}
                    </strong>
                    <span class="kpi-sub">
                        {{ $t('acc_ov_incl_current_result', { amount: formatMoney(position?.equity?.current_period_result) }) }}
                    </span>
                </router-link>

                <router-link :to="incomeLink" class="kpi-card kpi-revenue">
                    <span class="kpi-icon"><i class="fas fa-arrow-trend-up"></i></span>
                    <span class="kpi-label">{{ $t('acc_ov_net_revenue') }} · {{ rangeLabel }}</span>
                    <strong class="kpi-value">
                        <el-skeleton-item v-if="incomeLoading && !income" variant="text" style="width: 60%" />
                        <template v-else>{{ formatMoney(income?.net_revenue) }}</template>
                    </strong>
                    <span v-if="revenueChange !== null" class="kpi-sub kpi-delta" :class="revenueChange >= 0 ? 'is-up' : 'is-down'">
                        <i class="fas" :class="revenueChange >= 0 ? 'fa-caret-up' : 'fa-caret-down'"></i>
                        {{ formatPct(Math.abs(revenueChange)) }} {{ $t('acc_ov_vs_previous') }}
                    </span>
                    <span v-else class="kpi-sub">{{ $t('acc_ov_no_comparison') }}</span>
                </router-link>

                <router-link :to="incomeLink" class="kpi-card" :class="(income?.net_income || 0) >= 0 ? 'kpi-profit' : 'kpi-loss'">
                    <span class="kpi-icon"><i class="fas fa-sack-dollar"></i></span>
                    <span class="kpi-label">
                        {{ (income?.net_income || 0) >= 0 ? $t('acc_ov_net_profit') : $t('acc_ov_net_loss') }} · {{ rangeLabel }}
                    </span>
                    <strong class="kpi-value" :class="{ 'is-negative': (income?.net_income || 0) < 0 }">
                        <el-skeleton-item v-if="incomeLoading && !income" variant="text" style="width: 60%" />
                        <template v-else>{{ formatMoney(income?.net_income) }}</template>
                    </strong>
                    <span class="kpi-sub">
                        <template v-if="income?.net_margin_pct !== null && income?.net_margin_pct !== undefined">
                            {{ $t('acc_ov_net_margin', { pct: formatPct(income.net_margin_pct) }) }}
                        </template>
                        <template v-else>{{ $t('acc_ov_no_revenue_yet') }}</template>
                    </span>
                </router-link>
            </template>
        </div>

        <div class="ov-grid">
            <!-- ── Main column ─────────────────────────────────────────────── -->
            <div class="ov-main">
                <!-- Cross-module consistency. Each module could already answer
                     for itself; nothing asked the question across the system,
                     so a single order whose invoice never reached the ledger
                     stayed invisible until somebody happened to open it. -->
                <section class="ov-card health-panel" v-loading="healthLoading">
                    <header class="ov-card-head">
                        <h2><i class="fas fa-heart-pulse"></i> {{ $t('system_integrity_checks') }}</h2>
                        <div class="ov-card-head-end">
                            <span v-if="health.checked_at" class="muted-sm">{{ $t('acc_ov_last_checked', { time: health.checked_at }) }}</span>
                            <el-button text size="small" :loading="healthLoading" @click="loadHealth">
                                <i class="fas fa-sync-alt mr-1"></i> {{ $t('run_checks_again') }}
                            </el-button>
                        </div>
                    </header>

                    <div v-if="healthError" class="health-banner is-unknown">
                        <i class="fas fa-circle-question"></i>
                        <span>{{ $t('acc_ov_health_failed') }}</span>
                    </div>
                    <div v-else-if="health.is_healthy" class="health-banner is-clear">
                        <i class="fas fa-circle-check"></i>
                        <span>{{ $t('all_checks_passed') }}</span>
                        <span class="health-banner-count">{{ passingChecks.length }}/{{ health.checks?.length || 0 }}</span>
                    </div>
                    <div v-else class="health-banner is-bad">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>
                            <strong>{{ health.issue_count }}</strong> {{ $t('checks_needing_attention') }}
                            <strong>{{ health.affected_records }}</strong> {{ $t('records_suffix') }}
                        </span>
                    </div>

                    <div v-if="failingChecks.length" class="health-list">
                        <article v-for="check in failingChecks" :key="check.code" class="health-check">
                            <div class="check-head">
                                <i class="fas fa-circle-exclamation"></i>
                                <span class="check-title">{{ check.title }}</span>
                                <span class="check-count">{{ check.count }}</span>
                            </div>
                            <p class="check-detail">{{ check.detail }}</p>
                            <p class="check-action"><i class="fas fa-arrow-turn-down"></i> {{ check.action }}</p>
                            <!-- The one finding that can be cleared without
                                 leaving the page. Everything else here still
                                 points at the screen that owns the repair. -->
                            <el-button
                                v-if="check.code === 'products_without_cost'"
                                class="check-fix"
                                size="small"
                                type="primary"
                                plain
                                @click="openPricingDialog"
                            >
                                <i class="fas fa-tags mr-1"></i>
                                {{ $t('price_stocked_items_now') }}
                            </el-button>
                        </article>
                    </div>

                    <!-- A passing check is confirmation, not work: folded away
                         so the failing ones are the first thing read. -->
                    <div v-if="passingChecks.length && !health.is_healthy" class="passed-toggle">
                        <el-button text size="small" @click="showPassed = !showPassed">
                            <i class="fas mr-1" :class="showPassed ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            {{ $t('acc_ov_passed_checks', { count: passingChecks.length }) }}
                        </el-button>
                    </div>
                    <ul v-if="passingChecks.length && (showPassed || health.is_healthy)" class="passed-list">
                        <li v-for="check in passingChecks" :key="check.code">
                            <i class="fas fa-check"></i> {{ check.title }}
                        </li>
                    </ul>
                </section>

                <section class="ov-card">
                    <header class="ov-card-head">
                        <h2><i class="fas fa-history"></i> {{ $t('latest_journal_entries') }}</h2>
                        <router-link to="/admin/accounting/journal" class="head-link">
                            {{ $t('acc_ov_view_all_entries', { count: formatNumber(entriesTotal) }) }}
                        </router-link>
                    </header>

                    <el-skeleton v-if="entriesLoading && !entries.length" :rows="5" animated />
                    <el-empty v-else-if="!entries.length" :image-size="70" :description="$t('no_journal_entries_yet')">
                        <el-button type="primary" plain @click="$router.push({ path: '/admin/accounting/journal', query: { new: 1 } })">
                            {{ $t('acc_ov_new_entry') }}
                        </el-button>
                    </el-empty>
                    <el-table v-else :data="entries" size="small" row-key="id" class="entries-table">
                        <!-- The lines are already in the payload; opening a row
                             answers "which accounts?" without leaving the page. -->
                        <el-table-column type="expand" width="36">
                            <template #default="{ row }">
                                <div class="entry-lines">
                                    <div v-for="line in row.lines || []" :key="line.id" class="entry-line">
                                        <span class="line-account">
                                            <span class="line-code">{{ line.ledger_account?.code }}</span>
                                            {{ line.ledger_account?.name || '—' }}
                                        </span>
                                        <span class="line-amt is-debit">{{ Number(line.debit) ? formatMoney(line.debit) : '' }}</span>
                                        <span class="line-amt is-credit">{{ Number(line.credit) ? formatMoney(line.credit) : '' }}</span>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('entry_number')" width="130">
                            <template #default="{ row }">
                                <span class="entry-no">{{ row.entry_number }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('date')" width="105">
                            <template #default="{ row }">{{ formatDate(row.entry_date) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('narration_description')" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }">
                                {{ row.description || '—' }}
                                <el-tag v-if="row.status === 'reversed'" size="small" type="info" effect="plain" class="ml-1">{{ $t('reversed') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('acc_ov_amount')" width="130" align="end">
                            <template #default="{ row }">
                                <span class="entry-amt">{{ formatMoney(row.total_debit) }}</span>
                                <i
                                    v-if="!isRowBalanced(row)"
                                    class="fas fa-triangle-exclamation text-danger ml-1"
                                    :title="$t('acc_ov_entry_unbalanced')"
                                ></i>
                            </template>
                        </el-table-column>
                    </el-table>
                </section>
            </div>

            <!-- ── Side column ─────────────────────────────────────────────── -->
            <aside class="ov-side">
                <section class="ov-card" v-loading="incomeLoading && !!income">
                    <header class="ov-card-head">
                        <h2><i class="fas fa-chart-column"></i> {{ $t('acc_ov_pl_summary') }}</h2>
                        <span class="muted-sm">{{ rangeLabel }}</span>
                    </header>
                    <el-skeleton v-if="!income" :rows="5" animated />
                    <div v-else class="pl-list">
                        <div v-for="row in plRows" :key="row.key" class="pl-row" :class="[row.kind, { 'is-negative': row.value < 0 }]">
                            <div class="pl-label">
                                <span>{{ row.label }}</span>
                                <strong>{{ row.sign }}{{ formatMoney(Math.abs(row.value)) }}</strong>
                            </div>
                            <div class="pl-bar"><span :style="{ width: row.width + '%' }"></span></div>
                            <span v-if="row.hint" class="pl-hint">{{ row.hint }}</span>
                        </div>
                        <router-link :to="incomeLink" class="card-foot-link">{{ $t('income_statement') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
                    </div>
                </section>

                <section class="ov-card">
                    <header class="ov-card-head">
                        <h2><i class="fas fa-scale-balanced"></i> {{ $t('acc_ov_financial_position') }}</h2>
                        <el-tag v-if="position" size="small" :type="position.is_balanced ? 'success' : 'danger'" effect="light" round>
                            {{ position.is_balanced ? $t('acc_ov_balanced') : $t('acc_ov_unbalanced') }}
                        </el-tag>
                    </header>
                    <el-skeleton v-if="!position" :rows="3" animated />
                    <template v-else>
                        <div class="eq-row">
                            <span class="eq-side">
                                <span class="eq-dot dot-assets"></span>{{ $t('total_assets_label') }}
                                <strong>{{ formatMoney(position.assets.total) }}</strong>
                            </span>
                        </div>
                        <div class="eq-bar"><span class="seg-assets" style="width: 100%"></span></div>
                        <div class="eq-bar">
                            <span class="seg-liabilities" :style="{ width: fundingShare.liabilities + '%' }"></span>
                            <span class="seg-equity" :style="{ width: fundingShare.equity + '%' }"></span>
                        </div>
                        <div class="eq-legend">
                            <span><span class="eq-dot dot-liabilities"></span>{{ $t('liabilities_group') }} {{ formatMoney(position.liabilities.total) }}</span>
                            <span><span class="eq-dot dot-equity"></span>{{ $t('type_equity') }} {{ formatMoney(position.equity.total) }}</span>
                        </div>
                        <p class="eq-note">{{ $t('acc_ov_equation_note') }}</p>
                        <router-link to="/admin/accounting/balance-sheet" class="card-foot-link">{{ $t('balance_sheet') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
                    </template>
                </section>
            </aside>
        </div>

        <!-- ── Every accounting screen, grouped by the job it does ─────────── -->
        <section class="hub">
            <h2 class="hub-title">{{ $t('acc_ov_books_and_reports') }}</h2>
            <div class="hub-grid">
                <div v-for="group in hubGroups" :key="group.key" class="hub-group">
                    <h3>{{ group.title }}</h3>
                    <router-link v-for="link in group.links" :key="link.path" :to="link.path" class="hub-link">
                        <span class="hub-icon" :class="group.tone"><i :class="link.icon"></i></span>
                        <span class="hub-text">
                            <strong>{{ $t(link.label) }}</strong>
                            <small>{{ $t(link.hint) }}</small>
                        </span>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Pricing the stocked items the health check is complaining about.
             The check knows exactly which products it counted; this shows that
             list and takes the missing number, instead of sending the reader to
             another screen to work it out again. -->
        <el-dialog
            v-model="pricingVisible"
            :title="$t('set_cost_for_stocked_items')"
            width="820px"
            top="6vh"
            destroy-on-close
        >
            <p class="pricing-intro">{{ $t('why_cost_price_matters') }}</p>

            <el-table
                v-loading="pricingLoading"
                :data="pricingRows"
                stripe
                max-height="420"
                style="width: 100%"
            >
                <el-table-column :label="$t('product')" min-width="200">
                    <template #default="{ row }">
                        <div class="pricing-name">{{ row.name }}</div>
                        <div class="pricing-sku">{{ row.sku || '—' }}</div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('stock_on_hand')" width="110">
                    <template #default="{ row }">
                        {{ formatNumber(row.stock_on_hand) }}<span v-if="row.unit" class="pricing-unit"> {{ row.unit }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('selling_price')" width="110">
                    <template #default="{ row }">{{ formatMoney(row.price) }}</template>
                </el-table-column>

                <!-- What it last actually cost to buy: the answer to the very
                     question being asked, whenever a purchase exists for it. -->
                <el-table-column :label="$t('last_purchase_cost')" width="160">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.suggested_cost"
                            size="small"
                            text
                            type="primary"
                            @click="row.cost_price = row.suggested_cost"
                        >
                            {{ formatMoney(row.suggested_cost) }} · {{ $t('use_this_cost') }}
                        </el-button>
                        <span v-else class="pricing-none">{{ $t('no_purchase_recorded') }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('cost_price')" width="150">
                    <template #default="{ row }">
                        <el-input-number
                            v-model="row.cost_price"
                            :min="0"
                            :step="0.01"
                            :controls="false"
                            size="small"
                            class="pricing-input"
                        />
                    </template>
                </el-table-column>

                <template #empty>
                    <span class="pricing-none">{{ $t('nothing_left_to_price') }}</span>
                </template>
            </el-table>

            <template #footer>
                <div class="pricing-footer">
                    <span class="pricing-count">{{ $t('items_ready_to_price', { count: pricedCount }) }}</span>
                    <span>
                        <el-button @click="pricingVisible = false">{{ $t('cancel') }}</el-button>
                        <el-button
                            type="primary"
                            :loading="pricingSaving"
                            :disabled="pricedCount === 0"
                            @click="savePricing"
                        >
                            {{ $t('save') }}
                        </el-button>
                    </span>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import api from '@/api';
import {
    formatMoney as formatMoneyWith,
    formatNumber,
    baseCurrencyCode,
    numberLocale,
} from '@/utils/currency';
import { useAccountingReportsStore } from '@/stores/accountingReports';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const reportsStore = useAccountingReportsStore();

const baseCode = baseCurrencyCode();
const formatMoney = (value) => formatMoneyWith(value || 0);
const formatPct = (value) => `${Number(value || 0).toLocaleString(numberLocale(), { maximumFractionDigits: 1 })}%`;
const formatDate = (date) => (date ? String(date).slice(0, 10) : '');

/** Local calendar date: `toISOString()` is UTC and names yesterday until 03:00 here. */
const isoDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const today = new Date();
const todayIso = isoDate(today);
const todayLabel = todayIso;

/* ------------------------------------------------------------------ *
 * Financial position
 *
 * Taken from the balance sheet endpoint, which sums every posted line up to
 * today. The old cards added up the `balance` column of the first 100 ledger
 * accounts in the browser: parents counted on top of their children, anything
 * past the hundredth account disappeared, and the year's unclosed profit was
 * nowhere in equity — so assets never equalled liabilities plus equity.
 * ------------------------------------------------------------------ */

const position = ref(null);
const positionLoading = ref(false);

const loadPosition = async () => {
    positionLoading.value = true;
    try {
        const response = await api.get('/admin/accounting/balance-sheet', { params: { as_of: todayIso } });
        position.value = response.data?.data || null;
    } finally {
        positionLoading.value = false;
    }
};

/** How the assets are funded; negative equity (accumulated losses) gets no bar. */
const fundingShare = computed(() => {
    const liabilities = Math.max(Number(position.value?.liabilities?.total || 0), 0);
    const equity = Math.max(Number(position.value?.equity?.total || 0), 0);
    const total = liabilities + equity;
    if (total === 0) return { liabilities: 0, equity: 0 };
    return {
        liabilities: Math.round((liabilities / total) * 100),
        equity: 100 - Math.round((liabilities / total) * 100),
    };
});

/* ------------------------------------------------------------------ *
 * Profit and loss for the chosen span
 * ------------------------------------------------------------------ */

const plRange = ref('year');
const rangeOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'month' },
    { label: t('acc_ov_this_year'), value: 'year' },
]);
const rangeLabel = computed(() => (plRange.value === 'month' ? t('acc_ov_this_month') : t('acc_ov_this_year')));

const rangeDates = computed(() => {
    const from = plRange.value === 'month'
        ? new Date(today.getFullYear(), today.getMonth(), 1)
        : new Date(today.getFullYear(), 0, 1);
    return { date_from: isoDate(from), date_to: todayIso };
});

const income = ref(null);
const incomeLoading = ref(false);

const loadIncome = async () => {
    incomeLoading.value = true;
    try {
        const response = await api.get('/admin/accounting/income-statement', { params: rangeDates.value });
        income.value = response.data?.data || null;
    } finally {
        incomeLoading.value = false;
    }
};

const incomeLink = computed(() => ({ path: '/admin/accounting/income-statement', query: rangeDates.value }));

/** Change against the same-length span just before; none when that span had nothing. */
const revenueChange = computed(() => {
    const previous = Number(income.value?.comparison?.net_revenue || 0);
    if (!income.value || previous === 0) return null;
    return ((Number(income.value.net_revenue || 0) - previous) / Math.abs(previous)) * 100;
});

const plRows = computed(() => {
    const s = income.value;
    if (!s) return [];
    const revenue = Number(s.net_revenue || 0);
    const cos = Number(s.cost_of_sales?.total || 0);
    const gross = Number(s.gross_profit || 0);
    const opex = Number(s.operating_expenses?.total || 0);
    const net = Number(s.net_income || 0);
    const scale = Math.max(Math.abs(revenue), Math.abs(cos), Math.abs(gross), Math.abs(opex), Math.abs(net), 1);
    const width = (v) => Math.round((Math.abs(v) / scale) * 100);
    const margin = (pct) => (pct === null || pct === undefined ? '' : t('acc_ov_margin', { pct: formatPct(pct) }));

    return [
        { key: 'revenue', kind: 'is-in', label: t('acc_ov_net_revenue'), value: revenue, sign: '', width: width(revenue) },
        { key: 'cos', kind: 'is-out', label: t('acc_ov_cost_of_sales'), value: cos, sign: cos ? '−' : '', width: width(cos) },
        { key: 'gross', kind: 'is-sub', label: t('acc_ov_gross_profit'), value: gross, sign: gross < 0 ? '−' : '', width: width(gross), hint: margin(s.gross_margin_pct) },
        { key: 'opex', kind: 'is-out', label: t('acc_ov_operating_expenses'), value: opex, sign: opex ? '−' : '', width: width(opex) },
        { key: 'net', kind: 'is-total', label: net >= 0 ? t('acc_ov_net_profit') : t('acc_ov_net_loss'), value: net, sign: net < 0 ? '−' : '', width: width(net), hint: margin(s.net_margin_pct) },
    ];
});

/* ------------------------------------------------------------------ *
 * Recent entries and period state
 * ------------------------------------------------------------------ */

const entries = ref([]);
const entriesTotal = ref(0);
const entriesLoading = ref(false);

const loadEntries = async () => {
    entriesLoading.value = true;
    try {
        const response = await api.get('/admin/accounting/journal-entries', { params: { per_page: 8 } });
        entries.value = response.data?.data?.entries || [];
        entriesTotal.value = response.data?.data?.pagination?.total ?? entries.value.length;
    } finally {
        entriesLoading.value = false;
    }
};

const isRowBalanced = (row) => Number(row.total_debit || 0).toFixed(2) === Number(row.total_credit || 0).toFixed(2);

const todayIsClosed = ref(false);

const loadPeriods = async () => {
    const response = await api.get('/admin/accounting/periods');
    todayIsClosed.value = !!response.data?.data?.today_is_closed;
};

/* ------------------------------------------------------------------ *
 * System health
 *
 * Read-only by design: it reports what disagrees and how to fix it, and never
 * repairs anything itself. Writing to the books is not something a dashboard
 * should do while nobody is looking.
 * ------------------------------------------------------------------ */

const health = ref({ is_healthy: true, issue_count: 0, affected_records: 0, checks: [] });
const healthLoading = ref(false);
const healthError = ref(false);
const showPassed = ref(false);

const failingChecks = computed(() => (health.value.checks || []).filter((c) => !c.ok));
const passingChecks = computed(() => (health.value.checks || []).filter((c) => c.ok));

const loadHealth = async () => {
    healthLoading.value = true;
    healthError.value = false;
    try {
        health.value = await reportsStore.fetchSystemHealth();
    } catch (e) {
        // Shown as "unknown", never as the green "all passed" the default
        // state would otherwise print.
        healthError.value = true;
        console.error('System health check failed', e);
    } finally {
        healthLoading.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Loading
 *
 * Each block loads on its own, so one slow or failing report leaves the rest
 * of the page usable and says which part is missing instead of blanking all.
 * ------------------------------------------------------------------ */

const refreshing = ref(false);
const loadErrors = ref([]);

const refresh = async () => {
    refreshing.value = true;
    loadHealth();
    const results = await Promise.allSettled([loadPosition(), loadIncome(), loadEntries(), loadPeriods()]);
    loadErrors.value = results.filter((r) => r.status === 'rejected');
    loadErrors.value.forEach((r) => console.error('Accounting overview block failed', r.reason));
    refreshing.value = false;
};

onMounted(refresh);

/* ------------------------------------------------------------------ *
 * Hub: every accounting screen, where only three were linked before
 * ------------------------------------------------------------------ */

const hubGroups = computed(() => [
    {
        key: 'books',
        title: t('acc_ov_group_books'),
        tone: 'tone-blue',
        links: [
            { path: '/admin/accounting/journal', icon: 'fas fa-book', label: 'general_journal', hint: 'acc_ov_hint_journal' },
            { path: '/admin/accounting/ledger', icon: 'fas fa-list-ol', label: 'chart_of_accounts', hint: 'acc_ov_hint_ledger' },
            { path: '/admin/accounting/periods', icon: 'fas fa-lock', label: 'accounting_periods', hint: 'acc_ov_hint_periods' },
        ],
    },
    {
        key: 'statements',
        title: t('acc_ov_group_statements'),
        tone: 'tone-green',
        links: [
            { path: '/admin/accounting/trial-balance', icon: 'fas fa-balance-scale', label: 'trial_balance', hint: 'acc_ov_hint_trial_balance' },
            { path: '/admin/accounting/income-statement', icon: 'fas fa-chart-line', label: 'income_statement', hint: 'acc_ov_hint_income_statement' },
            { path: '/admin/accounting/balance-sheet', icon: 'fas fa-scale-balanced', label: 'balance_sheet', hint: 'acc_ov_hint_balance_sheet' },
            { path: '/admin/accounting/cash-flow', icon: 'fas fa-money-bill-transfer', label: 'cash_flow_statement', hint: 'acc_ov_hint_cash_flow' },
        ],
    },
    {
        key: 'parties',
        title: t('acc_ov_group_parties'),
        tone: 'tone-amber',
        links: [
            { path: '/admin/accounting/aging', icon: 'fas fa-hourglass-half', label: 'aging_report', hint: 'acc_ov_hint_aging' },
            { path: '/admin/accounting/party-statement', icon: 'fas fa-file-invoice', label: 'party_statement', hint: 'acc_ov_hint_party_statement' },
            { path: '/admin/accounting/vat-return', icon: 'fas fa-percent', label: 'vat_return', hint: 'acc_ov_hint_vat_return' },
        ],
    },
    {
        key: 'control',
        title: t('acc_ov_group_control'),
        tone: 'tone-violet',
        links: [
            { path: '/admin/accounting/fixed-assets', icon: 'fas fa-building', label: 'fixed_assets', hint: 'acc_ov_hint_fixed_assets' },
            { path: '/admin/accounting/bank-reconciliation', icon: 'fas fa-building-columns', label: 'bank_reconciliation', hint: 'acc_ov_hint_bank_reconciliation' },
            { path: '/admin/accounting/cost-centers', icon: 'fas fa-sitemap', label: 'cost_centers', hint: 'acc_ov_hint_cost_centers' },
        ],
    },
]);

/* ------------------------------------------------------------------ *
 * Pricing the stocked items that have no cost
 *
 * The check above stays read-only; this is the deliberate repair it points
 * at, kept on its own endpoint precisely because it writes.
 * ------------------------------------------------------------------ */

const pricingVisible = ref(false);
const pricingLoading = ref(false);
const pricingSaving = ref(false);
const pricingRows = ref([]);

/** Only rows actually given a cost are sent; a blank row is "not now", not zero. */
const pricedRows = computed(() => pricingRows.value.filter((row) => Number(row.cost_price) > 0));
const pricedCount = computed(() => pricedRows.value.length);

const openPricingDialog = async () => {
    pricingVisible.value = true;
    pricingLoading.value = true;
    try {
        const response = await api.get('/admin/accounting/unpriced-stock');
        pricingRows.value = (response.data?.data?.products || []).map((product) => ({
            ...product,
            // Left empty rather than pre-filled with the suggestion: a cost
            // nobody chose is how the books ended up unable to say what
            // anything cost. The suggestion is one click away.
            cost_price: null,
        }));
    } catch (error) {
        ElMessage.error(t('failed_to_load_unpriced_items'));
        pricingRows.value = [];
    } finally {
        pricingLoading.value = false;
    }
};

const savePricing = async () => {
    pricingSaving.value = true;
    try {
        const response = await api.put('/admin/accounting/unpriced-stock', {
            items: pricedRows.value.map((row) => ({ id: row.id, cost_price: Number(row.cost_price) })),
        });

        const updated = response.data?.data?.updated ?? 0;
        const skipped = response.data?.data?.skipped || [];

        ElMessage.success(t('cost_prices_saved', { count: updated }));

        // Someone else priced these while the dialog sat open. Saying so beats
        // letting the count quietly disagree with what was typed.
        if (skipped.length) {
            ElMessage.warning(t('items_already_priced_elsewhere', { count: skipped.length }));
        }

        pricingVisible.value = false;
        loadHealth();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_cost_prices'));
    } finally {
        pricingSaving.value = false;
    }
};
</script>

<style scoped>
.accounting-page {
    font-family: 'Cairo', sans-serif;
    --ov-radius: 14px;
    --ov-border: var(--el-border-color-lighter, #e5e7eb);
    --ov-muted: var(--el-text-color-secondary, #64748b);
    --ov-text: var(--el-text-color-primary, #1e293b);
    --c-assets: #2563eb;
    --c-liabilities: #d97706;
    --c-equity: #059669;
    --c-revenue: #0891b2;
    --c-loss: #dc2626;
}

.muted-sm { font-size: 0.75rem; color: var(--ov-muted); }
.currency-tag { align-self: center; }

.ov-alert { margin-bottom: 1rem; border-radius: 10px; }
.ov-alert-link { font-weight: 700; font-size: 0.82rem; color: inherit; text-decoration: underline; }

/* ── KPIs ─────────────────────────────────────────────────────────────── */
.kpi-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.kpi-toolbar-label { font-size: 0.82rem; color: var(--ov-muted); }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 0.9rem;
    margin-bottom: 1.25rem;
}

.kpi-card {
    --tone: var(--c-assets);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 1rem 1.1rem;
    background: var(--el-bg-color, #fff);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    border-top: 3px solid var(--tone);
    color: var(--ov-text);
    text-decoration: none;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    min-width: 0;
}

a.kpi-card:hover { box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
a.kpi-card:focus-visible { outline: 2px solid var(--tone); outline-offset: 2px; }

.kpi-liabilities { --tone: var(--c-liabilities); }
.kpi-equity { --tone: var(--c-equity); }
.kpi-revenue { --tone: var(--c-revenue); }
.kpi-profit { --tone: var(--c-equity); }
.kpi-loss { --tone: var(--c-loss); }

.kpi-icon {
    position: absolute;
    inset-inline-end: 1rem;
    top: 0.9rem;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, transparent);
    font-size: 0.95rem;
}

.kpi-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ov-muted);
    padding-inline-end: 2.6rem;
}

.kpi-value {
    font-size: 1.35rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.kpi-value.is-negative { color: var(--c-loss); }
.kpi-sub { font-size: 0.74rem; color: var(--ov-muted); }
.kpi-delta.is-up { color: var(--c-equity); font-weight: 700; }
.kpi-delta.is-down { color: var(--c-loss); font-weight: 700; }

/* ── Layout ───────────────────────────────────────────────────────────── */
.ov-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr);
    gap: 1.1rem;
    align-items: start;
}

.ov-main, .ov-side { display: flex; flex-direction: column; gap: 1.1rem; min-width: 0; }

@media (max-width: 1100px) {
    .ov-grid { grid-template-columns: minmax(0, 1fr); }
}

.ov-card {
    background: var(--el-bg-color, #fff);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    padding: 1rem 1.1rem;
    min-width: 0;
}

.ov-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
}

.ov-card-head h2 {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--ov-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ov-card-head h2 i { color: var(--el-color-primary); font-size: 0.9rem; }
.ov-card-head-end { display: flex; align-items: center; gap: 0.5rem; }

.head-link, .card-foot-link {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--el-color-primary);
    text-decoration: none;
}

.head-link:hover, .card-foot-link:hover { text-decoration: underline; }
.card-foot-link { display: inline-block; margin-top: 0.9rem; }
.dir-arrow { font-size: 0.7em; margin-inline-start: 0.25rem; }
:global([dir="rtl"]) .dir-arrow { transform: scaleX(-1); }

/* ── Health ───────────────────────────────────────────────────────────── */
.health-banner {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 0.9rem;
    border-radius: 10px;
    font-size: 0.86rem;
}

.health-banner.is-clear { background: var(--el-color-success-light-9); color: var(--el-color-success-dark-2); }
.health-banner.is-bad { background: var(--el-color-danger-light-9); color: var(--el-color-danger-dark-2); }
.health-banner.is-unknown { background: var(--el-fill-color-light); color: var(--ov-muted); }
.health-banner-count { margin-inline-start: auto; font-weight: 700; font-variant-numeric: tabular-nums; }

.health-list { display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.75rem; }

.health-check {
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--el-color-danger-light-7);
    border-inline-start: 4px solid var(--el-color-danger);
    border-radius: 10px;
}

.check-head { display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: 600; }
.check-head i { color: var(--el-color-danger); flex-shrink: 0; }
.check-title { flex: 1; min-width: 0; }

.check-count {
    min-width: 1.8rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    text-align: center;
    font-size: 0.8rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: #fff;
    background: var(--el-color-danger);
}

.check-detail { margin: 0.45rem 0 0; font-size: 0.8rem; line-height: 1.7; color: var(--ov-muted); }

.check-action {
    margin: 0.3rem 0 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--el-color-danger);
    overflow-wrap: anywhere;
}

.check-fix { margin-top: 0.6rem; }
.passed-toggle { margin-top: 0.5rem; }

.passed-list {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.35rem 1rem;
}

.passed-list li { font-size: 0.8rem; color: var(--ov-muted); display: flex; gap: 0.45rem; align-items: baseline; }
.passed-list i { color: var(--el-color-success); font-size: 0.72rem; }

/* ── Entries ──────────────────────────────────────────────────────────── */
.entry-no { font-weight: 700; font-variant-numeric: tabular-nums; }
.entry-amt { font-weight: 700; font-variant-numeric: tabular-nums; }

.entry-lines { padding: 0.4rem 2.5rem 0.6rem 1rem; }

.entry-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 110px 110px;
    gap: 0.75rem;
    padding: 0.3rem 0;
    font-size: 0.8rem;
    border-bottom: 1px dashed var(--ov-border);
}

.entry-line:last-child { border-bottom: 0; }
.line-code { color: var(--ov-muted); font-variant-numeric: tabular-nums; margin-inline-end: 0.35rem; }
.line-amt { text-align: end; font-variant-numeric: tabular-nums; }
.line-amt.is-debit { color: var(--el-color-success-dark-2); }
.line-amt.is-credit { color: var(--el-color-warning-dark-2); }

/* ── P&L ──────────────────────────────────────────────────────────────── */
.pl-list { display: flex; flex-direction: column; gap: 0.8rem; }
.pl-label { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.84rem; color: var(--ov-text); }
.pl-label strong { font-variant-numeric: tabular-nums; }
.pl-row.is-sub .pl-label, .pl-row.is-total .pl-label { font-weight: 700; }
.pl-row.is-total { padding-top: 0.7rem; border-top: 1px solid var(--ov-border); }
.pl-row.is-negative .pl-label strong { color: var(--c-loss); }

.pl-bar { height: 6px; margin-top: 0.3rem; border-radius: 999px; background: var(--el-fill-color-light); overflow: hidden; }
.pl-bar span { display: block; height: 100%; border-radius: inherit; background: var(--c-revenue); transition: width 0.4s ease; }
.pl-row.is-out .pl-bar span { background: var(--c-liabilities); }
.pl-row.is-sub .pl-bar span, .pl-row.is-total .pl-bar span { background: var(--c-equity); }
.pl-row.is-negative .pl-bar span { background: var(--c-loss); }
.pl-hint { font-size: 0.72rem; color: var(--ov-muted); }

/* ── Financial position ───────────────────────────────────────────────── */
.eq-row { font-size: 0.84rem; }
.eq-side { display: flex; align-items: center; gap: 0.4rem; }
.eq-side strong { margin-inline-start: auto; font-variant-numeric: tabular-nums; }

.eq-bar {
    display: flex;
    height: 10px;
    margin-top: 0.45rem;
    border-radius: 999px;
    overflow: hidden;
    background: var(--el-fill-color-light);
}

.eq-bar span { display: block; height: 100%; transition: width 0.4s ease; }
.seg-assets { background: var(--c-assets); }
.seg-liabilities { background: var(--c-liabilities); }
.seg-equity { background: var(--c-equity); }

.eq-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.35rem 1rem;
    margin-top: 0.55rem;
    font-size: 0.78rem;
    color: var(--ov-muted);
    font-variant-numeric: tabular-nums;
}

.eq-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-inline-end: 0.35rem; }
.dot-assets { background: var(--c-assets); }
.dot-liabilities { background: var(--c-liabilities); }
.dot-equity { background: var(--c-equity); }
.eq-note { margin: 0.7rem 0 0; font-size: 0.74rem; line-height: 1.7; color: var(--ov-muted); }

/* ── Hub ──────────────────────────────────────────────────────────────── */
.hub { margin-top: 1.5rem; }
.hub-title { margin: 0 0 0.8rem; font-size: 1rem; font-weight: 700; color: var(--ov-text); }

.hub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

.hub-group {
    background: var(--el-bg-color, #fff);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.hub-group h3 {
    margin: 0 0 0.35rem;
    padding-inline: 0.4rem;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--ov-muted);
}

.hub-link {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.5rem 0.4rem;
    border-radius: 10px;
    color: var(--ov-text);
    text-decoration: none;
    transition: background 0.15s ease;
}

.hub-link:hover { background: var(--el-fill-color-light); }
.hub-link:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 1px; }

.hub-icon {
    --tone: var(--c-assets);
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 9px;
    color: var(--tone);
    background: color-mix(in srgb, var(--tone) 12%, transparent);
    font-size: 0.88rem;
}

.hub-icon.tone-green { --tone: var(--c-equity); }
.hub-icon.tone-amber { --tone: var(--c-liabilities); }
.hub-icon.tone-violet { --tone: #7c3aed; }

.hub-text { display: flex; flex-direction: column; min-width: 0; }
.hub-text strong { font-size: 0.86rem; font-weight: 700; }
.hub-text small { font-size: 0.72rem; color: var(--ov-muted); line-height: 1.5; }

/* ── Pricing dialog ───────────────────────────────────────────────────── */
.pricing-intro {
    margin: 0 0 1rem;
    font-size: 0.85rem;
    line-height: 1.7;
    color: var(--el-text-color-regular);
}

.pricing-name { font-weight: 700; }
.pricing-sku, .pricing-unit { font-size: 0.75rem; color: var(--el-text-color-secondary); }
.pricing-none { font-size: 0.78rem; color: var(--el-text-color-secondary); }
.pricing-input { width: 100%; }

.pricing-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.pricing-count { font-size: 0.82rem; color: var(--el-text-color-secondary); }
</style>
