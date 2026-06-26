<template>
  <div class="financial-analytics-page">
    <div class="page-header">
      <h1><el-icon><Money /></el-icon> {{ $t('analytics.financial_analytics') }}</h1>
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

    <!-- P&L Summary -->
    <el-card class="pl-card">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.profit_loss') }}</span>
        </div>
      </template>
      <el-row :gutter="20">
        <el-col :xs="24" :md="12">
          <div class="pl-item">
            <span class="pl-label">{{ $t('analytics.revenue') }}</span>
            <span class="pl-value positive">${{ formatNumber(pl.revenue) }}</span>
          </div>
          <div class="pl-item">
            <span class="pl-label">{{ $t('analytics.cost_of_goods_sold') }}</span>
            <span class="pl-value negative">-${{ formatNumber(pl.costOfGoodsSold) }}</span>
          </div>
          <div class="pl-item pl-subtotal">
            <span class="pl-label">{{ $t('analytics.gross_profit') }}</span>
            <span class="pl-value positive">${{ formatNumber(pl.grossProfit) }}</span>
          </div>
        </el-col>
        <el-col :xs="24" :md="12">
          <div class="pl-item">
            <span class="pl-label">{{ $t('analytics.operating_expenses') }}</span>
            <span class="pl-value negative">-${{ formatNumber(pl.operatingExpenses) }}</span>
          </div>
          <div class="pl-item pl-total">
            <span class="pl-label">{{ $t('analytics.net_profit') }}</span>
            <span class="pl-value positive">${{ formatNumber(pl.netProfit) }}</span>
          </div>
          <div class="pl-item">
            <span class="pl-label">{{ $t('analytics.profit_margin') }}</span>
            <span class="pl-value">{{ pl.profitMargin }}%</span>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- Financial Ratios -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="ratio-card">
          <div class="ratio-content">
            <h4>{{ $t('analytics.current_ratio') }}</h4>
            <h3>{{ ratios.currentRatio }}</h3>
            <el-progress :percentage="calculatePercentage(ratios.currentRatio, 3)" :status="getRatioStatus(ratios.currentRatio, 2, 3)" />
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="ratio-card">
          <div class="ratio-content">
            <h4>{{ $t('analytics.quick_ratio') }}</h4>
            <h3>{{ ratios.quickRatio }}</h3>
            <el-progress :percentage="calculatePercentage(ratios.quickRatio, 2)" :status="getRatioStatus(ratios.quickRatio, 1, 2)" />
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="ratio-card">
          <div class="ratio-content">
            <h4>{{ $t('analytics.debt_to_equity') }}</h4>
            <h3>{{ ratios.debtToEquity }}</h3>
            <el-progress :percentage="calculatePercentage(ratios.debtToEquity, 1)" :status="getRatioStatus(ratios.debtToEquity, 0, 0.5, true)" />
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="ratio-card">
          <div class="ratio-content">
            <h4>{{ $t('analytics.inventory_turnover') }}</h4>
            <h3>{{ ratios.inventoryTurnover }}</h3>
            <el-progress :percentage="calculatePercentage(ratios.inventoryTurnover, 10)" :status="getRatioStatus(ratios.inventoryTurnover, 5, 10)" />
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Cash Flow Chart -->
    <el-card style="margin-top: 20px">
      <template #header>
        <div class="card-header">
          <span>{{ $t('analytics.cash_flow') }}</span>
        </div>
      </template>
      <div ref="cashFlowChartRef" style="height: 300px"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Money, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t } = useI18n()
const loading = ref(false)
const dateRange = ref([])
const pl = ref({
  revenue: 0,
  costOfGoodsSold: 0,
  grossProfit: 0,
  operatingExpenses: 0,
  netProfit: 0,
  profitMargin: 0
})
const ratios = ref({
  currentRatio: 0,
  quickRatio: 0,
  debtToEquity: 0,
  inventoryTurnover: 0
})
const cashFlowChartRef = ref(null)
let cashFlowChart = null

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num)
}

const calculatePercentage = (value, max) => {
  return Math.min((value / max) * 100, 100)
}

const getRatioStatus = (value, min, max, inverse = false) => {
  const percentage = calculatePercentage(value, max)
  if (inverse) {
    return percentage < 50 ? 'success' : (percentage < 80 ? 'warning' : 'exception')
  }
  if (value < min) return 'exception'
  if (value < max) return 'warning'
  return 'success'
}

const loadAnalytics = async () => {
  loading.value = true
  try {
    // const response = await api.get('/api/v1/analytics/financial/pnl', {
    //   params: { start_date: dateRange.value[0], end_date: dateRange.value[1] }
    // })
    pl.value = {
      revenue: 250000,
      costOfGoodsSold: 150000,
      grossProfit: 100000,
      operatingExpenses: 45000,
      netProfit: 55000,
      profitMargin: 22
    }
    
    // const ratiosResponse = await api.get('/api/v1/analytics/financial/ratios')
    ratios.value = {
      currentRatio: 2.5,
      quickRatio: 1.8,
      debtToEquity: 0.4,
      inventoryTurnover: 8.5
    }
    
    initCashFlowChart()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const initCashFlowChart = () => {
  if (!cashFlowChartRef.value) return
  
  if (cashFlowChart) cashFlowChart.dispose()
  cashFlowChart = echarts.init(cashFlowChartRef.value)
  
  const option = {
    tooltip: { trigger: 'axis' },
    legend: {
      data: [t('analytics.inflow'), t('analytics.outflow'), t('analytics.net')]
    },
    xAxis: {
      type: 'category',
      data: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
    },
    yAxis: { type: 'value' },
    series: [
      {
        name: t('analytics.inflow'),
        type: 'bar',
        data: [30000, 35000, 40000, 38000, 45000, 50000],
        itemStyle: { color: '#67c23a' }
      },
      {
        name: t('analytics.outflow'),
        type: 'bar',
        data: [25000, 28000, 32000, 30000, 35000, 38000],
        itemStyle: { color: '#f56c6c' }
      },
      {
        name: t('analytics.net'),
        type: 'line',
        data: [5000, 7000, 8000, 8000, 10000, 12000],
        smooth: true
      }
    ]
  }
  
  cashFlowChart.setOption(option)
}

onMounted(() => {
  loadAnalytics()
  
  window.addEventListener('resize', () => {
    cashFlowChart?.resize()
  })
})
</script>

<style scoped>
.financial-analytics-page {
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

.pl-card {
  margin-bottom: 20px;
}

.pl-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 0;
  border-bottom: 1px solid #eee;
}

.pl-item:last-child {
  border-bottom: none;
}

.pl-subtotal {
  background: #f5f7fa;
  padding: 15px;
  margin: 10px 0;
  border-radius: 4px;
}

.pl-total {
  background: #e6f1fc;
  padding: 20px;
  border-radius: 4px;
  font-weight: 600;
}

.pl-label {
  font-size: 14px;
  color: #666;
}

.pl-value {
  font-size: 18px;
  font-weight: 600;
}

.pl-value.positive {
  color: #67c23a;
}

.pl-value.negative {
  color: #f56c6c;
}

.stats-row {
  margin-bottom: 20px;
}

.ratio-card {
  margin-bottom: 20px;
}

.ratio-content {
  text-align: center;
}

.ratio-content h4 {
  margin: 0 0 10px 0;
  font-size: 14px;
  color: #666;
}

.ratio-content h3 {
  margin: 0 0 15px 0;
  font-size: 28px;
  font-weight: 600;
  color: #333;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
