<!-- resources/js/Components/Forms/AssignmentForm.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    assignment: {
        type: Object,
        default: null,
    },
    product: {
        type: Object,
        default: null,
    },
    warehouse: {
        type: Object,
        default: null,
    },
    products: {
        type: Array,
        default: () => [],
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    bins: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['success', 'cancel']);

const currentStep = ref(1);
const bins = ref(props.assignment?.bins || []);

const form = useForm({
    product_id: props.assignment?.product_id || props.product?.id || null,
    warehouse_id: props.assignment?.warehouse_id || props.warehouse?.id || null,
    product_variant_id: props.assignment?.product_variant_id || null,
    replenishment_method: props.assignment?.replenishment_method || 'purchase',
    planning_method: props.assignment?.planning_method || 'mrp',
    min_stock: props.assignment?.min_stock || 0,
    max_stock: props.assignment?.max_stock || 0,
    safety_stock: props.assignment?.safety_stock || 0,
    supplier_id: props.assignment?.supplier_id || null,
    lead_time_days: props.assignment?.lead_time_days || 0,
    primary_bin_code: props.assignment?.primary_bin_code || null,
    storage_zone: props.assignment?.storage_zone || null,
    is_active: props.assignment?.is_active ?? true,
    effective_date: props.assignment?.effective_date || new Date().toISOString().split('T')[0],
    expiry_date: props.assignment?.expiry_date || null,
    source_warehouse_id: props.assignment?.source_warehouse_id || null,
    bins: bins.value,
});

// طرق التزويد
const replenishmentMethods = [
    { value: 'purchase', label: 'شراء' },
    { value: 'manufacturing', label: 'تصنيع' },
    { value: 'internal_transfer', label: 'نقل داخلي' },
];

// طرق التخطيط
const planningMethods = [
    { value: 'rop', label: 'نقطة إعادة الطلب' },
    { value: 'mrp', label: 'تخطيط متطلبات المواد' },
];

// اقتراحات ذكية بناءً على الاستهلاك السابق
watch(() => form.product_id, async (newProductId) => {
    if (newProductId && !props.assignment) {
        try {
            const response = await fetch(`/api/products/${newProductId}/consumption-stats`);
            const stats = await response.json();
            
            if (stats.suggested_min) form.min_stock = stats.suggested_min;
            if (stats.suggested_max) form.max_stock = stats.suggested_max;
            if (stats.suggested_safety) form.safety_stock = stats.suggested_safety;
        } catch (e) {
            console.error('Error fetching consumption stats:', e);
        }
    }
});

// اقتراح موقع تلقائي
watch([() => form.product_id, () => form.warehouse_id], ([productId, warehouseId]) => {
    if (productId && warehouseId && !props.assignment) {
        const product = props.product || props.products.find(p => p.id === productId);
        const warehouse = props.warehouse || props.warehouses.find(w => w.id === warehouseId);
        
        if (product && warehouse) {
            const categoryCode = product.category?.code || 'GEN';
            const warehouseCode = warehouse.code.substring(0, 3).toUpperCase();
            form.primary_bin_code = `${warehouseCode}-${categoryCode}-01`;
        }
    }
});

// التحقق من صحة البيانات قبل الانتقال
const canProceedToStep2 = computed(() => {
    return form.product_id && form.warehouse_id;
});

const canProceedToStep3 = computed(() => {
    return form.min_stock >= 0 && 
           form.max_stock > form.min_stock && 
           form.safety_stock >= 0 &&
           form.safety_stock < form.min_stock;
});

const availableQuantity = computed(() => {
    return form.max_stock - form.min_stock;
});

// إضافة موقع إضافي
function addBin() {
    bins.value.push({
        bin_id: null,
        is_primary: bins.value.length === 0,
        priority_order: bins.value.length + 1,
        capacity_allocation: 100,
    });
}

function removeBin(index) {
    bins.value.splice(index, 1);
    bins.value.forEach((bin, idx) => {
        bin.priority_order = idx + 1;
    });
}

function nextStep() {
    if (currentStep.value < 3) {
        currentStep.value++;
    }
}

function previousStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

function submit() {
    form.bins = bins.value;
    const url = props.assignment 
        ? `/assignments/${props.assignment.id}`
        : '/assignments';
    
    const method = props.assignment ? 'put' : 'post';
    
    form[method](url, {
        onSuccess: () => {
            emit('success');
        },
    });
}

function cancel() {
    emit('cancel');
}
</script>

<template>
    <div class="space-y-6">
        <!-- شريط التقدم -->
        <div>
            <div class="flex justify-between mb-2">
                <span class="text-sm font-medium">التقدم</span>
                <span class="text-sm text-gray-600">{{ currentStep }} من 3</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div 
                    class="bg-blue-600 h-2 rounded-full transition-all"
                    :style="{ width: `${(currentStep / 3) * 100}%` }"
                ></div>
            </div>
        </div>

        <!-- الخطوة 1: اختيار المنتج والمستودع -->
        <div v-if="currentStep === 1" class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">الخطوة 1: اختيار المنتج والمستودع</h2>
            
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">المنتج *</label>
                    <select 
                        v-model="form.product_id"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.product_id }"
                    >
                        <option value="">اختر المنتج</option>
                        <option v-for="prod in products" :key="prod.id" :value="prod.id">
                            {{ prod.name }} ({{ prod.code }})
                        </option>
                    </select>
                    <div v-if="form.errors.product_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.product_id }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">المستودع *</label>
                    <select 
                        v-model="form.warehouse_id"
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.warehouse_id }"
                    >
                        <option value="">اختر المستودع</option>
                        <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                            {{ wh.name }} ({{ wh.code }})
                        </option>
                    </select>
                    <div v-if="form.errors.warehouse_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.warehouse_id }}
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button 
                    @click="nextStep"
                    :disabled="!canProceedToStep2"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    التالي
                </button>
            </div>
        </div>

        <!-- الخطوة 2: بيانات التخطيط -->
        <div v-if="currentStep === 2" class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">الخطوة 2: بيانات التخطيط</h2>
            
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium mb-2">طريقة التزويد</label>
                    <select 
                        v-model="form.replenishment_method"
                        class="w-full border rounded-lg p-2"
                    >
                        <option v-for="method in replenishmentMethods" :key="method.value" :value="method.value">
                            {{ method.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">طريقة التخطيط</label>
                    <select 
                        v-model="form.planning_method"
                        class="w-full border rounded-lg p-2"
                    >
                        <option v-for="method in planningMethods" :key="method.value" :value="method.value">
                            {{ method.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">وقت التسليم (أيام)</label>
                    <input 
                        v-model="form.lead_time_days"
                        type="number"
                        class="w-full border rounded-lg p-2"
                    />
                </div>
            </div>

            <!-- حقل المستودع المصدر للنقل الداخلي -->
            <div v-if="form.replenishment_method === 'internal_transfer'" class="mb-6">
                <label class="block text-sm font-medium mb-2">المستودع المصدر</label>
                <select 
                    v-model="form.source_warehouse_id"
                    class="w-full border rounded-lg p-2"
                >
                    <option value="">اختر المستودع المصدر</option>
                    <option 
                        v-for="wh in warehouses.filter(w => w.id !== form.warehouse_id)" 
                        :key="wh.id" 
                        :value="wh.id"
                    >
                        {{ wh.name }}
                    </option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">المورد</label>
                <select 
                    v-model="form.supplier_id"
                    class="w-full border rounded-lg p-2"
                >
                    <option value="">اختر المورد</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                        {{ supplier.name }}
                    </option>
                </select>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="font-bold mb-4">مستويات المخزون</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">الحد الأدنى</label>
                        <input 
                            v-model="form.min_stock"
                            type="number"
                            class="w-full border rounded-lg p-2"
                            :class="{ 'border-red-500': form.errors.min_stock }"
                        />
                        <div v-if="form.errors.min_stock" class="text-red-500 text-sm mt-1">
                            {{ form.errors.min_stock }}
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الحد الأقصى</label>
                        <input 
                            v-model="form.max_stock"
                            type="number"
                            class="w-full border rounded-lg p-2"
                            :class="{ 'border-red-500': form.errors.max_stock }"
                        />
                        <div v-if="form.errors.max_stock" class="text-red-500 text-sm mt-1">
                            {{ form.errors.max_stock }}
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">مخزون الأمان</label>
                        <input 
                            v-model="form.safety_stock"
                            type="number"
                            class="w-full border rounded-lg p-2"
                        />
                    </div>
                </div>
                
                <div class="mt-4 text-sm text-gray-600">
                    الكمية المتاحة: {{ availableQuantity }}
                </div>
            </div>

            <div class="flex justify-between">
                <button 
                    @click="previousStep"
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                >
                    السابق
                </button>
                <button 
                    @click="nextStep"
                    :disabled="!canProceedToStep3"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    التالي
                </button>
            </div>
        </div>

        <!-- الخطوة 3: المواقع الدقيقة -->
        <div v-if="currentStep === 3" class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">الخطوة 3: المواقع الدقيقة</h2>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">الموقع الرئيسي</label>
                <input 
                    v-model="form.primary_bin_code"
                    type="text"
                    placeholder="مثال: RIY-GEN-01"
                    class="w-full border rounded-lg p-2"
                />
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">منطقة التخزين</label>
                <input 
                    v-model="form.storage_zone"
                    type="text"
                    class="w-full border rounded-lg p-2"
                />
            </div>

            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold">مواقع إضافية</h3>
                    <button 
                        @click="addBin"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                    >
                        + إضافة موقع
                    </button>
                </div>
                
                <div v-if="bins.length === 0" class="text-gray-500 text-center py-4">
                    لم يتم إضافة مواقع إضافية
                </div>
                
                <div v-else class="space-y-3">
                    <div 
                        v-for="(bin, index) in bins" 
                        :key="index"
                        class="flex gap-4 items-center bg-gray-50 p-3 rounded-lg"
                    >
                        <select v-model="bin.bin_id" class="flex-1 border rounded-lg p-2">
                            <option value="">اختر الموقع</option>
                            <option v-for="b in bins" :key="b.id" :value="b.id">
                                {{ b.code }} - {{ b.zone }}
                            </option>
                        </select>
                        
                        <input 
                            v-model="bin.priority_order"
                            type="number"
                            placeholder="الأولوية"
                            class="w-24 border rounded-lg p-2"
                        />
                        
                        <label class="flex items-center">
                            <input 
                                v-model="bin.is_primary"
                                type="checkbox"
                                class="mr-2"
                            />
                            أساسي
                        </label>
                        
                        <button 
                            @click="removeBin(index)"
                            class="text-red-600 hover:text-red-700"
                        >
                            حذف
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">تاريخ البدء</label>
                <input 
                    v-model="form.effective_date"
                    type="date"
                    class="w-full border rounded-lg p-2"
                />
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">تاريخ الانتهاء (اختياري)</label>
                <input 
                    v-model="form.expiry_date"
                    type="date"
                    class="w-full border rounded-lg p-2"
                />
            </div>

            <div class="flex justify-between">
                <button 
                    @click="previousStep"
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                >
                    السابق
                </button>
                <button 
                    @click="submit"
                    :disabled="form.processing"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'جاري الحفظ...' : (assignment ? 'تحديث' : 'حفظ') }}
                </button>
            </div>
        </div>
    </div>
</template>
