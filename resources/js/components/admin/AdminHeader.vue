<template>
    <header class="admin-header">
        <div class="header-left">
            <el-button :icon="Menu" circle @click="toggleMobileSidebar" class="mobile-toggle" />
            <div class="header-search">
                <el-input
                    v-model="searchQuery"
                    placeholder="بحث سريع..."
                    :prefix-icon="Search"
                    clearable
                />
            </div>
        </div>

        <div class="header-right">
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
                            <span>الملف الشخصي</span>
                        </el-dropdown-item>
                        <el-dropdown-item command="settings">
                            <el-icon><Setting /></el-icon>
                            <span>الإعدادات</span>
                        </el-dropdown-item>
                        <el-dropdown-item divided command="logout">
                            <el-icon><SwitchButton /></el-icon>
                            <span>تسجيل الخروج</span>
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
import {
    Menu, Search, Bell, UserFilled, User, Setting,
    SwitchButton, ArrowDown
} from '@element-plus/icons-vue';

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

const toggleMobileSidebar = () => {
    // Emit event to parent or use store
    console.log('Toggle mobile sidebar');
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
    background: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.mobile-toggle {
    display: none;
}

.header-search {
    width: 300px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.notification-badge {
    margin-right: 0.5rem;
}

.user-dropdown {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-dropdown:hover {
    background: #f5f7fa;
}

.user-name {
    font-weight: 500;
    color: #333;
}

.dropdown-icon {
    font-size: 0.75rem;
    color: #666;
}

@media (max-width: 992px) {
    .mobile-toggle {
        display: flex;
    }
    
    .header-search {
        display: none;
    }
}
</style>
