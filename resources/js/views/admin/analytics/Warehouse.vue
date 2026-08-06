<template>
  <div class="warehouse-analytics-page">
    <div class="page-header">
      <h1><el-icon><Management /></el-icon> {{ $t('analytics.warehouse_analytics') }}</h1>
      <el-form :inline="true">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="selectedWarehouse" :placeholder="$t('wms.select_warehouse')" clearable @change="loadAnalytics">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('common.date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="$t('common.to')"
            :start-placeholder="$t('common.start_date')"
            :end-placeholder="$t('common.end_date')"
            @change="loadAnalytics"
          />
        </el-form-item>
      </el-form>
    </div>

    <!-- Overview Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Grid /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.utilization }}%</h3>
              <p>{{ $t('analytics.utilization') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Aim /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.pickingAccuracy }}%</h3>
              <p>{{ $t('analytics.picking_accuracy') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Open /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.packingAccuracy }}%</h3>
              <p>{{ $t('analytics.packing_accuracy') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><Odometer /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.throughput }}</h3>
              <p>{{ $t('analytics.throughput') }}</p>
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
              <span>{{ $t('analytics.picking_efficiency') }}</span>
            </div>
          </template>
          <div ref="pickingChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('analytics.zone_utilization') }}</span>
            </div>
          </template>
          <div ref="zoneChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Performance Metrics -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.performance_metrics') }}</span>
        </div>
      </template>
      <el-table :data="performanceMetrics" v-loading="loading">
        <el-table-column prop="metric" :label="$t('analytics.metric')" />
        <el-table-column prop="value" :label="$t('analytics.value')" />
        <el-table-column prop="target" :label="$t('analytics.target')" />
        <el-table-column prop="variance" :label="$t('analytics.variance')">
          <template #default="{ row }">
            <el-tag :type="row.variance >= 0 ? 'success' : 'danger'">{{ row.variance }}%</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="trend" :label="$t('analytics.trend')">
          <template #default="{ row }">
            <el-icon :color="row.trend === 'up' ? '#67c23a' : '#f56c6c'">
              <component :is="row.trend === 'up' ? 'ArrowUp' : 'ArrowDown'" />
            </el-icon>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Management, Grid, Aim, Open, Odometer, ArrowUp, ArrowDown } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const selectedWarehouse = ref(null)
const dateRange = ref([])
const warehouses = ref([])
const overview = ref({
  utilization: 0,
  pickingAccuracy: 0,
  packingAccuracy: 0,
  throughput: 0
})
const performanceMetrics = ref([])
const pickingChartRef = ref(null)
const zoneChartRef = ref(null)
let pickingChart = null
let zoneChart = null

const loadWarehouses = async () => {
  try {
    // await api.get('/api/v1/wms/warehouses')
    warehouses.value = [
      { id: 1, name: 'Main Warehouse' }
    ]
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadAnalytics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/warehouse/performance', {
    //   params: { warehouse_id: selectedWarehouse.value, start_date: dateRange.value[0], end_date: dateRange.value[1] }
    // })
    overview.value = {
      utilization: 78,
      pickingAccuracy: 98.5,
      packingAccuracy: 99.2,
      throughput: 450
    }
    
    performanceMetrics.value = [
      { metric: t('wms.average_picking_time'), value: '15.5 min', target: '15 min', variance: 3.3, trend: 'up' },
      { metric: t('wms.average_packing_time'), value: '12.3 min', target: '12 min', variance: -2.5, trend: 'down' },
      { metric: t('wms.cycle_count_accuracy'), value: '97.8%', target: '98%', variance: -0.2, trend: 'down' }
    ]
    
    initPickingChart()
    initZoneChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const initPickingChart = () => {
  if (!pickingChartRef.value) return
  
  if (pickingChart) pickingChart.dispose()
  pickingChart = echarts.init(pickingChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    },
    yAxis: { type: 'value' },
    series: [{
      name: t('analytics.orders_picked'),
      type: 'line',
      data: [45, 52, 48, 55, 60, 42],
      smooth: true
    }]
  }
  
  pickingChart.setOption(option)
}

const initZoneChart = () => {
  if (!zoneChartRef.value) return
  
  if (zoneChart) zoneChart.dispose()
  zoneChart = echarts.init(zoneChartRef.value)
  
  const option = {
    tooltip: { trigger: 'item' },
    series: [{
      type: 'pie',
      data: [
        { value: 35, name: 'Zone A' },
        { value: 30, name: 'Zone B' },
        { value: 20, name: 'Zone C' },
        { value: 15, name: 'Zone D' }
      ]
    }]
  }
  
  zoneChart.setOption(option)
}

onMounted(() => {
  loadWarehouses()
  loadAnalytics()
  
  window.addEventListener('resize', () => {
    pickingChart?.resize()
    zoneChart?.resize()
  })
})
</script>

<style scoped>
.warehouse-analytics-page {
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
