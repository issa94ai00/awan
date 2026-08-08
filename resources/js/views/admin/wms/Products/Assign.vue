<!-- resources/js/views/admin/wms/Products/Assign.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

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
        error.value = err.response?.data?.message || err.message || 'فشل في جلب البيانات';
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
            ElMessage.warning('يرجى إكمال جميع الحقول المطلوبة بشكل صحيح');
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
        ElMessage.warning('يرجى اختيار المنتج والمستودع أولاً');
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
        
        ElMessage.success('تم اقتراح القيم بنجاح');
    } catch (err) {
        console.error('Error suggesting stock levels:', err);
        ElMessage.error('فشل في اقتراح القيم');
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
            'هل أنت متأكد من حفظ هذا الربط؟',
            'تأكيد الحفظ',
            {
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء',
                type: 'warning',
            }
        );

        const response = await axios.post('/api/v1/admin/wms/assignments', form.value);
        
        if (!response.data) {
            throw new Error('Invalid response from server');
        }
        
        ElMessage.success('تم حفظ الربط بنجاح');
        router.push('/admin/wms/products');
    } catch (err) {
        if (err !== 'cancel') {
            console.error('Error submitting form:', err);
            
            if (err.response?.data?.errors) {
                errors.value = err.response.data.errors;
                ElMessage.error('يوجد أخطاء في النموذج');
            } else {
                const errorMsg = err.response?.data?.message || err.message || 'فشل في الحفظ';
                ElMessage.error(errorMsg);
            }
        }
    } finally {
        submitting.value = false;
    }
}

function handleCancel() {
    ElMessageBox.confirm(
        'هل أنت متأكد من إلغاء العملية؟ سيتم فقدان البيانات غير المحفوظة.',
        'تأكيد الإلغاء',
        {
            confirmButtonText: 'نعم، ألغِ',
            cancelButtonText: 'لا، استمر',
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
    return Number(num).toLocaleString('ar-SA');
}
</script>

<template>
    <div class="max-w-5xl mx-auto p-6">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <div class="flex flex-col items-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                <p class="mt-4 text-gray-600">جاري تحميل البيانات...</p>
            </div>
        </div>
        
        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center gap-3">
                <span class="text-2xl">❌</span>
                <div>
                    <p class="font-medium text-red-800">خطأ في جلب البيانات</p>
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>
                <button @click="fetchData" class="mr-auto text-red-600 hover:text-red-700 text-sm font-medium">
                    إعادة المحاولة
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
                    ← العودة للمنتجات
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-2xl font-bold mb-2 text-gray-900">ربط المنتج بالمستودع</h1>
                <p class="text-gray-600 mb-8">
                    اتبع الخطوات الثلاث لإعداد ربط المنتج بالمستودع مع بيانات التخطيط
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
                        <span>اختيار المنتج والمستودع</span>
                        <span>بيانات التخطيط</span>
                        <span>المواقع الدقيقة</span>
                    </div>
                </div>

                <!-- Step 1: Product & Warehouse Selection -->
                <div v-if="currentStep === 1" class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900">الخطوة 1: اختيار المنتج والمستودع</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                المنتج <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.product_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">اختر المنتج...</option>
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
                                المستودع <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.warehouse_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">اختر المستودع...</option>
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
                    <h2 class="text-xl font-bold text-gray-900">الخطوة 2: بيانات التخطيط</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                طريقة التزويد <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.replenishment_method"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option value="purchase">شراء</option>
                                <option value="manufacturing">تصنيع</option>
                                <option value="internal_transfer">نقل داخلي</option>
                            </select>
                            <div v-if="errors.replenishment_method" class="text-red-600 text-sm mt-1">
                                {{ errors.replenishment_method }}
                            </div>
                        </div>

                        <div v-if="form.replenishment_method === 'internal_transfer'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                المستودع المصدر <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.source_warehouse_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">اختر المستودع المصدر...</option>
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
                                طريقة التخطيط <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.planning_method"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option value="rop">ROP (نقطة إعادة الطلب)</option>
                                <option value="mrp">MRP (تخطيط متطلبات المواد)</option>
                            </select>
                            <div v-if="errors.planning_method" class="text-red-600 text-sm mt-1">
                                {{ errors.planning_method }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                المهلة الزمنية (أيام) <span class="text-red-500">*</span>
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
                                الحد الأدنى <span class="text-red-500">*</span>
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
                                الحد الأقصى <span class="text-red-500">*</span>
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
                                مخزون الأمان <span class="text-red-500">*</span>
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
                                المورد الأساسي
                            </label>
                            <select 
                                v-model="form.supplier_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">اختر المورد...</option>
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
                        {{ suggesting ? 'جاري الاقتراح...' : 'اقتراح تلقائي بناءً على الاستهلاك السابق' }}
                    </button>
                </div>

                <!-- Step 3: Exact Locations -->
                <div v-if="currentStep === 3" class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900">الخطوة 3: المواقع الدقيقة</h2>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            الموقع الرئيسي (Primary Bin)
                        </label>
                        <input 
                            v-model="form.primary_bin_code"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            placeholder="مثال: RUH-A3-12"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            الاقتراح التلقائي: WH-{{ form.warehouse_id }}-Aisle{{ Math.floor(Math.random() * 10) }}
                        </p>
                        <div v-if="errors.primary_bin_code" class="text-red-600 text-sm mt-1">
                            {{ errors.primary_bin_code }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            منطقة التخزين
                        </label>
                        <input 
                            v-model="form.storage_zone"
                            type="text"
                            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            placeholder="مثال: المنطقة الباردة، المنطقة الجافة، إلخ."
                        />
                        <div v-if="errors.storage_zone" class="text-red-600 text-sm mt-1">
                            {{ errors.storage_zone }}
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h3 class="font-medium text-blue-800 mb-2">ملخص الربط</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>المنتج:</strong> {{ getProductName(form.product_id) }}</p>
                            <p><strong>المستودع:</strong> {{ getWarehouseName(form.warehouse_id) }}</p>
                            <p><strong>طريقة التزويد:</strong> {{ form.replenishment_method }}</p>
                            <p><strong>الحد الأدنى:</strong> {{ formatNumber(form.min_stock) }}</p>
                            <p><strong>الحد الأقصى:</strong> {{ formatNumber(form.max_stock) }}</p>
                            <p><strong>مخزون الأمان:</strong> {{ formatNumber(form.safety_stock) }}</p>
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
                        السابق
                    </button>
                    
                    <button 
                        type="button" 
                        @click="nextStep" 
                        v-if="currentStep < totalSteps" 
                        :disabled="!isStepValid"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        التالي
                    </button>
                    
                    <button 
                        type="button"
                        @click="submit" 
                        v-if="currentStep === totalSteps" 
                        :disabled="submitting || !isStepValid"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                    >
                        <span v-if="submitting" class="animate-spin">⟳</span>
                        {{ submitting ? 'جاري الحفظ...' : 'حفظ الربط' }}
                    </button>
                </div>

                <!-- Form Errors Display -->
                <div v-if="Object.keys(errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-600 font-bold mb-2">يوجد أخطاء في النموذج:</p>
                    <ul class="list-disc list-inside text-red-600 text-sm">
                        <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
