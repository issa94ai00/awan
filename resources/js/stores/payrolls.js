import { defineStore } from 'pinia';
import { payrollsApi } from '@/api/payrolls';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const usePayrollsStore = defineStore('payrolls', {
    state: () => ({
        payrolls: [],
        current: null,
        loading: false,
        error: null,
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    getters: {
        isLoading: s => s.loading
    },

    actions: {
        async fetchPayrolls(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = auth.token;
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/payrolls' } });
                    return;
                }
                const res = await payrollsApi.getAll(params);
                const d = res.data.data || res.data;
                this.payrolls = d.payrolls || d || [];
                const p = d.pagination || {};
                this.pagination = {
                    current_page: p.current_page || 1,
                    per_page: p.per_page || this.pagination.per_page,
                    total: p.total || this.payrolls.length
                };
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to fetch payrolls';
                console.error('Payrolls error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async createPayroll(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await payrollsApi.create(payload);
                const created = res.data?.data || res.data;
                await this.fetchPayrolls();
                return created;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to create payroll';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deletePayroll(id) {
            this.loading = true;
            this.error = null;
            try {
                await payrollsApi.delete(id);
                this.payrolls = this.payrolls.filter(p => p.id !== id);
                if (this.pagination.total > 0) this.pagination.total--;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to delete payroll';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async autoGenerate(payload) {
            this.loading = true;
            this.error = null;
            try {
                const res = await payrollsApi.autoGenerate(payload);
                await this.fetchPayrolls();
                return res.data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to auto-generate payrolls';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updatePayroll(id, payload) {
            this.error = null;
            try {
                const res = await payrollsApi.update(id, payload);
                return res.data?.data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to update payroll';
                throw err;
            }
        }
    }
});
