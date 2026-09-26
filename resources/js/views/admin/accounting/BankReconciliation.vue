<template>
    <div class="accounting-page bank-reconciliation">
        <AdminPageHeader
            icon="fas fa-scale-balanced text-primary"
            :title="$t('bank_reconciliation')"
            :subtitle="$t('bank_reconciliation_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag no-print">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button class="no-print" :loading="loading" @click="refresh">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button v-if="current" type="primary" plain class="no-print" @click="printPage">
                    <i class="fas fa-print mr-1"></i> {{ $t('ag_print') }}
                </el-button>
                <el-button v-else type="primary" class="no-print" :disabled="!accounts.length" @click="openCreate()">
                    <i class="fas fa-plus mr-1"></i> {{ $t('start_reconciliation') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="refresh">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ════════════════════════ The working sheet ════════════════════════ -->
        <template v-if="sheetId">
            <div v-if="!current" class="br-card"><el-skeleton :rows="8" animated /></div>

            <template v-else>
                <!-- ── Which statement ─────────────────────────────────────── -->
                <section class="br-card sheet-head">
                    <el-button class="no-print back-btn" text @click="goToList">
                        <i class="fas fa-arrow-left dir-arrow-back mr-1"></i> {{ $t('back_to_list') }}
                    </el-button>

                    <div class="sheet-title">
                        <span class="acc-icon" :class="current.account?.posting_role === 'cash' ? 'is-cash' : ''">
                            <i class="fas" :class="current.account?.posting_role === 'cash' ? 'fa-cash-register' : 'fa-building-columns'"></i>
                        </span>
                        <div class="sheet-title-text">
                            <h2>
                                <span class="code-badge">{{ current.reference }}</span>
                                {{ current.account?.name }}
                                <span class="status-chip" :class="isOpen ? 'is-open' : 'is-done'">
                                    <i class="fas" :class="isOpen ? 'fa-pen' : 'fa-lock'"></i>
                                    {{ isOpen ? $t('open_period') : $t('completed_state') }}
                                </span>
                            </h2>
                            <p class="sheet-meta">
                                <span><i class="far fa-calendar"></i> {{ $t('br_statement_to', { date: dateOnly(current.statement_date) }) }}</span>
                                <span v-if="current.account?.code"><i class="fas fa-hashtag"></i> {{ current.account.code }}</span>
                                <span v-if="!isOpen && current.completed_at">
                                    <i class="fas fa-user-check"></i>
                                    {{ $t('br_completed_on', { date: dateTime(current.completed_at), name: current.completed_by?.name || '—' }) }}
                                </span>
                            </p>
                            <p v-if="current.notes" class="sheet-notes"><i class="far fa-note-sticky"></i> {{ current.notes }}</p>
                        </div>
                    </div>

                    <div class="sheet-actions no-print">
                        <template v-if="isOpen">
                            <el-button :disabled="busy" @click="openEdit">
                                <i class="fas fa-pen-to-square mr-1"></i> {{ $t('br_edit_statement') }}
                            </el-button>
                            <el-button type="danger" plain :disabled="busy" @click="removeReconciliation(current)">
                                <i class="fas fa-trash mr-1"></i> {{ $t('delete') }}
                            </el-button>
                            <el-tooltip :disabled="summary.is_reconciled" :content="$t('br_complete_blocked')" placement="top">
                                <span>
                                    <el-button
                                        type="success"
                                        :disabled="!summary.is_reconciled || inflight > 0"
                                        :loading="saving"
                                        @click="complete"
                                    >
                                        <i class="fas fa-check-double mr-1"></i> {{ $t('complete_reconciliation') }}
                                    </el-button>
                                </span>
                            </el-tooltip>
                        </template>
                        <el-button v-else type="warning" plain :loading="saving" @click="reopen">
                            <i class="fas fa-lock-open mr-1"></i> {{ $t('reopen_period') }}
                        </el-button>
                    </div>
                </section>

                <!-- ── The answer ──────────────────────────────────────────── -->
                <!-- book balance − still in transit = what the statement should
                     say; anything between that and what it does say is not timing. -->
                <section class="result" :class="summary.is_reconciled ? 'is-ok' : 'is-bad'">
                    <div class="result-main">
                        <span class="result-label">
                            <i class="fas" :class="summary.is_reconciled ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                            {{ summary.is_reconciled ? $t('br_reconciled') : $t('difference') }}
                        </span>
                        <strong class="result-amount">{{ summary.is_reconciled ? money(0) : money(summary.difference) }}</strong>
                        <span class="result-note">
                            {{ summary.is_reconciled ? $t('all_differences_are_timing') : $t('difference_is_not_timing') }}
                        </span>
                    </div>

                    <div class="formula">
                        <span class="f-term">
                            <small>{{ $t('book_balance') }}</small>
                            <b>{{ plain(summary.book_balance) }}</b>
                        </span>
                        <span class="f-op">−</span>
                        <span class="f-term is-transit">
                            <small>{{ $t('still_outstanding') }}</small>
                            <b>{{ plain(summary.outstanding_total) }}</b>
                        </span>
                        <span class="f-op">=</span>
                        <span class="f-term">
                            <small>{{ $t('br_should_show') }}</small>
                            <b>{{ plain(summary.cleared_total) }}</b>
                        </span>
                        <span class="f-op">{{ summary.is_reconciled ? '=' : '≠' }}</span>
                        <span class="f-term is-statement">
                            <small>{{ $t('br_statement_shows') }}</small>
                            <b>{{ plain(summary.statement_balance) }}</b>
                        </span>
                    </div>

                    <div class="result-foot">
                        <div class="progress">
                            <span class="progress-label">
                                {{ $t('br_ticked_progress', { done: formatNumber(counts.all - counts.outstanding), total: formatNumber(counts.all) }) }}
                                <i v-if="inflight > 0" class="fas fa-circle-notch fa-spin updating" :title="$t('br_updating')"></i>
                            </span>
                            <span class="progress-track"><span class="progress-bar" :style="{ width: progressPct + '%' }"></span></span>
                        </div>
                        <span class="transit-split">
                            <span><i class="fas fa-arrow-down is-in"></i> {{ $t('br_deposits_in_transit') }} <b>{{ plain(summary.deposits_in_transit) }}</b></span>
                            <span><i class="fas fa-arrow-up is-out"></i> {{ $t('br_payments_in_transit') }} <b>{{ plain(summary.payments_in_transit) }}</b></span>
                        </span>
                    </div>
                </section>

                <!-- ── Help finding the remainder ──────────────────────────── -->
                <section v-if="!summary.is_reconciled && isOpen && current.movements.length" class="br-card hints no-print">
                    <header class="br-card-head">
                        <h2><i class="fas fa-magnifying-glass-dollar"></i> {{ $t('br_find_difference') }}</h2>
                    </header>

                    <div v-if="suggestions.length" class="suggestions">
                        <div v-for="s in suggestions" :key="s.row.id" class="suggestion">
                            <span class="s-text">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                {{ s.cleared ? $t('br_tick_would_close') : $t('br_untick_would_close') }}
                            </span>
                            <span class="s-row">
                                <span class="code-badge">{{ s.row.entry_number }}</span>
                                {{ dateOnly(s.row.entry_date) }} ·
                                <b :class="s.row.amount >= 0 ? 'is-in' : 'is-out'">{{ plain(s.row.amount) }}</b>
                            </span>
                            <el-button size="small" type="primary" plain @click="setCleared([s.row], s.cleared)">
                                {{ s.cleared ? $t('br_tick') : $t('br_untick') }}
                            </el-button>
                        </div>
                    </div>

                    <ul class="hint-list">
                        <li>
                            <i class="fas fa-receipt"></i>
                            <span>{{ $t('br_hint_missing') }}</span>
                            <router-link to="/admin/accounting/journal?new=1" class="help-link">{{ $t('br_record_entry') }} <i class="fas fa-arrow-right dir-arrow"></i></router-link>
                        </li>
                        <li v-if="halfMatch">
                            <i class="fas fa-right-left"></i>
                            <span>{{ $t('br_hint_sign', { amount: plain(Math.abs(summary.difference) / 2) }) }}</span>
                        </li>
                        <li v-if="duplicateIds.size">
                            <i class="fas fa-clone"></i>
                            <span>{{ $t('br_hint_duplicate') }}</span>
                        </li>
                        <li v-if="divisibleByNine">
                            <i class="fas fa-shuffle"></i>
                            <span>{{ $t('br_hint_transposed') }}</span>
                        </li>
                        <li>
                            <i class="fas fa-file-invoice"></i>
                            <span>{{ $t('br_hint_balance') }}</span>
                            <el-button link type="primary" @click="openEdit">{{ $t('br_edit_statement') }}</el-button>
                        </li>
                    </ul>
                </section>

                <!-- ── The movements ───────────────────────────────────────── -->
                <section class="br-card">
                    <header class="br-card-head">
                        <h2><i class="fas fa-list-check"></i> {{ $t('br_movements') }}</h2>
                        <span v-if="isOpen" class="section-note">{{ $t('tick_what_the_bank_has_seen') }}</span>
                    </header>

                    <div class="toolbar no-print">
                        <el-segmented v-model="show" :options="showOptions" />
                        <el-segmented v-model="kind" :options="kindOptions" />
                        <el-input
                            v-model="search"
                            clearable
                            class="t-search"
                            :placeholder="$t('br_search_placeholder')"
                        >
                            <template #prefix><i class="fas fa-search"></i></template>
                        </el-input>
                        <div v-if="isOpen" class="bulk">
                            <el-button size="small" :disabled="!tickable.length" @click="setCleared(tickable, true)">
                                <i class="far fa-square-check mr-1"></i> {{ $t('br_tick_shown', { n: formatNumber(tickable.length) }) }}
                            </el-button>
                            <el-button size="small" :disabled="!untickable.length" @click="setCleared(untickable, false)">
                                <i class="far fa-square mr-1"></i> {{ $t('br_untick_shown', { n: formatNumber(untickable.length) }) }}
                            </el-button>
                        </div>
                    </div>

                    <el-table
                        v-if="current.movements.length"
                        :data="rows"
                        :row-class-name="rowClass"
                        style="width: 100%"
                        :empty-text="show === 'outstanding' && !search ? $t('br_all_ticked') : $t('br_no_rows_match')"
                        @row-click="onRowClick"
                    >
                        <el-table-column width="52" align="center" class-name="no-print">
                            <template #default="{ row }">
                                <span @click.stop>
                                    <el-checkbox
                                        :model-value="row.is_cleared"
                                        :disabled="!isOpen || !!row.cleared_in || pending.has(row.id)"
                                        :aria-label="row.entry_number"
                                        @change="(v) => setCleared([row], v)"
                                    />
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('date')" width="110">
                            <template #default="{ row }"><span class="num">{{ dateOnly(row.entry_date) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('entry_number')" width="140">
                            <template #default="{ row }">
                                <router-link
                                    class="code-badge entry-link"
                                    :to="{ path: '/admin/accounting/journal', query: { search: row.entry_number } }"
                                    :title="$t('br_view_entry')"
                                    @click.stop
                                >{{ row.entry_number }}</router-link>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('narration')" min-width="220">
                            <template #default="{ row }">
                                <span class="narration">{{ row.line_description || row.description || '—' }}</span>
                                <span v-if="duplicateIds.has(row.id)" class="mini-tag is-warn"><i class="fas fa-clone"></i> {{ $t('br_possible_duplicate') }}</span>
                                <span v-if="suggestedIds.has(row.id)" class="mini-tag is-hint"><i class="fas fa-wand-magic-sparkles"></i> {{ $t('br_closes_it') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('br_deposit')" width="140" align="right" header-align="right">
                            <template #default="{ row }">
                                <span v-if="row.amount > 0" class="num is-in">{{ plain(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('br_payment')" width="140" align="right" header-align="right">
                            <template #default="{ row }">
                                <span v-if="row.amount < 0" class="num is-out">{{ plain(-row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" width="150" align="center">
                            <template #default="{ row }">
                                <span v-if="row.cleared_in" class="state is-earlier">
                                    <i class="fas fa-lock"></i> {{ $t('br_cleared_in', { ref: row.cleared_in }) }}
                                </span>
                                <span v-else class="state" :class="row.is_cleared ? 'is-cleared' : 'is-transit'">
                                    <i class="fas" :class="row.is_cleared ? 'fa-check' : 'fa-hourglass-half'"></i>
                                    {{ row.is_cleared ? $t('cleared') : $t('in_transit') }}
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else :description="$t('no_movements_up_to_this_date')" />

                    <div v-if="rows.length" class="rows-total">
                        <span>{{ $t('br_shown_total', { n: formatNumber(rows.length) }) }}</span>
                        <span><small>{{ $t('br_deposit') }}</small> <b class="num is-in">{{ plain(rowsTotals.in) }}</b></span>
                        <span><small>{{ $t('br_payment') }}</small> <b class="num is-out">{{ plain(rowsTotals.out) }}</b></span>
                    </div>
                </section>
            </template>
        </template>

        <!-- ════════════════════════ Accounts and history ═════════════════════ -->
        <template v-else>
            <div v-if="loading && !listLoaded" class="br-card"><el-skeleton :rows="6" animated /></div>

            <template v-else-if="listLoaded">
                <!-- ── Where each account stands ───────────────────────────── -->
                <section class="br-card">
                    <header class="br-card-head">
                        <h2><i class="fas fa-building-columns"></i> {{ $t('br_accounts_title') }}</h2>
                    </header>

                    <div v-if="accounts.length" class="accounts">
                        <article
                            v-for="account in accounts"
                            :key="account.id"
                            class="account"
                            :class="{ 'has-open': account.open_reconciliation, 'is-stale': isStale(account) }"
                        >
                            <div class="account-top">
                                <span class="acc-icon" :class="account.posting_role === 'cash' ? 'is-cash' : ''">
                                    <i class="fas" :class="account.posting_role === 'cash' ? 'fa-cash-register' : 'fa-building-columns'"></i>
                                </span>
                                <div class="account-name">
                                    <strong>{{ account.name }}</strong>
                                    <span class="code-badge">{{ account.code }}</span>
                                </div>
                            </div>

                            <p class="account-state">
                                <template v-if="account.last_reconciled_date">
                                    <i class="fas fa-circle-check is-ok-text"></i>
                                    {{ $t('br_last_reconciled', { date: account.last_reconciled_date }) }}
                                    <span class="muted">· {{ $t('br_days_ago', { n: formatNumber(daysSince(account.last_reconciled_date)) }) }}</span>
                                </template>
                                <template v-else>
                                    <i class="fas fa-circle-exclamation is-warn-text"></i> {{ $t('br_never_reconciled') }}
                                </template>
                            </p>

                            <el-button
                                v-if="account.open_reconciliation"
                                type="primary"
                                class="account-action"
                                @click="openSheet(account.open_reconciliation.id)"
                            >
                                <i class="fas fa-play mr-1"></i>
                                {{ $t('br_continue', { ref: account.open_reconciliation.reference, date: account.open_reconciliation.statement_date }) }}
                            </el-button>
                            <el-button v-else class="account-action" @click="openCreate(account.id)">
                                <i class="fas fa-plus mr-1"></i> {{ $t('start_reconciliation') }}
                            </el-button>
                        </article>
                    </div>

                    <el-empty v-else :description="$t('br_no_accounts')" />
                </section>

                <!-- ── History ─────────────────────────────────────────────── -->
                <section class="br-card">
                    <header class="br-card-head">
                        <h2><i class="fas fa-clock-rotate-left"></i> {{ $t('reconciliations') }}</h2>
                        <div class="toolbar-inline">
                            <el-segmented v-model="statusFilter" :options="statusOptions" @change="loadList(1)" />
                            <el-select
                                v-if="accounts.length > 1"
                                v-model="accountFilter"
                                clearable
                                class="t-account"
                                :placeholder="$t('br_all_accounts')"
                                @change="loadList(1)"
                            >
                                <el-option v-for="a in accounts" :key="a.id" :label="`${a.code} — ${a.name}`" :value="a.id" />
                            </el-select>
                        </div>
                    </header>

                    <el-table
                        v-if="reconciliations.length"
                        v-loading="loading"
                        :data="reconciliations"
                        row-class-name="is-clickable"
                        style="width: 100%"
                        @row-click="(row) => openSheet(row.id)"
                    >
                        <el-table-column :label="$t('br_reference')" width="120">
                            <template #default="{ row }"><span class="code-badge">{{ row.reference }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('br_account')" min-width="180">
                            <template #default="{ row }">
                                <span class="muted">{{ row.account?.code }}</span> {{ row.account?.name }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('statement_date')" width="130">
                            <template #default="{ row }"><span class="num">{{ dateOnly(row.statement_date) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('statement_balance')" width="170" align="right" header-align="right">
                            <template #default="{ row }"><span class="num">{{ money(row.statement_balance) }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('br_result')" width="170" align="center">
                            <template #default="{ row }">
                                <span class="state" :class="row.summary.is_reconciled ? 'is-cleared' : 'is-bad'">
                                    <i class="fas" :class="row.summary.is_reconciled ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                                    {{ row.summary.is_reconciled ? $t('br_reconciled') : $t('br_off_by', { amount: plain(row.summary.difference) }) }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" min-width="170">
                            <template #default="{ row }">
                                <span class="status-chip" :class="row.status === 'completed' ? 'is-done' : 'is-open'">
                                    <i class="fas" :class="row.status === 'completed' ? 'fa-lock' : 'fa-pen'"></i>
                                    {{ row.status === 'completed' ? $t('completed_state') : $t('open_period') }}
                                </span>
                                <small v-if="row.status === 'completed' && row.completed_at" class="done-by">
                                    {{ dateOnly(row.completed_at) }}<template v-if="row.completed_by"> · {{ row.completed_by.name }}</template>
                                </small>
                            </template>
                        </el-table-column>
                        <el-table-column width="110" align="center">
                            <template #default="{ row }">
                                <div class="row-actions" @click.stop>
                                    <el-button size="small" @click="openSheet(row.id)">{{ $t('open_sheet') }}</el-button>
                                    <el-button
                                        v-if="row.status === 'open'"
                                        size="small"
                                        type="danger"
                                        text
                                        :aria-label="$t('delete')"
                                        @click="removeReconciliation(row)"
                                    ><i class="fas fa-trash"></i></el-button>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else :description="$t('no_reconciliations_yet')">
                        <el-button v-if="accounts.length && !statusFilter && !accountFilter" type="primary" @click="openCreate()">
                            {{ $t('start_reconciliation') }}
                        </el-button>
                    </el-empty>

                    <div v-if="pagination.last_page > 1" class="pager">
                        <el-pagination
                            layout="prev, pager, next"
                            :current-page="pagination.current_page"
                            :page-size="pagination.per_page"
                            :total="pagination.total"
                            @current-change="loadList"
                        />
                    </div>
                </section>
            </template>
        </template>

        <!-- ════════════════════════ Start / correct a statement ══════════════ -->
        <el-dialog
            v-model="formVisible"
            :title="editing ? $t('br_edit_statement') : $t('start_reconciliation')"
            width="480px"
            destroy-on-close
        >
            <el-form :model="form" label-position="top" @submit.prevent="submit">
                <el-form-item :label="$t('br_account')" required>
                    <el-select v-model="form.account_id" :disabled="editing" style="width: 100%">
                        <el-option v-for="a in accounts" :key="a.id" :label="`${a.code} — ${a.name}`" :value="a.id">
                            <span>{{ a.code }} — {{ a.name }}</span>
                            <span class="opt-meta">
                                {{ a.open_reconciliation ? a.open_reconciliation.reference : (a.last_reconciled_date || $t('br_never_reconciled')) }}
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>

                <el-alert v-if="!editing && formAccount?.open_reconciliation" type="warning" :closable="false" show-icon class="mb-3">
                    <template #title>
                        {{ $t('br_open_exists', { ref: formAccount.open_reconciliation.reference, date: formAccount.open_reconciliation.statement_date }) }}
                    </template>
                    <el-button size="small" type="warning" class="mt-1" @click="continueFromDialog">
                        {{ $t('br_continue', { ref: formAccount.open_reconciliation.reference, date: formAccount.open_reconciliation.statement_date }) }}
                    </el-button>
                </el-alert>

                <el-form-item :label="$t('statement_date')" required>
                    <el-date-picker
                        v-model="form.statement_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :disabled-date="(d) => d > new Date()"
                        :clearable="false"
                        style="width: 100%"
                    />
                    <small v-if="!editing && formAccount?.last_reconciled_date" class="hint">
                        {{ $t('br_last_reconciled_hint', { date: formAccount.last_reconciled_date }) }}
                    </small>
                </el-form-item>

                <el-form-item :label="$t('statement_balance')" required>
                    <el-input v-model="form.statement_balance" type="number" step="0.01" inputmode="decimal" class="num-input">
                        <template #append>{{ baseCode }}</template>
                    </el-input>
                    <small class="hint">{{ $t('statement_balance_hint') }}</small>
                </el-form-item>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" :placeholder="$t('br_notes_placeholder')" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="formVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                    {{ editing ? $t('save') : $t('start_reconciliation') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { bankReconciliationsApi } from '@/api/bankReconciliations';
import { formatMoney, formatNumber, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const dateOnly = (value) => String(value || '').slice(0, 10);
const dateTime = (value) => (value ? new Date(value).toLocaleString(numberLocale(), { dateStyle: 'medium', timeStyle: 'short' }) : '');

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');

const loading = ref(false);
const saving = ref(false);
const error = ref('');

/* ------------------------------------------------------------------ *
 * Accounts and history
 * ------------------------------------------------------------------ */

const reconciliations = ref([]);
const accounts = ref([]);
const listLoaded = ref(false);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const statusFilter = ref('');
const accountFilter = ref('');

const statusOptions = computed(() => [
    { label: t('all'), value: '' },
    { label: t('open_period'), value: 'open' },
    { label: t('completed_state'), value: 'completed' },
]);

const loadList = async (page = 1) => {
    loading.value = true;
    error.value = '';
    try {
        const res = await bankReconciliationsApi.getAll({
            page,
            per_page: 20,
            status: statusFilter.value || undefined,
            account_id: accountFilter.value || undefined,
        });
        const data = res.data?.data || {};
        reconciliations.value = data.reconciliations || [];
        accounts.value = data.accounts || [];
        pagination.value = data.pagination || pagination.value;
        listLoaded.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const daysSince = (date) => Math.max(0, Math.floor((Date.now() - new Date(`${date}T00:00:00`).getTime()) / 86400000));

// Bank statements come monthly; an account not proved in over a month and a
// half has at least one statement nobody held it against.
const isStale = (account) => !account.open_reconciliation && (!account.last_reconciled_date || daysSince(account.last_reconciled_date) > 45);

/* ------------------------------------------------------------------ *
 * The sheet — kept in the URL so a refresh or a shared link lands on it
 * ------------------------------------------------------------------ */

const current = ref(null);
const sheetId = computed(() => Number(route.query.id) || null);
const isOpen = computed(() => current.value?.status === 'open');
const summary = computed(() => current.value?.summary || {});

const openSheet = (id) => router.push({ query: { id: String(id) } });
const goToList = () => router.push({ query: {} });

const loadSheet = async (id, { quiet = false } = {}) => {
    if (!quiet) loading.value = true;
    error.value = '';
    try {
        const res = await bankReconciliationsApi.get(id);
        current.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const refresh = () => (sheetId.value ? loadSheet(sheetId.value) : loadList(pagination.value.current_page));

watch(sheetId, (id, before) => {
    if (id) {
        if (current.value?.id !== id) current.value = null;
        resetFilters();
        loadSheet(id);
    } else {
        current.value = null;
        if (before || !listLoaded.value) loadList(pagination.value.current_page);
    }
});

/* ------------------------------------------------------------------ *
 * Filtering the movements
 * ------------------------------------------------------------------ */

const show = ref('all');
const kind = ref('all');
const search = ref('');

const resetFilters = () => {
    show.value = 'all';
    kind.value = 'all';
    search.value = '';
};

const movements = computed(() => current.value?.movements || []);

const counts = computed(() => {
    const ownRows = movements.value.filter((m) => !m.cleared_in);
    return {
        all: ownRows.length,
        outstanding: ownRows.filter((m) => !m.is_cleared).length,
        cleared: ownRows.filter((m) => m.is_cleared).length,
        earlier: movements.value.length - ownRows.length,
    };
});

const progressPct = computed(() => (counts.value.all ? Math.round(((counts.value.all - counts.value.outstanding) / counts.value.all) * 100) : 100));

const showOptions = computed(() => [
    { label: t('br_filter_all', { n: formatNumber(counts.value.all) }), value: 'all' },
    { label: t('br_filter_outstanding', { n: formatNumber(counts.value.outstanding) }), value: 'outstanding' },
    { label: t('br_filter_cleared', { n: formatNumber(counts.value.cleared) }), value: 'cleared' },
    ...(counts.value.earlier ? [{ label: t('br_filter_earlier', { n: formatNumber(counts.value.earlier) }), value: 'earlier' }] : []),
]);

const kindOptions = computed(() => [
    { label: t('br_kind_all'), value: 'all' },
    { label: t('br_kind_in'), value: 'in' },
    { label: t('br_kind_out'), value: 'out' },
]);

const rows = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return movements.value.filter((m) => {
        if (show.value === 'earlier' ? !m.cleared_in : m.cleared_in) return false;
        if (show.value === 'outstanding' && m.is_cleared) return false;
        if (show.value === 'cleared' && !m.is_cleared) return false;
        if (kind.value === 'in' && m.amount <= 0) return false;
        if (kind.value === 'out' && m.amount >= 0) return false;
        if (!needle) return true;
        return [m.entry_number, m.line_description, m.description, String(Math.abs(m.amount)), Math.abs(m.amount).toFixed(2)]
            .some((v) => String(v || '').toLowerCase().includes(needle));
    });
});

const rowsTotals = computed(() => rows.value.reduce(
    (acc, m) => {
        if (m.amount > 0) acc.in += m.amount;
        else acc.out -= m.amount;
        return acc;
    },
    { in: 0, out: 0 },
));

const tickable = computed(() => rows.value.filter((m) => !m.cleared_in && !m.is_cleared));
const untickable = computed(() => rows.value.filter((m) => !m.cleared_in && m.is_cleared));

/* ------------------------------------------------------------------ *
 * Finding the remainder
 *
 * difference = book − in transit − statement. Ticking a movement of amount a
 * takes it out of transit and moves the difference by +a; unticking moves it
 * by −a. So a single movement whose amount is exactly −difference (to tick) or
 * +difference (to untick) is the likeliest one-click explanation.
 * ------------------------------------------------------------------ */

const near = (a, b) => Math.abs(a - b) < 0.005;

const suggestions = computed(() => {
    const d = Number(summary.value.difference || 0);
    if (!isOpen.value || near(d, 0)) return [];
    const out = [];
    for (const m of movements.value) {
        if (m.cleared_in) continue;
        if (!m.is_cleared && near(m.amount, -d)) out.push({ row: m, cleared: true });
        else if (m.is_cleared && near(m.amount, d)) out.push({ row: m, cleared: false });
        if (out.length >= 3) break;
    }
    return out;
});

const suggestedIds = computed(() => new Set(suggestions.value.map((s) => s.row.id)));

// A movement entered the wrong way round is off by twice its amount.
const halfMatch = computed(() => {
    const half = Math.abs(Number(summary.value.difference || 0)) / 2;
    return half > 0 && movements.value.some((m) => !m.cleared_in && near(Math.abs(m.amount), half));
});

const divisibleByNine = computed(() => {
    const cents = Math.round(Math.abs(Number(summary.value.difference || 0)) * 100);
    return cents > 0 && cents % 9 === 0;
});

// The same amount twice on the same day is usually a receipt posted twice.
const duplicateIds = computed(() => {
    const seen = new Map();
    const dupes = new Set();
    for (const m of movements.value) {
        if (m.cleared_in) continue;
        const key = `${dateOnly(m.entry_date)}|${m.amount}`;
        if (seen.has(key)) {
            dupes.add(m.id);
            dupes.add(seen.get(key));
        } else {
            seen.set(key, m.id);
        }
    }
    return dupes;
});

const rowClass = ({ row }) => [
    row.cleared_in ? 'is-earlier' : row.is_cleared ? 'is-cleared' : '',
    suggestedIds.value.has(row.id) ? 'is-suggested' : '',
    isOpen.value && !row.cleared_in ? 'is-clickable' : '',
].join(' ');

/* ------------------------------------------------------------------ *
 * Ticking
 *
 * The tick shows at once and the arithmetic follows from the server — the
 * figures that decide whether this reconciles are computed there, and a
 * second copy here would be free to disagree. Requests say "cleared" or "not"
 * rather than "flip", so clicks sent close together cannot cancel out; when
 * they overlap, the sheet is re-read once they have all landed.
 * ------------------------------------------------------------------ */

const pending = reactive(new Set());
const inflight = ref(0);
let overlapped = false;

const busy = computed(() => saving.value || inflight.value > 0);

const setCleared = async (targetRows, cleared) => {
    if (!isOpen.value) return;
    const targets = targetRows.filter((m) => !m.cleared_in && m.is_cleared !== cleared && !pending.has(m.id));
    if (!targets.length) return;

    const id = current.value.id;
    targets.forEach((m) => {
        m.is_cleared = cleared;
        pending.add(m.id);
    });
    if (inflight.value > 0) overlapped = true;
    inflight.value += 1;

    try {
        const res = await bankReconciliationsApi.setLines(id, targets.map((m) => m.id), cleared);
        if (inflight.value === 1 && !overlapped && current.value?.id === id) {
            current.value = res.data?.data || current.value;
        }
    } catch (e) {
        targets.forEach((m) => { m.is_cleared = !cleared; });
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
        overlapped = true;
    } finally {
        targets.forEach((m) => pending.delete(m.id));
        inflight.value -= 1;
        if (inflight.value === 0 && overlapped) {
            overlapped = false;
            if (current.value?.id === id) await loadSheet(id, { quiet: true });
        }
    }
};

const onRowClick = (row) => {
    if (!isOpen.value || row.cleared_in) return;
    // A click that ends a text selection is someone copying a narration.
    if (window.getSelection()?.toString()) return;
    setCleared([row], !row.is_cleared);
};

/* ------------------------------------------------------------------ *
 * Starting, correcting, closing
 * ------------------------------------------------------------------ */

const formVisible = ref(false);
const editing = ref(false);
const form = reactive({ account_id: null, statement_date: '', statement_balance: '', notes: '' });

const formAccount = computed(() => accounts.value.find((a) => a.id === form.account_id) || null);

const canSubmit = computed(() => form.account_id && form.statement_date && form.statement_balance !== '' && form.statement_balance !== null
    && (editing.value || !formAccount.value?.open_reconciliation));

const openCreate = (accountId = null) => {
    editing.value = false;
    const firstFree = accounts.value.find((a) => !a.open_reconciliation) || accounts.value[0];
    Object.assign(form, {
        account_id: accountId ?? firstFree?.id ?? null,
        statement_date: localDate(new Date()),
        statement_balance: '',
        notes: '',
    });
    formVisible.value = true;
};

const openEdit = () => {
    editing.value = true;
    Object.assign(form, {
        account_id: current.value.account_id,
        statement_date: dateOnly(current.value.statement_date),
        statement_balance: String(Number(current.value.statement_balance)),
        notes: current.value.notes || '',
    });
    formVisible.value = true;
};

const continueFromDialog = () => {
    formVisible.value = false;
    openSheet(formAccount.value.open_reconciliation.id);
};

const submit = async () => {
    if (!canSubmit.value) return;
    saving.value = true;
    try {
        const payload = {
            statement_date: form.statement_date,
            statement_balance: Number(form.statement_balance),
            notes: form.notes || null,
        };
        if (editing.value) {
            const res = await bankReconciliationsApi.update(current.value.id, payload);
            current.value = res.data?.data || current.value;
            ElMessage.success(t('br_statement_updated'));
        } else {
            const res = await bankReconciliationsApi.create({ ...payload, account_id: form.account_id });
            const created = res.data?.data;
            listLoaded.value = false;
            current.value = created || null;
            if (created) openSheet(created.id);
        }
        formVisible.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

const complete = async () => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.complete(current.value.id);
        current.value = res.data?.data || current.value;
        listLoaded.value = false;
        ElMessage.success(t('reconciliation_completed'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
        await loadSheet(current.value.id, { quiet: true });
    } finally {
        saving.value = false;
    }
};

const reopen = async () => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.reopen(current.value.id);
        current.value = res.data?.data || current.value;
        listLoaded.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

const removeReconciliation = async (row) => {
    try {
        await ElMessageBox.confirm(t('br_delete_confirm', { ref: row.reference }), t('delete'), {
            type: 'warning',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
            confirmButtonClass: 'el-button--danger',
        });
    } catch {
        return;
    }

    saving.value = true;
    try {
        await bankReconciliationsApi.remove(row.id);
        ElMessage.success(t('br_deleted'));
        if (sheetId.value === row.id) {
            goToList();
        } else {
            await loadList(pagination.value.current_page);
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

const printPage = () => window.print();

onMounted(() => {
    if (sheetId.value) {
        loadSheet(sheetId.value);
        // The accounts are needed by the edit form and the back link.
        loadList(1);
    } else {
        loadList(1);
    }
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. Money in is teal, money out is blue. */
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
    --c-in: #0f766e;
    --c-in-bg: #ccfbf1;
    --c-out: #1d4ed8;
    --c-out-bg: #dbeafe;
    --c-ok: #065f46;
    --c-ok-bg: #d1fae5;
    --c-bad: #991b1b;
    --c-bad-bg: #fee2e2;
    --c-warn: #92400e;
    --c-warn-bg: #fef3c7;
    color: var(--ov-body);
}

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.mb-3 { margin-bottom: 1rem; }
.mt-1 { margin-top: 0.4rem; }
.mr-1 { margin-inline-end: 0.35rem; }
.muted { color: var(--ov-subtle); }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; unicode-bidi: isolate; }
.is-in { color: var(--c-in); }
.is-out { color: var(--c-out); }
.is-ok-text { color: #047857; }
.is-warn-text { color: #b45309; }

.br-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.br-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.br-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.br-card-head h2 i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }
.section-note { font-size: 0.8rem; color: var(--ov-muted); }

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
    unicode-bidi: isolate;
}

.entry-link { text-decoration: none; }
.entry-link:hover { border-color: #1d4ed8; color: #1d4ed8; }

.status-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.05rem 0.6rem; border-radius: 999px; font-size: 0.74rem; font-weight: 700; }
.status-chip.is-open { color: var(--c-warn); background: var(--c-warn-bg); }
.status-chip.is-done { color: var(--ov-muted); background: #f1f5f9; }

.state { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.78rem; font-weight: 700; white-space: nowrap; }
.state.is-cleared { color: var(--c-ok); }
.state.is-transit { color: var(--c-warn); }
.state.is-earlier { color: var(--ov-subtle); font-weight: 600; }
.state.is-bad { color: var(--c-bad); }

.acc-icon { width: 38px; height: 38px; flex-shrink: 0; display: grid; place-items: center; border-radius: 11px; color: #1d4ed8; background: #dbeafe; }
.acc-icon.is-cash { color: var(--c-in); background: var(--c-in-bg); }

/* ── Accounts ─────────────────────────────────────────────────────────── */
.accounts { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 0.8rem; }

.account {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.9rem;
    border: 1px solid var(--ov-border);
    border-inline-start: 4px solid #047857;
    border-radius: 12px;
    background: var(--ov-soft);
}

.account.is-stale { border-inline-start-color: #d97706; }
.account.has-open { border-inline-start-color: #1d4ed8; background: #eff6ff; }
.account-top { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.account-name { display: flex; flex-direction: column; align-items: flex-start; gap: 0.15rem; min-width: 0; }
.account-name strong { color: var(--ov-text); font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.account-state { margin: 0; font-size: 0.8rem; font-weight: 600; color: var(--ov-body); }
.account-state i { margin-inline-end: 0.25rem; }
.account-action { margin-top: auto; width: 100%; }

.toolbar-inline { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
.t-account { width: 220px; }

:deep(.is-clickable) { cursor: pointer; }
.row-actions { display: inline-flex; align-items: center; gap: 0.15rem; }
.done-by { display: block; margin-top: 0.15rem; font-size: 0.72rem; color: var(--ov-subtle); }
.pager { display: flex; justify-content: center; margin-top: 0.9rem; }

/* ── Sheet header ─────────────────────────────────────────────────────── */
.sheet-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem; }
.back-btn { flex-basis: 100%; justify-content: flex-start; padding-inline: 0; height: auto; color: var(--ov-muted); font-weight: 700; }
.sheet-title { display: flex; align-items: flex-start; gap: 0.75rem; min-width: 0; flex: 1 1 320px; }
.sheet-title-text { min-width: 0; }
.sheet-title h2 { margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; font-size: 1.1rem; font-weight: 800; color: var(--ov-text); }
.sheet-meta { margin: 0.3rem 0 0; display: flex; flex-wrap: wrap; gap: 0.3rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); }
.sheet-meta i { margin-inline-end: 0.25rem; color: var(--ov-subtle); }
.sheet-notes { margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--ov-body); }
.sheet-notes i { margin-inline-end: 0.3rem; color: var(--ov-subtle); }
.sheet-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-inline-start: auto; }
.sheet-actions .el-button + .el-button, .sheet-actions span .el-button { margin-inline-start: 0; }

.dir-arrow { font-size: 0.7em; margin-inline-start: 0.25rem; }
[dir="rtl"] .dir-arrow, [dir="rtl"] .dir-arrow-back { transform: scaleX(-1); }

/* ── Result ───────────────────────────────────────────────────────────── */
.result {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem 1.5rem;
    padding: 1.2rem 1.3rem;
    margin-bottom: 1rem;
    border: 1px solid;
    border-radius: var(--ov-radius);
}

.result.is-ok { background: #ecfdf5; border-color: #a7f3d0; --r: var(--c-ok); }
.result.is-bad { background: #fef2f2; border-color: #fecaca; --r: var(--c-bad); }

.result-main { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; max-width: 340px; }
.result-label { display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.9rem; font-weight: 800; color: var(--r); }
.result-amount { font-size: 2rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; line-height: 1.2; unicode-bidi: isolate; }
.result.is-bad .result-amount { color: var(--c-bad); }
.result-note { font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); line-height: 1.5; }

.formula { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-inline-start: auto; }
.f-term { display: flex; flex-direction: column; padding: 0.45rem 0.75rem; border-radius: 10px; background: #fff; border: 1px solid var(--ov-border); min-width: 110px; }
.f-term small { font-size: 0.7rem; font-weight: 700; color: var(--ov-muted); }
.f-term b { font-size: 0.98rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.f-term.is-transit b { color: var(--c-warn); }
.f-term.is-statement { border-color: var(--r); }
.f-op { font-size: 1.2rem; font-weight: 800; color: var(--ov-subtle); }
.result.is-bad .f-op:nth-last-child(2) { color: var(--c-bad); }

.result-foot { flex-basis: 100%; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1.5rem; padding-top: 0.8rem; border-top: 1px dashed rgba(15, 23, 42, 0.12); }
.progress { display: flex; flex-direction: column; gap: 0.3rem; flex: 1 1 240px; max-width: 420px; }
.progress-label { font-size: 0.8rem; font-weight: 700; color: var(--ov-body); }
.updating { margin-inline-start: 0.35rem; color: var(--ov-subtle); }
.progress-track { display: block; height: 8px; border-radius: 999px; background: rgba(15, 23, 42, 0.08); overflow: hidden; }
.progress-bar { display: block; height: 100%; border-radius: inherit; background: #10b981; transition: width 0.25s ease; }
.transit-split { display: flex; flex-wrap: wrap; gap: 0.3rem 1.2rem; font-size: 0.8rem; font-weight: 600; color: var(--ov-muted); }
.transit-split b { color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; margin-inline-start: 0.25rem; }
.transit-split i { margin-inline-end: 0.25rem; }

@media (max-width: 720px) {
    .formula { margin-inline-start: 0; }
    .f-term { min-width: 0; flex: 1 1 40%; }
    .f-op { display: none; }
}

/* ── Hints ────────────────────────────────────────────────────────────── */
.hints .br-card-head h2 i { color: #b45309; background: var(--c-warn-bg); }
.suggestions { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.8rem; }
.suggestion { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; padding: 0.6rem 0.8rem; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; }
.s-text { font-size: 0.84rem; font-weight: 700; color: var(--c-warn); }
.s-text i { margin-inline-end: 0.3rem; }
.s-row { font-size: 0.84rem; color: var(--ov-body); }
.s-row b { font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.suggestion .el-button { margin-inline-start: auto; }
.hint-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.45rem; }
.hint-list li { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.25rem 0.5rem; font-size: 0.84rem; line-height: 1.7; color: var(--ov-body); }
.hint-list li > i { width: 1.1rem; text-align: center; color: var(--ov-subtle); }
.help-link { font-weight: 700; color: #1d4ed8; text-decoration: none; }
.help-link:hover { text-decoration: underline; }

/* ── Movements ────────────────────────────────────────────────────────── */
.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; }
.t-search { width: 240px; }
.bulk { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-inline-start: auto; }
.bulk .el-button + .el-button { margin-inline-start: 0; }

@media (max-width: 640px) {
    .t-search, .t-account { width: 100%; }
    .bulk { margin-inline-start: 0; }
}

.narration { color: var(--ov-text); }
.mini-tag { display: inline-flex; align-items: center; gap: 0.25rem; margin-inline-start: 0.4rem; padding: 0 0.45rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; vertical-align: middle; }
.mini-tag.is-warn { color: var(--c-bad); background: var(--c-bad-bg); }
.mini-tag.is-hint { color: var(--c-warn); background: var(--c-warn-bg); }

:deep(.el-table .is-cleared > td.el-table__cell) { background: #f0fdf4; }
:deep(.el-table .is-earlier > td.el-table__cell) { color: var(--ov-subtle); background: var(--ov-soft); }
:deep(.el-table .is-suggested > td.el-table__cell) { background: #fffbeb; }
:deep(.el-table .is-clickable:hover > td.el-table__cell) { background: #eff6ff; }

.rows-total { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.4rem 1.5rem; margin-top: 0.6rem; padding: 0.55rem 0.8rem; border-radius: 8px; background: #f1f5f9; font-size: 0.82rem; font-weight: 700; color: var(--ov-text); }
.rows-total > span:first-child { margin-inline-end: auto; color: var(--ov-muted); }
.rows-total small { font-weight: 600; color: var(--ov-muted); margin-inline-end: 0.3rem; }

/* ── Dialog ───────────────────────────────────────────────────────────── */
.hint { display: block; margin-top: 0.35rem; color: var(--ov-muted); font-size: 0.78rem; line-height: 1.5; }
.opt-meta { float: inline-end; margin-inline-start: 1rem; font-size: 0.75rem; color: var(--ov-subtle); }
.num-input :deep(input) { font-variant-numeric: tabular-nums; direction: ltr; text-align: end; }

/* ── Print ────────────────────────────────────────────────────────────── */
@media print {
    .no-print, :deep(.no-print) { display: none !important; }
    .br-card, .result { box-shadow: none; break-inside: avoid-page; }
    .progress-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
