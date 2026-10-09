import { defineStore } from 'pinia';
import { supplierPaymentsApi } from '@/api/supplierPayments';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';

// Numbers each list request, so a slow earlier response can't land after a
// later one and put stale rows on screen.
let fetchSeq = 0;

export const useSupplierPaymentsStore = defineStore('supplierPayments', {
    state: () => ({
        payments: [],
        outstanding: [],
        // Totals for whatever the list is filtered to.
        totalPaid: 0,
        byMethod: {},
        // Debt and advances are kept apart: a supplier paid ahead does not
        // make what is owed to the others any smaller.
        totalOutstanding: 0,
        owedCount: 0,
        totalAdvances: 0,
        advancesCount: 0,
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
            const seq = ++fetchSeq;
            this.loading = true;
            this.error = null;
            try {
                if (!requireAuth(useAuthStore(), router, '/admin/purchases/payments')) return;

                const res = await supplierPaymentsApi.getAll(params);
                if (seq !== fetchSeq) return;
                const data = res.data?.data || {};
                this.payments = data.payments || [];
                this.totalPaid = Number(data.total_paid || 0);
                // An empty group comes back as [] from PHP, not {}.
                this.byMethod = Array.isArray(data.by_method) ? {} : (data.by_method || {});
                this.pagination = readPagination(data.pagination, this.pagination, this.payments.length);
            } catch (error) {
                if (seq !== fetchSeq) return;
                this.error = error.response?.data?.message || error.message || 'Failed to load supplier payments';
                throw error;
            } finally {
                if (seq === fetchSeq) this.loading = false;
            }
        },

        async fetchOutstanding(params = {}) {
            try {
                const res = await supplierPaymentsApi.outstanding(params);
                const data = res.data?.data || {};
                this.outstanding = data.suppliers || [];
                this.totalOutstanding = Number(data.total_outstanding || 0);
                this.owedCount = Number(data.owed_count || 0);
                this.totalAdvances = Number(data.total_advances || 0);
                this.advancesCount = Number(data.advances_count || 0);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load supplier balances';
                throw error;
            }
        },

        /**
         * Records a payment. The API moves the supplier balance and the ledger
         * with it, so both this list and the outstanding balances are stale
         * afterwards and are refreshed by the caller.
         *
         * @returns {Promise<{payment: object, supplierBalance: number|undefined}>}
         */
        async createPayment(payload) {
            this.saving = true;
            try {
                const res = await supplierPaymentsApi.create(payload);
                return { payment: res.data?.data, supplierBalance: res.data?.supplier_balance };
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
