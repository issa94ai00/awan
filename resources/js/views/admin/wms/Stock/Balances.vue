<!-- resources/js/views/admin/wms/Stock/Balances.vue -->
<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api';

const { t } = useI18n();

const router = useRouter();

// State management
const loading = ref(true);
const products = ref([]);
const warehouses = ref([]);
const selectedProduct = ref(null);
const selectedWarehouse = ref(null);
const balance = ref(null);
const transactions = ref([]);
const showMovementModal = ref(false);
const submitting = ref(false);
const error = ref(null);
const refreshing = ref(false);

// فلاتر الحركات
const movementTypeFilter = ref('');
const dateFromFilter = ref('');
const dateToFilter = ref('');

const form = ref({
    product_id: null,
    warehouse_id: null,
    to_warehouse_id: null,
    movement_type: 'in',
    quantity: 0,
    reference_document: '',
    notes: '',
});

// معاينة الرصيد بعد الحركة مع التحقق من البيانات
const previewBalance = computed(() => {
    if (!balance.value) return 0;
    
    const availableQty = Number(balance.value.available_quantity) || 0;
    const qty = Number(form.value.quantity) || 0;
    
    if (form.value.movement_type === 'in' || form.value.movement_type === 'adjustment') {
        return availableQty + qty;
    } else if (form.value.movement_type === 'out' || form.value.movement_type === 'transfer') {
        return availableQty - qty;
    }
    return availableQty;
});

// حالة الرصيد مع التحقق من البيانات
const balanceStatus = computed(() => {
    if (!balance.value) return { color: 'gray', text: t('out_of_stock'), icon: '○' };
    
    const availableQty = Number(balance.value.available_quantity) || 0;
    const minStock = Number(balance.value.min_stock) || 0;
    const safetyStock = Number(balance.value.safety_stock) || 0;
    
    if (availableQty <= safetyStock) {
        return { color: 'red', text: t('very_low'), icon: '⚠' };
    } else if (availableQty <= minStock) {
        return { color: 'orange', text: t('low'), icon: '⚠' };
    } else {
        return { color: 'green', text: t('good'), icon: '✓' };
    }
});

// الحركات المفلترة مع التحقق من البيانات
const filteredTransactions = computed(() => {
    if (!Array.isArray(transactions.value)) return [];
    
    let result = [...transactions.value];
    
    if (movementTypeFilter.value) {
        result = result.filter(t => t.movement_type === movementTypeFilter.value);
    }
    
    if (dateFromFilter.value) {
        result = result.filter(t => t.created_at >= dateFromFilter.value);
    }
    
    if (dateToFilter.value) {
        result = result.filter(t => t.created_at <= dateToFilter.value);
    }
    
    return result;
});

// جلب البيانات مع معالجة أخطاء محسنة
async function fetchProducts() {
    try {
        const response = await api.get('/products');
        
        if (!response.data || !Array.isArray(response.data.data)) {
            throw new Error('Invalid products data format');
        }
        
        products.value = response.data.data;
    } catch (err) {
        console.error('Error fetching products:', err);
        ElMessage.warning(t('failed_to_fetch_products'));
    }
}

async function fetchWarehouses() {
    try {
        const response = await api.get('/admin/wms/warehouses');
        
        if (!response.data || !Array.isArray(response.data.data)) {
            throw new Error('Invalid warehouses data format');
        }
        
        warehouses.value = response.data.data;
    } catch (err) {
        console.error('Error fetching warehouses:', err);
        ElMessage.warning(t('failed_to_fetch_warehouses'));
    }
}

async function fetchBalance() {
    if (!selectedProduct.value || !selectedWarehouse.value) return;
    
    try {
        const response = await api.get('/admin/wms/stock/balance', {
            params: {
                product_id: selectedProduct.value.id,
                warehouse_id: selectedWarehouse.value.id,
            }
        });
        
        if (!response.data || !response.data.data) {
            throw new Error('Invalid balance data format');
        }
        
        balance.value = response.data.data;
        error.value = null;
    } catch (err) {
        console.error('Error fetching balance:', err);
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_balance');
        ElMessage.error(error.value);
    }
}

async function fetchTransactions() {
    if (!selectedProduct.value || !selectedWarehouse.value) return;
    
    try {
        const response = await api.get('/admin/wms/stock/transactions', {
            params: {
                product_id: selectedProduct.value.id,
                warehouse_id: selectedWarehouse.value.id,
            }
        });
        
        if (!response.data || !Array.isArray(response.data.data)) {
            throw new Error('Invalid transactions data format');
        }
        
        transactions.value = response.data.data;
    } catch (err) {
        console.error('Error fetching transactions:', err);
        ElMessage.warning(t('failed_to_fetch_movements'));
    }
}

// تحديث البيانات
async function refreshData() {
    refreshing.value = true;
    await Promise.all([fetchProducts(), fetchWarehouses()]);
    if (selectedProduct.value && selectedWarehouse.value) {
        await Promise.all([fetchBalance(), fetchTransactions()]);
    }
    refreshing.value = false;
    ElMessage.success(t('data_updated_successfully'));
}

onMounted(() => {
    fetchProducts();
    fetchWarehouses();
    loading.value = false;
});

// اختيار منتج ومستودع مع التحقق
function selectProductAndWarehouse() {
    if (!selectedProduct.value || !selectedWarehouse.value) {
        balance.value = null;
        transactions.value = [];
        return;
    }
    
    form.value.product_id = selectedProduct.value.id;
    form.value.warehouse_id = selectedWarehouse.value.id;
    fetchBalance();
    fetchTransactions();
}

// فتح Modal إضافة حركة مع التحقق
function openMovementModal() {
    if (!selectedProduct.value || !selectedWarehouse.value) {
        ElMessage.warning(t('choose_product_and_warehouse_first'));
        return;
    }
    
    selectProductAndWarehouse();
    showMovementModal.value = true;
}

// التحقق من صحة الكمية قبل الإرسال
function validateQuantity() {
    const qty = Number(form.value.quantity);
    
    if (!qty || qty <= 0) {
        ElMessage.error(t('quantity_must_be_positive'));
        return false;
    }
    
    if ((form.value.movement_type === 'out' || form.value.movement_type === 'transfer') && previewBalance.value < 0) {
        ElMessage.error(t('insufficient_available_balance'));
        return false;
    }

    if (form.value.movement_type === 'transfer' && !form.value.to_warehouse_id) {
        ElMessage.error(t('please_choose_destination_warehouse'));
        return false;
    }

    return true;
}

// إضافة حركة مع معالجة أخطاء محسنة
async function submitMovement() {
    if (!validateQuantity()) return;
    
    submitting.value = true;
    try {
        const response = await api.post('/admin/wms/stock/movements', form.value);
        
        if (!response.data) {
            throw new Error('Invalid response from server');
        }
        
        ElMessage.success(t('movement_added'));
        showMovementModal.value = false;
        
        // إعادة تعيين النموذج
        form.value = {
            product_id: selectedProduct.value?.id,
            warehouse_id: selectedWarehouse.value?.id,
            to_warehouse_id: null,
            movement_type: 'in',
            quantity: 0,
            reference_document: '',
            notes: '',
        };
        
        // تحديث البيانات
        await Promise.all([fetchBalance(), fetchTransactions()]);
    } catch (err) {
        console.error('Error adding movement:', err);
        const errorMsg = err.response?.data?.message || err.message || t('failed_to_add_movement');
        ElMessage.error(errorMsg);
    } finally {
        submitting.value = false;
    }
}

// تصدير الحركات مع التحقق
function exportTransactions() {
    if (!Array.isArray(filteredTransactions.value) || filteredTransactions.value.length === 0) {
        ElMessage.warning(t('no_movements_to_export'));
        return;
    }
    
    try {
        const csv = [
            [t('date'), t('type'), t('quantity'), t('document'), t('notes')],
            ...filteredTransactions.value.map(t => [
                t.created_at,
                t.movement_type === 'in' ? t('deposit') : t.movement_type === 'out' ? t('issue_movement') : t.movement_type === 'adjustment' ? t('adjustment') : t('transfer_movement'),
                t.quantity,
                t.reference_document || '-',
                t.notes || '-'
            ])
        ].map(row => row.join(',')).join('\n');
        
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `stock_movements_${selectedProduct.value?.code}_${selectedWarehouse.value?.code}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
        
        ElMessage.success(t('movements_exported'));
    } catch (err) {
        console.error('Error exporting transactions:', err);
        ElMessage.error(t('failed_to_export_movements'));
    }
}

function handleCancel() {
    showMovementModal.value = false;
    form.value = {
        product_id: selectedProduct.value?.id,
        warehouse_id: selectedWarehouse.value?.id,
        to_warehouse_id: null,
        movement_type: 'in',
        quantity: 0,
        reference_document: '',
        notes: '',
    };
}

// تنسيق الأرقام
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return formatCount(num);
}

// تنسيق النسبة المئوية
function formatPercentage(value) {
    if (value === null || value === undefined) return '0%';
    return Math.round(value) + '%';
}

// الحصول على لون نوع الحركة
function getMovementTypeColor(type) {
    const colors = {
        'in': 'green',
        'out': 'red',
        'adjustment': 'yellow',
        'transfer': 'blue'
    };
    return colors[type] || 'gray';
}

// الحصول على نص نوع الحركة
function getMovementTypeText(type) {
    const texts = {
        'in': t('deposit'),
        'out': t('issue_movement'),
        'adjustment': t('adjustment'),
        'transfer': t('transfer_movement')
    };
    return texts[type] || type;
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $t('balances_and_movements') }}</h1>
                <p class="text-gray-600 mt-1">{{ $t('balances_subtitle') }}</p>
            </div>
            <div class="flex gap-3">
                <button 
                    @click="refreshData"
                    :disabled="refreshing"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 disabled:opacity-50 transition-all"
                >
                    <span v-if="refreshing" class="animate-spin">⟳</span>
                    <span v-else>↻</span>
                    {{ $t('update') }}
                </button>
                <button 
                    @click="openMovementModal"
                    class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all"
                >
                    <span>+</span>
                    {{ $t('add_movement') }}
                </button>
            </div>
        </div>

        <!-- اختيار المنتج والمستودع -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('product') }}</label>
                    <select 
                        v-model="selectedProduct"
                        @change="selectProductAndWarehouse"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    >
                        <option value="">{{ $t('choose_product') }}</option>
                        <option v-for="prod in products" :key="prod.id" :value="prod">
                            {{ prod.name }} ({{ prod.code }})
                        </option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('warehouse') }}</label>
                    <select 
                        v-model="selectedWarehouse"
                        @change="selectProductAndWarehouse"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                    >
                        <option value="">{{ $t('choose_warehouse') }}</option>
                        <option v-for="wh in warehouses" :key="wh.id" :value="wh">
                            {{ wh.name }} ({{ wh.code }})
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3">
                <span class="text-2xl">❌</span>
                <div>
                    <p class="font-medium text-red-800">{{ $t('failed_to_fetch_data') }}</p>
                    <p class="text-sm text-red-600">{{ error }}</p>
                </div>
                <button @click="fetchBalance" class="mr-auto text-red-600 hover:text-red-700 text-sm font-medium">
                    {{ $t('retry') }}
                </button>
            </div>
        </div>

        <!-- بطاقة الرصيد المحسنة -->
        <div v-if="balance" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('current_balance') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(balance.quantity) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">📦</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('reserved') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(balance.reserved_quantity) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">🔒</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('available') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(balance.available_quantity) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">✓</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">{{ $t('safety_stock') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ formatNumber(balance.safety_stock) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl">🛡</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- مؤشر الحالة مع شريط التقدم -->
        <div v-if="balance" class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">{{ $t('balance_status') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        الحد الأدنى: {{ formatNumber(balance.min_stock) }} | الحد الأقصى: {{ formatNumber(balance.max_stock) }}
                    </p>
                </div>
                <span 
                    :class="{
                        'bg-green-100 text-green-800': balanceStatus.color === 'green',
                        'bg-orange-100 text-orange-800': balanceStatus.color === 'orange',
                        'bg-red-100 text-red-800': balanceStatus.color === 'red',
                    }"
                    class="px-4 py-2 rounded-full text-sm font-bold"
                >
                    {{ balanceStatus.icon }} {{ balanceStatus.text }}
                </span>
            </div>
            
            <!-- شريط التقدم -->
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200">
                            {{ $t('fill_level') }}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold inline-block text-blue-600">
                            {{ formatPercentage((balance.available_quantity / balance.max_stock) * 100) }}
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-3 mb-4 text-xs flex rounded bg-blue-200">
                    <div 
                        :style="{ width: Math.min((balance.available_quantity / balance.max_stock) * 100, 100) + '%' }"
                        :class="{
                            'bg-green-500': balanceStatus.color === 'green',
                            'bg-orange-500': balanceStatus.color === 'orange',
                            'bg-red-500': balanceStatus.color === 'red',
                        }"
                        class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center transition-all duration-500"
                    ></div>
                </div>
            </div>
        </div>

        <!-- سجل الحركات مع الفلاتر -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">{{ $t('movement_ledger') }}</h3>
                <button 
                    @click="exportTransactions"
                    class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-2 transition-colors"
                >
                    <span>📥</span> {{ $t('export_csv') }}
                </button>
            </div>

            <!-- فلاتر الحركات -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('movement_type') }}</label>
                    <select 
                        v-model="movementTypeFilter"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 transition-all"
                    >
                        <option value="">{{ $t('all') }}</option>
                        <option value="in">{{ $t('deposit') }}</option>
                        <option value="out">{{ $t('issue_movement') }}</option>
                        <option value="adjustment">{{ $t('adjustment') }}</option>
                        <option value="transfer">{{ $t('transfer_movement') }}</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('date_from') }}</label>
                    <input 
                        v-model="dateFromFilter"
                        type="date"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 transition-all"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('date_to') }}</label>
                    <input 
                        v-model="dateToFilter"
                        type="date"
                        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 transition-all"
                    />
                </div>
            </div>

            <!-- جدول الحركات -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('date') }}</th>
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('type') }}</th>
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('quantity') }}</th>
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('balance_after') }}</th>
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('document') }}</th>
                            <th class="text-right p-4 font-medium text-gray-700">{{ $t('notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!selectedProduct || !selectedWarehouse">
                            <td colspan="6" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-4xl mb-2">📋</span>
                                    <p class="text-gray-500">{{ $t('choose_product_and_warehouse') }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="loading">
                            <td colspan="6" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                                    <p class="mt-4 text-gray-600">{{ $t('loading_data') }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="filteredTransactions.length === 0">
                            <td colspan="6" class="p-8 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-4xl mb-2">📭</span>
                                    <p class="text-gray-500">{{ $t('no_movements') }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr 
                            v-for="(txn, index) in filteredTransactions" 
                            :key="txn.id" 
                            class="border-b hover:bg-gray-50 transition-colors"
                        >
                            <td class="p-4 text-sm">{{ txn.created_at }}</td>
                            <td class="p-4">
                                <span 
                                    :class="{
                                        'bg-green-100 text-green-800': getMovementTypeColor(txn.movement_type) === 'green',
                                        'bg-red-100 text-red-800': getMovementTypeColor(txn.movement_type) === 'red',
                                        'bg-yellow-100 text-yellow-800': getMovementTypeColor(txn.movement_type) === 'yellow',
                                        'bg-blue-100 text-blue-800': getMovementTypeColor(txn.movement_type) === 'blue',
                                    }"
                                    class="px-3 py-1 rounded-full text-xs font-bold"
                                >
                                    {{ getMovementTypeText(txn.movement_type) }}
                                </span>
                            </td>
                            <td class="p-4 font-bold text-sm" :class="{
                                'text-green-600': txn.movement_type === 'in',
                                'text-red-600': txn.movement_type === 'out',
                            }">
                                {{ txn.movement_type === 'in' ? '+' : '-' }}{{ formatNumber(txn.quantity) }}
                            </td>
                            <td class="p-4 text-sm font-medium">{{ formatNumber(txn.balance_after) || '-' }}</td>
                            <td class="p-4 text-sm font-mono">{{ txn.reference_document || '-' }}</td>
                            <td class="p-4 text-sm text-gray-600">{{ txn.notes || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal إضافة حركة -->
        <div v-if="showMovementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" @click="handleCancel"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900">{{ $t('add_stock_movement') }}</h2>
                    <button @click="handleCancel" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form @submit.prevent="submitMovement">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('movement_type') }}</label>
                            <select 
                                v-model="form.movement_type"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option value="in">{{ $t('deposit') }}</option>
                                <option value="out">{{ $t('issue_movement') }}</option>
                                <option value="adjustment">{{ $t('adjustment') }}</option>
                                <option value="transfer">{{ $t('transfer_movement') }}</option>
                            </select>
                        </div>

                        <div v-if="form.movement_type === 'transfer'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('destination_warehouse') }}</label>
                            <select
                                v-model="form.to_warehouse_id"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            >
                                <option :value="null">{{ $t('choose_warehouse') }}</option>
                                <option
                                    v-for="wh in warehouses.filter(w => w.id !== form.warehouse_id)"
                                    :key="wh.id"
                                    :value="wh.id"
                                >
                                    {{ wh.name }} ({{ wh.code }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('quantity') }}</label>
                            <input 
                                v-model="form.quantity"
                                type="number"
                                min="1"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                        </div>

                        <!-- معاينة الرصيد -->
                        <div v-if="balance" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">{{ $t('available_after_operation') }}</span>
                                <span class="font-bold text-blue-600 text-lg mx-2">{{ formatNumber(previewBalance) }}</span>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('reference_document') }}</label>
                            <input 
                                v-model="form.reference_document"
                                type="text"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                :placeholder="$t('example_po_number')"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('notes') }}</label>
                            <textarea 
                                v-model="form.notes"
                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-4">
                        <button 
                            type="button"
                            @click="handleCancel"
                            class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors"
                            :disabled="submitting"
                        >
                            {{ $t('cancel') }}
                        </button>
                        <button 
                            type="submit"
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            :disabled="submitting"
                        >
                            <span v-if="submitting" class="animate-spin">⟳</span>
                            {{ submitting ? 'جاري الحفظ...' : 'حفظ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
