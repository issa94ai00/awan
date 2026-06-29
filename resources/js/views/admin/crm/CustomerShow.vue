<template>
  <div class="crm-page crm-customer-show" v-loading="loading">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><User /></el-icon>
        </div>
        <div>
          <h1 class="header-title">ملف العميل: {{ customer.name }}</h1>
          <p class="header-subtitle">{{ customer.company || 'عميل فردي' }} | {{ customer.email || 'لا يوجد بريد إلكتروني' }}</p>
        </div>
      </div>
      <div class="header-actions">
        <el-button @click="router.back()" class="btn-back-premium">
          <el-icon><Back /></el-icon> رجوع
        </el-button>
        <el-button type="warning" class="btn-edit-premium" @click="editCustomer">
          <el-icon><Edit /></el-icon> تعديل البيانات
        </el-button>
      </div>
    </div>

    <el-row :gutter="25">
      <!-- Left Panel: Contact and Financial Details Card -->
      <el-col :xs="24" :lg="8">
        <!-- Contact details -->
        <el-card shadow="never" class="details-section-card">
          <div class="profile-avatar-container">
            <div class="profile-avatar" :style="{ backgroundColor: getAvatarColor(customer.name) }">
              {{ getInitials(customer.name) }}
            </div>
            <h3>{{ customer.name }}</h3>
            <span class="status-dot-badge" :class="customer.status || 'inactive'">
              <span class="dot"></span>
              <span class="text">{{ (customer.status || 'inactive') === 'active' ? 'نشط' : 'غير نشط' }}</span>
            </span>
          </div>

          <div class="divider"></div>

          <h4 class="info-section-title">معلومات الاتصال والشركة</h4>
          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item label="اسم العميل">{{ customer.name }}</el-descriptions-item>
            <el-descriptions-item label="اسم الشركة">{{ customer.company || '-' }}</el-descriptions-item>
            <el-descriptions-item label="البريد الإلكتروني">{{ customer.email || '-' }}</el-descriptions-item>
            <el-descriptions-item label="رقم الجوال">{{ customer.phone || '-' }}</el-descriptions-item>
            <el-descriptions-item label="مصدر العميل">{{ customer.source || '-' }}</el-descriptions-item>
          </el-descriptions>

          <div class="divider"></div>

          <h4 class="info-section-title">العنوان والتخزين</h4>
          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item label="العنوان">{{ customer.address || '-' }}</el-descriptions-item>
            <el-descriptions-item label="المدينة">{{ customer.city || '-' }}</el-descriptions-item>
            <el-descriptions-item label="الدولة">{{ customer.country || '-' }}</el-descriptions-item>
            <el-descriptions-item label="الرمز البريدي">{{ customer.postal_code || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- Financial limits and stats -->
        <el-card shadow="never" class="details-section-card financial-card" style="margin-top: 25px">
          <template #header>
            <div class="section-card-header">
              <span class="dot green"></span>
              <h3>الإعدادات والملخص المالي</h3>
            </div>
          </template>

          <el-descriptions :column="1" class="descriptions-premium">
            <el-descriptions-item label="الرصيد القائم">
              <span class="balance-value" :class="{ 'has-debt': customer.balance > 0 }">
                {{ formatCurrency(customer.balance) }}
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="الحد الائتماني">
              {{ formatCurrency(customer.credit_limit || 0) }}
            </el-descriptions-item>
            <el-descriptions-item label="الرقم الضريبي">{{ customer.tax_number || '-' }}</el-descriptions-item>
            <el-descriptions-item label="العملة الافتراضية">{{ customer.currency || 'SAR' }}</el-descriptions-item>
            <el-descriptions-item label="إجمالي المشتريات">{{ formatCurrency(customer.total_purchases || 0) }}</el-descriptions-item>
            <el-descriptions-item label="تاريخ آخر شراء">{{ formatDate(customer.last_purchase_at) }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>

      <!-- Right Panel: Tabs for transactions, tickets -->
      <el-col :xs="24" :lg="16">
        <el-card shadow="never" class="table-card-premium">
          <el-tabs v-model="activeTab" class="premium-tabs">
            <!-- Tab 1: Invoices -->
            <el-tab-pane name="invoices" label="فواتير المبيعات">
              <div class="tab-content-wrapper">
                <el-table :data="invoices" stripe class="premium-table" v-loading="invoicesLoading">
                  <el-table-column prop="invoice_number" label="رقم الفاتورة" width="140" />
                  <el-table-column prop="total" label="إجمالي الفاتورة" width="130">
                    <template #default="{ row }">
                      {{ formatCurrency(row.total) }}
                    </template>
                  </el-table-column>
                  <el-table-column prop="payment_method" label="طريقة الدفع" width="120">
                    <template #default="{ row }">
                      {{ getPaymentMethodLabel(row.payment_method) }}
                    </template>
                  </el-table-column>
                  <el-table-column prop="status" label="حالة الفاتورة" width="120">
                    <template #default="{ row }">
                      <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" class="premium-tag">
                        {{ row.status === 'paid' ? 'مدفوعة' : 'معلقة' }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column prop="created_at" label="تاريخ الفاتورة" width="160">
                    <template #default="{ row }">
                      {{ formatDate(row.created_at) }}
                    </template>
                  </el-table-column>
                </el-table>
                <div v-if="!invoicesLoading && invoices.length === 0" class="empty-state">
                  لا توجد فواتير مبيعات مسجلة لهذا العميل.
                </div>
              </div>
            </el-tab-pane>

            <!-- Tab 2: Support Tickets -->
            <el-tab-pane name="tickets" label="تذاكر الدعم الفني">
              <div class="tab-content-wrapper">
                <el-table :data="tickets" stripe class="premium-table" v-loading="ticketsLoading">
                  <el-table-column prop="ticket_number" label="رقم التذكرة" width="130">
                    <template #default="{ row }">
                      #{{ row.id }}
                    </template>
                  </el-table-column>
                  <el-table-column prop="subject" label="العنوان" min-width="180" />
                  <el-table-column prop="priority" label="الأولوية" width="110">
                    <template #default="{ row }">
                      <el-tag :type="getPriorityClass(row.priority)" class="premium-tag">
                        {{ getPriorityLabel(row.priority) }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column prop="status" label="الحالة" width="110">
                    <template #default="{ row }">
                      <el-tag :type="row.status === 'open' ? 'danger' : 'success'" class="premium-tag">
                        {{ row.status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column prop="created_at" label="تاريخ الإنشاء" width="160">
                    <template #default="{ row }">
                      {{ formatDate(row.created_at) }}
                    </template>
                  </el-table-column>
                </el-table>
                <div v-if="!ticketsLoading && tickets.length === 0" class="empty-state">
                  لا توجد تذاكر دعم فني مفتوحة لهذا العميل.
                </div>
              </div>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { User, Back, Edit } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useCustomersStore } from '@/stores/customers';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const store = useCustomersStore();

const loading = ref(false);
const activeTab = ref('invoices');
const customer = ref({
  id: null,
  name: '',
  email: '',
  phone: '',
  company: '',
  address: '',
  status: 'inactive',
  notes: '',
  credit_limit: 0,
  tax_number: '',
  city: '',
  state: '',
  country: '',
  postal_code: '',
  currency: 'SAR',
  total_purchases: 0,
  last_purchase_at: null
});

const invoices = ref([]);
const invoicesLoading = ref(false);

const tickets = ref([]);
const ticketsLoading = ref(false);

const loadCustomer = async () => {
  loading.value = true;
  try {
    const data = await store.fetchCustomer(route.params.id);
    if (!data) {
      ElMessage.error('العميل غير موجود');
      router.back();
      return;
    }
    customer.value = data;
    await Promise.all([loadInvoices(), loadTickets()]);
  } catch (error) {
    console.error('Failed to load customer profile:', error);
    ElMessage.error('خطأ في تحميل ملف العميل');
  } finally {
    loading.value = false;
  }
};

const loadInvoices = async () => {
  invoicesLoading.value = true;
  try {
    const response = await axios.get('/api/admin/invoices', { params: { customer_id: route.params.id } });
    invoices.value = response.data.data?.invoices || response.data.data || [];
  } catch (error) {
    console.error('Failed to load customer invoices:', error);
    invoices.value = [];
  } finally {
    invoicesLoading.value = false;
  }
};

const loadTickets = async () => {
  ticketsLoading.value = true;
  try {
    const response = await axios.get('/api/admin/tickets', { params: { customer_id: route.params.id } });
    tickets.value = response.data.data?.tickets || response.data.data || [];
  } catch (error) {
    console.error('Failed to load customer tickets:', error);
    tickets.value = [];
  } finally {
    ticketsLoading.value = false;
  }
};

const editCustomer = () => {
  router.push({ name: 'admin.crm.customers.edit', params: { id: customer.value.id } });
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

const formatDate = (dateStr) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const getPaymentMethodLabel = (method) => {
  const labels = {
    cash: 'نقدي (كاش)',
    card: 'بطاقة ائتمانية',
    transfer: 'تحويل بنكي'
  };
  return labels[method] || method || '-';
};

const getPriorityLabel = (priority) => {
  const labels = {
    low: 'منخفضة',
    medium: 'متوسطة',
    high: 'مرتفعة',
    urgent: 'عاجلة'
  };
  return labels[priority] || priority;
};

const getPriorityClass = (priority) => {
  const classes = {
    low: 'info',
    medium: 'warning',
    high: 'danger',
    urgent: 'danger'
  };
  return classes[priority] || 'info';
};

onMounted(() => {
  loadCustomer();
});
</script>

<style scoped>
.crm-customer-show {
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
  margin-bottom: 25px;
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
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.header-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-back-premium {
  border-color: #cbd5e1;
  color: #475569;
  font-weight: 600;
  border-radius: 10px;
  padding: 12px 20px;
}

.btn-back-premium:hover {
  background: #f8fafc;
  color: #0f172a;
}

.btn-edit-premium {
  padding: 12px 20px;
  border-radius: 10px;
  font-weight: 600;
  border-color: #fde68a;
  color: #d97706;
  background: #fffbeb;
}

.btn-edit-premium:hover {
  background: #fef3c7;
  color: #b45309;
}

/* Details Section cards */
.details-section-card {
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(148, 163, 184, 0.04);
  padding: 24px;
  margin-bottom: 25px;
}

.profile-avatar-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  color: #ffffff;
  font-weight: 800;
  font-size: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.profile-avatar-container h3 {
  margin: 0;
  font-weight: 700;
  color: #0f172a;
  font-size: 18px;
}

.divider {
  height: 1px;
  background: #f1f5f9;
  margin: 20px 0;
}

.info-section-title {
  font-size: 13px;
  font-weight: 700;
  color: #94a3b8;
  margin: 0 0 12px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.section-card-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-card-header .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
}

.section-card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.descriptions-premium {
  --el-descriptions-table-border: 1px solid #f1f5f9;
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

/* Table / Tab Panel card */
.table-card-premium {
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(148, 163, 184, 0.04);
  padding: 24px;
}

.tab-content-wrapper {
  padding-top: 15px;
}

.premium-table {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.premium-tag {
  border-radius: 6px;
  font-weight: 600;
}

.empty-state {
  padding: 40px 20px;
  text-align: center;
  color: #94a3b8;
}
</style>
