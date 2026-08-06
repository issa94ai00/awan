<template>
  <div class="dashboards-page">
    <div class="page-header">
      <h1><el-icon><DataBoard /></el-icon> {{ $t('analytics.dashboards') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('analytics.create_dashboard') }}
      </el-button>
    </div>

    <el-row :gutter="20">
      <el-col :xs="24" :sm="12" :md="8" v-for="dashboard in dashboards" :key="dashboard.id">
        <el-card class="dashboard-card" @click="viewDashboard(dashboard)">
          <div class="dashboard-icon">
            <el-icon><DataBoard /></el-icon>
          </div>
          <h3>{{ dashboard.name }}</h3>
          <p>{{ dashboard.type }}</p>
          <div class="dashboard-meta">
            <span>{{ dashboard.widgets_count }} {{ $t('analytics.widgets') }}</span>
            <span>{{ dashboard.updated_at }}</span>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Create Dialog -->
    <el-dialog v-model="showCreateDialog" :title="$t('analytics.create_dashboard')" width="500px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="$t('analytics.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('analytics.type')">
          <el-select v-model="form.type">
            <el-option value="executive" :label="$t('analytics.executive')" />
            <el-option value="sales" :label="$t('analytics.sales')" />
            <el-option value="inventory" :label="$t('analytics.inventory')" />
            <el-option value="financial" :label="$t('analytics.financial')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('analytics.layout')">
          <el-select v-model="form.layout">
            <el-option value="grid" :label="$t('analytics.grid')" />
            <el-option value="list" :label="$t('analytics.list')" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="createDashboard" :loading="saving">
          {{ $t('common.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { DataBoard, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const dashboards = ref([])
const form = ref({
  name: '',
  type: 'executive',
  layout: 'grid'
})

const loadDashboards = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/dashboards')
    dashboards.value = [
      { id: 1, name: 'Executive Dashboard', type: 'executive', widgets_count: 8, updated_at: '2026-06-23' },
      { id: 2, name: 'Sales Dashboard', type: 'sales', widgets_count: 6, updated_at: '2026-06-22' },
      { id: 3, name: 'Inventory Dashboard', type: 'inventory', widgets_count: 5, updated_at: '2026-06-21' }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const createDashboard = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/analytics/dashboards', form.value)
    ElMessage.success(t('common.create_success'))
    showCreateDialog.value = false
    await loadDashboards()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const viewDashboard = (dashboard) => {
  $router.push(`/admin/analytics/dashboards/${dashboard.id}`)
}

onMounted(() => {
  loadDashboards()
})
</script>

<style scoped>
.dashboards-page {
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

.dashboard-card {
  margin-bottom: 20px;
  cursor: pointer;
  transition: all 0.3s;
}

.dashboard-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dashboard-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: white;
  margin-bottom: 15px;
}

.dashboard-card h3 {
  margin: 0 0 10px 0;
  font-size: 18px;
  color: #333;
}

.dashboard-card p {
  margin: 0 0 15px 0;
  font-size: 14px;
  color: #666;
}

.dashboard-meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #999;
}
</style>
