<template>
    <div class="cycle-counts-page">
        <AdminPageHeader
            icon="fas fa-clipboard-check"
            :title="$t('wms.cycle_counts')"
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
                    <i class="fas fa-plus"></i> {{ $t('wms.create_cycle_count') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="never">
            <div v-if="loading" class="loading-state"><el-skeleton :rows="6" animated /></div>

            <template v-else>
                <el-table v-if="counts.length" :data="counts" stripe class="custom-table">
                    <el-table-column :label="$t('wms.count_number')" width="140">
                        <template #default="{ row }">
                            <span class="list-link" @click="open(row)">{{ row.count_number }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.warehouse')" min-width="130">
                        <template #default="{ row }">{{ row.warehouse_name || '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.bin')" width="110">
                        <template #default="{ row }">{{ row.bin_code || $t('wms.whole_warehouse') }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.count_type')" width="100" align="center">
                        <template #default="{ row }">{{ row.type_text }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('common.status')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" size="small">{{ row.status_text }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.accuracy')" width="100" align="center">
                        <template #default="{ row }">{{ row.accuracy !== null ? `${row.accuracy}%` : '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.requires_adjustment')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.requires_adjustment" type="warning" size="small" effect="plain">{{ $t('wms.yes') }}</el-tag>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('common.actions')" width="200" align="center">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-button size="small" type="info" plain :title="$t('wms.open_list')" @click="open(row)">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="success" :disabled="!row.can_start" :title="$t('wms.start_count')" @click="act(row, 'start')">
                                    <i class="fas fa-play"></i>
                                </el-button>
                                <el-button size="small" type="primary" :disabled="!row.can_complete" :title="$t('wms.complete_count')" @click="act(row, 'complete')">
                                    <i class="fas fa-check"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain :disabled="!row.can_cancel" :title="$t('common.cancel')" @click="act(row, 'cancel')">
                                    <i class="fas fa-ban"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('wms.no_cycle_counts')" />

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
        <el-dialog v-model="showCreateDialog" :title="$t('wms.create_cycle_count')" width="520px">
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('wms.warehouse')" required>
                    <el-select v-model="form.warehouse_id" style="width: 100%" @change="loadBinsForForm">
                        <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('wms.count_type')" required>
                    <el-select v-model="form.type" style="width: 100%">
                        <el-option value="full" :label="$t('wms.type_full')" />
                        <el-option value="partial" :label="$t('wms.type_partial')" />
                        <el-option value="abc" :label="$t('wms.type_abc')" />
                        <el-option value="blind" :label="$t('wms.type_blind')" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('wms.bin')">
                    <el-select v-model="form.bin_id" clearable :placeholder="$t('wms.whole_warehouse')" style="width: 100%">
                        <el-option v-for="bin in formBins" :key="bin.id" :value="bin.id" :label="bin.bin_code" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('wms.notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
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

const counts = ref([]);
const warehouses = ref([]);
const formBins = ref([]);
const pagination = ref({ current_page: 1, per_page: 20, total: 0 });

const loading = ref(false);
const saving = ref(false);
const showCreateDialog = ref(false);

const filters = reactive({ status: '', warehouse_id: null });
const form = reactive({ warehouse_id: null, type: 'partial', bin_id: null, notes: '' });

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');

const load = async (page = 1) => {
    loading.value = true;
    try {
        const params = { page, per_page: pagination.value.per_page };
        if (filters.status) params.status = filters.status;
        if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;

        const res = await wmsService.getCycleCounts(params);
        const data = res.data?.data || {};
        counts.value = data.counts || [];
        pagination.value = data.pagination || pagination.value;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_load_cycle_counts'));
    } finally {
        loading.value = false;
    }
};

const ACTIONS = {
    start: { fn: (id) => wmsService.startCycleCount(id), confirm: null },
    complete: { fn: (id) => wmsService.completeCycleCount(id), confirm: t('wms.confirm_complete_count') },
    cancel: { fn: (id) => wmsService.cancelCycleCount(id), confirm: t('wms.confirm_cancel_count') },
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
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    }
};

const openCreateDialog = () => {
    form.warehouse_id = warehouses.value[0]?.id || null;
    form.type = 'partial';
    form.bin_id = null;
    form.notes = '';
    formBins.value = [];
    if (form.warehouse_id) loadBinsForForm();
    showCreateDialog.value = true;
};

const loadBinsForForm = async () => {
    if (!form.warehouse_id) {
        formBins.value = [];
        return;
    }
    try {
        const res = await wmsService.getBins({ warehouse_id: form.warehouse_id });
        formBins.value = res.data?.data || res.data || [];
    } catch {
        formBins.value = [];
    }
};

const create = async () => {
    if (!form.warehouse_id || !form.type) {
        ElMessage.warning(t('wms.warehouse_and_type_required'));
        return;
    }

    saving.value = true;
    try {
        await wmsService.createCycleCount({ ...form });
        ElMessage.success(t('wms.cycle_count_created'));
        showCreateDialog.value = false;
        await load(1);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_cycle_count'));
    } finally {
        saving.value = false;
    }
};

const open = (row) => router.push(`/admin/wms/cycle-counts/${row.id}`);

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
.cycle-counts-page { font-family: 'Cairo', sans-serif; }

.list-link { color: var(--el-color-primary); font-weight: 700; cursor: pointer; font-family: monospace; }
.list-link:hover { text-decoration: underline; }
.muted { color: var(--text-muted); font-size: 0.78rem; }

.pagination-row { display: flex; justify-content: flex-end; padding-top: 1rem; }
.loading-state { padding: 2rem; }
</style>
