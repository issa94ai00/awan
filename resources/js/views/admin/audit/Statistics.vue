<template>
  <div class="audit-statistics-page">
    <div class="page-header">
      <h1><el-icon><DataBoard /></el-icon> {{ $t('audit.statistics') }}</h1>
      <el-form :inline="true">
        <el-form-item :label="$t('common.date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="$t('common.to')"
            :start-placeholder="$t('common.start_date')"
            :end-placeholder="$t('common.end_date')"
            @change="loadStatistics"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadStatistics">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- Overview Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Document /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.total_logs }}</h3>
              <p>{{ $t('audit.total_logs') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><User /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.active_users }}</h3>
              <p>{{ $t('audit.active_users') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Folder /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.active_modules }}</h3>
              <p>{{ $t('audit.active_modules') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><Warning /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.security_events }}</h3>
              <p>{{ $t('audit.security_events') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Charts -->
    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('audit.activity_by_module') }}</span>
            </div>
          </template>
          <div ref="moduleChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('audit.activity_by_action') }}</span>
            </div>
          </template>
          <div ref="actionChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Top Users -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('audit.top_users') }}</span>
        </div>
      </template>
      <el-table :data="topUsers" v-loading="loading">
        <el-table-column prop="user" :label="$t('audit.user')" />
        <el-table-column prop="actions_count" :label="$t('audit.actions_count')" />
        <el-table-column prop="last_active" :label="$t('audit.last_active')" />
        <el-table-column prop="modules" :label="$t('audit.modules')" width="200">
          <template #default="{ row }">
            <el-tag v-for="module in row.modules" :key="module" size="small" style="margin-right: 5px">
              {{ module }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { DataBoard, Search, Document, User, Folder, Warning } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const dateRange = ref([])
const stats = ref({
  total_logs: 0,
  active_users: 0,
  active_modules: 0,
  security_events: 0
})
const topUsers = ref([])
const moduleChartRef = ref(null)
const actionChartRef = ref(null)
let moduleChart = null
let actionChart = null

const loadStatistics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/audit/statistics', {
    //   params: { start_date: dateRange.value[0], end_date: dateRange.value[1] }
    // })
    stats.value = {
      total_logs: 5000,
      active_users: 25,
      active_modules: 8,
      security_events: 15
    }
    topUsers.value = [
      { user: 'Admin User', actions_count: 150, last_active: '2026-06-23 10:30', modules: ['products', 'orders', 'users'] },
      { user: 'Warehouse Manager', actions_count: 120, last_active: '2026-06-23 09:45', modules: ['inventory', 'wms'] }
    ]
    initModuleChart()
    initActionChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const initModuleChart = () => {
  if (!moduleChartRef.value) return
  
  if (moduleChart) moduleChart.dispose()
  moduleChart = echarts.init(moduleChartRef.value)
  
  const option = {
    tooltip: { trigger: 'item' },
    series: [{
      type: 'pie',
      data: [
        { value: 35, name: 'Products' },
        { value: 25, name: 'Orders' },
        { value: 20, name: 'Inventory' },
        { value: 10, name: 'Users' },
        { value: 10, name: 'Other' }
      ]
    }]
  }
  
  moduleChart.setOption(option)
}

const initActionChart = () => {
  if (!actionChartRef.value) return
  
  if (actionChart) actionChart.dispose()
  actionChart = echarts.init(actionChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: ['Create', 'Update', 'Delete', 'Login', 'Logout']
    },
    yAxis: { type: 'value' },
    series: [{
      name: t('audit.actions'),
      type: 'bar',
      data: [1500, 2000, 500, 800, 200]
    }]
  }
  
  actionChart.setOption(option)
}

onMounted(() => {
  loadStatistics()
  
  window.addEventListener('resize', () => {
    moduleChart?.resize()
    actionChart?.resize()
  })
})
</script>

<style scoped>
.audit-statistics-page {
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
.stat-icon-purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

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
