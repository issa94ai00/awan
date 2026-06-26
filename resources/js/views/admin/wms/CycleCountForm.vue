<template>
  <div class="cycle-count-form-page">
    <div class="page-header">
      <h1><el-icon><Document /></el-icon> {{ isEdit ? $t('wms.edit_cycle_count') : $t('wms.create_cycle_count') }}</h1>
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
        <el-form-item :label="$t('wms.zone')" prop="zone">
          <el-input v-model="form.zone" :placeholder="$t('wms.zone_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.count_date')" prop="count_date">
          <el-date-picker v-model="form.count_date" type="date" />
        </el-form-item>
        <el-form-item :label="$t('wms.notes')">
          <el-input v-model="form.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card style="margin-top: 20px" v-if="bins.length > 0">
      <template #header>
        <div class="card-header">
          <span>{{ $t('wms.bins_to_count') }}</span>
          <el-button size="small" @click="selectAllBins">{{ $t('common.select_all') }}</el-button>
        </div>
      </template>
      <el-table :data="bins" @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="bin_code" :label="$t('wms.bin_code')" />
        <el-table-column prop="zone" :label="$t('wms.zone')" />
        <el-table-column prop="rack" :label="$t('wms.rack')" />
        <el-table-column prop="shelf" :label="$t('wms.shelf')" />
        <el-table-column prop="system_quantity" :label="$t('wms.system_quantity')" />
        <el-table-column prop="counted_quantity" :label="$t('wms.counted_quantity')">
          <template #default="{ row }">
            <el-input-number v-model="row.counted_quantity" :min="0" size="small" />
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
import { Document, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const warehouses = ref([])
const bins = ref([])
const selectedBins = ref([])
const form = ref({
  warehouse_id: null,
  zone: '',
  count_date: new Date(),
  notes: ''
})

const rules = {
  warehouse_id: [{ required: true, message: t('wms.warehouse_required'), trigger: 'change' }],
  zone: [{ required: true, message: t('wms.zone_required'), trigger: 'blur' }],
  count_date: [{ required: true, message: t('wms.count_date_required'), trigger: 'change' }]
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

const loadBins = async () => {
  if (!form.value.warehouse_id) return
  try {
    // await api.get(`/api/v1/wms/bins?warehouse_id=${form.value.warehouse_id}&zone=${form.value.zone}`)
    bins.value = [
      { id: 1, bin_code: 'A-R1-S1', zone: 'A', rack: 'R1', shelf: 'S1', system_quantity: 100, counted_quantity: 0 },
      { id: 2, bin_code: 'A-R1-S2', zone: 'A', rack: 'R1', shelf: 'S2', system_quantity: 50, counted_quantity: 0 }
    ]
  } catch (error) {
    console.error('Failed to load bins:', error)
  }
}

const selectAllBins = () => {
  bins.value.forEach(bin => {
    bin.counted_quantity = bin.system_quantity
  })
}

const handleSelectionChange = (selection) => {
  selectedBins.value = selection
}

const loadCycleCount = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/wms/cycle-counts/${route.params.id}`)
    form.value = {
      warehouse_id: 1,
      zone: 'A',
      count_date: new Date(),
      notes: 'Test notes'
    }
    await loadBins()
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
          bins: bins.value.filter(bin => bin.counted_quantity > 0).map(bin => ({
            bin_id: bin.id,
            system_quantity: bin.system_quantity,
            counted_quantity: bin.counted_quantity
          }))
        }
        if (isEdit.value) {
          // await api.put(`/api/v1/wms/cycle-counts/${route.params.id}`, data)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/wms/cycle-counts', data)
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
  if (isEdit.value) {
    loadCycleCount()
  }
})
</script>

<style scoped>
.cycle-count-form-page {
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
