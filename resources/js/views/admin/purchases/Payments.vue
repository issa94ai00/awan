<template>
    <div class="purchases-page supplier-payments">
        <AdminPageHeader
            icon="fas fa-money-check-dollar"
            :title="$t('supplier_payments')"
            :subtitle="$t('supplier_payments_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="reload" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="openForm()">
                    {{ $t('record_supplier_payment') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon red"><el-icon><Wallet /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(store.totalOutstanding) }}</h3>
                        <p>{{ $t('outstanding_to_suppliers') }}</p>
                        <span class="stat-sub">{{ $t('spay_suppliers_n', { count: store.owedCount }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card v-if="store.totalAdvances > 0" shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon purple"><el-icon><Promotion /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(store.totalAdvances) }}</h3>
                        <p>{{ $t('spay_advances') }}</p>
                        <span class="stat-sub">{{ $t('spay_suppliers_n', { count: store.advancesCount }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon green"><el-icon><Coin /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(store.totalPaid) }}</h3>
                        <p>{{ $t('spay_paid') }} · {{ periodLabel }}</p>
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
                        <span v-if="methodSplit.length" class="stat-sub">{{ methodSplitText }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><el-icon><Tickets /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ Number(store.pagination.total || 0).toLocaleString() }}</h3>
                        <p>{{ $t('payments') }} · {{ periodLabel }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="layout">
            <!-- ── Payments ── -->
            <section class="panel-card payments-panel">
                <div class="filters">
                    <el-input
                        v-model="filters.search"
                        class="filter-search"
                        :placeholder="$t('spay_search_placeholder')"
                        :prefix-icon="Search"
                        clearable
                        @input="onSearchInput"
                    />
                    <el-select
                        v-model="filters.supplier_id"
                        class="filter-select"
                        :placeholder="$t('pret_all_suppliers')"
                        filterable
                        clearable
                        @change="applyFilters"
                    >
                        <el-option v-for="s in store.outstanding" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                    <el-select
                        v-model="filters.method"
                        class="filter-select"
                        :placeholder="$t('spay_all_methods')"
                        clearable
                        @change="applyFilters"
                    >
                        <el-option v-for="m in METHOD_FILTERS" :key="m" :label="paymentMethodLabel(m)" :value="m" />
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
                >
                    <template #empty>
                        <el-empty v-if="!store.loading && activeFilterCount" :description="$t('spay_no_matches')" :image-size="90">
                            <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                        </el-empty>
                        <el-empty v-else-if="!store.loading" :description="$t('no_supplier_payments_yet')" :image-size="90">
                            <el-button type="primary" :icon="Plus" @click="openForm()">{{ $t('record_supplier_payment') }}</el-button>
                        </el-empty>
                        <span v-else />
                    </template>

                    <el-table-column type="expand" width="36">
                        <template #default="{ row }">
                            <dl class="row-details">
                                <div>
                                    <dt>{{ $t('spay_applied_to') }}</dt>
                                    <dd dir="auto">{{ row.purchase_receipt?.receipt_number || $t('spay_on_account') }}</dd>
                                </div>
                                <div>
                                    <dt>{{ $t('pret_recorded_by') }}</dt>
                                    <dd>{{ row.creator?.name || '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ $t('spay_recorded_at') }}</dt>
                                    <dd>{{ formatDateTime(row.created_at) }}</dd>
                                </div>
                                <div class="wide">
                                    <dt>{{ $t('notes') }}</dt>
                                    <dd>{{ row.notes || '—' }}</dd>
                                </div>
                            </dl>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_number')" min-width="140">
                        <template #default="{ row }">
                            <div class="cell-stack">
                                <span class="mono" dir="ltr">{{ row.payment_number }}</span>
                                <span class="cell-secondary">{{ formatDate(row.payment_date) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('supplier')" min-width="170">
                        <template #default="{ row }">
                            <div class="cell-stack">
                                <button
                                    v-if="row.supplier"
                                    type="button"
                                    class="link-button"
                                    :title="$t('spay_filter_by_supplier')"
                                    @click="filterBySupplier(row.supplier_id)"
                                >
                                    {{ row.supplier.name }}
                                </button>
                                <span v-else>—</span>
                                <span v-if="row.purchase_receipt" class="cell-secondary">
                                    <el-icon :size="11"><Document /></el-icon>
                                    <span dir="ltr">{{ row.purchase_receipt.receipt_number }}</span>
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('payment_method')" min-width="150">
                        <template #default="{ row }">
                            <div class="cell-stack">
                                <span class="method-tag" :class="`m-${row.payment_method}`">
                                    <el-icon :size="13"><component :is="methodIcon(row.payment_method)" /></el-icon>
                                    {{ paymentMethodLabel(row.payment_method) }}
                                </span>
                                <span v-if="row.reference" class="cell-secondary" dir="auto">{{ row.reference }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('amount')" min-width="130" align="right">
                        <template #default="{ row }">
                            <strong class="amount">{{ formatCurrency(row.amount, row.currency) }}</strong>
                        </template>
                    </el-table-column>

                    <el-table-column width="64" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="$t('cancel_the_payment')" placement="top" :enterable="false">
                                <el-button
                                    size="small"
                                    circle
                                    text
                                    type="danger"
                                    :icon="RefreshLeft"
                                    :aria-label="$t('cancel_the_payment')"
                                    @click="cancelPayment(row)"
                                />
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="store.pagination.total > 0" class="pagination-row">
                    <span class="pagination-summary">
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

            <!-- ── Balances: a payment starts from the debt, not a blank form ── -->
            <aside class="panel-card balances-panel">
                <div class="balances-head">
                    <h3>{{ $t('supplier_balances') }}</h3>
                    <el-segmented v-if="store.advancesCount" v-model="balanceView" :options="balanceViews" size="small" />
                </div>

                <el-input
                    v-if="balanceList.length > 6"
                    v-model="balanceSearch"
                    size="small"
                    clearable
                    :prefix-icon="Search"
                    :placeholder="$t('spay_find_supplier')"
                    class="balances-search"
                />

                <el-empty
                    v-if="!visibleBalances.length"
                    :description="balanceView === 'owed' ? $t('nothing_owed_to_suppliers') : $t('spay_no_advances')"
                    :image-size="70"
                />

                <ul v-else class="balances-list">
                    <li v-for="supplier in visibleBalances" :key="supplier.id" :class="{ 'is-filtered': filters.supplier_id === supplier.id }">
                        <div class="balance-main">
                            <button type="button" class="link-button balance-name" @click="filterBySupplier(supplier.id)">
                                {{ supplier.name }}
                            </button>
                            <strong class="amount" :class="balanceView === 'owed' ? 'owed' : 'advance'">
                                {{ formatCurrency(Math.abs(supplier.balance), supplier.currency) }}
                            </strong>
                        </div>
                        <div class="balance-bar"><span :style="{ width: `${barWidth(supplier)}%` }" :class="balanceView" /></div>
                        <el-button
                            v-if="balanceView === 'owed'"
                            size="small"
                            type="primary"
                            plain
                            class="pay-button"
                            @click="openForm(supplier)"
                        >
                            {{ $t('pay') }}
                        </el-button>
                    </li>
                </ul>
            </aside>
        </div>

        <SupplierPaymentForm
            v-model="formVisible"
            :suppliers="store.outstanding"
            :preset-supplier-id="formSupplierId"
            @saved="onSaved"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Coin, CreditCard, Document, Money, Plus, Promotion, Refresh, RefreshLeft, Search, Tickets, Wallet,
} from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import SupplierPaymentForm from '@/components/admin/purchases/SupplierPaymentForm.vue';
import { useSupplierPaymentsStore } from '@/stores/supplierPayments';
import { apiErrorMessage, formatCurrency, formatDate, localIsoDate, paymentMethodLabel } from '@/utils/sales';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useSupplierPaymentsStore();

// Card is accepted by the API (a goods receipt can be paid by card), so it can
// be filtered on even though this screen never records one.
const METHOD_FILTERS = ['bank_transfer', 'cash', 'check', 'card'];
const METHOD_ICONS = { bank_transfer: CreditCard, cash: Money, check: Tickets, card: CreditCard };
const methodIcon = (method) => METHOD_ICONS[method] || Coin;

// ── Filters and paging, kept in the URL ───────────────────────────────────
const filters = reactive({ search: '', supplier_id: null, method: '', range: null });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.supplier_id = Number(q.supplier_id) || null;
    filters.method = METHOD_FILTERS.includes(q.method) ? q.method : '';
    filters.range = q.from && q.to ? [String(q.from), String(q.to)] : null;
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        supplier_id: filters.supplier_id || undefined,
        method: filters.method || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.supplier_id, filters.method, filters.range?.length ? '1' : '',
].filter(Boolean).length);

const fetchPayments = () => store.fetchPayments({
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search || undefined,
    supplier_id: filters.supplier_id || undefined,
    payment_method: filters.method || undefined,
    date_from: filters.range?.[0] || undefined,
    date_to: filters.range?.[1] || undefined,
}).catch(() => {});

const reload = () => Promise.all([fetchPayments(), store.fetchOutstanding().catch(() => {})]);

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

let searchTimer = null;
const onSearchInput = (value) => {
    clearTimeout(searchTimer);
    if (!value) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = () => {
    Object.assign(filters, { search: '', supplier_id: null, method: '', range: null });
    applyFilters();
};

const filterBySupplier = (id) => {
    filters.supplier_id = filters.supplier_id === id ? null : id;
    applyFilters();
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
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_last_90_days'), value: () => [at(y, m, now.getDate() - 90), localIsoDate(now)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

// The paid card says which period it adds up, rather than "in period" with
// no period chosen.
const periodLabel = computed(() => {
    if (!filters.range?.length) return t('spay_all_time');
    const [from, to] = filters.range;
    return `${formatDate(from)} – ${formatDate(to)}`;
});

// ── Cards ────────────────────────────────────────────────────────────────
const methodSplit = computed(() => {
    const total = Object.values(store.byMethod).reduce((sum, m) => sum + Number(m.total || 0), 0);
    if (!total) return [];
    return Object.entries(store.byMethod)
        .map(([method, m]) => ({ method, total: Number(m.total || 0), share: Number(m.total || 0) / total }))
        .filter((p) => p.total > 0)
        .sort((a, b) => b.total - a.total);
});

const methodSplitText = computed(() => methodSplit.value
    .map((p) => `${paymentMethodLabel(p.method)} ${Math.round(p.share * 100)}%`)
    .join(' · '));

// ── Balances panel ───────────────────────────────────────────────────────
const balanceView = ref('owed');
const balanceSearch = ref('');
const balanceViews = computed(() => [
    { label: t('spay_owed_tab', { count: store.owedCount }), value: 'owed' },
    { label: t('spay_advances_tab', { count: store.advancesCount }), value: 'advance' },
]);

const balanceList = computed(() => (balanceView.value === 'owed'
    ? store.outstanding.filter((s) => Number(s.balance) > 0.009)
    : store.outstanding.filter((s) => Number(s.balance) < -0.009).sort((a, b) => a.balance - b.balance)));

const visibleBalances = computed(() => {
    const q = balanceSearch.value.trim().toLowerCase();
    return q ? balanceList.value.filter((s) => s.name.toLowerCase().includes(q)) : balanceList.value;
});

const barWidth = (supplier) => {
    const max = Math.max(...balanceList.value.map((s) => Math.abs(Number(s.balance))), 1);
    return Math.max(2, Math.round((Math.abs(Number(supplier.balance)) / max) * 100));
};

// ── Form and cancelling ──────────────────────────────────────────────────
const formVisible = ref(false);
const formSupplierId = ref(null);

const openForm = (supplier = null) => {
    formSupplierId.value = supplier?.id ?? filters.supplier_id ?? null;
    formVisible.value = true;
};

const onSaved = () => {
    currentPage.value = 1;
    writeQuery();
    reload();
};

const cancelPayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            t('spay_cancel_confirm', {
                number: payment.payment_number,
                amount: formatCurrency(payment.amount, payment.currency),
                supplier: payment.supplier?.name || '—',
            }),
            t('cancel_the_payment'),
            {
                type: 'warning',
                confirmButtonText: t('cancel_the_payment'),
                cancelButtonText: t('spay_keep_payment'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await store.cancelPayment(payment.id);
        ElMessage.success(t('supplier_payment_cancelled'));
        await reload();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_cancel_supplier_payment')));
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

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.supplier-payments.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchPayments();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    reload();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.supplier-payments { font-family: 'Cairo', sans-serif; }

/* ── Cards ── */
.stat-card { border-radius: 14px; }
.stat-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
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
.method-bar-part { min-width: 3px; }
.m-bank_transfer { --m: #2563eb; }
.m-cash { --m: #16a34a; }
.m-check { --m: #d97706; }
.m-card { --m: #7c3aed; }
.method-bar-part { background: var(--m); }

/* ── Layout ── */
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1.25rem; align-items: start; }

.panel-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
}

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 170px; }
.filter-dates { max-width: 270px; }

/* ── Table ── */
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.amount.owed { color: #b91c1c; }
.amount.advance { color: #7c3aed; }

.link-button {
    all: unset;
    cursor: pointer;
    font-weight: 600;
    color: #0f172a;
}
.link-button:hover { color: #2563eb; text-decoration: underline; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

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

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
.pagination-summary { font-size: 0.85rem; color: #64748b; }

/* ── Balances ── */
.balances-panel { position: sticky; top: 1rem; }
.balances-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.balances-head h3 { margin: 0; font-size: 1rem; font-weight: 700; }
.balances-search { margin-bottom: 0.6rem; }

.balances-list { list-style: none; margin: 0; padding: 0; max-height: 560px; overflow-y: auto; }
.balances-list li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas: 'main pay' 'bar pay';
    gap: 0.35rem 0.75rem;
    align-items: center;
    padding: 0.65rem 0.35rem;
    border-bottom: 1px solid #f1f5f9;
    border-radius: 8px;
}
.balances-list li:last-child { border-bottom: none; }
.balances-list li.is-filtered { background: #eff6ff; }
.balance-main { grid-area: main; display: flex; justify-content: space-between; gap: 0.5rem; min-width: 0; }
.balance-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.balance-bar { grid-area: bar; height: 4px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.balance-bar span { display: block; height: 100%; border-radius: 999px; }
.balance-bar .owed { background: #fca5a5; }
.balance-bar .advance { background: #c4b5fd; }
.pay-button { grid-area: pay; }

@media (max-width: 1100px) {
    .layout { grid-template-columns: 1fr; }
    .balances-panel { position: static; order: -1; }
    .balances-list { max-height: 280px; }
}

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 1.05rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
}
</style>
