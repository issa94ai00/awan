<template>
    <div class="not-found-page">
        <section class="page-header">
            <div class="container">
                <h1>{{ t('page_not_found') || 'الصفحة غير موجودة' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>404</span>
                </div>
            </div>
        </section>
        <section class="not-found-content fade-up">
            <div class="container">
                <div class="error-code">404</div>
                <p>{{ t('page_not_found_desc') || 'عذراً، الصفحة التي تبحث عنها غير موجودة.' }}</p>
                <router-link to="/" class="btn btn-primary">{{ t('back_to_home') || 'العودة إلى الرئيسية' }}</router-link>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';

const settingsStore = useSettingsStore();
const { t } = useI18n();

onMounted(() => {
    settingsStore.fetch().catch((err) => console.warn(err));
    document.title = `404 - ${t('page_not_found') || 'الصفحة غير موجودة'}`;
});
</script>

<style scoped>
.not-found-page {
    padding-bottom: 5rem;
}
.not-found-content {
    text-align: center;
    padding: 4rem 0;
}
.error-code {
    font-size: 8rem;
    font-weight: 900;
    color: var(--mobile-primary);
    line-height: 1;
    margin-bottom: 1rem;
    opacity: 0.3;
}
.not-found-content p {
    font-size: 1.2rem;
    color: #64748b;
    margin-bottom: 2rem;
}
[data-theme="dark"] .not-found-content p {
    color: #94a3b8;
}
</style>
