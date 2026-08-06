<template>
  <div class="analytics-dashboard">
    <div class="page-header">
      <h1><el-icon><TrendCharts /></el-icon> {{ $t('analytics.title') }}</h1>
      <p>{{ $t('analytics.description') }}</p>
    </div>

    <!-- Quick Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card" @click="$router.push('/admin/analytics/sales')">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><ShoppingCart /></el-icon>
            </div>
            <div class="stat-info">
              <h3>${{ formatNumber(stats.totalRevenue) }}</h3>
              <p>{{ $t('analytics.total_revenue') }}</p>
              <small class="trend positive">+12.5%</small>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card" @click="$router.push('/admin/analytics/inventory')">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Box /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.totalProducts }}</h3>
              <p>{{ $t('analytics.total_products') }}</p>
              <small>{{ stats.lowStock }} {{ $t('analytics.low_stock') }}</small>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card" @click="$router.push('/admin/analytics/warehouse')">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><Management /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.warehouseUtilization }}%</h3>
              <p>{{ $t('analytics.warehouse_utilization') }}</p>
              <small class="trend positive">+5.2%</small>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card" @click="$router.push('/admin/analytics/financial')">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><Money /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.profitMargin }}%</h3>
              <p>{{ $t('analytics.profit_margin') }}</p>
              <small class="trend positive">+2.1%</small>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Quick Links -->
    <el-card class="quick-links-card">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.quick_links') }}</span>
        </div>
      </template>
      <el-row :gutter="20">
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/sales')">
            <el-icon><TrendCharts /></el-icon>
            <span>{{ $t('analytics.sales_analytics') }}</span>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/inventory')">
            <el-icon><Box /></el-icon>
            <span>{{ $t('analytics.inventory_analytics') }}</span>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/warehouse')">
            <el-icon><Management /></el-icon>
            <span>{{ $t('analytics.warehouse_analytics') }}</span>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/financial')">
            <el-icon><Money /></el-icon>
            <span>{{ $t('analytics.financial_analytics') }}</span>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/reports')">
            <el-icon><Document /></el-icon>
            <span>{{ $t('analytics.reports') }}</span>
          </div>
        </el-col>
        <el-col :xs="12" :sm="8" :md="4">
          <div class="quick-link" @click="$router.push('/admin/analytics/dashboards')">
            <el-icon><DataBoard /></el-icon>
            <span>{{ $t('analytics.dashboards') }}</span>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- Recent Reports -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.recent_reports') }}</span>
          <el-button text @click="$router.push('/admin/analytics/reports')">
            {{ $t('common.view_all') }}
          </el-button>
        </div>
      </template>
      <el-table :data="recentReports" v-loading="loading">
        <el-table-column prop="name" :label="$t('analytics.report_name')" />
        <el-table-column prop="type" :label="$t('analytics.type')" />
        <el-table-column prop="created_at" :label="$t('common.created_at')" />
        <el-table-column prop="status" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : 'warning'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="viewReport(row)">
                <el-icon><View /></el-icon>
              </el-button>
              <el-button size="small" @click="downloadReport(row)">
                <el-icon><Download /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { TrendCharts, ShoppingCart, Box, Management, Money, Document, DataBoard, View, Download } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const stats = ref({
  totalRevenue: 150000,
  totalProducts: 500,
  lowStock: 25,
  warehouseUtilization: 78,
  profitMargin: 22
})
const recentReports = ref([])

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num)
}

const loadStats = async () => {
  loading.value = true
  try {
    // await api.get('/api/v1/analytics/overview')
    stats.value = {
      totalRevenue: 150000,
      totalProducts: 500,
      lowStock: 25,
      warehouseUtilization: 78,
      profitMargin: 22
    }
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const loadRecentReports = async () => {
  try {
    // await api.get('/api/v1/analytics/reports?limit=5')
    recentReports.value = [
      { id: 1, name: 'Monthly Sales Report', type: 'sales', created_at: '2026-06-23', status: 'completed' },
      { id: 2, name: 'Inventory Status Report', type: 'inventory', created_at: '2026-06-22', status: 'completed' }
    ]
  } catch (error) {
    console.error('Failed to load reports:', error)
  }
}

const viewReport = (report) => {
  $router.push(`/admin/analytics/reports/${report.id}`)
}

const downloadReport = async (report) => {
  try {
    // await api.get(`/api/v1/analytics/reports/${report.id}/export`)
    ElMessage.success(t('analytics.download_started'))
  } catch (error) {
    ElMessage.error(t('common.action_error'))
  }
}

onMounted(() => {
  loadStats()
  loadRecentReports()
})
</script>

<style scoped>
.analytics-dashboard {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 10px 0;
  font-size: 28px;
  color: #333;
}

.page-header p {
  margin: 0;
  color: #666;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  margin-bottom: 20px;
  cursor: pointer;
  transition: transform 0.2s;
}

.stat-card:hover {
  transform: translateY(-5px);
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
  margin: 0 0 5px 0;
  font-size: 14px;
  color: #666;
}

.stat-info small {
  margin: 0;
  font-size: 12px;
  color: #999;
}

.trend.positive {
  color: #67c23a;
}

.trend.negative {
  color: #f56c6c;
}

.quick-links-card {
  margin-bottom: 20px;
}

.quick-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
}

.quick-link:hover {
  background: #e6f1fc;
  transform: translateY(-3px);
}

.quick-link el-icon {
  font-size: 32px;
  color: #409eff;
}

.quick-link span {
  font-size: 14px;
  color: #333;
  text-align: center;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
