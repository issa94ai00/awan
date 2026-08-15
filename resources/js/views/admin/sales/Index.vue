<template>
    <div class="sales-page sales-index">
        <AdminPageHeader
            icon="fas fa-chart-line"
            :title="$t('sales_overview')"
            :subtitle="$t('track_orders_invoices_offers_and')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="loading" @click="refreshSummary">
                    {{ $t('data_update') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- KPI row. Each card navigates to the screen it summarises. -->
        <AdminStatGrid>
            <el-card v-for="card in stats" :key="card.key" shadow="hover" class="stat-card" @click="card.to && router.push(card.to)">
                <div class="stat-inner">
                    <div class="stat-icon" :class="card.tone">
                        <i class="fas" :class="card.icon"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ card.value }}</h3>
                        <p>{{ card.label }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- The sales pipeline, showing where work is stuck. -->
        <el-card shadow="hover" class="table-panel pipeline-card">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-diagram-project"></i> {{ $t('sales_pipeline') }}</span>
                </div>
            </template>
            <div class="pipeline">
                <template v-for="(stage, index) in pipeline" :key="stage.key">
                    <button type="button" class="pipeline-stage" @click="router.push(stage.to)">
                        <div class="pipeline-icon" :class="stage.tone">
                            <i class="fas" :class="stage.icon"></i>
                        </div>
                        <strong>{{ stage.count }}</strong>
                        <span>{{ stage.label }}</span>
                        <small>{{ stage.amount }}</small>
                    </button>
                    <div v-if="index < pipeline.length - 1" class="pipeline-arrow" aria-hidden="true">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                </template>
            </div>
        </el-card>

        <el-row :gutter="16" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-clock-rotate-left"></i> {{ $t('recent_activity') }}</span>
                            <el-radio-group v-model="activityTab" size="small">
                                <el-radio-button value="orders">{{ $t('sales_orders') }}</el-radio-button>
                                <el-radio-button value="invoices">{{ $t('invoices') }}</el-radio-button>
                                <el-radio-button value="quotes">{{ $t('quotes') }}</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>

                    <el-skeleton v-if="loading" :rows="5" animated />

                    <template v-else>
                        <el-table v-if="activityRows.length" :data="activityRows" style="width: 100%" stripe>
                            <el-table-column :label="$t('reference')" width="150">
                                <template #default="{ row }">
                                    <button type="button" class="record-link" @click="router.push(activityTarget)">
                                        {{ row.reference }}
                                    </button>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('client')" min-width="150">
                                <template #default="{ row }">{{ row.customer }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('total')" width="150">
                                <template #default="{ row }">
                                    <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('status')" width="150" align="center">
                                <template #default="{ row }">
                                    <!-- This was `type="item.type"` (no binding), so Element
                                         Plus received the literal string and rendered an
                                         untyped tag on every row. -->
                                    <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                        <i class="fas" :class="statusIcon(row.status)"></i>
                                        {{ statusLabel(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                        </el-table>

                        <el-empty v-else :description="$t('there_are_no_sell_orders_yet')" />
                    </template>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="table-panel insight-card">
                    <template #header>
                        <span><i class="fas fa-circle-info"></i> {{ $t('quick_summary') }}</span>
                    </template>
                    <div class="insight-list">
                        <router-link
                            v-for="insight in insights"
                            :key="insight.key"
                            :to="insight.to"
                            class="insight-item"
                        >
                            <span>{{ insight.label }}</span>
                            <strong :class="insight.tone">{{ insight.value }}</strong>
                        </router-link>
                    </div>

                    <el-alert
                        v-if="outstandingTotal > 0"
                        type="warning"
                        show-icon
                        :closable="false"
                        class="outstanding-alert"
                    >
                        {{ $t('outstanding_amount') }}:
                        <strong>{{ formatCurrency(outstandingTotal) }}</strong>
                    </el-alert>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Refresh } from '@element-plus/icons-vue';
import { useSalesOrdersStore } from '@/stores/salesOrders';
import { useQuotesStore } from '@/stores/quotes';
import { useInvoicesStore } from '@/stores/invoices';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();
import {
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    formatCurrency,
    customerName,
    sumBy,
    invoiceDue,
    isInvoiceSettled,
} from '@/utils/sales';

const router = useRouter();
const salesStore = useSalesOrdersStore();
const quotesStore = useQuotesStore();
const invoicesStore = useInvoicesStore();

const loading = ref(false);
const activityTab = ref('orders');

const dueAmount = invoiceDue;

/**
 * Revenue = cash actually collected.
 *
 * This previously summed the *total* of every invoice whose status was one of
 * confirmed/processing/shipped/delivered, which counts unpaid invoices as
 * revenue and overstates the figure. `paid_amount` is what the payments
 * endpoint maintains, so it is the honest number.
 */
const collectedRevenue = computed(() => sumBy(invoicesStore.invoices, 'paid_amount'));

const outstandingTotal = computed(() =>
    invoicesStore.invoices
        .filter((invoice) => normalizeStatus(invoice.status) !== 'cancelled')
        .reduce((sum, invoice) => sum + Math.max(0, dueAmount(invoice)), 0)
);

const stats = computed(() => [
    {
        key: 'quotes',
        label: window.t?.('quotes') || t('quotes'),
        value: quotesStore.quotes.length,
        icon: 'fa-file-signature',
        tone: 'purple',
        to: '/admin/sales/quotes',
    },
    {
        key: 'orders',
        label: window.t?.('sales_orders') || t('sales_orders'),
        value: salesStore.orders.length,
        icon: 'fa-cart-shopping',
        tone: 'blue',
        to: '/admin/sales/sales-orders',
    },
    {
        key: 'invoices',
        label: window.t?.('invoices') || t('invoices'),
        value: invoicesStore.invoices.length,
        icon: 'fa-file-invoice-dollar',
        tone: 'orange',
        to: '/admin/sales/invoices',
    },
    {
        key: 'revenue',
        label: window.t?.('collected_amount') || t('collected'),
        value: formatCurrency(collectedRevenue.value),
        icon: 'fa-sack-dollar',
        tone: 'green',
        to: '/admin/sales/payments',
    },
]);

const pipeline = computed(() => {
    const acceptedQuotes = quotesStore.quotes.filter((q) => normalizeStatus(q.status) === 'accepted');
    const openOrders = salesStore.orders.filter((o) =>
        ['pending', 'confirmed', 'processing'].includes(normalizeStatus(o.status))
    );
    const unpaidInvoices = invoicesStore.invoices.filter(
        (i) => normalizeStatus(i.status) !== 'cancelled' && !isInvoiceSettled(i)
    );
    const settledInvoices = invoicesStore.invoices.filter(
        (i) => normalizeStatus(i.status) !== 'cancelled' && isInvoiceSettled(i)
    );

    return [
        {
            key: 'quotes',
            label: window.t?.('accepted_offers') || t('accepted_quotes'),
            count: acceptedQuotes.length,
            amount: formatCurrency(sumBy(acceptedQuotes, 'total')),
            icon: 'fa-file-signature',
            tone: 'purple',
            to: '/admin/sales/quotes',
        },
        {
            key: 'orders',
            label: window.t?.('open_orders') || t('open_orders'),
            count: openOrders.length,
            amount: formatCurrency(sumBy(openOrders, 'total')),
            icon: 'fa-cart-shopping',
            tone: 'blue',
            to: '/admin/sales/sales-orders',
        },
        {
            key: 'unpaid',
            label: window.t?.('unpaid_invoices') || t('unpaid_invoices'),
            count: unpaidInvoices.length,
            amount: formatCurrency(unpaidInvoices.reduce((s, i) => s + Math.max(0, dueAmount(i)), 0)),
            icon: 'fa-hourglass-half',
            tone: 'orange',
            to: '/admin/sales/invoices',
        },
        {
            key: 'settled',
            label: window.t?.('settled_invoices') || t('settled_invoices'),
            count: settledInvoices.length,
            amount: formatCurrency(sumBy(settledInvoices, 'paid_amount')),
            icon: 'fa-circle-check',
            tone: 'green',
            to: '/admin/sales/payments',
        },
    ];
});

const insights = computed(() => [
    {
        key: 'orders',
        label: window.t?.('sales_orders') || t('sales_orders'),
        value: salesStore.orders.length,
        to: '/admin/sales/sales-orders',
        tone: '',
    },
    {
        key: 'invoices',
        label: window.t?.('invoices') || t('invoices'),
        value: invoicesStore.invoices.length,
        to: '/admin/sales/invoices',
        tone: '',
    },
    {
        key: 'quotes',
        label: window.t?.('quotes') || t('quotes'),
        value: quotesStore.quotes.length,
        to: '/admin/sales/quotes',
        tone: '',
    },
    {
        key: 'collected',
        label: window.t?.('collected_amount') || t('collected'),
        value: formatCurrency(collectedRevenue.value),
        to: '/admin/sales/payments',
        tone: 'positive',
    },
]);

const activityTarget = computed(() => ({
    orders: '/admin/sales/sales-orders',
    invoices: '/admin/sales/invoices',
    quotes: '/admin/sales/quotes',
}[activityTab.value]));

const activityRows = computed(() => {
    if (activityTab.value === 'invoices') {
        return invoicesStore.invoices.slice(0, 6).map((invoice) => ({
            reference: invoice.invoice_number,
            customer: customerName(invoice),
            total: invoice.total,
            status: invoice.status,
        }));
    }
    if (activityTab.value === 'quotes') {
        return quotesStore.quotes.slice(0, 6).map((quote) => ({
            reference: quote.quote_number,
            customer: customerName(quote),
            total: quote.total,
            status: quote.status,
        }));
    }
    return salesStore.orders.slice(0, 6).map((order) => ({
        reference: order.order_number,
        customer: customerName(order),
        total: order.total,
        status: order.status,
    }));
});

const refreshSummary = async () => {
    loading.value = true;
    try {
        await Promise.all([
            salesStore.fetchOrders().catch(() => {}),
            quotesStore.fetchQuotes().catch(() => {}),
            invoicesStore.fetchInvoices().catch(() => {}),
        ]);
    } finally {
        loading.value = false;
    }
};

onMounted(refreshSummary);
</script>

<style scoped>
.mt-4 {
    margin-top: 1.5rem;
}

/* ---------- Pipeline ---------- */

.pipeline {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.pipeline-stage {
    flex: 1 1 0;
    min-width: 130px;
    display: grid;
    justify-items: center;
    gap: 0.3rem;
    padding: 1rem 0.75rem;
    border: 1px solid var(--el-border-color-lighter, #ebeef5);
    border-radius: 0.85rem;
    background: var(--el-bg-color, #fff);
    cursor: pointer;
    font: inherit;
    transition: border-color 0.2s ease, transform 0.2s ease;
}

.pipeline-stage:hover,
.pipeline-stage:focus-visible {
    border-color: var(--el-color-primary, #409eff);
    transform: translateY(-2px);
}

.pipeline-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    margin-bottom: 0.2rem;
}

.pipeline-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.pipeline-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
.pipeline-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.pipeline-icon.purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

.pipeline-stage strong {
    font-size: 1.5rem;
    line-height: 1;
    color: var(--el-text-color-primary, #24314f);
}

.pipeline-stage span {
    font-size: 0.85rem;
    color: var(--el-text-color-secondary, #6b7c98);
    text-align: center;
}

.pipeline-stage small {
    font-size: 0.78rem;
    color: var(--el-text-color-placeholder, #a8abb2);
    font-variant-numeric: tabular-nums;
}

.pipeline-arrow {
    display: grid;
    place-items: center;
    color: var(--el-text-color-placeholder, #c0c4cc);
    flex: 0 0 auto;
}

/* The chevron points along the reading direction. */
:global([dir='ltr']) .pipeline-arrow i {
    transform: rotate(180deg);
}

/* ---------- Insights ---------- */

.insight-list {
    display: grid;
    gap: 0.65rem;
}

.insight-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    background: var(--el-fill-color-light, #f8fbff);
    color: var(--el-text-color-regular, #5f6d85);
    text-decoration: none;
    transition: background 0.2s ease;
}

.insight-item:hover {
    background: var(--el-fill-color, #f0f2f5);
}

.insight-item strong {
    color: var(--el-text-color-primary, #24314f);
    font-size: 1.1rem;
}

.insight-item strong.positive {
    color: var(--el-color-success, #67c23a);
}

.outstanding-alert {
    margin-top: 1rem;
}
</style>
