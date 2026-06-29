<template>
  <div class="rma-details-container">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><RefreshLeft /></el-icon>
        </div>
        <div>
          <h1 class="header-title">تفاصيل طلب المرتجع: {{ rma.rma_number }}</h1>
          <p class="header-subtitle">استعراض تفاصيل طلب الاسترجاع وحالة معالجة المنتجات وتعديل الحسابات والمخازن</p>
        </div>
      </div>
      <div class="header-actions">
        <el-button @click="goBack" class="btn-back-premium">
          <el-icon><Back /></el-icon> رجوع
        </el-button>
        <el-button @click="router.push(`/admin/rma/${rma.id}/edit`)" :disabled="rma.status !== 'pending'" class="btn-edit-premium">
          <el-icon><Edit /></el-icon> تعديل
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
            <span class="step-title">تم تقديم الطلب</span>
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
            <span class="step-title">تمت الموافقة</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.approved_at">{{ formatDate(rma.approved_at) }}</span>
            <span class="step-desc" v-else>في انتظار الموافقة</span>
          </template>
        </el-step>
        <el-step>
          <template #icon>
            <el-icon><Box /></el-icon>
          </template>
          <template #title>
            <span class="step-title">استلام المنتجات</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.received_at">{{ formatDate(rma.received_at) }}</span>
            <span class="step-desc" v-else>في انتظار الاستلام</span>
          </template>
        </el-step>
        <el-step>
          <template #icon>
            <el-icon><CircleCheck /></el-icon>
          </template>
          <template #title>
            <span class="step-title">اكتمال التسوية</span>
          </template>
          <template #description>
            <span class="step-desc" v-if="rma.completed_at">{{ formatDate(rma.completed_at) }}</span>
            <span class="step-desc" v-else>في انتظار الإكمال</span>
          </template>
        </el-step>
      </el-steps>
    </el-card>

    <!-- Reject/Cancel Banner -->
    <div class="alert-banner" :class="rma.status" v-else>
      <el-icon><Warning /></el-icon>
      <div>
        <h4>طلب إرجاع {{ rma.status === 'rejected' ? 'مرفوض' : 'ملغي' }}</h4>
        <p v-if="rma.status === 'rejected'">سبب الرفض: {{ rma.notes || 'لم يتم كتابة سبب الرفض' }}</p>
        <p v-else>تم إلغاء هذا الطلب من قبل النظام أو العميل.</p>
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
              <h3>المنتجات المدرجة في طلب الإرجاع</h3>
            </div>
          </template>

          <el-table :data="rma.items" stripe class="items-table-premium">
            <el-table-column prop="product" label="المنتج" min-width="200" />
            <el-table-column prop="quantity_requested" label="الكمية المطلوبة" width="130" align="center" />
            <el-table-column prop="quantity_received" label="الكمية المستلمة" width="130" align="center">
              <template #default="{ row }">
                <span :class="{'qty-warning': row.quantity_received < row.quantity_requested, 'qty-success': row.quantity_received === row.quantity_requested}">
                  {{ row.quantity_received }} / {{ row.quantity_requested }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="condition" label="حالة المنتج المستلم" width="140">
              <template #default="{ row }">
                <el-tag :type="getConditionTagType(row.condition)" class="premium-tag">
                  {{ getConditionLabel(row.condition) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="resolution" label="نوع التسوية البند" width="130">
              <template #default="{ row }">
                <el-tag :type="getResolutionTagType(row.resolution)" class="premium-tag">
                  {{ getResolutionLabel(row.resolution) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="exchange_product" label="المنتج البديل" min-width="160" v-if="rma.items.some(i => i.resolution === 'exchange')">
              <template #default="{ row }">
                <span class="exchange-product-text" v-if="row.resolution === 'exchange'">{{ row.exchange_product || 'لم يحدد بعد' }}</span>
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
              <h3>سجل الحركات والتتبع (النشاطات)</h3>
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
              <h3>العمليات والإجراءات</h3>
            </div>
          </template>

          <div class="operations-buttons-grid">
            <el-button type="success" class="op-btn approve" :disabled="rma.status !== 'pending'" @click="approveRma">
              <el-icon><Check /></el-icon> الموافقة على الطلب
            </el-button>
            <el-button type="danger" class="op-btn reject" :disabled="rma.status !== 'pending'" @click="rejectRma">
              <el-icon><Close /></el-icon> رفض طلب الإرجاع
            </el-button>
            <el-button type="warning" class="op-btn receive" :disabled="rma.status !== 'approved'" @click="openReceiveDialog">
              <el-icon><Location /></el-icon> استلام وفحص المنتجات
            </el-button>
            <el-button type="primary" class="op-btn complete" :disabled="rma.status !== 'approved' && rma.status !== 'received'" @click="openCompleteDialog">
              <el-icon><Finished /></el-icon> إكمال تسوية الطلب
            </el-button>
            <el-button type="info" class="op-btn cancel" :disabled="rma.status === 'completed' || rma.status === 'rejected' || rma.status === 'cancelled'" @click="cancelRma">
              <el-icon><Warning /></el-icon> إلغاء الطلب
            </el-button>
          </div>
        </el-card>

        <!-- Information Card -->
        <el-card class="info-card-premium" shadow="never" style="margin-top: 25px">
          <template #header>
            <div class="section-card-header">
              <span class="dot blue"></span>
              <h3>معلومات العميل والفاتورة</h3>
            </div>
          </template>

          <el-descriptions :column="1" border class="premium-descriptions">
            <el-descriptions-item label="العميل">
              <div class="customer-info-box">
                <span class="name">{{ rma.customer }}</span>
                <span class="phone" v-if="rma.customer_phone">{{ rma.customer_phone }}</span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="فاتورة المبيعات الأصلية">
              <span class="invoice-number">#{{ rma.order_number }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="نوع التسوية">
              <el-tag :type="getReturnTypeClass(rma.return_type)" class="premium-tag">
                {{ getReturnTypeLabel(rma.return_type) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="سبب الإرجاع">
              {{ getReasonLabel(rma.reason) }}
            </el-descriptions-item>
            <el-descriptions-item label="تاريخ التقديم">
              {{ formatDate(rma.created_at) }}
            </el-descriptions-item>
            <el-descriptions-item label="قيمة التعويض المعتمدة" v-if="rma.status === 'completed'">
              <span class="final-refund-amount">{{ formatCurrency(rma.refund_amount) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="طريقة التسوية المالية" v-if="rma.status === 'completed'">
              <el-tag type="info" class="premium-tag">{{ getRefundMethodLabel(rma.refund_method) }}</el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- Receive Items Dialog -->
    <el-dialog v-model="showReceiveDialog" title="استلام وفحص المنتجات المسترجعة" width="600px" class="premium-dialog">
      <p class="dialog-desc">قم بتسجيل الكميات التي تم فحصها واستلامها بالفعل في مخزن البضائع لتعديل الكميات الإجمالية.</p>
      <el-table :data="receiveForm.items" stripe class="mini-table">
        <el-table-column prop="product" label="المنتج" />
        <el-table-column prop="quantity_requested" label="المطلوب إرجاعه" width="130" align="center" />
        <el-table-column label="الكمية المستلمة" width="150" align="center">
          <template #default="{ row }">
            <el-input-number v-model="row.quantity_received" :min="0" :max="row.quantity_requested" size="small" />
          </template>
        </el-table-column>
      </el-table>
      <template #footer>
        <el-button @click="showReceiveDialog = false">إلغاء</el-button>
        <el-button type="primary" @click="submitReceive" :loading="receiveLoading">تأكيد الاستلام</el-button>
      </template>
    </el-dialog>

    <!-- Complete RMA Dialog -->
    <el-dialog v-model="showCompleteDialog" title="إكمال وتسوية طلب الإرجاع" width="550px" class="premium-dialog">
      <el-form :model="completeForm" label-position="top">
        <el-form-item label="طريقة التسوية المالية للتعويض" prop="refund_method">
          <el-select v-model="completeForm.refund_method" placeholder="اختر طريقة التعويض" class="premium-select-field">
            <el-option value="original" label="استرداد للحساب الأصلي (كاش/بنكي)" />
            <el-option value="store_credit" label="رصيد متجر (إضافة لمحفظة العميل)" />
            <el-option value="bank_transfer" label="تحويل بنكي مخصص" />
            <el-option value="check" label="شيك بنكي ورقي" />
          </el-select>
        </el-form-item>
        <el-form-item label="قيمة الاسترداد المالي الإجمالية (SAR)" prop="refund_amount">
          <el-input-number v-model="completeForm.refund_amount" :min="0" class="premium-select-field" />
          <span class="input-helper-text">تم احتساب القيمة تلقائياً بناءً على حالة المنتج المسجلة، يمكنك تعديل القيمة يدوياً.</span>
        </el-form-item>
        <el-form-item label="ملاحظات التسوية النهائية" prop="admin_notes">
          <el-input v-model="completeForm.admin_notes" type="textarea" :rows="3" placeholder="ملاحظات تظهر للعميل أو كمرجع للمحاسبة..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCompleteDialog = false">إلغاء</el-button>
        <el-button type="primary" @click="submitComplete" :loading="completeLoading">تأكيد وإغلاق الطلب</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { RefreshLeft, Back, Edit, Check, Close, Location, Finished, Warning, User, DocumentAdd, Select, Box, CircleCheck } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import rmaService from '@/services/rma'
import api from '@/api'

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

const currentStep = computed(() => {
  const steps = {
    pending: 1,
    approved: 2,
    received: 3,
    completed: 4
  }
  // If rma has received quantity but status is still approved, we can consider it received (step 3)
  if (rma.value.status === 'approved' && rma.value.items.some(i => i.quantity_received > 0)) {
    return 3
  }
  return steps[rma.value.status] || 1
})

const loadRma = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequest(rmaId.value)
    const data = response.data.data || response.data
    rma.value = {
      id: data.id,
      rma_number: data.rma_number,
      customer: data.customer?.name || 'N/A',
      customer_phone: data.customer?.phone || '',
      order_number: data.sales_order?.order_number || 'N/A',
      order_id: data.sales_order_id,
      return_type: data.type || 'refund',
      reason: data.reason || 'defective',
      reason_description: data.reason_description || 'N/A',
      status: data.status || 'pending',
      created_at: data.requested_at || data.created_at,
      resolved_at: data.approved_at || null,
      resolved_by: data.approver?.name || null,
      refund_amount: parseFloat(data.refund_amount) || 0,
      refund_method: data.refund_method || null,
      notes: data.admin_notes || null,
      items: data.items ? data.items.map(item => ({
        id: item.id,
        product: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
        quantity_requested: item.quantity_requested,
        quantity_received: item.quantity_received || 0,
        reason: item.notes || 'N/A',
        condition: item.condition || 'new',
        resolution: item.resolution || 'refund',
        unit_price: parseFloat(item.sales_order_item?.unit_price) || 0,
        exchange_product: item.exchange_product ? (item.exchange_product.name_ar || item.exchange_product.name) : null
      })) : []
    }
    await loadActivities()
  } catch (error) {
    console.error('Failed to load RMA details:', error)
    ElMessage.error('خطأ في تحميل تفاصيل المرتجع')
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
    const { value } = await ElMessageBox.prompt('هل أنت متأكد من الموافقة على طلب الإرجاع؟ يمكنك إضافة ملاحظات إدارية هنا:', 'الموافقة على الطلب', {
      confirmButtonText: 'موافق',
      cancelButtonText: 'إلغاء',
      inputPlaceholder: 'أدخل ملاحظات إضافية (اختياري)...'
    })
    await rmaService.approveRma(rma.value.id, { admin_notes: value })
    ElMessage.success('تمت الموافقة على طلب الإرجاع بنجاح')
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت عملية الموافقة')
    }
  }
}

const rejectRma = async () => {
  try {
    const { value } = await ElMessageBox.prompt('يرجى إدخال سبب رفض طلب الإرجاع:', 'رفض الطلب', {
      type: 'warning',
      confirmButtonText: 'رفض الطلب',
      cancelButtonText: 'إلغاء',
      inputPattern: /.+/,
      inputErrorMessage: 'يجب كتابة سبب الرفض'
    })
    await rmaService.rejectRma(rma.value.id, { reason: value })
    ElMessage.success('تم رفض طلب الإرجاع بنجاح')
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت عملية الرفض')
    }
  }
}

const cancelRma = async () => {
  try {
    await ElMessageBox.confirm('هل أنت متأكد من إلغاء طلب الإرجاع هذا بالكامل؟', 'إلغاء الطلب', {
      type: 'warning',
      confirmButtonText: 'إلغاء الطلب',
      cancelButtonText: 'تراجع'
    })
    await rmaService.cancelRma(rma.value.id)
    ElMessage.success('تم إلغاء طلب الإرجاع بنجاح')
    loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت عملية إلغاء الطلب')
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
    await api.post(`/admin/rma/${rma.value.id}/receive`, data)
    ElMessage.success('تم تسجيل استلام وفحص المنتجات بنجاح')
    showReceiveDialog.value = false
    loadRma()
  } catch (error) {
    console.error('Failed to receive items:', error)
    ElMessage.error(error.response?.data?.message || 'فشل تسجيل استلام المنتجات')
  } finally {
    receiveLoading.value = false
  }
}

const openCompleteDialog = () => {
  // Pre-calculate estimated refund sum
  const sum = rma.value.items.reduce((acc, item) => {
    const multiplier = {
      new: 1.0,
      used: 0.7,
      damaged: 0.5,
      missing: 0.0
    }[item.condition] || 0.5
    return acc + (item.unit_price * multiplier * item.quantity_requested)
  }, 0)

  completeForm.value = {
    refund_amount: rma.value.refund_amount || sum,
    refund_method: rma.value.refund_method || 'store_credit',
    admin_notes: rma.value.notes || ''
  }
  showCompleteDialog.value = true
}

const submitComplete = async () => {
  completeLoading.value = true
  try {
    await rmaService.completeRma(rma.value.id, completeForm.value)
    ElMessage.success('تمت تسوية وإكمال طلب الإرجاع بنجاح')
    showCompleteDialog.value = false
    loadRma()
  } catch (error) {
    console.error('Failed to complete RMA:', error)
    ElMessage.error(error.response?.data?.message || 'فشل إكمال تسوية طلب الإرجاع')
  } finally {
    completeLoading.value = false
  }
}

const getStatusLabel = (status) => {
  const labels = {
    pending: 'بانتظار الموافقة',
    approved: 'تمت الموافقة',
    rejected: 'مرفوض',
    completed: 'مكتمل',
    cancelled: 'ملغي'
  }
  return labels[status] || status
}

const getReturnTypeLabel = (type) => {
  const labels = {
    refund: 'استرداد نقدي',
    exchange: 'استبدال المنتج',
    store_credit: 'رصيد متجر'
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
    defective: 'منتج معيب (خلل مصنعي)',
    damaged: 'منتج تالف أو مكسور',
    wrong_item: 'منتج خاطئ أو غير المطلوب',
    not_as_described: 'لا يطابق الوصف المعروض',
    changed_mind: 'تغيير رأي العميل',
    other: 'أسباب أخرى'
  }
  return labels[reason] || reason
}

const getConditionLabel = (condition) => {
  const labels = {
    new: 'جديد (سليم)',
    used: 'مستعمل',
    damaged: 'تالف',
    missing: 'مفقود'
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
    refund: 'استرداد مالي',
    exchange: 'استبدال',
    repair: 'إصلاح وصيانة',
    discard: 'إتلاف'
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
    original: 'استرداد للحساب الأصلي',
    store_credit: 'رصيد متجر (إضافة للمحفظة)',
    bank_transfer: 'تحويل بنكي مخصص',
    check: 'شيك بنكي'
  }
  return labels[method] || method || '-'
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)
}

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
