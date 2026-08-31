import { defineStore } from 'pinia';
import { employeeCommissionsApi } from '@/api/employeeCommissions';

export const useEmployeeCommissionsStore = defineStore('employeeCommissions', {
    state: () => ({
        records: [],
        loading: false,
        error: null
    }),

    actions: {
        async fetchRecords(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await employeeCommissionsApi.getAll(params);
                this.records = res.data.data || [];
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to fetch commission records';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async calculateSales(payload) {
            const res = await employeeCommissionsApi.calculateSales(payload);
            return res.data.data;
        },

        async saveRecord(payload) {
            const res = await employeeCommissionsApi.create(payload);
            return res.data.data;
        },

        async updateRecord(id, payload) {
            const res = await employeeCommissionsApi.update(id, payload);
            return res.data.data;
        },

        async deleteRecord(id) {
            await employeeCommissionsApi.delete(id);
        },

        async fetchTrashed(params = {}) {
            const res = await employeeCommissionsApi.getTrashed(params);
            return res.data.data || [];
        },

        async restoreRecord(id) {
            const res = await employeeCommissionsApi.restore(id);
            return res.data.data;
        },

        async fetchWithdrawals(commissionId) {
            const res = await employeeCommissionsApi.getWithdrawals(commissionId);
            return res.data.data;
        },

        async createWithdrawal(commissionId, payload) {
            const res = await employeeCommissionsApi.createWithdrawal(commissionId, payload);
            return res.data.data;
        },

        async updateWithdrawal(commissionId, withdrawalId, payload) {
            const res = await employeeCommissionsApi.updateWithdrawal(commissionId, withdrawalId, payload);
            return res.data.data;
        },

        async deleteWithdrawal(commissionId, withdrawalId) {
            await employeeCommissionsApi.deleteWithdrawal(commissionId, withdrawalId);
        },

        async fetchTrashedWithdrawals(commissionId) {
            const res = await employeeCommissionsApi.getTrashedWithdrawals(commissionId);
            return res.data.data || [];
        },

        async restoreWithdrawal(commissionId, withdrawalId) {
            const res = await employeeCommissionsApi.restoreWithdrawal(commissionId, withdrawalId);
            return res.data.data;
        }
    }
});
