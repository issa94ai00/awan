<template>
  <div class="rma-show-page">
    <div class="page-header">
      <h1><el-icon><RefreshLeft /></el-icon> {{ $t('rma.return_details') }} - {{ rma.rma_number }}</h1>
      <div class="header-actions">
        <el-button @click="router.back()">
          <el-icon><Back /></el-icon> {{ $t('common.back') }}
        </el-button>
        <el-button @click="router.push(`/admin/rma/${rma.id}/edit`)" :disabled="rma.status !== 'pending'">
          <el-icon><Edit /></el-icon> {{ $t('common.edit') }}
        </el-button>
        <el-button type="success" @click="approveRma" :disabled="rma.status !== 'pending'">
          <el-icon><Check /></el-icon> {{ $t('rma.approve') }}
        </el-button>
        <el-button type="danger" @click="rejectRma" :disabled="rma.status !== 'pending'">
          <el-icon><Close /></el-icon> {{ $t('rma.reject') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('rma.return_info') }}</span>
            </div>
          </template>
          <el-descriptions :column="1" border>
            <el-descriptions-item :label="$t('rma.rma_number')">{{ rma.rma_number }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.customer')">{{ rma.customer }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.order_number')">{{ rma.order_number }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.return_type')">
              <el-tag>{{ rma.return_type }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('rma.reason')">{{ rma.reason }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.status')">
              <el-tag :type="getStatusType(rma.status)">{{ rma.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('common.created_at')">{{ rma.created_at }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('rma.resolution') }}</span>
            </div>
          </template>
          <el-descriptions :column="1" border>
            <el-descriptions-item :label="$t('rma.resolved_at')">{{ rma.resolved_at || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.resolved_by')">{{ rma.resolved_by || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.refund_amount')">{{ rma.refund_amount ? '$' + rma.refund_amount : '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('rma.notes')">{{ rma.notes || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('rma.returned_items') }}</span>
        </div>
      </template>
      <el-table :data="rma.items" v-loading="loading" stripe>
        <el-table-column prop="product" :label="$t('rma.product')" />
        <el-table-column prop="quantity" :label="$t('rma.quantity')" width="100" />
        <el-table-column prop="reason" :label="$t('rma.item_reason')" />
        <el-table-column prop="condition" :label="$t('rma.condition')" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.condition }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="$t('common.status')" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status === 'approved' ? 'success' : 'warning'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('rma.activity_timeline') }}</span>
        </div>
      </template>
      <el-timeline>
        <el-timeline-item
          v-for="activity in activities"
          :key="activity.id"
          :timestamp="activity.created_at"
          :type="getTimelineType(activity.action)"
        >
          <el-card>
            <h4>{{ activity.action }}</h4>
            <p>{{ activity.description }}</p>
            <small>{{ $t('audit.user') }}: {{ activity.user }}</small>
          </el-card>
        </el-timeline-item>
      </el-timeline>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshLeft, Back, Edit, Check, Close } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import rmaService from '@/services/rma'

const router = useRouter()
const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const rma = ref({})
const activities = ref([])

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    completed: 'primary'
  }
  return types[status] || 'info'
}

const getTimelineType = (action) => {
  const types = {
    create: 'primary',
    approve: 'success',
    reject: 'danger',
    complete: 'success'
  }
  return types[action] || 'primary'
}

const loadRma = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequest(route.params.id)
    const data = response.data.data || response.data
    rma.value = {
      id: data.id,
      rma_number: data.rma_number,
      customer: data.customer?.name || 'N/A',
      order_number: data.sales_order?.order_number || 'N/A',
      return_type: data.type || 'refund',
      reason: data.reason_description || 'N/A',
      status: data.status || 'pending',
      created_at: data.requested_at || data.created_at,
      resolved_at: data.approved_at || null,
      resolved_by: data.approver?.name || null,
      refund_amount: data.refund_amount || null,
      notes: data.admin_notes || null,
      items: data.items ? data.items.map(item => ({
        product: item.product?.name || item.product_name || 'N/A',
        quantity: item.quantity_requested,
        reason: item.notes || 'N/A',
        condition: item.condition || 'new',
        status: data.status
      })) : []
    }
    await loadActivities()
  } catch (error) {
    console.error('Failed to load RMA:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    router.back()
  } finally {
    loading.value = false
  }
}

const loadActivities = async () => {
  try {
    const response = await rmaService.getActivity(route.params.id)
    activities.value = response.data.data || []
  } catch (error) {
    console.error('Failed to load activities:', error)
    // Don't show error for activities as it's not critical
    activities.value = []
  }
}

const approveRma = async () => {
  try {
    await ElMessageBox.confirm(t('rma.approve_confirm'), t('common.warning'), {
      type: 'warning',
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel')
    })
    await rmaService.approveRma(route.params.id)
    ElMessage.success(t('rma.approved'))
    await loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      const errorMessage = error.response?.data?.message || t('common.action_error')
      ElMessage.error(errorMessage)
    }
  }
}

const rejectRma = async () => {
  try {
    const { value } = await ElMessageBox.prompt(t('rma.reject_reason'), t('rma.reject_confirm'), {
      type: 'warning',
      inputPattern: /.+/,
      inputErrorMessage: t('common.required_field'),
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel')
    })
    await rmaService.rejectRma(route.params.id, { reason: value })
    ElMessage.success(t('rma.rejected'))
    await loadRma()
  } catch (error) {
    if (error !== 'cancel') {
      const errorMessage = error.response?.data?.message || t('common.action_error')
      ElMessage.error(errorMessage)
    }
  }
}

onMounted(() => {
  loadRma()
})
</script>

<style scoped>
.rma-show-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 24px;
  color: #333;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
