<template>
  <div class="notification-preferences-page">
    <div class="page-header">
      <h1><el-icon><Setting /></el-icon> {{ $t('notifications.preferences') }}</h1>
    </div>

    <el-card>
      <el-table :data="preferences" v-loading="loading" stripe>
        <el-table-column prop="notification_type" :label="$t('notifications.notification_type')" />
        <el-table-column :label="$t('notifications.email')" width="100">
          <template #default="{ row }">
            <el-switch v-model="row.email" @change="savePreference(row)" />
          </template>
        </el-table-column>
        <el-table-column :label="$t('notifications.sms')" width="100">
          <template #default="{ row }">
            <el-switch v-model="row.sms" @change="savePreference(row)" />
          </template>
        </el-table-column>
        <el-table-column :label="$t('notifications.push')" width="100">
          <template #default="{ row }">
            <el-switch v-model="row.push" @change="savePreference(row)" />
          </template>
        </el-table-column>
        <el-table-column :label="$t('notifications.in_app')" width="100">
          <template #default="{ row }">
            <el-switch v-model="row.in_app" @change="savePreference(row)" />
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Setting } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const preferences = ref([])

const loadPreferences = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/notifications/preferences')
    preferences.value = [
      { id: 1, notification_type: t('notifications.order_updates'), email: true, sms: false, push: true, in_app: true },
      { id: 2, notification_type: t('notifications.inventory_alerts'), email: true, sms: true, push: true, in_app: true },
      { id: 3, notification_type: t('notifications.payment_notifications'), email: true, sms: false, push: false, in_app: true },
      { id: 4, notification_type: t('notifications.system_messages'), email: false, sms: false, push: false, in_app: true }
    ]
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const savePreference = async (preference) => {
  try {
    // await api.put(`/api/v1/notifications/preferences/${preference.id}`, preference)
    ElMessage.success(t('common.update_success'))
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  }
}

onMounted(() => {
  loadPreferences()
})
</script>

<style scoped>
.notification-preferences-page {
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
