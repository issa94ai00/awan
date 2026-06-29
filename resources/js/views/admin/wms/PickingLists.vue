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
import { useRouter } from 'vue-router'
import { wmsService } from '@/services/wms'
import { salesOrdersApi } from '@/api/salesOrders'

const { t } = useI18n()
const router = useRouter()
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
    const response = await wmsService.getWarehouses()
    const data = response.data
    warehouses.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadOrders = async () => {
  try {
    const response = await salesOrdersApi.getAll({ per_page: 100 })
    const data = response.data
    orders.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load orders:', error)
  }
}

const loadPickingLists = async () => {
  loading.value = true
  try {
    const response = await wmsService.getPickingLists({
      status: filters.value.status,
      warehouse_id: filters.value.warehouse_id,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })
    const data = response.data
    pickingLists.value = data.data || data || []
    pagination.value.total = data.total || pickingLists.value.length
  } catch (error) {
    ElMessage.error('خطأ في تحميل قوائم الانتقاء')
  } finally {
    loading.value = false
  }
}

const createPickingList = async () => {
  saving.value = true
  try {
    // API expects sales_order_id, warehouse_id, priority
    const payload = {
      sales_order_id: form.value.order_id,
      warehouse_id: form.value.warehouse_id,
      priority: form.value.priority
    }
    await wmsService.createPickingList(payload)
    ElMessage.success('تم إنشاء قائمة الانتقاء بنجاح')
    showCreateDialog.value = false
    await loadPickingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء حفظ قائمة الانتقاء')
  } finally {
    saving.value = false
  }
}

const startPicking = async (pickingList) => {
  try {
    await wmsService.startPicking(pickingList.id)
    ElMessage.success('تم بدء عملية الانتقاء')
    await loadPickingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء بدء الانتقاء')
  }
}

const completePicking = async (pickingList) => {
  try {
    await wmsService.completePicking(pickingList.id)
    ElMessage.success('تم إنهاء عملية الانتقاء بنجاح')
    await loadPickingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء إنهاء عملية الانتقاء')
  }
}

const cancelPicking = async (pickingList) => {
  try {
    await wmsService.cancelPicking(pickingList.id)
    ElMessage.success('تم إلغاء عملية الانتقاء')
    await loadPickingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء إلغاء عملية الانتقاء')
  }
}

const viewPickingList = (pickingList) => {
  router.push(`/admin/wms/picking/${pickingList.id}`)
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
