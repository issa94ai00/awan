<template>
  <div class="workflow-show-page">
    <div class="page-header">
      <h1><el-icon><Operation /></el-icon> {{ workflow.name }}</h1>
      <div class="header-actions">
        <el-button @click="$router.back()">
          <el-icon><Back /></el-icon> {{ $t('common.back') }}
        </el-button>
        <el-button @click="$router.push(`/admin/workflows/${workflow.id}/edit`)">
          <el-icon><Edit /></el-icon> {{ $t('common.edit') }}
        </el-button>
        <el-button type="success" @click="executeWorkflow" :disabled="workflow.status !== 'active'">
          <el-icon><VideoPlay /></el-icon> {{ $t('workflows.execute') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('workflows.details') }}</span>
            </div>
          </template>
          <el-descriptions :column="1" border>
            <el-descriptions-item :label="$t('workflows.name')">{{ workflow.name }}</el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.name_ar')">{{ workflow.name_ar }}</el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.description')">{{ workflow.description }}</el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.trigger_type')">
              <el-tag>{{ workflow.trigger_type }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.status')">
              <el-tag :type="workflow.status === 'active' ? 'success' : 'danger'">{{ workflow.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.steps_count')">{{ workflow.steps_count }}</el-descriptions-item>
            <el-descriptions-item :label="$t('workflows.executions_count')">{{ workflow.executions_count }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('workflows.statistics') }}</span>
            </div>
          </template>
          <el-row :gutter="20">
            <el-col :xs="12">
              <div class="stat-item">
                <h4>{{ $t('workflows.success_rate') }}</h4>
                <h3>{{ statistics.success_rate }}%</h3>
              </div>
            </el-col>
            <el-col :xs="12">
              <div class="stat-item">
                <h4>{{ $t('workflows.avg_duration') }}</h4>
                <h3>{{ statistics.avg_duration }}s</h3>
              </div>
            </el-col>
            <el-col :xs="12">
              <div class="stat-item">
                <h4>{{ $t('workflows.last_execution') }}</h4>
                <h3>{{ statistics.last_execution }}</h3>
              </div>
            </el-col>
            <el-col :xs="12">
              <div class="stat-item">
                <h4>{{ $t('workflows.next_execution') }}</h4>
                <h3>{{ statistics.next_execution }}</h3>
              </div>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('workflows.steps') }}</span>
          <el-button size="small" @click="$router.push(`/admin/workflows/${workflow.id}/steps`)">
            <el-icon><Plus /></el-icon> {{ $t('workflows.add_step') }}
          </el-button>
        </div>
      </template>
      <el-table :data="steps" v-loading="loading" stripe>
        <el-table-column prop="order" :label="$t('workflows.order')" width="80" />
        <el-table-column prop="name" :label="$t('workflows.step_name')" />
        <el-table-column prop="action_type" :label="$t('workflows.action_type')" width="150">
          <template #default="{ row }">
            <el-tag>{{ row.action_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_required" :label="$t('workflows.required')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.is_required ? 'success' : 'info'">{{ row.is_required ? $t('common.yes') : $t('common.no') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="editStep(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteStep(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('workflows.recent_executions') }}</span>
          <el-button text @click="$router.push(`/admin/workflows/${workflow.id}/executions`)">
            {{ $t('common.view_all') }}
          </el-button>
        </div>
      </template>
      <el-table :data="recentExecutions" v-loading="loading" stripe>
        <el-table-column prop="execution_number" :label="$t('workflows.execution_number')" />
        <el-table-column prop="status" :label="$t('common.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : (row.status === 'failed' ? 'danger' : 'warning')">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" :label="$t('workflows.duration')" width="100" />
        <el-table-column prop="executed_at" :label="$t('workflows.executed_at')" />
        <el-table-column :label="$t('common.actions')" width="100">
          <template #default="{ row }">
            <el-button size="small" @click="viewExecution(row)">
              <el-icon><View /></el-icon>
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Operation, Back, Edit, VideoPlay, Plus, Delete, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const workflow = ref({})
const steps = ref([])
const statistics = ref({
  success_rate: 0,
  avg_duration: 0,
  last_execution: '-',
  next_execution: '-'
})
const recentExecutions = ref([])

const loadWorkflow = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}`)
    workflow.value = {
      id: 1,
      name: 'Order Confirmation',
      name_ar: 'تأكيد الطلب',
      description: 'Automatically send confirmation when order is placed',
      trigger_type: 'event',
      status: 'active',
      steps_count: 3,
      executions_count: 150
    }
    await loadSteps()
    await loadStatistics()
    await loadRecentExecutions()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const loadSteps = async () => {
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}/steps`)
    steps.value = [
      { id: 1, order: 1, name: 'Send Email', action_type: 'notification', is_required: true },
      { id: 2, order: 2, name: 'Update Inventory', action_type: 'api_call', is_required: true },
      { id: 3, order: 3, name: 'Log Activity', action_type: 'audit', is_required: false }
    ]
  } catch (error) {
    console.error('Failed to load steps:', error)
  }
}

const loadStatistics = async () => {
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}/statistics`)
    statistics.value = {
      success_rate: 95.5,
      avg_duration: 2.3,
      last_execution: '2026-06-23 10:30',
      next_execution: '-'
    }
  } catch (error) {
    console.error('Failed to load statistics:', error)
  }
}

const loadRecentExecutions = async () => {
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}/executions?limit=5`)
    recentExecutions.value = [
      { id: 1, execution_number: 'EXE-000150', status: 'completed', duration: '2.1s', executed_at: '2026-06-23 10:30' },
      { id: 2, execution_number: 'EXE-000149', status: 'completed', duration: '2.5s', executed_at: '2026-06-23 09:45' }
    ]
  } catch (error) {
    console.error('Failed to load executions:', error)
  }
}

const executeWorkflow = async () => {
  try {
    // await api.post(`/api/v1/workflows/${route.params.id}/execute`)
    ElMessage.success(t('workflows.execution_started'))
    await loadStatistics()
    await loadRecentExecutions()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const editStep = (step) => {
  $router.push(`/admin/workflows/${route.params.id}/steps/${step.id}/edit`)
}

const deleteStep = async (step) => {
  try {
    // await api.delete(`/api/v1/workflows/${route.params.id}/steps/${step.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadSteps()
  } catch (error) {
    ElMessage.error(t('common.delete_error'))
  }
}

const viewExecution = (execution) => {
  $router.push(`/admin/workflows/${route.params.id}/executions/${execution.id}`)
}

onMounted(() => {
  loadWorkflow()
})
</script>

<style scoped>
.workflow-show-page {
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

.stat-item {
  text-align: center;
  padding: 15px;
}

.stat-item h4 {
  margin: 0 0 10px 0;
  font-size: 14px;
  color: #666;
}

.stat-item h3 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color: #333;
}
</style>
