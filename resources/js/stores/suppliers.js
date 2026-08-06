import { defineStore } from 'pinia';
import { suppliersApi } from '@/api/suppliers';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useSuppliersStore = defineStore('suppliers', {
    state: () => ({
        suppliers: [],
        currentSupplier: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 20,
            total: 0,
        },
    }),

    actions: {
        async fetchSuppliers(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/purchases/suppliers' } });
                    return;
                }
                const res = await suppliersApi.getAll(params);
                const data = res.data.data;
                this.suppliers = data.suppliers || [];
                this.pagination = {
                    current_page: data.pagination.current_page || 1,
                    per_page: data.pagination.per_page || 20,
                    total: data.pagination.total || this.suppliers.length,
                };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load suppliers';
                console.error('Supplier load error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchSupplier(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await suppliersApi.getById(id);
                this.currentSupplier = res.data.data || res.data;
                return this.currentSupplier;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load supplier';
                console.error('Supplier fetch error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
    },
});
