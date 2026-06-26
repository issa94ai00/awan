<template>
    <header class="admin-header">
        <div class="header-left">
            <el-button :icon="Menu" circle @click="toggleMobileSidebar" class="mobile-toggle" />
            <div class="header-search">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('quick_search')"
                    :prefix-icon="Search"
                    clearable
                />
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

            <el-badge :value="unreadCount" :hidden="unreadCount === 0" class="notification-badge">
                <el-button :icon="Bell" circle @click="showNotifications" />
            </el-badge>

            <el-dropdown @command="handleDropdownCommand" trigger="click">
                <div class="user-dropdown">
                    <el-avatar :size="36" :icon="UserFilled" />
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useInquiriesStore } from '@/stores/inquiries';
import { useI18n } from 'vue-i18n';
import { updateDirection } from '@/app';
import {
    Menu, Search, Bell, UserFilled, User, Setting,
    SwitchButton, ArrowDown
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();
const emit = defineEmits(['toggle-mobile-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const inquiriesStore = useInquiriesStore();
const searchQuery = ref('');
const unreadCount = computed(() => {
    const statusCounts = inquiriesStore.statusCounts || {};
    const rawCount = Number(statusCounts.new || statusCounts['new'] || 0);
    if (rawCount > 0) {
        return rawCount;
    }
    return inquiriesStore.items.filter((inquiry) => inquiry.status === 'new').length;
});
const userName = ref(authStore.userName);
const currentLocale = computed(() => locale.value);

const handleLanguageCommand = (command) => {
    locale.value = command;
    localStorage.setItem('locale', command);
    updateDirection(command);
};

const toggleMobileSidebar = () => {
    emit('toggle-mobile-sidebar');
};

const showNotifications = () => {
    router.push('/admin/inquiries');
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

onMounted(() => {
    // Fetch user data and notification counts
    authStore.fetchUser();
    if (Object.keys(inquiriesStore.statusCounts).length === 0 && inquiriesStore.items.length === 0) {
        inquiriesStore.fetch({ per_page: 1 }).catch(() => {});
    }
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.mobile-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.header-search {
    width: 300px;
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
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

.user-dropdown :deep(.el-avatar) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
