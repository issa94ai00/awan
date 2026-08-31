<template>
    <div class="bins-page">
        <AdminPageHeader icon="fas fa-cube" :title="$t('wms.bins')">
            <template #actions>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('wms.select_warehouse')" clearable style="width: 180px" @change="loadBins">
                    <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
                </el-select>
                <el-input v-model="filters.zone" :placeholder="$t('wms.search_zone')" clearable style="width: 160px" @change="loadBins" />
                <el-button type="primary" @click="openCreateDialog">
                    <i class="fas fa-plus"></i> {{ $t('common.add_new') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="never">
            <el-table :data="bins" v-loading="loading" stripe>
                <el-table-column :label="$t('wms.bin_code')" width="130">
                    <template #default="{ row }">{{ row.code || row.bin_code || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('wms.warehouse')" min-width="140">
                    <template #default="{ row }">{{ row.warehouse_name || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('wms.bin_type')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="binTypeTagType(row.type)">{{ row.type_text }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('wms.zone')" width="110">
                    <template #default="{ row }">{{ row.zone || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('wms.aisle')" width="90">
                    <template #default="{ row }">{{ row.aisle || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('wms.shelf')" width="90">
                    <template #default="{ row }">{{ row.shelf || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('wms.utilization')" width="140">
                    <template #default="{ row }">
                        <el-progress
                            v-if="row.capacity_value"
                            :percentage="row.utilization_percentage"
                            :stroke-width="8"
                            :status="row.utilization_percentage >= 90 ? 'exception' : undefined"
                        />
                        <span v-else class="muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.status')" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? $t('wms.active') : $t('wms.inactive') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions')" width="130" align="center">
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
        <el-dialog v-model="showCreateDialog" :title="editingBin ? $t('wms.edit_bin') : $t('wms.create_bin')" width="560px">
            <el-form :model="form" label-position="top">
                <div class="form-grid-2">
                    <el-form-item :label="$t('wms.warehouse')" required>
                        <el-select v-model="form.warehouse_id" style="width: 100%">
                            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.bin_code')" required>
                        <el-input v-model="form.code" :placeholder="$t('wms.bin_code_placeholder')" />
                    </el-form-item>
                </div>
                <el-form-item :label="$t('wms.bin_name')" required>
                    <el-input v-model="form.name" />
                </el-form-item>
                <div class="form-grid-3">
                    <el-form-item :label="$t('wms.zone')">
                        <el-input v-model="form.zone" :placeholder="$t('wms.zone_placeholder')" />
                    </el-form-item>
                    <el-form-item :label="$t('wms.aisle')">
                        <el-input v-model="form.aisle" />
                    </el-form-item>
                    <el-form-item :label="$t('wms.shelf')">
                        <el-input v-model="form.shelf" :placeholder="$t('wms.shelf_placeholder')" />
                    </el-form-item>
                </div>
                <div class="form-grid-2">
                    <el-form-item :label="$t('wms.bin_type')" required>
                        <el-select v-model="form.type" style="width: 100%">
                            <el-option value="storage" :label="$t('wms.type_storage')" />
                            <el-option value="picking" :label="$t('wms.type_picking')" />
                            <el-option value="receiving" :label="$t('wms.type_receiving')" />
                            <el-option value="shipping" :label="$t('wms.type_shipping')" />
                            <el-option value="quarantine" :label="$t('wms.type_quarantine')" />
                            <el-option value="returns" :label="$t('wms.type_returns')" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.level')">
                        <el-input v-model="form.level" />
                    </el-form-item>
                </div>
                <div class="form-grid-2">
                    <el-form-item :label="$t('wms.capacity_type')" required>
                        <el-select v-model="form.capacity_type" style="width: 100%">
                            <el-option value="count" :label="$t('wms.capacity_count')" />
                            <el-option value="weight" :label="$t('wms.capacity_weight')" />
                            <el-option value="volume" :label="$t('wms.capacity_volume')" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.capacity_value')">
                        <el-input-number v-model="form.capacity_value" :min="0" style="width: 100%" />
                    </el-form-item>
                </div>
                <el-form-item :label="$t('wms.notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="form.is_active">{{ $t('wms.active') }}</el-checkbox>
                    <el-checkbox v-model="form.requires_equipment" class="ms-3">{{ $t('wms.requires_equipment') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="saveBin" :loading="saving">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { wmsService } from '@/services/wms'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'

const { t } = useI18n()
const route = useRoute()
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

const emptyForm = () => ({
  warehouse_id: null,
  code: '',
  name: '',
  zone: '',
  aisle: '',
  shelf: '',
  level: '',
  type: 'storage',
  capacity_type: 'count',
  capacity_value: 0,
  is_active: true,
  requires_equipment: false,
  notes: '',
})
const form = ref(emptyForm())

const binTypeTagType = (type) => ({
  storage: 'info', picking: 'primary', receiving: 'success', shipping: 'warning', quarantine: 'danger', returns: 'danger',
}[type] || 'info')

const loadWarehouses = async () => {
  try {
    const response = await wmsService.getWarehouses()
    const data = response.data
    warehouses.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load warehouses:', error)
  }
}

const loadBins = async () => {
  loading.value = true
  try {
    const response = await wmsService.getBins({
      warehouse_id: filters.value.warehouse_id,
      zone: filters.value.zone,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })
    const data = response.data
    bins.value = data.data || data || []
    pagination.value.total = data.total || bins.value.length
  } catch (error) {
    ElMessage.error(t('failed_to_load_bins'))
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingBin.value = null
  form.value = emptyForm()
  if (filters.value.warehouse_id) form.value.warehouse_id = filters.value.warehouse_id
  showCreateDialog.value = true
}

const editBin = (bin) => {
  editingBin.value = bin
  form.value = {
    warehouse_id: bin.warehouse_id,
    code: bin.code || bin.bin_code || '',
    name: bin.name || '',
    zone: bin.zone || '',
    aisle: bin.aisle || '',
    shelf: bin.shelf || '',
    level: bin.level || '',
    type: bin.type || 'storage',
    capacity_type: bin.capacity_type || 'count',
    capacity_value: bin.capacity_value || 0,
    is_active: bin.is_active ?? true,
    requires_equipment: bin.requires_equipment ?? false,
    notes: bin.notes || '',
  }
  showCreateDialog.value = true
}

const saveBin = async () => {
  if (!form.value.warehouse_id || !form.value.code || !form.value.name) {
    ElMessage.warning(t('wms.bin_required_fields'))
    return
  }

  saving.value = true
  try {
    const payload = { ...form.value }

    if (editingBin.value) {
      await wmsService.updateBin(editingBin.value.id, payload)
      ElMessage.success(t('bin_updated_successfully'))
    } else {
      await wmsService.createBin(payload)
      ElMessage.success(t('bin_created_successfully'))
    }
    showCreateDialog.value = false
    editingBin.value = null
    await loadBins()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || t('failed_to_save_bin'))
  } finally {
    saving.value = false
  }
}

const deleteBin = async (bin) => {
  try {
    await ElMessageBox.confirm(t('confirm_delete_bin'), t('warning_title'), {
      type: 'warning'
    })
    await wmsService.deleteBin(bin.id)
    ElMessage.success(t('delete_success'))
    await loadBins()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || t('failed_to_delete_bin'))
    }
  }
}

onMounted(() => {
  if (route.query.warehouse_id) {
    filters.value.warehouse_id = Number(route.query.warehouse_id)
  }
  loadWarehouses()
  loadBins()
})
</script>

<style scoped>
.bins-page {
  font-family: 'Cairo', sans-serif;
}

.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
.form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 1rem; }
.ms-3 { margin-inline-start: 1rem; }
.muted { color: var(--text-muted); font-size: 0.85rem; }
</style>
