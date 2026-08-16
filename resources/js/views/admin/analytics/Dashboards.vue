<template>
  <div class="dashboards-page">
    <AdminPageHeader
      badge="BI"
      icon="fas fa-table-columns"
      :title="$t('analytics.dashboards')"
      :subtitle="$t('analytics.dashboards_subtitle')"
    >
      <template #actions>
        <el-button :loading="loading" @click="loadDashboards">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ $t('refresh') }}
        </el-button>
        <el-button type="primary" @click="openCreate">
          <el-icon class="mr-1"><Plus /></el-icon>{{ $t('analytics.create_dashboard') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-4" />

    <el-skeleton v-if="loading" animated :rows="6" />

    <el-empty v-else-if="!dashboards.length" :description="$t('analytics.no_dashboards')">
      <el-button type="primary" @click="openCreate">{{ $t('analytics.create_dashboard') }}</el-button>
    </el-empty>

    <div v-else class="dashboard-grid">
      <el-card v-for="board in dashboards" :key="board.id" shadow="hover" class="dashboard-card">
        <div class="dashboard-card__head">
          <el-tag size="small" effect="plain">{{ $t(`analytics.type_${board.type}`) }}</el-tag>
          <el-tag v-if="board.is_default" size="small" type="success" effect="plain">
            {{ $t('analytics.default_dashboard') }}
          </el-tag>
        </div>

        <h3 class="dashboard-card__title">{{ localizedName(board) }}</h3>
        <p class="dashboard-card__desc">{{ board.description || '—' }}</p>

        <div class="dashboard-card__meta">
          <span>
            <el-icon><View /></el-icon>
            {{ board.is_public ? $t('analytics.public_dashboard') : $t('analytics.private') }}
          </span>
          <span v-if="board.widgets_count !== undefined">
            <el-icon><Grid /></el-icon>
            {{ board.widgets_count }} {{ $t('analytics.widgets') }}
          </span>
        </div>

        <div class="dashboard-card__actions">
          <el-button size="small" @click="openEdit(board)">
            <el-icon class="mr-1"><Edit /></el-icon>{{ $t('edit') }}
          </el-button>
          <el-button size="small" type="danger" plain @click="removeDashboard(board)">
            <el-icon class="mr-1"><Delete /></el-icon>{{ $t('delete') }}
          </el-button>
        </div>
      </el-card>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="editing ? $t('analytics.edit_dashboard') : $t('analytics.create_dashboard')"
      width="560px"
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

        <el-form-item :label="$t('analytics.type')" prop="type">
          <el-select v-model="form.type" style="width: 100%">
            <el-option v-for="type in TYPES" :key="type" :value="type" :label="$t(`analytics.type_${type}`)" />
          </el-select>
        </el-form-item>

        <el-form-item :label="$t('analytics.description')">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>

        <el-form-item :label="$t('analytics.visibility')">
          <div class="switch-row">
            <el-switch v-model="form.is_public" :active-text="$t('analytics.public_dashboard')" />
            <el-switch v-model="form.is_default" :active-text="$t('analytics.default_dashboard')" />
          </div>
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
import { Plus, Edit, Delete, Refresh, View, Grid } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import analyticsApi from '@/api/analytics';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

/**
 * BI dashboards.
 *
 * Creating one used to announce success without sending anything: the POST was
 * commented out directly above the toast, and the list was a pair of literals.
 * Every action here now waits on the server before reporting anything.
 */

const { t, locale } = useI18n();

/** Mirrors the `in:` rule in `AnalyticsController::storeDashboard`. */
const TYPES = ['executive', 'sales', 'inventory', 'warehouse', 'financial', 'custom'];

const dashboards = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref(null);
const dialogVisible = ref(false);
const editing = ref(null);
const formRef = ref(null);

const emptyForm = () => ({
  name: '',
  name_ar: '',
  description: '',
  type: 'custom',
  is_public: false,
  is_default: false,
});

const form = reactive(emptyForm());

const rules = {
  name: [{ required: true, message: () => t('required_field'), trigger: 'blur' }],
  type: [{ required: true, message: () => t('required_field'), trigger: 'change' }],
};

const localizedName = (board) => (
  (locale.value === 'ar' ? (board.name_ar || board.name) : (board.name || board.name_ar)) || '—'
);

const loadDashboards = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await analyticsApi.dashboards();
    // The endpoint paginates; rows live under `data`.
    dashboards.value = data?.data ?? data ?? [];
  } catch (err) {
    error.value = err?.response?.data?.message || t('analytics.load_failed');
    dashboards.value = [];
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  editing.value = null;
  Object.assign(form, emptyForm());
  dialogVisible.value = true;
};

const openEdit = (board) => {
  editing.value = board;
  Object.assign(form, {
    ...emptyForm(),
    ...board,
    is_public: !!board.is_public,
    is_default: !!board.is_default,
  });
  dialogVisible.value = true;
};

const save = async () => {
  if (!formRef.value) return;

  try {
    await formRef.value.validate();
  } catch {
    return;
  }

  saving.value = true;
  try {
    if (editing.value) {
      await analyticsApi.updateDashboard(editing.value.id, form);
    } else {
      await analyticsApi.createDashboard(form);
    }

    ElMessage.success(t('saved_successfully'));
    dialogVisible.value = false;
    editing.value = null;
    await loadDashboards();
  } catch (err) {
    const validation = err?.response?.data?.errors;
    const firstError = validation ? Object.values(validation)[0]?.[0] : null;

    ElMessage.error(firstError || err?.response?.data?.message || t('an_error_occurred_while_saving'));
  } finally {
    saving.value = false;
  }
};

const removeDashboard = async (board) => {
  try {
    await ElMessageBox.confirm(
      t('are_you_sure_you_want'),
      t('confirm_deletion'),
      { type: 'warning', confirmButtonText: t('yes'), cancelButtonText: t('no') }
    );
  } catch {
    return;
  }

  try {
    await analyticsApi.deleteDashboard(board.id);
    ElMessage.success(t('deleted_successfully'));
    await loadDashboards();
  } catch (err) {
    ElMessage.error(err?.response?.data?.message || t('an_error_occurred_while_saving'));
  }
};

onMounted(loadDashboards);
</script>

<style scoped>
.dashboards-page {
  padding: 0;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.25rem;
}

.dashboard-card {
  border-radius: 14px;
  display: flex;
  flex-direction: column;
}

.dashboard-card__head {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}

.dashboard-card__title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
  color: #1f2d3d;
}

.dashboard-card__desc {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #64748b;
  min-height: 2.4em;
}

.dashboard-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.9rem;
  font-size: 0.8rem;
  color: #94a3b8;
  margin-bottom: 1rem;
}

.dashboard-card__meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.dashboard-card__actions {
  display: flex;
  gap: 0.5rem;
  margin-top: auto;
}

.switch-row {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.mr-1 {
  margin-inline-end: 0.25rem;
}

.mb-4 {
  margin-bottom: 1.5rem;
}
</style>
