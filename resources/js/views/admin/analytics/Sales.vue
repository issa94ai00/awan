<template>
  <div class="sales-analytics-page">
    <div class="page-header">
      <h1><el-icon><TrendCharts /></el-icon> {{ $t('analytics.sales_analytics') }}</h1>
      <el-form :inline="true">
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
        <el-form-item>
          <el-button type="primary" @click="loadAnalytics">
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
              <el-icon><Money /></el-icon>
            </div>
            <div class="stat-info">
              <h3>${{ formatNumber(overview.totalRevenue) }}</h3>
              <p>{{ $t('analytics.total_revenue') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><ShoppingCart /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.totalOrders }}</h3>
              <p>{{ $t('analytics.total_orders') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Wallet /></el-icon>
            </div>
            <div class="stat-info">
              <h3>${{ formatNumber(overview.averageOrderValue) }}</h3>
              <p>{{ $t('analytics.average_order_value') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><TrendCharts /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.growthRate }}%</h3>
              <p>{{ $t('analytics.growth_rate') }}</p>
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
              <span>{{ $t('analytics.sales_trends') }}</span>
            </div>
          </template>
          <div ref="trendsChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('analytics.sales_by_category') }}</span>
            </div>
          </template>
          <div ref="categoryChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Top Products -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.top_products') }}</span>
        </div>
      </template>
      <el-table :data="topProducts" v-loading="loading">
        <el-table-column prop="product" :label="$t('analytics.product')" />
        <el-table-column prop="category" :label="$t('analytics.category')" />
        <el-table-column prop="quantity" :label="$t('analytics.quantity_sold')" />
        <el-table-column prop="revenue" :label="$t('analytics.revenue')">
          <template #default="{ row }">
            ${{ formatNumber(row.revenue) }}
          </template>
        </el-table-column>
        <el-table-column prop="growth" :label="$t('analytics.growth')">
          <template #default="{ row }">
            <el-tag :type="row.growth >= 0 ? 'success' : 'danger'">{{ row.growth }}%</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Sales Forecast -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.sales_forecast') }}</span>
          <el-select v-model="forecastPeriod" @change="loadForecast" size="small">
            <el-option :value="7" :label="$t('analytics.7_days')" />
            <el-option :value="30" :label="$t('analytics.30_days')" />
            <el-option :value="90" :label="$t('analytics.90_days')" />
          </el-select>
        </div>
      </template>
      <div ref="forecastChartRef" style="height: 300px"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { TrendCharts, Money, ShoppingCart, Wallet, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const dateRange = ref([])
const forecastPeriod = ref(30)
const overview = ref({
  totalRevenue: 0,
  totalOrders: 0,
  averageOrderValue: 0,
  growthRate: 0
})
const topProducts = ref([])
const trendsChartRef = ref(null)
const categoryChartRef = ref(null)
const forecastChartRef = ref(null)
let trendsChart = null
let categoryChart = null
let forecastChart = null

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num)
}

const loadAnalytics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/sales/overview', {
    //   params: { start_date: dateRange.value[0], end_date: dateRange.value[1] }
    // })
    overview.value = {
      totalRevenue: 150000,
      totalOrders: 250,
      averageOrderValue: 600,
      growthRate: 12.5
    }
    initTrendsChart()
    initCategoryChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const loadTopProducts = async () => {
  try {
    // const response = await api.get('/api/v1/analytics/sales/top-products')
    topProducts.value = [
      { id: 1, product: 'Product A', category: 'Electronics', quantity: 150, revenue: 15000, growth: 15 },
      { id: 2, product: 'Product B', category: 'Clothing', quantity: 120, revenue: 12000, growth: 8 },
      { id: 3, product: 'Product C', category: 'Electronics', quantity: 100, revenue: 10000, growth: -5 }
    ]
  } catch (error) {
    console.error('Failed to load top products:', error)
  }
}

const loadForecast = async () => {
  try {
    // const response = await api.get('/api/v1/analytics/sales/forecast', {
    //   params: { period: forecastPeriod.value }
    // })
    initForecastChart()
  } catch (error) {
    console.error('Failed to load forecast:', error)
  }
}

const initTrendsChart = () => {
  if (!trendsChartRef.value) return
  
  if (trendsChart) trendsChart.dispose()
  trendsChart = echarts.init(trendsChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
    },
    yAxis: { type: 'value' },
    series: [{
      name: t('analytics.revenue'),
      type: 'line',
      data: [10000, 12000, 15000, 14000, 18000, 22000],
      smooth: true
    }]
  }
  
  trendsChart.setOption(option)
}

const initCategoryChart = () => {
  if (!categoryChartRef.value) return
  
  if (categoryChart) categoryChart.dispose()
  categoryChart = echarts.init(categoryChartRef.value)
  
  const option = {
    tooltip: { trigger: 'item' },
    series: [{
      type: 'pie',
      data: [
        { value: 40, name: 'Electronics' },
        { value: 30, name: 'Clothing' },
        { value: 20, name: 'Food' },
        { value: 10, name: 'Other' }
      ]
    }]
  }
  
  categoryChart.setOption(option)
}

const initForecastChart = () => {
  if (!forecastChartRef.value) return
  
  if (forecastChart) forecastChart.dispose()
  forecastChart = echarts.init(forecastChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: Array.from({ length: forecastPeriod.value }, (_, i) => `Day ${i + 1}`)
    },
    yAxis: { type: 'value' },
    series: [{
      name: t('analytics.forecast'),
      type: 'line',
      data: Array.from({ length: forecastPeriod.value }, () => Math.floor(Math.random() * 5000) + 3000),
      smooth: true,
      lineStyle: { type: 'dashed' }
    }]
  }
  
  forecastChart.setOption(option)
}

onMounted(() => {
  loadAnalytics()
  loadTopProducts()
  loadForecast()
  
  window.addEventListener('resize', () => {
    trendsChart?.resize()
    categoryChart?.resize()
    forecastChart?.resize()
  })
})
</script>

<style scoped>
.sales-analytics-page {
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
