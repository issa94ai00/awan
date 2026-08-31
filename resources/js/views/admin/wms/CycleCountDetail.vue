<template>
    <div class="cycle-count-detail-page">
        <div v-if="loading && !count" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <div v-else-if="count">
            <AdminPageHeader
                icon="fas fa-clipboard-check"
                :title="count.count_number"
            >
                <template #actions>
                    <el-tag :type="statusType(count.status)" effect="dark" size="large">{{ count.status_text }}</el-tag>
                </template>
            </AdminPageHeader>

            <el-card shadow="never" class="info-card">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.warehouse') }}</span>
                        <strong>{{ count.warehouse_name || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.bin') }}</span>
                        <strong>{{ count.bin_code || $t('wms.whole_warehouse') }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.count_type') }}</span>
                        <strong>{{ count.type_text }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.counter') }}</span>
                        <strong>{{ count.counter_name || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.reviewer') }}</span>
                        <strong>{{ count.reviewer_name || '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.accuracy') }}</span>
                        <strong>{{ count.accuracy !== null ? `${count.accuracy}%` : '—' }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.variance_items') }}</span>
                        <strong :class="{ 'text-warning': count.variance_items > 0 }">{{ count.variance_items }} / {{ count.total_items }}</strong>
                    </div>
                    <div class="info-item">
                        <span class="info-label">{{ $t('wms.variance_value') }}</span>
                        <strong>{{ formatNumber(count.variance_value) }}</strong>
                    </div>
                </div>

                <div class="progress-actions">
                    <el-button v-if="count.can_start" type="primary" :loading="saving" @click="start">
                        <i class="fas fa-play"></i> {{ $t('wms.start_count') }}
                    </el-button>
                    <el-button v-if="count.can_complete" type="success" :loading="saving" @click="complete">
                        <i class="fas fa-check"></i> {{ $t('wms.complete_count') }}
                    </el-button>
                    <el-button v-if="count.can_review" type="primary" plain :loading="saving" @click="review">
                        <i class="fas fa-user-check"></i> {{ $t('wms.review_count') }}
                    </el-button>
                    <el-button v-if="count.can_apply_adjustment" type="warning" :loading="saving" @click="applyAdjustment">
                        <i class="fas fa-scale-balanced"></i> {{ $t('wms.apply_adjustment') }}
                    </el-button>
                    <el-button v-if="count.can_cancel" type="danger" plain :loading="saving" @click="cancel">
                        <i class="fas fa-ban"></i> {{ $t('common.cancel') }}
                    </el-button>
                </div>

                <el-alert
                    v-if="count.can_start"
                    type="info" :closable="false" show-icon class="mt-3"
                    :title="$t('wms.start_count_first_hint')"
                />
                <el-alert
                    v-if="count.can_apply_adjustment"
                    type="warning" :closable="false" show-icon class="mt-3"
                    :title="$t('wms.apply_adjustment_hint')"
                />
            </el-card>

            <!-- Recording a counted line -->
            <el-card v-if="count.can_add_items" shadow="never" class="mt-3">
                <template #header><span><i class="fas fa-plus"></i> {{ $t('wms.record_count') }}</span></template>

                <el-form :model="itemForm" label-position="top" class="record-form">
                    <el-form-item :label="$t('wms.product')" required>
                        <el-select v-model="itemForm.product_id" filterable :placeholder="$t('choose_product_placeholder')" style="width: 100%">
                            <el-option v-for="p in products" :key="p.id" :value="p.id" :label="`${p.code || p.sku || ''} — ${p.name}`" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.bin')">
                        <el-select v-model="itemForm.bin_id" clearable :placeholder="$t('wms.whole_warehouse')" style="width: 100%">
                            <el-option v-for="bin in bins" :key="bin.id" :value="bin.id" :label="bin.bin_code" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.counted_quantity')" required>
                        <el-input-number v-model="itemForm.counted_quantity" :min="0" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('wms.variance_reason')">
                        <el-select v-model="itemForm.variance_reason" clearable style="width: 100%">
                            <el-option value="theft" :label="$t('wms.reason_theft')" />
                            <el-option value="damage" :label="$t('wms.reason_damage')" />
                            <el-option value="data_entry" :label="$t('wms.reason_data_entry')" />
                            <el-option value="unknown" :label="$t('wms.reason_unknown')" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('wms.notes')">
                        <el-input v-model="itemForm.notes" type="textarea" :rows="1" />
                    </el-form-item>
                </el-form>
                <el-button type="primary" :loading="saving" @click="addItem">
                    <i class="fas fa-plus"></i> {{ $t('wms.add_item') }}
                </el-button>
            </el-card>

            <!-- The counted lines -->
            <el-card shadow="never" class="mt-3">
                <template #header><span><i class="fas fa-list"></i> {{ $t('wms.counted_items') }}</span></template>

                <el-table :data="count.items" stripe>
                    <el-table-column :label="$t('wms.item')" min-width="200">
                        <template #default="{ row }">
                            <strong>{{ row.product_name || '—' }}</strong>
                            <p class="row-sub">
                                {{ row.sku || '—' }}
                                <span v-if="row.bin_code"> · {{ $t('wms.bin_prefix') }} {{ row.bin_code }}</span>
                            </p>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('wms.system_quantity')" width="110" align="center">
                        <template #default="{ row }">{{ row.expected_quantity }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('wms.counted_quantity')" width="110" align="center">
                        <template #default="{ row }">{{ row.counted_quantity }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('wms.variance')" width="100" align="center">
                        <template #default="{ row }">
                            <span :class="{ 'text-danger': row.variance < 0, 'text-success': row.variance > 0 }">
                                {{ row.variance > 0 ? '+' : '' }}{{ row.variance }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('wms.variance_value')" width="110" align="center">
                        <template #default="{ row }">{{ row.variance !== 0 ? formatNumber(row.variance_value) : '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('wms.variance_reason')" min-width="120">
                        <template #default="{ row }">{{ row.variance !== 0 ? (row.variance_reason_text || '—') : '—' }}</template>
                    </el-table-column>
                </el-table>

                <el-empty v-if="!count.items.length" :description="$t('wms.no_items_counted_yet')" />
            </el-card>
        </div>

        <el-empty v-else :description="$t('wms.cycle_count_not_found')" />
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { wmsService } from '@/services/wms';
import api from '@/api';
import { formatNumber as formatCount } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const route = useRoute();

const count = ref(null);
const loading = ref(false);
const saving = ref(false);
const products = ref([]);
const bins = ref([]);

const itemForm = reactive({ product_id: null, bin_id: null, counted_quantity: 0, variance_reason: '', notes: '' });

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');
const formatNumber = (n) => (n === null || n === undefined ? '—' : formatCount(n));

const resetItemForm = () => {
    itemForm.product_id = null;
    itemForm.bin_id = null;
    itemForm.counted_quantity = 0;
    itemForm.variance_reason = '';
    itemForm.notes = '';
};

const apply = (payload) => {
    count.value = payload;
};

const load = async () => {
    loading.value = true;
    try {
        const res = await wmsService.getCycleCount(route.params.id);
        apply(res.data?.data?.count || null);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('wms.cycle_count_not_found'));
    } finally {
        loading.value = false;
    }
};

const run = async (fn, message) => {
    saving.value = true;
    try {
        const res = await fn();
        apply(res.data?.data?.count);
        ElMessage.success(res.data?.message || message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    } finally {
        saving.value = false;
    }
};

const start = () => run(() => wmsService.startCycleCount(count.value.id));

const complete = async () => {
    try {
        await ElMessageBox.confirm(t('wms.confirm_complete_count'), t('wms.complete_count'), {
            type: 'info', confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'),
        });
    } catch { return; }
    return run(() => wmsService.completeCycleCount(count.value.id));
};

const review = () => run(() => wmsService.reviewCycleCount(count.value.id));

const applyAdjustment = async () => {
    try {
        await ElMessageBox.confirm(t('wms.confirm_apply_adjustment'), t('wms.apply_adjustment'), {
            type: 'warning', confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'),
        });
    } catch { return; }
    return run(() => wmsService.applyAdjustment(count.value.id));
};

const cancel = async () => {
    try {
        await ElMessageBox.confirm(t('wms.confirm_cancel_count'), t('common.cancel'), {
            type: 'warning', confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'),
        });
    } catch { return; }
    return run(() => wmsService.cancelCycleCount(count.value.id));
};

const addItem = async () => {
    if (!itemForm.product_id) {
        ElMessage.warning(t('choose_product_and_warehouse_first'));
        return;
    }

    saving.value = true;
    try {
        const res = await wmsService.addCycleCountItem(count.value.id, { ...itemForm });
        apply(res.data?.data?.count);
        ElMessage.success(res.data?.message || t('saved_successfully'));
        resetItemForm();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('operation_failed'));
    } finally {
        saving.value = false;
    }
};

const loadProducts = async () => {
    try {
        const res = await api.get('/products', { params: { per_page: 200 } });
        products.value = res.data?.data || [];
    } catch {
        products.value = [];
    }
};

const loadBins = async () => {
    if (!count.value?.warehouse_id) return;
    try {
        const res = await wmsService.getBins({ warehouse_id: count.value.warehouse_id });
        bins.value = res.data?.data || res.data || [];
    } catch {
        bins.value = [];
    }
};

watch(() => count.value?.warehouse_id, (id) => {
    if (id) loadBins();
});

onMounted(() => {
    load();
    loadProducts();
});
</script>

<style scoped>
.cycle-count-detail-page { padding: 0; font-family: 'Cairo', sans-serif; }

.info-card { border-radius: 12px; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
.info-item { display: flex; flex-direction: column; gap: 0.2rem; }
.info-label { font-size: 0.76rem; color: var(--text-muted); }

.progress-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-top: 1.25rem; }

.record-form { max-width: 480px; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }

.text-success { color: var(--el-color-success); font-weight: 700; }
.text-warning { color: var(--el-color-warning); font-weight: 700; }
.text-danger { color: var(--el-color-danger); font-weight: 700; }

.loading-state { padding: 2rem; }
.mt-3 { margin-top: 0.75rem; }
</style>
