import { defineStore } from 'pinia';
import { customersApi } from '@/api/customers';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useCustomersStore = defineStore('customers', {
    state: () => ({
        customers: [],
        currentCustomer: null,
        loading: false,
        error: null,
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    actions: {
        async fetchCustomers(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return;
                }

                const res = await customersApi.getAll(params);
                const data = res.data.data || res.data;

                this.customers = data.customers || [];
                const p = data.pagination || {};
                this.pagination = {
                    current_page: p.current_page || 1,
                    per_page: p.per_page || this.pagination.per_page,
                    total: p.total || this.customers.length
                };
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load customers';
                console.error('Customers load error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },
    }
});
