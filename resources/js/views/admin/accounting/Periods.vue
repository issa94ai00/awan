<template>
    <div class="accounting-page accounting-periods">
        <AdminPageHeader
            icon="fas fa-lock text-primary"
            :title="$t('accounting_periods')"
            :subtitle="$t('accounting_periods_subtitle')"
        >
            <template #actions>
                <el-button :loading="store.loading" @click="reload">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" @click="openCreate()">
                    <i class="fas fa-plus mr-1"></i> {{ $t('add_period') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Anyone entering documents today needs to know before the API
             refuses them, not after. -->
        <el-alert
            v-if="store.todayIsClosed"
            type="warning"
            show-icon
            :closable="false"
            class="mb-3"
            :title="$t('today_is_inside_a_closed_period')"
        />

        <el-alert v-if="store.error" type="error" show-icon :closable="false" class="mb-3" :title="store.error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <div v-if="store.loading && !loaded" class="ap-card"><el-skeleton :rows="6" animated /></div>

        <template v-else-if="loaded">
            <!-- ── Where the lock stands ─────────────────────────────────── -->
            <section class="tiles">
                <div class="tile" :class="lockedThrough ? 'is-locked' : 'is-none'">
                    <span class="tile-icon"><i class="fas fa-lock"></i></span>
                    <div>
                        <small>{{ $t('ap_locked_through') }}</small>
                        <strong>{{ lockedThrough ? longDate(lockedThrough) : $t('ap_nothing_locked') }}</strong>
                        <span class="tile-sub">{{ lockedThrough ? $t('ap_locked_through_hint') : $t('ap_nothing_locked_hint') }}</span>
                    </div>
                </div>

                <div class="tile" :class="todayPeriod ? (todayPeriod.status === 'closed' ? 'is-bad' : 'is-open') : 'is-none'">
                    <span class="tile-icon"><i class="far fa-calendar-check"></i></span>
                    <div>
                        <small>{{ $t('ap_today') }}</small>
                        <strong>{{ todayPeriod ? todayPeriod.name : $t('ap_no_period') }}</strong>
                        <span class="tile-sub">
                            {{ todayPeriod
                                ? (todayPeriod.status === 'closed' ? $t('ap_today_closed') : $t('ap_today_open'))
                                : $t('ap_today_uncovered') }}
                        </span>
                    </div>
                </div>

                <div class="tile" :class="endedOpen.length ? 'is-warn' : 'is-ok'">
                    <span class="tile-icon"><i class="fas fa-hourglass-end"></i></span>
                    <div>
                        <small>{{ $t('ap_ended_still_open') }}</small>
                        <strong>{{ formatNumber(endedOpen.length) }}</strong>
                        <span class="tile-sub">
                            <template v-if="endedOpen.length">
                                {{ $t('ap_oldest', { name: endedOpen[0].name }) }}
                                <el-button link type="primary" class="tile-link" @click="askClose(endedOpen[0])">{{ $t('close_period') }}</el-button>
                            </template>
                            <template v-else>{{ $t('ap_all_ended_closed') }}</template>
                        </span>
                    </div>
                </div>
            </section>

            <!-- ── The year at a glance ───────────────────────────────────── -->
            <section class="ap-card">
                <header class="ap-card-head">
                    <h2><i class="fas fa-calendar-days"></i> {{ $t('ap_year_view') }}</h2>
                    <div class="year-nav">
                        <el-button circle size="small" :aria-label="$t('ap_prev_year')" @click="setYear(year - 1)"><i class="fas fa-chevron-left dir-flip"></i></el-button>
                        <strong class="year-label">{{ year }}</strong>
                        <el-button circle size="small" :aria-label="$t('ap_next_year')" @click="setYear(year + 1)"><i class="fas fa-chevron-right dir-flip"></i></el-button>
                    </div>
                </header>

                <div class="months">
                    <button
                        v-for="m in months"
                        :key="m.index"
                        type="button"
                        class="month"
                        :class="['is-' + m.state, { 'is-future': m.future, 'is-current': m.current }]"
                        :title="m.title"
                        @click="onMonthClick(m)"
                    >
                        <span class="m-name">{{ m.label }}</span>
                        <span class="m-state">
                            <i class="fas" :class="stateIcon[m.state]"></i>
                            {{ $t('ap_state_' + m.state) }}
                        </span>
                        <span v-if="m.unbalanced" class="m-flag"><i class="fas fa-triangle-exclamation"></i> {{ formatNumber(m.unbalanced) }}</span>
                    </button>
                </div>

                <div class="months-foot">
                    <span class="legend">
                        <span><i class="dot is-closed"></i> {{ $t('ap_state_closed') }}</span>
                        <span><i class="dot is-open"></i> {{ $t('ap_state_open') }}</span>
                        <span><i class="dot is-partial"></i> {{ $t('ap_state_partial') }}</span>
                        <span><i class="dot is-none"></i> {{ $t('ap_state_none') }}</span>
                    </span>
                    <el-button v-if="missingMonths.length" type="primary" plain size="small" :loading="generating" @click="generateMissing">
                        <i class="fas fa-wand-magic-sparkles mr-1"></i>
                        {{ $t('ap_create_missing', { n: formatNumber(missingMonths.length), year }) }}
                    </el-button>
                </div>
            </section>

            <!-- ── Periods of the year ───────────────────────────────────── -->
            <section class="ap-card">
                <header class="ap-card-head">
                    <h2><i class="fas fa-list"></i> {{ $t('ap_periods_of', { year }) }}
                        <span class="count">{{ formatNumber(yearPeriods.length) }}</span>
                    </h2>
                    <el-segmented v-model="statusFilter" :options="statusOptions" />
                </header>

                <el-table
                    v-if="shownPeriods.length"
                    :data="shownPeriods"
                    :row-class-name="({ row }) => (row.id === highlightId ? 'is-highlight' : '')"
                    style="width: 100%"
                >
                    <el-table-column :label="$t('name')" min-width="200">
                        <template #default="{ row }">
                            <div class="p-name">
                                <strong>{{ row.name }}</strong>
                                <span class="p-range">{{ rangeLabel(row) }} · {{ $t('ap_days', { n: formatNumber(daysIn(row)) }) }}</span>
                                <el-tooltip v-if="row.notes" placement="top" :content="row.notes" popper-class="notes-tip">
                                    <span class="p-notes"><i class="far fa-note-sticky"></i> {{ lastLine(row.notes) }}</span>
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('journal_entries')" width="120" align="center">
                        <template #default="{ row }">
                            <router-link v-if="row.entry_count" class="count-link" :to="journalLink(row)">
                                {{ formatNumber(row.entry_count) }}
                            </router-link>
                            <span v-else class="muted">0</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" min-width="200">
                        <template #default="{ row }">
                            <span class="status-chip" :class="row.status === 'closed' ? 'is-closed' : 'is-open'">
                                <i class="fas" :class="row.status === 'closed' ? 'fa-lock' : 'fa-lock-open'"></i>
                                {{ row.status === 'closed' ? $t('closed_period') : $t('open_period') }}
                            </span>
                            <span v-if="row.status === 'open'" class="readiness" :class="'is-' + readiness(row)">
                                <template v-if="readiness(row) === 'blocked'">
                                    <router-link :to="journalLink(row)">
                                        <i class="fas fa-triangle-exclamation"></i> {{ $t('ap_unbalanced', { n: formatNumber(row.unbalanced_entries) }) }}
                                    </router-link>
                                </template>
                                <template v-else-if="readiness(row) === 'ready'"><i class="fas fa-check"></i> {{ $t('ap_ready_to_close') }}</template>
                                <template v-else-if="readiness(row) === 'running'"><i class="fas fa-play"></i> {{ $t('ap_in_progress') }}</template>
                                <template v-else><i class="far fa-clock"></i> {{ $t('ap_not_started') }}</template>
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('last_action')" min-width="210">
                        <template #default="{ row }">
                            <span v-if="row.status === 'closed' && row.closed_at" class="action-line">
                                <i class="fas fa-lock"></i>
                                {{ $t('ap_closed_by', { date: dateTime(row.closed_at), name: row.closed_by || '—' }) }}
                            </span>
                            <span v-else-if="row.reopened_at" class="action-line is-reopen">
                                <i class="fas fa-lock-open"></i>
                                {{ $t('ap_reopened_by', { date: dateTime(row.reopened_at), name: row.reopened_by || '—' }) }}
                            </span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column width="170" align="center">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-tooltip v-if="row.status === 'open'" :disabled="!row.unbalanced_entries" :content="$t('ap_close_blocked')" placement="top">
                                    <span>
                                        <el-button size="small" type="danger" plain :disabled="row.unbalanced_entries > 0" @click="askClose(row)">
                                            <i class="fas fa-lock mr-1"></i> {{ $t('close_period') }}
                                        </el-button>
                                    </span>
                                </el-tooltip>
                                <el-button v-else size="small" type="warning" plain @click="askReopen(row)">
                                    <i class="fas fa-lock-open mr-1"></i> {{ $t('reopen_period') }}
                                </el-button>
                                <el-button v-if="row.status === 'open'" size="small" text type="danger" :aria-label="$t('delete')" @click="confirmRemove(row)">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="yearPeriods.length ? $t('ap_no_match') : $t('ap_no_periods_in_year', { year })">
                    <el-button v-if="!yearPeriods.length && missingMonths.length" type="primary" :loading="generating" @click="generateMissing">
                        {{ $t('ap_create_missing', { n: formatNumber(missingMonths.length), year }) }}
                    </el-button>
                </el-empty>
            </section>
        </template>

        <!-- ── Add ──────────────────────────────────────────────────────── -->
        <el-dialog v-model="createVisible" :title="$t('add_period')" width="480px" destroy-on-close>
            <el-form :model="form" label-position="top" @submit.prevent="submit">
                <el-form-item :label="$t('ap_length')">
                    <el-segmented v-model="form.kind" :options="kindOptions" @change="onKindChange" />
                </el-form-item>

                <el-form-item :label="$t('period')" required>
                    <el-date-picker
                        v-if="form.kind === 'month'"
                        v-model="form.month"
                        type="month"
                        value-format="YYYY-MM"
                        :clearable="false"
                        style="width: 100%"
                        @change="applyKind"
                    />
                    <div v-else-if="form.kind === 'quarter'" class="quarter-pick">
                        <el-date-picker v-model="form.year" type="year" value-format="YYYY" :clearable="false" @change="applyKind" />
                        <el-segmented v-model="form.quarter" :options="[1, 2, 3, 4].map((q) => ({ label: 'Q' + q, value: q }))" @change="applyKind" />
                    </div>
                    <el-date-picker
                        v-else-if="form.kind === 'year'"
                        v-model="form.year"
                        type="year"
                        value-format="YYYY"
                        :clearable="false"
                        style="width: 100%"
                        @change="applyKind"
                    />
                    <el-date-picker
                        v-else
                        v-model="form.range"
                        type="daterange"
                        unlink-panels
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :start-placeholder="$t('period_from')"
                        :end-placeholder="$t('to')"
                        style="width: 100%"
                        @change="onCustomRange"
                    />
                    <small v-if="form.range?.length === 2" class="hint">
                        {{ rangeLabel({ start_date: form.range[0], end_date: form.range[1] }) }}
                        · {{ $t('ap_days', { n: formatNumber(daysIn({ start_date: form.range[0], end_date: form.range[1] })) }) }}
                    </small>
                </el-form-item>

                <el-alert v-if="formOverlap" type="error" :closable="false" show-icon class="mb-3" :title="$t('ap_overlaps', { name: formOverlap.name })" />

                <el-form-item :label="$t('name')" required>
                    <el-input v-model="form.name" maxlength="100" :placeholder="$t('period_name_example')" @input="nameTouched = true" />
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="createVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- ── Close / reopen ───────────────────────────────────────────── -->
        <el-dialog
            v-model="lockVisible"
            :title="lockAction === 'close' ? `${$t('close_period')} — ${lockTarget?.name}` : `${$t('reopen_period')} — ${lockTarget?.name}`"
            width="480px"
            destroy-on-close
        >
            <template v-if="lockTarget">
                <dl class="facts">
                    <div><dt>{{ $t('period') }}</dt><dd>{{ rangeLabel(lockTarget) }}</dd></div>
                    <div><dt>{{ $t('journal_entries') }}</dt><dd>{{ formatNumber(lockTarget.entry_count) }}</dd></div>
                </dl>

                <template v-if="lockAction === 'close'">
                    <p class="lock-text"><i class="fas fa-lock"></i> {{ $t('confirm_close_period') }}</p>
                    <el-alert v-if="!hasEnded(lockTarget)" type="warning" :closable="false" show-icon class="mb-3" :title="$t('ap_close_not_ended', { date: lockTarget.end_date })" />
                    <el-alert v-if="openBefore(lockTarget).length" type="info" :closable="false" show-icon class="mb-3" :title="$t('ap_close_earlier_open', { names: openBefore(lockTarget).map((p) => p.name).join('، ') })" />
                </template>
                <p v-else class="lock-text"><i class="fas fa-lock-open"></i> {{ $t('confirm_reopen_period') }}</p>

                <el-form label-position="top" @submit.prevent="confirmLock">
                    <el-form-item :label="lockAction === 'close' ? $t('ap_close_note') : $t('ap_reopen_reason')" :required="lockAction === 'reopen'">
                        <el-input
                            v-model="lockReason"
                            type="textarea"
                            :rows="2"
                            maxlength="300"
                            :placeholder="lockAction === 'close' ? $t('ap_close_note_placeholder') : $t('ap_reopen_reason_placeholder')"
                        />
                        <small class="hint">{{ $t('ap_reason_kept') }}</small>
                    </el-form-item>
                </el-form>
            </template>

            <template #footer>
                <el-button @click="lockVisible = false">{{ $t('cancel') }}</el-button>
                <el-button
                    :type="lockAction === 'close' ? 'danger' : 'warning'"
                    :loading="store.saving"
                    :disabled="lockAction === 'reopen' && !lockReason.trim()"
                    @click="confirmLock"
                >
                    {{ lockAction === 'close' ? $t('close_period') : $t('reopen_period') }}
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
import { useAccountingPeriodsStore } from '@/stores/accountingPeriods';
import { accountingPeriodsApi } from '@/api/accountingPeriods';
import { formatNumber, numberLocale } from '@/utils/currency';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useAccountingPeriodsStore();

const loaded = ref(false);

/* ------------------------------------------------------------------ *
 * Dates — all as YYYY-MM-DD strings, compared as text, so no timezone
 * can move a period's first or last day.
 * ------------------------------------------------------------------ */

const pad = (n) => String(n).padStart(2, '0');
const localDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const lastDay = (y, m) => new Date(y, m, 0).getDate(); // m is 1-based
const monthRange = (y, m) => [`${y}-${pad(m)}-01`, `${y}-${pad(m)}-${pad(lastDay(y, m))}`];
const addDays = (iso, n) => {
    const [y, m, d] = iso.split('-').map(Number);
    return localDate(new Date(y, m - 1, d + n));
};

const today = computed(() => store.today || localDate(new Date()));

const fmt = (iso, opts) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Intl.DateTimeFormat(numberLocale(), opts).format(new Date(y, m - 1, d));
};
const longDate = (iso) => fmt(iso, { day: 'numeric', month: 'long', year: 'numeric' });
const monthName = (y, m, withYear = true) => fmt(`${y}-${pad(m)}-01`, withYear ? { month: 'long', year: 'numeric' } : { month: 'short' });
const rangeLabel = (p) => `${fmt(p.start_date, { day: 'numeric', month: 'short', year: 'numeric' })} → ${fmt(p.end_date, { day: 'numeric', month: 'short', year: 'numeric' })}`;
const dateTime = (value) => {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString(numberLocale(), { dateStyle: 'medium', timeStyle: 'short' });
};
const daysIn = (p) => {
    const [a, b] = [p.start_date, p.end_date].map((iso) => { const [y, m, d] = iso.split('-').map(Number); return Date.UTC(y, m - 1, d); });
    return Math.round((b - a) / 86400000) + 1;
};
const lastLine = (notes) => String(notes).trim().split('\n').pop();

/* ------------------------------------------------------------------ *
 * The lock at a glance
 * ------------------------------------------------------------------ */

const periods = computed(() => store.periods || []);
const ascending = computed(() => [...periods.value].sort((a, b) => a.start_date.localeCompare(b.start_date)));

// The last date before which everything defined is closed: an open period
// earlier on means the lock has a hole in it, however late the last close.
const lockedThrough = computed(() => {
    let through = null;
    for (const p of ascending.value) {
        if (p.status !== 'closed') break;
        through = p.end_date;
    }
    return through;
});

const todayPeriod = computed(() => periods.value.find((p) => p.start_date <= today.value && p.end_date >= today.value) || null);
const hasEnded = (p) => p.end_date < today.value;
const endedOpen = computed(() => ascending.value.filter((p) => p.status === 'open' && hasEnded(p)));
const openBefore = (period) => ascending.value.filter((p) => p.status === 'open' && p.end_date < period.start_date);

const readiness = (p) => {
    if (p.unbalanced_entries > 0) return 'blocked';
    if (hasEnded(p)) return 'ready';
    if (p.start_date <= today.value) return 'running';
    return 'future';
};

const journalLink = (p) => ({ path: '/admin/accounting/journal', query: { date_from: p.start_date, date_to: p.end_date } });

/* ------------------------------------------------------------------ *
 * Year view
 * ------------------------------------------------------------------ */

const year = ref(new Date().getFullYear());

const setYear = (y) => {
    year.value = y;
    router.replace({ query: y === new Date().getFullYear() ? {} : { year: String(y) } });
};

const stateIcon = { closed: 'fa-lock', open: 'fa-lock-open', mixed: 'fa-code-branch', partial: 'fa-circle-half-stroke', none: 'fa-circle-minus' };

const months = computed(() => Array.from({ length: 12 }, (_, i) => {
    const m = i + 1;
    const [start, end] = monthRange(year.value, m);
    const overlapping = ascending.value.filter((p) => p.start_date <= end && p.end_date >= start);

    // Whether the periods touching this month cover every day of it.
    let cursor = start;
    for (const p of overlapping) {
        if (p.start_date > cursor) break;
        if (p.end_date >= cursor) cursor = addDays(p.end_date, 1);
    }
    const covered = overlapping.length && cursor > end;

    let state = 'none';
    if (overlapping.length && !covered) state = 'partial';
    else if (covered) {
        const closed = overlapping.filter((p) => p.status === 'closed').length;
        state = closed === overlapping.length ? 'closed' : closed ? 'mixed' : 'open';
    }

    return {
        index: m,
        start,
        end,
        state,
        overlapping,
        label: monthName(year.value, m, false),
        future: start > today.value,
        current: start <= today.value && end >= today.value,
        unbalanced: overlapping.reduce((sum, p) => sum + (p.status === 'open' ? Number(p.unbalanced_entries || 0) : 0), 0),
        title: overlapping.map((p) => p.name).join(' · ') || t('ap_state_none'),
    };
}));

const missingMonths = computed(() => months.value.filter((m) => m.state === 'none'));

const highlightId = ref(null);

const onMonthClick = (m) => {
    if (!m.overlapping.length) {
        openCreate({ kind: 'month', month: `${year.value}-${pad(m.index)}` });
        return;
    }
    statusFilter.value = 'all';
    highlightId.value = m.overlapping[0].id;
    setTimeout(() => { if (highlightId.value === m.overlapping[0].id) highlightId.value = null; }, 2500);
};

const generating = ref(false);

// Monthly periods are what nearly everyone closes; creating the gaps of a year
// in one go replaces filling the same form twelve times.
const generateMissing = async () => {
    const list = missingMonths.value.map((m) => ({ name: monthName(year.value, m.index), start_date: m.start, end_date: m.end }));
    try {
        await ElMessageBox.confirm(t('ap_create_missing_confirm', { n: formatNumber(list.length), year: year.value }), t('add_period'), {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
        });
    } catch {
        return;
    }

    generating.value = true;
    try {
        const res = await accountingPeriodsApi.batch(list);
        const created = res.data?.data?.created?.length || 0;
        const skipped = res.data?.data?.skipped || [];
        ElMessage.success(t('ap_created_n', { n: formatNumber(created) }) + (skipped.length ? ` — ${t('ap_skipped', { names: skipped.join('، ') })}` : ''));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_period'));
    } finally {
        generating.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Table
 * ------------------------------------------------------------------ */

const statusFilter = ref('all');

const yearPeriods = computed(() => {
    const [from, to] = [`${year.value}-01-01`, `${year.value}-12-31`];
    return periods.value.filter((p) => p.start_date <= to && p.end_date >= from);
});

const statusOptions = computed(() => [
    { label: t('all'), value: 'all' },
    { label: t('open_period'), value: 'open' },
    { label: t('closed_period'), value: 'closed' },
]);

const shownPeriods = computed(() => (statusFilter.value === 'all' ? yearPeriods.value : yearPeriods.value.filter((p) => p.status === statusFilter.value)));

const reload = async () => {
    try {
        await store.fetchPeriods();
        loaded.value = true;
    } catch {
        // The store keeps the message; the alert shows it.
    }
};

/* ------------------------------------------------------------------ *
 * Adding a period
 * ------------------------------------------------------------------ */

const createVisible = ref(false);
const nameTouched = ref(false);
const form = reactive({ kind: 'month', month: '', year: '', quarter: 1, range: [], name: '', notes: '' });

const kindOptions = computed(() => [
    { label: t('ap_kind_month'), value: 'month' },
    { label: t('ap_kind_quarter'), value: 'quarter' },
    { label: t('ap_kind_year'), value: 'year' },
    { label: t('lg_custom'), value: 'custom' },
]);

const applyKind = () => {
    let range = form.range;
    let name = form.name;
    if (form.kind === 'month' && form.month) {
        const [y, m] = form.month.split('-').map(Number);
        range = monthRange(y, m);
        name = monthName(y, m);
    } else if (form.kind === 'quarter' && form.year) {
        const y = Number(form.year);
        const first = (form.quarter - 1) * 3 + 1;
        range = [monthRange(y, first)[0], monthRange(y, first + 2)[1]];
        name = t('ap_quarter_name', { q: form.quarter, year: y });
    } else if (form.kind === 'year' && form.year) {
        range = [`${form.year}-01-01`, `${form.year}-12-31`];
        name = t('ap_year_name', { year: form.year });
    }
    form.range = range;
    if (!nameTouched.value) form.name = name;
};

const onKindChange = () => {
    const start = form.range?.[0] || today.value;
    const [y, m] = start.split('-').map(Number);
    form.month = `${y}-${pad(m)}`;
    form.year = String(y);
    form.quarter = Math.floor((m - 1) / 3) + 1;
    applyKind();
};

const onCustomRange = () => {
    if (!nameTouched.value && form.range?.length === 2) form.name = rangeLabel({ start_date: form.range[0], end_date: form.range[1] });
};

// The month after the latest period is nearly always the next one wanted.
const nextMonth = () => {
    const last = ascending.value[ascending.value.length - 1];
    const base = last ? addDays(last.end_date, 1) : today.value;
    return base.slice(0, 7);
};

const openCreate = (preset = null) => {
    nameTouched.value = false;
    Object.assign(form, { kind: 'month', month: preset?.month || nextMonth(), year: '', quarter: 1, range: [], name: '', notes: '' });
    form.year = form.month.slice(0, 4);
    applyKind();
    createVisible.value = true;
};

const formOverlap = computed(() => {
    if (form.range?.length !== 2) return null;
    const [s, e] = form.range;
    return periods.value.find((p) => p.start_date <= e && p.end_date >= s) || null;
});

const canSubmit = computed(() => form.name.trim() && form.range?.length === 2 && !formOverlap.value);

const submit = async () => {
    if (!canSubmit.value) return;
    try {
        await store.createPeriod({
            name: form.name.trim(),
            start_date: form.range[0],
            end_date: form.range[1],
            notes: form.notes || null,
        });
        createVisible.value = false;
        setYear(Number(form.range[0].slice(0, 4)));
        ElMessage.success(t('period_created'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_period'));
    }
};

/* ------------------------------------------------------------------ *
 * Closing and reopening — each with a reason that stays in the notes
 * ------------------------------------------------------------------ */

const lockVisible = ref(false);
const lockAction = ref('close');
const lockTarget = ref(null);
const lockReason = ref('');

const askClose = (period) => {
    lockAction.value = 'close';
    lockTarget.value = period;
    lockReason.value = '';
    lockVisible.value = true;
};

const askReopen = (period) => {
    lockAction.value = 'reopen';
    lockTarget.value = period;
    lockReason.value = '';
    lockVisible.value = true;
};

const confirmLock = async () => {
    const closing = lockAction.value === 'close';
    if (!closing && !lockReason.value.trim()) return;
    try {
        await store.setClosed(lockTarget.value.id, closing, lockReason.value.trim() ? { reason: lockReason.value.trim() } : {});
        lockVisible.value = false;
        ElMessage.success(closing ? t('period_closed') : t('period_reopened'));
    } catch (error) {
        // The server refuses to close over an unbalanced entry, and says how
        // many — surface that rather than a generic failure.
        ElMessage.error(error.response?.data?.message || (closing ? t('failed_to_close_period') : t('failed_to_save_period')));
    }
};

const confirmRemove = async (period) => {
    try {
        await ElMessageBox.confirm(t('confirm_delete_period'), `${t('confirm_deletion')} — ${period.name}`, {
            type: 'warning',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
            confirmButtonClass: 'el-button--danger',
        });
    } catch {
        return;
    }

    try {
        await store.removePeriod(period.id);
        ElMessage.success(t('period_deleted'));
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_period'));
    }
};

onMounted(() => {
    const y = Number(route.query.year);
    if (Number.isInteger(y) && y > 1900 && y < 3000) year.value = y;
    reload();
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. Closed is the lock (slate-red), open is green. */
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
    --c-closed: #9f1239;
    --c-closed-bg: #ffe4e6;
    --c-open: #047857;
    --c-open-bg: #d1fae5;
    --c-warn: #92400e;
    --c-warn-bg: #fef3c7;
    color: var(--ov-body);
}

.mb-3 { margin-bottom: 1rem; }
.mr-1 { margin-inline-end: 0.35rem; }
.muted { color: var(--ov-subtle); }

.ap-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.ap-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.ap-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.ap-card-head h2 > i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }
.count { padding: 0 0.5rem; border-radius: 999px; background: #f1f5f9; color: var(--ov-muted); font-size: 0.75rem; font-weight: 700; }

/* ── Tiles ────────────────────────────────────────────────────────────── */
.tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.tile { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.9rem 1rem; background: var(--ov-surface); border: 1px solid var(--ov-border); border-inline-start: 4px solid var(--ov-border); border-radius: 12px; min-width: 0; }
.tile > div { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.tile small { font-size: 0.76rem; font-weight: 700; color: var(--ov-muted); }
.tile strong { font-size: 1.1rem; font-weight: 800; color: var(--ov-text); }
.tile-sub { font-size: 0.78rem; color: var(--ov-muted); line-height: 1.5; }
.tile-link { font-size: 0.78rem; font-weight: 700; margin-inline-start: 0.25rem; vertical-align: baseline; }
.tile-icon { width: 36px; height: 36px; flex-shrink: 0; display: grid; place-items: center; border-radius: 10px; background: #f1f5f9; color: var(--ov-muted); }
.tile.is-locked { border-inline-start-color: var(--c-closed); }
.tile.is-locked .tile-icon { color: var(--c-closed); background: var(--c-closed-bg); }
.tile.is-open, .tile.is-ok { border-inline-start-color: var(--c-open); }
.tile.is-open .tile-icon, .tile.is-ok .tile-icon { color: var(--c-open); background: var(--c-open-bg); }
.tile.is-warn { border-inline-start-color: #d97706; }
.tile.is-warn .tile-icon { color: var(--c-warn); background: var(--c-warn-bg); }
.tile.is-bad { border-inline-start-color: var(--c-closed); }
.tile.is-bad .tile-icon { color: var(--c-closed); background: var(--c-closed-bg); }

/* ── Year view ────────────────────────────────────────────────────────── */
.year-nav { display: flex; align-items: center; gap: 0.6rem; }
.year-label { font-size: 1.05rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; min-width: 3.5rem; text-align: center; }
[dir="rtl"] .dir-flip { transform: scaleX(-1); }

.months { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0.5rem; }
@media (max-width: 900px) { .months { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 520px) { .months { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

.month {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--ov-border);
    border-radius: 10px;
    background: var(--ov-surface);
    font: inherit;
    text-align: start;
    cursor: pointer;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.month:hover { box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
.month:focus-visible { outline: 2px solid #1d4ed8; outline-offset: 2px; }
.m-name { font-weight: 800; color: var(--ov-text); font-size: 0.9rem; }
.m-state { font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem; }
.m-flag { position: absolute; top: 0.45rem; inset-inline-end: 0.5rem; font-size: 0.68rem; font-weight: 800; color: #b91c1c; }

.month.is-closed { background: var(--c-closed-bg); border-color: #fecdd3; }
.month.is-closed .m-state { color: var(--c-closed); }
.month.is-open { background: #ecfdf5; border-color: #a7f3d0; }
.month.is-open .m-state { color: var(--c-open); }
.month.is-mixed, .month.is-partial { background: var(--c-warn-bg); border-color: #fde68a; }
.month.is-mixed .m-state, .month.is-partial .m-state { color: var(--c-warn); }
.month.is-none { border-style: dashed; background: var(--ov-soft); }
.month.is-none .m-state { color: var(--ov-subtle); }
.month.is-future { opacity: 0.75; }
.month.is-current { box-shadow: inset 0 0 0 2px #1d4ed8; }

.months-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; margin-top: 0.8rem; }
.legend { display: flex; flex-wrap: wrap; gap: 0.3rem 1rem; font-size: 0.76rem; font-weight: 600; color: var(--ov-muted); }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-inline-end: 0.3rem; vertical-align: -1px; border: 1px solid transparent; }
.dot.is-closed { background: var(--c-closed-bg); border-color: #fda4af; }
.dot.is-open { background: #ecfdf5; border-color: #6ee7b7; }
.dot.is-partial { background: var(--c-warn-bg); border-color: #fcd34d; }
.dot.is-none { background: var(--ov-soft); border: 1px dashed #94a3b8; }

/* ── Table ────────────────────────────────────────────────────────────── */
.p-name { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.p-name strong { color: var(--ov-text); font-weight: 800; }
.p-range { font-size: 0.76rem; color: var(--ov-muted); font-variant-numeric: tabular-nums; }
.p-notes { font-size: 0.74rem; color: var(--ov-subtle); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; cursor: help; }
.p-notes i { margin-inline-end: 0.25rem; }

.count-link { font-weight: 800; color: #1d4ed8; text-decoration: none; font-variant-numeric: tabular-nums; }
.count-link:hover { text-decoration: underline; }

.status-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.05rem 0.6rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; }
.status-chip.is-closed { color: var(--c-closed); background: var(--c-closed-bg); }
.status-chip.is-open { color: var(--c-open); background: var(--c-open-bg); }

.readiness { display: block; margin-top: 0.25rem; font-size: 0.74rem; font-weight: 700; }
.readiness i { margin-inline-end: 0.2rem; }
.readiness.is-blocked, .readiness.is-blocked a { color: #b91c1c; text-decoration: none; }
.readiness.is-blocked a:hover { text-decoration: underline; }
.readiness.is-ready { color: var(--c-open); }
.readiness.is-running { color: #1d4ed8; }
.readiness.is-future { color: var(--ov-subtle); }

.action-line { font-size: 0.8rem; color: var(--ov-muted); }
.action-line i { margin-inline-end: 0.3rem; color: var(--c-closed); }
.action-line.is-reopen i { color: var(--c-warn); }

.row-actions { display: inline-flex; align-items: center; gap: 0.15rem; }
.row-actions .el-button + .el-button { margin-inline-start: 0; }
:deep(.el-table .is-highlight > td.el-table__cell) { background: #fef9c3 !important; transition: background 0.3s ease; }

/* ── Dialogs ──────────────────────────────────────────────────────────── */
.hint { display: block; margin-top: 0.35rem; color: var(--ov-muted); font-size: 0.78rem; line-height: 1.5; }
.quarter-pick { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.quarter-pick :deep(.el-date-editor) { width: 130px; }

.facts { margin: 0 0 0.8rem; display: flex; flex-direction: column; }
.facts div { display: flex; justify-content: space-between; gap: 1rem; padding: 0.4rem 0; border-bottom: 1px dashed var(--ov-border); font-size: 0.86rem; }
.facts dt { color: var(--ov-muted); font-weight: 600; }
.facts dd { margin: 0; color: var(--ov-text); font-weight: 700; font-variant-numeric: tabular-nums; }
.lock-text { margin: 0 0 0.8rem; font-size: 0.86rem; line-height: 1.7; color: var(--ov-body); }
.lock-text i { margin-inline-end: 0.35rem; color: var(--ov-subtle); }
</style>

<style>
.notes-tip { max-width: 420px; white-space: pre-line; line-height: 1.6; }
</style>
