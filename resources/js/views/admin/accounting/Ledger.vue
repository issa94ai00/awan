<template>
    <div class="accounting-page accounting-ledger">
        <AdminPageHeader
            icon="fas fa-list-ol text-primary"
            :title="$t('ledger')"
            :subtitle="$t('ledger_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button :loading="store.loading" @click="load">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" @click="openCreateDrawer()">
                    <i class="fas fa-plus mr-1"></i> {{ $t('add_new_account') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── One card per type; each filters the chart below ─────────────── -->
        <div class="type-grid">
            <button
                v-for="tp in TYPES"
                :key="tp"
                type="button"
                class="type-card"
                :class="['tone-' + tp, { 'is-active': filters.type === tp }]"
                :aria-pressed="filters.type === tp"
                @click="filters.type = filters.type === tp ? '' : tp"
            >
                <span class="type-icon"><i :class="TYPE_ICONS[tp]"></i></span>
                <span class="type-label">{{ $t('lg_type_' + tp) }}</span>
                <strong class="type-total" :class="{ 'is-negative': typeStats[tp].total < 0 }">{{ money(typeStats[tp].total) }}</strong>
                <span class="type-count">{{ $t('lg_accounts_count', { count: typeStats[tp].count }) }}</span>
            </button>
        </div>

        <!-- ── Chart ──────────────────────────────────────────────────────── -->
        <section class="lg-card">
            <header class="lg-card-head">
                <h2><i class="fas fa-sitemap"></i> {{ $t('lg_chart_title') }}</h2>
                <span class="count-pill">{{ $t('lg_shown_of', { shown: visibleCount, total: accounts.length }) }}</span>
            </header>

            <div class="toolbar">
                <el-input
                    v-model="filters.search"
                    :placeholder="$t('lg_search_placeholder')"
                    clearable
                    class="t-search"
                >
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>
                <el-segmented v-model="filters.status" :options="statusOptions" />
                <el-checkbox v-model="filters.hideZero" class="t-zero">{{ $t('lg_hide_zero') }}</el-checkbox>
                <el-button v-if="activeFilterCount" text class="clear-btn" @click="resetFilters">
                    <i class="fas fa-xmark mr-1"></i> {{ $t('lg_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-alert
                v-if="loadError"
                type="error"
                show-icon
                :closable="false"
                class="mb-3"
                :title="$t('lg_load_failed')"
            >
                <el-button size="small" type="danger" plain @click="load">{{ $t('dash_try_again') }}</el-button>
            </el-alert>

            <el-skeleton v-if="store.loading && !accounts.length" :rows="8" animated />

            <div v-else-if="!visibleTree.length && !loadError" class="empty-state">
                <span class="empty-icon"><i class="fas fa-sitemap"></i></span>
                <p>{{ accounts.length ? $t('lg_no_match') : $t('lg_no_accounts') }}</p>
                <el-button v-if="accounts.length" @click="resetFilters">{{ $t('lg_clear_filters', { count: activeFilterCount }) }}</el-button>
                <el-button v-else type="primary" @click="openCreateDrawer()"><i class="fas fa-plus mr-1"></i> {{ $t('add_new_account') }}</el-button>
            </div>

            <el-table
                v-else-if="visibleTree.length"
                :key="treeKey"
                v-loading="store.loading"
                :data="visibleTree"
                row-key="id"
                default-expand-all
                :tree-props="{ children: 'children' }"
                :row-class-name="rowClass"
                class="chart-table"
            >
                <el-table-column :label="$t('lg_col_account')" min-width="320">
                    <template #default="{ row }">
                        <span class="acc-cell">
                            <span class="code-badge">{{ row.code }}</span>
                            <span class="acc-main">
                                <button type="button" class="acc-name" @click="openStatementDrawer(row)">{{ row.name }}</button>
                                <span class="acc-meta">
                                    <span v-if="row.children?.length" class="meta-chip">{{ $t('lg_sub_accounts', { count: row.children.length }) }}</span>
                                    <el-tooltip v-if="row.posting_role" :content="$t('lg_role_hint', { role: row.posting_role })" placement="top">
                                        <span class="meta-chip is-role"><i class="fas fa-gear"></i> {{ $t('lg_system_account') }}</span>
                                    </el-tooltip>
                                    <span v-if="!row.is_active" class="meta-chip is-off">{{ $t('inactive') }}</span>
                                    <span v-if="row.description" class="acc-desc">{{ row.description }}</span>
                                </span>
                            </span>
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('lg_col_type')" width="130">
                    <template #default="{ row }">
                        <span class="type-chip" :class="'tone-' + row.type">{{ typeLabel(row.type) }}</span>
                    </template>
                </el-table-column>

                <!-- A parent holds nothing itself; what it shows is the sum of
                     the branch below it, labelled as such. -->
                <el-table-column :label="$t('lg_col_balance')" width="190" align="right">
                    <template #default="{ row }">
                        <span v-if="row.children?.length" class="bal is-rollup" :class="{ 'is-negative': row.rollup < 0, 'is-zero': row.rollup === 0 }">
                            <small>{{ $t('lg_total') }}</small> {{ money(row.rollup) }}
                        </span>
                        <span v-else class="bal" :class="{ 'is-negative': Number(row.balance) < 0, 'is-zero': Number(row.balance) === 0 }">
                            {{ money(row.balance) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column width="230" align="center">
                    <template #default="{ row }">
                        <span class="row-actions">
                            <el-button size="small" class="act-statement" @click="openStatementDrawer(row)">
                                <i class="fas fa-file-invoice mr-1"></i> {{ $t('lg_statement') }}
                            </el-button>
                            <el-tooltip :content="$t('lg_add_sub_account')" placement="top">
                                <el-button size="small" text class="act-icon" :aria-label="$t('lg_add_sub_account')" @click="openCreateDrawer(row)">
                                    <i class="fas fa-plus"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('edit')" placement="top">
                                <el-button size="small" text class="act-icon" :aria-label="$t('edit')" @click="openEditDrawer(row)">
                                    <i class="fas fa-pen"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="deleteBlocker(row) ? $t(deleteBlocker(row)) : $t('delete')" placement="top">
                                <span>
                                    <el-button
                                        size="small"
                                        text
                                        class="act-icon is-danger"
                                        :aria-label="$t('delete')"
                                        :disabled="!!deleteBlocker(row)"
                                        @click="deleteAccount(row)"
                                    >
                                        <i class="fas fa-trash-can"></i>
                                    </el-button>
                                </span>
                            </el-tooltip>
                        </span>
                    </template>
                </el-table-column>
            </el-table>
        </section>

        <!-- ── Create / edit ──────────────────────────────────────────────── -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? $t('lg_edit_title') : $t('lg_create_title')"
            size="min(520px, 100%)"
            direction="rtl"
            destroy-on-close
            class="lg-drawer"
        >
            <el-form :model="form" label-position="top" class="account-form" @submit.prevent>
                <el-form-item :label="$t('lg_col_type')" required>
                    <div class="type-picker">
                        <button
                            v-for="tp in TYPES"
                            :key="tp"
                            type="button"
                            class="type-option"
                            :class="['tone-' + tp, { 'is-active': form.type === tp }]"
                            :disabled="!!form.parent_id"
                            @click="setFormType(tp)"
                        >
                            <i :class="TYPE_ICONS[tp]"></i> {{ $t('lg_type_' + tp) }}
                        </button>
                    </div>
                    <p v-if="form.parent_id" class="field-hint">{{ $t('lg_type_follows_parent') }}</p>
                </el-form-item>

                <el-form-item :label="$t('lg_parent_account')">
                    <el-select
                        v-model="form.parent_id"
                        :placeholder="$t('lg_no_parent')"
                        clearable
                        filterable
                        style="width: 100%"
                        @change="onParentChange"
                    >
                        <el-option v-for="acc in parentOptions" :key="acc.id" :label="`${acc.code} - ${acc.name}`" :value="acc.id" />
                    </el-select>
                </el-form-item>

                <div class="form-pair">
                    <el-form-item :label="$t('account_code')" required>
                        <el-input v-model="form.code" :placeholder="$t('account_code_example')" :disabled="isEditMode" dir="ltr" />
                        <p v-if="!isEditMode && suggestedCode && form.code !== suggestedCode" class="field-hint">
                            <button type="button" class="link-btn" @click="form.code = suggestedCode">{{ $t('lg_use_code', { code: suggestedCode }) }}</button>
                        </p>
                    </el-form-item>
                    <el-form-item :label="$t('account_name')" required>
                        <el-input v-model="form.name" :placeholder="$t('account_name_example')" />
                    </el-form-item>
                </div>

                <el-form-item :label="$t('account_description')">
                    <el-input v-model="form.description" type="textarea" :autosize="{ minRows: 2, maxRows: 5 }" :placeholder="$t('account_notes_placeholder')" />
                </el-form-item>

                <div class="switch-row">
                    <div>
                        <strong>{{ $t('lg_active_label') }}</strong>
                        <p class="field-hint">{{ $t('lg_active_hint') }}</p>
                    </div>
                    <el-switch v-model="form.is_active" />
                </div>

                <p v-if="!isEditMode" class="note">
                    <i class="fas fa-circle-info"></i>
                    <span>{{ $t('lg_opening_balance_note') }}</span>
                </p>
            </el-form>

            <template #footer>
                <div class="drawer-footer">
                    <el-button @click="formDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAccount">
                        <i class="fas fa-check mr-1"></i> {{ $t('save_account') }}
                    </el-button>
                </div>
            </template>
        </el-drawer>

        <!-- ── Statement ──────────────────────────────────────────────────── -->
        <el-drawer
            v-model="statementDrawerVisible"
            :title="$t('lg_statement_title')"
            size="min(920px, 100%)"
            direction="rtl"
            destroy-on-close
            class="lg-drawer"
        >
            <template v-if="selectedAccount">
                <div class="st-head" :class="'tone-' + selectedAccount.type">
                    <div class="st-id">
                        <span class="code-badge">{{ selectedAccount.code }}</span>
                        <h3>{{ selectedAccount.name }}</h3>
                        <span class="st-sub">
                            <span class="type-chip" :class="'tone-' + selectedAccount.type">{{ typeLabel(selectedAccount.type) }}</span>
                            <span v-if="selectedAccount.posting_role" class="meta-chip is-role"><i class="fas fa-gear"></i> {{ selectedAccount.posting_role }}</span>
                        </span>
                    </div>
                    <div class="st-balance">
                        <span>{{ $t('lg_current_balance') }}</span>
                        <strong :class="{ 'is-negative': Number(selectedAccount.balance) < 0 }">{{ money(selectedAccount.balance) }}</strong>
                    </div>
                </div>

                <div class="st-controls">
                    <el-segmented v-model="statementPreset" :options="presetOptions" @change="applyPreset" />
                    <el-date-picker
                        v-model="statementRange"
                        type="daterange"
                        unlink-panels
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :start-placeholder="$t('jr_date_from')"
                        :end-placeholder="$t('jr_date_to')"
                        :clearable="false"
                        class="st-range"
                        @change="onRangePicked"
                    />
                </div>

                <div v-if="loadingStatement && !statement" v-loading="true" class="st-loading"></div>

                <template v-else-if="statement">
                    <div class="st-tiles" v-loading="loadingStatement">
                        <div class="st-tile">
                            <span>{{ $t('lg_opening') }}</span>
                            <strong>{{ money(statement.opening_balance) }}</strong>
                            <small>{{ statement.period?.from }}</small>
                        </div>
                        <div class="st-tile is-debit">
                            <span>{{ $t('jr_col_debit') }}</span>
                            <strong>{{ money(statement.totals?.debits) }}</strong>
                        </div>
                        <div class="st-tile is-credit">
                            <span>{{ $t('jr_col_credit') }}</span>
                            <strong>{{ money(statement.totals?.credits) }}</strong>
                        </div>
                        <div class="st-tile is-closing">
                            <span>{{ $t('closing_balance') }}</span>
                            <strong :class="{ 'is-negative': statement.closing_balance < 0 }">{{ money(statement.closing_balance) }}</strong>
                            <small>{{ statement.period?.to }}</small>
                        </div>
                    </div>

                    <!-- Only meaningful when the period runs to today: an
                         earlier closing figure is not supposed to equal
                         today's stored balance. -->
                    <el-alert
                        v-if="statementReachesToday && !statement.matches_stored_balance"
                        type="warning"
                        show-icon
                        :closable="false"
                        class="mb-3"
                        :title="$t('statement_does_not_match_stored_balance')"
                    />

                    <div v-if="!statement.movements?.length" class="empty-state is-compact">
                        <span class="empty-icon"><i class="fas fa-file-invoice"></i></span>
                        <p>{{ $t('lg_no_movements_in_period') }}</p>
                    </div>

                    <!-- Oldest first, the way a statement is read: from the
                         opening figure down to the closing one. -->
                    <el-table v-else :data="statementRows" size="small" class="st-table" :row-class-name="stRowClass">
                        <el-table-column :label="$t('jr_col_date')" width="105">
                            <template #default="{ row }"><span class="cell-date">{{ row.kind ? '' : String(row.entry_date).slice(0, 10) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_entry')" width="130">
                            <template #default="{ row }">
                                <router-link
                                    v-if="!row.kind"
                                    :to="{ path: '/admin/accounting/journal', query: { search: row.entry_number } }"
                                    class="entry-link"
                                >{{ row.entry_number }}</router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_narration')" min-width="200">
                            <template #default="{ row }">
                                <strong v-if="row.kind">{{ row.kind === 'opening' ? $t('lg_opening') : $t('closing_balance') }}</strong>
                                <template v-else>
                                    <span class="st-narr">{{ row.description || '—' }}</span>
                                    <span v-if="row.status === 'reversed'" class="meta-chip is-off">{{ $t('reversed') }}</span>
                                </template>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_debit')" width="130" align="right">
                            <template #default="{ row }"><span v-if="row.debit > 0" class="amt is-debit">{{ money(row.debit) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('jr_col_credit')" width="130" align="right">
                            <template #default="{ row }"><span v-if="row.credit > 0" class="amt is-credit">{{ money(row.credit) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('lg_col_balance')" width="150" align="right">
                            <template #default="{ row }">
                                <strong class="amt" :class="{ 'is-negative': row.balance < 0 }">{{ money(row.balance) }}</strong>
                            </template>
                        </el-table-column>
                    </el-table>
                </template>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, computed, reactive } from 'vue';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ledgerAccountsApi } from '@/api/ledgerAccounts';
import { accountingReportsApi } from '@/api/accountingReports';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formatMoney, baseCurrencyCode } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t, te } = useI18n();
const store = useLedgerAccountsStore();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);

const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];
const TYPE_ICONS = {
    asset: 'fas fa-coins',
    liability: 'fas fa-hand-holding-usd',
    equity: 'fas fa-shield-alt',
    revenue: 'fas fa-arrow-trend-up',
    expense: 'fas fa-receipt',
};

/** Stored lowercase; older rows or imports may not be. */
const normType = (type) => String(type || '').toLowerCase().replace(/s$/, '');
const typeLabel = (type) => (te('lg_type_' + normType(type)) ? t('lg_type_' + normType(type)) : type);

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());

/* ------------------------------------------------------------------ *
 * Loading
 *
 * The chart is small enough to hold whole; the old screen asked for the 100
 * newest accounts, so an older one past that simply was not in the list.
 * ------------------------------------------------------------------ */

const loadError = ref(false);

const load = async () => {
    loadError.value = false;
    try {
        await store.fetchAccounts({ per_page: 1000 });
    } catch (e) {
        loadError.value = true;
    }
};

const accounts = computed(() => (store.accounts || []).map((a) => ({ ...a, type: normType(a.type) })));

/* ------------------------------------------------------------------ *
 * Tree
 *
 * Built from parent_id and sorted by code. A parent carries no balance of its
 * own (postings land on the leaves), so it shows the sum of its branch.
 * ------------------------------------------------------------------ */

const byCode = (a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true });

const tree = computed(() => {
    const nodes = new Map(accounts.value.map((a) => [a.id, { ...a, children: [] }]));
    const roots = [];
    nodes.forEach((node) => {
        const parent = node.parent_id ? nodes.get(node.parent_id) : null;
        (parent && parent !== node ? parent.children : roots).push(node);
    });
    const finish = (list, depth = 0) => {
        list.sort(byCode);
        list.forEach((n) => {
            finish(n.children, depth + 1);
            n.rollup = Math.round((Number(n.balance || 0) + n.children.reduce((s, c) => s + c.rollup, 0)) * 100) / 100;
        });
        return list;
    };
    return finish(roots);
});

const typeStats = computed(() => {
    const stats = Object.fromEntries(TYPES.map((tp) => [tp, { count: 0, total: 0 }]));
    accounts.value.forEach((a) => {
        if (!stats[a.type]) return;
        stats[a.type].count += 1;
        stats[a.type].total += Number(a.balance || 0);
    });
    TYPES.forEach((tp) => { stats[tp].total = Math.round(stats[tp].total * 100) / 100; });
    return stats;
});

/* ------------------------------------------------------------------ *
 * Filters
 *
 * A match keeps its ancestors, so a found account is still shown where it
 * sits in the chart rather than floating on its own.
 * ------------------------------------------------------------------ */

const filters = reactive({ search: '', type: '', status: '', hideZero: false });

const statusOptions = computed(() => [
    { label: t('jr_status_all'), value: '' },
    { label: t('active'), value: 'active' },
    { label: t('inactive'), value: 'inactive' },
]);

const activeFilterCount = computed(() => [filters.search.trim(), filters.type, filters.status, filters.hideZero].filter(Boolean).length);

const resetFilters = () => Object.assign(filters, { search: '', type: '', status: '', hideZero: false });

const matches = (node) => {
    const q = filters.search.trim().toLowerCase();
    if (q && !String(node.code).toLowerCase().includes(q) && !String(node.name).toLowerCase().includes(q)) return false;
    if (filters.status === 'active' && !node.is_active) return false;
    if (filters.status === 'inactive' && node.is_active) return false;
    if (filters.hideZero && node.rollup === 0) return false;
    return true;
};

const prune = (list) => list.reduce((out, node) => {
    const children = prune(node.children);
    if (matches(node) || children.length) out.push({ ...node, children });
    return out;
}, []);

const visibleTree = computed(() => {
    const roots = filters.type ? tree.value.filter((n) => n.type === filters.type) : tree.value;
    const narrowing = filters.search.trim() || filters.status || filters.hideZero;
    return narrowing ? prune(roots) : roots;
});

const countNodes = (list) => list.reduce((s, n) => s + 1 + countNodes(n.children), 0);
const visibleCount = computed(() => countNodes(visibleTree.value));

/** Remounts the table when filters change so `default-expand-all` applies to the new rows. */
const treeKey = computed(() => JSON.stringify(filters) + accounts.value.length);

const rowClass = ({ row }) => [row.children?.length ? 'is-parent' : '', row.is_active ? '' : 'is-inactive'].join(' ');

/** Why the server would refuse; entries are only known to the server, which then says so. */
const deleteBlocker = (row) => {
    if (row.children?.length) return 'lg_cannot_delete_parent';
    if (row.posting_role || row.is_system) return 'lg_cannot_delete_system';
    return null;
};

/* ------------------------------------------------------------------ *
 * Create / edit
 * ------------------------------------------------------------------ */

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingAccountId = ref(null);
const form = reactive({ code: '', name: '', type: 'asset', parent_id: null, is_active: true, description: '' });

const resetForm = () => Object.assign(form, { code: '', name: '', type: 'asset', parent_id: null, is_active: true, description: '' });

/** Ids of the account and everything below it: none of them can be its parent. */
const excludedParents = computed(() => {
    const out = new Set();
    if (!editingAccountId.value || !isEditMode.value) return out;
    const walk = (id) => {
        out.add(id);
        accounts.value.filter((a) => a.parent_id === id).forEach((a) => walk(a.id));
    };
    walk(editingAccountId.value);
    return out;
});

const parentOptions = computed(() => accounts.value
    .filter((a) => !excludedParents.value.has(a.id) && (!form.type || a.type === form.type || a.id === form.parent_id))
    .sort(byCode));

/** Next free numeric code: after the parent's last child, or after the type's last account. */
const suggestedCode = computed(() => {
    const siblings = accounts.value.filter((a) => (form.parent_id ? a.parent_id === form.parent_id : a.type === form.type && !a.parent_id));
    const codes = siblings.map((a) => Number(a.code)).filter(Number.isFinite);
    let next;
    if (codes.length) next = Math.max(...codes) + 1;
    else if (form.parent_id) {
        const parent = accounts.value.find((a) => a.id === form.parent_id);
        next = Number(parent?.code) + 1;
    }
    if (!Number.isFinite(next)) return '';
    const taken = new Set(accounts.value.map((a) => String(a.code)));
    while (taken.has(String(next))) next += 1;
    return String(next);
});

const setFormType = (tp) => {
    form.type = tp;
    const parent = accounts.value.find((a) => a.id === form.parent_id);
    if (parent && parent.type !== tp) form.parent_id = null;
};

const onParentChange = (id) => {
    const parent = accounts.value.find((a) => a.id === id);
    if (parent) form.type = parent.type;
};

const openCreateDrawer = (parent = null) => {
    isEditMode.value = false;
    editingAccountId.value = null;
    resetForm();
    if (parent) {
        form.parent_id = parent.id;
        form.type = parent.type;
    }
    formDrawerVisible.value = true;
};

const openEditDrawer = (account) => {
    isEditMode.value = true;
    editingAccountId.value = account.id;
    Object.assign(form, {
        code: account.code,
        name: account.name,
        type: normType(account.type),
        parent_id: account.parent_id || null,
        is_active: !!account.is_active,
        description: account.description || '',
    });
    formDrawerVisible.value = true;
};

/** The server's own words when it has them: "type cannot change after posting" beats "could not save". */
const serverMessage = (e, fallback) => {
    const errors = e.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    return e.response?.data?.message || fallback;
};

const saveAccount = async () => {
    if (!String(form.code).trim() || !form.name.trim()) {
        ElMessage.warning(t('account_code_and_name_required'));
        return;
    }

    // Balance is left out on purpose: only posted entries move it.
    const payload = {
        code: String(form.code).trim(),
        name: form.name.trim(),
        type: form.type,
        parent_id: form.parent_id || null,
        is_active: form.is_active,
        description: form.description || null,
    };

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await ledgerAccountsApi.update(editingAccountId.value, payload);
            ElMessage.success(t('account_updated'));
        } else {
            await ledgerAccountsApi.create(payload);
            ElMessage.success(t('account_saved'));
        }
        formDrawerVisible.value = false;
        await load();
    } catch (e) {
        ElMessage.error(serverMessage(e, t('failed_to_save_account')));
    } finally {
        submittingForm.value = false;
    }
};

const deleteAccount = (row) => {
    ElMessageBox.confirm(
        `${t('confirm_delete_account')}\n${row.code} - ${row.name}`,
        t('delete'),
        { confirmButtonText: t('delete'), cancelButtonText: t('cancel'), type: 'warning', confirmButtonClass: 'el-button--danger' }
    ).then(async () => {
        try {
            await ledgerAccountsApi.delete(row.id);
            ElMessage.success(t('account_deleted'));
            await load();
        } catch (e) {
            ElMessage.error(serverMessage(e, t('failed_to_delete_account')));
        }
    }).catch(() => {});
};

/* ------------------------------------------------------------------ *
 * Statement
 *
 * The server answers with the opening balance and a balance per row, and says
 * whether the rows land on the account's stored balance.
 * ------------------------------------------------------------------ */

const statementDrawerVisible = ref(false);
const loadingStatement = ref(false);
const selectedAccount = ref(null);
const statement = ref(null);
const statementRange = ref([]);
const statementPreset = ref('year');

const presetOptions = computed(() => [
    { label: t('acc_ov_this_month'), value: 'month' },
    { label: t('acc_ov_this_year'), value: 'year' },
    { label: t('lg_last_year'), value: 'last_year' },
    { label: t('lg_custom'), value: 'custom' },
]);

const presetRange = (preset) => {
    const now = new Date();
    if (preset === 'month') return [localDate(new Date(now.getFullYear(), now.getMonth(), 1)), todayIso];
    if (preset === 'last_year') return [`${now.getFullYear() - 1}-01-01`, `${now.getFullYear() - 1}-12-31`];
    return [`${now.getFullYear()}-01-01`, todayIso];
};

const statementReachesToday = computed(() => !statement.value?.period?.to || statement.value.period.to >= todayIso);

const statementRows = computed(() => {
    const s = statement.value;
    if (!s) return [];
    return [
        { kind: 'opening', balance: s.opening_balance },
        ...(s.movements || []),
        { kind: 'closing', balance: s.closing_balance, debit: s.totals?.debits, credit: s.totals?.credits },
    ];
});

const stRowClass = ({ row }) => (row.kind ? 'is-edge' : row.status === 'reversed' ? 'is-reversed' : '');

const fetchStatement = async () => {
    if (!selectedAccount.value) return;
    loadingStatement.value = true;
    try {
        const res = await accountingReportsApi.accountStatement({
            account_id: selectedAccount.value.id,
            date_from: statementRange.value?.[0] || undefined,
            date_to: statementRange.value?.[1] || undefined,
        });
        statement.value = res.data?.data || null;
    } catch (e) {
        ElMessage.error(t('failed_to_load_account_movements'));
    } finally {
        loadingStatement.value = false;
    }
};

const openStatementDrawer = (account) => {
    selectedAccount.value = account;
    statement.value = null;
    statementPreset.value = 'year';
    statementRange.value = presetRange('year');
    statementDrawerVisible.value = true;
    fetchStatement();
};

const applyPreset = (preset) => {
    if (preset === 'custom') return;
    statementRange.value = presetRange(preset);
    fetchStatement();
};

const onRangePicked = () => {
    statementPreset.value = 'custom';
    fetchStatement();
};

onMounted(load);
</script>

<style scoped>
/* Same palette as the accounting overview and journal: every text colour
   reaches at least 4.5:1 on white. */
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
    --c-debit: #047857;
    --c-credit: #b45309;
    --c-loss: #b91c1c;
    color: var(--ov-body);
}

.tone-asset { --tone: #1d4ed8; --tone-bg: #dbeafe; }
.tone-liability { --tone: #b45309; --tone-bg: #fef3c7; }
.tone-equity { --tone: #047857; --tone-bg: #d1fae5; }
.tone-revenue { --tone: #0e7490; --tone-bg: #cffafe; }
.tone-expense { --tone: #be123c; --tone-bg: #ffe4e6; }

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.is-negative { color: var(--c-loss) !important; }

/* ── Type cards ───────────────────────────────────────────────────────── */
.type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.8rem;
    margin-bottom: 1.1rem;
}

.type-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
    padding: 0.9rem 1rem;
    text-align: start;
    font: inherit;
    background: linear-gradient(180deg, color-mix(in srgb, var(--tone) 6%, #fff) 0%, #fff 70%);
    border: 1px solid var(--ov-border);
    border-top: 3px solid var(--tone);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
    min-width: 0;
}

.type-card:hover { box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
.type-card:focus-visible { outline: 2px solid var(--tone); outline-offset: 2px; }
.type-card.is-active { border-color: var(--tone); box-shadow: 0 0 0 1px var(--tone), 0 8px 20px rgba(15, 23, 42, 0.08); }

.type-icon {
    position: absolute;
    inset-inline-end: 0.9rem;
    top: 0.85rem;
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    color: var(--tone);
    background: var(--tone-bg);
    font-size: 0.85rem;
}

.type-label { font-size: 0.8rem; font-weight: 700; color: var(--ov-muted); padding-inline-end: 2.5rem; }
.type-total { font-size: 1.2rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.type-count { font-size: 0.75rem; color: var(--ov-subtle); }

/* ── Card ─────────────────────────────────────────────────────────────── */
.lg-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    min-width: 0;
}

.lg-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.lg-card-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--ov-text);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.lg-card-head h2 i {
    width: 30px;
    height: 30px;
    display: inline-grid;
    place-items: center;
    border-radius: 9px;
    font-size: 0.85rem;
    color: #1d4ed8;
    background: #dbeafe;
}

.count-pill {
    padding: 0.1rem 0.65rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: var(--ov-muted);
    font-size: 0.8rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 1rem; margin-bottom: 0.9rem; }
.t-search { flex: 1 1 260px; max-width: 420px; }
.t-search :deep(.el-input__prefix) { color: var(--ov-subtle); }
.toolbar :deep(.el-input__inner::placeholder) { color: var(--ov-subtle); }
.t-zero :deep(.el-checkbox__label) { color: var(--ov-body); font-weight: 600; }
.clear-btn { color: var(--ov-link); font-weight: 700; }

/* ── Chart table ──────────────────────────────────────────────────────── */
.chart-table { --el-table-header-bg-color: var(--ov-soft); --el-table-border-color: var(--ov-border); --el-table-row-hover-bg-color: #f5f8ff; }
.chart-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.78rem; }
.chart-table :deep(td.el-table__cell .cell) { color: var(--ov-body); display: flex; align-items: center; }
.chart-table :deep(td.el-table__cell.is-right .cell) { justify-content: flex-end; }
.chart-table :deep(td.el-table__cell.is-center .cell) { justify-content: center; }
.chart-table :deep(.el-table__row.is-parent td.el-table__cell) { background: #f8fafc; }
.chart-table :deep(.el-table__row.is-parent .acc-name) { font-weight: 800; }
.chart-table :deep(.el-table__row.is-inactive .acc-name) { color: var(--ov-subtle); }
.chart-table :deep(.el-table__expand-icon) { color: var(--ov-muted); }

.acc-cell { display: inline-flex; align-items: flex-start; gap: 0.6rem; min-width: 0; }
.acc-main { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }

.acc-name {
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    font-weight: 700;
    color: var(--ov-text);
    text-align: start;
    cursor: pointer;
}

.acc-name:hover { color: var(--ov-link); text-decoration: underline; text-underline-offset: 3px; }
.acc-name:focus-visible { outline: 2px solid var(--ov-link); outline-offset: 2px; border-radius: 3px; }
.acc-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem; }
.acc-desc { font-size: 0.74rem; color: var(--ov-subtle); flex-basis: 100%; line-height: 1.5; }

.code-badge {
    display: inline-block;
    flex-shrink: 0;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0.05rem 0.45rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    direction: ltr;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1.7;
    color: var(--ov-muted);
    background: #f1f5f9;
}

.meta-chip.is-role { color: #5b21b6; background: #ede9fe; }
.meta-chip.is-off { color: #475569; background: #e2e8f0; }

.type-chip {
    display: inline-block;
    padding: 0.05rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--tone);
    background: var(--tone-bg);
}

.bal { font-weight: 700; color: var(--ov-text); font-variant-numeric: tabular-nums; white-space: nowrap; }
.bal.is-zero { color: var(--ov-subtle); font-weight: 600; }
.bal.is-rollup { font-weight: 800; }
.bal small { font-size: 0.7rem; font-weight: 700; color: var(--ov-subtle); margin-inline-end: 0.25rem; }

.row-actions { display: inline-flex; align-items: center; gap: 0.15rem; }
.act-statement { color: #1e40af; border-color: #bfdbfe; background: #eff6ff; font-weight: 700; }
.act-statement:hover, .act-statement:focus-visible { color: #fff; background: #1d4ed8; border-color: #1d4ed8; }
.act-icon { color: var(--ov-muted); }
.act-icon:not(.is-disabled):hover { color: var(--ov-link); background: #eff6ff; }
.act-icon.is-danger:not(.is-disabled):hover { color: var(--c-loss); background: #fef2f2; }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.9rem; padding: 3rem 1rem; text-align: center; }
.empty-state.is-compact { padding: 2rem 1rem; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; border-radius: 18px; background: #eff6ff; color: #1d4ed8; font-size: 1.5rem; }
.empty-state p { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--ov-muted); }

/* ── Drawers ──────────────────────────────────────────────────────────── */
.lg-drawer :deep(.el-drawer__header) {
    margin-bottom: 0;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--ov-border);
    color: var(--ov-text);
    font-weight: 800;
    font-size: 1.05rem;
}

.lg-drawer :deep(.el-drawer__footer) { border-top: 1px solid var(--ov-border); padding-top: 0.9rem; }
.account-form :deep(.el-form-item__label) { color: var(--ov-body); font-weight: 700; }
.drawer-footer { display: flex; justify-content: flex-end; gap: 0.6rem; }

.type-picker { display: flex; flex-wrap: wrap; gap: 0.4rem; }

.type-option {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid var(--ov-border);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ov-body);
    cursor: pointer;
}

.type-option i { color: var(--tone); }
.type-option.is-active { color: var(--tone); background: var(--tone-bg); border-color: var(--tone); }
.type-option:disabled { cursor: not-allowed; opacity: 0.55; }
.type-option.is-active:disabled { opacity: 1; }

.form-pair { display: grid; grid-template-columns: 170px minmax(0, 1fr); gap: 0 0.9rem; }
@media (max-width: 520px) { .form-pair { grid-template-columns: minmax(0, 1fr); } }

.field-hint { margin: 0.3rem 0 0; font-size: 0.76rem; line-height: 1.6; color: var(--ov-subtle); }
.link-btn { padding: 0; border: 0; background: none; font: inherit; font-weight: 700; color: var(--ov-link); cursor: pointer; }
.link-btn:hover { text-decoration: underline; }

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--ov-border);
    border-radius: 10px;
    margin-bottom: 1rem;
}

.switch-row strong { color: var(--ov-text); font-size: 0.88rem; }

.note {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin: 0;
    padding: 0.6rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 0.82rem;
    line-height: 1.7;
}

.note i { color: #1d4ed8; }

/* ── Statement ────────────────────────────────────────────────────────── */
.st-head {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    border: 1px solid var(--ov-border);
    border-top: 3px solid var(--tone);
    border-radius: 12px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--tone) 6%, #fff) 0%, #fff 80%);
}

.st-id { display: flex; flex-direction: column; align-items: flex-start; gap: 0.35rem; min-width: 0; }
.st-id h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--ov-text); }
.st-sub { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.st-balance { display: flex; flex-direction: column; align-items: flex-end; gap: 0.15rem; }
.st-balance span { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.st-balance strong { font-size: 1.5rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; }

.st-controls { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.65rem; margin-bottom: 1rem; }
.st-range { max-width: 300px; }
.st-loading { min-height: 220px; }

.st-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.6rem; margin-bottom: 1rem; }
@media (max-width: 640px) { .st-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.st-tile {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid var(--ov-border);
    border-radius: 10px;
    background: var(--ov-soft);
    min-width: 0;
}

.st-tile span { font-size: 0.74rem; font-weight: 700; color: var(--ov-muted); }
.st-tile strong { font-size: 0.98rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.st-tile small { font-size: 0.7rem; color: var(--ov-subtle); font-variant-numeric: tabular-nums; }
.st-tile.is-debit strong { color: var(--c-debit); }
.st-tile.is-credit strong { color: var(--c-credit); }
.st-tile.is-closing { background: #eff6ff; border-color: #bfdbfe; }

.st-table { --el-table-header-bg-color: var(--ov-soft); --el-table-border-color: var(--ov-border); }
.st-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.76rem; }
.st-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }
.st-table :deep(.el-table__row.is-edge td.el-table__cell) { background: #f1f5f9; }
.st-table :deep(.el-table__row.is-edge strong) { color: var(--ov-text); }
.st-table :deep(.el-table__row.is-reversed .st-narr) { color: var(--ov-subtle); text-decoration: line-through; }

.cell-date { font-variant-numeric: tabular-nums; white-space: nowrap; }
.entry-link { font-weight: 700; color: var(--ov-link); text-decoration: none; font-variant-numeric: tabular-nums; direction: ltr; }
.entry-link:hover { text-decoration: underline; }
.st-narr { margin-inline-end: 0.35rem; }
.amt { font-weight: 700; color: var(--ov-text); font-variant-numeric: tabular-nums; white-space: nowrap; }
.amt.is-debit { color: var(--c-debit); }
.amt.is-credit { color: var(--c-credit); }
</style>
