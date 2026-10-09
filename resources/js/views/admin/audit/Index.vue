<template>
  <div class="audit-hub-page p-4 md:p-6 bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1">
          <el-icon><Shield /></el-icon>
          <span>{{ $t('security_monitoring') || 'الأمان والرقابة' }}</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
          <el-icon class="text-indigo-600"><Document /></el-icon>
          {{ $t('audit.title') || 'سجل وتدقيق العمليات والأمان' }}
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {{ $t('audit_trail_description') || 'تتبع ومراقبة العمليات الحساسة، التعديلات الميدانية، ومحاولات الدخول عبر كافة الأنظمة.' }}
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <el-button
          type="primary"
          plain
          :icon="Download"
          :loading="exporting"
          @click="handleExportLogs"
        >
          {{ $t('audit.export_audit_logs') || 'تصدير السجل (CSV)' }}
        </el-button>

        <el-button
          type="warning"
          plain
          :icon="Delete"
          @click="showCleanupDialog = true"
        >
          {{ $t('audit.retention_policy') || 'تنظيف السجلات القديمة' }}
        </el-button>

        <el-button
          :icon="Refresh"
          :loading="loadingLogs || loadingStats || loadingRisk"
          @click="refreshAll"
        >
          {{ $t('common.refresh') || 'تحديث' }}
        </el-button>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.total_logs') || 'إجمالي العمليات المسجلة' }}</p>
            <h3 class="text-2xl font-bold text-slate-900">{{ stats.total_logs.toLocaleString() }}</h3>
            <span class="text-xs text-slate-400 mt-1 block">
              {{ $t('today') || 'اليوم' }}: <strong class="text-indigo-600">{{ stats.today_logs }}</strong>
            </span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
            <el-icon><Document /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-indigo-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.security_events') || 'عمليات الأمان والتحذيرات' }}</p>
            <h3 class="text-2xl font-bold" :class="stats.security_events > 0 ? 'text-rose-600' : 'text-slate-900'">
              {{ stats.security_events }}
            </h3>
            <span class="text-xs text-slate-400 mt-1 block">
              {{ $t('audit.failed_login') || 'محاولات فاشلة / حذف' }}
            </span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
            <el-icon><Warning /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-rose-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('audit.active_users') || 'المستخدمون النشطون' }}</p>
            <h3 class="text-2xl font-bold text-slate-900">{{ stats.active_users }}</h3>
            <span class="text-xs text-slate-400 mt-1 block">
              {{ stats.active_modules }} {{ $t('audit.modules') || 'وحدات مفعلة' }}
            </span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
            <el-icon><User /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-emerald-500"></div>
      </div>

      <div class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-xs relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1">{{ $t('total_issues') || 'تنبيهات ومخاطر المطابقة' }}</p>
            <h3 class="text-2xl font-bold" :class="riskSummary.total_issues > 0 ? 'text-amber-600' : 'text-slate-900'">
              {{ riskSummary.total_issues }}
            </h3>
            <span class="text-xs text-slate-400 mt-1 block">
              {{ $t('critical') || 'حرج' }}: <strong class="text-rose-600">{{ riskSummary.critical_issues }}</strong>
            </span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
            <el-icon><DataBoard /></el-icon>
          </div>
        </div>
        <div class="absolute bottom-0 inset-x-0 h-1 bg-amber-500"></div>
      </div>
    </div>

    <!-- Main Navigation Tabs -->
    <el-tabs v-model="activeTab" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs" @tab-change="handleTabChange">
      <!-- Tab 1: Audit Trail -->
      <el-tab-pane name="audit_trail">
        <template #label>
          <span class="flex items-center gap-2 font-medium">
            <el-icon><Document /></el-icon>
            {{ $t('audit.audit_trail') || 'سجل التدقيق الأمني' }}
            <el-badge v-if="stats.today_logs > 0" :value="stats.today_logs" type="primary" class="ml-1" />
          </span>
        </template>

        <!-- Filters Toolbar -->
        <div class="bg-slate-50/80 rounded-xl p-4 border border-slate-200/70 mb-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Keyword search -->
            <div class="lg:col-span-2">
              <el-input
                v-model="filters.search"
                :placeholder="$t('audit.search_placeholder') || 'بحث في الوصف، IP، أو اسم المستخدم...'"
                clearable
                :prefix-icon="Search"
                @keyup.enter="handleSearch"
                @clear="handleSearch"
              />
            </div>

            <!-- Action -->
            <div>
              <el-select
                v-model="filters.action"
                :placeholder="$t('audit.all_actions') || 'كافة العمليات'"
                clearable
                @change="handleSearch"
              >
                <el-option value="" :label="$t('audit.all_actions') || 'كافة العمليات'" />
                <el-option value="login" :label="$t('audit.login') || 'تسجيل دخول'" />
                <el-option value="failed_login" :label="$t('audit.failed_login') || 'فشل تسجيل الدخول'" />
                <el-option value="logout" :label="$t('audit.logout') || 'تسجيل خروج'" />
                <el-option value="password_change" :label="$t('audit.password_change') || 'تغيير كلمة المرور'" />
                <el-option value="revoke_session" :label="$t('audit.revoke_session') || 'إنهاء جلسة'" />
                <el-option value="create" :label="$t('audit.create') || 'إنشاء'" />
                <el-option value="update" :label="$t('audit.update') || 'تحديث'" />
                <el-option value="delete" :label="$t('audit.delete') || 'حذف'" />
                <el-option value="export" :label="$t('export') || 'تصدير'" />
                <el-option value="approve" :label="$t('approve') || 'موافقة'" />
                <el-option value="reject" :label="$t('reject') || 'رفض'" />
              </el-select>
            </div>

            <!-- Module -->
            <div>
              <el-select
                v-model="filters.module"
                :placeholder="$t('audit.all_modules') || 'كافة الوحدات'"
                clearable
                @change="handleSearch"
              >
                <el-option value="" :label="$t('audit.all_modules') || 'كافة الوحدات'" />
                <el-option value="security" :label="$t('audit.security') || 'الأمان والجلسات'" />
                <el-option value="users" :label="$t('audit.users') || 'المستخدمين'" />
                <el-option value="orders" :label="$t('audit.orders') || 'الطلبات والمبيعات'" />
                <el-option value="inventory" :label="$t('audit.inventory') || 'المخزون'" />
                <el-option value="products" :label="$t('audit.products') || 'المنتجات'" />
                <el-option value="finance" :label="$t('finance') || 'المالي والفواتير'" />
                <el-option value="warehouse" :label="$t('warehouse') || 'المستودع'" />
                <el-option value="rma" :label="'المرتجعات (RMA)'" />
                <el-option value="settings" :label="$t('settings') || 'الإعدادات'" />
              </el-select>
            </div>

            <!-- Severity -->
            <div>
              <el-select
                v-model="filters.severity"
                :placeholder="$t('audit.all_severities') || 'مستوى الخطورة'"
                clearable
                @change="handleSearch"
              >
                <el-option value="" :label="$t('audit.all_severities') || 'كافة المستويات'" />
                <el-option value="critical" :label="$t('audit.critical') || 'حرج / أمني'" />
                <el-option value="warning" :label="$t('audit.warning') || 'تحذير'" />
                <el-option value="info" :label="$t('audit.info') || 'عادي / معلوماتي'" />
              </el-select>
            </div>

            <!-- Date Range -->
            <div>
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

          <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-200/60">
            <span class="text-xs text-slate-500">
              {{ $t('showing_results') || 'عرض' }} <strong class="text-slate-800">{{ logs.length }}</strong> {{ $t('of') || 'من أصل' }} <strong class="text-slate-800">{{ pagination.total }}</strong> {{ $t('records') || 'سجل' }}
            </span>
            <div class="flex items-center gap-2">
              <el-button size="small" @click="resetFilters">
                {{ $t('reset_filters') || 'إعادة ضبط' }}
              </el-button>
              <el-button type="primary" size="small" :icon="Search" @click="handleSearch">
                {{ $t('apply_filter') || 'تطبيق الفلترة' }}
              </el-button>
            </div>
          </div>
        </div>

        <!-- Audit Table -->
        <el-table
          :data="logs"
          v-loading="loadingLogs"
          stripe
          class="w-full rounded-lg overflow-hidden border border-slate-200"
          :empty-text="$t('no_audit_logs_found') || 'لا توجد سجلات تدقيق مطابقة للشروط'"
        >
          <!-- ID -->
          <el-table-column prop="id" label="#" width="75" align="center">
            <template #default="{ row }">
              <span class="text-xs font-mono font-semibold text-slate-500">#{{ row.id }}</span>
            </template>
          </el-table-column>

          <!-- Timestamp -->
          <el-table-column :label="$t('created_at') || 'التاريخ والوقت'" width="165">
            <template #default="{ row }">
              <div class="text-xs">
                <div class="font-medium text-slate-800">{{ formatDate(row.created_at) }}</div>
                <div class="text-slate-400 font-mono text-[11px]">{{ formatTime(row.created_at) }}</div>
              </div>
            </template>
          </el-table-column>

          <!-- User -->
          <el-table-column :label="$t('audit.user') || 'المستخدم'" width="170">
            <template #default="{ row }">
              <div v-if="row.user" class="flex items-center gap-2">
                <el-avatar :size="26" class="bg-indigo-100 text-indigo-700 text-xs font-bold">
                  {{ row.user.name?.charAt(0) || 'U' }}
                </el-avatar>
                <div class="overflow-hidden">
                  <router-link
                    :to="`/admin/audit/user-activity/${row.user.id}`"
                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 truncate block hover:underline"
                  >
                    {{ row.user.name }}
                  </router-link>
                  <span class="text-[11px] text-slate-400 truncate block">{{ row.user.email }}</span>
                </div>
              </div>
              <div v-else class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                <el-icon><Monitor /></el-icon>
                <span>{{ $t('system') || 'النظام التلقائي' }}</span>
              </div>
            </template>
          </el-table-column>

          <!-- Action -->
          <el-table-column :label="$t('audit.action') || 'الإجراء'" width="140">
            <template #default="{ row }">
              <el-tag :type="getActionTagType(row.action)" size="small" effect="light" class="font-medium">
                {{ row.action_text || row.action }}
              </el-tag>
            </template>
          </el-table-column>

          <!-- Module -->
          <el-table-column :label="$t('audit.module') || 'الوحدة'" width="130">
            <template #default="{ row }">
              <router-link
                v-if="row.module"
                :to="`/admin/audit/module-logs/${row.module}`"
                class="inline-block"
              >
                <el-tag type="info" size="small" class="cursor-pointer hover:border-indigo-400">
                  {{ row.module_text || row.module }}
                </el-tag>
              </router-link>
              <span v-else class="text-slate-400 text-xs">—</span>
            </template>
          </el-table-column>

          <!-- Entity -->
          <el-table-column :label="$t('audit.entity') || 'الكيان المستهدف'" width="140">
            <template #default="{ row }">
              <div v-if="row.entity_type" class="text-xs">
                <span class="font-semibold text-slate-700">{{ row.entity_type }}</span>
                <span v-if="row.entity_id" class="text-slate-400 font-mono ml-1">#{{ row.entity_id }}</span>
              </div>
              <span v-else class="text-slate-400 text-xs">—</span>
            </template>
          </el-table-column>

          <!-- Description -->
          <el-table-column :label="$t('description') || 'تفاصيل العملية'" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">
              <span class="text-xs text-slate-700">{{ row.description || '—' }}</span>
            </template>
          </el-table-column>

          <!-- IP & Client -->
          <el-table-column :label="$t('audit.ip_address') || 'عنوان IP'" width="130">
            <template #default="{ row }">
              <el-tooltip v-if="row.user_agent" :content="row.user_agent" placement="top" :enterable="false">
                <span class="font-mono text-xs text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded cursor-help">
                  {{ row.ip_address || '—' }}
                </span>
              </el-tooltip>
              <span v-else class="font-mono text-xs text-slate-500">
                {{ row.ip_address || '—' }}
              </span>
            </template>
          </el-table-column>

          <!-- Actions -->
          <el-table-column :label="$t('actions') || 'إجراءات'" width="90" align="center" fixed="right">
            <template #default="{ row }">
              <el-button
                size="small"
                type="primary"
                plain
                :icon="View"
                @click="openDetailsModal(row)"
              />
            </template>
          </el-table-column>
        </el-table>

        <!-- Pagination -->
        <div class="flex items-center justify-between mt-4">
          <span class="text-xs text-slate-500">
            {{ $t('page') || 'الصفحة' }} {{ pagination.page }} {{ $t('of') || 'من' }} {{ Math.ceil(pagination.total / pagination.per_page) || 1 }}
          </span>
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.per_page"
            :total="pagination.total"
            :page-sizes="[15, 25, 50, 100]"
            layout="sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handlePageChange"
          />
        </div>
      </el-tab-pane>

      <!-- Tab 2: Risk & Reconciliation Scan -->
      <el-tab-pane name="risk_scan">
        <template #label>
          <span class="flex items-center gap-2 font-medium">
            <el-icon><Warning /></el-icon>
            {{ $t('audit.risk_scan') || 'فحص المخاطر ومطابقة العمليات' }}
            <el-badge v-if="riskSummary.total_issues > 0" :value="riskSummary.total_issues" type="danger" class="ml-1" />
          </span>
        </template>

        <div class="flex items-center justify-between mb-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
          <div>
            <h3 class="text-base font-bold text-slate-800">{{ $t('manual_risk_scan_results') || 'نتائج الفحص الذكي للبيانات والمطابقة' }}</h3>
            <p class="text-xs text-slate-500">
              {{ $t('last_scan') || 'آخر فحص' }}: <strong>{{ riskSummary.last_scan }}</strong>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <el-button type="success" plain :icon="Download" @click="handleExportRiskScan">
              {{ $t('export_csv') || 'تصدير المخاطر (CSV)' }}
            </el-button>
            <el-button type="primary" :icon="Refresh" :loading="loadingRisk" @click="loadRiskScan">
              {{ $t('run_checks_again') || 'إعادة الفحص المباشر' }}
            </el-button>
          </div>
        </div>

        <el-table :data="riskIssues" v-loading="loadingRisk" stripe border :empty-text="$t('no_issues_right_now') || 'لا توجد مخاطر أو فجوات مطابقة في البيانات'">
          <el-table-column prop="type" :label="$t('type') || 'النوع'" width="220">
            <template #default="{ row }">
              <span class="font-mono text-xs font-semibold text-slate-700">{{ row.type }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="severity" :label="$t('severity') || 'الخطورة'" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="row.severity === 'critical' ? 'danger' : 'warning'" size="small">
                {{ row.severity === 'critical' ? 'حرج' : 'تحذير' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="reference" :label="$t('indicator') || 'المرجع'" width="160">
            <template #default="{ row }">
              <span class="font-bold text-slate-800 text-xs">{{ row.reference }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="message" :label="$t('description') || 'الوصف والمشكلة'" show-overflow-tooltip>
            <template #default="{ row }">
              <span class="text-xs text-slate-700">{{ row.message }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="$t('details') || 'البيانات التفصيلية'" width="280">
            <template #default="{ row }">
              <pre class="bg-slate-50 p-2 rounded text-[11px] font-mono border border-slate-200 text-slate-700 max-h-24 overflow-y-auto">{{ formatDetails(row.details) }}</pre>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab 3: Analytics & Trends -->
      <el-tab-pane name="analytics">
        <template #label>
          <span class="flex items-center gap-2 font-medium">
            <el-icon><DataBoard /></el-icon>
            {{ $t('audit.analytics_and_trends') || 'المؤشرات والتحليلات البيانية' }}
          </span>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-2 mb-6">
          <!-- Activity Trends Line Chart -->
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <el-icon class="text-indigo-600"><DataBoard /></el-icon>
                {{ $t('activity_trends_last_30_days') || 'منحنى النشاط اليومي (آخر 30 يوماً)' }}
              </h4>
            </div>
            <div ref="trendsChartRef" style="height: 280px;"></div>
          </div>

          <!-- Activity by Action Bar Chart -->
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <el-icon class="text-emerald-600"><Document /></el-icon>
                {{ $t('audit.activity_by_action') || 'توزيع العمليات حسب الإجراء' }}
              </h4>
            </div>
            <div ref="actionChartRef" style="height: 280px;"></div>
          </div>
        </div>

        <!-- Top Users Table -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
          <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
            <el-icon class="text-indigo-600"><User /></el-icon>
            {{ $t('audit.top_users') || 'المستخدمون الأكثر نشاطاً في العمليات' }}
          </h4>
          <el-table :data="stats.top_users || []" stripe border :empty-text="$t('no_data') || 'لا توجد بيانات'">
            <el-table-column :label="$t('audit.user') || 'المستخدم'" min-width="160">
              <template #default="{ row }">
                <div class="font-bold text-xs text-slate-800">{{ row.user }}</div>
                <div class="text-[11px] text-slate-400">{{ row.email }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="actions_count" :label="$t('audit.actions_count') || 'عدد العمليات'" width="130" align="center">
              <template #default="{ row }">
                <el-tag type="primary" size="small">{{ row.actions_count }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="last_active" :label="$t('audit.last_active') || 'آخر نشاط'" width="160" />
            <el-table-column :label="$t('audit.modules') || 'الوحدات المستخدمة'" min-width="200">
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
      </el-tab-pane>
    </el-tabs>

    <!-- Details / Diff Dialog -->
    <el-dialog
      v-model="showDetailsModal"
      :title="$t('audit.change_details') || 'فحص تفاصيل العملية والتعديلات'"
      width="780px"
      destroy-on-close
    >
      <div v-if="selectedLog">
        <!-- Overview Grid -->
        <el-descriptions :column="2" border class="mb-4">
          <el-descriptions-item :label="$t('audit.id') || 'المعرف'">#{{ selectedLog.id }}</el-descriptions-item>
          <el-descriptions-item :label="$t('created_at') || 'التاريخ والوقت'">{{ selectedLog.created_at }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.user') || 'المستخدم'">{{ selectedLog.user?.name || 'النظام' }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.action') || 'الإجراء'">
            <el-tag :type="getActionTagType(selectedLog.action)" size="small">
              {{ selectedLog.action_text || selectedLog.action }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="$t('audit.module') || 'الوحدة'">{{ selectedLog.module_text || selectedLog.module }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.ip_address') || 'عنوان IP'">{{ selectedLog.ip_address || '—' }}</el-descriptions-item>
          <el-descriptions-item :label="$t('audit.entity') || 'الكيان'">
            {{ selectedLog.entity_type }} <span v-if="selectedLog.entity_id">#{{ selectedLog.entity_id }}</span>
          </el-descriptions-item>
          <el-descriptions-item :label="$t('description') || 'الوصف'">{{ selectedLog.description || '—' }}</el-descriptions-item>
        </el-descriptions>

        <!-- Changes Diff -->
        <div class="mb-4">
          <h4 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">
            <el-icon class="text-indigo-600"><Document /></el-icon>
            {{ $t('audit.changes') || 'التغييرات المسجلة في قيم الحقول' }}
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
                  <td class="px-3 py-2 font-mono text-rose-600 bg-rose-50/20 break-all">
                    {{ formatValue(item.old) }}
                  </td>
                  <td class="px-3 py-2 font-mono text-emerald-600 bg-emerald-50/20 break-all">
                    {{ formatValue(item.new) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center py-4 bg-slate-50 rounded-lg border border-dashed border-slate-200 text-slate-400 text-xs">
            {{ $t('audit.no_changes_recorded') || 'لم تُسجّل أي تغييرات في قيم الحقول' }}
          </div>
        </div>

        <!-- Metadata Section (if present) -->
        <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length > 0">
          <h4 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">
            <el-icon class="text-amber-600"><InfoFilled /></el-icon>
            {{ $t('audit.metadata') || 'البيانات الوصفية الإضافية (Metadata)' }}
          </h4>
          <pre class="bg-slate-900 text-emerald-400 p-3 rounded-lg text-xs font-mono overflow-x-auto">{{ JSON.stringify(selectedLog.metadata, null, 2) }}</pre>
        </div>
      </div>
      <template #footer>
        <el-button @click="showDetailsModal = false">{{ $t('common.close') || 'إغلاق' }}</el-button>
      </template>
    </el-dialog>

    <!-- Cleanup Dialog -->
    <el-dialog
      v-model="showCleanupDialog"
      :title="$t('audit.retention_policy') || 'تنظيف سجلات التدقيق القديمة'"
      width="480px"
    >
      <div class="text-sm text-slate-600 mb-4">
        {{ $t('audit.cleanup_desc') || 'حدد عدد الأيام لحذف كافة سجلات التدقيق الأقدم منها لتوفير مساحة التخزين وتحسين الأداء.' }}
      </div>
      <el-form label-position="top">
        <el-form-item :label="$t('retention_period') || 'الاحتفاظ بالسجلات لمدة'">
          <el-select v-model="cleanupDays" class="w-full">
            <el-option :value="30" label="30 يوماً (أحدث شهر)" />
            <el-option :value="60" label="60 يوماً (أحدث شهرين)" />
            <el-option :value="90" label="90 يوماً (أحدث 3 أشهر)" />
            <el-option :value="180" label="180 يوماً (أحدث 6 أشهر)" />
            <el-option :value="365" label="365 يوماً (سنة كاملة)" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCleanupDialog = false">{{ $t('cancel') || 'إلغاء' }}</el-button>
        <el-button type="danger" :loading="cleaningUp" @click="handleExecuteCleanup">
          {{ $t('execute_cleanup') || 'تنفيذ الحذف' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Document, Warning, User, DataBoard, Search, Refresh, Download,
  Delete, View, Monitor, InfoFilled, Filter
} from '@element-plus/icons-vue';
import auditService from '@/services/audit';
import * as echarts from 'echarts';

const { t } = useI18n();

const activeTab = ref('audit_trail');
const loadingLogs = ref(false);
const loadingStats = ref(false);
const loadingRisk = ref(false);
const exporting = ref(false);
const cleaningUp = ref(false);

const showDetailsModal = ref(false);
const selectedLog = ref(null);
const showCleanupDialog = ref(false);
const cleanupDays = ref(90);

const dateRange = ref([]);

const filters = reactive({
  search: '',
  action: '',
  module: '',
  severity: '',
  start_date: '',
  end_date: '',
});

const pagination = reactive({
  page: 1,
  per_page: 25,
  total: 0,
});

const logs = ref([]);

const stats = reactive({
  total_logs: 0,
  today_logs: 0,
  active_users: 0,
  active_modules: 0,
  security_events: 0,
  by_action: {},
  by_module: {},
  trends: { dates: [], counts: [] },
  top_users: [],
});

const riskIssues = ref([]);
const riskSummary = reactive({
  total_issues: 0,
  critical_issues: 0,
  warning_issues: 0,
  last_scan: '—',
});

// Charts
const trendsChartRef = ref(null);
const actionChartRef = ref(null);
let trendsChart = null;
let actionChart = null;

const getActionTagType = (action) => {
  switch (action) {
    case 'failed_login':
    case 'delete':
    case 'force_delete':
      return 'danger';
    case 'update':
    case 'password_change':
    case 'revoke_session':
    case 'cancel':
    case 'reject':
      return 'warning';
    case 'create':
    case 'approve':
      return 'success';
    case 'login':
      return 'primary';
    case 'logout':
      return 'info';
    default:
      return 'info';
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatTime = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const formatDetails = (details) => {
  if (!details) return '';
  return JSON.stringify(details, null, 2);
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

const loadLogs = async () => {
  loadingLogs.value = true;
  try {
    const params = {
      page: pagination.page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      action: filters.action || undefined,
      module: filters.module || undefined,
      severity: filters.severity || undefined,
      start_date: filters.start_date || undefined,
      end_date: filters.end_date || undefined,
    };
    const res = await auditService.getAuditLogs(params);
    const data = res.data;
    logs.value = data.data || [];
    pagination.total = data.total || 0;
  } catch (err) {
    ElMessage.error(t('common.load_error') || 'فشل تحميل سجلات التدقيق');
    console.error(err);
  } finally {
    loadingLogs.value = false;
  }
};

const loadStats = async () => {
  loadingStats.value = true;
  try {
    const res = await auditService.getStatistics({ days: 30 });
    const data = res.data || {};
    stats.total_logs = data.total_logs || 0;
    stats.today_logs = data.today_logs || 0;
    stats.active_users = data.active_users || 0;
    stats.active_modules = data.active_modules || 0;
    stats.security_events = data.security_events || 0;
    stats.by_action = data.by_action || {};
    stats.by_module = data.by_module || {};
    stats.trends = data.trends || { dates: [], counts: [] };
    stats.top_users = data.top_users || [];

    if (activeTab.value === 'analytics') {
      nextTick(() => {
        renderCharts();
      });
    }
  } catch (err) {
    console.error(err);
  } finally {
    loadingStats.value = false;
  }
};

const loadRiskScan = async () => {
  loadingRisk.value = true;
  try {
    const res = await auditService.getRiskScan();
    const data = res.data || {};
    riskIssues.value = data.issues || [];
    if (data.summary) {
      riskSummary.total_issues = data.summary.total_issues || 0;
      riskSummary.critical_issues = data.summary.critical_issues || 0;
      riskSummary.warning_issues = data.summary.warning_issues || 0;
      riskSummary.last_scan = data.summary.last_scan || '—';
    }
  } catch (err) {
    console.error(err);
  } finally {
    loadingRisk.value = false;
  }
};

const handleSearch = () => {
  pagination.page = 1;
  loadLogs();
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
  filters.search = '';
  filters.action = '';
  filters.module = '';
  filters.severity = '';
  filters.start_date = '';
  filters.end_date = '';
  dateRange.value = [];
  pagination.page = 1;
  loadLogs();
};

const handlePageChange = (p) => {
  pagination.page = p;
  loadLogs();
};

const handleSizeChange = (s) => {
  pagination.per_page = s;
  pagination.page = 1;
  loadLogs();
};

const openDetailsModal = (log) => {
  selectedLog.value = log;
  showDetailsModal.value = true;
};

const handleExportLogs = async () => {
  exporting.value = true;
  try {
    const params = {
      search: filters.search || undefined,
      action: filters.action || undefined,
      module: filters.module || undefined,
      severity: filters.severity || undefined,
      start_date: filters.start_date || undefined,
      end_date: filters.end_date || undefined,
    };
    const res = await auditService.exportLogs(params);
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    ElMessage.success(t('csv_exported') || 'تم تصدير سجل التدقيق بنجاح');
  } catch (err) {
    ElMessage.error(t('export_failed') || 'فشل تصدير ملف السجلات');
    console.error(err);
  } finally {
    exporting.value = false;
  }
};

const handleExportRiskScan = async () => {
  try {
    const res = await auditService.exportRiskScan();
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `risk-scan-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    ElMessage.success(t('csv_exported') || 'تم تصدير تقرير المخاطر بنجاح');
  } catch (err) {
    ElMessage.error(t('export_failed') || 'فشل تصدير ملف المخاطر');
  }
};

const handleExecuteCleanup = async () => {
  try {
    await ElMessageBox.confirm(
      t('audit.cleanup_confirm', { days: cleanupDays.value }) || `هل أنت متأكد من رغبتك في حذف السجلات الأقدم من ${cleanupDays.value} يوماً؟`,
      t('warning') || 'تأكيد الحذف',
      {
        confirmButtonText: t('confirm') || 'تأكيد',
        cancelButtonText: t('cancel') || 'إلغاء',
        type: 'warning',
      }
    );

    cleaningUp.value = true;
    const res = await auditService.cleanupOldLogs(cleanupDays.value);
    showCleanupDialog.value = false;
    ElMessage.success(t('audit.logs_purged') || `تم حذف ${res.data.deleted || 0} سجلاً بنجاح`);
    refreshAll();
  } catch (err) {
    if (err !== 'cancel') {
      ElMessage.error(t('error_occurred') || 'حدث خطأ أثناء تنظيف السجلات');
    }
  } finally {
    cleaningUp.value = false;
  }
};

const handleTabChange = (tabName) => {
  if (tabName === 'analytics') {
    nextTick(() => {
      renderCharts();
    });
  }
};

const renderCharts = () => {
  // 1. Trends Line Chart
  if (trendsChartRef.value) {
    if (trendsChart) trendsChart.dispose();
    trendsChart = echarts.init(trendsChartRef.value);

    const dates = stats.trends?.dates || [];
    const counts = stats.trends?.counts || [];

    trendsChart.setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '8%', top: '6%', containLabel: true },
      xAxis: {
        type: 'category',
        data: dates,
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
          data: counts,
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

  // 2. Action Bar Chart
  if (actionChartRef.value) {
    if (actionChart) actionChart.dispose();
    actionChart = echarts.init(actionChartRef.value);

    const actions = Object.keys(stats.by_action || {});
    const counts = actions.map((a) => stats.by_action[a]);

    actionChart.setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: '3%', right: '4%', bottom: '8%', top: '6%', containLabel: true },
      xAxis: {
        type: 'category',
        data: actions,
        axisLine: { lineStyle: { color: '#cbd5e1' } },
        axisLabel: { color: '#64748b', fontSize: 11, interval: 0, rotate: 20 },
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
          data: counts,
          itemStyle: {
            color: '#10b981',
            borderRadius: [4, 4, 0, 0],
          },
        },
      ],
    });
  }
};

const refreshAll = () => {
  loadLogs();
  loadStats();
  loadRiskScan();
};

onMounted(() => {
  refreshAll();
  window.addEventListener('resize', () => {
    trendsChart?.resize();
    actionChart?.resize();
  });
});
</script>

<style scoped>
.audit-hub-page :deep(.el-tabs__item) {
  font-size: 14px;
}
.audit-hub-page :deep(.el-card) {
  border-radius: 12px;
}
</style>
