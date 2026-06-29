<template>
  <div class="wms-dashboard">
    <div class="page-header">
      <h1><el-icon><OfficeBuilding /></el-icon> {{ $t('wms.title') }}</h1>
      <p>{{ $t('wms.description') }}</p>
    </div>

    <!-- WMS Stats -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-blue">
              <el-icon><Management /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.warehouses || 0 }}</h3>
              <p>{{ $t('wms.warehouses') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-green">
              <el-icon><Box /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.bins || 0 }}</h3>
              <p>{{ $t('wms.bins') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-orange">
              <el-icon><List /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.pickingLists || 0 }}</h3>
              <p>{{ $t('wms.picking_lists') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon stat-icon-purple">
              <el-icon><Open /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ stats.packingLists || 0 }}</h3>
              <p>{{ $t('wms.packing_lists') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Quick Actions -->
    <el-card class="actions-card">
      <template #header>
        <div class="card-header">
          <span>{{ $t('wms.quick_actions') }}</span>
        </div>
      </template>
      <el-row :gutter="20">
        <el-col :xs="12" :sm="8" :md="6">
          <el-button type="primary" @click="$router.push('/admin/wms/warehouses')" block>
            <el-icon><Management /></el-icon> {{ $t('wms.manage_warehouses') }}
          </el-button>
        </el-col>
        <el-col :xs="12" :sm="8" :md="6">
          <el-button type="success" @click="$router.push('/admin/wms/picking')" block>
            <el-icon><List /></el-icon> {{ $t('wms.picking_lists') }}
          </el-button>
        </el-col>
        <el-col :xs="12" :sm="8" :md="6">
          <el-button type="warning" @click="$router.push('/admin/wms/packing')" block>
            <el-icon><Open /></el-icon> {{ $t('wms.packing_lists') }}
          </el-button>
        </el-col>
        <el-col :xs="12" :sm="8" :md="6">
          <el-button type="info" @click="$router.push('/admin/wms/cycle-counts')" block>
            <el-icon><Document /></el-icon> {{ $t('wms.cycle_counts') }}
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- Recent Activity -->
    <el-row :gutter="20">
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('wms.recent_picking') }}</span>
              <el-button text @click="$router.push('/admin/wms/picking')">
                {{ $t('common.view_all') }}
              </el-button>
            </div>
          </template>
          <el-table :data="recentPicking" v-loading="loading">
            <el-table-column prop="list_number" :label="$t('wms.list_number')" />
            <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
            <el-table-column prop="status" :label="$t('common.status')">
              <template #default="{ row }">
                <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="$t('common.created_at')" />
          </el-table>
        </el-card>
      </el-col>
      <el-col :xs="24" :md="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ $t('wms.recent_packing') }}</span>
              <el-button text @click="$router.push('/admin/wms/packing')">
                {{ $t('common.view_all') }}
              </el-button>
            </div>
          </template>
          <el-table :data="recentPacking" v-loading="loading">
            <el-table-column prop="list_number" :label="$t('wms.list_number')" />
            <el-table-column prop="warehouse" :label="$t('wms.warehouse')" />
            <el-table-column prop="status" :label="$t('common.status')">
              <template #default="{ row }">
                <el-tag :type="getStatusType(row.status)">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="$t('common.created_at')" />
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Management, Box, List, Open, Document, OfficeBuilding } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import { wmsService } from '@/services/wms'

const { t } = useI18n()
const loading = ref(false)
const stats = ref({
  warehouses: 0,
  bins: 0,
  pickingLists: 0,
  packingLists: 0
})
const recentPicking = ref([])
const recentPacking = ref([])

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    in_progress: 'primary',
    completed: 'success',
    cancelled: 'danger'
  }
  return types[status] || 'info'
}

onMounted(async () => {
  loading.value = true
  try {
    const [statsRes, pickingRes, packingRes] = await Promise.all([
      wmsService.getWmsStats(),
      wmsService.getPickingLists({ per_page: 5 }),
      wmsService.getPackingLists({ per_page: 5 })
    ])
    
    stats.value = statsRes.data
    recentPicking.value = (pickingRes.data.data || pickingRes.data || []).slice(0, 5)
    recentPacking.value = (packingRes.data.data || packingRes.data || []).slice(0, 5)
  } catch (error) {
    console.error('Failed to load WMS stats:', error)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.wms-dashboard {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 10px 0;
  font-size: 28px;
  color: #333;
}

.page-header p {
  margin: 0;
  color: #666;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  margin-bottom: 20px;
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.stat-icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon-purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.stat-info h3 {
  margin: 0 0 5px 0;
  font-size: 24px;
  font-weight: 600;
  color: #333;
}

.stat-info p {
  margin: 0;
  font-size: 14px;
  color: #666;
}

.actions-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
