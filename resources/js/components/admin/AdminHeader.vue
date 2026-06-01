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
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import {
    Menu, Search, Bell, UserFilled, User, Setting,
    SwitchButton, ArrowDown
} from '@element-plus/icons-vue';

const router = useRouter();
const authStore = useAuthStore();
const searchQuery = ref('');
const unreadCount = ref(3);
const userName = ref(authStore.userName);

const toggleMobileSidebar = () => {
    // Emit event to parent or use store
    console.log('Toggle mobile sidebar');
};

const showNotifications = () => {
    ElMessage.info('الاستفسارات الجديدة');
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
    // Fetch user data
    authStore.fetchUser();
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
