import { defineStore } from 'pinia';
import { paymentsApi } from '@/api/payments';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';

export const usePaymentsStore = defineStore('payments', {
    state: () => ({
        payments: [],
        loading: false,
        saving: false,
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
                if (!requireAuth(useAuthStore(), router, '/admin/sales/payments')) return;

                const res = await paymentsApi.getAll(params);
                const data = res.data?.data || {};
                this.payments = data.payments || [];
                this.pagination = readPagination(data.pagination, this.pagination, this.payments.length);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load payments';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Records a payment. The API also updates the linked invoice's
         * paid/due amounts and marks it delivered once it is settled, so
         * callers should refresh invoices afterwards.
         */
        async createPayment(payload) {
            this.saving = true;
            try {
                const res = await paymentsApi.create(payload);
                const created = res.data?.data || res.data;
                if (created) this.payments.unshift(created);
                return created;
            } finally {
                this.saving = false;
            }
        },

        async deletePayment(id) {
            this.saving = true;
            try {
                await paymentsApi.delete(id);
                this.payments = this.payments.filter((payment) => payment.id !== id);
            } finally {
                this.saving = false;
            }
        },
    },
});
