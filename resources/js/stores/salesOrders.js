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
        // Counted by the API across the whole table, so the stage badges mean
        // "orders in this stage" rather than "orders in this stage on this page".
        statusCounts: {
            all: 0,
            pending: 0,
            confirmed: 0,
            processing: 0,
            shipped: 0,
            delivered: 0,
            cancelled: 0,
            overdue: 0,
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
                if (data.status_counts) this.statusCounts = data.status_counts;
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

        /** Returns the order's invoice, raising it if confirmation had not. */
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

        /**
         * The detail screen's whole payload: the order, the invoice, payments,
         * journal entries, stock movements and the diagnosis of what is missing.
         * One request rather than four, so the screen can never show a half-
         * refreshed mixture of old and new after a stage move.
         */
        async fetchDetail(id) {
            const res = await salesOrdersApi.detail(id);
            const data = res.data?.data || {};
            this.applyOrderUpdate(data.sales_order);
            this.currentOrder = data.sales_order || this.currentOrder;

            return data;
        },

        /** Warehouse coverage for the routing panel. Read-only, changes nothing. */
        async fetchRouting(id) {
            const res = await salesOrdersApi.routing(id);
            return res.data?.data || {};
        },

        /**
         * Every stage move goes through here so the list, the open drawer and
         * the freshly returned invoice/movements/entries all stay in step —
         * a stage move changes far more than the order row.
         */
        async moveToStage(id, status, payload = {}) {
            this.saving = true;
            try {
                const res = status === 'confirmed' && !Object.keys(payload).length
                    ? await salesOrdersApi.confirm(id)
                    : await salesOrdersApi.transition(id, { status, ...payload });

                const data = res.data?.data || {};
                this.applyOrderUpdate(data.sales_order);

                return { ...data, message: res.data?.message };
            } finally {
                this.saving = false;
            }
        },

        async changeFulfillmentType(id, payload) {
            this.saving = true;
            try {
                const res = await salesOrdersApi.changeFulfillmentType(id, payload);
                const data = res.data?.data || {};
                this.applyOrderUpdate(data.sales_order);

                return { ...data, message: res.data?.message };
            } finally {
                this.saving = false;
            }
        },

        /** Patches an order into the loaded list without a full refetch. */
        applyOrderUpdate(order) {
            if (!order?.id) return;

            const index = this.orders.findIndex((row) => row.id === order.id);
            if (index !== -1) this.orders.splice(index, 1, { ...this.orders[index], ...order });

            if (this.currentOrder?.id === order.id) {
                this.currentOrder = { ...this.currentOrder, ...order };
            }
        },
    },
});
