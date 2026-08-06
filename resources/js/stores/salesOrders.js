import { defineStore } from 'pinia';
import { salesOrdersApi } from '@/api/salesOrders';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { readPagination, requireAuth } from '@/stores/salesShared';

export const useSalesOrdersStore = defineStore('salesOrders', {
    state: () => ({
        orders: [],
        currentOrder: null,
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
        async fetchOrders(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                if (!requireAuth(useAuthStore(), router, '/admin/sales/sales-orders')) return;

                const res = await salesOrdersApi.getAll(params);
                const data = res.data?.data || {};
                this.orders = data.sales_orders || [];
                this.pagination = readPagination(data.pagination, this.pagination, this.orders.length);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load sales orders';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchOrder(id) {
            const res = await salesOrdersApi.getById(id);
            this.currentOrder = res.data?.data || res.data;
            return this.currentOrder;
        },

        async deleteOrder(id) {
            this.saving = true;
            try {
                await salesOrdersApi.delete(id);
                this.orders = this.orders.filter((order) => order.id !== id);
            } finally {
                this.saving = false;
            }
        },

        /** Creates the invoice for an order and refreshes the list linkage. */
        async convertToInvoice(id) {
            this.saving = true;
            try {
                const res = await salesOrdersApi.convertToInvoice(id);
                await this.fetchOrders().catch(() => {});
                return res.data?.data || res.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
