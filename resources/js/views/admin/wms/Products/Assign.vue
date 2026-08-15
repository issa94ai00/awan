<!-- resources/js/views/admin/wms/Products/Assign.vue -->
<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

const { t } = useI18n();

const router = useRouter();
const route = useRoute();

// State management
const loading = ref(true);
const submitting = ref(false);
const suggesting = ref(false);
const currentStep = ref(1);
const totalSteps = 3;
const error = ref(null);

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
    lead_time_days: 1,
    primary_bin_code: '',
    storage_zone: '',
    source_warehouse_id: null,
});

const errors = ref({});

// التحقق من صحة الخطوة الحالية مع التحقق من البيانات
const isStepValid = computed(() => {
    switch (currentStep.value) {
        case 1:
            return form.value.product_id && form.value.warehouse_id;
        case 2:
            const minStock = Number(form.value.min_stock) || 0;
            const maxStock = Number(form.value.max_stock) || 0;
            const safetyStock = Number(form.value.safety_stock) || 0;
            const leadTime = Number(form.value.lead_time_days) || 0;
            
            return minStock >= 0 && maxStock > minStock && safetyStock >= 0 && leadTime > 0;
        case 3:
            return true;
        default:
            return false;
    }
});

// جلب البيانات مع معالجة أخطاء محسنة
async function fetchData() {
    loading.value = true;
    error.value = null;
    
    try {
        const [productsRes, warehousesRes, suppliersRes, binsRes] = await Promise.all([
            axios.get('/api/v1/products'),
            axios.get('/api/v1/admin/wms/warehouses'),
            axios.get('/api/v1/suppliers'),
            axios.get('/api/v1/admin/wms/bins'),
        ]);

        // التحقق من صحة البيانات
        if (!productsRes.data || !Array.isArray(productsRes.data.data)) {
            throw new Error('Invalid products data format');
        }
        
        if (!warehousesRes.data || !Array.isArray(warehousesRes.data.data)) {
            throw new Error('Invalid warehouses data format');
        }

        products.value = productsRes.data.data;
        warehouses.value = warehousesRes.data.data;
        suppliers.value = suppliersRes.data?.data || [];
        bins.value = binsRes.data?.data || [];

        // إذا كان هناك product_id في params
        if (route.params.id) {
            const productId = parseInt(route.params.id);
            if (!isNaN(productId)) {
                form.value.product_id = productId;
                try {
                    const productRes = await axios.get(`/api/v1/products/${productId}`);
                    if (productRes.data?.data) {
                        const product = productRes.data.data;
                    }
                } catch (err) {
                    console.error('Error fetching product details:', err);
                }
            }
        }
    } catch (err) {
        console.error('Error fetching data:', err);
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_data_short');
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchData();
});

// التنقل بين الخطوات مع التحقق
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
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

// اقتراح تلقائي للقيم مع معالجة أخطاء
async function suggestStockLevels() {
    if (!form.value.product_id || !form.value.warehouse_id) {
        ElMessage.warning(t('choose_product_and_warehouse_first'));
        return;
    }
    
    suggesting.value = true;
    try {
        const response = await axios.get(`/api/v1/admin/wms/suggest-stock-levels`, {
            params: {
                product_id: form.value.product_id,
                warehouse_id: form.value.warehouse_id,
            }
        });
        
        if (!response.data) {
            throw new Error('Invalid response from server');
        }
        
        form.value.min_stock = response.data.min_stock || 0;
        form.value.max_stock = response.data.max_stock || 0;
        form.value.safety_stock = response.data.safety_stock || 0;
        
        ElMessage.success(t('values_suggested'));
    } catch (err) {
        console.error('Error suggesting stock levels:', err);
        ElMessage.error(t('failed_to_suggest_values'));
    } finally {
        suggesting.value = false;
    }
}

// إرسال النموذج مع معالجة أخطاء محسنة
async function submit() {
    submitting.value = true;
    errors.value = {};
    
    try {
        await ElMessageBox.confirm(
            t('confirm_save_assignment'),
            t('confirm_save_title'),
            {
                confirmButtonText: t('yes_save'),
                cancelButtonText: t('cancel'),
                type: 'warning',
            }
        );

        const response = await axios.post('/api/v1/admin/wms/assignments', form.value);
        
        if (!response.data) {
            throw new Error('Invalid response from server');
        }
        
        ElMessage.success(t('assignment_saved'));
        router.push('/admin/wms/products');
    } catch (err) {
        if (err !== 'cancel') {
            console.error('Error submitting form:', err);
            
            if (err.response?.data?.errors) {
                errors.value = err.response.data.errors;
                ElMessage.error(t('form_has_errors_short'));
            } else {
                const errorMsg = err.response?.data?.message || err.message || t('failed_to_save');
                ElMessage.error(errorMsg);
            }
        }
    } finally {
        submitting.value = false;
    }
}

function handleCancel() {
    ElMessageBox.confirm(
        t('confirm_cancel_operation'),
        t('confirm_cancel_title'),
        {
            confirmButtonText: t('yes_cancel'),
            cancelButtonText: t('no_continue'),
            type: 'warning',
        }
    ).then(() => {
        router.push('/admin/wms/products');
    }).catch(() => {
        // User cancelled
    });
}

// الحصول على اسم المنتج مع التحقق
function getProductName(productId) {
    if (!productId || !Array.isArray(products.value)) return '';
    const product = products.value.find(p => p.id === productId);
    return product ? `${product.code || 'N/A'} - ${product.name}` : '';
}

// الحصول على اسم المستودع مع التحقق
function getWarehouseName(warehouseId) {
    if (!warehouseId || !Array.isArray(warehouses.value)) return '';
    const warehouse = warehouses.value.find(w => w.id === warehouseId);
    return warehouse ? `${warehouse.name} (${warehouse.code || 'N/A'})` : '';
}

// تنسيق الأرقام
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return formatCount(num);
}
</script>

<template>
    <div class="max-w-5xl mx-auto p-6">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <div class="flex flex-col items-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                <p class="mt-4 text-gray-600">{{ $t('loading_data') }}</p>
            </div>
        </div>
        
        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center gap-3">
                <span class="text-2xl">❌</span>
                <div>
                    <p class="font-medium text-red-800">{{ $t('failed_to_fetch_data') }}</p>
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>
                <button @click="fetchData" class="mr-auto text-red-600 hover:text-red-700 text-sm font-medium">
                    {{ $t('retry') }}
                </button>
            </div>
        </div>
        
        <div v-else>
            <!-- Header -->
            <div class="mb-6">
                <button 
                    @click="handleCancel"
                    class="text-blue-600 hover:text-blue-700 font-medium flex items-center gap-2 transition-colors"
                >
                    {{ $t('back_to_products') }}
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-2xl font-bold mb-2 text-gray-900">{{ $t('link_product_to_warehouse') }}</h1>
                <p class="text-gray-600 mb-8">
                    {{ $t('assign_wizard_subtitle') }}
                </p>

                <!-- Progress Bar -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div v-for="step in totalSteps" :key="step" class="flex items-center">
                            <div :class="[
                                'w-12 h-12 rounded-full flex items-center justify-center font-bold text-sm transition-all',
                                currentStep >= step ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-200 text-gray-600'
                            ]">
                                {{ step }}
                            </div>
                            <div v-if="step < totalSteps" :class="[
                                'w-32 h-1 mx-2 transition-all',
                                currentStep > step ? 'bg-blue-600' : 'bg-gray-200'
                            ]"></div>
                        </div>
                    </div>
                    <div class="flex justify-between mt-2 text-sm text-gray-600">
                        <span>{{ $t('choose_product_and_warehouse_step') }}</span>
                        <span>{{ $t('planning_data') }}</span>
                        <span>{{ $t('exact_locations') }}</span>
                    </div>
                </div>

                <!-- Step 1: Product & Warehouse Selection -->
                <div v-if="currentStep === 1" class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900">{{ $t('step_1_choose_product_warehouse') }}</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('product') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.product_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">{{ $t('choose_product_placeholder') }}</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.code || 'N/A' }} - {{ product.name }}
                                </option>
                            </select>
                            <div v-if="errors.product_id" class="text-red-600 text-sm mt-1">
                                {{ errors.product_id }}
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('warehouse') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.warehouse_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">{{ $t('choose_warehouse_placeholder') }}</option>
                                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                                    {{ warehouse.name }} ({{ warehouse.code || 'N/A' }})
                                </option>
                            </select>
                            <div v-if="errors.warehouse_id" class="text-red-600 text-sm mt-1">
                                {{ errors.warehouse_id }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Planning Data -->
                <div v-if="currentStep === 2" class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900">{{ $t('step_2_planning_data') }}</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('supply_method') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.replenishment_method"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option value="purchase">{{ $t('purchase_supply') }}</option>
                                <option value="manufacturing">{{ $t('manufacture_supply') }}</option>
                                <option value="internal_transfer">{{ $t('internal_transfer') }}</option>
                            </select>
                            <div v-if="errors.replenishment_method" class="text-red-600 text-sm mt-1">
                                {{ errors.replenishment_method }}
                            </div>
                        </div>

                        <div v-if="form.replenishment_method === 'internal_transfer'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('source_warehouse') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.source_warehouse_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">{{ $t('choose_source_warehouse') }}</option>
                                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id" v-if="wh.id !== form.warehouse_id">
                                    {{ wh.name }}
                                </option>
                            </select>
                            <div v-if="errors.source_warehouse_id" class="text-red-600 text-sm mt-1">
                                {{ errors.source_warehouse_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('planning_method') }} <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.planning_method"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option value="rop">{{ $t('planning_rop') }}</option>
                                <option value="mrp">{{ $t('planning_mrp') }}</option>
                            </select>
                            <div v-if="errors.planning_method" class="text-red-600 text-sm mt-1">
                                {{ errors.planning_method }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('lead_time_days') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model.number="form.lead_time_days"
                                type="number"
                                min="1"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                            <div v-if="errors.lead_time_days" class="text-red-600 text-sm mt-1">
                                {{ errors.lead_time_days }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('minimum') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model.number="form.min_stock"
                                type="number"
                                min="0"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                            <div v-if="errors.min_stock" class="text-red-600 text-sm mt-1">
                                {{ errors.min_stock }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('maximum') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model.number="form.max_stock"
                                type="number"
                                min="0"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                            <div v-if="errors.max_stock" class="text-red-600 text-sm mt-1">
                                {{ errors.max_stock }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('safety_stock') }} <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model.number="form.safety_stock"
                                type="number"
                                min="0"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                            <div v-if="errors.safety_stock" class="text-red-600 text-sm mt-1">
                                {{ errors.safety_stock }}
                            </div>
                        </div>

                        <div v-if="form.replenishment_method === 'purchase'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $t('primary_supplier') }}
                            </label>
                            <select 
                                v-model="form.supplier_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">{{ $t('choose_supplier') }}</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <button 
                        type="button"
                        @click="suggestStockLevels"
                        :disabled="suggesting"
                        class="text-blue-600 hover:text-blue-700 font-medium flex items-center gap-2 transition-colors disabled:opacity-50"
                    >
                        <span v-if="suggesting" class="animate-spin">⟳</span>
                        <span v-else>🔄</span> 
                        {{ suggesting ? $t('suggesting_now') : $t('auto_suggest_from_history') }}
                    </button>
                </div>

                <!-- Step 3: Exact Locations -->
                <div v-if="currentStep === 3" class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900">{{ $t('step_3_exact_locations') }}</h2>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('primary_bin') }}
                        </label>
                        <input 
                            v-model="form.primary_bin_code"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            :placeholder="$t('example_bin_code')"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $t('auto_suggestion_label') }} WH-{{ form.warehouse_id }}-Aisle{{ Math.floor(Math.random() * 10) }}
                        </p>
                        <div v-if="errors.primary_bin_code" class="text-red-600 text-sm mt-1">
                            {{ errors.primary_bin_code }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('storage_zone') }}
                        </label>
                        <input 
                            v-model="form.storage_zone"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            :placeholder="$t('example_storage_zone')"
                        />
                        <div v-if="errors.storage_zone" class="text-red-600 text-sm mt-1">
                            {{ errors.storage_zone }}
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h3 class="font-medium text-blue-800 mb-2">{{ $t('assignment_summary') }}</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>{{ $t('product_label') }}</strong> {{ getProductName(form.product_id) }}</p>
                            <p><strong>{{ $t('warehouse_label') }}</strong> {{ getWarehouseName(form.warehouse_id) }}</p>
                            <p><strong>{{ $t('supply_method_label') }}</strong> {{ form.replenishment_method }}</p>
                            <p><strong>{{ $t('minimum_label') }}</strong> {{ formatNumber(form.min_stock) }}</p>
                            <p><strong>{{ $t('maximum_label') }}</strong> {{ formatNumber(form.max_stock) }}</p>
                            <p><strong>{{ $t('safety_stock_label') }}</strong> {{ formatNumber(form.safety_stock) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between mt-8">
                    <button 
                        type="button" 
                        @click="previousStep" 
                        v-if="currentStep > 1" 
                        class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                    >
                        {{ $t('previous') }}
                    </button>
                    
                    <button 
                        type="button" 
                        @click="nextStep" 
                        v-if="currentStep < totalSteps" 
                        :disabled="!isStepValid"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ $t('next') }}
                    </button>
                    
                    <button 
                        type="button"
                        @click="submit" 
                        v-if="currentStep === totalSteps" 
                        :disabled="submitting || !isStepValid"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                    >
                        <span v-if="submitting" class="animate-spin">⟳</span>
                        {{ submitting ? $t('saving_now') : $t('save_assignment') }}
                    </button>
                </div>

                <!-- Form Errors Display -->
                <div v-if="Object.keys(errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-600 font-bold mb-2">{{ $t('form_has_errors') }}</p>
                    <ul class="list-disc list-inside text-red-600 text-sm">
                        <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
