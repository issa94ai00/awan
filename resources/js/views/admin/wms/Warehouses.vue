<template>
  <div class="warehouses-list">
    <AdminPageHeader icon="fas fa-warehouse" :title="$t('wms.warehouses')" :subtitle="$t('wms.description')">
      <template #actions>
        <el-button type="primary" @click="router.push({ name: 'admin.wms.warehouses.create' })">
          <el-icon><Plus /></el-icon> {{ $t('common.add_new') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <AdminStatGrid>
      <el-card v-for="card in statCards" :key="card.key" shadow="hover" class="stat-card-wrapper" v-loading="loading">
        <div class="stat-card-inner">
          <div class="stat-icon-box" :class="card.key">
            <el-icon><component :is="card.icon" /></el-icon>
          </div>
          <div class="stat-details">
            <h3>{{ card.value }}</h3>
            <p>{{ card.title }}</p>
          </div>
        </div>
      </el-card>
    </AdminStatGrid>

    <div class="panel-card">
      <div class="filters-row">
        <div class="filter-item filter-item-search">
          <label>{{ $t('common.search') }}</label>
          <el-input v-model="filters.search" :placeholder="$t('wms.search_placeholder')" clearable :prefix-icon="Search" @keyup.enter="loadWarehouses" />
        </div>
        <div class="filter-item">
          <label>{{ $t('common.status') }}</label>
          <el-select v-model="filters.is_active" clearable @change="loadWarehouses">
            <el-option value="1" :label="$t('common.active')" />
            <el-option value="0" :label="$t('common.inactive')" />
          </el-select>
        </div>
        <div class="filter-item">
          <label>{{ $t('wms.location_type') }}</label>
          <el-select v-model="filters.location_type" :placeholder="$t('wms.all_types')" clearable @change="loadWarehouses">
            <el-option value="warehouse" :label="$t('wms.warehouse')" />
            <el-option value="branch" :label="$t('wms.branch')" />
            <el-option value="distribution_center" :label="$t('wms.distribution_center')" />
            <el-option value="3pl" :label="$t('wms.3pl')" />
          </el-select>
        </div>
        <div class="filter-actions">
          <el-button type="primary" @click="loadWarehouses">
            <el-icon><Search /></el-icon> {{ $t('common.search') }}
          </el-button>
          <el-button @click="resetFilters">{{ $t('common.reset') }}</el-button>
        </div>
      </div>

      <div class="table-wrapper">
        <el-table :data="warehouses" v-loading="loading" stripe>
          <el-table-column :label="$t('wms.name')" min-width="200">
            <template #default="{ row }">
              <div class="cell-stack">
                <span class="cell-primary">
                  {{ row.name }}
                  <el-tag v-if="row.is_primary" type="warning" size="small" effect="dark" class="primary-tag">
                    {{ $t('wms.primary_warehouse') }}
                  </el-tag>
                </span>
                <span class="cell-secondary">{{ row.code }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column :label="$t('wms.location_type')" width="150">
            <template #default="{ row }">
              <el-tag size="small" type="info">{{ locationTypeLabel(row.location_type) }}</el-tag>
            </template>
          </el-table-column>

          <el-table-column :label="$t('wms.city')" min-width="150">
            <template #default="{ row }">
              <span v-if="row.city || row.country" class="cell-secondary">{{ [row.city, row.country].filter(Boolean).join(' — ') }}</span>
              <span v-else class="cell-empty">-</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('wms.manager')" min-width="170">
            <template #default="{ row }">
              <span v-if="row.manager?.name || row.manager_name">{{ row.manager?.name || row.manager_name }}</span>
              <span v-else class="cell-empty">{{ $t('wms.no_manager') }}</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('wms.capacity')" width="180">
            <template #default="{ row }">
              <template v-if="row.capacity > 0">
                <el-progress
                  :percentage="Math.min(100, row.utilization_percentage ?? 0)"
                  :status="progressStatus(row.utilization_percentage)"
                  :stroke-width="8"
                />
                <span class="cell-secondary">{{ row.total_stock ?? 0 }} / {{ row.capacity }}</span>
              </template>
              <span v-else class="cell-empty">{{ $t('wms.unlimited') }}</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('common.status')" width="110">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                {{ row.is_active ? $t('common.active') : $t('common.inactive') }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column :label="$t('common.actions')" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <div class="row-actions">
                <el-tooltip :content="$t('wms.view_bins')" placement="top" :enterable="false">
                  <el-button size="small" circle @click="viewBins(row)">
                    <el-icon><Box /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="$t('common.edit')" placement="top" :enterable="false">
                  <el-button size="small" circle type="primary" plain @click="editWarehouse(row)">
                    <el-icon><Edit /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="$t('common.delete')" placement="top" :enterable="false">
                  <el-button size="small" circle type="danger" plain @click="deleteWarehouse(row)">
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>

          <template #empty>
            <span class="cell-empty">{{ $t('wms.no_warehouses') }}</span>
          </template>
        </el-table>
      </div>

      <div class="pagination-row">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadWarehouses"
          @current-change="loadWarehouses"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { OfficeBuilding, CircleCheck, Star, PieChart, Plus, Search, Box, Edit, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { wmsService } from '@/services/wms'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue'

const { t } = useI18n()
const router = useRouter()

const loading = ref(false)
const warehouses = ref([])

const filters = ref({
  search: '',
  is_active: '',
  location_type: '',
})

const pagination = ref({
  page: 1,
  per_page: 50,
  total: 0,
})

const locationTypeLabel = (type) => {
  const map = {
    warehouse: 'wms.warehouse',
    branch: 'wms.branch',
    distribution_center: 'wms.distribution_center',
    '3pl': 'wms.3pl',
  }
  return t(map[type] || type)
}

const progressStatus = (percentage) => {
  if (percentage === null || percentage === undefined) return ''
  if (percentage >= 100) return 'exception'
  if (percentage >= 80) return 'warning'
  return 'success'
}

const statCards = computed(() => {
  const total = pagination.value.total || warehouses.value.length
  const active = warehouses.value.filter((w) => w.is_active).length
  const primary = warehouses.value.find((w) => w.is_primary)
  const withCapacity = warehouses.value.filter((w) => w.capacity > 0 && w.utilization_percentage !== null)
  const avgUtilization = withCapacity.length
    ? Math.round(withCapacity.reduce((sum, w) => sum + Number(w.utilization_percentage || 0), 0) / withCapacity.length)
    : 0

  return [
    { key: 'total', title: t('wms.total_warehouses'), value: total, icon: OfficeBuilding },
    { key: 'active', title: t('wms.active_count'), value: active, icon: CircleCheck },
    { key: 'primary', title: t('wms.primary_warehouse'), value: primary?.name || '-', icon: Star },
    { key: 'utilization', title: t('avg_utilization'), value: `${avgUtilization}%`, icon: PieChart },
  ]
})

const loadWarehouses = async () => {
  loading.value = true
  try {
    const response = await wmsService.getWarehouses({
      search: filters.value.search || undefined,
      is_active: filters.value.is_active === '' ? undefined : filters.value.is_active,
      location_type: filters.value.location_type || undefined,
      page: pagination.value.page,
      per_page: pagination.value.per_page,
    })
    const data = response.data
    warehouses.value = data.data || []
    pagination.value.total = data.total ?? warehouses.value.length
  } catch (error) {
    ElMessage.error(t('wms.failed_to_load_warehouses'))
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.value = { search: '', is_active: '', location_type: '' }
  pagination.value.page = 1
  loadWarehouses()
}

const editWarehouse = (row) => {
  router.push({ name: 'admin.wms.warehouses.edit', params: { id: row.id } })
}

const viewBins = (row) => {
  router.push({ name: 'admin.wms.bins.index', query: { warehouse_id: row.id } })
}

const deleteWarehouse = async (row) => {
  try {
    await ElMessageBox.confirm(t('wms.confirm_delete_warehouse'), t('common.warning'), { type: 'warning' })
    await wmsService.deleteWarehouse(row.id)
    ElMessage.success(t('wms.warehouse_deleted'))
    await loadWarehouses()
  } catch (error) {
    if (error === 'cancel') return
    ElMessage.error(error.response?.data?.message || t('wms.failed_to_delete_warehouse'))
  }
}

onMounted(() => {
  loadWarehouses()
})
</script>

<style scoped>
.warehouses-list {
  --surface: #ffffff;
  --ground: #f8fafc;
  --line: #e2e8f0;
  --ink: #0f172a;
  --ink-soft: #334155;
  --ink-mute: #64748b;
  --primary: #2563eb;
  --primary-soft: #eff6ff;
  --ok: #16a34a;
  --ok-soft: #f0fdf4;
  --warn: #d97706;
  --warn-soft: #fffbeb;
  --purple: #7c3aed;
  --purple-soft: #f5f3ff;

  padding: 20px;
  font-family: 'Cairo', sans-serif;
  color: var(--ink);
}

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card-wrapper { border-radius: 14px; }
.stat-card-inner { display: flex; align-items: center; gap: 1rem; }

.stat-icon-box {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
}

.stat-icon-box.total { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.active { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.primary { background: var(--warn-soft); color: var(--warn); }
.stat-icon-box.utilization { background: var(--primary-soft); color: var(--primary); }

.stat-details h3 { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--ink); line-height: 1.2; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.82rem; font-weight: 600; }

/* ── Panel / filters ────────────────────────────────────────────────── */
.panel-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
  margin-top: 1.25rem;
}

.filters-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem;
  padding-bottom: 1.1rem;
  margin-bottom: 1.1rem;
  border-bottom: 1px solid var(--line);
}

.filter-item { display: flex; flex-direction: column; gap: 0.35rem; width: 170px; }
.filter-item-search { width: 260px; }
.filter-item label { font-size: 0.76rem; font-weight: 700; color: var(--ink-mute); }
.filter-item :deep(.el-select) { width: 100%; }

.filter-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }

.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; }
.cell-primary { font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 0.4rem; }
.cell-secondary { font-size: 0.76rem; color: var(--ink-mute); }
.cell-empty { color: #cbd5e1; }

.primary-tag { font-size: 0.68rem; }

.row-actions { display: flex; gap: 0.4rem; justify-content: center; }

.pagination-row { margin-top: 1.1rem; display: flex; justify-content: flex-end; }

@media (max-width: 768px) {
  .filters-row { flex-direction: column; align-items: stretch; }
  .filter-item, .filter-item-search { width: 100%; }
  .filter-actions { width: 100%; }
  .filter-actions .el-button { flex: 1; }
}
</style>
