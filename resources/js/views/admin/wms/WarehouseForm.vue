<template>
  <div class="warehouse-form-page">
    <div class="page-header">
      <h1><el-icon><Management /></el-icon> {{ isEdit ? $t('wms.edit_warehouse') : $t('wms.create_warehouse') }}</h1>
      <el-button @click="$router.back()">
        <el-icon><Back /></el-icon> {{ $t('common.back') }}
      </el-button>
    </div>

    <el-card>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" v-loading="loading">
        <el-form-item :label="$t('wms.name')" prop="name">
          <el-input v-model="form.name" :placeholder="$t('wms.name_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.code')" prop="code">
          <el-input v-model="form.code" :placeholder="$t('wms.code_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.address')" prop="address">
          <el-input v-model="form.address" type="textarea" :rows="3" :placeholder="$t('wms.address_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.city')" prop="city">
          <el-input v-model="form.city" :placeholder="$t('wms.city_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.country')" prop="country">
          <el-input v-model="form.country" :placeholder="$t('wms.country_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.location_type')" prop="location_type">
          <el-select v-model="form.location_type" :placeholder="$t('wms.select_location_type')">
            <el-option value="warehouse" :label="$t('wms.warehouse')" />
            <el-option value="branch" :label="$t('wms.branch')" />
            <el-option value="distribution_center" :label="$t('wms.distribution_center')" />
            <el-option value="3pl" :label="$t('wms.3pl')" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('wms.capacity')" prop="capacity">
          <el-input-number v-model="form.capacity" :min="0" :max="100000" />
        </el-form-item>
        <el-form-item :label="$t('wms.manager')">
          <el-select v-model="form.manager_id" :placeholder="$t('wms.select_manager')" filterable>
            <el-option v-for="manager in managers" :key="manager.id" :value="manager.id" :label="manager.name" />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('common.status')">
          <el-switch v-model="form.is_active" :active-text="$t('common.active')" :inactive-text="$t('common.inactive')" />
        </el-form-item>
        <el-form-item :label="$t('wms.is_primary')">
          <el-switch v-model="form.is_primary" :active-text="$t('common.yes')" :inactive-text="$t('common.no')" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitForm" :loading="saving">
            {{ $t('common.save') }}
          </el-button>
          <el-button @click="$router.back()">
            {{ $t('common.cancel') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { Office, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const managers = ref([])
const form = ref({
  name: '',
  code: '',
  address: '',
  city: '',
  country: '',
  location_type: 'warehouse',
  capacity: 0,
  manager_id: null,
  is_active: true,
  is_primary: false
})

const rules = {
  name: [{ required: true, message: t('wms.name_required'), trigger: 'blur' }],
  code: [{ required: true, message: t('wms.code_required'), trigger: 'blur' }],
  address: [{ required: true, message: t('wms.address_required'), trigger: 'blur' }],
  city: [{ required: true, message: t('wms.city_required'), trigger: 'blur' }],
  country: [{ required: true, message: t('wms.country_required'), trigger: 'blur' }],
  location_type: [{ required: true, message: t('wms.location_type_required'), trigger: 'change' }]
}

const isEdit = computed(() => !!route.params.id)

const loadWarehouse = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/wms/warehouses/${route.params.id}`)
    form.value = {
      name: 'Main Warehouse',
      code: 'WH-001',
      address: 'Industrial Zone',
      city: 'Riyadh',
      country: 'Saudi Arabia',
      location_type: 'warehouse',
      capacity: 10000,
      manager_id: 1,
      is_active: true,
      is_primary: true
    }
  } catch (error) {
    ElMessage.error(t('common.load_error'))
  } finally {
    loading.value = false
  }
}

const loadManagers = async () => {
  try {
    // await api.get('/api/v1/users?role=manager')
    managers.value = [
      { id: 1, name: 'Admin User' },
      { id: 2, name: 'Warehouse Manager' }
    ]
  } catch (error) {
    console.error('Failed to load managers:', error)
  }
}

const submitForm = async () => {
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        if (isEdit.value) {
          // await api.put(`/api/v1/wms/warehouses/${route.params.id}`, form.value)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/wms/warehouses', form.value)
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
  loadManagers()
  if (isEdit.value) {
    loadWarehouse()
  }
})
</script>

<style scoped>
.warehouse-form-page {
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
