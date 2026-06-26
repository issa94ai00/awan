<template>
  <div class="module-logs-page">
    <div class="page-header">
      <h1><el-icon><Folder /></el-icon> {{ $t('audit.module_logs') }} - {{ moduleName }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('audit.action')">
          <el-select v-model="filters.action" :placeholder="$t('audit.select_action')" clearable @change="loadModuleLogs">
            <el-option value="create" :label="$t('audit.create')" />
            <el-option value="update" :label="$t('audit.update')" />
            <el-option value="delete" :label="$t('audit.delete')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('common.date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="$t('common.to')"
            :start-placeholder="$t('common.start_date')"
            :end-placeholder="$t('common.end_date')"
            @change="loadModuleLogs"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadModuleLogs">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="moduleLogs" v-loading="loading" stripe>
        <el-table-column prop="id" :label="$t('audit.id')" width="80" />
        <el-table-column prop="user" :label="$t('audit.user')" width="150" />
        <el-table-column prop="action" :label="$t('audit.action')" width="100">
          <template #default="{ row }">
            <el-tag :type="getActionType(row.action)">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" :label="$t('audit.description')" show-overflow-tooltip />
        <el-table-column prop="ip_address" :label="$t('audit.ip_address')" width="120" />
        <el-table-column prop="created_at" :label="$t('common.created_at')" width="180" />
        <el-table-column :label="$t('common.actions')" width="100">
          <template #default="{ row }">
            <el-button size="small" @click="viewDetails(row)">
              <el-icon><View /></el-icon>
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="loadModuleLogs"
        @current-change="loadModuleLogs"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Details Dialog -->
    <el-dialog v-model="showDetailsDialog" :title="$t('audit.change_details')" width="800px">
      <el-descriptions :column="1" border>
        <el-descriptions-item :label="$t('audit.user')">{{ selectedLog.user }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.action')">{{ selectedLog.action }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.description')">{{ selectedLog.description }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.ip_address')">{{ selectedLog.ip_address }}</el-descriptions-item>
        <el-descriptions-item :label="$t('common.created_at')">{{ selectedLog.created_at }}</el-descriptions-item>
      </el-descriptions>
      <el-divider />
      <h4>{{ $t('audit.changes') }}</h4>
      <el-table :data="selectedLog.changes || []" max-height="300">
        <el-table-column prop="field" :label="$t('audit.field')" />
        <el-table-column prop="old_value" :label="$t('audit.old_value')" />
        <el-table-column prop="new_value" :label="$t('audit.new_value')" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Folder, Back, Search, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const showDetailsDialog = ref(false)
const moduleName = ref('')
const moduleLogs = ref([])
const selectedLog = ref({})
const filters = ref({
  action: ''
})
const dateRange = ref([])
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

const getActionType = (action) => {
  const types = {
    create: 'success',
    update: 'warning',
    delete: 'danger'
  }
  return types[action] || 'info'
}

const loadModuleLogs = async () => {
  loading.value = true
  try {
    // const response = await api.get(`/api/v1/audit/module-logs/${route.params.module}`, {
    //   params: { ...filters.value, start_date: dateRange.value[0], end_date: dateRange.value[1], ...pagination.value }
    // })
    moduleName.value = route.params.module
    moduleLogs.value = [
      { id: 1, user: 'Admin User', action: 'create', description: 'Created product A', ip_address: '192.168.1.1', created_at: '2026-06-23 10:30' },
      { id: 2, user: 'Warehouse Manager', action: 'update', description: 'Updated product B', ip_address: '192.168.1.2', created_at: '2026-06-23 09:45' }
    ]
    pagination.value.total = 100
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const viewDetails = async (log) => {
  try {
    // const response = await api.get(`/api/v1/audit/${log.id}`)
    selectedLog.value = {
      ...log,
      changes: [
        { field: 'name', old_value: 'Old Name', new_value: 'New Name' },
        { field: 'price', old_value: '100', new_value: '150' }
      ]
    }
    showDetailsDialog.value = true
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  }
}

onMounted(() => {
  loadModuleLogs()
})
</script>

<style scoped>
.module-logs-page {
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
