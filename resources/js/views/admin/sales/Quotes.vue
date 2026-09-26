<template>
    <div class="quotes-page">
        <AdminPageHeader
            icon="fas fa-file-signature"
            :title="$t('quotes')"
            :subtitle="$t('qt_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="fetchQuotes" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="openForm()">{{ $t('qt_new') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Where the pipeline stands ── -->
        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.status === 'open' }" @click="setStatus('open')">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.open_value) }}</h3>
                        <p>{{ $t('qt_open_pipeline') }}</p>
                        <span class="stat-sub">{{ $t('qt_open_split', { draft: count('draft'), sent: count('sent') }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.status === 'sent' }" @click="setStatus('sent')">
                <div class="stat-inner">
                    <div class="stat-icon orange"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-details">
                        <h3>{{ count('sent') }}</h3>
                        <p>{{ $t('qt_awaiting_reply') }}</p>
                        <span class="stat-sub" :class="{ warn: summary.expiring }">
                            {{ $t('qt_expiring_n', { count: summary.expiring || 0 }) }}
                        </span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.status === 'accepted' }" @click="setStatus('accepted')">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(value('accepted')) }}</h3>
                        <p>{{ $t('qt_won', { count: count('accepted') }) }}</p>
                        <span class="stat-sub">{{ $t('qt_converted_n', { count: summary.converted || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon purple"><i class="fas fa-percent"></i></div>
                    <div class="stat-details">
                        <h3>{{ summary.win_rate === null || summary.win_rate === undefined ? '—' : `${summary.win_rate}%` }}</h3>
                        <p>{{ $t('qt_win_rate') }}</p>
                        <span class="stat-sub">{{ $t('qt_win_rate_hint') }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- ── What needs doing ── -->
        <div v-if="summary.lapsed || summary.awaiting_conversion" class="attention">
            <button v-if="summary.awaiting_conversion" type="button" class="attention-item ok" @click="showAwaitingConversion">
                <i class="fas fa-right-left"></i>
                <span>{{ $t('qt_attention_convert', { count: summary.awaiting_conversion }) }}</span>
                <span class="attention-cta">{{ $t('qt_show') }}</span>
            </button>
            <button v-if="summary.lapsed" type="button" class="attention-item warn" @click="setValidity('lapsed')">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ $t('qt_attention_lapsed', { count: summary.lapsed }) }}</span>
                <span class="attention-cta">{{ $t('qt_show') }}</span>
            </button>
        </div>

        <section class="panel-card">
            <!-- Status tabs, with counts across the whole search -->
            <div class="status-tabs" role="tablist">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    class="status-tab"
                    :class="[`t-${tab.value || 'all'}`, { 'is-on': filters.status === tab.value }]"
                    :aria-selected="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span class="status-tab-count">{{ tab.count }}</span>
                </button>
            </div>

            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('qt_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.validity" class="filter-select" :placeholder="$t('qt_any_validity')" clearable @change="applyFilters">
                    <el-option value="expiring" :label="$t('qt_validity_expiring')" />
                    <el-option value="lapsed" :label="$t('qt_validity_lapsed')" />
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

            <el-result v-if="store.error && !store.quotes.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchQuotes">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.quotes"
                row-key="id"
                style="width: 100%"
                class="quotes-table"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
                @row-click="(row, column) => column?.property !== 'actions' && openDetail(row)"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && (activeFilterCount || filters.status)" :description="$t('there_are_no_offers_matching_your_search')" :image-size="90">
                        <el-button @click="resetFilters(true)">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('no_quotes_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="openForm()">{{ $t('qt_new') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column prop="quote_number" :label="$t('quote_number')" min-width="135" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.quote_number }}</span>
                            <span class="cell-secondary">{{ formatDate(row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="180">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="strong">{{ customerName(row) }}</span>
                            <span v-if="row.customer?.phone" class="cell-secondary" dir="ltr">{{ row.customer.phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="total" :label="$t('total')" min-width="130" align="right" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack align-end">
                            <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                            <span class="cell-secondary">{{ $t('qt_items_n', { count: row.items_count ?? 0 }) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="valid_until" :label="$t('valid_until')" min-width="140" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span>{{ row.valid_until ? formatDate(row.valid_until) : '—' }}</span>
                            <span v-if="validity(row)" class="validity" :class="validity(row).tone">{{ validity(row).text }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="150">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="status-pill" :class="`s-${row.status}`">
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                            </span>
                            <button v-if="row.sales_order" type="button" class="link-button order-link" @click.stop="goToOrder(row.sales_order)">
                                <i class="fas fa-cart-shopping"></i>
                                <span dir="ltr">{{ row.sales_order.order_number }}</span>
                            </button>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="actions" :label="$t('actions')" min-width="200" align="center">
                    <template #default="{ row }">
                        <div class="row-actions" @click.stop>
                            <el-button
                                v-if="nextStep(row)"
                                size="small"
                                :type="nextStep(row).type"
                                plain
                                :loading="busyId === row.id"
                                @click="nextStep(row).run()"
                            >
                                <i class="fas" :class="nextStep(row).icon"></i>&nbsp;{{ nextStep(row).label }}
                            </el-button>
                            <el-dropdown trigger="click" @command="(cmd) => runCommand(row, cmd)">
                                <el-button size="small" text circle :aria-label="$t('qt_more_actions')">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="view"><i class="fas fa-eye"></i> {{ $t('view_details') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="row.is_editable" command="edit"><i class="fas fa-pen"></i> {{ $t('edit') }}</el-dropdown-item>
                                        <el-dropdown-item command="duplicate"><i class="fas fa-copy"></i> {{ $t('qt_duplicate') }}</el-dropdown-item>
                                        <el-dropdown-item command="print"><i class="fas fa-print"></i> {{ $t('print') }}</el-dropdown-item>
                                        <el-dropdown-item
                                            v-for="status in row.allowed_statuses || []"
                                            :key="status"
                                            :command="`status:${status}`"
                                            :divided="status === (row.allowed_statuses || [])[0]"
                                        >
                                            <i class="fas" :class="statusIcon(status)"></i> {{ moveLabel(row.status, status) }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="!row.sales_order" command="delete" divided class="danger-item">
                                            <i class="fas fa-trash"></i> {{ $t('delete') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
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

        <!-- ── Detail drawer ── -->
        <el-drawer v-model="detailVisible" :size="isNarrow ? '100%' : '640px'" class="quote-drawer" :with-header="false">
            <div v-if="selected" class="drawer">
                <header class="drawer-head">
                    <div>
                        <span class="mono drawer-number" dir="ltr">{{ selected.quote_number }}</span>
                        <p class="drawer-sub">{{ $t('qt_created_by', { date: formatDate(selected.created_at), name: selected.creator?.name || '—' }) }}</p>
                    </div>
                    <div class="drawer-head-actions">
                        <span class="status-pill" :class="`s-${selected.status}`">
                            <i class="fas" :class="statusIcon(selected.status)"></i>
                            {{ statusLabel(selected.status) }}
                        </span>
                        <el-button text circle :aria-label="$t('close')" @click="detailVisible = false"><i class="fas fa-xmark"></i></el-button>
                    </div>
                </header>

                <!-- Where the quote is on its way to an order -->
                <ol class="journey">
                    <li v-for="step in journey(selected)" :key="step.key" :class="step.state">
                        <span class="journey-dot"><i class="fas" :class="step.icon"></i></span>
                        <span class="journey-label">{{ step.label }}</span>
                    </li>
                </ol>

                <el-alert
                    v-if="selected.is_past_validity"
                    type="warning"
                    show-icon
                    :closable="false"
                    :title="$t('qt_lapsed_alert')"
                    class="drawer-alert"
                />

                <dl class="facts">
                    <div>
                        <dt>{{ $t('client') }}</dt>
                        <dd>{{ customerName(selected) }}</dd>
                        <dd v-if="selected.customer?.phone" class="fact-sub" dir="ltr">{{ selected.customer.phone }}</dd>
                    </div>
                    <div>
                        <dt>{{ $t('valid_until') }}</dt>
                        <dd>{{ selected.valid_until ? formatDate(selected.valid_until) : $t('qt_no_expiry') }}</dd>
                        <dd v-if="validity(selected)" class="fact-sub validity" :class="validity(selected).tone">{{ validity(selected).text }}</dd>
                    </div>
                    <div v-if="selected.sales_order">
                        <dt>{{ $t('qt_sales_order') }}</dt>
                        <dd>
                            <button type="button" class="link-button order-link" @click="goToOrder(selected.sales_order)">
                                <span dir="ltr">{{ selected.sales_order.order_number }}</span>
                                · {{ statusLabel(selected.sales_order.status) }}
                            </button>
                        </dd>
                    </div>
                </dl>

                <div class="drawer-lines">
                    <div class="dl-row dl-head">
                        <span>{{ $t('product') }}</span>
                        <span class="num">{{ $t('quantity') }}</span>
                        <span class="num">{{ $t('unit_price') }}</span>
                        <span class="num">{{ $t('total') }}</span>
                    </div>
                    <el-skeleton v-if="detailLoading && !selected.items" :rows="3" animated />
                    <div v-for="item in selected.items || []" :key="item.id" class="dl-row">
                        <span class="dl-name">
                            {{ item.description || item.product?.name_ar || '—' }}
                            <small v-if="Number(item.discount) || Number(item.tax)" class="cell-secondary">
                                <template v-if="Number(item.discount)">{{ $t('discount') }} −{{ formatCurrency(item.discount) }}</template>
                                <template v-if="Number(item.discount) && Number(item.tax)"> · </template>
                                <template v-if="Number(item.tax)">{{ $t('tax') }} +{{ formatCurrency(item.tax) }}</template>
                            </small>
                        </span>
                        <span class="num">{{ item.quantity }}</span>
                        <span class="num">{{ formatCurrency(item.unit_price) }}</span>
                        <span class="num strong">{{ formatCurrency(item.total) }}</span>
                    </div>
                </div>

                <div class="drawer-totals">
                    <div><span>{{ $t('subtotal') }}</span><span class="num">{{ formatCurrency(selected.subtotal) }}</span></div>
                    <div v-if="Number(selected.discount)"><span>{{ $t('qt_extra_discount') }}</span><span class="num">−{{ formatCurrency(selected.discount) }}</span></div>
                    <div v-if="Number(selected.tax)"><span>{{ $t('qt_extra_tax') }}</span><span class="num">+{{ formatCurrency(selected.tax) }}</span></div>
                    <div class="grand"><span>{{ $t('total') }}</span><span class="num">{{ formatCurrency(selected.total) }}</span></div>
                </div>

                <div v-if="selected.notes || selected.terms" class="drawer-notes">
                    <div v-if="selected.notes"><h5>{{ $t('notes') }}</h5><p>{{ selected.notes }}</p></div>
                    <div v-if="selected.terms"><h5>{{ $t('qt_terms') }}</h5><p>{{ selected.terms }}</p></div>
                </div>

                <footer class="drawer-foot">
                    <el-button v-if="selected.is_editable" :icon="Edit" @click="openForm(selected)">{{ $t('edit') }}</el-button>
                    <el-button @click="duplicate(selected)"><i class="fas fa-copy"></i>&nbsp;{{ $t('qt_duplicate') }}</el-button>
                    <el-button @click="printQuote(selected)"><i class="fas fa-print"></i>&nbsp;{{ $t('print') }}</el-button>
                    <span class="spacer" />
                    <el-button
                        v-for="status in secondaryMoves(selected)"
                        :key="status"
                        :type="status === 'rejected' ? 'danger' : 'default'"
                        plain
                        @click="changeStatus(selected, status)"
                    >
                        {{ moveLabel(selected.status, status) }}
                    </el-button>
                    <el-button
                        v-if="nextStep(selected)"
                        :type="nextStep(selected).type"
                        :loading="busyId === selected.id"
                        @click="nextStep(selected).run()"
                    >
                        <i class="fas" :class="nextStep(selected).icon"></i>&nbsp;{{ nextStep(selected).label }}
                    </el-button>
                </footer>
            </div>
        </el-drawer>

        <QuoteForm v-model="formVisible" :quote="editing" @saved="onSaved" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Edit, Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import QuoteForm from '@/components/admin/sales/QuoteForm.vue';
import { useQuotesStore } from '@/stores/quotes';
import { useSettingsStore } from '@/stores/settings';
import {
    QUOTE_STATUSES,
    apiErrorMessage,
    customerName,
    formatCurrency,
    formatDate,
    localIsoDate,
    statusIcon,
    statusLabel,
} from '@/utils/sales';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useQuotesStore();
const settings = useSettingsStore();

// 'open' is draft + sent together: what is still in play.
const STATUS_FILTERS = ['', 'open', ...QUOTE_STATUSES];

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary || { by_status: {}, open_value: 0, lapsed: 0, expiring: 0, converted: 0, awaiting_conversion: 0, win_rate: null, total: 0 });
const count = (status) => summary.value.by_status?.[status]?.count || 0;
const value = (status) => summary.value.by_status?.[status]?.value || 0;

const statusTabs = computed(() => [
    { value: '', label: t('all'), count: summary.value.total || 0 },
    ...QUOTE_STATUSES.map((status) => ({ value: status, label: statusLabel(status), count: count(status) })),
]);

// ── Filters, sorting and paging, kept in the URL ─────────────────────────
const filters = reactive({ search: '', status: '', validity: '', converted: '', range: null });
const sort = reactive({ prop: 'created_at', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(20);

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.status = STATUS_FILTERS.includes(q.status) ? q.status : '';
    filters.validity = ['lapsed', 'expiring'].includes(q.validity) ? q.validity : '';
    filters.converted = q.converted === '0' ? '0' : '';
    filters.range = q.from && q.to ? [String(q.from), String(q.to)] : null;
    sort.prop = ['total', 'valid_until', 'quote_number'].includes(q.sort) ? q.sort : 'created_at';
    sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        status: filters.status || undefined,
        validity: filters.validity || undefined,
        converted: filters.converted || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        sort: sort.prop !== 'created_at' ? sort.prop : undefined,
        direction: sort.order === 'ascending' ? 'asc' : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.validity, filters.converted, filters.range?.length ? '1' : '',
].filter(Boolean).length);

const fetchQuotes = () => {
    // 'open' is draft and sent together, which the API takes as its own flag.
    const status = filters.status === 'open' ? undefined : filters.status || undefined;
    return store.fetchQuotes({
        page: currentPage.value,
        per_page: pageSize.value,
        search: filters.search || undefined,
        status,
        open: filters.status === 'open' ? 1 : undefined,
        validity: filters.validity || undefined,
        converted: filters.converted || undefined,
        date_from: filters.range?.[0] || undefined,
        date_to: filters.range?.[1] || undefined,
        sort: sort.prop,
        direction: sort.order === 'ascending' ? 'asc' : 'desc',
    }).catch(() => {});
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchQuotes();
};

let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = (withStatus = false) => {
    Object.assign(filters, { search: '', validity: '', converted: '', range: null });
    if (withStatus === true) filters.status = '';
    applyFilters();
};

const setStatus = (status) => {
    filters.status = filters.status === status && status ? '' : status;
    filters.converted = '';
    applyFilters();
};

const setValidity = (validity) => {
    filters.validity = validity;
    filters.status = '';
    filters.converted = '';
    applyFilters();
};

const showAwaitingConversion = () => {
    filters.status = 'accepted';
    filters.converted = '0';
    filters.validity = '';
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'created_at';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    fetchQuotes();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchQuotes();
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

// ── Validity ─────────────────────────────────────────────────────────────
const daysUntil = (date) => {
    const [y, m, d] = String(date).slice(0, 10).split('-').map(Number);
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    return Math.round((new Date(y, m - 1, d) - start) / 86400000);
};

/** "3 days left" / "lapsed 2 days ago" — only while the quote is still open. */
const validity = (quote) => {
    if (!quote?.valid_until || !['draft', 'sent'].includes(quote.status)) return null;
    const days = daysUntil(quote.valid_until);
    if (days < 0) return { tone: 'bad', text: t('qt_lapsed_days', { count: -days }) };
    if (days === 0) return { tone: 'warn', text: t('qt_last_day') };
    if (days <= 7) return { tone: 'warn', text: t('qt_days_left', { count: days }) };
    return { tone: 'calm', text: t('qt_days_left', { count: days }) };
};

// ── Next step: the one move each quote is waiting for ────────────────────
const busyId = ref(null);

const nextStep = (quote) => {
    if (!quote) return null;
    if (quote.sales_order) {
        return { label: t('qt_open_order'), icon: 'fa-arrow-up-right-from-square', type: 'info', run: () => goToOrder(quote.sales_order) };
    }
    switch (quote.status) {
        case 'draft':
            return { label: t('qt_mark_sent'), icon: 'fa-paper-plane', type: 'primary', run: () => changeStatus(quote, 'sent') };
        case 'sent':
            // An offer that no longer stands is extended first, not accepted.
            return quote.is_past_validity
                ? { label: t('qt_extend'), icon: 'fa-calendar-plus', type: 'warning', run: () => openForm(quote) }
                : { label: t('qt_mark_accepted'), icon: 'fa-circle-check', type: 'success', run: () => changeStatus(quote, 'accepted') };
        case 'accepted':
            return { label: t('convert_to_sales_order'), icon: 'fa-right-left', type: 'success', run: () => convertToOrder(quote) };
        default:
            return { label: t('qt_duplicate'), icon: 'fa-copy', type: 'default', run: () => duplicate(quote) };
    }
};

// Status moves other than the one the next-step button already offers.
const secondaryMoves = (quote) => {
    const primary = { draft: 'sent', sent: quote.is_past_validity ? null : 'accepted' }[quote.status];
    return (quote.allowed_statuses || []).filter((s) => s !== primary);
};

const moveLabel = (from, to) => {
    if (to === 'draft') return t('qt_back_to_draft');
    if (from === 'accepted' && to === 'sent') return t('qt_undo_acceptance');
    return t(`qt_move_${to}`);
};

const journey = (quote) => {
    const order = ['draft', 'sent', 'accepted', 'ordered'];
    const reached = quote.sales_order ? 3 : Math.max(0, order.indexOf(quote.status));
    const failed = ['rejected', 'expired'].includes(quote.status);
    const steps = [
        { key: 'draft', label: statusLabel('draft'), icon: 'fa-pen' },
        { key: 'sent', label: statusLabel('sent'), icon: 'fa-paper-plane' },
        failed
            ? { key: quote.status, label: statusLabel(quote.status), icon: statusIcon(quote.status) }
            : { key: 'accepted', label: statusLabel('accepted'), icon: 'fa-circle-check' },
        { key: 'ordered', label: t('qt_sales_order'), icon: 'fa-cart-shopping' },
    ];
    return steps.map((step, i) => {
        if (failed) return { ...step, state: i < 2 ? 'done' : i === 2 ? 'failed' : 'todo' };
        return { ...step, state: i < reached ? 'done' : i === reached ? 'current' : 'todo' };
    });
};

// ── Detail ───────────────────────────────────────────────────────────────
const detailVisible = ref(false);
const detailLoading = ref(false);
const selected = ref(null);

const openDetail = async (quote) => {
    selected.value = quote;
    detailVisible.value = true;
    detailLoading.value = true;
    try {
        selected.value = await store.fetchQuote(quote.id);
    } catch {
        // Keep the list row rather than emptying the drawer.
    } finally {
        detailLoading.value = false;
    }
};

const refreshSelected = (quote) => {
    if (quote && selected.value?.id === quote.id) selected.value = quote;
};

// ── Actions ──────────────────────────────────────────────────────────────
const changeStatus = async (quote, status) => {
    try {
        await ElMessageBox.confirm(
            t('qt_move_confirm', { number: quote.quote_number, status: statusLabel(status) }),
            t('confirm'),
            { type: status === 'rejected' ? 'warning' : 'info', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    busyId.value = quote.id;
    try {
        const updated = await store.updateQuoteStatus(quote, status);
        refreshSelected(updated);
        ElMessage.success(t('quote_status_updated'));
        fetchQuotes();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_update_quote_status')));
    } finally {
        busyId.value = null;
    }
};

const convertToOrder = async (quote) => {
    try {
        await ElMessageBox.confirm(
            t('qt_convert_confirm', { number: quote.quote_number, total: formatCurrency(quote.total) }),
            t('convert_to_sales_order'),
            { type: 'info', confirmButtonText: t('convert'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    busyId.value = quote.id;
    try {
        const order = await store.convertToSalesOrder(quote.id);
        ElMessage.success({ message: t('qt_converted', { number: order?.order_number || '' }), duration: 4000 });
        detailVisible.value = false;
        if (order?.id) goToOrder(order);
    } catch (error) {
        // Already converted: take them to the order it became.
        const existing = error.response?.status === 409 ? error.response.data?.data : null;
        ElMessage.error(apiErrorMessage(error, t('failed_to_convert_quote')));
        if (existing?.id) goToOrder(existing);
        else fetchQuotes();
    } finally {
        busyId.value = null;
    }
};

const duplicate = async (quote) => {
    try {
        const copy = await store.duplicateQuote(quote.id);
        ElMessage.success(t('qt_duplicated', { number: copy?.quote_number || '' }));
        await fetchQuotes();
        // Straight into the copy: a duplicate is nearly always edited next.
        if (copy) openForm(copy);
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('qt_duplicate_failed')));
    }
};

const removeQuote = async (quote) => {
    try {
        await ElMessageBox.confirm(
            t('qt_delete_confirm', { number: quote.quote_number }),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await store.deleteQuote(quote.id);
        ElMessage.success(t('quote_deleted'));
        if (selected.value?.id === quote.id) detailVisible.value = false;
        fetchQuotes();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_quote')));
    }
};

const runCommand = (quote, command) => {
    if (command === 'view') return openDetail(quote);
    if (command === 'edit') return openForm(quote);
    if (command === 'duplicate') return duplicate(quote);
    if (command === 'print') return printQuote(quote);
    if (command === 'delete') return removeQuote(quote);
    if (command.startsWith('status:')) return changeStatus(quote, command.slice(7));
    return null;
};

const goToOrder = (order) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: order.id } });
};

// ── Form ─────────────────────────────────────────────────────────────────
const formVisible = ref(false);
const editing = ref(null);

const openForm = async (quote = null) => {
    editing.value = null;
    if (quote) {
        // The form needs the lines; list rows carry only their count.
        try {
            editing.value = quote.items ? quote : await store.fetchQuote(quote.id);
        } catch (error) {
            ElMessage.error(apiErrorMessage(error, t('qt_load_failed')));
            return;
        }
    }
    formVisible.value = true;
};

const onSaved = (quote) => {
    refreshSelected(quote);
    fetchQuotes();
};

// ── Print ────────────────────────────────────────────────────────────────
const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const printQuote = async (quote) => {
    const win = window.open('', '_blank');
    if (!win) {
        ElMessage.warning(t('qt_popup_blocked'));
        return;
    }
    win.document.write(`<p style="font-family:sans-serif;padding:2rem">${escapeHtml(t('loading'))}</p>`);

    let full = quote;
    try {
        full = quote.items ? quote : await store.fetchQuote(quote.id);
        if (!Object.keys(settings.data || {}).length) await settings.fetch();
    } catch {
        // Print what we have.
    }

    const s = settings.data || {};
    const rtl = locale.value !== 'en';
    const company = (rtl ? s.site_name : s.site_name_en || s.site_name) || '';
    const rows = (full.items || []).map((item, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${escapeHtml(item.description || item.product?.name_ar || '—')}</td>
            <td class="n">${escapeHtml(item.quantity)}</td>
            <td class="n">${escapeHtml(formatCurrency(item.unit_price))}</td>
            <td class="n">${Number(item.discount) ? escapeHtml(formatCurrency(item.discount)) : '—'}</td>
            <td class="n">${escapeHtml(formatCurrency(item.total))}</td>
        </tr>`).join('');
    const totalRow = (label, amount) => `<tr><td>${escapeHtml(label)}</td><td class="n">${escapeHtml(amount)}</td></tr>`;

    win.document.open();
    win.document.write(`<!doctype html><html dir="${rtl ? 'rtl' : 'ltr'}" lang="${rtl ? 'ar' : 'en'}"><head><meta charset="utf-8">
        <title>${escapeHtml(full.quote_number)}</title>
        <style>
            body { font-family: 'Cairo', Tahoma, sans-serif; color: #0f172a; margin: 2rem; font-size: 13px; }
            header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.25rem; }
            h1 { margin: 0; font-size: 1.5rem; } h2 { margin: 0; font-size: 1.1rem; }
            .muted { color: #64748b; } .n { text-align: ${rtl ? 'left' : 'right'}; font-variant-numeric: tabular-nums; }
            .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
            .meta b { display: block; font-size: 0.8rem; color: #64748b; font-weight: 600; }
            table { width: 100%; border-collapse: collapse; }
            .lines th, .lines td { border-bottom: 1px solid #e2e8f0; padding: 0.45rem 0.4rem; text-align: ${rtl ? 'right' : 'left'}; }
            .lines th { background: #f1f5f9; font-size: 0.8rem; }
            .totals { width: 280px; margin-${rtl ? 'right' : 'left'}: auto; margin-top: 1rem; }
            .totals td { padding: 0.3rem 0.4rem; } .totals tr:last-child td { font-weight: 800; font-size: 1.05rem; border-top: 2px solid #0f172a; }
            .notes { margin-top: 1.5rem; white-space: pre-line; } .notes h3 { font-size: 0.9rem; margin: 0 0 0.25rem; }
            @media print { body { margin: 1cm; } }
        </style></head><body>
        <header>
            <div><h1>${escapeHtml(t('qt_print_heading'))}</h1><div class="muted" dir="ltr">${escapeHtml(full.quote_number)}</div></div>
            <div style="text-align:${rtl ? 'left' : 'right'}"><h2>${escapeHtml(company)}</h2>
                <div class="muted">${escapeHtml(s.contact_address || s.address || '')}</div>
                <div class="muted" dir="ltr">${escapeHtml(s.contact_phone || s.phone || '')}</div></div>
        </header>
        <div class="meta">
            <div><b>${escapeHtml(t('client'))}</b>${escapeHtml(customerName(full))}<div class="muted" dir="ltr">${escapeHtml(full.customer?.phone || '')}</div></div>
            <div><b>${escapeHtml(t('qt_issue_date'))}</b>${escapeHtml(formatDate(full.created_at))}</div>
            <div><b>${escapeHtml(t('valid_until'))}</b>${escapeHtml(full.valid_until ? formatDate(full.valid_until) : t('qt_no_expiry'))}</div>
        </div>
        <table class="lines"><thead><tr>
            <th>#</th><th>${escapeHtml(t('product'))}</th><th class="n">${escapeHtml(t('quantity'))}</th>
            <th class="n">${escapeHtml(t('unit_price'))}</th><th class="n">${escapeHtml(t('discount'))}</th><th class="n">${escapeHtml(t('total'))}</th>
        </tr></thead><tbody>${rows}</tbody></table>
        <table class="totals">
            ${totalRow(t('subtotal'), formatCurrency(full.subtotal))}
            ${Number(full.discount) ? totalRow(t('qt_extra_discount'), `−${formatCurrency(full.discount)}`) : ''}
            ${Number(full.tax) ? totalRow(t('qt_extra_tax'), formatCurrency(full.tax)) : ''}
            ${totalRow(t('total'), formatCurrency(full.total))}
        </table>
        ${full.terms ? `<div class="notes"><h3>${escapeHtml(t('qt_terms'))}</h3>${escapeHtml(full.terms)}</div>` : ''}
        ${full.notes ? `<div class="notes"><h3>${escapeHtml(t('notes'))}</h3>${escapeHtml(full.notes)}</div>` : ''}
        <script>window.onload = () => { window.focus(); window.print(); };<\/script>
        </body></html>`);
    win.document.close();
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// Reusing the component for a new URL (the sidebar link) re-reads it.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.quotes.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchQuotes();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    fetchQuotes();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.quotes-page { font-family: 'Cairo', sans-serif; }

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
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.orange { background: #fff7ed; color: #ea580c; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
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
.stat-sub.warn { color: #d97706; font-weight: 600; }

/* ── Attention strip ── */
.attention { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1rem; }
.attention-item {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem 0.9rem;
    border-radius: 10px;
    font-size: 0.86rem;
    border: 1px solid;
}
.attention-item.warn { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.attention-item.ok { background: #f0fdf4; border-color: #86efac; color: #166534; }
.attention-item:hover { filter: brightness(0.97); }
.attention-item:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.attention-cta { font-weight: 700; text-decoration: underline; }

/* ── Panel ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }

.status-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.9rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.status-tab {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    color: #475569;
}
.status-tab:hover { background: #f1f5f9; }
.status-tab.is-on { background: #0f172a; color: #fff; }
.status-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.status-tab-count { font-size: 0.72rem; font-weight: 700; background: rgba(100, 116, 139, 0.14); border-radius: 999px; padding: 0 0.45rem; min-width: 1.2rem; text-align: center; }
.status-tab.is-on .status-tab-count { background: rgba(255, 255, 255, 0.2); }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 260px; max-width: 360px; }
.filter-select { width: 190px; }
.filter-dates { max-width: 270px; }

/* ── Table ── */
.quotes-table :deep(.el-table__row) { cursor: pointer; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-stack.align-end { align-items: flex-end; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; }
.strong { font-weight: 600; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }

.validity { font-size: 0.76rem; font-weight: 600; }
.validity.calm { color: #64748b; font-weight: 400; }
.validity.warn { color: #d97706; }
.validity.bad { color: #dc2626; }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 11%, #fff);
}
.s-draft { --c: #64748b; }
.s-sent { --c: #2563eb; }
.s-accepted { --c: #16a34a; }
.s-rejected { --c: #dc2626; }
.s-expired { --c: #b45309; }

.link-button { all: unset; cursor: pointer; font-weight: 600; color: #2563eb; }
.link-button:hover { text-decoration: underline; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }
.order-link { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.78rem; }

.row-actions { display: inline-flex; align-items: center; gap: 0.25rem; }
:deep(.danger-item) { color: #dc2626; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
.pagination-summary { font-size: 0.85rem; color: #64748b; }

/* ── Drawer ── */
.drawer { display: flex; flex-direction: column; gap: 1.1rem; min-height: 100%; font-family: 'Cairo', sans-serif; }
.drawer-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
.drawer-number { font-size: 1.3rem; }
.drawer-sub { margin: 0.1rem 0 0; font-size: 0.8rem; color: #64748b; }
.drawer-head-actions { display: flex; align-items: center; gap: 0.4rem; }
.drawer-alert { margin-top: -0.3rem; }

.journey { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); }
.journey li { position: relative; display: flex; flex-direction: column; align-items: center; gap: 0.3rem; font-size: 0.76rem; color: #94a3b8; text-align: center; }
.journey li::before {
    content: '';
    position: absolute;
    top: 14px;
    inset-inline-start: -50%;
    width: 100%;
    height: 2px;
    background: #e2e8f0;
    z-index: 0;
}
.journey li:first-child::before { display: none; }
.journey-dot {
    position: relative;
    z-index: 1;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    font-size: 0.72rem;
}
.journey li.done { color: #16a34a; }
.journey li.done .journey-dot { background: #16a34a; color: #fff; }
.journey li.done::before, .journey li.current::before, .journey li.failed::before { background: #16a34a; }
.journey li.current { color: #2563eb; font-weight: 700; }
.journey li.current .journey-dot { background: #2563eb; color: #fff; box-shadow: 0 0 0 4px #dbeafe; }
.journey li.failed { color: #dc2626; font-weight: 700; }
.journey li.failed .journey-dot { background: #dc2626; color: #fff; }

.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem 1.25rem; margin: 0; padding: 0.85rem 1rem; background: #f8fafc; border-radius: 10px; }
.facts dt { font-size: 0.74rem; color: #64748b; }
.facts dd { margin: 0.1rem 0 0; font-weight: 600; }
.facts .fact-sub { font-weight: 400; font-size: 0.8rem; color: #64748b; }

.drawer-lines { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.dl-row { display: grid; grid-template-columns: minmax(0, 1fr) 60px 110px 120px; gap: 0.5rem; padding: 0.55rem 0.8rem; border-bottom: 1px solid #f1f5f9; font-size: 0.86rem; align-items: center; }
.dl-row:last-child { border-bottom: none; }
.dl-head { background: #f8fafc; font-size: 0.74rem; color: #64748b; font-weight: 600; }
.dl-name { display: flex; flex-direction: column; min-width: 0; }
.num { text-align: end; font-variant-numeric: tabular-nums; }

.drawer-totals { align-self: flex-end; width: min(300px, 100%); }
.drawer-totals > div { display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.88rem; }
.drawer-totals .grand { border-top: 2px solid #0f172a; margin-top: 0.25rem; padding-top: 0.5rem; font-weight: 800; font-size: 1.05rem; }

.drawer-notes { display: grid; gap: 0.75rem; }
.drawer-notes h5 { margin: 0 0 0.2rem; font-size: 0.8rem; color: #64748b; }
.drawer-notes p { margin: 0; white-space: pre-line; font-size: 0.88rem; }

.drawer-foot {
    margin-top: auto;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    padding-top: 0.9rem;
    border-top: 1px solid #f1f5f9;
    position: sticky;
    bottom: 0;
    background: #fff;
}
.drawer-foot .el-button + .el-button { margin-inline-start: 0; }
.spacer { flex: 1; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .status-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .status-tab { white-space: nowrap; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: 100%; }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
    .dl-row { grid-template-columns: minmax(0, 1fr) 40px 90px; }
    .dl-row > :nth-child(3) { display: none; }
}
</style>
