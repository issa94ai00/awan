<template>
  <div class="performance-page">
    <div class="page-header">
      <h1><el-icon><TrendCharts /></el-icon> {{ $t('wms.performance') }}</h1>
      <el-form :inline="true">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="selectedWarehouse" :placeholder="$t('wms.select_warehouse')" clearable @change="loadPerformance">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.date_range')">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            :range-separator="$t('common.to')"
            :start-placeholder="$t('common.start_date')"
            :end-placeholder="$t('common.end_date')"
            @change="loadPerformance"
          />
        </el-form-item>
      </el-form>
    </div>

    <el-row :gutter="20">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="metric-card">
          <div class="metric-content">
            <div class="metric-icon metric-icon-blue">
              <el-icon><Aim /></el-icon>
            </div>
            <div class="metric-info">
              <h3>{{ performance.picking_accuracy || 0 }}%</h3>
              <p>{{ $t('wms.picking_accuracy') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="metric-card">
          <div class="metric-content">
            <div class="metric-icon metric-icon-green">
              <el-icon><Open /></el-icon>
            </div>
            <div class="metric-info">
              <h3>{{ performance.packing_accuracy || 0 }}%</h3>
              <p>{{ $t('wms.packing_accuracy') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="metric-card">
          <div class="metric-content">
            <div class="metric-icon metric-icon-orange">
              <el-icon><Document /></el-icon>
            </div>
            <div class="metric-info">
              <h3>{{ performance.cycle_count_accuracy || 0 }}%</h3>
              <p>{{ $t('wms.cycle_count_accuracy') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="metric-card">
          <div class="metric-content">
            <div class="metric-icon metric-icon-purple">
              <el-icon><Odometer /></el-icon>
            </div>
            <div class="metric-info">
              <h3>{{ performance.throughput || 0 }}</h3>
              <p>{{ $t('wms.throughput') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('wms.average_picking_time') }}</span>
              <el-tag>{{ performance.average_picking_time || 0 }} {{ $t('common.minutes') }}</el-tag>
            </div>
          </template>
          <div class="progress-bar">
            <el-progress :percentage="calculatePercentage(performance.average_picking_time, 30)" :status="getProgressStatus(performance.average_picking_time, 30)" />
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('wms.average_packing_time') }}</span>
              <el-tag>{{ performance.average_packing_time || 0 }} {{ $t('common.minutes') }}</el-tag>
            </div>
          </template>
          <div class="progress-bar">
            <el-progress :percentage="calculatePercentage(performance.average_packing_time, 25)" :status="getProgressStatus(performance.average_packing_time, 25)" />
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('wms.performance_trends') }}</span>
        </div>
      </template>
      <div ref="chartRef" style="height: 300px"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { TrendCharts, Aim, Open, Document, Odometer } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const selectedWarehouse = ref(null)
const dateRange = ref([])
const warehouses = ref([])
const performance = ref({
  picking_accuracy: 0,
  packing_accuracy: 0,
  cycle_count_accuracy: 0,
  average_picking_time: 0,
  average_packing_time: 0,
  throughput: 0
})
const chartRef = ref(null)
let chartInstance = null

const calculatePercentage = (value, max) => {
  return Math.min((value / max) * 100, 100)
}

const getProgressStatus = (value, max) => {
  const percentage = calculatePercentage(value, max)
  if (percentage < 50) return 'success'
  if (percentage < 80) return 'warning'
  return 'exception'
}

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

const loadPerformance = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/wms/performance', {
    //   params: { warehouse_id: selectedWarehouse.value, start_date: dateRange.value[0], end_date: dateRange.value[1] }
    // })
    performance.value = {
      picking_accuracy: 98.5,
      packing_accuracy: 99.2,
      cycle_count_accuracy: 97.8,
      average_picking_time: 15.5,
      average_packing_time: 12.3,
      throughput: 450
    }
    initChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const initChart = () => {
  if (!chartRef.value) return
  
  if (chartInstance) {
    chartInstance.dispose()
  }
  
  chartInstance = echarts.init(chartRef.value)
  
  const option = {
    tooltip: {
      trigger: 'axis'
    },
    legend: {
      data: [t('wms.picking_accuracy'), t('wms.packing_accuracy'), t('wms.cycle_count_accuracy')]
    },
    xAxis: {
      type: 'category',
      data: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
    },
    yAxis: {
      type: 'value',
      max: 100,
      axisLabel: {
        formatter: '{value}%'
      }
    },
    series: [
      {
        name: t('wms.picking_accuracy'),
        type: 'line',
        data: [95, 96, 97, 98, 98.5, 98.5]
      },
      {
        name: t('wms.packing_accuracy'),
        type: 'line',
        data: [97, 98, 98.5, 99, 99.2, 99.2]
      },
      {
        name: t('wms.cycle_count_accuracy'),
        type: 'line',
        data: [94, 95, 96, 97, 97.5, 97.8]
      }
    ]
  }
  
  chartInstance.setOption(option)
}

onMounted(() => {
  loadWarehouses()
  loadPerformance()
  
  window.addEventListener('resize', () => {
    if (chartInstance) {
      chartInstance.resize()
    }
  })
})
</script>

<style scoped>
.performance-page {
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

.metric-card {
  margin-bottom: 20px;
}

.metric-content {
  display: flex;
  align-items: center;
  gap: 15px;
}

.metric-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.metric-icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.metric-icon-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.metric-icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.metric-icon-purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.metric-info h3 {
  margin: 0 0 5px 0;
  font-size: 24px;
  font-weight: 600;
  color: #333;
}

.metric-info p {
  margin: 0;
  font-size: 14px;
  color: #666;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.progress-bar {
  padding: 20px 0;
}
</style>
