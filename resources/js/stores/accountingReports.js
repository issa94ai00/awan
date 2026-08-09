import { defineStore } from 'pinia';
import { accountingReportsApi } from '@/api/accountingReports';

export const useAccountingReportsStore = defineStore('accountingReports', {
    state: () => ({
        trialBalance: null,
        incomeStatement: null,
        balanceSheet: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchTrialBalance() {
            this.loading = true;
            this.error = null;
            try {
                const res = await accountingReportsApi.trialBalance();
                this.trialBalance = res.data.data || res.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load trial balance';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchIncomeStatement(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await accountingReportsApi.incomeStatement(params);
                this.incomeStatement = res.data.data || res.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load income statement';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Cross-module consistency report. Kept out of the shared `loading`
         * flag so a slow health scan never blanks the figures beside it.
         */
        async fetchSystemHealth() {
            const res = await accountingReportsApi.systemHealth();

            return res.data.data || res.data;
        },

        async fetchBalanceSheet() {
            this.loading = true;
            this.error = null;
            try {
                const res = await accountingReportsApi.balanceSheet();
                this.balanceSheet = res.data.data || res.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load balance sheet';
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
