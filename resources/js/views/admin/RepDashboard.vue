<template>
    <div ref="rootRef" class="rep-dash" v-loading="loading && !data">
        <!-- Hero: who, which warehouse, and that the figures are theirs alone. -->
        <header class="rd-hero">
            <div class="rd-hero-text">
                <span class="rd-date">{{ todayLabel }}</span>
                <h1>{{ greeting }}</h1>
                <p>{{ $t('rd_subtitle') }}</p>
                <div class="rd-chips">
                    <span v-if="profile.role" class="rd-chip"><i class="fas fa-id-badge"></i>{{ profile.role }}</span>
                    <span v-if="profile.warehouse" class="rd-chip"><i class="fas fa-warehouse"></i>{{ profile.warehouse.name }}</span>
                    <span class="rd-chip rd-chip--lock"><i class="fas fa-lock"></i>{{ $t('rd_private') }}</span>
                </div>
            </div>
            <div class="rd-hero-actions">
                <el-button v-if="canOpen('/admin/sales/invoices/create')" type="primary" :icon="DocumentAdd" @click="go('/admin/sales/invoices/create')">
                    {{ $t('rd_new_invoice') }}
                </el-button>
                <el-button v-if="canOpen('/admin/pos')" :icon="Monitor" @click="go('/admin/pos')">{{ $t('rd_open_pos') }}</el-button>
                <el-button v-if="canOpen('/admin/sales/reports')" :icon="DataAnalysis" @click="go('/admin/sales/reports')">{{ $t('rd_my_report') }}</el-button>
                <el-button circle :icon="Refresh" :loading="loading" :aria-label="$t('rd_refresh')" @click="load" />
            </div>
        </header>

        <el-alert v-if="failed && !data" type="error" :closable="false" show-icon :title="$t('rd_load_failed')" class="rd-error">
            <el-button size="small" @click="load">{{ $t('rd_retry') }}</el-button>
        </el-alert>

        <template v-if="data">
            <!-- KPIs -->
            <section class="rd-kpis">
                <article class="rd-kpi rd-kpi--hero">
                    <span class="rd-kpi-label"><el-icon><TrendCharts /></el-icon>{{ $t('rd_sales_month') }}</span>
                    <strong class="rd-kpi-value">{{ money(sales.month) }}</strong>
                    <span v-if="sales.growth !== null" class="rd-kpi-delta" :class="sales.growth >= 0 ? 'up' : 'down'">
                        <el-icon><component :is="sales.growth >= 0 ? Top : Bottom" /></el-icon>
                        {{ $t('rd_vs_last_month', { pct: `${Math.abs(sales.growth)}%` }) }}
                    </span>
                    <span v-else class="rd-kpi-sub">{{ $t('rd_no_last_month') }}</span>
                    <span class="rd-kpi-sub">{{ $t('rd_today_week', { today: money(sales.today), week: money(sales.week) }) }}</span>
                </article>
                <article class="rd-kpi rd-kpi--green">
                    <span class="rd-kpi-label"><el-icon><Wallet /></el-icon>{{ $t('rd_collected') }}</span>
                    <strong class="rd-kpi-value">{{ money(collections.collected_month) }}</strong>
                </article>
                <article class="rd-kpi" :class="collections.overdue_count ? 'rd-kpi--red' : 'rd-kpi--amber'">
                    <span class="rd-kpi-label"><el-icon><Warning /></el-icon>{{ $t('rd_outstanding') }}</span>
                    <strong class="rd-kpi-value">{{ money(collections.outstanding) }}</strong>
                    <span class="rd-kpi-sub">
                        {{ $t('rd_unpaid_n', { n: collections.unpaid_count }) }}
                        <b v-if="collections.overdue_count" class="rd-overdue">· {{ $t('rd_overdue_n', { n: collections.overdue_count }) }}</b>
                    </span>
                </article>
                <article class="rd-kpi rd-kpi--indigo">
                    <span class="rd-kpi-label"><el-icon><Tickets /></el-icon>{{ $t('rd_invoices_month') }}</span>
                    <strong class="rd-kpi-value">{{ number(sales.month_count) }}</strong>
                    <span class="rd-kpi-sub">{{ $t('rd_avg_value', { value: money(sales.average_value) }) }}</span>
                </article>
                <article class="rd-kpi rd-kpi--cyan">
                    <span class="rd-kpi-label"><el-icon><User /></el-icon>{{ $t('rd_customers_month') }}</span>
                    <strong class="rd-kpi-value">{{ number(data.customers.served_month) }}</strong>
                    <span class="rd-kpi-sub">{{ $t('rd_customers_total', { n: number(data.customers.total) }) }}</span>
                </article>
            </section>

            <!-- Target + trend -->
            <section class="rd-grid rd-grid--target">
                <el-card shadow="never" class="rd-card rd-target">
                    <template #header><span class="rd-card-title"><i class="fas fa-bullseye"></i>{{ $t('rd_target_title') }}</span></template>
                    <template v-if="target && target.monthly_target > 0">
                        <div class="rd-target-ring">
                            <el-progress
                                type="dashboard"
                                :percentage="Math.min(100, target.achievement || 0)"
                                :width="150"
                                :stroke-width="12"
                                :color="ringColor"
                            >
                                <template #default>
                                    <strong class="rd-ring-pct">{{ target.achievement }}%</strong>
                                </template>
                            </el-progress>
                        </div>
                        <p class="rd-ring-sub">{{ $t('rd_target_of', { sales: money(target.sales), target: money(target.monthly_target) }) }}</p>
                        <p v-if="target.remaining <= 0" class="rd-target-done"><i class="fas fa-trophy"></i>{{ $t('rd_target_reached') }}</p>
                        <dl v-else class="rd-target-facts">
                            <div><dt>{{ $t('rd_target_remaining') }}</dt><dd>{{ money(target.remaining) }}</dd></div>
                            <div><dt>{{ $t('rd_target_days_left') }}</dt><dd>{{ target.days_left }}</dd></div>
                            <div><dt>{{ $t('rd_target_per_day') }}</dt><dd>{{ money(target.needed_per_day) }}</dd></div>
                        </dl>
                    </template>
                    <el-empty v-else :description="$t('rd_target_none')" :image-size="70" />
                    <div v-if="target && target.commission_rate > 0" class="rd-commission">
                        <span>{{ $t('rd_commission') }} <small>{{ $t('rd_commission_rate', { rate: target.commission_rate }) }}</small></span>
                        <strong>{{ money(target.commission) }}</strong>
                    </div>
                </el-card>

                <el-card shadow="never" class="rd-card">
                    <template #header><span class="rd-card-title"><i class="fas fa-chart-area"></i>{{ $t('rd_trend_title') }}</span></template>
                    <div v-show="hasTrend" ref="trendRef" class="rd-trend"></div>
                    <el-empty v-if="!hasTrend" :description="$t('rd_trend_empty')" :image-size="80" />
                </el-card>
            </section>

            <!-- To collect + pipeline -->
            <section class="rd-grid">
                <el-card shadow="never" class="rd-card">
                    <template #header>
                        <span class="rd-card-title"><i class="fas fa-hand-holding-dollar"></i>{{ $t('rd_follow_up') }}</span>
                        <router-link v-if="canOpen('/admin/sales/invoices')" to="/admin/sales/invoices" class="rd-link">{{ $t('rd_view_all') }}</router-link>
                    </template>
                    <ul v-if="data.unpaid_invoices.length" class="rd-list">
                        <li v-for="inv in data.unpaid_invoices" :key="inv.id" class="rd-row" @click="openInvoice(inv)">
                            <div class="rd-row-main">
                                <strong>{{ inv.customer || '—' }}</strong>
                                <small>
                                    <span class="rd-mono">{{ inv.invoice_number }}</span>
                                    <span v-if="inv.days_overdue" class="rd-overdue">· {{ $t('rd_overdue_days', { n: inv.days_overdue }) }}</span>
                                    <span v-else-if="inv.due_date">· {{ $t('rd_due_on', { date: shortDate(inv.due_date) }) }}</span>
                                </small>
                            </div>
                            <strong class="rd-amount" :class="{ 'rd-overdue': inv.days_overdue }">{{ money(inv.due_amount) }}</strong>
                        </li>
                    </ul>
                    <el-empty v-else :description="$t('rd_follow_up_empty')" :image-size="70" />
                </el-card>

                <el-card shadow="never" class="rd-card">
                    <template #header>
                        <span class="rd-card-title"><i class="fas fa-boxes-packing"></i>{{ $t('rd_pipeline') }}</span>
                        <span v-if="orders.open_count" class="rd-muted">{{ $t('rd_open_orders', { n: orders.open_count, value: money(orders.open_value) }) }}</span>
                    </template>
                    <div v-if="pipelineTotal" class="rd-pipeline">
                        <button
                            v-for="step in pipeline"
                            :key="step.status"
                            type="button"
                            class="rd-step"
                            :class="`rd-step--${step.status}`"
                            :disabled="!canOpen('/admin/sales/sales-orders')"
                            @click="go('/admin/sales/sales-orders')"
                        >
                            <strong>{{ step.count }}</strong>
                            <span>{{ statusLabel(step.status) }}</span>
                            <i class="rd-step-bar" :style="{ width: `${(step.count / pipelineMax) * 100}%` }"></i>
                        </button>
                    </div>
                    <el-empty v-else :description="$t('rd_pipeline_empty')" :image-size="70" />
                </el-card>
            </section>

            <!-- Top customers + top products -->
            <section class="rd-grid">
                <el-card shadow="never" class="rd-card">
                    <template #header><span class="rd-card-title"><i class="fas fa-users"></i>{{ $t('rd_top_customers') }}</span></template>
                    <ol v-if="data.top_customers.length" class="rd-rank">
                        <li v-for="(c, i) in data.top_customers" :key="c.id">
                            <span class="rd-rank-n">{{ i + 1 }}</span>
                            <div class="rd-row-main">
                                <strong>{{ c.name || '—' }}</strong>
                                <small>{{ $t('rd_invoices_n', { n: c.count }) }}</small>
                                <i class="rd-rank-bar" :style="{ width: `${(c.total / data.top_customers[0].total) * 100}%` }"></i>
                            </div>
                            <strong class="rd-amount">{{ money(c.total) }}</strong>
                        </li>
                    </ol>
                    <el-empty v-else :description="$t('rd_none_this_month')" :image-size="70" />
                </el-card>

                <el-card shadow="never" class="rd-card">
                    <template #header><span class="rd-card-title"><i class="fas fa-box-open"></i>{{ $t('rd_top_products') }}</span></template>
                    <ol v-if="data.top_products.length" class="rd-rank">
                        <li v-for="(p, i) in data.top_products" :key="p.id ?? p.name">
                            <span class="rd-rank-n">{{ i + 1 }}</span>
                            <div class="rd-row-main">
                                <strong>{{ p.name || '—' }}</strong>
                                <small>{{ $t('rd_qty', { n: number(p.quantity) }) }}</small>
                                <i class="rd-rank-bar rd-rank-bar--alt" :style="{ width: `${(p.total / data.top_products[0].total) * 100}%` }"></i>
                            </div>
                            <strong class="rd-amount">{{ money(p.total) }}</strong>
                        </li>
                    </ol>
                    <el-empty v-else :description="$t('rd_none_this_month')" :image-size="70" />
                </el-card>
            </section>

            <!-- Recent invoices + low stock -->
            <section class="rd-grid" :class="{ 'rd-grid--single': !data.low_stock.length }">
                <el-card shadow="never" class="rd-card">
                    <template #header>
                        <span class="rd-card-title"><i class="fas fa-file-invoice"></i>{{ $t('rd_recent') }}</span>
                        <router-link v-if="canOpen('/admin/sales/invoices')" to="/admin/sales/invoices" class="rd-link">{{ $t('rd_view_all') }}</router-link>
                    </template>
                    <ul v-if="data.recent_invoices.length" class="rd-list">
                        <li v-for="inv in data.recent_invoices" :key="inv.id" class="rd-row" @click="openInvoice(inv)">
                            <div class="rd-row-main">
                                <strong>{{ inv.customer || '—' }}</strong>
                                <small><span class="rd-mono">{{ inv.invoice_number }}</span> · {{ shortDate(inv.created_at) }}</small>
                            </div>
                            <el-tag :type="statusTagType(inv.status)" size="small" effect="light" round>{{ statusLabel(inv.status) }}</el-tag>
                            <strong class="rd-amount">{{ money(inv.total) }}</strong>
                        </li>
                    </ul>
                    <el-empty v-else :description="$t('rd_recent_empty')" :image-size="70" />
                </el-card>

                <el-card v-if="data.low_stock.length" shadow="never" class="rd-card">
                    <template #header>
                        <span class="rd-card-title"><i class="fas fa-triangle-exclamation"></i>{{ $t('rd_low_stock', { warehouse: profile.warehouse?.name || '' }) }}</span>
                    </template>
                    <ul class="rd-list">
                        <li v-for="item in data.low_stock" :key="item.id" class="rd-row rd-row--static">
                            <div class="rd-row-main">
                                <strong>{{ item.name }}</strong>
                                <small>{{ $t('rd_available', { n: number(item.available), point: number(item.reorder_point) }) }}</small>
                            </div>
                            <el-tag :type="item.available > 0 ? 'warning' : 'danger'" effect="light" round>{{ number(item.available) }}</el-tag>
                        </li>
                    </ul>
                </el-card>
            </section>
        </template>
    </div>
</template>

<script setup>
// A sales rep's landing screen (User::isSalesScoped): their own business,
// served by /dashboard/stats as the `rep` payload (RepDashboardService).
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import * as echarts from 'echarts';
import {
    Bottom, DataAnalysis, DocumentAdd, Monitor, Refresh, Tickets, Top, TrendCharts, User, Wallet, Warning,
} from '@element-plus/icons-vue';
import { dashboardApi } from '@/api/dashboard';
import { useAuthStore } from '@/stores/auth';
import { useCurrency } from '@/Composables/useCurrency';
import { featureForPath } from '@/config/features';
import { pagePermissionFor } from '@/config/pagePermissions';
import { statusLabel, statusTagType } from '@/utils/sales';

const { t, locale } = useI18n();
const router = useRouter();
const auth = useAuthStore();
const { formatMoney, formatNumber } = useCurrency();

const data = ref(null);
const loading = ref(false);
const failed = ref(false);
const rootRef = ref(null);
const trendRef = ref(null);
let chart = null;
let resizeObserver = null;

const profile = computed(() => data.value?.profile || {});
const sales = computed(() => data.value?.sales || {});
const collections = computed(() => data.value?.collections || {});
const orders = computed(() => data.value?.orders || { by_status: {} });
const target = computed(() => data.value?.target || null);

const money = (value) => formatMoney(value || 0);
const number = (value) => formatNumber(value || 0);
const dateLocale = computed(() => (locale.value === 'en' ? 'en-GB' : 'ar-SY'));
const shortDate = (value) => (value
    ? new Intl.DateTimeFormat(dateLocale.value, { day: 'numeric', month: 'short' }).format(new Date(value))
    : '');

const todayLabel = computed(() => new Intl.DateTimeFormat(dateLocale.value, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}).format(new Date()));

const greeting = computed(() => {
    const hour = new Date().getHours();
    const key = hour < 12 ? 'dash_good_morning' : hour < 18 ? 'dash_good_afternoon' : 'dash_good_evening';
    const firstName = (auth.user?.name || '').trim().split(/\s+/)[0];
    return firstName ? t(`${key}_name`, { name: firstName }) : t(key);
});

// A link is offered only where the rep may actually go.
const canOpen = (path) => {
    const permission = pagePermissionFor(path);
    return auth.canAccessFeature(featureForPath(path)) && (!permission || auth.can(permission));
};
const go = (path) => router.push(path);
const openInvoice = (invoice) => {
    if (canOpen('/admin/sales/invoices')) go(`/admin/sales/invoices/${invoice.id}/edit`);
};

const ringColor = computed(() => {
    const pct = target.value?.achievement || 0;
    if (pct >= 100) return '#16a34a';
    if (pct >= 60) return '#0891b2';
    return '#f59e0b';
});

const pipeline = computed(() => Object.entries(orders.value.by_status || {})
    .map(([status, count]) => ({ status, count })));
const pipelineTotal = computed(() => pipeline.value.reduce((sum, step) => sum + step.count, 0));
const pipelineMax = computed(() => Math.max(1, ...pipeline.value.map((step) => step.count)));

const hasTrend = computed(() => (data.value?.trend || []).some((day) => day.total > 0));

function renderTrend() {
    if (!trendRef.value || !hasTrend.value) return;
    chart = chart || echarts.init(trendRef.value);
    const rows = data.value.trend;
    chart.setOption({
        grid: { left: 8, right: 8, top: 16, bottom: 4, containLabel: true },
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const row = rows[params[0].dataIndex];
                return `${shortDate(row.date)}<br/><b>${money(row.total)}</b> · ${t('rd_invoices_n', { n: row.count })}`;
            },
        },
        xAxis: {
            type: 'category',
            data: rows.map((row) => shortDate(row.date)),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisLabel: { color: '#94a3b8', fontSize: 10 },
            inverse: document.documentElement.dir === 'rtl',
        },
        yAxis: {
            type: 'value',
            position: document.documentElement.dir === 'rtl' ? 'right' : 'left',
            splitLine: { lineStyle: { color: '#f1f5f9' } },
            axisLabel: { color: '#94a3b8', fontSize: 10 },
        },
        // Bars, not a line: a rep's days are often empty, and a smoothed line
        // drew hills between them that no sale ever made.
        series: [{
            type: 'bar',
            barMaxWidth: 18,
            data: rows.map((row) => row.total),
            itemStyle: {
                borderRadius: [6, 6, 0, 0],
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#22c55e' },
                    { offset: 1, color: '#15803d' },
                ]),
            },
        }],
    }, true);
    chart.resize();
}

async function load() {
    loading.value = true;
    failed.value = false;
    try {
        const response = await dashboardApi.getOverviewStats();
        data.value = response.data?.data || null;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
        await nextTick();
        renderTrend();
    }
}

watch(locale, () => nextTick(renderTrend));

onMounted(() => {
    load();
    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => chart?.resize());
        if (rootRef.value) resizeObserver.observe(rootRef.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart?.dispose();
    chart = null;
});
</script>

<style scoped>
.rep-dash {
    min-height: 300px;
}

/* Hero */
.rd-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding: 1.6rem 1.75rem;
    margin-bottom: 1.25rem;
    border-radius: 20px;
    color: #e2e8f0;
    background:
        radial-gradient(50% 140% at 90% 0%, rgba(134, 239, 172, 0.28), transparent 60%),
        radial-gradient(45% 120% at 0% 100%, rgba(103, 232, 249, 0.2), transparent 60%),
        linear-gradient(120deg, #052e16 0%, #0f3d2e 55%, #0d4f4a 100%);
    box-shadow: 0 18px 40px -24px rgba(5, 46, 22, 0.8);
}

.rd-hero-text {
    min-width: 0;
}

.rd-date {
    display: block;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    color: rgba(187, 247, 208, 0.85);
    margin-bottom: 0.35rem;
}

.rd-hero h1 {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
}

.rd-hero p {
    margin: 0.35rem 0 0;
    color: rgba(226, 232, 240, 0.85);
}

.rd-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}

.rd-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #f0fdf4;
}

.rd-chip--lock {
    background: rgba(134, 239, 172, 0.18);
    border-color: rgba(134, 239, 172, 0.4);
}

.rd-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.rd-hero-actions .el-button + .el-button {
    margin-inline-start: 0;
}

.rd-error {
    margin-bottom: 1rem;
}

/* KPIs */
.rd-kpis {
    display: grid;
    grid-template-columns: 1.4fr repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.rd-kpi {
    --tone: #4f46e5;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 1rem 1.1rem;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top: 4px solid var(--tone);
    min-width: 0;
}

.rd-kpi--hero { --tone: #16a34a; background: linear-gradient(160deg, #f0fdf4 0%, #fff 60%); }
.rd-kpi--green { --tone: #059669; }
.rd-kpi--amber { --tone: #d97706; }
.rd-kpi--red { --tone: #dc2626; }
.rd-kpi--indigo { --tone: #4f46e5; }
.rd-kpi--cyan { --tone: #0891b2; }

.rd-kpi-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}

.rd-kpi-label .el-icon {
    color: var(--tone);
}

.rd-kpi-value {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    overflow-wrap: anywhere;
}

.rd-kpi--hero .rd-kpi-value {
    font-size: 1.8rem;
}

.rd-kpi-sub {
    font-size: 0.78rem;
    color: #64748b;
}

.rd-kpi-delta {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 700;
}

.rd-kpi-delta.up { color: #16a34a; }
.rd-kpi-delta.down { color: #dc2626; }

.rd-overdue {
    color: #dc2626;
    font-weight: 700;
}

/* Cards */
.rd-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.rd-grid--target {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

.rd-grid--single {
    grid-template-columns: minmax(0, 1fr);
}

.rd-card {
    border-radius: 16px;
}

.rd-card :deep(.el-card__header) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.85rem 1.1rem;
}

.rd-card-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #0f172a;
}

.rd-card-title i {
    color: #16a34a;
}

.rd-link {
    font-size: 0.82rem;
    font-weight: 600;
    color: #4f46e5;
    text-decoration: none;
}

.rd-muted {
    font-size: 0.8rem;
    color: #64748b;
}

/* Target */
.rd-target-ring {
    display: flex;
    justify-content: center;
}

.rd-ring-pct {
    display: block;
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
}

.rd-ring-sub {
    margin: -0.5rem 0 0;
    text-align: center;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}

.rd-target-facts {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin: 0.75rem 0 0;
}

.rd-target-facts div {
    padding: 0.5rem;
    border-radius: 10px;
    background: #f8fafc;
    text-align: center;
}

.rd-target-facts dt {
    font-size: 0.72rem;
    color: #64748b;
}

.rd-target-facts dd {
    margin: 0.15rem 0 0;
    font-weight: 700;
    color: #0f172a;
    font-size: 0.9rem;
}

.rd-target-done {
    text-align: center;
    color: #16a34a;
    font-weight: 700;
}

.rd-target-done i {
    margin-inline-end: 0.35rem;
}

.rd-commission {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.85rem;
    padding: 0.65rem 0.85rem;
    border-radius: 12px;
    background: #f0fdf4;
    border: 1px dashed #86efac;
    color: #14532d;
    font-weight: 600;
}

.rd-commission small {
    color: #64748b;
    font-weight: 500;
}

.rd-trend {
    height: 260px;
}

/* Lists */
.rd-list,
.rd-rank {
    list-style: none;
    margin: 0;
    padding: 0;
}

.rd-row,
.rd-rank li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.25rem;
    border-bottom: 1px solid #f1f5f9;
}

.rd-row:last-child,
.rd-rank li:last-child {
    border-bottom: none;
}

.rd-row {
    cursor: pointer;
    border-radius: 8px;
}

.rd-row:hover {
    background: #f8fafc;
}

.rd-row--static {
    cursor: default;
}

.rd-row-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    position: relative;
}

.rd-row-main strong {
    font-size: 0.88rem;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rd-row-main small {
    font-size: 0.75rem;
    color: #64748b;
}

.rd-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.rd-amount {
    font-size: 0.9rem;
    color: #0f172a;
    white-space: nowrap;
}

.rd-rank-n {
    flex: 0 0 26px;
    height: 26px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    font-weight: 800;
    color: #16a34a;
    background: #f0fdf4;
}

.rd-rank-bar {
    display: block;
    height: 4px;
    margin-top: 4px;
    border-radius: 999px;
    background: linear-gradient(90deg, #16a34a, #86efac);
}

.rd-rank-bar--alt {
    background: linear-gradient(90deg, #4f46e5, #a5b4fc);
}

/* Pipeline */
.rd-pipeline {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.5rem;
}

.rd-step {
    --tone: #64748b;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 0.85rem 0.35rem 1.1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    overflow: hidden;
    font: inherit;
}

.rd-step:disabled {
    cursor: default;
}

.rd-step strong {
    font-size: 1.35rem;
    color: var(--tone);
}

.rd-step span {
    font-size: 0.75rem;
    color: #475569;
    text-align: center;
}

.rd-step-bar {
    position: absolute;
    inset-inline-start: 0;
    bottom: 0;
    height: 4px;
    background: var(--tone);
}

.rd-step--pending { --tone: #64748b; }
.rd-step--confirmed { --tone: #d97706; }
.rd-step--processing { --tone: #2563eb; }
.rd-step--shipped { --tone: #0891b2; }
.rd-step--delivered { --tone: #16a34a; }

@media (max-width: 1200px) {
    .rd-kpis {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .rd-kpi--hero {
        grid-column: span 3;
    }
}

@media (max-width: 900px) {
    .rd-grid,
    .rd-grid--target {
        grid-template-columns: minmax(0, 1fr);
    }

    .rd-pipeline {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .rd-hero {
        padding: 1.2rem;
    }

    .rd-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rd-kpi--hero {
        grid-column: span 2;
    }
}
</style>
