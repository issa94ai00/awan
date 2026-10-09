<template>
    <el-dialog
        v-model="visible"
        :title="$t('cost_calculation_method_management')"
        width="820px"
        top="6vh"
        destroy-on-close
        class="cost-method-dialog"
    >
        <div v-loading="loading" class="cost-method-content">
            <!-- Header banner with explanation -->
            <div class="dialog-banner">
                <div class="banner-icon">
                    <el-icon :size="28"><Operation /></el-icon>
                </div>
                <div class="banner-text">
                    <h4>{{ $t('cost_calculation_method_title') }}</h4>
                    <p>{{ $t('cost_calculation_method_desc') }}</p>
                </div>
                <div v-if="stats" class="banner-badge">
                    <span class="badge-label">{{ $t('current_inventory_value') }}</span>
                    <strong class="badge-value">{{ formatMoney(stats.total_value) }}</strong>
                </div>
            </div>

            <!-- Stats Bar -->
            <div v-if="stats" class="valuation-stats-bar">
                <div class="v-stat-item">
                    <span class="v-stat-title">{{ $t('total_quantity') }}</span>
                    <strong class="v-stat-num">{{ formatNumber(stats.total_layers_quantity) }}</strong>
                </div>
                <div class="v-stat-item">
                    <span class="v-stat-title">{{ $t('active_cost_layers') }}</span>
                    <strong class="v-stat-num">{{ formatNumber(stats.active_layers_count) }}</strong>
                </div>
                <div class="v-stat-item">
                    <span class="v-stat-title">{{ $t('warehouses') }}</span>
                    <strong class="v-stat-num">{{ formatNumber(stats.warehouses_count) }}</strong>
                </div>
                <div class="v-stat-item highlight">
                    <span class="v-stat-title">{{ $t('active_method') }}</span>
                    <strong class="v-stat-num">{{ activeMethodLabel }}</strong>
                </div>
            </div>

            <!-- Section 1: Choose Global Method -->
            <div class="section-block">
                <div class="section-heading">
                    <h5><el-icon><Cpu /></el-icon> {{ $t('default_cost_method') }}</h5>
                    <span class="section-hint">{{ $t('default_cost_method_hint') }}</span>
                </div>

                <div class="methods-grid">
                    <div
                        v-for="method in availableMethods"
                        :key="method.id"
                        class="method-card"
                        :class="{ 'is-selected': selectedMethod === method.id }"
                        role="button"
                        tabindex="0"
                        @click="selectedMethod = method.id"
                        @keydown.enter.prevent="selectedMethod = method.id"
                    >
                        <div class="card-top">
                            <div class="card-radio">
                                <span class="radio-circle" :class="{ checked: selectedMethod === method.id }" />
                                <strong class="card-title">{{ isAr ? method.name_ar : method.name_en }}</strong>
                            </div>
                            <el-tag v-if="method.recommended" size="small" type="success" effect="light">
                                {{ $t('recommended') }}
                            </el-tag>
                            <el-tag v-else-if="method.id === 'WEIGHTED_AVERAGE'" size="small" type="primary" effect="plain">
                                AVCO / WAC
                            </el-tag>
                        </div>

                        <p class="card-desc">{{ isAr ? method.description_ar : method.description_en }}</p>

                        <div class="card-formula">
                            <span class="formula-label">{{ $t('formula') }}:</span>
                            <span class="formula-text">{{ isAr ? method.formula_ar : method.formula_en }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Per-Warehouse Customization -->
            <div class="section-block mt-4">
                <div class="section-heading flex-between">
                    <div>
                        <h5><el-icon><OfficeBuilding /></el-icon> {{ $t('warehouse_cost_methods') }}</h5>
                        <span class="section-hint">{{ $t('warehouse_cost_methods_hint') }}</span>
                    </div>
                    <el-button link type="primary" size="small" @click="resetAllWarehousesToDefault">
                        {{ $t('reset_all_to_default') }}
                    </el-button>
                </div>

                <el-table :data="warehouses" stripe style="width: 100%" class="warehouses-table" size="small">
                    <el-table-column :label="$t('warehouse')" min-width="170">
                        <template #default="{ row }">
                            <strong>{{ row.name }}</strong>
                            <span v-if="row.code" class="table-sub" dir="ltr">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('quantity')" width="110" align="center">
                        <template #default="{ row }">
                            {{ formatNumber(row.total_quantity) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('value')" width="130" align="center">
                        <template #default="{ row }">
                            {{ formatMoney(row.total_value) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('cost_calculation_method')" min-width="190">
                        <template #default="{ row }">
                            <el-select
                                v-model="warehouseMethods[row.id]"
                                size="small"
                                style="width: 100%"
                                :placeholder="$t('use_default')"
                            >
                                <el-option :label="$t('use_system_default', { method: activeMethodLabel })" value="DEFAULT" />
                                <el-option
                                    v-for="m in availableMethods"
                                    :key="m.id"
                                    :label="isAr ? m.name_ar : m.name_en"
                                    :value="m.id"
                                />
                            </el-select>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <!-- Section 3: Options -->
            <div class="section-block mt-4">
                <el-card shadow="never" class="options-card">
                    <el-checkbox v-model="applyToExisting">
                        <div class="checkbox-label-block">
                            <strong>{{ $t('apply_cost_method_to_existing_stock') }}</strong>
                            <small class="text-muted d-block">{{ $t('apply_cost_method_to_existing_stock_hint') }}</small>
                        </div>
                    </el-checkbox>
                </el-card>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :icon="Check" @click="saveSettings">
                    {{ $t('save_cost_method_settings') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Operation, Cpu, OfficeBuilding, Check } from '@element-plus/icons-vue';
import { inventoryApi } from '@/api/inventory';
import { formatMoney as formatBaseMoney, formatNumber as formatCount } from '@/utils/currency';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const { t, locale } = useI18n();

const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

const isAr = computed(() => locale.value === 'ar');
const loading = ref(false);
const saving = ref(false);

const selectedMethod = ref('FIFO');
const availableMethods = ref([]);
const warehouseMethods = reactive({});
const warehouses = ref([]);
const stats = ref(null);
const applyToExisting = ref(true);

const formatMoney = (n) => formatBaseMoney(n);
const formatNumber = (n) => formatCount(n);

const activeMethodLabel = computed(() => {
    const found = availableMethods.value.find((m) => m.id === selectedMethod.value);
    if (!found) return selectedMethod.value;
    return isAr.value ? found.name_ar : found.name_en;
});

const fetchConfig = async () => {
    loading.value = true;
    try {
        const res = await inventoryApi.getCostingMethod();
        const data = res.data?.data || {};

        selectedMethod.value = data.current_method || 'FIFO';
        availableMethods.value = data.available_methods || [];
        warehouses.value = data.warehouses || [];
        stats.value = data.stats || null;

        // Initialize per-warehouse selections
        const savedWarehouseMethods = data.warehouse_methods || {};
        warehouses.value.forEach((w) => {
            warehouseMethods[w.id] = savedWarehouseMethods[w.id] || 'DEFAULT';
        });
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('failed_to_load_cost_method_config'));
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            fetchConfig();
        }
    }
);

const resetAllWarehousesToDefault = () => {
    warehouses.value.forEach((w) => {
        warehouseMethods[w.id] = 'DEFAULT';
    });
    ElMessage.info(t('all_warehouses_set_to_default'));
};

const saveSettings = async () => {
    saving.value = true;
    try {
        const payload = {
            method: selectedMethod.value,
            warehouse_methods: { ...warehouseMethods },
            apply_to_existing: applyToExisting.value,
        };

        const res = await inventoryApi.updateCostingMethod(payload);
        ElMessage.success(res.data?.message || t('cost_method_updated_successfully'));
        visible.value = false;
        emit('saved', res.data?.data);
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('failed_to_update_cost_method'));
    } finally {
        saving.value = false;
    }
};
</script>

<style scoped>
.cost-method-dialog :deep(.el-dialog__body) {
    padding: 16px 24px;
}

.dialog-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(124, 58, 237, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}

.banner-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: #4f46e5;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.banner-text {
    flex: 1;
}

.banner-text h4 {
    margin: 0 0 4px;
    font-size: 15px;
    font-weight: 700;
    color: #1f2937;
}

.banner-text p {
    margin: 0;
    font-size: 13px;
    color: #4b5563;
    line-height: 1.4;
}

.banner-badge {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 14px;
    text-align: right;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.badge-label {
    display: block;
    font-size: 11px;
    color: #6b7280;
}

.badge-value {
    display: block;
    font-size: 15px;
    font-weight: 700;
    color: #059669;
}

.valuation-stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 20px;
}

.v-stat-item {
    display: flex;
    flex-direction: column;
}

.v-stat-title {
    font-size: 11px;
    color: #6b7280;
}

.v-stat-num {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}

.v-stat-item.highlight .v-stat-num {
    color: #4f46e5;
}

.section-block {
    margin-bottom: 20px;
}

.section-heading {
    margin-bottom: 12px;
}

.section-heading.flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-heading h5 {
    margin: 0 0 2px;
    font-size: 14px;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 6px;
}

.section-hint {
    font-size: 12px;
    color: #6b7280;
}

.methods-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.method-card {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px;
    cursor: pointer;
    background: #fff;
    transition: all 0.18s ease;
    outline: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.method-card:hover {
    border-color: #a5b4fc;
    background: #fcfdff;
}

.method-card.is-selected {
    border-color: #4f46e5;
    background: rgba(79, 70, 229, 0.03);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.card-radio {
    display: flex;
    align-items: center;
    gap: 8px;
}

.radio-circle {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #d1d5db;
    position: relative;
    transition: all 0.18s ease;
}

.radio-circle.checked {
    border-color: #4f46e5;
    background: #4f46e5;
}

.radio-circle.checked::after {
    content: '';
    position: absolute;
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.card-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #1f2937;
}

.card-desc {
    margin: 0 0 10px;
    font-size: 12px;
    color: #4b5563;
    line-height: 1.45;
}

.card-formula {
    background: #f3f4f6;
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 11px;
    display: flex;
    gap: 6px;
}

.formula-label {
    font-weight: 700;
    color: #374151;
}

.formula-text {
    color: #6b7280;
    font-family: inherit;
}

.options-card {
    background: #fafafa;
    border-radius: 8px;
}

.checkbox-label-block {
    margin-inline-start: 6px;
    text-align: right;
}

.checkbox-label-block strong {
    font-size: 13px;
    color: #111827;
}

.checkbox-label-block small {
    font-size: 11.5px;
    color: #6b7280;
    margin-top: 2px;
}

.table-sub {
    font-size: 11px;
    color: #9ca3af;
    display: block;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

@media (max-width: 640px) {
    .methods-grid {
        grid-template-columns: 1fr;
    }
    .valuation-stats-bar {
        grid-template-columns: repeat(2, 1fr);
    }
    .dialog-banner {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
