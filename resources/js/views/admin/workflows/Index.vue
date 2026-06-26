<template>
  <div class="workflows-page">
    <div class="page-header">
      <h1><el-icon><Operation /></el-icon> {{ $t('workflows.title') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('workflows.create_workflow') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('workflows.status')">
          <el-select v-model="filters.status" :placeholder="$t('workflows.select_status')" clearable @change="loadWorkflows">
            <el-option value="active" :label="$t('common.active')" />
            <el-option value="inactive" :label="$t('common.inactive')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('workflows.trigger_type')">
          <el-select v-model="filters.trigger_type" :placeholder="$t('workflows.select_trigger')" clearable @change="loadWorkflows">
            <el-option value="manual" :label="$t('workflows.manual')" />
            <el-option value="event" :label="$t('workflows.event')" />
            <el-option value="scheduled" :label="$t('workflows.scheduled')" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadWorkflows">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="workflows" v-loading="loading" stripe>
        <el-table-column prop="name" :label="$t('workflows.name')" />
        <el-table-column prop="trigger_type" :label="$t('workflows.trigger_type')" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.trigger_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="steps_count" :label="$t('workflows.steps')" width="100" />
        <el-table-column prop="executions_count" :label="$t('workflows.executions')" width="100" />
        <el-table-column prop="status" :label="$t('common.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" :label="$t('common.updated_at')" width="180" />
        <el-table-column :label="$t('common.actions')" width="250">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewWorkflow(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" @click="editWorkflow(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" type="success" @click="executeWorkflow(row)" :disabled="row.status !== 'active'">
                <el-icon><VideoPlay /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteWorkflow(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('workflows.create_workflow')" width="600px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('workflows.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('workflows.name_ar')">
          <el-input v-model="form.name_ar" />
        </el-form-item>
        <el-form-item :label="$t('workflows.description')">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="$t('workflows.trigger_type')">
          <el-select v-model="form.trigger_type">
            <el-option value="manual" :label="$t('workflows.manual')" />
            <el-option value="event" :label="$t('workflows.event')" />
            <el-option value="scheduled" :label="$t('workflows.scheduled')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('workflows.status')">
          <el-switch v-model="form.status" :active-value="'active'" :inactive-value="'inactive'" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createWorkflow" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Operation, Plus, Search, View, Edit, VideoPlay, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const workflows = ref([])
const filters = ref({
  status: '',
  trigger_type: ''
})
const form = ref({
  name: '',
  name_ar: '',
  description: '',
  trigger_type: 'manual',
  status: 'active'
})

const loadWorkflows = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/workflows', { params: filters.value })
    workflows.value = [
      { id: 1, name: 'Order Confirmation', name_ar: 'تأكيد الطلب', trigger_type: 'event', steps_count: 3, executions_count: 150, status: 'active', updated_at: '2026-06-23' },
      { id: 2, name: 'Low Stock Alert', name_ar: 'تنبيه المخزون المنخفض', trigger_type: 'event', steps_count: 2, executions_count: 45, status: 'active', updated_at: '2026-06-22' }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const createWorkflow = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/workflows', form.value)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    form.value = {
      name: '',
      name_ar: '',
      description: '',
      trigger_type: 'manual',
      status: 'active'
    }
    await loadWorkflows()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const viewWorkflow = (workflow) => {
  $router.push(`/admin/workflows/${workflow.id}`)
}

const editWorkflow = (workflow) => {
  $router.push(`/admin/workflows/${workflow.id}/edit`)
}

const executeWorkflow = async (workflow) => {
  try {
    // await api.post(`/api/v1/workflows/${workflow.id}/execute`)
    ElMessage.success(t('workflows.execution_started'))
    await loadWorkflows()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const deleteWorkflow = async (workflow) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/workflows/${workflow.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadWorkflows()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

onMounted(() => {
  loadWorkflows()
})
</script>

<style scoped>
.workflows-page {
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
