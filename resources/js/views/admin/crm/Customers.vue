<template>
  <div class="crm-page crm-customers">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><User /></el-icon>
        </div>
        <div>
          <h1 class="header-title">{{ $t('customers') }}</h1>
          <p class="header-subtitle">إدارة سجلات وبيانات العملاء، حدود الائتمان، والأرصدة المستحقة وحالة الحسابات</p>
        </div>
      </div>
      <div class="header-actions">
        <el-button type="primary" class="btn-create-premium" @click="createCustomer">
          <el-icon><Plus /></el-icon> {{ $t('new_client') }}
        </el-button>
      </div>
    </div>

    <!-- Statistics Cards Grid -->
    <el-row :gutter="20" class="stats-grid">
      <el-col :xs="24" :sm="12" :md="6">
        <div class="stat-card total" v-loading="store.loading">
          <div class="stat-card-glow"></div>
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-label">إجمالي العملاء</span>
              <span class="stat-value">{{ store.pagination.total }}</span>
            </div>
            <div class="stat-icon-wrapper">
              <el-icon><User /></el-icon>
            </div>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :md="6">
        <div class="stat-card balance" v-loading="store.loading">
          <div class="stat-card-glow"></div>
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-label">إجمالي الأرصدة المستحقة</span>
              <span class="stat-value">{{ formatCurrency(totalOutstandingBalance) }}</span>
            </div>
            <div class="stat-icon-wrapper">
              <el-icon><Money /></el-icon>
            </div>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :md="6">
        <div class="stat-card credit" v-loading="store.loading">
          <div class="stat-card-glow"></div>
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-label">إجمالي الحدود الائتمانية</span>
              <span class="stat-value">{{ formatCurrency(totalCreditLimits) }}</span>
            </div>
            <div class="stat-icon-wrapper">
              <el-icon><CreditCard /></el-icon>
            </div>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :md="6">
        <div class="stat-card recent" v-loading="store.loading">
          <div class="stat-card-glow"></div>
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-label">عملاء نشطين</span>
              <span class="stat-value">{{ activeCustomersCount }}</span>
            </div>
            <div class="stat-icon-wrapper">
              <el-icon><CircleCheck /></el-icon>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- Filters and Table Panel -->
    <el-card shadow="never" class="table-card-premium">
      <div class="filter-bar-premium">
        <el-form :inline="true" class="premium-filter-form">
          <el-form-item label="بحث سريع">
            <el-input
              v-model="searchQuery"
              :placeholder="$t('search_by_customer_name_email_or_phone')"
              clearable
              class="search-input"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item label="حالة الحساب">
            <el-select v-model="statusFilter" placeholder="كل الحالات" clearable class="status-select">
              <el-option value="active" label="نشط" />
              <el-option value="inactive" label="غير نشط" />
            </el-select>
          </el-form-item>
        </el-form>
      </div>

      <div class="table-wrapper-premium">
        <el-table
          v-loading="store.loading"
          :data="filteredCustomers"
          stripe
          class="premium-table"
          header-row-class-name="premium-table-header"
        >
          <!-- Avatar Column -->
          <el-table-column width="70" align="center">
            <template #default="{ row }">
              <div class="customer-avatar" :style="{ backgroundColor: getAvatarColor(row.name) }">
                {{ getInitials(row.name) }}
              </div>
            </template>
          </el-table-column>

          <!-- Customer info (Name, Company) -->
          <el-table-column prop="name" label="العميل والشركة" min-width="200">
            <template #default="{ row }">
              <div class="customer-info-cell">
                <span class="customer-name" @click="viewCustomer(row)">{{ row.name }}</span>
                <span class="customer-company" v-if="row.company">{{ row.company }}</span>
              </div>
            </template>
          </el-table-column>

          <!-- Contacts (Email, Phone) -->
          <el-table-column label="الاتصال" min-width="180">
            <template #default="{ row }">
              <div class="customer-contact-cell">
                <span class="email" v-if="row.email">{{ row.email }}</span>
                <span class="phone" v-if="row.phone">{{ row.phone }}</span>
              </div>
            </template>
          </el-table-column>

          <!-- VAT Number -->
          <el-table-column prop="tax_number" label="الرقم الضريبي" width="130">
            <template #default="{ row }">
              <span class="vat-badge" v-if="row.tax_number">{{ row.tax_number }}</span>
              <span class="text-placeholder" v-else>-</span>
            </template>
          </el-table-column>

          <!-- Balance / Debt -->
          <el-table-column prop="balance" label="الرصيد القائم" width="130">
            <template #default="{ row }">
              <span class="balance-value" :class="{ 'has-debt': row.balance > 0 }">
                {{ formatCurrency(row.balance) }}
              </span>
            </template>
          </el-table-column>

          <!-- Credit Limit -->
          <el-table-column prop="credit_limit" label="الحد الائتماني" width="130">
            <template #default="{ row }">
              <span>{{ formatCurrency(row.credit_limit || 0) }}</span>
            </template>
          </el-table-column>

          <!-- Status -->
          <el-table-column :label="$t('status')" width="110" align="center">
            <template #default="{ row }">
              <span class="status-dot-badge" :class="row.status || 'inactive'">
                <span class="dot"></span>
                <span class="text">{{ (row.status || 'inactive') === 'active' ? 'نشط' : 'غير نشط' }}</span>
              </span>
            </template>
          </el-table-column>

          <!-- Actions -->
          <el-table-column :label="$t('procedures')" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <div class="actions-wrapper">
                <el-tooltip content="الملف الشخصي للعميل" placement="top" :enterable="false">
                  <el-button class="action-btn view" size="small" circle @click="viewCustomer(row)">
                    <el-icon><View /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="تعديل البيانات" placement="top" :enterable="false">
                  <el-button class="action-btn edit" size="small" circle @click="editCustomer(row)">
                    <el-icon><Edit /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="حذف العميل" placement="top" :enterable="false">
                  <el-button class="action-btn delete" size="small" circle @click="deleteCustomer(row)">
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="pagination-container-premium" v-if="store.pagination.total > 0">
        <el-pagination
          v-model:current-page="store.pagination.current_page"
          v-model:page-size="store.pagination.per_page"
          :total="store.pagination.total"
          layout="total, prev, pager, next"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { User, Plus, Search, View, Edit, Delete, Money, CreditCard, CircleCheck } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useCustomersStore } from '@/stores/customers';

const router = useRouter();
const store = useCustomersStore();

const searchQuery = ref('');
const statusFilter = ref('');

const loadCustomersData = () => {
  store.fetchCustomers({
    page: store.pagination.current_page,
    per_page: store.pagination.per_page
  }).catch(() => {});
};

const handlePageChange = (page) => {
  store.pagination.current_page = page;
  loadCustomersData();
};

const filteredCustomers = computed(() => {
  let list = store.customers;
  
  if (statusFilter.value) {
    list = list.filter(c => c.status === statusFilter.value);
  }

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase();
    list = list.filter(customer => {
      return [
        customer.name,
        customer.company,
        customer.email,
        customer.phone,
        customer.tax_number
      ].some(field => String(field || '').toLowerCase().includes(query));
    });
  }

  return list;
});

const totalOutstandingBalance = computed(() => {
  return store.customers.reduce((sum, c) => sum + (parseFloat(c.balance) || 0), 0);
});

const totalCreditLimits = computed(() => {
  return store.customers.reduce((sum, c) => sum + (parseFloat(c.credit_limit) || 0), 0);
});

const activeCustomersCount = computed(() => {
  return store.customers.filter(c => c.status === 'active').length;
});

const createCustomer = () => {
  router.push({ name: 'admin.crm.customers.create' });
};

const viewCustomer = (customer) => {
  if (!customer?.id) return;
  router.push({ name: 'admin.crm.customers.show', params: { id: customer.id } });
};

const editCustomer = (customer) => {
  if (!customer?.id) return;
  router.push({ name: 'admin.crm.customers.edit', params: { id: customer.id } });
};

const deleteCustomer = async (customer) => {
  if (!customer?.id) return;

  try {
    await ElMessageBox.confirm(
      'هل أنت متأكد من رغبتك في حذف هذا العميل نهائياً؟ سيؤدي ذلك لإزالة بيانات جهة الاتصال والحدود الائتمانية.',
      'تأكيد الحذف',
      {
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        type: 'warning'
      }
    );

    await store.deleteCustomer(customer.id);
    ElMessage.success('تم حذف العميل بنجاح');
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('فشل عملية الحذف');
    }
  }
};

const getInitials = (name) => {
  if (!name) return 'C';
  const parts = name.split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
};

const getAvatarColor = (name) => {
  if (!name) return '#2563eb';
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  const colors = ['#6366f1', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#14b8a6'];
  const index = Math.abs(hash) % colors.length;
  return colors[index];
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val || 0);
};

onMounted(() => {
  loadCustomersData();
});
</script>

<style scoped>
.crm-customers {
  padding: 30px;
  background: #f8fafc;
  min-height: 100vh;
  font-family: 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  direction: rtl;
}

/* Premium Page Header */
.page-header-premium {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  background: #ffffff;
  padding: 24px;
  border-radius: 16px;
  box-shadow: 0 4px 20px -2px rgba(148, 163, 184, 0.08);
  border: 1px solid #f1f5f9;
}

.header-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon-box {
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  color: #2563eb;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  box-shadow: inset 0 2px 4px rgba(37, 99, 235, 0.05);
}

.header-title {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.header-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.btn-create-premium {
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 600;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border: none;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
  transition: all 0.2s ease;
}

.btn-create-premium:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
}

/* Stats Cards Grid */
.stats-grid {
  margin-bottom: 25px;
}

.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-radius: 16px;
  padding: 24px;
  overflow: hidden;
  box-shadow: 0 4px 20px -2px rgba(148, 163, 184, 0.06);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -10px rgba(148, 163, 184, 0.2);
}

.stat-card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  filter: blur(40px);
  opacity: 0.12;
  pointer-events: none;
}

.stat-card.total .stat-card-glow { background: #6366f1; }
.stat-card.balance .stat-card-glow { background: #ef4444; }
.stat-card.credit .stat-card-glow { background: #10b981; }
.stat-card.recent .stat-card-glow { background: #2563eb; }

.stat-card-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 1;
}

.stat-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-label {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.stat-card.total .stat-icon-wrapper { background: #f5f3ff; color: #7c3aed; }
.stat-card.balance .stat-icon-wrapper { background: #fef2f2; color: #dc2626; }
.stat-card.credit .stat-icon-wrapper { background: #ecfdf5; color: #059669; }
.stat-card.recent .stat-icon-wrapper { background: #eff6ff; color: #2563eb; }

/* Filter & Table Card */
.table-card-premium {
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 24px -2px rgba(148, 163, 184, 0.06);
  background: #ffffff;
  padding: 24px;
}

.filter-bar-premium {
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f1f5f9;
}

.premium-filter-form {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.search-input {
  width: 280px;
}

.status-select {
  width: 150px;
}

/* Table styling */
.table-wrapper-premium {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.premium-table {
  --el-table-border-color: #f1f5f9;
  --el-table-header-bg-color: #f8fafc;
}

.premium-table-header th {
  color: #475569 !important;
  font-weight: 700 !important;
  font-size: 13px !important;
  padding: 14px 0 !important;
}

.customer-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  color: #ffffff;
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.customer-info-cell, .customer-contact-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.customer-name {
  font-weight: 700;
  color: #2563eb;
  cursor: pointer;
}

.customer-name:hover {
  text-decoration: underline;
}

.customer-company, .phone {
  font-size: 12px;
  color: #64748b;
}

.email {
  font-weight: 500;
  color: #1e293b;
}

.vat-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #e2e8f0;
}

.balance-value {
  font-weight: 700;
  color: #475569;
}

.balance-value.has-debt {
  color: #dc2626;
  background: #fef2f2;
  padding: 2px 6px;
  border-radius: 4px;
}

.text-placeholder {
  color: #cbd5e1;
}

/* Status dots */
.status-dot-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}

.status-dot-badge.active { background: #ecfdf5; color: #059669; }
.status-dot-badge.inactive { background: #f1f5f9; color: #64748b; }

.status-dot-badge .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-dot-badge.active .dot { background: #059669; }
.status-dot-badge.inactive .dot { background: #64748b; }

/* Actions */
.actions-wrapper {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.action-btn {
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.action-btn:hover {
  transform: scale(1.1);
}

.action-btn.view:hover { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.action-btn.edit:hover { background: #fffbeb; color: #d97706; border-color: #fde68a; }
.action-btn.delete:hover { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }

.pagination-container-premium {
  margin-top: 24px;
  display: flex;
  justify-content: flex-end;
}
</style>
