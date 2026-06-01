<template>
    <aside class="admin-sidebar" :class="{ collapsed: collapsed }">
        <div class="sidebar-header">
            <div class="logo">
                <el-icon :size="28"><Box /></el-icon>
                <span v-if="!collapsed">أوان التكادوم</span>
            </div>
            <el-button
                v-if="!collapsed"
                :icon="Fold"
                circle
                size="small"
                @click="toggleSidebar"
            />
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li>
                    <router-link to="/admin/dashboard" class="nav-link" :class="{ active: isActive('/admin/dashboard') }">
                        <el-icon><Odometer /></el-icon>
                        <span v-if="!collapsed">لوحة التحكم</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('content') }">
                    <div class="nav-group-header" @click="toggleGroup('content')">
                        <el-icon><Box /></el-icon>
                        <span v-if="!collapsed">إدارة المحتوى</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('content') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('content')">
                        <li>
                            <router-link to="/admin/categories" class="nav-link">
                                <el-icon><Folder /></el-icon>
                                <span v-if="!collapsed">الفئات</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products" class="nav-link">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">المنتجات</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('sales') }">
                    <div class="nav-group-header" @click="toggleGroup('sales')">
                        <el-icon><ShoppingCart /></el-icon>
                        <span v-if="!collapsed">المبيعات</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('sales') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('sales')">
                        <li>
                            <router-link to="/admin/sales" class="nav-link">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/orders" class="nav-link">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">الطلبات</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <el-button
                v-if="collapsed"
                :icon="Expand"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <router-link v-if="!collapsed" to="/admin/settings" class="footer-link" :class="{ active: isActive('/admin/settings') }">
                <el-icon><Setting /></el-icon>
                <span>الإعدادات</span>
            </router-link>
        </div>
    </aside>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
    Box, Fold, Expand, Odometer, Folder, ShoppingCart,
    TrendCharts, Document, ArrowDown, Setting
} from '@element-plus/icons-vue';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:collapsed']);

const route = useRoute();
const openGroups = ref(['content']);

const isActive = (path) => {
    return route.path === path || route.path.startsWith(path + '/');
};

const isGroupOpen = (group) => {
    return openGroups.value.includes(group);
};

const toggleGroup = (group) => {
    const index = openGroups.value.indexOf(group);
    if (index > -1) {
        openGroups.value.splice(index, 1);
    } else {
        openGroups.value = [group];
    }
};

const toggleSidebar = () => {
    emit('update:collapsed', !props.collapsed);
    localStorage.setItem('sidebarCollapsed', !props.collapsed);
};

watch(() => props.collapsed, (newVal) => {
    if (newVal) {
        openGroups.value = [];
    }
});
</script>

<style scoped>
.admin-sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a2332 0%, #2d3e50 100%);
    color: white;
    position: fixed;
    right: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.admin-sidebar.collapsed {
    width: 70px;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-weight: 600;
    font-size: 1.125rem;
}

.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: all 0.3s ease;
    border-right: 3px solid transparent;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

.nav-group {
    margin-bottom: 0.25rem;
}

.nav-group-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    color: rgba(255, 255, 255, 0.85);
    border-right: 3px solid transparent;
}

.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

.toggle-icon {
    margin-left: auto;
    transition: transform 0.3s ease;
    font-size: 0.75rem;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

.nav-group-items {
    padding-right: 1.5rem;
    list-style: none;
    padding: 0;
    margin: 0.25rem 0;
}

.nav-group-items li {
    margin-bottom: 0.25rem;
}

.nav-group-items .nav-link {
    padding: 0.625rem 1.5rem;
    font-size: 0.875rem;
}

.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: center;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.footer-link:hover {
    color: white;
}

.footer-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

@media (max-width: 992px) {
    .admin-sidebar {
        transform: translateX(100%);
    }
    
    .admin-sidebar:not(.collapsed) {
        transform: translateX(0);
    }
}
</style>
