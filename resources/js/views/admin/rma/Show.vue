<template>
    <div class="rma-details" v-loading="loading">
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <header class="builder-header">
            <div class="header-identity">
                <span class="eyebrow">{{ t('rma') }}</span>
                <h1>{{ t('return_request_details_title', { number: rma.rma_number }) }}</h1>
                <p>{{ t('rma_show_subtitle') }}</p>
            </div>

            <div class="header-actions">
                <el-button text @click="goBack">
                    <el-icon><ArrowRight /></el-icon>
                    {{ t('back') }}
                </el-button>
                <el-button
                    :disabled="rma.status !== 'pending'"
                    @click="router.push(`/admin/rma/${rma.id}/edit`)"
                >
                    <el-icon><Edit /></el-icon>
                    {{ t('edit') }}
                </el-button>
            </div>
        </header>

        <!-- ── Lifecycle stepper ──────────────────────────────────────────── -->
        <div class="panel-card lifecycle-card" v-if="!['rejected', 'cancelled'].includes(rma.status)">
            <el-steps :active="currentStep" align-center finish-status="success" process-status="success">
                <el-step>
                    <template #icon><el-icon><DocumentAdd /></el-icon></template>
                    <template #title><span class="step-title">{{ t('request_submitted') }}</span></template>
                    <template #description><span class="step-desc">{{ formatDate(rma.created_at) }}</span></template>
                </el-step>
                <el-step>
                    <template #icon><el-icon><Select /></el-icon></template>
                    <template #title><span class="step-title">{{ t('approved') }}</span></template>
                    <template #description>
                        <span class="step-desc" v-if="rma.approved_at">{{ formatDate(rma.approved_at) }}</span>
                        <span class="step-desc" v-else>{{ t('awaiting_approval_state') }}</span>
                    </template>
                </el-step>
                <el-step>
                    <template #icon><el-icon><Box /></el-icon></template>
                    <template #title><span class="step-title">{{ t('receive_products') }}</span></template>
                    <template #description>
                        <span class="step-desc" v-if="rma.received_at">{{ formatDate(rma.received_at) }}</span>
                        <span class="step-desc" v-else-if="receivedUnits > 0">{{ t('units_received_count', { count: receivedUnits }) }}</span>
                        <span class="step-desc" v-else>{{ t('awaiting_receipt') }}</span>
                    </template>
                </el-step>
                <el-step>
                    <template #icon><el-icon><CircleCheck /></el-icon></template>
                    <template #title><span class="step-title">{{ t('settlement_completed') }}</span></template>
                    <template #description>
                        <span class="step-desc" v-if="rma.completed_at">{{ formatDate(rma.completed_at) }}</span>
                        <span class="step-desc" v-else>{{ t('awaiting_completion') }}</span>
                    </template>
                </el-step>
            </el-steps>
        </div>

        <!-- ── Reject/cancel banner ───────────────────────────────────────── -->
        <div class="alert-banner" :class="rma.status" v-else>
            <el-icon><Warning /></el-icon>
            <div>
                <h4>{{ rma.status === 'rejected' ? t('return_request_rejected_heading') : t('return_request_cancelled_heading') }}</h4>
                <p v-if="rma.status === 'rejected'">{{ t('rejection_reason_label') }}: {{ rma.notes || t('no_rejection_reason_given') }}</p>
                <p v-else>{{ t('request_cancelled_notice') }}</p>
            </div>
        </div>

        <div class="builder-grid">
            <!-- ── Work area ──────────────────────────────────────────────── -->
            <section class="work-area">
                <!-- Returned items -->
                <div class="panel-card">
                    <h2 class="panel-title">{{ t('products_in_return_request') }}</h2>

                    <div class="return-lines">
                        <article v-for="item in rma.items" :key="item.id" class="return-line">
                            <div class="line-identity">
                                <span class="line-name">{{ item.product }}</span>
                                <span class="line-meta">
                                    {{ t('quantity_received') }}:
                                    <b :class="{ 'qty-warning': item.quantity_received < item.quantity_requested, 'qty-success': item.quantity_received === item.quantity_requested }">
                                        {{ item.quantity_received }} / {{ item.quantity_requested }}
                                    </b>
                                </span>
                            </div>

                            <div class="line-tags">
                                <el-tag :type="getConditionTagType(item.condition)" size="small">{{ getConditionLabel(item.condition) }}</el-tag>
                                <el-tag :type="getResolutionTagType(item.resolution)" size="small">{{ getResolutionLabel(item.resolution) }}</el-tag>
                                <span class="exchange-chip" v-if="item.resolution === 'exchange'">
                                    <el-icon><Sort /></el-icon> {{ item.exchange_product || t('not_chosen_yet') }}
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Activity log -->
                <div class="panel-card">
                    <h2 class="panel-title">{{ t('activity_and_tracking_log') }}</h2>

                    <el-timeline class="activity-timeline">
                        <el-timeline-item
                            v-for="activity in activities"
                            :key="activity.id"
                            :timestamp="formatDate(activity.created_at)"
                            placement="top"
                            :type="getTimelineType(activity.action)"
                        >
                            <div class="activity-card">
                                <div class="activity-head">
                                    <span class="activity-action">{{ activity.action }}</span>
                                    <span class="activity-user"><el-icon><User /></el-icon> {{ activity.user }}</span>
                                </div>
                                <p class="activity-desc">{{ activity.description }}</p>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
            </section>

            <!-- ── Summary rail ───────────────────────────────────────────── -->
            <aside class="summary-rail">
                <div class="rail-card">
                    <h3 class="rail-title">{{ t('operations_and_actions') }}</h3>

                    <div class="op-buttons">
                        <el-button type="success" class="op-btn" :disabled="rma.status !== 'pending'" @click="approveRma">
                            <el-icon><Check /></el-icon> {{ t('approve_the_request') }}
                        </el-button>
                        <el-button type="danger" class="op-btn" :disabled="rma.status !== 'pending'" @click="rejectRma">
                            <el-icon><Close /></el-icon> {{ t('reject_the_return') }}
                        </el-button>
                        <!-- Receiving stays available while `received` so a
                             miscounted receipt can be corrected; the server
                             books only the difference. -->
                        <el-button type="warning" class="op-btn" :disabled="!canReceive" @click="openReceiveDialog">
                            <el-icon><Location /></el-icon>
                            {{ rma.status === 'received' ? t('edit_received_quantities') : t('receive_and_inspect_products') }}
                        </el-button>
                        <el-button type="primary" class="op-btn" :disabled="!canComplete" @click="openCompleteDialog">
                            <el-icon><Finished /></el-icon> {{ t('complete_the_settlement') }}
                        </el-button>
                        <el-button class="op-btn" :disabled="['completed', 'rejected', 'cancelled'].includes(rma.status)" @click="cancelRma">
                            <el-icon><Warning /></el-icon> {{ t('cancel_the_request') }}
                        </el-button>
                    </div>
                </div>

                <div class="rail-card">
                    <h3 class="rail-title">{{ t('customer_and_invoice_details') }}</h3>

                    <dl class="fact-list">
                        <div class="fact-row">
                            <dt>{{ t('customer') }}</dt>
                            <dd>
                                <span class="fact-primary">{{ rma.customer }}</span>
                                <span class="fact-secondary" v-if="rma.customer_phone">{{ rma.customer_phone }}</span>
                            </dd>
                        </div>
                        <div class="fact-row">
                            <dt>{{ t('original_sales_invoice') }}</dt>
                            <dd class="fact-accent">#{{ rma.order_number }}</dd>
                        </div>
                        <div class="fact-row">
                            <dt>{{ t('settlement_type') }}</dt>
                            <dd><el-tag :type="getReturnTypeClass(rma.return_type)" size="small">{{ getReturnTypeLabel(rma.return_type) }}</el-tag></dd>
                        </div>
                        <div class="fact-row">
                            <dt>{{ t('return_reason') }}</dt>
                            <dd>{{ getReasonLabel(rma.reason) }}</dd>
                        </div>
                        <div class="fact-row">
                            <dt>{{ t('submitted_on') }}</dt>
                            <dd>{{ formatDate(rma.created_at) }}</dd>
                        </div>
                        <div class="fact-row" v-if="rma.status === 'completed'">
                            <dt>{{ t('approved_compensation_value') }}</dt>
                            <dd class="fact-money">{{ formatCurrency(rma.refund_amount) }}</dd>
                        </div>
                        <div class="fact-row" v-if="rma.status === 'completed'">
                            <dt>{{ t('financial_settlement_method') }}</dt>
                            <dd><el-tag type="info" size="small">{{ getRefundMethodLabel(rma.refund_method) }}</el-tag></dd>
                        </div>
                    </dl>
                </div>

                <!-- Credit notes raised when this return was settled: the
                     document trail behind the money, rather than just a
                     total on the request. -->
                <div class="rail-card" v-if="creditNotes.length">
                    <h3 class="rail-title">{{ t('credit_notes') }}</h3>

                    <div class="credit-note" v-for="note in creditNotes" :key="note.id">
                        <div class="credit-note-head">
                            <strong>{{ note.credit_note_number }}</strong>
                            <el-tag :type="creditNoteTagType(note.status)" size="small">{{ note.status_text }}</el-tag>
                        </div>
                        <dl class="fact-list compact">
                            <div class="fact-row">
                                <dt>{{ t('value') }}</dt>
                                <dd class="fact-money">{{ formatCurrency(note.total) }}</dd>
                            </div>
                            <div class="fact-row" v-if="Number(note.applied_to_invoice) > 0">
                                <dt>{{ t('deducted_from_invoice') }}</dt>
                                <dd class="fact-money">{{ formatCurrency(note.applied_to_invoice) }}</dd>
                            </div>
                            <div class="fact-row" v-if="Number(note.refunded_amount) > 0">
                                <dt>{{ t('refunded_in_cash') }}</dt>
                                <dd class="fact-money">{{ formatCurrency(note.refunded_amount) }}</dd>
                            </div>
                            <div class="fact-row" v-if="Number(note.store_credit_amount) > 0">
                                <dt>{{ t('credited_to_customer') }}</dt>
                                <dd class="fact-money">{{ formatCurrency(note.store_credit_amount) }}</dd>
                            </div>
                            <div class="fact-row" v-if="Number(note.open_amount) > 0">
                                <dt>{{ t('not_settled_yet') }}</dt>
                                <dd class="fact-money open">{{ formatCurrency(note.open_amount) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ── Receive items dialog ───────────────────────────────────────── -->
        <el-dialog v-model="showReceiveDialog" :title="t('receive_and_inspect_returns')" class="rma-dialog">
            <p class="dialog-desc">{{ t('record_inspected_quantities_hint') }}</p>

            <div class="dialog-lines">
                <div class="dialog-line" v-for="item in receiveForm.items" :key="item.rma_item_id">
                    <span class="dialog-line-name">{{ item.product }}</span>
                    <div class="dialog-line-fields">
                        <span class="dialog-line-requested">{{ t('requested_for_return') }}: {{ item.quantity_requested }}</span>
                        <el-input-number v-model="item.quantity_received" :min="0" :max="item.quantity_requested" size="small" />
                    </div>
                </div>
            </div>

            <template #footer>
                <el-button @click="showReceiveDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="receiveLoading" @click="submitReceive">{{ t('confirm_receipt') }}</el-button>
            </template>
        </el-dialog>

        <!-- ── Complete RMA dialog ────────────────────────────────────────── -->
        <el-dialog v-model="showCompleteDialog" :title="t('complete_and_settle_return')" class="rma-dialog">
            <!-- Breakdown so the operator can see how the return splits
                 before committing: cash back vs. credit consumed by a
                 replacement. -->
            <div class="settlement-summary">
                <div class="settlement-row">
                    <span>{{ t('refundable_lines_value') }}</span>
                    <strong>{{ formatCurrency(refundableTotal) }}</strong>
                </div>
                <div class="settlement-row" v-if="hasExchangeItems">
                    <span>{{ t('credit_against_exchange_order') }}</span>
                    <strong class="exchange">{{ formatCurrency(exchangeCredit) }}</strong>
                </div>
                <p class="settlement-hint" v-if="hasExchangeItems">{{ t('exchange_lines_not_refunded_notice') }}</p>
            </div>

            <el-form :model="completeForm" label-position="top">
                <el-form-item :label="t('compensation_settlement_method')" prop="refund_method">
                    <el-select v-model="completeForm.refund_method" :placeholder="t('select_compensation_method')" class="full-width">
                        <el-option value="original" :label="t('refund_to_original_account')" />
                        <el-option value="store_credit" :label="t('store_credit_to_customer_wallet')" />
                        <el-option value="bank_transfer" :label="t('custom_bank_transfer')" />
                        <el-option value="check" :label="t('paper_bank_cheque')" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('refund_amount')" prop="refund_amount">
                    <el-input-number v-model="completeForm.refund_amount" :min="0" :precision="2" class="full-width" />
                    <span class="helper-text">{{ t('compensation_calculation_hint') }}</span>
                </el-form-item>

                <!-- What each method will actually record, since they behave differently. -->
                <el-alert
                    :type="completeForm.refund_method === 'store_credit' ? 'info' : 'warning'"
                    :closable="false"
                    show-icon
                    class="settlement-effect"
                >
                    <template v-if="completeForm.refund_method === 'store_credit'">{{ t('amount_deducted_from_balance_notice') }}</template>
                    <template v-else>{{ t('negative_payment_notice') }}</template>
                </el-alert>

                <el-form-item :label="t('final_settlement_notes')" prop="admin_notes">
                    <el-input v-model="completeForm.admin_notes" type="textarea" :rows="3" :placeholder="t('settlement_notes_placeholder')" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="showCompleteDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="completeLoading" @click="submitComplete">{{ t('confirm_and_close_request') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { formatMoney } from '@/utils/currency';
import { ref, onMounted, computed } from 'vue'
import { ArrowRight, Edit, Check, Close, Location, Finished, Warning, User, DocumentAdd, Select, Box, CircleCheck, Sort } from '@element-plus/icons-vue'
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
.rma-details {
    --surface: #ffffff;
    --ground: #f8fafc;
    --line: #e2e8f0;
    --ink: #0f172a;
    --ink-soft: #334155;
    --ink-mute: #64748b;
    --primary: #2563eb;
    --primary-soft: #eff6ff;
    --ok: #16a34a;
    --ok-soft: #f0fdf4;
    --warn: #d97706;
    --warn-soft: #fffbeb;
    --bad: #dc2626;
    --bad-soft: #fef2f2;

    font-family: 'Cairo', sans-serif;
    color: var(--ink);
    padding-bottom: 2rem;
}

/* ── Header ─────────────────────────────────────────────────────────── */
.builder-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--line);
}

.eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: var(--primary);
    text-transform: uppercase;
}

.header-identity h1 {
    margin: 0.35rem 0 0;
    font-size: clamp(1.35rem, 3vw, 2rem);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.header-identity p {
    margin: 0.3rem 0 0;
    color: var(--ink-mute);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* ── Shared panel chrome (mirrors the RMA create page) ─────────────── */
.panel-card,
.rail-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
}

.panel-title,
.rail-title {
    margin: 0 0 0.85rem;
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--ink);
}

.full-width { width: 100%; }

.helper-text {
    font-size: 0.78rem;
    color: var(--ink-mute);
    margin-top: 0.4rem;
    display: block;
    line-height: 1.5;
}

/* ── Lifecycle stepper ──────────────────────────────────────────────── */
.lifecycle-card { margin-bottom: 1.5rem; }

.step-title { font-weight: 700; color: var(--ink-soft); }
.step-desc { font-size: 0.76rem; color: var(--ink-mute); display: block; margin-top: 0.2rem; }

:deep(.el-step__icon) {
    background: var(--primary-soft);
    border: 2px solid #bfdbfe;
    color: var(--primary);
}

:deep(.el-step__icon.is-process),
:deep(.el-step__icon.is-finish) {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #34d399;
    color: #fff;
}

/* ── Alert banner ───────────────────────────────────────────────────── */
.alert-banner {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    padding: 1.1rem 1.4rem;
    border-radius: 14px;
    margin-bottom: 1.5rem;
    border: 1px solid;
}

.alert-banner .el-icon { font-size: 1.9rem; flex: none; }
.alert-banner h4 { margin: 0 0 0.3rem; font-weight: 800; font-size: 1rem; }
.alert-banner p { margin: 0; font-size: 0.85rem; font-weight: 600; opacity: 0.9; }

.alert-banner.rejected { background: var(--bad-soft); border-color: #fca5a5; color: #991b1b; }
.alert-banner.cancelled { background: var(--ground); border-color: var(--line); color: var(--ink-soft); }

/* ── Grid ───────────────────────────────────────────────────────────── */
.builder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 380px;
    gap: 1.5rem;
    align-items: start;
}

.work-area {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
}

.summary-rail {
    position: sticky;
    top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ── Return lines ───────────────────────────────────────────────────── */
.return-lines { display: flex; flex-direction: column; gap: 0.65rem; }

.return-line {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.line-identity { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.line-name { font-weight: 700; }
.line-meta { font-size: 0.8rem; color: var(--ink-mute); }
.line-meta b { font-variant-numeric: tabular-nums; }
.line-meta b.qty-warning { color: var(--warn); }
.line-meta b.qty-success { color: var(--ok); }

.line-tags { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

.exchange-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-soft);
    border: 1px dashed #bfdbfe;
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
}

/* ── Activity timeline ──────────────────────────────────────────────── */
.activity-timeline { padding: 0.5rem 0.25rem; }

.activity-card {
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 0.85rem 1rem;
}

.activity-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.activity-action { font-weight: 800; font-size: 0.92rem; }
.activity-user {
    font-size: 0.78rem;
    color: var(--ink-mute);
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
}
.activity-user .el-icon { color: var(--primary); }
.activity-desc { margin: 0; font-size: 0.85rem; color: var(--ink-soft); line-height: 1.6; }

/* ── Operations panel ───────────────────────────────────────────────── */
.op-buttons { display: flex; flex-direction: column; gap: 0.6rem; }
.op-btn { width: 100%; justify-content: flex-start; margin-inline-start: 0 !important; }

/* ── Fact list (customer/invoice details, credit notes) ────────────── */
.fact-list { margin: 0; display: flex; flex-direction: column; gap: 0.6rem; }
.fact-list.compact { gap: 0.4rem; margin-top: 0.6rem; }

.fact-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px dashed var(--line);
}
.fact-list.compact .fact-row { padding-bottom: 0; border-bottom: none; font-size: 0.85rem; }
.fact-row:last-child { border-bottom: none; padding-bottom: 0; }

.fact-row dt { margin: 0; font-size: 0.78rem; font-weight: 700; color: var(--ink-mute); flex: none; }
.fact-row dd { margin: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 0.15rem; text-align: end; min-width: 0; }

.fact-primary { font-weight: 700; }
.fact-secondary { font-size: 0.76rem; color: var(--ink-mute); }
.fact-accent { font-weight: 700; color: var(--primary); }
.fact-money { font-weight: 800; font-variant-numeric: tabular-nums; color: var(--ok); }
.fact-money.open { color: var(--warn); }

/* ── Credit notes ───────────────────────────────────────────────────── */
.credit-note { border: 1px solid var(--line); border-radius: 12px; padding: 0.85rem 1rem; }
.credit-note + .credit-note { margin-top: 0.75rem; }
.credit-note-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.4rem; }
.credit-note-head strong { font-variant-numeric: tabular-nums; }

/* ── Dialogs ────────────────────────────────────────────────────────── */
.rma-dialog { width: 600px; max-width: 92vw; }

.dialog-desc { font-size: 0.85rem; color: var(--ink-mute); margin: 0 0 1rem; line-height: 1.6; }

.dialog-lines { display: flex; flex-direction: column; gap: 0.6rem; }

.dialog-line {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 0.7rem 0.9rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.dialog-line-name { font-weight: 700; min-width: 0; }

.dialog-line-fields { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.dialog-line-requested { font-size: 0.8rem; color: var(--ink-mute); }

.settlement-summary {
    background: var(--ground);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 0.9rem 1rem;
    margin-bottom: 1.1rem;
}

.settlement-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; color: var(--ink-soft); }
.settlement-row + .settlement-row { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--line); }
.settlement-row strong { color: var(--ink); font-variant-numeric: tabular-nums; }
.settlement-row strong.exchange { color: #6d28d9; }

.settlement-hint { margin: 0.6rem 0 0; font-size: 0.78rem; line-height: 1.6; color: var(--ink-mute); }
.settlement-effect { margin-bottom: 1rem; }

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .builder-grid { grid-template-columns: minmax(0, 1fr); }
    .summary-rail { position: static; }
}

@media (max-width: 720px) {
    .header-actions .el-button:not(.is-text) { flex: 1; }

    .fact-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
    .fact-row dd { align-items: flex-start; text-align: start; }
}

@media (max-width: 480px) {
    :deep(.el-steps) { --el-text-color-placeholder: transparent; }
    .step-desc { display: none; }
}
</style>
