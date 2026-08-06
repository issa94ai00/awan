<template>
  <div class="metrics-page">
    <div class="page-header">
      <h1><el-icon><DataBoard /></el-icon> {{ $t('analytics.metrics') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('analytics.create_metric') }}
      </el-button>
    </div>

    <el-card>
      <el-table :data="metrics" v-loading="loading" stripe>
        <el-table-column prop="name" :label="$t('analytics.name')" />
        <el-table-column prop="code" :label="$t('analytics.code')" />
        <el-table-column prop="type" :label="$t('analytics.type')" />
        <el-table-column prop="value" :label="$t('analytics.current_value')" />
        <el-table-column prop="target" :label="$t('analytics.target')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="editMetric(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteMetric(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create/Edit Dialog -->
    <el-dialog v-model="showCreateDialog" :title="editingMetric ? $t('common.edit') : $t('analytics.create_metric')" width="500px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="$t('analytics.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('analytics.code')">
          <el-input v-model="form.code" />
        </el-form-item>
        <el-form-item :label="$t('analytics.type')">
          <el-select v-model="form.type">
            <el-option value="number" :label="$t('analytics.number')" />
            <el-option value="percentage" :label="$t('analytics.percentage')" />
            <el-option value="currency" :label="$t('analytics.currency')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('analytics.target')">
          <el-input-number v-model="form.target" />
        </el-form-item>
        <el-form-item :label="$t('analytics.description')">
          <el-input v-model="form.description" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="saveMetric" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { DataBoard, Plus, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingMetric = ref(null)
const metrics = ref([])
const form = ref({
  name: '',
  code: '',
  type: 'number',
  target: 0,
  description: ''
})

const loadMetrics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/metrics')
    metrics.value = [
      { id: 1, name: 'Revenue Growth', code: 'REV_GROWTH', type: 'percentage', value: 12.5, target: 10, status: 'active' },
      { id: 2, name: 'Inventory Turnover', code: 'INV_TURN', type: 'number', value: 8.5, target: 8, status: 'active' }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const editMetric = (metric) => {
  editingMetric.value = metric
  form.value = { ...metric }
  showCreateDialog.value = true
}

const saveMetric = async () => {
  saving.value = true
  try {
    if (editingMetric.value) {
      // await api.put(`/api/v1/analytics/metrics/${editingMetric.value.id}`, form.value)
      ElMessage.success(t('common.update_success'))
    } else {
      // await api.post('/api/v1/analytics/metrics', form.value)
      ElMessage.success(t('common.create_success'))
    }
    showCreateDialog.value = false
    editingMetric.value = null
    await loadMetrics()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const deleteMetric = async (metric) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/analytics/metrics/${metric.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadMetrics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

onMounted(() => {
  loadMetrics()
})
</script>

<style scoped>
.metrics-page {
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
</style>
