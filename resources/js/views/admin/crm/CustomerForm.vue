<template>
  <div class="crm-page crm-customer-form">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><User /></el-icon>
        </div>
        <div>
          <h1 class="header-title">{{ formTitle }}</h1>
          <p class="header-subtitle">إدخال وتحديث البيانات التفصيلية للعميل، الإعدادات المالية، وعناوين الفوترة والشحن</p>
        </div>
      </div>
      <el-button @click="cancel" class="btn-back-premium">
        <el-icon><Back /></el-icon> رجوع للسجلات
      </el-button>
    </div>

    <!-- Form Section -->
    <el-card shadow="never" class="form-card-premium">
      <el-form :model="form" :rules="rules" ref="customerForm" label-position="top" status-icon>
        <!-- Element Plus Tabs for Sectioning -->
        <el-tabs v-model="activeTab" class="premium-tabs">
          <!-- Tab 1: General Info -->
          <el-tab-pane name="general" label="البيانات الأساسية">
            <div class="tab-content-wrapper">
              <el-row :gutter="20">
                <el-col :xs="24" :sm="12">
                  <el-form-item :label="$t('name')" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                    <el-input v-model="form.name" :placeholder="$t('customer_name')" class="premium-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12">
                  <el-form-item :label="$t('company')" prop="company" :error="serverErrors.company && serverErrors.company[0]">
                    <el-input v-model="form.company" :placeholder="$t('company_name')" class="premium-input" />
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row :gutter="20">
                <el-col :xs="24" :sm="12">
                  <el-form-item :label="$t('email')" prop="email" :error="serverErrors.email && serverErrors.email[0]">
                    <el-input v-model="form.email" placeholder="example@domain.com" class="premium-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12">
                  <el-form-item :label="$t('phone')" prop="phone" :error="serverErrors.phone && serverErrors.phone[0]">
                    <el-input v-model="form.phone" :placeholder="$t('phone')" class="premium-input" />
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row :gutter="20">
                <el-col :xs="24" :sm="12">
                  <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                    <el-select v-model="form.status" :placeholder="$t('choose_the_status')" class="premium-select">
                      <el-option :label="$t('active')" value="active" />
                      <el-option :label="$t('inactive')" value="inactive" />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12">
                  <el-form-item label="مصدر العميل (القناة)">
                    <el-input v-model="form.source" placeholder="مثال: متجر إلكتروني، زيارة، ترشيح..." class="premium-input" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>

          <!-- Tab 2: Financial Settings -->
          <el-tab-pane name="financial" label="الإعدادات المالية">
            <div class="tab-content-wrapper">
              <el-row :gutter="20">
                <el-col :xs="24" :sm="12">
                  <el-form-item label="الحد الائتماني (SAR)" prop="credit_limit" :error="serverErrors.credit_limit && serverErrors.credit_limit[0]">
                    <el-input-number v-model="form.credit_limit" :min="0" class="premium-number-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12">
                  <el-form-item label="الرقم الضريبي (VAT ID)" prop="tax_number" :error="serverErrors.tax_number && serverErrors.tax_number[0]">
                    <el-input v-model="form.tax_number" placeholder="مثال: 300012345600003" class="premium-input" />
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row :gutter="20">
                <el-col :xs="24" :sm="12">
                  <el-form-item label="العملة الافتراضية">
                    <el-select v-model="form.currency" class="premium-select">
                      <el-option value="SAR" label="ريال سعودي (SAR)" />
                      <el-option value="USD" label="دولار أمريكي (USD)" />
                      <el-option value="AED" label="درهم إماراتي (AED)" />
                    </el-select>
                  </el-form-item>
                </el-col>
              </el-row>

              <el-form-item :label="$t('notes')" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                <el-input type="textarea" v-model="form.notes" :placeholder="$t('additional_notes')" rows="4" />
              </el-form-item>
            </div>
          </el-tab-pane>

          <!-- Tab 3: Detailed Address -->
          <el-tab-pane name="address" label="العناوين والموقع">
            <div class="tab-content-wrapper">
              <el-form-item :label="$t('address')" prop="address" :error="serverErrors.address && serverErrors.address[0]">
                <el-input v-model="form.address" placeholder="العنوان بالتفصيل (الحي، الشارع)" class="premium-input" />
              </el-form-item>

              <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="6">
                  <el-form-item label="المدينة">
                    <el-input v-model="form.city" placeholder="المدينة" class="premium-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                  <el-form-item label="المنطقة/الولاية">
                    <el-input v-model="form.state" placeholder="المنطقة" class="premium-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                  <el-form-item label="الدولة">
                    <el-input v-model="form.country" placeholder="الدولة" class="premium-input" />
                  </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12" :md="6">
                  <el-form-item label="الرمز البريدي">
                    <el-input v-model="form.postal_code" placeholder="الرمز البريدي" class="premium-input" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>
        </el-tabs>

        <!-- Actions Panel -->
        <div class="form-actions">
          <el-button type="primary" @click="submitForm" :loading="customersStore.loading" class="btn-save-premium">
            {{ submitLabel }}
          </el-button>
          <el-button @click="cancel" class="btn-cancel-premium">{{ $t('cancel') }}</el-button>
        </div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { User, Back } from '@element-plus/icons-vue';
import { useCustomersStore } from '@/stores/customers';

const router = useRouter();
const route = useRoute();
const customersStore = useCustomersStore();
const customerForm = ref(null);
const activeTab = ref('general');

const form = ref({
  name: '',
  email: '',
  phone: '',
  company: '',
  address: '',
  status: 'active',
  notes: '',
  credit_limit: 0,
  tax_number: '',
  city: '',
  state: '',
  country: 'المملكة العربية السعودية',
  postal_code: '',
  currency: 'SAR',
  source: ''
});

const rules = {
  name: [{ required: true, message: 'اسم العميل مطلوب', trigger: 'blur' }],
  email: [{ type: 'email', message: 'البريد الإلكتروني غير صالح', trigger: 'blur' }],
  status: [{ required: true, message: 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل بيانات العميل' : 'إضافة عميل جديد'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث البيانات' : 'حفظ العميل'));
const serverErrors = computed(() => customersStore.validationErrors || {});

const loadCustomer = async () => {
  if (!isEdit.value) return;
  const customer = await customersStore.fetchCustomer(route.params.id).catch(() => null);
  if (!customer) {
    ElMessage.error('العميل غير موجود');
    router.push({ name: 'admin.crm.customers' });
    return;
  }
  form.value = {
    name: customer.name || '',
    email: customer.email || '',
    phone: customer.phone || '',
    company: customer.company || '',
    address: customer.address || '',
    status: customer.status || 'active',
    notes: customer.notes || '',
    credit_limit: parseFloat(customer.credit_limit) || 0,
    tax_number: customer.tax_number || '',
    city: customer.city || '',
    state: customer.state || '',
    country: customer.country || 'المملكة العربية السعودية',
    postal_code: customer.postal_code || '',
    currency: customer.currency || 'SAR',
    source: customer.source || ''
  };
};

const submitForm = () => {
  customerForm.value.validate(async (valid) => {
    if (!valid) {
      ElMessage.warning('يرجى التحقق من الحقول المطلوبة');
      return;
    }

    try {
      if (isEdit.value) {
        await customersStore.updateCustomer(route.params.id, form.value);
        ElMessage.success('تم تحديث بيانات العميل بنجاح');
      } else {
        await customersStore.createCustomer(form.value);
        ElMessage.success('تم تسجيل العميل الجديد بنجاح');
      }
      router.push({ name: 'admin.crm.customers' });
    } catch (err) {
      ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء حفظ البيانات');
    }
  });
};

const cancel = () => {
  router.push({ name: 'admin.crm.customers' });
};

onMounted(async () => {
  await loadCustomer();
});
</script>

<style scoped>
.crm-customer-form {
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

/* Form Card */
.form-card-premium {
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 4px 20px rgba(148, 163, 184, 0.04);
  background: #ffffff;
  padding: 24px;
}

.tab-content-wrapper {
  padding: 20px 0;
}

.premium-input {
  width: 100%;
}

.premium-select {
  width: 100%;
}

.premium-number-input {
  width: 100%;
}

/* Actions */
.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 25px;
  justify-content: flex-end;
}

.btn-save-premium {
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 600;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border: none;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
}

.btn-cancel-premium {
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 600;
  border-color: #cbd5e1;
  color: #475569;
}
</style>
