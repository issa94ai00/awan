<template>
  <div class="notifications-page">
    <div class="page-header">
      <h1><el-icon><Bell /></el-icon> {{ $t('notifications.title') }}</h1>
      <div class="header-actions">
        <el-button @click="markAllAsRead" :disabled="unreadCount === 0">
          <el-icon><Check /></el-icon> {{ $t('notifications.mark_all_read') }}
        </el-button>
        <el-button type="primary" @click="showSendDialog = true">
          <el-icon><Plus /></el-icon> {{ $t('notifications.send_notification') }}
        </el-button>
      </div>
    </div>

    <!-- Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Bell /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ totalCount }}</h3>
              <p>{{ $t('notifications.total') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Message /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ unreadCount }}</h3>
              <p>{{ $t('notifications.unread') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Check /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ readCount }}</h3>
              <p>{{ $t('notifications.read') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Filters -->
    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('notifications.type')">
          <el-select v-model="filters.type" :placeholder="$t('notifications.select_type')" clearable @change="loadNotifications">
            <el-option value="info" :label="$t('notifications.info')" />
            <el-option value="success" :label="$t('notifications.success')" />
            <el-option value="warning" :label="$t('notifications.warning')" />
            <el-option value="error" :label="$t('notifications.error')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('notifications.status')">
          <el-select v-model="filters.status" :placeholder="$t('notifications.select_status')" clearable @change="loadNotifications">
            <el-option value="read" :label="$t('notifications.read')" />
            <el-option value="unread" :label="$t('notifications.unread')" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadNotifications">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="notifications" v-loading="loading" stripe>
        <el-table-column :label="$t('notifications.status')" width="80">
          <template #default="{ row }">
            <el-icon v-if="!row.read_at" color="#409eff"><Circle /></el-icon>
            <el-icon v-else color="#909399"><CircleCheck /></el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="title" :label="$t('notifications.title')" />
        <el-table-column prop="message" :label="$t('notifications.message')" show-overflow-tooltip />
        <el-table-column prop="type" :label="$t('notifications.type')" width="100">
          <template #default="{ row }">
            <el-tag :type="getTypeColor(row.type)">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="$t('common.created_at')" width="180" />
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewNotification(row)" :disabled="!row.read_at">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" @click="markAsRead(row)" :disabled="!!row.read_at">
                <el-icon><Check /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteNotification(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="loadNotifications"
        @current-change="loadNotifications"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Send Dialog -->
    <el-dialog v-model="showSendDialog" :title="$t('notifications.send_notification')" width="600px">
      <el-form :model="sendForm" label-width="120px">
        <el-form-item :label="$t('notifications.recipient')">
          <el-select v-model="sendForm.user_id" :placeholder="$t('notifications.select_user')" filterable>
            <el-option v-for="user in users" :key="user.id" :value="user.id" :label="user.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('notifications.title')">
          <el-input v-model="sendForm.title" />
        </el-form-item>
        <el-form-item :label="$t('notifications.message')">
          <el-input v-model="sendForm.message" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item :label="$t('notifications.type')">
          <el-select v-model="sendForm.type">
            <el-option value="info" :label="$t('notifications.info')" />
            <el-option value="success" :label="$t('notifications.success')" />
            <el-option value="warning" :label="$t('notifications.warning')" />
            <el-option value="error" :label="$t('notifications.error')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('notifications.channels')">
          <el-checkbox-group v-model="sendForm.channels">
            <el-checkbox value="in_app">{{ $t('notifications.in_app') }}</el-checkbox>
            <el-checkbox value="email">{{ $t('notifications.email') }}</el-checkbox>
            <el-checkbox value="sms">{{ $t('notifications.sms') }}</el-checkbox>
            <el-checkbox value="push">{{ $t('notifications.push') }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSendDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="sendNotification" :loading="sending">
          {{ $t('notifications.send') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Bell, Plus, Check, Message, Search, View, Delete, CircleCheck } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showSendDialog = ref(false)
const notifications = ref([])
const users = ref([])
const totalCount = ref(0)
const unreadCount = ref(0)
const readCount = ref(0)
const filters = ref({
  type: '',
  status: ''
})
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})
const sendForm = ref({
  user_id: null,
  title: '',
  message: '',
  type: 'info',
  channels: ['in_app']
})

const getTypeColor = (type) => {
  const colors = {
    info: 'primary',
    success: 'success',
    warning: 'warning',
    error: 'danger'
  }
  return colors[type] || 'info'
}

const loadUsers = async () => {
  try {
    // await api.get('/api/v1/users')
    users.value = [
      { id: 1, name: 'Admin User' },
      { id: 2, name: 'Warehouse Manager' }
    ]
  } catch (error) {
    console.error('Failed to load users:', error)
  }
}

const loadNotifications = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/notifications', { params: { ...filters.value, ...pagination.value } })
    // notifications.value = response.data.data
    // pagination.value.total = response.data.total
    notifications.value = [
      { id: 1, title: 'New Order Received', message: 'Order #SO-000001 has been received', type: 'info', read_at: null, created_at: '2026-06-23 10:30' },
      { id: 2, title: 'Low Stock Alert', message: 'Product A is running low on stock', type: 'warning', read_at: '2026-06-23 09:00', created_at: '2026-06-23 09:00' }
    ]
    pagination.value.total = 50
    totalCount.value = 50
    unreadCount.value = 25
    readCount.value = 25
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const markAsRead = async (notification) => {
  try {
    // await api.post(`/api/v1/notifications/${notification.id}/read`)
    ElMessage.success(t('notifications.marked_read'))
    await loadNotifications()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const markAllAsRead = async () => {
  try {
    // await api.post('/api/v1/notifications/mark-all-read')
    ElMessage.success(t('notifications.all_marked_read'))
    await loadNotifications()
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const viewNotification = (notification) => {
  // Show notification detail
}

const deleteNotification = async (notification) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/notifications/${notification.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadNotifications()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

const sendNotification = async () => {
  saving.value = true
  try {
    // await api.post('/api/v1/notifications/send', sendForm.value)
    ElMessage.success(t('notifications.sent_successfully'))
    showSendDialog.value = false
    sendForm.value = {
      user_id: null,
      title: '',
      message: '',
      type: 'info',
      channels: ['in_app']
    }
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadUsers()
  loadNotifications()
})
</script>

<style scoped>
.notifications-page {
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

.header-actions {
  display: flex;
  gap: 10px;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  margin-bottom: 20px;
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.stat-icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }

.stat-info h3 {
  margin: 0 0 5px 0;
  font-size: 24px;
  font-weight: 600;
  color: #333;
}

.stat-info p {
  margin: 0;
  font-size: 14px;
  color: #666;
}

.filter-form {
  margin-bottom: 20px;
}
</style>
