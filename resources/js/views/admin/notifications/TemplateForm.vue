<template>
  <div class="template-form-page">
    <AdminPageHeader :title="isEdit ? t('notifications.edit_template') : t('notifications.create_template')">
      <template #actions>
        <el-button :icon="Back" @click="router.back()">
          {{ t('common.back') }}
        </el-button>
        <el-button :icon="View" @click="showPreviewDialog = true">
          {{ t('notifications.preview') }}
        </el-button>
        <el-button type="primary" :icon="Check" @click="submitForm" :loading="saving">
          {{ t('common.save') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-card shadow="never" class="form-card" v-loading="loading">
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('notifications.template_key')" prop="template_key">
              <el-input
                v-model="form.template_key"
                :placeholder="t('notifications.template_key_placeholder')"
                :disabled="isEdit"
              />
              <span class="text-xs text-slate-400 mt-1 block">{{ t('notifications.template_key_help') }}</span>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('notifications.type')" prop="type">
              <el-select v-model="form.type" class="w-full">
                <el-option value="email" :label="t('notifications.email')" />
                <el-option value="sms" :label="t('notifications.sms')" />
                <el-option value="push" :label="t('notifications.push')" />
                <el-option value="in_app" :label="t('notifications.in_app')" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('notifications.name_ar')" prop="name_ar">
              <el-input v-model="form.name_ar" placeholder="مثال: تأكيد طلب المبيعات" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('notifications.name')" prop="name">
              <el-input v-model="form.name" placeholder="e.g. Order Confirmation" />
            </el-form-item>
          </el-col>
        </el-row>

        <template v-if="form.type === 'email'">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item :label="t('notifications.subject_ar')">
                <el-input v-model="form.subject_ar" placeholder="عنوان البريد بالعربية" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item :label="t('notifications.subject')">
                <el-input v-model="form.subject" placeholder="Email subject in English" />
              </el-form-item>
            </el-col>
          </el-row>
        </template>

        <!-- Variables Quick Picker -->
        <div class="var-picker-container">
          <span class="picker-title">{{ t('notifications.quick_add_variable') || 'انقر لإدراج المتغير في النص:' }}</span>
          <div class="picker-tags">
            <el-tag
              v-for="varName in commonVars"
              :key="varName"
              class="cursor-pointer hover:opacity-80"
              @click="insertVar(varName)"
            >
              + {{ '{' + varName + '}' }}
            </el-tag>
          </div>
        </div>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('notifications.body_ar')" prop="body_ar">
              <el-input
                v-model="form.body_ar"
                type="textarea"
                :rows="6"
                placeholder="اكتب نص الرسالة بالعربية..."
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('notifications.body')" prop="body">
              <el-input
                v-model="form.body"
                type="textarea"
                :rows="6"
                placeholder="Write message template in English..."
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item :label="t('notifications.variables')">
          <el-input
            v-model="form.variables_str"
            :placeholder="t('notifications.variables_placeholder')"
          />
          <span class="text-xs text-slate-400 mt-1 block">{{ t('notifications.variables_help') }}</span>
        </el-form-item>

        <el-form-item :label="t('common.status')">
          <el-switch v-model="form.is_active" active-color="#10b981" />
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Preview Modal -->
    <el-dialog v-model="showPreviewDialog" :title="t('notifications.preview')" width="620px">
      <div class="preview-box">
        <el-tabs v-model="previewTab">
          <el-tab-pane label="العربية (Arabic)" name="ar">
            <div class="preview-bubble rtl">
              <h4 v-if="form.subject_ar || form.subject" class="preview-subject">
                {{ interpolate(form.subject_ar || form.subject) }}
              </h4>
              <p class="preview-body">{{ interpolate(form.body_ar || form.body) }}</p>
            </div>
          </el-tab-pane>
          <el-tab-pane label="English" name="en">
            <div class="preview-bubble ltr">
              <h4 v-if="form.subject" class="preview-subject">
                {{ interpolate(form.subject) }}
              </h4>
              <p class="preview-body">{{ interpolate(form.body) }}</p>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
      <template #footer>
        <el-button @click="showPreviewDialog = false">{{ t('common.close') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Back, View, Check } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import notificationsService from '@/services/notifications';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const formRef = ref(null);
const showPreviewDialog = ref(false);
const previewTab = ref('ar');

const isEdit = computed(() => !!route.params.id);

const form = ref({
  template_key: '',
  name: '',
  name_ar: '',
  type: 'email',
  subject: '',
  subject_ar: '',
  body: '',
  body_ar: '',
  variables_str: 'order_number, customer_name',
  is_active: true,
});

const commonVars = ['order_number', 'customer_name', 'product_name', 'current_stock', 'min_stock', 'tracking_number'];

const mockData = {
  order_number: 'SO-000100',
  customer_name: 'شركة الأمل للتجارة',
  product_name: 'خلاط مغسلة شك قصير',
  current_stock: '3',
  min_stock: '15',
  tracking_number: 'TRK-987123',
};

const rules = {
  template_key: [
    { required: true, message: t('notifications.template_key_required'), trigger: 'blur' },
    { pattern: /^[a-z0-9_]+$/, message: 'يجب أن يحتوي الرمز على أحرف صغيرة وأرقام و _ فقط', trigger: 'blur' },
  ],
  name: [{ required: true, message: t('notifications.name_required'), trigger: 'blur' }],
  type: [{ required: true, message: t('notifications.type_required'), trigger: 'change' }],
  body: [{ required: true, message: t('notifications.body_required'), trigger: 'blur' }],
};

const loadTemplate = async () => {
  loading.value = true;
  try {
    const res = await notificationsService.getTemplate(route.params.id);
    const data = res.data || {};
    form.value = {
      template_key: data.template_key || '',
      name: data.name || '',
      name_ar: data.name_ar || '',
      type: data.type || 'email',
      subject: data.subject || '',
      subject_ar: data.subject_ar || '',
      body: data.body || '',
      body_ar: data.body_ar || '',
      variables_str: (data.variables || []).join(', '),
      is_active: Boolean(data.is_active),
    };
  } catch (error) {
    ElMessage.error(t('common.load_error'));
  } finally {
    loading.value = false;
  }
};

const insertVar = (varName) => {
  const token = `{${varName}}`;
  if (form.value.body_ar) {
    form.value.body_ar += ` ${token}`;
  } else {
    form.value.body_ar = token;
  }
  if (form.value.body) {
    form.value.body += ` ${token}`;
  } else {
    form.value.body = token;
  }
};

const interpolate = (text) => {
  if (!text) return '';
  let res = text;
  Object.keys(mockData).forEach((k) => {
    res = res.replaceAll(`{${k}}`, mockData[k]);
  });
  return res;
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true;
      try {
        const payload = {
          ...form.value,
          variables: form.value.variables_str
            ? form.value.variables_str.split(',').map((v) => v.trim()).filter(Boolean)
            : [],
        };
        if (isEdit.value) {
          await notificationsService.updateTemplate(route.params.id, payload);
          ElMessage.success(t('common.update_success'));
        } else {
          await notificationsService.createTemplate(payload);
          ElMessage.success(t('common.create_success'));
        }
        router.back();
      } catch (error) {
        ElMessage.error(t('common.save_error'));
      } finally {
        saving.value = false;
      }
    }
  });
};

onMounted(() => {
  if (isEdit.value) {
    loadTemplate();
  }
});
</script>

<style scoped>
.template-form-page {
  padding: 1.5rem;
}

.form-card {
  border-radius: 14px;
}

.var-picker-container {
  margin-bottom: 1.25rem;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.picker-title {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.4rem;
}

.picker-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.preview-bubble {
  padding: 1.25rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.preview-subject {
  margin: 0 0 0.75rem 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 0.5rem;
}

.preview-body {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.6;
  color: #334155;
  white-space: pre-wrap;
}
</style>
