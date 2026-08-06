import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import router from './router';
import App from './App.vue';
import { useAuthStore } from '@/stores/auth';
import i18n from './i18n';

const app = createApp(App);
const pinia = createPinia();

// Register all Element Plus icons
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(pinia);
app.use(router);
app.use(i18n);
app.use(ElementPlus);

// Register global translation helper for JS scripts
window.t = (key) => i18n.global.t(key);

// Register global property for dynamic db translations (name_ar / name_en)
app.config.globalProperties.$p = (obj, field) => {
    if (!obj) return '';
    const currentLocale = i18n.global.locale.value;
    if (currentLocale === 'en') {
        return obj[`${field}_en`] || obj[`${field}_ar`] || obj[field] || '';
    }
    return obj[`${field}_ar`] || obj[field] || '';
};

// Handle RTL / LTR direction based on locale
const updateDirection = (locale) => {
    const isRtl = locale === 'ar';
    document.documentElement.setAttribute('lang', locale);
    document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr');

    // Manage LTR stylesheets for public pages
    let ltrLink = document.getElementById('ltr-stylesheet');
    let vueLtrLink = document.getElementById('vue-ltr-stylesheet');
    
    if (!isRtl) {
        if (!ltrLink) {
            ltrLink = document.createElement('link');
            ltrLink.id = 'ltr-stylesheet';
            ltrLink.rel = 'stylesheet';
            ltrLink.href = '/assets/css/style-ltr.css';
            document.head.appendChild(ltrLink);
        }
        if (!vueLtrLink) {
            vueLtrLink = document.createElement('link');
            vueLtrLink.id = 'vue-ltr-stylesheet';
            vueLtrLink.rel = 'stylesheet';
            vueLtrLink.href = '/assets/css/vue-custom-ltr.css';
            document.head.appendChild(vueLtrLink);
        }
    } else {
        if (ltrLink) ltrLink.remove();
        if (vueLtrLink) vueLtrLink.remove();
    }
};

// Set initial direction
updateDirection(i18n.global.locale.value);

// Initialize authentication (fetch user if token exists) before mounting
const auth = useAuthStore();
auth.init()
    .catch((err) => {
        console.warn('Auth initialization skipped/failed:', err);
    })
    .finally(() => {
        app.mount('#app');
    });

// Export updateDirection if needed elsewhere
export { updateDirection };
