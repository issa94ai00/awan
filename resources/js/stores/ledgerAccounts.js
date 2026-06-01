import { defineStore } from 'pinia';
import { ledgerAccountsApi } from '@/api/ledgerAccounts';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useLedgerAccountsStore = defineStore('ledgerAccounts', {
    state: () => ({
        accounts: [],
        currentAccount: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchAccounts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/accounting/ledger' } });
                    return;
                }
                const res = await ledgerAccountsApi.getAll(params);
                const data = res.data.data;
                this.accounts = data.accounts || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.accounts.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load ledger accounts';
                console.error('Ledger accounts error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
