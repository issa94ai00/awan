<template>
  <div class="workflow-steps-page">
    <div class="page-header">
      <h1><el-icon><List /></el-icon> {{ $t('workflows.steps') }} - {{ workflowName }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('workflows.add_step') }}
      </el-button>
    </div>

    <el-card>
      <el-table :data="steps" v-loading="loading" stripe>
        <el-table-column prop="order" :label="$t('workflows.order')" width="80" />
        <el-table-column prop="name" :label="$t('workflows.step_name')" />
        <el-table-column prop="name_ar" :label="$t('workflows.step_name_ar')" />
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
        <el-table-column :label="$t('common.actions')" width="200">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="moveUp(row)" :disabled="row.order === 1">
                <el-icon><ArrowUp /></el-icon>
              </el-button>
              <el-button size="small" @click="moveDown(row)" :disabled="row.order === steps.length">
                <el-icon><ArrowDown /></el-icon>
              </el-button>
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

    <!-- Create/Edit Dialog -->
    <el-dialog v-model="showCreateDialog" :title="editingStep ? $t('common.edit') : $t('workflows.add_step')" width="600px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('workflows.step_name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('workflows.step_name_ar')">
          <el-input v-model="form.name_ar" />
        </el-form-item>
        <el-form-item :label="$t('workflows.action_type')">
          <el-select v-model="form.action_type">
            <el-option value="notification" :label="$t('workflows.notification')" />
            <el-option value="api_call" :label="$t('workflows.api_call')" />
            <el-option value="email" :label="$t('workflows.email')" />
            <el-option value="sms" :label="$t('workflows.sms')" />
            <el-option value="audit" :label="$t('workflows.audit')" />
            <el-option value="custom" :label="$t('workflows.custom')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('workflows.action_config')">
          <el-input v-model="form.action_config_str" type="textarea" :rows="4" placeholder="JSON configuration" />
        </el-form-item>
        <el-form-item :label="$t('workflows.conditions')">
          <el-input v-model="form.conditions_str" type="textarea" :rows="3" placeholder="JSON conditions" />
        </el-form-item>
        <el-form-item :label="$t('workflows.required')">
          <el-switch v-model="form.is_required" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="saveStep" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { List, Plus, ArrowUp, ArrowDown, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingStep = ref(null)
const workflowName = ref('')
const steps = ref([])
const form = ref({
  name: '',
  name_ar: '',
  action_type: 'notification',
  action_config_str: '',
  conditions_str: '',
  is_required: true
})

const loadWorkflow = async () => {
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}`)
    workflowName.value = 'Order Confirmation'
  } catch (error) {
    console.error('Failed to load workflow:', error)
  }
}

const loadSteps = async () => {
  loading.value = true
  try {
    // const response = await api.get(`/api/v1/workflows/${route.params.id}/steps`)
    steps.value = [
      { id: 1, order: 1, name: 'Send Email', name_ar: 'إرسال بريد', action_type: 'notification', is_required: true },
      { id: 2, order: 2, name: 'Update Inventory', name_ar: 'تحديث المخزون', action_type: 'api_call', is_required: true },
      { id: 3, order: 3, name: 'Log Activity', name_ar: 'تسجيل النشاط', action_type: 'audit', is_required: false }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const editStep = (step) => {
  editingStep.value = step
  form.value = {
    name: step.name,
    name_ar: step.name_ar,
    action_type: step.action_type,
    action_config_str: '',
    conditions_str: '',
    is_required: step.is_required
  }
  showCreateDialog.value = true
}

const saveStep = async () => {
  saving.value = true
  try {
    const data = {
      ...form.value,
      action_config: form.value.action_config_str ? JSON.parse(form.value.action_config_str) : {},
      conditions: form.value.conditions_str ? JSON.parse(form.value.conditions_str) : {}
    }
    if (editingStep.value) {
      // await api.put(`/api/v1/workflows/${route.params.id}/steps/${editingStep.value.id}`, data)
      ElMessage.success(t('common.update_success'))
    } else {
      // await api.post(`/api/v1/workflows/${route.params.id}/steps`, data)
      ElMessage.success(t('common.create_success'))
    }
    showCreateDialog.value = false
    editingStep.value = null
    await loadSteps()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const moveUp = async (step) => {
  try {
    // await api.post(`/api/v1/workflows/${route.params.id}/steps/${step.id}/move-up`)
    ElMessage.success(t('common.update_success'))
    await loadSteps()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const moveDown = async (step) => {
  try {
    // await api.post(`/api/v1/workflows/${route.params.id}/steps/${step.id}/move-down`)
    ElMessage.success(t('common.update_success'))
    await loadSteps()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
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

onMounted(() => {
  loadWorkflow()
  loadSteps()
})
</script>

<style scoped>
.workflow-steps-page {
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
