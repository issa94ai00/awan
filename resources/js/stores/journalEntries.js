import { defineStore } from 'pinia';
import { journalEntriesApi } from '@/api/journalEntries';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useJournalEntriesStore = defineStore('journalEntries', {
    state: () => ({
        entries: [],
        trialBalance: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchEntries(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/accounting/journal' } });
                    return;
                }
                const res = await journalEntriesApi.getAll(params);
                const data = res.data.data;
                this.entries = data.entries || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.entries.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load journal entries';
                console.error('Journal entries error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchTrialBalance(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/accounting/trial-balance' } });
                    return;
                }
                const res = await journalEntriesApi.trialBalance(params);
                this.trialBalance = res.data.data || res.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load trial balance';
                console.error('Trial balance error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    },
});
