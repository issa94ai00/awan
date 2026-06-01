import { defineStore } from 'pinia';
import { quotesApi } from '@/api/quotes';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useQuotesStore = defineStore('quotes', {
    state: () => ({
        quotes: [],
        currentQuote: null,
        loading: false,
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
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/quotes' } });
                    return;
                }
                const res = await quotesApi.getAll(params);
                const data = res.data.data;
                this.quotes = data.quotes || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.quotes.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load quotes';
                console.error('Quotes load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
