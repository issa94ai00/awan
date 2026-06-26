<template>
  <div class="rma-page">
    <div class="page-header">
      <h1><el-icon><RefreshLeft /></el-icon> {{ $t('rma.title') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('rma.create_return') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('rma.status')">
          <el-select v-model="filters.status" :placeholder="$t('rma.select_status')" clearable @change="loadRmaRequests">
            <el-option value="pending" :label="$t('rma.pending')" />
            <el-option value="approved" :label="$t('rma.approved')" />
            <el-option value="rejected" :label="$t('rma.rejected')" />
            <el-option value="completed" :label="$t('rma.completed')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.return_type')">
          <el-select v-model="filters.return_type" :placeholder="$t('rma.select_type')" clearable @change="loadRmaRequests">
            <el-option value="refund" :label="$t('rma.refund')" />
            <el-option value="exchange" :label="$t('rma.exchange')" />
            <el-option value="repair" :label="$t('rma.repair')" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadRmaRequests">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="rmaRequests" v-loading="loading" stripe>
        <el-table-column prop="rma_number" :label="$t('rma.rma_number')" width="150" />
        <el-table-column prop="customer" :label="$t('rma.customer')" width="150">
          <template #default="{ row }">
            {{ row.customer?.name || row.customer || 'N/A' }}
          </template>
        </el-table-column>
        <el-table-column prop="order_number" :label="$t('rma.order_number')" width="150">
          <template #default="{ row }">
            {{ row.sales_order?.order_number || row.order_number || 'N/A' }}
          </template>
        </el-table-column>
        <el-table-column prop="return_type" :label="$t('rma.return_type')" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.type || row.return_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="reason" :label="$t('rma.reason')" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.reason_description || row.reason || 'N/A' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="$t('common.status')" width="120">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="$t('common.created_at')" width="180">
          <template #default="{ row }">
            {{ row.requested_at || row.created_at || 'N/A' }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="250">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewRma(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="approveRma(row)" :disabled="row.status !== 'pending'">
                <el-icon><Check /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="rejectRma(row)" :disabled="row.status !== 'pending'">
                <el-icon><Close /></el-icon>
              </el-button>
              <el-button size="small" type="primary" @click="editRma(row)" :disabled="row.status !== 'pending'">
                <el-icon><Edit /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="loadRmaRequests"
        @current-change="loadRmaRequests"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('rma.create_return')" width="700px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('rma.customer')">
          <el-select v-model="form.customer_id" :placeholder="$t('rma.select_customer')" filterable>
            <el-option v-for="customer in customers" :key="customer.id" :value="customer.id" :label="customer.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.order')">
          <el-select v-model="form.order_id" :placeholder="$t('rma.select_order')" filterable>
            <el-option v-for="order in orders" :key="order.id" :value="order.id" :label="order.order_number" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.reason')">
          <el-input v-model="form.reason" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="$t('rma.return_type')">
          <el-select v-model="form.return_type">
            <el-option value="refund" :label="$t('rma.refund')" />
            <el-option value="exchange" :label="$t('rma.exchange')" />
            <el-option value="repair" :label="$t('rma.repair')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.items')">
          <el-button size="small" @click="addItem">
            <el-icon><Plus /></el-icon> {{ $t('rma.add_item') }}
          </el-button>
        </el-form-item>
        <el-table :data="form.items" max-height="200">
          <el-table-column prop="product" :label="$t('rma.product')" />
          <el-table-column prop="quantity" :label="$t('rma.quantity')" width="100">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="1" size="small" />
            </template>
          </el-table-column>
          <el-table-column prop="reason" :label="$t('rma.item_reason')" width="150">
            <template #default="{ row }">
              <el-input v-model="row.reason" size="small" />
            </template>
          </el-table-column>
          <el-table-column :label="$t('common.actions')" width="80">
            <template #default="{ row, $index }">
              <el-button size="small" type="danger" @click="removeItem($index)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createRma" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { RefreshLeft, Plus, Search, View, Check, Close, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import rmaService from '@/services/rma'
import axios from 'axios'

const router = useRouter()
const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const rmaRequests = ref([])
const customers = ref([])
const orders = ref([])
const filters = ref({
  status: '',
  return_type: ''
})
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})
const form = ref({
  customer_id: null,
  order_id: null,
  reason: '',
  return_type: 'refund',
  items: []
})

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    completed: 'primary'
  }
  return types[status] || 'info'
}

const loadCustomers = async () => {
  try {
    const response = await axios.get('/api/admin/customers')
    customers.value = response.data.data || response.data
  } catch (error) {
    console.error('Failed to load customers:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    customers.value = []
  }
}

const loadOrders = async () => {
  try {
    const response = await axios.get('/api/admin/sales-orders', { params: { status: 'delivered' } })
    orders.value = response.data.data || response.data
  } catch (error) {
    console.error('Failed to load orders:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    orders.value = []
  }
}

const loadRmaRequests = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequests({
      ...filters.value,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })
    rmaRequests.value = response.data.data || []
    pagination.value.total = response.data.total || 0
  } catch (error) {
    console.error('Failed to load RMA requests:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    rmaRequests.value = []
    pagination.value.total = 0
  } finally {
    loading.value = false
  }
}

const addItem = () => {
  form.value.items.push({
    product_id: null,
    quantity: 1,
    reason: ''
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const createRma = async () => {
  // Validate form
  if (!form.value.customer_id) {
    ElMessage.error(t('rma.customer_required'))
    return
  }
  if (!form.value.order_id) {
    ElMessage.error(t('rma.order_required'))
    return
  }
  if (!form.value.reason) {
    ElMessage.error(t('rma.reason_required'))
    return
  }
  if (!form.value.items || form.value.items.length === 0) {
    ElMessage.error(t('rma.items_required'))
    return
  }

  saving.value = true
  try {
    const data = {
      customer_id: form.value.customer_id,
      sales_order_id: form.value.order_id,
      reason: 'defective',
      type: form.value.return_type,
      reason_description: form.value.reason,
      items: form.value.items.map(item => ({
        sales_order_item_id: item.sales_order_item_id,
        quantity_requested: item.quantity,
        condition: 'new',
        resolution: form.value.return_type,
        notes: item.reason
      }))
    }
    await rmaService.createRmaRequest(data)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    resetForm()
    await loadRmaRequests()
  } catch (error) {
    const errorMessage = error.response?.data?.message || error.response?.data?.errors?.items?.[0] || t('common.save_error')
    ElMessage.error(errorMessage)
  } finally {
    saving.value = false
  }
}

const resetForm = () => {
  form.value = {
    customer_id: null,
    order_id: null,
    reason: '',
    return_type: 'refund',
    items: []
  }
}

const viewRma = (rma) => {
  router.push(`/admin/rma/${rma.id}`)
}

const editRma = (rma) => {
  router.push(`/admin/rma/${rma.id}/edit`)
}

const approveRma = async (rma) => {
  try {
    await ElMessageBox.confirm(t('rma.approve_confirm'), t('common.warning'), {
      type: 'warning',
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel')
    })
    await rmaService.approveRma(rma.id)
    ElMessage.success(t('rma.approved'))
    await loadRmaRequests()
  } catch (error) {
    if (error !== 'cancel') {
      const errorMessage = error.response?.data?.message || t('common.action_error')
      ElMessage.error(errorMessage)
    }
  }
}

const rejectRma = async (rma) => {
  try {
    const { value } = await ElMessageBox.prompt(t('rma.reject_reason'), t('rma.reject_confirm'), {
      type: 'warning',
      inputPattern: /.+/,
      inputErrorMessage: t('common.required_field'),
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel')
    })
    await rmaService.rejectRma(rma.id, { reason: value })
    ElMessage.success(t('rma.rejected'))
    await loadRmaRequests()
  } catch (error) {
    if (error !== 'cancel') {
      const errorMessage = error.response?.data?.message || t('common.action_error')
      ElMessage.error(errorMessage)
    }
  }
}

onMounted(() => {
  loadCustomers()
  loadOrders()
  loadRmaRequests()
})
</script>

<style scoped>
.rma-page {
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

.filter-form {
  margin-bottom: 20px;
}
</style>
