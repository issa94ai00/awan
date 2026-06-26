import { defineStore } from 'pinia';
import { productsApi } from '@/api/products';
import api from '@/api/index';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useProductsStore = defineStore('products', {
    state: () => ({
        products: [],
        currentProduct: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        },
        filters: {
            search: '',
            category_id: null,
            status: '',
            featured: null,
            stock: null,
            sort_by: 'created_at',
            sort_order: 'desc'
        },
        selectedProducts: [],
        categories: []
    }),

    getters: {
        activeProducts: (state) => state.products.filter(p => p.is_active),
        featuredProducts: (state) => state.products.filter(p => p.is_featured),
        isLoading: (state) => state.loading,
        hasError: (state) => !!state.error,
        hasSelected: (state) => state.selectedProducts.length > 0,
        isAllSelected: (state) => state.products.length > 0 && state.selectedProducts.length === state.products.length
    },

    actions: {
        async fetchPublicProducts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const queryParams = {
                    page: params.page || 1,
                    per_page: params.per_page || 12,
                    search: params.search || undefined,
                    category_id: params.category_id || undefined,
                    featured: params.featured !== undefined ? params.featured : undefined,
                    in_stock: params.in_stock !== undefined ? params.in_stock : undefined,
                    sort: params.sort || undefined,
                    price_min: params.price_min || undefined,
                    price_max: params.price_max || undefined
                };

                Object.keys(queryParams).forEach(k => {
                    if (queryParams[k] === undefined || queryParams[k] === null || queryParams[k] === '') {
                        delete queryParams[k];
                    }
                });

                const response = await productsApi.getPublicAll(queryParams);
                const responseData = response.data;

                if (responseData.data) {
                    this.products = responseData.data;
                } else if (Array.isArray(responseData)) {
                    this.products = responseData;
                } else {
                    this.products = [];
                }

                if (responseData.pagination !== undefined) {
                    this.pagination = {
                        current_page: responseData.pagination.current_page,
                        last_page: responseData.pagination.last_page || 1,
                        per_page: responseData.pagination.per_page,
                        total: responseData.pagination.total
                    };
                } else if (responseData.current_page !== undefined) {
                    this.pagination = {
                        current_page: responseData.current_page,
                        last_page: responseData.last_page || 1,
                        per_page: responseData.per_page,
                        total: responseData.total
                    };
                }

                return responseData;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch public products';
                console.error('Fetch public products error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchPublicCategories() {
            try {
                const response = await api.get('/categories');
                const cats = response.data?.data || response.data || [];
                this.categories = cats.filter(c => c.id);
                return cats;
            } catch (error) {
                console.error('Failed to fetch public categories:', error);
                return [];
            }
        },

        async fetchCategories() {
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                const url = token ? '/admin/categories' : '/categories';
                const response = await api.get(url);
                const cats = response.data?.data || response.data || [];
                this.categories = cats.filter(c => c.id);
                return response;
            } catch (error) {
                console.error('Failed to fetch categories:', error);
                return { data: [] };
            }
        },

        async fetchProducts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                let response;

                const queryParams = {
                    page: params.page || this.pagination.current_page,
                    per_page: params.per_page || this.pagination.per_page,
                    search: params.search || this.filters.search || undefined,
                    category_id: params.category_id || this.filters.category_id || undefined,
                    featured: params.featured !== undefined ? params.featured : (this.filters.featured !== null ? this.filters.featured : undefined),
                    in_stock: params.stock !== undefined ? params.stock : (this.filters.stock !== null ? this.filters.stock : undefined),
                    is_active: params.is_active !== undefined ? params.is_active : undefined,
                    sort_by: params.sort_by || this.filters.sort_by,
                    sort_order: params.sort_order || this.filters.sort_order,
                    sort: params.sort || undefined,
                    price_min: params.price_min || undefined,
                    price_max: params.price_max || undefined
                };

                Object.keys(queryParams).forEach(k => {
                    if (queryParams[k] === undefined || queryParams[k] === null || queryParams[k] === '') {
                        delete queryParams[k];
                    }
                });

                if (token) {
                    response = await productsApi.getAll(queryParams);
                } else {
                    response = await productsApi.getPublicAll(queryParams);
                }

                const responseData = response.data;

                if (responseData.data) {
                    this.products = responseData.data;
                } else if (Array.isArray(responseData)) {
                    this.products = responseData;
                } else {
                    this.products = [];
                }

                if (responseData.pagination !== undefined) {
                    this.pagination = {
                        current_page: responseData.pagination.current_page,
                        last_page: responseData.pagination.last_page || 1,
                        per_page: responseData.pagination.per_page,
                        total: responseData.pagination.total
                    };
                } else if (responseData.current_page !== undefined) {
                    this.pagination = {
                        current_page: responseData.current_page,
                        last_page: responseData.last_page || 1,
                        per_page: responseData.per_page,
                        total: responseData.total
                    };
                } else {
                    this.pagination = {
                        current_page: 1,
                        last_page: 1,
                        per_page: params.per_page || this.pagination.per_page,
                        total: this.products.length
                    };
                }

                if (params.search !== undefined) this.filters.search = params.search;
                if (params.category_id !== undefined) this.filters.category_id = params.category_id;
                if (params.featured !== undefined) this.filters.featured = params.featured;
                if (params.stock !== undefined) this.filters.stock = params.stock;

                this.selectedProducts = [];
                return responseData;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch products';
                console.error('Fetch products error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchProduct(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                let response;

                if (token) {
                    response = await productsApi.getById(id);
                } else {
                    response = await productsApi.getPublicById(id);
                }

                this.currentProduct = response.data.data || response.data;
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch product';
                console.error('Fetch product error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createProduct(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await productsApi.create(data);
                const product = response.data.data;
                this.products.unshift(product);
                return product;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to create product';
                console.error('Create product error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateProduct(id, data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await productsApi.update(id, data);
                const updated = response.data.data;
                const index = this.products.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.products[index] = updated;
                }
                if (this.currentProduct?.id === id) {
                    this.currentProduct = updated;
                }
                return updated;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to update product';
                console.error('Update product error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async deleteProduct(id) {
            this.loading = true;
            this.error = null;
            try {
                await productsApi.delete(id);
                this.products = this.products.filter(p => p.id !== id);
                this.selectedProducts = this.selectedProducts.filter(s => s !== id);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to delete product';
                console.error('Delete product error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async bulkDelete(ids) {
            this.loading = true;
            this.error = null;
            try {
                const results = await Promise.allSettled(ids.map(id => productsApi.delete(id)));
                const succeeded = ids.filter((id, i) => results[i].status === 'fulfilled');
                this.products = this.products.filter(p => succeeded.includes(p.id));
                this.selectedProducts = this.selectedProducts.filter(s => !succeeded.includes(s));
                return { succeeded, failed: ids.length - succeeded.length };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to bulk delete';
                console.error('Bulk delete error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async bulkUpdateStatus(ids, isActive) {
            this.loading = true;
            this.error = null;
            try {
                const results = await Promise.allSettled(
                    ids.map(id => productsApi.update(id, { is_active: isActive }))
                );
                const succeeded = ids.filter((id, i) => results[i].status === 'fulfilled');
                this.products.forEach(p => {
                    if (succeeded.includes(p.id)) {
                        p.is_active = isActive;
                    }
                });
                this.selectedProducts = this.selectedProducts.filter(s => !succeeded.includes(s));
                return { succeeded, failed: ids.length - succeeded.length };
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to bulk update status';
                console.error('Bulk update status error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        toggleProductSelection(productId) {
            const index = this.selectedProducts.indexOf(productId);
            if (index === -1) {
                this.selectedProducts.push(productId);
            } else {
                this.selectedProducts.splice(index, 1);
            }
        },

        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedProducts = [];
            } else {
                this.selectedProducts = this.products.map(p => p.id);
            }
        },

        setPage(page) {
            this.pagination.current_page = page;
        },

        setPerPage(size) {
            this.pagination.per_page = size;
        },

        setFilters(filters) {
            this.filters = { ...this.filters, ...filters };
        },

        clearFilters() {
            this.filters = {
                search: '',
                category_id: null,
                status: '',
                featured: null,
                stock: null,
                sort_by: 'created_at',
                sort_order: 'desc'
            };
        },

        clearError() {
            this.error = null;
        }
    }
});
