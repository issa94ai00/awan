<template>
  <div class="notifications-page">
    <AdminPageHeader :title="t('notifications.title')">
      <template #actions>
        <el-button :icon="Refresh" @click="refreshAll" :loading="loading || alertsLoading">
          {{ t('common.refresh') || 'تحديث' }}
        </el-button>
        <el-button :icon="Check" @click="markAllAsRead" :disabled="unreadCount === 0">
          {{ t('notifications.mark_all_read') }}
        </el-button>
        <el-button :icon="Delete" type="danger" plain @click="deleteAllRead" :disabled="readCount === 0">
          {{ t('notifications.delete_all_read') || 'حذف المقروء' }}
        </el-button>
        <el-button type="primary" :icon="Plus" @click="openSendDialog">
          {{ t('notifications.send_notification') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <!-- Top KPI Stat Cards -->
    <el-row :gutter="16" class="stats-row">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card card-total">
          <div class="stat-content">
            <div class="stat-icon icon-blue">
              <el-icon><Bell /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ totalCount }}</h3>
              <p>{{ t('notifications.total') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card card-unread">
          <div class="stat-content">
            <div class="stat-icon icon-orange">
              <el-icon><Message /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ unreadCount }}</h3>
              <p>{{ t('notifications.unread') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card card-read">
          <div class="stat-content">
            <div class="stat-icon icon-green">
              <el-icon><CircleCheck /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ readCount }}</h3>
              <p>{{ t('notifications.read') }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card
          shadow="hover"
          class="stat-card card-alerts"
          :class="{ 'has-alerts': systemAlertsCount > 0 }"
          @click="activeTab = 'alerts'"
          style="cursor: pointer"
        >
          <div class="stat-content">
            <div class="stat-icon icon-red">
              <el-icon><Warning /></el-icon>
            </div>
            <div class="stat-info">
              <h3>{{ systemAlertsCount }}</h3>
              <p>{{ t('notifications.system_alerts') || 'تنبيهات النظام النشطة' }}</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Main Navigation Tabs -->
    <el-card class="main-card" shadow="never">
      <el-tabs v-model="activeTab" class="notification-tabs">
        <!-- TAB 1: Notification Feed -->
        <el-tab-pane name="feed">
          <template #label>
            <span class="tab-label">
              <el-icon><Bell /></el-icon>
              <span>{{ t('notifications.feed_tab') || 'سجل الإشعارات' }}</span>
              <el-badge v-if="unreadCount > 0" :value="unreadCount" class="tab-badge" />
            </span>
          </template>

          <!-- Search & Filters Toolbar -->
          <div class="filter-toolbar">
            <div class="filter-inputs">
              <el-input
                v-model="filters.search"
                :placeholder="t('notifications.search_placeholder') || 'بحث في نص أو عنوان الإشعار...'"
                :prefix-icon="Search"
                clearable
                @clear="handleSearch"
                @keyup.enter="handleSearch"
                class="search-input"
              />

              <el-select
                v-model="filters.type"
                :placeholder="t('notifications.select_type')"
                clearable
                @change="handleFilterChange"
                class="filter-select"
              >
                <el-option value="" :label="t('common.all_types') || 'جميع التصنيفات'" />
                <el-option value="info" :label="t('notifications.info')" />
                <el-option value="success" :label="t('notifications.success')" />
                <el-option value="warning" :label="t('notifications.warning')" />
                <el-option value="error" :label="t('notifications.error')" />
                <el-option value="order" :label="t('notifications.order_updates') || 'الطلبات'" />
                <el-option value="inventory" :label="t('notifications.inventory_alerts') || 'المخزون'" />
                <el-option value="warehouse" :label="t('notifications.warehouse') || 'المستودعات'" />
                <el-option value="financial" :label="t('notifications.payment_notifications') || 'المالية'" />
                <el-option value="system" :label="t('notifications.system_messages') || 'النظام'" />
              </el-select>

              <el-select
                v-model="filters.status"
                :placeholder="t('notifications.select_status')"
                clearable
                @change="handleFilterChange"
                class="filter-select"
              >
                <el-option value="" :label="t('common.all_status') || 'جميع الحالات'" />
                <el-option value="unread" :label="t('notifications.unread')" />
                <el-option value="read" :label="t('notifications.read')" />
              </el-select>
            </div>

            <div class="filter-actions">
              <el-button type="primary" :icon="Search" @click="handleSearch">
                {{ t('common.search') }}
              </el-button>
              <el-button @click="resetFilters">
                {{ t('common.reset') || 'إعادة ضبط' }}
              </el-button>
            </div>
          </div>

          <!-- Batch Operations Bar (when rows are selected) -->
          <transition name="el-zoom-in-top">
            <div v-if="selectedRowIds.length > 0" class="batch-bar">
              <div class="batch-info">
                <el-icon><Tickets /></el-icon>
                <span>{{ t('notifications.selected_count', { count: selectedRowIds.length }) || `تم تحديد ${selectedRowIds.length} إشعار` }}</span>
              </div>
              <div class="batch-btns">
                <el-button size="small" type="primary" :icon="Check" @click="handleBatchMarkRead">
                  {{ t('notifications.mark_selected_read') || 'تحديد كمقروء' }}
                </el-button>
                <el-button size="small" type="danger" :icon="Delete" @click="handleBatchDelete">
                  {{ t('notifications.delete_selected') || 'حذف المحدد' }}
                </el-button>
                <el-button size="small" @click="clearSelection">
                  {{ t('common.cancel') }}
                </el-button>
              </div>
            </div>
          </transition>

          <!-- Table -->
          <el-table
            ref="tableRef"
            :data="notifications"
            v-loading="loading"
            stripe
            border
            @selection-change="handleSelectionChange"
            class="notifications-table"
          >
            <el-table-column type="selection" width="45" align="center" />

            <el-table-column :label="t('notifications.status')" width="75" align="center">
              <template #default="{ row }">
                <el-tooltip :content="row.is_read ? t('notifications.read') : t('notifications.unread')" placement="top">
                  <span class="status-dot" :class="{ unread: !row.is_read }"></span>
                </el-tooltip>
              </template>
            </el-table-column>

            <el-table-column prop="type" :label="t('notifications.type')" width="130">
              <template #default="{ row }">
                <el-tag :type="getTypeTag(row.type)" effect="light" class="type-tag">
                  <el-icon class="mr-1"><component :is="getTypeIcon(row.type)" /></el-icon>
                  <span>{{ formatTypeName(row.type) }}</span>
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column prop="title" :label="t('notifications.title')" min-width="200">
              <template #default="{ row }">
                <div class="title-cell" :class="{ unread: !row.is_read }" @click="viewNotification(row)">
                  <span class="title-text">{{ row.title }}</span>
                </div>
              </template>
            </el-table-column>

            <el-table-column prop="message" :label="t('notifications.message')" min-width="260" show-overflow-tooltip>
              <template #default="{ row }">
                <span class="message-preview">{{ row.message }}</span>
              </template>
            </el-table-column>

            <el-table-column :label="t('notifications.event_action') || 'الحدث المرتبط'" width="130" align="center">
              <template #default="{ row }">
                <el-button
                  v-if="row.target_route"
                  size="small"
                  type="primary"
                  link
                  @click="goToTargetRoute(row.target_route)"
                >
                  <el-icon><ArrowRight /></el-icon>
                  <span>{{ t('notifications.go_to_event') || 'انتقال للحدث' }}</span>
                </el-button>
                <span v-else class="text-slate-400 text-xs">—</span>
              </template>
            </el-table-column>

            <el-table-column prop="created_at" :label="t('common.created_at')" width="180">
              <template #default="{ row }">
                <div class="time-cell">
                  <span class="exact-time">{{ formatDate(row.created_at) }}</span>
                  <span class="relative-time">{{ formatTimeAgo(row.created_at) }}</span>
                </div>
              </template>
            </el-table-column>

            <el-table-column :label="t('common.actions')" width="140" align="center" fixed="right">
              <template #default="{ row }">
                <div class="row-actions">
                  <el-tooltip :content="t('common.view')" placement="top">
                    <el-button size="small" circle :icon="View" @click="viewNotification(row)" />
                  </el-tooltip>
                  <el-tooltip :content="row.is_read ? (t('notifications.mark_unread') || 'تحديد كغير مقروء') : t('notifications.marked_read')" placement="top">
                    <el-button
                      size="small"
                      circle
                      :type="row.is_read ? 'default' : 'success'"
                      :icon="Check"
                      @click="toggleReadStatus(row)"
                    />
                  </el-tooltip>
                  <el-tooltip :content="t('common.delete')" placement="top">
                    <el-button size="small" circle type="danger" :icon="Delete" @click="deleteSingle(row)" />
                  </el-tooltip>
                </div>
              </template>
            </el-table-column>
          </el-table>

          <!-- Pagination -->
          <div class="pagination-container">
            <el-pagination
              v-model:current-page="pagination.page"
              v-model:page-size="pagination.per_page"
              :total="pagination.total"
              :page-sizes="[10, 20, 50, 100]"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="loadNotifications"
              @current-change="loadNotifications"
            />
          </div>
        </el-tab-pane>

        <!-- TAB 2: Operational System Alerts -->
        <el-tab-pane name="alerts">
          <template #label>
            <span class="tab-label">
              <el-icon><Warning /></el-icon>
              <span>{{ t('notifications.system_alerts_center') || 'مركز تنبيهات النظام التشغيلية' }}</span>
              <el-badge v-if="systemAlertsCount > 0" :value="systemAlertsCount" type="danger" class="tab-badge" />
            </span>
          </template>

          <div class="alerts-tab-content">
            <div class="alerts-intro">
              <div class="intro-text">
                <h3>{{ t('notifications.operational_pulse') || 'نبض العمليات والمستودعات والمالية' }}</h3>
                <p>{{ t('notifications.operational_pulse_desc') || 'يقوم النظام تلقائياً بتحليل المخزون المتدني، والطلبات المعلقة، واستلامات البضائع، والفواتير المتأخرة لتنبيهك الفوري.' }}</p>
              </div>
              <el-button :icon="Refresh" @click="loadSystemAlerts" :loading="alertsLoading">
                {{ t('common.refresh') }}
              </el-button>
            </div>

            <div v-if="alertsLoading" class="alerts-loading">
              <el-icon class="is-loading" :size="32"><Loading /></el-icon>
              <span>{{ t('common.loading') }}...</span>
            </div>

            <div v-else-if="systemAlerts.length === 0" class="alerts-empty-state">
              <el-icon :size="48" class="text-emerald-500"><CircleCheck /></el-icon>
              <h3>{{ t('notifications.all_systems_normal') || 'جميع العمليات بحالة ممتازة' }}</h3>
              <p>{{ t('notifications.no_operational_alerts') || 'لا توجد طلبات معلقة أو نواقص مخزون حرجة حالياً.' }}</p>
            </div>

            <div v-else class="alerts-grid">
              <el-card
                v-for="alert in systemAlerts"
                :key="alert.id"
                shadow="hover"
                class="alert-card"
                :class="`alert-severity-${alert.severity}`"
              >
                <div class="alert-card-header">
                  <div class="header-main">
                    <span class="severity-pill" :class="alert.severity">
                      {{ formatSeverity(alert.severity) }}
                    </span>
                    <h4 class="alert-title">{{ currentLocale === 'ar' ? alert.title : (alert.title_en || alert.title) }}</h4>
                  </div>
                  <span class="count-badge" :class="alert.severity">
                    {{ alert.count }}
                  </span>
                </div>

                <p class="alert-desc">{{ currentLocale === 'ar' ? alert.message : (alert.message_en || alert.message) }}</p>

                <!-- Sample Items Preview (if present) -->
                <div v-if="alert.items && alert.items.length" class="alert-items-preview">
                  <span class="preview-title">{{ t('notifications.affected_samples') || 'عينة من السجلات المتأثرة:' }}</span>
                  <div class="preview-tags">
                    <span v-for="it in alert.items" :key="it.id" class="sample-chip">
                      {{ it.order_number || it.invoice_number || it.name || it.sku || `#${it.id}` }}
                    </span>
                  </div>
                </div>

                <div class="alert-card-footer">
                  <el-button
                    type="primary"
                    :icon="ArrowRight"
                    @click="goToTargetRoute(alert.route)"
                    class="action-btn"
                  >
                    {{ t('notifications.take_action') || 'معالجة الأمر الآن' }}
                  </el-button>
                </div>
              </el-card>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- Dialog: View Notification Details -->
    <el-dialog
      v-model="showViewDialog"
      :title="selectedNotification?.title || t('notifications.details')"
      width="560px"
      destroy-on-close
      class="detail-dialog"
    >
      <div v-if="selectedNotification" class="notification-detail-content">
        <div class="detail-header-row">
          <el-tag :type="getTypeTag(selectedNotification.type)" class="type-tag">
            <el-icon class="mr-1"><component :is="getTypeIcon(selectedNotification.type)" /></el-icon>
            <span>{{ formatTypeName(selectedNotification.type) }}</span>
          </el-tag>
          <span class="detail-time">{{ formatDate(selectedNotification.created_at) }}</span>
        </div>

        <div class="detail-message-box">
          <p>{{ selectedNotification.message }}</p>
        </div>

        <div v-if="selectedNotification.read_at" class="read-receipt">
          <el-icon color="#10b981"><Check /></el-icon>
          <span>{{ t('notifications.read_at') || 'تمت القراءة في' }}: {{ formatDate(selectedNotification.read_at) }}</span>
        </div>

        <div v-if="selectedNotification.target_route" class="target-link-box">
          <el-button type="primary" :icon="ArrowRight" @click="goToTargetRoute(selectedNotification.target_route)">
            {{ t('notifications.go_to_event') || 'الانتقال إلى الحدث المرتبط' }}
          </el-button>
        </div>
      </div>
      <template #footer>
        <div class="detail-footer">
          <el-button type="danger" plain :icon="Delete" @click="deleteFromDialog">
            {{ t('common.delete') }}
          </el-button>
          <el-button @click="showViewDialog = false">
            {{ t('common.close') }}
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- Dialog: Compose & Send Notification -->
    <el-dialog
      v-model="showSendDialog"
      :title="t('notifications.send_notification')"
      width="620px"
      destroy-on-close
    >
      <el-form
        ref="sendFormRef"
        :model="sendForm"
        :rules="sendRules"
        label-position="top"
        v-loading="sending"
      >
        <el-form-item :label="t('notifications.target_audience') || 'الجهة المستلمة'" prop="send_to">
          <el-radio-group v-model="sendForm.send_to">
            <el-radio-button value="user">{{ t('notifications.specific_user') || 'مستخدم محدد' }}</el-radio-button>
            <el-radio-button value="all_admins">{{ t('notifications.all_admins') || 'جميع المدراء (Admins)' }}</el-radio-button>
            <el-radio-button value="all_users">{{ t('notifications.all_users') || 'جميع المستخدمين' }}</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item
          v-if="sendForm.send_to === 'user'"
          :label="t('notifications.recipient')"
          prop="user_id"
        >
          <el-select
            v-model="sendForm.user_id"
            :placeholder="t('notifications.select_user')"
            filterable
            class="w-full"
          >
            <el-option
              v-for="user in users"
              :key="user.id"
              :value="user.id"
              :label="`${user.name} (${user.email})`"
            />
          </el-select>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :span="16">
            <el-form-item :label="t('notifications.notification_title') || 'عنوان الإشعار'" prop="title">
              <el-input v-model="sendForm.title" :placeholder="t('notifications.title_placeholder') || 'أدخل عنوان الإشعار...'" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('notifications.type')" prop="type">
              <el-select v-model="sendForm.type" class="w-full">
                <el-option value="info" :label="t('notifications.info')" />
                <el-option value="success" :label="t('notifications.success')" />
                <el-option value="warning" :label="t('notifications.warning')" />
                <el-option value="error" :label="t('notifications.error')" />
                <el-option value="order" :label="t('notifications.order_updates') || 'طلب'" />
                <el-option value="inventory" :label="t('notifications.inventory_alerts') || 'مخزون'" />
                <el-option value="financial" :label="t('notifications.payment_notifications') || 'مالي'" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item :label="t('notifications.message')" prop="message">
          <el-input
            v-model="sendForm.message"
            type="textarea"
            :rows="4"
            :placeholder="t('notifications.message_placeholder') || 'اكتب نص الإشعار بالتفصيل...'"
          />
        </el-form-item>

        <el-form-item :label="t('notifications.target_route') || 'رابط الحدث الداخلي (اختياري)'">
          <el-input
            v-model="sendForm.route"
            :placeholder="t('notifications.route_placeholder') || 'مثال: /admin/stock أو /admin/sales/sales-orders'"
          />
        </el-form-item>

        <el-form-item :label="t('notifications.channels')">
          <el-checkbox-group v-model="sendForm.channels">
            <el-checkbox value="in_app">{{ t('notifications.in_app') }}</el-checkbox>
            <el-checkbox value="email">{{ t('notifications.email') }}</el-checkbox>
            <el-checkbox value="sms">{{ t('notifications.sms') }}</el-checkbox>
            <el-checkbox value="push">{{ t('notifications.push') }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showSendDialog = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleSendSubmit" :loading="sending">
          {{ t('notifications.send') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  Bell, Plus, Check, Message, Search, View, Delete, CircleCheck,
  Warning, WarningFilled, Refresh, ArrowRight, Tickets, Box,
  ShoppingCart, Money, Loading
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import notificationsService from '@/services/notifications';
import { useNotificationsStore } from '@/stores/notifications';

const { t, locale } = useI18n();
const router = useRouter();
const notificationsStore = useNotificationsStore();
const currentLocale = computed(() => locale.value);

// State
const activeTab = ref('feed');
const loading = ref(false);
const alertsLoading = ref(false);
const sending = ref(false);
const showViewDialog = ref(false);
const showSendDialog = ref(false);
const selectedNotification = ref(null);
const selectedRows = ref([]);

const notifications = ref([]);
const users = ref([]);
const systemAlerts = ref([]);
const totalCount = ref(0);
const unreadCount = ref(0);
const readCount = ref(0);
const systemAlertsCount = ref(0);

const filters = ref({
  search: '',
  type: '',
  status: '',
});

const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0,
});

const sendFormRef = ref(null);
const sendForm = ref({
  send_to: 'user',
  user_id: null,
  title: '',
  message: '',
  type: 'info',
  route: '',
  channels: ['in_app'],
});

const sendRules = {
  title: [{ required: true, message: t('notifications.title_required') || 'عنوان الإشعار مطلوب', trigger: 'blur' }],
  message: [{ required: true, message: t('notifications.body_required') || 'نص الإشعار مطلوب', trigger: 'blur' }],
  user_id: [
    {
      validator: (rule, value, callback) => {
        if (sendForm.value.send_to === 'user' && !value) {
          callback(new Error(t('notifications.user_required') || 'يرجى اختيار المستخدم المستلم'));
        } else {
          callback();
        }
      },
      trigger: 'change',
    },
  ],
};

const selectedRowIds = computed(() => selectedRows.value.map((r) => r.id));

// Methods
const refreshAll = async () => {
  await Promise.all([
    loadNotifications(),
    loadSystemAlerts(),
    loadStats(),
  ]);
};

const loadStats = async () => {
  try {
    const res = await notificationsService.getStats();
    const data = res.data || {};
    totalCount.value = data.total ?? totalCount.value;
    unreadCount.value = data.unread ?? unreadCount.value;
    readCount.value = data.read ?? readCount.value;
    systemAlertsCount.value = data.system_alerts_count ?? systemAlertsCount.value;
  } catch (e) {
    // silent
  }
};

const loadUsers = async () => {
  try {
    const res = await notificationsService.getUsers();
    users.value = res.data || [];
  } catch (error) {
    console.error('Failed to load users:', error);
  }
};

const loadNotifications = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
    };
    if (filters.value.search) params.search = filters.value.search.trim();
    if (filters.value.type) params.type = filters.value.type;
    if (filters.value.status === 'unread') params.unread_only = 1;
    if (filters.value.status === 'read') params.status = 'read';

    const response = await notificationsService.getNotifications(params);
    const resData = response.data || {};
    const rows = Array.isArray(resData.data) ? resData.data : (Array.isArray(resData) ? resData : []);

    notifications.value = rows;
    pagination.value.total = resData.total ?? rows.length;

    if (resData.summary) {
      totalCount.value = resData.summary.total ?? totalCount.value;
      unreadCount.value = resData.summary.unread ?? unreadCount.value;
      readCount.value = resData.summary.read ?? readCount.value;
    }
  } catch (error) {
    ElMessage.error(t('common.load_error'));
  } finally {
    loading.value = false;
  }
};

const loadSystemAlerts = async () => {
  alertsLoading.value = true;
  try {
    const res = await notificationsService.getSystemAlerts();
    const data = res.data || {};
    systemAlerts.value = data.alerts || [];
    systemAlertsCount.value = data.total_alerts || systemAlerts.value.length;
  } catch (e) {
    // silent
  } finally {
    alertsLoading.value = false;
  }
};

const handleSearch = () => {
  pagination.value.page = 1;
  loadNotifications();
};

const handleFilterChange = () => {
  pagination.value.page = 1;
  loadNotifications();
};

const resetFilters = () => {
  filters.value = { search: '', type: '', status: '' };
  pagination.value.page = 1;
  loadNotifications();
};

const handleSelectionChange = (rows) => {
  selectedRows.value = rows;
};

const clearSelection = () => {
  selectedRows.value = [];
};

// Item Actions
const viewNotification = async (notification) => {
  selectedNotification.value = notification;
  showViewDialog.value = true;
  if (!notification.is_read) {
    await toggleReadStatus(notification, true);
  }
};

const toggleReadStatus = async (notification, silent = false) => {
  try {
    if (!notification.is_read) {
      await notificationsService.markAsRead(notification.id);
      notification.is_read = true;
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
      readCount.value = readCount.value + 1;
      notificationsStore.unreadCount = unreadCount.value;
      if (!silent) ElMessage.success(t('notifications.marked_read'));
    }
  } catch (error) {
    ElMessage.error(t('common.action_error'));
  }
};

const markAllAsRead = async () => {
  try {
    await notificationsService.markAllAsRead();
    ElMessage.success(t('notifications.all_marked_read'));
    notifications.value.forEach((n) => {
      n.is_read = true;
      n.read_at = n.read_at || new Date().toISOString();
    });
    unreadCount.value = 0;
    readCount.value = totalCount.value;
    notificationsStore.unreadCount = 0;
  } catch (error) {
    ElMessage.error(t('common.action_error'));
  }
};

const deleteSingle = async (notification) => {
  try {
    await ElMessageBox.confirm(t('common.delete_confirm'), t('common.warning'), {
      type: 'warning',
      confirmButtonText: t('common.delete'),
      cancelButtonText: t('common.cancel'),
    });
    await notificationsService.deleteNotification(notification.id);
    ElMessage.success(t('common.delete_success'));
    await refreshAll();
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(t('common.delete_error'));
  }
};

const deleteFromDialog = async () => {
  if (!selectedNotification.value) return;
  const n = selectedNotification.value;
  showViewDialog.value = false;
  await deleteSingle(n);
};

const deleteAllRead = async () => {
  try {
    await ElMessageBox.confirm(
      t('notifications.delete_all_read_confirm') || 'هل أنت متأكد من حذف جميع الإشعارات المقروءة؟',
      t('common.warning'),
      { type: 'warning', confirmButtonText: t('common.delete'), cancelButtonText: t('common.cancel') }
    );
    await notificationsService.deleteAllRead();
    ElMessage.success(t('common.delete_success'));
    await refreshAll();
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(t('common.delete_error'));
  }
};

const handleBatchMarkRead = async () => {
  if (selectedRowIds.value.length === 0) return;
  try {
    await notificationsService.markMultipleAsRead(selectedRowIds.value);
    ElMessage.success(t('notifications.marked_read'));
    clearSelection();
    await refreshAll();
  } catch (error) {
    ElMessage.error(t('common.action_error'));
  }
};

const handleBatchDelete = async () => {
  if (selectedRowIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(
      t('notifications.delete_selected_confirm', { count: selectedRowIds.value.length }) || `هل أنت متأكد من حذف ${selectedRowIds.value.length} إشعار؟`,
      t('common.warning'),
      { type: 'warning', confirmButtonText: t('common.delete'), cancelButtonText: t('common.cancel') }
    );
    await notificationsService.deleteMultiple(selectedRowIds.value);
    ElMessage.success(t('common.delete_success'));
    clearSelection();
    await refreshAll();
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(t('common.delete_error'));
  }
};

const goToTargetRoute = (route) => {
  if (!route) return;
  showViewDialog.value = false;
  router.push(route);
};

// Send Dialog
const openSendDialog = () => {
  sendForm.value = {
    send_to: 'user',
    user_id: users.value.length ? users.value[0].id : null,
    title: '',
    message: '',
    type: 'info',
    route: '',
    channels: ['in_app'],
  };
  showSendDialog.value = true;
};

const handleSendSubmit = async () => {
  if (!sendFormRef.value) return;
  await sendFormRef.value.validate(async (valid) => {
    if (valid) {
      sending.value = true;
      try {
        await notificationsService.sendNotification(sendForm.value);
        ElMessage.success(t('notifications.sent_successfully'));
        showSendDialog.value = false;
        await refreshAll();
      } catch (error) {
        ElMessage.error(t('common.save_error'));
      } finally {
        sending.value = false;
      }
    }
  });
};

// Formatting Helpers
const getTypeTag = (type) => {
  const map = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    error: 'danger',
    order: 'primary',
    inventory: 'warning',
    warehouse: 'primary',
    financial: 'success',
    system: 'info',
  };
  return map[type] || 'info';
};

const getTypeIcon = (type) => {
  const map = {
    info: Bell,
    success: CircleCheck,
    warning: Warning,
    error: WarningFilled,
    order: ShoppingCart,
    inventory: Box,
    warehouse: Box,
    financial: Money,
    system: Bell,
  };
  return map[type] || Bell;
};

const formatTypeName = (type) => {
  const map = {
    info: t('notifications.info'),
    success: t('notifications.success'),
    warning: t('notifications.warning'),
    error: t('notifications.error'),
    order: t('notifications.order_updates') || 'طلب',
    inventory: t('notifications.inventory_alerts') || 'مخزون',
    warehouse: t('notifications.warehouse') || 'مستودع',
    financial: t('notifications.payment_notifications') || 'مالي',
    system: t('notifications.system_messages') || 'نظام',
  };
  return map[type] || type;
};

const formatSeverity = (sev) => {
  if (sev === 'critical') return t('notifications.critical') || 'حرج';
  if (sev === 'warning') return t('notifications.warning');
  return t('notifications.info');
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatTimeAgo = (dateStr) => {
  if (!dateStr) return '';
  const diffMs = Date.now() - new Date(dateStr).getTime();
  const minutes = Math.floor(diffMs / 60000);
  const isAr = locale.value === 'ar';
  if (minutes < 1) return isAr ? 'الآن' : 'just now';
  if (minutes < 60) return isAr ? `منذ ${minutes} دقيقة` : `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return isAr ? `منذ ${hours} ساعة` : `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return isAr ? `منذ ${days} يوم` : `${days}d ago`;
};

onMounted(() => {
  loadUsers();
  refreshAll();
});
</script>

<style scoped>
.notifications-page {
  padding: 1.5rem;
}

.stats-row {
  margin-bottom: 1.5rem;
}

.stat-card {
  border-radius: 14px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  transition: all 0.25s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  color: white;
  flex-shrink: 0;
}

.icon-blue { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35); }
.icon-orange { background: linear-gradient(135deg, #f97316 0%, #c2410c 100%); box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35); }
.icon-green { background: linear-gradient(135deg, #10b981 0%, #047857 100%); box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); }
.icon-red { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35); }

.card-alerts.has-alerts {
  border-color: #fca5a5;
  background: linear-gradient(to bottom, #fff5f5, #ffffff);
}

.stat-info h3 {
  margin: 0;
  font-size: 1.65rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
}

.stat-info p {
  margin: 0.25rem 0 0 0;
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 500;
}

.main-card {
  border-radius: 14px;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.tab-label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
}

.tab-badge {
  margin-inline-start: 0.25rem;
}

.filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 10px;
}

.filter-inputs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
}

.search-input {
  width: 260px;
}

.filter-select {
  width: 170px;
}

.filter-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.batch-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
}

.batch-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #1d4ed8;
  font-weight: 600;
  font-size: 0.88rem;
}

.batch-btns {
  display: flex;
  gap: 0.5rem;
}

.status-dot {
  display: inline-block;
  width: 9px;
  height: 9px;
  border-radius: 999px;
  background: #cbd5e1;
}

.status-dot.unread {
  background: #3b82f6;
  box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
}

.title-cell {
  cursor: pointer;
  display: flex;
  align-items: center;
}

.title-cell.unread .title-text {
  font-weight: 700;
  color: #0f172a;
}

.title-text {
  color: #334155;
  transition: color 0.15s ease;
}

.title-cell:hover .title-text {
  color: #2563eb;
}

.message-preview {
  color: #64748b;
  font-size: 0.86rem;
}

.time-cell {
  display: flex;
  flex-direction: column;
}

.exact-time {
  font-size: 0.82rem;
  color: #334155;
}

.relative-time {
  font-size: 0.74rem;
  color: #94a3b8;
}

.row-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.pagination-container {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.5rem;
}

/* System Alerts Center Styles */
.alerts-tab-content {
  padding: 0.5rem 0;
}

.alerts-intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding: 1rem 1.25rem;
  background: #f8fafc;
  border-radius: 10px;
}

.intro-text h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}

.intro-text p {
  margin: 0.25rem 0 0 0;
  font-size: 0.83rem;
  color: #64748b;
}

.alerts-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 4rem 1rem;
  color: #64748b;
}

.alerts-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
  padding: 4rem 1rem;
  color: #64748b;
}

.alerts-empty-state h3 {
  margin: 0.5rem 0 0 0;
  color: #0f172a;
  font-size: 1.2rem;
}

.alerts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.25rem;
}

.alert-card {
  border-radius: 12px;
  transition: all 0.25s ease;
}

.alert-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.alert-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.header-main {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.alert-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.severity-pill {
  display: inline-block;
  align-self: flex-start;
  padding: 0.15rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.severity-pill.critical { background: #fee2e2; color: #dc2626; }
.severity-pill.warning { background: #fef3c7; color: #d97706; }
.severity-pill.info { background: #dbeafe; color: #2563eb; }

.count-badge {
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-size: 0.95rem;
  font-weight: 800;
}

.count-badge.critical { background: #fee2e2; color: #b91c1c; }
.count-badge.warning { background: #fef3c7; color: #b45309; }
.count-badge.info { background: #e0e7ff; color: #3730a3; }

.alert-desc {
  margin: 0 0 1rem 0;
  font-size: 0.86rem;
  line-height: 1.45;
  color: #475569;
}

.alert-items-preview {
  margin-bottom: 1.25rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
}

.preview-title {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.4rem;
}

.preview-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.sample-chip {
  padding: 0.2rem 0.5rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 600;
  color: #334155;
}

.alert-card-footer {
  display: flex;
  justify-content: flex-end;
}

.alert-card.alert-severity-critical {
  border-left: 4px solid #ef4444;
}

[dir="rtl"] .alert-card.alert-severity-critical {
  border-left: 1px solid rgba(0, 0, 0, 0.05);
  border-right: 4px solid #ef4444;
}

.alert-card.alert-severity-warning {
  border-left: 4px solid #f59e0b;
}

[dir="rtl"] .alert-card.alert-severity-warning {
  border-left: 1px solid rgba(0, 0, 0, 0.05);
  border-right: 4px solid #f59e0b;
}

.alert-card.alert-severity-info {
  border-left: 4px solid #3b82f6;
}

[dir="rtl"] .alert-card.alert-severity-info {
  border-left: 1px solid rgba(0, 0, 0, 0.05);
  border-right: 4px solid #3b82f6;
}

/* Detail Modal */
.notification-detail-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.detail-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.detail-time {
  font-size: 0.8rem;
  color: #94a3b8;
}

.detail-message-box {
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.detail-message-box p {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.6;
  color: #1e293b;
  white-space: pre-wrap;
}

.read-receipt {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: #059669;
}

.target-link-box {
  display: flex;
  justify-content: center;
  padding-top: 0.5rem;
}

.detail-footer {
  display: flex;
  justify-content: space-between;
}
</style>
