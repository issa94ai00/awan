<!-- resources/js/views/admin/wms/Stock/Organization.vue -->
<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

const { t } = useI18n();

const router = useRouter();

// State management
const loading = ref(false);
const products = ref([]);
const warehouses = ref([]);
const unassignedProducts = ref([]);
const assignedProducts = ref([]);
const error = ref(null);
const refreshing = ref(false);

const selectedWarehouse = ref(null);
const searchQuery = ref('');
const showAssignModal = ref(false);
const selectedProduct = ref(null);
const assigning = ref(false);

// Statistics
const stats = computed(() => {
    return {
        total: products.value.length,
        assigned: assignedProducts.value.length,
        unassigned: unassignedProducts.value.length
    };
});

// جلب البيانات مع معالجة أخطاء محسنة
async function fetchData() {
    loading.value = true;
    error.value = null;
    
    try {
        const [productsRes, warehousesRes] = await Promise.all([
            axios.get('/api/v1/admin/wms/products'),
            axios.get('/api/v1/admin/wms/warehouses')
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
        
        // تصنيف المنتجات
        categorizeProducts();
    } catch (err) {
        console.error('Error fetching data:', err);
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_data_short');
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

function categorizeProducts() {
    if (!Array.isArray(products.value)) {
        unassignedProducts.value = [];
        assignedProducts.value = [];
        return;
    }
    
    unassignedProducts.value = products.value.filter(p => p.warehouses_count === 0);
    assignedProducts.value = products.value.filter(p => p.warehouses_count > 0);
}

// المنتجات المفلترة مع التحقق من البيانات
const filteredUnassigned = computed(() => {
    if (!Array.isArray(unassignedProducts.value)) return [];
    
    if (!searchQuery.value) return unassignedProducts.value;
    
    const query = searchQuery.value.toLowerCase();
    return unassignedProducts.value.filter(p => 
        (p.name && p.name.toLowerCase().includes(query)) ||
        (p.code && p.code.toLowerCase().includes(query))
    );
});

const filteredAssigned = computed(() => {
    if (!Array.isArray(assignedProducts.value)) return [];
    
    if (!searchQuery.value) return assignedProducts.value;
    
    const query = searchQuery.value.toLowerCase();
    return assignedProducts.value.filter(p => 
        (p.name && p.name.toLowerCase().includes(query)) ||
        (p.code && p.code.toLowerCase().includes(query))
    );
});

// تحديث البيانات
async function refreshData() {
    refreshing.value = true;
    await fetchData();
    refreshing.value = false;
    ElMessage.success(t('data_updated_successfully'));
}

// فتح Modal ربط مع التحقق
function openAssignModal(product) {
    if (!product || !product.id) {
        ElMessage.error(t('invalid_product_data'));
        return;
    }
    
    selectedProduct.value = product;
    selectedWarehouse.value = null;
    showAssignModal.value = true;
}

function closeAssignModal() {
    showAssignModal.value = false;
    selectedProduct.value = null;
    selectedWarehouse.value = null;
}

// ربط المنتج بالمستودع مع معالجة أخطاء محسنة
async function assignToWarehouse() {
    if (!selectedProduct.value || !selectedProduct.value.id) {
        ElMessage.error(t('invalid_product_data'));
        return;
    }
    
    if (!selectedWarehouse.value) {
        ElMessage.warning(t('please_choose_warehouse'));
        return;
    }
    
    assigning.value = true;
    try {
        const response = await axios.post('/api/v1/admin/wms/assignments', {
            product_id: selectedProduct.value.id,
            warehouse_id: selectedWarehouse.value,
            quantity: 0,
            min_stock: 10,
            max_stock: 1000,
            safety_stock: 5,
        });
        
        if (!response.data) {
            throw new Error('Invalid response from server');
        }
        
        ElMessage.success(t('product_linked_to_warehouse'));
        closeAssignModal();
        await fetchData();
    } catch (err) {
        console.error('Error assigning product:', err);
        const errorMsg = err.response?.data?.message || err.message || t('failed_to_link_product');
        ElMessage.error(errorMsg);
    } finally {
        assigning.value = false;
    }
}

// التنقل مع التحقق
function goToProductDetails(productId) {
    if (!productId) {
        ElMessage.error(t('invalid_product_id'));
        return;
    }
    router.push(`/admin/wms/products/${productId}`);
}

function goToAssignment(productId) {
    if (!productId) {
        ElMessage.error(t('invalid_product_id'));
        return;
    }
    router.push(`/admin/wms/products/${productId}/assign`);
}

// تنسيق الأرقام
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return formatCount(num);
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $t('stock_organization') }}</h1>
                <p class="text-gray-600 mt-1">{{ $t('stock_organization_subtitle') }}</p>
            </div>
            <button 
                @click="refreshData"
                :disabled="refreshing"
                class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50 transition-all"
            >
                <span v-if="refreshing" class="animate-spin">⟳</span>
                <span v-else>↻</span>
                {{ $t('update') }}
            </button>
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
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

        <!-- Search Bar -->
        <div class="mb-6">
            <input
                v-model="searchQuery"
                type="text"
                :placeholder="$t('search_by_name_or_code')"
                class="w-full md:w-96 border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('total_products') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">📦</span>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('linked_products') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.assigned) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">✓</span>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('unlinked_products') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.unassigned) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">○</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="flex flex-col items-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                <p class="mt-4 text-gray-600">{{ $t('loading_data') }}</p>
            </div>
        </div>

        <!-- Products Grid -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Unassigned Products -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="p-4 border-b bg-yellow-50">
                    <h2 class="text-lg font-bold text-gray-800">{{ $t('unlinked_products') }}</h2>
                    <p class="text-sm text-gray-600">{{ formatNumber(filteredUnassigned.length) }} منتج</p>
                </div>
                <div class="p-4 max-h-96 overflow-y-auto">
                    <div v-if="filteredUnassigned.length === 0" class="text-center py-8">
                        <span class="text-4xl mb-2">📭</span>
                        <p class="text-gray-500">{{ $t('no_unlinked_products') }}</p>
                    </div>
                    <div 
                        v-for="product in filteredUnassigned" 
                        :key="product.id"
                        class="p-4 border-b hover:bg-gray-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-900">{{ product.name }}</h3>
                                <p class="text-sm text-gray-600">الكود: {{ product.code }}</p>
                                <p class="text-sm text-gray-600">الفئة: {{ product.category?.name || '-' }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="openAssignModal(product)"
                                    class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors"
                                >
                                    {{ $t('link_action') }}
                                </button>
                                <button
                                    @click="goToAssignment(product.id)"
                                    class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition-colors"
                                >
                                    {{ $t('details') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Products -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="p-4 border-b bg-green-50">
                    <h2 class="text-lg font-bold text-gray-800">{{ $t('linked_products') }}</h2>
                    <p class="text-sm text-gray-600">{{ formatNumber(filteredAssigned.length) }} منتج</p>
                </div>
                <div class="p-4 max-h-96 overflow-y-auto">
                    <div v-if="filteredAssigned.length === 0" class="text-center py-8">
                        <span class="text-4xl mb-2">📭</span>
                        <p class="text-gray-500">{{ $t('no_linked_products') }}</p>
                    </div>
                    <div 
                        v-for="product in filteredAssigned" 
                        :key="product.id"
                        class="p-4 border-b hover:bg-gray-50 cursor-pointer transition-colors"
                        @click="goToProductDetails(product.id)"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-900">{{ product.name }}</h3>
                                <p class="text-sm text-gray-600">الكود: {{ product.code }}</p>
                                <p class="text-sm text-gray-600">المستودعات: {{ formatNumber(product.warehouses_count) }}</p>
                                <p class="text-sm text-gray-600">إجمالي الرصيد: {{ formatNumber(product.total_balance) }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click.stop="goToAssignment(product.id)"
                                    class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition-colors"
                                >
                                    {{ $t('edit_action') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" @click="closeAssignModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">{{ $t('link_product_to_warehouse') }}</h3>
                    <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div v-if="selectedProduct" class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-700"><span class="font-medium">{{ $t('product_label') }}</span> {{ selectedProduct.name }}</p>
                    <p class="text-sm text-gray-700"><span class="font-medium">{{ $t('code_label') }}</span> {{ selectedProduct.code }}</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('choose_warehouse') }}</label>
                    <select
                        v-model="selectedWarehouse"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    >
                        <option value="">{{ $t('choose_warehouse_placeholder') }}</option>
                        <option 
                            v-for="warehouse in warehouses" 
                            :key="warehouse.id" 
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }} ({{ warehouse.code }})
                        </option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button
                        @click="closeAssignModal"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
                        :disabled="assigning"
                    >
                        {{ $t('cancel') }}
                    </button>
                    <button
                        @click="assignToWarehouse"
                        :disabled="!selectedWarehouse || assigning"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                    >
                        <span v-if="assigning" class="animate-spin">⟳</span>
                        {{ assigning ? 'جاري الربط...' : 'ربط' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
