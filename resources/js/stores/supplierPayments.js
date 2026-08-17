import { defineStore } from 'pinia';
import { supplierPaymentsApi } from '@/api/supplierPayments';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';

export const useSupplierPaymentsStore = defineStore('supplierPayments', {
    state: () => ({
        payments: [],
        outstanding: [],
        totalPaid: 0,
        totalOutstanding: 0,
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
                if (!requireAuth(useAuthStore(), router, '/admin/purchases/payments')) return;

                const res = await supplierPaymentsApi.getAll(params);
                const data = res.data?.data || {};
                this.payments = data.payments || [];
                this.totalPaid = Number(data.total_paid || 0);
                this.pagination = readPagination(data.pagination, this.pagination, this.payments.length);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load supplier payments';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchOutstanding(params = {}) {
            try {
                const res = await supplierPaymentsApi.outstanding(params);
                const data = res.data?.data || {};
                this.outstanding = data.suppliers || [];
                this.totalOutstanding = Number(data.total_outstanding || 0);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load supplier balances';
                throw error;
            }
        },

        /**
         * Records a payment. The API moves the supplier balance and the ledger
         * with it, so both this list and the outstanding balances are stale
         * afterwards and are refreshed by the caller.
         */
        async createPayment(payload) {
            this.saving = true;
            try {
                const res = await supplierPaymentsApi.create(payload);
                return res.data?.data;
            } finally {
                this.saving = false;
            }
        },

        async cancelPayment(id) {
            this.saving = true;
            try {
                await supplierPaymentsApi.cancel(id);
                this.payments = this.payments.filter((payment) => payment.id !== id);
            } finally {
                this.saving = false;
            }
        },
    },
});
