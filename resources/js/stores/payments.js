import { defineStore } from 'pinia';
import { paymentsApi } from '@/api/payments';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const usePaymentsStore = defineStore('payments', {
    state: () => ({
        payments: [],
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchPayments(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/payments' } });
                    return;
                }
                const res = await paymentsApi.getAll(params);
                const data = res.data.data;
                this.payments = data.payments || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.payments.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load payments';
                console.error('Payments load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
