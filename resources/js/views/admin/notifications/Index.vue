<template>
  <div class="notifications-page">
    <AdminPageHeader :title="t('nf_title')" :subtitle="t('nf_subtitle')">
      <template #actions>
        <el-button :icon="Refresh" :loading="loading" @click="refreshAll">
          {{ t('nf_refresh') }}
        </el-button>
        <el-dropdown trigger="click" @command="onMoreCommand">
          <el-button :icon="MoreFilled">{{ t('nf_more') }}</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="preferences" :icon="Setting">{{ t('nf_open_preferences') }}</el-dropdown-item>
              <el-dropdown-item command="delete-read" :icon="Delete" :disabled="summary.read === 0" divided>
                {{ t('nf_delete_all_read', { count: summary.read }) }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
        <el-button :icon="Check" :disabled="summary.unread === 0" @click="markAllAsRead">
          {{ t('nf_mark_all_read') }}
        </el-button>
        <el-button v-if="canSend" type="primary" :icon="Promotion" @click="openSendDialog">
          {{ t('nf_send') }}
        </el-button>
      </template>
    </AdminPageHeader>

    <el-card class="main-card" shadow="never">
      <el-tabs v-model="activeTab" class="notification-tabs">
        <!-- Inbox -->
        <el-tab-pane name="feed">
          <template #label>
            <span class="tab-label">
              <el-icon><Bell /></el-icon>
              <span>{{ t('nf_tab_inbox') }}</span>
              <span v-if="summary.unread > 0" class="tab-count">{{ summary.unread }}</span>
            </span>
          </template>

          <!-- Status + search -->
          <div class="toolbar">
            <div class="status-switch" role="tablist">
              <button
                v-for="s in statusOptions"
                :key="s.value"
                type="button"
                role="tab"
                class="status-btn"
                :class="{ active: filters.status === s.value }"
                :aria-selected="filters.status === s.value"
                @click="setStatus(s.value)"
              >
                {{ s.label }}
                <span class="status-num">{{ s.count }}</span>
              </button>
            </div>
            <el-input
              v-model="filters.search"
              :placeholder="t('nf_search_placeholder')"
              :prefix-icon="Search"
              clearable
              class="nf-search"
            />
          </div>

          <!-- Categories -->
          <div class="category-chips">
            <button
              v-for="c in categoryOptions"
              :key="c.value || 'all'"
              type="button"
              class="chip"
              :class="[{ active: filters.category === c.value }, c.value ? `cat-${c.value}` : '']"
              @click="setCategory(c.value)"
            >
              <el-icon v-if="c.icon" :size="14"><component :is="c.icon" /></el-icon>
              <span>{{ c.label }}</span>
              <span v-if="c.unread > 0" class="chip-unread">{{ c.unread }}</span>
            </button>
          </div>

          <!-- Selection bar -->
          <div v-if="notifications.length" class="select-bar" :class="{ 'has-selection': selectedIds.length > 0 }">
            <el-checkbox
              :model-value="allOnPageSelected"
              :indeterminate="selectedIds.length > 0 && !allOnPageSelected"
              @change="toggleSelectAll"
            >
              {{ selectedIds.length ? t('nf_selected', { count: selectedIds.length }) : t('nf_select_page') }}
            </el-checkbox>
            <div v-if="selectedIds.length" class="select-actions">
              <el-button size="small" :icon="Check" @click="handleBatchMarkRead">{{ t('nf_mark_read') }}</el-button>
              <el-button size="small" type="danger" plain :icon="Delete" @click="handleBatchDelete">{{ t('nf_delete') }}</el-button>
              <el-button size="small" text @click="selectedIds = []">{{ t('nf_clear_selection') }}</el-button>
            </div>
          </div>

          <!-- List -->
          <div v-loading="loading" class="inbox">
            <div v-if="!loading && notifications.length === 0" class="empty-state">
              <el-icon :size="44"><component :is="emptyState.icon" /></el-icon>
              <h3>{{ emptyState.title }}</h3>
              <p>{{ emptyState.text }}</p>
              <el-button v-if="hasActiveFilters" size="small" @click="resetFilters">{{ t('nf_clear_filters') }}</el-button>
            </div>

            <section v-for="group in groupedNotifications" :key="group.key" class="day-group">
              <h4 class="day-label">{{ group.label }}</h4>
              <ul class="items">
                <li
                  v-for="item in group.items"
                  :key="item.id"
                  class="item"
                  :class="{ unread: !item.is_read, selected: selectedIds.includes(item.id) }"
                  tabindex="0"
                  @click="openNotification(item)"
                  @keydown.enter="openNotification(item)"
                >
                  <el-checkbox
                    class="item-check"
                    :model-value="selectedIds.includes(item.id)"
                    @click.stop
                    @change="toggleSelected(item.id)"
                  />
                  <div class="item-icon" :class="`cat-${item.category || 'system'}`">
                    <el-icon :size="18"><component :is="typeIcon(item.type)" /></el-icon>
                  </div>
                  <div class="item-body">
                    <div class="item-head">
                      <span class="item-title">{{ item.title }}</span>
                      <span class="item-time" :title="formatDate(item.created_at)">{{ timeAgo(item.created_at) }}</span>
                    </div>
                    <p class="item-message">{{ item.message }}</p>
                    <div class="item-meta">
                      <span class="type-pill" :class="`cat-${item.category || 'system'}`">{{ typeLabel(item.type) }}</span>
                      <button
                        v-if="item.target_route"
                        type="button"
                        class="open-link"
                        @click.stop="goTo(item)"
                      >
                        {{ t('nf_open_related') }}
                        <el-icon :size="12" class="dir-arrow"><ArrowRight /></el-icon>
                      </button>
                    </div>
                  </div>
                  <div class="item-actions" @click.stop>
                    <el-tooltip :content="item.is_read ? t('nf_mark_unread') : t('nf_mark_read')" placement="top">
                      <button type="button" class="icon-btn" :aria-label="item.is_read ? t('nf_mark_unread') : t('nf_mark_read')" @click="toggleRead(item)">
                        <el-icon :size="15"><component :is="item.is_read ? Message : Check" /></el-icon>
                      </button>
                    </el-tooltip>
                    <el-tooltip :content="t('nf_delete')" placement="top">
                      <button type="button" class="icon-btn danger" :aria-label="t('nf_delete')" @click="deleteSingle(item)">
                        <el-icon :size="15"><Delete /></el-icon>
                      </button>
                    </el-tooltip>
                  </div>
                  <span v-if="!item.is_read" class="unread-dot" aria-hidden="true"></span>
                </li>
              </ul>
            </section>
          </div>

          <div v-if="pagination.total > pagination.per_page" class="pagination-container">
            <el-pagination
              v-model:current-page="pagination.page"
              v-model:page-size="pagination.per_page"
              :total="pagination.total"
              :page-sizes="[20, 50, 100]"
              layout="total, sizes, prev, pager, next"
              background
              small
              @size-change="loadNotifications"
              @current-change="loadNotifications"
            />
          </div>
        </el-tab-pane>

        <!-- Live operational alerts -->
        <el-tab-pane name="alerts">
          <template #label>
            <span class="tab-label">
              <el-icon><Warning /></el-icon>
              <span>{{ t('nf_tab_alerts') }}</span>
              <span v-if="systemAlerts.length > 0" class="tab-count danger">{{ systemAlerts.length }}</span>
            </span>
          </template>

          <div class="alerts-intro">
            <p>{{ t('nf_alerts_intro') }}</p>
            <span v-if="alertsCheckedAt" class="checked-at">{{ t('nf_alerts_checked', { time: timeAgo(alertsCheckedAt) }) }}</span>
          </div>

          <div v-loading="alertsLoading" class="alerts-wrap">
            <div v-if="!alertsLoading && systemAlerts.length === 0" class="empty-state">
              <el-icon :size="44" class="ok"><CircleCheck /></el-icon>
              <h3>{{ t('nf_alerts_none_title') }}</h3>
              <p>{{ t('nf_alerts_none_text') }}</p>
            </div>

            <div v-else class="alerts-grid">
              <article
                v-for="alert in systemAlerts"
                :key="alert.id"
                class="alert-card"
                :class="`sev-${alert.severity}`"
              >
                <header class="alert-head">
                  <div class="alert-icon" :class="`cat-${alert.type}`">
                    <el-icon :size="18"><component :is="typeIcon(alert.type)" /></el-icon>
                  </div>
                  <div class="alert-heading">
                    <span class="sev-pill" :class="alert.severity">{{ severityLabel(alert.severity) }}</span>
                    <h4>{{ isAr ? alert.title : (alert.title_en || alert.title) }}</h4>
                  </div>
                  <span class="alert-count">{{ alert.count }}</span>
                </header>
                <p class="alert-desc">{{ isAr ? alert.message : (alert.message_en || alert.message) }}</p>
                <div v-if="alert.items && alert.items.length" class="samples">
                  <span v-for="it in alert.items" :key="it.id" class="sample">
                    {{ it.order_number || it.invoice_number || it.name || it.sku || `#${it.id}` }}
                  </span>
                </div>
                <footer class="alert-foot">
                  <el-button type="primary" plain size="small" @click="router.push(alert.route)">
                    {{ t('nf_alert_action') }}
                    <el-icon class="el-icon--right dir-arrow"><ArrowRight /></el-icon>
                  </el-button>
                </footer>
              </article>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- Detail -->
    <el-drawer
      v-model="showDetail"
      :title="t('nf_details')"
      :direction="isAr ? 'ltr' : 'rtl'"
      size="440px"
      class="detail-drawer"
    >
      <div v-if="current" class="detail">
        <div class="detail-top">
          <div class="item-icon lg" :class="`cat-${current.category || 'system'}`">
            <el-icon :size="22"><component :is="typeIcon(current.type)" /></el-icon>
          </div>
          <div>
            <span class="type-pill" :class="`cat-${current.category || 'system'}`">{{ typeLabel(current.type) }}</span>
            <h3 class="detail-title">{{ current.title }}</h3>
            <span class="detail-time">{{ formatDate(current.created_at) }} · {{ timeAgo(current.created_at) }}</span>
          </div>
        </div>
        <p class="detail-message">{{ current.message }}</p>
        <p v-if="current.read_at" class="detail-read">
          <el-icon><Check /></el-icon>
          {{ t('nf_read_at', { time: formatDate(current.read_at) }) }}
        </p>
        <el-button v-if="current.target_route" type="primary" class="detail-go" @click="goTo(current)">
          {{ t('nf_open_related') }}
          <el-icon class="el-icon--right dir-arrow"><ArrowRight /></el-icon>
        </el-button>
      </div>
      <template #footer>
        <div class="detail-footer">
          <el-button type="danger" plain :icon="Delete" @click="deleteSingle(current, true)">{{ t('nf_delete') }}</el-button>
          <el-button v-if="current" :icon="Message" @click="toggleRead(current); showDetail = false">
            {{ current.is_read ? t('nf_mark_unread') : t('nf_mark_read') }}
          </el-button>
        </div>
      </template>
    </el-drawer>

    <!-- Compose -->
    <el-dialog v-model="showSendDialog" :title="t('nf_send')" width="600px" destroy-on-close>
      <el-form ref="sendFormRef" :model="sendForm" :rules="sendRules" label-position="top">
        <el-form-item :label="t('nf_send_to')" prop="send_to">
          <el-radio-group v-model="sendForm.send_to">
            <el-radio-button value="user">{{ t('nf_send_to_user') }}</el-radio-button>
            <el-radio-button value="all_admins">{{ t('nf_send_to_admins') }}</el-radio-button>
            <el-radio-button value="all_users">{{ t('nf_send_to_all') }}</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="sendForm.send_to === 'user'" :label="t('nf_recipient')" prop="user_id">
          <el-select v-model="sendForm.user_id" :placeholder="t('nf_recipient_placeholder')" filterable class="w-full">
            <el-option v-for="user in users" :key="user.id" :value="user.id" :label="`${user.name} (${user.email})`" />
          </el-select>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :xs="24" :sm="16">
            <el-form-item :label="t('nf_field_title')" prop="title">
              <el-input v-model="sendForm.title" maxlength="255" />
            </el-form-item>
          </el-col>
          <el-col :xs="24" :sm="8">
            <el-form-item :label="t('nf_field_type')" prop="type">
              <el-select v-model="sendForm.type" class="w-full">
                <el-option v-for="type in sendTypes" :key="type" :value="type" :label="typeLabel(type)" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item :label="t('nf_field_message')" prop="message">
          <el-input v-model="sendForm.message" type="textarea" :rows="4" />
        </el-form-item>

        <el-form-item :label="t('nf_field_route')" prop="route">
          <el-input v-model="sendForm.route" placeholder="/admin/stock" dir="ltr" />
        </el-form-item>

        <el-form-item :label="t('nf_field_channels')" prop="channels">
          <el-checkbox-group v-model="sendForm.channels">
            <el-checkbox value="in_app">{{ t('nf_channel_in_app') }}</el-checkbox>
            <el-checkbox value="email">{{ t('nf_channel_email') }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <p class="form-hint">{{ t('nf_channels_hint') }}</p>
      </el-form>

      <template #footer>
        <el-button @click="showSendDialog = false">{{ t('nf_cancel') }}</el-button>
        <el-button type="primary" :loading="sending" @click="handleSendSubmit">{{ t('nf_send_now') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  Bell, Check, Message, Search, Delete, CircleCheck, Warning, WarningFilled,
  Refresh, ArrowRight, Box, ShoppingCart, Money, MoreFilled, Setting,
  Promotion, House, InfoFilled, Filter
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import notificationsService from '@/services/notifications';
import { useNotificationsStore } from '@/stores/notifications';
import { useAuthStore } from '@/stores/auth';

const { t, locale } = useI18n();
const router = useRouter();
const notificationsStore = useNotificationsStore();
const authStore = useAuthStore();
const isAr = computed(() => locale.value === 'ar');

// Template management and sending are admin-only on the server.
const canSend = computed(() => authStore.isSuperAdmin || authStore.hasRole(['admin', 'master']));

const CATEGORIES = ['order', 'inventory', 'warehouse', 'financial', 'system'];

const activeTab = ref('feed');
const loading = ref(false);
const alertsLoading = ref(false);
const notifications = ref([]);
const systemAlerts = ref([]);
const alertsCheckedAt = ref(null);
const selectedIds = ref([]);
const summary = ref({ total: 0, unread: 0, read: 0 });
const categoryCounts = ref({});
const filters = ref({ search: '', category: '', status: '' });
const pagination = ref({ page: 1, per_page: 20, total: 0 });

// ---- Labels & icons ----
const typeIcon = (type) => ({
  success: CircleCheck,
  warning: Warning,
  error: WarningFilled,
  order: ShoppingCart,
  inventory: Box,
  warehouse: House,
  financial: Money,
  system: Setting,
}[type] || InfoFilled);

const typeLabel = (type) => (
  ['info', 'success', 'warning', 'error', ...CATEGORIES].includes(type) ? t(`nf_type_${type}`) : type
);

const severityLabel = (sev) => t(`nf_sev_${['critical', 'warning'].includes(sev) ? sev : 'info'}`);

const statusOptions = computed(() => [
  { value: '', label: t('nf_status_all'), count: summary.value.total },
  { value: 'unread', label: t('nf_status_unread'), count: summary.value.unread },
  { value: 'read', label: t('nf_status_read'), count: summary.value.read },
]);

const categoryOptions = computed(() => [
  { value: '', label: t('nf_cat_all'), icon: Filter, unread: 0 },
  ...CATEGORIES.map((c) => ({
    value: c,
    label: t(`nf_cat_${c}`),
    icon: typeIcon(c),
    unread: categoryCounts.value[c]?.unread || 0,
  })),
]);

const hasActiveFilters = computed(() => !!(filters.value.search || filters.value.category || filters.value.status));

const emptyState = computed(() => {
  if (filters.value.search || filters.value.category) {
    return { icon: Search, title: t('nf_empty_filtered_title'), text: t('nf_empty_filtered_text') };
  }
  if (filters.value.status === 'unread') {
    return { icon: CircleCheck, title: t('nf_empty_unread_title'), text: t('nf_empty_unread_text') };
  }
  return { icon: Bell, title: t('nf_empty_title'), text: t('nf_empty_text') };
});

// ---- Grouping by day ----
const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

const groupedNotifications = computed(() => {
  const today = new Date();
  const yesterday = new Date(Date.now() - 86400000);
  const groups = [];
  const index = {};
  for (const item of notifications.value) {
    const d = new Date(item.created_at);
    const key = dayKey(d);
    if (!index[key]) {
      let label;
      if (key === dayKey(today)) label = t('nf_today');
      else if (key === dayKey(yesterday)) label = t('nf_yesterday');
      else label = d.toLocaleDateString(isAr.value ? 'ar-SY' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
      index[key] = { key, label, items: [] };
      groups.push(index[key]);
    }
    index[key].items.push(item);
  }
  return groups;
});

// ---- Loading ----
const loadNotifications = async () => {
  loading.value = true;
  try {
    const params = { page: pagination.value.page, per_page: pagination.value.per_page };
    if (filters.value.search.trim()) params.search = filters.value.search.trim();
    if (filters.value.category) params.category = filters.value.category;
    if (filters.value.status) params.status = filters.value.status;

    const { data } = await notificationsService.getNotifications(params);
    notifications.value = Array.isArray(data?.data) ? data.data : [];
    pagination.value.total = data?.total ?? notifications.value.length;
    if (data?.summary) summary.value = data.summary;
    if (data?.categories) categoryCounts.value = data.categories;
    notificationsStore.unreadCount = summary.value.unread;

    // A page emptied by deletes or filtering falls back to the last page.
    if (notifications.value.length === 0 && pagination.value.page > 1 && data?.last_page) {
      pagination.value.page = data.last_page;
      return loadNotifications();
    }
    const onPage = new Set(notifications.value.map((n) => n.id));
    selectedIds.value = selectedIds.value.filter((id) => onPage.has(id));
  } catch (error) {
    ElMessage.error(t('nf_load_error'));
  } finally {
    loading.value = false;
  }
};

const loadSystemAlerts = async () => {
  alertsLoading.value = true;
  try {
    const { data } = await notificationsService.getSystemAlerts();
    systemAlerts.value = data?.alerts || [];
    alertsCheckedAt.value = data?.timestamp || new Date().toISOString();
  } catch (e) {
    // Leave the last result in place
  } finally {
    alertsLoading.value = false;
  }
};

const refreshAll = () => Promise.all([loadNotifications(), loadSystemAlerts()]);

// ---- Filters ----
const setStatus = (value) => {
  filters.value.status = value;
  pagination.value.page = 1;
  loadNotifications();
};

const setCategory = (value) => {
  filters.value.category = value;
  pagination.value.page = 1;
  loadNotifications();
};

const resetFilters = () => {
  filters.value = { search: '', category: '', status: '' };
  pagination.value.page = 1;
  loadNotifications();
};

let searchTimer = null;
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    pagination.value.page = 1;
    loadNotifications();
  }, 350);
});

// ---- Selection ----
const allOnPageSelected = computed(() => notifications.value.length > 0 && selectedIds.value.length === notifications.value.length);

const toggleSelectAll = () => {
  selectedIds.value = allOnPageSelected.value ? [] : notifications.value.map((n) => n.id);
};

const toggleSelected = (id) => {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((x) => x !== id)
    : [...selectedIds.value, id];
};

// ---- Item actions ----
const showDetail = ref(false);
const current = ref(null);

const applyRead = (item, isRead) => {
  if (item.is_read === isRead) return;
  item.is_read = isRead;
  item.read_at = isRead ? new Date().toISOString() : null;
  const delta = isRead ? -1 : 1;
  summary.value.unread = Math.max(0, summary.value.unread + delta);
  summary.value.read = Math.max(0, summary.value.read - delta);
  const cat = categoryCounts.value[item.category];
  if (cat) cat.unread = Math.max(0, cat.unread + delta);
  notificationsStore.unreadCount = summary.value.unread;
};

const markRead = async (item) => {
  if (item.is_read) return;
  await notificationsService.markAsRead(item.id);
  applyRead(item, true);
};

const toggleRead = async (item) => {
  try {
    if (item.is_read) {
      await notificationsService.markAsUnread(item.id);
      applyRead(item, false);
    } else {
      await markRead(item);
    }
  } catch (e) {
    ElMessage.error(t('nf_action_error'));
  }
};

const openNotification = (item) => {
  current.value = item;
  showDetail.value = true;
  markRead(item).catch(() => {});
};

const goTo = (item) => {
  markRead(item).catch(() => {});
  showDetail.value = false;
  router.push(item.target_route);
};

const deleteSingle = async (item, fromDetail = false) => {
  if (!item) return;
  try {
    await notificationsService.deleteNotification(item.id);
    if (fromDetail) showDetail.value = false;
    ElMessage.success(t('nf_deleted', { count: 1 }));
    await loadNotifications();
  } catch (e) {
    ElMessage.error(t('nf_action_error'));
  }
};

const markAllAsRead = async () => {
  try {
    await notificationsService.markAllAsRead();
    ElMessage.success(t('nf_all_marked_read'));
    await loadNotifications();
  } catch (e) {
    ElMessage.error(t('nf_action_error'));
  }
};

const handleBatchMarkRead = async () => {
  try {
    await notificationsService.markMultipleAsRead(selectedIds.value);
    selectedIds.value = [];
    await loadNotifications();
  } catch (e) {
    ElMessage.error(t('nf_action_error'));
  }
};

const handleBatchDelete = async () => {
  const count = selectedIds.value.length;
  try {
    await ElMessageBox.confirm(t('nf_delete_selected_confirm', { count }), t('nf_delete'), {
      type: 'warning',
      confirmButtonText: t('nf_delete'),
      cancelButtonText: t('nf_cancel'),
    });
  } catch {
    return;
  }
  try {
    await notificationsService.deleteMultiple(selectedIds.value);
    selectedIds.value = [];
    ElMessage.success(t('nf_deleted', { count }));
    await loadNotifications();
  } catch (e) {
    ElMessage.error(t('nf_action_error'));
  }
};

const onMoreCommand = async (command) => {
  if (command === 'preferences') {
    router.push({ name: 'admin.notifications.preferences' });
    return;
  }
  if (command === 'delete-read') {
    try {
      await ElMessageBox.confirm(t('nf_delete_all_read_confirm', { count: summary.value.read }), t('nf_delete'), {
        type: 'warning',
        confirmButtonText: t('nf_delete'),
        cancelButtonText: t('nf_cancel'),
      });
    } catch {
      return;
    }
    try {
      const { data } = await notificationsService.deleteAllRead();
      ElMessage.success(t('nf_deleted', { count: data?.count ?? summary.value.read }));
      pagination.value.page = 1;
      await loadNotifications();
    } catch (e) {
      ElMessage.error(t('nf_action_error'));
    }
  }
};

// ---- Compose ----
const users = ref([]);
const sending = ref(false);
const showSendDialog = ref(false);
const sendFormRef = ref(null);
const sendTypes = ['info', 'success', 'warning', 'error', ...CATEGORIES];
const blankSendForm = () => ({
  send_to: 'user',
  user_id: null,
  title: '',
  message: '',
  type: 'info',
  route: '',
  channels: ['in_app'],
});
const sendForm = ref(blankSendForm());

const sendRules = computed(() => ({
  title: [{ required: true, message: t('nf_title_required'), trigger: 'blur' }],
  message: [{ required: true, message: t('nf_message_required'), trigger: 'blur' }],
  user_id: [{
    validator: (rule, value, cb) => (sendForm.value.send_to === 'user' && !value ? cb(new Error(t('nf_recipient_required'))) : cb()),
    trigger: 'change',
  }],
  route: [{
    validator: (rule, value, cb) => (value && !/^\/(?!\/)/.test(value) ? cb(new Error(t('nf_route_invalid'))) : cb()),
    trigger: 'blur',
  }],
  channels: [{ type: 'array', required: true, min: 1, message: t('nf_channels_required'), trigger: 'change' }],
}));

const openSendDialog = async () => {
  sendForm.value = blankSendForm();
  showSendDialog.value = true;
  if (!users.value.length) {
    try {
      const { data } = await notificationsService.getUsers();
      users.value = data || [];
    } catch (e) {
      // Recipient list stays empty; sending to groups still works
    }
  }
};

const handleSendSubmit = async () => {
  if (!sendFormRef.value) return;
  try {
    await sendFormRef.value.validate();
  } catch {
    return;
  }
  sending.value = true;
  try {
    const { data } = await notificationsService.sendNotification(sendForm.value);
    ElMessage.success(t('nf_sent', { count: data?.count ?? 1 }));
    showSendDialog.value = false;
    await loadNotifications();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('nf_action_error'));
  } finally {
    sending.value = false;
  }
};

// ---- Formatting ----
const formatDate = (value) => {
  if (!value) return '';
  return new Date(value).toLocaleString(isAr.value ? 'ar-SY' : 'en-GB', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
};

const timeAgo = (value) => {
  if (!value) return '';
  const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000);
  const rtf = new Intl.RelativeTimeFormat(isAr.value ? 'ar' : 'en', { numeric: 'auto' });
  if (minutes < 1) return t('nf_just_now');
  if (minutes < 60) return rtf.format(-minutes, 'minute');
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return rtf.format(-hours, 'hour');
  return rtf.format(-Math.floor(hours / 24), 'day');
};

// Keep the list in step with the header's polling: when new notifications
// arrive while this page is open, reload the first page.
watch(() => notificationsStore.unreadCount, (count) => {
  if (count > summary.value.unread && pagination.value.page === 1 && !loading.value) {
    loadNotifications();
  }
});

onMounted(refreshAll);
onUnmounted(() => clearTimeout(searchTimer));
</script>

<style scoped>
.notifications-page {
  padding: 1.5rem;
  --cat-order: #2563eb;
  --cat-inventory: #d97706;
  --cat-warehouse: #0891b2;
  --cat-financial: #059669;
  --cat-system: #64748b;
}

.main-card {
  border-radius: 14px;
}

.tab-label {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 600;
}

.tab-count {
  min-width: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--el-color-primary);
  color: #fff;
  font-size: 11px;
  line-height: 18px;
  text-align: center;
}

.tab-count.danger {
  background: var(--el-color-danger);
}

/* Toolbar */
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.status-switch {
  display: inline-flex;
  padding: 3px;
  border-radius: 10px;
  background: var(--el-fill-color-light);
}

.status-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.9rem;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--el-text-color-regular);
  font: inherit;
  font-size: 0.86rem;
  font-weight: 600;
  cursor: pointer;
}

.status-btn.active {
  background: var(--el-bg-color);
  color: var(--el-color-primary);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.status-num {
  font-size: 0.75rem;
  color: var(--el-text-color-secondary);
  font-variant-numeric: tabular-nums;
}

.nf-search {
  width: 280px;
  max-width: 100%;
}

.category-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.75rem;
  border: 1px solid var(--el-border-color);
  border-radius: 999px;
  background: var(--el-bg-color);
  color: var(--el-text-color-regular);
  font: inherit;
  font-size: 0.82rem;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.chip:hover {
  border-color: var(--el-color-primary-light-5);
}

.chip.active {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
  font-weight: 600;
}

.chip-unread {
  min-width: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--el-color-danger);
  color: #fff;
  font-size: 10.5px;
  line-height: 16px;
  text-align: center;
}

.select-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 40px;
  padding: 0.25rem 0.75rem;
  margin-bottom: 0.5rem;
  border-radius: 8px;
}

.select-bar.has-selection {
  background: var(--el-color-primary-light-9);
}

.select-actions {
  display: flex;
  gap: 0.4rem;
}

/* Inbox list */
.inbox {
  min-height: 160px;
}

.day-group + .day-group {
  margin-top: 1rem;
}

.day-label {
  margin: 0 0 0.4rem;
  padding: 0 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--el-text-color-secondary);
}

.items {
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 12px;
  overflow: hidden;
}

.item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.85rem 0.75rem;
  cursor: pointer;
  transition: background 0.15s;
  outline: none;
}

.item + .item {
  border-top: 1px solid var(--el-border-color-lighter);
}

.item:hover,
.item:focus-visible {
  background: var(--el-fill-color-light);
}

.item.unread {
  background: var(--el-color-primary-light-9);
}

.item.unread:hover {
  background: var(--el-color-primary-light-8);
}

.item.selected {
  box-shadow: inset 3px 0 0 var(--el-color-primary);
}

[dir='rtl'] .item.selected {
  box-shadow: inset -3px 0 0 var(--el-color-primary);
}

.item-check {
  height: 36px;
}

.item-icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: var(--cat, var(--cat-system));
  background: color-mix(in srgb, var(--cat, var(--cat-system)) 12%, transparent);
}

.item-icon.lg {
  width: 48px;
  height: 48px;
  border-radius: 12px;
}

.cat-order { --cat: var(--cat-order); }
.cat-inventory { --cat: var(--cat-inventory); }
.cat-warehouse { --cat: var(--cat-warehouse); }
.cat-financial { --cat: var(--cat-financial); }
.cat-system { --cat: var(--cat-system); }

.item-body {
  flex: 1;
  min-width: 0;
}

.item-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
}

.item-title {
  color: var(--el-text-color-regular);
  font-size: 0.92rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item.unread .item-title {
  color: var(--el-text-color-primary);
  font-weight: 700;
}

.item-time {
  flex-shrink: 0;
  font-size: 0.75rem;
  color: var(--el-text-color-secondary);
}

.item-message {
  display: -webkit-box;
  margin: 0.2rem 0 0.4rem;
  overflow: hidden;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 0.84rem;
  line-height: 1.5;
  color: var(--el-text-color-secondary);
}

.item-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.type-pill {
  display: inline-block;
  padding: 0.05rem 0.5rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--cat, var(--cat-system));
  background: color-mix(in srgb, var(--cat, var(--cat-system)) 12%, transparent);
}

.open-link {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  padding: 0;
  border: 0;
  background: none;
  color: var(--el-color-primary);
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.open-link:hover {
  text-decoration: underline;
}

[dir='rtl'] .dir-arrow {
  transform: scaleX(-1);
}

.item-actions {
  display: flex;
  gap: 0.25rem;
  opacity: 0;
  transition: opacity 0.15s;
}

.item:hover .item-actions,
.item:focus-within .item-actions {
  opacity: 1;
}

@media (hover: none) {
  .item-actions {
    opacity: 1;
  }
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 1px solid var(--el-border-color);
  border-radius: 8px;
  background: var(--el-bg-color);
  color: var(--el-text-color-regular);
  cursor: pointer;
}

.icon-btn:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}

.icon-btn.danger:hover {
  border-color: var(--el-color-danger);
  color: var(--el-color-danger);
}

.unread-dot {
  position: absolute;
  top: 50%;
  inset-inline-start: 4px;
  width: 6px;
  height: 6px;
  margin-top: -3px;
  border-radius: 50%;
  background: var(--el-color-primary);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 3.5rem 1rem;
  text-align: center;
  color: var(--el-text-color-secondary);
}

.empty-state h3 {
  margin: 0.4rem 0 0;
  color: var(--el-text-color-primary);
  font-size: 1.05rem;
}

.empty-state p {
  margin: 0 0 0.5rem;
  font-size: 0.86rem;
}

.empty-state .ok {
  color: var(--el-color-success);
}

.pagination-container {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.25rem;
}

/* Alerts */
.alerts-intro {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.86rem;
  color: var(--el-text-color-secondary);
}

.alerts-intro p {
  margin: 0;
}

.checked-at {
  font-size: 0.78rem;
}

.alerts-wrap {
  min-height: 160px;
}

.alerts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1rem;
}

.alert-card {
  display: flex;
  flex-direction: column;
  padding: 1rem 1.1rem;
  border: 1px solid var(--el-border-color-lighter);
  border-inline-start: 4px solid var(--sev, var(--el-color-info));
  border-radius: 12px;
  background: var(--el-bg-color);
}

.alert-card.sev-critical { --sev: var(--el-color-danger); }
.alert-card.sev-warning { --sev: var(--el-color-warning); }
.alert-card.sev-info { --sev: var(--el-color-primary); }

.alert-head {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.alert-icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: var(--cat, var(--cat-system));
  background: color-mix(in srgb, var(--cat, var(--cat-system)) 12%, transparent);
}

.alert-heading {
  flex: 1;
  min-width: 0;
}

.alert-heading h4 {
  margin: 0.2rem 0 0;
  font-size: 0.95rem;
  color: var(--el-text-color-primary);
}

.sev-pill {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--sev);
}

.alert-count {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--sev);
  font-variant-numeric: tabular-nums;
}

.alert-desc {
  margin: 0.6rem 0;
  font-size: 0.85rem;
  line-height: 1.5;
  color: var(--el-text-color-regular);
}

.samples {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}

.sample {
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  background: var(--el-fill-color-light);
  font-size: 0.74rem;
  color: var(--el-text-color-regular);
}

.alert-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: auto;
}

/* Detail drawer */
.detail-top {
  display: flex;
  gap: 0.9rem;
  align-items: flex-start;
}

.detail-title {
  margin: 0.35rem 0 0.2rem;
  font-size: 1.05rem;
  color: var(--el-text-color-primary);
}

.detail-time {
  font-size: 0.78rem;
  color: var(--el-text-color-secondary);
}

.detail-message {
  margin: 1.25rem 0;
  padding: 1rem;
  border-radius: 10px;
  background: var(--el-fill-color-light);
  font-size: 0.92rem;
  line-height: 1.7;
  color: var(--el-text-color-primary);
  white-space: pre-wrap;
}

.detail-read {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0 0 1rem;
  font-size: 0.78rem;
  color: var(--el-color-success);
}

.detail-go {
  width: 100%;
}

.detail-footer {
  display: flex;
  justify-content: space-between;
}

.form-hint {
  margin: -0.5rem 0 0;
  font-size: 0.78rem;
  color: var(--el-text-color-secondary);
}

@media (max-width: 640px) {
  .notifications-page {
    padding: 1rem;
  }

  .nf-search {
    width: 100%;
  }

  .status-switch {
    width: 100%;
  }

  .status-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
