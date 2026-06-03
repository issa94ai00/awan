import { defineStore } from 'pinia';
import { customersApi } from '@/api/customers';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useCustomersStore = defineStore('customers', {
    state: () => ({
        customers: [],
        currentCustomer: null,
        loading: false,
        error: null,
        validationErrors: {},
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    actions: {
        async fetchCustomers(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return;
                }

                const res = await customersApi.getAll(params);
                const data = res.data.data || res.data;

                this.customers = data.customers || [];
                const p = data.pagination || {};
                this.pagination = {
                    current_page: p.current_page || 1,
                    per_page: p.per_page || this.pagination.per_page,
                    total: p.total || this.customers.length
                };
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load customers';
                console.error('Customers load error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchCustomer(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return null;
                }

                const res = await customersApi.getById(id);
                const data = res.data?.data || res.data || null;
                this.currentCustomer = data;
                return data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load customer';
                console.error('Customer load error:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createCustomer(payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return null;
                }

                const res = await customersApi.store(payload);
                const customer = res.data?.data || res.data;
                if (customer) {
                    this.customers.unshift(customer);
                    this.pagination.total += 1;
                }
                return customer;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }
                this.error = err.response?.data?.message || err.message || 'Failed to create customer';
                console.error('Create customer error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateCustomer(id, payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return null;
                }

                const res = await customersApi.update(id, payload);
                const updated = res.data?.data || res.data;
                if (updated) {
                    this.customers = this.customers.map((customer) =>
                        customer.id === id ? { ...customer, ...updated } : customer
                    );
                }
                return updated;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }
                this.error = err.response?.data?.message || err.message || 'Failed to update customer';
                console.error('Update customer error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteCustomer(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/sales/customers' } });
                    return;
                }

                await customersApi.delete(id);
                this.customers = this.customers.filter((customer) => customer.id !== id);
                this.pagination.total -= 1;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to delete customer';
                console.error('Delete customer error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        }
    }
});
