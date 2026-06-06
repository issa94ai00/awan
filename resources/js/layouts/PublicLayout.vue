<template>
    <div class="public-layout min-h-screen bg-gray-100 font-sans antialiased">
        <PublicNavbar />
        <main class="main-content">
            <router-view />
        </main>
        <PublicFooter />
        <FloatButtons />
    </div>
</template>

<script setup>
import PublicNavbar from '@/components/public/PublicNavbar.vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import FloatButtons from '@/components/public/FloatButtons.vue';
</script>

<style scoped>
.public-layout {
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1;
}
</style>
