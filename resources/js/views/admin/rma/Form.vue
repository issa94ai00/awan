<template>
  <div class="rma-form-page">
    <div class="page-header">
      <h1><el-icon><RefreshLeft /></el-icon> {{ isEdit ? $t('rma.edit_return') : $t('rma.create_return') }}</h1>
      <el-button @click="router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('rma.customer')" prop="customer_id">
          <el-select v-model="form.customer_id" :placeholder="$t('rma.select_customer')" filterable>
            <el-option v-for="customer in customers" :key="customer.id" :value="customer.id" :label="customer.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.order')" prop="order_id">
          <el-select v-model="form.order_id" :placeholder="$t('rma.select_order')" filterable @change="loadOrderItems">
            <el-option v-for="order in orders" :key="order.id" :value="order.id" :label="order.order_number" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.reason')" prop="reason">
          <el-input v-model="form.reason" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="$t('rma.return_type')" prop="return_type">
          <el-select v-model="form.return_type">
            <el-option value="refund" :label="$t('rma.refund')" />
            <el-option value="exchange" :label="$t('rma.exchange')" />
            <el-option value="repair" :label="$t('rma.repair')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('rma.notes')">
          <el-input v-model="form.notes" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="$t('rma.items')" prop="items">
          <el-button size="small" @click="addItem">
            <el-icon><Plus /></el-icon> {{ $t('rma.add_item') }}
          </el-button>
        </el-form-item>
        <el-table :data="form.items" max-height="200">
          <el-table-column prop="product" :label="$t('rma.product')" />
          <el-table-column prop="quantity" :label="$t('rma.quantity')" width="100">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="1" size="small" />
            </template>
          </el-table-column>
          <el-table-column prop="reason" :label="$t('rma.item_reason')" width="150">
            <template #default="{ row }">
              <el-input v-model="row.reason" size="small" />
            </template>
          </el-table-column>
          <el-table-column :label="$t('common.actions')" width="80">
            <template #default="{ row, $index }">
              <el-button size="small" type="danger" @click="removeItem($index)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-form-item>
          <el-button type="primary" @click="submitForm" :loading="saving">
            {{ $t('common.save') }}
          </el-button>
          <el-button @click="router.back()">
            {{ $t('common.cancel') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshLeft, Back, Plus, Delete } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import rmaService from '@/services/rma'
import axios from 'axios'

const router = useRouter()
const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const customers = ref([])
const orders = ref([])
const form = ref({})
const rules = {
  customer_id: [{ required: true, message: t('rma.customer_required'), trigger: 'change' }],
  order_id: [{ required: true, message: t('rma.order_required'), trigger: 'change' }],
  reason: [{ required: true, message: t('rma.reason_required'), trigger: 'blur' }],
  return_type: [{ required: true, message: t('rma.return_type_required'), trigger: 'change' }],
  items: [{ required: true, message: t('rma.items_required'), trigger: 'change' }]
}

const isEdit = computed(() => !!route.params.id)

const loadCustomers = async () => {
  try {
    const response = await axios.get('/api/admin/customers')
    customers.value = response.data.data || response.data
  } catch (error) {
    console.error('Failed to load customers:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    customers.value = []
  }
}

const loadOrders = async () => {
  try {
    const response = await axios.get('/api/admin/sales-orders', { params: { status: 'delivered' } })
    orders.value = response.data.data || response.data
  } catch (error) {
    console.error('Failed to load orders:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    orders.value = []
  }
}

const loadOrderItems = async () => {
  if (!form.value.order_id) return
  try {
    const response = await axios.get(`/api/admin/sales-orders/${form.value.order_id}`)
    const order = response.data.data || response.data
    if (order && order.items) {
      form.value.items = order.items.map(item => ({
        sales_order_item_id: item.id,
        product_id: item.product_id,
        product: item.product?.name || item.product_name,
        quantity: 1,
        reason: ''
      }))
    } else {
      form.value.items = []
      ElMessage.warning(t('rma.no_items_in_order'))
    }
  } catch (error) {
    console.error('Failed to load order items:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    form.value.items = []
  }
}

const loadRma = async () => {
  loading.value = true
  try {
    const response = await rmaService.getRmaRequest(route.params.id)
    const rma = response.data.data || response.data
    form.value = {
      customer_id: rma.customer_id,
      order_id: rma.sales_order_id,
      reason: rma.reason_description,
      return_type: rma.type,
      notes: rma.admin_notes,
      items: rma.items ? rma.items.map(item => ({
        sales_order_item_id: item.sales_order_item_id,
        product_id: item.product_id,
        product: item.product?.name,
        quantity: item.quantity_requested,
        reason: item.notes
      })) : []
    }
  } catch (error) {
    console.error('Failed to load RMA:', error)
    ElMessage.error(error.response?.data?.message || t('common.load_error'))
    router.back()
  } finally {
    loading.value = false
  }
}

const addItem = () => {
  form.value.items.push({
    product_id: null,
    product: '',
    quantity: 1,
    reason: ''
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const submitForm = async () => {
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const data = {
          customer_id: form.value.customer_id,
          sales_order_id: form.value.order_id,
          reason: 'defective',
          type: form.value.return_type,
          reason_description: form.value.reason,
          admin_notes: form.value.notes,
          items: form.value.items.map(item => ({
            sales_order_item_id: item.sales_order_item_id,
            quantity_requested: item.quantity,
            condition: 'new',
            resolution: form.value.return_type,
            notes: item.reason
          }))
        }
        if (isEdit.value) {
          await rmaService.updateRmaRequest(route.params.id, data)
          ElMessage.success(t('common.update_success'))
        } else {
          await rmaService.createRmaRequest(data)
          ElMessage.success(t('common.create_success'))
        }
        router.back()
      } catch (error) {
        const errorMessage = error.response?.data?.message || error.response?.data?.errors?.items?.[0] || t('common.save_error')
        ElMessage.error(errorMessage)
      } finally {
        saving.value = false
      }
    }
  })
}

onMounted(() => {
  loadCustomers()
  loadOrders()
  if (isEdit.value) {
    loadRma()
  } else {
    form.value = {
      customer_id: null,
      order_id: null,
      reason: '',
      return_type: 'refund',
      notes: '',
      items: []
    }
  }
})
</script>

<style scoped>
.rma-form-page {
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
</style>
