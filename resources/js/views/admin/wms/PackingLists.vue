<template>
  <div class="packing-lists-page">
    <div class="page-header">
      <h1><el-icon><Open /></el-icon> {{ $t('wms.packing_lists') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('wms.create_packing_list') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('wms.status')">
          <el-select v-model="filters.status" :placeholder="$t('wms.select_status')" clearable @change="loadPackingLists">
            <el-option value="pending" :label="$t('wms.pending')" />
            <el-option value="in_progress" :label="$t('wms.in_progress')" />
            <el-option value="completed" :label="$t('wms.completed')" />
            <el-option value="cancelled" :label="$t('wms.cancelled')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="filters.warehouse_id" :placeholder="$t('wms.select_warehouse')" clearable @change="loadPackingLists">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadPackingLists">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="packingLists" v-loading="loading" stripe>
        <el-table-column prop="list_number" :label="$t('wms.list_number')" />
        <el-table-column prop="picking_list_number" :label="$t('wms.picking_list')" />
        <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="$t('common.created_at')" />
        <el-table-column :label="$t('common.actions')" width="200">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewPackingList(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="startPacking(row)" :disabled="row.status !== 'pending'">
                <el-icon><VideoPlay /></el-icon>
              </el-button>
              <el-button size="small" type="primary" @click="completePacking(row)" :disabled="row.status !== 'in_progress'">
                <el-icon><Check /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="cancelPacking(row)" :disabled="row.status === 'completed'">
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
        @size-change="loadPackingLists"
        @current-change="loadPackingLists"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('wms.create_packing_list')" width="600px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="form.warehouse_id">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.picking_list')">
          <el-select v-model="form.picking_list_id" filterable>
            <el-option v-for="pl in pickingLists" :key="pl.id" :value="pl.id" :label="pl.list_number" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createPackingList" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Open, Plus, Search, View, VideoPlay, Check, Close } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { wmsService } from '@/services/wms'

const { t } = useI18n()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const packingLists = ref([])
const warehouses = ref([])
const completedPickingLists = ref([])
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
  picking_list_id: null
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

const loadWarehouses = async () => {
  try {
    const response = await wmsService.getWarehouses()
    const data = response.data
    warehouses.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadCompletedPickingLists = async () => {
  try {
    const response = await wmsService.getPickingLists({ status: 'completed' })
    const data = response.data
    completedPickingLists.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load picking lists:', error)
  }
}

const loadPackingLists = async () => {
  loading.value = true
  try {
    const response = await wmsService.getPackingLists({
      status: filters.value.status,
      warehouse_id: filters.value.warehouse_id,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })
    const data = response.data
    packingLists.value = data.data || data || []
    pagination.value.total = data.total || packingLists.value.length
  } catch (error) {
    ElMessage.error('خطأ في تحميل قوائم التعبئة')
  } finally {
    loading.value = false
  }
}

const createPackingList = async () => {
  saving.value = true
  try {
    // API expects picking_list_id, warehouse_id
    const payload = {
      picking_list_id: form.value.picking_list_id,
      warehouse_id: form.value.warehouse_id
    }
    await wmsService.createPackingList(payload)
    ElMessage.success('تم إنشاء قائمة التعبئة بنجاح')
    showCreateDialog.value = false
    await loadPackingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء حفظ قائمة التعبئة')
  } finally {
    saving.value = false
  }
}

const startPacking = async (packingList) => {
  try {
    await wmsService.startPacking(packingList.id)
    ElMessage.success('تم بدء التعبئة')
    await loadPackingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء بدء التعبئة')
  }
}

const completePacking = async (packingList) => {
  try {
    await wmsService.completePacking(packingList.id)
    ElMessage.success('تم إنهاء التعبئة بنجاح')
    await loadPackingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء إنهاء التعبئة')
  }
}

const cancelPacking = async (packingList) => {
  try {
    await wmsService.cancelPacking(packingList.id)
    ElMessage.success('تم إلغاء التعبئة')
    await loadPackingLists()
  } catch (error) {
    ElMessage.error('خطأ أثناء إلغاء التعبئة')
  }
}

const viewPackingList = (packingList) => {
  router.push(`/admin/wms/packing/${packingList.id}`)
}

onMounted(() => {
  loadWarehouses()
  loadCompletedPickingLists()
  loadPackingLists()
})
</script>

<style scoped>
.packing-lists-page {
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
