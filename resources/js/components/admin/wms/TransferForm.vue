<template>
    <el-drawer
        :model-value="modelValue"
        :size="drawerSize"
        direction="rtl"
        :title="$t('trf_new')"
        :close-on-click-modal="false"
        :before-close="confirmClose"
        destroy-on-close
        @update:model-value="(value) => emit('update:modelValue', value)"
        @open="reset"
    >
        <el-form label-position="top" class="trf-form" @submit.prevent>
            <!-- From where, to where -->
            <div class="route-row">
                <el-form-item :label="$t('trf_from')" required class="route-field">
                    <el-select v-model="form.from_warehouse_id" :placeholder="$t('trf_pick_source')" filterable @change="onSourceChange">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" :disabled="w.id === form.to_warehouse_id" />
                    </el-select>
                </el-form-item>
                <i class="fas fa-arrow-left route-arrow" aria-hidden="true"></i>
                <el-form-item :label="$t('trf_to')" required class="route-field">
                    <el-select v-model="form.to_warehouse_id" :placeholder="$t('trf_pick_destination')" filterable>
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" :disabled="w.id === form.from_warehouse_id" />
                    </el-select>
                </el-form-item>
            </div>

            <!-- What goes -->
            <el-form-item :label="$t('trf_add_product')">
                <el-select
                    v-model="pickedKey"
                    class="product-picker"
                    filterable
                    remote
                    clearable
                    :disabled="!form.from_warehouse_id"
                    :placeholder="form.from_warehouse_id ? $t('trf_search_source_stock') : $t('trf_pick_source_first')"
                    :remote-method="searchStock"
                    :loading="searching"
                    @change="addLine"
                >
                    <el-option
                        v-for="option in stockOptions"
                        :key="option.product_id"
                        :value="option.product_id"
                        :label="option.name"
                        :disabled="option.available <= 0"
                    >
                        <div class="stock-option">
                            <span class="stock-option-name">{{ option.name }}</span>
                            <code v-if="option.sku" class="stock-option-sku">{{ option.sku }}</code>
                            <span class="stock-option-avail" :class="{ none: option.available <= 0 }">
                                {{ $t('trf_available_n', { count: option.available }) }}
                            </span>
                        </div>
                    </el-option>
                </el-select>
            </el-form-item>

            <el-table v-if="lines.length" :data="lines" size="small" class="lines-table">
                <el-table-column :label="$t('product')" min-width="200">
                    <template #default="{ row }">
                        <div class="line-name">{{ row.name }}</div>
                        <code v-if="row.sku" class="line-sku">{{ row.sku }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('trf_available')" width="90" align="center">
                    <template #default="{ row }">
                        <span class="avail">{{ row.available }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('quantity')" width="150" align="center">
                    <template #default="{ row }">
                        <el-input-number v-model="row.quantity" :min="1" :max="Math.max(1, row.available)" size="small" controls-position="right" />
                        <span v-if="row.unit" class="line-unit">{{ row.unit }}</span>
                    </template>
                </el-table-column>
                <el-table-column width="48" align="center">
                    <template #default="{ $index }">
                        <el-button size="small" circle text type="danger" :aria-label="$t('delete')" @click="lines.splice($index, 1)">
                            <i class="fas fa-xmark"></i>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <p v-else class="lines-empty">{{ $t('trf_no_lines') }}</p>

            <el-form-item :label="$t('notes')" class="notes-item">
                <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" show-word-limit />
            </el-form-item>

            <el-alert type="info" :closable="false" show-icon class="hold-hint" :title="$t('trf_hold_hint')" />
        </el-form>

        <template #footer>
            <div class="drawer-footer">
                <span v-if="lines.length" class="footer-summary">{{ $t('trf_lines_summary', { lines: lines.length, units: totalUnits }) }}</span>
                <el-button @click="emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSave" @click="save">{{ $t('trf_create') }}</el-button>
            </div>
        </template>
    </el-drawer>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { inventoryApi } from '@/api/inventory';
import { inventoryTransfersApi } from '@/api/inventoryTransfers';
import { apiErrorMessage } from '@/utils/sales';

/**
 * Raises a transfer: the lines are held at the source the moment it is saved,
 * so the picker only offers what that warehouse can actually spare.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    warehouses: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'saved']);

const { t } = useI18n();

const form = reactive({ from_warehouse_id: null, to_warehouse_id: null, notes: '' });
const lines = ref([]);
const saving = ref(false);

const drawerSize = computed(() => (window.innerWidth < 768 ? '100%' : '640px'));
const totalUnits = computed(() => lines.value.reduce((sum, line) => sum + (Number(line.quantity) || 0), 0));
const canSave = computed(() => form.from_warehouse_id && form.to_warehouse_id && lines.value.length > 0);

const reset = () => {
    Object.assign(form, { from_warehouse_id: null, to_warehouse_id: null, notes: '' });
    lines.value = [];
    stockOptions.value = [];
    pickedKey.value = null;
    currentSource = null;
};

// ── The source's stock ─────────────────────────────────────────────────────
const stockOptions = ref([]);
const searching = ref(false);
const pickedKey = ref(null);
let searchSeq = 0;

// Stock is kept per bin and size; what can leave is the product's total.
const groupByProduct = (rows) => {
    const byProduct = new Map();
    rows.forEach((row) => {
        const id = row.product_id;
        const entry = byProduct.get(id) || {
            product_id: id,
            name: row.product?.name_ar || row.product?.name_en || `#${id}`,
            sku: row.product?.sku || '',
            unit: row.product?.unit || '',
            available: 0,
        };
        entry.available += Math.max(0, Number(row.available) || 0);
        byProduct.set(id, entry);
    });
    return [...byProduct.values()];
};

const searchStock = async (term) => {
    if (!form.from_warehouse_id) return;
    const seq = ++searchSeq;
    searching.value = true;
    try {
        const res = await inventoryApi.getStock({
            warehouse_id: form.from_warehouse_id,
            search: term || undefined,
            sort_by: 'available',
            sort_dir: 'desc',
            per_page: 40,
        });
        if (seq !== searchSeq) return;
        stockOptions.value = groupByProduct(res.data?.data?.stock || []);
    } catch {
        if (seq === searchSeq) stockOptions.value = [];
    } finally {
        if (seq === searchSeq) searching.value = false;
    }
};

const addLine = (productId) => {
    const option = stockOptions.value.find((o) => o.product_id === productId);
    pickedKey.value = null;
    if (!option) return;
    const existing = lines.value.find((line) => line.product_id === productId);
    if (existing) {
        existing.quantity = Math.min(existing.available, existing.quantity + 1);
        return;
    }
    lines.value.push({ ...option, quantity: 1 });
};

// The lines were picked against the old source's stock, so changing it
// clears them — after asking, when there are any.
let currentSource = null;
const onSourceChange = async (value) => {
    if (lines.value.length) {
        try {
            await ElMessageBox.confirm(t('trf_source_change_confirm'), t('trf_from'), { type: 'warning' });
        } catch {
            form.from_warehouse_id = currentSource;
            return;
        }
        lines.value = [];
    }
    currentSource = value;
    stockOptions.value = [];
    if (value) searchStock('');
};

// ── Save ───────────────────────────────────────────────────────────────────
const save = async () => {
    if (!canSave.value || saving.value) return;
    saving.value = true;
    try {
        const res = await inventoryTransfersApi.create({
            from_warehouse_id: form.from_warehouse_id,
            to_warehouse_id: form.to_warehouse_id,
            notes: form.notes || undefined,
            items: lines.value.map((line) => ({ product_id: line.product_id, quantity: line.quantity })),
        });
        ElMessage.success(res.data?.message || t('trf_created'));
        lines.value = [];
        emit('saved', res.data?.data);
        emit('update:modelValue', false);
    } catch (e) {
        // The server's message names every short product and what is there.
        ElMessage({ type: 'error', message: apiErrorMessage(e, t('trf_create_failed')), duration: 8000, showClose: true });
    } finally {
        saving.value = false;
    }
};

const confirmClose = async (done) => {
    if (!lines.value.length) return done();
    try {
        await ElMessageBox.confirm(t('trf_discard_confirm'), t('trf_new'), { type: 'warning' });
        done();
    } catch { /* keep editing */ }
};
</script>

<style scoped>
.trf-form { padding-bottom: 0.5rem; }
.route-row { display: flex; align-items: flex-end; gap: 0.75rem; }
.route-field { flex: 1; min-width: 0; }
.route-field :deep(.el-select) { width: 100%; }
.route-arrow { color: #94a3b8; margin-bottom: 1.5rem; }
[dir='ltr'] .route-arrow { transform: scaleX(-1); }

.product-picker { width: 100%; }
.stock-option { display: flex; align-items: center; gap: 0.5rem; width: 100%; }
.stock-option-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stock-option-sku { font-size: 0.75rem; color: #64748b; }
.stock-option-avail { font-size: 0.75rem; font-weight: 700; color: #16a34a; white-space: nowrap; }
.stock-option-avail.none { color: #dc2626; }

.lines-table { margin-bottom: 1rem; }
.line-name { font-weight: 600; color: #0f172a; }
.line-sku { font-size: 0.72rem; color: #64748b; }
.line-unit { display: block; font-size: 0.72rem; color: #64748b; }
.avail { font-variant-numeric: tabular-nums; color: #16a34a; font-weight: 700; }
.lines-empty { margin: 0 0 1rem; padding: 1rem; text-align: center; color: #94a3b8; border: 1px dashed #e2e8f0; border-radius: 10px; }
.notes-item { margin-bottom: 0.75rem; }
.hold-hint { margin-top: 0.25rem; }

.drawer-footer { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; }
.footer-summary { margin-inline-end: auto; font-size: 0.85rem; color: #475569; }

@media (max-width: 768px) {
    .route-row { flex-direction: column; align-items: stretch; gap: 0; }
    .route-arrow { display: none; }
}
</style>
