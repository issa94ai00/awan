import axios from 'axios';
import { h } from 'vue';
import { ElNotification } from 'element-plus';
import i18n from '@/i18n';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// A save can succeed with something worth a second look — a sale priced
// below cost, say. The server lists those as top-level `warnings`; they are
// shown here so every screen that saves through this client gets them.
const showWarnings = (response) => {
    const warnings = response?.data?.warnings;
    const method = String(response?.config?.method || 'get').toLowerCase();
    if (method === 'get' || !Array.isArray(warnings) || !warnings.length) return;

    ElNotification({
        type: 'warning',
        title: i18n.global.t('dq_warning_title'),
        message: h('ul', { style: 'margin:0;padding-inline-start:18px' }, warnings.map((w) => h('li', String(w)))),
        duration: 12000,
    });
};

// Response interceptor
api.interceptors.response.use(
    (response) => {
        showWarnings(response);
        return response;
    },
    (error) => {
        if (error.response?.status === 401) {
            // Handle unauthorized - clear token. Do not force a full-page redirect; let the app handle navigation.
            localStorage.removeItem('token');
            window.dispatchEvent(new Event('auth:unauthorized'));
        }
        return Promise.reject(error);
    }
);

export default api;
