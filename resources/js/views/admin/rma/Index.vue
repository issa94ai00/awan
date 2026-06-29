<template>
  <div class="rma-container">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><RefreshLeft /></el-icon>
        </div>
        <div>
          <h1 class="header-title">{{ $t('rma.title') }}</h1>
          <p class="header-subtitle">إدارة ومتابعة طلبات المرتجعات والاستبدال وحسابات التعويض المالي للعملاء</p>
        </div>
      </div>
      <el-button type="primary" class="btn-create-premium" @click="createRma">
        <el-icon><Plus /></el-icon> {{ $t('rma.create_return') }}
      </el-button>
    </div>

    <!-- Statistics Cards Grid -->
    <el-row :gutter="20" class="stats-grid">
      <el-col :xs="24" :sm="12" :md="6" v-for="card in statCards" :key="card.key">
        <div class="stat-card" :class="card.key" v-loading="statsLoading">
          <div class="stat-card-glow"></div>
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-label">{{ card.title }}</span>
              <span class="stat-value">{{ card.value }}</span>
            </div>
            <div class="stat-icon-wrapper">
              <el-icon><component :is="card.icon" /></el-icon>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- Filters and Table Card -->
    <el-card class="table-card-premium" shadow="never">
      <div class="filter-bar-premium">
        <el-form :inline="true" :model="filters" class="premium-filter-form">
          <el-form-item label="بحث">
            <el-input 
              v-model="filters.search" 
              placeholder="رقم المرتجع، العميل، رقم الطلب" 
              clearable 
              @keyup.enter="loadRmaRequests"
              class="premium-search-input"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item :label="$t('rma.status')">
            <el-select v-model="filters.status" placeholder="كل الحالات" clearable @change="loadRmaRequests" class="premium-select">
              <el-option value="pending" label="قيد الانتظار" />
              <el-option value="approved" label="تمت الموافقة" />
              <el-option value="rejected" label="مرفوض" />
              <el-option value="completed" label="مكتمل" />
              <el-option value="cancelled" label="ملغي" />
            </el-select>
          </el-form-item>
          <el-form-item :label="$t('rma.return_type')">
            <el-select v-model="filters.return_type" placeholder="كل الأنواع" clearable @change="loadRmaRequests" class="premium-select">
              <el-option value="refund" label="استرداد نقدي" />
              <el-option value="exchange" label="استبدال" />
              <el-option value="store_credit" label="رصيد متجر" />
            </el-select>
          </el-form-item>
          <el-form-item label="من تاريخ">
            <el-date-picker 
              v-model="filters.from_date" 
              type="date" 
              placeholder="اختر التاريخ"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              @change="loadRmaRequests"
              class="premium-date-picker"
            />
          </el-form-item>
          <el-form-item label="إلى تاريخ">
            <el-date-picker 
              v-model="filters.to_date" 
              type="date" 
              placeholder="اختر التاريخ"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              @change="loadRmaRequests"
              class="premium-date-picker"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadRmaRequests" class="btn-search-premium">
              <el-icon><Search /></el-icon> بحث
            </el-button>
            <el-button @click="resetFilters" class="btn-reset-premium">إعادة تعيين</el-button>
          </el-form-item>
          <el-form-item v-if="selectedRows.length > 0">
            <el-dropdown @command="handleBulkAction" class="bulk-actions-dropdown">
              <el-button type="warning" class="btn-bulk-premium">
                <el-icon><Operation /></el-icon> إجراءات جماعية ({{ selectedRows.length }})
                <el-icon class="el-icon--right"><ArrowDown /></el-icon>
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="approve" :disabled="!canBulkApprove">
                    <el-icon><Check /></el-icon> موافقة جماعية
                  </el-dropdown-item>
                  <el-dropdown-item command="reject" :disabled="!canBulkReject">
                    <el-icon><Close /></el-icon> رفض جماعي
                  </el-dropdown-item>
                  <el-dropdown-item command="cancel" :disabled="!canBulkCancel">
                    <el-icon><Delete /></el-icon> إلغاء جماعي
                  </el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </el-form-item>
          <el-form-item>
            <el-button @click="exportData" class="btn-export-premium">
              <el-icon><Download /></el-icon> تصدير
            </el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- Table Section -->
      <div class="table-wrapper-premium">
        <el-table 
          :data="rmaRequests" 
          v-loading="loading" 
          stripe 
          class="premium-table" 
          header-row-class-name="premium-table-header"
          @selection-change="handleSelectionChange"
        >
          <el-table-column type="selection" width="55" align="center" />
          <el-table-column prop="rma_number" label="رقم المرتجع" width="130">
            <template #default="{ row }">
              <span class="rma-number-badge" @click="viewRma(row)">{{ row.rma_number }}</span>
            </template>
          </el-table-column>
          
          <el-table-column prop="customer" label="العميل" min-width="180">
            <template #default="{ row }">
              <div class="customer-info-cell">
                <span class="customer-name">{{ row.customer?.name || row.customer || 'N/A' }}</span>
                <span class="customer-subtext" v-if="row.customer?.phone">{{ row.customer.phone }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="order_number" label="طلب البيع الأصلي" min-width="150">
            <template #default="{ row }">
              <div class="order-info-cell">
                <span class="order-number">#{{ row.sales_order?.order_number || row.order_number || 'N/A' }}</span>
                <span class="order-date" v-if="row.sales_order?.order_date">{{ row.sales_order.order_date }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="return_type" label="نوع التسوية" width="120">
            <template #default="{ row }">
              <el-tag :type="getReturnTypeClass(row.type || row.return_type)" class="premium-tag">
                {{ getReturnTypeLabel(row.type || row.return_type) }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column prop="refund_amount" label="قيمة التعويض" width="130">
            <template #default="{ row }">
              <span class="amount-value" v-if="row.refund_amount > 0">{{ formatCurrency(row.refund_amount) }}</span>
              <span class="amount-none" v-else>-</span>
            </template>
          </el-table-column>

          <el-table-column prop="status" label="حالة الطلب" width="120">
            <template #default="{ row }">
              <span class="status-dot-badge" :class="row.status">
                <span class="dot"></span>
                <span class="text">{{ getStatusLabel(row.status) }}</span>
              </span>
            </template>
          </el-table-column>

          <el-table-column prop="requested_at" label="تاريخ التقديم" width="160">
            <template #default="{ row }">
              {{ formatDate(row.requested_at || row.created_at) }}
            </template>
          </el-table-column>

          <el-table-column label="العمليات" width="180" align="center" fixed="right">
            <template #default="{ row }">
              <div class="actions-wrapper">
                <el-tooltip content="عرض التفاصيل" placement="top" :enterable="false">
                  <el-button class="action-btn view" size="small" circle @click="viewRma(row)">
                    <el-icon><View /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="تعديل الطلب" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button class="action-btn edit" size="small" circle @click="editRma(row)">
                    <el-icon><Edit /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="موافقة" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button class="action-btn approve" size="small" circle @click="approveRma(row)">
                    <el-icon><Check /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="رفض" placement="top" :enterable="false" v-if="row.status === 'pending'">
                  <el-button class="action-btn reject" size="small" circle @click="rejectRma(row)">
                    <el-icon><Close /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="pagination-container-premium">
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
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { RefreshLeft, Plus, Search, View, Check, Close, Edit, Files, Finished, Warning, Tickets, Operation, ArrowDown, Download, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import rmaService from '@/services/rma'

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
  { key: 'total', title: 'إجمالي الطلبات', value: statistics.value.total_requests, icon: Tickets },
  { key: 'pending', title: 'بانتظار الموافقة', value: statistics.value.pending, icon: Warning },
  { key: 'completed', title: 'طلبات مكتملة', value: statistics.value.completed, icon: Finished },
  { key: 'refund', title: 'مبالغ مستردة', value: formatCurrency(statistics.value.total_refund_amount), icon: Files }
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
    ElMessage.error('خطأ في تحميل طلبات الإرجاع')
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
      await ElMessageBox.confirm(`هل أنت متأكد من الموافقة على ${ids.length} طلب؟`, 'تأكيد الموافقة الجماعية', {
        type: 'warning',
        confirmButtonText: 'موافق',
        cancelButtonText: 'إلغاء'
      })
      await Promise.all(ids.map(id => rmaService.approveRma(id)))
      ElMessage.success(`تمت الموافقة على ${ids.length} طلب بنجاح`)
    } else if (action === 'reject') {
      const { value } = await ElMessageBox.prompt('يرجى كتابة سبب رفض الطلبات:', 'رفض جماعي', {
        type: 'warning',
        inputPattern: /.+/,
        inputErrorMessage: 'سبب الرفض مطلوب',
        confirmButtonText: 'رفض',
        cancelButtonText: 'إلغاء'
      })
      await Promise.all(ids.map(id => rmaService.rejectRma(id, { reason: value })))
      ElMessage.success(`تم رفض ${ids.length} طلب بنجاح`)
    } else if (action === 'cancel') {
      await ElMessageBox.confirm(`هل أنت متأكد من إلغاء ${ids.length} طلب؟`, 'تأكيد الإلغاء الجماعي', {
        type: 'warning',
        confirmButtonText: 'موافق',
        cancelButtonText: 'إلغاء'
      })
      await Promise.all(ids.map(id => rmaService.cancelRma(id)))
      ElMessage.success(`تم إلغاء ${ids.length} طلب بنجاح`)
    }
    
    selectedRows.value = []
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت العملية')
    }
  }
}

const exportData = async () => {
  try {
    const response = await rmaService.exportRmaRequests({
      status: filters.value.status,
      type: filters.value.return_type,
      from_date: filters.value.from_date,
      to_date: filters.value.to_date
    }, 'csv')
    
    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.setAttribute('href', url)
    link.setAttribute('download', `rma-requests-${new Date().toISOString().split('T')[0]}.csv`)
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    
    ElMessage.success('تم تصدير البيانات بنجاح')
  } catch (error) {
    console.error('Export failed:', error)
    ElMessage.error('فشل تصدير البيانات')
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
    await ElMessageBox.confirm('هل أنت متأكد من الموافقة على طلب الإرجاع هذا؟ سيتمكن المندوب من استلام البضائع بعد ذلك.', 'تأكيد الموافقة', {
      type: 'warning',
      confirmButtonText: 'موافق',
      cancelButtonText: 'إلغاء'
    })
    await rmaService.approveRma(row.id)
    ElMessage.success('تمت الموافقة على طلب الإرجاع بنجاح')
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت عملية الموافقة')
    }
  }
}

const rejectRma = async (row) => {
  try {
    const { value } = await ElMessageBox.prompt('يرجى كتابة سبب رفض طلب الإرجاع:', 'رفض الطلب', {
      type: 'warning',
      inputPattern: /.+/,
      inputErrorMessage: 'سبب الرفض مطلوب',
      confirmButtonText: 'رفض',
      cancelButtonText: 'إلغاء'
    })
    await rmaService.rejectRma(row.id, { reason: value })
    ElMessage.success('تم رفض طلب الإرجاع بنجاح')
    loadRmaRequests()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'فشلت عملية الرفض')
    }
  }
}

const getStatusLabel = (status) => {
  const labels = {
    pending: 'بانتظار الموافقة',
    approved: 'تمت الموافقة',
    rejected: 'مرفوض',
    completed: 'مكتمل',
    cancelled: 'ملغي'
  }
  return labels[status] || status
}

const getReturnTypeLabel = (type) => {
  const labels = {
    refund: 'استرداد نقدي',
    exchange: 'استبدال',
    store_credit: 'رصيد متجر'
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

const formatCurrency = (val) => {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)
}

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
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap');

.rma-container {
  padding: 30px;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  min-height: 100vh;
  font-family: 'Cairo', 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  direction: rtl;
}

/* Premium Page Header */
.page-header-premium {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  padding: 24px 32px;
  border-radius: 20px;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.header-info {
  display: flex;
  align-items: center;
  gap: 20px;
}

.header-icon-box {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  color: #2563eb;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  box-shadow: inset 0 2px 4px rgba(37, 99, 235, 0.06), 0 4px 12px rgba(37, 99, 235, 0.08);
}

.header-title {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px 0;
  letter-spacing: -0.5px;
}

.header-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  font-weight: 500;
}

.btn-create-premium {
  padding: 14px 28px;
  border-radius: 12px;
  font-weight: 700;
  font-family: 'Cairo', sans-serif;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border: none;
  color: #ffffff;
  box-shadow: 0 8px 20px -6px rgba(37, 99, 235, 0.4);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-create-premium:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px -4px rgba(37, 99, 235, 0.5);
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.btn-create-premium:active {
  transform: translateY(0);
}

/* Stats Cards Grid */
.stats-grid {
  margin-bottom: 30px;
}

.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 20px;
  padding: 24px;
  overflow: hidden;
  box-shadow: 0 4px 20px -2px rgba(148, 163, 184, 0.05);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 32px -10px rgba(148, 163, 184, 0.25);
  border-color: rgba(37, 99, 235, 0.2);
}

.stat-card-glow {
  position: absolute;
  top: -20px;
  right: -20px;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  filter: blur(40px);
  opacity: 0.15;
  pointer-events: none;
  transition: all 0.3s ease;
}

.stat-card:hover .stat-card-glow {
  transform: scale(1.2);
  opacity: 0.25;
}

.stat-card.total .stat-card-glow { background: #6366f1; }
.stat-card.pending .stat-card-glow { background: #f59e0b; }
.stat-card.completed .stat-card-glow { background: #10b981; }
.stat-card.refund .stat-card-glow { background: #3b82f6; }

.stat-card-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 2;
}

.stat-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-label {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.5px;
}

.stat-icon-wrapper {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover .stat-icon-wrapper {
  transform: scale(1.1) rotate(5deg);
}

.stat-card.total .stat-icon-wrapper { background: #f5f3ff; color: #7c3aed; }
.stat-card.pending .stat-icon-wrapper { background: #fffbeb; color: #d97706; }
.stat-card.completed .stat-icon-wrapper { background: #ecfdf5; color: #059669; }
.stat-card.refund .stat-icon-wrapper { background: #eff6ff; color: #2563eb; }

/* Filter & Table Card */
.table-card-premium {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
  background: #ffffff;
  padding: 8px;
}

.filter-bar-premium {
  margin-bottom: 24px;
  padding: 16px 20px 24px 20px;
  border-bottom: 1px solid #f1f5f9;
}

.premium-filter-form {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.premium-filter-form :deep(.el-form-item) {
  margin-bottom: 0;
  margin-right: 0;
}

.premium-filter-form :deep(.el-form-item__label) {
  font-weight: 700;
  color: #475569;
  font-size: 13px;
  margin-bottom: 6px;
}

.premium-select {
  width: 190px;
}

.premium-select :deep(.el-input__wrapper) {
  border-radius: 10px;
  padding: 6px 12px;
  border-color: #cbd5e1;
}

.premium-search-input {
  width: 250px;
}

.premium-search-input :deep(.el-input__wrapper) {
  border-radius: 10px;
  padding: 6px 12px;
  border-color: #cbd5e1;
}

.premium-date-picker {
  width: 160px;
}

.premium-date-picker :deep(.el-input__wrapper) {
  border-radius: 10px;
  padding: 6px 12px;
  border-color: #cbd5e1;
}

.btn-search-premium {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border: none;
  font-weight: 700;
  border-radius: 10px;
  padding: 12px 24px;
  color: white;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
  transition: all 0.2s;
  height: 40px;
  font-family: 'Cairo', sans-serif;
}

.btn-search-premium:hover {
  background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.25);
  transform: translateY(-1px);
}

.btn-reset-premium {
  border: 1px solid #cbd5e1;
  color: #475569;
  font-weight: 600;
  border-radius: 10px;
  padding: 12px 24px;
  transition: all 0.2s;
  height: 40px;
  font-family: 'Cairo', sans-serif;
}

.btn-reset-premium:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

.btn-bulk-premium {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  border: none;
  font-weight: 700;
  border-radius: 10px;
  padding: 12px 20px;
  color: white;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
  transition: all 0.2s;
  height: 40px;
  font-family: 'Cairo', sans-serif;
}

.btn-bulk-premium:hover {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
  transform: translateY(-1px);
}

.btn-export-premium {
  border: 1px solid #10b981;
  color: #10b981;
  font-weight: 600;
  border-radius: 10px;
  padding: 12px 20px;
  transition: all 0.2s;
  height: 40px;
  font-family: 'Cairo', sans-serif;
}

.btn-export-premium:hover {
  background: #10b981;
  color: white;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.25);
  transform: translateY(-1px);
}

/* Premium Table Styles */
.table-wrapper-premium {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  margin: 0 12px;
}

.premium-table {
  --el-table-border-color: #f1f5f9;
  --el-table-header-bg-color: #f8fafc;
}

.premium-table :deep(.el-table__row) {
  transition: all 0.2s ease-in-out;
}

.premium-table :deep(.el-table__row:hover) {
  background-color: #f8fafc !important;
}

.premium-table-header th {
  color: #475569 !important;
  font-weight: 800 !important;
  font-size: 13px !important;
  padding: 16px 0 !important;
  background-color: #f8fafc !important;
}

.rma-number-badge {
  color: #2563eb;
  font-weight: 700;
  cursor: pointer;
  background: #eff6ff;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-family: monospace, 'Cairo';
  border: 1px dashed #bfdbfe;
  transition: all 0.2s ease-in-out;
  display: inline-block;
}

.rma-number-badge:hover {
  background: #2563eb;
  color: #ffffff;
  border-style: solid;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

.customer-info-cell, .order-info-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px 0;
}

.customer-name, .order-number {
  font-weight: 700;
  color: #1e293b;
  font-size: 14px;
}

.customer-subtext, .order-date {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.premium-tag {
  border-radius: 8px;
  font-weight: 700;
  font-size: 11px;
  padding: 6px 10px;
  border: 1px solid transparent;
}

.amount-value {
  font-weight: 700;
  color: #0f172a;
}

.amount-none {
  color: #cbd5e1;
}

/* Status Badges */
.status-dot-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid transparent;
}

.status-dot-badge.pending { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.status-dot-badge.approved { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.status-dot-badge.rejected { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
.status-dot-badge.completed { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.status-dot-badge.cancelled { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

.status-dot-badge .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

@keyframes pulse-dot {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.5; }
  100% { transform: scale(1); opacity: 1; }
}

.status-dot-badge.pending .dot { background: #b45309; animation: pulse-dot 2s infinite ease-in-out; }
.status-dot-badge.approved .dot { background: #15803d; }
.status-dot-badge.rejected .dot { background: #b91c1c; }
.status-dot-badge.completed .dot { background: #1d4ed8; }
.status-dot-badge.cancelled .dot { background: #475569; }

/* Action Buttons */
.actions-wrapper {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.action-btn {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  color: #475569;
  border-radius: 8px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

.action-btn.view:hover { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.action-btn.edit:hover { background: #d97706; color: #ffffff; border-color: #d97706; }
.action-btn.approve:hover { background: #059669; color: #ffffff; border-color: #059669; }
.action-btn.reject:hover { background: #dc2626; color: #ffffff; border-color: #dc2626; }

.pagination-container-premium {
  margin-top: 24px;
  margin-bottom: 12px;
  padding: 0 12px;
  display: flex;
  justify-content: flex-end;
}
</style>
