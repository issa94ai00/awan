<template>
  <div class="rma-form-container">
    <!-- Header Section -->
    <div class="page-header-premium">
      <div class="header-info">
        <div class="header-icon-box">
          <el-icon><RefreshLeft /></el-icon>
        </div>
        <div>
          <h1 class="header-title">{{ isEdit ? 'تعديل طلب المرتجع' : 'إنشاء طلب ترخيص مرتجع جديد' }}</h1>
          <p class="header-subtitle">قم بتعبئة بيانات العميل، الفاتورة الأصلية، والمنتجات المراد إرجاعها أو استبدالها</p>
        </div>
      </div>
      <el-button @click="goBack" class="btn-back-premium">
        <el-icon><Back /></el-icon> رجوع للرئيسية
      </el-button>
    </div>

    <el-form :model="form" :rules="rules" ref="formRef" label-position="top" class="premium-form-layout" v-loading="loading">
      <el-row :gutter="25">
        <!-- Left Column: RMA Request Details -->
        <el-col :xs="24" :lg="8">
          <el-card class="form-section-card" shadow="never">
            <template #header>
              <div class="section-card-header">
                <span class="dot"></span>
                <h3>معلومات الطلب الأساسية</h3>
              </div>
            </template>

            <!-- Select Customer -->
            <el-form-item label="العميل المسترجع" prop="customer_id">
              <el-select
                v-model="form.customer_id"
                placeholder="ابحث عن العميل بالاسم أو الهاتف"
                filterable
                remote
                reserve-keyword
                :remote-method="searchCustomers"
                :loading="customersLoading"
                :remote-show-suffix="true"
                clearable
                @change="onCustomerChange"
                @focus="onCustomerFocus"
                class="premium-input-field"
                :disabled="isEdit"
              >
                <el-option 
                  v-for="customer in customers" 
                  :key="customer.id" 
                  :value="customer.id" 
                  :label="customer.name"
                >
                  <div class="customer-option">
                    <div class="customer-option-header">
                      <span class="customer-option-name">{{ customer.name }}</span>
                      <span class="customer-option-id">#{{ customer.id }}</span>
                    </div>
                    <div class="customer-option-details">
                      <span class="customer-option-phone" v-if="customer.phone">
                        <el-icon><Phone /></el-icon> {{ customer.phone }}
                      </span>
                      <span class="customer-option-email" v-if="customer.email">
                        <el-icon><Message /></el-icon> {{ customer.email }}
                      </span>
                    </div>
                  </div>
                </el-option>
              </el-select>
            </el-form-item>

            <!-- Customer Details Card (shown when customer is selected) -->
            <div class="customer-details-card" v-if="selectedCustomer">
              <div class="customer-details-header">
                <div class="customer-avatar">
                  <el-icon><User /></el-icon>
                </div>
                <div class="customer-info">
                  <h4>{{ selectedCustomer.name }}</h4>
                  <p class="customer-meta">رقم العميل: #{{ selectedCustomer.id }}</p>
                </div>
                <div class="customer-stats">
                  <div class="stat-item">
                    <span class="stat-value">{{ customerStats.totalOrders }}</span>
                    <span class="stat-label">إجمالي الطلبات</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-value">{{ customerStats.totalReturns }}</span>
                    <span class="stat-label">المرتجعات</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-value">{{ customerStats.availableOrders }}</span>
                    <span class="stat-label">طلبات قابلة للإرجاع</span>
                  </div>
                </div>
              </div>
              <div class="customer-contact-info" v-if="selectedCustomer.phone || selectedCustomer.email">
                <span class="contact-item" v-if="selectedCustomer.phone">
                  <el-icon><Phone /></el-icon> {{ selectedCustomer.phone }}
                </span>
                <span class="contact-item" v-if="selectedCustomer.email">
                  <el-icon><Message /></el-icon> {{ selectedCustomer.email }}
                </span>
              </div>
            </div>

            <!-- Select Sales Order -->
            <el-form-item label="الفاتورة الأصلية المرتبطة" prop="order_id">
              <el-select
                v-model="form.order_id"
                placeholder="اختر الفاتورة الأصلية للعميل"
                filterable
                clearable
                @change="loadOrderItems"
                class="premium-input-field"
                :disabled="!form.customer_id || isEdit"
              >
                <el-option v-for="order in filteredOrders" :key="order.id" :value="order.id" :label="order.order_number" />
              </el-select>
              <span class="input-helper-text" v-if="!form.customer_id">يرجى اختيار العميل أولاً لتصفية فواتير المبيعات الخاصة به.</span>
            </el-form-item>

            <!-- Return Type (Resolution) -->
            <el-form-item label="نوع ومعالجة التسوية العامة" prop="return_type">
              <el-select v-model="form.return_type" placeholder="اختر المعالجة الافتراضية" class="premium-input-field">
                <el-option value="refund" label="استرداد مالي (تعويض)" />
                <el-option value="exchange" label="استبدال منتج بآخر" />
                <el-option value="store_credit" label="رصيد متجر (إضافة للمحفظة)" />
              </el-select>
            </el-form-item>

            <!-- Return Cause Reason -->
            <el-form-item label="سبب الإرجاع الرئيسي" prop="reason">
              <el-select v-model="form.reason" placeholder="اختر سبب الإرجاع الرئيسي" class="premium-input-field">
                <el-option value="defective" label="منتج معيب أو به خلل مصنعي" />
                <el-option value="damaged" label="منتج تالف أو مكسور" />
                <el-option value="wrong_item" label="منتج خاطئ أو غير المطلوب" />
                <el-option value="not_as_described" label="منتج يختلف عن الوصف المعروض" />
                <el-option value="changed_mind" label="تغيير رأي العميل" />
                <el-option value="other" label="أسباب أخرى مخصصة" />
              </el-select>
            </el-form-item>

            <!-- Reason Description -->
            <el-form-item label="تفاصيل إضافية عن سبب الإرجاع">
              <el-input v-model="form.reason_description" type="textarea" :rows="3" placeholder="اكتب تفاصيل إضافية لشرح سبب الإرجاع..." />
            </el-form-item>

            <!-- Return Address -->
            <div class="address-section">
              <div class="address-header">
                <el-icon><Location /></el-icon>
                <span>عنوان الاسترجاع (اختياري)</span>
              </div>
              <el-form-item label="العنوان الرئيسي">
                <el-input v-model="form.return_address.address_line1" placeholder="مثال: الشارع، الحي، رقم المبنى" />
              </el-form-item>
              <el-row :gutter="10">
                <el-col :span="12">
                  <el-form-item label="المدينة">
                    <el-input v-model="form.return_address.city" placeholder="المدينة" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="الدولة">
                    <el-input v-model="form.return_address.country" placeholder="الدولة" />
                  </el-form-item>
                </el-col>
              </el-row>
            </div>

            <!-- Notes -->
            <el-form-item label="ملاحظات المعالجة والمتابعة (داخلية)">
              <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="ملاحظات تظهر لمسؤولي النظام فقط..." />
            </el-form-item>
          </el-card>
        </el-col>

        <!-- Right Column: Products Selection & Configuration -->
        <el-col :xs="24" :lg="16">
          <el-card class="form-section-card" shadow="never">
            <template #header>
              <div class="section-card-header items-header">
                <div class="title-with-count">
                  <span class="dot"></span>
                  <h3>المنتجات المرتجعة</h3>
                  <span class="items-count-badge" v-if="orderItems.length">{{ selectedItemsCount }} من {{ orderItems.length }}</span>
                </div>
              </div>
            </template>

            <!-- Empty State -->
            <div v-if="orderItems.length === 0" class="items-empty-state">
              <el-icon><ShoppingCart /></el-icon>
              <p>يرجى اختيار العميل وفاتورة مبيعات صالحة لعرض بنود المنتجات وتحديد المرتجع منها.</p>
            </div>

            <!-- Table of Items -->
            <div v-else class="items-table-wrapper">
              <el-table :data="orderItems" stripe class="items-selection-table">
                <!-- Checkbox Column -->
                <el-table-column width="55" align="center">
                  <template #default="{ row }">
                    <el-checkbox v-model="row.selected" @change="calculateRefundSummary" />
                  </template>
                </el-table-column>

                <!-- Product Details -->
                <el-table-column label="المنتج الأصلي">
                  <template #default="{ row }">
                    <div class="item-product-cell">
                      <span class="item-product-name">{{ row.product_name }}</span>
                      <div class="item-product-meta">
                        <span class="price-badge">{{ formatCurrency(row.unit_price) }}</span>
                        <span class="qty-badge">الكمية بالفاتورة: {{ row.original_quantity }}</span>
                      </div>
                    </div>
                  </template>
                </el-table-column>

                <!-- Return Qty -->
                <el-table-column label="كمية الإرجاع" width="130">
                  <template #default="{ row }">
                    <el-input-number
                      v-model="row.quantity"
                      :min="1"
                      :max="row.original_quantity"
                      size="small"
                      :disabled="!row.selected"
                      @change="calculateRefundSummary"
                      class="qty-input-premium"
                    />
                  </template>
                </el-table-column>

                <!-- Condition -->
                <el-table-column label="حالة المستلم" width="130">
                  <template #default="{ row }">
                    <el-select v-model="row.condition" size="small" :disabled="!row.selected" @change="calculateRefundSummary" class="condition-select">
                      <el-option value="new" label="جديد (سليم)" />
                      <el-option value="used" label="مستعمل" />
                      <el-option value="damaged" label="تالف" />
                      <el-option value="missing" label="مفقود" />
                    </el-select>
                  </template>
                </el-table-column>

                <!-- Resolution -->
                <el-table-column label="طريقة التسوية" width="130">
                  <template #default="{ row }">
                    <el-select v-model="row.resolution" size="small" :disabled="!row.selected" class="resolution-select">
                      <el-option value="refund" label="استرداد مالي" />
                      <el-option value="exchange" label="استبدال" />
                      <el-option value="repair" label="إصلاح" />
                      <el-option value="discard" label="إتلاف" />
                    </el-select>
                  </template>
                </el-table-column>

                <!-- Exchange Product Selector (Shown only when resolution is exchange) -->
                <el-table-column label="المنتج البديل" min-width="180">
                  <template #default="{ row }">
                    <div v-if="row.resolution === 'exchange'" class="exchange-selector-cell">
                      <el-select
                        v-model="row.exchange_product_id"
                        placeholder="ابحث عن منتج بديل"
                        filterable
                        remote
                        reserve-keyword
                        :remote-method="queryCatalogProducts"
                        :loading="catalogLoading"
                        size="small"
                        :disabled="!row.selected"
                        class="exchange-product-select"
                      >
                        <el-option
                          v-for="prod in catalogProducts"
                          :key="prod.id"
                          :value="prod.id"
                          :label="prod.name_ar || prod.name_en"
                        />
                      </el-select>
                    </div>
                    <span v-else class="text-placeholder">-</span>
                  </template>
                </el-table-column>
              </el-table>
            </div>

            <!-- Refund Summary Section -->
            <div class="refund-summary-panel" v-if="orderItems.some(i => i.selected)">
              <div class="summary-row title">
                <span>ملخص التعويض المالي المقدر</span>
                <span class="value">{{ formatCurrency(estimatedRefundTotal) }}</span>
              </div>
              <div class="summary-row desc">
                <p>ملاحظة: تحتسب القيمة بناءً على حالة المنتج المستلم (جديد: 100٪، مستعمل: 70٪، تالف: 50٪، مفقود: 0٪) من سعر الشراء الأصلي.</p>
              </div>
            </div>

            <!-- Actions buttons -->
            <div class="form-actions-panel">
              <el-button type="primary" @click="submitForm" :loading="saving" class="btn-save-premium">
                {{ isEdit ? 'تحديث وحفظ التغييرات' : 'إنشاء طلب الإرجاع' }}
              </el-button>
              <el-button @click="router.back()" class="btn-cancel-premium">
                إلغاء
              </el-button>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { RefreshLeft, Back, Location, ShoppingCart, User, Phone, Message } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import rmaService from '@/services/rma'
import api from '@/api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const rmaId = computed(() => route.params.id ? parseInt(route.params.id) : null)
const goBack = () => {
  router.push('/admin/rma')
}

const loading = ref(false)
const saving = ref(false)
const catalogLoading = ref(false)
const customersLoading = ref(false)

const customers = ref([])
const orders = ref([])
const filteredOrders = ref([])
const orderItems = ref([])
const catalogProducts = ref([])
const selectedCustomer = ref(null)
const customerStats = ref({
  totalOrders: 0,
  totalReturns: 0,
  availableOrders: 0
})

const form = ref({
  customer_id: null,
  order_id: null,
  reason: 'defective',
  return_type: 'refund',
  reason_description: '',
  notes: '',
  return_address: {
    address_line1: '',
    city: '',
    country: '',
    postal_code: ''
  }
})

const rules = {
  customer_id: [{ required: true, message: 'يرجى اختيار العميل المسترجع', trigger: 'change' }],
  order_id: [{ required: true, message: 'يرجى اختيار الفاتورة الأصلية', trigger: 'change' }],
  reason: [{ required: true, message: 'سبب الإرجاع مطلوب', trigger: 'change' }],
  return_type: [{ required: true, message: 'نوع التسوية مطلوب', trigger: 'change' }],
  reason_description: [
    { max: 1000, message: 'الوصف لا يمكن أن يتجاوز 1000 حرف', trigger: 'blur' }
  ],
  'return_address.address_line1': [
    { required: true, message: 'العنوان مطلوب عند تحديد عنوان الاسترجاع', trigger: 'blur' }
  ],
  'return_address.city': [
    { required: true, message: 'المدينة مطلوبة عند تحديد عنوان الاسترجاع', trigger: 'blur' }
  ],
  'return_address.country': [
    { required: true, message: 'الدولة مطلوبة عند تحديد عنوان الاسترجاع', trigger: 'blur' }
  ]
}

const isEdit = computed(() => !!rmaId.value)

const selectedItemsCount = computed(() => {
  return orderItems.value.filter(i => i.selected).length
})

const estimatedRefundTotal = computed(() => {
  return orderItems.value.reduce((sum, item) => {
    if (!item.selected) return sum
    const multiplier = {
      new: 1.0,
      used: 0.7,
      damaged: 0.5,
      missing: 0.0
    }[item.condition] || 0.5
    return sum + (item.unit_price * multiplier * item.quantity)
  }, 0)
})

const loadCustomers = async () => {
  // Don't load all customers initially - use remote search instead
  customers.value = []
}

const searchCustomers = async (query = '') => {
  customersLoading.value = true
  try {
    const params = { per_page: 50 }
    if (query) {
      params.search = query
    }
    const response = await api.get('/admin/rma/customers-with-orders', { params })
    const customersData = response.data.data?.data || response.data.data || response.data || []
    
    // Ensure selectedCustomer is kept in the array list so el-select doesn't hide it
    let list = Array.isArray(customersData) ? customersData : []
    if (selectedCustomer.value && !list.some(c => c.id === selectedCustomer.value.id)) {
      list.push(selectedCustomer.value)
    }
    customers.value = list
  } catch (error) {
    console.error('Failed to search customers:', error)
    customers.value = selectedCustomer.value ? [selectedCustomer.value] : []
  } finally {
    customersLoading.value = false
  }
}

const onCustomerFocus = () => {
  // Load initial customers when select is focused
  if (customers.value.length === 0) {
    searchCustomers('')
  }
}

const loadOrders = async () => {
  try {
    const response = await api.get('/sales-orders', { params: { per_page: 500 } })
    const ordersData = response.data.data?.sales_orders || response.data.data || response.data || []
    orders.value = Array.isArray(ordersData) ? ordersData : []
    console.log(`Loaded ${orders.value.length} sales orders`)
  } catch (error) {
    console.error('Failed to load sales orders:', error)
    ElMessage.error('خطأ في تحميل فواتير المبيعات')
  }
}

const onCustomerChange = async () => {
  form.value.order_id = null
  orderItems.value = []
  
  if (form.value.customer_id) {
    const customerId = form.value.customer_id
    selectedCustomer.value = customers.value.find(c => c.id === customerId)
    
    // Filter orders by customer_id, handling both direct ID and nested customer object
    filteredOrders.value = orders.value.filter(order => {
      const orderCustomerId = order.customer_id || order.customer?.id
      return orderCustomerId === customerId
    })
    
    // Load customer statistics
    await loadCustomerStats(customerId)
    
    console.log(`Filtered ${filteredOrders.value.length} orders for customer ${customerId}`)
    
    // Show warning if no orders found
    if (filteredOrders.value.length === 0) {
      ElMessage.warning('لا توجد فواتير مبيعات لهذا العميل')
    }
  } else {
    selectedCustomer.value = null
    customerStats.value = {
      totalOrders: 0,
      totalReturns: 0,
      availableOrders: 0
    }
    filteredOrders.value = []
  }
}

const loadCustomerStats = async (customerId) => {
  try {
    // Get total orders for this customer
    const totalOrders = orders.value.filter(order => {
      const orderCustomerId = order.customer_id || order.customer?.id
      return orderCustomerId === customerId
    }).length
    
    // Get delivered orders (available for return)
    const availableOrders = orders.value.filter(order => {
      const orderCustomerId = order.customer_id || order.customer?.id
      return orderCustomerId === customerId && order.status === 'delivered'
    }).length
    
    // Get total returns for this customer (from RMA requests)
    try {
      const response = await api.get('/admin/rma', { 
        params: { 
          customer_id: customerId,
          per_page: 1
        } 
      })
      const totalReturns = response.data.data?.total || 0
      
      customerStats.value = {
        totalOrders,
        totalReturns,
        availableOrders
      }
    } catch (error) {
      // If RMA stats fail, just use order stats
      customerStats.value = {
        totalOrders,
        totalReturns: 0,
        availableOrders
      }
    }
  } catch (error) {
    console.error('Failed to load customer stats:', error)
  }
}

const validateOrderItems = () => {
  if (orderItems.value.length === 0) {
    ElMessage.warning('لا توجد منتجات في الفاتورة المحددة')
    return false
  }
  
  const availableItems = orderItems.value.filter(item => {
    const previouslyReturned = item.quantity_requested || 0
    const availableToReturn = item.original_quantity - previouslyReturned
    return availableToReturn > 0
  })
  
  if (availableItems.length === 0) {
    ElMessage.warning('جميع منتجات هذه الفاتورة تم إرجاعها بالفعل')
    return false
  }
  
  return true
}

const loadOrderItems = async () => {
  if (!form.value.order_id) {
    orderItems.value = []
    return
  }
  loading.value = true
  try {
    const response = await api.get(`/sales-orders/${form.value.order_id}`)
    const order = response.data.data || response.data
    if (order && order.items) {
      // Check if order is delivered
      if (order.status !== 'delivered') {
        ElMessage.warning('يجب أن تكون الفاتورة في حالة "تم التسليم" لإنشاء طلب إرجاع')
        orderItems.value = []
        loading.value = false
        return
      }
      
      orderItems.value = order.items.map(item => ({
        sales_order_item_id: item.id,
        product_id: item.product_id,
        product_name: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
        original_quantity: item.quantity,
        quantity: 1,
        unit_price: parseFloat(item.unit_price) || 0,
        condition: 'new',
        resolution: form.value.return_type,
        selected: false,
        exchange_product_id: null
      }))
      
      // Validate items availability
      validateOrderItems()
    } else {
      orderItems.value = []
      ElMessage.warning('لا توجد منتجات في هذه الفاتورة')
    }
  } catch (error) {
    console.error('Failed to load order items:', error)
    ElMessage.error('خطأ في تحميل منتجات الفاتورة')
    orderItems.value = []
  } finally {
    loading.value = false
  }
}

const queryCatalogProducts = async (query = '') => {
  if (!query) return
  catalogLoading.value = true
  try {
    const response = await api.get('/admin/products', { params: { search: query, per_page: 50 } })
    catalogProducts.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Failed to query catalog products:', error)
  } finally {
    catalogLoading.value = false
  }
}

const calculateRefundSummary = () => {
  // Computed property updates automatically
}

const loadRma = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequest(rmaId.value)
    const rma = response.data.data || response.data
    
    // Set form basics
    form.value = {
      customer_id: rma.customer_id,
      order_id: rma.sales_order_id,
      reason: rma.reason || 'defective',
      return_type: rma.type || 'refund',
      reason_description: rma.reason_description || '',
      notes: rma.admin_notes || '',
      return_address: rma.return_address || { address_line1: '', city: '', country: '', postal_code: '' }
    }

    if (rma.customer) {
      selectedCustomer.value = rma.customer
      if (!customers.value.some(c => c.id === rma.customer.id)) {
        customers.value.push(rma.customer)
      }
    }
    
    // Filter orders by customer_id
    filteredOrders.value = orders.value.filter(order => {
      const orderCustomerId = order.customer_id || order.customer?.id
      return orderCustomerId === rma.customer_id
    })
    
    // Load customer statistics
    await loadCustomerStats(rma.customer_id)

    // Load original sales order items first
    const soResponse = await api.get(`/sales-orders/${rma.sales_order_id}`)
    const order = soResponse.data.data || soResponse.data
    const orderItemsMap = {}
    if (order && order.items) {
      order.items.forEach(item => {
        orderItemsMap[item.id] = item
      })
    }

    // Map existing returned items and check them in the list
    if (rma.items) {
      orderItems.value = rma.items.map(item => {
        const originalItem = orderItemsMap[item.sales_order_item_id]
        return {
          sales_order_item_id: item.sales_order_item_id,
          product_id: item.product_id,
          product_name: item.product?.name_ar || item.product?.name || item.product_name || 'N/A',
          original_quantity: originalItem ? originalItem.quantity : item.quantity_requested,
          quantity: item.quantity_requested,
          unit_price: parseFloat(originalItem?.unit_price || item.refund_amount / item.quantity_requested) || 0,
          condition: item.condition || 'new',
          resolution: item.resolution || 'refund',
          selected: true,
          exchange_product_id: item.exchange_product_id
        }
      })

      // Query products for exchange if any exists
      const exchangeItem = rma.items.find(i => i.exchange_product_id)
      if (exchangeItem && exchangeItem.exchange_product) {
        catalogProducts.value = [exchangeItem.exchange_product]
      }
    }
  } catch (error) {
    console.error('Failed to load RMA data:', error)
    ElMessage.error('خطأ في تحميل بيانات طلب الإرجاع')
    router.back()
  } finally {
    loading.value = false
  }
}

const submitForm = async () => {
  const selectedItems = orderItems.value.filter(i => i.selected)
  if (selectedItems.length === 0) {
    ElMessage.warning('يرجى تحديد منتج واحد على الأقل لإرجاعه')
    return
  }

  // Validate exchange items have exchange_product_id
  for (const item of selectedItems) {
    if (item.resolution === 'exchange' && !item.exchange_product_id) {
      ElMessage.error(`يرجى تحديد المنتج البديل لمنتج: ${item.product_name}`)
      return
    }
    
    // Validate quantity doesn't exceed original
    if (item.quantity > item.original_quantity) {
      ElMessage.error(`الكمية المطلوبة (${item.quantity}) تتجاوز الكمية الأصلية (${item.original_quantity}) لمنتج: ${item.product_name}`)
      return
    }
  }

  // Validate return address if provided
  if (form.value.return_address && (form.value.return_address.address_line1 || form.value.return_address.city || form.value.return_address.country)) {
    if (!form.value.return_address.address_line1) {
      ElMessage.error('يرجى إدخال العنوان الرئيسي')
      return
    }
    if (!form.value.return_address.city) {
      ElMessage.error('يرجى إدخال المدينة')
      return
    }
    if (!form.value.return_address.country) {
      ElMessage.error('يرجى إدخال الدولة')
      return
    }
  }

  saving.value = true
  try {
    // Extract customer_id from object if needed
    const customerId = typeof form.value.customer_id === 'object' ? form.value.customer_id.id : form.value.customer_id
    
    const data = {
      customer_id: customerId,
      sales_order_id: form.value.order_id,
      reason: form.value.reason,
      type: form.value.return_type,
      reason_description: form.value.reason_description,
      admin_notes: form.value.notes,
      return_address: form.value.return_address,
      items: selectedItems.map(item => ({
        sales_order_item_id: item.sales_order_item_id,
        quantity_requested: item.quantity,
        condition: item.condition,
        resolution: item.resolution,
        exchange_product_id: item.exchange_product_id,
        notes: item.notes || ''
      }))
    }

    if (isEdit.value) {
      await rmaService.updateRmaRequest(rmaId.value, data)
      ElMessage.success('تم تحديث طلب الإرجاع بنجاح')
    } else {
      await rmaService.createRmaRequest(data)
      ElMessage.success('تم إنشاء طلب الإرجاع بنجاح')
    }
    router.push('/admin/rma')
  } catch (error) {
    console.error('Failed to save RMA:', error)
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors
      const errorMessages = Object.values(errors).flat()
      ElMessage.error(errorMessages[0] || 'خطأ أثناء حفظ طلب الإرجاع')
    } else {
      ElMessage.error(error.response?.data?.message || 'خطأ أثناء حفظ طلب الإرجاع')
    }
  } finally {
    saving.value = false
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(val)
}

onMounted(async () => {
  await loadCustomers()
  await loadOrders()
  
  if (isEdit.value) {
    await loadRma()
  } else {
    form.value = {
      customer_id: null,
      order_id: null,
      reason: 'defective',
      return_type: 'refund',
      reason_description: '',
      notes: '',
      return_address: { address_line1: '', city: '', country: '', postal_code: '' }
    }
  }
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap');

.rma-form-container {
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
  font-size: 22px;
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

.btn-back-premium {
  border: 1px solid #cbd5e1;
  color: #475569;
  font-weight: 700;
  border-radius: 12px;
  padding: 12px 24px;
  font-family: 'Cairo', sans-serif;
  transition: all 0.2s;
  height: 44px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-back-premium:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

/* Form Layout */
.premium-form-layout {
  margin-bottom: 40px;
}

.form-section-card {
  border-radius: 20px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #ffffff;
  box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.08);
  margin-bottom: 25px;
  padding: 8px;
}

.form-section-card :deep(.el-card__header) {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.section-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-card-header .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #2563eb;
  box-shadow: 0 0 8px rgba(37, 99, 235, 0.6);
}

.section-card-header h3 {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.premium-input-field {
  width: 100%;
}

.premium-input-field :deep(.el-input__wrapper),
.premium-input-field :deep(.el-select__wrapper) {
  border-radius: 10px;
  padding: 6px 12px;
}

.premium-form-layout :deep(.el-form-item__label) {
  font-weight: 700;
  color: #475569;
  font-size: 13px;
  margin-bottom: 6px;
}

.input-helper-text {
  font-size: 12px;
  color: #94a3b8;
  margin-top: 6px;
  display: block;
  line-height: 1.4;
}

/* Address Fields Section */
.address-section {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 20px;
  border-radius: 14px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  margin: 20px 0;
}

.address-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 800;
  color: #1e293b;
  font-size: 13px;
  margin-bottom: 16px;
}

.address-header .el-icon {
  color: #2563eb;
}

/* Items Table & Lists */
.items-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.title-with-count {
  display: flex;
  align-items: center;
  gap: 12px;
}

.items-count-badge {
  background: #eff6ff;
  color: #2563eb;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
}

.items-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
  color: #94a3b8;
  gap: 16px;
  background: #fafbfc;
  border-radius: 16px;
  border: 2px dashed #e2e8f0;
  margin: 20px 12px;
}

.items-empty-state .el-icon {
  font-size: 54px;
  color: #bfdbfe;
}

.items-empty-state p {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  max-width: 400px;
  line-height: 1.6;
  margin: 0;
}

.items-table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
  margin: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.items-selection-table {
  --el-table-header-bg-color: #f8fafc;
}

.items-selection-table :deep(.el-table__header-wrapper) th {
  font-weight: 800 !important;
  color: #475569 !important;
  font-size: 13px !important;
  background-color: #f8fafc !important;
  padding: 14px 0 !important;
}

.item-product-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 4px 0;
}

.item-product-name {
  font-weight: 700;
  color: #0f172a;
  font-size: 14px;
}

.item-product-meta {
  display: flex;
  gap: 10px;
  align-items: center;
}

.price-badge {
  color: #059669;
  font-weight: 700;
  font-size: 12px;
}

.qty-badge {
  color: #475569;
  font-size: 11px;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 600;
}

.qty-input-premium {
  width: 110px;
}

.qty-input-premium :deep(.el-input-number__increase),
.qty-input-premium :deep(.el-input-number__decrease) {
  border-radius: 6px;
}

.condition-select,
.resolution-select {
  width: 100%;
}

.condition-select :deep(.el-select__wrapper),
.resolution-select :deep(.el-select__wrapper) {
  border-radius: 8px;
}

.text-placeholder {
  color: #cbd5e1;
  font-weight: 500;
}

/* Exchange Selector styling */
.exchange-selector-cell {
  width: 100%;
}

.exchange-product-select {
  width: 100%;
}

.exchange-product-select :deep(.el-select__wrapper) {
  border-radius: 8px;
}

/* Refund summary panel */
.refund-summary-panel {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 1px solid #bbf7d0;
  border-radius: 14px;
  padding: 20px;
  margin: 12px;
  box-shadow: 0 4px 16px rgba(22, 101, 52, 0.05);
}

.summary-row.title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 800;
  color: #166534;
  font-size: 16px;
  margin-bottom: 10px;
}

.summary-row.title .value {
  font-size: 22px;
  font-weight: 900;
  color: #15803d;
  font-family: monospace, 'Cairo';
}

.summary-row.desc {
  font-size: 12px;
  color: #166534;
  opacity: 0.85;
  margin: 0;
  line-height: 1.6;
  font-weight: 500;
}

/* Actions Panel */
.form-actions-panel {
  display: flex;
  gap: 14px;
  justify-content: flex-end;
  margin: 20px 12px 12px 12px;
}

.btn-save-premium {
  padding: 14px 28px;
  border-radius: 12px;
  font-weight: 700;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border: none;
  font-family: 'Cairo', sans-serif;
  color: white;
  box-shadow: 0 8px 20px -6px rgba(37, 99, 235, 0.4);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  height: 46px;
}

.btn-save-premium:hover {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  box-shadow: 0 12px 24px -4px rgba(37, 99, 235, 0.5);
  transform: translateY(-2px);
}

.btn-save-premium:active {
  transform: translateY(0);
}

.btn-cancel-premium {
  padding: 14px 28px;
  border-radius: 12px;
  font-weight: 700;
  border: 1px solid #cbd5e1;
  color: #475569;
  font-family: 'Cairo', sans-serif;
  transition: all 0.2s;
  height: 46px;
}

.btn-cancel-premium:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

.customer-option {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px 0;
}

.customer-option-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.customer-option-name {
  font-weight: 700;
  color: #1e293b;
  font-size: 14px;
}

.customer-option-id {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 600;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}

.customer-option-details {
  display: flex;
  gap: 12px;
  align-items: center;
}

.customer-option-phone,
.customer-option-email {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
}

.customer-option-phone .el-icon,
.customer-option-email .el-icon {
  font-size: 12px;
  color: #94a3b8;
}

/* Customer Details Card */
.customer-details-card {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.customer-details-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.customer-avatar {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2563eb;
  font-size: 24px;
  flex-shrink: 0;
}

.customer-info {
  flex: 1;
}

.customer-info h4 {
  margin: 0 0 4px 0;
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
}

.customer-meta {
  margin: 0;
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.customer-stats {
  display: flex;
  gap: 20px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  background: #ffffff;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  min-width: 80px;
}

.stat-value {
  font-size: 20px;
  font-weight: 800;
  color: #2563eb;
}

.stat-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

.customer-contact-info {
  display: flex;
  gap: 16px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #475569;
  font-weight: 600;
}

.contact-item .el-icon {
  color: #64748b;
  font-size: 14px;
}
</style>
