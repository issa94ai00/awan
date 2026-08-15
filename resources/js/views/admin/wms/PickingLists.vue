<template>
  <div class="picking-lists-page">
    <div class="page-header">
      <div class="page-title">
        <h1><i class="fas fa-clipboard-list"></i> {{ $t('picking_lists') }}</h1>
        <p>{{ $t('picking_lists_subtitle') }}</p>
      </div>
      <div class="header-actions">
        <el-input
          v-model="filters.search"
          :placeholder="$t('search_list_or_order_number')"
          clearable
          class="search-input"
          @input="onSearch"
          @clear="load(1)"
        >
          <template #prefix><i class="fas fa-search"></i></template>
        </el-input>
        <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable style="width: 180px" @change="load(1)">
          <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
        </el-select>
        <el-button type="primary" @click="showCreateDialog = true">
          <i class="fas fa-plus"></i> {{ $t('manual_list') }}
        </el-button>
      </div>
    </div>

    <!-- Stage tabs with counts across the whole queue, not the page -->
    <el-tabs v-model="activeStatus" class="status-tabs" @tab-change="load(1)">
      <el-tab-pane v-for="tab in statusTabs" :key="tab.name" :name="tab.name">
        <template #label>
          <span class="tab-label">
            <i class="fas" :class="tab.icon"></i> {{ tab.label }}
            <el-badge v-if="tab.count" :value="tab.count" :type="tab.badge" class="tab-badge" />
          </span>
        </template>
      </el-tab-pane>
    </el-tabs>

    <el-card shadow="never">
      <div v-if="loading" class="loading-state"><el-skeleton :rows="6" animated /></div>

      <template v-else>
        <el-table v-if="lists.length" :data="lists" stripe class="custom-table">
          <el-table-column :label="$t('list_number')" width="140">
            <template #default="{ row }">
              <span class="list-link" @click="open(row)">{{ row.list_number }}</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('sales_order')" min-width="150">
            <template #default="{ row }">
              <strong>{{ row.order_number || '—' }}</strong>
              <p class="row-sub">{{ row.customer_name || '' }}</p>
            </template>
          </el-table-column>

          <el-table-column :label="$t('warehouse')" min-width="130">
            <template #default="{ row }">{{ row.warehouse_name || '—' }}</template>
          </el-table-column>

          <el-table-column :label="$t('priority')" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="priorityType(row.priority)" size="small" effect="plain">{{ row.priority_text }}</el-tag>
            </template>
          </el-table-column>

          <el-table-column :label="$t('progress')" width="170">
            <template #default="{ row }">
              <el-progress
                :percentage="row.progress"
                :stroke-width="8"
                :status="row.progress === 100 ? 'success' : undefined"
                :show-text="false"
              />
              <span class="progress-note">{{ row.picked_items }} / {{ row.total_items }} {{ $t('items_unit') }}</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('status')" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ row.status_text }}</el-tag>
            </template>
          </el-table-column>

          <el-table-column :label="$t('picker')" width="120">
            <template #default="{ row }">{{ row.picker_name || '—' }}</template>
          </el-table-column>

          <el-table-column :label="$t('actions')" width="200" align="center">
            <template #default="{ row }">
              <el-button-group>
                <el-button size="small" type="info" plain :title="$t('open_list')" @click="open(row)">
                  <i class="fas fa-eye"></i>
                </el-button>
                <el-button size="small" type="success" :disabled="!row.can_start" :title="$t('start_picking')" @click="act(row, 'start')">
                  <i class="fas fa-play"></i>
                </el-button>
                <el-button size="small" type="primary" :disabled="!row.can_complete" :title="$t('complete_picking')" @click="act(row, 'complete')">
                  <i class="fas fa-check"></i>
                </el-button>
                <el-button size="small" type="danger" plain :disabled="!row.can_cancel" :title="$t('cancel')" @click="act(row, 'cancel')">
                  <i class="fas fa-ban"></i>
                </el-button>
              </el-button-group>
            </template>
          </el-table-column>
        </el-table>

        <el-empty v-else :description="$t('no_picking_lists_in_status')" />

        <div v-if="pagination.total > pagination.per_page" class="pagination-row">
          <el-pagination
            layout="prev, pager, next, total"
            :total="pagination.total"
            :current-page="pagination.current_page"
            :page-size="pagination.per_page"
            background
            @current-change="load"
          />
        </div>
      </template>
    </el-card>

    <!-- Manual creation. Normally the list arrives with the order's confirmation. -->
    <el-dialog v-model="showCreateDialog" :title="$t('create_picking_list_manually')" width="520px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="mb-3"
        :title="$t('manual_picking_list_hint')"
      />
      <el-form :model="form" label-position="top">
        <el-form-item :label="$t('sales_order')" required>
          <el-select v-model="form.sales_order_id" filterable :placeholder="$t('choose_the_order')" style="width: 100%">
            <el-option
              v-for="o in orders"
              :key="o.id"
              :value="o.id"
              :label="`${o.order_number} — ${o.customer?.name || ''}`"
            />
          </el-select>
        </el-form-item>
        <el-form-item :label="$t('warehouse')">
          <el-select v-model="form.warehouse_id" clearable :placeholder="$t('fulfillment_warehouse')" style="width: 100%">
            <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ $t('cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="create">{{ $t('create') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { wmsService } from '@/services/wms';
import { salesOrdersApi } from '@/api/salesOrders';

const { t } = useI18n();

const router = useRouter();

const lists = ref([]);
const warehouses = ref([]);
const orders = ref([]);
const counts = ref({ all: 0, pending: 0, in_progress: 0, completed: 0, cancelled: 0 });
const pagination = ref({ current_page: 1, per_page: 20, total: 0 });

const loading = ref(false);
const saving = ref(false);
const showCreateDialog = ref(false);
const activeStatus = ref('open');

const filters = reactive({ search: '', warehouse_id: null });
const form = reactive({ sales_order_id: null, warehouse_id: null });

// "Open" leads because it is the work still to be done; the rest are history.
const statusTabs = computed(() => [
  { name: 'open', label: t('in_progress_status'), icon: 'fa-hourglass-half', count: counts.value.pending + counts.value.in_progress, badge: 'warning' },
  { name: 'pending', label: t('awaiting_start'), icon: 'fa-clock', count: counts.value.pending, badge: 'warning' },
  { name: 'in_progress', label: t('picking_in_progress'), icon: 'fa-person-walking', count: counts.value.in_progress, badge: 'primary' },
  { name: 'completed', label: t('completed_female'), icon: 'fa-check', count: counts.value.completed, badge: 'success' },
  { name: 'cancelled', label: t('invoice_status_cancelled'), icon: 'fa-ban', count: counts.value.cancelled, badge: 'danger' },
  { name: 'all', label: t('all'), icon: 'fa-layer-group', count: counts.value.all, badge: 'info' },
]);

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');
const priorityType = (p) => ({ low: 'info', normal: 'primary', high: 'warning', urgent: 'danger' }[p] || 'info');

const load = async (page = 1) => {
  loading.value = true;
  try {
    const params = { page, per_page: pagination.value.per_page };

    if (activeStatus.value === 'open') params.open_only = 1;
    else if (activeStatus.value !== 'all') params.status = activeStatus.value;

    if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
    if (filters.search.trim()) params.search = filters.search.trim();

    const res = await wmsService.getPickingLists(params);
    const data = res.data?.data || {};
    lists.value = data.lists || [];
    counts.value = data.status_counts || counts.value;
    pagination.value = data.pagination || pagination.value;
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('failed_to_load_picking_lists'));
  } finally {
    loading.value = false;
  }
};

let searchTimer = null;
const onSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 400);
};

const ACTIONS = {
  start: { fn: (id) => wmsService.startPicking(id), confirm: null },
  complete: { fn: (id) => wmsService.completePicking(id), confirm: t('confirm_complete_picking') },
  cancel: { fn: (id) => wmsService.cancelPicking(id), confirm: t('confirm_cancel_picking_list') },
};

const act = async (row, action) => {
  const cfg = ACTIONS[action];

  if (cfg.confirm) {
    try {
      await ElMessageBox.confirm(cfg.confirm, t('confirm'), {
        type: action === 'cancel' ? 'warning' : 'info',
        confirmButtonText: t('proceed'),
        cancelButtonText: t('back'),
      });
    } catch {
      return;
    }
  }

  try {
    const res = await cfg.fn(row.id);
    ElMessage.success(res.data?.message || t('operation_completed'));
    await load(pagination.value.current_page);
  } catch (e) {
    // The API explains precisely why (unpicked items, wrong state); a generic
    // message here would hide the reason and the way forward.
    ElMessage.error(e.response?.data?.message || t('operation_failed'));
  }
};

const create = async () => {
  if (!form.sales_order_id) {
    ElMessage.warning(t('choose_sales_order_first'));
    return;
  }

  saving.value = true;
  try {
    await wmsService.createPickingList({ ...form });
    ElMessage.success(t('picking_list_created'));
    showCreateDialog.value = false;
    form.sales_order_id = null;
    form.warehouse_id = null;
    await load(1);
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('failed_to_create_picking_list'));
  } finally {
    saving.value = false;
  }
};

const open = (row) => router.push(`/admin/wms/picking/${row.id}`);

const loadWarehouses = async () => {
  try {
    const res = await wmsService.getWarehouses();
    warehouses.value = res.data?.data || res.data || [];
  } catch {
    /* the filter simply stays empty */
  }
};

const loadOrders = async () => {
  try {
    // Confirmed orders are the ones that need picking.
    const res = await salesOrdersApi.getAll({ per_page: 100, status: 'confirmed' });
    orders.value = res.data?.data?.sales_orders || [];
  } catch {
    /* the dialog's dropdown stays empty */
  }
};

onMounted(() => {
  load(1);
  loadWarehouses();
  loadOrders();
});
</script>

<style scoped>
.picking-lists-page { font-family: 'Cairo', sans-serif; }

.page-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border-color);
}

.page-title h1 { margin: 0; font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 0.6rem; }
.page-title h1 i { color: var(--el-color-primary); }
.page-title p { margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.85rem; }

.header-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.search-input { width: 230px; }

.status-tabs { margin-bottom: 0.5rem; }
.tab-label { display: inline-flex; align-items: center; gap: 0.4rem; }
.tab-badge { margin-inline-start: 0.5rem; }

.list-link { color: var(--el-color-primary); font-weight: 700; cursor: pointer; font-family: monospace; }
.list-link:hover { text-decoration: underline; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }
.progress-note { font-size: 0.72rem; color: var(--text-muted); }

.pagination-row { display: flex; justify-content: flex-end; padding-top: 1rem; }
.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
