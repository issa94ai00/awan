# مواصفات تنفيذ واجهات ERP التفاعلية
# Interactive ERP UI Implementation Specification

## الهدف الاستراتيجي / Strategic Objective

تطوير واجهات مستخدم (UI) تفاعلية وسلسة لوحدة إدارة المخزون والمستودعات، باستخدام Vue.js 3 (Composition API) مع Inertia.js كموصل بين الواجهة الأمامية والخادم الخلفي (Laravel)، لتحقيق تجربة مستخدم (UX) استثنائية تدعم منطق الأعمال المعقد.

Develop interactive and smooth user interfaces for the inventory and warehouse management module using Vue.js 3 (Composition API) with Inertia.js as a connector between the frontend and backend (Laravel), to achieve an exceptional user experience (UX) that supports complex business logic.

### المتطلبات الأساسية / Core Requirements

- ربط المنتجات بالمستودعات مع بيانات تخطيط مختلفة لكل مستودع
- إدارة الأرصدة والحركات مع منع الأرصدة السالبة
- عرض تقارير وتحليلات فورية لدعم اتخاذ القرار
- تكامل سلس مع قاعدة بيانات MySQL المُصممة خصيصاً لدعم هذا المنطق

---

## المبادئ التوجيهية للتصميم / Design Principles

| المبدأ | التوضيح |
|--------|---------|
| **البساطة (Simplicity)** | إخفاء تعقيد قاعدة البيانات (المفاتيح الأجنبية، القيود، الـ Triggers) عن المستخدم، وتقديم واجهة بديهية تنجز المهمة في أقل من 3 خطوات |
| **التغذية الراجعة الفورية (Real-time Feedback)** | تحديث البيانات (مثل الأرصدة، التنبيهات) دون إعادة تحميل الصفحة باستخدام WebSockets أو Polling |
| **التحقق المسبق (Proactive Validation)** | منع المستخدم من إدخال بيانات غير صالحة (مثل رصيد سالب، أو ربط مكرر) قبل إرسال الطلب إلى الخادم |
| **التجاوب (Responsiveness)** | دعم جميع الأجهزة (شاشات المكتب، التابلت، الجوال) لتلبية احتياجات المستخدمين الميدانيين |
| **الاتساق (Consistency)** | توحيد أنماط التصميم (الألوان، الخطوط، الأزرار، النماذج) في جميع أنحاء التطبيق |

---

## الشاشات المطلوب تطويرها / Required Screens

### 1. شاشة "إدارة المنتجات" (Products Index)

#### الوصف / Description
صفحة رئيسية تعرض جميع المنتجات في جدول تفاعلي.

Main page displaying all products in an interactive table.

#### الميزات المطلوبة / Required Features

**جدول البيانات (Data Table):**
- الكود (Code)
- الاسم (Name)
- الفئة (Category)
- وحدة القياس (Unit of Measure)
- عدد المستودعات المرتبطة (Linked Warehouses Count)
- إجمالي الرصيد (Total Balance)

**فلتر البحث (Search Filter):**
- بحث متقدم بالكود، الاسم، أو الفئة
- Advanced search by code, name, or category

**أزرار الإجراء (Action Buttons):**
- زر "إضافة منتج جديد": يفتح نافذة منبثقة (Modal) لإضافة منتج مع التحقق من عدم تكرار الكود
- زر "ربط بمستودع": لكل منتج، زر سريع يأخذ المستخدم مباشرة إلى شاشة الربط

**حالة المنتج (Product Status):**
- أيقونة ملونة تشير إلى ما إذا كان المنتج مرتبطاً بمستودعات أم لا

#### مثال التنفيذ / Implementation Example

```vue
<!-- resources/js/Pages/Products/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import { Table } from "@starfolksoftware/inertia-table";

const props = defineProps({
    products: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category || '');

// البحث المتأخر
const debouncedSearch = debounce((value) => {
    router.get('/products', { 
        search: value, 
        category: categoryFilter.value 
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

// حالة المنتج
const getProductStatus = (product) => {
    return product.warehouses_count > 0 ? 'linked' : 'unlinked';
};

const getStatusColor = (status) => {
    return status === 'linked' ? 'bg-green-500' : 'bg-gray-400';
};

// فتح Modal إضافة منتج
const showAddModal = ref(false);

function openAddModal() {
    showAddModal.value = true;
}

// الانتقال لشاشة الربط
function goToAssignment(productId) {
    router.get(`/products/${productId}/assign`);
}
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">إدارة المنتجات</h1>
            <button 
                @click="openAddModal"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
            >
                إضافة منتج جديد
            </button>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">بحث</label>
                    <input 
                        v-model="search"
                        @input="debouncedSearch(search)"
                        type="text"
                        placeholder="بحث بالكود أو الاسم..."
                        class="w-full border rounded-lg p-2"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">الفئة</label>
                    <select 
                        v-model="categoryFilter"
                        @change="debouncedSearch(search)"
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="">جميع الفئات</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- جدول المنتجات -->
        <Table :resource="products" class="w-full">
            <template #head>
                <tr>
                    <th>الحالة</th>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>الفئة</th>
                    <th>الوحدة</th>
                    <th>المستودعات</th>
                    <th>الرصيد الإجمالي</th>
                    <th>الإجراءات</th>
                </tr>
            </template>

            <template #body="{ item }">
                <tr>
                    <td>
                        <div 
                            :class="getStatusColor(getProductStatus(item))"
                            class="w-3 h-3 rounded-full"
                        ></div>
                    </td>
                    <td>{{ item.code }}</td>
                    <td>{{ item.name }}</td>
                    <td>{{ item.category?.name }}</td>
                    <td>{{ item.unit }}</td>
                    <td>{{ item.warehouses_count }}</td>
                    <td>{{ item.total_balance }}</td>
                    <td>
                        <div class="flex gap-2">
                            <Link 
                                :href="`/products/${item.id}`"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                عرض
                            </Link>
                            <button 
                                @click="goToAssignment(item.id)"
                                class="text-green-600 hover:text-green-700"
                            >
                                ربط بمستودع
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </Table>

        <!-- Modal إضافة منتج -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <ProductForm @success="showAddModal = false" />
        </Modal>
    </div>
</template>
```

---

### 2. شاشة "ربط المنتج بالمستودع" (Product-Warehouse Assignment) - محورية

#### الوصف / Description
واجهة على شكل (Wizard) من 3 خطوات لإعداد ربط المنتج بالمستودع مع بيانات التخطيط.

Interface in the form of a 3-step Wizard to set up product-warehouse assignment with planning data.

#### الميزات المطلوبة / Required Features

| الخطوة | المحتوى | المنطق |
|--------|---------|--------|
| **الخطوة 1: اختيار المنتج والمستودع** | حقلان للبحث (Autocomplete) للمنتج والمستودع مع عرض تفاصيل كل منهما | التحقق الفوري: إذا كان الربط موجوداً مسبقاً، عرض رسالة وزر "تعديل" بدلاً من الإضافة |
| **الخطوة 2: بيانات التخطيط** | حقول: (طريقة التزويد، طريقة التخطيط، الحد الأدنى، الأقصى، مخزون الأمان، المورد، المهلة الزمنية) | - اقتراحات ذكية: عند اختيار المنتج، اقتراح قيم افتراضية للحد الأدنى/الأقصى بناءً على استهلاك الـ 3 أشهر الماضية<br>- تحديث ديناميكي: عند اختيار طريقة تزويد = "نقل داخلي"، ظهور حقل إضافي لاختيار "المستودع المصدر" |
| **الخطوة 3: المواقع الدقيقة** | حقل "الموقع الرئيسي" مع زر "+ إضافة موقع إضافي" | اقتراح تلقائي للموقع الرئيسي بناءً على (فئة المنتج + اسم المستودع)، مثل: Riyadh-A3-12 |

**شريط التقدم (Progress Bar):**
- يعرض تقدم المستخدم في الخطوات
- إمكانية الحفظ كـ "مسودة" والعودة لاحقاً

#### مثال التنفيذ / Implementation Example

```vue
<!-- resources/js/Pages/Products/Assign.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    product: Object,
    warehouse: Object,
    existingAssignment: Object,
    categories: Array,
    warehouses: Array,
    suppliers: Array,
    bins: Array,
});

const currentStep = ref(1);
const isDraft = ref(false);

const form = useForm({
    product_id: props.product?.id || null,
    warehouse_id: props.warehouse?.id || null,
    product_variant_id: null,
    replenishment_method: 'purchase',
    planning_method: 'mrp',
    min_stock: 0,
    max_stock: 0,
    safety_stock: 0,
    supplier_id: null,
    lead_time_days: 0,
    primary_bin_code: null,
    storage_zone: null,
    is_active: true,
    effective_date: new Date().toISOString().split('T')[0],
    expiry_date: null,
    source_warehouse_id: null, // للنقل الداخلي
    bins: ref([]),
});

// اقتراحات ذكية بناءً على الاستهلاك السابق
watch(() => form.product_id, async (newProductId) => {
    if (newProductId) {
        const response = await fetch(`/api/products/${newProductId}/consumption-stats`);
        const stats = await response.json();
        
        form.min_stock = stats.suggested_min;
        form.max_stock = stats.suggested_max;
        form.safety_stock = stats.suggested_safety;
    }
});

// اقتراح موقع تلقائي
watch([() => form.product_id, () => form.warehouse_id], ([productId, warehouseId]) => {
    if (productId && warehouseId) {
        const product = props.product || props.categories.find(p => p.id === productId);
        const warehouse = props.warehouse || props.warehouses.find(w => w.id === warehouseId);
        
        if (product && warehouse) {
            const categoryCode = product.category?.code || 'GEN';
            const warehouseCode = warehouse.code.substring(0, 3).toUpperCase();
            form.primary_bin_code = `${warehouseCode}-${categoryCode}-01`;
        }
    }
});

// إضافة موقع إضافي
const bins = ref([]);
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

// التحقق من صحة البيانات قبل الانتقال
const canProceedToStep2 = computed(() => {
    return form.product_id && form.warehouse_id;
});

const canProceedToStep3 = computed(() => {
    return form.min_stock >= 0 && 
           form.max_stock > form.min_stock && 
           form.safety_stock >= 0;
});

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

function saveAsDraft() {
    isDraft.value = true;
    form.put(`/assignments/${props.existingAssignment?.id}`, {
        data: { ...form, bins: bins.value, is_draft: true },
    });
}

function submit() {
    form.bins = bins.value;
    form.post('/assignments', {
        onSuccess: () => {
            router.visit('/products');
        },
    });
}
</script>

<template>
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">
            {{ existingAssignment ? 'تعديل ربط المنتج بالمستودع' : 'ربط منتج جديد بالمستودع' }}
        </h1>

        <!-- شريط التقدم -->
        <div class="mb-8">
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
                    <label class="block text-sm font-medium mb-2">المنتج</label>
                    <select 
                        v-model="form.product_id"
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="">اختر المنتج</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }} ({{ cat.code }})
                        </option>
                    </select>
                    <div v-if="form.errors.product_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.product_id }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">المستودع</label>
                    <select 
                        v-model="form.warehouse_id"
                        class="w-full border rounded-lg p-2"
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

            <!-- التحقق من وجود ربط سابق -->
            <div v-if="existingAssignment" class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-yellow-800">
                    هذا المنتج مرتبط بهذا المستودع بالفعل. 
                    <Link 
                        :href="`/assignments/${existingAssignment.id}/edit`"
                        class="text-blue-600 underline"
                    >
                        تعديل الربط الحالي
                    </Link>
                </p>
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
                        <option value="purchase">شراء</option>
                        <option value="manufacturing">تصنيع</option>
                        <option value="internal_transfer">نقل داخلي</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">طريقة التخطيط</label>
                    <select 
                        v-model="form.planning_method"
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="rop">نقطة إعادة الطلب</option>
                        <option value="mrp">تخطيط متطلبات المواد</option>
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

            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="font-bold mb-4">مستويات المخزون</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">الحد الأدنى</label>
                        <input 
                            v-model="form.min_stock"
                            type="number"
                            class="w-full border rounded-lg p-2"
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
                    الكمية المتاحة: {{ form.max_stock - form.min_stock }}
                </div>
            </div>

            <div class="flex justify-between">
                <button 
                    @click="previousStep"
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                >
                    السابق
                </button>
                <div class="flex gap-4">
                    <button 
                        @click="saveAsDraft"
                        class="bg-yellow-500 text-white px-6 py-2 rounded-lg hover:bg-yellow-600"
                    >
                        حفظ كمسودة
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
                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                </button>
            </div>
        </div>
    </div>
</template>
```

---

### 3. شاشة "عرض الأرصدة والحركات" (Stock Balances & Transactions)

#### الوصف / Description
واجهة تعرض الرصيد الحالي لكل منتج في كل مستودع، مع سجل الحركات.

Interface displaying current stock balance for each product in each warehouse, with transaction log.

#### الميزات المطلوبة / Required Features

**بطاقة الرصيد (Stock Card):**
- الرصيد الحالي، المحجوز، المتاح
- مؤشر لوني (Gauge): أخضر (فوق الحد الأدنى)، برتقالي (بين الأدنى والأقصى)، أحمر (أقل من الأمان)

**زر "إضافة حركة":**
- يفتح نافذة منبثقة تحتوي على:
  - نوع الحركة (إيداع/صرف/تسوية/تحويل)
  - الكمية مع معاينة فورية (Preview) للرصيد المتاح بعد العملية
  - حقل المستند المرجعي مع إمكانية البحث

**سجل الحركات (Transaction Log):**
- جدول زمني يعرض آخر 50 حركة
- إمكانية التصفية (حسب التاريخ، النوع، المستند)
- تصدير إلى Excel/PDF

#### مثال التنفيذ / Implementation Example

```vue
<!-- resources/js/Pages/Stock/Balances.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    product: Object,
    warehouse: Object,
    balance: Object,
    transactions: Object,
});

const showMovementModal = ref(false);

const form = useForm({
    product_id: props.product.id,
    warehouse_id: props.warehouse.id,
    movement_type: 'in',
    quantity: 0,
    reference_document: '',
    notes: '',
});

// معاينة الرصيد بعد الحركة
const previewBalance = computed(() => {
    if (form.movement_type === 'in') {
        return props.balance.available_quantity + form.quantity;
    } else if (form.movement_type === 'out') {
        return props.balance.available_quantity - form.quantity;
    }
    return props.balance.available_quantity;
});

// حالة الرصيد
const balanceStatus = computed(() => {
    const { available_quantity, min_stock, safety_stock } = props.balance;
    
    if (available_quantity <= safety_stock) {
        return { color: 'red', text: 'منخفض جداً' };
    } else if (available_quantity <= min_stock) {
        return { color: 'orange', text: 'منخفض' };
    } else {
        return { color: 'green', text: 'جيد' };
    }
});

// التحقق من صحة الكمية قبل الإرسال
const validateQuantity = () => {
    if (form.quantity <= 0) {
        form.errors.quantity = 'الكمية يجب أن تكون أكبر من صفر';
        return false;
    }
    
    if (form.movement_type === 'out' && previewBalance.value < 0) {
        form.errors.quantity = 'الرصيد المتاح غير كافٍ';
        return false;
    }
    
    return true;
};

function submitMovement() {
    if (!validateQuantity()) return;
    
    form.post('/stock/movements', {
        onSuccess: () => {
            showMovementModal.value = false;
            form.reset();
            router.reload({ only: ['balance', 'transactions'] });
        },
    });
}

// تصفية الحركات
const filters = ref({
    date_from: null,
    date_to: null,
    type: null,
    reference: '',
});

function applyFilters() {
    router.get('/stock/balances', filters.value, {
        preserveState: true,
    });
}

function exportTransactions(format) {
    window.open(`/stock/transactions/export?format=${format}`, '_blank');
}
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">الأرصدة والحركات</h1>
            <button 
                @click="showMovementModal = true"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
            >
                إضافة حركة
            </button>
        </div>

        <!-- بطاقة الرصيد -->
        <div class="grid grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-bold mb-2">الرصيد الحالي</h3>
                <p class="text-3xl font-bold">{{ balance.quantity }}</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-bold mb-2">المحجوز</h3>
                <p class="text-3xl font-bold">{{ balance.reserved_quantity }}</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-bold mb-2">المتاح</h3>
                <p class="text-3xl font-bold">{{ balance.available_quantity }}</p>
            </div>
        </div>

        <!-- مؤشر الحالة -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">حالة الرصيد</h3>
                    <p class="text-gray-600">
                        الحد الأدنى: {{ balance.min_stock }} | 
                        الحد الأقصى: {{ balance.max_stock }} | 
                        مخزون الأمان: {{ balance.safety_stock }}
                    </p>
                </div>
                <div 
                    :class="`bg-${balanceStatus.color}-500 text-white px-6 py-3 rounded-lg`"
                >
                    {{ balanceStatus.text }}
                </div>
            </div>
        </div>

        <!-- سجل الحركات -->
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">سجل الحركات</h3>
                <div class="flex gap-2">
                    <button 
                        @click="exportTransactions('excel')"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                    >
                        تصدير Excel
                    </button>
                    <button 
                        @click="exportTransactions('pdf')"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
                    >
                        تصدير PDF
                    </button>
                </div>
            </div>

            <!-- فلاتر -->
            <div class="grid grid-cols-4 gap-4 mb-4">
                <input 
                    v-model="filters.date_from"
                    type="date"
                    class="border rounded-lg p-2"
                    placeholder="من تاريخ"
                />
                <input 
                    v-model="filters.date_to"
                    type="date"
                    class="border rounded-lg p-2"
                    placeholder="إلى تاريخ"
                />
                <select 
                    v-model="filters.type"
                    class="border rounded-lg p-2"
                >
                    <option value="">جميع الأنواع</option>
                    <option value="in">إيداع</option>
                    <option value="out">صرف</option>
                    <option value="adjustment">تسوية</option>
                    <option value="transfer">تحويل</option>
                </select>
                <button 
                    @click="applyFilters"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                    تطبيق الفلاتر
                </button>
            </div>

            <!-- جدول الحركات -->
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left p-2">التاريخ</th>
                        <th class="text-left p-2">النوع</th>
                        <th class="text-left p-2">الكمية</th>
                        <th class="text-left p-2">المستند</th>
                        <th class="text-left p-2">الملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="txn in transactions.data" :key="txn.id" class="border-b">
                        <td class="p-2">{{ txn.created_at }}</td>
                        <td class="p-2">
                            <span 
                                :class="{
                                    'bg-green-100 text-green-800': txn.movement_type === 'in',
                                    'bg-red-100 text-red-800': txn.movement_type === 'out',
                                    'bg-yellow-100 text-yellow-800': txn.movement_type === 'adjustment',
                                    'bg-blue-100 text-blue-800': txn.movement_type === 'transfer',
                                }"
                                class="px-2 py-1 rounded text-xs"
                            >
                                {{ txn.movement_type }}
                            </span>
                        </td>
                        <td class="p-2">{{ txn.quantity }}</td>
                        <td class="p-2">{{ txn.reference_document }}</td>
                        <td class="p-2">{{ txn.notes }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal إضافة حركة -->
        <Modal :show="showMovementModal" @close="showMovementModal = false">
            <div class="p-6">
                <h2 class="text-xl font-bold mb-4">إضافة حركة مخزنية</h2>
                
                <form @submit.prevent="submitMovement">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع الحركة</label>
                            <select 
                                v-model="form.movement_type"
                                class="w-full border rounded-lg p-2"
                            >
                                <option value="in">إيداع</option>
                                <option value="out">صرف</option>
                                <option value="adjustment">تسوية</option>
                                <option value="transfer">تحويل</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">الكمية</label>
                            <input 
                                v-model="form.quantity"
                                type="number"
                                class="w-full border rounded-lg p-2"
                            />
                            <div v-if="form.errors.quantity" class="text-red-500 text-sm mt-1">
                                {{ form.errors.quantity }}
                            </div>
                        </div>

                        <!-- معاينة الرصيد -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">
                                الرصيد المتاح بعد العملية: 
                                <span class="font-bold">{{ previewBalance }}</span>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">المستند المرجعي</label>
                            <input 
                                v-model="form.reference_document"
                                type="text"
                                class="w-full border rounded-lg p-2"
                                placeholder="مثال: PO-12345"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">ملاحظات</label>
                            <textarea 
                                v-model="form.notes"
                                class="w-full border rounded-lg p-2"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-4">
                        <button 
                            type="button"
                            @click="showMovementModal = false"
                            class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                        >
                            إلغاء
                        </button>
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
```

---

### 4. لوحة المعلومات (Dashboard)

#### الوصف / Description
صفحة رئيسية للمشرفين تعرض مؤشرات الأداء الرئيسية (KPIs).

Main page for supervisors displaying key performance indicators (KPIs).

#### الميزات المطلوبة / Required Features

**بطاقات سريعة (Quick Stats):**
- عدد المنتجات المرتبطة بمستودعات
- عدد المستودعات النشطة
- عدد المنتجات التي وصلت إلى نقطة إعادة الطلب (ROP)

**رسوم بيانية (Charts):**
- أكثر 5 منتجات استهلاكاً في آخر 30 يوماً (شريطي أو خطي)
- توزيع المخزون بين المستودعات (دائري)

**قائمة تنبيهات (Alerts) لحظية:**
- تنبيهات تلقائية مثل: "المنتج (X) في المستودع (Y) وصل للحد الأدنى"
- تظهر كقائمة منسدلة في أعلى الصفحة مع إمكانية النقر للانتقال إلى المنتج مباشرة

#### مثال التنفيذ / Implementation Example

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    topProducts: Array,
    warehouseDistribution: Array,
    alerts: Array,
});

const showAlerts = ref(false);

// الاستماع لتحديثات فورية
onMounted(() => {
    window.Echo.private('dashboard.alerts')
        .listen('StockAlert', (e) => {
            props.alerts.unshift(e.alert);
            showNotification(`تنبيه: ${e.alert.message}`);
        });
});

onUnmounted(() => {
    window.Echo.leave('dashboard.alerts');
});

function showNotification(message) {
    // عرض إشعار
    // Show notification
}
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">لوحة المعلومات</h1>
            
            <!-- قائمة التنبيهات -->
            <div class="relative">
                <button 
                    @click="showAlerts = !showAlerts"
                    class="relative bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600"
                >
                    التنبيهات
                    <span 
                        v-if="alerts.length > 0"
                        class="absolute -top-2 -right-2 bg-white text-red-500 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold"
                    >
                        {{ alerts.length }}
                    </span>
                </button>
                
                <div 
                    v-if="showAlerts"
                    class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-10"
                >
                    <div class="p-4 border-b">
                        <h3 class="font-bold">التنبيهات الحديثة</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div 
                            v-for="alert in alerts" 
                            :key="alert.id"
                            class="p-4 border-b hover:bg-gray-50 cursor-pointer"
                        >
                            <Link :href="`/products/${alert.product_id}`">
                                <p class="text-sm">{{ alert.message }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ alert.created_at }}</p>
                            </Link>
                        </div>
                        <div v-if="alerts.length === 0" class="p-4 text-gray-500 text-center">
                            لا توجد تنبيهات
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- البطاقات السريعة -->
        <div class="grid grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-gray-600 mb-2">المنتجات المرتبطة</h3>
                <p class="text-3xl font-bold">{{ stats.linked_products }}</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-gray-600 mb-2">المستودعات النشطة</h3>
                <p class="text-3xl font-bold">{{ stats.active_warehouses }}</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-gray-600 mb-2">منتجات تحتاج إعادة طلب</h3>
                <p class="text-3xl font-bold text-red-600">{{ stats.reorder_products }}</p>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-bold mb-4">أكثر 5 منتجات استهلاكاً</h3>
                <Chart :data="topProducts" type="bar" />
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-lg font-bold mb-4">توزيع المخزون</h3>
                <Chart :data="warehouseDistribution" type="pie" />
            </div>
        </div>

        <!-- التنبيهات الأخيرة -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-bold mb-4">التنبيهات الأخيرة</h3>
            <div v-if="alerts.length > 0" class="space-y-3">
                <div 
                    v-for="alert in alerts.slice(0, 5)" 
                    :key="alert.id"
                    class="flex items-center justify-between p-3 bg-red-50 rounded-lg"
                >
                    <div>
                        <p class="text-sm">{{ alert.message }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ alert.created_at }}</p>
                    </div>
                    <Link 
                        :href="`/products/${alert.product_id}`"
                        class="text-blue-600 hover:text-blue-700 text-sm"
                    >
                        عرض
                    </Link>
                </div>
            </div>
            <div v-else class="text-gray-500 text-center py-4">
                لا توجد تنبيهات
            </div>
        </div>
    </div>
</template>
```

---

## تنظيم المشروع / Project Structure

```
resources/js/
├── Pages/
│   ├── Products/
│   │   ├── Index.vue          # قائمة المنتجات
│   │   ├── Show.vue           # تفاصيل المنتج
│   │   ├── Create.vue         # إنشاء منتج
│   │   ├── Edit.vue           # تعديل منتج
│   │   └── Assign.vue         # شاشة ربط المنتج بالمستودع (Wizard)
│   ├── Stock/
│   │   ├── Balances.vue       # عرض الأرصدة والحركات
│   │   ├── Movements.vue      # إضافة حركة
│   │   └── Adjustments.vue    # تعديلات المخزون
│   ├── Warehouses/
│   │   ├── Index.vue          # قائمة المستودعات
│   │   ├── Show.vue           # تفاصيل المستودع
│   │   ├── Create.vue         # إنشاء مستودع
│   │   └── Edit.vue           # تعديل مستودع
│   └── Dashboard.vue          # لوحة المعلومات
├── Components/
│   ├── Common/
│   │   ├── AppLayout.vue      # التخطيط العام
│   │   ├── Modal.vue          # مكون النافذة المنبثقة
│   │   ├── Alert.vue          # مكون التنبيهات
│   │   └── LoadingSpinner.vue # مؤشر التحميل
│   ├── Forms/
│   │   ├── ProductForm.vue    # نموذج المنتج
│   │   ├── WarehouseForm.vue # نموذج المستودع
│   │   └── AssignmentForm.vue # نموذج الربط
│   ├── Tables/
│   │   ├── ProductsTable.vue  # جدول المنتجات
│   │   ├── WarehousesTable.vue # جدول المستودعات
│   │   └── TransactionsTable.vue # جدول الحركات
│   └── Charts/
│       ├── BarChart.vue       # رسم بياني شريطي
│       ├── PieChart.vue       # رسم بياني دائري
│       └── LineChart.vue      # رسم بياني خطي
├── Composables/
│   ├── useStock.js            # إدارة الأرصدة
│   ├── useWarehouse.js        # إدارة المستودعات
│   ├── useProduct.js          # إدارة المنتجات
│   ├── useNotification.js     # إدارة الإشعارات
│   └── useChart.js            # إدارة الرسوم البيانية
├── Stores/
│   ├── userStore.js           # حالة المستخدم (Pinia)
│   ├── warehouseStore.js      # حالة المستودعات (Pinia)
│   └── productStore.js        # حالة المنتجات (Pinia)
└── app.js
```

---

## سيناريو اختبار القبول / Acceptance Criteria

| # | السيناريو | الإجراء المتوقع من النظام |
|---|-----------|-------------------------|
| 1 | مستخدم يضيف منتجاً جديداً | يتم حفظ المنتج في قاعدة البيانات، وتظهر رسالة نجاح، ويُعاد توجيه المستخدم إلى قائمة المنتجات مع ظهور المنتج الجديد في أعلى القائمة |
| 2 | مستخدم يربط منتجاً بمستودع | يمر بخطوات الـ Wizard، وبعد الحفظ، تظهر رسالة نجاح، ويتم تحديث قائمة المستودعات المرتبطة بالمنتج فوراً دون إعادة تحميل الصفحة |
| 3 | مستخدم يحاول إضافة صرف بكمية تتجاوز الرصيد المتاح | تظهر رسالة خطأ واضحة قبل إرسال الطلب إلى الخادم (تحقق أمامي)، وفي حال تجاوزه، يرفض الخادم الطلب ويعرض خطأ في form.errors |
| 4 | وصول رصيد منتج إلى الحد الأدنى | يظهر تنبيه فوري في لوحة المعلومات (Dashboard) وداخل شاشة المنتج، مع إشعار صوتي أو بصري |
| 5 | مستخدم يقوم بتصفية المنتجات حسب المستودع | يتم تحديث الجدول ديناميكياً باستخدام Inertia، مع الحفاظ على حالة التصفية في الـ URL (للمشاركة) |

---

## المخرجات المطلوبة / Required Deliverables

### 1. تصاميم واجهات (UI Mockups)
- استخدام Figma أو Adobe XD
- عرض جميع الشاشات المذكورة أعلاه
- تدفق المستخدم (User Flow)

### 2. كود Vue.js جاهز للتشغيل
- تنفيذ جميع المتطلبات المذكورة
- استخدام Vue.js 3 (Composition API) و Inertia.js
- الالتزام بالهيكل التنظيمي المقترح

### 3. تكامل مع الخادم الخلفي (Laravel)
- إعداد الـ Routes
- إعداد الـ Controllers
- استخدام Inertia::render و Inertia::location

### 4. توثيق المطور (Developer Guide)
- ملف README.md يشرح:
  - كيفية إضافة صفحة جديدة
  - كيفية استخدام useForm مع الأخطاء
  - كيفية إعداد Laravel Echo للتنبيهات الفورية

### 5. اختبارات (Testing)
- كتابة اختبارات وحدة (Unit Tests) باستخدام Vitest أو Jest
- اختبار المكونات الرئيسية (خاصة النماذج والجداول)

---

## نصائح إضافية / Additional Tips

### استخدام Inertia::location
```php
// للانتقالات التي تتطلب إعادة تحميل كاملة
return Inertia::location('/external-link');
```

### استخدام useRemember
```javascript
import { useRemember } from '@inertiajs/vue3';

const tabs = useRemember('details', 'product-tabs');
```

### إعداد Laravel Echo
```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});
```

### تحسين الأداء
```vue
<!-- استخدام v-once و v-memo للمكونات الثقيلة -->
<div v-memo="[expensiveData]">
    <HeavyComponent :data="expensiveData" />
</div>
```

### دعم الترجمة (i18n)
```javascript
// إعداد Vue I18n
import { createI18n } from 'vue-i18n';

const i18n = createI18n({
    locale: 'ar',
    fallbackLocale: 'en',
    messages: {
        ar: { /* Arabic translations */ },
        en: { /* English translations */ },
    },
});
```

---

## الخلاصة / Conclusion

هذه المواصفات توفر خارطة طريق شاملة لتطوير واجهات ERP تفاعلية باستخدام Vue.js 3 و Inertia.js. باتباع هذه الإرشادات، يمكن للفريق بناء واجهات مستخدم سلسة وسريعة مع الحفاظ على كود منظم وقابل للصيانة.

This specification provides a comprehensive roadmap for developing interactive ERP interfaces using Vue.js 3 and Inertia.js. By following these guidelines, the team can build smooth and fast user interfaces while maintaining organized and maintainable code.

---

**تاريخ الإنشاء:** 7 أغسطس 2026  
**الإصدار:** 1.0  
**الحالة:** جاهز للتنفيذ
