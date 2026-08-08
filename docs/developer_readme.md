# دليل المطور - ERP UI Development Guide
# دليل المطور - واجهات ERP التفاعلية

## نظرة عامة / Overview
هذا الدليل يشرح كيفية استخدام المكونات والمكتبات التي تم إنشاؤها لتطوير واجهات ERP تفاعلية باستخدام Vue.js 3 و Inertia.js.

This guide explains how to use the components and libraries created for developing interactive ERP interfaces using Vue.js 3 and Inertia.js.

---

## التثبيت / Installation

### 1. تثبيت المكتبات المطلوبة / Install Required Libraries

```bash
npm install @inertiajs/vue3 @starfolksoftware/inertia-table portal-vue pinia laravel-echo pusher-js
```

### 2. إعداد Tailwind CSS / Setup Tailwind CSS

أضف مسارات المكتبات إلى `tailwind.config.js`:

Add library paths to `tailwind.config.js`:

```javascript
module.exports = {
    content: [
        './resources/js/**/*.vue',
        './node_modules/@starfolksoftware/inertia-table/src/**/*.vue',
    ],
    // ...
}
```

### 3. إعداد Portal / Setup Portal

في `resources/js/app.js`:

In `resources/js/app.js`:

```javascript
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { PortalTarget } from 'portal-vue';
import { createPinia } from 'pinia';

createInertiaApp({
    resolve: (name) => require(`./Pages/${name}.vue`),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        const pinia = createPinia();
        
        app.use(plugin);
        app.use(pinia);
        app.component('PortalTarget', PortalTarget);
        app.mount(el);
    },
});
```

---

## المكونات القابلة لإعادة الاستخدام / Reusable Components

### 1. Modal (النوافذ المنبثقة)

**الموقع:** `resources/js/Components/Common/Modal.vue`

**الاستخدام:**

```vue
<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Common/Modal.vue';

const showModal = ref(false);
</script>

<template>
    <div>
        <button @click="showModal = true">فتح النافذة</button>
        
        <Modal :show="showModal" @close="showModal = false" max-width="2xl">
            <div class="p-6">
                <h2>عنوان النافذة</h2>
                <p>محتوى النافذة</p>
            </div>
        </Modal>
    </div>
</template>
```

**الخصائص:**

- `show` (Boolean): عرض/إخفاء النافذة
- `maxWidth` (String): العرض الأقصى (sm, md, lg, 2xl, 3xl, 4xl, 5xl, 6xl, full)
- `closeable` (Boolean): إمكانية الإغلاق

---

### 2. Alert (الإشعارات)

**الموقع:** `resources/js/Components/Common/Alert.vue`

**الاستخدام:**

```vue
<script setup>
import { ref } from 'vue';
import Alert from '@/Components/Common/Alert.vue';

const showAlert = ref(true);
</script>

<template>
    <Alert 
        :show="showAlert" 
        type="success" 
        message="تم الحفظ بنجاح!"
        @close="showAlert = false"
    />
</template>
```

**الخصائص:**

- `show` (Boolean): عرض/إخفاء الإشعار
- `type` (String): النوع (success, error, warning, info)
- `message` (String): رسالة الإشعار
- `duration` (Number): مدة العرض بالمللي ثانية (الافتراضي: 5000)
- `position` (String): الموقع (top-right, top-left, bottom-right, bottom-left, top-center, bottom-center)

---

### 3. LoadingSpinner (مؤشر التحميل)

**الموقع:** `resources/js/Components/Common/LoadingSpinner.vue`

**الاستخدام:**

```vue
<script setup>
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue';
import { ref } from 'vue';

const loading = ref(true);
</script>

<template>
    <div>
        <LoadingSpinner v-if="loading" size="lg" color="blue" />
    </div>
</template>
```

**الخصائص:**

- `size` (String): الحجم (sm, md, lg, xl)
- `color` (String): اللون (blue, green, red, yellow, gray)

---

### 4. StatusBadge (شارة الحالة)

**الموقع:** `resources/js/Components/Common/StatusBadge.vue`

**الاستخدام:**

```vue
<script setup>
import StatusBadge from '@/Components/Common/StatusBadge.vue';
</script>

<template>
    <div>
        <StatusBadge status="active" />
        <StatusBadge status="low_stock" />
        <StatusBadge status="linked" />
    </div>
</template>
```

**الحالات المدعومة:**

- `active`, `inactive`, `featured`, `out_of_stock`, `low_stock`
- `primary`, `secondary`
- `linked`, `unlinked`, `pending`
- `inbound`, `outbound`, `adjustment`, `transfer`

---

### 5. SearchInput (حقل البحث)

**الموقع:** `resources/js/Components/Common/SearchInput.vue`

**الاستخدام:**

```vue
<script setup>
import { ref } from 'vue';
import SearchInput from '@/Components/Common/SearchInput.vue';

const searchQuery = ref('');

function handleSearch(query) {
    console.log('Searching:', query);
}
</script>

<template>
    <SearchInput 
        v-model="searchQuery" 
        placeholder="بحث عن منتج..."
        @search="handleSearch"
    />
</template>
```

**الخصائص:**

- `modelValue` (String): قيمة البحث
- `placeholder` (String): نص العنصر النائب
- `debounceDelay` (Number): تأخير البحث بالمللي ثانية (الافتراضي: 300)

---

### 6. ConfirmDialog (حوار التأكيد)

**الموقع:** `resources/js/Components/Common/ConfirmDialog.vue`

**الاستخدام:**

```vue
<script setup>
import { ref } from 'vue';
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue';

const showConfirm = ref(false);

function handleConfirm() {
    console.log('Confirmed');
    showConfirm.value = false;
}
</script>

<template>
    <div>
        <button @click="showConfirm = true">حذف</button>
        
        <ConfirmDialog
            :show="showConfirm"
            title="تأكيد الحذف"
            message="هل أنت متأكد من حذف هذا العنصر؟"
            type="danger"
            @confirm="handleConfirm"
            @cancel="showConfirm = false"
        />
    </div>
</template>
```

**الخصائص:**

- `show` (Boolean): عرض/إخفاء الحوار
- `title` (String): عنوان الحوار
- `message` (String): رسالة التأكيد
- `confirmText` (String): نص زر التأكيد
- `cancelText` (String): نص زر الإلغاء
- `type` (String): النوع (danger, warning, info)

---

### 7. Pagination (الترقيم)

**الموقع:** `resources/js/Components/Common/Pagination.vue`

**الاستخدام:**

```vue
<script setup>
import Pagination from '@/Components/Common/Pagination.vue';

defineProps({
    products: Object,
});
</script>

<template>
    <div>
        <!-- جدول البيانات -->
        
        <Pagination :links="products.links" />
    </div>
</template>
```

---

## النماذج / Forms

### 1. ProductForm (نموذج المنتج)

**الموقع:** `resources/js/Components/Forms/ProductForm.vue`

**الاستخدام:**

```vue
<script setup>
import { ref } from 'vue';
import ProductForm from '@/Components/Forms/ProductForm.vue';
import Modal from '@/Components/Common/Modal.vue';

const showProductModal = ref(false);
const categories = ref([]);
const units = ref(['piece', 'kg', 'liter']);

function handleSuccess() {
    showProductModal.value = false;
    // تحديث القائمة
}
</script>

<template>
    <Modal :show="showProductModal" @close="showProductModal = false">
        <ProductForm 
            :categories="categories"
            :units="units"
            @success="handleSuccess"
            @cancel="showProductModal = false"
        />
    </Modal>
</template>
```

---

### 2. WarehouseForm (نموذج المستودع)

**الموقع:** `resources/js/Components/Forms/WarehouseForm.vue`

**الاستخدام:**

```vue
<script setup>
import WarehouseForm from '@/Components/Forms/WarehouseForm.vue';
import Modal from '@/Components/Common/Modal.vue';

const showWarehouseModal = ref(false);
const users = ref([]);

function handleSuccess() {
    showWarehouseModal.value = false;
}
</script>

<template>
    <Modal :show="showWarehouseModal" @close="showWarehouseModal = false">
        <WarehouseForm 
            :users="users"
            @success="handleSuccess"
            @cancel="showWarehouseModal = false"
        />
    </Modal>
</template>
```

---

### 3. AssignmentForm (نموذج الربط)

**الموقع:** `resources/js/Components/Forms/AssignmentForm.vue`

**الاستخدام:**

```vue
<script setup>
import AssignmentForm from '@/Components/Forms/AssignmentForm.vue';

const products = ref([]);
const warehouses = ref([]);
const suppliers = ref([]);
const bins = ref([]);

function handleSuccess() {
    // الانتقال لقائمة المنتجات
    router.visit('/products');
}
</script>

<template>
    <AssignmentForm 
        :products="products"
        :warehouses="warehouses"
        :suppliers="suppliers"
        :bins="bins"
        @success="handleSuccess"
        @cancel="router.visit('/products')"
    />
</template>
```

---

## Composables

### 1. useStock (إدارة الأرصدة)

**الموقع:** `resources/js/Composables/useStock.js`

**الاستخدام:**

```vue
<script setup>
import { useStock } from '@/Composables/useStock';

const {
    balances,
    movements,
    loading,
    fetchBalances,
    fetchBalance,
    addMovement,
    getBalanceStatus,
    canWithdraw,
} = useStock();

// جلب الأرصدة
onMounted(() => {
    fetchBalances();
});

// إضافة حركة
async function handleAddMovement(data) {
    const success = await addMovement(data);
    if (success) {
        // تحديث البيانات
    }
}
</script>
```

**الوظائف:**

- `fetchBalances(params)`: جلب الأرصدة
- `fetchBalance(productId, warehouseId)`: جلب رصيد محدد
- `fetchMovements(params)`: جلب الحركات
- `addMovement(data)`: إضافة حركة
- `getBalanceStatus(balance)`: الحصول على حالة الرصيد
- `canWithdraw(balance, quantity)`: التحقق من إمكانية الصرف
- `calculateExpectedBalance(balance, type, quantity)`: حساب الرصيد المتوقع

---

### 2. useWarehouse (إدارة المستودعات)

**الموقع:** `resources/js/Composables/useWarehouse.js`

**الاستخدام:**

```vue
<script setup>
import { useWarehouse } from '@/Composables/useWarehouse';

const {
    warehouses,
    loading,
    fetchWarehouses,
    createWarehouse,
    updateWarehouse,
    deleteWarehouse,
    getActiveWarehouses,
    getPrimaryWarehouse,
} = useWarehouse();

onMounted(() => {
    fetchWarehouses();
});
</script>
```

**الوظائف:**

- `fetchWarehouses(params)`: جلب المستودعات
- `fetchWarehouse(id)`: جلب مستودع محدد
- `createWarehouse(data)`: إنشاء مستودع
- `updateWarehouse(id, data)`: تحديث مستودع
- `deleteWarehouse(id)`: حذف مستودع
- `getActiveWarehouses()`: الحصول على المستودعات النشطة
- `getPrimaryWarehouse()`: الحصول على المستودع الرئيسي

---

### 3. useProduct (إدارة المنتجات)

**الموقع:** `resources/js/Composables/useProduct.js`

**الاستخدام:**

```vue
<script setup>
import { useProduct } from '@/Composables/useProduct';

const {
    products,
    loading,
    fetchProducts,
    createProduct,
    updateProduct,
    searchProducts,
    getProductStatus,
} = useProduct();

onMounted(() => {
    fetchProducts();
});
</script>
```

**الوظائف:**

- `fetchProducts(params)`: جلب المنتجات
- `fetchProduct(id)`: جلب منتج محدد
- `createProduct(data)`: إنشاء منتج
- `updateProduct(id, data)`: تحديث منتج
- `deleteProduct(id)`: حذف منتج
- `searchProducts(query)`: البحث عن منتجات
- `getConsumptionStats(productId)`: الحصول على إحصائيات الاستهلاك
- `getProductStatus(product)`: الحصول على حالة المنتج

---

### 4. useNotification (إدارة الإشعارات)

**الموقع:** `resources/js/Composables/useNotification.js`

**الاستخدام:**

```vue
<script setup>
import { useNotification } from '@/Composables/useNotification';

const {
    notifications,
    success,
    error,
    warning,
    info,
    clear,
} = useNotification();

function handleSave() {
    success('تم الحفظ بنجاح!');
}

function handleError() {
    error('حدث خطأ أثناء الحفظ');
}
</script>

<template>
    <div>
        <button @click="handleSave">حفظ</button>
        
        <!-- عرض الإشعارات -->
        <div class="fixed top-4 right-4 space-y-2">
            <Alert
                v-for="notif in notifications"
                :key="notif.id"
                :show="true"
                :type="notif.type"
                :message="notif.message"
                @close="remove(notif.id)"
            />
        </div>
    </div>
</template>
```

**الوظائف:**

- `success(message, duration)`: إشعار نجاح
- `error(message, duration)`: إشعار خطأ
- `warning(message, duration)`: إشعار تحذير
- `info(message, duration)`: إشعار معلومات
- `persistent(message, type)`: إشعار دائم
- `clear()`: مسح جميع الإشعارات

---

## Pinia Stores

### 1. userStore (حالة المستخدم)

**الموقع:** `resources/js/Stores/userStore.js`

**الاستخدام:**

```vue
<script setup>
import { useUserStore } from '@/Stores/userStore';

const userStore = useUserStore();

// التحقق من الصلاحيات
if (userStore.hasPermission('create_product')) {
    // عرض زر إنشاء منتج
}

// التحقق من الأدوار
if (userStore.hasRole('admin')) {
    // عرض إعدادات المشرف
}
</script>
```

**الوظائف:**

- `setUser(userData)`: تعيين بيانات المستخدم
- `setPermissions(permissions)`: تعيين الصلاحيات
- `setRoles(roles)`: تعيين الأدوار
- `hasPermission(permission)`: التحقق من صلاحية
- `hasRole(role)`: التحقق من دور
- `hasAnyPermission(permissions)`: التحقق من أي صلاحية
- `hasAllPermissions(permissions)`: التحقق من جميع الصلاحيات
- `clearUser()`: مسح بيانات المستخدم

---

### 2. warehouseStore (حالة المستودعات)

**الموقع:** `resources/js/Stores/warehouseStore.js`

**الاستخدام:**

```vue
<script setup>
import { useWarehouseStore } from '@/Stores/warehouseStore';

const warehouseStore = useWarehouseStore();

// الحصول على المستودعات النشطة
const activeWarehouses = warehouseStore.getActiveWarehouses();

// الحصول على المستودع الرئيسي
const primaryWarehouse = warehouseStore.getPrimaryWarehouse();
</script>
```

**الوظائف:**

- `setWarehouses(warehouses)`: تعيين قائمة المستودعات
- `addWarehouse(warehouse)`: إضافة مستودع
- `updateWarehouse(warehouse)`: تحديث مستودع
- `removeWarehouse(id)`: حذف مستودع
- `getWarehouseById(id)`: الحصول على مستودع بالمعرف
- `getActiveWarehouses()`: الحصول على المستودعات النشطة
- `getPrimaryWarehouse()`: الحصول على المستودع الرئيسي
- `setPrimaryWarehouse(id)`: تعيين المستودع الرئيسي

---

## إضافة صفحة جديدة / Adding a New Page

### 1. إنشاء ملف الصفحة / Create Page File

```vue
<!-- resources/js/Pages/Admin/WMS/NewPage.vue -->
<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    data: Object,
});
</script>

<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">صفحة جديدة</h1>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <p>{{ data.message }}</p>
        </div>
        
        <Link href="/admin/wms" class="mt-4 inline-block text-blue-600">
            عودة
        </Link>
    </div>
</template>
```

### 2. إضافة المسار في Laravel / Add Route in Laravel

```php
// routes/web.php
Route::get('/admin/wms/new-page', function () {
    return Inertia::render('Admin/WMS/NewPage', [
        'data' => ['message' => 'مرحباً بالعالم!'],
    ]);
})->name('admin.wms.new-page');
```

---

## استخدام useForm / Using useForm

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
});

function submit() {
    form.post('/users', {
        onSuccess: () => {
            // نجاح
            form.reset();
        },
        onError: (errors) => {
            // أخطاء - تظهر تلقائياً في form.errors
            console.log(errors);
        },
    });
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" />
        <div v-if="form.errors.name">{{ form.errors.name }}</div>
        
        <input v-model="form.email" />
        <div v-if="form.errors.email">{{ form.errors.email }}</div>
        
        <button type="submit" :disabled="form.processing">
            {{ form.processing ? 'جاري...' : 'حفظ' }}
        </button>
    </form>
</template>
```

---

## إعداد Laravel Echo / Setting Up Laravel Echo

### 1. تثبيت المكتبات / Install Libraries

```bash
npm install laravel-echo pusher-js
```

### 2. إعداد Echo / Setup Echo

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

### 3. الاستماع للأحداث / Listen to Events

```vue
<script setup>
import { onMounted, onUnmounted } from 'vue';

onMounted(() => {
    // الاستماع لتحديثات المخزون
    window.Echo.private(`warehouse.${warehouseId}`)
        .listen('StockUpdated', (e) => {
            console.log('Stock updated:', e);
            // تحديث البيانات
        });
});

onUnmounted(() => {
    window.Echo.leave(`warehouse.${warehouseId}`);
});
</script>
```

---

## نصائح الأداء / Performance Tips

### 1. استخدام v-memo / Using v-memo

```vue
<template>
    <div v-memo="[expensiveData]">
        <HeavyComponent :data="expensiveData" />
    </div>
</template>
```

### 2. استخدام v-once / Using v-once

```vue
<template>
    <div v-once>
        <p>هذا النص لن يتم إعادة عرضه</p>
    </div>
</template>
```

### 3. منع الطلبات المتداخلة / Prevent Overlapping Requests

```javascript
router.get('/search', { query: searchValue }, {
    preserveState: true,
    replace: true,
    preventOverlappingRequests: true,
});
```

---

## دعم الترجمة / i18n Support

### إعداد Vue I18n / Setup Vue I18n

```javascript
// resources/js/app.js
import { createI18n } from 'vue-i18n';

const i18n = createI18n({
    locale: 'ar',
    fallbackLocale: 'en',
    messages: {
        ar: {
            products: 'المنتجات',
            warehouses: 'المستودعات',
        },
        en: {
            products: 'Products',
            warehouses: 'Warehouses',
        },
    },
});

app.use(i18n);
```

### الاستخدام / Usage

```vue
<template>
    <div>{{ $t('products') }}</div>
</template>
```

---

## استكشاف الأخطاء / Troubleshooting

### المشكلة: Modal لا تظهر / Problem: Modal Not Showing

**الحل:** تأكد من إضافة `<PortalTarget />` في التخطيط الرئيسي.

**Solution:** Ensure `<PortalTarget />` is added to the main layout.

```vue
<!-- resources/js/Components/Layouts/AppLayout.vue -->
<template>
    <div>
        <header>...</header>
        <main><slot /></main>
        <footer>...</footer>
        <PortalTarget name="modal" />
    </div>
</template>
```

### المشكلة: useForm لا يعمل / Problem: useForm Not Working

**الحل:** تأكد من استيراد useForm من @inertiajs/vue3.

**Solution:** Ensure useForm is imported from @inertiajs/vue3.

```javascript
import { useForm } from '@inertiajs/vue3';
```

### المشكلة: Laravel Echo لا يعمل / Problem: Laravel Echo Not Working

**الحل:** تأكد من إعدادات PUSHER في ملف .env.

**Solution:** Ensure PUSHER settings in .env file.

```env
PUSHER_APP_KEY=your-app-key
PUSHER_APP_CLUSTER=your-cluster
VITE_PUSHER_APP_KEY=${PUSHER_APP_KEY}
VITE_PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER}
```

---

## الموارد الإضافية / Additional Resources

- [Vue.js Documentation](https://vuejs.org/)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Laravel Documentation](https://laravel.com/docs)

---

**تاريخ الإنشاء:** 7 أغسطس 2026  
**الإصدار:** 1.0  
**الحالة:** جاهز للاستخدام
