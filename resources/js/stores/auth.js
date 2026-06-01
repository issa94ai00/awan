import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import * as authApi from '@/api/auth';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('token'));
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    async function init() {
        if (!token.value) return;
        await fetchUser();
    }

    async function fetchUser() {
        if (!token.value) {
            user.value = null;
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const res = await authApi.fetchUser();
            user.value = res.data?.data?.user || res.data?.user || null;
            if (!user.value) {
                token.value = null;
                localStorage.removeItem('token');
            }
            return user.value;
        } catch (err) {
            token.value = null;
            localStorage.removeItem('token');
            user.value = null;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function login(credentials) {
        loading.value = true;
        error.value = null;
        try {
            const res = await authApi.login(credentials);
            const receivedToken = res.data?.data?.token || res.data?.token || res.data?.access_token;
            if (!receivedToken) throw new Error('No token received');
            token.value = receivedToken;
            localStorage.setItem('token', receivedToken);
            await fetchUser();
            return user.value;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        loading.value = true;
        try {
            await authApi.logout();
        } catch (e) {
            console.error('Logout failed:', e);
        } finally {
            token.value = null;
            localStorage.removeItem('token');
            user.value = null;
            loading.value = false;
        }
    }

    return { user, token, loading, error, isAuthenticated, init, fetchUser, login, logout };
});

export default useAuthStore;
