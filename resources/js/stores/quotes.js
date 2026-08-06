import { defineStore } from 'pinia';
import { quotesApi } from '@/api/quotes';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';

export const useQuotesStore = defineStore('quotes', {
    state: () => ({
        quotes: [],
        currentQuote: null,
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
        async fetchQuotes(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                if (!requireAuth(useAuthStore(), router, '/admin/sales/quotes')) return;

                const res = await quotesApi.getAll(params);
                const data = res.data?.data || {};
                this.quotes = data.quotes || [];
                this.pagination = readPagination(data.pagination, this.pagination, this.quotes.length);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load quotes';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchQuote(id) {
            const res = await quotesApi.getById(id);
            this.currentQuote = res.data?.data || res.data;
            return this.currentQuote;
        },

        async createQuote(payload) {
            this.saving = true;
            try {
                const res = await quotesApi.create(payload);
                const created = res.data?.data || res.data;
                if (created) this.quotes.unshift(created);
                return created;
            } finally {
                this.saving = false;
            }
        },

        async updateQuote(id, payload) {
            this.saving = true;
            try {
                const res = await quotesApi.update(id, payload);
                const updated = res.data?.data || res.data;
                this.replaceInList(updated);
                return updated;
            } finally {
                this.saving = false;
            }
        },

        /**
         * Status-only change. Uses the dedicated /status endpoint — the full
         * update endpoint requires the whole items array and rewrites every
         * line, so it must not be used for a workflow transition.
         */
        async updateQuoteStatus(quote, status) {
            this.saving = true;
            try {
                const res = await quotesApi.updateStatus(quote.id, status);
                const updated = res.data?.data || res.data;
                this.replaceInList(updated);
                return updated;
            } finally {
                this.saving = false;
            }
        },

        async deleteQuote(id) {
            this.saving = true;
            try {
                await quotesApi.delete(id);
                this.quotes = this.quotes.filter((quote) => quote.id !== id);
            } finally {
                this.saving = false;
            }
        },

        /**
         * Turns an accepted quote into a sales order. The API rejects anything
         * that is not in the `accepted` state, so callers should gate on that.
         */
        async convertToSalesOrder(id) {
            this.saving = true;
            try {
                const res = await quotesApi.convertToSalesOrder(id);
                // The quote's own status is unchanged server-side, but the list
                // needs refreshing so the new linkage shows up.
                await this.fetchQuotes().catch(() => {});
                return res.data?.data || res.data;
            } finally {
                this.saving = false;
            }
        },

        replaceInList(updated) {
            if (!updated?.id) return;
            const index = this.quotes.findIndex((quote) => quote.id === updated.id);
            if (index !== -1) this.quotes[index] = updated;
        },
    },
});
