<template>
  <div class="warehouses-page">
    <div class="page-header">
      <h1><el-icon><Management /></el-icon> {{ $t('wms.warehouses') }}</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon> {{ $t('common.add_new') }}
      </el-button>
    </div>

    <el-card>
      <el-table :data="warehouses" v-loading="loading" stripe>
        <el-table-column prop="name" :label="$t('wms.name')" />
        <el-table-column prop="code" :label="$t('wms.code')" />
        <el-table-column prop="location_type" :label="$t('wms.location_type')" />
        <el-table-column prop="city" :label="$t('wms.city')" />
        <el-table-column prop="capacity" :label="$t('wms.capacity')" />
        <el-table-column prop="is_active" :label="$t('common.status')">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'">
              {{ row.is_active ? $t('common.active') : $t('common.inactive') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="200">
          <template #default="{ row }">
            <el-button-group>
              <el-button size="small" @click="editWarehouse(row)">
                <el-icon><Edit /></el-icon>
              </el-button>
              <el-button size="small" type="danger" @click="deleteWarehouse(row)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Create/Edit Dialog -->
    <el-dialog v-model="showCreateDialog" :title="editingWarehouse ? $t('common.edit') : $t('common.add_new')" width="600px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="$t('wms.name')">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="$t('wms.code')">
          <el-input v-model="form.code" />
        </el-form-item>
        <el-form-item :label="$t('wms.address')">
          <el-input v-model="form.address" type="textarea" />
        </el-form-item>
        <el-form-item :label="$t('wms.city')">
          <el-input v-model="form.city" />
        </el-form-item>
        <el-form-item :label="$t('wms.country')">
          <el-input v-model="form.country" />
        </el-form-item>
        <el-form-item :label="$t('wms.location_type')">
          <el-select v-model="form.location_type">
            <el-option value="warehouse" :label="$t('wms.warehouse')" />
            <el-option value="branch" :label="$t('wms.branch')" />
            <el-option value="distribution_center" :label="$t('wms.distribution_center')" />
            <el-option value="3pl" :label="$t('wms.3pl')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.capacity')">
          <el-input-number v-model="form.capacity" :min="0" />
        </el-form-item>
        <el-form-item :label="$t('common.status')">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" @click="saveWarehouse" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Management, Plus, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { wmsService } from '@/services/wms'

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingWarehouse = ref(null)
const warehouses = ref([])
const form = ref({
  name: '',
  code: '',
  address: '',
  city: '',
  country: '',
  location_type: 'warehouse',
  capacity: 0,
  is_active: true
})

const loadWarehouses = async () => {
  loading.value = true
  try {
    const response = await wmsService.getWarehouses()
    const data = response.data
    warehouses.value = data.data || data || []
  } catch (error) {
    ElMessage.error('خطأ في تحميل المستودعات')
  } finally {
    loading.value = false
  }
}

const editWarehouse = (warehouse) => {
  editingWarehouse.value = warehouse
  form.value = { ...warehouse }
  showCreateDialog.value = true
}

const saveWarehouse = async () => {
  saving.value = true
  try {
    if (editingWarehouse.value) {
      await wmsService.updateWarehouse(editingWarehouse.value.id, form.value)
      ElMessage.success('تم تحديث المستودع بنجاح')
    } else {
      await wmsService.createWarehouse(form.value)
      ElMessage.success('تم إنشاء المستودع بنجاح')
    }
    showCreateDialog.value = false
    editingWarehouse.value = null
    resetForm()
    await loadWarehouses()
  } catch (error) {
    ElMessage.error('خطأ أثناء حفظ المستودع')
  } finally {
    saving.value = false
  }
}

const deleteWarehouse = async (warehouse) => {
  try {
    await ElMessageBox.confirm('هل أنت متأكد من حذف هذا المستودع بالكامل؟', 'تنبيه', {
      type: 'warning'
    })
    await wmsService.deleteWarehouse(warehouse.id)
    ElMessage.success('تم حذف المستودع بنجاح')
    await loadWarehouses()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('خطأ في حذف المستودع')
    }
  }
}

const resetForm = () => {
  form.value = {
    name: '',
    code: '',
    address: '',
    city: '',
    country: '',
    location_type: 'warehouse',
    capacity: 0,
    is_active: true
  }
}

onMounted(() => {
  loadWarehouses()
})
</script>

<style scoped>
.warehouses-page {
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
