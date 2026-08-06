import { defineStore } from 'pinia';
import { purchaseRequestsApi } from '@/api/purchaseRequests';

export const usePurchaseRequestsStore = defineStore('purchaseRequests', {
    state: () => ({
        orders: [],
        selectedOrder: null,
        loading: false,
        error: null,
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    actions: {
        async fetchOrder(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await purchaseRequestsApi.get(id);
                this.selectedOrder = res.data.data;
                return this.selectedOrder;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load order';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchOrders(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await purchaseRequestsApi.getAll(params);
                const data = res.data.data;
                this.orders = data.orders || [];
                const p = data.pagination || {};
                this.pagination = {
                    current_page: p.current_page || 1,
                    per_page: p.per_page || 20,
                    total: p.total || 0
                };
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load purchase requests';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateOrderStatus(id, status) {
            this.error = null;
            try {
                await purchaseRequestsApi.updateStatus(id, status);
                const order = this.orders.find(o => o.id === id);
                if (order) {
                    order.status = status;
                }
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to update status';
                throw err;
            }
        }
    }
});
