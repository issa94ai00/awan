import { defineStore } from 'pinia';
import { categoriesApi } from '@/api/categories';
import api from '@/api';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

export const useCategoriesStore = defineStore('categories', {
    state: () => ({
        categories: [],
        currentCategory: null,
        loading: false,
        error: null
    }),
    
    getters: {
        activeCategories: (state) => state.categories.filter(c => c.is_active),
        isLoading: (state) => state.loading,
        hasError: (state) => !!state.error
    },
    
    actions: {
        async fetchCategories(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                let response;

                // If user is authenticated use admin endpoint, otherwise use public categories
                if (token) {
                    response = await categoriesApi.getAll(params);
                } else {
                    response = await api.get('/categories', { params });
                }

                this.categories = response.data.data || response.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch categories';
                console.error('Fetch categories error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async fetchCategory(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await categoriesApi.getById(id);
                this.currentCategory = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to fetch category';
                console.error('Fetch category error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async createCategory(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await categoriesApi.create(data);
                this.categories.unshift(response.data.data);
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to create category';
                console.error('Create category error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async updateCategory(id, data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await categoriesApi.update(id, data);
                const index = this.categories.findIndex(c => c.id === id);
                if (index !== -1) {
                    this.categories[index] = response.data.data;
                }
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to update category';
                console.error('Update category error:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async deleteCategory(id) {
            this.loading = true;
            this.error = null;
            try {
                await categoriesApi.delete(id);
                this.categories = this.categories.filter(c => c.id !== id);
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to delete category';
                console.error('Delete category error:', error);
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
