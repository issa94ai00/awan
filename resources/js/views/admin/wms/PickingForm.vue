<template>
  <div class="picking-form-page">
    <div class="page-header">
      <h1><el-icon><List /></el-icon> {{ isEdit ? $t('wms.edit_picking_list') : $t('wms.create_picking_list') }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('wms.warehouse')" prop="warehouse_id">
          <el-select v-model="form.warehouse_id" :placeholder="$t('wms.select_warehouse')" @change="loadBins">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.order')" prop="order_id">
          <el-select v-model="form.order_id" :placeholder="$t('wms.select_order')" filterable @change="loadOrderItems">
            <el-option v-for="order in orders" :key="order.id" :value="order.id" :label="order.order_number" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.priority')" prop="priority">
          <el-select v-model="form.priority">
            <el-option value="low" :label="$t('wms.low')" />
            <el-option value="normal" :label="$t('wms.normal')" />
            <el-option value="high" :label="$t('wms.high')" />
            <el-option value="urgent" :label="$t('wms.urgent')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.notes')">
          <el-input v-model="form.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 20px" v-if="orderItems.length > 0">
      <template #header>
        <div class="card-header">
          <span>{{ $t('wms.items_to_pick') }}</span>
        </div>
      </template>
      <el-table :data="orderItems">
        <el-table-column prop="product" :label="$t('wms.product')" />
        <el-table-column prop="quantity" :label="$t('wms.quantity')" />
        <el-table-column prop="bin" :label="$t('wms.bin')" />
        <el-table-column prop="zone" :label="$t('wms.zone')" />
        <el-table-column prop="picked_quantity" :label="$t('wms.picked_quantity')">
          <template #default="{ row }">
            <el-input-number v-model="row.picked_quantity" :min="0" :max="row.quantity" size="small" />
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <div class="form-actions">
      <el-button @click="$router.back()">{{ $t('common.cancel') }}</el-button>
      <el-button type="primary" @click="submitForm" :loading="saving">
        {{ $t('common.save') }}
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { List, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const warehouses = ref([])
const orders = ref([])
const bins = ref([])
const orderItems = ref([])
const form = ref({
  warehouse_id: null,
  order_id: null,
  priority: 'normal',
  notes: ''
})

const rules = {
  warehouse_id: [{ required: true, message: t('wms.warehouse_required'), trigger: 'change' }],
  order_id: [{ required: true, message: t('wms.order_required'), trigger: 'change' }],
  priority: [{ required: true, message: t('wms.priority_required'), trigger: 'change' }]
}

const isEdit = computed(() => !!route.params.id)

const loadWarehouses = async () => {
  try {
    // await api.get('/api/v1/wms/warehouses')
    warehouses.value = [
      { id: 1, name: 'Main Warehouse' }
    ]
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadOrders = async () => {
  try {
    // await api.get('/api/v1/sales-orders?status=confirmed')
    orders.value = [
      { id: 1, order_number: 'SO-000001' },
      { id: 2, order_number: 'SO-000002' }
    ]
  } catch (error) {
    console.error('Failed to load orders:', error)
  }
}

const loadBins = async () => {
  if (!form.value.warehouse_id) return
  try {
    // await api.get(`/api/v1/wms/bins?warehouse_id=${form.value.warehouse_id}`)
    bins.value = [
      { id: 1, bin_code: 'A-R1-S1', zone: 'A' }
    ]
  } catch (error) {
    console.error('Failed to load bins:', error)
  }
}

const loadOrderItems = async () => {
  if (!form.value.order_id) return
  try {
    // await api.get(`/api/v1/sales-orders/${form.value.order_id}/items`)
    orderItems.value = [
      { id: 1, product: 'Product 1', quantity: 10, bin: 'A-R1-S1', zone: 'A', picked_quantity: 0 }
    ]
  } catch (error) {
    console.error('Failed to load order items:', error)
  }
}

const loadPickingList = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/wms/picking-lists/${route.params.id}`)
    form.value = {
      warehouse_id: 1,
      order_id: 1,
      priority: 'normal',
      notes: 'Test notes'
    }
    await loadOrderItems()
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const submitForm = async () => {
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const data = {
          ...form.value,
          items: orderItems.value.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            picked_quantity: item.picked_quantity,
            bin_id: item.bin_id
          }))
        }
        if (isEdit.value) {
          // await api.put(`/api/v1/wms/picking-lists/${route.params.id}`, data)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/wms/picking-lists', data)
          ElMessage.success(t('common.create_success'))
        }
        $router.back()
      } catch (error) {
        ElMessage.error(t('common.save_error'))
      } finally {
        saving.value = false
      }
    }
  })
}

onMounted(() => {
  loadWarehouses()
  loadOrders()
  if (isEdit.value) {
    loadPickingList()
  }
})
</script>

<style scoped>
.picking-form-page {
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

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-actions {
  margin-top: 20px;
  display: flex;
  gap: 10px;
}
</style>
