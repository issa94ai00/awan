<template>
  <div class="packing-form-page">
    <div class="page-header">
      <h1><el-icon><Open /></el-icon> {{ isEdit ? $t('wms.edit_packing_list') : $t('wms.create_packing_list') }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('wms.warehouse')" prop="warehouse_id">
          <el-select v-model="form.warehouse_id" :placeholder="$t('wms.select_warehouse')">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.picking_list')" prop="picking_list_id">
          <el-select v-model="form.picking_list_id" :placeholder="$t('wms.select_picking_list')" filterable @change="loadPickingItems">
            <el-option v-for="pl in pickingLists" :key="pl.id" :value="pl.id" :label="pl.list_number" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.notes')">
          <el-input v-model="form.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 20px" v-if="pickingItems.length > 0">
      <template #header>
        <div class="card-header">
          <span>{{ $t('wms.items_to_pack') }}</span>
        </div>
      </template>
      <el-table :data="pickingItems">
        <el-table-column prop="product" :label="$t('wms.product')" />
        <el-table-column prop="quantity" :label="$t('wms.quantity')" />
        <el-table-column prop="picked_quantity" :label="$t('wms.picked_quantity')" />
        <el-table-column prop="packed_quantity" :label="$t('wms.packed_quantity')">
          <template #default="{ row }">
            <el-input-number v-model="row.packed_quantity" :min="0" :max="row.picked_quantity" size="small" />
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
import { BoxOpened, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const warehouses = ref([])
const pickingLists = ref([])
const pickingItems = ref([])
const form = ref({
  warehouse_id: null,
  picking_list_id: null,
  notes: ''
})

const rules = {
  warehouse_id: [{ required: true, message: t('wms.warehouse_required'), trigger: 'change' }],
  picking_list_id: [{ required: true, message: t('wms.picking_list_required'), trigger: 'change' }]
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

const loadPickingLists = async () => {
  try {
    // await api.get('/api/v1/wms/picking-lists?status=completed')
    pickingLists.value = [
      { id: 1, list_number: 'PL-000001' }
    ]
  } catch (error) {
    console.error('Failed to load picking lists:', error)
  }
}

const loadPickingItems = async () => {
  if (!form.value.picking_list_id) return
  try {
    // await api.get(`/api/v1/wms/picking-lists/${form.value.picking_list_id}/items`)
    pickingItems.value = [
      { id: 1, product: 'Product 1', quantity: 10, picked_quantity: 10, packed_quantity: 0 }
    ]
  } catch (error) {
    console.error('Failed to load picking items:', error)
  }
}

const loadPackingList = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/wms/packing-lists/${route.params.id}`)
    form.value = {
      warehouse_id: 1,
      picking_list_id: 1,
      notes: 'Test notes'
    }
    await loadPickingItems()
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
          items: pickingItems.value.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            packed_quantity: item.packed_quantity
          }))
        }
        if (isEdit.value) {
          // await api.put(`/api/v1/wms/packing-lists/${route.params.id}`, data)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/wms/packing-lists', data)
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
  loadPickingLists()
  if (isEdit.value) {
    loadPackingList()
  }
})
</script>

<style scoped>
.packing-form-page {
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
