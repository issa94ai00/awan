<template>
  <div class="user-activity-page">
    <div class="page-header">
      <h1><el-icon><User /></el-icon> {{ $t('audit.user_activity') }} - {{ userName }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Document /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.total_actions }}</h3>
              <p>{{ $t('audit.total_actions') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Calendar /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.last_active }}</h3>
              <p>{{ $t('audit.last_active') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="8">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Clock /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.avg_daily }}</h3>
              <p>{{ $t('audit.avg_daily') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>{{ $t('audit.activity_timeline') }}</span>
          <el-form :inline="true">
            <el-form-item :label="$t('common.date_range')">
              <el-date-picker
                v-model="dateRange"
                type="daterange"
                :range-separator="$t('common.to')"
                :start-placeholder="$t('common.start_date')"
                :end-placeholder="$t('common.end_date')"
                @change="loadActivity"
              />
            </el-form-item>
          </el-form>
        </div>
      </template>
      <el-timeline>
        <el-timeline-item
          v-for="activity in activities"
          :key="activity.id"
          :timestamp="activity.created_at"
          :type="getTimelineType(activity.action)"
        >
          <el-card>
            <h4>{{ activity.action }} - {{ activity.module }}</h4>
            <p>{{ activity.description }}</p>
            <small>{{ $t('audit.ip_address') }}: {{ activity.ip_address }}</small>
          </el-card>
        </el-timeline-item>
      </el-timeline>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="loadActivity"
        @current-change="loadActivity"
        style="margin-top: 20px"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { User, Back, Document, Calendar, Clock } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const userName = ref('')
const stats = ref({
  total_actions: 0,
  last_active: '-',
  avg_daily: 0
})
const activities = ref([])
const dateRange = ref([])
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

const getTimelineType = (action) => {
  const types = {
    create: 'success',
    update: 'warning',
    delete: 'danger',
    login: 'primary',
    logout: 'info'
  }
  return types[action] || 'primary'
}

const loadUser = async () => {
  try {
    // await api.get(`/api/v1/users/${route.params.id}`)
    userName.value = 'Admin User'
  } catch (error) {
    console.error('Failed to load user:', error)
  }
}

const loadActivity = async () => {
  loading.value = true
  try {
    // const response = await api.get(`/api/v1/audit/user-summary/${route.params.id}`, {
    //   params: { start_date: dateRange.value[0], end_date: dateRange.value[1], ...pagination.value }
    // })
    stats.value = {
      total_actions: 150,
      last_active: '2026-06-23 10:30',
      avg_daily: 25
    }
    activities.value = [
      { id: 1, action: 'create', module: 'products', description: 'Created product A', ip_address: '192.168.1.1', created_at: '2026-06-23 10:30' },
      { id: 2, action: 'update', module: 'inventory', description: 'Updated stock for product B', ip_address: '192.168.1.1', created_at: '2026-06-23 09:45' }
    ]
    pagination.value.total = 150
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadUser()
  loadActivity()
})
</script>

<style scoped>
.user-activity-page {
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
.stat-icon-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

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

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
