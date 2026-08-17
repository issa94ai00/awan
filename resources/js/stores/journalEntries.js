import { defineStore } from 'pinia';
import { journalEntriesApi } from '@/api/journalEntries';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useJournalEntriesStore = defineStore('journalEntries', {
    state: () => ({
        entries: [],
        currentEntry: null,
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

        async fetchEntry(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await journalEntriesApi.get(id);
                this.currentEntry = res.data.data;
                return this.currentEntry;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load journal entry';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createEntry(payload) {
            this.error = null;
            try {
                const res = await journalEntriesApi.create(payload);
                return res.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to create journal entry';
                throw error;
            }
        },

        /**
         * Posts a mirror entry that cancels an existing one.
         *
         * This replaces the old update and delete actions. Both are refused by
         * the API now: a posted entry that can be rewritten makes two trial
         * balances of the same period disagree with nothing to explain why.
         */
        async reverseEntry(id, payload = {}) {
            this.error = null;
            try {
                const res = await journalEntriesApi.reverse(id, payload);
                return res.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to reverse journal entry';
                throw error;
            }
        },
    },
});
