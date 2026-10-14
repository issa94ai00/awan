import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import * as authApi from '@/api/auth';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('token'));
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    // The ERP modules this account may open, as decided by the master user on
    // the Features Management screen. Super admins receive every enabled
    // feature from the API, so the same list drives the whole UI.
    const features = computed(() => user.value?.features || []);

    const isSuperAdmin = computed(() => !!user.value?.is_super_admin);
    const isMaster = computed(() => !!user.value?.is_master);

    // Whether the signed-in role may open a feature. A missing key (a screen
    // no module guards) is always allowed so new screens never disappear.
    // Super admins are not short-circuited: the API already hands them every
    // *enabled* feature, so a module switched off on the Features Management
    // screen disappears for them too.
    function canAccessFeature(key) {
        if (!key) return true;
        return features.value.includes(key);
    }

    async function init() {
        if (!token.value) return;
        try {
            await fetchUser();
        } catch (err) {
            console.warn('Failed to pre-fetch user in auth store initialization', err);
        }
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

    // Re-reads the signed-in user (role, feature list) in the background.
    // Unlike fetchUser, a failed request keeps the session as it is, so a
    // network blip never logs anyone out.
    async function refreshUser() {
        if (!token.value) return null;
        try {
            const res = await authApi.fetchUser();
            const fresh = res.data?.data?.user || res.data?.user || null;
            if (fresh) user.value = fresh;
        } catch {
            // Keep the current user; the next full fetch will sort it out.
        }
        return user.value;
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

    return {
        user, token, loading, error, isAuthenticated,
        features, isSuperAdmin, isMaster, canAccessFeature,
        init, fetchUser, refreshUser, login, logout,
    };
});

export default useAuthStore;
