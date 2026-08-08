# دليل تطوير واجهات ERP متقدمة باستخدام Vue.js و Inertia.js
# Advanced ERP Interface Development Guide using Vue.js and Inertia.js

## الهدف / Objective
تطوير واجهات مستخدم تفاعلية وسلسة لوحدة إدارة المخزون والمستودعات باستخدام إطار **Vue.js 3** مع **Inertia.js**، لضمان تجربة مستخدم تشبه تطبيقات سطح المكتب (SPA) مع الحفاظ على بساطة منطق الخادم.

Develop interactive and smooth user interfaces for the inventory and warehouse management module using **Vue.js 3** with **Inertia.js**, ensuring a desktop-like SPA user experience while maintaining simple server-side logic.

---

## هيكل المشروع / Project Structure

### تنظيم المجلدات / Folder Organization

```
resources/js/
├── Pages/
│   ├── Admin/
│   │   ├── WMS/
│   │   │   ├── Warehouses/
│   │   │   │   ├── Index.vue          # قائمة المستودعات
│   │   │   │   ├── Create.vue         # إنشاء مستودع
│   │   │   │   ├── Edit.vue           # تعديل مستودع
│   │   │   │   └── Show.vue           # تفاصيل مستودع
│   │   │   ├── Products/
│   │   │   │   ├── Index.vue          # قائمة المنتجات
│   │   │   │   ├── Create.vue         # إنشاء منتج
│   │   │   │   ├── Edit.vue           # تعديل منتج
│   │   │   │   └── Show.vue           # تفاصيل منتج
│   │   │   ├── Assignments/
│   │   │   │   ├── Index.vue          # قائمة التخصيصات
│   │   │   │   ├── Create.vue         # ربط منتج بمستودع
│   │   │   │   └── Edit.vue           # تعديل التخصيص
│   │   │   ├── Inventory/
│   │   │   │   ├── Index.vue          # قائمة المخزون
│   │   │   │   ├── Movements.vue      # حركات المخزون
│   │   │   │   └── Adjustments.vue    # تعديلات المخزون
│   │   │   └── Dashboard.vue          # لوحة تحكم WMS
│   │   └── Dashboard.vue              # لوحة تحكم عامة
│   └── Auth/
│       ├── Login.vue
│       └── Register.vue
├── Components/
│   ├── Admin/
│   │   ├── WMS/
│   │   │   ├── WarehouseCard.vue
│   │   │   ├── ProductCard.vue
│   │   │   ├── AssignmentForm.vue
│   │   │   ├── InventoryTable.vue
│   │   │   ├── MovementForm.vue
│   │   │   └── StatsCard.vue
│   │   └── Shared/
│   │       ├── DataTable.vue
│   │       ├── SearchInput.vue
│   │       ├── StatusBadge.vue
│   │       └── ActionButton.vue
│   ├── Layouts/
│   │   ├── AdminLayout.vue
│   │   └── AuthLayout.vue
│   └── Shared/
│       ├── Modal.vue
│       ├── Notification.vue
│       ├── ConfirmDialog.vue
│       └── LoadingSpinner.vue
├── Composables/
│   ├── useWarehouse.js
│   ├── useProduct.js
│   ├── useInventory.js
│   └── useNotification.js
└── app.js
```

---

## 1. إدارة النماذج والبيانات / Forms & State Management

### استخدام useForm من Inertia.js

استخدم `useForm` من `@inertiajs/vue3` لإدارة جميع نماذج البيانات المعقدة. هذا المدمج يوفر تتبعاً فورياً لحالة الإرسال (`processing`) وعرض أخطاء التحقق من الصحة (`errors`).

Use `useForm` from `@inertiajs/vue3` to manage all complex data forms. This composable provides real-time tracking of submission state (`processing`) and validation error display (`errors`).

#### مثال أساسي / Basic Example

```vue
<!-- resources/js/Pages/Admin/WMS/Warehouses/Create.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    code: '',
    address: '',
    city: '',
    country: '',
    manager_name: '',
    manager_phone: '',
    location_type: 'warehouse',
    latitude: null,
    longitude: null,
    capacity: null,
    operating_hours: null,
    is_active: true,
    is_primary: false,
    manager_id: null,
});

function submit() {
    form.post('/api/v1/admin/wms/warehouses', {
        onSuccess: () => {
            // عرض رسالة نجاح
            // Show success message
        },
        onError: (errors) => {
            // معالجة الأخطاء
            // Handle errors
        },
    });
}
</script>

<template>
    <div class="max-w-2xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">إنشاء مستودع جديد</h1>
        
        <form @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <!-- الاسم -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">اسم المستودع</label>
                    <input 
                        v-model="form.name" 
                        type="text" 
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                        {{ form.errors.name }}
                    </div>
                </div>

                <!-- الكود -->
                <div>
                    <label class="block text-sm font-medium mb-2">كود المستودع</label>
                    <input 
                        v-model="form.code" 
                        type="text" 
                        class="w-full border rounded-lg p-2"
                        :class="{ 'border-red-500': form.errors.code }"
                    />
                    <div v-if="form.errors.code" class="text-red-500 text-sm mt-1">
                        {{ form.errors.code }}
                    </div>
                </div>

                <!-- نوع الموقع -->
                <div>
                    <label class="block text-sm font-medium mb-2">نوع الموقع</label>
                    <select 
                        v-model="form.location_type" 
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="warehouse">مستودع</option>
                        <option value="branch">فرع</option>
                        <option value="distribution_center">مركز توزيع</option>
                        <option value="3pl">طرف ثالث</option>
                    </select>
                </div>

                <!-- العنوان -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">العنوان</label>
                    <textarea 
                        v-model="form.address" 
                        class="w-full border rounded-lg p-2"
                        rows="3"
                    ></textarea>
                </div>

                <!-- السعة -->
                <div>
                    <label class="block text-sm font-medium mb-2">السعة</label>
                    <input 
                        v-model="form.capacity" 
                        type="number" 
                        class="w-full border rounded-lg p-2"
                    />
                </div>

                <!-- نشط -->
                <div class="flex items-center">
                    <input 
                        v-model="form.is_active" 
                        type="checkbox" 
                        id="is_active"
                        class="mr-2"
                    />
                    <label for="is_active" class="text-sm font-medium">نشط</label>
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                </button>
                <Link 
                    href="/admin/wms/warehouses" 
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                >
                    إلغاء
                </Link>
            </div>
        </form>
    </div>
</template>
```

#### نموذج معقد مع بيانات ديناميكية / Complex Form with Dynamic Data

```vue
<!-- resources/js/Pages/Admin/WMS/Assignments/Create.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    products: Array,
    warehouses: Array,
    bins: Array,
});

// إدارة الحالة المحلية للبيانات الديناميكية
// Manage local state for dynamic data
const selectedBins = ref([]);

const form = useForm({
    product_id: null,
    warehouse_id: null,
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
    bins: selectedBins.value,
});

// حساب الكمية المتاحة
// Calculate available quantity
const availableQuantity = computed(() => {
    return form.max_stock - form.min_stock;
});

// إضافة موقع تخزين
// Add storage location
function addBin() {
    selectedBins.value.push({
        bin_id: null,
        is_primary: selectedBins.value.length === 0,
        priority_order: selectedBins.value.length + 1,
        capacity_allocation: 100,
    });
}

// إزالة موقع تخزين
// Remove storage location
function removeBin(index) {
    selectedBins.value.splice(index, 1);
    // إعادة ترتيب الأولويات
    // Reorder priorities
    selectedBins.value.forEach((bin, idx) => {
        bin.priority_order = idx + 1;
    });
}

function submit() {
    form.bins = selectedBins.value;
    form.post('/api/v1/admin/product-warehouse-assignments', {
        onSuccess: () => {
            // إعادة تعيين النموذج
            // Reset form
            form.reset();
            selectedBins.value = [];
        },
    });
}
</script>

<template>
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">ربط منتج بمستودع</h1>
        
        <form @submit.prevent="submit">
            <!-- بيانات المنتج والمستودع -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium mb-2">المنتج</label>
                    <select 
                        v-model="form.product_id" 
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="">اختر المنتج</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }}
                        </option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">المستودع</label>
                    <select 
                        v-model="form.warehouse_id" 
                        class="w-full border rounded-lg p-2"
                    >
                        <option value="">اختر المستودع</option>
                        <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                            {{ warehouse.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- بيانات التخطيط -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="font-bold mb-4">بيانات التخطيط</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">طريقة التزويد</label>
                        <select v-model="form.replenishment_method" class="w-full border rounded-lg p-2">
                            <option value="purchase">شراء</option>
                            <option value="manufacturing">تصنيع</option>
                            <option value="internal_transfer">نقل داخلي</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">طريقة التخطيط</label>
                        <select v-model="form.planning_method" class="w-full border rounded-lg p-2">
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
            </div>

            <!-- مستويات المخزون -->
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
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الحد الأقصى</label>
                        <input 
                            v-model="form.max_stock" 
                            type="number" 
                            class="w-full border rounded-lg p-2"
                        />
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

            <!-- مواقع التخزين -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold">مواقع التخزين</h3>
                    <button 
                        type="button" 
                        @click="addBin"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                    >
                        إضافة موقع
                    </button>
                </div>
                
                <div v-if="selectedBins.length === 0" class="text-gray-500 text-center py-4">
                    لم يتم إضافة مواقع تخزين
                </div>
                
                <div v-else class="space-y-3">
                    <div 
                        v-for="(bin, index) in selectedBins" 
                        :key="index"
                        class="flex gap-4 items-center bg-white p-3 rounded-lg"
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
                            type="button" 
                            @click="removeBin(index)"
                            class="text-red-600 hover:text-red-700"
                        >
                            حذف
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                </button>
                <button 
                    type="button"
                    @click="form.reset()"
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400"
                >
                    إعادة تعيين
                </button>
            </div>
        </form>
    </div>
</template>
```

---

## 2. التنقل وعرض البيانات / Navigation & Data Tables

### استخدام Link للتنقل

استخدم مكون `<Link>` من Inertia.js لجميع عمليات التنقل الداخلية.

Use the `<Link>` component from Inertia.js for all internal navigation.

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div>
        <!-- رابط بسيط -->
        <!-- Simple link -->
        <Link href="/admin/wms/warehouses">
            المستودعات
        </Link>

        <!-- رابط مع معلمات -->
        <!-- Link with parameters -->
        <Link :href="`/admin/wms/warehouses/${warehouse.id}`">
            تفاصيل المستودع
        </Link>

        <!-- رابط مع طريقة HTTP -->
        <!-- Link with HTTP method -->
        <Link 
            :href="`/admin/wms/warehouses/${warehouse.id}`" 
            method="delete"
            as="button"
        >
            حذف
        </Link>
    </div>
</template>
```

### استخدام inertia-table للجداول

استخدم مكتبة `@starfolksoftware/inertia-table` لعرض الجداول الغنية بالبيانات مع دعم البحث والترتيب والترقيم.

Use the `@starfolksoftware/inertia-table` library to display rich data tables with search, sorting, and pagination support.

#### التثبيت / Installation

```bash
npm install @starfolksoftware/inertia-table
```

#### إضافة المسار إلى Tailwind / Add Path to Tailwind

```javascript
// tailwind.config.js
module.exports = {
    content: [
        './resources/js/**/*.vue',
        './node_modules/@starfolksoftware/inertia-table/src/**/*.vue',
    ],
    // ...
}
```

#### مثال الاستخدام / Usage Example

```vue
<!-- resources/js/Pages/Admin/WMS/Warehouses/Index.vue -->
<script setup>
import { Table } from "@starfolksoftware/inertia-table";
import { Link } from '@inertiajs/vue3';

defineProps({
    warehouses: Object,
});
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">المستودعات</h1>
            <Link 
                href="/admin/wms/warehouses/create"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
            >
                إضافة مستودع
            </Link>
        </div>

        <Table 
            :resource="warehouses"
            class="w-full"
        >
            <template #head>
                <tr>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>المدينة</th>
                    <th>النوع</th>
                    <th>السعة</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </template>

            <template #body="{ item }">
                <tr>
                    <td>{{ item.code }}</td>
                    <td>{{ item.name }}</td>
                    <td>{{ item.city }}</td>
                    <td>{{ item.location_type }}</td>
                    <td>{{ item.capacity }}</td>
                    <td>
                        <span 
                            :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                            class="px-2 py-1 rounded-full text-xs"
                        >
                            {{ item.is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <Link 
                                :href="`/admin/wms/warehouses/${item.id}`"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                عرض
                            </Link>
                            <Link 
                                :href="`/admin/wms/warehouses/${item.id}/edit`"
                                class="text-green-600 hover:text-green-700"
                            >
                                تعديل
                            </Link>
                        </div>
                    </td>
                </tr>
            </template>
        </Table>
    </div>
</template>
```

#### جدول مع بحث مخصص / Table with Custom Search

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

const search = ref('');

// البحث المتأخر لتجنب طلبات كثيرة
// Debounced search to avoid excessive requests
const debouncedSearch = debounce((value) => {
    router.get('/admin/wms/warehouses', { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300);

search.value = new URLSearchParams(window.location.search).get('search') || '';
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <input 
                v-model="search"
                @input="debouncedSearch(search)"
                type="text"
                placeholder="بحث..."
                class="w-full border rounded-lg p-2"
            />
        </div>

        <!-- باقي محتوى الجدول -->
        <!-- Rest of table content -->
    </div>
</template>
```

---

## 3. النوافذ المنبثقة / Modals

### تنفيذ نظام Modals

لتحسين تجربة المستخدم، قم بتنفيذ نظام النوافذ المنبثقة باستخدام `portal-vue` أو مكتبة مخصصة.

To improve user experience, implement a modal system using `portal-vue` or a custom library.

#### التثبيت / Installation

```bash
npm install portal-vue
```

#### إعداد Portal / Setup Portal

```javascript
// resources/js/app.js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { PortalTarget } from 'portal-vue';

createInertiaApp({
    resolve: (name) => require(`./Pages/${name}.vue`),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.component('PortalTarget', PortalTarget);
        app.mount(el);
    },
});
```

#### مكون Modal / Modal Component

```vue
<!-- resources/js/Components/Shared/Modal.vue -->
<script setup>
import { ref, watch } from 'vue';
import { Portal } from 'portal-vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

// إغلاق عند الضغط على Escape
// Close on Escape key press
const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

watch(() => props.show, (show) => {
    if (show) {
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', closeOnEscape);
    }
});
</script>

<template>
    <Portal to="modal">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <!-- الخلفية -->
                    <!-- Backdrop -->
                    <div 
                        class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                        @click="close"
                    ></div>

                    <!-- المحتوى -->
                    <!-- Content -->
                    <div 
                        class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full"
                        :class="{
                            'max-w-sm': maxWidth === 'sm',
                            'max-w-md': maxWidth === 'md',
                            'max-w-lg': maxWidth === 'lg',
                            'max-w-2xl': maxWidth === '2xl',
                            'max-w-3xl': maxWidth === '3xl',
                        }"
                    >
                        <slot></slot>
                    </div>
                </div>
            </div>
        </Transition>
    </Portal>
</template>
```

#### استخدام Modal / Using Modal

```vue
<!-- resources/js/Pages/Admin/WMS/Warehouses/Index.vue -->
<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Shared/Modal.vue';

const showModal = ref(false);
const selectedWarehouse = ref(null);

function openModal(warehouse) {
    selectedWarehouse.value = warehouse;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    selectedWarehouse.value = null;
}
</script>

<template>
    <div>
        <!-- زر فتح Modal -->
        <!-- Button to open Modal -->
        <button @click="openModal(warehouse)">
            عرض التفاصيل
        </button>

        <!-- Modal -->
        <Modal :show="showModal" @close="closeModal" max-width="2xl">
            <div class="p-6">
                <h2 class="text-xl font-bold mb-4">تفاصيل المستودع</h2>
                
                <div v-if="selectedWarehouse">
                    <p><strong>الاسم:</strong> {{ selectedWarehouse.name }}</p>
                    <p><strong>الكود:</strong> {{ selectedWarehouse.code }}</p>
                    <p><strong>العنوان:</strong> {{ selectedWarehouse.address }}</p>
                    <!-- المزيد من التفاصيل -->
                    <!-- More details -->
                </div>

                <div class="mt-6 flex justify-end">
                    <button 
                        @click="closeModal"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg"
                    >
                        إغلاق
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
```

---

## 4. التفاعل الفوري / Real-time Updates

### إعداد Laravel Echo

قم بإعداد Laravel Echo لتحقيق تحديثات فورية للبيانات.

Set up Laravel Echo to achieve real-time data updates.

#### التثبيت / Installation

```bash
npm install --save-dev laravel-echo pusher-js
```

#### إعداد Echo / Setup Echo

```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    wssPort: import.meta.env.VITE_PUSHER_PORT,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

#### الاستماع للأحداث / Listening to Events

```vue
<!-- resources/js/Pages/Admin/WMS/Dashboard.vue -->
<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const inventoryUpdates = ref([]);

onMounted(() => {
    // الاستماع لتحديثات المخزون
    // Listen to inventory updates
    window.Echo.private(`inventory.updates`)
        .listen('InventoryUpdated', (e) => {
            inventoryUpdates.value.unshift(e);
            
            // تحديث البيانات المحلية
            // Update local data
            updateInventoryData(e);
        });

    // الاستماع لتنبيهات إعادة الطلب
    // Listen to reorder alerts
    window.Echo.private(`reorder.alerts`)
        .listen('ReorderAlert', (e) => {
            showNotification(e.message);
        });
});

onUnmounted(() => {
    // إلغاء الاشتراك
    // Unsubscribe
    window.Echo.leave(`inventory.updates`);
    window.Echo.leave(`reorder.alerts`);
});

function updateInventoryData(update) {
    // تحديث البيانات
    // Update data
}

function showNotification(message) {
    // عرض إشعار
    // Show notification
}
</script>

<template>
    <div>
        <h1>لوحة تحكم WMS</h1>
        
        <!-- تحديثات المخزون الحديثة -->
        <!-- Recent inventory updates -->
        <div v-if="inventoryUpdates.length > 0">
            <h2>تحديثات المخزون</h2>
            <div v-for="update in inventoryUpdates" :key="update.id">
                {{ update.message }}
            </div>
        </div>
    </div>
</template>
```

#### إعداد الأحداث في Laravel / Setup Events in Laravel

```php
// app/Events/InventoryUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $inventory;

    public function __construct($inventory)
    {
        $this->inventory = $inventory;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('inventory.updates');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->inventory->id,
            'product_id' => $this->inventory->product_id,
            'warehouse_id' => $this->inventory->warehouse_id,
            'quantity' => $this->inventory->quantity,
            'message' => "تم تحديث مخزون المنتج {$this->inventory->product->name}",
        ];
    }
}
```

---

## 5. Composables مخصصة / Custom Composables

### useWarehouse

```javascript
// resources/js/Composables/useWarehouse.js
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useWarehouse() {
    const warehouses = ref([]);
    const loading = ref(false);
    const error = ref(null);

    async function fetchWarehouses(params = {}) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/admin/wms/warehouses', params, {
                preserveState: true,
            });
            warehouses.value = response.props.warehouses.data;
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    }

    async function createWarehouse(data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.post('/api/v1/admin/wms/warehouses', data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    return {
        warehouses,
        loading,
        error,
        fetchWarehouses,
        createWarehouse,
    };
}
```

### useNotification

```javascript
// resources/js/Composables/useNotification.js
import { ref } from 'vue';

export function useNotification() {
    const notifications = ref([]);

    function show(message, type = 'info') {
        const id = Date.now();
        notifications.value.push({ id, message, type });
        
        // إزالة الإشعار تلقائياً بعد 5 ثواني
        // Auto-remove notification after 5 seconds
        setTimeout(() => {
            remove(id);
        }, 5000);
    }

    function remove(id) {
        notifications.value = notifications.value.filter(n => n.id !== id);
    }

    function success(message) {
        show(message, 'success');
    }

    function error(message) {
        show(message, 'error');
    }

    function warning(message) {
        show(message, 'warning');
    }

    return {
        notifications,
        show,
        remove,
        success,
        error,
        warning,
    };
}
```

---

## 6. نصائح إضافية / Additional Tips

### التحقق من صحة البيانات / Validation

استفد من ميزة Inertia في عرض أخطاء التحقق من صحة البيانات الصادرة من الخادم مباشرة على النموذج.

Leverage Inertia's feature to display validation errors from the server directly on the form.

```vue
<script setup>
const form = useForm({
    name: '',
    email: '',
});

form.post('/users', {
    onError: (errors) => {
        // الأخطاء متاحة تلقائياً في form.errors
        // Errors automatically available in form.errors
        console.log(form.errors);
    },
});
</script>

<template>
    <form @submit.prevent="form.post('/users')">
        <input v-model="form.name" />
        <div v-if="form.errors.name">{{ form.errors.name }}</div>
    </form>
</template>
```

### معالجة الطلبات المتداخلة / Handling Overlapping Requests

استخدم خاصية `preventOverlappingRequests` لإلغاء الطلبات السابقة عند البحث السريع.

Use `preventOverlappingRequests` to cancel previous requests during fast searching.

```javascript
router.get('/search', { query: searchValue }, {
    preserveState: true,
    replace: true,
    preventOverlappingRequests: true,
});
```

### تحسين الأداء / Performance Optimization

استخدم `code-splitting` و `lazy loading` للمكونات الكبيرة.

Use `code-splitting` and `lazy loading` for large components.

```vue
<script setup>
import { defineAsyncComponent } from 'vue';

const HeavyComponent = defineAsyncComponent(() => 
    import('@/Components/HeavyComponent.vue')
);
</script>
```

---

## 7. دليل المطور السريع / Developer Quick-Start Guide

### إضافة صفحة جديدة / Adding a New Page

1. أنشئ ملف Vue في `resources/js/Pages/`
2. أضف المسار في Laravel
3. استخدم `Link` للتنقل

```vue
<!-- resources/js/Pages/Admin/WMS/NewPage.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    data: Object,
});
</script>

<template>
    <div>
        <h1>صفحة جديدة</h1>
        <Link href="/admin/wms">عودة</Link>
    </div>
</template>
```

### إضافة نموذج جديد / Adding a New Form

1. استخدم `useForm`
2. أضف حقول النموذج
3. عالج الأخطاء

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    field1: '',
    field2: '',
});

function submit() {
    form.post('/endpoint');
}
</script>
```

### إضافة جدول بيانات / Adding a Data Table

1. ثبت `@starfolksoftware/inertia-table`
2. استخدم مكون `Table`
3. قم بتخصيص القوالب

```vue
<script setup>
import { Table } from "@starfolksoftware/inertia-table";
defineProps({ data: Object });
</script>

<template>
    <Table :resource="data">
        <template #head>
            <tr>
                <th>العمود 1</th>
                <th>العمود 2</th>
            </tr>
        </template>
        <template #body="{ item }">
            <tr>
                <td>{{ item.field1 }}</td>
                <td>{{ item.field2 }}</td>
            </tr>
        </template>
    </Table>
</template>
```

---

## الخلاصة / Conclusion

هذا الدليل يوفر أساساً قوياً لتطوير واجهات ERP تفاعلية باستخدام Vue.js و Inertia.js. باتباع هذه الممارسات، يمكن للفريق بناء واجهات مستخدم سلسة وسريعة مع الحفاظ على كود منظم وقابل للصيانة.

This guide provides a strong foundation for developing interactive ERP interfaces using Vue.js and Inertia.js. By following these practices, the team can build smooth and fast user interfaces while maintaining organized and maintainable code.

---

**تاريخ الإنشاء:** 7 أغسطس 2026  
**الإصدار:** 1.0  
**الحالة:** جاهز للاستخدام
