<!-- resources/js/views/admin/wms/Dashboard.vue -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const loading = ref(true);
const stats = ref({
    linked_products: 0,
    total_products: 0,
    active_warehouses: 0,
    total_warehouses: 0,
    reorder_products: 0,
    total_stock: 0,
    total_value: 0,
    today_movements: 0,
    active_users: 0,
});
const topProducts = ref([]);
const warehouseDistribution = ref([]);
const recentMovements = ref([]);
const alerts = ref([]);
const showAlerts = ref(false);

// جلب البيانات
async function fetchDashboardData() {
    loading.value = true;
    try {
        const response = await axios.get('/api/v1/admin/wms/dashboard');
        stats.value = response.data.stats;
        topProducts.value = response.data.top_products;
        warehouseDistribution.value = response.data.warehouse_distribution;
        recentMovements.value = response.data.recent_movements;
        alerts.value = response.data.alerts;
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    } finally {
        loading.value = false;
    }
}

// الاستماع لتحديثات Laravel Echo
function setupEchoListeners() {
    if (typeof window.Echo === 'undefined') {
        console.warn('Laravel Echo is not configured');
        return;
    }

    // الاستماع لتنبيهات لوحة التحكم
    window.Echo.private('dashboard.alerts')
        .listen('StockAlert', (e) => {
            alerts.value.unshift(e.alert);
            showNotification(e.alert.message);
        });
}

function showNotification(message) {
    // عرض إشعار بسيط
    alert(message);
}

onMounted(() => {
    fetchDashboardData();
    setupEchoListeners();
    
    // إغلاق قائمة التنبيهات عند النقر خارجها
    document.addEventListener('click', closeAlertsDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', closeAlertsDropdown);
    if (window.Echo) {
        window.Echo.leave('dashboard.alerts');
    }
});

function closeAlertsDropdown(e) {
    if (!e.target.closest('.alerts-dropdown')) {
        showAlerts.value = false;
    }
}

function navigateTo(path) {
    router.push(path);
}
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">{{ $t('wms_dashboard') }}</h1>
            
            <!-- قائمة التنبيهات -->
            <div class="relative alerts-dropdown">
                <button 
                    @click="showAlerts = !showAlerts"
                    class="relative bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600"
                >
                    <span class="flex items-center gap-2">
                        {{ $t('alerts_with_icon') }}
                        <span 
                            v-if="alerts.length > 0"
                            class="bg-white text-red-500 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold"
                        >
                            {{ alerts.length }}
                        </span>
                    </span>
                </button>
                
                <div 
                    v-if="showAlerts"
                    class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-10"
                >
                    <div class="p-4 border-b">
                        <h3 class="font-bold">{{ $t('recent_alerts') }}</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div 
                            v-for="alert in alerts" 
                            :key="alert.id"
                            @click="navigateTo(`/admin/wms/products/${alert.product_id}`)"
                            class="p-4 border-b hover:bg-gray-50 cursor-pointer"
                        >
                            <div class="flex items-start gap-3">
                                <span class="text-2xl">⚠</span>
                                <div class="flex-1">
                                    <p class="text-sm">{{ alert.message }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ alert.created_at }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="alerts.length === 0" class="p-4 text-gray-500 text-center">
                            {{ $t('no_alerts') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="mt-4 text-gray-600">{{ $t('loading') }}</p>
        </div>

        <div v-else>
            <!-- البطاقات السريعة -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('linked_products') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.linked_products }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">📦</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('active_warehouses') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.active_warehouses }} / {{ stats.total_warehouses }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">🏭</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('products_needing_reorder') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.reorder_products }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">⚠️</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('total_stock') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.total_stock }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">📊</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('stock_value') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ formatCurrency(stats.total_value) }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">💰</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('movements_today') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.today_movements }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">🔄</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('active_users') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ stats.active_users }}</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">👥</div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-lg border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 mb-1">{{ $t('fill_rate') }}</h3>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-800">87%</p>
                        </div>
                        <div class="text-2xl sm:text-3xl">📈</div>
                    </div>
                </div>
            </div>

            <!-- الرسوم البيانية -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-bold mb-4 text-gray-800">{{ $t('top_five_consumed_products') }}</h3>
                    <div v-if="topProducts.length > 0" class="space-y-4">
                        <div 
                            v-for="(product, index) in topProducts" 
                            :key="product.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium text-sm">{{ product.name }}</span>
                                    <span class="text-sm text-gray-600">{{ product.quantity }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                        :style="{ width: (product.quantity / topProducts[0].quantity * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500 py-8">
                        {{ $t('no_data') }}
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-bold mb-4 text-gray-800">{{ $t('stock_distribution_across_warehouses') }}</h3>
                    <div v-if="warehouseDistribution.length > 0" class="space-y-4">
                        <div 
                            v-for="(warehouse, index) in warehouseDistribution" 
                            :key="warehouse.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-bold text-sm">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium text-sm">{{ warehouse.name }}</span>
                                    <span class="text-sm text-gray-600">{{ warehouse.percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-green-600 h-2 rounded-full transition-all duration-500"
                                        :style="{ width: warehouse.percentage + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500 py-8">
                        {{ $t('no_data') }}
                    </div>
                </div>
            </div>

            <!-- آخر الحركات -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">{{ $t('latest_stock_movements') }}</h3>
                    <button 
                        @click="navigateTo('/admin/wms/stock/balances')"
                        class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                    >
                        {{ $t('view_all') }}
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="text-right p-3 font-medium text-gray-700">{{ $t('date') }}</th>
                                <th class="text-right p-3 font-medium text-gray-700">{{ $t('product') }}</th>
                                <th class="text-right p-3 font-medium text-gray-700">{{ $t('warehouse') }}</th>
                                <th class="text-right p-3 font-medium text-gray-700">{{ $t('type') }}</th>
                                <th class="text-right p-3 font-medium text-gray-700">{{ $t('quantity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="stats.today_movements === 0">
                                <td colspan="5" class="p-4 text-center text-gray-500">{{ $t('no_movements_today') }}</td>
                            </tr>
                            <tr v-else class="border-b hover:bg-gray-50">
                                <td class="p-3 text-sm">{{ $t('today') }}</td>
                                <td class="p-3 text-sm">-</td>
                                <td class="p-3 text-sm">-</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $t('deposit') }}
                                    </span>
                                </td>
                                <td class="p-3 text-sm font-medium">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- روابط سريعة -->
            <div class="mt-6">
                <h3 class="text-lg font-bold mb-4 text-gray-800">{{ $t('quick_links') }}</h3>
                <div class="grid grid-cols-4 gap-4">
                    <button 
                        @click="navigateTo('/admin/wms/products')"
                        class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow text-right"
                    >
                        <div class="text-2xl mb-2">📦</div>
                        <div class="font-medium">{{ $t('nav_products') }}</div>
                    </button>
                    
                    <button 
                        @click="navigateTo('/admin/wms/warehouses')"
                        class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow text-right"
                    >
                        <div class="text-2xl mb-2">🏭</div>
                        <div class="font-medium">{{ $t('warehouses') }}</div>
                    </button>
                    
                    <button 
                        @click="navigateTo('/admin/wms/stock/balances')"
                        class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow text-right"
                    >
                        <div class="text-2xl mb-2">⚖</div>
                        <div class="font-medium">{{ $t('balances') }}</div>
                    </button>
                    
                    <button 
                        @click="navigateTo('/admin/reports/inventory')"
                        class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow text-right"
                    >
                        <div class="text-2xl mb-2">📊</div>
                        <div class="font-medium">{{ $t('nav_reports') }}</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
