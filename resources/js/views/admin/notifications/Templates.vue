<template>
  <div class="notification-templates-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ $t('notifications.templates') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('notifications.create_template') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('notifications.type')">
          <el-select v-model="filters.type" :placeholder="$t('notifications.select_type')" clearable @change="loadTemplates">
            <el-option value="email" :label="$t('notifications.email')" />
            <el-option value="sms" :label="$t('notifications.sms')" />
            <el-option value="push" :label="$t('notifications.push')" />
            <el-option value="in_app" :label="$t('notifications.in_app')" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadTemplates">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="templates" v-loading="loading" stripe>
        <el-table-column prop="template_key" :label="$t('notifications.template_key')" />
        <el-table-column prop="name" :label="$t('notifications.name')" />
        <el-table-column prop="type" :label="$t('notifications.type')" width="100">
          <template #default="{ row }">
            <el-tag>{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="variables" :label="$t('notifications.variables')" width="200">
          <template #default="{ row }">
            <el-tag v-for="variable in row.variables" :key="variable" size="small" style="margin-right: 5px">
              {{ variable }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_active" :label="$t('common.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'">
              {{ row.is_active ? $t('common.active') : $t('common.inactive') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="editTemplate(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" @click="previewTemplate(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteTemplate(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create/Edit Dialog -->
    <el-dialog v-model="showCreateDialog" :title="editingTemplate ? $t('common.edit') : $t('notifications.create_template')" width="700px">
      <el-form :model="form" label-width="150px">
        <el-form-item :label="$t('notifications.template_key')">
          <el-input v-model="form.name" :placeholder="$t('notifications.template_key_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('notifications.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('notifications.name_ar')">
          <el-input v-model="form.name_ar" />
        </el-form-item>
        <el-form-item :label="$t('notifications.type')">
          <el-select v-model="form.type">
            <el-option value="email" :label="$t('notifications.email')" />
            <el-option value="sms" :label="$t('notifications.sms')" />
            <el-option value="push" :label="$t('notifications.push')" />
            <el-option value="in_app" :label="$t('notifications.in_app')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('notifications.subject')" v-if="form.type === 'email'">
          <el-input v-model="form.subject" />
        </el-form-item>
        <el-form-item :label="$t('notifications.subject_ar')" v-if="form.type === 'email'">
          <el-input v-model="form.subject_ar" />
        </el-form-item>
        <el-form-item :label="$t('notifications.body')">
          <el-input v-model="form.body" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item :label="$t('notifications.body_ar')">
          <el-input v-model="form.body_ar" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item :label="$t('notifications.variables')">
          <el-input v-model="form.variables_str" :placeholder="$t('notifications.variables_placeholder')" />
          <small style="color: #999">{{ $t('notifications.variables_help') }}</small>
        </el-form-item>
        <el-form-item :label="$t('common.status')">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="saveTemplate" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>

    <!-- Preview Dialog -->
    <el-dialog v-model="showPreviewDialog" :title="$t('notifications.preview')" width="600px">
      <div class="preview-content">
        <h4>{{ previewData.subject || previewData.title }}</h4>
        <p>{{ previewData.body }}</p>
        <el-divider />
        <h4>{{ previewData.subject_ar || previewData.title_ar }}</h4>
        <p>{{ previewData.body_ar }}</p>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Document, Plus, Search, Edit, View, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const showPreviewDialog = ref(false)
const editingTemplate = ref(null)
const templates = ref([])
const previewData = ref({})
const filters = ref({
  type: ''
})
const form = ref({
  template_key: '',
  name: '',
  name_ar: '',
  type: 'email',
  subject: '',
  subject_ar: '',
  body: '',
  body_ar: '',
  variables_str: '',
  is_active: true
})

const loadTemplates = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/notifications/templates', { params: filters.value })
    templates.value = [
      { 
        id: 1, 
        template_key: 'order_confirmation', 
        name: 'Order Confirmation', 
        name_ar: 'تأكيد الطلب',
        type: 'email', 
        subject: 'Order Confirmation',
        subject_ar: 'تأكيد الطلب',
        body: 'Your order {order_number} has been confirmed',
        body_ar: 'تم تأكيد طلبك {order_number}',
        variables: ['order_number', 'customer_name'],
        is_active: true 
      }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const editTemplate = (template) => {
  editingTemplate.value = template
  form.value = {
    ...template,
    variables_str: template.variables.join(', ')
  }
  showCreateDialog.value = true
}

const saveTemplate = async () => {
  saving.value = true
  try {
    const data = {
      ...form.value,
      variables: form.value.variables_str.split(',').map(v => v.trim()).filter(v => v)
    }
    if (editingTemplate.value) {
      // await api.put(`/api/v1/notifications/templates/${editingTemplate.value.id}`, data)
      ElMessage.success(t('common.update_success'))
    } else {
      // await api.post('/api/v1/notifications/templates', data)
      ElMessage.success(t('common.create_success'))
    }
    showCreateDialog.value = false
    editingTemplate.value = null
    await loadTemplates()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const previewTemplate = (template) => {
  previewData.value = template
  showPreviewDialog.value = true
}

const deleteTemplate = async (template) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/notifications/templates/${template.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadTemplates()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

onMounted(() => {
  loadTemplates()
})
</script>

<style scoped>
.notification-templates-page {
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

.preview-content {
  padding: 20px;
}

.preview-content h4 {
  margin: 0 0 10px 0;
  color: #333;
}

.preview-content p {
  margin: 0 0 20px 0;
  color: #666;
  white-space: pre-wrap;
}
</style>
