<template>
    <div class="packing-lists-page">
        <AdminPageHeader
            icon="fas fa-box"
            :title="$t('wms.packing_lists')"
        >
            <template #actions>
                <el-select v-model="filters.status" :placeholder="$t('wms.select_status')" clearable style="width: 160px" @change="load(1)">
                    <el-option value="pending" :label="$t('wms.pending')" />
                    <el-option value="in_progress" :label="$t('wms.in_progress')" />
                    <el-option value="completed" :label="$t('wms.completed')" />
                    <el-option value="cancelled" :label="$t('wms.cancelled')" />
                </el-select>
                <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable style="width: 180px" @change="load(1)">
                    <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
                </el-select>
                <el-button type="primary" @click="openCreateDialog">
                    <i class="fas fa-plus"></i> {{ $t('wms.create_packing_list') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="never">
            <div v-if="loading" class="loading-state"><el-skeleton :rows="6" animated /></div>

            <template v-else>
                <el-table v-if="lists.length" :data="lists" stripe class="custom-table">
                    <el-table-column :label="$t('wms.list_number')" width="140">
                        <template #default="{ row }">
                            <span class="list-link" @click="open(row)">{{ row.list_number }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.order')" min-width="150">
                        <template #default="{ row }">
                            <strong>{{ row.order_number || '—' }}</strong>
                            <p class="row-sub">{{ row.customer_name || '' }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.warehouse')" min-width="130">
                        <template #default="{ row }">{{ row.warehouse_name || '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.picking_list')" width="140">
                        <template #default="{ row }">{{ row.picking_list_number || '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('common.status')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" size="small">{{ row.status_text }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.total_packages')" width="100" align="center">
                        <template #default="{ row }">{{ row.total_packages }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.packer')" width="120">
                        <template #default="{ row }">{{ row.packer_name || '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('common.actions')" width="200" align="center">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-button size="small" type="info" plain :title="$t('wms.open_list')" @click="open(row)">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="success" :disabled="!row.can_start" :title="$t('wms.start_packing')" @click="act(row, 'start')">
                                    <i class="fas fa-play"></i>
                                </el-button>
                                <el-button size="small" type="primary" :disabled="!row.can_complete" :title="$t('wms.complete_packing')" @click="act(row, 'complete')">
                                    <i class="fas fa-check"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain :disabled="!row.can_cancel" :title="$t('common.cancel')" @click="act(row, 'cancel')">
                                    <i class="fas fa-ban"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('wms.no_packing_lists')" />

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

        <!-- Create Dialog -->
        <el-dialog v-model="showCreateDialog" :title="$t('wms.create_packing_list')" width="520px">
            <el-alert
                type="info"
                :closable="false"
                show-icon
                class="mb-3"
                :title="$t('wms.packing_from_picking_hint')"
            />
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('wms.picking_list')" required>
                    <el-select v-model="form.picking_list_id" filterable :placeholder="$t('wms.select_picking_list')" style="width: 100%">
                        <el-option v-for="pl in completedPickingLists" :key="pl.id" :value="pl.id" :label="`${pl.list_number} — ${pl.order_number || ''}`" />
                    </el-select>
                    <p v-if="!completedPickingLists.length" class="empty-hint">{{ $t('wms.no_completed_picking_lists') }}</p>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="create">{{ $t('common.create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { wmsService } from '@/services/wms';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const router = useRouter();

const lists = ref([]);
const warehouses = ref([]);
const completedPickingLists = ref([]);
const pagination = ref({ current_page: 1, per_page: 20, total: 0 });

const loading = ref(false);
const saving = ref(false);
const showCreateDialog = ref(false);

const filters = reactive({ status: '', warehouse_id: null });
const form = reactive({ picking_list_id: null });

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');

const load = async (page = 1) => {
    loading.value = true;
    try {
        const params = { page, per_page: pagination.value.per_page };
        if (filters.status) params.status = filters.status;
        if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;

        const res = await wmsService.getPackingLists(params);
        const data = res.data?.data || {};
        lists.value = data.lists || [];
        pagination.value = data.pagination || pagination.value;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_load_packing_lists'));
    } finally {
        loading.value = false;
    }
};

const ACTIONS = {
    start: { fn: (id) => wmsService.startPacking(id), confirm: null },
    complete: { fn: (id) => wmsService.completePacking(id), confirm: t('wms.confirm_complete_packing') },
    cancel: { fn: (id) => wmsService.cancelPacking(id), confirm: t('wms.confirm_cancel_packing') },
};

const act = async (row, action) => {
    const cfg = ACTIONS[action];

    if (cfg.confirm) {
        try {
            await ElMessageBox.confirm(cfg.confirm, t('common.confirm'), {
                type: action === 'cancel' ? 'warning' : 'info',
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
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
        // The API explains precisely why (wrong state, etc.); a generic
        // message here would hide the reason and the way forward.
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    }
};

const openCreateDialog = async () => {
    form.picking_list_id = null;
    showCreateDialog.value = true;
    try {
        const res = await wmsService.getPickingLists({ status: 'completed', per_page: 100 });
        completedPickingLists.value = res.data?.data?.lists || [];
    } catch {
        completedPickingLists.value = [];
    }
};

const create = async () => {
    if (!form.picking_list_id) {
        ElMessage.warning(t('wms.picking_list_required'));
        return;
    }

    saving.value = true;
    try {
        await wmsService.createPackingList({ ...form });
        ElMessage.success(t('wms.packing_list_created'));
        showCreateDialog.value = false;
        await load(1);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_packing_list'));
    } finally {
        saving.value = false;
    }
};

const open = (row) => router.push(`/admin/wms/packing/${row.id}`);

const loadWarehouses = async () => {
    try {
        const res = await wmsService.getWarehouses();
        warehouses.value = res.data?.data || res.data || [];
    } catch {
        /* the filter simply stays empty */
    }
};

onMounted(() => {
    load(1);
    loadWarehouses();
});
</script>

<style scoped>
.packing-lists-page { font-family: 'Cairo', sans-serif; }

.list-link { color: var(--el-color-primary); font-weight: 700; cursor: pointer; font-family: monospace; }
.list-link:hover { text-decoration: underline; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }
.empty-hint { margin: 0.4rem 0 0; font-size: 0.78rem; color: var(--text-muted); }

.pagination-row { display: flex; justify-content: flex-end; padding-top: 1rem; }
.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
