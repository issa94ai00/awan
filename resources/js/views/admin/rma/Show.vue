<template>
  <div class="rma-details-container">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><RefreshLeft /></el-icon>
        </div>
        <div>
          <h1 class="header-title">{{ $t('return_request_details_title', { number: rma.rma_number }) }}</h1>
          <p class="header-subtitle">{{ $t('rma_show_subtitle') }}</p>
        </div>
      </div>
      <div class="header-actions">
        <el-button @click="goBack" class="btn-back-premium">
          <el-icon><Back /></el-icon> {{ $t('back') }}
        </el-button>
        <el-button @click="router.push(`/admin/rma/${rma.id}/edit`)" :disabled="rma.status !== 'pending'" class="btn-edit-premium">
          <el-icon><Edit /></el-icon> {{ $t('edit') }}
        </el-button>
      </div>
    </div>

    <!-- Stepper Lifecycle Progress -->
    <el-card class="lifecycle-card" shadow="never" v-if="rma.status !== 'rejected' && rma.status !== 'cancelled'">
      <el-steps :active="currentStep" align-center finish-status="success" class="premium-steps" process-status="success">
        <el-step>
          <template #icon>
            <el-icon><DocumentAdd /></el-icon>
          </template>
          <template #title>
            <span class="step-title">{{ $t('request_submitted') }}</span>
          </template>
          <template #description>
            <span class="step-desc">{{ formatDate(rma.created_at) }}</span>
          </template>
        </el-step>
        <el-step>
          <template #icon>
            <el-icon><Select /></el-icon>
          </template>
          <template #title>
            <span class="step-title">{{ $t('approved') }}</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.approved_at">{{ formatDate(rma.approved_at) }}</span>
            <span class="step-desc" v-else>{{ $t('awaiting_approval_state') }}</span>
          </template>
        </el-step>
        <el-step>
          <template #icon>
            <el-icon><Box /></el-icon>
          </template>
          <template #title>
            <span class="step-title">{{ $t('receive_products') }}</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.received_at">{{ formatDate(rma.received_at) }}</span>
            <span class="step-desc" v-else-if="receivedUnits > 0">{{ $t('units_received_count', { count: receivedUnits }) }}</span>
            <span class="step-desc" v-else>{{ $t('awaiting_receipt') }}</span>
          </template>
        </el-step>
        <el-step>
          <template #icon>
            <el-icon><CircleCheck /></el-icon>
          </template>
          <template #title>
            <span class="step-title">{{ $t('settlement_completed') }}</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.completed_at">{{ formatDate(rma.completed_at) }}</span>
            <span class="step-desc" v-else>{{ $t('awaiting_completion') }}</span>
          </template>
        </el-step>
      </el-steps>
    </el-card>

    <!-- Reject/Cancel Banner -->
    <div class="alert-banner" :class="rma.status" v-else>
      <el-icon><Warning /></el-icon>
      <div>
        <h4>{{ rma.status === 'rejected' ? $t('return_request_rejected_heading') : $t('return_request_cancelled_heading') }}</h4>
        <p v-if="rma.status === 'rejected'">{{ $t('rejection_reason_label') }}: {{ rma.notes || $t('no_rejection_reason_given') }}</p>
        <p v-else>{{ $t('request_cancelled_notice') }}</p>
      </div>
    </div>

    <!-- Main Content Area Grid -->
    <el-row :gutter="25">
      <!-- Left Column: Details Cards -->
      <el-col :xs="24" :lg="16">
        <!-- Returned Items Card -->
        <el-card class="details-section-card" shadow="never">
          <template #header>
            <div class="section-card-header">
              <span class="dot"></span>
              <h3>{{ $t('products_in_return_request') }}</h3>
            </div>
          </template>

          <el-table :data="rma.items" stripe class="items-table-premium">
            <el-table-column prop="product" :label="$t('product')" min-width="200" />
            <el-table-column prop="quantity_requested" :label="$t('quantity_ordered')" width="130" align="center" />
            <el-table-column prop="quantity_received" :label="$t('quantity_received')" width="130" align="center">
              <template #default="{ row }">
                <span :class="{'qty-warning': row.quantity_received < row.quantity_requested, 'qty-success': row.quantity_received === row.quantity_requested}">
                  {{ row.quantity_received }} / {{ row.quantity_requested }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="condition" :label="$t('received_product_condition')" width="140">
              <template #default="{ row }">
                <el-tag :type="getConditionTagType(row.condition)" class="premium-tag">
                  {{ getConditionLabel(row.condition) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="resolution" :label="$t('line_settlement_type')" width="130">
              <template #default="{ row }">
                <el-tag :type="getResolutionTagType(row.resolution)" class="premium-tag">
                  {{ getResolutionLabel(row.resolution) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="exchange_product" :label="$t('replacement_product')" min-width="160" v-if="rma.items.some(i => i.resolution === 'exchange')">
              <template #default="{ row }">
                <span class="exchange-product-text" v-if="row.resolution === 'exchange'">{{ row.exchange_product || $t('not_chosen_yet') }}</span>
                <span class="text-placeholder" v-else>-</span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>

        <!-- Timeline / Audit Logs Card -->
        <el-card class="details-section-card" shadow="never" style="margin-top: 25px">
          <template #header>
            <div class="section-card-header">
              <span class="dot"></span>
              <h3>{{ $t('activity_and_tracking_log') }}</h3>
            </div>
          </template>

          <el-timeline class="premium-timeline">
            <el-timeline-item
              v-for="activity in activities"
              :key="activity.id"
              :timestamp="formatDate(activity.created_at)"
              placement="top"
              :type="getTimelineType(activity.action)"
            >
              <div class="timeline-content-card">
                <div class="timeline-header">
                  <span class="action-badge">{{ activity.action }}</span>
                  <span class="user-badge"><el-icon><User /></el-icon> {{ activity.user }}</span>
                </div>
                <p class="timeline-desc">{{ activity.description }}</p>
              </div>
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>

      <!-- Right Column: Operations Panel & Information Summary -->
      <el-col :xs="24" :lg="8">
        <!-- Operations Panel Card -->
        <el-card class="operations-card-premium" shadow="never">
          <template #header>
            <div class="section-card-header">
              <span class="dot orange"></span>
              <h3>{{ $t('operations_and_actions') }}</h3>
            </div>
          </template>

          <div class="operations-buttons-grid">
            <el-button type="success" class="op-btn approve" :disabled="rma.status !== 'pending'" @click="approveRma">
              <el-icon><Check /></el-icon> {{ $t('approve_the_request') }}
            </el-button>
            <el-button type="danger" class="op-btn reject" :disabled="rma.status !== 'pending'" @click="rejectRma">
              <el-icon><Close /></el-icon> {{ $t('reject_the_return') }}
            </el-button>
            <!-- Receiving stays available while `received` so a miscounted
                 receipt can be corrected; the server books only the difference. -->
            <el-button type="warning" class="op-btn receive" :disabled="!canReceive" @click="openReceiveDialog">
              <el-icon><Location /></el-icon>
              {{ rma.status === 'received' ? $t('edit_received_quantities') : $t('receive_and_inspect_products') }}
            </el-button>
            <el-button type="primary" class="op-btn complete" :disabled="!canComplete" @click="openCompleteDialog">
              <el-icon><Finished /></el-icon> {{ $t('complete_the_settlement') }}
            </el-button>
            <el-button type="info" class="op-btn cancel" :disabled="rma.status === 'completed' || rma.status === 'rejected' || rma.status === 'cancelled'" @click="cancelRma">
              <el-icon><Warning /></el-icon> {{ $t('cancel_the_request') }}
            </el-button>
          </div>
        </el-card>

        <!-- Information Card -->
        <el-card class="info-card-premium" shadow="never" style="margin-top: 25px">
          <template #header>
            <div class="section-card-header">
              <span class="dot blue"></span>
              <h3>{{ $t('customer_and_invoice_details') }}</h3>
            </div>
          </template>

          <el-descriptions :column="1" border class="premium-descriptions">
            <el-descriptions-item :label="$t('customer')">
              <div class="customer-info-box">
                <span class="name">{{ rma.customer }}</span>
                <span class="phone" v-if="rma.customer_phone">{{ rma.customer_phone }}</span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('original_sales_invoice')">
              <span class="invoice-number">#{{ rma.order_number }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('settlement_type')">
              <el-tag :type="getReturnTypeClass(rma.return_type)" class="premium-tag">
                {{ getReturnTypeLabel(rma.return_type) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('return_reason')">
              {{ getReasonLabel(rma.reason) }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('submitted_on')">
              {{ formatDate(rma.created_at) }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('approved_compensation_value')" v-if="rma.status === 'completed'">
              <span class="final-refund-amount">{{ formatCurrency(rma.refund_amount) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('financial_settlement_method')" v-if="rma.status === 'completed'">
              <el-tag type="info" class="premium-tag">{{ getRefundMethodLabel(rma.refund_method) }}</el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- Credit notes raised when this return was settled: the document
             trail behind the money, rather than just a total on the request. -->
        <el-card class="details-section-card" shadow="never" v-if="creditNotes.length">
          <template #header>
            <div class="section-card-header">
              <span class="dot purple"></span>
              <h3>{{ $t('credit_notes') }}</h3>
            </div>
          </template>

          <div v-for="note in creditNotes" :key="note.id" class="credit-note-card">
            <div class="credit-note-head">
              <strong>{{ note.credit_note_number }}</strong>
              <el-tag :type="creditNoteTagType(note.status)" size="small">{{ note.status_text }}</el-tag>
            </div>
            <div class="credit-note-rows">
              <div class="credit-note-row">
                <span>{{ $t('value') }}</span>
                <strong>{{ formatCurrency(note.total) }}</strong>
              </div>
              <div class="credit-note-row" v-if="Number(note.applied_to_invoice) > 0">
                <span>{{ $t('deducted_from_invoice') }}</span>
                <strong>{{ formatCurrency(note.applied_to_invoice) }}</strong>
              </div>
              <div class="credit-note-row" v-if="Number(note.refunded_amount) > 0">
                <span>{{ $t('refunded_in_cash') }}</span>
                <strong>{{ formatCurrency(note.refunded_amount) }}</strong>
              </div>
              <div class="credit-note-row" v-if="Number(note.store_credit_amount) > 0">
                <span>{{ $t('credited_to_customer') }}</span>
                <strong>{{ formatCurrency(note.store_credit_amount) }}</strong>
              </div>
              <div class="credit-note-row open" v-if="Number(note.open_amount) > 0">
                <span>{{ $t('not_settled_yet') }}</span>
                <strong>{{ formatCurrency(note.open_amount) }}</strong>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Receive Items Dialog -->
    <el-dialog v-model="showReceiveDialog" :title="$t('receive_and_inspect_returns')" width="600px" class="premium-dialog">
      <p class="dialog-desc">{{ $t('record_inspected_quantities_hint') }}</p>
      <el-table :data="receiveForm.items" stripe class="mini-table">
        <el-table-column prop="product" :label="$t('product')" />
        <el-table-column prop="quantity_requested" :label="$t('requested_for_return')" width="130" align="center" />
        <el-table-column :label="$t('quantity_received')" width="150" align="center">
          <template #default="{ row }">
            <el-input-number v-model="row.quantity_received" :min="0" :max="row.quantity_requested" size="small" />
          </template>
        </el-table-column>
      </el-table>
      <template #footer>
        <el-button @click="showReceiveDialog = false">{{ $t('cancel') }}</el-button>
        <el-button type="primary" @click="submitReceive" :loading="receiveLoading">{{ $t('confirm_receipt') }}</el-button>
      </template>
    </el-dialog>

    <!-- Complete RMA Dialog -->
    <el-dialog v-model="showCompleteDialog" :title="$t('complete_and_settle_return')" width="550px" class="premium-dialog">
      <!-- Breakdown so the operator can see how the return splits before
           committing: cash back vs. credit consumed by a replacement. -->
      <div class="settlement-summary">
        <div class="settlement-row">
          <span>{{ $t('refundable_lines_value') }}</span>
          <strong>{{ formatCurrency(refundableTotal) }}</strong>
        </div>
        <div class="settlement-row" v-if="hasExchangeItems">
          <span>{{ $t('credit_against_exchange_order') }}</span>
          <strong class="exchange">{{ formatCurrency(exchangeCredit) }}</strong>
        </div>
        <p class="settlement-hint" v-if="hasExchangeItems">
          {{ $t('exchange_lines_not_refunded_notice') }}
        </p>
      </div>

      <el-form :model="completeForm" label-position="top">
        <el-form-item :label="$t('compensation_settlement_method')" prop="refund_method">
          <el-select v-model="completeForm.refund_method" :placeholder="$t('select_compensation_method')" class="premium-select-field">
            <el-option value="original" :label="$t('refund_to_original_account')" />
            <el-option value="store_credit" :label="$t('store_credit_to_customer_wallet')" />
            <el-option value="bank_transfer" :label="$t('custom_bank_transfer')" />
            <el-option value="check" :label="$t('paper_bank_cheque')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('refund_amount')" prop="refund_amount">
          <el-input-number v-model="completeForm.refund_amount" :min="0" :precision="2" class="premium-select-field" />
          <span class="input-helper-text">
            {{ $t('compensation_calculation_hint') }}
          </span>
        </el-form-item>

        <!-- What each method will actually record, since they behave differently. -->
        <el-alert
          :type="completeForm.refund_method === 'store_credit' ? 'info' : 'warning'"
          :closable="false"
          show-icon
          class="settlement-effect"
        >
          <template v-if="completeForm.refund_method === 'store_credit'">
            {{ $t('amount_deducted_from_balance_notice') }}
          </template>
          <template v-else>
            {{ $t('negative_payment_notice') }}
          </template>
        </el-alert>
        <el-form-item :label="$t('final_settlement_notes')" prop="admin_notes">
          <el-input v-model="completeForm.admin_notes" type="textarea" :rows="3" :placeholder="$t('settlement_notes_placeholder')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCompleteDialog = false">{{ $t('cancel') }}</el-button>
        <el-button type="primary" @click="submitComplete" :loading="completeLoading">{{ $t('confirm_and_close_request') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { formatMoney } from '@/utils/currency';
import { ref, onMounted, computed } from 'vue'
import { RefreshLeft, Back, Edit, Check, Close, Location, Finished, Warning, User, DocumentAdd, Select, Box, CircleCheck } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import rmaService from '@/services/rma'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const rmaId = computed(() => route.params.id ? parseInt(route.params.id) : null)
const goBack = () => {
  router.push('/admin/rma')
}

const loading = ref(false)
const rma = ref({
  id: null,
  rma_number: '',
  customer: '',
  customer_phone: '',
  order_number: '',
  order_id: null,
  return_type: 'refund',
  reason: 'defective',
  reason_description: '',
  status: 'pending',
  created_at: null,
  resolved_at: null,
  resolved_by: null,
  refund_amount: 0,
  refund_method: null,
  notes: '',
  items: []
})

const activities = ref([])

// Dialogue states
const showReceiveDialog = ref(false)
const receiveLoading = ref(false)
const receiveForm = ref({
  items: []
})

const showCompleteDialog = ref(false)
const completeLoading = ref(false)
const completeForm = ref({
  refund_amount: 0,
  refund_method: 'store_credit',
  admin_notes: ''
})

const receivedUnits = computed(() =>
  (rma.value.items || []).reduce((sum, item) => sum + (Number(item.quantity_received) || 0), 0)
)

// Mirrors RmaSettlementService: only `refund` and `discard` lines are paid out.
// `exchange` lines are compensated with replacement goods and `repair` lines
// get neither, so paying them in cash as well would compensate twice.
const REFUNDABLE_RESOLUTIONS = ['refund', 'discard']

const sumRefund = (predicate) =>
  (rma.value.items || [])
    .filter(predicate)
    .reduce((sum, item) => sum + (parseFloat(item.refund_amount) || 0), 0)

const creditNotes = computed(() => rma.value.credit_notes || [])

const creditNoteTagType = (status) => ({
  issued: 'warning',
  partially_applied: 'primary',
  applied: 'success',
  cancelled: 'info'
}[status] || 'info')

const refundableTotal = computed(() => sumRefund(i => REFUNDABLE_RESOLUTIONS.includes(i.resolution)))
const exchangeCredit = computed(() => sumRefund(i => i.resolution === 'exchange'))
const hasExchangeItems = computed(() => (rma.value.items || []).some(i => i.resolution === 'exchange'))

// Mirrors RmaRequest::canReceive() / canComplete() so the UI never offers an
// action the API will reject.
const canReceive = computed(() => ['approved', 'received'].includes(rma.value.status))
const canComplete = computed(() => ['approved', 'received'].includes(rma.value.status))

const currentStep = computed(() => {
  const steps = {
    pending: 1,
    approved: 2,
    received: 3,
    completed: 4
  }
  // `received` is a real status now, so the old fallback that inferred receipt
  // from item quantities is no longer needed. It is kept only for requests
  // received before the status existed.
  if (rma.value.status === 'approved' && (rma.value.items || []).some(i => i.quantity_received > 0)) {
    return 3
  }
  return steps[rma.value.status] || 1
})

const loadRma = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequest(rmaId.value)
    const data = response.data.data || response.data
    // The mapping is an explicit whitelist, so anything the template needs has
    // to be listed here. It previously dropped the lifecycle timestamps (the
    // stepper read rma.approved_at / received_at / completed_at and therefore
    // sat on "awaiting approval" forever) and each line's refund_amount.
    rma.value = {
      id: data.id,
      rma_number: data.rma_number,
      customer: data.customer?.name || 'N/A',
      customer_phone: data.customer?.phone || '',
      order_number: data.invoice?.invoice_number || 'N/A',
      order_id: data.invoice_id,
      return_type: data.type || 'refund',
      reason: data.reason || 'defective',
      reason_description: data.reason_description || 'N/A',
      status: data.status || 'pending',
      created_at: data.requested_at || data.created_at,

      // Lifecycle timestamps driving the stepper.
      approved_at: data.approved_at || null,
      received_at: data.received_at || null,
      completed_at: data.completed_at || null,
      resolved_at: data.approved_at || null,
      resolved_by: data.approver?.name || null,
      received_by: data.receiver?.name || null,
      completed_by: data.completer?.name || null,

      refund_amount: parseFloat(data.refund_amount) || 0,
      refund_method: data.refund_method || null,
      admin_notes: data.admin_notes || null,
      notes: data.admin_notes || null,

      // Settlement documents produced when the return was completed.
      credit_notes: data.credit_notes || [],

      items: data.items ? data.items.map(item => ({
        id: item.id,
        product: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
        quantity_requested: item.quantity_requested,
        quantity_received: item.quantity_received || 0,
        reason: item.notes || 'N/A',
        condition: item.condition || 'new',
        resolution: item.resolution || 'refund',
        // Server-calculated credit for this line; the settlement breakdown and
        // the completion dialog's default both read it.
        refund_amount: parseFloat(item.refund_amount) || 0,
        unit_price: parseFloat(item.invoice_item?.unit_price) || 0,
        exchange_product: item.exchange_product ? (item.exchange_product.name_ar || item.exchange_product.name) : null
      })) : []
    }
    await loadActivities()
  } catch (error) {
    console.error('Failed to load RMA details:', error)
    ElMessage.error(t('failed_to_load_return_details'))
    goBack()
  } finally {
    loading.value = false
  }
}

const loadActivities = async () => {
  try {
    const response = await rmaService.getActivity(rmaId.value)
    activities.value = response.data.data || []
  } catch (error) {
    console.error('Failed to load activities:', error)
    activities.value = []
  }
}

const approveRma = async () => {
  try {
    const { value } = await ElMessageBox.prompt(t('confirm_approve_return_with_notes'), t('approve_the_request'), {
      confirmButtonText: t('ok_agreed'),
      cancelButtonText: t('cancel'),
      inputPlaceholder: t('enter_optional_notes')
    })
    await rmaService.approveRma(rma.value.id, { admin_notes: value })
    ElMessage.success(t('return_approved'))
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('approval_failed'))
    }
  }
}

const rejectRma = async () => {
  try {
    const { value } = await ElMessageBox.prompt(t('enter_rejection_reason'), t('reject_request'), {
      type: 'warning',
      confirmButtonText: t('reject_request'),
      cancelButtonText: t('cancel'),
      inputPattern: /.+/,
      inputErrorMessage: t('rejection_reason_required')
    })
    await rmaService.rejectRma(rma.value.id, { reason: value })
    ElMessage.success(t('return_rejected'))
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('rejection_failed'))
    }
  }
}

const cancelRma = async () => {
  try {
    await ElMessageBox.confirm(t('confirm_cancel_return'), t('cancel_the_request'), {
      type: 'warning',
      confirmButtonText: t('cancel_the_request'),
      cancelButtonText: t('undo')
    })
    await rmaService.cancelRma(rma.value.id)
    ElMessage.success(t('return_cancelled'))
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('cancellation_failed'))
    }
  }
}

const openReceiveDialog = () => {
  receiveForm.value.items = rma.value.items.map(item => ({
    rma_item_id: item.id,
    product: item.product,
    quantity_requested: item.quantity_requested,
    quantity_received: item.quantity_received || item.quantity_requested
  }))
  showReceiveDialog.value = true
}

const submitReceive = async () => {
  receiveLoading.value = true
  try {
    const data = {
      items: receiveForm.value.items.map(item => ({
        rma_item_id: item.rma_item_id,
        quantity_received: item.quantity_received
      }))
    }
    // Goes through the service like every other RMA action, instead of a raw
    // api.post that bypassed it.
    await rmaService.receiveRma(rma.value.id, data)
    ElMessage.success(t('receipt_recorded_stock_updated'))
    showReceiveDialog.value = false
    loadRma()
    loadActivities()
  } catch (error) {
    console.error('Failed to receive items:', error)
    ElMessage.error(error.response?.data?.message || t('failed_to_record_receipt'))
  } finally {
    receiveLoading.value = false
  }
}

const openCompleteDialog = () => {
  // Defaults to the refundable total (see refundableTotal): the previous
  // estimate re-derived the condition multiplier from `item.unit_price`, a
  // field RMA items do not carry, so it evaluated to NaN and silently fell back
  // to a figure that also paid out the exchange lines.
  completeForm.value = {
    refund_amount: Number(refundableTotal.value.toFixed(2)),
    refund_method: rma.value.refund_method || 'store_credit',
    admin_notes: rma.value.admin_notes || ''
  }
  showCompleteDialog.value = true
}

const submitComplete = async () => {
  completeLoading.value = true
  try {
    const response = await rmaService.completeRma(rma.value.id, completeForm.value)
    const settlement = response.data?.settlement

    // Report what the settlement actually produced rather than a generic
    // "done" — the operator needs the refund and replacement references.
    const notes = []
    if (settlement?.credit_note) {
      notes.push(t('credit_note_note', { number: settlement.credit_note.credit_note_number }))
    }
    if (settlement?.applied_to_invoice > 0) {
      notes.push(t('deducted_from_invoice_note', { amount: formatCurrency(settlement.applied_to_invoice) }))
    }
    if (settlement?.refund_payment) {
      notes.push(t('refund_payment_note', { number: settlement.refund_payment.payment_number }))
    }
    if (settlement?.store_credit_applied > 0) {
      notes.push(t('customer_credit_note', { amount: formatCurrency(settlement.store_credit_applied) }))
    }
    if (settlement?.replacement_order) {
      notes.push(t('replacement_order_note', { number: settlement.replacement_order.order_number }))
    }
    if (settlement?.unused_exchange_credit > 0) {
      notes.push(t('unused_credit_note', { amount: formatCurrency(settlement.unused_exchange_credit) }))
    }

    ElMessage.success({
      message: notes.length
        ? t('settled_summary', { details: notes.join(' • ') })
        : t('return_settled_and_completed'),
      duration: 5000
    })

    showCompleteDialog.value = false
    loadRma()
    loadActivities()
  } catch (error) {
    console.error('Failed to complete RMA:', error)
    ElMessage.error(error.response?.data?.message || t('failed_to_complete_settlement'))
  } finally {
    completeLoading.value = false
  }
}

const getStatusLabel = (status) => {
  const labels = {
    pending: t('awaiting_approval'),
    approved: t('approved'),
    received: t('goods_received'),
    rejected: t('sales_status_rejected'),
    completed: t('sales_status_completed'),
    cancelled: t('sales_status_cancelled')
  }
  return labels[status] || status
}

const getReturnTypeLabel = (type) => {
  const labels = {
    refund: t('cash_refund'),
    exchange: t('product_exchange'),
    store_credit: t('store_credit')
  }
  return labels[type] || type
}

const getReturnTypeClass = (type) => {
  const classes = {
    refund: 'success',
    exchange: 'warning',
    store_credit: 'danger'
  }
  return classes[type] || 'info'
}

const getReasonLabel = (reason) => {
  const labels = {
    defective: t('reason_defective'),
    damaged: t('reason_damaged'),
    wrong_item: t('reason_wrong_item'),
    not_as_described: t('reason_not_as_described'),
    changed_mind: t('reason_changed_mind'),
    other: t('reason_other')
  }
  return labels[reason] || reason
}

const getConditionLabel = (condition) => {
  const labels = {
    new: t('condition_new'),
    used: t('condition_used'),
    damaged: t('condition_damaged'),
    missing: t('condition_missing')
  }
  return labels[condition] || condition
}

const getConditionTagType = (condition) => {
  const types = {
    new: 'success',
    used: 'warning',
    damaged: 'danger',
    missing: 'info'
  }
  return types[condition] || 'info'
}

const getResolutionLabel = (resolution) => {
  const labels = {
    refund: t('refund'),
    exchange: t('exchange'),
    repair: t('repair_and_maintenance'),
    discard: t('scrap')
  }
  return labels[resolution] || resolution
}

const getResolutionTagType = (resolution) => {
  const types = {
    refund: 'success',
    exchange: 'warning',
    repair: 'primary',
    discard: 'danger'
  }
  return types[resolution] || 'info'
}

const getRefundMethodLabel = (method) => {
  const labels = {
    original: t('refund_to_original_account_short'),
    store_credit: t('store_credit_to_wallet'),
    bank_transfer: t('custom_bank_transfer'),
    check: t('bank_cheque')
  }
  return labels[method] || method || '-'
}

const formatCurrency = (val) => formatMoney(val)

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getTimelineType = (action) => {
  const types = {
    'تم إنشاء': 'primary',
    'تمت الموافقة': 'success',
    'تم رفض': 'danger',
    'تم استلام': 'warning',
    'تم إكمال': 'success',
    'تم إلغاء': 'info'
  }
  for (const [key, val] of Object.entries(types)) {
    if (action.includes(key)) return val
  }
  return 'primary'
}

onMounted(() => {
  loadRma()
})
</script>

<style scoped>
/* Credit notes panel */
.credit-note-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 16px;
}

.credit-note-card + .credit-note-card {
  margin-top: 12px;
}

.credit-note-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.credit-note-head strong {
  font-size: 1rem;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.credit-note-rows {
  display: grid;
  gap: 6px;
}

.credit-note-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.88rem;
  color: #475569;
}

.credit-note-row strong {
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.credit-note-row.open strong {
  color: #b45309;
}

.section-card-header .dot.purple {
  background: #8b5cf6;
}

/* Settlement breakdown inside the completion dialog */
.settlement-summary {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
}

.settlement-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
  color: #475569;
}

.settlement-row + .settlement-row {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #cbd5e1;
}

.settlement-row strong {
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.settlement-row strong.exchange {
  color: #6d28d9;
}

.settlement-hint {
  margin: 10px 0 0;
  font-size: 0.8rem;
  line-height: 1.6;
  color: #64748b;
}

.settlement-effect {
  margin-bottom: 16px;
}

@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap');

.rma-details-container {
  padding: 30px;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  min-height: 100vh;
  font-family: 'Cairo', 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  direction: rtl;
}

/* Premium Page Header */
.page-header-premium {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  padding: 24px 32px;
  border-radius: 20px;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.header-info {
  display: flex;
  align-items: center;
  gap: 20px;
}

.header-icon-box {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  color: #2563eb;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  box-shadow: inset 0 2px 4px rgba(37, 99, 235, 0.06), 0 4px 12px rgba(37, 99, 235, 0.08);
}

.header-title {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px 0;
  letter-spacing: -0.5px;
}

.header-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  font-weight: 500;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-back-premium {
  border: 1px solid #cbd5e1;
  color: #475569;
  font-weight: 700;
  border-radius: 12px;
  padding: 12px 24px;
  font-family: 'Cairo', sans-serif;
  transition: all 0.2s;
  height: 44px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-back-premium:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

.btn-edit-premium {
  padding: 12px 24px;
  border-radius: 12px;
  font-weight: 700;
  border: 1px solid #fde68a;
  color: #d97706;
  background: #fffbeb;
  font-family: 'Cairo', sans-serif;
  transition: all 0.2s;
  height: 44px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-edit-premium:hover {
  background: #fef3c7;
  color: #b45309;
  border-color: #fcd34d;
}

/* Stepper cards */
.lifecycle-card {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #ffffff;
  padding: 24px;
  margin-bottom: 25px;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
}

.premium-steps :deep(.el-step__title) {
  font-family: 'Cairo', sans-serif;
  font-weight: 800;
  font-size: 14px;
}

.premium-steps :deep(.el-step__description) {
  font-family: 'Cairo', sans-serif;
  font-size: 11px;
  font-weight: 600;
}

.step-title {
  font-weight: 700;
  color: #1e293b;
}

.step-desc {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
  display: block;
  margin-top: 4px;
}

.premium-steps :deep(.el-step__icon) {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border: 2px solid #bfdbfe;
  color: #2563eb;
  font-size: 18px;
  width: 40px;
  height: 40px;
}

.premium-steps :deep(.el-step__icon.is-process) {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border: 2px solid #34d399;
  color: #ffffff;
}

.premium-steps :deep(.el-step__icon.is-finish) {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border: 2px solid #34d399;
  color: #ffffff;
}

/* Alert banners */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px 28px;
  border-radius: 16px;
  margin-bottom: 25px;
  border: 1px solid;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.alert-banner.rejected {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border-color: #fca5a5;
  color: #991b1b;
}

.alert-banner.cancelled {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-color: #cbd5e1;
  color: #475569;
}

.alert-banner .el-icon {
  font-size: 36px;
}

.alert-banner h4 {
  margin: 0 0 6px 0;
  font-weight: 800;
  font-size: 16px;
}

.alert-banner p {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  opacity: 0.9;
}

/* Details Section cards */
.details-section-card {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #ffffff;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
  padding: 24px;
}

.details-section-card :deep(.el-card__header) {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.section-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-card-header .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #2563eb;
  box-shadow: 0 0 8px rgba(37, 99, 235, 0.6);
}

.section-card-header .dot.orange { background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.6); }
.section-card-header .dot.blue { background: #3b82f6; box-shadow: 0 0 8px rgba(59, 130, 246, 0.6); }

.section-card-header h3 {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.items-table-premium {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}

.items-table-premium :deep(.el-table__header-wrapper) th {
  font-weight: 800 !important;
  color: #475569 !important;
  font-size: 13px !important;
  background-color: #f8fafc !important;
  padding: 14px 0 !important;
}

.qty-warning {
  color: #b45309;
  font-weight: 700;
  background: #fffbeb;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid #fde68a;
  font-size: 12px;
}

.qty-success {
  color: #15803d;
  font-weight: 700;
  background: #f0fdf4;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid #bbf7d0;
  font-size: 12px;
}

.premium-tag {
  border-radius: 8px;
  font-weight: 700;
  font-size: 11px;
  padding: 6px 10px;
  border: 1px solid transparent;
}

.exchange-product-text {
  font-weight: 700;
  color: #2563eb;
  background: #eff6ff;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  border: 1px dashed #bfdbfe;
}

.text-placeholder {
  color: #cbd5e1;
  font-weight: 500;
}

/* Operations Panel styling */
.operations-card-premium {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #ffffff;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
  padding: 24px;
}

.operations-card-premium :deep(.el-card__header) {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.operations-buttons-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 15px;
}

.op-btn {
  width: 100%;
  justify-content: flex-start;
  padding: 14px 18px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 13px;
  margin-left: 0 !important;
  font-family: 'Cairo', sans-serif;
  height: 44px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.op-btn:hover:not(:disabled) {
  transform: translateY(-2px);
}

.op-btn.approve {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}
.op-btn.approve:hover:not(:disabled) {
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

.op-btn.reject {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}
.op-btn.reject:hover:not(:disabled) {
  box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
}

.op-btn.receive {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
}
.op-btn.receive:hover:not(:disabled) {
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
}

.op-btn.complete {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}
.op-btn.complete:hover:not(:disabled) {
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
}

.op-btn.cancel {
  border: 1px solid #cbd5e1;
  color: #475569;
  background: #ffffff;
}
.op-btn.cancel:hover:not(:disabled) {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

/* Info Card styling */
.info-card-premium {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #ffffff;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
  padding: 24px;
}

.info-card-premium :deep(.el-card__header) {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.customer-info-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.customer-info-box .name {
  font-weight: 700;
  color: #1e293b;
}

.customer-info-box .phone {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.invoice-number {
  font-weight: 700;
  color: #2563eb;
}

.final-refund-amount {
  font-weight: 800;
  color: #059669;
  font-size: 16px;
}

.premium-descriptions {
  --el-descriptions-table-border: 1px solid #f1f5f9;
}

.premium-descriptions :deep(.el-descriptions__label) {
  font-weight: 800;
  color: #475569;
  background-color: #f8fafc;
  font-size: 13px;
  padding: 14px 20px;
}

.premium-descriptions :deep(.el-descriptions__content) {
  color: #0f172a;
  font-weight: 600;
  font-size: 13px;
  padding: 14px 20px;
}

/* Dialog styles */
.premium-dialog {
  border-radius: 20px;
}

.premium-dialog :deep(.el-dialog__header) {
  padding: 24px 24px 16px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.premium-dialog :deep(.el-dialog__title) {
  font-weight: 800;
  font-family: 'Cairo', sans-serif;
  color: #0f172a;
}

.premium-dialog :deep(.el-dialog__body) {
  padding: 24px;
}

.premium-dialog :deep(.el-dialog__footer) {
  padding: 16px 24px 24px 24px;
  border-top: 1px solid #f1f5f9;
}

.dialog-desc {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 20px;
  line-height: 1.5;
  font-weight: 500;
}

.mini-table {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.mini-table :deep(.el-table__header-wrapper) th {
  font-weight: 800 !important;
  color: #475569 !important;
  background-color: #f8fafc !important;
}

.premium-select-field {
  width: 100%;
}

.premium-select-field :deep(.el-input__wrapper),
.premium-select-field :deep(.el-select__wrapper) {
  border-radius: 10px;
  padding: 6px 12px;
}

.input-helper-text {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 6px;
  display: block;
  line-height: 1.4;
  font-weight: 500;
}

/* Timeline Custom Styles */
.premium-timeline {
  padding: 10px 12px;
}

.timeline-content-card {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 14px;
  padding: 16px 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.action-badge {
  font-weight: 800;
  color: #0f172a;
  font-size: 14px;
}

.user-badge {
  font-size: 12px;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.user-badge .el-icon {
  color: #2563eb;
}

.timeline-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
  line-height: 1.6;
  font-weight: 500;
}
</style>
