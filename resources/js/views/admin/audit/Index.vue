<template>
  <div class="audit-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ $t('audit_and_risk_monitoring') }}</h1>
    </div>

    <el-row :gutter="20" class="summary-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="metric-card critical">
          <div class="metric-label">{{ $t('total_issues') }}</div>
          <div class="metric-value">{{ summary.total_issues }}</div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="metric-card warning">
          <div class="metric-label">{{ $t('critical_female') }}</div>
          <div class="metric-value">{{ summary.critical_issues }}</div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="metric-card info">
          <div class="metric-label">{{ $t('warnings') }}</div>
          <div class="metric-value">{{ summary.warning_issues }}</div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="metric-card neutral">
          <div class="metric-label">{{ $t('last_scan') }}</div>
          <div class="metric-value small">{{ summary.last_scan }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="panel-card">
      <template #header>
        <div class="card-header">
          <span>{{ $t('manual_risk_scan_results') }}</span>
          <div class="header-actions">
            <el-button type="success" :loading="loading" @click="exportRiskScan">{{ $t('export_csv') }}</el-button>
            <el-button type="primary" :loading="loading" @click="loadRiskScan">{{ $t('run_checks_again') }}</el-button>
          </div>
        </div>
      </template>

      <el-table :data="riskIssues" v-loading="loading" stripe :empty-text="$t('no_issues_right_now')">
        <el-table-column prop="type" :label="$t('type')" width="220" />
        <el-table-column prop="severity" :label="$t('severity')" width="120">
          <template #default="{ row }">
            <el-tag :type="row.severity === 'critical' ? 'danger' : 'warning'">{{ row.severity }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="reference" :label="$t('indicator')" width="180" />
        <el-table-column prop="message" :label="$t('description')" show-overflow-tooltip />
        <el-table-column :label="$t('details')" width="220">
          <template #default="{ row }">
            <pre class="detail-box">{{ formatDetails(row.details) }}</pre>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
import { ref, onMounted } from 'vue'
import { Document } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const loading = ref(false)
const riskIssues = ref([])
const summary = ref({
  total_issues: 0,
  critical_issues: 0,
  warning_issues: 0,
  last_scan: '—'
})

const formatDetails = (details) => {
  if (!details) {
    return ''
  }

  return JSON.stringify(details, null, 2)
}

const loadRiskScan = async () => {
  loading.value = true

  try {
    const response = await axios.get('/api/v1/audit/risk-scan')
    const result = response.data || {}

    riskIssues.value = result.issues || []
    summary.value = result.summary || {
      total_issues: 0,
      critical_issues: 0,
      warning_issues: 0,
      last_scan: '—'
    }
  } catch (error) {
    ElMessage.error(t('failed_to_load_risk_scan'))
    console.error(error)
  } finally {
    loading.value = false
  }
}

const exportRiskScan = async () => {
  try {
    const response = await axios.get('/api/v1/audit/risk-scan/export', {
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'manual-risk-audit.csv')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    ElMessage.success(t('csv_exported'))
  } catch (error) {
    ElMessage.error(t('failed_to_export_csv'))
    console.error(error)
  }
}

onMounted(() => {
  loadRiskScan()
})
</script>

<style scoped>
.audit-page {
  padding: 24px;
  background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
  min-height: 100%;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.page-header h1 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
}

.summary-row {
  margin-bottom: 22px;
}

.metric-card {
  border-radius: 16px;
  overflow: hidden;
}

.metric-card.critical {
  border-top: 4px solid #ef4444;
}

.metric-card.warning {
  border-top: 4px solid #f59e0b;
}

.metric-card.info {
  border-top: 4px solid #3b82f6;
}

.metric-card.neutral {
  border-top: 4px solid #64748b;
}

.metric-label {
  color: #6b7280;
  font-size: 13px;
  margin-bottom: 8px;
}

.metric-value {
  font-size: 30px;
  font-weight: 800;
  line-height: 1.1;
  color: #111827;
}

.metric-value.small {
  font-size: 15px;
  line-height: 1.4;
}

.panel-card {
  border-radius: 18px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 700;
}

.detail-box {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  margin: 0;
  font-size: 11px;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
