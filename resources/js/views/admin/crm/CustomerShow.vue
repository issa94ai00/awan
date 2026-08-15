<template>
  <div class="crm-page crm-customer-show" v-loading="loading">
    <!-- Header -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><User /></el-icon>
        </div>
        <div>
          <h1 class="header-title">ملف العميل: {{ customer.name }}</h1>
          <p class="header-subtitle">
            {{ customer.company || 'عميل فردي' }} · {{ customer.email || 'لا يوجد بريد إلكتروني' }}
          </p>
        </div>
      </div>
      <div class="header-actions">
        <el-button @click="router.back()" class="btn-back-premium">
          <el-icon><Back /></el-icon> {{ $t('back') }}
        </el-button>
        <el-button :icon="Refresh" :loading="loading" @click="loadOverview" />
        <el-button type="success" plain @click="paymentDialogVisible = true">
          <i class="fas fa-money-bill-wave"></i> {{ $t('record_payment') }}
        </el-button>
        <el-button type="warning" class="btn-edit-premium" @click="editCustomer">
          <el-icon><Edit /></el-icon> {{ $t('edit_data') }}
        </el-button>
      </div>
    </div>

    <!-- Headline figures, aggregated server-side over the full history -->
    <el-row :gutter="16" class="kpi-row">
      <el-col v-for="kpi in kpis" :key="kpi.key" :xs="12" :sm="12" :md="6">
        <div class="kpi-card" :class="kpi.tone">
          <div class="kpi-icon"><i class="fas" :class="kpi.icon"></i></div>
          <div class="kpi-body">
            <span class="kpi-label">{{ kpi.label }}</span>
            <strong class="kpi-value">{{ kpi.value }}</strong>
            <small v-if="kpi.hint" class="kpi-hint">{{ kpi.hint }}</small>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-alert
      v-if="metrics.over_credit_limit"
      type="error"
      show-icon
      :closable="false"
      class="credit-alert"
      :title="$t('credit_limit_exceeded')"
      :description="`الرصيد المستحق ${money(metrics.balance)} يتجاوز الحد الائتماني المعتمد ${money(metrics.credit_limit)}.`"
    />

    <el-row :gutter="25">
      <!-- Left: profile -->
      <el-col :xs="24" :lg="8">
        <el-card shadow="never" class="details-section-card">
          <div class="profile-avatar-container">
            <div class="profile-avatar" :style="{ backgroundColor: getAvatarColor(customer.name) }">
              {{ getInitials(customer.name) }}
            </div>
            <h3>{{ customer.name }}</h3>
            <span class="status-dot-badge" :class="customer.status || 'inactive'">
              <span class="dot"></span>
              <span class="text">{{ (customer.status || 'inactive') === 'active' ? 'نشط' : 'غير نشط' }}</span>
            </span>
          </div>

          <div class="divider"></div>

          <h4 class="info-section-title">{{ $t('contact_and_company_info') }}</h4>
          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item :label="$t('company_name')">{{ customer.company || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('email')">
              <a v-if="customer.email" :href="`mailto:${customer.email}`" class="contact-link">{{ customer.email }}</a>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('mobile_number')">
              <a v-if="customer.phone" :href="`tel:${customer.phone}`" class="contact-link" dir="ltr">{{ customer.phone }}</a>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('customer_source')">{{ customer.source || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('assigned_employee')">{{ customer.employee?.name || '-' }}</el-descriptions-item>
          </el-descriptions>

          <div class="divider"></div>

          <h4 class="info-section-title">{{ $t('address') }}</h4>
          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item :label="$t('address')">{{ customer.address || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('city')">{{ customer.city || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('country')">{{ customer.country || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('postal_code')">{{ customer.postal_code || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <el-card shadow="never" class="details-section-card financial-card" style="margin-top: 25px">
          <template #header>
            <div class="section-card-header">
              <span class="dot green"></span>
              <h3>{{ $t('financial_summary') }}</h3>
            </div>
          </template>

          <!-- Credit utilisation, so the limit is a live control not a stored number -->
          <div v-if="metrics.credit_limit > 0" class="credit-meter">
            <div class="credit-meter-head">
              <span>{{ $t('credit_limit_usage') }}</span>
              <strong>{{ creditUsagePercent }}%</strong>
            </div>
            <el-progress
              :percentage="Math.min(100, creditUsagePercent)"
              :status="creditUsagePercent >= 100 ? 'exception' : (creditUsagePercent >= 80 ? 'warning' : undefined)"
              :stroke-width="8"
              :show-text="false"
            />
            <div class="credit-meter-foot">
              <span>المتاح: {{ money(metrics.remaining_credit) }}</span>
              <span>الحد: {{ money(metrics.credit_limit) }}</span>
            </div>
          </div>

          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item :label="$t('outstanding_balance')">
              <span class="balance-value" :class="{ 'has-debt': metrics.balance > 0, 'has-credit': metrics.balance < 0 }">
                {{ money(metrics.balance) }}
              </span>
              <small v-if="metrics.balance < 0" class="inline-hint">{{ $t('credit_in_customers_favour') }}</small>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('total_invoiced')">{{ money(metrics.invoiced_total) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('total_collected')">{{ money(metrics.collected_total) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('due_on_invoices')">
              <span :class="{ 'has-debt': metrics.outstanding_total > 0 }">{{ money(metrics.outstanding_total) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('total_refunded')">{{ money(metrics.refunded_total) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('open_credit_notes')">{{ money(metrics.open_credit_total) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('tax_number')">{{ customer.tax_number || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('currency')">{{ currency }}</el-descriptions-item>
            <el-descriptions-item :label="$t('first_order')">{{ date(metrics.first_order_at) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('last_order')">{{ date(metrics.last_order_at) }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>

      <!-- Right: activity across every module the customer touches -->
      <el-col :xs="24" :lg="16">
        <el-card shadow="never" class="table-card-premium">
          <el-tabs v-model="activeTab" class="premium-tabs">
            <el-tab-pane name="orders" :label="tabLabel('طلبات البيع', data.sales_orders)">
              <el-table :data="data.sales_orders" stripe class="premium-table">
                <el-table-column :label="$t('order_number')" width="150">
                  <template #default="{ row }">
                    <button type="button" class="record-link" @click="go('/admin/sales/sales-orders')">
                      {{ row.order_number }}
                    </button>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('grand_total')" width="150">
                  <template #default="{ row }">{{ money(row.total) }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="140" align="center">
                  <template #default="{ row }"><StatusTag :status="row.status" /></template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="140">
                  <template #default="{ row }">{{ date(row.order_date) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.sales_orders.length" text="لا توجد طلبات بيع لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="invoices" :label="tabLabel('الفواتير', data.invoices)">
              <el-table :data="data.invoices" stripe class="premium-table">
                <el-table-column :label="$t('invoice_number')" width="160">
                  <template #default="{ row }">
                    <button type="button" class="record-link" @click="go(`/admin/sales/invoices/${row.id}/edit`)">
                      {{ row.invoice_number }}
                    </button>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('grand_total')" width="130">
                  <template #default="{ row }">{{ money(row.total) }}</template>
                </el-table-column>
                <el-table-column :label="$t('payment_status')" width="180">
                  <template #default="{ row }">
                    <el-progress
                      :percentage="paidPercent(row)"
                      :status="paidPercent(row) >= 100 ? 'success' : undefined"
                      :stroke-width="6"
                      :show-text="false"
                    />
                    <div class="mini-figures">
                      <span class="paid">{{ money(row.paid_amount) }}</span>
                      <span class="sep">/</span>
                      <span class="due">{{ money(row.due_amount) }}</span>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="130" align="center">
                  <template #default="{ row }"><StatusTag :status="row.status" /></template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="130">
                  <template #default="{ row }">{{ date(row.created_at) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.invoices.length" text="لا توجد فواتير مسجلة لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="payments" :label="tabLabel('المدفوعات', data.payments)">
              <el-table :data="data.payments" stripe class="premium-table">
                <el-table-column :label="$t('payment_number')" width="170">
                  <template #default="{ row }">{{ row.payment_number || row.reference || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('invoice')" width="150">
                  <template #default="{ row }">{{ row.invoice?.invoice_number || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('amount')" width="150">
                  <template #default="{ row }">
                    <!-- Refunds are negative, so they read as money out -->
                    <strong :class="Number(row.amount) < 0 ? 'due' : 'paid'">{{ money(row.amount) }}</strong>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('method')" width="130" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ methodLabel(row.payment_method) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="120" align="center">
                  <template #default="{ row }"><StatusTag :status="row.status" /></template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="130">
                  <template #default="{ row }">{{ date(row.payment_date) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.payments.length" text="لم تُسجَّل دفعات لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="credit_notes" :label="tabLabel('إشعارات دائنة', data.credit_notes)">
              <el-table :data="data.credit_notes" stripe class="premium-table">
                <el-table-column prop="credit_note_number" :label="$t('note_number')" width="150" />
                <el-table-column :label="$t('source')" width="150">
                  <template #default="{ row }">{{ row.rma_request?.rma_number || row.invoice?.invoice_number || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('value')" width="140">
                  <template #default="{ row }">{{ money(row.total) }}</template>
                </el-table-column>
                <el-table-column :label="$t('not_settled')" width="140">
                  <template #default="{ row }">
                    <strong :class="Number(row.open_amount) > 0 ? 'due' : ''">{{ money(row.open_amount) }}</strong>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="140" align="center">
                  <template #default="{ row }">
                    <el-tag :type="creditNoteTone(row.status)" size="small">{{ row.status_text }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="130">
                  <template #default="{ row }">{{ date(row.issue_date) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.credit_notes.length" text="لا توجد إشعارات دائنة لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="returns" :label="tabLabel('المرتجعات', data.rma_requests)">
              <el-table :data="data.rma_requests" stripe class="premium-table">
                <el-table-column :label="$t('rma_number')" width="150">
                  <template #default="{ row }">
                    <button type="button" class="record-link" @click="go(`/admin/rma/${row.id}`)">
                      {{ row.rma_number }}
                    </button>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('sales_order')" width="150">
                  <template #default="{ row }">{{ row.sales_order?.order_number || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('compensation_value')" width="150">
                  <template #default="{ row }">{{ money(row.refund_amount) }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="150" align="center">
                  <template #default="{ row }">
                    <el-tag :type="rmaTone(row.status)" size="small">{{ rmaStatusLabel(row.status) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="130">
                  <template #default="{ row }">{{ date(row.requested_at) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.rma_requests.length" text="لا توجد مرتجعات لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="quotes" :label="tabLabel('عروض الأسعار', data.quotes)">
              <el-table :data="data.quotes" stripe class="premium-table">
                <el-table-column :label="$t('quote_number')" width="150">
                  <template #default="{ row }">
                    <button type="button" class="record-link" @click="go('/admin/sales/quotes')">
                      {{ row.quote_number }}
                    </button>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('grand_total')" width="150">
                  <template #default="{ row }">{{ money(row.total) }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="140" align="center">
                  <template #default="{ row }"><StatusTag :status="row.status" /></template>
                </el-table-column>
                <el-table-column :label="$t('valid_until')" width="140">
                  <template #default="{ row }">{{ date(row.valid_until) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.quotes.length" text="لا توجد عروض أسعار لهذا العميل." />
            </el-tab-pane>

            <el-tab-pane name="tickets" :label="tabLabel('تذاكر الدعم', data.tickets)">
              <el-table :data="data.tickets" stripe class="premium-table">
                <el-table-column label="#" width="90">
                  <template #default="{ row }">#{{ row.id }}</template>
                </el-table-column>
                <el-table-column prop="subject" :label="$t('address')" min-width="180" />
                <el-table-column :label="$t('priority')" width="120" align="center">
                  <template #default="{ row }">
                    <el-tag :type="getPriorityClass(row.priority)" size="small">{{ getPriorityLabel(row.priority) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="120" align="center">
                  <template #default="{ row }">
                    <el-tag :type="['closed', 'resolved'].includes(row.status) ? 'success' : 'danger'" size="small">
                      {{ ['closed', 'resolved'].includes(row.status) ? 'مغلقة' : 'مفتوحة' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="$t('date')" width="130">
                  <template #default="{ row }">{{ date(row.created_at) }}</template>
                </el-table-column>
              </el-table>
              <EmptyHint v-if="!data.tickets.length" text="لا توجد تذاكر دعم لهذا العميل." />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>

    <QuickPaymentDialog
      v-model="paymentDialogVisible"
      :customer-id="customer.id"
      @saved="loadOverview"
    />
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, h, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { User, Back, Edit, Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElTag } from 'element-plus';
import api from '@/api';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';

const { t } = useI18n();
import {
  formatCurrency,
  formatDate,
  statusLabel,
  statusTagType,
  paymentMethodLabel,
} from '@/utils/sales';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const activeTab = ref('orders');
const paymentDialogVisible = ref(false);

const customer = ref({ id: null, name: '', status: 'inactive' });
const metrics = ref({});
const data = ref({
  sales_orders: [],
  invoices: [],
  payments: [],
  credit_notes: [],
  rma_requests: [],
  quotes: [],
  tickets: [],
});

const currency = computed(() => customer.value.currency || 'SAR');

// Money is shown in the customer's own currency rather than the system default.
const money = (value) => formatCurrency(value, currency.value);
const date = (value) => formatDate(value);
const methodLabel = (value) => paymentMethodLabel(value);

/**
 * Shared status pill. Sales orders, invoices, payments and quotes all use the
 * same identifiers, so they share the sales module's presentation helpers and
 * stay consistent with those screens.
 */
const StatusTag = (props) =>
  h(ElTag, { type: statusTagType(props.status), size: 'small', effect: 'light' }, () => statusLabel(props.status));
StatusTag.props = ['status'];

const EmptyHint = (props) => h('div', { class: 'empty-state' }, props.text);
EmptyHint.props = ['text'];

const tabLabel = (label, rows) => (rows?.length ? `${label} (${rows.length})` : label);

const go = (path) => router.push(path);

const creditUsagePercent = computed(() => {
  const limit = Number(metrics.value.credit_limit) || 0;
  if (limit <= 0) return 0;
  return Math.max(0, Math.round(((Number(metrics.value.balance) || 0) / limit) * 100));
});

const paidPercent = (invoice) => {
  const total = Number(invoice.total) || 0;
  if (total <= 0) return 0;
  return Math.min(100, Math.round(((Number(invoice.paid_amount) || 0) / total) * 100));
};

const kpis = computed(() => [
  {
    key: 'outstanding',
    label: t('due_on_invoices'),
    value: money(metrics.value.outstanding_total),
    icon: 'fa-hand-holding-dollar',
    tone: Number(metrics.value.outstanding_total) > 0 ? 'red' : 'green',
    hint: `${metrics.value.invoices_count || 0} فاتورة`,
  },
  {
    key: 'lifetime',
    label: t('total_business'),
    value: money(metrics.value.orders_total),
    icon: 'fa-cart-shopping',
    tone: 'blue',
    hint: `${metrics.value.orders_count || 0} طلب · ${metrics.value.open_orders_count || 0} مفتوح`,
  },
  {
    key: 'collected',
    label: t('total_collected'),
    value: money(metrics.value.collected_total),
    icon: 'fa-sack-dollar',
    tone: 'green',
    hint: Number(metrics.value.refunded_total) > 0 ? `مسترد ${money(metrics.value.refunded_total)}` : null,
  },
  {
    key: 'credit',
    label: t('open_credit_notes'),
    value: money(metrics.value.open_credit_total),
    icon: 'fa-receipt',
    tone: 'purple',
    hint: `${metrics.value.returns_count || 0} مرتجع · ${metrics.value.open_returns_count || 0} قيد المعالجة`,
  },
]);

const creditNoteTone = (status) => ({
  issued: 'warning',
  partially_applied: 'primary',
  applied: 'success',
  cancelled: 'info',
}[status] || 'info');

const rmaTone = (status) => ({
  pending: 'warning',
  approved: 'primary',
  received: 'success',
  completed: 'success',
  rejected: 'danger',
  cancelled: 'info',
}[status] || 'info');

const rmaStatusLabel = (status) => ({
  pending: t('awaiting_approval'),
  approved: t('approved'),
  received: t('event_received'),
  completed: t('sales_status_completed'),
  rejected: t('sales_status_rejected'),
  cancelled: t('sales_status_cancelled'),
}[status] || status);

/**
 * One request for the whole profile.
 *
 * The page used to call `/api/admin/invoices` and `/api/admin/tickets` with a
 * bare axios instance: the paths did not exist (the API is served under
 * `/api/v1`) and the bare instance sent no bearer token, so both tabs failed
 * silently to the console and always rendered empty.
 */
const loadOverview = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/admin/customers/${route.params.id}/overview`);
    const payload = response.data?.data || {};

    customer.value = payload.customer || {};
    metrics.value = payload.metrics || {};
    data.value = {
      sales_orders: payload.sales_orders || [],
      invoices: payload.invoices || [],
      payments: payload.payments || [],
      credit_notes: payload.credit_notes || [],
      rma_requests: payload.rma_requests || [],
      quotes: payload.quotes || [],
      tickets: payload.tickets || [],
    };
  } catch (error) {
    console.error('Failed to load customer profile:', error);
    ElMessage.error(error.response?.data?.message || t('failed_to_load_customer_profile'));
    if (error.response?.status === 404) router.back();
  } finally {
    loading.value = false;
  }
};

const editCustomer = () => {
  router.push({ name: 'admin.crm.customers.edit', params: { id: customer.value.id } });
};

const getInitials = (name) => {
  if (!name) return 'C';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
};

const getAvatarColor = (name) => {
  if (!name) return '#2563eb';
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  const colors = ['#6366f1', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#14b8a6'];
  return colors[Math.abs(hash) % colors.length];
};

const getPriorityLabel = (priority) => ({
  low: t('low'),
  medium: t('medium'),
  high: t('high'),
  urgent: t('urgent'),
}[priority] || priority || '-');

const getPriorityClass = (priority) => ({
  low: 'info',
  medium: 'warning',
  high: 'danger',
  urgent: 'danger',
}[priority] || 'info');

onMounted(loadOverview);
</script>

<style scoped>
.crm-customer-show {
  padding: 4px;
}

/* ---------- Header ---------- */

.page-header-premium {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  padding: 20px 24px;
  margin-bottom: 20px;
}

.header-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon-box {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #fff;
  font-size: 22px;
}

.header-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: #0f172a;
}

.header-subtitle {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 0.88rem;
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* ---------- KPI cards ---------- */

.kpi-row {
  margin-bottom: 18px;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 16px 18px;
  margin-bottom: 12px;
  min-height: 86px;
}

.kpi-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 1.05rem;
}

.kpi-card.blue .kpi-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.kpi-card.green .kpi-icon { background: linear-gradient(135deg, #10b981, #059669); }
.kpi-card.red .kpi-icon { background: linear-gradient(135deg, #ef4444, #b91c1c); }
.kpi-card.purple .kpi-icon { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

.kpi-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.kpi-label {
  font-size: 0.8rem;
  color: #64748b;
}

.kpi-value {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-hint {
  font-size: 0.74rem;
  color: #94a3b8;
  margin-top: 2px;
}

.credit-alert {
  margin-bottom: 18px;
  border-radius: 14px;
}

/* ---------- Cards ---------- */

.details-section-card,
.table-card-premium {
  border: 1px solid #e2e8f0;
  border-radius: 18px;
}

.profile-avatar-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 8px 0 4px;
}

.profile-avatar {
  width: 78px;
  height: 78px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 1.6rem;
  font-weight: 700;
}

.profile-avatar-container h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #0f172a;
}

.divider {
  height: 1px;
  background: #e2e8f0;
  margin: 18px 0;
}

.info-section-title {
  margin: 0 0 10px;
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
}

.section-card-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-card-header .dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #3b82f6;
}

.section-card-header .dot.green { background: #10b981; }

.section-card-header h3 {
  margin: 0;
  font-size: 1rem;
  color: #0f172a;
}

.status-dot-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
}

.status-dot-badge.active { background: #ecfdf5; color: #059669; }
.status-dot-badge.inactive { background: #f1f5f9; color: #64748b; }

.status-dot-badge .dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.status-dot-badge.active .dot { background: #059669; }
.status-dot-badge.inactive .dot { background: #64748b; }

/* ---------- Financials ---------- */

.credit-meter {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 16px;
}

.credit-meter-head,
.credit-meter-foot {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
  color: #64748b;
}

.credit-meter-head {
  margin-bottom: 8px;
}

.credit-meter-head strong {
  color: #0f172a;
}

.credit-meter-foot {
  margin-top: 8px;
  font-size: 0.76rem;
}

.balance-value {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.balance-value.has-debt,
.has-debt,
.due {
  color: #dc2626;
}

.balance-value.has-credit,
.paid {
  color: #059669;
}

.inline-hint {
  display: block;
  font-size: 0.72rem;
  color: #94a3b8;
}

/* ---------- Tables ---------- */

.premium-table {
  width: 100%;
  margin-top: 4px;
}

.record-link {
  background: none;
  border: none;
  padding: 0;
  font: inherit;
  font-weight: 600;
  color: #2563eb;
  cursor: pointer;
}

.record-link:hover,
.record-link:focus-visible {
  text-decoration: underline;
}

.mini-figures {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  margin-top: 3px;
}

.mini-figures .sep {
  color: #cbd5e1;
}

.contact-link {
  color: #475569;
  text-decoration: none;
}

.contact-link:hover {
  color: #2563eb;
  text-decoration: underline;
}

.empty-state {
  padding: 28px;
  text-align: center;
  color: #94a3b8;
  font-size: 0.9rem;
}

@media (max-width: 768px) {
  .header-actions {
    width: 100%;
  }
}
</style>
