import { defineStore } from 'pinia';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const usePurchaseOrdersStore = defineStore('purchaseOrders', {
    state: () => ({
        orders: [],
        currentOrder: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchOrders(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/purchases/orders' } });
                    return;
                }
                const res = await purchaseOrdersApi.getAll(params);
                const data = res.data.data;
                this.orders = data.orders || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.orders.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load purchase orders';
                console.error('Purchase orders load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
