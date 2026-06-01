import { defineStore } from 'pinia';
import api from '@/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
        loading: false,
        error: null
    }),
    
    getters: {
        isAuthenticated: (state) => !!state.token,
        userName: (state) => state.user?.name || 'المستخدم',
        userEmail: (state) => state.user?.email || ''
    },
    
    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.post('/auth/login', credentials);
                this.token = response.data.data.token;
                this.user = response.data.data.user;
                localStorage.setItem('token', response.data.data.token);
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Login failed';
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async logout() {
            this.loading = true;
            try {
                await api.post('/auth/logout');
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('token');
                this.loading = false;
                window.location.href = '/login';
            }
        },
        
        async fetchUser() {
            if (!this.token) return;
            
            this.loading = true;
            try {
                const response = await api.get('/auth/user');
                this.user = response.data.data;
            } catch (error) {
                console.error('Fetch user error:', error);
                // If unauthorized, clear token
                if (error.response?.status === 401) {
                    this.token = null;
                    this.user = null;
                    localStorage.removeItem('token');
                }
            } finally {
                this.loading = false;
            }
        },
        
        async updateProfile(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.put('/auth/profile', data);
                this.user = response.data.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Update failed';
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }
});
