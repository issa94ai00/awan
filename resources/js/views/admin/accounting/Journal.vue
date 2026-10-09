<template>
    <div class="accounting-page accounting-journal">
        <AdminPageHeader
            icon="fas fa-book text-primary"
            :title="$t('journal')"
            :subtitle="$t('journal_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button :loading="store.loading" @click="load">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" @click="openCreateDrawer">
                    <i class="fas fa-plus mr-1"></i> {{ $t('record_manual_entry') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Says up front why there is no edit button, instead of leaving the
             answer to be discovered when the API refuses. -->
        <p class="final-note">
            <i class="fas fa-circle-info"></i>
            <span>{{ $t('posted_entry_is_final') }}</span>
        </p>

        <!-- ── Filters ────────────────────────────────────────────────────── -->
        <section class="jr-card filters">
            <div class="filters-row">
                <el-input
                    v-model="filters.search"
                    :placeholder="$t('jr_search_placeholder')"
                    clearable
                    class="f-search"
                    @input="onSearchInput"
                    @clear="applyFilters"
                >
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>

                <el-select
                    v-model="filters.ledger_account_id"
                    :placeholder="$t('jr_all_accounts')"
                    clearable
                    filterable
                    class="f-account"
                    @change="applyFilters"
                >
                    <el-option v-for="acc in accounts" :key="acc.id" :label="accountLabel(acc)" :value="acc.id" />
                </el-select>

                <el-select
                    v-model="filters.source_module"
                    :placeholder="$t('jr_all_sources')"
                    clearable
                    class="f-source"
                    @change="applyFilters"
                >
                    <el-option v-for="m in sourceModules" :key="m" :label="$t('jr_source_' + m)" :value="m" />
                </el-select>

                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    unlink-panels
                    :start-placeholder="$t('jr_date_from')"
                    :end-placeholder="$t('jr_date_to')"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    class="f-dates"
                    @change="applyFilters"
                />

                <el-segmented v-model="filters.status" :options="statusOptions" class="f-status" @change="applyFilters" />
            </div>

            <div v-if="activeFilterCount" class="filters-foot">
                <el-button text size="small" class="clear-btn" @click="resetFilters">
                    <i class="fas fa-xmark mr-1"></i> {{ $t('jr_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>
        </section>

        <!-- ── Entries ────────────────────────────────────────────────────── -->
        <section class="jr-card">
            <header class="jr-card-head">
                <h2><i class="fas fa-list-alt"></i> {{ $t('journal_entries') }}</h2>
                <span v-if="!store.loading || store.entries.length" class="count-pill">
                    {{ $t('jr_entries_count', { count: formatNumber(store.pagination.total) }) }}
                </span>
            </header>

            <el-alert
                v-if="loadError"
                type="error"
                show-icon
                :closable="false"
                class="mb-3"
                :title="$t('jr_load_failed')"
            >
                <el-button size="small" type="danger" plain @click="load">{{ $t('dash_try_again') }}</el-button>
            </el-alert>

            <el-skeleton v-if="store.loading && !store.entries.length" :rows="6" animated />

            <div v-else-if="!store.entries.length && !loadError" class="empty-state">
                <span class="empty-icon"><i class="fas fa-book"></i></span>
                <p>{{ activeFilterCount ? $t('jr_no_match') : $t('jr_no_entries') }}</p>
                <el-button v-if="activeFilterCount" @click="resetFilters">{{ $t('jr_clear_filters', { count: activeFilterCount }) }}</el-button>
                <el-button v-else type="primary" @click="openCreateDrawer">
                    <i class="fas fa-plus mr-1"></i> {{ $t('record_manual_entry') }}
                </el-button>
            </div>

            <template v-else-if="store.entries.length">
                <el-table
                    ref="tableRef"
                    v-loading="store.loading"
                    :data="store.entries"
                    row-key="id"
                    :row-class-name="rowClass"
                    class="entries-table"
                    @row-click="toggleRow"
                >
                    <!-- The lines are already in the payload; opening a row
                         answers "which accounts?" without leaving the page. -->
                    <el-table-column type="expand" width="40">
                        <template #default="{ row }">
                            <div class="entry-lines">
                                <div class="entry-line is-head">
                                    <span>{{ $t('jr_line_account') }}</span>
                                    <span class="line-amt">{{ $t('jr_col_debit') }}</span>
                                    <span class="line-amt">{{ $t('jr_col_credit') }}</span>
                                </div>
                                <div v-for="line in row.lines || []" :key="line.id" class="entry-line">
                                    <span class="line-account">
                                        <span v-if="line.ledger_account?.code" class="line-code">{{ line.ledger_account.code }}</span>
                                        <span>{{ line.ledger_account?.name || '—' }}</span>
                                        <small v-if="line.description" class="line-memo">{{ line.description }}</small>
                                    </span>
                                    <span class="line-amt is-debit">{{ Number(line.debit) ? money(line.debit, row) : '' }}</span>
                                    <span class="line-amt is-credit">{{ Number(line.credit) ? money(line.credit, row) : '' }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_entry')" width="140">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.entry_number }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_date')" width="110">
                        <template #default="{ row }"><span class="cell-date">{{ formatDate(row.entry_date) }}</span></template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_narration')" min-width="260">
                        <template #default="{ row }">
                            <div class="cell-narration">
                                <span class="narration-text">{{ row.description || '—' }}</span>
                                <span class="narration-tags">
                                    <span v-if="row.source_module" class="src-tag" :class="'src-' + row.source_module">
                                        {{ sourceLabel(row.source_module) }}
                                    </span>
                                    <span v-if="row.reversal_of_id" class="src-tag src-reversal">
                                        <i class="fas fa-rotate-left"></i> {{ $t('jr_reversal_entry') }}
                                    </span>
                                    <span class="lines-count">{{ $t('jr_lines_count', { count: row.lines?.length || 0 }) }}</span>
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_debit')" width="150" align="right">
                        <template #default="{ row }">
                            <span class="amt is-debit">{{ money(row.total_debit, row) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_credit')" width="150" align="right">
                        <template #default="{ row }">
                            <span class="amt is-credit">{{ money(row.total_credit, row) }}</span>
                            <i
                                v-if="!isRowBalanced(row)"
                                class="fas fa-triangle-exclamation unbalanced-icon"
                                :title="$t('jr_unbalanced')"
                            ></i>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('jr_col_status')" width="120" align="center">
                        <template #default="{ row }">
                            <span class="status-pill" :class="'st-' + row.status">{{ statusLabel(row.status) }}</span>
                        </template>
                    </el-table-column>

                    <!-- Reversal is the only action: a posted entry stays as it
                         was recorded, and a correction is a second entry beside
                         it rather than an edit of it. -->
                    <el-table-column width="110" align="center">
                        <template #default="{ row }">
                            <el-button
                                v-if="row.status !== 'reversed'"
                                size="small"
                                class="reverse-btn"
                                @click.stop="confirmReverse(row)"
                            >
                                <i class="fas fa-rotate-left mr-1"></i> {{ $t('jr_reverse') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="store.pagination.total > store.pagination.per_page" class="pager">
                    <el-pagination
                        v-model:current-page="page"
                        v-model:page-size="perPage"
                        :total="store.pagination.total"
                        :page-sizes="[20, 50, 100]"
                        layout="total, sizes, prev, pager, next"
                        background
                        @current-change="onPageChange"
                        @size-change="applyFilters"
                    />
                </div>
            </template>
        </section>

        <!-- ── New manual entry ───────────────────────────────────────────── -->
        <el-drawer
            v-model="drawerVisible"
            :title="$t('jr_new_entry_title')"
            size="min(780px, 100%)"
            direction="rtl"
            destroy-on-close
            class="entry-drawer"
        >
            <el-form :model="form" label-position="top" class="entry-form" @submit.prevent>
                <div class="form-top">
                    <el-form-item :label="$t('entry_date')" required class="fi-date">
                        <el-date-picker
                            v-model="form.entry_date"
                            type="date"
                            :placeholder="$t('choose_the_date')"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                            style="width: 100%"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('detailed_narration')" required class="fi-narration">
                        <el-input
                            v-model="form.description"
                            type="textarea"
                            :autosize="{ minRows: 2, maxRows: 4 }"
                            :placeholder="$t('narration_placeholder')"
                        />
                    </el-form-item>
                </div>

                <div class="lines-section">
                    <div class="lines-header">
                        <span class="lines-title">{{ $t('entry_lines_debit_credit') }}</span>
                        <span class="muted-sm">{{ $t('amounts_in_base_currency', { currency: baseCode }) }}</span>
                    </div>

                    <div class="line-grid is-head">
                        <span>#</span>
                        <span>{{ $t('jr_line_account') }}</span>
                        <span class="ta-end">{{ $t('jr_col_debit') }}</span>
                        <span class="ta-end">{{ $t('jr_col_credit') }}</span>
                        <span></span>
                    </div>

                    <div
                        v-for="(line, index) in form.lines"
                        :key="line.key"
                        class="line-grid"
                        :class="{ 'is-missing': lineNeedsAccount(line) }"
                    >
                        <span class="line-no">{{ index + 1 }}</span>
                        <el-select
                            v-model="line.ledger_account_id"
                            :placeholder="$t('jr_choose_account')"
                            filterable
                            class="line-account-select"
                        >
                            <el-option
                                v-for="acc in accounts"
                                :key="acc.id"
                                :label="accountLabel(acc)"
                                :value="acc.id"
                                :disabled="acc.is_active === false"
                            />
                        </el-select>
                        <el-input-number
                            v-model="line.debit"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            placeholder="0.00"
                            class="amt-input is-debit"
                            @input="(v) => v && (line.credit = null)"
                        />
                        <el-input-number
                            v-model="line.credit"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            placeholder="0.00"
                            class="amt-input is-credit"
                            @input="(v) => v && (line.debit = null)"
                        />
                        <el-button
                            text
                            circle
                            class="remove-line"
                            :disabled="form.lines.length <= 2"
                            :title="$t('jr_remove_line')"
                            @click="removeLine(index)"
                        >
                            <i class="fas fa-trash-can"></i>
                        </el-button>
                    </div>

                    <div class="lines-actions">
                        <el-button size="small" @click="addLine"><i class="fas fa-plus mr-1"></i> {{ $t('add_line') }}</el-button>
                        <el-button
                            v-if="fillTarget && formDifference !== 0"
                            size="small"
                            type="primary"
                            plain
                            @click="fillDifference"
                        >
                            <i class="fas fa-wand-magic-sparkles mr-1"></i> {{ $t('jr_fill_difference') }}
                        </el-button>
                    </div>

                    <div class="totals" :class="isFormBalanced ? 'is-ok' : 'is-off'">
                        <div class="total-cell">
                            <span>{{ $t('jr_col_debit') }}</span>
                            <strong>{{ money(formTotalDebit) }}</strong>
                        </div>
                        <div class="total-cell">
                            <span>{{ $t('jr_col_credit') }}</span>
                            <strong>{{ money(formTotalCredit) }}</strong>
                        </div>
                        <div class="total-cell">
                            <span>{{ $t('jr_difference') }}</span>
                            <strong>{{ money(Math.abs(formDifference)) }}</strong>
                        </div>
                    </div>
                </div>

                <p class="submit-hint" :class="submitBlocker ? 'is-blocked' : 'is-ready'">
                    <i class="fas" :class="submitBlocker ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
                    {{ submitBlocker ? $t(submitBlocker) : $t('jr_ready') }}
                </p>
            </el-form>

            <template #footer>
                <div class="drawer-footer">
                    <el-button @click="drawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" :disabled="!!submitBlocker" @click="saveEntry">
                        <i class="fas fa-check mr-1"></i> {{ $t('jr_post_entry') }}
                    </el-button>
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, reactive, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useJournalEntriesStore } from '@/stores/journalEntries';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formatMoney, formatNumber, baseCurrencyCode } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t, te } = useI18n();
const route = useRoute();
const router = useRouter();

const store = useJournalEntriesStore();
const ledgerStore = useLedgerAccountsStore();

const baseCode = baseCurrencyCode();

/** Each entry is stamped with the currency its amounts are in; the base may have changed since. */
const money = (value, row = null) => formatMoney(value || 0, { code: row?.base_currency || undefined });

const formatDate = (date) => (date ? String(date).slice(0, 10) : '');

/** Local calendar date: `toISOString()` is UTC and names yesterday until 03:00 here. */
const localToday = () => {
    const d = new Date();
    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
};

const accounts = computed(() => [...(ledgerStore.accounts || [])].sort((a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true })));
const accountLabel = (acc) => `${acc.code} - ${acc.name}`;

/** Every module that posts through the ledger engine. */
const sourceModules = ['manual', 'sales', 'purchases', 'inventory', 'expenses', 'payroll', 'assets', 'rma', 'opening', 'closing'];
const sourceLabel = (m) => (te('jr_source_' + m) ? t('jr_source_' + m) : m);

const statusLabel = (status) => {
    if (status === 'posted') return t('posted');
    if (status === 'reversed') return t('reversed');
    return status;
};

const isRowBalanced = (row) => Number(row.total_debit || 0).toFixed(2) === Number(row.total_credit || 0).toFixed(2);

/* ------------------------------------------------------------------ *
 * Filters, paging and the URL
 *
 * Kept in the query string so a filtered view survives a reload and can be
 * sent to someone else. The API paginates; the old screen showed the first
 * page and nothing else, so anything past the twentieth entry was unreachable.
 * ------------------------------------------------------------------ */

const filters = reactive({ search: '', ledger_account_id: '', source_module: '', status: '', date_from: '', date_to: '' });
const page = ref(1);
const perPage = ref(20);
const loadError = ref(false);

const statusOptions = computed(() => [
    { label: t('jr_status_all'), value: '' },
    { label: t('posted'), value: 'posted' },
    { label: t('reversed'), value: 'reversed' },
]);

const dateRange = computed({
    get: () => (filters.date_from && filters.date_to ? [filters.date_from, filters.date_to] : null),
    set: (v) => {
        filters.date_from = v?.[0] || '';
        filters.date_to = v?.[1] || '';
    },
});

const activeFilterCount = computed(() => [
    filters.search.trim(),
    filters.ledger_account_id,
    filters.source_module,
    filters.status,
    filters.date_from || filters.date_to,
].filter(Boolean).length);

const buildParams = () => {
    const params = { page: page.value, per_page: perPage.value };
    if (filters.search.trim()) params.search = filters.search.trim();
    ['ledger_account_id', 'source_module', 'status', 'date_from', 'date_to'].forEach((k) => {
        if (filters[k]) params[k] = filters[k];
    });
    return params;
};

const syncUrl = () => {
    const { per_page, page: p, ...rest } = buildParams();
    const query = { ...rest };
    if (p > 1) query.page = String(p);
    if (per_page !== 20) query.per_page = String(per_page);
    router.replace({ query });
};

const load = async () => {
    loadError.value = false;
    try {
        await store.fetchEntries(buildParams());
    } catch (e) {
        loadError.value = true;
    }
};

const applyFilters = () => {
    page.value = 1;
    syncUrl();
    load();
};

const onPageChange = () => {
    syncUrl();
    load();
};

let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
};

const resetFilters = () => {
    Object.assign(filters, { search: '', ledger_account_id: '', source_module: '', status: '', date_from: '', date_to: '' });
    applyFilters();
};

const readUrl = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.ledger_account_id = q.ledger_account_id ? Number(q.ledger_account_id) : '';
    filters.source_module = q.source_module ? String(q.source_module) : '';
    filters.status = ['posted', 'reversed'].includes(q.status) ? q.status : '';
    filters.date_from = q.date_from ? String(q.date_from) : '';
    filters.date_to = q.date_to ? String(q.date_to) : '';
    page.value = Math.max(1, Number(q.page) || 1);
    perPage.value = [20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

/* ------------------------------------------------------------------ *
 * Table
 * ------------------------------------------------------------------ */

const tableRef = ref(null);
const toggleRow = (row) => tableRef.value?.toggleRowExpansion(row);
const rowClass = ({ row }) => (row.status === 'reversed' ? 'is-reversed' : '');

/* ------------------------------------------------------------------ *
 * New manual entry
 *
 * Blank lines (no account, no amount) are ignored rather than refused, so an
 * extra row added by mistake does not block posting. The form says in words
 * what is still missing instead of just greying out the button.
 * ------------------------------------------------------------------ */

const drawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({ entry_date: '', description: '', lines: [] });

let lineSeq = 0;
const emptyLine = () => ({ key: ++lineSeq, ledger_account_id: '', debit: null, credit: null });

const resetForm = () => {
    form.entry_date = localToday();
    form.description = '';
    form.lines = [emptyLine(), emptyLine()];
};

const addLine = () => form.lines.push(emptyLine());
const removeLine = (index) => {
    if (form.lines.length > 2) form.lines.splice(index, 1);
};

const amountOf = (line) => (Number(line.debit) || 0) + (Number(line.credit) || 0);
const isBlankLine = (line) => !line.ledger_account_id && amountOf(line) === 0;
const lineNeedsAccount = (line) => !line.ledger_account_id && amountOf(line) > 0;
const usedLines = computed(() => form.lines.filter((l) => !isBlankLine(l)));

const round2 = (v) => Math.round(v * 100) / 100;
const formTotalDebit = computed(() => round2(form.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0)));
const formTotalCredit = computed(() => round2(form.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0)));
const formDifference = computed(() => round2(formTotalDebit.value - formTotalCredit.value));
const isFormBalanced = computed(() => formTotalDebit.value > 0 && formDifference.value === 0);

/** The last line with no amount yet — where the difference can go without overwriting anything. */
const fillTarget = computed(() => [...form.lines].reverse().find((l) => amountOf(l) === 0) || null);

const fillDifference = () => {
    const line = fillTarget.value;
    if (!line) return;
    if (formDifference.value > 0) line.credit = formDifference.value;
    else line.debit = -formDifference.value;
};

/** The first thing still stopping the entry from posting, as an i18n key. */
const submitBlocker = computed(() => {
    if (!form.description.trim()) return 'jr_hint_narration';
    if (formTotalDebit.value === 0 && formTotalCredit.value === 0) return 'jr_hint_needs_amounts';
    if (usedLines.value.some(lineNeedsAccount)) return 'jr_hint_missing_account';
    if (usedLines.value.some((l) => amountOf(l) === 0)) return 'jr_hint_missing_amount';
    if (usedLines.value.length < 2) return 'jr_hint_two_lines';
    if (!isFormBalanced.value) return 'jr_hint_unbalanced';
    return null;
});

const openCreateDrawer = () => {
    resetForm();
    drawerVisible.value = true;
};

const saveEntry = async () => {
    if (submitBlocker.value) {
        ElMessage.warning(t(submitBlocker.value));
        return;
    }

    const payload = {
        entry_date: form.entry_date,
        description: form.description.trim(),
        lines: usedLines.value.map((l) => ({
            ledger_account_id: l.ledger_account_id,
            debit: Number(l.debit) || 0,
            credit: Number(l.credit) || 0,
        })),
    };

    submittingForm.value = true;
    try {
        await store.createEntry(payload);
        ElMessage.success(t('entry_recorded_and_posted'));
        drawerVisible.value = false;
        page.value = 1;
        syncUrl();
        await load();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_entry'));
    } finally {
        submittingForm.value = false;
    }
};

const confirmReverse = (row) => {
    ElMessageBox.confirm(
        `${t('confirm_reverse_entry')}\n${row.entry_number}`,
        t('reverse_entry'),
        { confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning' }
    ).then(async () => {
        try {
            await store.reverseEntry(row.id);
            ElMessage.success(t('entry_reversed'));
            await load();
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('failed_to_reverse_entry'));
        }
    }).catch(() => {});
};

onMounted(() => {
    readUrl();

    // `?new=1` is how the accounting overview's "new entry" button lands here
    // with the form already open. Dropped from the URL once used, so a reload
    // or the back button does not open it a second time.
    if (route.query.new) {
        openCreateDrawer();
        const { new: _new, ...query } = route.query;
        router.replace({ query });
    }

    load();
    // The list endpoint pages at 20 by default, newest first; a hundred left
    // older accounts out of both pickers.
    ledgerStore.fetchAccounts({ per_page: 1000 }).catch(() => {});
});
</script>

<style scoped>
/* Same palette as the accounting overview: every text colour reaches at least
   4.5:1 on white, where Element Plus's secondary grey and its default
   success/warning tones sit near 3:1. */
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

.muted-sm { font-size: 0.76rem; color: var(--ov-subtle); }
.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }

.final-note {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin: 0 0 1rem;
    padding: 0.6rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 0.84rem;
    line-height: 1.7;
}

.final-note i { color: #1d4ed8; }

/* ── Cards ────────────────────────────────────────────────────────────── */
.jr-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1.1rem;
    min-width: 0;
}

.jr-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.jr-card-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--ov-text);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.jr-card-head h2 i {
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

/* ── Filters ──────────────────────────────────────────────────────────── */
.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
}

.f-search { flex: 1 1 240px; min-width: 200px; }
.f-account { width: 240px; }
.f-source { width: 170px; }
.f-dates { max-width: 280px; }
.f-search :deep(.el-input__prefix) { color: var(--ov-subtle); }

.filters :deep(.el-input__inner::placeholder) { color: var(--ov-subtle); }
.filters :deep(.el-select__placeholder) { color: var(--ov-subtle); }
.filters :deep(.el-range-input::placeholder) { color: var(--ov-subtle); }

.filters-foot { margin-top: 0.6rem; }
.clear-btn { color: var(--ov-link); font-weight: 700; }

@media (max-width: 720px) {
    .f-account, .f-source, .f-dates, .f-status { width: 100%; max-width: none; }
    .f-dates { flex: 1 1 100%; }
}

/* ── Table ────────────────────────────────────────────────────────────── */
.entries-table { --el-table-header-bg-color: var(--ov-soft); --el-table-border-color: var(--ov-border); --el-table-row-hover-bg-color: #f5f8ff; }
.entries-table :deep(th.el-table__cell .cell) { color: var(--ov-muted); font-weight: 700; font-size: 0.78rem; }
.entries-table :deep(td.el-table__cell .cell) { color: var(--ov-body); }
.entries-table :deep(.el-table__row) { cursor: pointer; }
.entries-table :deep(.el-table__expanded-cell) { background: var(--ov-soft); }
.entries-table :deep(.el-table__row.is-reversed td.el-table__cell) { background: #fafafa; }
.entries-table :deep(.el-table__row.is-reversed .narration-text),
.entries-table :deep(.el-table__row.is-reversed .amt) { color: var(--ov-subtle); text-decoration: line-through; text-decoration-color: #cbd5e1; }

.code-badge {
    display: inline-block;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0.1rem 0.5rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    direction: ltr;
}

.cell-date { font-variant-numeric: tabular-nums; color: var(--ov-body); white-space: nowrap; }

.cell-narration { display: flex; flex-direction: column; gap: 0.25rem; min-width: 0; }
.narration-text { color: var(--ov-text); font-weight: 600; line-height: 1.6; overflow-wrap: anywhere; }
.narration-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }

.src-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.7;
    color: #334155;
    background: #f1f5f9;
}

.src-manual { color: #1e40af; background: #dbeafe; }
.src-sales { color: #065f46; background: #d1fae5; }
.src-purchases { color: #92400e; background: #fef3c7; }
.src-inventory { color: #155e75; background: #cffafe; }
.src-expenses { color: #9f1239; background: #ffe4e6; }
.src-payroll { color: #5b21b6; background: #ede9fe; }
.src-assets { color: #3f3f46; background: #e4e4e7; }
.src-rma { color: #9a3412; background: #ffedd5; }
.src-opening, .src-closing { color: #334155; background: #e2e8f0; }
.src-reversal { color: #991b1b; background: #fee2e2; }

.lines-count { font-size: 0.74rem; color: var(--ov-subtle); }

.amt { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
.amt.is-debit { color: var(--c-debit); }
.amt.is-credit { color: var(--c-credit); }
.unbalanced-icon { color: var(--c-loss); margin-inline-start: 0.35rem; }

.status-pill {
    display: inline-block;
    padding: 0.05rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.st-posted { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
.st-reversed { color: #475569; background: #f1f5f9; border-color: #cbd5e1; }

.reverse-btn { color: #92400e; border-color: #fcd34d; background: #fffbeb; font-weight: 700; }
.reverse-btn:hover, .reverse-btn:focus-visible { color: #fff; background: #b45309; border-color: #b45309; }

.entry-lines { padding: 0.4rem 2.5rem 0.6rem 1rem; }

.entry-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 140px 140px;
    gap: 0.75rem;
    padding: 0.4rem 0;
    font-size: 0.82rem;
    color: var(--ov-body);
    border-bottom: 1px dashed var(--ov-border);
}

.entry-line.is-head { padding-top: 0; font-size: 0.72rem; font-weight: 700; color: var(--ov-subtle); border-bottom-style: solid; }
.entry-line:last-child { border-bottom: 0; }
.line-account { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.15rem 0.4rem; min-width: 0; }

.line-code {
    padding: 0 0.35rem;
    border-radius: 5px;
    background: #e2e8f0;
    color: var(--ov-muted);
    font-size: 0.74rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.line-memo { flex-basis: 100%; font-size: 0.74rem; color: var(--ov-subtle); }
.line-amt { text-align: end; font-variant-numeric: tabular-nums; }
.line-amt.is-debit { color: var(--c-debit); font-weight: 700; }
.line-amt.is-credit { color: var(--c-credit); font-weight: 700; }

@media (max-width: 640px) {
    .entry-lines { padding-inline: 0.5rem; }
    .entry-line { grid-template-columns: minmax(0, 1fr) 95px 95px; gap: 0.5rem; }
}

.pager { display: flex; justify-content: center; margin-top: 1rem; }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.9rem;
    padding: 3rem 1rem;
    text-align: center;
}

.empty-icon {
    width: 64px;
    height: 64px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 1.6rem;
}

.empty-state p { margin: 0; font-size: 0.98rem; font-weight: 600; color: var(--ov-muted); }

/* ── Drawer form ──────────────────────────────────────────────────────── */
.entry-drawer :deep(.el-drawer__header) {
    margin-bottom: 0;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--ov-border);
    color: var(--ov-text);
    font-weight: 800;
    font-size: 1.05rem;
}

.entry-drawer :deep(.el-drawer__footer) { border-top: 1px solid var(--ov-border); padding-top: 0.9rem; }
.entry-form :deep(.el-form-item__label) { color: var(--ov-body); font-weight: 700; }

.form-top { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 0 1rem; }

@media (max-width: 640px) {
    .form-top { grid-template-columns: minmax(0, 1fr); }
}

.lines-section {
    border: 1px solid var(--ov-border);
    border-radius: 12px;
    padding: 0.9rem;
    background: var(--ov-soft);
}

.lines-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.lines-title { font-weight: 800; color: var(--ov-text); }

.line-grid {
    display: grid;
    grid-template-columns: 26px minmax(0, 1fr) 130px 130px 32px;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
}

.line-grid.is-head { margin-bottom: 0.35rem; font-size: 0.74rem; font-weight: 700; color: var(--ov-subtle); }
.ta-end { text-align: end; }
.line-no { font-size: 0.78rem; font-weight: 700; color: var(--ov-subtle); text-align: center; font-variant-numeric: tabular-nums; }
.line-account-select { width: 100%; }
.line-grid.is-missing .line-account-select :deep(.el-select__wrapper) { box-shadow: 0 0 0 1px var(--c-loss) inset; }

.amt-input { width: 100%; }
.amt-input :deep(.el-input__inner) { text-align: end; font-variant-numeric: tabular-nums; font-weight: 700; }
.amt-input.is-debit :deep(.el-input__inner) { color: var(--c-debit); }
.amt-input.is-credit :deep(.el-input__inner) { color: var(--c-credit); }

.remove-line { color: var(--ov-subtle); }
.remove-line:not(.is-disabled):hover { color: var(--c-loss); background: #fef2f2; }

@media (max-width: 640px) {
    .line-grid { grid-template-columns: 20px minmax(0, 1fr) minmax(0, 1fr) 28px; }
    .line-grid.is-head { display: none; }
    .line-account-select { grid-column: 2 / -1; }
    .line-grid .amt-input.is-debit { grid-column: 2; }
    .line-grid .amt-input.is-credit { grid-column: 3; }
    .line-grid .remove-line { grid-column: 4; grid-row: 2; }
    .line-grid { padding-bottom: 0.5rem; border-bottom: 1px dashed var(--ov-border); }
}

.lines-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.25rem; }

.totals {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin-top: 0.85rem;
    padding: 0.7rem 0.9rem;
    border-radius: 10px;
    border: 1px solid transparent;
}

.totals.is-ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.totals.is-off { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.total-cell { display: flex; flex-direction: column; gap: 0.1rem; font-size: 0.76rem; font-weight: 700; min-width: 0; }
.total-cell strong { font-size: 0.95rem; font-weight: 800; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }

.submit-hint {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    margin: 0.9rem 0 0;
    font-size: 0.84rem;
    font-weight: 700;
}

.submit-hint.is-blocked { color: #9a3412; }
.submit-hint.is-ready { color: #065f46; }

.drawer-footer { display: flex; justify-content: flex-end; gap: 0.6rem; }
</style>
