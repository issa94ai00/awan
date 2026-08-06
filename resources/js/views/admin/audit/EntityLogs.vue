<template>
  <div class="entity-logs-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ $t('audit.entity_logs') }}</h1>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('audit.entity_type')">
          <el-input v-model="filters.entity_type" :placeholder="$t('audit.entity_type_placeholder')" clearable @change="loadEntityLogs" />
        </el-form-item>
        <el-form-item :label="$t('audit.entity_id')">
          <el-input v-model="filters.entity_id" :placeholder="$t('audit.entity_id_placeholder')" clearable @change="loadEntityLogs" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadEntityLogs">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="entityLogs" v-loading="loading" stripe>
        <el-table-column prop="id" :label="$t('audit.id')" width="80" />
        <el-table-column prop="entity_type" :label="$t('audit.entity_type')" width="200" />
        <el-table-column prop="entity_id" :label="$t('audit.entity_id')" width="100" />
        <el-table-column prop="action" :label="$t('audit.action')" width="100">
          <template #default="{ row }">
            <el-tag :type="getActionType(row.action)">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="user" :label="$t('audit.user')" width="150" />
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
        @size-change="loadEntityLogs"
        @current-change="loadEntityLogs"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Details Dialog -->
    <el-dialog v-model="showDetailsDialog" :title="$t('audit.change_details')" width="800px">
      <el-descriptions :column="1" border>
        <el-descriptions-item :label="$t('audit.entity_type')">{{ selectedLog.entity_type }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.entity_id')">{{ selectedLog.entity_id }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.action')">{{ selectedLog.action }}</el-descriptions-item>
        <el-descriptions-item :label="$t('audit.user')">{{ selectedLog.user }}</el-descriptions-item>
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
import { Document, Search, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const showDetailsDialog = ref(false)
const entityLogs = ref([])
const selectedLog = ref({})
const filters = ref({
  entity_type: '',
  entity_id: ''
})
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

const loadEntityLogs = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/audit/entity-logs', {
    //   params: { ...filters.value, ...pagination.value }
    // })
    entityLogs.value = [
      { id: 1, entity_type: 'App\\Models\\Product', entity_id: 1, action: 'update', user: 'Admin User', created_at: '2026-06-23 10:30' },
      { id: 2, entity_type: 'App\\Models\\Order', entity_id: 5, action: 'create', user: 'Warehouse Manager', created_at: '2026-06-23 09:45' }
    ]
    pagination.value.total = 200
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
  loadEntityLogs()
})
</script>

<style scoped>
.entity-logs-page {
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
