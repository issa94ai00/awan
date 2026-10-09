<template>
  <div class="audit-statistics-page p-4 md:p-6 bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
          <el-icon class="text-indigo-600"><DataBoard /></el-icon>
          {{ $t('audit.statistics') || 'إحصائيات وتحليلات الرقابة والتدقيق' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          {{ $t('audit_stats_description') || 'تحليل معدلات النشاط، توزيع العمليات، والمسؤولين الأكثر نشاطاً في النظام.' }}
        </p>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <el-select v-model="selectedDays" class="w-36" @change="loadStatistics">
          <el-option :value="7" label="آخر 7 أيام" />
          <el-option :value="30" label="آخر 30 يوماً" />
          <el-option :value="60" label="آخر 60 يوماً" />
          <el-option :value="90" label="آخر 90 يوماً" />
        </el-select>

        <el-button type="primary" :icon="Refresh" :loading="loading" @click="loadStatistics">
          {{ $t('common.refresh') || 'تحديث' }}
        </el-button>
      </div>
    </div>

    <!-- Overview Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.total_logs') || 'إجمالي العمليات المسجلة' }}</p>
            <h3 class="text-2xl font-bold text-slate-900">{{ stats.total_logs.toLocaleString() }}</h3>
          </div>
          <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
            <el-icon><Document /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-indigo-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.active_users') || 'المستخدمون النشطون' }}</p>
            <h3 class="text-2xl font-bold text-slate-900">{{ stats.active_users }}</h3>
          </div>
          <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
            <el-icon><User /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-emerald-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.active_modules') || 'الوحدات الخاضعة للتدقيق' }}</p>
            <h3 class="text-2xl font-bold text-slate-900">{{ stats.active_modules }}</h3>
          </div>
          <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
            <el-icon><Folder /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-amber-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.security_events') || 'عمليات الأمان الحرجة' }}</p>
            <h3 class="text-2xl font-bold" :class="stats.security_events > 0 ? 'text-rose-600' : 'text-slate-900'">
              {{ stats.security_events }}
            </h3>
          </div>
          <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
            <el-icon><Warning /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-rose-500"></div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
          <el-icon class="text-indigo-600"><DataBoard /></el-icon>
          {{ $t('activity_trends') || 'منحنى النشاط اليومي' }}
        </h3>
        <div ref="trendsChartRef" style="height: 320px;"></div>
      </div>

      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
          <el-icon class="text-emerald-600"><Folder /></el-icon>
          {{ $t('audit.activity_by_module') || 'توزيع النشاط حسب الوحدة' }}
        </h3>
        <div ref="moduleChartRef" style="height: 320px;"></div>
      </div>
    </div>

    <!-- Actions Breakdown Chart & Top Users -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs lg:col-span-1">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
          <el-icon class="text-amber-600"><Document /></el-icon>
          {{ $t('audit.activity_by_action') || 'حسب نوع العملية' }}
        </h3>
        <div ref="actionChartRef" style="height: 340px;"></div>
      </div>

      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs lg:col-span-2">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
          <el-icon class="text-indigo-600"><User /></el-icon>
          {{ $t('audit.top_users') || 'المستخدمون الأكثر إنجازاً للعمليات' }}
        </h3>
        <el-table :data="topUsers" v-loading="loading" stripe border :empty-text="$t('no_data') || 'لا توجد بيانات مسجلة'">
          <el-table-column :label="$t('audit.user') || 'المستخدم'" min-width="160">
            <template #default="{ row }">
              <div class="font-bold text-xs text-slate-800">{{ row.user }}</div>
              <div class="text-[11px] text-slate-400">{{ row.email }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="actions_count" :label="$t('audit.actions_count') || 'عدد العمليات'" width="120" align="center">
            <template #default="{ row }">
              <el-tag type="primary" size="small">{{ row.actions_count }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="last_active" :label="$t('audit.last_active') || 'آخر نشاط'" width="160" />
          <el-table-column :label="$t('audit.modules') || 'الوحدات المستخدمة'" min-width="180">
            <template #default="{ row }">
              <div class="flex flex-wrap gap-1">
                <el-tag v-for="m in row.modules" :key="m" size="small" type="info">{{ m }}</el-tag>
              </div>
            </template>
          </el-table-column>
          <el-table-column :label="$t('actions') || 'إجراءات'" width="130" align="center">
            <template #default="{ row }">
              <router-link v-if="row.user_id" :to="`/admin/audit/user-activity/${row.user_id}`">
                <el-button size="small" type="primary" plain>{{ $t('view_activity') || 'عرض النشاط' }}</el-button>
              </router-link>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { DataBoard, Search, Refresh, Document, User, Folder, Warning } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import auditService from '@/services/audit';
import * as echarts from 'echarts';

const { t } = useI18n();

const loading = ref(false);
const selectedDays = ref(30);

const stats = ref({
  total_logs: 0,
  active_users: 0,
  active_modules: 0,
  security_events: 0,
  by_action: {},
  by_module: {},
  trends: { dates: [], counts: [] },
});

const topUsers = ref([]);

const trendsChartRef = ref(null);
const moduleChartRef = ref(null);
const actionChartRef = ref(null);
let trendsChart = null;
let moduleChart = null;
let actionChart = null;

const loadStatistics = async () => {
  loading.value = true;
  try {
    const response = await auditService.getStatistics({ days: selectedDays.value });
    const data = response.data || {};

    stats.value = {
      total_logs: data.total_logs || 0,
      active_users: data.active_users || 0,
      active_modules: data.active_modules || 0,
      security_events: data.security_events || 0,
      by_action: data.by_action || {},
      by_module: data.by_module || {},
      trends: data.trends || { dates: [], counts: [] },
    };

    topUsers.value = data.top_users || [];

    nextTick(() => {
      renderCharts();
    });
  } catch (error) {
    ElMessage.error(t('common.load_error') || 'فشل تحميل الإحصائيات');
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const renderCharts = () => {
  // 1. Trends Line Chart
  if (trendsChartRef.value) {
    if (trendsChart) trendsChart.dispose();
    trendsChart = echarts.init(trendsChartRef.value);

    trendsChart.setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '8%', top: '6%', containLabel: true },
      xAxis: {
        type: 'category',
        data: stats.value.trends.dates || [],
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        axisLabel: { color: '#64748b', fontSize: 11 },
      },
      yAxis: {
        type: 'value',
        axisLine: { show: false },
        splitLine: { lineStyle: { color: '#f1f5f9' } },
        axisLabel: { color: '#64748b' },
      },
      series: [
        {
          name: t('audit.total_logs') || 'العمليات',
          type: 'line',
          smooth: true,
          data: stats.value.trends.counts || [],
          itemStyle: { color: '#4f46e5' },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(79, 70, 229, 0.35)' },
              { offset: 1, color: 'rgba(79, 70, 229, 0.02)' },
            ]),
          },
        },
      ],
    });
  }

  // 2. Module Pie Chart
  if (moduleChartRef.value) {
    if (moduleChart) moduleChart.dispose();
    moduleChart = echarts.init(moduleChartRef.value);

    const moduleData = Object.keys(stats.value.by_module || {}).map((m) => ({
      name: m,
      value: stats.value.by_module[m],
    }));

    moduleChart.setOption({
      tooltip: { trigger: 'item' },
      legend: { orient: 'horizontal', bottom: 'bottom' },
      series: [
        {
          type: 'pie',
          radius: ['40%', '70%'],
          avoidLabelOverlap: false,
          itemStyle: {
            borderRadius: 6,
            borderColor: '#fff',
            borderWidth: 2,
          },
          label: { show: false, position: 'center' },
          emphasis: {
            label: { show: true, fontSize: 14, fontWeight: 'bold' },
          },
          data: moduleData.length > 0 ? moduleData : [{ name: 'لا توجد بيانات', value: 0 }],
        },
      ],
    });
  }

  // 3. Action Bar Chart
  if (actionChartRef.value) {
    if (actionChart) actionChart.dispose();
    actionChart = echarts.init(actionChartRef.value);

    const actionKeys = Object.keys(stats.value.by_action || {});
    const actionVals = actionKeys.map((k) => stats.value.by_action[k]);

    actionChart.setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '15%', top: '6%', containLabel: true },
      xAxis: {
        type: 'category',
        data: actionKeys,
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        axisLabel: { color: '#64748b', fontSize: 11, rotate: 30, interval: 0 },
      },
      yAxis: {
        type: 'value',
        axisLine: { show: false },
        splitLine: { lineStyle: { color: '#f1f5f9' } },
        axisLabel: { color: '#64748b' },
      },
      series: [
        {
          name: t('audit.actions') || 'العمليات',
          type: 'bar',
          data: actionVals,
          itemStyle: {
            color: '#f59e0b',
            borderRadius: [4, 4, 0, 0],
          },
        },
      ],
    });
  }
};

onMounted(() => {
  loadStatistics();
  window.addEventListener('resize', () => {
    trendsChart?.resize();
    moduleChart?.resize();
    actionChart?.resize();
  });
});
</script>

<style scoped>
.audit-statistics-page :deep(.el-card) {
  border-radius: 12px;
}
</style>
