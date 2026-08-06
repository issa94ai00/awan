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
                        <el-dropdown-item command="ar" :class="{ 'active-lang': currentLocale === 'ar' }">العربية</el-dropdown-item>
                        <el-dropdown-item command="en" :class="{ 'active-lang': currentLocale === 'en' }">English</el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <el-popover
                v-model:visible="notificationsOpen"
                trigger="click"
                placement="bottom-end"
                width="360"
                popper-class="notifications-popover"
            >
                <template #reference>
                    <el-badge :value="notificationsStore.unreadCount" :hidden="notificationsStore.unreadCount === 0" class="notification-badge">
                        <el-button :icon="Bell" circle @click="onOpenNotifications" />
                    </el-badge>
                </template>

                <div class="notifications-dropdown">
                    <div class="notifications-header">
                        <span>{{ t('notifications.title') }}</span>
                        <el-button
                            link
                            type="primary"
                            size="small"
                            :disabled="notificationsStore.unreadCount === 0"
                            @click="handleMarkAllAsRead"
                        >
                            {{ t('notifications.mark_all_read') }}
                        </el-button>
                    </div>

                    <div v-if="notificationsStore.loading" class="notifications-empty">
                        <el-icon class="is-loading"><Loading /></el-icon>
                    </div>
                    <div v-else-if="notificationsStore.items.length === 0" class="notifications-empty">
                        <el-icon :size="28"><Bell /></el-icon>
                        <span>{{ t('no_notifications') }}</span>
                    </div>
                    <div v-else class="notifications-list">
                        <div
                            v-for="item in notificationsStore.items"
                            :key="item.id"
                            class="notification-row"
                            :class="{ unread: !item.is_read }"
                            @click="handleNotificationClick(item)"
                        >
                            <span class="notification-dot" :class="`type-${item.type}`"></span>
                            <div class="notification-body">
                                <span class="notification-title">{{ item.title }}</span>
                                <span class="notification-message">{{ item.message }}</span>
                                <span class="notification-time">{{ timeAgo(item.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="notifications-footer">
                        <el-button link type="primary" @click="goToAllNotifications">
                            {{ t('notifications_view_all') }}
                        </el-button>
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
    Document, UserFilled, Tickets
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

// ---- Notifications ----
const notificationsOpen = ref(false);

const onOpenNotifications = () => {
    notificationsStore.fetchRecent();
};

const handleMarkAllAsRead = async () => {
    await notificationsStore.markAllAsRead();
};

const handleNotificationClick = async (item) => {
    if (!item.is_read) {
        await notificationsStore.markAsRead(item.id);
    }
};

const goToAllNotifications = () => {
    notificationsOpen.value = false;
    router.push('/admin/notifications');
};

const timeAgo = (dateStr) => {
    if (!dateStr) return '';
    const diffMs = Date.now() - new Date(dateStr).getTime();
    const minutes = Math.floor(diffMs / 60000);
    if (minutes < 1) return t('now') || 'now';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    return `${days}d`;
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

.notifications-dropdown {
    display: flex;
    flex-direction: column;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.6rem;
    margin-bottom: 0.4rem;
    border-bottom: 1px solid #f0f2f7;
    font-weight: 700;
    color: #1f2d3d;
}

.notifications-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem 0;
    color: #94a3b8;
    font-size: 0.85rem;
}

.notifications-list {
    max-height: 340px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.notification-row {
    display: flex;
    gap: 0.6rem;
    padding: 0.6rem 0.25rem;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.15s ease;
}

.notification-row:hover {
    background: #f8fafc;
}

.notification-row.unread {
    background: #f4f7ff;
}

.notification-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    margin-top: 0.4rem;
    flex-shrink: 0;
    background: #cbd5e1;
}

.notification-row.unread .notification-dot {
    background: #667eea;
}

.notification-dot.type-warning { background: #f59e0b; }
.notification-dot.type-error { background: #ef4444; }
.notification-dot.type-success { background: #10b981; }

.notification-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.notification-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2d3d;
}

.notification-message {
    font-size: 0.78rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-time {
    font-size: 0.7rem;
    color: #a3adc2;
    margin-top: 0.1rem;
}

.notifications-footer {
    border-top: 1px solid #f0f2f7;
    margin-top: 0.4rem;
    padding-top: 0.4rem;
    text-align: center;
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
