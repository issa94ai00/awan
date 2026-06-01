import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import router from './router';
import App from './App.vue';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App);
const pinia = createPinia();

// Register all Element Plus icons
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(pinia);
app.use(router);
app.use(ElementPlus);

// Initialize authentication (fetch user if token exists) before mounting
const auth = useAuthStore();
auth.init().finally(() => {
    app.mount('#app');
});
