import { defineStore } from 'pinia';
import { posApi } from '@/api/pos';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const usePosStore = defineStore('pos', {
    state: () => ({
        options: null,
        customers: [],
        products: [],
        loading: false,
        error: null,
    }),

    getters: {
        isLoading: (s) => s.loading,
    },

    actions: {
        async loadOptions() {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    // rely on router guard to redirect to login
                    router.push({ path: '/login', query: { redirect: '/admin/pos' } });
                    return;
                }
                const res = await posApi.options();
                this.options = res.data.data || res.data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load POS options';
                console.error('POS options error:', err);
            } finally {
                this.loading = false;
            }
        },

        async lookupProducts(q) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                const params = { q };
                if (!token) {
                    // fallback to public product lookup if implemented
                    // use existing public products endpoint
                    const res = await import('@/api/products').then(m => m.productsApi.getPublicAll({ search: q, per_page: 50 }));
                    this.products = res.data.data || res.data || [];
                    return this.products;
                }
                const res = await posApi.productLookup(params);
                this.products = res.data.data || res.data || [];
                return this.products;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Product lookup failed';
                console.error('POS product lookup error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async loadCustomers(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/pos' } });
                    return;
                }
                const res = await posApi.customers(params);
                const d = res.data.data || res.data;
                this.customers = d.customers || d || [];
                return this.customers;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load customers';
                console.error('POS customers error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        }
    }
});
