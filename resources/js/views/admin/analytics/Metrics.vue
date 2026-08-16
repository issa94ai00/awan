<template>
  <div class="metrics-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-gauge-high"
      :title="$t('analytics.metrics')"
      :subtitle="$t('analytics.metrics_subtitle')"
    >
      <template #actions>
        <el-button :loading="loading" @click="loadMetrics">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ $t('refresh') }}
        </el-button>
        <el-button type="primary" @click="openCreate">
          <el-icon class="mr-1"><Plus /></el-icon>{{ $t('analytics.create_metric') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-4" />

    <el-card shadow="never">
      <el-table :data="metrics" v-loading="loading" stripe :empty-text="$t('analytics.no_metrics')">
        <el-table-column prop="name" :label="$t('analytics.name')" min-width="200">
          <template #default="{ row }">
            <strong>{{ localizedName(row) }}</strong>
            <div v-if="row.description" class="row-sub">{{ row.description }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="metric_key" :label="$t('analytics.code')" width="180">
          <template #default="{ row }"><code class="metric-key">{{ row.metric_key }}</code></template>
        </el-table-column>
        <el-table-column :label="$t('analytics.category')" width="140">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ $t(`analytics.category_${row.category}`) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('analytics.data_type')" width="140">
          <template #default="{ row }">{{ $t(`analytics.data_type_${row.data_type}`) }}</template>
        </el-table-column>
        <el-table-column :label="$t('analytics.aggregation')" width="130">
          <template #default="{ row }">{{ $t(`analytics.aggregation_${row.aggregation}`) }}</template>
        </el-table-column>
        <el-table-column :label="$t('status')" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? $t('active') : $t('inactive') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('actions')" width="130" align="center" fixed="right">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="openEdit(row)"><el-icon><Edit /></el-icon></el-button>
              <el-button size="small" type="danger" @click="removeMetric(row)"><el-icon><Delete /></el-icon></el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="editing ? $t('analytics.edit_metric') : $t('analytics.create_metric')"
      width="620px"
      destroy-on-close
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
        <el-row :gutter="16">
          <el-col :xs="24" :sm="12">
            <el-form-item :label="$t('analytics.name')" prop="name">
              <el-input v-model="form.name" />
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="12">
            <el-form-item :label="$t('analytics.name_ar')">
              <el-input v-model="form.name_ar" />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- The identifier the metric is referenced by; immutable once created. -->
        <el-form-item :label="$t('analytics.code')" prop="metric_key">
          <el-input v-model="form.metric_key" :disabled="!!editing" placeholder="revenue_growth" />
          <span class="field-hint">{{ $t('analytics.code_hint') }}</span>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :xs="24" :sm="8">
            <el-form-item :label="$t('analytics.category')" prop="category">
              <el-select v-model="form.category" style="width: 100%">
                <el-option v-for="c in CATEGORIES" :key="c" :value="c" :label="$t(`analytics.category_${c}`)" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="8">
            <el-form-item :label="$t('analytics.data_type')" prop="data_type">
              <el-select v-model="form.data_type" style="width: 100%">
                <el-option v-for="d in DATA_TYPES" :key="d" :value="d" :label="$t(`analytics.data_type_${d}`)" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="8">
            <el-form-item :label="$t('analytics.aggregation')" prop="aggregation">
              <el-select v-model="form.aggregation" style="width: 100%">
                <el-option v-for="a in AGGREGATIONS" :key="a" :value="a" :label="$t(`analytics.aggregation_${a}`)" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item :label="$t('analytics.description')">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>

        <el-form-item>
          <el-switch v-model="form.is_active" :active-text="$t('active')" :inactive-text="$t('inactive')" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="save">{{ $t('save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, Edit, Delete, Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import analyticsApi from '@/api/analytics';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

/**
 * KPI metric definitions.
 *
 * Every action on this screen used to be theatre: the create, update and delete
 * handlers popped a success toast with the API call sitting next to them as a
 * comment. An admin defined a metric, was told it saved, and it never existed.
 *
 * The form was wrong as well as disconnected — it collected `code`, `type` and
 * `target`, none of which the endpoint accepts. It now matches the validator in
 * `AnalyticsController::storeMetric`, and no message claims success before the
 * server has confirmed it.
 */

const { t, locale } = useI18n();

/** Mirrors the `in:` rules on the server; a mismatch here is a 422 there. */
const CATEGORIES = ['sales', 'inventory', 'warehouse', 'financial', 'customer', 'operational'];
const DATA_TYPES = ['number', 'percentage', 'currency', 'count', 'duration'];
const AGGREGATIONS = ['sum', 'avg', 'count', 'min', 'max', 'last'];

const metrics = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const dialogVisible = ref(false);
const editing = ref(null);
const formRef = ref(null);

const emptyForm = () => ({
  metric_key: '',
  name: '',
  name_ar: '',
  description: '',
  category: 'sales',
  data_type: 'number',
  aggregation: 'sum',
  is_active: true,
});

const form = reactive(emptyForm());

const rules = {
  name: [{ required: true, message: () => t('required_field'), trigger: 'blur' }],
  metric_key: [
    { required: true, message: () => t('required_field'), trigger: 'blur' },
    {
      pattern: /^[a-z0-9_]+$/,
      message: () => t('analytics.code_hint'),
      trigger: 'blur',
    },
  ],
  category: [{ required: true, message: () => t('required_field'), trigger: 'change' }],
  data_type: [{ required: true, message: () => t('required_field'), trigger: 'change' }],
  aggregation: [{ required: true, message: () => t('required_field'), trigger: 'change' }],
};

const localizedName = (row) => (
  (locale.value === 'ar' ? (row.name_ar || row.name) : (row.name || row.name_ar)) || row.metric_key
);

const loadMetrics = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await analyticsApi.metrics();
    metrics.value = data?.data ?? data ?? [];
  } catch (err) {
    error.value = err?.response?.data?.message || t('analytics.load_failed');
    metrics.value = [];
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editing.value = null;
  Object.assign(form, emptyForm());
  dialogVisible.value = true;
};

const openEdit = (metric) => {
  editing.value = metric;
  Object.assign(form, { ...emptyForm(), ...metric, is_active: !!metric.is_active });
  dialogVisible.value = true;
};

const save = async () => {
  if (!formRef.value) return;

  try {
    await formRef.value.validate();
  } catch {
    return; // Element Plus has already marked the offending fields.
  }

  saving.value = true;
  try {
    if (editing.value) {
      await analyticsApi.updateMetric(editing.value.id, form);
      ElMessage.success(t('saved_successfully'));
    } else {
      await analyticsApi.createMetric(form);
      ElMessage.success(t('saved_successfully'));
    }

    dialogVisible.value = false;
    editing.value = null;
    await loadMetrics();
  } catch (err) {
    // Surfaces the server's own validation message rather than a generic one,
    // so a rejected metric_key says which field is at fault.
    const validation = err?.response?.data?.errors;
    const firstError = validation ? Object.values(validation)[0]?.[0] : null;

    ElMessage.error(firstError || err?.response?.data?.message || t('an_error_occurred_while_saving'));
  } finally {
    saving.value = false;
  }
};

const removeMetric = async (metric) => {
  try {
    await ElMessageBox.confirm(
      t('are_you_sure_you_want'),
      t('confirm_deletion'),
      { type: 'warning', confirmButtonText: t('yes'), cancelButtonText: t('no') }
    );
  } catch {
    return; // Dismissed.
  }

  try {
    await analyticsApi.deleteMetric(metric.id);
    ElMessage.success(t('deleted_successfully'));
    await loadMetrics();
  } catch (err) {
    ElMessage.error(err?.response?.data?.message || t('an_error_occurred_while_saving'));
  }
};

onMounted(loadMetrics);
</script>

<style scoped>
.metrics-page {
  padding: 0;
}

.row-sub {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-top: 0.15rem;
}

.metric-key {
  background: #f1f5f9;
  border-radius: 6px;
  padding: 0.15rem 0.45rem;
  font-size: 0.8rem;
  color: #475569;
}

.field-hint {
  font-size: 0.78rem;
  color: #94a3b8;
}

.mr-1 {
  margin-inline-end: 0.25rem;
}

.mb-4 {
  margin-bottom: 1.5rem;
}
</style>
