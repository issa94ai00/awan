<template>
    <header class="admin-header">
        <div class="header-left">
            <button
                type="button"
                class="mobile-toggle-btn"
                @click="toggleMobileSidebar"
                :title="$t('menu') || 'القائمة'"
                :aria-label="$t('menu') || 'القائمة'"
            >
                <el-icon :size="20"><Menu /></el-icon>
            </button>

            <!-- Desktop Search Bar -->
            <div class="header-search" ref="searchWrapperRef">
                <el-input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    :placeholder="$t('quick_search')"
                    :prefix-icon="Search"
                    clearable
                    @focus="onSearchFocus"
                    @keydown="onSearchKeydown"
                    @clear="clearSearch"
                >
                    <template #suffix>
                        <kbd class="search-kbd-badge" aria-hidden="true">{{ isMac ? '⌘K' : 'Ctrl+K' }}</kbd>
                    </template>
                </el-input>

                <transition name="dropdown-fade">
                    <div v-if="showSearchResults" class="search-results-panel">
                        <div v-if="searchLoading" class="search-state">
                            <el-icon class="is-loading"><Loading /></el-icon>
                            <span>{{ $t('search_searching') }}</span>
                        </div>
                        <div v-else-if="searchQuery.trim().length < 2" class="search-state">
                            <el-icon :size="16" class="text-slate-400"><Search /></el-icon>
                            <span>{{ $t('search_type_to_search') }}</span>
                        </div>
                        <div v-else-if="totalSearchResults === 0" class="search-state empty">
                            <el-icon :size="22" class="text-slate-400"><Search /></el-icon>
                            <span>{{ $t('search_no_results') }}</span>
                        </div>
                        <template v-else>
                            <div class="search-results-scroll">
                                <div v-for="group in searchGroups" :key="group.type" class="search-group">
                                    <template v-if="group.items.length">
                                        <div class="search-group-title">
                                            <span>{{ $t(group.labelKey) }}</span>
                                            <span class="search-group-count">{{ group.items.length }}</span>
                                        </div>
                                        <div
                                            v-for="item in group.items"
                                            :key="`${group.type}-${item.id}`"
                                            class="search-result-item"
                                            :class="[`item-${group.type}`, { active: isActiveResult(group.type, item.id) }]"
                                            @mousedown.prevent="goToResult(item)"
                                        >
                                            <div class="result-icon-box" :class="`icon-box-${group.type}`">
                                                <el-icon><component :is="group.icon" /></el-icon>
                                            </div>
                                            <div class="result-text">
                                                <span class="result-title">{{ item.title }}</span>
                                                <span v-if="item.subtitle" class="result-subtitle">{{ item.subtitle }}</span>
                                            </div>
                                            <el-icon class="result-arrow"><ArrowRight /></el-icon>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="search-panel-footer">
                                <span class="footer-hint"><kbd>↑↓</kbd> {{ currentLocale === 'ar' ? 'للتنقل' : 'navigate' }}</span>
                                <span class="footer-hint"><kbd>↵</kbd> {{ currentLocale === 'ar' ? 'للاختيار' : 'select' }}</span>
                                <span class="footer-hint"><kbd>Esc</kbd> {{ currentLocale === 'ar' ? 'للإغلاق' : 'close' }}</span>
                            </div>
                        </template>
                    </div>
                </transition>
            </div>

            <!-- Mobile Search Toggle -->
            <button
                type="button"
                class="mobile-search-toggle"
                :class="{ active: mobileSearchOpen }"
                @click="toggleMobileSearch"
                :title="$t('quick_search')"
                :aria-label="$t('quick_search')"
            >
                <el-icon :size="18"><component :is="mobileSearchOpen ? Close : Search" /></el-icon>
            </button>
        </div>

        <!-- Mobile Expandable Search Bar -->
        <transition name="slide-down">
            <div v-if="mobileSearchOpen" class="mobile-search-bar" ref="mobileSearchWrapperRef">
                <el-input
                    ref="mobileSearchInputRef"
                    v-model="searchQuery"
                    :placeholder="$t('quick_search')"
                    :prefix-icon="Search"
                    clearable
                    @focus="onSearchFocus"
                    @keydown="onSearchKeydown"
                    @clear="clearSearch"
                />
            </div>
        </transition>

        <div class="header-right">
            <!-- Language Switcher -->
            <el-dropdown @command="handleLanguageCommand" class="language-dropdown" trigger="click">
                <button type="button" class="lang-pill-btn" :title="t('language') || 'اللغة'">
                    <span class="lang-flag">{{ currentLocale === 'ar' ? '🇸🇾' : '🇬🇧' }}</span>
                    <span class="lang-label">{{ currentLocale === 'ar' ? 'العربية' : 'English' }}</span>
                    <el-icon class="lang-arrow"><ArrowDown /></el-icon>
                </button>
                <template #dropdown>
                    <el-dropdown-menu class="header-custom-dropdown">
                        <el-dropdown-item command="ar" :class="{ 'active-lang': currentLocale === 'ar' }">
                            <div class="lang-menu-item">
                                <span>🇸🇾 العربية</span>
                                <el-icon v-if="currentLocale === 'ar'" class="active-check"><Check /></el-icon>
                            </div>
                        </el-dropdown-item>
                        <el-dropdown-item command="en" :class="{ 'active-lang': currentLocale === 'en' }">
                            <div class="lang-menu-item">
                                <span>🇬🇧 English</span>
                                <el-icon v-if="currentLocale === 'en'" class="active-check"><Check /></el-icon>
                            </div>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <!-- Notifications Center -->
            <el-popover
                v-model:visible="notificationsOpen"
                trigger="click"
                placement="bottom-end"
                :width="440"
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
                        <button
                            type="button"
                            class="header-action-btn bell-btn"
                            :class="{ active: notificationsOpen, 'has-unread': notificationsStore.totalBadgeCount > 0 }"
                            @click="onOpenNotifications"
                            :title="t('notifications.title')"
                            :aria-label="t('notifications.title')"
                        >
                            <el-icon :size="19"><Bell /></el-icon>
                        </button>
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
                            <el-icon class="is-loading" :size="24"><Loading /></el-icon>
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
                            <el-icon class="is-loading" :size="24"><Loading /></el-icon>
                            <span>{{ t('common.loading') || 'جاري فحص تنبيهات النظام...' }}</span>
                        </div>
                        <div v-else-if="notificationsStore.systemAlerts.length === 0" class="nc-state-box ok-state">
                            <el-icon :size="34" class="text-emerald-500"><CircleCheck /></el-icon>
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

            <!-- User Profile Dropdown -->
            <el-dropdown @command="handleDropdownCommand" trigger="click" class="user-dropdown-container">
                <div class="user-profile-btn" role="button" tabindex="0">
                    <div class="user-avatar-badge">
                        <span class="user-avatar-text">{{ userInitials }}</span>
                        <span class="user-online-dot"></span>
                    </div>
                    <div class="user-details">
                        <span class="user-name">{{ userName }}</span>
                        <span class="user-role">{{ userRoleDisplay }}</span>
                    </div>
                    <el-icon class="dropdown-chevron"><ArrowDown /></el-icon>
                </div>
                <template #dropdown>
                    <el-dropdown-menu class="header-user-dropdown-menu">
                        <div class="user-menu-profile-card">
                            <div class="menu-avatar">{{ userInitials }}</div>
                            <div class="menu-user-info">
                                <span class="menu-user-name">{{ userName }}</span>
                                <span class="menu-user-email">{{ authStore.user?.email || '' }}</span>
                                <span class="menu-user-badge">{{ userRoleDisplay }}</span>
                            </div>
                        </div>
                        <el-dropdown-item command="profile">
                            <el-icon><User /></el-icon>
                            <span>{{ t('profile') }}</span>
                        </el-dropdown-item>
                        <el-dropdown-item command="settings">
                            <el-icon><Setting /></el-icon>
                            <span>{{ t('settings') }}</span>
                        </el-dropdown-item>
                        <el-dropdown-item divided command="logout" class="menu-item-logout">
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
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
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
    Money, MuteNotification, BellFilled, Close
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();
const emit = defineEmits(['toggle-mobile-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const notificationsStore = useNotificationsStore();
const currentLocale = computed(() => locale.value);

const isMac = typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const searchInputRef = ref(null);
const mobileSearchInputRef = ref(null);
const mobileSearchOpen = ref(false);

const userName = computed(() => authStore.user?.name || authStore.user?.email || t('profile'));
const userInitials = computed(() => {
    const name = userName.value || '';
    return name.trim().slice(0, 1).toUpperCase() || 'A';
});

const userRoleDisplay = computed(() => {
    if (authStore.isMaster) return t('role_master') || (currentLocale.value === 'ar' ? 'المدير الرئيسي' : 'Master Admin');
    if (authStore.isSuperAdmin) return t('role_superadmin') || (currentLocale.value === 'ar' ? 'مدير النظام' : 'Super Admin');
    const firstRole = authStore.roleNames?.[0];
    if (firstRole) return firstRole;
    return t('role_admin') || (currentLocale.value === 'ar' ? 'مشرف' : 'Admin');
});

const handleLanguageCommand = (command) => {
    locale.value = command;
    localStorage.setItem('locale', command);
    updateDirection(command);

    fetch(`/lang/${command}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => {});
};

const toggleMobileSidebar = () => {
    emit('toggle-mobile-sidebar');
};

const toggleMobileSearch = () => {
    mobileSearchOpen.value = !mobileSearchOpen.value;
    if (mobileSearchOpen.value) {
        showSearchResults.value = true;
        nextTick(() => {
            mobileSearchInputRef.value?.focus();
        });
    } else {
        showSearchResults.value = false;
    }
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
const mobileSearchWrapperRef = ref(null);
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
    const insideDesktop = searchWrapperRef.value && searchWrapperRef.value.contains(event.target);
    const insideMobile = mobileSearchWrapperRef.value && mobileSearchWrapperRef.value.contains(event.target);
    const isMobileToggle = event.target.closest?.('.mobile-search-toggle');

    if (!insideDesktop && !insideMobile && !isMobileToggle) {
        showSearchResults.value = false;
        if (mobileSearchOpen.value && !searchQuery.value) {
            mobileSearchOpen.value = false;
        }
    }
};

const goToResult = (item) => {
    showSearchResults.value = false;
    mobileSearchOpen.value = false;
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
        mobileSearchOpen.value = false;
    }
};

const onGlobalKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && (event.key === 'k' || event.key === 'K')) {
        const target = event.target;
        if (target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName)) {
            if (target === searchInputRef.value?.$el?.querySelector('input')) return;
        }
        event.preventDefault();
        showSearchResults.value = true;
        if (window.innerWidth <= 992) {
            mobileSearchOpen.value = true;
            nextTick(() => mobileSearchInputRef.value?.focus());
        } else {
            nextTick(() => searchInputRef.value?.focus());
        }
    }
};

// ---- Notifications Center ----
const notificationsOpen = ref(false);
const activeNotificationTab = ref('notifications');
const notificationFilter = ref('all');

const filteredNotifications = computed(() => notificationsStore.items || []);

watch(notificationFilter, () => {
    if (notificationsOpen.value) notificationsStore.fetchRecent(recentParams());
});

const recentParams = () => (notificationFilter.value === 'unread' ? { unread_only: 1 } : {});

const onOpenNotifications = () => {
    refreshNotificationData();
};

const refreshNotificationData = async () => {
    await Promise.all([
        notificationsStore.fetchRecent(recentParams()),
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
        await notificationsStore.markAsUnread(item.id);
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
    document.addEventListener('keydown', onGlobalKeydown);
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
    document.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<style scoped>
.admin-header {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    padding: 0.75rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03), 0 1px 2px -1px rgba(15, 23, 42, 0.03);
    position: sticky;
    top: 0;
    z-index: 100;
    transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.mobile-toggle-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.mobile-toggle-btn:hover {
    background: #1e293b;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

.mobile-search-toggle {
    display: none;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mobile-search-toggle:hover,
.mobile-search-toggle.active {
    background: #e2e8f0;
    color: #0f172a;
}

.header-search {
    width: 340px;
    position: relative;
}

.header-search :deep(.el-input__wrapper) {
    border-radius: 12px;
    background: #f1f5f9;
    box-shadow: none;
    border: 1px solid #e2e8f0;
    padding: 3px 12px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.header-search :deep(.el-input__wrapper:hover) {
    background: #e2e8f0;
    border-color: #cbd5e1;
}

.header-search :deep(.el-input__wrapper.is-focus) {
    background: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.14);
}

.search-kbd-badge {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.05);
    color: #64748b;
    border: 1px solid rgba(0, 0, 0, 0.06);
    line-height: 1;
    font-family: inherit;
}

.search-results-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 16px 40px -10px rgba(15, 23, 42, 0.15), 0 0 0 1px rgba(15, 23, 42, 0.06);
    z-index: 200;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.search-results-scroll {
    max-height: 380px;
    overflow-y: auto;
    padding: 0.4rem 0;
}

.search-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 1.5rem 1.25rem;
    color: #64748b;
    font-size: 0.85rem;
}

.search-state.empty {
    flex-direction: column;
    padding: 2rem 1.25rem;
}

.search-group {
    padding: 0.25rem 0;
}

.search-group:not(:last-child) {
    border-bottom: 1px solid #f1f5f9;
}

.search-group-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 1rem 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #94a3b8;
    text-transform: uppercase;
}

.search-group-count {
    font-size: 0.68rem;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-weight: 600;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 1rem;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.15s ease;
}

.search-result-item:hover,
.search-result-item.active {
    background: #f8fafc;
}

.search-result-item.active {
    background: #f0fdfa;
}

.result-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
    transition: transform 0.15s ease;
}

.search-result-item:hover .result-icon-box {
    transform: scale(1.05);
}

.icon-box-products { background: #ccfbf1; color: #0d9488; }
.icon-box-customers { background: #dbeafe; color: #2563eb; }
.icon-box-invoices { background: #fef3c7; color: #d97706; }
.icon-box-sales_orders { background: #ede9fe; color: #7c3aed; }
.icon-box-employees { background: #e0f2fe; color: #0284c7; }

.result-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.result-title {
    font-size: 0.88rem;
    color: #0f172a;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-subtitle {
    font-size: 0.74rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-arrow {
    font-size: 0.75rem;
    color: #cbd5e1;
    transition: transform 0.15s ease, color 0.15s ease;
}

.search-result-item:hover .result-arrow {
    color: #0d9488;
    transform: translateX(-2px);
}

[dir="ltr"] .search-result-item:hover .result-arrow {
    transform: translateX(2px);
}

.search-panel-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    font-size: 0.7rem;
    color: #64748b;
}

.footer-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.footer-hint kbd {
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    font-size: 0.65rem;
    font-weight: 600;
    color: #475569;
}

.mobile-search-bar {
    display: none;
    padding: 0.75rem 1.75rem;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Language Switcher */
.lang-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.8rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    font-size: 0.83rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.lang-pill-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
}

.lang-flag {
    font-size: 0.95rem;
    line-height: 1;
}

.lang-arrow {
    font-size: 0.72rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.language-dropdown:hover .lang-arrow {
    color: #0f172a;
}

.lang-menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 120px;
    gap: 0.75rem;
    font-weight: 500;
}

.active-check {
    color: #0d9488;
    font-weight: 700;
}

/* Action button for notifications */
.header-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.header-action-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
}

.header-action-btn.active {
    background: #e2e8f0;
    color: #0d9488;
    border-color: #0d9488;
}

.notification-badge :deep(.el-badge__content) {
    background: #ef4444;
    border: 2px solid white;
    font-weight: 700;
    font-size: 0.7rem;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
}

.notification-badge.has-critical :deep(.el-badge__content) {
    animation: badge-pulse 2s infinite ease-in-out;
}

@keyframes badge-pulse {
    0%, 100% { transform: translateY(-50%) translateX(100%) scale(1); }
    50% { transform: translateY(-50%) translateX(100%) scale(1.15); box-shadow: 0 0 12px rgba(239, 68, 68, 0.7); }
}

/* User Profile */
.user-dropdown-container {
    display: inline-flex;
}

.user-profile-btn {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.3rem 0.65rem 0.3rem 0.4rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
}

.user-profile-btn:hover,
.user-profile-btn:focus-visible {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.user-avatar-badge {
    position: relative;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.35);
    flex-shrink: 0;
}

.user-online-dot {
    position: absolute;
    bottom: -2px;
    inset-inline-end: -2px;
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #10b981;
    border: 2px solid white;
}

.user-details {
    display: flex;
    flex-direction: column;
    min-width: 0;
    text-align: start;
    line-height: 1.2;
}

.user-name {
    font-size: 0.86rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.user-role {
    font-size: 0.7rem;
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-chevron {
    font-size: 0.72rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.user-profile-btn:hover .dropdown-chevron {
    color: #0f172a;
}

/* User Menu Dropdown Card */
.user-menu-profile-card {
    padding: 0.85rem 1rem;
    margin-bottom: 0.4rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.menu-avatar {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(13, 148, 136, 0.25);
    flex-shrink: 0;
}

.menu-user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.menu-user-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.menu-user-email {
    font-size: 0.74rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.menu-user-badge {
    margin-top: 0.25rem;
    align-self: flex-start;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    background: #ccfbf1;
    color: #0f766e;
    font-size: 0.68rem;
    font-weight: 700;
}

.menu-item-logout {
    color: #ef4444 !important;
}

.menu-item-logout:hover {
    background: #fef2f2 !important;
    color: #dc2626 !important;
}

/* Notifications Popover Content */
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
    border-bottom: 1px solid #f1f5f9;
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
    color: #0f172a;
}

.nc-tab-btn.active {
    background: #ffffff;
    color: #0d9488;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
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
    background: #ccfbf1;
    color: #0f766e;
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
    color: #0f172a;
}

.nc-icon-btn.active {
    color: #0d9488;
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
    color: #0f172a;
}

.nc-pill.active {
    background: #f1f5f9;
    color: #0d9488;
    font-weight: 700;
}

.nc-mark-all-btn {
    font-size: 0.74rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #0d9488 !important;
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
    padding: 2.2rem 1rem;
}

.ok-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.92rem;
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
    background: #f0fdfa;
}

.nc-item.unread:hover {
    background: #e6faf7;
}

.nc-unread-indicator {
    position: absolute;
    top: 1rem;
    inset-inline-end: 0.75rem;
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #0d9488;
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
    color: #0f172a;
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
    background: #ccfbf1;
    color: #0f766e;
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
    color: #0f172a;
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
.nc-alert-card.severity-info .nc-alert-icon { color: #0d9488; }

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
.nc-alert-badge.info { background: #ccfbf1; color: #0f766e; }

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

.nc-alert-card.severity-critical .nc-alert-cta-btn { background: #dc2626; }
.nc-alert-card.severity-critical .nc-alert-cta-btn:hover { background: #b91c1c; }
.nc-alert-card.severity-warning .nc-alert-cta-btn { background: #d97706; }
.nc-alert-card.severity-warning .nc-alert-cta-btn:hover { background: #b45309; }
.nc-alert-card.severity-info .nc-alert-cta-btn { background: #0d9488; }
.nc-alert-card.severity-info .nc-alert-cta-btn:hover { background: #0f766e; }

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
    color: #0d9488;
    font-size: 0.77rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.nc-footer-link:hover {
    background: #f0fdfa;
}

.nc-footer-link.secondary {
    color: #64748b;
}

.nc-footer-link.secondary:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Transitions */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: max-height 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    max-height: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .mobile-toggle-btn {
        display: inline-flex;
    }

    .mobile-search-toggle {
        display: inline-flex;
    }

    .header-search {
        display: none;
    }

    .mobile-search-bar {
        display: block;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
    }

    .user-details {
        display: none;
    }

    .lang-label {
        display: none;
    }

    .admin-header {
        padding: 0.65rem 1rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .admin-header {
        background: rgba(15, 23, 42, 0.88);
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }

    .header-search :deep(.el-input__wrapper) {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .header-search :deep(.el-input__wrapper:hover) {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(45, 212, 191, 0.4);
    }

    .header-search :deep(.el-input__wrapper.is-focus) {
        background: rgba(255, 255, 255, 0.1);
        border-color: #2dd4bf;
    }

    .header-search :deep(.el-input__inner) {
        color: #f8fafc;
    }

    .header-search :deep(.el-input__inner::placeholder) {
        color: rgba(255, 255, 255, 0.45);
    }

    .search-kbd-badge {
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.6);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .lang-pill-btn,
    .header-action-btn,
    .user-profile-btn,
    .mobile-search-toggle {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
        color: #cbd5e1;
    }

    .lang-pill-btn:hover,
    .header-action-btn:hover,
    .user-profile-btn:hover,
    .mobile-search-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.18);
        color: #ffffff;
    }

    .user-name {
        color: #f8fafc;
    }

    .search-results-panel {
        background: #0f172a;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .search-result-item:hover,
    .search-result-item.active {
        background: rgba(255, 255, 255, 0.05);
    }

    .result-title {
        color: #f8fafc;
    }

    .search-panel-footer {
        background: #090e1a;
        border-top-color: rgba(255, 255, 255, 0.06);
    }

    .mobile-search-bar {
        background: #0f172a;
        border-bottom-color: rgba(255, 255, 255, 0.1);
    }
}
</style>
