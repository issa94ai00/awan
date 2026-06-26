<template>
  <div class="inventory-analytics-page">
    <div class="page-header">
      <h1><el-icon><Box /></el-icon> {{ $t('analytics.inventory_analytics') }}</h1>
      <el-button type="primary" @click="exportReport">
        <el-icon><Download /></el-icon> {{ $t('analytics.export_report') }}
      </el-button>
    </div>

    <!-- Overview Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Box /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.totalProducts }}</h3>
              <p>{{ $t('analytics.total_products') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Goods /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.totalStock }}</h3>
              <p>{{ $t('analytics.total_stock') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Warning /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ overview.lowStock }}</h3>
              <p>{{ $t('analytics.low_stock') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><Money /></el-icon>
            </div>
            <div class="stat-info">
              <h3>${{ formatNumber(overview.inventoryValue) }}</h3>
              <p>{{ $t('analytics.inventory_value') }}</p>
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
              <span>{{ $t('analytics.inventory_turnover') }}</span>
            </div>
          </template>
          <div ref="turnoverChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('analytics.abc_analysis') }}</span>
            </div>
          </template>
          <div ref="abcChartRef" style="height: 300px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Low Stock Alerts -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.low_stock_alerts') }}</span>
          <el-tag type="danger">{{ lowStockProducts.length }}</el-tag>
        </div>
      </template>
      <el-table :data="lowStockProducts" v-loading="loading">
        <el-table-column prop="product" :label="$t('analytics.product')" />
        <el-table-column prop="sku" :label="$t('analytics.sku')" />
        <el-table-column prop="current_stock" :label="$t('analytics.current_stock')" />
        <el-table-column prop="min_stock" :label="$t('analytics.min_stock')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag type="danger">{{ $t('analytics.critical') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button size="small" @click="reorderProduct(row)">
              <el-icon><ShoppingCart /></el-icon> {{ $t('analytics.reorder') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Box, Goods, Warning, Money, Download, ShoppingCart } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const overview = ref({
  totalProducts: 0,
  totalStock: 0,
  lowStock: 0,
  inventoryValue: 0
})
const lowStockProducts = ref([])
const turnoverChartRef = ref(null)
const abcChartRef = ref(null)
let turnoverChart = null
let abcChart = null

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num)
}

const loadAnalytics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/inventory/overview')
    overview.value = {
      totalProducts: 500,
      totalStock: 15000,
      lowStock: 25,
      inventoryValue: 750000
    }
    initTurnoverChart()
    initABCChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const loadLowStockProducts = async () => {
  try {
    // const response = await api.get('/api/v1/analytics/inventory/low-stock')
    lowStockProducts.value = [
      { id: 1, product: 'Product A', sku: 'SKU-001', current_stock: 5, min_stock: 10 },
      { id: 2, product: 'Product B', sku: 'SKU-002', current_stock: 3, min_stock: 15 }
    ]
  } catch (error) {
    console.error('Failed to load low stock products:', error)
  }
}

const initTurnoverChart = () => {
  if (!turnoverChartRef.value) return
  
  if (turnoverChart) turnoverChart.dispose()
  turnoverChart = echarts.init(turnoverChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
    },
    yAxis: { type: 'value' },
    series: [{
      name: t('analytics.turnover_rate'),
      type: 'bar',
      data: [8.5, 9.2, 8.8, 9.5, 10.2, 9.8]
    }]
  }
  
  turnoverChart.setOption(option)
}

const initABCChart = () => {
  if (!abcChartRef.value) return
  
  if (abcChart) abcChart.dispose()
  abcChart = echarts.init(abcChartRef.value)
  
  const option = {
    tooltip: { trigger: 'item' },
    series: [{
      type: 'pie',
      data: [
        { value: 20, name: 'Class A (80% value)', itemStyle: { color: '#67c23a' } },
        { value: 30, name: 'Class B (15% value)', itemStyle: { color: '#e6a23c' } },
        { value: 50, name: 'Class C (5% value)', itemStyle: { color: '#909399' } }
      ]
    }]
  }
  
  abcChart.setOption(option)
}

const exportReport = async () => {
  try {
    // await api.get('/api/v1/analytics/inventory/export')
    ElMessage.success(t('analytics.export_started'))
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

const reorderProduct = (product) => {
  $router.push(`/admin/purchases/orders?product=${product.id}`)
}

onMounted(() => {
  loadAnalytics()
  loadLowStockProducts()
  
  window.addEventListener('resize', () => {
    turnoverChart?.resize()
    abcChart?.resize()
  })
})
</script>

<style scoped>
.inventory-analytics-page {
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
