import { defineStore } from 'pinia';
import { inventoryApi } from '@/api/inventory';

export const useInventoryStore = defineStore('inventory', {
    state: () => ({
        summary: null,
        today: null,
        warehouses: [],
        stock: [],
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
        loading: false,
        error: null,
    }),

    actions: {
        async fetchSummary() {
            this.loading = true;
            this.error = null;
            try {
                const res = await inventoryApi.getSummary();
                const data = res.data.data || {};
                this.summary = data.summary || null;
                this.today = data.today || null;
                this.warehouses = data.warehouses || [];
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'فشل في تحميل ملخص المخزون';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchStock(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const res = await inventoryApi.getStock(params);
                const data = res.data.data || {};
                this.stock = data.stock || [];
                this.pagination = data.pagination || {
                    current_page: 1,
                    per_page: params.per_page || 20,
                    total: 0,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'فشل في تحميل المخزون';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchWarehouses() {
            this.loading = true;
            this.error = null;
            try {
                const res = await inventoryApi.getWarehouses({ per_page: 500, is_active: true });
                this.warehouses = res.data.data || [];
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'فشل في تحميل المستودعات';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createMovement(data) {
            const res = await inventoryApi.createMovement(data);
            return res.data;
        },

        async transferStock(data) {
            const res = await inventoryApi.transferStock(data);
            return res.data;
        },
    },
});
