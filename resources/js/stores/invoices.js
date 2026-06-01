import { defineStore } from 'pinia';
import { invoicesApi } from '@/api/invoices';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useInvoicesStore = defineStore('invoices', {
    state: () => ({
        invoices: [],
        currentInvoice: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 15,
            total: 0,
        }
    }),

    getters: {
        isLoading: (state) => state.loading,
        hasError: (state) => !!state.error
    },

    actions: {
        async fetchInvoices(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    // Not authenticated — route to SPA login
                    router.push({ path: '/login', query: { redirect: '/admin/sales/invoices' } });
                    return;
                }

                const response = await invoicesApi.getAll(params);
                const data = response.data;

                // Some endpoints return data structure with invoices/pagination
                if (data.data && data.data.invoices) {
                    this.invoices = data.data.invoices;
                    const p = data.data.pagination || {};
                    this.pagination = {
                        current_page: p.current_page || 1,
                        per_page: p.per_page || this.pagination.per_page,
                        total: p.total || (this.invoices.length || 0)
                    };
                } else if (Array.isArray(data.data)) {
                    this.invoices = data.data;
                } else {
                    this.invoices = data.invoices || data.data || [];
                }

            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch invoices';
                console.error('Fetch invoices error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchInvoice(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: `/admin/sales/invoices` } });
                    return;
                }

                const response = await invoicesApi.getById(id);
                this.currentInvoice = response.data.data || response.data;
                return this.currentInvoice;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch invoice';
                console.error('Fetch invoice error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createInvoice(payload) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/invoices' } });
                    return;
                }

                const response = await invoicesApi.create(payload);
                // prepend created invoice
                const created = response.data.data || response.data;
                if (created) this.invoices.unshift(created);
                return created;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to create invoice';
                console.error('Create invoice error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateInvoiceStatus(id, status) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/invoices' } });
                    return;
                }

                const response = await invoicesApi.updateStatus(id, { status });
                const updated = response.data.data || response.data;
                const idx = this.invoices.findIndex(i => i.id === id);
                if (idx !== -1) this.invoices[idx] = updated;
                return updated;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to update invoice';
                console.error('Update invoice error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async deleteInvoice(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/invoices' } });
                    return;
                }

                await invoicesApi.delete(id);
                this.invoices = this.invoices.filter(i => i.id !== id);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to delete invoice';
                console.error('Delete invoice error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
});
