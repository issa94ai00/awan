<template>
  <div class="cycle-counts-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ $t('wms.cycle_counts') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('wms.create_cycle_count') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('wms.status')">
          <el-select v-model="filters.status" :placeholder="$t('wms.select_status')" clearable @change="loadCycleCounts">
            <el-option value="pending" :label="$t('wms.pending')" />
            <el-option value="in_progress" :label="$t('wms.in_progress')" />
            <el-option value="completed" :label="$t('wms.completed')" />
            <el-option value="cancelled" :label="$t('wms.cancelled')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="filters.warehouse_id" :placeholder="$t('wms.select_warehouse')" clearable @change="loadCycleCounts">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadCycleCounts">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="cycleCounts" v-loading="loading" stripe>
        <el-table-column prop="count_number" :label="$t('wms.count_number')" />
        <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
        <el-table-column prop="zone" :label="$t('wms.zone')" />
        <el-table-column prop="count_date" :label="$t('wms.count_date')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="accuracy" :label="$t('wms.accuracy')">
          <template #default="{ row }">
            {{ row.accuracy ? row.accuracy + '%' : '-' }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="200">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewCycleCount(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="startCount(row)" :disabled="row.status !== 'pending'">
                <el-icon><VideoPlay /></el-icon>
              </el-button>
              <el-button size="small" type="primary" @click="completeCount(row)" :disabled="row.status !== 'in_progress'">
                <el-icon><Check /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="cancelCount(row)" :disabled="row.status === 'completed'">
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
        @size-change="loadCycleCounts"
        @current-change="loadCycleCounts"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('wms.create_cycle_count')" width="600px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="form.warehouse_id">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.zone')">
          <el-input v-model="form.zone" />
        </el-form-item>
        <el-form-item :label="$t('wms.count_date')">
          <el-date-picker v-model="form.count_date" type="date" />
        </el-form-item>
        <el-form-item :label="$t('wms.notes')">
          <el-input v-model="form.notes" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createCycleCount" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Document, Plus, Search, View, VideoPlay, Check, Close } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const cycleCounts = ref([])
const warehouses = ref([])
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
  zone: '',
  count_date: new Date(),
  notes: ''
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
    // await api.get('/api/v1/wms/warehouses')
    warehouses.value = [
      { id: 1, name: 'Main Warehouse' }
    ]
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadCycleCounts = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/wms/cycle-counts', { params: { ...filters.value, ...pagination.value } })
    cycleCounts.value = [
      { id: 1, count_number: 'CC-000001', warehouse: 'Main Warehouse', zone: 'A', count_date: '2026-06-23', status: 'pending', accuracy: null }
    ]
    pagination.value.total = 2
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const createCycleCount = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/wms/cycle-counts', form.value)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    await loadCycleCounts()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const startCount = async (cycleCount) => {
  try {
    // await api.post(`/api/v1/wms/cycle-counts/${cycleCount.id}/start`)
    ElMessage.success(t('wms.count_started'))
    await loadCycleCounts()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const completeCount = async (cycleCount) => {
  try {
    // await api.post(`/api/v1/wms/cycle-counts/${cycleCount.id}/complete`)
    ElMessage.success(t('wms.count_completed'))
    await loadCycleCounts()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const cancelCount = async (cycleCount) => {
  try {
    // await api.post(`/api/v1/wms/cycle-counts/${cycleCount.id}/cancel`)
    ElMessage.success(t('wms.count_cancelled'))
    await loadCycleCounts()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const viewCycleCount = (cycleCount) => {
  $router.push(`/admin/wms/cycle-counts/${cycleCount.id}`)
}

onMounted(() => {
  loadWarehouses()
  loadCycleCounts()
})
</script>

<style scoped>
.cycle-counts-page {
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
