import { defineStore } from 'pinia';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const usePurchaseReceiptsStore = defineStore('purchaseReceipts', {
    state: () => ({
        receipts: [],
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchReceipts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/purchases/receipts' } });
                    return;
                }
                const res = await purchaseReceiptsApi.getAll(params);
                const data = res.data.data;
                this.receipts = data.receipts || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.receipts.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load purchase receipts';
                console.error('Purchase receipts load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
