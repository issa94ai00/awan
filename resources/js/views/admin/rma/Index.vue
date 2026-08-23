<template>
  <div class="rma-list">
    <AdminPageHeader icon="fas fa-rotate-left text-primary" :title="$t('rma.title')" :subtitle="$t('rma_index_subtitle')">
      <template #actions>
        <el-button type="primary" @click="createRma">
          <el-icon><Plus /></el-icon> {{ $t('rma.create_return') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <AdminStatGrid>
      <el-card v-for="card in statCards" :key="card.key" shadow="hover" class="stat-card-wrapper" v-loading="statsLoading">
        <div class="stat-card-inner">
          <div class="stat-icon-box" :class="card.key">
            <el-icon><component :is="card.icon" /></el-icon>
          </div>
          <div class="stat-details">
            <h3>{{ card.value }}</h3>
            <p>{{ card.title }}</p>
          </div>
        </div>
      </el-card>
    </AdminStatGrid>

    <div class="panel-card">
      <!-- Filters -->
      <div class="filters-row">
        <div class="filter-item filter-item-search">
          <label>{{ $t('search') }}</label>
          <el-input
            v-model="filters.search"
            :placeholder="$t('rma_search_placeholder')"
            clearable
            :prefix-icon="Search"
            @keyup.enter="loadRmaRequests"
          />
        </div>
        <div class="filter-item">
          <label>{{ $t('rma.status') }}</label>
          <el-select v-model="filters.status" :placeholder="$t('all_statuses')" clearable @change="loadRmaRequests">
            <el-option value="pending" :label="$t('on_hold')" />
            <el-option value="approved" :label="$t('approved')" />
            <el-option value="received" :label="$t('goods_received')" />
            <el-option value="rejected" :label="$t('sales_status_rejected')" />
            <el-option value="completed" :label="$t('sales_status_completed')" />
            <el-option value="cancelled" :label="$t('sales_status_cancelled')" />
          </el-select>
        </div>
        <div class="filter-item">
          <label>{{ $t('rma.return_type') }}</label>
          <el-select v-model="filters.return_type" :placeholder="$t('all_types')" clearable @change="loadRmaRequests">
            <el-option value="refund" :label="$t('cash_refund')" />
            <el-option value="exchange" :label="$t('exchange')" />
            <el-option value="store_credit" :label="$t('store_credit')" />
          </el-select>
        </div>
        <div class="filter-item">
          <label>{{ $t('date_from') }}</label>
          <el-date-picker
            v-model="filters.from_date"
            type="date"
            :placeholder="$t('choose_the_date')"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            @change="loadRmaRequests"
          />
        </div>
        <div class="filter-item">
          <label>{{ $t('date_to') }}</label>
          <el-date-picker
            v-model="filters.to_date"
            type="date"
            :placeholder="$t('choose_the_date')"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            @change="loadRmaRequests"
          />
        </div>

        <div class="filter-actions">
          <el-button type="primary" @click="loadRmaRequests">
            <el-icon><Search /></el-icon> {{ $t('search') }}
          </el-button>
          <el-button @click="resetFilters">{{ $t('reset') }}</el-button>

          <el-dropdown v-if="selectedRows.length > 0" @command="handleBulkAction">
            <el-button type="warning">
              <el-icon><Operation /></el-icon> {{ $t('bulk_actions_count', { count: selectedRows.length }) }}
              <el-icon class="el-icon--right"><ArrowDown /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="approve" :disabled="!canBulkApprove">
                  <el-icon><Check /></el-icon> {{ $t('bulk_approve') }}
                </el-dropdown-item>
                <el-dropdown-item command="reject" :disabled="!canBulkReject">
                  <el-icon><Close /></el-icon> {{ $t('bulk_reject') }}
                </el-dropdown-item>
                <el-dropdown-item command="cancel" :disabled="!canBulkCancel">
                  <el-icon><Delete /></el-icon> {{ $t('bulk_cancel') }}
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>

          <el-button @click="exportData">
            <el-icon><Download /></el-icon> {{ $t('export') }}
          </el-button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-wrapper">
        <el-table
          :data="rmaRequests"
          v-loading="loading"
          stripe
          class="rma-table"
          @selection-change="handleSelectionChange"
        >
          <el-table-column type="selection" width="48" align="center" />
          <el-table-column prop="rma_number" :label="$t('rma_number')" width="130">
            <template #default="{ row }">
              <span class="rma-number-badge" @click="viewRma(row)">{{ row.rma_number }}</span>
            </template>
          </el-table-column>

          <el-table-column prop="customer" :label="$t('customer')" min-width="180">
            <template #default="{ row }">
              <div class="cell-stack">
                <span class="cell-primary">{{ row.customer?.name || row.customer || 'N/A' }}</span>
                <span class="cell-secondary" v-if="row.customer?.phone">{{ row.customer.phone }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="order_number" :label="$t('linked_original_invoice')" min-width="150">
            <template #default="{ row }">
              <span class="cell-primary">#{{ row.invoice?.invoice_number || row.order_number || 'N/A' }}</span>
            </template>
          </el-table-column>

          <el-table-column prop="return_type" :label="$t('settlement_type')" width="130">
            <template #default="{ row }">
              <el-tag :type="getReturnTypeClass(row.type || row.return_type)" size="small">
                {{ getReturnTypeLabel(row.type || row.return_type) }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column prop="refund_amount" :label="$t('compensation_value')" width="130">
            <template #default="{ row }">
              <span class="cell-money" v-if="row.refund_amount > 0">{{ formatCurrency(row.refund_amount) }}</span>
              <span class="cell-empty" v-else>-</span>
            </template>
          </el-table-column>

          <el-table-column prop="status" :label="$t('order_status')" width="140">
            <template #default="{ row }">
              <span class="status-badge" :class="row.status">
                <span class="dot"></span>
                {{ getStatusLabel(row.status) }}
              </span>
            </template>
          </el-table-column>

          <el-table-column prop="requested_at" :label="$t('submitted_on')" width="160">
            <template #default="{ row }">{{ formatDate(row.requested_at || row.created_at) }}</template>
          </el-table-column>

          <el-table-column :label="$t('operations')" width="170" align="center" fixed="right">
            <template #default="{ row }">
              <div class="row-actions">
                <el-tooltip :content="$t('view_details')" placement="top" :enterable="false">
                  <el-button size="small" circle @click="viewRma(row)">
                    <el-icon><View /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="$t('edit_request')" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button size="small" circle type="warning" plain @click="editRma(row)">
                    <el-icon><Edit /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="$t('approve')" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button size="small" circle type="success" plain @click="approveRma(row)">
                    <el-icon><Check /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="$t('to_reject')" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button size="small" circle type="danger" plain @click="rejectRma(row)">
                    <el-icon><Close /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="pagination-row">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadRmaRequests"
          @current-change="loadRmaRequests"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatMoney } from '@/utils/currency';
import { ref, onMounted, computed } from 'vue'
import { Plus, Search, View, Check, Close, Edit, Files, Finished, Warning, Tickets, Operation, ArrowDown, Download, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import rmaService from '@/services/rma'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue'

const { t } = useI18n()
const router = useRouter()
const createRma = () => {
  router.push('/admin/rma/create')
}

const loading = ref(false)
const statsLoading = ref(false)
const rmaRequests = ref([])
const selectedRows = ref([])
const statistics = ref({
  total_requests: 0,
  pending: 0,
  approved: 0,
  received: 0,
  rejected: 0,
  completed: 0,
  total_refund_amount: 0
})

const filters = ref({
  status: '',
  return_type: '',
  from_date: '',
  to_date: '',
  search: ''
})

const pagination = ref({
  page: 1,
  per_page: 15,
  total: 0
})

const statCards = computed(() => [
  { key: 'total', title: t('total_orders'), value: statistics.value.total_requests, icon: Tickets },
  { key: 'pending', title: t('awaiting_approval'), value: statistics.value.pending, icon: Warning },
  { key: 'completed', title: t('completed_requests'), value: statistics.value.completed, icon: Finished },
  { key: 'refund', title: t('refunded_amounts'), value: formatCurrency(statistics.value.total_refund_amount), icon: Files }
])

const loadStatistics = async () => {
  statsLoading.value = true
  try {
    const response = await rmaService.getStatistics()
    if (response.data?.success) {
      statistics.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load RMA statistics:', error)
  } finally {
    statsLoading.value = false
  }
}

const loadRmaRequests = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequests({
      status: filters.value.status,
      type: filters.value.return_type,
      from_date: filters.value.from_date,
      to_date: filters.value.to_date,
      search: filters.value.search,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })
    if (response.data?.success) {
      rmaRequests.value = response.data.data.data || []
      pagination.value.total = response.data.data.total || 0
    }
  } catch (error) {
    console.error('Failed to load RMA requests:', error)
    ElMessage.error(t('failed_to_load_returns'))
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.value = {
    status: '',
    return_type: '',
    from_date: '',
    to_date: '',
    search: ''
  }
  loadRmaRequests()
}

const handleSelectionChange = (selection) => {
  selectedRows.value = selection
}

const canBulkApprove = computed(() => {
  return selectedRows.value.length > 0 && selectedRows.value.every(row => row.status === 'pending')
})

const canBulkReject = computed(() => {
  return selectedRows.value.length > 0 && selectedRows.value.every(row => row.status === 'pending')
})

const canBulkCancel = computed(() => {
  return selectedRows.value.length > 0 && selectedRows.value.every(row => ['pending', 'approved'].includes(row.status))
})

const handleBulkAction = async (action) => {
  const ids = selectedRows.value.map(row => row.id)

  try {
    if (action === 'approve') {
      await ElMessageBox.confirm(t('confirm_bulk_approve_count', { count: ids.length }), t('confirm_bulk_approve'), {
        type: 'warning',
        confirmButtonText: t('ok_agreed'),
        cancelButtonText: t('cancel')
      })
      await Promise.all(ids.map(id => rmaService.approveRma(id)))
      ElMessage.success(t('bulk_approved_count', { count: ids.length }))
    } else if (action === 'reject') {
      const { value } = await ElMessageBox.prompt(t('enter_bulk_rejection_reason'), t('bulk_reject'), {
        type: 'warning',
        inputPattern: /.+/,
        inputErrorMessage: t('rejection_reason_required'),
        confirmButtonText: t('to_reject'),
        cancelButtonText: t('cancel')
      })
      await Promise.all(ids.map(id => rmaService.rejectRma(id, { reason: value })))
      ElMessage.success(t('bulk_rejected_count', { count: ids.length }))
    } else if (action === 'cancel') {
      await ElMessageBox.confirm(t('confirm_bulk_cancel_count', { count: ids.length }), t('confirm_bulk_cancel'), {
        type: 'warning',
        confirmButtonText: t('ok_agreed'),
        cancelButtonText: t('cancel')
      })
      await Promise.all(ids.map(id => rmaService.cancelRma(id)))
      ElMessage.success(t('bulk_cancelled_count', { count: ids.length }))
    }

    selectedRows.value = []
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('operation_failed'))
    }
  }
}

const exportData = async () => {
  try {
    const response = await rmaService.exportRmaRequests({
      status: filters.value.status,
      type: filters.value.return_type,
      from_date: filters.value.from_date,
      to_date: filters.value.to_date,
      search: filters.value.search
    })

    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.setAttribute('href', url)
    link.setAttribute('download', `rma-requests-${new Date().toISOString().split('T')[0]}.csv`)
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    ElMessage.success(t('data_exported_successfully'))
  } catch (error) {
    console.error('Export failed:', error)
    ElMessage.error(t('failed_to_export_data'))
  }
}

const viewRma = (row) => {
  router.push(`/admin/rma/${row.id}`)
}

const editRma = (row) => {
  router.push(`/admin/rma/${row.id}/edit`)
}

const approveRma = async (row) => {
  try {
    await ElMessageBox.confirm(t('confirm_approve_return'), t('confirm_approval'), {
      type: 'warning',
      confirmButtonText: t('ok_agreed'),
      cancelButtonText: t('cancel')
    })
    await rmaService.approveRma(row.id)
    ElMessage.success(t('return_approved'))
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('approval_failed'))
    }
  }
}

const rejectRma = async (row) => {
  try {
    const { value } = await ElMessageBox.prompt(t('enter_rejection_reason'), t('reject_request'), {
      type: 'warning',
      inputPattern: /.+/,
      inputErrorMessage: t('rejection_reason_required'),
      confirmButtonText: t('to_reject'),
      cancelButtonText: t('cancel')
    })
    await rmaService.rejectRma(row.id, { reason: value })
    ElMessage.success(t('return_rejected'))
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('rejection_failed'))
    }
  }
}

const getStatusLabel = (status) => {
  const labels = {
    pending: t('awaiting_approval'),
    approved: t('approved'),
    received: t('goods_received'),
    rejected: t('sales_status_rejected'),
    completed: t('sales_status_completed'),
    cancelled: t('sales_status_cancelled')
  }
  return labels[status] || status
}

const getReturnTypeLabel = (type) => {
  const labels = {
    refund: t('cash_refund'),
    exchange: t('exchange'),
    store_credit: t('store_credit')
  }
  return labels[type] || type
}

const getReturnTypeClass = (type) => {
  const classes = {
    refund: 'success',
    exchange: 'warning',
    store_credit: 'danger'
  }
  return classes[type] || 'info'
}

const formatCurrency = (val) => formatMoney(val)

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  loadStatistics()
  loadRmaRequests()
})
</script>

<style scoped>
.rma-list {
    --surface: #ffffff;
    --ground: #f8fafc;
    --line: #e2e8f0;
    --ink: #0f172a;
    --ink-soft: #334155;
    --ink-mute: #64748b;
    --primary: #2563eb;
    --primary-soft: #eff6ff;
    --ok: #16a34a;
    --ok-soft: #f0fdf4;
    --warn: #d97706;
    --warn-soft: #fffbeb;
    --bad: #dc2626;
    --bad-soft: #fef2f2;
    --purple: #7c3aed;
    --purple-soft: #f5f3ff;

    font-family: 'Cairo', sans-serif;
    color: var(--ink);
}

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card-wrapper { border-radius: 14px; }

.stat-card-inner { display: flex; align-items: center; gap: 1rem; }

.stat-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.stat-icon-box.total { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.pending { background: var(--warn-soft); color: var(--warn); }
.stat-icon-box.completed { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.refund { background: var(--primary-soft); color: var(--primary); }

.stat-details h3 { margin: 0; font-size: 1.55rem; font-weight: 800; color: var(--ink); line-height: 1.2; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.82rem; font-weight: 600; }

/* ── Panel / filters ────────────────────────────────────────────────── */
.panel-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
    margin-top: 1.25rem;
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 1rem;
    padding-bottom: 1.1rem;
    margin-bottom: 1.1rem;
    border-bottom: 1px solid var(--line);
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    width: 170px;
}

.filter-item-search { width: 230px; }

.filter-item label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ink-mute);
}

.filter-item :deep(.el-select),
.filter-item :deep(.el-date-editor) { width: 100%; }

.filter-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper {
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
}

.rma-number-badge {
    display: inline-block;
    color: var(--primary);
    font-weight: 700;
    cursor: pointer;
    background: var(--primary-soft);
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Cascadia Mono', Consolas, monospace;
    border: 1px dashed #bfdbfe;
    transition: all 0.15s ease;
}

.rma-number-badge:hover { background: var(--primary); color: #fff; border-style: solid; }

.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; }
.cell-primary { font-weight: 700; color: var(--ink); }
.cell-secondary { font-size: 0.76rem; color: var(--ink-mute); }
.cell-money { font-weight: 700; font-variant-numeric: tabular-nums; }
.cell-empty { color: #cbd5e1; }

/* ── Status badge ───────────────────────────────────────────────────── */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.status-badge .dot { width: 6px; height: 6px; border-radius: 50%; flex: none; }

.status-badge.pending { background: var(--warn-soft); color: #b45309; border-color: #fde68a; }
.status-badge.pending .dot { background: #b45309; animation: pulse-dot 2s infinite ease-in-out; }
.status-badge.approved { background: var(--ok-soft); color: #15803d; border-color: #bbf7d0; }
.status-badge.approved .dot { background: #15803d; }
.status-badge.received { background: var(--purple-soft); color: #6d28d9; border-color: #ddd6fe; }
.status-badge.received .dot { background: #6d28d9; }
.status-badge.rejected { background: var(--bad-soft); color: #b91c1c; border-color: #fca5a5; }
.status-badge.rejected .dot { background: #b91c1c; }
.status-badge.completed { background: var(--primary-soft); color: #1d4ed8; border-color: #bfdbfe; }
.status-badge.completed .dot { background: #1d4ed8; }
.status-badge.cancelled { background: var(--ground); color: var(--ink-soft); border-color: var(--line); }
.status-badge.cancelled .dot { background: var(--ink-soft); }

@keyframes pulse-dot {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
    100% { transform: scale(1); opacity: 1; }
}

.row-actions { display: flex; gap: 0.4rem; justify-content: center; }

.pagination-row {
    margin-top: 1.1rem;
    display: flex;
    justify-content: flex-end;
}

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .filters-row { flex-direction: column; align-items: stretch; }
    .filter-item,
    .filter-item-search { width: 100%; }
    .filter-actions { width: 100%; }
    .filter-actions .el-button,
    .filter-actions .el-dropdown { flex: 1; }
}
</style>
