<template>
    <div class="packing-detail-page">
        <div v-if="loading && !list" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <div v-else-if="list">
            <AdminPageHeader
                icon="fas fa-box"
                :title="list.list_number"
            >
                <template #actions>
                    <el-tag :type="statusType(list.status)" effect="dark" size="large">{{ list.status_text }}</el-tag>
                </template>
            </AdminPageHeader>

            <!-- What this list is for, and where it stands -->
            <el-card shadow="never" class="info-card">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.order') }}</span>
                        <strong>{{ list.order_number || '—' }}</strong>
                        <span v-if="list.customer_name" class="info-sub">{{ list.customer_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.warehouse') }}</span>
                        <strong>{{ list.warehouse_name || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.picking_list') }}</span>
                        <strong>{{ list.picking_list_number || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.packer') }}</span>
                        <strong>{{ list.packer_name || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.total_packages') }}</span>
                        <strong>{{ list.total_packages }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.total_weight') }}</span>
                        <strong>{{ formatNumber(list.total_weight) }} {{ $t('wms.kg') }}</strong>
                    </div>
                </div>

                <div class="progress-actions">
                    <el-button v-if="list.can_start" type="primary" :loading="saving" @click="start">
                        <i class="fas fa-play"></i> {{ $t('wms.start_packing') }}
                    </el-button>
                    <el-button v-if="list.can_complete" type="success" :loading="saving" @click="complete">
                        <i class="fas fa-check"></i> {{ $t('wms.complete_packing') }}
                    </el-button>
                    <el-button v-if="list.can_cancel" type="danger" plain :loading="saving" @click="cancel">
                        <i class="fas fa-ban"></i> {{ $t('common.cancel') }}
                    </el-button>
                    <el-button plain :loading="validating" @click="validate">
                        <i class="fas fa-circle-check"></i> {{ $t('wms.validate_packing') }}
                    </el-button>
                    <el-button plain @click="showLabels">
                        <i class="fas fa-tag"></i> {{ $t('wms.view_labels') }}
                    </el-button>
                </div>

                <el-alert
                    v-if="list.can_start"
                    type="info"
                    :closable="false"
                    show-icon
                    class="mt-3"
                    :title="$t('wms.start_packing_first_hint')"
                />
            </el-card>

            <!-- The packages, editable while packing is in progress -->
            <el-card shadow="never" class="mt-3">
                <template #header>
                    <span><i class="fas fa-boxes-stacked"></i> {{ $t('wms.items_to_pack') }}</span>
                </template>

                <el-table :data="list.items" stripe>
                    <el-table-column :label="$t('wms.item')" min-width="200">
                        <template #default="{ row }">
                            <strong>{{ row.product_name || '—' }}</strong>
                            <p class="row-sub">{{ row.sku || '—' }}</p>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.quantity')" width="90" align="center">
                        <template #default="{ row }">{{ row.quantity }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.package_number')" width="120" align="center">
                        <template #default="{ row }">{{ row.package_number || '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.weight')" width="100" align="center">
                        <template #default="{ row }">{{ row.weight !== null ? `${formatNumber(row.weight)} ${$t('wms.kg')}` : '—' }}</template>
                    </el-table-column>

                    <el-table-column :label="$t('wms.fragile')" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.fragile" type="warning" size="small" effect="plain">{{ $t('wms.fragile') }}</el-tag>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('common.actions')" width="90" align="center">
                        <template #default="{ row }">
                            <el-button size="small" plain :disabled="!isEditable" @click="openEdit(row)">
                                <i class="fas fa-pen"></i>
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <el-empty v-else :description="$t('wms.packing_list_not_found')" />

        <!-- Edit package details -->
        <el-dialog v-model="showEditDialog" :title="$t('wms.edit_package_details')" width="480px">
            <el-form :model="editForm" label-position="top">
                <el-form-item :label="$t('wms.package_number')">
                    <el-input v-model="editForm.package_number" />
                </el-form-item>
                <el-form-item :label="$t('wms.weight')">
                    <el-input-number v-model="editForm.weight" :min="0" :step="0.1" style="width: 100%" />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="$t('wms.length')">
                            <el-input-number v-model="editForm.dimensions.length" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('wms.width')">
                            <el-input-number v-model="editForm.dimensions.width" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('wms.height')">
                            <el-input-number v-model="editForm.dimensions.height" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item>
                    <el-checkbox v-model="editForm.fragile">{{ $t('wms.fragile') }}</el-checkbox>
                </el-form-item>
                <el-form-item :label="$t('wms.notes')">
                    <el-input v-model="editForm.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveEdit">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Validation result -->
        <el-dialog v-model="showValidateDialog" :title="$t('wms.validate_packing')" width="480px">
            <el-alert
                v-if="validation"
                :type="validation.valid ? 'success' : 'error'"
                :closable="false"
                show-icon
                :title="validation.valid ? $t('wms.packing_is_valid') : $t('wms.packing_has_errors')"
            />
            <ul v-if="validation?.errors?.length" class="issue-list issue-list-error">
                <li v-for="(e, i) in validation.errors" :key="`e${i}`">{{ e }}</li>
            </ul>
            <ul v-if="validation?.warnings?.length" class="issue-list issue-list-warning">
                <li v-for="(w, i) in validation.warnings" :key="`w${i}`">{{ w }}</li>
            </ul>
            <template #footer>
                <el-button type="primary" @click="showValidateDialog = false">{{ $t('close') }}</el-button>
            </template>
        </el-dialog>

        <!-- Labels -->
        <el-dialog v-model="showLabelsDialog" :title="$t('wms.view_labels')" width="560px">
            <div v-for="(label, i) in labels" :key="i" class="label-card">
                <div class="label-row"><strong>{{ label.package_number }}</strong><span>{{ label.barcode }}</span></div>
                <div class="label-row">{{ label.product_name }} × {{ label.quantity }}</div>
                <div class="label-row muted">
                    {{ label.order_number || '—' }}
                    <span v-if="label.weight"> · {{ formatNumber(label.weight) }} {{ $t('wms.kg') }}</span>
                    <el-tag v-if="label.fragile" type="warning" size="small" effect="plain">{{ $t('wms.fragile') }}</el-tag>
                </div>
            </div>
            <el-empty v-if="!labels.length" :description="$t('wms.no_labels')" />
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { wmsService } from '@/services/wms';
import { formatNumber as formatCount } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const route = useRoute();

const list = ref(null);
const loading = ref(false);
const saving = ref(false);
const validating = ref(false);

const showEditDialog = ref(false);
const showValidateDialog = ref(false);
const showLabelsDialog = ref(false);
const validation = ref(null);
const labels = ref([]);

const editingItemId = ref(null);
const editForm = reactive({ package_number: '', weight: 0, dimensions: { length: 0, width: 0, height: 0 }, fragile: false, notes: '' });

const isEditable = ref(false);

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');
const formatNumber = (n) => (n === null || n === undefined ? '—' : formatCount(n));

const apply = (payload) => {
    list.value = payload;
    isEditable.value = payload?.status !== 'completed' && payload?.status !== 'cancelled';
};

const load = async () => {
    loading.value = true;
    try {
        const res = await wmsService.getPackingList(route.params.id);
        apply(res.data?.data?.list || null);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('wms.packing_list_not_found'));
    } finally {
        loading.value = false;
    }
};

const run = async (fn, message) => {
    saving.value = true;
    try {
        const res = await fn();
        apply(res.data?.data?.list);
        ElMessage.success(res.data?.message || message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    } finally {
        saving.value = false;
    }
};

const start = () => run(() => wmsService.startPacking(list.value.id));

const complete = async () => {
    try {
        await ElMessageBox.confirm(t('wms.confirm_complete_packing'), t('wms.complete_packing'), {
            type: 'info', confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'),
        });
    } catch {
        return;
    }
    return run(() => wmsService.completePacking(list.value.id));
};

const cancel = async () => {
    try {
        await ElMessageBox.confirm(t('wms.confirm_cancel_packing'), t('common.cancel'), {
            type: 'warning', confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'),
        });
    } catch {
        return;
    }
    return run(() => wmsService.cancelPacking(list.value.id));
};

const openEdit = (row) => {
    editingItemId.value = row.id;
    editForm.package_number = row.package_number || '';
    editForm.weight = row.weight ?? 0;
    editForm.dimensions = { length: row.dimensions?.length ?? 0, width: row.dimensions?.width ?? 0, height: row.dimensions?.height ?? 0 };
    editForm.fragile = !!row.fragile;
    editForm.notes = row.notes || '';
    showEditDialog.value = true;
};

const saveEdit = async () => {
    saving.value = true;
    try {
        const res = await wmsService.updatePackageDetails(editingItemId.value, { ...editForm });
        apply(res.data?.data?.list);
        showEditDialog.value = false;
        ElMessage.success(res.data?.message || t('saved_successfully'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    } finally {
        saving.value = false;
    }
};

const validate = async () => {
    validating.value = true;
    try {
        const res = await wmsService.validatePacking(list.value.id);
        validation.value = res.data;
        showValidateDialog.value = true;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    } finally {
        validating.value = false;
    }
};

const showLabels = async () => {
    try {
        const res = await wmsService.getPackingLabels(list.value.id);
        labels.value = res.data || [];
        showLabelsDialog.value = true;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    }
};

onMounted(load);
</script>

<style scoped>
.packing-detail-page { padding: 0; font-family: 'Cairo', sans-serif; }

.info-card { border-radius: 12px; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
.info-item { display: flex; flex-direction: column; gap: 0.2rem; }
.info-label { font-size: 0.76rem; color: var(--text-muted); }
.info-sub { font-size: 0.78rem; color: var(--text-muted); }

.progress-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-top: 1.25rem; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }
.muted { color: var(--text-muted); font-size: 0.78rem; }

.issue-list { margin: 0.75rem 0 0; padding-inline-start: 1.2rem; font-size: 0.85rem; }
.issue-list-error { color: var(--el-color-danger); }
.issue-list-warning { color: var(--el-color-warning); }

.label-card { border: 1px dashed var(--border-color); border-radius: 8px; padding: 0.6rem 0.8rem; margin-bottom: 0.6rem; }
.label-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem; }
.label-row:not(:last-child) { margin-bottom: 0.2rem; }

.loading-state { padding: 2rem; }
.mt-3 { margin-top: 0.75rem; }
</style>
