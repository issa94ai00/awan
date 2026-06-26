<template>
  <div class="template-form-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ isEdit ? $t('notifications.edit_template') : $t('notifications.create_template') }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('notifications.template_key')" prop="template_key">
          <el-input v-model="form.template_key" :placeholder="$t('notifications.template_key_placeholder')" />
          <small style="color: #999">{{ $t('notifications.template_key_help') }}</small>
        </el-form-item>
        <el-form-item :label="$t('notifications.name')" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('notifications.name_ar')">
          <el-input v-model="form.name_ar" />
        </el-form-item>
        <el-form-item :label="$t('notifications.type')" prop="type">
          <el-select v-model="form.type">
            <el-option value="email" :label="$t('notifications.email')" />
            <el-option value="sms" :label="$t('notifications.sms')" />
            <el-option value="push" :label="$t('notifications.push')" />
            <el-option value="in_app" :label="$t('notifications.in_app')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('notifications.subject')" prop="subject" v-if="form.type === 'email'">
          <el-input v-model="form.subject" />
        </el-form-item>
        <el-form-item :label="$t('notifications.subject_ar')" v-if="form.type === 'email'">
          <el-input v-model="form.subject_ar" />
        </el-form-item>
        <el-form-item :label="$t('notifications.body')" prop="body">
          <el-input v-model="form.body" type="textarea" :rows="6" />
          <small style="color: #999">{{ $t('notifications.variables_help') }}</small>
        </el-form-item>
        <el-form-item :label="$t('notifications.body_ar')">
          <el-input v-model="form.body_ar" type="textarea" :rows="6" />
        </el-form-item>
        <el-form-item :label="$t('notifications.variables')" prop="variables">
          <el-input v-model="form.variables_str" :placeholder="$t('notifications.variables_placeholder')" />
          <small style="color: #999">{{ $t('notifications.variables_help') }}</small>
        </el-form-item>
        <el-form-item :label="$t('common.status')">
          <el-switch v-model="form.is_active" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitForm" :loading="saving">
            {{ $t('common.save') }}
          </el-button>
          <el-button @click="$router.back()">
            {{ $t('common.cancel') }}
          </el-button>
          <el-button @click="previewTemplate">
            <el-icon><View /></el-icon> {{ $t('notifications.preview') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Preview Dialog -->
    <el-dialog v-model="showPreviewDialog" :title="$t('notifications.preview')" width="600px">
      <div class="preview-content">
        <div v-if="form.type === 'email'">
          <h4>{{ form.subject }}</h4>
          <p>{{ form.body }}</p>
          <el-divider />
          <h4>{{ form.subject_ar }}</h4>
          <p>{{ form.body_ar }}</p>
        </div>
        <div v-else>
          <p>{{ form.body }}</p>
          <el-divider />
          <p>{{ form.body_ar }}</p>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { Document, Back, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const showPreviewDialog = ref(false)
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

const rules = {
  template_key: [{ required: true, message: t('notifications.template_key_required'), trigger: 'blur' }],
  name: [{ required: true, message: t('notifications.name_required'), trigger: 'blur' }],
  type: [{ required: true, message: t('notifications.type_required'), trigger: 'change' }],
  subject: [{ required: true, message: t('notifications.subject_required'), trigger: 'blur' }],
  body: [{ required: true, message: t('notifications.body_required'), trigger: 'blur' }],
  variables_str: [{ required: true, message: t('notifications.variables_required'), trigger: 'blur' }]
}

const isEdit = computed(() => !!route.params.id)

const loadTemplate = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/notifications/templates/${route.params.id}`)
    form.value = {
      template_key: 'order_confirmation',
      name: 'Order Confirmation',
      name_ar: 'تأكيد الطلب',
      type: 'email',
      subject: 'Order Confirmation',
      subject_ar: 'تأكيد الطلب',
      body: 'Your order {order_number} has been confirmed',
      body_ar: 'تم تأكيد طلبك {order_number}',
      variables_str: 'order_number, customer_name',
      is_active: true
    }
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const submitForm = async () => {
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const data = {
          ...form.value,
          variables: form.value.variables_str.split(',').map(v => v.trim()).filter(v => v)
        }
        if (isEdit.value) {
          // await api.put(`/api/v1/notifications/templates/${route.params.id}`, data)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/notifications/templates', data)
          ElMessage.success(t('common.create_success'))
        }
        $router.back()
      } catch (error) {
        ElMessage.error(t('common.save_error'))
      } finally {
        saving.value = false
      }
    }
  })
}

const previewTemplate = () => {
  showPreviewDialog.value = true
}

onMounted(() => {
  if (isEdit.value) {
    loadTemplate()
  }
})
</script>

<style scoped>
.template-form-page {
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
