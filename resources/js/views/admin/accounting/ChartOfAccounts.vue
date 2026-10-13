<template>
    <div class="accounting-page accounting-coa">
        <AdminPageHeader
            icon="fas fa-sitemap text-primary"
            :title="$t('chart_of_accounts')"
            :subtitle="$t('coa_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :disabled="!accounts.length" @click="exportCsv">
                    <i class="fas fa-file-csv mr-1"></i> {{ $t('coa_export_csv') }}
                </el-button>
                <el-button class="no-print" :disabled="!accounts.length" @click="printChart">
                    <i class="fas fa-print mr-1"></i> {{ $t('coa_print') }}
                </el-button>
                <el-button class="no-print" :loading="store.loading" @click="load">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button class="no-print" type="primary" @click="openCreateDrawer()">
                    <i class="fas fa-plus mr-1"></i> {{ $t('add_new_account') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Types and the equation they have to satisfy ─────────────────── -->
        <section class="summary no-print">
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

            <!-- Each stored balance is signed on its own normal side, so a set
                 of books that balances gives A = L + E + (R − X) exactly. -->
            <div v-if="accounts.length" class="equation" :class="equation.balanced ? 'is-ok' : 'is-off'">
                <span class="eq-icon"><i :class="equation.balanced ? 'fas fa-scale-balanced' : 'fas fa-triangle-exclamation'"></i></span>
                <div class="eq-body">
                    <strong>{{ equation.balanced ? $t('coa_equation_balanced') : $t('coa_equation_off', { amount: money(Math.abs(equation.diff)) }) }}</strong>
                    <span class="eq-formula">
                        <span class="tone-asset eq-term">{{ $t('lg_type_asset') }} <b>{{ money(typeStats.asset.total) }}</b></span>
                        <span class="eq-op">=</span>
                        <span class="tone-liability eq-term">{{ $t('lg_type_liability') }} <b>{{ money(typeStats.liability.total) }}</b></span>
                        <span class="eq-op">+</span>
                        <span class="tone-equity eq-term">{{ $t('lg_type_equity') }} <b>{{ money(typeStats.equity.total) }}</b></span>
                        <span class="eq-op">+</span>
                        <span class="tone-revenue eq-term">{{ $t('coa_current_result') }} <b>{{ money(typeStats.revenue.total - typeStats.expense.total) }}</b></span>
                    </span>
                </div>
            </div>
        </section>

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

        <div class="coa-layout">
            <!-- ── Tree ────────────────────────────────────────────────────── -->
            <section class="coa-card tree-card">
                <header class="coa-card-head">
                    <h2><i class="fas fa-sitemap"></i> {{ $t('coa_tree_title') }}</h2>
                    <span class="count-pill">{{ $t('lg_shown_of', { shown: visibleCount, total: accounts.length }) }}</span>
                </header>

                <div class="toolbar no-print">
                    <el-input
                        v-model="filters.search"
                        :placeholder="$t('lg_search_placeholder')"
                        clearable
                        class="t-search"
                    >
                        <template #prefix><i class="fas fa-search"></i></template>
                    </el-input>
                    <el-segmented v-model="filters.status" :options="statusOptions" size="small" />
                    <el-checkbox v-model="filters.hideZero" class="t-zero">{{ $t('lg_hide_zero') }}</el-checkbox>
                    <el-button v-if="activeFilterCount" text class="clear-btn" @click="resetFilters">
                        <i class="fas fa-xmark mr-1"></i> {{ $t('lg_clear_filters', { count: activeFilterCount }) }}
                    </el-button>
                </div>

                <div v-if="maxDepth > 1 && !narrowing" class="depth-bar no-print">
                    <span class="depth-label">{{ $t('coa_depth') }}</span>
                    <div class="depth-options" role="group" :aria-label="$t('coa_depth')">
                        <button
                            v-for="opt in depthOptions"
                            :key="opt.value"
                            type="button"
                            class="depth-btn"
                            :class="{ 'is-active': depth === opt.value }"
                            :aria-pressed="depth === opt.value"
                            @click="setDepth(opt.value)"
                        >{{ opt.label }}</button>
                    </div>
                    <span class="depth-spacer"></span>
                    <el-button size="small" text class="tree-tool" @click="setDepth('all')">
                        <i class="fas fa-angles-down mr-1"></i> {{ $t('coa_expand_all') }}
                    </el-button>
                    <el-button size="small" text class="tree-tool" @click="setDepth(1)">
                        <i class="fas fa-angles-up mr-1"></i> {{ $t('coa_collapse_all') }}
                    </el-button>
                </div>

                <el-skeleton v-if="store.loading && !accounts.length" :rows="10" animated />

                <div v-else-if="!rows.length && !loadError" class="empty-state">
                    <span class="empty-icon"><i class="fas fa-sitemap"></i></span>
                    <p>{{ accounts.length ? $t('lg_no_match') : $t('lg_no_accounts') }}</p>
                    <el-button v-if="accounts.length" @click="resetFilters">{{ $t('lg_clear_filters', { count: activeFilterCount }) }}</el-button>
                    <el-button v-else type="primary" @click="openCreateDrawer()"><i class="fas fa-plus mr-1"></i> {{ $t('add_new_account') }}</el-button>
                </div>

                <template v-else-if="rows.length">
                    <div class="tree-cols" aria-hidden="true">
                        <span>{{ $t('lg_col_account') }}</span>
                        <span>{{ $t('lg_col_balance') }}</span>
                    </div>

                    <div
                        ref="treeEl"
                        class="tree"
                        role="tree"
                        :aria-label="$t('chart_of_accounts')"
                        v-loading="store.loading"
                        @keydown="onTreeKeydown"
                    >
                        <div
                            v-for="row in rows"
                            :key="row.node.id"
                            class="tree-row"
                            :class="[
                                'tone-' + row.node.type,
                                {
                                    'is-root': row.depth === 0,
                                    'is-group': row.hasKids,
                                    'is-selected': row.node.id === selectedId,
                                    'is-inactive': !row.node.is_active,
                                },
                            ]"
                            role="treeitem"
                            :data-id="row.node.id"
                            :aria-level="row.depth + 1"
                            :aria-expanded="row.hasKids ? row.open : undefined"
                            :aria-selected="row.node.id === selectedId"
                            :tabindex="row.node.id === focusId ? 0 : -1"
                            @click="selectAndShow(row.node.id)"
                            @dblclick="openEditDrawer(row.node)"
                        >
                            <span v-for="n in row.depth" :key="n" class="indent"></span>
                            <button
                                v-if="row.hasKids"
                                type="button"
                                class="twisty"
                                tabindex="-1"
                                :aria-label="row.open ? $t('coa_collapse') : $t('coa_expand')"
                                @click.stop="toggle(row.node.id)"
                            >
                                <i class="fas fa-chevron-down" :class="{ 'is-closed': !row.open }"></i>
                            </button>
                            <span v-else class="twisty is-leaf"><i class="fas fa-circle"></i></span>

                            <span class="code-badge">{{ row.node.code }}</span>
                            <span class="row-name">
                                <span class="row-title">{{ row.node.name }}</span>
                                <span v-if="row.hasKids" class="meta-chip">{{ fullNode(row.node.id)?.children.length }}</span>
                                <el-tooltip v-if="row.node.posting_role" :content="$t('lg_role_hint', { role: row.node.posting_role })" placement="top">
                                    <span class="meta-chip is-role"><i class="fas fa-gear"></i></span>
                                </el-tooltip>
                                <span v-if="!row.node.is_active" class="meta-chip is-off">{{ $t('inactive') }}</span>
                            </span>

                            <span
                                class="bal"
                                :class="{
                                    'is-rollup': row.hasKids,
                                    'is-negative': amountOf(row.node) < 0,
                                    'is-zero': amountOf(row.node) === 0,
                                }"
                                :title="row.hasKids ? $t('coa_branch_total') : ''"
                            >{{ money(amountOf(row.node)) }}</span>

                            <el-tooltip :content="$t('lg_add_sub_account')" placement="top">
                                <button
                                    type="button"
                                    class="row-add no-print"
                                    tabindex="-1"
                                    :aria-label="$t('lg_add_sub_account')"
                                    @click.stop="openCreateDrawer(row.node)"
                                >
                                    <i class="fas fa-plus"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>

                    <p class="kbd-hint no-print">
                        <i class="fas fa-keyboard"></i> {{ $t('coa_keyboard_hint') }}
                    </p>
                </template>
            </section>

            <!-- ── Selected account ────────────────────────────────────────── -->
            <aside ref="detailEl" class="coa-card detail-card no-print" :class="selected ? 'tone-' + selected.type : ''">
                <div v-if="!selected" class="empty-state is-compact">
                    <span class="empty-icon"><i class="fas fa-hand-pointer"></i></span>
                    <p>{{ $t('coa_select_hint') }}</p>
                </div>

                <template v-else>
                    <button type="button" class="back-link" @click="backToTree">
                        <i class="fas fa-arrow-up"></i> {{ $t('coa_back_to_tree') }}
                    </button>

                    <nav class="crumbs" :aria-label="$t('coa_path')">
                        <span class="type-chip" :class="'tone-' + selected.type">{{ typeLabel(selected.type) }}</span>
                        <template v-for="anc in ancestors" :key="anc.id">
                            <i class="fas fa-chevron-left crumb-sep" aria-hidden="true"></i>
                            <button type="button" class="crumb" @click="select(anc.id)">{{ anc.code }} · {{ anc.name }}</button>
                        </template>
                    </nav>

                    <div class="d-head">
                        <div class="d-id">
                            <span class="code-badge is-lg">{{ selected.code }}</span>
                            <h3>{{ selected.name }}</h3>
                            <span class="d-chips">
                                <span class="meta-chip" :class="selected.children.length ? 'is-group' : 'is-post'">
                                    <i :class="selected.children.length ? 'fas fa-folder-tree' : 'fas fa-pen-to-square'"></i>
                                    {{ selected.children.length ? $t('coa_kind_group') : $t('coa_kind_postable') }}
                                </span>
                                <span class="meta-chip" :class="selected.is_active ? 'is-on' : 'is-off'">
                                    {{ selected.is_active ? $t('active') : $t('inactive') }}
                                </span>
                                <span v-if="selected.posting_role || selected.is_system" class="meta-chip is-role">
                                    <i class="fas fa-gear"></i> {{ $t('lg_system_account') }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="d-balance">
                        <div>
                            <span>{{ selected.children.length ? $t('coa_branch_total') : $t('lg_current_balance') }}</span>
                            <strong :class="{ 'is-negative': amountOf(selected) < 0 }">{{ money(amountOf(selected)) }}</strong>
                        </div>
                        <div v-if="selected.children.length && Number(selected.balance)" class="d-own">
                            <span>{{ $t('coa_own_balance') }}</span>
                            <b>{{ money(selected.balance) }}</b>
                        </div>
                    </div>

                    <dl class="facts">
                        <div>
                            <dt>{{ $t('coa_normal_side') }}</dt>
                            <dd>
                                <span class="side-chip" :class="normalSide(selected.type) === 'debit' ? 'is-debit' : 'is-credit'">
                                    {{ normalSide(selected.type) === 'debit' ? $t('coa_side_debit') : $t('coa_side_credit') }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ $t('coa_level') }}</dt>
                            <dd>{{ selected.depth + 1 }}</dd>
                        </div>
                        <div>
                            <dt>{{ $t('coa_direct_children') }}</dt>
                            <dd>{{ selected.children.length }}<small v-if="selected.descendants > selected.children.length"> / {{ $t('coa_all_descendants', { count: selected.descendants }) }}</small></dd>
                        </div>
                        <div>
                            <dt>{{ $t('coa_has_entries') }}</dt>
                            <dd>{{ selected.has_entries ? $t('coa_yes') : $t('coa_no') }}</dd>
                        </div>
                        <div v-if="selected.posting_role" class="is-wide">
                            <dt>{{ $t('coa_posting_role') }}</dt>
                            <dd><code dir="ltr">{{ selected.posting_role }}</code></dd>
                        </div>
                        <div v-if="selected.description" class="is-wide">
                            <dt>{{ $t('account_description') }}</dt>
                            <dd class="d-desc">{{ selected.description }}</dd>
                        </div>
                    </dl>

                    <div class="d-actions">
                        <router-link
                            :to="{ path: '/admin/accounting/ledger', query: { account: selected.id } }"
                            class="el-button el-button--primary is-plain d-statement"
                        >
                            <i class="fas fa-file-invoice mr-1"></i> {{ $t('coa_open_statement') }}
                        </router-link>
                        <el-button @click="openCreateDrawer(selected)">
                            <i class="fas fa-plus mr-1"></i> {{ $t('lg_add_sub_account') }}
                        </el-button>
                        <el-button @click="openEditDrawer(selected)">
                            <i class="fas fa-pen mr-1"></i> {{ $t('edit') }}
                        </el-button>
                        <el-button :loading="togglingId === selected.id" @click="toggleActive(selected)">
                            <i class="fas mr-1" :class="selected.is_active ? 'fa-toggle-off' : 'fa-toggle-on'"></i>
                            {{ selected.is_active ? $t('coa_deactivate') : $t('coa_activate') }}
                        </el-button>
                        <el-tooltip :disabled="!deleteBlocker(selected)" :content="deleteBlocker(selected) ? $t(deleteBlocker(selected)) : ''" placement="top">
                            <span>
                                <el-button type="danger" plain :disabled="!!deleteBlocker(selected)" @click="deleteAccount(selected)">
                                    <i class="fas fa-trash-can mr-1"></i> {{ $t('delete') }}
                                </el-button>
                            </span>
                        </el-tooltip>
                    </div>

                    <div class="children">
                        <h4>
                            {{ $t('coa_children_title') }}
                            <span class="count-pill">{{ selected.children.length }}</span>
                        </h4>
                        <p v-if="!selected.children.length" class="field-hint">{{ $t('coa_no_children') }}</p>
                        <ul v-else>
                            <li v-for="child in selected.children" :key="child.id">
                                <button type="button" class="child-row" :class="{ 'is-inactive': !child.is_active }" @click="select(child.id)">
                                    <span class="code-badge">{{ child.code }}</span>
                                    <span class="child-name">{{ child.name }}</span>
                                    <span class="bal" :class="{ 'is-negative': amountOf(child) < 0, 'is-zero': amountOf(child) === 0 }">{{ money(amountOf(child)) }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>
            </aside>
        </div>

        <!-- ── Create / edit ──────────────────────────────────────────────── -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? $t('lg_edit_title') : $t('lg_create_title')"
            size="min(520px, 100%)"
            direction="rtl"
            destroy-on-close
            class="coa-drawer"
        >
            <el-form :model="form" label-position="top" class="account-form" @submit.prevent="saveAccount(false)">
                <el-form-item :label="$t('lg_col_type')" required>
                    <div class="type-picker">
                        <button
                            v-for="tp in TYPES"
                            :key="tp"
                            type="button"
                            class="type-option"
                            :class="['tone-' + tp, { 'is-active': form.type === tp }]"
                            :disabled="!!form.parent_id || typeLocked"
                            @click="setFormType(tp)"
                        >
                            <i :class="TYPE_ICONS[tp]"></i> {{ $t('lg_type_' + tp) }}
                        </button>
                    </div>
                    <p v-if="typeLocked" class="field-hint"><i class="fas fa-lock mr-1"></i>{{ $t('coa_type_locked') }}</p>
                    <p v-else-if="form.parent_id" class="field-hint">{{ $t('lg_type_follows_parent') }}</p>
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
                        <el-option v-for="acc in parentOptions" :key="acc.id" :label="`${acc.code} - ${acc.name}`" :value="acc.id">
                            <span class="opt-row" :style="{ paddingInlineStart: (acc.depth * 14) + 'px' }">
                                <span class="code-badge">{{ acc.code }}</span> {{ acc.name }}
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>

                <div class="form-pair">
                    <el-form-item :label="$t('account_code')" required>
                        <el-input v-model="form.code" :placeholder="$t('account_code_example')" :disabled="isEditMode" dir="ltr" @input="codeTouched = true" />
                        <p v-if="codeTaken" class="field-hint is-error">{{ $t('coa_code_taken') }}</p>
                        <p v-if="!isEditMode && suggestedCode && form.code !== suggestedCode" class="field-hint">
                            <button type="button" class="link-btn" @click="useSuggestedCode">{{ $t('lg_use_code', { code: suggestedCode }) }}</button>
                        </p>
                    </el-form-item>
                    <el-form-item :label="$t('account_name')" required>
                        <el-input ref="nameInput" v-model="form.name" :placeholder="$t('account_name_example')" />
                    </el-form-item>
                </div>

                <!-- Where the account will sit, before it is saved. -->
                <div class="placement" :class="'tone-' + form.type">
                    <span class="placement-label">{{ $t('coa_placement') }}</span>
                    <span class="placement-path">
                        <span class="type-chip" :class="'tone-' + form.type">{{ typeLabel(form.type) }}</span>
                        <template v-for="anc in formPath" :key="anc.id">
                            <i class="fas fa-chevron-left crumb-sep" aria-hidden="true"></i>
                            <span>{{ anc.code }} · {{ anc.name }}</span>
                        </template>
                        <i class="fas fa-chevron-left crumb-sep" aria-hidden="true"></i>
                        <strong>{{ form.code || '…' }} · {{ form.name || $t('coa_new_account') }}</strong>
                    </span>
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
                    <el-button v-if="!isEditMode" :loading="submittingForm" @click="saveAccount(true)">
                        <i class="fas fa-plus mr-1"></i> {{ $t('coa_save_add_another') }}
                    </el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAccount(false)">
                        <i class="fas fa-check mr-1"></i> {{ $t('save_account') }}
                    </el-button>
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ledgerAccountsApi } from '@/api/ledgerAccounts';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formatMoney, baseCurrencyCode } from '@/utils/currency';
import { matchesSearch } from '@/utils/search';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t, te } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useLedgerAccountsStore();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const round2 = (n) => Math.round(n * 100) / 100;

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
const normalSide = (type) => (['asset', 'expense'].includes(type) ? 'debit' : 'credit');

/* ------------------------------------------------------------------ *
 * Loading — the chart is small enough to hold whole.
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

const accounts = computed(() => (store.accounts || []).map((a) => ({ ...a, type: normType(a.type), has_entries: !!a.has_entries })));

/* ------------------------------------------------------------------ *
 * Tree
 *
 * Built from parent_id and sorted by code. A parent shows the sum of its
 * branch (postings land on the leaves); `depth` and `descendants` feed the
 * level controls and the detail panel.
 * ------------------------------------------------------------------ */

const byCode = (a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true });

const built = computed(() => {
    const nodes = new Map(accounts.value.map((a) => [a.id, { ...a, children: [] }]));
    const roots = [];
    nodes.forEach((node) => {
        const parent = node.parent_id ? nodes.get(node.parent_id) : null;
        (parent && parent !== node ? parent.children : roots).push(node);
    });
    const seen = new Set();
    const finish = (list, depth) => {
        list.sort(byCode);
        list.forEach((n) => {
            // A parent loop in old data must not hang the page.
            if (seen.has(n.id)) { n.children = []; }
            seen.add(n.id);
            n.depth = depth;
            finish(n.children, depth + 1);
            n.rollup = round2(Number(n.balance || 0) + n.children.reduce((s, c) => s + c.rollup, 0));
            n.descendants = n.children.reduce((s, c) => s + 1 + c.descendants, 0);
        });
        return list;
    };
    return { roots: finish(roots, 0), nodes };
});

const tree = computed(() => built.value.roots);
const fullNode = (id) => built.value.nodes.get(id);
const amountOf = (node) => (node.children?.length ? node.rollup : Number(node.balance || 0));

const maxDepth = computed(() => {
    let max = 0;
    built.value.nodes.forEach((n) => { max = Math.max(max, (n.depth ?? 0) + 1); });
    return max;
});

const typeStats = computed(() => {
    const stats = Object.fromEntries(TYPES.map((tp) => [tp, { count: 0, total: 0 }]));
    accounts.value.forEach((a) => {
        if (!stats[a.type]) return;
        stats[a.type].count += 1;
        stats[a.type].total += Number(a.balance || 0);
    });
    TYPES.forEach((tp) => { stats[tp].total = round2(stats[tp].total); });
    return stats;
});

const equation = computed(() => {
    const s = typeStats.value;
    const diff = round2(s.asset.total - (s.liability.total + s.equity.total + s.revenue.total - s.expense.total));
    return { diff, balanced: Math.abs(diff) < 0.01 };
});

/* ------------------------------------------------------------------ *
 * Filters — a match keeps its ancestors so it is shown where it sits.
 * ------------------------------------------------------------------ */

const filters = reactive({ search: '', type: '', status: '', hideZero: false });

const statusOptions = computed(() => [
    { label: t('jr_status_all'), value: '' },
    { label: t('active'), value: 'active' },
    { label: t('inactive'), value: 'inactive' },
]);

const activeFilterCount = computed(() => [filters.search.trim(), filters.type, filters.status, filters.hideZero].filter(Boolean).length);
const resetFilters = () => Object.assign(filters, { search: '', type: '', status: '', hideZero: false });

const narrowing = computed(() => !!(filters.search.trim() || filters.status || filters.hideZero));

const matches = (node) => {
    if (!matchesSearch([node.code, node.name], filters.search)) return false;
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
    return narrowing.value ? prune(roots) : roots;
});

/* ------------------------------------------------------------------ *
 * Expansion
 *
 * A filtered tree opens fully, so every match is in sight; otherwise the
 * rows follow what the user opened, or the level picked above the tree.
 * ------------------------------------------------------------------ */

const expanded = reactive(new Set());
const depth = ref('all');

const depthOptions = computed(() => [
    ...Array.from({ length: Math.min(maxDepth.value, 5) }, (_, i) => ({ label: t('coa_level_n', { n: i + 1 }), value: i + 1 })),
    { label: t('coa_depth_all'), value: 'all' },
]);

const setDepth = (value) => {
    depth.value = value;
    expanded.clear();
    built.value.nodes.forEach((n) => {
        if (n.children.length && (value === 'all' || n.depth < value - 1)) expanded.add(n.id);
    });
};

const toggle = (id) => {
    if (expanded.has(id)) expanded.delete(id);
    else expanded.add(id);
    depth.value = null;
};

/** Opens every ancestor of an account so its row is on screen. */
const reveal = (id) => {
    for (let n = fullNode(fullNode(id)?.parent_id); n; n = fullNode(n.parent_id)) {
        if (expanded.has(n.id)) continue;
        expanded.add(n.id);
        depth.value = null;
    }
};

const rows = computed(() => {
    const out = [];
    const walk = (list, d) => list.forEach((node) => {
        const hasKids = node.children.length > 0;
        const open = hasKids && (narrowing.value || expanded.has(node.id));
        out.push({ node, depth: d, hasKids, open });
        if (open) walk(node.children, d + 1);
    });
    walk(visibleTree.value, 0);
    return out;
});

const countNodes = (list) => list.reduce((s, n) => s + 1 + countNodes(n.children), 0);
const visibleCount = computed(() => countNodes(visibleTree.value));

/* ------------------------------------------------------------------ *
 * Selection and keyboard
 *
 * One row is in the tab order (the selected one, or the first); arrows move
 * through the visible rows, and the expand/collapse keys follow the reading
 * direction, so in Arabic ← opens a branch.
 * ------------------------------------------------------------------ */

const treeEl = ref(null);
const detailEl = ref(null);
const selectedId = ref(null);

const selected = computed(() => (selectedId.value ? fullNode(selectedId.value) || null : null));
const focusId = computed(() => (rows.value.some((r) => r.node.id === selectedId.value) ? selectedId.value : rows.value[0]?.node.id));

const ancestors = computed(() => {
    const out = [];
    for (let n = fullNode(selected.value?.parent_id); n && out.length < 20; n = fullNode(n.parent_id)) out.unshift(n);
    return out;
});

const focusRow = (id) => nextTick(() => {
    const el = treeEl.value?.querySelector(`[data-id="${id}"]`);
    if (el) {
        el.focus({ preventScroll: true });
        el.scrollIntoView({ block: 'nearest' });
    }
});

const select = (id, { focus = true } = {}) => {
    if (!fullNode(id)) return;
    selectedId.value = id;
    reveal(id);
    if (focus) focusRow(id);
    if (String(route.query.account || '') !== String(id)) {
        router.replace({ query: { ...route.query, account: id } });
    }
};

const isNarrow = () => window.matchMedia('(max-width: 1023px)').matches;

/** On a narrow screen the panel sits under the tree; a click scrolls to it. */
const selectAndShow = (id) => {
    select(id);
    if (isNarrow()) nextTick(() => detailEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};

const backToTree = () => {
    if (selectedId.value) focusRow(selectedId.value);
    treeEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const onTreeKeydown = (e) => {
    const list = rows.value;
    if (!list.length) return;
    const i = Math.max(0, list.findIndex((r) => r.node.id === focusId.value));
    const row = list[i];
    const rtl = getComputedStyle(treeEl.value).direction === 'rtl';
    const openKey = rtl ? 'ArrowLeft' : 'ArrowRight';
    const closeKey = rtl ? 'ArrowRight' : 'ArrowLeft';
    let next = null;

    switch (e.key) {
        case 'ArrowDown': next = list[Math.min(i + 1, list.length - 1)]; break;
        case 'ArrowUp': next = list[Math.max(i - 1, 0)]; break;
        case 'Home': next = list[0]; break;
        case 'End': next = list[list.length - 1]; break;
        case openKey:
            if (row.hasKids && !row.open) toggle(row.node.id);
            else if (row.open) next = list[i + 1];
            break;
        case closeKey:
            if (row.open && !narrowing.value) toggle(row.node.id);
            else next = list.find((r) => r.node.id === row.node.parent_id) || null;
            break;
        case 'Enter':
            if (e.target.closest?.('.tree-row')) openEditDrawer(row.node);
            break;
        case '+':
            openCreateDrawer(row.node);
            break;
        default:
            return;
    }
    e.preventDefault();
    if (next) select(next.node.id);
};

/* ------------------------------------------------------------------ *
 * Actions
 * ------------------------------------------------------------------ */

/** Mirrors what the server refuses, so the button says why before it is pressed. */
const deleteBlocker = (node) => {
    if (node.children?.length) return 'lg_cannot_delete_parent';
    if (node.posting_role || node.is_system) return 'lg_cannot_delete_system';
    if (node.has_entries) return 'coa_cannot_delete_entries';
    return null;
};

/** The server's own words when it has them. */
const serverMessage = (e, fallback) => {
    const errors = e.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    return e.response?.data?.message || fallback;
};

const payloadOf = (account, overrides = {}) => ({
    code: String(account.code).trim(),
    name: String(account.name).trim(),
    type: account.type,
    parent_id: account.parent_id || null,
    is_active: !!account.is_active,
    description: account.description || null,
    ...overrides,
});

const togglingId = ref(null);

const toggleActive = async (account) => {
    togglingId.value = account.id;
    try {
        await ledgerAccountsApi.update(account.id, payloadOf(account, { is_active: !account.is_active }));
        ElMessage.success(account.is_active ? t('coa_deactivated') : t('coa_activated'));
        await load();
    } catch (e) {
        ElMessage.error(serverMessage(e, t('failed_to_save_account')));
    } finally {
        togglingId.value = null;
    }
};

const deleteAccount = (account) => {
    ElMessageBox.confirm(
        `${t('confirm_delete_account')}\n${account.code} - ${account.name}`,
        t('delete'),
        { confirmButtonText: t('delete'), cancelButtonText: t('cancel'), type: 'warning', confirmButtonClass: 'el-button--danger' }
    ).then(async () => {
        try {
            await ledgerAccountsApi.delete(account.id);
            ElMessage.success(t('account_deleted'));
            const fallback = account.parent_id;
            await load();
            selectedId.value = null;
            if (fallback) select(fallback, { focus: false });
        } catch (e) {
            ElMessage.error(serverMessage(e, t('failed_to_delete_account')));
        }
    }).catch(() => {});
};

/* ------------------------------------------------------------------ *
 * Create / edit
 * ------------------------------------------------------------------ */

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingAccountId = ref(null);
const codeTouched = ref(false);
const nameInput = ref(null);
const blankForm = () => ({ code: '', name: '', type: 'asset', parent_id: null, is_active: true, description: '' });
const form = reactive(blankForm());

/** Entries are posted with the type's sign, so the server refuses changing it. */
const typeLocked = computed(() => isEditMode.value && !!fullNode(editingAccountId.value)?.has_entries);

/** The account being edited and its branch cannot become its own parent. */
const excludedParents = computed(() => {
    const out = new Set();
    if (!isEditMode.value || !editingAccountId.value) return out;
    const walk = (n) => { out.add(n.id); n.children.forEach(walk); };
    const node = fullNode(editingAccountId.value);
    if (node) walk(node);
    return out;
});

/** In tree order, so the indented list reads like the chart. */
const parentOptions = computed(() => {
    const out = [];
    const walk = (list) => list.forEach((n) => {
        if (excludedParents.value.has(n.id)) return;
        if (n.type === form.type || n.id === form.parent_id) out.push(n);
        walk(n.children);
    });
    walk(tree.value);
    return out;
});

const formPath = computed(() => {
    const out = [];
    for (let n = fullNode(form.parent_id); n && out.length < 20; n = fullNode(n.parent_id)) out.unshift(n);
    return out;
});

/** Next free numeric code: after the parent's last child, or after the type's last top-level account. */
const suggestedCode = computed(() => {
    const siblings = accounts.value.filter((a) => a.id !== editingAccountId.value
        && (form.parent_id ? a.parent_id === form.parent_id : a.type === form.type && !a.parent_id));
    const codes = siblings.map((a) => Number(a.code)).filter(Number.isFinite);
    let next;
    if (codes.length) next = Math.max(...codes) + 1;
    else if (form.parent_id) {
        const parentCode = String(fullNode(form.parent_id)?.code ?? '');
        next = /^\d+$/.test(parentCode) ? Number(parentCode + '1') : NaN;
    }
    if (!Number.isFinite(next)) return '';
    const taken = new Set(accounts.value.map((a) => String(a.code)));
    while (taken.has(String(next))) next += 1;
    return String(next);
});

const codeTaken = computed(() => !isEditMode.value
    && accounts.value.some((a) => String(a.code) === String(form.code).trim()));

// A code the user has not typed follows the parent and type they pick.
watch(suggestedCode, (code) => {
    if (formDrawerVisible.value && !isEditMode.value && !codeTouched.value) form.code = code;
});

const useSuggestedCode = () => {
    form.code = suggestedCode.value;
    codeTouched.value = false;
};

const setFormType = (tp) => {
    form.type = tp;
    const parent = fullNode(form.parent_id);
    if (parent && parent.type !== tp) form.parent_id = null;
};

const onParentChange = (id) => {
    const parent = fullNode(id);
    if (parent) form.type = parent.type;
};

const focusName = () => nextTick(() => setTimeout(() => nameInput.value?.focus(), 250));

const openCreateDrawer = (parent = null) => {
    isEditMode.value = false;
    editingAccountId.value = null;
    codeTouched.value = false;
    Object.assign(form, blankForm());
    if (parent) {
        form.parent_id = parent.id;
        form.type = parent.type;
    } else if (filters.type) {
        form.type = filters.type;
    }
    form.code = suggestedCode.value;
    formDrawerVisible.value = true;
    focusName();
};

const openEditDrawer = (account) => {
    isEditMode.value = true;
    editingAccountId.value = account.id;
    codeTouched.value = true;
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

const saveAccount = async (addAnother = false) => {
    if (submittingForm.value) return;
    if (!String(form.code).trim() || !form.name.trim()) {
        ElMessage.warning(t('account_code_and_name_required'));
        return;
    }

    // Balance is left out on purpose: only posted entries move it.
    const payload = payloadOf(form);

    submittingForm.value = true;
    try {
        let savedId = editingAccountId.value;
        if (isEditMode.value) {
            await ledgerAccountsApi.update(editingAccountId.value, payload);
            ElMessage.success(t('account_updated'));
        } else {
            const res = await ledgerAccountsApi.create(payload);
            savedId = res.data?.data?.id;
            ElMessage.success(t('account_saved'));
        }
        await load();
        if (savedId) select(savedId, { focus: false });

        if (addAnother) {
            // Same parent and type; a fresh code and name for the next one.
            codeTouched.value = false;
            Object.assign(form, { name: '', description: '', code: suggestedCode.value });
            focusName();
        } else {
            formDrawerVisible.value = false;
        }
    } catch (e) {
        ElMessage.error(serverMessage(e, t('failed_to_save_account')));
    } finally {
        submittingForm.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Export and print — the whole chart in tree order, not just what is open.
 * ------------------------------------------------------------------ */

const exportCsv = () => {
    const head = [t('account_code'), t('account_name'), t('lg_col_type'), t('coa_level'), t('coa_col_parent'),
        t('coa_normal_side'), t('coa_col_status'), t('coa_own_balance'), t('coa_branch_total')];
    const lines = [head];
    const walk = (list) => list.forEach((n) => {
        lines.push([
            n.code,
            `${'  '.repeat(n.depth)}${n.name}`,
            typeLabel(n.type),
            n.depth + 1,
            fullNode(n.parent_id)?.code || '',
            normalSide(n.type) === 'debit' ? t('coa_side_debit') : t('coa_side_credit'),
            n.is_active ? t('active') : t('inactive'),
            Number(n.balance || 0).toFixed(2),
            n.rollup.toFixed(2),
        ]);
        walk(n.children);
    });
    walk(tree.value);
    const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    // The BOM makes Excel read the Arabic names as UTF-8.
    const blob = new Blob(['﻿' + lines.map((l) => l.map(esc).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `chart-of-accounts_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const printChart = async () => {
    resetFilters();
    setDepth('all');
    await nextTick();
    window.print();
};

/* ------------------------------------------------------------------ */

let initialised = false;

watch(accounts, () => {
    if (!accounts.value.length) return;
    if (!initialised) {
        initialised = true;
        // A large chart opens at two levels; a small one shows everything.
        setDepth(accounts.value.length > 80 ? 2 : 'all');
        const wanted = Number(route.query.account);
        if (wanted && fullNode(wanted)) select(wanted, { focus: false });
    } else if (depth.value) {
        setDepth(depth.value);
    }
    if (selectedId.value && !fullNode(selectedId.value)) selectedId.value = null;
});

watch(() => route.query.account, (id) => {
    if (id && Number(id) !== selectedId.value && fullNode(Number(id))) select(Number(id), { focus: false });
});

onMounted(load);
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

/* ── Summary ──────────────────────────────────────────────────────────── */
.summary { display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.1rem; }

.type-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.8rem; }

.type-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
    padding: 0.85rem 1rem;
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
    top: 0.8rem;
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
.type-total { font-size: 1.15rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.type-count { font-size: 0.75rem; color: var(--ov-subtle); }

.equation {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.7rem 1rem;
    border-radius: 12px;
    border: 1px solid;
}

.equation.is-ok { background: #ecfdf5; border-color: #a7f3d0; }
.equation.is-off { background: #fffbeb; border-color: #fcd34d; }

.eq-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 0.95rem;
}

.is-ok .eq-icon { color: #047857; background: #d1fae5; }
.is-off .eq-icon { color: #b45309; background: #fef3c7; }
.eq-body { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.eq-body > strong { font-size: 0.88rem; color: var(--ov-text); }
.eq-formula { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.5rem; font-size: 0.78rem; color: var(--ov-muted); }
.eq-term { font-weight: 600; }
.eq-term b { color: var(--tone); font-variant-numeric: tabular-nums; }
.eq-op { font-weight: 800; color: var(--ov-subtle); }

/* ── Layout ───────────────────────────────────────────────────────────── */
.coa-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
    gap: 1rem;
    align-items: start;
}

@media (max-width: 1023px) {
    .coa-layout { grid-template-columns: minmax(0, 1fr); }
}

.coa-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    min-width: 0;
}

.coa-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.coa-card-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--ov-text);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.coa-card-head h2 i {
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

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem 0.9rem; margin-bottom: 0.7rem; }
.t-search { flex: 1 1 240px; max-width: 380px; }
.t-search :deep(.el-input__prefix) { color: var(--ov-subtle); }
.toolbar :deep(.el-input__inner::placeholder) { color: var(--ov-subtle); }
.t-zero :deep(.el-checkbox__label) { color: var(--ov-body); font-weight: 600; }
.clear-btn { color: var(--ov-link); font-weight: 700; }

.depth-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem 0.6rem;
    margin-bottom: 0.7rem;
    padding: 0.45rem 0.6rem;
    border-radius: 10px;
    background: var(--ov-soft);
    border: 1px solid var(--ov-border);
}

.depth-label { font-size: 0.78rem; font-weight: 700; color: var(--ov-muted); }
.depth-options { display: inline-flex; flex-wrap: wrap; gap: 0.25rem; }

.depth-btn {
    padding: 0.15rem 0.6rem;
    border: 1px solid var(--ov-border);
    border-radius: 999px;
    background: #fff;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ov-body);
    cursor: pointer;
}

.depth-btn:hover { border-color: #93c5fd; color: #1e40af; }
.depth-btn.is-active { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.depth-btn:focus-visible { outline: 2px solid #1d4ed8; outline-offset: 2px; }
.depth-spacer { flex: 1; }
.tree-tool { color: var(--ov-link); font-weight: 700; }

/* ── Tree ─────────────────────────────────────────────────────────────── */
.tree-cols {
    display: flex;
    justify-content: space-between;
    padding: 0.4rem 0.75rem;
    padding-inline-end: 2.6rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ov-muted);
    background: var(--ov-soft);
    border: 1px solid var(--ov-border);
    border-bottom: 0;
    border-radius: 10px 10px 0 0;
}

.tree {
    max-height: calc(100vh - 230px);
    min-height: 240px;
    overflow: auto;
    border: 1px solid var(--ov-border);
    border-radius: 0 0 10px 10px;
    overscroll-behavior: contain;
}

@media (max-width: 1023px) { .tree { max-height: 70vh; } }

.tree-row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 40px;
    padding: 0.25rem 0.6rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    outline: none;
}

.tree-row:last-child { border-bottom: 0; }
.tree-row:hover { background: #f5f8ff; }
.tree-row:focus-visible { box-shadow: inset 0 0 0 2px #1d4ed8; }
.tree-row.is-root { background: color-mix(in srgb, var(--tone) 5%, #fff); }
.tree-row.is-root::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 3px;
    background: var(--tone);
}
.tree-row.is-selected { background: #e0ecff; }
.tree-row.is-selected::after {
    content: '';
    position: absolute;
    inset-block: 4px;
    inset-inline-end: 0;
    width: 3px;
    border-radius: 3px;
    background: #1d4ed8;
}

.indent {
    flex-shrink: 0;
    align-self: stretch;
    width: 1.1rem;
    margin-block: -0.25rem;
    border-inline-start: 1px dashed #cbd5e1;
    margin-inline-start: 0.45rem;
    margin-inline-end: -0.45rem;
}

.twisty {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--ov-muted);
    font-size: 0.7rem;
    cursor: pointer;
}

.twisty:hover { background: #e2e8f0; color: var(--ov-text); }
.twisty i { transition: transform 0.15s ease; }
.twisty i.is-closed { transform: rotate(-90deg); }
[dir='rtl'] .twisty i.is-closed { transform: rotate(90deg); }
.twisty.is-leaf { cursor: default; font-size: 0.3rem; color: #cbd5e1; }
.twisty.is-leaf:hover { background: transparent; }

.row-name { display: flex; align-items: center; flex-wrap: wrap; gap: 0.3rem; flex: 1; min-width: 0; }
.row-title { font-weight: 600; color: var(--ov-text); overflow-wrap: anywhere; }
.is-group > .row-name .row-title { font-weight: 800; }
.is-inactive .row-title { color: var(--ov-subtle); text-decoration: line-through; text-decoration-color: #cbd5e1; }

.row-add {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    color: var(--ov-subtle);
    font-size: 0.72rem;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.tree-row:hover .row-add, .tree-row:focus-within .row-add, .tree-row.is-selected .row-add { opacity: 1; }
.row-add:hover { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
@media (hover: none) { .row-add { opacity: 1; } }

.kbd-hint { margin: 0.55rem 0 0; font-size: 0.74rem; color: var(--ov-subtle); }

.code-badge {
    display: inline-block;
    flex-shrink: 0;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0.02rem 0.42rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
    direction: ltr;
}

.code-badge.is-lg { font-size: 0.9rem; padding: 0.1rem 0.55rem; }

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
.meta-chip.is-on { color: #047857; background: #d1fae5; }
.meta-chip.is-group { color: #1e40af; background: #dbeafe; }
.meta-chip.is-post { color: #0f766e; background: #ccfbf1; }

.type-chip {
    display: inline-block;
    padding: 0.05rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--tone);
    background: var(--tone-bg);
}

.bal { flex-shrink: 0; font-weight: 700; color: var(--ov-text); font-variant-numeric: tabular-nums; white-space: nowrap; }
.bal.is-zero { color: var(--ov-subtle); font-weight: 600; }
.bal.is-rollup { font-weight: 800; }

/* ── Detail ───────────────────────────────────────────────────────────── */
.detail-card {
    position: sticky;
    top: 1rem;
    border-top: 3px solid var(--tone, var(--ov-border));
    scroll-margin-top: 1rem;
}

.back-link {
    display: none;
    margin-bottom: 0.6rem;
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ov-link);
    cursor: pointer;
}

@media (max-width: 1023px) {
    .detail-card { position: static; }
    .back-link { display: inline-flex; align-items: center; gap: 0.35rem; }
}

.crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem 0.4rem; margin-bottom: 0.75rem; font-size: 0.78rem; }
.crumb-sep { font-size: 0.6rem; color: #94a3b8; }
[dir='ltr'] .crumb-sep { transform: scaleX(-1); }

.crumb {
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    font-weight: 600;
    color: var(--ov-link);
    cursor: pointer;
}

.crumb:hover { text-decoration: underline; }

.d-head { margin-bottom: 0.85rem; }
.d-id { display: flex; flex-direction: column; align-items: flex-start; gap: 0.4rem; }
.d-id h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--ov-text); overflow-wrap: anywhere; }
.d-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }

.d-balance {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-end;
    gap: 0.6rem;
    padding: 0.8rem 0.95rem;
    margin-bottom: 0.9rem;
    border-radius: 12px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--tone, #1d4ed8) 8%, #fff) 0%, #fff 100%);
    border: 1px solid var(--ov-border);
}

.d-balance > div { display: flex; flex-direction: column; gap: 0.1rem; }
.d-balance span { font-size: 0.76rem; font-weight: 700; color: var(--ov-muted); }
.d-balance strong { font-size: 1.45rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.d-own { align-items: flex-end; }
.d-own b { font-variant-numeric: tabular-nums; color: var(--ov-text); }

.facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    margin: 0 0 0.95rem;
}

.facts > div { padding: 0.5rem 0.7rem; border: 1px solid var(--ov-border); border-radius: 10px; background: var(--ov-soft); min-width: 0; }
.facts > div.is-wide { grid-column: 1 / -1; }
.facts dt { font-size: 0.72rem; font-weight: 700; color: var(--ov-muted); margin-bottom: 0.15rem; }
.facts dd { margin: 0; font-weight: 700; color: var(--ov-text); font-variant-numeric: tabular-nums; }
.facts dd small { font-weight: 600; color: var(--ov-subtle); }
.facts code { font-size: 0.8rem; color: #5b21b6; }
.d-desc { font-weight: 500 !important; line-height: 1.7; white-space: pre-line; }

.side-chip { display: inline-block; padding: 0 0.5rem; border-radius: 999px; font-size: 0.78rem; }
.side-chip.is-debit { color: var(--c-debit); background: #d1fae5; }
.side-chip.is-credit { color: var(--c-credit); background: #fef3c7; }

.d-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 1rem; }
.d-actions .el-button + .el-button { margin-inline-start: 0; }
.d-statement { text-decoration: none; }

.children h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.5rem;
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--ov-text);
}

.children ul { list-style: none; margin: 0; padding: 0; border: 1px solid var(--ov-border); border-radius: 10px; overflow: hidden; }
.children li + li { border-top: 1px solid #f1f5f9; }

.child-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: 0;
    background: #fff;
    font: inherit;
    text-align: start;
    cursor: pointer;
}

.child-row:hover { background: #f5f8ff; }
.child-row:focus-visible { outline: 2px solid #1d4ed8; outline-offset: -2px; }
.child-name { flex: 1; min-width: 0; font-weight: 600; color: var(--ov-text); overflow-wrap: anywhere; }
.child-row.is-inactive .child-name { color: var(--ov-subtle); }

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.9rem; padding: 3rem 1rem; text-align: center; }
.empty-state.is-compact { padding: 2.5rem 1rem; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; border-radius: 18px; background: #eff6ff; color: #1d4ed8; font-size: 1.5rem; }
.empty-state p { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--ov-muted); }

/* ── Drawer ───────────────────────────────────────────────────────────── */
.coa-drawer :deep(.el-drawer__header) {
    margin-bottom: 0;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--ov-border);
    color: var(--ov-text);
    font-weight: 800;
    font-size: 1.05rem;
}

.coa-drawer :deep(.el-drawer__footer) { border-top: 1px solid var(--ov-border); padding-top: 0.9rem; }
.account-form :deep(.el-form-item__label) { color: var(--ov-body); font-weight: 700; }
.drawer-footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.6rem; }
.drawer-footer .el-button + .el-button { margin-inline-start: 0; }

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

.opt-row { display: inline-flex; align-items: center; gap: 0.4rem; }

.form-pair { display: grid; grid-template-columns: 170px minmax(0, 1fr); gap: 0 0.9rem; }
@media (max-width: 520px) { .form-pair { grid-template-columns: minmax(0, 1fr); } }

.field-hint { margin: 0.3rem 0 0; font-size: 0.76rem; line-height: 1.6; color: var(--ov-subtle); }
.field-hint.is-error { color: var(--c-loss); font-weight: 700; }
.link-btn { padding: 0; border: 0; background: none; font: inherit; font-weight: 700; color: var(--ov-link); cursor: pointer; }
.link-btn:hover { text-decoration: underline; }

.placement {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.6rem 0.8rem;
    margin-bottom: 1.1rem;
    border-radius: 10px;
    border: 1px dashed color-mix(in srgb, var(--tone) 45%, #fff);
    background: color-mix(in srgb, var(--tone) 4%, #fff);
}

.placement-label { font-size: 0.74rem; font-weight: 700; color: var(--ov-muted); }
.placement-path { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem 0.4rem; font-size: 0.8rem; color: var(--ov-body); }
.placement-path strong { color: var(--tone); }

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

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print { display: none !important; }
    .coa-layout { display: block; }
    .coa-card { box-shadow: none; border: 0; padding: 0; }
    .tree { max-height: none; overflow: visible; border: 0; }
    .tree-row { min-height: 0; padding-block: 0.1rem; break-inside: avoid; }
    .tree-row.is-selected { background: none; }
    .tree-row.is-selected::after { display: none; }
    .twisty { visibility: hidden; }
}
</style>
