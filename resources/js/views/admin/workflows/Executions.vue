<template>
  <div class="workflow-executions-page">
    <div class="page-header">
      <h1><el-icon><VideoPlay /></el-icon> {{ $t('workflows.executions') }} - {{ workflowName }}</h1>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('workflows.status')">
          <el-select v-model="filters.status" :placeholder="$t('workflows.select_status')" clearable @change="loadExecutions">
            <el-option value="completed" :label="$t('workflows.completed')" />
            <el-option value="failed" :label="$t('workflows.failed')" />
            <el-option value="running" :label="$t('workflows.running')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('common.date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="$t('common.to')"
            :start-placeholder="$t('common.start_date')"
            :end-placeholder="$t('common.end_date')"
            @change="loadExecutions"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadExecutions">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="executions" v-loading="loading" stripe>
        <el-table-column prop="execution_number" :label="$t('workflows.execution_number')" />
        <el-table-column prop="status" :label="$t('common.status')" width="120">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : (row.status === 'failed' ? 'danger' : 'warning')">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" :label="$t('workflows.duration')" width="100" />
        <el-table-column prop="executed_at" :label="$t('workflows.executed_at')" width="180" />
        <el-table-column prop="completed_at" :label="$t('workflows.completed_at')" width="180" />
        <el-table-column prop="error_message" :label="$t('workflows.error')" show-overflow-tooltip />
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewExecution(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="retryExecution(row)" :disabled="row.status !== 'failed'">
                <el-icon><Refresh /></el-icon>
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
        @size-change="loadExecutions"
        @current-change="loadExecutions"
        style="margin-top: 20px"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { VideoPlay, Search, View, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const workflowName = ref('')
const executions = ref([])
const filters = ref({
  status: ''
})
const dateRange = ref([])
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

const loadWorkflow = async () => {
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}`)
    workflowName.value = 'Order Confirmation'
  } catch (error) {
    console.error('Failed to load workflow:', error)
  }
}

const loadExecutions = async () => {
  loading.value = true
  try {
    // const response = await api.get(`/api/v1/workflows/${route.params.id}/executions`, {
    //   params: { ...filters.value, start_date: dateRange.value[0], end_date: dateRange.value[1], ...pagination.value }
    // })
    executions.value = [
      { id: 1, execution_number: 'EXE-000150', status: 'completed', duration: '2.1s', executed_at: '2026-06-23 10:30', completed_at: '2026-06-23 10:32', error_message: null },
      { id: 2, execution_number: 'EXE-000149', status: 'failed', duration: '1.5s', executed_at: '2026-06-23 09:45', completed_at: '2026-06-23 09:47', error_message: 'API timeout' }
  ]
    pagination.value.total = 150
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const viewExecution = (execution) => {
  $router.push(`/admin/workflows/${route.params.id}/executions/${execution.id}`)
}

const retryExecution = async (execution) => {
  try {
    // await api.post(`/api/v1/workflows/${route.params.id}/executions/${execution.id}/retry`)
    ElMessage.success(t('workflows.retry_started'))
    await loadExecutions()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

onMounted(() => {
  loadWorkflow()
  loadExecutions()
})
</script>

<style scoped>
.workflow-executions-page {
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
