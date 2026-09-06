import { defineStore } from 'pinia';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';

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
        // Counted by the API over the whole table, so a stage badge means
        // "orders waiting in this stage" rather than "…on the page in view".
        statusCounts: {
            all: 0,
            pending: 0,
            confirmed: 0,
            processing: 0,
            completed: 0,
            cancelled: 0,
        },
    }),

    actions: {
        async fetchOrders(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                if (!requireAuth(useAuthStore(), router, '/admin/purchases/orders')) return;

                const res = await purchaseOrdersApi.getAll(params);
                const data = res.data?.data || {};
                this.orders = data.orders || [];
                this.pagination = readPagination(data.pagination, this.pagination, this.orders.length);
                if (data.status_counts) this.statusCounts = data.status_counts;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load purchase orders';
                console.error('Purchase orders load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Approves, cancels, or otherwise moves one order along.
         *
         * Patches the row already on screen from the response rather than
         * refetching the page, so approving does not make the list jump or
         * lose the operator's place mid-review.
         */
        async updateStatus(id, status) {
            const res = await purchaseOrdersApi.updateStatus(id, status);
            const updated = res.data?.data;
            if (updated) {
                const index = this.orders.findIndex((order) => order.id === updated.id);
                if (index !== -1) {
                    // Read before the splice: afterwards the row already
                    // carries the new status and the shift would be a no-op.
                    const previous = this.orders[index].status;
                    this.orders.splice(index, 1, { ...this.orders[index], ...updated });
                    this.applyCountShift(previous, updated.status);
                }
            }
            return updated;
        },

        /** Keeps the stage badges honest between fetches. */
        applyCountShift(from, to) {
            const before = normalizePurchaseOrderStatus(from);
            const after = normalizePurchaseOrderStatus(to);
            if (before === after) return;
            if (this.statusCounts[before] > 0) this.statusCounts[before] -= 1;
            if (after in this.statusCounts) this.statusCounts[after] += 1;
        },
    },
});
