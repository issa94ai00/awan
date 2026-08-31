<!-- resources/js/views/admin/wms/Products/Assign.vue -->
<template>
    <div class="assign-page">
        <AdminPageHeader icon="fas fa-link" :title="$t('link_product_to_warehouse')" :subtitle="$t('assign_wizard_subtitle')" />

        <div v-if="loading" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <el-alert v-else-if="error" type="error" :title="error" show-icon :closable="false">
            <el-button size="small" @click="fetchData">{{ $t('retry') }}</el-button>
        </el-alert>

        <el-card v-else shadow="never" class="wizard-card">
            <el-steps :active="currentStep - 1" finish-status="success" align-center class="mb-4">
                <el-step :title="$t('choose_product_and_warehouse_step')" />
                <el-step :title="$t('planning_data')" />
                <el-step :title="$t('exact_locations')" />
            </el-steps>

            <!-- Step 1 -->
            <el-form v-if="currentStep === 1" :model="form" label-position="top" class="step-form">
                <el-form-item :label="$t('product')" required>
                    <el-select v-model="form.product_id" filterable :placeholder="$t('choose_product_placeholder')" style="width: 100%">
                        <el-option v-for="product in products" :key="product.id" :value="product.id" :label="`${product.code || 'N/A'} - ${product.name}`" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('warehouse')" required>
                    <el-select v-model="form.warehouse_id" :placeholder="$t('choose_warehouse_placeholder')" style="width: 100%">
                        <el-option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id" :label="`${warehouse.name} (${warehouse.code || 'N/A'})`" />
                    </el-select>
                </el-form-item>
            </el-form>

            <!-- Step 2 -->
            <el-form v-if="currentStep === 2" :model="form" label-position="top" class="step-form">
                <el-form-item :label="$t('supply_method')" required>
                    <el-select v-model="form.replenishment_method" style="width: 100%">
                        <el-option value="purchase" :label="$t('purchase_supply')" />
                        <el-option value="manufacturing" :label="$t('manufacture_supply')" />
                        <el-option value="internal_transfer" :label="$t('internal_transfer')" />
                    </el-select>
                </el-form-item>

                <el-form-item v-if="form.replenishment_method === 'internal_transfer'" :label="$t('source_warehouse')" required>
                    <el-select v-model="form.source_warehouse_id" :placeholder="$t('choose_source_warehouse')" style="width: 100%">
                        <el-option
                            v-for="wh in warehouses.filter(w => w.id !== form.warehouse_id)"
                            :key="wh.id" :value="wh.id" :label="wh.name"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('planning_method')" required>
                    <el-select v-model="form.planning_method" style="width: 100%">
                        <el-option value="rop" :label="$t('planning_rop')" />
                        <el-option value="mrp" :label="$t('planning_mrp')" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('lead_time_days')" required>
                    <el-input-number v-model="form.lead_time_days" :min="1" style="width: 100%" />
                </el-form-item>

                <div class="grid-3">
                    <el-form-item :label="$t('minimum')" required>
                        <el-input-number v-model="form.min_stock" :min="0" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('maximum')" required>
                        <el-input-number v-model="form.max_stock" :min="0" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('safety_stock')" required>
                        <el-input-number v-model="form.safety_stock" :min="0" style="width: 100%" />
                    </el-form-item>
                </div>

                <el-form-item v-if="form.replenishment_method === 'purchase'" :label="$t('primary_supplier')">
                    <el-select v-model="form.supplier_id" clearable :placeholder="$t('choose_supplier')" style="width: 100%">
                        <el-option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id" :label="supplier.name" />
                    </el-select>
                </el-form-item>

                <el-button plain :loading="suggesting" @click="suggestStockLevels">
                    <i class="fas fa-wand-magic-sparkles"></i> {{ suggesting ? $t('suggesting_now') : $t('auto_suggest_from_history') }}
                </el-button>
                <p v-if="suggestionBasis" class="suggestion-note">{{ suggestionBasis }}</p>
            </el-form>

            <!-- Step 3 -->
            <el-form v-if="currentStep === 3" :model="form" label-position="top" class="step-form">
                <el-form-item :label="$t('primary_bin')">
                    <el-select v-model="form.primary_bin_code" clearable filterable allow-create :placeholder="$t('example_bin_code')" style="width: 100%">
                        <el-option v-for="bin in bins" :key="bin.id" :value="bin.code || bin.bin_code" :label="bin.code || bin.bin_code" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('storage_zone')">
                    <el-input v-model="form.storage_zone" :placeholder="$t('example_storage_zone')" />
                </el-form-item>

                <el-card shadow="never" class="summary-card">
                    <template #header>{{ $t('assignment_summary') }}</template>
                    <p><strong>{{ $t('product_label') }}</strong> {{ getProductName(form.product_id) }}</p>
                    <p><strong>{{ $t('warehouse_label') }}</strong> {{ getWarehouseName(form.warehouse_id) }}</p>
                    <p><strong>{{ $t('supply_method_label') }}</strong> {{ form.replenishment_method }}</p>
                    <p><strong>{{ $t('minimum_label') }}</strong> {{ formatNumber(form.min_stock) }}</p>
                    <p><strong>{{ $t('maximum_label') }}</strong> {{ formatNumber(form.max_stock) }}</p>
                    <p><strong>{{ $t('safety_stock_label') }}</strong> {{ formatNumber(form.safety_stock) }}</p>
                </el-card>
            </el-form>

            <div class="wizard-actions">
                <el-button v-if="currentStep > 1" @click="previousStep">{{ $t('previous') }}</el-button>
                <el-button v-if="currentStep < totalSteps" type="primary" :disabled="!isStepValid" @click="nextStep">{{ $t('next') }}</el-button>
                <el-button v-if="currentStep === totalSteps" type="success" :loading="submitting" :disabled="!isStepValid" @click="submit">
                    {{ submitting ? $t('saving_now') : $t('save_assignment') }}
                </el-button>
                <el-button plain @click="handleCancel">{{ $t('cancel') }}</el-button>
            </div>

            <el-alert v-if="Object.keys(errors).length" type="error" show-icon :closable="false" class="mt-3" :title="$t('form_has_errors')">
                <ul class="error-list">
                    <li v-for="(err, field) in errors" :key="field">{{ err }}</li>
                </ul>
            </el-alert>
        </el-card>
    </div>
</template>

<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api';
import { wmsService } from '@/services/wms';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();

const loading = ref(true);
const submitting = ref(false);
const suggesting = ref(false);
const currentStep = ref(1);
const totalSteps = 3;
const error = ref(null);
const suggestionBasis = ref('');

const products = ref([]);
const warehouses = ref([]);
const suppliers = ref([]);
const bins = ref([]);

const form = ref({
    product_id: null,
    warehouse_id: null,
    replenishment_method: 'purchase',
    planning_method: 'mrp',
    min_stock: 0,
    max_stock: 0,
    safety_stock: 0,
    supplier_id: null,
    lead_time_days: 7,
    primary_bin_code: '',
    storage_zone: '',
    source_warehouse_id: null,
});

const errors = ref({});

const isStepValid = computed(() => {
    switch (currentStep.value) {
        case 1:
            return !!(form.value.product_id && form.value.warehouse_id);
        case 2: {
            const minStock = Number(form.value.min_stock) || 0;
            const maxStock = Number(form.value.max_stock) || 0;
            const safetyStock = Number(form.value.safety_stock) || 0;
            const leadTime = Number(form.value.lead_time_days) || 0;
            return minStock >= 0 && maxStock > minStock && safetyStock >= 0 && leadTime > 0;
        }
        case 3:
            return true;
        default:
            return false;
    }
});

async function fetchData() {
    loading.value = true;
    error.value = null;

    try {
        const [productsRes, warehousesRes, suppliersRes, binsRes] = await Promise.all([
            api.get('/products', { params: { per_page: 500 } }),
            wmsService.getWarehouses(),
            api.get('/admin/suppliers'),
            wmsService.getBins(),
        ]);

        products.value = productsRes.data?.data || [];
        warehouses.value = warehousesRes.data?.data || warehousesRes.data || [];
        suppliers.value = suppliersRes.data?.data || [];
        bins.value = binsRes.data?.data || [];

        if (route.params.id) {
            const productId = parseInt(route.params.id);
            if (!isNaN(productId)) form.value.product_id = productId;
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_data_short');
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

onMounted(fetchData);

function nextStep() {
    if (currentStep.value < totalSteps) {
        if (!isStepValid.value) {
            ElMessage.warning(t('please_complete_required_fields'));
            return;
        }
        currentStep.value++;
    }
}

function previousStep() {
    if (currentStep.value > 1) currentStep.value--;
}

async function suggestStockLevels() {
    if (!form.value.product_id || !form.value.warehouse_id) {
        ElMessage.warning(t('choose_product_and_warehouse_first'));
        return;
    }

    suggesting.value = true;
    try {
        const response = await wmsService.suggestStockLevels({
            product_id: form.value.product_id,
            warehouse_id: form.value.warehouse_id,
            lead_time_days: form.value.lead_time_days,
        });

        form.value.min_stock = response.data.min_stock || 0;
        form.value.max_stock = response.data.max_stock || 0;
        form.value.safety_stock = response.data.safety_stock || 0;

        // The suggestion is only as good as the history behind it — say so,
        // instead of presenting a number as if it came from nowhere.
        suggestionBasis.value = response.data.total_consumed > 0
            ? t('suggestion_based_on_history', { days: response.data.based_on_days, avg: response.data.avg_daily_consumption })
            : t('suggestion_no_history', { days: response.data.based_on_days });

        ElMessage.success(t('values_suggested'));
    } catch {
        ElMessage.error(t('failed_to_suggest_values'));
    } finally {
        suggesting.value = false;
    }
}

async function submit() {
    submitting.value = true;
    errors.value = {};

    try {
        await ElMessageBox.confirm(t('confirm_save_assignment'), t('confirm_save_title'), {
            confirmButtonText: t('yes_save'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        });
    } catch {
        submitting.value = false;
        return;
    }

    try {
        await wmsService.createAssignment(form.value);
        ElMessage.success(t('assignment_saved'));
        router.push('/admin/wms/products');
    } catch (err) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
            ElMessage.error(t('form_has_errors_short'));
        } else {
            ElMessage.error(err.response?.data?.message || t('failed_to_save'));
        }
    } finally {
        submitting.value = false;
    }
}

function handleCancel() {
    ElMessageBox.confirm(t('confirm_cancel_operation'), t('confirm_cancel_title'), {
        confirmButtonText: t('yes_cancel'),
        cancelButtonText: t('no_continue'),
        type: 'warning',
    }).then(() => {
        router.push('/admin/wms/products');
    }).catch(() => {});
}

function getProductName(productId) {
    const product = products.value.find((p) => p.id === productId);
    return product ? `${product.code || 'N/A'} - ${product.name}` : '';
}

function getWarehouseName(warehouseId) {
    const warehouse = warehouses.value.find((w) => w.id === warehouseId);
    return warehouse ? `${warehouse.name} (${warehouse.code || 'N/A'})` : '';
}

function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return formatCount(num);
}
</script>

<style scoped>
.assign-page { font-family: 'Cairo', sans-serif; }
.wizard-card { border-radius: 12px; max-width: 760px; margin: 0 auto; }
.step-form { max-width: 560px; margin: 0 auto; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.suggestion-note { margin: 0.5rem 0 0; font-size: 0.8rem; color: var(--text-muted); }
.summary-card { background: var(--el-fill-color-light); }
.summary-card p { margin: 0.3rem 0; font-size: 0.88rem; }
.wizard-actions { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 1.5rem; }
.error-list { margin: 0.4rem 0 0; padding-inline-start: 1.2rem; font-size: 0.85rem; }
.loading-state { padding: 2rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
</style>
