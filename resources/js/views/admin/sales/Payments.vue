<template>
    <div class="payments-page">
        <AdminPageHeader
            icon="fas fa-money-bill-transfer"
            :title="$t('payments')"
            :subtitle="$t('pay_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="reload" />
                </el-tooltip>
                <el-button v-if="tab === 'expenses'" :icon="Plus" @click="openExpenseDialog">{{ $t('add_expense') }}</el-button>
                <el-button type="primary" :icon="Plus" @click="paymentDialogVisible = true">{{ $t('record_payment') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Money in, over the search and dates ── -->
        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.collected) }}</h3>
                        <p>{{ $t('pay_collected') }} · {{ periodLabel }}</p>
                        <div v-if="methodSplit.length" class="method-bar" :aria-label="methodSplitText">
                            <span
                                v-for="part in methodSplit"
                                :key="part.method"
                                class="method-bar-part"
                                :class="`m-${part.method}`"
                                :style="{ flexGrow: part.share }"
                                :title="`${paymentMethodLabel(part.method)}: ${formatCurrency(part.total)}`"
                            />
                        </div>
                        <span class="stat-sub">{{ methodSplitText || $t('pay_payments_n', { count: summary.collected_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.today) }}</h3>
                        <p>{{ $t('pay_today') }}</p>
                        <span class="stat-sub">{{ $t('pay_payments_n', { count: summary.today_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.kind === 'on_account' }" @click="setKind('on_account')">
                <div class="stat-inner">
                    <div class="stat-icon purple"><i class="fas fa-user-tag"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.on_account) }}</h3>
                        <p>{{ $t('pay_on_account') }}</p>
                        <span class="stat-sub">{{ $t('pay_on_account_hint', { count: summary.on_account_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                v-if="summary.refunded_count"
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': filters.kind === 'refund' }"
                @click="setKind('refund')"
            >
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.refunded) }}</h3>
                        <p>{{ $t('pay_refunded') }}</p>
                        <span class="stat-sub">{{ $t('pay_net', { amount: formatCurrency(summary.net) }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Cash by currency — only worth a panel once more than one is held.
             Each is its own drawer, never blended into one converted figure. -->
        <section v-if="showWallets" class="wallets">
            <div v-for="wallet in store.wallets" :key="wallet.currency" class="wallet" :class="{ 'is-base': wallet.is_base }">
                <span class="wallet-code">{{ wallet.currency }}</span>
                <strong class="wallet-amount">{{ formatWalletTotal(wallet) }}</strong>
                <span class="stat-sub">{{ $t('pay_payments_n', { count: wallet.payments_count }) }}</span>
            </div>
        </section>

        <el-tabs v-model="tab" class="page-tabs" @tab-change="onTabChange">
            <el-tab-pane name="payments" :label="$t('pay_tab_payments')" />
            <el-tab-pane name="expenses" :label="$t('pay_tab_expenses')" />
        </el-tabs>

        <!-- ══ Payments ══ -->
        <section v-show="tab === 'payments'" class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('pay_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.method" class="filter-select" :placeholder="$t('spay_all_methods')" clearable @change="applyFilters">
                    <el-option v-for="m in PAYMENT_METHODS" :key="m" :label="paymentMethodLabel(m)" :value="m" />
                </el-select>
                <el-select v-model="filters.kind" class="filter-select" :placeholder="$t('pay_any_kind')" clearable @change="applyFilters">
                    <el-option value="invoice" :label="$t('pay_kind_invoice')" />
                    <el-option value="on_account" :label="$t('pay_on_account')" />
                    <el-option value="refund" :label="$t('pay_kind_refund')" />
                </el-select>
                <el-date-picker
                    v-model="filters.range"
                    type="daterange"
                    class="filter-dates"
                    value-format="YYYY-MM-DD"
                    format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('pret_from')"
                    :end-placeholder="$t('pret_to')"
                    :shortcuts="dateShortcuts"
                    @change="applyFilters"
                />
                <el-tag v-if="filters.customer" closable size="large" class="customer-chip" @close="clearCustomer">
                    <i class="fas fa-user"></i> {{ $t('pay_customer_filter', { name: filters.customerName || `#${filters.customer}` }) }}
                </el-tag>
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="store.error && !store.payments.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchPayments">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.payments"
                row-key="id"
                style="width: 100%"
                class="payments-table"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && activeFilterCount" :description="$t('there_are_no_payments_matching')" :image-size="90">
                        <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('no_payments_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="paymentDialogVisible = true">{{ $t('record_payment') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column type="expand" width="36">
                    <template #default="{ row }">
                        <dl class="row-details">
                            <div>
                                <dt>{{ $t('pret_recorded_by') }}</dt>
                                <dd>{{ row.creator?.name || '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ $t('spay_recorded_at') }}</dt>
                                <dd>{{ formatDateTime(row.created_at) }}</dd>
                            </div>
                            <div v-if="row.tendered_amount !== null && row.tendered_amount !== undefined">
                                <dt>{{ $t('pay_tendered') }}</dt>
                                <dd dir="ltr">{{ formatCurrency(row.tendered_amount, row.currency) }}</dd>
                            </div>
                            <div v-if="row.invoice">
                                <dt>{{ $t('pay_invoice_left') }}</dt>
                                <dd>{{ formatCurrency(invoiceOwed(row)) }}</dd>
                            </div>
                            <div class="wide">
                                <dt>{{ $t('notes') }}</dt>
                                <dd>{{ row.notes || '—' }}</dd>
                            </div>
                        </dl>
                    </template>
                </el-table-column>

                <el-table-column prop="payment_date" :label="$t('payment_number')" min-width="140" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.payment_number || '—' }}</span>
                            <span class="cell-secondary">{{ formatDate(row.payment_date || row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <el-tooltip v-if="row.customer_id && String(row.customer_id) !== filters.customer" :content="$t('pay_filter_by_customer')" placement="top" :enterable="false">
                                <button type="button" class="link-button plain" @click="filterByCustomer(row)">{{ customerName(row) }}</button>
                            </el-tooltip>
                            <span v-else class="strong">{{ customerName(row) }}</span>
                            <span v-if="row.customer?.phone" class="cell-secondary" dir="ltr">{{ row.customer.phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('spay_applied_to')" min-width="160">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span v-if="row.is_refund" class="kind-tag refund"><i class="fas fa-rotate-left"></i> {{ $t('pay_kind_refund') }}</span>
                            <button v-if="row.invoice" type="button" class="link-button" @click="goToInvoice(row.invoice)">
                                <span dir="ltr">{{ row.invoice.invoice_number }}</span>
                            </button>
                            <span v-else-if="!row.is_refund" class="kind-tag account">{{ $t('pay_on_account') }}</span>
                            <span v-if="row.invoice && !row.is_refund" class="cell-secondary" :class="invoiceState(row).cls">
                                {{ invoiceState(row).text }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('payment_method')" min-width="140">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="method-tag" :class="`m-${row.payment_method}`">
                                <i class="fas" :class="methodIcon(row.payment_method)"></i>
                                {{ paymentMethodLabel(row.payment_method) }}
                            </span>
                            <span v-if="row.reference" class="cell-secondary" dir="auto">{{ row.reference }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="amount" :label="$t('amount')" min-width="130" align="right" sortable="custom">
                    <template #default="{ row }">
                        <strong class="amount" :class="row.is_refund ? 'out' : 'in'">
                            {{ row.is_refund ? '−' : '' }}{{ formatCurrency(Math.abs(Number(row.amount))) }}
                        </strong>
                        <span v-if="isForeign(row)" class="cell-secondary tendered" dir="ltr">
                            {{ formatCurrency(Math.abs(Number(row.tendered_amount)), row.currency) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column width="96" align="center">
                    <template #default="{ row }">
                        <template v-if="row.can_change">
                            <el-tooltip :content="$t('edit')" placement="top" :enterable="false">
                                <el-button size="small" circle text :aria-label="$t('edit')" @click="openEdit(row)"><i class="fas fa-pen"></i></el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('pay_reverse')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="danger" :aria-label="$t('pay_reverse')" @click="reversePayment(row)">
                                    <i class="fas fa-rotate-left"></i>
                                </el-button>
                            </el-tooltip>
                        </template>
                        <el-tooltip v-else :content="$t('pay_refund_locked')" placement="top" :enterable="false">
                            <i class="fas fa-lock locked"></i>
                        </el-tooltip>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="store.pagination.total > 0" class="pagination-row">
                <span class="cell-secondary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: store.pagination.total }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="store.pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </section>

        <!-- ══ Expenses ══ -->
        <section v-show="tab === 'expenses'" class="panel-card">
            <div class="filters">
                <el-input
                    v-model="expenseSearch"
                    class="filter-search"
                    :placeholder="$t('pay_expense_search')"
                    :prefix-icon="Search"
                    clearable
                />
                <span class="cell-secondary">{{ $t('pay_expenses_total', { amount: formatCurrency(expenseTotal) }) }}</span>
            </div>

            <el-alert v-if="expensesError" type="error" show-icon :closable="false" :title="expensesError" />
            <el-table v-else v-loading="expensesLoading" :data="filteredExpenses" style="width: 100%">
                <template #empty>
                    <el-empty v-if="!expensesLoading" :description="$t('there_are_no_expenses_matching')" :image-size="80">
                        <el-button :icon="Plus" @click="openExpenseDialog">{{ $t('add_expense') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>
                <el-table-column :label="$t('pay_expense')" min-width="220">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="strong">{{ row.description }}</span>
                            <span class="cell-secondary"><span class="mono" dir="ltr">{{ row.expense_number }}</span> · {{ formatDate(row.expense_date) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('category')" min-width="120">
                    <template #default="{ row }">{{ expenseCategoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column :label="$t('invoice')" min-width="140">
                    <template #default="{ row }">
                        <button v-if="row.invoice" type="button" class="link-button" @click="goToInvoice(row.invoice)">
                            <span dir="ltr">{{ row.invoice.invoice_number }}</span>
                        </button>
                        <span v-else class="cell-secondary">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('amount')" min-width="120" align="right">
                    <template #default="{ row }"><strong class="amount out">{{ formatCurrency(row.amount) }}</strong></template>
                </el-table-column>
            </el-table>
        </section>

        <QuickPaymentDialog v-model="paymentDialogVisible" @saved="onPaymentSaved" />

        <!-- Correct a payment: what it was, never who or which invoice -->
        <el-dialog v-model="editVisible" :title="$t('pay_edit_title', { number: editing?.payment_number || '' })" :width="isNarrow ? '94%' : '460px'" :close-on-click-modal="false">
            <el-form v-if="editing" label-position="top" @submit.prevent>
                <div class="edit-context">
                    <span>{{ customerName(editing) }}</span>
                    <span v-if="editing.invoice" dir="ltr">{{ editing.invoice.invoice_number }}</span>
                    <span v-else>{{ $t('pay_on_account') }}</span>
                </div>
                <el-form-item :label="$t('amount')">
                    <el-input-number v-model="editForm.amount" :min="0.01" :precision="2" :controls="false" :disabled="editing.amount_locked" style="width: 100%" />
                    <p v-if="editing.amount_locked" class="field-hint">
                        {{ editing.invoice?.status === 'cancelled' ? $t('pay_amount_locked_cancelled') : $t('pay_amount_locked_currency') }}
                    </p>
                    <p v-else-if="editing.invoice" class="field-hint">{{ $t('pay_amount_max', { amount: formatCurrency(editMax) }) }}</p>
                </el-form-item>
                <el-form-item :label="$t('payment_method')">
                    <el-radio-group v-model="editForm.payment_method">
                        <el-radio-button v-for="m in PAYMENT_METHODS" :key="m" :value="m">{{ paymentMethodLabel(m) }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <div class="edit-row">
                    <el-form-item :label="$t('payment_date')">
                        <el-date-picker v-model="editForm.payment_date" type="date" value-format="YYYY-MM-DD" format="YYYY-MM-DD" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('payment_reference')">
                        <el-input v-model="editForm.reference" maxlength="100" />
                    </el-form-item>
                </div>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="editForm.notes" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
                <el-alert v-if="editAmountChanged" type="info" :closable="false" show-icon :title="$t('pay_edit_effect')" />
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" :disabled="!!editBlocker" @click="saveEdit">{{ $t('save_changes') }}</el-button>
            </template>
        </el-dialog>

        <!-- Expense -->
        <el-dialog v-model="showExpenseDialog" :title="$t('add_expense')" :width="isNarrow ? '94%' : '480px'" :close-on-click-modal="false">
            <el-form :model="expenseForm" label-position="top" @submit.prevent>
                <el-form-item :label="$t('description')" required>
                    <el-input v-model="expenseForm.description" maxlength="255" />
                </el-form-item>
                <div class="edit-row">
                    <el-form-item :label="$t('amount')" required>
                        <el-input-number v-model="expenseForm.amount" :min="0" :precision="2" :controls="false" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('date')">
                        <el-date-picker v-model="expenseForm.expense_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                    </el-form-item>
                </div>
                <el-form-item :label="$t('category')">
                    <el-radio-group v-model="expenseForm.category">
                        <el-radio-button v-for="category in EXPENSE_CATEGORIES" :key="category" :value="category">
                            {{ expenseCategoryLabel(category) }}
                        </el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="expenseForm.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showExpenseDialog = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="savingExpense" @click="addExpense">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import { usePaymentsStore } from '@/stores/payments';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import {
    PAYMENT_METHODS,
    apiErrorMessage,
    customerName,
    formatCurrency,
    formatDate,
    localIsoDate,
    normalizeStatus,
    paymentMethodLabel,
} from '@/utils/sales';
import { formatMoney } from '@/utils/currency';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const store = usePaymentsStore();

const METHOD_ICONS = { cash: 'fa-money-bill-wave', card: 'fa-credit-card', bank_transfer: 'fa-building-columns', check: 'fa-money-check' };
const methodIcon = (method) => METHOD_ICONS[method] || 'fa-coins';

/** Each wallet is written in its own currency's precision — never the base's. */
const formatWalletTotal = (wallet) => formatMoney(wallet.total, { code: wallet.currency, decimals: wallet.decimal_places });
const showWallets = computed(() => store.wallets.length > 1 || store.wallets.some((w) => !w.is_base));

const goToInvoice = (invoice) => router.push(`/admin/sales/invoices/${invoice.id}/edit`);

// What the invoice still owes, net of credit notes (sent by the server); the
// old reading, total less paid, is only a fallback for a stale row.
const invoiceOwed = (row) => (row.invoice_owed !== null && row.invoice_owed !== undefined
    ? Number(row.invoice_owed)
    : Math.max(0, Number(row.invoice?.total) - Number(row.invoice?.paid_amount)));

const invoiceState = (row) => {
    if (row.invoice?.status === 'cancelled') return { cls: 'is-cancelled', text: t('pay_invoice_cancelled') };
    const owed = invoiceOwed(row);
    return owed > 0.009
        ? { cls: 'is-owing', text: t('pay_invoice_owes', { amount: formatCurrency(owed) }) }
        : { cls: 'is-settled', text: t('pay_invoice_settled') };
};

const isForeign = (row) => row.tendered_amount !== null && row.tendered_amount !== undefined
    && row.currency && !store.wallets.find((w) => w.currency === row.currency)?.is_base;

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary || {
    collected: 0, collected_count: 0, by_method: {}, on_account: 0, on_account_count: 0,
    today: 0, today_count: 0, refunded: 0, refunded_count: 0, net: 0,
});

const methodSplit = computed(() => {
    const byMethod = summary.value.by_method || {};
    const total = Object.values(byMethod).reduce((sum, m) => sum + Number(m.total || 0), 0);
    if (!total) return [];
    return Object.entries(byMethod)
        .map(([method, m]) => ({ method, total: Number(m.total || 0), share: Number(m.total || 0) / total }))
        .filter((p) => p.total > 0)
        .sort((a, b) => b.total - a.total);
});

const methodSplitText = computed(() => (methodSplit.value.length > 1
    ? methodSplit.value.map((p) => `${paymentMethodLabel(p.method)} ${Math.round(p.share * 100)}%`).join(' · ')
    : ''));

const periodLabel = computed(() => {
    if (!filters.range?.length) return t('spay_all_time');
    const [from, to] = filters.range;
    return `${formatDate(from)} – ${formatDate(to)}`;
});

// ── Tabs ─────────────────────────────────────────────────────────────────
const tab = ref('payments');
let expensesLoaded = false;
const onTabChange = (name) => {
    writeQuery();
    if (name === 'expenses' && !expensesLoaded) fetchExpenses();
};

// ── Filters, sorting and paging, kept in the URL ─────────────────────────
const blankFilters = () => ({ search: '', method: '', kind: '', range: null, customer: '', customerName: '' });
const filters = reactive(blankFilters());
const sort = reactive({ prop: 'payment_date', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    Object.assign(filters, {
        search: q.search ? String(q.search) : '',
        method: PAYMENT_METHODS.includes(q.method) ? q.method : '',
        kind: ['invoice', 'on_account', 'refund'].includes(q.kind) ? q.kind : '',
        range: q.from && q.to ? [String(q.from), String(q.to)] : null,
        customer: /^\d+$/.test(String(q.customer || '')) ? String(q.customer) : '',
        customerName: q.customer_name ? String(q.customer_name) : '',
    });
    tab.value = q.tab === 'expenses' ? 'expenses' : 'payments';
    sort.prop = q.sort === 'amount' ? 'amount' : 'payment_date';
    sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        tab: tab.value !== 'payments' ? tab.value : undefined,
        search: filters.search || undefined,
        method: filters.method || undefined,
        kind: filters.kind || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        customer: filters.customer || undefined,
        customer_name: filters.customer ? filters.customerName || undefined : undefined,
        sort: sort.prop !== 'payment_date' ? sort.prop : undefined,
        direction: sort.order === 'ascending' ? 'asc' : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [filters.search, filters.method, filters.kind, filters.range?.length ? '1' : '', filters.customer].filter(Boolean).length);

const fetchPayments = () => store.fetchPayments({
    with_summary: 1,
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search.trim() || undefined,
    payment_method: filters.method || undefined,
    kind: filters.kind || undefined,
    date_from: filters.range?.[0] || undefined,
    date_to: filters.range?.[1] || undefined,
    customer_id: filters.customer || undefined,
    // The server clock is UTC; "today" on the cards is the day here.
    today: localIsoDate(),
    sort: sort.prop === 'amount' ? 'amount' : 'date',
    direction: sort.order === 'ascending' ? 'asc' : 'desc',
}).catch(() => {});

const reload = () => {
    fetchPayments();
    store.fetchCurrencyWallets().catch(() => {});
    if (tab.value === 'expenses') fetchExpenses();
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = () => {
    Object.assign(filters, blankFilters());
    applyFilters();
};

const filterByCustomer = (row) => {
    filters.customer = String(row.customer_id);
    filters.customerName = customerName(row);
    applyFilters();
};

const clearCustomer = () => {
    filters.customer = '';
    filters.customerName = '';
    applyFilters();
};

const setKind = (kind) => {
    filters.kind = filters.kind === kind ? '' : kind;
    tab.value = 'payments';
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'payment_date';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

const rangeFrom = computed(() => (store.pagination.total ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, store.pagination.total));

const dateShortcuts = computed(() => {
    const now = new Date();
    const at = (y, m, d) => localIsoDate(new Date(y, m, d));
    const y = now.getFullYear();
    const m = now.getMonth();
    return [
        { text: t('today'), value: () => [localIsoDate(now), localIsoDate(now)] },
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

// ── Recording, correcting and reversing ──────────────────────────────────
const paymentDialogVisible = ref(false);

const onPaymentSaved = () => {
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
    store.fetchCurrencyWallets().catch(() => {});
};

const editVisible = ref(false);
const editing = ref(null);
const editForm = reactive({ amount: 0, payment_method: 'cash', payment_date: '', reference: '', notes: '' });

const openEdit = (payment) => {
    editing.value = payment;
    Object.assign(editForm, {
        amount: Number(payment.amount),
        payment_method: payment.payment_method,
        payment_date: String(payment.payment_date || payment.created_at || '').slice(0, 10),
        reference: payment.reference || '',
        notes: payment.notes || '',
    });
    editVisible.value = true;
};

const editAmountChanged = computed(() => editing.value && Math.abs(Number(editForm.amount) - Number(editing.value.amount)) > 0.009);

// What the invoice leaves room for: its balance plus what this payment already covers.
const editMax = computed(() => {
    if (!editing.value?.invoice) return Infinity;
    return invoiceOwed(editing.value) + Number(editing.value.amount);
});

const editBlocker = computed(() => {
    if (!(Number(editForm.amount) > 0)) return t('enter_amount_above_zero');
    if (Number(editForm.amount) - editMax.value > 0.009) return t('pay_amount_max', { amount: formatCurrency(editMax.value) });
    return '';
});

const saveEdit = async () => {
    if (editBlocker.value) return;
    try {
        await store.updatePayment(editing.value.id, { ...editForm });
        ElMessage.success(t('pay_saved'));
        editVisible.value = false;
        fetchPayments();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('pay_save_failed')));
    }
};

const reversePayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            payment.invoice
                ? t('pay_reverse_confirm_invoice', { number: payment.payment_number, amount: formatCurrency(payment.amount), invoice: payment.invoice.invoice_number })
                : t('pay_reverse_confirm', { number: payment.payment_number, amount: formatCurrency(payment.amount), customer: customerName(payment) }),
            t('pay_reverse'),
            { type: 'warning', confirmButtonText: t('pay_reverse'), cancelButtonText: t('pay_keep'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await store.deletePayment(payment.id);
        ElMessage.success(t('pay_reversed'));
        fetchPayments();
        store.fetchCurrencyWallets().catch(() => {});
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_payment')));
    }
};

// ── Expenses ─────────────────────────────────────────────────────────────
const EXPENSE_CATEGORIES = ['shipping', 'packaging', 'handling', 'other'];
const EXPENSE_CATEGORY_LABELS = { shipping: t('shipping'), packaging: t('packaging'), handling: t('process'), other: t('subject_other') };
const expenseCategoryLabel = (category) => EXPENSE_CATEGORY_LABELS[normalizeStatus(category)] || category || '—';

const expenses = ref([]);
const expensesLoading = ref(false);
const expensesError = ref('');
const expenseSearch = ref('');
const savingExpense = ref(false);
const showExpenseDialog = ref(false);
const expenseForm = reactive({ description: '', amount: 0, category: 'other', expense_date: localIsoDate(), notes: '' });

// The expenses endpoint returns every row, so filtering here does see them all.
const filteredExpenses = computed(() => {
    const q = expenseSearch.value.trim().toLowerCase();
    if (!q) return expenses.value;
    return expenses.value.filter((e) => [e.expense_number, e.description, expenseCategoryLabel(e.category), e.invoice?.invoice_number]
        .some((f) => String(f || '').toLowerCase().includes(q)));
});
const expenseTotal = computed(() => filteredExpenses.value.reduce((sum, e) => sum + (Number(e.amount) || 0), 0));

const fetchExpenses = async () => {
    expensesLoading.value = true;
    expensesError.value = '';
    try {
        const response = await axios.get('/api/v1/expenses');
        const payload = response.data?.data;
        expenses.value = Array.isArray(payload) ? payload : (payload?.expenses || []);
        expensesLoaded = true;
    } catch (error) {
        expensesError.value = apiErrorMessage(error, t('failed_to_load_expenses'));
    } finally {
        expensesLoading.value = false;
    }
};

const openExpenseDialog = () => {
    // The local day: toISOString() gave yesterday before 3am in Damascus.
    Object.assign(expenseForm, { description: '', amount: 0, category: 'other', expense_date: localIsoDate(), notes: '' });
    showExpenseDialog.value = true;
};

const addExpense = async () => {
    if (!expenseForm.description.trim()) {
        ElMessage.warning(t('enter_expense_description'));
        return;
    }
    if (!expenseForm.amount || expenseForm.amount <= 0) {
        ElMessage.warning(t('enter_amount_above_zero'));
        return;
    }

    savingExpense.value = true;
    try {
        await axios.post('/api/v1/expenses', { ...expenseForm });
        showExpenseDialog.value = false;
        ElMessage.success(t('expense_added'));
        tab.value = 'expenses';
        writeQuery();
        await fetchExpenses();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_add_expense')));
    } finally {
        savingExpense.value = false;
    }
};

// ── Formatting, layout and lifecycle ─────────────────────────────────────
const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date);
};

const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

watch(() => route.query, (query) => {
    if (route.name !== 'admin.payments.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchPayments();
    if (tab.value === 'expenses' && !expensesLoaded) fetchExpenses();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    fetchPayments();
    store.fetchCurrencyWallets().catch(() => {});
    if (tab.value === 'expenses') fetchExpenses();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.payments-page { font-family: 'Cairo', sans-serif; }

/* ── Cards ── */
.stat-card { border-radius: 14px; transition: border-color 0.15s, box-shadow 0.15s; }
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-active { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
.stat-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-details { min-width: 0; flex: 1; }
.stat-details h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }
.stat-sub { display: block; font-size: 0.74rem; color: #94a3b8; margin-top: 0.15rem; }

.method-bar { display: flex; height: 5px; border-radius: 999px; overflow: hidden; margin-top: 0.4rem; background: #f1f5f9; gap: 2px; }
.method-bar-part { min-width: 3px; background: var(--m); }
.m-cash { --m: #16a34a; }
.m-card { --m: #7c3aed; }
.m-bank_transfer { --m: #2563eb; }
.m-check { --m: #d97706; }

/* ── Wallets ── */
.wallets { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.wallet { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.7rem 0.9rem; display: grid; gap: 0.1rem; }
.wallet.is-base { border-color: #93c5fd; }
.wallet-code { font-size: 0.75rem; font-weight: 700; color: #64748b; }
.wallet-amount { font-size: 1.05rem; font-variant-numeric: tabular-nums; }

.page-tabs { margin-bottom: 0.25rem; }
.page-tabs :deep(.el-tabs__header) { margin-bottom: 0.75rem; }

/* ── Panel ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 170px; }
.filter-dates { max-width: 270px; }

/* ── Table ── */
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; font-size: 0.85rem; }
.strong { font-weight: 600; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.amount.in { color: #15803d; }
.amount.out { color: #b91c1c; }

.link-button { all: unset; cursor: pointer; color: #2563eb; font-weight: 600; }
.link-button:hover { text-decoration: underline; }
.link-button.plain { color: #0f172a; }
.link-button.plain:hover { color: #2563eb; }
.cell-secondary.is-owing { color: #b45309; }
.cell-secondary.is-settled { color: #15803d; }
.cell-secondary.is-cancelled { color: #94a3b8; text-decoration: line-through; }
.tendered { display: block; margin-top: 0.1rem; }
.customer-chip { font-weight: 600; }
.customer-chip i { margin-inline-end: 0.3rem; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.kind-tag { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; font-weight: 600; padding: 0.05rem 0.5rem; border-radius: 999px; }
.kind-tag.account { color: #7c3aed; background: #f5f3ff; }
.kind-tag.refund { color: #b91c1c; background: #fef2f2; }

.method-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--m, #475569);
    background: color-mix(in srgb, var(--m, #475569) 10%, #fff);
}
.locked { color: #cbd5e1; }

.row-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem 1.25rem;
    margin: 0;
    padding: 0.5rem 1.25rem 0.75rem;
}
.row-details .wide { grid-column: 1 / -1; }
.row-details dt { font-size: 0.74rem; color: #64748b; }
.row-details dd { margin: 0.1rem 0 0; font-weight: 600; white-space: pre-line; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

/* ── Dialogs ── */
.edit-context { display: flex; justify-content: space-between; gap: 1rem; padding: 0.55rem 0.8rem; margin-bottom: 1rem; background: #f8fafc; border-radius: 8px; font-weight: 600; font-size: 0.88rem; }
.edit-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 0.75rem; }
.field-hint { margin: 0.25rem 0 0; font-size: 0.76rem; color: #64748b; line-height: 1.4; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
    .edit-row { grid-template-columns: 1fr; }
}
</style>
