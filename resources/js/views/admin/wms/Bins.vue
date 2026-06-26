<template>
  <div class="bins-page">
    <div class="page-header">
      <h1><el-icon><Box /></el-icon> {{ $t('wms.bins') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('common.add_new') }}
      </el-button>
    </div>

    <el-card>
      <el-form :inline="true" class="filter-form">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="filters.warehouse_id" :placeholder="$t('wms.select_warehouse')" clearable @change="loadBins">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.zone')">
          <el-input v-model="filters.zone" :placeholder="$t('wms.search_zone')" clearable @change="loadBins" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadBins">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="bins" v-loading="loading" stripe>
        <el-table-column prop="bin_code" :label="$t('wms.bin_code')" />
        <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
        <el-table-column prop="zone" :label="$t('wms.zone')" />
        <el-table-column prop="rack" :label="$t('wms.rack')" />
        <el-table-column prop="shelf" :label="$t('wms.shelf')" />
        <el-table-column prop="max_weight" :label="$t('wms.max_weight')" />
        <el-table-column :label="$t('common.actions')" width="150">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="editBin(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteBin(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @size-change="loadBins"
        @current-change="loadBins"
        style="margin-top: 20px"
      />
    </el-card>

    <!-- Create/Edit Dialog -->
    <el-dialog v-model="showCreateDialog" :title="editingBin ? $t('common.edit') : $t('common.add_new')" width="500px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="$t('wms.warehouse')">
          <el-select v-model="form.warehouse_id">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.bin_code')">
          <el-input v-model="form.bin_code" />
        </el-form-item>
        <el-form-item :label="$t('wms.zone')">
          <el-input v-model="form.zone" />
        </el-form-item>
        <el-form-item :label="$t('wms.rack')">
          <el-input v-model="form.rack" />
        </el-form-item>
        <el-form-item :label="$t('wms.shelf')">
          <el-input v-model="form.shelf" />
        </el-form-item>
        <el-form-item :label="$t('wms.max_weight')">
          <el-input-number v-model="form.max_weight" :min="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="saveBin" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Box, Plus, Edit, Delete, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingBin = ref(null)
const bins = ref([])
const warehouses = ref([])
const filters = ref({
  warehouse_id: null,
  zone: ''
})
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})
const form = ref({
  warehouse_id: null,
  bin_code: '',
  zone: '',
  rack: '',
  shelf: '',
  max_weight: 0
})

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
  loading.value = true
  try {
    // const response = await api.get('/api/v1/wms/bins', { params: { ...filters.value, ...pagination.value } })
    // bins.value = response.data.data
    // pagination.value.total = response.data.total
    bins.value = [
      { id: 1, bin_code: 'A-R1-S1', warehouse: 'Main Warehouse', zone: 'A', rack: 'R1', shelf: 'S1', max_weight: 500 },
      { id: 2, bin_code: 'A-R1-S2', warehouse: 'Main Warehouse', zone: 'A', rack: 'R1', shelf: 'S2', max_weight: 500 }
    ]
    pagination.value.total = 200
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const editBin = (bin) => {
  editingBin.value = bin
  form.value = { ...bin }
  showCreateDialog.value = true
}

const saveBin = async () => {
  saving.value = true
  try {
    if (editingBin.value) {
      // await api.put(`/api/v1/wms/bins/${editingBin.value.id}`, form.value)
      ElMessage.success(t('common.update_success'))
    } else {
      // await api.post('/api/v1/wms/bins', form.value)
      ElMessage.success(t('common.create_success'))
    }
    showCreateDialog.value = false
    editingBin.value = null
    await loadBins()
  } catch (error) {
    ElMessage.error(t('common.save_error'))
  } finally {
    saving.value = false
  }
}

const deleteBin = async (bin) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning'
    })
    // await api.delete(`/api/v1/wms/bins/${bin.id}`)
    ElMessage.success(t('common.delete_success'))
    await loadBins()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.delete_error'))
    }
  }
}

onMounted(() => {
  loadWarehouses()
  loadBins()
})
</script>

<style scoped>
.bins-page {
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

.filter-form {
  margin-bottom: 20px;
}
</style>
