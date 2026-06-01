import { defineStore } from 'pinia';
import { stockMovementsApi } from '@/api/stockMovements';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useStockMovementsStore = defineStore('stockMovements', {
    state: () => ({
        movements: [],
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchMovements(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/inventory/movements' } });
                    return;
                }
                const res = await stockMovementsApi.getAll(params);
                const data = res.data.data;
                this.movements = data.movements || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.movements.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load stock movements';
                console.error('Stock movements error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
