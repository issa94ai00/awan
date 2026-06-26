import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import customerApi from '@/api/customerIndex';

export const useCustomerAuthStore = defineStore('customerAuth', () => {
    const customer = ref(null);
    const token = ref(localStorage.getItem('customer_token') || null);
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => !!token.value && !!customer.value);

    // Initialize customer auth from local storage
    async function init() {
        const storedInfo = localStorage.getItem('customer_info');
        if (storedInfo) {
            try {
                customer.value = JSON.parse(storedInfo);
            } catch (e) {
                console.error('Failed to parse customer_info from localStorage, clearing...', e);
                clearSession();
            }
        }
        if (token.value) {
            try {
                await fetchUser();
            } catch (err) {
                console.warn('Customer session validation failed', err);
            }
        }
    }

    // Fetch authenticated customer profile
    async function fetchUser() {
        if (!token.value) return null;
        loading.value = true;
        try {
            const res = await customerApi.get('/customer/auth/user');
            if (res.data?.success) {
                customer.value = res.data.data;
                localStorage.setItem('customer_info', JSON.stringify(res.data.data));
            } else {
                clearSession();
            }
            return customer.value;
        } catch (err) {
            clearSession();
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Customer Login
    async function login(credentials) {
        loading.value = true;
        error.value = null;
        try {
            const res = await customerApi.post('/customer/auth/login', credentials);
            if (res.data?.success) {
                const receivedToken = res.data.data.token;
                const receivedCustomer = res.data.data.customer;
                token.value = receivedToken;
                customer.value = receivedCustomer;
                localStorage.setItem('customer_token', receivedToken);
                localStorage.setItem('customer_info', JSON.stringify(receivedCustomer));
                return res.data;
            } else {
                throw new Error(res.data?.message || 'Login failed');
            }
        } catch (err) {
            error.value = err.response?.data?.message || err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Customer Registration
    async function register(details) {
        loading.value = true;
        error.value = null;
        try {
            const res = await customerApi.post('/customer/auth/register', details);
            if (res.data?.success) {
                const receivedToken = res.data.data.token;
                const receivedCustomer = res.data.data.customer;
                token.value = receivedToken;
                customer.value = receivedCustomer;
                localStorage.setItem('customer_token', receivedToken);
                localStorage.setItem('customer_info', JSON.stringify(receivedCustomer));
                return res.data;
            } else {
                throw new Error(res.data?.message || 'Registration failed');
            }
        } catch (err) {
            error.value = err.response?.data?.message || err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Customer Logout
    async function logout() {
        loading.value = true;
        try {
            await customerApi.post('/customer/auth/logout');
        } catch (e) {
            console.error('Customer backend logout failed', e);
        } finally {
            clearSession();
            loading.value = false;
        }
    }

    function clearSession() {
        token.value = null;
        customer.value = null;
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_info');
    }

    return {
        customer,
        token,
        loading,
        error,
        isAuthenticated,
        init,
        fetchUser,
        login,
        register,
        logout,
        clearSession
    };
});

export default useCustomerAuthStore;
