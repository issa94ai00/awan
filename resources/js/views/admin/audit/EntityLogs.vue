<template>
  <div class="entity-logs-page p-4 md:p-6 bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
          <el-icon class="text-indigo-600"><Document /></el-icon>
          {{ $t('audit.entity_logs') || 'سجل العمليات على الكيانات والمستندات' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          {{ $t('entity_logs_description') || 'البحث في سجل التغييرات والتحديثات الخاصة بكيان معين (فواتير، طلبات، منتجات، عملاء...)' }}
        </p>
      </div>

      <router-link to="/admin/audit">
        <el-button :icon="Back">
          {{ $t('common.back') || 'رجوع' }}
        </el-button>
      </router-link>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $t('audit.entity_type') || 'نوع الكيان' }}</label>
          <el-input
            v-model="filters.entity_type"
            :placeholder="$t('audit.entity_type_placeholder') || 'مثال: Product, Invoice, SalesOrder...'"
            clearable
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $t('audit.entity_id') || 'رقم الكيان' }}</label>
          <el-input
            v-model="filters.entity_id"
            :placeholder="$t('audit.entity_id_placeholder') || 'رقم المعرف (ID)'"
            clearable
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $t('audit.action') || 'الإجراء' }}</label>
          <el-select v-model="filters.action" :placeholder="$t('audit.all_actions') || 'كافة العمليات'" clearable @change="handleSearch">
            <el-option value="" :label="$t('audit.all_actions') || 'كافة العمليات'" />
            <el-option value="create" :label="$t('audit.create') || 'إنشاء'" />
            <el-option value="update" :label="$t('audit.update') || 'تحديث'" />
            <el-option value="delete" :label="$t('audit.delete') || 'حذف'" />
            <el-option value="approve" :label="$t('approve') || 'موافقة'" />
            <el-option value="reject" :label="$t('reject') || 'رفض'" />
          </el-select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ $t('common.date_range') || 'النطاق الزمني' }}</label>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="-"
            :start-placeholder="$t('start_date') || 'من'"
            :end-placeholder="$t('end_date') || 'إلى'"
            value-format="YYYY-MM-DD"
            class="w-full!"
            @change="handleDateRangeChange"
          />
        </div>
      </div>

      <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
        <span class="text-xs text-slate-500">
          {{ $t('showing_results') || 'عرض' }} <strong class="text-slate-800">{{ entityLogs.length }}</strong> {{ $t('of') || 'من أصل' }} <strong class="text-slate-800">{{ pagination.total }}</strong>
        </span>
        <div class="flex items-center gap-2">
          <el-button size="small" @click="resetFilters">{{ $t('reset_filters') || 'إعادة ضبط' }}</el-button>
          <el-button type="primary" size="small" :icon="Search" @click="handleSearch">{{ $t('common.search') || 'بحث' }}</el-button>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
      <el-table :data="entityLogs" v-loading="loading" stripe :empty-text="$t('no_audit_logs_found') || 'لا توجد سجلات مطابقة'">
        <el-table-column prop="id" label="#" width="75" align="center">
          <template #default="{ row }">
            <span class="font-mono text-xs font-semibold text-slate-500">#{{ row.id }}</span>
          </template>
        </el-table-column>

        <el-table-column :label="$t('audit.entity_type') || 'نوع الكيان'" min-width="160">
          <template #default="{ row }">
            <span class="font-bold text-xs text-slate-800">{{ row.entity_type || '—' }}</span>
          </template>
        </el-table-column>

        <el-table-column prop="entity_id" :label="$t('audit.entity_id') || 'المعرف'" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.entity_id" size="small" type="info" class="font-mono">#{{ row.entity_id }}</el-tag>
            <span v-else class="text-slate-400">—</span>
          </template>
        </el-table-column>

        <el-table-column :label="$t('audit.action') || 'الإجراء'" width="130">
          <template #default="{ row }">
            <el-tag :type="getActionTagType(row.action)" size="small">
              {{ row.action_text || row.action }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column :label="$t('audit.user') || 'المستخدم'" width="160">
          <template #default="{ row }">
            <span v-if="row.user" class="text-xs font-semibold text-indigo-600">{{ row.user.name }}</span>
            <span v-else class="text-xs text-slate-500">{{ $t('system') || 'النظام' }}</span>
          </template>
        </el-table-column>

        <el-table-column :label="$t('description') || 'الوصف'" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="text-xs text-slate-700">{{ row.description || '—' }}</span>
          </template>
        </el-table-column>

        <el-table-column prop="created_at" :label="$t('created_at') || 'التاريخ'" width="165" />

        <el-table-column :label="$t('actions') || 'إجراءات'" width="80" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" plain :icon="View" @click="viewDetails(row)" />
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between">
        <span class="text-xs text-slate-500">
          {{ $t('page') || 'الصفحة' }} {{ pagination.page }}
        </span>
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[15, 25, 50, 100]"
          layout="sizes, prev, pager, next"
          @size-change="handleSearch"
          @current-change="loadEntityLogs"
        />
      </div>
    </div>

    <!-- Details Dialog -->
    <el-dialog v-model="showDetailsDialog" :title="$t('audit.change_details') || 'تفاصيل التعديلات'" width="780px" destroy-on-close>
      <div v-if="selectedLog">
        <el-descriptions :column="2" border class="mb-4">
          <el-descriptions-item :label="$t('audit.entity_type')">{{ selectedLog.entity_type }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.entity_id')">#{{ selectedLog.entity_id }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.action')">
            <el-tag :type="getActionTagType(selectedLog.action)" size="small">
              {{ selectedLog.action_text || selectedLog.action }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="$t('audit.user')">{{ selectedLog.user?.name || 'النظام' }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.ip_address')">{{ selectedLog.ip_address || '—' }}</el-descriptions-item>
          <el-descriptions-item :label="$t('created_at')">{{ selectedLog.created_at }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">
          <el-icon class="text-indigo-600"><Document /></el-icon>
          {{ $t('audit.changes') || 'التعديلات المسجلة على الحقول' }}
        </h4>

        <div v-if="computedChangesList.length > 0" class="border border-slate-200 rounded-lg overflow-hidden">
          <table class="w-full text-xs text-left rtl:text-right">
            <thead class="bg-slate-100 text-slate-700 uppercase font-semibold">
              <tr>
                <th class="px-3 py-2">{{ $t('audit.field') || 'الحقل' }}</th>
                <th class="px-3 py-2 text-rose-700 bg-rose-50/50">{{ $t('audit.old_value') || 'القيمة السابقة' }}</th>
                <th class="px-3 py-2 text-emerald-700 bg-emerald-50/50">{{ $t('audit.new_value') || 'القيمة الجديدة' }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="item in computedChangesList" :key="item.field" class="hover:bg-slate-50">
                <td class="px-3 py-2 font-mono font-medium text-slate-800 bg-slate-50/50">{{ item.field }}</td>
                <td class="px-3 py-2 font-mono text-rose-600 bg-rose-50/20 break-all">{{ formatValue(item.old) }}</td>
                <td class="px-3 py-2 font-mono text-emerald-600 bg-emerald-50/20 break-all">{{ formatValue(item.new) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-4 bg-slate-50 rounded-lg border border-dashed border-slate-200 text-slate-400 text-xs">
          {{ $t('audit.no_changes_recorded') || 'لم تُسجّل أي تغييرات في قيم الحقول' }}
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Document, Search, View, Back } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import auditService from '@/services/audit';

const { t } = useI18n();

const loading = ref(false);
const showDetailsDialog = ref(false);
const entityLogs = ref([]);
const selectedLog = ref(null);
const dateRange = ref([]);

const filters = reactive({
  entity_type: '',
  entity_id: '',
  action: '',
  start_date: '',
  end_date: '',
});

const pagination = reactive({
  page: 1,
  per_page: 25,
  total: 0,
});

const getActionTagType = (action) => {
  switch (action) {
    case 'delete':
    case 'failed_login':
      return 'danger';
    case 'update':
      return 'warning';
    case 'create':
    case 'approve':
      return 'success';
    default:
      return 'info';
  }
};

const formatValue = (val) => {
  if (val === null || val === undefined) return 'null';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
};

const computedChangesList = computed(() => {
  if (!selectedLog.value || !selectedLog.value.changes) return [];
  const entries = selectedLog.value.changes;
  return Object.keys(entries).map((key) => ({
    field: key,
    old: entries[key]?.old,
    new: entries[key]?.new,
  }));
});

const loadEntityLogs = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.page,
      per_page: pagination.per_page,
      entity_type: filters.entity_type || undefined,
      entity_id: filters.entity_id || undefined,
      action: filters.action || undefined,
      start_date: filters.start_date || undefined,
      end_date: filters.end_date || undefined,
    };
    const res = await auditService.getEntityLogs(params);
    entityLogs.value = res.data.data || [];
    pagination.total = res.data.total || 0;
  } catch (error) {
    ElMessage.error(t('common.load_error') || 'فشل تحميل سجلات الكيان');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  pagination.page = 1;
  loadEntityLogs();
};

const handleDateRangeChange = (val) => {
  if (val && val.length === 2) {
    filters.start_date = val[0];
    filters.end_date = val[1];
  } else {
    filters.start_date = '';
    filters.end_date = '';
  }
  handleSearch();
};

const resetFilters = () => {
  filters.entity_type = '';
  filters.entity_id = '';
  filters.action = '';
  filters.start_date = '';
  filters.end_date = '';
  dateRange.value = [];
  handleSearch();
};

const viewDetails = (log) => {
  selectedLog.value = log;
  showDetailsDialog.value = true;
};

onMounted(() => {
  loadEntityLogs();
});
</script>
