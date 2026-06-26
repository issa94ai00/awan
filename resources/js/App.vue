<template>
    <el-config-provider :locale="elementLocale">
        <router-view />
    </el-config-provider>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import ar from 'element-plus/es/locale/lang/ar';
import en from 'element-plus/es/locale/lang/en';
import { ElConfigProvider } from 'element-plus';

const { locale } = useI18n();

const elementLocale = computed(() => {
    return locale.value === 'en' ? en : ar;
});

onMounted(() => {
    console.log('Vue app mounted');
});
</script>

<style>
body {
    margin: 0;
    padding: 0;
}
</style>
