<template>
    <div class="admin-layout" :class="{ 'mobile-sidebar-open': mobileSidebarOpen }">
        <AdminSidebar v-model:collapsed="sidebarCollapsed" v-model:mobile-open="mobileSidebarOpen" />
        <div v-if="mobileSidebarOpen" class="sidebar-overlay" @click="mobileSidebarOpen = false"></div>
        <div class="admin-main-wrapper" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
            <AdminHeader @toggle-mobile-sidebar="mobileSidebarOpen = !mobileSidebarOpen" />
            <div class="admin-content">
                <router-view />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AdminHeader from '@/components/admin/AdminHeader.vue';
import { useSettingsStore } from '@/stores/settings';

const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const settingsStore = useSettingsStore();

onMounted(async () => {
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState) {
        sidebarCollapsed.value = JSON.parse(savedState);
    }

    if (Object.keys(settingsStore.data).length === 0) {
        await settingsStore.fetch();
    }
});

watch(mobileSidebarOpen, (open) => {
    if (open) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});
</script>

<style scoped>
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: #f5f7fa;
}

.admin-main-wrapper {
    flex: 1;
    margin-right: 260px;
    transition: margin-right 0.3s ease;
    min-width: 0;
}

.admin-main-wrapper.sidebar-collapsed {
    margin-right: 70px;
}

[dir="ltr"] .admin-main-wrapper {
    margin-right: 0;
    margin-left: 260px;
    transition: margin-left 0.3s ease;
}

[dir="ltr"] .admin-main-wrapper.sidebar-collapsed {
    margin-right: 0;
    margin-left: 70px;
}

.admin-content {
    padding: 2rem;
    min-height: calc(100vh - 70px);
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 992px) {
    .admin-main-wrapper {
        margin-right: 0;
    }

    [dir="ltr"] .admin-main-wrapper {
        margin-left: 0;
        margin-right: 0;
    }

    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        animation: fadeIn 0.2s ease;
    }

    .admin-content {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .admin-content {
        padding: 0.75rem;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
