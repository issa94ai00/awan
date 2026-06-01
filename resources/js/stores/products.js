import { defineStore } from 'pinia';
import { productsApi } from '@/api/products';

export const useProductsStore = defineStore('products', {
    state: () => ({
        products: [],
        currentProduct: null,
        loading: false,
        error: null,
        pagination: {
            current_page: 1,
            per_page: 10,
            total: 0
        }
    }),
    
    getters: {
        activeProducts: (state) => state.products.filter(p => p.is_active),
        featuredProducts: (state) => state.products.filter(p => p.is_featured),
        isLoading: (state) => state.loading,
        hasError: (state) => !!state.error
    },
    
    actions: {
        async fetchProducts(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const response = await productsApi.getAll(params);
                this.products = response.data.data;
                this.pagination = {
                    current_page: response.data.current_page,
                    per_page: response.data.per_page,
                    total: response.data.total
                };
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
                const response = await productsApi.getById(id);
                this.currentProduct = response.data.data;
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
                this.products.unshift(response.data.data);
                return response.data.data;
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
                const index = this.products.findIndex(p => p.id === id);
                if (index !== -1) {
                    this.products[index] = response.data.data;
                }
                return response.data.data;
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
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to delete product';
                console.error('Delete product error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        clearError() {
            this.error = null;
        }
    }
});
