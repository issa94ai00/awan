<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('wms_reports_and_analytics') }}</h1>
      <button 
        @click="refreshData"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
      >
        {{ $t('data_update') }}
      </button>
    </div>

    <!-- فلاتر الفترة -->
    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
      <div class="grid grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('report_type') }}</label>
          <select 
            v-model="selectedReportType"
            @change="loadReportData"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          >
            <option value="inventory">{{ $t('inventory_report') }}</option>
            <option value="movements">{{ $t('movements_report') }}</option>
            <option value="low_stock">{{ $t('low_stock_products') }}</option>
            <option value="warehouse">{{ $t('warehouse_distribution') }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('date_from') }}</label>
          <input 
            v-model="dateFrom"
            type="date"
            @change="loadReportData"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('date_to') }}</label>
          <input 
            v-model="dateTo"
            type="date"
            @change="loadReportData"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('warehouse') }}</label>
          <select 
            v-model="selectedWarehouse"
            @change="loadReportData"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          >
            <option value="">{{ $t('all_warehouses') }}</option>
            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
              {{ wh.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="grid grid-cols-4 gap-6 mb-6">
      <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">{{ $t('total_stock') }}</h3>
            <p class="text-3xl font-bold text-gray-800">{{ stats.totalStock }}</p>
          </div>
          <div class="text-3xl">📦</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">{{ $t('stock_value') }}</h3>
            <p class="text-3xl font-bold text-gray-800">{{ stats.totalValue }}</p>
          </div>
          <div class="text-3xl">💰</div>
        </div>
      </div>
      
      <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">{{ $t('low_products') }}</h3>
            <p class="text-3xl font-bold text-gray-800">{{ stats.lowStockCount }}</p>
          </div>
          <div class="text-3xl">⚠</div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">{{ $t('movements_today') }}</h3>
            <p class="text-3xl font-bold text-gray-800">{{ stats.todayMovements }}</p>
          </div>
          <div class="text-3xl">🔄</div>
        </div>
      </div>
    </div>

    <!-- الرسوم البيانية -->
    <div class="grid grid-cols-2 gap-6 mb-6">
      <!-- تقرير المخزون حسب الفئة -->
      <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-gray-800">{{ $t('stock_by_category') }}</h3>
          <button 
            @click="exportChart('inventory')"
            class="text-blue-600 hover:text-blue-700 text-sm"
          >
            {{ $t('export') }}
          </button>
        </div>
        <div v-if="inventoryByCategory.length > 0" class="space-y-4">
          <div 
            v-for="(item, index) in inventoryByCategory" 
            :key="item.category"
            class="flex items-center gap-3"
          >
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <div class="flex justify-between mb-1">
                <span class="font-medium text-sm">{{ item.category }}</span>
                <span class="text-sm text-gray-600">{{ item.quantity }}</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3">
                <div 
                  class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                  :style="{ width: (item.quantity / inventoryByCategory[0].quantity * 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center text-gray-500 py-8">
          {{ $t('no_data') }}
        </div>
      </div>

      <!-- الحركات حسب النوع -->
      <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-gray-800">{{ $t('movements_by_type') }}</h3>
          <button 
            @click="exportChart('movements')"
            class="text-blue-600 hover:text-blue-700 text-sm"
          >
            {{ $t('export') }}
          </button>
        </div>
        <div v-if="movementsByType.length > 0" class="space-y-4">
          <div 
            v-for="(item, index) in movementsByType" 
            :key="item.type"
            class="flex items-center gap-3"
          >
            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
              :class="{
                'bg-green-100 text-green-600': item.type === 'in',
                'bg-red-100 text-red-600': item.type === 'out',
                'bg-yellow-100 text-yellow-600': item.type === 'adjustment',
              }"
            >
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <div class="flex justify-between mb-1">
                <span class="font-medium text-sm">
                  {{ item.type === 'in' ? 'إيداع' : item.type === 'out' ? 'صرف' : 'تسوية' }}
                </span>
                <span class="text-sm text-gray-600">{{ item.count }}</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3">
                <div 
                  class="h-3 rounded-full transition-all duration-500"
                  :class="{
                    'bg-green-500': item.type === 'in',
                    'bg-red-500': item.type === 'out',
                    'bg-yellow-500': item.type === 'adjustment',
                  }"
                  :style="{ width: (item.count / movementsByType[0].count * 100) + '%' }"
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

    <!-- جدول البيانات التفصيلية -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-gray-800">
          {{ selectedReportType === 'inventory' ? 'تفاصيل المخزون' : 
             selectedReportType === 'movements' ? 'تفاصيل الحركات' :
             selectedReportType === 'low_stock' ? 'المنتجات منخفضة المخزون' :
             'توزيع المستودعات' }}
        </h3>
        <button 
          @click="exportTable"
          class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-2"
        >
          <span>📥</span> {{ $t('export_csv') }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b bg-gray-50">
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('product') }}</th>
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('code') }}</th>
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('warehouse') }}</th>
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('balance') }}</th>
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('value') }}</th>
              <th class="text-right p-3 font-medium text-gray-700">{{ $t('status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="p-4 text-center">
                <div class="flex justify-center">
                  <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
              </td>
            </tr>
            <tr v-else-if="reportData.length === 0">
              <td colspan="6" class="p-4 text-center text-gray-500">
                {{ $t('no_data') }}
              </td>
            </tr>
            <tr 
              v-for="item in reportData" 
              :key="item.id" 
              class="border-b hover:bg-gray-50"
            >
              <td class="p-3 font-medium">{{ item.product_name }}</td>
              <td class="p-3 font-mono text-sm">{{ item.product_code }}</td>
              <td class="p-3">{{ item.warehouse_name }}</td>
              <td class="p-3 font-bold">{{ item.quantity }}</td>
              <td class="p-3">{{ item.value }}</td>
              <td class="p-3">
                <span 
                  :class="{
                    'bg-green-100 text-green-800': item.status === 'ok',
                    'bg-orange-100 text-orange-800': item.status === 'low',
                    'bg-red-100 text-red-800': item.status === 'critical',
                  }"
                  class="px-2 py-1 rounded-full text-xs font-medium"
                >
                  {{ item.status === 'ok' ? 'جيد' : item.status === 'low' ? 'منخفض' : 'حرج' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const { t } = useI18n();

const loading = ref(false);
const selectedReportType = ref('inventory');
const dateFrom = ref('');
const dateTo = ref('');
const selectedWarehouse = ref('');
const warehouses = ref([]);

const stats = ref({
  totalStock: 0,
  totalValue: 0,
  lowStockCount: 0,
  todayMovements: 0,
});

const inventoryByCategory = ref([]);
const movementsByType = ref([]);
const reportData = ref([]);

async function fetchWarehouses() {
  try {
    const response = await axios.get('/api/v1/admin/wms/warehouses');
    warehouses.value = response.data.data;
  } catch (error) {
    console.error('Error fetching warehouses:', error);
  }
}

async function loadReportData() {
  loading.value = true;
  try {
    // محاكاة استدعاء API - يجب استبدالها بـ API حقيقي
    const response = await axios.get('/api/v1/admin/wms/reports', {
      params: {
        type: selectedReportType.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        warehouse_id: selectedWarehouse.value,
      }
    });
    
    stats.value = response.data.stats;
    inventoryByCategory.value = response.data.inventory_by_category || [];
    movementsByType.value = response.data.movements_by_type || [];
    reportData.value = response.data.data || [];
  } catch (error) {
    console.error('Error loading report data:', error);
    // بيانات وهمية للعرض
    loadMockData();
  } finally {
    loading.value = false;
  }
}

function loadMockData() {
  stats.value = {
    totalStock: 15420,
    totalValue: '245,320 ر.س',
    lowStockCount: 23,
    todayMovements: 156,
  };

  inventoryByCategory.value = [
    { category: t('electronics'), quantity: 5200 },
    { category: 'أجهزة منزلية', quantity: 3800 },
    { category: t('clothing'), quantity: 2900 },
    { category: 'مواد غذائية', quantity: 2100 },
    { category: t('subject_other'), quantity: 1420 },
  ];

  movementsByType.value = [
    { type: 'in', count: 89 },
    { type: 'out', count: 67 },
    { type: 'adjustment', count: 12 },
  ];

  reportData.value = [
    { id: 1, product_name: 'تلفزيون سامسونج 55"', product_code: 'TV-001', warehouse_name: 'المستودع الرئيسي', quantity: 45, value: '22,500 ر.س', status: 'ok' },
    { id: 2, product_name: 'ثلاجة LG', product_code: 'RF-002', warehouse_name: 'المستودع الرئيسي', quantity: 12, value: '18,000 ر.س', status: 'low' },
    { id: 3, product_name: 'غسالة باناسونيك', product_code: 'WM-003', warehouse_name: 'مستودع الرياض', quantity: 8, value: '12,000 ر.س', status: 'critical' },
    { id: 4, product_name: 'مايكروويف شارب', Product_code: 'MW-004', warehouse_name: 'مستودع جدة', quantity: 32, value: '9,600 ر.س', status: 'ok' },
    { id: 5, product_name: 'مكيف كاريير', product_code: 'AC-005', warehouse_name: 'المستودع الرئيسي', quantity: 18, value: '27,000 ر.س', status: 'ok' },
  ];
}

function refreshData() {
  loadReportData();
}

function exportChart(type) {
  alert(`سيتم تصدير الرسم البياني: ${type}`);
}

function exportTable() {
  if (reportData.value.length === 0) {
    alert(t('no_data_to_export'));
    return;
  }
  
  const csv = [
    [t('product'), t('code'), t('warehouse'), t('balance'), t('value'), t('status')],
    ...reportData.value.map(item => [
      item.product_name,
      item.product_code,
      item.warehouse_name,
      item.quantity,
      item.value,
      item.status === 'ok' ? 'جيد' : item.status === 'low' ? 'منخفض' : t('critical')
    ])
  ].map(row => row.join(',')).join('\n');
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `wms_report_${selectedReportType.value}_${new Date().toISOString().split('T')[0]}.csv`;
  link.click();
}

onMounted(() => {
  fetchWarehouses();
  loadReportData();
});
</script>
