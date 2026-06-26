<template>
  <div class="bin-form-page">
    <div class="page-header">
      <h1><el-icon><Box /></el-icon> {{ isEdit ? $t('wms.edit_bin') : $t('wms.create_bin') }}</h1>
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
        <el-form-item :label="$t('wms.bin_code')" prop="bin_code">
          <el-input v-model="form.bin_code" :placeholder="$t('wms.bin_code_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.zone')" prop="zone">
          <el-input v-model="form.zone" :placeholder="$t('wms.zone_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.rack')" prop="rack">
          <el-input v-model="form.rack" :placeholder="$t('wms.rack_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.shelf')" prop="shelf">
          <el-input v-model="form.shelf" :placeholder="$t('wms.shelf_placeholder')" />
        </el-form-item>
        <el-form-item :label="$t('wms.max_weight')" prop="max_weight">
          <el-input-number v-model="form.max_weight" :min="0" :max="10000" />
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
import { Box, Back } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)
const warehouses = ref([])
const form = ref({
  warehouse_id: null,
  bin_code: '',
  zone: '',
  rack: '',
  shelf: '',
  max_weight: 0
})

const rules = {
  warehouse_id: [{ required: true, message: t('wms.warehouse_required'), trigger: 'change' }],
  bin_code: [{ required: true, message: t('wms.bin_code_required'), trigger: 'blur' }],
  zone: [{ required: true, message: t('wms.zone_required'), trigger: 'blur' }],
  rack: [{ required: true, message: t('wms.rack_required'), trigger: 'blur' }],
  shelf: [{ required: true, message: t('wms.shelf_required'), trigger: 'blur' }]
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

const loadBin = async () => {
  loading.value = true
  try {
    // await api.get(`/api/v1/wms/bins/${route.params.id}`)
    form.value = {
      warehouse_id: 1,
      bin_code: 'A-R1-S1',
      zone: 'A',
      rack: 'R1',
      shelf: 'S1',
      max_weight: 500
    }
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
        if (isEdit.value) {
          // await api.put(`/api/v1/wms/bins/${route.params.id}`, form.value)
          ElMessage.success(t('common.update_success'))
        } else {
          // await api.post('/api/v1/wms/bins', form.value)
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
    loadBin()
  }
})
</script>

<style scoped>
.bin-form-page {
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
