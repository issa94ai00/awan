<template>
  <div class="warehouse-form-page">
    <AdminPageHeader
      icon="fas fa-warehouse"
      :title="isEdit ? $t('wms.edit_warehouse') : $t('wms.create_warehouse')"
    >
      <template #actions>
        <el-button @click="router.push({ name: 'admin.wms.warehouses.index' })">
          <el-icon><Back /></el-icon> {{ $t('common.back') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-form
      :model="form"
      :rules="rules"
      ref="formRef"
      label-position="top"
      v-loading="loading"
      @submit.prevent="submitForm"
    >
      <el-card class="form-section">
        <template #header>{{ $t('wms.basic_info') }}</template>
        <div class="form-grid">
          <el-form-item :label="$t('wms.name')" prop="name">
            <el-input v-model="form.name" :placeholder="$t('wms.name_placeholder')" />
          </el-form-item>
          <el-form-item :label="$t('wms.code')" prop="code">
            <el-input v-model="form.code" :placeholder="$t('wms.code_placeholder')" />
          </el-form-item>
          <el-form-item :label="$t('wms.location_type')" prop="location_type">
            <el-select v-model="form.location_type" :placeholder="$t('wms.select_location_type')" class="full-width">
              <el-option value="warehouse" :label="$t('wms.warehouse')" />
              <el-option value="branch" :label="$t('wms.branch')" />
              <el-option value="distribution_center" :label="$t('wms.distribution_center')" />
              <el-option value="3pl" :label="$t('wms.3pl')" />
            </el-select>
          </el-form-item>
          <el-form-item :label="$t('common.status')">
            <el-switch v-model="form.is_active" :active-text="$t('common.active')" :inactive-text="$t('common.inactive')" />
          </el-form-item>
          <el-form-item :label="$t('wms.is_primary')">
            <el-switch v-model="form.is_primary" :active-text="$t('common.yes')" :inactive-text="$t('common.no')" />
            <p v-if="form.is_primary" class="field-hint">{{ $t('wms.primary_hint') }}</p>
          </el-form-item>
        </div>
      </el-card>

      <el-card class="form-section">
        <template #header>{{ $t('wms.address_info') }}</template>
        <el-form-item :label="$t('wms.address')" prop="address">
          <el-input v-model="form.address" type="textarea" :rows="2" :placeholder="$t('wms.address_placeholder')" />
        </el-form-item>
        <div class="form-grid">
          <el-form-item :label="$t('wms.city')" prop="city">
            <el-input v-model="form.city" :placeholder="$t('wms.city_placeholder')" />
          </el-form-item>
          <el-form-item :label="$t('wms.country')" prop="country">
            <el-input v-model="form.country" :placeholder="$t('wms.country_placeholder')" />
          </el-form-item>
        </div>
      </el-card>

      <el-card class="form-section">
        <template #header>{{ $t('wms.manager_section') }}</template>
        <el-form-item :label="$t('wms.manager_id')">
          <el-select
            v-model="form.manager_id"
            :placeholder="$t('wms.select_linked_user')"
            filterable
            clearable
            class="full-width"
          >
            <el-option v-for="user in managers" :key="user.id" :value="user.id" :label="`${user.name} (${user.email})`" />
          </el-select>
        </el-form-item>
        <div class="form-grid">
          <el-form-item :label="$t('wms.manager_name')">
            <el-input v-model="form.manager_name" />
          </el-form-item>
          <el-form-item :label="$t('wms.manager_phone')">
            <el-input v-model="form.manager_phone" />
          </el-form-item>
        </div>
        <p class="field-hint">{{ $t('wms.manager_contact') }}</p>
      </el-card>

      <el-card class="form-section">
        <template #header>{{ $t('wms.capacity_location') }}</template>
        <div class="form-grid">
          <el-form-item :label="$t('wms.capacity')" prop="capacity">
            <el-input-number v-model="form.capacity" :min="0" :max="10000000" class="full-width" />
          </el-form-item>
        </div>
        <div class="form-grid form-grid-3">
          <el-form-item :label="$t('wms.latitude')">
            <el-input-number v-model="form.latitude" :precision="6" :step="0.0001" :controls="false" class="full-width" />
          </el-form-item>
          <el-form-item :label="$t('wms.longitude')">
            <el-input-number v-model="form.longitude" :precision="6" :step="0.0001" :controls="false" class="full-width" />
          </el-form-item>
          <el-form-item label=" ">
            <el-button @click="useCurrentLocation">
              <el-icon><LocationFilled /></el-icon> {{ $t('wms.use_current_location') }}
            </el-button>
          </el-form-item>
        </div>
      </el-card>

      <el-collapse class="form-section hours-collapse">
        <el-collapse-item :title="$t('wms.operating_hours')" name="hours">
          <div v-for="day in days" :key="day.key" class="hours-row">
            <span class="hours-day">{{ $t(day.label) }}</span>
            <el-switch
              v-model="form.operating_hours[day.key].is_closed"
              :active-text="$t('wms.closed')"
              inline-prompt
            />
            <template v-if="!form.operating_hours[day.key].is_closed">
              <span class="hours-label">{{ $t('wms.from') }}</span>
              <el-time-select
                v-model="form.operating_hours[day.key].open"
                start="00:00" step="00:30" end="23:30"
                class="hours-time"
              />
              <span class="hours-label">{{ $t('wms.to') }}</span>
              <el-time-select
                v-model="form.operating_hours[day.key].close"
                start="00:00" step="00:30" end="23:30"
                class="hours-time"
              />
            </template>
          </div>
        </el-collapse-item>
      </el-collapse>

      <div class="form-actions">
        <el-button type="primary" native-type="submit" :loading="saving">
          {{ $t('common.save') }}
        </el-button>
        <el-button @click="router.push({ name: 'admin.wms.warehouses.index' })">
          {{ $t('common.cancel') }}
        </el-button>
      </div>
    </el-form>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Back, LocationFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { wmsService } from '@/services/wms'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const managers = ref([])

const isEdit = computed(() => !!route.params.id)

const days = [
  { key: 'sunday', label: 'wms.day_sun' },
  { key: 'monday', label: 'wms.day_mon' },
  { key: 'tuesday', label: 'wms.day_tue' },
  { key: 'wednesday', label: 'wms.day_wed' },
  { key: 'thursday', label: 'wms.day_thu' },
  { key: 'friday', label: 'wms.day_fri' },
  { key: 'saturday', label: 'wms.day_sat' },
]

function defaultOperatingHours() {
  const hours = {}
  days.forEach(({ key }) => {
    hours[key] = { open: '09:00', close: '17:00', is_closed: false }
  })
  return hours
}

const form = reactive({
  name: '',
  code: '',
  address: '',
  city: '',
  country: '',
  location_type: 'warehouse',
  capacity: null,
  latitude: null,
  longitude: null,
  manager_id: null,
  manager_name: '',
  manager_phone: '',
  is_active: true,
  is_primary: false,
  operating_hours: defaultOperatingHours(),
})

const rules = {
  name: [{ required: true, message: t('wms.name_required'), trigger: 'blur' }],
  code: [{ required: true, message: t('wms.code_required'), trigger: 'blur' }],
  location_type: [{ required: true, message: t('wms.location_type_required'), trigger: 'change' }],
}

const loadWarehouse = async () => {
  loading.value = true
  try {
    const { data } = await wmsService.getWarehouse(route.params.id)
    Object.assign(form, {
      name: data.name || '',
      code: data.code || '',
      address: data.address || '',
      city: data.city || '',
      country: data.country || '',
      location_type: data.location_type || 'warehouse',
      capacity: data.capacity ?? null,
      latitude: data.latitude !== null ? Number(data.latitude) : null,
      longitude: data.longitude !== null ? Number(data.longitude) : null,
      manager_id: data.manager_id ?? null,
      manager_name: data.manager_name || '',
      manager_phone: data.manager_phone || '',
      is_active: !!data.is_active,
      is_primary: !!data.is_primary,
      operating_hours: { ...defaultOperatingHours(), ...(data.operating_hours || {}) },
    })
  } catch (error) {
    ElMessage.error(t('common.load_error'))
    router.push({ name: 'admin.wms.warehouses.index' })
  } finally {
    loading.value = false
  }
}

const loadManagers = async () => {
  try {
    const { data } = await wmsService.getManagers()
    managers.value = data || []
  } catch (error) {
    // The manager picker is a convenience — a warehouse can still be saved
    // with just the free-text contact fields if this fails.
    console.error('Failed to load managers:', error)
  }
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    ElMessage.error(t('wms.location_fetch_failed'))
    return
  }
  navigator.geolocation.getCurrentPosition(
    (position) => {
      form.latitude = position.coords.latitude
      form.longitude = position.coords.longitude
    },
    () => ElMessage.error(t('wms.location_fetch_failed'))
  )
}

const submitForm = async () => {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    const payload = { ...form }

    if (isEdit.value) {
      await wmsService.updateWarehouse(route.params.id, payload)
      ElMessage.success(t('wms.warehouse_updated'))
    } else {
      await wmsService.createWarehouse(payload)
      ElMessage.success(t('wms.warehouse_created'))
    }
    router.push({ name: 'admin.wms.warehouses.index' })
  } catch (error) {
    const errors = error.response?.data?.errors
    if (errors) {
      ElMessage.error(Object.values(errors).flat()[0] || t('wms.failed_to_save_warehouse'))
    } else {
      ElMessage.error(error.response?.data?.message || t('wms.failed_to_save_warehouse'))
    }
  } finally {
    saving.value = false
  }
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
  max-width: 900px;
  margin: 0 auto;
}

.form-section {
  margin-bottom: 1rem;
  border-radius: 12px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0 1.25rem;
}

.form-grid-3 {
  grid-template-columns: repeat(3, 1fr);
  align-items: start;
}

.full-width {
  width: 100%;
}

.field-hint {
  margin: -0.5rem 0 0.75rem;
  font-size: 0.8rem;
  color: #d97706;
}

.hours-collapse {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.hours-collapse :deep(.el-collapse-item__header) {
  padding: 0 1.25rem;
  font-weight: 700;
}

.hours-row {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 0.4rem 0;
}

.hours-day {
  width: 90px;
  font-weight: 600;
  color: #334155;
}

.hours-label {
  font-size: 0.82rem;
  color: #64748b;
}

.hours-time {
  width: 130px;
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1.25rem;
}

@media (max-width: 640px) {
  .form-grid,
  .form-grid-3 {
    grid-template-columns: 1fr;
  }
}
</style>
