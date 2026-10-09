<template>
    <header class="admin-header">
        <div class="header-left">
            <el-button :icon="Menu" circle @click="toggleMobileSidebar" class="mobile-toggle" />

            <div class="header-search" ref="searchWrapperRef">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('quick_search')"
                    :prefix-icon="Search"
                    clearable
                    @focus="onSearchFocus"
                    @keydown="onSearchKeydown"
                    @clear="clearSearch"
                />

                <div v-if="showSearchResults" class="search-results-panel">
                    <div v-if="searchLoading" class="search-state">
                        <el-icon class="is-loading"><Loading /></el-icon>
                        <span>{{ $t('search_searching') }}</span>
                    </div>
                    <div v-else-if="searchQuery.trim().length < 2" class="search-state">
                        <span>{{ $t('search_type_to_search') }}</span>
                    </div>
                    <div v-else-if="totalSearchResults === 0" class="search-state">
                        <span>{{ $t('search_no_results') }}</span>
                    </div>
                    <template v-else>
                        <div v-for="group in searchGroups" :key="group.type" class="search-group">
                            <template v-if="group.items.length">
                                <div class="search-group-title">{{ $t(group.labelKey) }}</div>
                                <div
                                    v-for="item in group.items"
                                    :key="`${group.type}-${item.id}`"
                                    class="search-result-item"
                                    :class="{ active: isActiveResult(group.type, item.id) }"
                                    @mousedown.prevent="goToResult(item)"
                                >
                                    <el-icon class="result-icon"><component :is="group.icon" /></el-icon>
                                    <div class="result-text">
                                        <span class="result-title">{{ item.title }}</span>
                                        <span v-if="item.subtitle" class="result-subtitle">{{ item.subtitle }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="header-right">
            <!-- Language Switcher -->
            <el-dropdown @command="handleLanguageCommand" class="language-dropdown" trigger="click">
                <span class="el-dropdown-link lang-btn">
                    {{ currentLocale === 'ar' ? 'العربية' : 'English' }}
                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                </span>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item command="ar" :class="{ 'active-lang': currentLocale === 'ar' }">{{ $t('arabic') }}</el-dropdown-item>
                        <el-dropdown-item command="en" :class="{ 'active-lang': currentLocale === 'en' }">English</el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <el-popover
                v-model:visible="notificationsOpen"
                trigger="click"
                placement="bottom-end"
                :width="430"
                popper-class="notifications-center-popover"
            >
                <template #reference>
                    <el-badge
                        :value="notificationsStore.totalBadgeCount"
                        :hidden="notificationsStore.totalBadgeCount === 0"
                        :is-dot="notificationsStore.unreadCount === 0 && notificationsStore.systemAlertsCount > 0"
                        class="notification-badge"
                        :class="{ 'has-critical': notificationsStore.hasCriticalAlerts }"
                    >
                        <el-button :icon="Bell" circle @click="onOpenNotifications" class="bell-btn" />
                    </el-badge>
                </template>

                <div class="notifications-center">
                    <!-- Top Bar with Tabs and Quick Actions -->
                    <div class="nc-top-bar">
                        <div class="nc-tabs">
                            <button
                                type="button"
                                class="nc-tab-btn"
                                :class="{ active: activeNotificationTab === 'notifications' }"
                                @click="activeNotificationTab = 'notifications'"
                            >
                                <span>{{ t('notifications.title') }}</span>
                                <span v-if="notificationsStore.unreadCount > 0" class="nc-tab-badge">
                                    {{ notificationsStore.unreadCount }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="nc-tab-btn"
                                :class="{ active: activeNotificationTab === 'alerts' }"
                                @click="activeNotificationTab = 'alerts'"
                            >
                                <span>{{ t('notifications.system_alerts') || 'تنبيهات النظام' }}</span>
                                <span
                                    v-if="notificationsStore.systemAlertsCount > 0"
                                    class="nc-tab-badge"
                                    :class="{ 'critical': notificationsStore.hasCriticalAlerts }"
                                >
                                    {{ notificationsStore.systemAlertsCount }}
                                </span>
                            </button>
                        </div>

                        <div class="nc-controls">
                            <el-tooltip :content="notificationsStore.soundEnabled ? (t('notifications.sound_enabled') || 'الصوت مفعل') : (t('notifications.sound_disabled') || 'الصوت معطل')" placement="top">
                                <button
                                    type="button"
                                    class="nc-icon-btn"
                                    :class="{ active: notificationsStore.soundEnabled }"
                                    @click="notificationsStore.toggleSound()"
                                >
                                    <el-icon :size="15">
                                        <component :is="notificationsStore.soundEnabled ? BellFilled : MuteNotification" />
                                    </el-icon>
                                </button>
                            </el-tooltip>
                            <el-tooltip :content="t('common.refresh') || 'تحديث'" placement="top">
                                <button
                                    type="button"
                                    class="nc-icon-btn"
                                    :disabled="notificationsStore.loading || notificationsStore.alertsLoading"
                                    @click="refreshNotificationData"
                                >
                                    <el-icon :size="14" :class="{ 'is-loading': notificationsStore.loading || notificationsStore.alertsLoading }">
                                        <Refresh />
                                    </el-icon>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>

                    <!-- TAB 1: User Notifications -->
                    <div v-show="activeNotificationTab === 'notifications'" class="nc-tab-content">
                        <!-- Subheader with Filter and Mark All Read -->
                        <div class="nc-sub-bar">
                            <div class="nc-filter-pills">
                                <button
                                    type="button"
                                    class="nc-pill"
                                    :class="{ active: notificationFilter === 'all' }"
                                    @click="notificationFilter = 'all'"
                                >
                                    {{ t('common.all') || 'الكل' }}
                                </button>
                                <button
                                    type="button"
                                    class="nc-pill"
                                    :class="{ active: notificationFilter === 'unread' }"
                                    @click="notificationFilter = 'unread'"
                                >
                                    {{ t('notifications.unread') }}
                                    <span v-if="notificationsStore.unreadCount > 0">({{ notificationsStore.unreadCount }})</span>
                                </button>
                            </div>

                            <el-button
                                link
                                type="primary"
                                size="small"
                                :disabled="notificationsStore.unreadCount === 0"
                                @click="handleMarkAllAsRead"
                                class="nc-mark-all-btn"
                            >
                                <el-icon :size="12"><Check /></el-icon>
                                <span>{{ t('notifications.mark_all_read') }}</span>
                            </el-button>
                        </div>

                        <!-- Notification List -->
                        <div v-if="notificationsStore.loading" class="nc-state-box">
                            <el-icon class="is-loading" :size="22"><Loading /></el-icon>
                            <span>{{ t('common.loading') || 'جاري التحميل...' }}</span>
                        </div>
                        <div v-else-if="filteredNotifications.length === 0" class="nc-state-box">
                            <el-icon :size="32" class="text-slate-300"><Bell /></el-icon>
                            <span>{{ notificationFilter === 'unread' ? (t('notifications.no_unread') || 'لا توجد إشعارات غير مقروءة') : t('no_notifications') }}</span>
                        </div>
                        <div v-else class="nc-list">
                            <div
                                v-for="item in filteredNotifications"
                                :key="item.id"
                                class="nc-item"
                                :class="{ unread: !item.is_read }"
                                @click="handleNotificationClick(item)"
                            >
                                <div class="nc-item-icon" :class="`icon-${item.type}`">
                                    <el-icon :size="16"><component :is="getNotificationIcon(item.type)" /></el-icon>
                                </div>
                                <div class="nc-item-body">
                                    <div class="nc-item-header">
                                        <span class="nc-item-title">{{ item.title }}</span>
                                        <span class="nc-item-time">{{ timeAgo(item.created_at) }}</span>
                                    </div>
                                    <p class="nc-item-message">{{ item.message }}</p>
                                    <div class="nc-item-actions">
                                        <span v-if="item.target_route" class="nc-target-chip">
                                            <span>{{ t('notifications.view_event') || 'معاينة' }}</span>
                                            <el-icon :size="11"><ArrowRight /></el-icon>
                                        </span>
                                        <div class="nc-quick-btns" @click.stop>
                                            <el-tooltip :content="item.is_read ? (t('notifications.mark_unread') || 'تحديد كغير مقروء') : t('notifications.marked_read')" placement="top">
                                                <button
                                                    type="button"
                                                    class="nc-action-btn"
                                                    @click="handleToggleItemRead(item)"
                                                >
                                                    <el-icon :size="13"><Check /></el-icon>
                                                </button>
                                            </el-tooltip>
                                            <el-tooltip :content="t('common.delete')" placement="top">
                                                <button
                                                    type="button"
                                                    class="nc-action-btn danger"
                                                    @click="handleDeleteItem(item)"
                                                >
                                                    <el-icon :size="13"><Delete /></el-icon>
                                                </button>
                                            </el-tooltip>
                                        </div>
                                    </div>
                                </div>
                                <span v-if="!item.is_read" class="nc-unread-indicator"></span>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: System Business Alerts -->
                    <div v-show="activeNotificationTab === 'alerts'" class="nc-tab-content">
                        <div class="nc-sub-bar">
                            <span class="nc-sub-title">{{ t('notifications.live_system_alerts') || 'تنبيهات فورية للمخزون والطلبات والمالية' }}</span>
                        </div>

                        <div v-if="notificationsStore.alertsLoading" class="nc-state-box">
                            <el-icon class="is-loading" :size="22"><Loading /></el-icon>
                            <span>{{ t('common.loading') || 'جاري فحص تنبيهات النظام...' }}</span>
                        </div>
                        <div v-else-if="notificationsStore.systemAlerts.length === 0" class="nc-state-box ok-state">
                            <el-icon :size="32" class="text-emerald-400"><CircleCheck /></el-icon>
                            <span class="ok-title">{{ t('notifications.all_systems_normal') || 'جميع العمليات بحالة ممتازة' }}</span>
                            <span class="ok-desc">{{ t('notifications.no_operational_alerts') || 'لا توجد طلبات معلقة أو نواقص مخزون حرجة.' }}</span>
                        </div>
                        <div v-else class="nc-alerts-list">
                            <div
                                v-for="alert in notificationsStore.systemAlerts"
                                :key="alert.id"
                                class="nc-alert-card"
                                :class="`severity-${alert.severity}`"
                                @click="handleSystemAlertClick(alert)"
                            >
                                <div class="nc-alert-top">
                                    <div class="nc-alert-heading">
                                        <el-icon :size="18" class="nc-alert-icon">
                                            <component :is="getAlertIcon(alert.type)" />
                                        </el-icon>
                                        <span class="nc-alert-title">{{ currentLocale === 'ar' ? alert.title : (alert.title_en || alert.title) }}</span>
                                    </div>
                                    <span class="nc-alert-badge" :class="alert.severity">
                                        {{ alert.count }}
                                    </span>
                                </div>
                                <p class="nc-alert-msg">{{ currentLocale === 'ar' ? alert.message : (alert.message_en || alert.message) }}</p>

                                <div class="nc-alert-footer">
                                    <button type="button" class="nc-alert-cta-btn">
                                        <span>{{ t('notifications.take_action') || 'معالجة الأمر الآن' }}</span>
                                        <el-icon :size="12"><ArrowRight /></el-icon>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="nc-footer">
                        <button type="button" class="nc-footer-link" @click="goToAllNotifications">
                            <el-icon :size="14"><Document /></el-icon>
                            <span>{{ t('notifications_view_all') || 'عرض كل الإشعارات والتنبيهات' }}</span>
                        </button>
                        <button type="button" class="nc-footer-link secondary" @click="goToPreferences">
                            <el-icon :size="14"><Setting /></el-icon>
                            <span>{{ t('notifications.preferences') || 'التفضيلات' }}</span>
                        </button>
                    </div>
                </div>
            </el-popover>

            <el-dropdown @command="handleDropdownCommand" trigger="click">
                <div class="user-dropdown">
                    <el-avatar :size="36" class="user-avatar">{{ userInitials }}</el-avatar>
                    <span class="user-name">{{ userName }}</span>
                    <el-icon class="dropdown-icon"><ArrowDown /></el-icon>
                </div>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item command="profile">
                            <el-icon><User /></el-icon>
                            <span>{{ t('profile') }}</span>
                        </el-dropdown-item>
                        <el-dropdown-item command="settings">
                            <el-icon><Setting /></el-icon>
                            <span>{{ t('settings') }}</span>
                        </el-dropdown-item>
                        <el-dropdown-item divided command="logout">
                            <el-icon><SwitchButton /></el-icon>
                            <span>{{ t('logout') }}</span>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
        </div>
    </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useI18n } from 'vue-i18n';
import { updateDirection } from '@/app';
import { adminSearchApi } from '@/api/search';
import {
    Menu, Search, Bell, User, Setting,
    SwitchButton, ArrowDown, Loading, Box, ShoppingCart,
    Document, UserFilled, Tickets, Check, Delete, Refresh,
    Warning, WarningFilled, InfoFilled, CircleCheck, ArrowRight,
    Money, MuteNotification, BellFilled
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();
const emit = defineEmits(['toggle-mobile-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const notificationsStore = useNotificationsStore();
const currentLocale = computed(() => locale.value);

const userName = computed(() => authStore.user?.name || authStore.user?.email || t('profile'));
const userInitials = computed(() => {
    const name = userName.value || '';
    return name.trim().slice(0, 1).toUpperCase() || 'A';
});

const handleLanguageCommand = (command) => {
    locale.value = command;
    localStorage.setItem('locale', command);
    updateDirection(command);

    // Keeps the server-rendered <html dir> (set from the session locale on
    // the next full load) in step with this choice, so the page doesn't
    // flash back to RTL for an instant before the client-side switch above
    // re-applies. No reload here — an admin mid-task shouldn't be interrupted
    // just for the session to catch up.
    fetch(`/lang/${command}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => {});
};

const toggleMobileSidebar = () => {
    emit('toggle-mobile-sidebar');
};

const handleDropdownCommand = (command) => {
    switch (command) {
        case 'profile':
            router.push('/admin/profile');
            break;
        case 'settings':
            router.push('/admin/settings');
            break;
        case 'logout':
            authStore.logout();
            break;
    }
};

// ---- Global quick search ----
const searchQuery = ref('');
const searchLoading = ref(false);
const showSearchResults = ref(false);
const searchWrapperRef = ref(null);
const activeResultIndex = ref(-1);
const searchResults = ref({
    products: [],
    customers: [],
    invoices: [],
    sales_orders: [],
    employees: []
});
let searchDebounceTimer = null;

const searchGroups = computed(() => ([
    { type: 'products', labelKey: 'search_products', icon: Box, items: searchResults.value.products },
    { type: 'customers', labelKey: 'search_customers', icon: UserFilled, items: searchResults.value.customers },
    { type: 'invoices', labelKey: 'search_invoices', icon: Document, items: searchResults.value.invoices },
    { type: 'sales_orders', labelKey: 'search_sales_orders', icon: ShoppingCart, items: searchResults.value.sales_orders },
    { type: 'employees', labelKey: 'search_employees', icon: Tickets, items: searchResults.value.employees }
]));

const flatResults = computed(() => searchGroups.value.flatMap((g) => g.items.map((item) => ({ ...item, groupType: g.type }))));
const totalSearchResults = computed(() => flatResults.value.length);

const isActiveResult = (type, id) => {
    const item = flatResults.value[activeResultIndex.value];
    return item && item.groupType === type && item.id === id;
};

const runSearch = async (query) => {
    if (query.trim().length < 2) {
        searchResults.value = { products: [], customers: [], invoices: [], sales_orders: [], employees: [] };
        return;
    }
    searchLoading.value = true;
    try {
        const res = await adminSearchApi.search(query.trim());
        const data = res.data?.data || {};
        searchResults.value = {
            products: data.products || [],
            customers: data.customers || [],
            invoices: data.invoices || [],
            sales_orders: data.sales_orders || [],
            employees: data.employees || []
        };
        activeResultIndex.value = -1;
    } catch (e) {
        searchResults.value = { products: [], customers: [], invoices: [], sales_orders: [], employees: [] };
    } finally {
        searchLoading.value = false;
    }
};

watch(searchQuery, (value) => {
    showSearchResults.value = true;
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => runSearch(value || ''), 300);
});

const onSearchFocus = () => {
    showSearchResults.value = true;
};

const clearSearch = () => {
    searchResults.value = { products: [], customers: [], invoices: [], sales_orders: [], employees: [] };
    showSearchResults.value = false;
};

const closeSearchOnOutsideClick = (event) => {
    if (searchWrapperRef.value && !searchWrapperRef.value.contains(event.target)) {
        showSearchResults.value = false;
    }
};

const goToResult = (item) => {
    showSearchResults.value = false;
    searchQuery.value = '';
    router.push(item.route);
};

const onSearchKeydown = (event) => {
    if (!showSearchResults.value || flatResults.value.length === 0) return;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeResultIndex.value = (activeResultIndex.value + 1) % flatResults.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeResultIndex.value = (activeResultIndex.value - 1 + flatResults.value.length) % flatResults.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const target = flatResults.value[activeResultIndex.value] || flatResults.value[0];
        if (target) goToResult(target);
    } else if (event.key === 'Escape') {
        showSearchResults.value = false;
    }
};

// ---- Notifications Center ----
const notificationsOpen = ref(false);
const activeNotificationTab = ref('notifications');
const notificationFilter = ref('all');

const filteredNotifications = computed(() => {
    const list = notificationsStore.items || [];
    if (notificationFilter.value === 'unread') {
        return list.filter((n) => !n.is_read);
    }
    return list;
});

const onOpenNotifications = () => {
    refreshNotificationData();
};

const refreshNotificationData = async () => {
    await Promise.all([
        notificationsStore.fetchRecent(),
        notificationsStore.fetchSystemAlerts(),
    ]);
};

const handleMarkAllAsRead = async () => {
    await notificationsStore.markAllAsRead();
};

const handleNotificationClick = async (item) => {
    if (!item.is_read) {
        await notificationsStore.markAsRead(item.id);
    }
    if (item.target_route) {
        notificationsOpen.value = false;
        router.push(item.target_route);
    }
};

const handleToggleItemRead = async (item) => {
    if (item.is_read) {
        item.is_read = false;
        notificationsStore.unreadCount = Math.max(0, notificationsStore.unreadCount + 1);
    } else {
        await notificationsStore.markAsRead(item.id);
    }
};

const handleDeleteItem = async (item) => {
    await notificationsStore.removeNotification(item.id);
};

const handleSystemAlertClick = (alert) => {
    notificationsOpen.value = false;
    if (alert.route) {
        router.push(alert.route);
    }
};

const goToAllNotifications = () => {
    notificationsOpen.value = false;
    router.push('/admin/notifications');
};

const goToPreferences = () => {
    notificationsOpen.value = false;
    router.push('/admin/notifications/preferences');
};

const getNotificationIcon = (type) => {
    switch (type) {
        case 'success': return CircleCheck;
        case 'warning': return Warning;
        case 'error': return WarningFilled;
        case 'order': return ShoppingCart;
        case 'inventory': return Box;
        case 'warehouse': return Box;
        case 'financial': return Money;
        default: return InfoFilled;
    }
};

const getAlertIcon = (type) => {
    switch (type) {
        case 'inventory': return Box;
        case 'order': return ShoppingCart;
        case 'warehouse': return Box;
        case 'financial': return Money;
        default: return WarningFilled;
    }
};

const timeAgo = (dateStr) => {
    if (!dateStr) return '';
    const diffMs = Date.now() - new Date(dateStr).getTime();
    const minutes = Math.floor(diffMs / 60000);
    const isAr = locale.value === 'ar';
    if (minutes < 1) return isAr ? 'الآن' : 'just now';
    if (minutes < 60) return isAr ? `منذ ${minutes} د` : `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return isAr ? `منذ ${hours} س` : `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return isAr ? `منذ ${days} ي` : `${days}d ago`;
};

onMounted(() => {
    authStore.fetchUser();
    if (authStore.token) {
        notificationsStore.startPolling();
    }
    document.addEventListener('click', closeSearchOnOutsideClick);
});

watch(() => authStore.isAuthenticated, (authenticated) => {
    if (authenticated) {
        notificationsStore.startPolling();
    } else {
        notificationsStore.stopPolling();
    }
});

onUnmounted(() => {
    notificationsStore.stopPolling();
    document.removeEventListener('click', closeSearchOnOutsideClick);
});
</script>

<style scoped>
.admin-header {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.95),
        rgba(248, 250, 252, 0.9)
    );
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 100;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-header:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

[dir="rtl"] .header-left {
    flex-direction: row;
}

[dir="ltr"] .header-left {
    flex-direction: row;
}

.mobile-toggle {
    display: none;
    background: var(--admin-gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    border: none;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.mobile-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.header-search {
    width: 320px;
    position: relative;
}

.header-search :deep(.el-input__wrapper) {
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.header-search :deep(.el-input__wrapper:hover) {
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    border-color: rgba(102, 126, 234, 0.3);
}

.header-search :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
    border-color: #667eea;
}

.search-results-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.16);
    border: 1px solid rgba(0, 0, 0, 0.06);
    max-height: 420px;
    overflow-y: auto;
    z-index: 200;
    padding: 0.5rem 0;
}

.search-state {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    color: #8b96a7;
    font-size: 0.85rem;
}

.search-group-title {
    padding: 0.4rem 1.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #94a3b8;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 1.25rem;
    cursor: pointer;
    transition: background 0.15s ease;
}

.search-result-item:hover,
.search-result-item.active {
    background: #f4f7ff;
}

.result-icon {
    color: #667eea;
    flex-shrink: 0;
}

.result-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.result-title {
    font-size: 0.88rem;
    color: #1f2d3d;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-subtitle {
    font-size: 0.76rem;
    color: #8b96a7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

[dir="rtl"] .header-right {
    flex-direction: row-reverse;
}

[dir="ltr"] .header-right {
    flex-direction: row;
}

.notification-badge {
    margin-right: 0.5rem;
}

[dir="rtl"] .notification-badge {
    margin-right: 0;
    margin-left: 0.5rem;
}

[dir="ltr"] .notification-badge {
    margin-right: 0.5rem;
    margin-left: 0;
}

.notification-badge :deep(.el-button) {
    background: var(--admin-gradient-danger, linear-gradient(135deg, #f093fb 0%, #f5576c 100%));
    border: none;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-badge :deep(.el-button:hover) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
}

.notification-badge :deep(.el-badge__content) {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(238, 90, 36, 0.4);
}

.notification-badge.has-critical :deep(.el-badge__content) {
    animation: badge-pulse 2s infinite ease-in-out;
}

@keyframes badge-pulse {
    0%, 100% { transform: translateY(-50%) translateX(100%) scale(1); }
    50% { transform: translateY(-50%) translateX(100%) scale(1.15); box-shadow: 0 0 12px rgba(239, 68, 68, 0.7); }
}

[dir="rtl"] @keyframes badge-pulse {
    0%, 100% { transform: translateY(-50%) translateX(-100%) scale(1); }
    50% { transform: translateY(-50%) translateX(-100%) scale(1.15); box-shadow: 0 0 12px rgba(239, 68, 68, 0.7); }
}

.notifications-center {
    display: flex;
    flex-direction: column;
    margin: -12px;
}

.nc-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #eef2f6;
    background: #f8fafc;
    border-radius: 12px 12px 0 0;
}

.nc-tabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.nc-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nc-tab-btn:hover {
    background: rgba(100, 116, 139, 0.08);
    color: #1e293b;
}

.nc-tab-btn.active {
    background: #ffffff;
    color: #3b82f6;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

.nc-tab-badge {
    padding: 0.1rem 0.45rem;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
}

.nc-tab-btn.active .nc-tab-badge {
    background: #dbeafe;
    color: #2563eb;
}

.nc-tab-badge.critical {
    background: #fee2e2;
    color: #dc2626;
}

.nc-controls {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.nc-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nc-icon-btn:hover {
    background: rgba(100, 116, 139, 0.1);
    color: #1e293b;
}

.nc-icon-btn.active {
    color: #2563eb;
}

.nc-sub-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 1rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}

.nc-sub-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}

.nc-filter-pills {
    display: flex;
    gap: 0.25rem;
}

.nc-pill {
    padding: 0.2rem 0.55rem;
    font-size: 0.74rem;
    font-weight: 500;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.nc-pill:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.nc-pill.active {
    background: #f1f5f9;
    color: #2563eb;
    font-weight: 700;
}

.nc-mark-all-btn {
    font-size: 0.74rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.nc-tab-content {
    display: flex;
    flex-direction: column;
}

.nc-state-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2.5rem 1rem;
    color: #94a3b8;
    font-size: 0.85rem;
    text-align: center;
}

.nc-state-box.ok-state {
    padding: 2rem 1rem;
}

.ok-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.9rem;
}

.ok-desc {
    font-size: 0.75rem;
    color: #64748b;
}

.nc-list {
    max-height: 360px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.nc-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.15s ease;
}

.nc-item:hover {
    background: #f8fafc;
}

.nc-item.unread {
    background: #f0f7ff;
}

.nc-item.unread:hover {
    background: #e6f1fe;
}

.nc-unread-indicator {
    position: absolute;
    top: 1rem;
    inset-inline-end: 0.75rem;
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #3b82f6;
}

.nc-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 0.1rem;
    background: #f1f5f9;
    color: #64748b;
}

.nc-item-icon.icon-order { background: #dbeafe; color: #2563eb; }
.nc-item-icon.icon-inventory { background: #fef3c7; color: #d97706; }
.nc-item-icon.icon-warehouse { background: #e0e7ff; color: #4f46e5; }
.nc-item-icon.icon-financial { background: #d1fae5; color: #059669; }
.nc-item-icon.icon-warning { background: #ffedd5; color: #ea580c; }
.nc-item-icon.icon-error { background: #fee2e2; color: #dc2626; }
.nc-item-icon.icon-success { background: #dcfce7; color: #16a34a; }
.nc-item-icon.icon-info { background: #e0f2fe; color: #0284c7; }

.nc-item-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.nc-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}

.nc-item-title {
    font-size: 0.83rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nc-item-time {
    font-size: 0.69rem;
    color: #94a3b8;
    flex-shrink: 0;
}

.nc-item-message {
    margin: 0;
    font-size: 0.77rem;
    line-height: 1.35;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.nc-item-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.35rem;
}

.nc-target-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: #e2e8f0;
    color: #334155;
    font-size: 0.68rem;
    font-weight: 600;
    transition: background 0.15s ease;
}

.nc-item:hover .nc-target-chip {
    background: #dbeafe;
    color: #1d4ed8;
}

.nc-quick-btns {
    display: flex;
    gap: 0.25rem;
    opacity: 0.6;
    transition: opacity 0.15s ease;
}

.nc-item:hover .nc-quick-btns {
    opacity: 1;
}

.nc-action-btn {
    width: 22px;
    height: 22px;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.nc-action-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.nc-action-btn.danger:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* System Alerts Tab Styles */
.nc-alerts-list {
    max-height: 360px;
    overflow-y: auto;
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.nc-alert-card {
    padding: 0.75rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.nc-alert-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
}

.nc-alert-card.severity-critical {
    border-color: #fecaca;
    background: linear-gradient(to bottom, #fff5f5, #ffffff);
}

.nc-alert-card.severity-warning {
    border-color: #fef3c7;
    background: linear-gradient(to bottom, #fffbeb, #ffffff);
}

.nc-alert-card.severity-info {
    border-color: #e0e7ff;
    background: linear-gradient(to bottom, #f8faff, #ffffff);
}

.nc-alert-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.nc-alert-heading {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.nc-alert-card.severity-critical .nc-alert-icon { color: #dc2626; }
.nc-alert-card.severity-warning .nc-alert-icon { color: #d97706; }
.nc-alert-card.severity-info .nc-alert-icon { color: #2563eb; }

.nc-alert-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
}

.nc-alert-badge {
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.nc-alert-badge.critical { background: #fee2e2; color: #dc2626; }
.nc-alert-badge.warning { background: #fef3c7; color: #b45309; }
.nc-alert-badge.info { background: #e0e7ff; color: #3730a3; }

.nc-alert-msg {
    margin: 0 0 0.5rem 0;
    font-size: 0.76rem;
    color: #475569;
    line-height: 1.35;
}

.nc-alert-footer {
    display: flex;
    justify-content: flex-end;
}

.nc-alert-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    border: none;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.nc-alert-card.severity-critical .nc-alert-cta-btn {
    background: #dc2626;
}

.nc-alert-card.severity-critical .nc-alert-cta-btn:hover {
    background: #b91c1c;
}

.nc-alert-card.severity-warning .nc-alert-cta-btn {
    background: #d97706;
}

.nc-alert-card.severity-warning .nc-alert-cta-btn:hover {
    background: #b45309;
}

.nc-alert-card.severity-info .nc-alert-cta-btn {
    background: #2563eb;
}

.nc-alert-card.severity-info .nc-alert-cta-btn:hover {
    background: #1d4ed8;
}

.nc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1rem;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
    border-radius: 0 0 12px 12px;
}

.nc-footer-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: none;
    background: transparent;
    color: #2563eb;
    font-size: 0.77rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.nc-footer-link:hover {
    background: #eff6ff;
}

.nc-footer-link.secondary {
    color: #64748b;
}

.nc-footer-link.secondary:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.user-dropdown {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    border: 1px solid rgba(102, 126, 234, 0.1);
}

.user-dropdown:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-color: rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.user-dropdown :deep(.user-avatar) {
    background: var(--admin-gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: white;
    font-weight: 700;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.user-name {
    font-weight: 600;
    color: #1a202c;
    font-size: 0.9rem;
}

.dropdown-icon {
    font-size: 0.75rem;
    color: #667eea;
    transition: transform 0.3s ease;
}

.user-dropdown:hover .dropdown-icon {
    transform: rotate(180deg);
}

@media (max-width: 992px) {
    .mobile-toggle {
        display: flex;
    }

    .header-search {
        display: none;
    }

    .user-name {
        display: none;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .admin-header {
        background: linear-gradient(135deg,
            rgba(26, 32, 44, 0.95),
            rgba(17, 24, 39, 0.9)
        );
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }

    .header-search :deep(.el-input__wrapper) {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .header-search :deep(.el-input__wrapper:hover) {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(102, 126, 234, 0.4);
    }

    .header-search :deep(.el-input__wrapper.is-focus) {
        background: rgba(255, 255, 255, 0.1);
        border-color: #667eea;
    }

    .header-search :deep(.el-input__inner) {
        color: white;
    }

    .header-search :deep(.el-input__inner::placeholder) {
        color: rgba(255, 255, 255, 0.5);
    }

    .user-dropdown {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        border-color: rgba(102, 126, 234, 0.2);
    }

    .user-dropdown:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    }

    .user-name {
        color: white;
    }
}
</style>
