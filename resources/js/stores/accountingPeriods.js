import { defineStore } from 'pinia';
import { accountingPeriodsApi } from '@/api/accountingPeriods';

export const useAccountingPeriodsStore = defineStore('accountingPeriods', {
    state: () => ({
        periods: [],
        todayIsClosed: false,
        loading: false,
        saving: false,
        error: null,
    }),

    actions: {
        async fetchPeriods(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await accountingPeriodsApi.getAll(params);
                const data = res.data?.data || {};
                this.periods = data.periods || [];
                this.todayIsClosed = Boolean(data.today_is_closed);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load accounting periods';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createPeriod(payload) {
            this.saving = true;
            try {
                await accountingPeriodsApi.create(payload);
                await this.fetchPeriods();
            } finally {
                this.saving = false;
            }
        },

        /**
         * Closes or reopens a period. Both refetch rather than patching the row
         * locally: the server refuses a close while the period still holds an
         * unbalanced entry, so what the list shows must come from what actually
         * happened.
         */
        async setClosed(id, closed, payload = {}) {
            this.saving = true;
            try {
                if (closed) {
                    await accountingPeriodsApi.close(id, payload);
                } else {
                    await accountingPeriodsApi.reopen(id, payload);
                }
                await this.fetchPeriods();
            } finally {
                this.saving = false;
            }
        },

        async removePeriod(id) {
            this.saving = true;
            try {
                await accountingPeriodsApi.remove(id);
                await this.fetchPeriods();
            } finally {
                this.saving = false;
            }
        },
    },
});
