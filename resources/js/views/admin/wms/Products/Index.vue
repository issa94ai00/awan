<!-- resources/js/views/admin/wms/Products/Index.vue -->
<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

const router = useRouter();

// State management
const loading = ref(true);
const products = ref([]);
const categories = ref([]);
const searchQuery = ref('');
const categoryFilter = ref('');
const statusFilter = ref('');
const showAddModal = ref(false);
const selectedProduct = ref(null);
const error = ref(null);
const refreshing = ref(false);

// Statistics
const stats = computed(() => {
    const total = products.value.length;
    const linked = products.value.filter(p => p.warehouses_count > 0).length;
    const unlinked = total - linked;
    const lowStock = products.value.filter(p => p.total_balance <= p.min_stock).length;
    
    return { total, linked, unlinked, lowStock };
});

// جلب البيانات مع معالجة أخطاء محسنة
async function fetchProducts() {
    loading.value = true;
    error.value = null;
    
    try {
        const params = {};
        if (searchQuery.value) params.search = searchQuery.value;
        if (categoryFilter.value) params.category = categoryFilter.value;

        const response = await axios.get('/api/v1/admin/wms/products', { params });
        
        // التحقق من صحة البيانات
        if (!response.data || !Array.isArray(response.data.data)) {
            throw new Error('Invalid data format received from server');
        }
        
        products.value = response.data.data;
    } catch (err) {
        console.error('Error fetching products:', err);
        error.value = err.response?.data?.message || err.message || 'فشل في جلب البيانات';
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

async function fetchCategories() {
    try {
        const response = await axios.get('/api/v1/categories');
        
        if (!response.data || !Array.isArray(response.data.data)) {
            throw new Error('Invalid categories data format');
        }
        
        categories.value = response.data.data;
    } catch (err) {
        console.error('Error fetching categories:', err);
        ElMessage.warning('فشل في جلب الفئات');
    }
}

// تحديث البيانات
async function refreshData() {
    refreshing.value = true;
    await Promise.all([fetchProducts(), fetchCategories()]);
    refreshing.value = false;
    ElMessage.success('تم تحديث البيانات بنجاح');
}

onMounted(() => {
    fetchProducts();
    fetchCategories();
});

// البحث مع debounce محسن
let searchTimeout;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchProducts();
    }, 300);
}

// تصفية حسب الفئة
function handleCategoryChange() {
    fetchProducts();
}

// تصفية حسب الحالة
function handleStatusChange() {
    fetchProducts();
}

// حالة المنتج مع التحقق من البيانات
function getProductStatus(product) {
    // التحقق من سلامة البيانات
    if (!product) return 'error';
    
    const warehousesCount = Number(product.warehouses_count) || 0;
    const totalBalance = Number(product.total_balance) || 0;
    const minStock = Number(product.min_stock) || 0;
    
    if (warehousesCount === 0) return 'unlinked';
    if (totalBalance <= minStock) return 'low_stock';
    return 'ok';
}

// المنتجات المفلترة مع التحقق من البيانات
const filteredProducts = computed(() => {
    if (!Array.isArray(products.value)) return [];
    
    let result = [...products.value];
    
    if (statusFilter.value === 'linked') {
        result = result.filter(p => p.warehouses_count > 0);
    } else if (statusFilter.value === 'unlinked') {
        result = result.filter(p => p.warehouses_count === 0);
    } else if (statusFilter.value === 'low_stock') {
        result = result.filter(p => p.total_balance <= p.min_stock);
    }
    
    return result;
});

// فتح Modal إضافة منتج
function openAddModal() {
    selectedProduct.value = null;
    showAddModal.value = true;
}

// فتح Modal تعديل منتج
function openEditModal(product) {
    if (!product || !product.id) {
        ElMessage.error('بيانات المنتج غير صالحة');
        return;
    }
    selectedProduct.value = product;
    showAddModal.value = true;
}

// الانتقال لشاشة الربط مع التحقق
function goToAssignment(productId) {
    if (!productId) {
        ElMessage.error('معرف المنتج غير صالح');
        return;
    }
    router.push(`/admin/wms/products/${productId}/assign`);
}

// الانتقال لشاشة العرض مع التحقق
function goToProduct(productId) {
    if (!productId) {
        ElMessage.error('معرف المنتج غير صالح');
        return;
    }
    router.push(`/admin/wms/products/${productId}`);
}

// حذف منتج مع تأكيد محسن
async function deleteProduct(productId) {
    if (!productId) {
        ElMessage.error('معرف المنتج غير صالح');
        return;
    }

    try {
        await ElMessageBox.confirm(
            'هل أنت متأكد من حذف هذا المنتج؟ لا يمكن التراجع عن هذا الإجراء.',
            'تأكيد الحذف',
            {
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                type: 'warning',
                confirmButtonClass: 'el-button--danger'
            }
        );

        await axios.delete(`/api/v1/admin/wms/products/${productId}`);
        ElMessage.success('تم حذف المنتج بنجاح');
        fetchProducts();
    } catch (err) {
        if (err !== 'cancel') {
            console.error('Error deleting product:', err);
            const errorMsg = err.response?.data?.message || err.message || 'فشل في حذف المنتج';
            ElMessage.error(errorMsg);
        }
    }
}

// النجاح
function handleSuccess() {
    showAddModal.value = false;
    fetchProducts();
    ElMessage.success('تم الحفظ بنجاح');
}

// الإلغاء
function handleCancel() {
    showAddModal.value = false;
    selectedProduct.value = null;
}

// تنسيق الأرقام
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return Number(num).toLocaleString('ar-SA');
}

// تنسيق السعر
function formatPrice(price) {
    if (price === null || price === undefined) return '-';
    return Number(price).toFixed(2);
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">إدارة المنتجات</h1>
                <p class="text-gray-600 mt-1">إدارة المنتجات وربطها بالمستودعات</p>
            </div>
            <div class="flex gap-3">
                <button 
                    @click="refreshData"
                    :disabled="refreshing"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50 transition-all"
                >
                    <span v-if="refreshing" class="animate-spin">⟳</span>
                    <span v-else>↻</span>
                    تحديث
                </button>
                <button 
                    @click="openAddModal"
                    class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all"
                >
                    <span>+</span>
                    إضافة منتج جديد
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">إجمالي المنتجات</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">📦</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">مرتبطة بمستودع</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.linked) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">✓</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">غير مرتبطة</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.unlinked) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">○</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">مخزون منخفض</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.lowStock) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">⚠</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">بحث</label>
                    <input 
                        v-model="searchQuery"
                        @input="handleSearch"
                        type="text"
                        placeholder="بحث بالكود أو الاسم..."
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                    <select 
                        v-model="categoryFilter"
                        @change="handleCategoryChange"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    >
                        <option value="">جميع الفئات</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select 
                        v-model="statusFilter"
                        @change="handleStatusChange"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    >
                        <option value="">جميع الحالات</option>
                        <option value="linked">مرتبط بمستودع</option>
                        <option value="unlinked">غير مرتبط</option>
                        <option value="low_stock">مخزون منخفض</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-right p-4 font-medium text-gray-700">الحالة</th>
                            <th class="text-right p-4 font-medium text-gray-700">الكود</th>
                            <th class="text-right p-4 font-medium text-gray-700">الاسم</th>
                            <th class="text-right p-4 font-medium text-gray-700">الفئة</th>
                            <th class="text-right p-4 font-medium text-gray-700">الوحدة</th>
                            <th class="text-right p-4 font-medium text-gray-700">المستودعات</th>
                            <th class="text-right p-4 font-medium text-gray-700">الرصيد</th>
                            <th class="text-right p-4 font-medium text-gray-700">السعر</th>
                            <th class="text-right p-4 font-medium text-gray-700">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="9" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                                    <p class="mt-4 text-gray-600">جاري تحميل البيانات...</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="error">
                            <td colspan="9" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-4xl mb-2">❌</span>
                                    <p class="text-red-600 font-medium">{{ error }}</p>
                                    <button @click="fetchProducts" class="mt-4 text-blue-600 hover:text-blue-700">
                                        إعادة المحاولة
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="filteredProducts.length === 0">
                            <td colspan="9" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-4xl mb-2">📭</span>
                                    <p class="text-gray-500">لا توجد منتجات</p>
                                </div>
                            </td>
                        </tr>
                        <tr 
                            v-for="product in filteredProducts" 
                            :key="product.id" 
                            class="border-b hover:bg-gray-50 transition-colors"
                        >
                            <td class="p-4">
                                <span 
                                    :class="{
                                        'bg-green-100 text-green-800': getProductStatus(product) === 'ok',
                                        'bg-red-100 text-red-800': getProductStatus(product) === 'low_stock',
                                        'bg-gray-100 text-gray-800': getProductStatus(product) === 'unlinked',
                                    }"
                                    class="px-3 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ getProductStatus(product) === 'ok' ? 'جيد' : getProductStatus(product) === 'low_stock' ? 'منخفض' : 'غير مرتبط' }}
                                </span>
                            </td>
                            <td class="p-4 font-mono text-sm">{{ product.code }}</td>
                            <td class="p-4">
                                <div class="font-medium text-gray-900">{{ product.name }}</div>
                                <div class="text-sm text-gray-500">{{ product.sku }}</div>
                            </td>
                            <td class="p-4 text-gray-700">{{ product.category?.name || '-' }}</td>
                            <td class="p-4 text-gray-700">{{ product.unit }}</td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ formatNumber(product.warehouses_count || 0) }}</span>
                                    <span 
                                        v-if="product.warehouses_count > 0"
                                        class="text-green-600"
                                    >
                                        ✓
                                    </span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span 
                                    :class="{
                                        'text-red-600 font-medium': product.total_balance <= product.min_stock,
                                        'text-green-600 font-medium': product.total_balance > product.min_stock,
                                    }"
                                >
                                    {{ formatNumber(product.total_balance || 0) }}
                                </span>
                            </td>
                            <td class="p-4 text-gray-700">{{ formatPrice(product.price) }}</td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <button 
                                        @click="goToProduct(product.id)"
                                        class="text-blue-600 hover:text-blue-700 text-sm font-medium transition-colors"
                                        title="عرض التفاصيل"
                                    >
                                        👁 عرض
                                    </button>
                                    <button 
                                        @click="openEditModal(product)"
                                        class="text-green-600 hover:text-green-700 text-sm font-medium transition-colors"
                                        title="تعديل"
                                    >
                                        ✎ تعديل
                                    </button>
                                    <button 
                                        @click="goToAssignment(product.id)"
                                        class="text-purple-600 hover:text-purple-700 text-sm font-medium transition-colors"
                                        title="ربط بمستودع"
                                    >
                                        🔗 ربط
                                    </button>
                                    <button 
                                        @click="deleteProduct(product.id)"
                                        class="text-red-600 hover:text-red-700 text-sm font-medium transition-colors"
                                        title="حذف"
                                    >
                                        🗑 حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal إضافة/تعديل منتج -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" @click="handleCancel"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">
                        {{ selectedProduct ? 'تعديل منتج' : 'إضافة منتج جديد' }}
                    </h2>
                    <button @click="handleCancel" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                <p class="text-gray-600 mb-4">استخدم نموذج المنتج الموجود في صفحة المنتجات الرئيسية</p>
                <div class="flex justify-end gap-4">
                    <button 
                        @click="handleCancel"
                        class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-all"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
