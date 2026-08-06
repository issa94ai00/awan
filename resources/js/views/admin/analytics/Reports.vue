<template>
  <div class="reports-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ $t('analytics.reports') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('analytics.create_report') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('analytics.type')">
          <el-select v-model="filters.type" :placeholder="$t('analytics.select_type')" clearable @change="loadReports">
            <el-option value="sales" :label="$t('analytics.sales')" />
            <el-option value="inventory" :label="$t('analytics.inventory')" />
            <el-option value="financial" :label="$t('analytics.financial')" />
            <el-option value="warehouse" :label="$t('analytics.warehouse')" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadReports">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="reports" v-loading="loading" stripe>
        <el-table-column prop="name" :label="$t('analytics.report_name')" />
        <el-table-column prop="type" :label="$t('analytics.type')" />
        <el-table-column prop="created_at" :label="$t('common.created_at')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : 'warning'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="200">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewReport(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" @click="executeReport(row)" :disabled="row.status === 'running'">
                <el-icon><VideoPlay /></el-icon>
              </el-button>
              <el-button size="small" @click="downloadReport(row)" :disabled="row.status !== 'completed'">
                <el-icon><Download /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteReport(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('analytics.create_report')" width="600px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('analytics.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('analytics.type')">
          <el-select v-model="form.type">
            <el-option value="sales" :label="$t('analytics.sales')" />
            <el-option value="inventory" :label="$t('analytics.inventory')" />
            <el-option value="financial" :label="$t('analytics.financial')" />
            <el-option value="warehouse" :label="$t('analytics.warehouse')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('analytics.query_config')">
          <el-input v-model="form.query_config" type="textarea" :rows="3" placeholder="JSON configuration" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createReport" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Document, Plus, Search, View, VideoPlay, Download, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const reports = ref([])
const filters = ref({
  type: ''
})
const form = ref({
  name: '',
  type: 'sales',
  query_config: ''
})

const loadReports = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/reports', { params: filters.value })
    reports.value = [
      { id: 1, name: 'Monthly Sales Report', type: 'sales', created_at: '2026-06-23', status: 'completed' },
      { id: 2, name: 'Inventory Status Report', type: 'inventory', created_at: '2026-06-22', status: 'completed' }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const createReport = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/analytics/reports', form.value)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    await loadReports()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const viewReport = (report) => {
  $router.push(`/admin/analytics/reports/${report.id}`)
}

const executeReport = async (report) => {
  try {
    // await api.post(`/api/v1/analytics/reports/${report.id}/execute`)
    ElMessage.success(t('analytics.execution_started'))
    await loadReports()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const downloadReport = async (report) => {
  try {
    // await api.get(`/api/v1/analytics/reports/${report.id}/export`)
    ElMessage.success(t('analytics.download_started'))
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const deleteReport = async (report) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/analytics/reports/${report.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadReports()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

onMounted(() => {
  loadReports()
})
</script>

<style scoped>
.reports-page {
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
