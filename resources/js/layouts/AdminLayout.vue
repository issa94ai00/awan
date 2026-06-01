<template>
    <div class="admin-layout">
        <AdminSidebar v-model:collapsed="sidebarCollapsed" />
        <div class="admin-main-wrapper" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
            <AdminHeader />
            <div class="admin-content">
                <router-view />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AdminHeader from '@/components/admin/AdminHeader.vue';

const sidebarCollapsed = ref(false);

onMounted(() => {
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState) {
        sidebarCollapsed.value = JSON.parse(savedState);
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
}

.admin-main-wrapper.sidebar-collapsed {
    margin-right: 70px;
}

.admin-content {
    padding: 2rem;
    min-height: calc(100vh - 70px);
}

@media (max-width: 992px) {
    .admin-main-wrapper {
        margin-right: 0;
    }
}
</style>
