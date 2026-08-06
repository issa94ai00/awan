<template>
  <div class="workflow-form-page">
    <div class="page-header">
      <h1><el-icon><Operation /></el-icon> {{ isEdit ? $t('workflows.edit_workflow') : $t('workflows.create_workflow') }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('workflows.name')" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('workflows.name_ar')">
          <el-input v-model="form.name_ar" />
        </el-form-item>
        <el-form-item :label="$t('workflows.description')" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="$t('workflows.trigger_type')" prop="trigger_type">
          <el-select v-model="form.trigger_type">
            <el-option value="manual" :label="$t('workflows.manual')" />
            <el-option value="event" :label="$t('workflows.event')" />
            <el-option value="scheduled" :label="$t('workflows.scheduled')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('workflows.trigger_config')" v-if="form.trigger_type === 'event'">
          <el-input v-model="form.trigger_config_str" type="textarea" :rows="3" placeholder="JSON configuration" />
        </el-form-item>
        <el-form-item :label="$t('workflows.cron_expression')" v-if="form.trigger_type === 'scheduled'">
          <el-input v-model="form.cron_expression" placeholder="0 0 * * *" />
        </el-form-item>
        <el-form-item :label="$t('workflows.status')">
          <el-switch v-model="form.status" :active-value="'active'" :inactive-value="'inactive'" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitForm" :loading="saving">
            {{ $t('common.save') }}
          </el-button>
          <el-button @click="$router.back()">
            {{ $t('common.cancel') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { Operation, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = ref({
  name: '',
  name_ar: '',
  description: '',
  trigger_type: 'manual',
  trigger_config_str: '',
  cron_expression: '',
  status: 'active'
})

const rules = {
  name: [{ required: true, message: t('workflows.name_required'), trigger: 'blur' }],
  description: [{ required: true, message: t('workflows.description_required'), trigger: 'blur' }],
  trigger_type: [{ required: true, message: t('workflows.trigger_type_required'), trigger: 'change' }]
}

const isEdit = computed(() => !!route.params.id)

const loadWorkflow = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/workflows/${route.params.id}`)
    form.value = {
      name: 'Order Confirmation',
      name_ar: 'تأكيد الطلب',
      description: 'Automatically send confirmation when order is placed',
      trigger_type: 'event',
      trigger_config_str: '{"event": "order.created"}',
      cron_expression: '',
      status: 'active'
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
          trigger_config: form.value.trigger_config_str ? JSON.parse(form.value.trigger_config_str) : {}
        }
        if (isEdit.value) {
          // await api.put(`/api/v1/workflows/${route.params.id}`, data)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/workflows', data)
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

onMounted(() => {
  if (isEdit.value) {
    loadWorkflow()
  }
})
</script>

<style scoped>
.workflow-form-page {
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
