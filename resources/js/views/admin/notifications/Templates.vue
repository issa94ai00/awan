<template>
  <div class="notification-templates-page">
    <AdminPageHeader :title="t('notifications.templates')">
      <template #actions>
        <el-button :icon="Refresh" @click="loadTemplates" :loading="loading">
          {{ t('common.refresh') }}
        </el-button>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog">
          {{ t('notifications.create_template') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-card shadow="never" class="main-card">
      <!-- Filters Toolbar -->
      <div class="filter-toolbar">
        <div class="filter-inputs">
          <el-select
            v-model="filters.type"
            :placeholder="t('notifications.select_type')"
            clearable
            @change="loadTemplates"
            class="filter-select"
          >
            <el-option value="" :label="t('common.all_types') || 'جميع القنوات'" />
            <el-option value="email" :label="t('notifications.email')" />
            <el-option value="sms" :label="t('notifications.sms')" />
            <el-option value="push" :label="t('notifications.push')" />
            <el-option value="in_app" :label="t('notifications.in_app')" />
          </el-select>

          <el-select
            v-model="filters.is_active"
            :placeholder="t('common.status')"
            clearable
            @change="loadTemplates"
            class="filter-select"
          >
            <el-option value="" :label="t('common.all_status') || 'جميع الحالات'" />
            <el-option :value="1" :label="t('common.active')" />
            <el-option :value="0" :label="t('common.inactive')" />
          </el-select>
        </div>

        <div class="filter-actions">
          <el-button type="primary" :icon="Search" @click="loadTemplates">
            {{ t('common.search') }}
          </el-button>
          <el-button @click="resetFilters">
            {{ t('common.reset') || 'إعادة ضبط' }}
          </el-button>
        </div>
      </div>

      <!-- Templates Table -->
      <el-table :data="filteredTemplates" v-loading="loading" stripe border class="templates-table">
        <el-table-column prop="template_key" :label="t('notifications.template_key')" min-width="170">
          <template #default="{ row }">
            <span class="font-mono text-sm font-semibold text-blue-600">{{ row.template_key }}</span>
          </template>
        </el-table-column>

        <el-table-column prop="name" :label="t('notifications.name')" min-width="180">
          <template #default="{ row }">
            <div class="name-cell">
              <span class="font-medium text-slate-800">{{ currentLocale === 'ar' && row.name_ar ? row.name_ar : row.name }}</span>
              <span v-if="row.name_ar && currentLocale !== 'ar'" class="text-xs text-slate-400">{{ row.name_ar }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="type" :label="t('notifications.type')" width="130" align="center">
          <template #default="{ row }">
            <el-tag :type="getChannelTag(row.type)" effect="light">
              {{ formatChannelName(row.type) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="variables" :label="t('notifications.variables')" min-width="220">
          <template #default="{ row }">
            <div class="variables-list">
              <el-tag
                v-for="variable in (row.variables || [])"
                :key="variable"
                size="small"
                type="info"
                class="var-chip"
              >
                {{ '{' + variable + '}' }}
              </el-tag>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="is_active" :label="t('common.status')" width="110" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="toggleTemplateStatus(row)"
              active-color="#10b981"
            />
          </template>
        </el-table-column>

        <el-table-column :label="t('common.actions')" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <div class="actions-group">
              <el-tooltip :content="t('notifications.preview')" placement="top">
                <el-button size="small" circle :icon="View" @click="previewTemplate(row)" />
              </el-tooltip>
              <el-tooltip :content="t('common.edit')" placement="top">
                <el-button size="small" circle type="primary" :icon="Edit" @click="editTemplate(row)" />
              </el-tooltip>
              <el-tooltip :content="t('common.delete')" placement="top">
                <el-button size="small" circle type="danger" :icon="Delete" @click="deleteTemplate(row)" />
              </el-tooltip>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create / Edit Dialog -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingTemplate ? t('notifications.edit_template') : t('notifications.create_template')"
      width="720px"
      destroy-on-close
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-position="top"
        v-loading="saving"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('notifications.template_key')" prop="template_key">
              <el-input
                v-model="form.template_key"
                :placeholder="t('notifications.template_key_placeholder')"
                :disabled="!!editingTemplate"
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

        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('notifications.name_ar')" prop="name_ar">
              <el-input v-model="form.name_ar" placeholder="مثال: تأكيد طلب المبيعات" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('notifications.name')" prop="name">
              <el-input v-model="form.name" placeholder="e.g. Sales Order Confirmation" />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- Subjects (for email) -->
        <template v-if="form.type === 'email'">
          <el-row :gutter="16">
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

        <!-- Variables Helper & Tags Picker -->
        <div class="variables-picker-box">
          <span class="picker-label">{{ t('notifications.quick_add_variable') || 'انقر لإدراج المتغير في النص:' }}</span>
          <div class="picker-chips">
            <el-tag
              v-for="varName in commonVariables"
              :key="varName"
              size="small"
              class="clickable-var"
              @click="insertVariable(varName)"
            >
              + {{ '{' + varName + '}' }}
            </el-tag>
          </div>
        </div>

        <!-- Body Templates -->
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('notifications.body_ar')" prop="body_ar">
              <el-input
                ref="bodyArInputRef"
                v-model="form.body_ar"
                type="textarea"
                :rows="5"
                placeholder="محتوى الرسالة بالعربية..."
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('notifications.body')" prop="body">
              <el-input
                ref="bodyInputRef"
                v-model="form.body"
                type="textarea"
                :rows="5"
                placeholder="Message body in English..."
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

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showCreateDialog = false">{{ t('common.cancel') }}</el-button>
          <el-button type="primary" @click="saveTemplate" :loading="saving">
            {{ t('common.save') }}
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- Rich Live Preview Dialog -->
    <el-dialog
      v-model="showPreviewDialog"
      :title="t('notifications.preview')"
      width="640px"
      destroy-on-close
    >
      <div v-if="previewTemplateData" class="preview-container">
        <div class="preview-banner">
          <div class="banner-channel">
            <el-tag :type="getChannelTag(previewTemplateData.type)">
              {{ formatChannelName(previewTemplateData.type) }}
            </el-tag>
            <span class="banner-key font-mono">{{ previewTemplateData.template_key }}</span>
          </div>
        </div>

        <!-- Sample Mock Variables Config -->
        <div class="mock-vars-box">
          <span class="mock-title">{{ t('notifications.mock_data') || 'بيانات المتغيرات التجريبية للمعاينة:' }}</span>
          <div class="mock-inputs">
            <div v-for="varName in (previewTemplateData.variables || ['order_number', 'customer_name'])" :key="varName" class="mock-row">
              <span class="mock-name font-mono">{{ varName }}:</span>
              <el-input v-model="mockValues[varName]" size="small" class="mock-input" />
            </div>
          </div>
        </div>

        <!-- Rendered Result -->
        <el-tabs v-model="previewLang" class="preview-tabs">
          <el-tab-pane label="العربية (Arabic)" name="ar">
            <div class="preview-bubble rtl">
              <h4 v-if="previewTemplateData.subject_ar || previewTemplateData.subject" class="bubble-subject">
                {{ interpolate(previewTemplateData.subject_ar || previewTemplateData.subject) }}
              </h4>
              <p class="bubble-body">
                {{ interpolate(previewTemplateData.body_ar || previewTemplateData.body) }}
              </p>
            </div>
          </el-tab-pane>

          <el-tab-pane label="English" name="en">
            <div class="preview-bubble ltr">
              <h4 v-if="previewTemplateData.subject" class="bubble-subject">
                {{ interpolate(previewTemplateData.subject) }}
              </h4>
              <p class="bubble-body">
                {{ interpolate(previewTemplateData.body) }}
              </p>
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
import { Document, Plus, Search, Edit, View, Delete, Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import notificationsService from '@/services/notifications';

const { t, locale } = useI18n();
const currentLocale = computed(() => locale.value);

const loading = ref(false);
const saving = ref(false);
const showCreateDialog = ref(false);
const showPreviewDialog = ref(false);
const editingTemplate = ref(null);
const previewTemplateData = ref(null);
const previewLang = ref('ar');

const templates = ref([]);
const mockValues = ref({
  order_number: 'SO-000142',
  customer_name: 'شركة الأمل للتجارة',
  product_name: 'خلاط مغسلة شك قصير',
  current_stock: '2',
  min_stock: '10',
  tracking_number: 'TRK-987654',
  reason: 'طلب من العميل',
  name: 'أحمد العلبي',
  zone: 'A-12',
  warehouse_name: 'المستودع الرئيسي',
  reset_link: 'https://example.com/reset-password',
});

const commonVariables = [
  'order_number',
  'customer_name',
  'product_name',
  'current_stock',
  'min_stock',
  'tracking_number',
  'amount',
  'name',
];

const filters = ref({
  type: '',
  is_active: '',
});

const formRef = ref(null);
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
  is_active: true,
});

const formRules = {
  template_key: [
    { required: true, message: t('notifications.template_key_required'), trigger: 'blur' },
    { pattern: /^[a-z0-9_]+$/, message: 'يجب أن يحتوي الرمز على أحرف صغيرة وأرقام و _ فقط', trigger: 'blur' },
  ],
  name: [{ required: true, message: t('notifications.name_required'), trigger: 'blur' }],
  type: [{ required: true, message: t('notifications.type_required'), trigger: 'change' }],
  body: [{ required: true, message: t('notifications.body_required'), trigger: 'blur' }],
};

const filteredTemplates = computed(() => {
  return templates.value.filter((tmpl) => {
    if (filters.value.type && tmpl.type !== filters.value.type) return false;
    if (filters.value.is_active !== '' && tmpl.is_active !== Boolean(filters.value.is_active)) return false;
    return true;
  });
});

const loadTemplates = async () => {
  loading.value = true;
  try {
    const res = await notificationsService.getTemplates();
    templates.value = res.data || [];
  } catch (error) {
    ElMessage.error(t('common.load_error'));
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.value = { type: '', is_active: '' };
};

const openCreateDialog = () => {
  editingTemplate.value = null;
  form.value = {
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
  };
  showCreateDialog.value = true;
};

const editTemplate = (template) => {
  editingTemplate.value = template;
  form.value = {
    ...template,
    variables_str: (template.variables || []).join(', '),
  };
  showCreateDialog.value = true;
};

const insertVariable = (varName) => {
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
  const currentVars = form.value.variables_str ? form.value.variables_str.split(',').map((v) => v.trim()) : [];
  if (!currentVars.includes(varName)) {
    currentVars.push(varName);
    form.value.variables_str = currentVars.join(', ');
  }
};

const saveTemplate = async () => {
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
        if (editingTemplate.value) {
          await notificationsService.updateTemplate(editingTemplate.value.id, payload);
          ElMessage.success(t('common.update_success'));
        } else {
          await notificationsService.createTemplate(payload);
          ElMessage.success(t('common.create_success'));
        }
        showCreateDialog.value = false;
        await loadTemplates();
      } catch (error) {
        ElMessage.error(t('common.save_error'));
      } finally {
        saving.value = false;
      }
    }
  });
};

const toggleTemplateStatus = async (template) => {
  try {
    await notificationsService.updateTemplate(template.id, {
      is_active: template.is_active,
    });
    ElMessage.success(t('common.update_success'));
  } catch (error) {
    template.is_active = !template.is_active;
    ElMessage.error(t('common.save_error'));
  }
};

const deleteTemplate = async (template) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning',
      confirmButtonText: t('common.delete'),
      cancelButtonText: t('common.cancel'),
    });
    await notificationsService.deleteTemplate(template.id);
    ElMessage.success(t('common.delete_success'));
    await loadTemplates();
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(t('common.delete_error'));
  }
};

const previewTemplate = (template) => {
  previewTemplateData.value = template;
  showPreviewDialog.value = true;
};

const interpolate = (text) => {
  if (!text) return '';
  let res = text;
  Object.keys(mockValues.value).forEach((key) => {
    res = res.replaceAll(`{${key}}`, mockValues.value[key] || `{${key}}`);
  });
  return res;
};

const getChannelTag = (type) => {
  switch (type) {
    case 'email': return 'primary';
    case 'sms': return 'warning';
    case 'push': return 'success';
    case 'in_app': return 'info';
    default: return 'info';
  }
};

const formatChannelName = (type) => {
  switch (type) {
    case 'email': return t('notifications.email');
    case 'sms': return t('notifications.sms');
    case 'push': return t('notifications.push');
    case 'in_app': return t('notifications.in_app');
    default: return type;
  }
};

onMounted(() => {
  loadTemplates();
});
</script>

<style scoped>
.notification-templates-page {
  padding: 1.5rem;
}

.main-card {
  border-radius: 14px;
}

.filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 10px;
}

.filter-inputs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.filter-select {
  width: 170px;
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
}

.templates-table {
  border-radius: 8px;
}

.name-cell {
  display: flex;
  flex-direction: column;
}

.variables-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.var-chip {
  font-family: monospace;
}

.actions-group {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.variables-picker-box {
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.picker-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.4rem;
}

.picker-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.clickable-var {
  cursor: pointer;
  transition: all 0.15s ease;
}

.clickable-var:hover {
  background: #dbeafe;
  color: #1d4ed8;
  border-color: #93c5fd;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

/* Preview Modal */
.preview-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.preview-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.banner-channel {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.banner-key {
  color: #475569;
  font-size: 0.88rem;
}

.mock-vars-box {
  padding: 0.85rem;
  background: #f1f5f9;
  border-radius: 8px;
}

.mock-title {
  display: block;
  font-size: 0.76rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 0.5rem;
}

.mock-inputs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 0.5rem;
}

.mock-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.mock-name {
  font-size: 0.72rem;
  color: #64748b;
  width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mock-input {
  flex: 1;
}

.preview-bubble {
  padding: 1.25rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.bubble-subject {
  margin: 0 0 0.75rem 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 0.5rem;
}

.bubble-body {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.65;
  color: #334155;
  white-space: pre-wrap;
}
</style>
