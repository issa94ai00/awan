<template>
  <div class="picking-lists-page">
    <div class="page-header">
      <h1><el-icon><List /></el-icon> {{ $t('wms.picking_lists') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('wms.create_picking_list') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('wms.status')">
          <el-select v-model="filters.status" :placeholder="$t('wms.select_status')" clearable @change="loadPickingLists">
            <el-option value="pending" :label="$t('wms.pending')" />
            <el-option value="in_progress" :label="$t('wms.in_progress')" />
            <el-option value="completed" :label="$t('wms.completed')" />
            <el-option value="cancelled" :label="$t('wms.cancelled')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="filters.warehouse_id" :placeholder="$t('wms.select_warehouse')" clearable @change="loadPickingLists">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadPickingLists">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="pickingLists" v-loading="loading" stripe>
        <el-table-column prop="list_number" :label="$t('wms.list_number')" />
        <el-table-column prop="order_number" :label="$t('wms.order_number')" />
        <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
        <el-table-column prop="priority" :label="$t('wms.priority')">
          <template #default="{ row }">
            <el-tag :type="getPriorityType(row.priority)">{{ row.priority }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="$t('common.created_at')" />
        <el-table-column :label="$t('common.actions')" width="250">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewPickingList(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="startPicking(row)" :disabled="row.status !== 'pending'">
                <el-icon><VideoPlay /></el-icon>
              </el-button>
              <el-button size="small" type="primary" @click="completePicking(row)" :disabled="row.status !== 'in_progress'">
                <el-icon><Check /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="cancelPicking(row)" :disabled="row.status === 'completed'">
                <el-icon><Close /></el-icon>
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
        @size-change="loadPickingLists"
        @current-change="loadPickingLists"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('wms.create_picking_list')" width="600px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="form.warehouse_id">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.order')">
          <el-select v-model="form.order_id" filterable>
            <el-option v-for="order in orders" :key="order.id" :value="order.id" :label="order.order_number" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.priority')">
          <el-select v-model="form.priority">
            <el-option value="low" :label="$t('wms.low')" />
            <el-option value="normal" :label="$t('wms.normal')" />
            <el-option value="high" :label="$t('wms.high')" />
            <el-option value="urgent" :label="$t('wms.urgent')" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createPickingList" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { List, Plus, Search, View, VideoPlay, Check, Close } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const pickingLists = ref([])
const warehouses = ref([])
const orders = ref([])
const filters = ref({
  status: '',
  warehouse_id: null
})
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})
const form = ref({
  warehouse_id: null,
  order_id: null,
  priority: 'normal'
})

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    in_progress: 'primary',
    completed: 'success',
    cancelled: 'danger'
  }
  return types[status] || 'info'
}

const getPriorityType = (priority) => {
  const types = {
    low: 'info',
    normal: 'primary',
    high: 'warning',
    urgent: 'danger'
  }
  return types[priority] || 'info'
}

const loadWarehouses = async () => {
  try {
    // await api.get('/api/v1/wms/warehouses')
    warehouses.value = [
      { id: 1, name: 'Main Warehouse' }
    ]
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadOrders = async () => {
  try {
    // await api.get('/api/v1/sales-orders?status=confirmed')
    orders.value = [
      { id: 1, order_number: 'SO-000001' },
      { id: 2, order_number: 'SO-000002' }
    ]
  } catch (error) {
    console.error('Failed to load orders:', error)
  }
}

const loadPickingLists = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/wms/picking-lists', { params: { ...filters.value, ...pagination.value } })
    pickingLists.value = [
      { id: 1, list_number: 'PL-000001', order_number: 'SO-000001', warehouse: 'Main Warehouse', priority: 'normal', status: 'pending', created_at: '2026-06-23' }
    ]
    pagination.value.total = 5
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const createPickingList = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/wms/picking-lists', form.value)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    await loadPickingLists()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const startPicking = async (pickingList) => {
  try {
    // await api.post(`/api/v1/wms/picking-lists/${pickingList.id}/start`)
    ElMessage.success(t('wms.picking_started'))
    await loadPickingLists()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const completePicking = async (pickingList) => {
  try {
    // await api.post(`/api/v1/wms/picking-lists/${pickingList.id}/complete`)
    ElMessage.success(t('wms.picking_completed'))
    await loadPickingLists()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const cancelPicking = async (pickingList) => {
  try {
    // await api.post(`/api/v1/wms/picking-lists/${pickingList.id}/cancel`)
    ElMessage.success(t('wms.picking_cancelled'))
    await loadPickingLists()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const viewPickingList = (pickingList) => {
  // Navigate to detail view
  $router.push(`/admin/wms/picking/${pickingList.id}`)
}

onMounted(() => {
  loadWarehouses()
  loadOrders()
  loadPickingLists()
})
</script>

<style scoped>
.picking-lists-page {
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
