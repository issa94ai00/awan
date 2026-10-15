<template>
    <RepDashboard v-if="isRep" />
    <Dashboard v-else />
</template>

<script setup>
// The admin landing page. A sales rep (User::isSalesScoped) gets their own
// business instead of the company overview; the server sends each only its
// own figures either way (DashboardController::stats).
import { computed, defineAsyncComponent } from 'vue';
import { useAuthStore } from '@/stores/auth';

const Dashboard = defineAsyncComponent(() => import('@/views/admin/Dashboard.vue'));
const RepDashboard = defineAsyncComponent(() => import('@/views/admin/RepDashboard.vue'));

const auth = useAuthStore();
const isRep = computed(() => !!auth.user?.is_sales_scoped);
</script>
