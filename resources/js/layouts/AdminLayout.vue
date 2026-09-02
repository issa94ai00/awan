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
    background: var(--bg-light, #f3f5fa);
}

.admin-main-wrapper {
    flex: 1;
    margin-right: 268px;
    transition: margin-right 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 0;
}

.admin-main-wrapper.sidebar-collapsed {
    margin-right: 72px;
}

[dir="ltr"] .admin-main-wrapper {
    margin-right: 0;
    margin-left: 268px;
    transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

[dir="ltr"] .admin-main-wrapper.sidebar-collapsed {
    margin-right: 0;
    margin-left: 72px;
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

/* Print views (price offer, statements, ...) build their own printable
   content; the fixed sidebar and sticky header aren't part of that and were
   still rendering, pushing the real content down and spilling extra blank
   pages in front of it. */
@media print {
    .admin-layout {
        display: block;
        min-height: 0;
    }
    :deep(.admin-sidebar),
    :deep(.admin-header) {
        display: none !important;
    }
    .admin-main-wrapper {
        margin: 0 !important;
    }
    .admin-content {
        padding: 0;
        min-height: 0;
    }
}
</style>
